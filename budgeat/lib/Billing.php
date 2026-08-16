<?php
declare(strict_types=1);

require_once __DIR__ . '/App.php';

/**
 * Encaissement via Stripe Checkout, sans SDK (un simple appel HTTP).
 *
 * Si aucune clé Stripe n'est configurée, le site bascule en MODE DÉMO :
 * le parcours de paiement fonctionne de bout en bout mais rien n'est débité.
 * Cela permet de tester le tunnel avant d'ouvrir un compte Stripe.
 */
final class Billing
{
    public function __construct(private App $app) {}

    public function isLive(): bool
    {
        return !empty($this->app->config['stripe']['secret_key']);
    }

    public function plans(): array
    {
        return $this->app->config['plans'];
    }

    /**
     * Crée une session de paiement et renvoie l'URL vers laquelle rediriger.
     * @param string $kind monthly | yearly | lifetime
     */
    public function checkout(string $kind): array
    {
        $plans = $this->plans();
        if (!isset($plans[$kind])) {
            return ['ok' => false, 'error' => 'Formule inconnue.'];
        }
        $user = $this->app->user();
        if ($user === null) {
            return ['ok' => false, 'error' => 'Connectez-vous pour souscrire.'];
        }

        $plan = $plans[$kind];
        $recurring = $kind !== 'lifetime';

        if (!$this->isLive()) {
            // Mode démo : on enregistre un paiement en attente et on renvoie
            // vers la page de confirmation locale.
            $ref = 'demo_' . bin2hex(random_bytes(6));
            $this->recordPayment($user['id'], 'demo', $ref, $kind, $plan['price'], 'pending');
            return ['ok' => true, 'url' => $this->app->url('/api/checkout.php?confirm=' . $ref), 'demo' => true];
        }

        $params = [
            'mode' => $recurring ? 'subscription' : 'payment',
            'success_url' => $this->app->url('/compte.php?paiement=ok&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url'  => $this->app->url('/tarifs.php?paiement=annule'),
            'customer_email' => $user['email'],
            'client_reference_id' => (string) $user['id'],
            'metadata' => ['user_id' => (string) $user['id'], 'kind' => $kind],
            'locale' => 'fr',
        ];

        $priceId = $this->app->config['stripe']['price_' . $kind] ?? '';
        if ($priceId !== '') {
            $params['line_items'] = [['price' => $priceId, 'quantity' => 1]];
        } else {
            $params['line_items'] = [[
                'quantity' => 1,
                'price_data' => array_filter([
                    'currency' => $plan['currency'],
                    'unit_amount' => $plan['price'],
                    'recurring' => $recurring
                        ? ['interval' => $kind === 'yearly' ? 'year' : 'month']
                        : null,
                    'product_data' => ['name' => $this->app->config['app_name'] . ' — ' . $plan['label']],
                ]),
            ]];
        }

        $res = $this->stripe('POST', '/v1/checkout/sessions', $params);
        if (!isset($res['url'])) {
            return ['ok' => false, 'error' => $res['error']['message'] ?? 'Stripe a refusé la demande.'];
        }

        $this->recordPayment($user['id'], 'stripe', $res['id'], $kind, $plan['price'], 'pending');
        return ['ok' => true, 'url' => $res['url']];
    }

    /** Confirme un paiement de démonstration. */
    public function confirmDemo(string $reference): bool
    {
        $stmt = $this->app->db->prepare('SELECT * FROM payments WHERE reference = ? AND provider = "demo"');
        $stmt->execute([$reference]);
        $payment = $stmt->fetch();
        if (!$payment || $payment['status'] === 'paid') {
            return false;
        }
        $this->activate((int) $payment['user_id'], $payment['kind'], $reference);
        return true;
    }

    /**
     * Traite un événement de webhook Stripe (checkout.session.completed,
     * invoice.paid, customer.subscription.deleted).
     */
    public function handleWebhook(string $payload, ?string $signature): array
    {
        $secret = $this->app->config['stripe']['webhook_secret'] ?? '';
        if ($secret !== '' && !$this->verifySignature($payload, $signature, $secret)) {
            return ['ok' => false, 'error' => 'signature invalide'];
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                $userId = (int) ($object['metadata']['user_id'] ?? $object['client_reference_id'] ?? 0);
                $kind = $object['metadata']['kind'] ?? 'monthly';
                if ($userId > 0) {
                    $this->activate($userId, $kind, $object['id'] ?? null, $object['customer'] ?? null, $object['subscription'] ?? null);
                }
                break;

            case 'invoice.paid':
                // Renouvellement d'abonnement : on repousse la date d'échéance.
                $customer = $object['customer'] ?? null;
                if ($customer) {
                    $stmt = $this->app->db->prepare('SELECT id, plan FROM users WHERE stripe_customer = ?');
                    $stmt->execute([$customer]);
                    if ($user = $stmt->fetch()) {
                        $this->extend((int) $user['id'], $user['plan'] === 'yearly' ? 'yearly' : 'monthly');
                    }
                }
                break;

            case 'customer.subscription.deleted':
                $customer = $object['customer'] ?? null;
                if ($customer) {
                    $stmt = $this->app->db->prepare(
                        'UPDATE users SET plan = "free" WHERE stripe_customer = ? AND plan != "lifetime"'
                    );
                    $stmt->execute([$customer]);
                }
                break;
        }

        return ['ok' => true, 'handled' => $type];
    }

    /** Active l'accès payant et récompense éventuellement le parrain. */
    public function activate(int $userId, string $kind, ?string $reference = null, ?string $customer = null, ?string $subscription = null): void
    {
        if ($kind === 'lifetime') {
            $stmt = $this->app->db->prepare(
                'UPDATE users SET plan = "lifetime", plan_until = NULL, stripe_customer = COALESCE(?, stripe_customer) WHERE id = ?'
            );
            $stmt->execute([$customer, $userId]);
        } else {
            $interval = $kind === 'yearly' ? '+1 year' : '+1 month';
            $stmt = $this->app->db->prepare(
                'UPDATE users SET plan = ?, stripe_customer = COALESCE(?, stripe_customer),
                        stripe_sub = COALESCE(?, stripe_sub),
                        plan_until = datetime(CASE WHEN plan_until > datetime("now") THEN plan_until ELSE datetime("now") END, ?)
                 WHERE id = ?'
            );
            $stmt->execute([$kind, $customer, $subscription, $interval, $userId]);
        }

        if ($reference !== null) {
            $stmt = $this->app->db->prepare('UPDATE payments SET status = "paid" WHERE reference = ?');
            $stmt->execute([$reference]);
        }

        $this->rewardSponsor($userId);
    }

    /**
     * Résiliation : l'accès reste actif jusqu'au terme déjà payé, seul le
     * renouvellement est coupé. C'est le comportement attendu par la loi et
     * par les clients.
     */
    public function cancel(int $userId): array
    {
        $stmt = $this->app->db->prepare('SELECT plan, stripe_sub FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['ok' => false, 'error' => 'Compte introuvable.'];
        }
        if ($user['plan'] === 'lifetime') {
            return ['ok' => false, 'error' => "L'accès à vie n'a pas de renouvellement à résilier."];
        }

        if ($this->isLive() && !empty($user['stripe_sub'])) {
            $res = $this->stripe('POST', '/v1/subscriptions/' . $user['stripe_sub'], [
                'cancel_at_period_end' => true,
            ]);
            if (isset($res['error'])) {
                return ['ok' => false, 'error' => $res['error']['message']];
            }
        }

        // On efface l'abonnement mais on conserve plan_until : l'utilisateur
        // garde ce qu'il a payé.
        $stmt = $this->app->db->prepare('UPDATE users SET stripe_sub = NULL WHERE id = ?');
        $stmt->execute([$userId]);

        return ['ok' => true];
    }

    private function extend(int $userId, string $kind): void
    {
        $interval = $kind === 'yearly' ? '+1 year' : '+1 month';
        $stmt = $this->app->db->prepare(
            'UPDATE users SET plan_until = datetime(
                CASE WHEN plan_until > datetime("now") THEN plan_until ELSE datetime("now") END, ?)
             WHERE id = ? AND plan != "lifetime"'
        );
        $stmt->execute([$interval, $userId]);
    }

    /** Offre des mois au parrain la première fois que son filleul paie. */
    private function rewardSponsor(int $userId): void
    {
        $config = $this->app->config['referral'];
        if (empty($config['enabled'])) {
            return;
        }
        $stmt = $this->app->db->prepare('SELECT referred_by, reward_given FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !$row['referred_by'] || $row['reward_given']) {
            return;
        }

        $months = (int) $config['reward_months'];
        $stmt = $this->app->db->prepare(
            'UPDATE users SET plan = CASE WHEN plan = "free" THEN "monthly" ELSE plan END,
                    plan_until = datetime(
                        CASE WHEN plan_until > datetime("now") THEN plan_until ELSE datetime("now") END,
                        ?)
             WHERE id = ? AND plan != "lifetime"'
        );
        $stmt->execute(["+$months month", (int) $row['referred_by']]);

        $stmt = $this->app->db->prepare('UPDATE users SET reward_given = 1 WHERE id = ?');
        $stmt->execute([$userId]);
    }

    private function recordPayment(int $userId, string $provider, string $ref, string $kind, int $amount, string $status): void
    {
        $stmt = $this->app->db->prepare(
            'INSERT INTO payments (user_id, provider, reference, kind, amount, currency, status, created_at)
             VALUES (?, ?, ?, ?, ?, "eur", ?, datetime("now"))'
        );
        $stmt->execute([$userId, $provider, $ref, $kind, $amount, $status]);
    }

    // ------------------------------------------------------------------ Stripe

    private function stripe(string $method, string $path, array $params = []): array
    {
        $ch = curl_init('https://api.stripe.com' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->app->config['stripe']['secret_key'],
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => $this->encode($params),
            CURLOPT_TIMEOUT    => 20,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            return ['error' => ['message' => curl_error($ch)]];
        }
        curl_close($ch);
        return json_decode((string) $body, true) ?: [];
    }

    /** Stripe attend des tableaux au format line_items[0][price]. */
    private function encode(array $params, string $prefix = ''): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $nested = $this->encode($value, $name);
                if ($nested !== '') {
                    $parts[] = $nested;
                }
            } elseif ($value !== null) {
                $parts[] = urlencode($name) . '=' . urlencode(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        }
        return implode('&', $parts);
    }

    private function verifySignature(string $payload, ?string $header, string $secret): bool
    {
        if ($header === null) {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;                       // rejoue trop ancien
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }
}
