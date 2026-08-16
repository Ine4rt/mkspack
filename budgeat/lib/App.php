<?php
declare(strict_types=1);

require_once __DIR__ . '/Catalog.php';
require_once __DIR__ . '/Engine.php';

/**
 * Configuration, base SQLite, sessions et comptes.
 * Tout est volontairement sans dépendance externe : le service tourne sur
 * n'importe quel hébergement mutualisé avec PHP 8 et SQLite.
 */
final class App
{
    private static ?App $instance = null;

    public array $config;
    public PDO $db;
    private ?array $user = null;
    private bool $userLoaded = false;

    public static function get(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $configFile = __DIR__ . '/../config.php';
        $this->config = file_exists($configFile)
            ? require $configFile
            : require __DIR__ . '/../config.example.php';

        $this->openDatabase();
        $this->startSession();
    }

    // ------------------------------------------------------------ base de données

    private function openDatabase(): void
    {
        $path = $this->config['db_path'];
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->protectStorage($dir);
        $path = $this->resolveDatabasePath($path, $dir);

        $fresh = !file_exists($path);
        $this->db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA foreign_keys = ON');

        if ($fresh || !$this->tableExists('users')) {
            $this->db->exec(file_get_contents(__DIR__ . '/../schema.sql'));
        }
    }

    /**
     * Le dossier de stockage se retrouve souvent à l'intérieur du répertoire
     * public après un envoi par FTP. Le .htaccess ne couvre qu'Apache : sur
     * nginx, une base nommée de façon prévisible se télécharge en une requête.
     * On sème donc les protections qu'on peut, et on rend le nom du fichier
     * non devinable — install.php vérifie ensuite l'accès pour de bon.
     */
    private function protectStorage(string $dir): void
    {
        if (!is_writable($dir)) {
            return;
        }
        if (!file_exists("$dir/.htaccess")) {
            @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
        }
        if (!file_exists("$dir/index.html")) {
            @file_put_contents("$dir/index.html", '');
        }
    }

    /**
     * Donne à la base un nom imprévisible, mémorisé pour les lancements
     * suivants. Une installation existante garde son fichier.
     */
    private function resolveDatabasePath(string $path, string $dir): string
    {
        if (basename($path) !== 'budgeat.sqlite' || file_exists($path)) {
            return $path;                        // chemin choisi par l'utilisateur, ou base déjà en place
        }

        $marker = "$dir/db.name";
        if (file_exists($marker)) {
            $name = trim((string) file_get_contents($marker));
            if ($name !== '') {
                return "$dir/$name";
            }
        }
        if (!is_writable($dir)) {
            return $path;
        }

        $name = 'budgeat-' . bin2hex(random_bytes(6)) . '.sqlite';
        @file_put_contents($marker, $name);
        return "$dir/$name";
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------ session

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 180,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('budgeat');
        session_start();

        if (empty($_SESSION['visitor'])) {
            $_SESSION['visitor'] = bin2hex(random_bytes(16));
        }
    }

    public function visitorId(): string
    {
        return $_SESSION['visitor'];
    }

    // ------------------------------------------------------------------ comptes

    public function user(): ?array
    {
        if ($this->userLoaded) {
            return $this->user;
        }
        $this->userLoaded = true;

        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $this->user = $stmt->fetch() ?: null;
        return $this->user;
    }

    public function register(string $email, string $password, ?string $referral = null): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Adresse e-mail invalide.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Le mot de passe doit faire au moins 8 caractères.'];
        }

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'Un compte existe déjà avec cette adresse.'];
        }

        $sponsor = null;
        if ($referral) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE referral_code = ?');
            $stmt->execute([strtoupper($referral)]);
            $sponsor = $stmt->fetchColumn() ?: null;
        }

        // Un filleul démarre avec un mois offert.
        $welcome = $sponsor && $this->config['referral']['enabled']
            ? (int) $this->config['referral']['welcome_months']
            : 0;
        $until = $welcome > 0 ? date('Y-m-d H:i:s', strtotime("+$welcome month")) : null;

        $stmt = $this->db->prepare(
            'INSERT INTO users (email, pass_hash, plan, plan_until, referral_code, referred_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime("now"))'
        );
        $stmt->execute([
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $welcome > 0 ? 'monthly' : 'free',
            $until,
            $this->newReferralCode(),
            $sponsor,
        ]);

        $_SESSION['user_id'] = (int) $this->db->lastInsertId();
        $this->userLoaded = false;
        $this->track('signup', ['referred' => (bool) $sponsor]);

        return ['ok' => true, 'welcome_months' => $welcome];
    }

    public function login(string $email, string $password): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['pass_hash'])) {
            return ['ok' => false, 'error' => 'E-mail ou mot de passe incorrect.'];
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $this->userLoaded = false;
        return ['ok' => true];
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        $this->user = null;
        $this->userLoaded = false;
    }

    private function newReferralCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $stmt = $this->db->prepare('SELECT 1 FROM users WHERE referral_code = ?');
            $stmt->execute([$code]);
        } while ($stmt->fetchColumn());
        return $code;
    }

    // -------------------------------------------------------------------- accès

    /** L'utilisateur a-t-il un accès payant en cours ? */
    public function isPremium(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ($user['plan'] === 'lifetime') {
            return true;
        }
        if (in_array($user['plan'], ['monthly', 'yearly'], true)) {
            return $user['plan_until'] !== null && strtotime($user['plan_until']) > time();
        }
        return false;
    }

    /**
     * Combien de menus l'utilisateur (ou le visiteur anonyme) peut-il encore
     * générer cette semaine ? Les premium ne sont jamais limités.
     */
    public function quota(): array
    {
        if ($this->isPremium()) {
            return ['unlimited' => true, 'used' => 0, 'left' => PHP_INT_MAX];
        }

        $limit = (int) $this->config['free']['menus_per_week'];
        $user = $this->user();
        $key = $user ? 'u' . $user['id'] : 'v' . $this->visitorId();

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM plans WHERE owner_key = ? AND created_at > datetime('now', '-7 days')"
        );
        $stmt->execute([$key]);
        $used = (int) $stmt->fetchColumn();

        return [
            'unlimited' => false,
            'used'      => $used,
            'left'      => max(0, $limit - $used),
            'limit'     => $limit,
        ];
    }

    public function ownerKey(): string
    {
        $user = $this->user();
        return $user ? 'u' . $user['id'] : 'v' . $this->visitorId();
    }

    // ---------------------------------------------------------------- menus

    public function savePlan(array $prefs, array $plan): string
    {
        $token = bin2hex(random_bytes(8));
        $user = $this->user();
        $stmt = $this->db->prepare(
            'INSERT INTO plans (token, owner_key, user_id, prefs_json, plan_json, total, budget, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, datetime("now"))'
        );
        $stmt->execute([
            $token,
            $this->ownerKey(),
            $user['id'] ?? null,
            json_encode($prefs, JSON_UNESCAPED_UNICODE),
            json_encode($plan, JSON_UNESCAPED_UNICODE),
            $plan['totals']['total'],
            $plan['totals']['budget'],
        ]);
        return $token;
    }

    public function loadPlan(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['prefs'] = json_decode($row['prefs_json'], true);
        $row['plan']  = json_decode($row['plan_json'], true);
        return $row;
    }

    public function updatePlan(string $token, array $plan): void
    {
        $stmt = $this->db->prepare('UPDATE plans SET plan_json = ?, total = ? WHERE token = ? AND owner_key = ?');
        $stmt->execute([
            json_encode($plan, JSON_UNESCAPED_UNICODE),
            $plan['totals']['total'],
            $token,
            $this->ownerKey(),
        ]);
    }

    /** @return array<int,array> historique des menus du compte */
    public function history(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT token, total, budget, created_at FROM plans
             WHERE owner_key = ? ORDER BY id DESC LIMIT ?'
        );
        $stmt->execute([$this->ownerKey(), $limit]);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------- statistiques

    public function track(string $event, array $payload = []): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO events (owner_key, event, payload, created_at) VALUES (?, ?, ?, datetime("now"))'
        );
        $stmt->execute([$this->ownerKey(), $event, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    // ------------------------------------------------------------------ divers

    public function engine(): Engine
    {
        return new Engine(Catalog::load());
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public function checkCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
    }

    public function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' €';
    }

    /**
     * URL absolue. Tant que base_url n'a pas été renseigné dans config.php,
     * on la déduit de la requête en cours : le site fonctionne donc
     * immédiatement après copie des fichiers, sans configuration.
     */
    public function url(string $path = ''): string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');

        if ($base === '' || str_contains($base, 'example')) {
            $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // Le service peut vivre dans un sous-dossier (/budgeat) : on
            // reprend le préfixe du script courant.
            $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            if (str_ends_with($dir, '/api') || str_ends_with($dir, '/legal')) {
                $dir = dirname($dir);
            }
            $base = $scheme . '://' . $host . ($dir === '/' ? '' : $dir);
        }

        return $base . '/' . ltrim($path, '/');
    }
}

/** Réponse JSON courte pour les endpoints d'API. */
function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
