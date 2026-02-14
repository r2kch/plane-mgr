<?php

declare(strict_types=1);

session_start();

function config(string $key, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $path = __DIR__ . '/../Config.php';
        if (!is_file($path)) {
            throw new RuntimeException('Config.php fehlt. Bitte public/Config.example.php nach public/Config.php kopieren und ausfüllen.');
        }

        $config = require $path;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string)config('db.host', '127.0.0.1');
    $port = (string)config('db.port', '3306');
    $name = (string)config('db.name', 'plane_mgr');
    $user = (string)config('db.user', 'root');
    $pass = (string)config('db.pass', '');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Backward-compatible schema update for existing local databases.
    $pdo->exec('ALTER TABLE aircraft ADD COLUMN IF NOT EXISTS start_hobbs DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER status');
    $pdo->exec('ALTER TABLE aircraft ADD COLUMN IF NOT EXISTS start_landings INT NOT NULL DEFAULT 1 AFTER start_hobbs');
    $pdo->exec('ALTER TABLE reservation_flights ADD COLUMN IF NOT EXISTS landings_count INT NOT NULL DEFAULT 1 AFTER landing_time');

    return $pdo;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['_csrf'];
}

function csrf_check(?string $token): bool
{
    return hash_equals($_SESSION['_csrf'] ?? '', $token ?? '');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function user_roles(int $userId): array
{
    $stmt = db()->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.name");
    $stmt->execute([$userId]);
    return array_values(array_map(static fn(array $row): string => $row['name'], $stmt->fetchAll()));
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $userRoles = $user['roles'] ?? [];
    foreach ($roles as $role) {
        if (in_array($role, $userRoles, true)) {
            return true;
        }
    }

    return false;
}

function require_role(string ...$roles): void
{
    if (!has_role(...$roles)) {
        http_response_code(403);
        echo 'Kein Zugriff.';
        exit;
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function audit_log(string $action, string $entity, ?int $entityId = null, array $meta = []): void
{
    $user = current_user();
    if (!$user) {
        return;
    }

    $stmt = db()->prepare('INSERT INTO audit_logs (actor_user_id, action, entity, entity_id, meta_json) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $user['id'],
        $action,
        $entity,
        $entityId,
        $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function user_rate_for_aircraft(int $userId, int $aircraftId): ?float
{
    $stmt = db()->prepare('SELECT hourly_rate FROM aircraft_user_rates WHERE user_id = ? AND aircraft_id = ?');
    $stmt->execute([$userId, $aircraftId]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (float)$value : null;
}

function role_permissions(): array
{
    return [
        'admin' => ['all'],
        'pilot' => ['reservation.create', 'reservation.complete.own', 'calendar.view'],
        'accounting' => ['calendar.view', 'invoice.create', 'invoice.send', 'invoice.status.update'],
    ];
}

function can(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $allPermissions = [];
    foreach (($user['roles'] ?? []) as $role) {
        $allPermissions = array_merge($allPermissions, role_permissions()[$role] ?? []);
    }
    $allPermissions = array_values(array_unique($allPermissions));

    return in_array('all', $allPermissions, true) || in_array($permission, $allPermissions, true);
}

function module_enabled(string $module): bool
{
    return (bool)config('modules.' . $module, true);
}
