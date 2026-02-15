<?php

declare(strict_types=1);

session_start();

function load_dompdf(): bool
{
    static $attempted = false;
    static $loaded = false;

    if (class_exists(\Dompdf\Dompdf::class)) {
        return true;
    }
    if ($attempted) {
        return $loaded;
    }
    $attempted = true;

    $autoloadCandidates = [
        __DIR__ . '/../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../vendor/autoload.php',
    ];
    foreach ($autoloadCandidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        require_once $path;
        if (class_exists(\Dompdf\Dompdf::class)) {
            $loaded = true;
            break;
        }
    }

    return $loaded;
}

function dompdf_is_available(): bool
{
    return load_dompdf();
}

function dompdf_requirements_ok(): bool
{
    return dompdf_is_available() && extension_loaded('gd');
}

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
    $pdo->exec('ALTER TABLE reservation_flights ADD COLUMN IF NOT EXISTS is_billable TINYINT(1) NOT NULL DEFAULT 1 AFTER hobbs_hours');
    $pdo->exec("CREATE TABLE IF NOT EXISTS aircraft_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_aircraft_groups (
        user_id INT NOT NULL,
        group_id INT NOT NULL,
        PRIMARY KEY (user_id, group_id),
        CONSTRAINT fk_uag_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_uag_group FOREIGN KEY (group_id) REFERENCES aircraft_groups(id) ON DELETE CASCADE
    )");
    $pdo->exec('ALTER TABLE aircraft ADD COLUMN IF NOT EXISTS aircraft_group_id INT NULL AFTER type');
    $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS street VARCHAR(150) NULL AFTER last_name');
    $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS house_number VARCHAR(20) NULL AFTER street');
    $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20) NULL AFTER house_number');
    $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER postal_code');
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS country_code CHAR(2) NOT NULL DEFAULT 'CH' AFTER city");
    $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL AFTER country_code');
    $pdo->exec('ALTER TABLE invoice_items ADD COLUMN IF NOT EXISTS flight_date DATE NULL AFTER reservation_id');
    $pdo->exec('ALTER TABLE invoice_items ADD COLUMN IF NOT EXISTS aircraft_type VARCHAR(100) NULL AFTER flight_date');
    $pdo->exec('ALTER TABLE invoice_items ADD COLUMN IF NOT EXISTS aircraft_immatriculation VARCHAR(30) NULL AFTER aircraft_type');
    $pdo->exec('ALTER TABLE invoice_items ADD COLUMN IF NOT EXISTS from_airfield VARCHAR(10) NULL AFTER aircraft_immatriculation');
    $pdo->exec('ALTER TABLE invoice_items ADD COLUMN IF NOT EXISTS to_airfield VARCHAR(10) NULL AFTER from_airfield');
    $pdo->exec("UPDATE invoices SET payment_status = 'open' WHERE payment_status = 'part_paid'");
    $pdo->exec('UPDATE reservation_flights SET is_billable = 1 WHERE is_billable IS NULL');

    $fkStmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'aircraft'
          AND CONSTRAINT_NAME = 'fk_aircraft_group'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    if ((int)$fkStmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE aircraft ADD CONSTRAINT fk_aircraft_group FOREIGN KEY (aircraft_group_id) REFERENCES aircraft_groups(id) ON DELETE SET NULL');
    }

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

function smtp_enabled(): bool
{
    $enabled = (bool)config('smtp.enabled', true);
    $host = trim((string)config('smtp.host', ''));
    $port = (int)config('smtp.port', 0);
    $from = trim((string)config('smtp.from', ''));

    return $enabled && $host !== '' && $port > 0 && $from !== '';
}

function smtp_send_mail(string $to, string $subject, string $htmlBody, ?string $textBody = null, array $attachments = []): array
{
    $enabled = (bool)config('smtp.enabled', true);
    $host = trim((string)config('smtp.host', ''));
    $port = (int)config('smtp.port', 25);
    $user = trim((string)config('smtp.user', ''));
    $pass = (string)config('smtp.pass', '');
    $from = trim((string)config('smtp.from', ''));
    $fromName = trim((string)config('smtp.from_name', ''));

    if (!$enabled) {
        return ['ok' => true, 'skipped' => true, 'error' => null];
    }

    if ($host === '' || $port <= 0 || $from === '') {
        return ['ok' => false, 'error' => 'SMTP ist unvollständig konfiguriert.'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Ungültige Empfängeradresse.'];
    }

    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        return ['ok' => false, 'error' => "SMTP-Verbindung fehlgeschlagen ($errno): $errstr"];
    }

    stream_set_timeout($socket, 15);

    $readResponse = static function ($conn): array {
        $full = '';
        $code = 0;
        while (($line = fgets($conn, 515)) !== false) {
            $full .= $line;
            if (preg_match('/^(\d{3})([\s-])/', $line, $m)) {
                $code = (int)$m[1];
                if ($m[2] === ' ') {
                    break;
                }
            }
        }
        return ['code' => $code, 'raw' => trim($full)];
    };

    $expect = static function ($conn, array $allowedCodes, ?string $command = null) use ($readResponse): array {
        if ($command !== null) {
            fwrite($conn, $command . "\r\n");
        }
        $resp = $readResponse($conn);
        if (!in_array($resp['code'], $allowedCodes, true)) {
            return ['ok' => false, 'error' => $resp['raw']];
        }
        return ['ok' => true, 'raw' => $resp['raw']];
    };

    $helloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $needAuth = ($user !== '' || $pass !== '');

    $result = $expect($socket, [220]);
    if (!$result['ok']) {
        fclose($socket);
        return ['ok' => false, 'error' => 'SMTP Begrüßung fehlgeschlagen: ' . $result['error']];
    }

    $result = $expect($socket, [250], 'EHLO ' . $helloHost);
    if (!$result['ok']) {
        $result = $expect($socket, [250], 'HELO ' . $helloHost);
        if (!$result['ok']) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP HELO/EHLO fehlgeschlagen: ' . $result['error']];
        }
    }

    if ($needAuth) {
        $result = $expect($socket, [334], 'AUTH LOGIN');
        if (!$result['ok']) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP AUTH LOGIN fehlgeschlagen: ' . $result['error']];
        }

        $result = $expect($socket, [334], base64_encode($user));
        if (!$result['ok']) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP Benutzername abgelehnt: ' . $result['error']];
        }

        $result = $expect($socket, [235], base64_encode($pass));
        if (!$result['ok']) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP Passwort abgelehnt: ' . $result['error']];
        }
    }

    $result = $expect($socket, [250], 'MAIL FROM:<' . $from . '>');
    if (!$result['ok']) {
        fclose($socket);
        return ['ok' => false, 'error' => 'MAIL FROM fehlgeschlagen: ' . $result['error']];
    }

    $result = $expect($socket, [250, 251], 'RCPT TO:<' . $to . '>');
    if (!$result['ok']) {
        fclose($socket);
        return ['ok' => false, 'error' => 'RCPT TO fehlgeschlagen: ' . $result['error']];
    }

    $result = $expect($socket, [354], 'DATA');
    if (!$result['ok']) {
        fclose($socket);
        return ['ok' => false, 'error' => 'DATA fehlgeschlagen: ' . $result['error']];
    }

    $boundaryAlt = 'PM-ALT-' . bin2hex(random_bytes(8));
    $boundaryMixed = 'PM-MIX-' . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $safeFromName = str_replace(['"', "\r", "\n"], '', $fromName);
    $fromHeader = $safeFromName !== '' ? sprintf('"%s" <%s>', $safeFromName, $from) : $from;
    $text = $textBody !== null ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    $text = preg_replace("/\r\n|\r|\n/", "\r\n", $text ?? '');

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . $fromHeader,
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
    ];
    $hasAttachments = !empty($attachments);
    if ($hasAttachments) {
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundaryMixed . '"';
    } else {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"';
    }

    $bodyLines = [];
    if ($hasAttachments) {
        $bodyLines[] = '--' . $boundaryMixed;
        $bodyLines[] = 'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"';
        $bodyLines[] = '';
    }

    $bodyLines[] = '--' . $boundaryAlt;
    $bodyLines[] = 'Content-Type: text/plain; charset=UTF-8';
    $bodyLines[] = 'Content-Transfer-Encoding: 8bit';
    $bodyLines[] = '';
    $bodyLines[] = $text;
    $bodyLines[] = '--' . $boundaryAlt;
    $bodyLines[] = 'Content-Type: text/html; charset=UTF-8';
    $bodyLines[] = 'Content-Transfer-Encoding: 8bit';
    $bodyLines[] = '';
    $bodyLines[] = $htmlBody;
    $bodyLines[] = '--' . $boundaryAlt . '--';
    $bodyLines[] = '';

    if ($hasAttachments) {
        foreach ($attachments as $attachment) {
            $filename = trim((string)($attachment['filename'] ?? 'attachment.bin'));
            $content = (string)($attachment['content'] ?? '');
            $mime = trim((string)($attachment['mime'] ?? 'application/octet-stream'));
            if ($content === '') {
                continue;
            }
            $safeFilename = str_replace(['"', "\r", "\n"], '', $filename);
            $encoded = chunk_split(base64_encode($content), 76, "\r\n");

            $bodyLines[] = '--' . $boundaryMixed;
            $bodyLines[] = 'Content-Type: ' . $mime . '; name="' . $safeFilename . '"';
            $bodyLines[] = 'Content-Transfer-Encoding: base64';
            $bodyLines[] = 'Content-Disposition: attachment; filename="' . $safeFilename . '"';
            $bodyLines[] = '';
            $bodyLines[] = $encoded;
        }
        $bodyLines[] = '--' . $boundaryMixed . '--';
        $bodyLines[] = '';
    }

    $data = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $bodyLines);
    $data = str_replace("\r\n.\r\n", "\r\n..\r\n", $data);

    fwrite($socket, $data . "\r\n.\r\n");
    $result = $readResponse($socket);
    if (!in_array($result['code'], [250], true)) {
        fclose($socket);
        return ['ok' => false, 'error' => 'Nachricht wurde nicht akzeptiert: ' . $result['raw']];
    }

    $expect($socket, [221, 250], 'QUIT');
    fclose($socket);

    return ['ok' => true, 'error' => null];
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

function is_group_restricted_pilot(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user) {
        return false;
    }

    $roles = $user['roles'] ?? [];
    return in_array('pilot', $roles, true)
        && !in_array('admin', $roles, true)
        && !in_array('accounting', $roles, true);
}

function permitted_aircraft_ids_for_user(int $userId): array
{
    $stmt = db()->prepare("SELECT DISTINCT a.id
        FROM aircraft a
        JOIN user_aircraft_groups uag ON uag.group_id = a.aircraft_group_id
        WHERE uag.user_id = ?
          AND a.status = 'active'
          AND a.aircraft_group_id IS NOT NULL");
    $stmt->execute([$userId]);
    return array_map(static fn($id): int => (int)$id, $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function user_has_group_access_to_aircraft(int $userId, int $aircraftId): bool
{
    $stmt = db()->prepare("SELECT COUNT(*)
        FROM aircraft a
        JOIN user_aircraft_groups uag ON uag.group_id = a.aircraft_group_id
        WHERE a.id = ?
          AND uag.user_id = ?
          AND a.aircraft_group_id IS NOT NULL");
    $stmt->execute([$aircraftId, $userId]);
    return (int)$stmt->fetchColumn() > 0;
}

function european_countries(): array
{
    return [
        'AL' => 'Albanien',
        'AD' => 'Andorra',
        'BE' => 'Belgien',
        'BA' => 'Bosnien und Herzegowina',
        'BG' => 'Bulgarien',
        'DK' => 'Dänemark',
        'DE' => 'Deutschland',
        'EE' => 'Estland',
        'FI' => 'Finnland',
        'FR' => 'Frankreich',
        'GR' => 'Griechenland',
        'IE' => 'Irland',
        'IS' => 'Island',
        'IT' => 'Italien',
        'XK' => 'Kosovo',
        'HR' => 'Kroatien',
        'LV' => 'Lettland',
        'LI' => 'Liechtenstein',
        'LT' => 'Litauen',
        'LU' => 'Luxemburg',
        'MT' => 'Malta',
        'MD' => 'Moldau',
        'MC' => 'Monaco',
        'ME' => 'Montenegro',
        'NL' => 'Niederlande',
        'MK' => 'Nordmazedonien',
        'NO' => 'Norwegen',
        'AT' => 'Österreich',
        'PL' => 'Polen',
        'PT' => 'Portugal',
        'RO' => 'Rumänien',
        'SM' => 'San Marino',
        'SE' => 'Schweden',
        'CH' => 'Schweiz',
        'RS' => 'Serbien',
        'SK' => 'Slowakei',
        'SI' => 'Slowenien',
        'ES' => 'Spanien',
        'CZ' => 'Tschechien',
        'TR' => 'Türkei',
        'UA' => 'Ukraine',
        'HU' => 'Ungarn',
        'VA' => 'Vatikanstadt',
        'GB' => 'Vereinigtes Königreich',
        'BY' => 'Weissrussland',
        'CY' => 'Zypern',
    ];
}
