<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Billing.php';

/**
 * Point d'entrée des webhooks Stripe.
 * À déclarer dans le dashboard Stripe :
 *   https://votre-domaine/api/webhook.php
 * Événements à écouter : checkout.session.completed, invoice.paid,
 * customer.subscription.deleted.
 */
$app = App::get();
$billing = new Billing($app);

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;

$result = $billing->handleWebhook($payload, $signature);

json_out($result, $result['ok'] ? 200 : 400);
