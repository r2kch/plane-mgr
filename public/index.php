<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$page = $_GET['page'] ?? 'dashboard';

$publicPages = ['login', 'install'];
if (!in_array($page, $publicPages, true)) {
    require_login();
}

$modulePages = [
    'reservations' => 'reservations',
    'my_invoices' => 'billing',
    'accounting' => 'billing',
    'accounting_flights' => 'billing',
    'credits' => 'billing',
    'rates' => 'billing',
    'invoices' => 'billing',
    'invoice_html' => 'billing',
    'invoice_pdf' => 'billing',
    'manual_flight' => 'billing',
    'audit' => 'audit',
];
if (isset($modulePages[$page]) && !module_enabled($modulePages[$page])) {
    flash('error', 'Dieses Modul ist deaktiviert.');
    header('Location: index.php?page=dashboard');
    exit;
}

switch ($page) {
    case 'install':
        header('Location: setup.php');
        exit;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=login');
                exit;
            }

            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $stmt = db()->prepare('SELECT u.id, u.first_name, u.last_name, u.email, u.password_hash, u.is_active FROM users u WHERE u.email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
                flash('error', 'Login fehlgeschlagen.');
                header('Location: index.php?page=login');
                exit;
            }

            $user['roles'] = user_roles((int)$user['id']);
            if (count($user['roles']) === 0) {
                flash('error', 'Kein Rollenprofil zugewiesen.');
                header('Location: index.php?page=login');
                exit;
            }
            unset($user['password_hash'], $user['is_active']);
            $_SESSION['user'] = $user;
            audit_log('login', 'user', (int)$user['id']);

            header('Location: index.php');
            exit;
        }

        render('Login', 'login');
        break;

    case 'logout':
        audit_log('logout', 'user', (int)(current_user()['id'] ?? 0));
        session_destroy();
        header('Location: index.php?page=login');
        break;

    case 'dashboard':
        $showReservationsModule = module_enabled('reservations');
        $showBillingModule = module_enabled('billing');
        $counts = [
            'invoices_open' => 0,
        ];

        if ($showBillingModule) {
            $dashboardUserId = (int)current_user()['id'];
            $invoiceCountStmt = db()->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND payment_status IN ('open', 'overdue')");
            $invoiceCountStmt->execute([$dashboardUserId]);
            $counts['invoices_open'] = (int)$invoiceCountStmt->fetchColumn();
        }

        $upcomingReservations = [];
        $calendarStartDate = date('Y-m-d');
        $calendarEndDate = date('Y-m-d');
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $isMobileDevice = (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $userAgent);
        $calendarDaysCount = $isMobileDevice ? 1 : 7;
        $calendarAircraft = [];
        $calendarReservationsByAircraft = [];
        $groupRestrictedPilot = is_group_restricted_pilot();
        $allowedAircraftIds = $groupRestrictedPilot ? permitted_aircraft_ids_for_user((int)current_user()['id']) : [];
        $latestNews = null;

        if ($showReservationsModule) {
            $calendarStartInput = (string)($_GET['calendar_start'] ?? date('Y-m-d'));
            $calendarStartTs = strtotime($calendarStartInput . ' 00:00:00');
            if ($calendarStartTs === false) {
                $calendarStartTs = strtotime(date('Y-m-d') . ' 00:00:00');
            }
            $calendarStartDate = date('Y-m-d', $calendarStartTs);

            $upcomingSql = "SELECT r.id, r.user_id, r.starts_at, r.ends_at, r.notes, a.immatriculation,
                    CONCAT(u.first_name, ' ', u.last_name) AS pilot_name
                FROM reservations r
                JOIN aircraft a ON a.id = r.aircraft_id
                JOIN users u ON u.id = r.user_id
                WHERE r.status = 'booked' AND r.ends_at >= ?";
            $upcomingParams = [$calendarStartDate . ' 00:00:00'];
            $upcomingSql .= ' ORDER BY r.starts_at ASC, r.id ASC LIMIT 100';
            $upcomingStmt = db()->prepare($upcomingSql);
            $upcomingStmt->execute($upcomingParams);
            $upcomingReservations = $upcomingStmt->fetchAll();

            $calendarEndDate = date('Y-m-d', strtotime($calendarStartDate . ' +' . ($calendarDaysCount - 1) . ' days'));
            $calendarStartBound = $calendarStartDate . ' 00:00:00';
            $calendarEndBound = $calendarEndDate . ' 23:59:59';

            $calendarAircraft = db()->query("SELECT id, immatriculation, type
                FROM aircraft
                WHERE status = 'active'
                ORDER BY immatriculation ASC")->fetchAll();
            $canReserve = has_role('admin') || can('reservation.create');
            foreach ($calendarAircraft as &$aircraftRow) {
                $aircraftRow['can_link'] = $canReserve && (!$groupRestrictedPilot || in_array((int)$aircraftRow['id'], $allowedAircraftIds, true));
            }
            unset($aircraftRow);

            $calendarSql = "SELECT r.id, r.aircraft_id, r.user_id, r.starts_at, r.ends_at, r.notes,
                    CONCAT(u.first_name, ' ', u.last_name) AS pilot_name
                FROM reservations r
                JOIN users u ON u.id = r.user_id
                WHERE r.status = 'booked'
                  AND r.starts_at <= ?
                  AND r.ends_at >= ?";
            $calendarParams = [$calendarEndBound, $calendarStartBound];
            $calendarSql .= ' ORDER BY r.starts_at ASC';
            $calendarStmt = db()->prepare($calendarSql);
            $calendarStmt->execute($calendarParams);
            $calendarReservationsRaw = $calendarStmt->fetchAll();

            foreach ($calendarReservationsRaw as $row) {
                $aircraftId = (int)$row['aircraft_id'];
                $calendarReservationsByAircraft[$aircraftId][] = $row;
            }
        }

        $newsStmt = db()->query("SELECT n.id, n.title, n.body_html, n.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS author_name
            FROM news n
            JOIN users u ON u.id = n.created_by
            ORDER BY n.created_at DESC
            LIMIT 1");
        $latestNews = $newsStmt->fetch() ?: null;

        render('Dashboard', 'dashboard', compact(
            'counts',
            'showReservationsModule',
            'showBillingModule',
            'upcomingReservations',
            'calendarStartDate',
            'calendarEndDate',
            'calendarDaysCount',
            'calendarAircraft',
            'calendarReservationsByAircraft',
            'latestNews'
        ));
        break;

    case 'news':
        $canManageNews = can_manage_news();
        $editNewsId = (int)($_GET['edit_id'] ?? 0);
        $editNews = null;
        if ($editNewsId > 0) {
            $editStmt = db()->prepare('SELECT * FROM news WHERE id = ?');
            $editStmt->execute([$editNewsId]);
            $editNews = $editStmt->fetch() ?: null;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=news');
                exit;
            }

            if (!$canManageNews) {
                flash('error', 'Kein Zugriff.');
                header('Location: index.php?page=news');
                exit;
            }

            $action = (string)($_POST['action'] ?? '');
            $newsId = (int)($_POST['news_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $bodyHtml = (string)($_POST['body_html'] ?? '');
            $bodyHtml = sanitize_news_html($bodyHtml);

            if ($action === 'create_news') {
                if ($title === '' || $bodyHtml === '') {
                    flash('error', 'Titel und Beitrag sind erforderlich.');
                    header('Location: index.php?page=news');
                    exit;
                }

                $stmt = db()->prepare('INSERT INTO news (title, body_html, created_by) VALUES (?, ?, ?)');
                $stmt->execute([$title, $bodyHtml, (int)current_user()['id']]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', 'news', $newId, ['title' => $title]);
                flash('success', 'News erstellt.');
                header('Location: index.php?page=news');
                exit;
            }

            if ($action === 'update_news') {
                if ($newsId <= 0 || $title === '' || $bodyHtml === '') {
                    flash('error', 'Ungültige Eingaben.');
                    header('Location: index.php?page=news' . ($newsId > 0 ? '&edit_id=' . $newsId : ''));
                    exit;
                }

                $stmt = db()->prepare('UPDATE news SET title = ?, body_html = ? WHERE id = ?');
                $stmt->execute([$title, $bodyHtml, $newsId]);
                audit_log('update', 'news', $newsId, ['title' => $title]);
                flash('success', 'News aktualisiert.');
                header('Location: index.php?page=news');
                exit;
            }

            if ($action === 'delete_news') {
                if ($newsId <= 0) {
                    flash('error', 'Ungültige News.');
                    header('Location: index.php?page=news');
                    exit;
                }
                db()->prepare('DELETE FROM news WHERE id = ?')->execute([$newsId]);
                audit_log('delete', 'news', $newsId);
                flash('success', 'News gelöscht.');
                header('Location: index.php?page=news');
                exit;
            }
        }

        $newsList = db()->query("SELECT n.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
            FROM news n
            JOIN users u ON u.id = n.created_by
            ORDER BY n.created_at DESC
            LIMIT 200")->fetchAll();

        render('News', 'news', compact('newsList', 'canManageNews', 'editNews'));
        break;

    case 'admin':
        require_role('admin');
        render('Admin', 'admin');
        break;

    case 'accounting':
        require_role('admin', 'accounting');
        render('Buchhaltung', 'accounting');
        break;

    case 'accounting_flights':
        require_role('admin', 'accounting');
        $aircraft = db()->query("SELECT id, immatriculation, type, status
            FROM aircraft
            ORDER BY immatriculation ASC")->fetchAll();
        render('Flüge', 'accounting_flights', compact('aircraft'));
        break;

    case 'credits':
        require_role('admin', 'accounting');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=credits');
                exit;
            }

            $action = (string)($_POST['action'] ?? '');
            $creditId = (int)($_POST['credit_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);
            $creditDate = trim((string)($_POST['credit_date'] ?? ''));
            $amount = round((float)($_POST['amount'] ?? 0), 2);
            $description = trim((string)($_POST['description'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($action === 'create_credit' || $action === 'update_credit') {
                $pilotExistsStmt = db()->prepare("SELECT COUNT(*)
                    FROM users u
                    JOIN user_roles ur ON ur.user_id = u.id
                    JOIN roles r ON r.id = ur.role_id
                    WHERE u.id = ? AND u.is_active = 1 AND r.name = 'pilot'");
                $pilotExistsStmt->execute([$userId]);
                if ((int)$pilotExistsStmt->fetchColumn() === 0) {
                    flash('error', 'Ungültiger Pilot.');
                    header('Location: index.php?page=credits' . ($creditId > 0 ? '&edit_credit_id=' . $creditId : ''));
                    exit;
                }

                $dateValid = DateTime::createFromFormat('Y-m-d', $creditDate) !== false;
                if (!$dateValid || $amount <= 0 || $description === '') {
                    flash('error', 'Bitte Datum, positiven Betrag und Beschreibung korrekt erfassen.');
                    header('Location: index.php?page=credits' . ($creditId > 0 ? '&edit_credit_id=' . $creditId : ''));
                    exit;
                }
            }

            if ($action === 'create_credit') {
                $stmt = db()->prepare('INSERT INTO credits (user_id, credit_date, amount, description, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $creditDate, $amount, $description, $notes !== '' ? $notes : null, (int)current_user()['id']]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', 'credit', $newId, ['user_id' => $userId, 'amount' => $amount, 'credit_date' => $creditDate]);
                flash('success', 'Gutschrift erfasst.');
                header('Location: index.php?page=credits');
                exit;
            }

            if ($action === 'update_credit') {
                if ($creditId <= 0) {
                    flash('error', 'Ungültige Gutschrift.');
                    header('Location: index.php?page=credits');
                    exit;
                }
                $openStmt = db()->prepare('SELECT id FROM credits WHERE id = ? AND invoice_id IS NULL');
                $openStmt->execute([$creditId]);
                if (!$openStmt->fetch()) {
                    flash('error', 'Verrechnete Gutschriften können nicht bearbeitet werden.');
                    header('Location: index.php?page=credits');
                    exit;
                }

                $stmt = db()->prepare('UPDATE credits
                    SET user_id = ?, credit_date = ?, amount = ?, description = ?, notes = ?
                    WHERE id = ?');
                $stmt->execute([$userId, $creditDate, $amount, $description, $notes !== '' ? $notes : null, $creditId]);
                audit_log('update', 'credit', $creditId, ['user_id' => $userId, 'amount' => $amount, 'credit_date' => $creditDate]);
                flash('success', 'Gutschrift aktualisiert.');
                header('Location: index.php?page=credits');
                exit;
            }

            if ($action === 'delete_credit') {
                if ($creditId <= 0) {
                    flash('error', 'Ungültige Gutschrift.');
                    header('Location: index.php?page=credits');
                    exit;
                }
                $openStmt = db()->prepare('SELECT id FROM credits WHERE id = ? AND invoice_id IS NULL');
                $openStmt->execute([$creditId]);
                if (!$openStmt->fetch()) {
                    flash('error', 'Verrechnete Gutschriften können nicht gelöscht werden.');
                    header('Location: index.php?page=credits');
                    exit;
                }
                db()->prepare('DELETE FROM credits WHERE id = ? AND invoice_id IS NULL')->execute([$creditId]);
                audit_log('delete', 'credit', $creditId);
                flash('success', 'Gutschrift gelöscht.');
                header('Location: index.php?page=credits');
                exit;
            }
        }

        $editCreditId = (int)($_GET['edit_credit_id'] ?? 0);
        $editCredit = null;
        if ($editCreditId > 0) {
            $editStmt = db()->prepare('SELECT * FROM credits WHERE id = ? AND invoice_id IS NULL');
            $editStmt->execute([$editCreditId]);
            $editCredit = $editStmt->fetch() ?: null;
        }

        $pilots = db()->query("SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE r.name = 'pilot' AND u.is_active = 1
            ORDER BY u.last_name ASC, u.first_name ASC")->fetchAll();

        $credits = db()->query("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) AS pilot_name
            FROM credits c
            JOIN users u ON u.id = c.user_id
            WHERE c.invoice_id IS NULL
            ORDER BY c.credit_date DESC, c.id DESC")->fetchAll();

        $settledCredits = db()->query("SELECT c.*, i.invoice_number, CONCAT(u.first_name, ' ', u.last_name) AS pilot_name
            FROM credits c
            JOIN users u ON u.id = c.user_id
            JOIN invoices i ON i.id = c.invoice_id
            ORDER BY c.credit_date DESC, c.id DESC
            LIMIT 200")->fetchAll();

        $defaultCreditDate = date('Y-m-d');
        render('Gutschrift', 'credits', compact('pilots', 'credits', 'settledCredits', 'editCredit', 'defaultCreditDate'));
        break;

    case 'aircraft':
        require_role('admin');
        $openAircraftId = (int)($_GET['open_aircraft_id'] ?? 0);
        $showNewAircraftForm = ((int)($_GET['new'] ?? 0)) === 1;
        $parseHobbsClock = static function (string $value): ?float {
            $trimmed = trim($value);
            if (!preg_match('/^\d+:[0-5]\d$/', $trimmed)) {
                return null;
            }
            [$hours, $minutes] = explode(':', $trimmed, 2);
            return ((int)$hours) + (((int)$minutes) / 60);
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=aircraft');
                exit;
            }

            $action = (string)($_POST['action'] ?? 'save');
            $id = (int)($_POST['id'] ?? 0);
            $imm = trim((string)($_POST['immatriculation'] ?? ''));
            $type = trim((string)($_POST['type'] ?? ''));
            $status = (string)($_POST['status'] ?? 'active');
            $startHobbsRaw = trim((string)($_POST['start_hobbs'] ?? '0:00'));
            $startHobbs = $parseHobbsClock($startHobbsRaw);
            $startLandings = (int)($_POST['start_landings'] ?? 1);
            $rate = (float)($_POST['base_hourly_rate'] ?? 0);

            if ($action === 'delete') {
                if ($id <= 0) {
                    flash('error', 'Ungültiges Flugzeug.');
                    header('Location: index.php?page=aircraft');
                    exit;
                }

                $resCountStmt = db()->prepare('SELECT COUNT(*) FROM reservations WHERE aircraft_id = ?');
                $resCountStmt->execute([$id]);
                $reservationCount = (int)$resCountStmt->fetchColumn();
                if ($reservationCount > 0) {
                    flash('error', 'Flugzeug kann nicht gelöscht werden, da Reservierungen vorhanden sind.');
                    header('Location: index.php?page=aircraft&open_aircraft_id=' . $id);
                    exit;
                }

                try {
                    db()->beginTransaction();
                    db()->prepare('DELETE FROM aircraft_user_rates WHERE aircraft_id = ?')->execute([$id]);
                    db()->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$id]);
                    db()->commit();
                    audit_log('delete', 'aircraft', $id);
                    flash('success', 'Flugzeug gelöscht.');
                    header('Location: index.php?page=aircraft');
                    exit;
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Flugzeug konnte nicht gelöscht werden.');
                    header('Location: index.php?page=aircraft&open_aircraft_id=' . $id);
                    exit;
                }
            }

            if ($startHobbs === null || $startLandings < 1) {
                flash('error', 'Start HOBBS muss im Format HH:MM sein und Start Landing min. 1.');
                header('Location: index.php?page=aircraft' . ($id > 0 ? '&open_aircraft_id=' . $id : '&new=1'));
                exit;
            }

            if ($id > 0) {
                $stmt = db()->prepare('UPDATE aircraft SET immatriculation = ?, type = ?, status = ?, start_hobbs = ?, start_landings = ?, base_hourly_rate = ? WHERE id = ?');
                $stmt->execute([$imm, $type, $status, $startHobbs, $startLandings, $rate, $id]);
                audit_log('update', 'aircraft', $id, ['immatriculation' => $imm]);
                flash('success', 'Flugzeug aktualisiert.');
                header('Location: index.php?page=aircraft&open_aircraft_id=' . $id);
            } else {
                $stmt = db()->prepare('INSERT INTO aircraft (immatriculation, type, status, start_hobbs, start_landings, base_hourly_rate) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$imm, $type, $status, $startHobbs, $startLandings, $rate]);
                $newId = (int)db()->lastInsertId();
                audit_log('create', 'aircraft', $newId, ['immatriculation' => $imm]);
                flash('success', 'Flugzeug angelegt.');
                header('Location: index.php?page=aircraft');
            }

            exit;
        }

        $aircraft = db()->query('SELECT * FROM aircraft ORDER BY immatriculation')->fetchAll();
        $vatEnabled = (bool)config('invoice.vat.enabled', false);
        render('Flugzeuge', 'aircraft', compact('aircraft', 'openAircraftId', 'showNewAircraftForm', 'vatEnabled'));
        break;

    case 'groups':
        require_role('admin');
        $openGroupId = (int)($_GET['open_group_id'] ?? 0);
        $showNewGroupForm = ((int)($_GET['new'] ?? 0)) === 1;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=groups');
                exit;
            }

            $action = (string)($_POST['action'] ?? '');

            if ($action === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $aircraftIds = array_values(array_unique(array_map(static fn($id): int => (int)$id, (array)($_POST['aircraft_ids'] ?? []))));
                $aircraftIds = array_values(array_filter($aircraftIds, static fn(int $id): bool => $id > 0));
                if ($name === '') {
                    flash('error', 'Gruppenname ist erforderlich.');
                    header('Location: index.php?page=groups&new=1');
                    exit;
                }

                try {
                    db()->beginTransaction();
                    $stmt = db()->prepare('INSERT INTO aircraft_groups (name) VALUES (?)');
                    $stmt->execute([$name]);
                    $newId = (int)db()->lastInsertId();

                    if ($aircraftIds !== []) {
                        $placeholders = implode(',', array_fill(0, count($aircraftIds), '?'));
                        $params = array_merge([$newId], $aircraftIds);
                        db()->prepare("UPDATE aircraft SET aircraft_group_id = ? WHERE id IN ($placeholders)")->execute($params);
                    }

                    db()->commit();
                    audit_log('create', 'aircraft_group', $newId, ['name' => $name, 'aircraft_ids' => $aircraftIds]);
                    flash('success', 'Gruppe angelegt.');
                    header('Location: index.php?page=groups&open_group_id=' . $newId);
                    exit;
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Gruppe konnte nicht angelegt werden (Name evtl. bereits vorhanden).');
                    header('Location: index.php?page=groups&new=1');
                    exit;
                }
            }

            if ($action === 'update') {
                $groupId = (int)($_POST['group_id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $aircraftIds = array_values(array_unique(array_map(static fn($id): int => (int)$id, (array)($_POST['aircraft_ids'] ?? []))));
                $aircraftIds = array_values(array_filter($aircraftIds, static fn(int $id): bool => $id > 0));

                if ($groupId <= 0 || $name === '') {
                    flash('error', 'Ungültige Eingaben.');
                    header('Location: index.php?page=groups&open_group_id=' . max(0, $groupId));
                    exit;
                }

                $groupExistsStmt = db()->prepare('SELECT COUNT(*) FROM aircraft_groups WHERE id = ?');
                $groupExistsStmt->execute([$groupId]);
                if ((int)$groupExistsStmt->fetchColumn() === 0) {
                    flash('error', 'Gruppe nicht gefunden.');
                    header('Location: index.php?page=groups');
                    exit;
                }

                try {
                    db()->beginTransaction();
                    db()->prepare('UPDATE aircraft_groups SET name = ? WHERE id = ?')->execute([$name, $groupId]);
                    db()->prepare('UPDATE aircraft SET aircraft_group_id = NULL WHERE aircraft_group_id = ?')->execute([$groupId]);

                    if ($aircraftIds !== []) {
                        $placeholders = implode(',', array_fill(0, count($aircraftIds), '?'));
                        $params = array_merge([$groupId], $aircraftIds);
                        db()->prepare("UPDATE aircraft SET aircraft_group_id = ? WHERE id IN ($placeholders)")->execute($params);
                    }

                    db()->commit();
                    audit_log('update', 'aircraft_group', $groupId, ['name' => $name, 'aircraft_ids' => $aircraftIds]);
                    flash('success', 'Gruppe aktualisiert.');
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Gruppe konnte nicht aktualisiert werden.');
                }

                header('Location: index.php?page=groups&open_group_id=' . $groupId);
                exit;
            }

            if ($action === 'delete') {
                $groupId = (int)($_POST['group_id'] ?? 0);
                if ($groupId <= 0) {
                    flash('error', 'Ungültige Gruppe.');
                    header('Location: index.php?page=groups');
                    exit;
                }

                $usageStmt = db()->prepare('SELECT COUNT(*) FROM aircraft WHERE aircraft_group_id = ?');
                $usageStmt->execute([$groupId]);
                if ((int)$usageStmt->fetchColumn() > 0) {
                    flash('error', 'Gruppe kann nicht gelöscht werden, solange Flugzeuge zugeordnet sind.');
                    header('Location: index.php?page=groups&open_group_id=' . $groupId);
                    exit;
                }

                try {
                    db()->beginTransaction();
                    db()->prepare('DELETE FROM user_aircraft_groups WHERE group_id = ?')->execute([$groupId]);
                    db()->prepare('DELETE FROM aircraft_groups WHERE id = ?')->execute([$groupId]);
                    db()->commit();
                    audit_log('delete', 'aircraft_group', $groupId);
                    flash('success', 'Gruppe gelöscht.');
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Gruppe konnte nicht gelöscht werden.');
                }

                header('Location: index.php?page=groups');
                exit;
            }
        }

        $groups = db()->query("SELECT g.id, g.name, COUNT(a.id) AS aircraft_count
            FROM aircraft_groups g
            LEFT JOIN aircraft a ON a.aircraft_group_id = g.id
            GROUP BY g.id, g.name
            ORDER BY g.name")->fetchAll();
        $aircraft = db()->query("SELECT id, immatriculation, type, aircraft_group_id, status
            FROM aircraft
            ORDER BY immatriculation")->fetchAll();

        render('Gruppen', 'groups', compact('groups', 'aircraft', 'openGroupId', 'showNewGroupForm'));
        break;

    case 'aircraft_flights':
        require_role('admin', 'accounting');
        $aircraftId = (int)($_GET['aircraft_id'] ?? 0);
        if ($aircraftId <= 0) {
            http_response_code(404);
            exit('Flugzeug nicht gefunden.');
        }

        $aircraftStmt = db()->prepare('SELECT id, immatriculation, type FROM aircraft WHERE id = ?');
        $aircraftStmt->execute([$aircraftId]);
        $aircraft = $aircraftStmt->fetch();
        if (!$aircraft) {
            http_response_code(404);
            exit('Flugzeug nicht gefunden.');
        }

        $parseHobbsClock = static function (string $value): ?float {
            $trimmed = trim($value);
            if (!preg_match('/^\d+:[0-5]\d$/', $trimmed)) {
                return null;
            }

            [$hours, $minutes] = explode(':', $trimmed, 2);
            return ((int)$hours) + (((int)$minutes) / 60);
        };

        $formatHobbsClock = static function (float $value): string {
            $hours = (int)floor($value);
            $minutes = (int)round(($value - $hours) * 60);
            if ($minutes === 60) {
                $hours++;
                $minutes = 0;
            }
            return sprintf('%d:%02d', $hours, $minutes);
        };

        $recalcReservationHours = static function (int $reservationId): void {
            $sumStmt = db()->prepare('SELECT COALESCE(SUM(hobbs_hours), 0) FROM reservation_flights WHERE reservation_id = ?');
            $sumStmt->execute([$reservationId]);
            $sum = round((float)$sumStmt->fetchColumn(), 2);
            db()->prepare('UPDATE reservations SET hours = ? WHERE id = ?')->execute([$sum, $reservationId]);
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId);
                exit;
            }

            $action = (string)($_POST['action'] ?? '');
            $flightId = (int)($_POST['flight_id'] ?? 0);

            $flightOwnerStmt = db()->prepare("SELECT rf.id, rf.reservation_id, rf.is_billable
                FROM reservation_flights rf
                JOIN reservations r ON r.id = rf.reservation_id
                WHERE rf.id = ? AND r.aircraft_id = ?");
            $flightOwnerStmt->execute([$flightId, $aircraftId]);
            $flightOwner = $flightOwnerStmt->fetch();

            if (!$flightOwner) {
                flash('error', 'Flug nicht gefunden.');
                header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId);
                exit;
            }

            $reservationId = (int)$flightOwner['reservation_id'];

            if ($action === 'delete_flight') {
                db()->prepare('DELETE FROM reservation_flights WHERE id = ?')->execute([$flightId]);
                $recalcReservationHours($reservationId);
                audit_log('delete', 'reservation_flight', $flightId, ['reservation_id' => $reservationId]);
                flash('success', 'Flug gelöscht.');
                header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId);
                exit;
            }

            if ($action === 'toggle_billable') {
                $newBillable = ((string)($_POST['is_billable'] ?? '1')) === '1' ? 1 : 0;
                $oldBillable = (int)$flightOwner['is_billable'];
                db()->prepare('UPDATE reservation_flights SET is_billable = ? WHERE id = ?')->execute([$newBillable, $flightId]);
                audit_log('update', 'reservation_flight', $flightId, [
                    'reservation_id' => $reservationId,
                    'is_billable_from' => $oldBillable,
                    'is_billable_to' => $newBillable,
                ]);
                flash('success', $newBillable === 1 ? 'Flug ist nun verrechenbar.' : 'Flug ist nun nicht verrechenbar.');
                header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId);
                exit;
            }

            if ($action === 'update_flight') {
                $pilotId = (int)($_POST['pilot_user_id'] ?? 0);
                $from = trim((string)($_POST['from_airfield'] ?? ''));
                $to = trim((string)($_POST['to_airfield'] ?? ''));
                $startTimeRaw = trim((string)($_POST['start_time'] ?? ''));
                $landingTimeRaw = trim((string)($_POST['landing_time'] ?? ''));
                $landingsCount = (int)($_POST['landings_count'] ?? 0);
                $hobbsStartRaw = trim((string)($_POST['hobbs_start'] ?? ''));
                $hobbsEndRaw = trim((string)($_POST['hobbs_end'] ?? ''));

                if ($pilotId <= 0 || $from === '' || $to === '' || $startTimeRaw === '' || $landingTimeRaw === '' || $hobbsStartRaw === '' || $hobbsEndRaw === '' || $landingsCount < 1) {
                    flash('error', 'Bitte alle Felder ausfüllen.');
                    header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId . '&edit_id=' . $flightId);
                    exit;
                }

                $startTs = strtotime($startTimeRaw);
                $landingTs = strtotime($landingTimeRaw);
                if ($startTs === false || $landingTs === false || $landingTs <= $startTs) {
                    flash('error', 'Ungültige Start-/Landezeit.');
                    header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId . '&edit_id=' . $flightId);
                    exit;
                }

                $hobbsStart = $parseHobbsClock($hobbsStartRaw);
                $hobbsEnd = $parseHobbsClock($hobbsEndRaw);
                if ($hobbsStart === null || $hobbsEnd === null) {
                    flash('error', 'Hobbs muss im Format HH:MM sein.');
                    header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId . '&edit_id=' . $flightId);
                    exit;
                }
                if ($hobbsEnd <= $hobbsStart) {
                    flash('error', 'Hobbs bis muss größer als Hobbs von sein.');
                    header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId . '&edit_id=' . $flightId);
                    exit;
                }

                $hobbsHours = round($hobbsEnd - $hobbsStart, 2);
                db()->prepare('UPDATE reservation_flights
                    SET pilot_user_id = ?, from_airfield = ?, to_airfield = ?, start_time = ?, landing_time = ?, landings_count = ?, hobbs_start = ?, hobbs_end = ?, hobbs_hours = ?
                    WHERE id = ?')
                    ->execute([
                        $pilotId,
                        strtoupper($from),
                        strtoupper($to),
                        date('Y-m-d H:i:s', $startTs),
                        date('Y-m-d H:i:s', $landingTs),
                        $landingsCount,
                        $hobbsStart,
                        $hobbsEnd,
                        $hobbsHours,
                        $flightId,
                    ]);
                $recalcReservationHours($reservationId);
                audit_log('update', 'reservation_flight', $flightId, ['reservation_id' => $reservationId, 'hobbs_hours' => $hobbsHours]);
                flash('success', 'Flug aktualisiert.');
                header('Location: index.php?page=aircraft_flights&aircraft_id=' . $aircraftId);
                exit;
            }
        }

        $flightsStmt = db()->prepare("SELECT rf.*, r.id AS reservation_id,
                CONCAT(p.first_name, ' ', p.last_name) AS pilot_name
            FROM reservation_flights rf
            JOIN reservations r ON r.id = rf.reservation_id
            JOIN users p ON p.id = rf.pilot_user_id
            WHERE r.aircraft_id = ?
              AND r.status = 'completed'
            ORDER BY rf.start_time DESC, rf.id DESC");
        $flightsStmt->execute([$aircraftId]);
        $flights = $flightsStmt->fetchAll();
        foreach ($flights as &$flight) {
            $flight['hobbs_start_clock'] = $formatHobbsClock((float)$flight['hobbs_start']);
            $flight['hobbs_end_clock'] = $formatHobbsClock((float)$flight['hobbs_end']);
        }
        unset($flight);

        $pilots = db()->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
            FROM users u
            WHERE u.is_active = 1
              AND EXISTS (
                SELECT 1 FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id AND r.name = 'pilot'
              )
            ORDER BY u.last_name, u.first_name")->fetchAll();
        $editFlightId = (int)($_GET['edit_id'] ?? 0);
        $backPage = has_role('admin') ? 'aircraft' : 'accounting_flights';

        render('Durchgeführte Flüge', 'aircraft_flights', compact('aircraft', 'flights', 'pilots', 'editFlightId', 'backPage'));
        break;

    case 'users':
        require_role('admin');
        $userSearch = trim((string)($_GET['q'] ?? ''));
        $openUserId = (int)($_GET['open_user_id'] ?? 0);
        $showNewUserForm = ((int)($_GET['new'] ?? 0)) === 1;
        $countryOptions = european_countries();
        $usersPageUrl = 'index.php?page=users' . ($userSearch !== '' ? '&q=' . urlencode($userSearch) : '');
        $allGroups = db()->query('SELECT id, name FROM aircraft_groups ORDER BY name')->fetchAll();
        $validGroupIds = array_map(static fn(array $row): int => (int)$row['id'], $allGroups);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: ' . $usersPageUrl);
                exit;
            }

            $action = (string)($_POST['action'] ?? '');

            if ($action === 'create') {
                $firstName = trim((string)($_POST['first_name'] ?? ''));
                $lastName = trim((string)($_POST['last_name'] ?? ''));
                $street = trim((string)($_POST['street'] ?? ''));
                $houseNumber = trim((string)($_POST['house_number'] ?? ''));
                $postalCode = trim((string)($_POST['postal_code'] ?? ''));
                $city = trim((string)($_POST['city'] ?? ''));
                $countryCode = strtoupper(trim((string)($_POST['country_code'] ?? 'CH')));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $roles = array_values(array_filter((array)($_POST['roles'] ?? [])));
                $groupIds = array_values(array_unique(array_map(static fn($id): int => (int)$id, (array)($_POST['group_ids'] ?? []))));
                $groupIds = array_values(array_intersect($validGroupIds, $groupIds));
                $password = (string)($_POST['password'] ?? '');

                $validRoles = ['admin', 'pilot', 'accounting', 'board', 'member'];
                $roles = array_values(array_intersect($validRoles, $roles));

                if ($firstName === '' || $lastName === '' || $email === '' || count($roles) === 0 || strlen($password) < 8) {
                    flash('error', 'Ungültige Eingaben (Passwort min. 8 Zeichen).');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }
                if (!isset($countryOptions[$countryCode])) {
                    $countryCode = 'CH';
                }

                try {
                    db()->beginTransaction();
                    $stmt = db()->prepare('INSERT INTO users (first_name, last_name, street, house_number, postal_code, city, country_code, phone, email, password_hash, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
                    $stmt->execute([$firstName, $lastName, $street, $houseNumber, $postalCode, $city, $countryCode, $phone, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $newId = (int)db()->lastInsertId();

                    $roleInsert = db()->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?');
                    foreach ($roles as $role) {
                        $roleInsert->execute([$newId, $role]);
                    }
                    db()->prepare('DELETE FROM user_aircraft_groups WHERE user_id = ?')->execute([$newId]);
                    if (in_array('pilot', $roles, true) && $groupIds !== []) {
                        $groupInsert = db()->prepare('INSERT INTO user_aircraft_groups (user_id, group_id) VALUES (?, ?)');
                        foreach ($groupIds as $groupId) {
                            $groupInsert->execute([$newId, $groupId]);
                        }
                    }
                    db()->commit();

                    audit_log('create', 'user', $newId, ['email' => $email, 'roles' => $roles, 'group_ids' => $groupIds]);
                    flash('success', 'Benutzer angelegt.');
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Benutzer konnte nicht angelegt werden (E-Mail evtl. bereits vorhanden).');
                }
            }

            if ($action === 'update') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $firstName = trim((string)($_POST['first_name'] ?? ''));
                $lastName = trim((string)($_POST['last_name'] ?? ''));
                $street = trim((string)($_POST['street'] ?? ''));
                $houseNumber = trim((string)($_POST['house_number'] ?? ''));
                $postalCode = trim((string)($_POST['postal_code'] ?? ''));
                $city = trim((string)($_POST['city'] ?? ''));
                $countryCode = strtoupper(trim((string)($_POST['country_code'] ?? 'CH')));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $roles = array_values(array_filter((array)($_POST['roles'] ?? [])));
                $groupIds = array_values(array_unique(array_map(static fn($id): int => (int)$id, (array)($_POST['group_ids'] ?? []))));
                $groupIds = array_values(array_intersect($validGroupIds, $groupIds));
                $isActive = ((string)($_POST['is_active'] ?? '1')) === '1' ? 1 : 0;
                $newPassword = (string)($_POST['new_password'] ?? '');
                $validRoles = ['admin', 'pilot', 'accounting', 'board', 'member'];
                $roles = array_values(array_intersect($validRoles, $roles));

                if ($userId <= 0 || $firstName === '' || $lastName === '' || $email === '' || count($roles) === 0) {
                    flash('error', 'Ungültige Eingaben.');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }
                if (!isset($countryOptions[$countryCode])) {
                    $countryCode = 'CH';
                }

                if ($userId === (int)current_user()['id'] && $isActive === 0) {
                    flash('error', 'Eigener Benutzer kann nicht deaktiviert werden.');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }

                try {
                    db()->beginTransaction();
                    $stmt = db()->prepare('UPDATE users SET first_name = ?, last_name = ?, street = ?, house_number = ?, postal_code = ?, city = ?, country_code = ?, phone = ?, email = ?, is_active = ? WHERE id = ?');
                    $stmt->execute([$firstName, $lastName, $street, $houseNumber, $postalCode, $city, $countryCode, $phone, $email, $isActive, $userId]);

                    db()->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
                    $roleInsert = db()->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?');
                    foreach ($roles as $role) {
                        $roleInsert->execute([$userId, $role]);
                    }

                    db()->prepare('DELETE FROM user_aircraft_groups WHERE user_id = ?')->execute([$userId]);
                    if (in_array('pilot', $roles, true) && $groupIds !== []) {
                        $groupInsert = db()->prepare('INSERT INTO user_aircraft_groups (user_id, group_id) VALUES (?, ?)');
                        foreach ($groupIds as $groupId) {
                            $groupInsert->execute([$userId, $groupId]);
                        }
                    }

                    if ($newPassword !== '') {
                        if (strlen($newPassword) < 8) {
                            db()->rollBack();
                            flash('error', 'Neues Passwort ist zu kurz (min. 8 Zeichen).');
                            header('Location: ' . $usersPageUrl);
                            exit;
                        }
                        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                    }

                    db()->commit();
                    if ($userId === (int)current_user()['id']) {
                        $_SESSION['user']['first_name'] = $firstName;
                        $_SESSION['user']['last_name'] = $lastName;
                        $_SESSION['user']['street'] = $street;
                        $_SESSION['user']['house_number'] = $houseNumber;
                        $_SESSION['user']['postal_code'] = $postalCode;
                        $_SESSION['user']['city'] = $city;
                        $_SESSION['user']['country_code'] = $countryCode;
                        $_SESSION['user']['phone'] = $phone;
                        $_SESSION['user']['email'] = $email;
                        $_SESSION['user']['roles'] = user_roles($userId);
                    }
                    audit_log('update', 'user', $userId, ['email' => $email, 'roles' => $roles, 'is_active' => $isActive, 'group_ids' => $groupIds]);
                    flash('success', 'Benutzer aktualisiert.');
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Benutzer konnte nicht aktualisiert werden.');
                }
            }

            if ($action === 'delete') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $adminId = (int)current_user()['id'];

                if ($userId <= 0) {
                    flash('error', 'Ungültiger Benutzer.');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }

                if ($userId === $adminId) {
                    flash('error', 'Eigener Benutzer kann nicht gelöscht werden.');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }

                try {
                    db()->beginTransaction();

                    // Aktive Reservierungen des Benutzers entfernen.
                    $delResStmt = db()->prepare("DELETE FROM reservations WHERE user_id = ? AND status = 'booked'");
                    $delResStmt->execute([$userId]);

                    // Historische Referenzen auf den löschenden Admin umhängen, damit FK bestehen bleibt.
                    db()->prepare('UPDATE reservations SET created_by = ? WHERE created_by = ?')->execute([$adminId, $userId]);
                    db()->prepare('UPDATE reservations SET cancelled_by = ? WHERE cancelled_by = ?')->execute([$adminId, $userId]);
                    db()->prepare('UPDATE invoices SET created_by = ? WHERE created_by = ?')->execute([$adminId, $userId]);

                    // Wenn historische Daten als Kunde existieren, nicht löschen.
                    $remainingReservationsStmt = db()->prepare('SELECT COUNT(*) FROM reservations WHERE user_id = ?');
                    $remainingReservationsStmt->execute([$userId]);
                    $remainingReservations = (int)$remainingReservationsStmt->fetchColumn();

                    $remainingInvoicesStmt = db()->prepare('SELECT COUNT(*) FROM invoices WHERE user_id = ?');
                    $remainingInvoicesStmt->execute([$userId]);
                    $remainingInvoices = (int)$remainingInvoicesStmt->fetchColumn();

                    if ($remainingReservations > 0 || $remainingInvoices > 0) {
                        db()->rollBack();
                        flash('error', 'Benutzer hat historische Daten (abgeschlossene/stornierte Reservierungen oder Rechnungen) und kann nicht gelöscht werden.');
                        header('Location: ' . $usersPageUrl);
                        exit;
                    }

                    db()->prepare('DELETE FROM aircraft_user_rates WHERE user_id = ?')->execute([$userId]);
                    db()->prepare('DELETE FROM user_aircraft_groups WHERE user_id = ?')->execute([$userId]);
                    db()->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
                    db()->prepare('DELETE FROM audit_logs WHERE actor_user_id = ?')->execute([$userId]);
                    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

                    db()->commit();
                    audit_log('delete', 'user', $userId, ['deleted_active_reservations' => true]);
                    flash('success', 'Benutzer gelöscht (aktive Reservierungen wurden entfernt).');
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Benutzer konnte nicht gelöscht werden.');
                }
            }

            header('Location: ' . $usersPageUrl);
            exit;
        }

        $usersSql = "SELECT u.id, u.first_name, u.last_name, u.street, u.house_number, u.postal_code, u.city, u.country_code, u.phone, u.email, u.is_active,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS roles_csv
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id";
        $usersParams = [];
        if ($userSearch !== '') {
            $usersSql .= ' WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?';
            $like = '%' . $userSearch . '%';
            $usersParams[] = $like;
            $usersParams[] = $like;
            $usersParams[] = $like;
        }
        $usersSql .= ' GROUP BY u.id, u.first_name, u.last_name, u.street, u.house_number, u.postal_code, u.city, u.country_code, u.phone, u.email, u.is_active
            ORDER BY u.last_name, u.first_name';
        $usersStmt = db()->prepare($usersSql);
        $usersStmt->execute($usersParams);
        $users = $usersStmt->fetchAll();
        foreach ($users as &$u) {
            $u['roles'] = $u['roles_csv'] ? explode(',', $u['roles_csv']) : [];
        }
        unset($u);

        $userGroupRows = db()->query('SELECT user_id, group_id FROM user_aircraft_groups')->fetchAll();
        $userGroupIdsByUser = [];
        foreach ($userGroupRows as $row) {
            $uid = (int)$row['user_id'];
            $gid = (int)$row['group_id'];
            if (!isset($userGroupIdsByUser[$uid])) {
                $userGroupIdsByUser[$uid] = [];
            }
            $userGroupIdsByUser[$uid][] = $gid;
        }

        render('Benutzer', 'users', compact('users', 'userSearch', 'openUserId', 'showNewUserForm', 'allGroups', 'userGroupIdsByUser', 'countryOptions'));
        break;

    case 'rates':
        require_role('admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=rates');
                exit;
            }

            $action = (string)($_POST['action'] ?? '');

            if ($action === 'save') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $aircraftId = (int)($_POST['aircraft_id'] ?? 0);
                $hourlyRate = (float)($_POST['hourly_rate'] ?? -1);

                if ($userId <= 0 || $aircraftId <= 0 || $hourlyRate < 0) {
                    flash('error', 'Ungültige Eingaben.');
                    header('Location: index.php?page=rates');
                    exit;
                }

                $stmt = db()->prepare('INSERT INTO aircraft_user_rates (aircraft_id, user_id, hourly_rate) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE hourly_rate = VALUES(hourly_rate)');
                $stmt->execute([$aircraftId, $userId, $hourlyRate]);
                audit_log('upsert', 'aircraft_user_rate', null, ['user_id' => $userId, 'aircraft_id' => $aircraftId, 'hourly_rate' => $hourlyRate]);
                flash('success', 'Preis gespeichert.');
            }

            if ($action === 'delete') {
                $rateId = (int)($_POST['rate_id'] ?? 0);
                if ($rateId > 0) {
                    db()->prepare('DELETE FROM aircraft_user_rates WHERE id = ?')->execute([$rateId]);
                    audit_log('delete', 'aircraft_user_rate', $rateId);
                    flash('success', 'Preiszuordnung gelöscht.');
                }
            }

            header('Location: index.php?page=rates');
            exit;
        }

        $pilots = db()->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
            FROM users u
            WHERE u.is_active = 1
              AND EXISTS (
                SELECT 1 FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id AND r.name = 'pilot'
              )
            ORDER BY u.last_name, u.first_name")->fetchAll();
        $aircraft = db()->query('SELECT id, immatriculation, type, base_hourly_rate, status FROM aircraft ORDER BY immatriculation')->fetchAll();
        $rates = db()->query("SELECT aur.id, aur.hourly_rate, aur.aircraft_id, aur.user_id, a.immatriculation, a.base_hourly_rate,
                CONCAT(u.first_name, ' ', u.last_name) AS pilot_name
            FROM aircraft_user_rates aur
            JOIN aircraft a ON a.id = aur.aircraft_id
            JOIN users u ON u.id = aur.user_id
            ORDER BY u.last_name, a.immatriculation")->fetchAll();
        $editRate = null;
        $editRateId = (int)($_GET['edit_rate_id'] ?? 0);
        if ($editRateId > 0) {
            foreach ($rates as $rateRow) {
                if ((int)$rateRow['id'] === $editRateId) {
                    $editRate = $rateRow;
                    break;
                }
            }
        }

        render('Preise', 'rates', compact('pilots', 'aircraft', 'rates', 'editRate'));
        break;

    case 'reservations':
        $month = $_GET['month'] ?? date('Y-m');
        $monthStart = date('Y-m-01 00:00:00', strtotime($month . '-01'));
        $monthEnd = date('Y-m-t 23:59:59', strtotime($month . '-01'));
        $prefillAircraftId = max(0, (int)($_GET['prefill_aircraft_id'] ?? 0));
        $prefillStartDate = trim((string)($_GET['prefill_start_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillStartDate)) {
            $prefillStartDate = '';
        }
        $canCompleteReservation = static function (int $ownerId): bool {
            return has_role('admin') || (can('reservation.complete.own') && $ownerId === (int)current_user()['id']);
        };
        $groupRestrictedPilot = is_group_restricted_pilot();
        $currentUserId = (int)current_user()['id'];
        $permittedAircraftIds = $groupRestrictedPilot ? permitted_aircraft_ids_for_user($currentUserId) : [];
        $isAircraftPermittedForCurrentUser = static function (int $aircraftId) use ($groupRestrictedPilot, $permittedAircraftIds): bool {
            return !$groupRestrictedPilot || in_array($aircraftId, $permittedAircraftIds, true);
        };
        $overlapStmt = db()->prepare("SELECT COUNT(*) FROM reservations
            WHERE aircraft_id = ?
              AND status <> 'cancelled'
              AND starts_at < ?
              AND ends_at > ?
              AND (? IS NULL OR id <> ?)");

        $reservationMailDataById = static function (int $reservationId): ?array {
            $stmt = db()->prepare("SELECT
                    r.id,
                    r.user_id,
                    r.aircraft_id,
                    r.starts_at,
                    r.ends_at,
                    r.notes,
                    a.immatriculation,
                    a.type AS aircraft_type,
                    u.first_name AS pilot_first_name,
                    u.last_name AS pilot_last_name,
                    u.email AS pilot_email
                FROM reservations r
                JOIN aircraft a ON a.id = r.aircraft_id
                JOIN users u ON u.id = r.user_id
                WHERE r.id = ?");
            $stmt->execute([$reservationId]);
            $row = $stmt->fetch();
            return $row ?: null;
        };

        $buildReservationIcs = static function (array $reservation, string $method, int $sequence): string {
            $timezone = (string)config('app.timezone', 'Europe/Zurich');
            $appUrl = (string)config('app.url', '');
            $host = parse_url($appUrl, PHP_URL_HOST);
            if (!is_string($host) || $host === '') {
                $host = 'plane-manager.local';
            }
            $uid = 'reservation-' . (int)$reservation['id'] . '@' . $host;
            $summary = 'Reservierung ' . (string)$reservation['immatriculation'] . ' / ' . (string)$reservation['aircraft_type'];
            $descriptionParts = [
                'Pilot: ' . trim((string)$reservation['pilot_first_name'] . ' ' . (string)$reservation['pilot_last_name']),
            ];
            if (trim((string)$reservation['notes']) !== '') {
                $descriptionParts[] = 'Notiz: ' . trim((string)$reservation['notes']);
            }
            $description = implode('\n', array_map(static fn(string $value): string => str_replace([',', ';'], ['\\,', '\\;'], $value), $descriptionParts));
            $location = str_replace([',', ';'], ['\\,', '\\;'], (string)$reservation['immatriculation'] . ' / ' . (string)$reservation['aircraft_type']);

            $from = trim((string)config('smtp.from', ''));
            $fromName = trim((string)config('smtp.from_name', ''));
            $organizerLine = '';
            if ($from !== '') {
                $safeFromName = str_replace([',', ';'], ['\\,', '\\;'], $fromName !== '' ? $fromName : 'Plane Manager');
                $organizerLine = "ORGANIZER;CN={$safeFromName}:mailto:{$from}\r\n";
            }
            $attendeeLine = '';
            $attendeeMail = trim((string)($reservation['pilot_email'] ?? ''));
            if ($attendeeMail !== '') {
                $attendeeName = str_replace([',', ';'], ['\\,', '\\;'], trim((string)$reservation['pilot_first_name'] . ' ' . (string)$reservation['pilot_last_name']));
                $attendeeLine = "ATTENDEE;CN={$attendeeName};ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$attendeeMail}\r\n";
            }

            $tz = new DateTimeZone($timezone);
            $utc = new DateTimeZone('UTC');
            $startDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$reservation['starts_at'], $tz)
                ?: new DateTimeImmutable((string)$reservation['starts_at'], $tz);
            $endDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$reservation['ends_at'], $tz)
                ?: new DateTimeImmutable((string)$reservation['ends_at'], $tz);
            $startUtc = $startDt->setTimezone($utc)->format('Ymd\THis\Z');
            $endUtc = $endDt->setTimezone($utc)->format('Ymd\THis\Z');
            $dtStamp = gmdate('Ymd\THis\Z');
            $status = strtoupper($method) === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED';

            $lines = [
                'BEGIN:VCALENDAR',
                'PRODID:-//Plane Manager//Reservation//DE',
                'VERSION:2.0',
                'CALSCALE:GREGORIAN',
                'METHOD:' . strtoupper($method),
                'BEGIN:VEVENT',
                'UID:' . $uid,
                'DTSTAMP:' . $dtStamp,
                'SEQUENCE:' . max(0, $sequence),
                'SUMMARY:' . str_replace([',', ';'], ['\\,', '\\;'], $summary),
                'STATUS:' . $status,
                'DTSTART:' . $startUtc,
                'DTEND:' . $endUtc,
                'LOCATION:' . $location,
                'DESCRIPTION:' . $description,
            ];
            if ($organizerLine !== '') {
                $lines[] = rtrim($organizerLine);
            }
            if ($attendeeLine !== '') {
                $lines[] = rtrim($attendeeLine);
            }
            $lines[] = 'END:VEVENT';
            $lines[] = 'END:VCALENDAR';

            return implode("\r\n", $lines) . "\r\n";
        };

        $sendReservationMail = static function (array $reservation, string $event) use ($buildReservationIcs): array {
            $to = trim((string)$reservation['pilot_email']);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Keine gültige Pilot-E-Mail vorhanden.'];
            }

            $issuer = [
                'name' => (string)config('invoice.issuer.name', ''),
                'street' => (string)config('invoice.issuer.street', ''),
                'house_number' => (string)config('invoice.issuer.house_number', ''),
                'postal_code' => (string)config('invoice.issuer.postal_code', ''),
                'city' => (string)config('invoice.issuer.city', ''),
                'country' => (string)config('invoice.issuer.country', 'Schweiz'),
                'email' => (string)config('invoice.issuer.email', ''),
                'phone' => (string)config('invoice.issuer.phone', ''),
                'website' => (string)config('invoice.issuer.website', ''),
            ];
            $issuerFull = trim(implode("\n", array_filter([
                (string)$issuer['name'],
                trim((string)$issuer['street'] . ' ' . (string)$issuer['house_number']),
                trim((string)$issuer['postal_code'] . ' ' . (string)$issuer['city']),
                (string)$issuer['country'],
                (string)$issuer['email'] !== '' ? 'E-Mail: ' . (string)$issuer['email'] : '',
                (string)$issuer['phone'] !== '' ? 'Telefon: ' . (string)$issuer['phone'] : '',
                (string)$issuer['website'] !== '' ? (string)$issuer['website'] : '',
            ])));

            $startDisplay = date('d.m.Y H:i', strtotime((string)$reservation['starts_at']));
            $endDisplay = date('d.m.Y H:i', strtotime((string)$reservation['ends_at']));
            $notes = trim((string)$reservation['notes']);

            $prefix = match ($event) {
                'create' => 'mail_reservation',
                'update' => 'mail_reservation_update',
                'cancel' => 'mail_reservation_cancel',
                default => 'mail_reservation',
            };
            $subjectTemplatePath = __DIR__ . '/templates/' . $prefix . '_subject.txt';
            $bodyTemplatePath = __DIR__ . '/templates/' . $prefix . '_body.txt';
            $fallbackSubject = match ($event) {
                'update' => '{issuer.name}: Reservierung geändert {reservation.aircraft} {reservation.start}-{reservation.end}',
                'cancel' => '{issuer.name}: Reservierung storniert {reservation.aircraft} {reservation.start}-{reservation.end}',
                default => '{issuer.name}: Neue Reservierung {reservation.aircraft} {reservation.start}-{reservation.end}',
            };
            $fallbackBody = "Hallo {customer.first_name}\n\nFlugzeug: {reservation.aircraft}\nStart: {reservation.start}\nEnde: {reservation.end}\nNotiz: {reservation.notes_or_dash}\n\nLiebe Grüsse,\n{issuer.full}";

            $subjectTemplate = is_file($subjectTemplatePath) ? (string)file_get_contents($subjectTemplatePath) : $fallbackSubject;
            $bodyTemplate = is_file($bodyTemplatePath) ? (string)file_get_contents($bodyTemplatePath) : $fallbackBody;

            $replacements = [
                '{issuer.name}' => (string)$issuer['name'],
                '{issuer.full}' => $issuerFull,
                '{customer.first_name}' => (string)$reservation['pilot_first_name'],
                '{customer.last_name}' => (string)$reservation['pilot_last_name'],
                '{customer.name}' => trim((string)$reservation['pilot_first_name'] . ' ' . (string)$reservation['pilot_last_name']),
                '{reservation.id}' => (string)$reservation['id'],
                '{reservation.aircraft}' => (string)$reservation['immatriculation'] . ' / ' . (string)$reservation['aircraft_type'],
                '{reservation.immatriculation}' => (string)$reservation['immatriculation'],
                '{reservation.aircraft_type}' => (string)$reservation['aircraft_type'],
                '{reservation.start}' => $startDisplay,
                '{reservation.end}' => $endDisplay,
                '{reservation.notes}' => $notes,
                '{reservation.notes_or_dash}' => $notes !== '' ? $notes : '-',
            ];

            $subject = trim(strtr($subjectTemplate, $replacements));
            $textBody = strtr($bodyTemplate, $replacements);
            $htmlBody = nl2br(h($textBody), false);

            $sendResult = ['ok' => false, 'error' => 'Unbekannter Versandfehler'];
            $auditMeta = ['to' => $to, 'provider' => 'smtp'];
            if ($event === 'cancel') {
                $sendResult = smtp_send_mail($to, $subject, $htmlBody, $textBody);
            } else {
                $method = 'REQUEST';
                $sequence = (int)round(microtime(true) * 1000);
                $ics = $buildReservationIcs($reservation, $method, $sequence);
                $icsName = 'reservation-' . (int)$reservation['id'] . '.ics';
                $sendResult = smtp_send_mail(
                    $to,
                    $subject,
                    $htmlBody,
                    $textBody,
                    [[
                        'filename' => $icsName,
                        'mime' => 'application/ics',
                        'content' => $ics,
                    ]],
                    [
                        'filename' => $icsName,
                        'method' => $method,
                        'content' => $ics,
                    ]
                );
                $auditMeta['ics_method'] = $method;
            }
            if (!$sendResult['ok']) {
                return ['ok' => false, 'error' => (string)$sendResult['error']];
            }
            if (!empty($sendResult['skipped'])) {
                return ['ok' => true, 'skipped' => true];
            }

            audit_log('mail_' . $event, 'reservation', (int)$reservation['id'], $auditMeta);
            return ['ok' => true];
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=reservations&month=' . urlencode($month));
                exit;
            }

            $action = $_POST['action'] ?? '';
            if ($action === 'create' && !can('reservation.create')) {
                flash('error', 'Keine Berechtigung für Reservierungen.');
                header('Location: index.php?page=reservations&month=' . urlencode($month));
                exit;
            }

            if ($action === 'create') {
                $aircraftId = (int)$_POST['aircraft_id'];
                if (has_role('admin')) {
                    $userId = (int)($_POST['user_id'] ?? (int)current_user()['id']);
                } elseif (has_role('pilot')) {
                    $userId = (int)current_user()['id'];
                } else {
                    $userId = (int)($_POST['user_id'] ?? 0);
                }
                $start = (string)($_POST['starts_at'] ?? '');
                $end = (string)($_POST['ends_at'] ?? '');
                if ($start === '' || $end === '') {
                    $startDate = (string)($_POST['start_date'] ?? '');
                    $startHour = (int)($_POST['start_hour'] ?? -1);
                    $startMinute = (int)($_POST['start_minute'] ?? -1);
                    $endDate = (string)($_POST['end_date'] ?? '');
                    $endHour = (int)($_POST['end_hour'] ?? -1);
                    $endMinute = (int)($_POST['end_minute'] ?? -1);

                    $start = sprintf('%s %02d:%02d:00', $startDate, $startHour, $startMinute);
                    $end = sprintf('%s %02d:%02d:00', $endDate, $endHour, $endMinute);
                }
                $notes = trim((string)$_POST['notes']);
                $startTs = strtotime($start);
                $endTs = strtotime($end);

                if ($startTs === false || $endTs === false) {
                    flash('error', 'Ungültiges Datum/Zeit-Format.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $isQuarterStep = ((int)date('i', $startTs) % 15 === 0) && ((int)date('i', $endTs) % 15 === 0);
                if (!$isQuarterStep) {
                    flash('error', 'Bitte nur 15-Minuten-Schritte verwenden.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                if ($startTs <= time() || $endTs <= time()) {
                    flash('error', 'Reservierungen sind nur in der Zukunft erlaubt.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                if (!$isAircraftPermittedForCurrentUser($aircraftId)) {
                    flash('error', 'Dieses Flugzeug ist für Sie nicht reservierbar.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $aircraftStatusStmt = db()->prepare('SELECT status FROM aircraft WHERE id = ?');
                $aircraftStatusStmt->execute([$aircraftId]);
                $aircraftStatus = (string)$aircraftStatusStmt->fetchColumn();
                if ($aircraftStatus !== 'active') {
                    flash('error', 'Dieses Flugzeug ist nicht aktiv und kann nicht reserviert werden.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $hours = max(0, ($endTs - $startTs) / 3600);

                if ($hours <= 0) {
                    flash('error', 'Ungültige Zeitspanne.');
                } else {
                    $overlapStmt->execute([$aircraftId, date('Y-m-d H:i:s', $endTs), date('Y-m-d H:i:s', $startTs), null, null]);
                    if ((int)$overlapStmt->fetchColumn() > 0) {
                        flash('error', 'Überschneidung: Für dieses Flugzeug existiert bereits eine Reservierung in diesem Zeitraum.');
                        header('Location: index.php?page=reservations&month=' . urlencode($month));
                        exit;
                    }

                    $stmt = db()->prepare('INSERT INTO reservations (aircraft_id, user_id, starts_at, ends_at, hours, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$aircraftId, $userId, $start, $end, $hours, $notes, (int)current_user()['id']]);
                    $newId = (int)db()->lastInsertId();
                    audit_log('create', 'reservation', $newId, ['hours' => $hours]);
                    $mailData = $reservationMailDataById($newId);
                    if ($mailData) {
                        $mailResult = $sendReservationMail($mailData, 'create');
                        if (!$mailResult['ok']) {
                            flash('error', 'Reservierung erstellt, E-Mail fehlgeschlagen: ' . (string)$mailResult['error']);
                        }
                    }
                    flash('success', 'Reservierung erstellt.');
                }
            }

            if ($action === 'update') {
                $reservationId = (int)($_POST['reservation_id'] ?? 0);
                $reservationStmt = db()->prepare('SELECT * FROM reservations WHERE id = ?');
                $reservationStmt->execute([$reservationId]);
                $reservation = $reservationStmt->fetch();

                if (!$reservation || $reservation['status'] !== 'booked') {
                    flash('error', 'Reservierung nicht bearbeitbar.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $isOwner = (int)$reservation['user_id'] === (int)current_user()['id'];
                if (!(has_role('admin') || $isOwner)) {
                    flash('error', 'Keine Berechtigung zum Bearbeiten.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $aircraftId = (int)($_POST['aircraft_id'] ?? 0);
                $userId = has_role('admin') ? (int)($_POST['user_id'] ?? $reservation['user_id']) : (int)$reservation['user_id'];
                $startDate = (string)($_POST['start_date'] ?? '');
                $startHour = (int)($_POST['start_hour'] ?? -1);
                $startMinute = (int)($_POST['start_minute'] ?? -1);
                $endDate = (string)($_POST['end_date'] ?? '');
                $endHour = (int)($_POST['end_hour'] ?? -1);
                $endMinute = (int)($_POST['end_minute'] ?? -1);
                $start = sprintf('%s %02d:%02d:00', $startDate, $startHour, $startMinute);
                $end = sprintf('%s %02d:%02d:00', $endDate, $endHour, $endMinute);
                $notes = trim((string)($_POST['notes'] ?? ''));

                $startTs = strtotime($start);
                $endTs = strtotime($end);
                if ($startTs === false || $endTs === false || $endTs <= $startTs) {
                    flash('error', 'Ungültige Zeitspanne.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&edit_id=' . $reservationId);
                    exit;
                }

                if ($startTs <= time() || $endTs <= time()) {
                    flash('error', 'Reservierungen sind nur in der Zukunft erlaubt.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&edit_id=' . $reservationId);
                    exit;
                }

                $isSameAircraft = $aircraftId === (int)$reservation['aircraft_id'];
                $canUseAircraftForUpdate = $isSameAircraft || $isAircraftPermittedForCurrentUser($aircraftId);
                if (!$canUseAircraftForUpdate) {
                    flash('error', 'Dieses Flugzeug ist für Sie nicht reservierbar.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&edit_id=' . $reservationId);
                    exit;
                }

                $isQuarterStep = ((int)date('i', $startTs) % 15 === 0) && ((int)date('i', $endTs) % 15 === 0);
                if (!$isQuarterStep) {
                    flash('error', 'Bitte nur 15-Minuten-Schritte verwenden.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&edit_id=' . $reservationId);
                    exit;
                }

                $aircraftStatusStmt = db()->prepare('SELECT status FROM aircraft WHERE id = ?');
                $aircraftStatusStmt->execute([$aircraftId]);
                if ((string)$aircraftStatusStmt->fetchColumn() !== 'active') {
                    flash('error', 'Dieses Flugzeug ist nicht aktiv und kann nicht reserviert werden.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&edit_id=' . $reservationId);
                    exit;
                }

                $overlapStmt->execute([$aircraftId, date('Y-m-d H:i:s', $endTs), date('Y-m-d H:i:s', $startTs), $reservationId, $reservationId]);
                if ((int)$overlapStmt->fetchColumn() > 0) {
                    flash('error', 'Überschneidung: Für dieses Flugzeug existiert bereits eine Reservierung in diesem Zeitraum.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&edit_id=' . $reservationId);
                    exit;
                }

                $hours = round(($endTs - $startTs) / 3600, 2);
                $updateStmt = db()->prepare('UPDATE reservations SET aircraft_id = ?, user_id = ?, starts_at = ?, ends_at = ?, hours = ?, notes = ? WHERE id = ?');
                $updateStmt->execute([$aircraftId, $userId, date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs), $hours, $notes, $reservationId]);
                audit_log('update', 'reservation', $reservationId, ['hours' => $hours]);
                $mailData = $reservationMailDataById($reservationId);
                if ($mailData) {
                    $mailResult = $sendReservationMail($mailData, 'update');
                    if (!$mailResult['ok']) {
                        flash('error', 'Reservierung aktualisiert, E-Mail fehlgeschlagen: ' . (string)$mailResult['error']);
                    }
                }
                flash('success', 'Reservierung aktualisiert.');
            }

            if ($action === 'delete') {
                $reservationId = (int)($_POST['reservation_id'] ?? 0);
                $reservationStmt = db()->prepare('SELECT user_id, status FROM reservations WHERE id = ?');
                $reservationStmt->execute([$reservationId]);
                $reservation = $reservationStmt->fetch();

                if (!$reservation || $reservation['status'] !== 'booked') {
                    flash('error', 'Reservierung kann nicht gelöscht werden.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $isOwner = (int)$reservation['user_id'] === (int)current_user()['id'];
                if (!(has_role('admin') || $isOwner)) {
                    flash('error', 'Keine Berechtigung zum Löschen.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $mailData = $reservationMailDataById($reservationId);
                db()->prepare("UPDATE reservations SET status = 'cancelled', cancelled_by = ? WHERE id = ?")
                    ->execute([(int)current_user()['id'], $reservationId]);
                audit_log('cancel', 'reservation', $reservationId);
                if ($mailData) {
                    $mailResult = $sendReservationMail($mailData, 'cancel');
                    if (!$mailResult['ok']) {
                        flash('error', 'Reservierung gelöscht, E-Mail fehlgeschlagen: ' . (string)$mailResult['error']);
                    }
                }
                flash('success', 'Reservierung gelöscht.');
            }

            if ($action === 'complete_save') {
                $reservationId = (int)($_POST['reservation_id'] ?? 0);
                $completeMode = (string)($_POST['complete_mode'] ?? 'finish');
                $isWithoutAdditionalFlight = $completeMode === 'finish_without_flight';
                $isCompleteNow = $completeMode !== 'next';
                $reservationStmt = db()->prepare('SELECT * FROM reservations WHERE id = ?');
                $reservationStmt->execute([$reservationId]);
                $reservation = $reservationStmt->fetch();

                if (!$reservation || $reservation['status'] !== 'booked') {
                    flash('error', 'Reservierung ist nicht mehr offen.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $ownerId = (int)$reservation['user_id'];
                if (!$canCompleteReservation($ownerId)) {
                    flash('error', 'Keine Berechtigung.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                if ($isWithoutAdditionalFlight) {
                    $savedFlightCountStmt = db()->prepare('SELECT COUNT(*) FROM reservation_flights WHERE reservation_id = ?');
                    $savedFlightCountStmt->execute([$reservationId]);
                    $savedFlightCount = (int)$savedFlightCountStmt->fetchColumn();
                    if ($savedFlightCount === 0) {
                        flash('error', 'Mindestens ein Flug muss erfasst werden.');
                        header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                        exit;
                    }

                    $totalStmt = db()->prepare('SELECT COALESCE(SUM(hobbs_hours), 0) FROM reservation_flights WHERE reservation_id = ?');
                    $totalStmt->execute([$reservationId]);
                    $totalHobbsHours = round((float)$totalStmt->fetchColumn(), 2);

                    db()->prepare("UPDATE reservations SET status = 'completed', hours = ? WHERE id = ?")
                        ->execute([$totalHobbsHours, $reservationId]);

                    audit_log('complete', 'reservation', $reservationId, ['flights' => $savedFlightCount, 'hobbs_hours' => $totalHobbsHours, 'mode' => 'without_additional_flight']);
                    flash('success', 'Reservierung ohne zusätzlichen Flug abgeschlossen.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month));
                    exit;
                }

                $flightPilotIds = (array)($_POST['flight_pilot_id'] ?? []);
                $flightFrom = (array)($_POST['flight_from'] ?? []);
                $flightTo = (array)($_POST['flight_to'] ?? []);
                $flightStart = (array)($_POST['flight_start_time'] ?? []);
                $flightLanding = (array)($_POST['flight_landing_time'] ?? []);
                $flightLandingsCount = (array)($_POST['flight_landings_count'] ?? []);
                $flightHobbsStart = (array)($_POST['flight_hobbs_start'] ?? []);
                $flightHobbsEnd = (array)($_POST['flight_hobbs_end'] ?? []);
                $parseHobbs = static function (string $value): ?float {
                    $trimmed = trim($value);
                    if (!preg_match('/^\d+:[0-5]\d$/', $trimmed)) {
                        return null;
                    }

                    [$hours, $minutes] = explode(':', $trimmed, 2);
                    return ((int)$hours) + (((int)$minutes) / 60);
                };

                $count = max(
                    count($flightPilotIds),
                    count($flightFrom),
                    count($flightTo),
                    count($flightStart),
                    count($flightLanding),
                    count($flightLandingsCount),
                    count($flightHobbsStart),
                    count($flightHobbsEnd)
                );

                $flights = [];
                for ($i = 0; $i < $count; $i++) {
                    $pilotId = (int)($flightPilotIds[$i] ?? 0);
                    $from = trim((string)($flightFrom[$i] ?? ''));
                    $to = trim((string)($flightTo[$i] ?? ''));
                    $startTime = trim((string)($flightStart[$i] ?? ''));
                    $landingTime = trim((string)($flightLanding[$i] ?? ''));
                    $landingsCount = (int)($flightLandingsCount[$i] ?? 0);
                    $hobbsStart = (string)($flightHobbsStart[$i] ?? '');
                    $hobbsEnd = (string)($flightHobbsEnd[$i] ?? '');

                    $allEmpty = $pilotId === 0 && $from === '' && $to === '' && $startTime === '' && $landingTime === '' && $landingsCount === 0 && $hobbsStart === '' && $hobbsEnd === '';
                    if ($allEmpty) {
                        continue;
                    }

                    if ($pilotId <= 0 || $from === '' || $to === '' || $startTime === '' || $landingTime === '' || $hobbsStart === '' || $hobbsEnd === '' || $landingsCount < 1) {
                        flash('error', 'Bitte alle Flugfelder vollständig ausfüllen.');
                        header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                        exit;
                    }

                    $startTs = strtotime($startTime);
                    $landingTs = strtotime($landingTime);
                    if ($startTs === false || $landingTs === false || $landingTs <= $startTs) {
                        flash('error', 'Ungültige Start-/Landezeit in Flugzeile ' . ($i + 1) . '.');
                        header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                        exit;
                    }

                    $hobbsStartVal = $parseHobbs($hobbsStart);
                    $hobbsEndVal = $parseHobbs($hobbsEnd);
                    if ($hobbsStartVal === null || $hobbsEndVal === null) {
                        flash('error', 'Hobbs muss im Format HH:MM sein (Zeile ' . ($i + 1) . ').');
                        header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                        exit;
                    }

                    if ($hobbsEndVal <= $hobbsStartVal) {
                        flash('error', 'Hobbs bis muss groesser als Hobbs von sein (Zeile ' . ($i + 1) . ').');
                        header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                        exit;
                    }

                    $flights[] = [
                        'pilot_id' => $pilotId,
                        'from' => $from,
                        'to' => $to,
                        'start_time' => date('Y-m-d H:i:s', $startTs),
                        'landing_time' => date('Y-m-d H:i:s', $landingTs),
                        'landings_count' => $landingsCount,
                        'hobbs_start' => $hobbsStartVal,
                        'hobbs_end' => $hobbsEndVal,
                        'hobbs_hours' => round($hobbsEndVal - $hobbsStartVal, 2),
                    ];
                }

                if (count($flights) === 0) {
                    if ($isCompleteNow) {
                        $savedFlightCountStmt = db()->prepare('SELECT COUNT(*) FROM reservation_flights WHERE reservation_id = ?');
                        $savedFlightCountStmt->execute([$reservationId]);
                        $savedFlightCount = (int)$savedFlightCountStmt->fetchColumn();
                        if ($savedFlightCount === 0) {
                            flash('error', 'Mindestens ein Flug muss erfasst werden.');
                            header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                            exit;
                        }
                    } else {
                        flash('error', 'Mindestens ein Flug muss erfasst werden.');
                        header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                        exit;
                    }
                }

                db()->beginTransaction();
                try {
                    $insertFlightStmt = db()->prepare('INSERT INTO reservation_flights
                        (reservation_id, pilot_user_id, from_airfield, to_airfield, start_time, landing_time, landings_count, hobbs_start, hobbs_end, hobbs_hours, is_billable)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');

                    foreach ($flights as $flight) {
                        $insertFlightStmt->execute([
                            $reservationId,
                            $flight['pilot_id'],
                            $flight['from'],
                            $flight['to'],
                            $flight['start_time'],
                            $flight['landing_time'],
                            $flight['landings_count'],
                            $flight['hobbs_start'],
                            $flight['hobbs_end'],
                            $flight['hobbs_hours'],
                        ]);
                    }

                    $totalStmt = db()->prepare('SELECT COALESCE(SUM(hobbs_hours), 0) FROM reservation_flights WHERE reservation_id = ?');
                    $totalStmt->execute([$reservationId]);
                    $totalHobbsHours = round((float)$totalStmt->fetchColumn(), 2);

                    if ($isCompleteNow) {
                        db()->prepare("UPDATE reservations SET status = 'completed', hours = ? WHERE id = ?")
                            ->execute([$totalHobbsHours, $reservationId]);
                    }
                    db()->commit();

                    if ($isCompleteNow) {
                        audit_log('complete', 'reservation', $reservationId, ['flights' => count($flights), 'hobbs_hours' => $totalHobbsHours]);
                        flash('success', 'Reservierung abgeschlossen und Flüge gespeichert.');
                    } else {
                        audit_log('update', 'reservation_flights', $reservationId, ['flights_added' => count($flights)]);
                        flash('success', 'Flug gespeichert. Nächsten Flug erfassen.');
                    }
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Flugerfassung konnte nicht gespeichert werden.');
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                    exit;
                }

                if (!$isCompleteNow) {
                    header('Location: index.php?page=reservations&month=' . urlencode($month) . '&complete_id=' . $reservationId);
                    exit;
                }
            }

            header('Location: index.php?page=reservations&month=' . urlencode($month));
            exit;
        }

        if ($groupRestrictedPilot) {
            $aircraftStmt = db()->prepare("SELECT a.id, a.immatriculation, a.type, a.status, a.start_hobbs, a.start_landings
                FROM aircraft a
                WHERE a.status = 'active'
                  AND (
                    (a.aircraft_group_id IS NOT NULL AND EXISTS (
                        SELECT 1 FROM user_aircraft_groups uag
                        WHERE uag.group_id = a.aircraft_group_id
                          AND uag.user_id = ?
                    ))
                    OR EXISTS (
                        SELECT 1 FROM reservations r2
                        WHERE r2.aircraft_id = a.id
                          AND r2.user_id = ?
                          AND r2.status = 'booked'
                    )
                  )
                ORDER BY a.immatriculation");
            $aircraftStmt->execute([$currentUserId, $currentUserId]);
            $aircraft = $aircraftStmt->fetchAll();
        } else {
            $aircraft = db()->query("SELECT id, immatriculation, type, status, start_hobbs, start_landings FROM aircraft WHERE status = 'active' ORDER BY immatriculation")->fetchAll();
        }
        if ($prefillAircraftId > 0 && !$isAircraftPermittedForCurrentUser($prefillAircraftId)) {
            $prefillAircraftId = 0;
        }
        $users = db()->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
            FROM users u
            WHERE u.is_active = 1
              AND EXISTS (
                SELECT 1 FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id AND r.name = 'pilot'
              )
            ORDER BY u.last_name")->fetchAll();

        $sql = 'SELECT r.*, a.immatriculation, a.start_hobbs, a.start_landings, CONCAT(u.first_name, " ", u.last_name) AS pilot_name
                FROM reservations r
                JOIN aircraft a ON a.id = r.aircraft_id
                JOIN users u ON u.id = r.user_id
                WHERE r.starts_at BETWEEN ? AND ?
                  AND r.status = "booked"';
        $params = [$monthStart, $monthEnd];

        if (has_role('pilot') && !has_role('admin') && !has_role('accounting')) {
            $sql .= ' AND r.user_id = ?';
            $params[] = (int)current_user()['id'];
        }
        $sql .= ' ORDER BY r.starts_at ASC, r.id ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $reservations = $stmt->fetchAll();
        $editReservation = null;
        $editId = (int)($_GET['edit_id'] ?? 0);
        if ($editId > 0) {
            foreach ($reservations as $row) {
                if ((int)$row['id'] === $editId) {
                    $isOwner = (int)$row['user_id'] === (int)current_user()['id'];
                    $isFuture = strtotime((string)$row['starts_at']) > time();
                    if ((has_role('admin') || $isOwner) && $isFuture) {
                        $editReservation = $row;
                    }
                    break;
                }
            }
        }

        $completeReservation = null;
        $completeDefaultHobbsStart = '';
        $completeDefaultLandings = 1;
        $completeDefaultFromAirfield = '';
        $completeLastReservationFlight = null;
        $completeId = (int)($_GET['complete_id'] ?? 0);
        if ($completeId > 0) {
            foreach ($reservations as $row) {
                if ((int)$row['id'] === $completeId && $row['status'] === 'booked') {
                    if ($canCompleteReservation((int)$row['user_id'])) {
                        $completeReservation = $row;
                        $lastCurrentReservationStmt = db()->prepare("SELECT rf.hobbs_start, rf.hobbs_end, rf.from_airfield, rf.to_airfield,
                                rf.start_time, rf.landing_time, rf.landings_count, rf.pilot_user_id, CONCAT(u.first_name, ' ', u.last_name) AS pilot_name
                            FROM reservation_flights rf
                            JOIN users u ON u.id = rf.pilot_user_id
                            WHERE rf.reservation_id = ?
                            ORDER BY rf.id DESC
                            LIMIT 1");
                        $lastCurrentReservationStmt->execute([(int)$row['id']]);
                        $completeLastReservationFlight = $lastCurrentReservationStmt->fetch() ?: null;
                        $lastFlight = $completeLastReservationFlight;

                        if (!$lastFlight) {
                            $lastAircraftStmt = db()->prepare("SELECT rf.hobbs_end, rf.to_airfield
                                FROM reservation_flights rf
                                JOIN reservations r2 ON r2.id = rf.reservation_id
                                WHERE r2.aircraft_id = ?
                                ORDER BY rf.id DESC
                                LIMIT 1");
                            $lastAircraftStmt->execute([(int)$row['aircraft_id']]);
                            $lastFlight = $lastAircraftStmt->fetch();
                        }

                        if ($lastFlight) {
                            $lastHobbsEnd = (float)$lastFlight['hobbs_end'];
                            $hours = (int)floor($lastHobbsEnd);
                            $minutes = (int)round(($lastHobbsEnd - $hours) * 60);
                            if ($minutes === 60) {
                                $hours++;
                                $minutes = 0;
                            }
                            $completeDefaultHobbsStart = sprintf('%d:%02d', $hours, $minutes);
                            $completeDefaultFromAirfield = strtoupper((string)($lastFlight['to_airfield'] ?? ''));
                        } else {
                            $startHobbs = (float)($row['start_hobbs'] ?? 0);
                            $hours = (int)floor($startHobbs);
                            $minutes = (int)round(($startHobbs - $hours) * 60);
                            if ($minutes === 60) {
                                $hours++;
                                $minutes = 0;
                            }
                            $completeDefaultHobbsStart = sprintf('%d:%02d', $hours, $minutes);
                            $completeDefaultLandings = 1;
                        }
                    }
                    break;
                }
            }
        }

        render('Reservierungen', 'reservations', compact(
            'reservations',
            'aircraft',
            'users',
            'month',
            'editReservation',
            'completeReservation',
            'completeDefaultHobbsStart',
            'completeDefaultLandings',
            'completeDefaultFromAirfield',
            'completeLastReservationFlight',
            'prefillAircraftId',
            'prefillStartDate'
        ));
        break;

    case 'invoices':
        require_role('admin', 'accounting');
        $nextInvoiceNumberForYear = static function (int $year): string {
            $prefix = 'R' . $year . '-';
            $stmt = db()->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number, 7) AS UNSIGNED)), 0)
                FROM invoices
                WHERE invoice_number LIKE ?");
            $stmt->execute([$prefix . '%']);
            $next = ((int)$stmt->fetchColumn()) + 1;
            return sprintf('R%d-%06d', $year, $next);
        };

        $collectBillableFlightRows = static function (int $userId, ?string $dateFrom = null, ?string $dateTo = null): array {
            $sql = "SELECT
                    rf.id AS flight_id,
                    r.id AS reservation_id,
                    r.aircraft_id,
                    a.immatriculation,
                    a.type AS aircraft_type,
                    a.base_hourly_rate,
                    rf.start_time,
                    rf.landing_time,
                    rf.from_airfield,
                    rf.to_airfield,
                    rf.hobbs_hours
                FROM reservation_flights rf
                JOIN reservations r ON r.id = rf.reservation_id
                JOIN aircraft a ON a.id = r.aircraft_id
                WHERE r.status = 'completed'
                  AND r.invoice_id IS NULL
                  AND rf.is_billable = 1
                  AND rf.pilot_user_id = ?";
            $params = [$userId];
            if ($dateFrom !== null && $dateTo !== null) {
                $sql .= ' AND DATE(rf.start_time) BETWEEN ? AND ?';
                $params[] = $dateFrom;
                $params[] = $dateTo;
            }
            $sql .= ' ORDER BY rf.start_time ASC, rf.id ASC';
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            return $rows;
        };

        $collectOpenCredits = static function (int $userId): array {
            $stmt = db()->prepare("SELECT id, user_id, credit_date, amount, description, notes
                FROM credits
                WHERE user_id = ?
                  AND invoice_id IS NULL
                ORDER BY credit_date ASC, id ASC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        };

        $loadInvoiceDocumentData = static function (int $invoiceId): ?array {
            $stmt = db()->prepare('SELECT i.*, CONCAT(u.first_name, " ", u.last_name) AS customer_name, u.first_name, u.last_name, u.email,
                    u.street, u.house_number, u.postal_code, u.city, u.country_code, u.phone
                FROM invoices i
                JOIN users u ON u.id = i.user_id
                WHERE i.id = ?');
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();
            if (!$invoice) {
                return null;
            }

            $itemsStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY flight_date ASC, id ASC');
            $itemsStmt->execute([$invoiceId]);
            $items = $itemsStmt->fetchAll();

            $creditsStmt = db()->prepare('SELECT id, credit_date, amount, description, notes
                FROM credits
                WHERE invoice_id = ?
                ORDER BY credit_date ASC, id ASC');
            $creditsStmt->execute([$invoiceId]);
            $credits = $creditsStmt->fetchAll();

            $countryOptions = european_countries();
            $issuer = [
                'name' => (string)config('invoice.issuer.name', ''),
                'street' => (string)config('invoice.issuer.street', ''),
                'house_number' => (string)config('invoice.issuer.house_number', ''),
                'postal_code' => (string)config('invoice.issuer.postal_code', ''),
                'city' => (string)config('invoice.issuer.city', ''),
                'country' => (string)config('invoice.issuer.country', 'Schweiz'),
                'email' => (string)config('invoice.issuer.email', ''),
                'phone' => (string)config('invoice.issuer.phone', ''),
                'website' => (string)config('invoice.issuer.website', ''),
            ];
            $bank = [
                'recipient' => (string)config('invoice.bank.recipient', ''),
                'iban' => (string)config('invoice.bank.iban', ''),
                'bic' => (string)config('invoice.bank.bic', ''),
                'bank_name' => (string)config('invoice.bank.bank_name', ''),
                'bank_address' => (string)config('invoice.bank.bank_address', ''),
            ];
            $vat = [
                'enabled' => (bool)config('invoice.vat.enabled', false),
                'rate_percent' => (float)config('invoice.vat.rate_percent', 0),
                'uid' => (string)config('invoice.vat.uid', ''),
            ];

            $paymentTargetDays = (int)config('invoice.payment_target_days', 30);
            $invoiceMeta = [
                'title' => (string)config('invoice.title', 'Rechnung'),
                'currency' => (string)config('invoice.currency', 'CHF'),
                'payment_target_days' => $paymentTargetDays,
                'due_date' => date('d.m.Y', strtotime((string)$invoice['created_at'] . ' +' . $paymentTargetDays . ' days')),
            ];

            $logoPathConfig = trim((string)config('invoice.logo_path', 'logo.png'));
            $logoFilesystemPath = __DIR__ . '/' . ltrim($logoPathConfig, '/');
            $logoPublicPath = is_file($logoFilesystemPath) ? $logoPathConfig : '';

            $customerAddress = [
                'name' => (string)$invoice['customer_name'],
                'street_line' => trim((string)$invoice['street'] . ' ' . (string)$invoice['house_number']),
                'city_line' => trim((string)$invoice['postal_code'] . ' ' . (string)$invoice['city']),
                'country' => $countryOptions[(string)$invoice['country_code']] ?? (string)$invoice['country_code'],
                'email' => (string)$invoice['email'],
                'phone' => (string)($invoice['phone'] ?? ''),
            ];

            $flightsSubtotal = array_reduce($items, static function (float $carry, array $item): float {
                return $carry + (float)$item['line_total'];
            }, 0.0);
            $creditsTotal = array_reduce($credits, static function (float $carry, array $credit): float {
                return $carry + (float)$credit['amount'];
            }, 0.0);

            $summary = [
                'flights_subtotal' => round((float)($invoice['flights_subtotal'] ?? $flightsSubtotal), 2),
                'credits_total' => round((float)($invoice['credits_total'] ?? $creditsTotal), 2),
                'vat_amount' => round((float)($invoice['vat_amount'] ?? 0), 2),
                'total_amount' => round((float)$invoice['total_amount'], 2),
            ];

            if ($summary['vat_amount'] === 0.0 && !empty($vat['enabled'])) {
                $vatBase = $summary['flights_subtotal'] - $summary['credits_total'];
                $summary['vat_amount'] = round($vatBase * ((float)$vat['rate_percent'] / 100), 2);
            }
            if ($summary['total_amount'] === 0.0) {
                $summary['total_amount'] = round($summary['flights_subtotal'] - $summary['credits_total'] + $summary['vat_amount'], 2);
            }

            return compact('invoice', 'items', 'credits', 'summary', 'issuer', 'bank', 'vat', 'invoiceMeta', 'logoFilesystemPath', 'logoPublicPath', 'customerAddress');
        };

        $renderInvoiceHtmlFromData = static function (array $data, string $renderMode): string {
            $invoice = $data['invoice'];
            $items = $data['items'];
            $credits = $data['credits'] ?? [];
            $summary = $data['summary'] ?? [];
            $issuer = $data['issuer'];
            $bank = $data['bank'];
            $vat = $data['vat'];
            $invoiceMeta = $data['invoiceMeta'];
            $customerAddress = $data['customerAddress'];
            $logoFilesystemPath = $data['logoFilesystemPath'];
            $logoPublicPath = $data['logoPublicPath'];

            $logoSrc = '';
            if ($renderMode === 'pdf' && $logoPublicPath !== '' && is_file($logoFilesystemPath)) {
                $logoData = @file_get_contents($logoFilesystemPath);
                if ($logoData !== false) {
                    $logoMime = 'image/png';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo !== false) {
                            $detectedMime = finfo_file($finfo, $logoFilesystemPath);
                            if (is_string($detectedMime) && str_starts_with($detectedMime, 'image/')) {
                                $logoMime = $detectedMime;
                            }
                            finfo_close($finfo);
                        }
                    }
                    $logoSrc = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
                }
            } elseif ($logoPublicPath !== '') {
                $logoSrc = $logoPublicPath;
            }

            ob_start();
            include __DIR__ . '/app/views/invoice_pdf.php';
            return (string)ob_get_clean();
        };

        $renderInvoicePdfBinary = static function (int $invoiceId) use ($loadInvoiceDocumentData, $renderInvoiceHtmlFromData): array {
            if (!dompdf_is_available()) {
                return ['ok' => false, 'error' => 'PDF-Engine nicht verfügbar. Bitte public/vendor/dompdf hochladen.'];
            }
            if (!extension_loaded('gd')) {
                return ['ok' => false, 'error' => 'PHP-Erweiterung gd fehlt auf dem Server.'];
            }

            $data = $loadInvoiceDocumentData($invoiceId);
            if ($data === null) {
                return ['ok' => false, 'error' => 'Rechnung nicht gefunden.'];
            }

            $invoiceHtml = $renderInvoiceHtmlFromData($data, 'pdf');
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($invoiceHtml, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)$data['invoice']['invoice_number']);
            return [
                'ok' => true,
                'filename' => $safeInvoiceNumber . '.pdf',
                'content' => $dompdf->output(),
                'invoice' => $data['invoice'],
                'invoice_meta' => $data['invoiceMeta'],
                'issuer' => $data['issuer'],
            ];
        };

        $sendInvoiceMailWithTemplate = static function (int $invoiceId, string $subjectTemplatePath, string $bodyTemplatePath, string $fallbackSubject, string $fallbackBody, string $auditAction) use ($loadInvoiceDocumentData, $renderInvoicePdfBinary): array {
            $data = $loadInvoiceDocumentData($invoiceId);
            if ($data === null) {
                return ['ok' => false, 'error' => 'Rechnung nicht gefunden.'];
            }
            $invoice = $data['invoice'];
            $issuer = $data['issuer'];
            $invoiceMeta = $data['invoiceMeta'];

            $to = trim((string)$invoice['email']);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Keine gültige Empfänger-E-Mail vorhanden.'];
            }

            $pdfResult = $renderInvoicePdfBinary($invoiceId);
            if (!$pdfResult['ok']) {
                return ['ok' => false, 'error' => (string)$pdfResult['error']];
            }

            $subjectTemplate = is_file($subjectTemplatePath)
                ? (string)file_get_contents($subjectTemplatePath)
                : $fallbackSubject;
            $bodyTemplate = is_file($bodyTemplatePath)
                ? (string)file_get_contents($bodyTemplatePath)
                : $fallbackBody;

            $issuerFull = trim(implode("\n", array_filter([
                (string)$issuer['name'],
                trim((string)$issuer['street'] . ' ' . (string)$issuer['house_number']),
                trim((string)$issuer['postal_code'] . ' ' . (string)$issuer['city']),
                (string)$issuer['country'],
                (string)$issuer['email'] !== '' ? 'E-Mail: ' . (string)$issuer['email'] : '',
                (string)$issuer['phone'] !== '' ? 'Telefon: ' . (string)$issuer['phone'] : '',
                (string)$issuer['website'] !== '' ? (string)$issuer['website'] : '',
            ])));

            $replacements = [
                '{issuer.name}' => (string)$issuer['name'],
                '{invoice.invoice_number}' => (string)$invoice['invoice_number'],
                '{invoiceMeta.due_date}' => (string)$invoiceMeta['due_date'],
                '{customer.first_name}' => (string)$invoice['first_name'],
                '{customer.last_name}' => (string)$invoice['last_name'],
                '{customer.name}' => (string)$invoice['customer_name'],
                '{issuer.full}' => $issuerFull,
                '{NUMMER}' => (string)$invoice['invoice_number'],
                '{Vorname}' => (string)$invoice['first_name'],
                '{zahlbar bis}' => (string)$invoiceMeta['due_date'],
            ];

            $subject = trim(strtr($subjectTemplate, $replacements));
            $textBody = strtr($bodyTemplate, $replacements);
            $htmlBody = nl2br(h($textBody), false);

            $sendResult = smtp_send_mail($to, $subject, $htmlBody, $textBody, [[
                'filename' => (string)$pdfResult['filename'],
                'mime' => 'application/pdf',
                'content' => (string)$pdfResult['content'],
            ]]);
            if (!$sendResult['ok']) {
                return ['ok' => false, 'error' => (string)$sendResult['error']];
            }
            if (!empty($sendResult['skipped'])) {
                return ['ok' => true, 'skipped' => true];
            }

            db()->prepare('UPDATE invoices SET mailed_at = NOW() WHERE id = ?')->execute([$invoiceId]);
            audit_log($auditAction, 'invoice', $invoiceId, ['to' => $to, 'provider' => 'smtp', 'attachment' => 'pdf']);
            return ['ok' => true];
        };

        $sendInvoiceByMail = static function (int $invoiceId) use ($sendInvoiceMailWithTemplate): array {
            return $sendInvoiceMailWithTemplate(
                $invoiceId,
                __DIR__ . '/templates/mail_invoice_subject.txt',
                __DIR__ . '/templates/mail_invoice_body.txt',
                '{issuer.name}: Rechnung {invoice.invoice_number}',
                "Hallo {customer.first_name}\n\nim Anhang senden wir die automatisiert unsere Rechnung zu. Bitte bezahle die Rechnung bis zum {invoiceMeta.due_date}.\n\nLiebe Grüsse,\n{issuer.full}",
                'mail'
            );
        };

        $sendInvoiceCancellationByMail = static function (int $invoiceId) use ($sendInvoiceMailWithTemplate): array {
            return $sendInvoiceMailWithTemplate(
                $invoiceId,
                __DIR__ . '/templates/mail_invoice_cancel_subject.txt',
                __DIR__ . '/templates/mail_invoice_cancel_body.txt',
                '{issuer.name}: Storno der Rechnung {invoice.invoice_number}',
                "Hallo {customer.first_name}\n\ndie Rechnung im Anhang mit der Nummer {invoice.invoice_number} wurde storniert. Bitte bezahle diese nicht mehr.\n\nLiebe Grüsse,\n{issuer.full}",
                'mail_cancel'
            );
        };

        $sendInvoiceReminderByMail = static function (int $invoiceId) use ($sendInvoiceMailWithTemplate): array {
            return $sendInvoiceMailWithTemplate(
                $invoiceId,
                __DIR__ . '/templates/mail_invoice_reminder_subject.txt',
                __DIR__ . '/templates/mail_invoice_reminder_body.txt',
                '{issuer.name}: Zahlungserinnerung Rechnung {invoice.invoice_number}',
                "Hallo {customer.first_name}\n\nzu der Rechnung {invoice.invoice_number} ist die Zahlung noch ausstehend. Bitte bezahle den offenen Betrag umgehend.\n\nLiebe Grüsse,\n{issuer.full}",
                'mail_reminder'
            );
        };

        $createInvoiceForUser = static function (int $userId, ?string $dateFrom = null, ?string $dateTo = null) use ($nextInvoiceNumberForYear, $collectBillableFlightRows, $collectOpenCredits): array {
            $rows = $collectBillableFlightRows($userId, $dateFrom, $dateTo);
            if ($rows === []) {
                return ['ok' => false, 'message' => 'Keine abrechenbaren Flüge gefunden.'];
            }
            $openCredits = $collectOpenCredits($userId);

            $periodFrom = date('Y-m-d', strtotime((string)$rows[0]['start_time']));
            $periodTo = date('Y-m-d', strtotime((string)$rows[count($rows) - 1]['landing_time']));
            $lastFlightYear = (int)date('Y', strtotime((string)$rows[count($rows) - 1]['landing_time']));
            $invoiceNumber = $nextInvoiceNumberForYear($lastFlightYear);

            db()->beginTransaction();
            try {
                $stmt = db()->prepare('INSERT INTO invoices (invoice_number, user_id, period_from, period_to, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$invoiceNumber, $userId, $periodFrom, $periodTo, (int)current_user()['id']]);
                $invoiceId = (int)db()->lastInsertId();

                $total = 0.0;
                $itemStmt = db()->prepare('INSERT INTO invoice_items (invoice_id, reservation_id, flight_date, aircraft_type, aircraft_immatriculation, from_airfield, to_airfield, description, hours, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

                $reservationIds = [];
                foreach ($rows as $row) {
                    $reservationId = (int)$row['reservation_id'];
                    $reservationIds[$reservationId] = true;

                    $hours = round((float)$row['hobbs_hours'], 2);
                    $rate = user_rate_for_aircraft($userId, (int)$row['aircraft_id']) ?? (float)$row['base_hourly_rate'];
                    $lineTotal = round($hours * $rate, 2);
                    $total += $lineTotal;

                    $fromAirfield = strtoupper(trim((string)($row['from_airfield'] ?? '')));
                    $toAirfield = strtoupper(trim((string)($row['to_airfield'] ?? '')));
                    $route = trim($fromAirfield . ($fromAirfield !== '' || $toAirfield !== '' ? ' - ' : '') . $toAirfield);
                    $description = trim(sprintf(
                        '%s | %s %s',
                        date('d.m.Y', strtotime((string)$row['start_time'])),
                        (string)$row['immatriculation'],
                        $route
                    ));

                    $itemStmt->execute([
                        $invoiceId,
                        $reservationId,
                        date('Y-m-d', strtotime((string)$row['start_time'])),
                        (string)$row['aircraft_type'],
                        (string)$row['immatriculation'],
                        $fromAirfield,
                        $toAirfield,
                        $description,
                        $hours,
                        $rate,
                        $lineTotal,
                    ]);
                }

                if ($reservationIds !== []) {
                    $resStmt = db()->prepare('UPDATE reservations SET invoice_id = ? WHERE id = ?');
                    foreach (array_keys($reservationIds) as $reservationId) {
                        $resStmt->execute([$invoiceId, (int)$reservationId]);
                    }
                }

                $creditsTotal = 0.0;
                if ($openCredits !== []) {
                    $creditIds = [];
                    foreach ($openCredits as $creditRow) {
                        $creditIds[] = (int)$creditRow['id'];
                        $creditsTotal += (float)$creditRow['amount'];
                    }

                    $creditPlaceholders = implode(',', array_fill(0, count($creditIds), '?'));
                    $creditParams = array_merge([$invoiceId], $creditIds);
                    db()->prepare("UPDATE credits SET invoice_id = ? WHERE id IN ($creditPlaceholders)")->execute($creditParams);
                }

                $vatEnabled = (bool)config('invoice.vat.enabled', false);
                $vatPercent = (float)config('invoice.vat.rate_percent', 0);
                $subtotalAfterCredits = round($total - $creditsTotal, 2);
                $vatAmount = $vatEnabled ? round($subtotalAfterCredits * ($vatPercent / 100), 2) : 0.0;
                $grossTotal = round($subtotalAfterCredits + $vatAmount, 2);
                db()->prepare('UPDATE invoices
                    SET flights_subtotal = ?, credits_total = ?, vat_amount = ?, total_amount = ?
                    WHERE id = ?')->execute([round($total, 2), round($creditsTotal, 2), $vatAmount, $grossTotal, $invoiceId]);
                db()->commit();

                audit_log('create', 'invoice', $invoiceId, [
                    'invoice_number' => $invoiceNumber,
                    'rows' => count($rows),
                    'flights_subtotal' => round($total, 2),
                    'credits_total' => round($creditsTotal, 2),
                    'vat' => $vatAmount,
                    'gross' => $grossTotal
                ]);
                return ['ok' => true, 'invoice_number' => $invoiceNumber, 'invoice_id' => $invoiceId];
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                return ['ok' => false, 'message' => 'Rechnung konnte nicht erstellt werden.'];
            }
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=invoices');
                exit;
            }

            $action = $_POST['action'] ?? '';

            if ($action === 'generate') {
                $userId = (int)$_POST['user_id'];
                $period = (string)$_POST['period'];
                $from = date('Y-m-01', strtotime($period . '-01'));
                $to = date('Y-m-t', strtotime($period . '-01'));
                $result = $createInvoiceForUser($userId, $from, $to);
                if ($result['ok']) {
                    flash('success', 'Rechnung erstellt: ' . $result['invoice_number']);
                } else {
                    flash('error', (string)$result['message']);
                }
            }

            if ($action === 'generate_open_for_pilot') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $sendByMail = ((string)($_POST['send_by_mail'] ?? '0')) === '1';
                if ($userId <= 0) {
                    flash('error', 'Ungültiger Pilot.');
                    header('Location: index.php?page=invoices');
                    exit;
                }
                $result = $createInvoiceForUser($userId);
                if ($result['ok']) {
                    flash('success', 'Rechnung erstellt: ' . $result['invoice_number']);
                    if ($sendByMail) {
                        $mailResult = $sendInvoiceByMail((int)$result['invoice_id']);
                        if ($mailResult['ok']) {
                            if (!empty($mailResult['skipped'])) {
                                flash('success', 'Rechnung erstellt. SMTP ist deaktiviert, keine E-Mail versendet.');
                            } else {
                                flash('success', 'Rechnung per E-Mail versendet.');
                            }
                        } else {
                            flash('error', 'Rechnung erstellt, E-Mail fehlgeschlagen: ' . (string)$mailResult['error']);
                        }
                    }
                } else {
                    flash('error', (string)$result['message']);
                }
            }

            if ($action === 'status') {
                $invoiceId = (int)$_POST['invoice_id'];
                $status = (string)$_POST['payment_status'];
                if (!in_array($status, ['open', 'paid', 'overdue'], true)) {
                    flash('error', 'Ungültiger Zahlungsstatus.');
                    header('Location: index.php?page=invoices');
                    exit;
                }
                db()->prepare('UPDATE invoices SET payment_status = ? WHERE id = ?')->execute([$status, $invoiceId]);
                audit_log('status_change', 'invoice', $invoiceId, ['status' => $status]);
                flash('success', 'Zahlungsstatus aktualisiert.');
            }

            if ($action === 'cancel_invoice') {
                $invoiceId = (int)($_POST['invoice_id'] ?? 0);
                if ($invoiceId <= 0) {
                    flash('error', 'Ungültige Rechnung.');
                    header('Location: index.php?page=invoices');
                    exit;
                }

                $invoiceStmt = db()->prepare('SELECT id, invoice_number, pdf_path FROM invoices WHERE id = ?');
                $invoiceStmt->execute([$invoiceId]);
                $invoice = $invoiceStmt->fetch();
                if (!$invoice) {
                    flash('error', 'Rechnung nicht gefunden.');
                    header('Location: index.php?page=invoices');
                    exit;
                }

                $cancelMailResult = $sendInvoiceCancellationByMail($invoiceId);
                if (!$cancelMailResult['ok']) {
                    flash('error', 'Storno-Mail fehlgeschlagen, Storno wurde nicht durchgeführt: ' . (string)$cancelMailResult['error']);
                    header('Location: index.php?page=invoices');
                    exit;
                }
                $cancelMailSkipped = !empty($cancelMailResult['skipped']);

                db()->beginTransaction();
                try {
                    db()->prepare('UPDATE reservations SET invoice_id = NULL WHERE invoice_id = ?')->execute([$invoiceId]);
                    db()->prepare('UPDATE credits SET invoice_id = NULL WHERE invoice_id = ?')->execute([$invoiceId]);
                    db()->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$invoiceId]);
                    db()->prepare('DELETE FROM invoices WHERE id = ?')->execute([$invoiceId]);
                    db()->commit();

                    $rawPath = trim((string)($invoice['pdf_path'] ?? ''));
                    if ($rawPath !== '') {
                        $candidatePaths = [];
                        if (str_starts_with($rawPath, '/')) {
                            $candidatePaths[] = $rawPath;
                        } else {
                            $candidatePaths[] = __DIR__ . '/' . ltrim($rawPath, '/');
                            $candidatePaths[] = dirname(__DIR__) . '/' . ltrim($rawPath, '/');
                        }
                        foreach ($candidatePaths as $candidatePath) {
                            if (is_file($candidatePath)) {
                                @unlink($candidatePath);
                                break;
                            }
                        }
                    }

                    audit_log('cancel', 'invoice', $invoiceId, ['invoice_number' => $invoice['invoice_number']]);
                    if ($cancelMailSkipped) {
                        flash('success', 'Rechnung storniert. SMTP ist deaktiviert, keine Storno-Mail versendet. Stunden sind wieder unverrechnet.');
                    } else {
                        flash('success', 'Rechnung storniert. Storno-Mail wurde versendet. Stunden sind wieder unverrechnet.');
                    }
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Rechnung konnte nicht storniert werden.');
                }
            }

            if ($action === 'send_reminder') {
                $invoiceId = (int)($_POST['invoice_id'] ?? 0);
                if ($invoiceId <= 0) {
                    flash('error', 'Ungültige Rechnung.');
                    header('Location: index.php?page=invoices');
                    exit;
                }

                $invoiceStmt = db()->prepare('SELECT payment_status FROM invoices WHERE id = ?');
                $invoiceStmt->execute([$invoiceId]);
                $status = (string)$invoiceStmt->fetchColumn();
                if ($status !== 'overdue') {
                    flash('error', 'Zahlungserinnerung ist nur bei überfälligen Rechnungen erlaubt.');
                    header('Location: index.php?page=invoices');
                    exit;
                }

                $reminderResult = $sendInvoiceReminderByMail($invoiceId);
                if ($reminderResult['ok']) {
                    if (!empty($reminderResult['skipped'])) {
                        flash('success', 'SMTP ist deaktiviert, keine Zahlungserinnerung versendet.');
                    } else {
                        flash('success', 'Zahlungserinnerung wurde versendet.');
                    }
                } else {
                    flash('error', 'Zahlungserinnerung fehlgeschlagen: ' . (string)$reminderResult['error']);
                }
            }

            if ($action === 'mail') {
                $invoiceId = (int)$_POST['invoice_id'];
                $stmt = db()->prepare('SELECT i.invoice_number, u.email FROM invoices i JOIN users u ON u.id = i.user_id WHERE i.id = ?');
                $stmt->execute([$invoiceId]);
                $invoice = $stmt->fetch();

                if ($invoice) {
                    $subject = 'Rechnung ' . $invoice['invoice_number'];
                    $messageText = "Ihre Rechnung {$invoice['invoice_number']} wurde erstellt.";
                    $messageHtml = '<p>Ihre Rechnung <strong>' . h((string)$invoice['invoice_number']) . '</strong> wurde erstellt.</p>';
                    $sendResult = smtp_send_mail((string)$invoice['email'], $subject, $messageHtml, $messageText);

                    if ($sendResult['ok']) {
                        if (!empty($sendResult['skipped'])) {
                            flash('success', 'SMTP ist deaktiviert, keine E-Mail versendet.');
                        } else {
                            db()->prepare('UPDATE invoices SET mailed_at = NOW() WHERE id = ?')->execute([$invoiceId]);
                            audit_log('mail', 'invoice', $invoiceId, ['to' => $invoice['email'], 'provider' => 'smtp']);
                            flash('success', 'E-Mail via SMTP versendet.');
                        }
                    } else {
                        flash('error', 'SMTP Versand fehlgeschlagen: ' . (string)$sendResult['error']);
                    }
                }
            }

            header('Location: index.php?page=invoices');
            exit;
        }

        $invoiceStatusFilter = (string)($_GET['invoice_status'] ?? 'unpaid');
        $invoiceSearch = trim((string)($_GET['invoice_q'] ?? ''));
        $allowedInvoiceFilters = ['unpaid', 'all', 'open', 'overdue', 'paid'];
        if (!in_array($invoiceStatusFilter, $allowedInvoiceFilters, true)) {
            $invoiceStatusFilter = 'unpaid';
        }

        $invoiceSql = "SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) AS customer_name
            FROM invoices i
            JOIN users u ON u.id = i.user_id
            WHERE 1=1";
        $invoiceParams = [];

        if ($invoiceStatusFilter === 'unpaid') {
            $invoiceSql .= " AND i.payment_status IN ('open', 'overdue')";
        } elseif ($invoiceStatusFilter !== 'all') {
            $invoiceSql .= ' AND i.payment_status = ?';
            $invoiceParams[] = $invoiceStatusFilter;
        }

        if ($invoiceSearch !== '') {
            $invoiceSql .= ' AND (i.invoice_number LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
            $like = '%' . $invoiceSearch . '%';
            $invoiceParams[] = $like;
            $invoiceParams[] = $like;
        }

        $invoiceSql .= ' ORDER BY i.created_at DESC LIMIT 100';
        $invoiceStmt = db()->prepare($invoiceSql);
        $invoiceStmt->execute($invoiceParams);
        $invoices = $invoiceStmt->fetchAll();
        $unbilledPilotHours = db()->query("SELECT
                rf.pilot_user_id,
                CONCAT(p.first_name, ' ', p.last_name) AS pilot_name,
                ROUND(SUM(rf.hobbs_hours), 2) AS open_hours
            FROM reservation_flights rf
            JOIN reservations r ON r.id = rf.reservation_id
            JOIN users p ON p.id = rf.pilot_user_id
            WHERE r.status = 'completed'
              AND r.invoice_id IS NULL
              AND rf.is_billable = 1
            GROUP BY rf.pilot_user_id, p.first_name, p.last_name
            ORDER BY open_hours DESC, p.last_name ASC, p.first_name ASC")->fetchAll();

        $openPilotId = (int)($_GET['open_pilot_id'] ?? 0);
        $openPilotFlights = [];
        $openPilotName = '';
        if ($openPilotId > 0) {
            $pilotFlightsStmt = db()->prepare("SELECT
                    rf.start_time,
                    rf.landing_time,
                    rf.from_airfield,
                    rf.to_airfield,
                    a.immatriculation,
                    CONCAT(p.first_name, ' ', p.last_name) AS pilot_name
                FROM reservation_flights rf
                JOIN reservations r ON r.id = rf.reservation_id
                JOIN aircraft a ON a.id = r.aircraft_id
                JOIN users p ON p.id = rf.pilot_user_id
                WHERE r.status = 'completed'
                  AND r.invoice_id IS NULL
                  AND rf.is_billable = 1
                  AND rf.pilot_user_id = ?
                ORDER BY rf.start_time DESC, rf.id DESC");
            $pilotFlightsStmt->execute([$openPilotId]);
            $openPilotFlights = $pilotFlightsStmt->fetchAll();
            if (!empty($openPilotFlights)) {
                $openPilotName = (string)$openPilotFlights[0]['pilot_name'];
            }
        }

        render('Abrechnung', 'invoices', compact('invoices', 'unbilledPilotHours', 'invoiceStatusFilter', 'invoiceSearch', 'openPilotId', 'openPilotFlights', 'openPilotName'));
        break;

    case 'manual_flight':
        require_role('admin', 'accounting');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=manual_flight');
                exit;
            }

            $pilotId = (int)($_POST['pilot_user_id'] ?? 0);
            $aircraftId = (int)($_POST['aircraft_id'] ?? 0);
            $from = strtoupper(trim((string)($_POST['from_airfield'] ?? '')));
            $to = strtoupper(trim((string)($_POST['to_airfield'] ?? '')));
            $startTimeRaw = trim((string)($_POST['start_time'] ?? ''));
            $landingTimeRaw = trim((string)($_POST['landing_time'] ?? ''));
            $landingsCount = (int)($_POST['landings_count'] ?? 0);
            $hobbsStartRaw = trim((string)($_POST['hobbs_start'] ?? ''));
            $hobbsEndRaw = trim((string)($_POST['hobbs_end'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($pilotId <= 0 || $aircraftId <= 0 || $from === '' || $to === '' || $startTimeRaw === '' || $landingTimeRaw === '' || $hobbsStartRaw === '' || $hobbsEndRaw === '' || $landingsCount < 1) {
                flash('error', 'Bitte alle Pflichtfelder vollständig ausfüllen.');
                header('Location: index.php?page=manual_flight');
                exit;
            }

            $startTs = strtotime($startTimeRaw);
            $landingTs = strtotime($landingTimeRaw);
            if ($startTs === false || $landingTs === false || $landingTs <= $startTs) {
                flash('error', 'Ungültige Start-/Landezeit.');
                header('Location: index.php?page=manual_flight');
                exit;
            }

            $aircraftStatusStmt = db()->prepare('SELECT status FROM aircraft WHERE id = ?');
            $aircraftStatusStmt->execute([$aircraftId]);
            if ((string)$aircraftStatusStmt->fetchColumn() !== 'active') {
                flash('error', 'Dieses Flugzeug ist nicht aktiv.');
                header('Location: index.php?page=manual_flight');
                exit;
            }

            $parseHobbs = static function (string $value): ?float {
                $trimmed = trim($value);
                if (!preg_match('/^\d+:[0-5]\d$/', $trimmed)) {
                    return null;
                }
                [$hours, $minutes] = explode(':', $trimmed, 2);
                return ((int)$hours) + (((int)$minutes) / 60);
            };

            $hobbsStart = $parseHobbs($hobbsStartRaw);
            $hobbsEnd = $parseHobbs($hobbsEndRaw);
            if ($hobbsStart !== null && $hobbsEnd === null) {
                $diffMinutes = (int)round(($landingTs - $startTs) / 60);
                if ($diffMinutes > 0) {
                    $hobbsEnd = $hobbsStart + ($diffMinutes / 60);
                }
            }

            if ($hobbsStart === null || $hobbsEnd === null) {
                flash('error', 'Hobbs muss im Format HH:MM sein.');
                header('Location: index.php?page=manual_flight');
                exit;
            }
            if ($hobbsEnd <= $hobbsStart) {
                flash('error', 'Hobbs bis muss größer als Hobbs von sein.');
                header('Location: index.php?page=manual_flight');
                exit;
            }

            $hobbsHours = round($hobbsEnd - $hobbsStart, 2);

            db()->beginTransaction();
            try {
                $reservationStmt = db()->prepare("INSERT INTO reservations
                    (aircraft_id, user_id, starts_at, ends_at, hours, notes, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)");
                $reservationStmt->execute([
                    $aircraftId,
                    $pilotId,
                    date('Y-m-d H:i:s', $startTs),
                    date('Y-m-d H:i:s', $landingTs),
                    $hobbsHours,
                    $notes,
                    (int)current_user()['id'],
                ]);
                $reservationId = (int)db()->lastInsertId();

                $flightStmt = db()->prepare("INSERT INTO reservation_flights
                    (reservation_id, pilot_user_id, from_airfield, to_airfield, start_time, landing_time, landings_count, hobbs_start, hobbs_end, hobbs_hours, is_billable)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $flightStmt->execute([
                    $reservationId,
                    $pilotId,
                    $from,
                    $to,
                    date('Y-m-d H:i:s', $startTs),
                    date('Y-m-d H:i:s', $landingTs),
                    $landingsCount,
                    $hobbsStart,
                    $hobbsEnd,
                    $hobbsHours,
                ]);

                db()->commit();
                audit_log('create', 'reservation', $reservationId, ['source' => 'manual_flight', 'hobbs_hours' => $hobbsHours]);
                audit_log('create', 'reservation_flight', (int)db()->lastInsertId(), ['reservation_id' => $reservationId, 'source' => 'manual_flight']);
                flash('success', 'Flug wurde händisch erfasst und ist abrechenbar.');
                header('Location: index.php?page=invoices');
                exit;
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('error', 'Flug konnte nicht erfasst werden.');
                header('Location: index.php?page=manual_flight');
                exit;
            }
        }

        $pilots = db()->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
            FROM users u
            WHERE u.is_active = 1
              AND EXISTS (
                SELECT 1 FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id AND r.name = 'pilot'
              )
            ORDER BY u.last_name, u.first_name")->fetchAll();

        $aircraft = db()->query("SELECT id, immatriculation, type, start_hobbs, start_landings
            FROM aircraft
            WHERE status = 'active'
            ORDER BY immatriculation")->fetchAll();

        $manualDefaultsByAircraft = [];
        $lastFlightByAircraftStmt = db()->prepare("SELECT rf.hobbs_end, rf.to_airfield
            FROM reservation_flights rf
            JOIN reservations r ON r.id = rf.reservation_id
            WHERE r.aircraft_id = ?
            ORDER BY rf.id DESC
            LIMIT 1");
        foreach ($aircraft as $aircraftRow) {
            $aircraftId = (int)$aircraftRow['id'];
            $lastFlightByAircraftStmt->execute([$aircraftId]);
            $lastFlight = $lastFlightByAircraftStmt->fetch();

            $hobbsClock = '';
            $fromAirfield = '';
            if ($lastFlight) {
                $lastHobbsEnd = (float)$lastFlight['hobbs_end'];
                $hours = (int)floor($lastHobbsEnd);
                $minutes = (int)round(($lastHobbsEnd - $hours) * 60);
                if ($minutes === 60) {
                    $hours++;
                    $minutes = 0;
                }
                $hobbsClock = sprintf('%d:%02d', $hours, $minutes);
                $fromAirfield = strtoupper((string)($lastFlight['to_airfield'] ?? ''));
            } else {
                $startHobbs = (float)($aircraftRow['start_hobbs'] ?? 0);
                $hours = (int)floor($startHobbs);
                $minutes = (int)round(($startHobbs - $hours) * 60);
                if ($minutes === 60) {
                    $hours++;
                    $minutes = 0;
                }
                $hobbsClock = sprintf('%d:%02d', $hours, $minutes);
            }

            $manualDefaultsByAircraft[$aircraftId] = [
                'hobbs_start' => $hobbsClock,
                'from_airfield' => $fromAirfield,
                'landings_count' => 1,
            ];
        }

        render('Flug händisch eintragen', 'manual_flight', compact('pilots', 'aircraft', 'manualDefaultsByAircraft'));
        break;

    case 'invoice_html':
    case 'invoice_pdf':
        $invoiceId = (int)($_GET['id'] ?? 0);

        $stmt = db()->prepare('SELECT i.*, CONCAT(u.first_name, " ", u.last_name) AS customer_name, u.email,
                u.street, u.house_number, u.postal_code, u.city, u.country_code, u.phone
            FROM invoices i
            JOIN users u ON u.id = i.user_id
            WHERE i.id = ?');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            http_response_code(404);
            exit('Rechnung nicht gefunden.');
        }

        $currentUserId = (int)current_user()['id'];
        $hasInvoiceAccess = has_role('admin', 'accounting') || (int)$invoice['user_id'] === $currentUserId;
        if (!$hasInvoiceAccess) {
            http_response_code(403);
            exit('Kein Zugriff.');
        }

        $itemsStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY flight_date ASC, id ASC');
        $itemsStmt->execute([$invoiceId]);
        $items = $itemsStmt->fetchAll();

        $creditsStmt = db()->prepare('SELECT id, credit_date, amount, description, notes
            FROM credits
            WHERE invoice_id = ?
            ORDER BY credit_date ASC, id ASC');
        $creditsStmt->execute([$invoiceId]);
        $credits = $creditsStmt->fetchAll();

        $countryOptions = european_countries();
        $issuer = [
            'name' => (string)config('invoice.issuer.name', ''),
            'street' => (string)config('invoice.issuer.street', ''),
            'house_number' => (string)config('invoice.issuer.house_number', ''),
            'postal_code' => (string)config('invoice.issuer.postal_code', ''),
            'city' => (string)config('invoice.issuer.city', ''),
            'country' => (string)config('invoice.issuer.country', 'Schweiz'),
            'email' => (string)config('invoice.issuer.email', ''),
            'phone' => (string)config('invoice.issuer.phone', ''),
            'website' => (string)config('invoice.issuer.website', ''),
        ];
        $bank = [
            'recipient' => (string)config('invoice.bank.recipient', ''),
            'iban' => (string)config('invoice.bank.iban', ''),
            'bic' => (string)config('invoice.bank.bic', ''),
            'bank_name' => (string)config('invoice.bank.bank_name', ''),
            'bank_address' => (string)config('invoice.bank.bank_address', ''),
        ];
        $vat = [
            'enabled' => (bool)config('invoice.vat.enabled', false),
            'rate_percent' => (float)config('invoice.vat.rate_percent', 0),
            'uid' => (string)config('invoice.vat.uid', ''),
        ];

        $invoiceMeta = [
            'title' => (string)config('invoice.title', 'Rechnung'),
            'currency' => (string)config('invoice.currency', 'CHF'),
            'payment_target_days' => (int)config('invoice.payment_target_days', 30),
            'due_date' => date('d.m.Y', strtotime((string)$invoice['created_at'] . ' +' . (int)config('invoice.payment_target_days', 30) . ' days')),
        ];

        $logoPathConfig = trim((string)config('invoice.logo_path', 'logo.png'));
        $logoFilesystemPath = __DIR__ . '/' . ltrim($logoPathConfig, '/');
        $logoPublicPath = is_file($logoFilesystemPath) ? $logoPathConfig : '';

        $customerAddress = [
            'name' => (string)$invoice['customer_name'],
            'street_line' => trim((string)$invoice['street'] . ' ' . (string)$invoice['house_number']),
            'city_line' => trim((string)$invoice['postal_code'] . ' ' . (string)$invoice['city']),
            'country' => $countryOptions[(string)$invoice['country_code']] ?? (string)$invoice['country_code'],
            'email' => (string)$invoice['email'],
            'phone' => (string)($invoice['phone'] ?? ''),
        ];

        $flightsSubtotal = array_reduce($items, static function (float $carry, array $item): float {
            return $carry + (float)$item['line_total'];
        }, 0.0);
        $creditsTotal = array_reduce($credits, static function (float $carry, array $credit): float {
            return $carry + (float)$credit['amount'];
        }, 0.0);
        $summary = [
            'flights_subtotal' => round((float)($invoice['flights_subtotal'] ?? $flightsSubtotal), 2),
            'credits_total' => round((float)($invoice['credits_total'] ?? $creditsTotal), 2),
            'vat_amount' => round((float)($invoice['vat_amount'] ?? 0), 2),
            'total_amount' => round((float)$invoice['total_amount'], 2),
        ];
        if ($summary['vat_amount'] === 0.0 && !empty($vat['enabled'])) {
            $summary['vat_amount'] = round(($summary['flights_subtotal'] - $summary['credits_total']) * ((float)$vat['rate_percent'] / 100), 2);
        }
        if ($summary['total_amount'] === 0.0) {
            $summary['total_amount'] = round($summary['flights_subtotal'] - $summary['credits_total'] + $summary['vat_amount'], 2);
        }

        $renderMode = $page === 'invoice_pdf' ? 'pdf' : 'html';
        $logoSrc = '';
        if ($renderMode === 'pdf' && $logoPublicPath !== '' && is_file($logoFilesystemPath)) {
            $logoData = @file_get_contents($logoFilesystemPath);
            if ($logoData !== false) {
                $logoMime = 'image/png';
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo !== false) {
                        $detectedMime = finfo_file($finfo, $logoFilesystemPath);
                        if (is_string($detectedMime) && str_starts_with($detectedMime, 'image/')) {
                            $logoMime = $detectedMime;
                        }
                        finfo_close($finfo);
                    }
                }
                $logoSrc = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
            }
        } elseif ($logoPublicPath !== '') {
            $logoSrc = $logoPublicPath;
        }

        $viewData = compact('invoice', 'items', 'credits', 'summary', 'issuer', 'bank', 'vat', 'invoiceMeta', 'logoSrc', 'customerAddress', 'renderMode');
        extract($viewData, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/app/views/invoice_pdf.php';
        $invoiceHtml = (string)ob_get_clean();

        if ($renderMode === 'html') {
            header('Content-Type: text/html; charset=utf-8');
            echo $invoiceHtml;
            exit;
        }

        if (!dompdf_is_available()) {
            http_response_code(500);
            exit('PDF-Engine nicht verfügbar. Bitte public/vendor/dompdf hochladen.');
        }
        if (!extension_loaded('gd')) {
            http_response_code(500);
            exit('PHP-Erweiterung gd fehlt auf dem Server.');
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($invoiceHtml, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)$invoice['invoice_number']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $safeInvoiceNumber . '.pdf"');
        echo $dompdf->output();
        exit;
        break;

    case 'my_invoices':
        $userId = (int)current_user()['id'];

        $openStmt = db()->prepare("SELECT *
            FROM invoices
            WHERE user_id = ?
              AND payment_status IN ('open', 'overdue')
            ORDER BY created_at DESC");
        $openStmt->execute([$userId]);
        $openInvoices = $openStmt->fetchAll();

        $paidStmt = db()->prepare("SELECT *
            FROM invoices
            WHERE user_id = ?
              AND payment_status = 'paid'
            ORDER BY created_at DESC");
        $paidStmt->execute([$userId]);
        $paidInvoices = $paidStmt->fetchAll();

        render('Meine Rechnungen', 'my_invoices', compact('openInvoices', 'paidInvoices'));
        break;

    case 'members':
        $membersSearch = trim((string)($_GET['q'] ?? ''));
        $membersSql = "SELECT first_name, last_name, phone
            FROM users";
        $membersParams = [];
        if ($membersSearch !== '') {
            $membersSql .= ' WHERE first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?';
            $like = '%' . $membersSearch . '%';
            $membersParams[] = $like;
            $membersParams[] = $like;
            $membersParams[] = $like;
        }
        $membersSql .= ' ORDER BY last_name ASC, first_name ASC';
        $membersStmt = db()->prepare($membersSql);
        $membersStmt->execute($membersParams);
        $members = $membersStmt->fetchAll();
        render('Mitglieder', 'members', compact('members', 'membersSearch'));
        break;

    case 'profile':
        $userId = (int)current_user()['id'];
        $countryOptions = european_countries();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=profile');
                exit;
            }

            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $street = trim((string)($_POST['street'] ?? ''));
            $houseNumber = trim((string)($_POST['house_number'] ?? ''));
            $postalCode = trim((string)($_POST['postal_code'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $countryCode = strtoupper(trim((string)($_POST['country_code'] ?? 'CH')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $newPassword = (string)($_POST['new_password'] ?? '');

            if ($firstName === '' || $lastName === '') {
                flash('error', 'Vorname und Nachname sind erforderlich.');
                header('Location: index.php?page=profile');
                exit;
            }
            if (!isset($countryOptions[$countryCode])) {
                $countryCode = 'CH';
            }

            db()->beginTransaction();
            try {
                db()->prepare('UPDATE users SET first_name = ?, last_name = ?, street = ?, house_number = ?, postal_code = ?, city = ?, country_code = ?, phone = ? WHERE id = ?')
                    ->execute([$firstName, $lastName, $street, $houseNumber, $postalCode, $city, $countryCode, $phone, $userId]);

                if ($newPassword !== '') {
                    if (strlen($newPassword) < 8) {
                        db()->rollBack();
                        flash('error', 'Neues Passwort ist zu kurz (min. 8 Zeichen).');
                        header('Location: index.php?page=profile');
                        exit;
                    }
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                }

                db()->commit();
                $_SESSION['user']['first_name'] = $firstName;
                $_SESSION['user']['last_name'] = $lastName;
                $_SESSION['user']['street'] = $street;
                $_SESSION['user']['house_number'] = $houseNumber;
                $_SESSION['user']['postal_code'] = $postalCode;
                $_SESSION['user']['city'] = $city;
                $_SESSION['user']['country_code'] = $countryCode;
                $_SESSION['user']['phone'] = $phone;

                audit_log('update', 'profile', $userId);
                flash('success', 'Profil gespeichert.');
                header('Location: index.php?page=profile');
                exit;
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('error', 'Profil konnte nicht gespeichert werden.');
                header('Location: index.php?page=profile');
                exit;
            }
        }

        $profileStmt = db()->prepare('SELECT id, first_name, last_name, street, house_number, postal_code, city, country_code, phone, email FROM users WHERE id = ?');
        $profileStmt->execute([$userId]);
        $profile = $profileStmt->fetch();
        if (!$profile) {
            flash('error', 'Benutzer nicht gefunden.');
            header('Location: index.php?page=logout');
            exit;
        }

        render('Mein Profil', 'profile', compact('profile', 'countryOptions'));
        break;

    case 'audit':
        require_role('admin');
        $logs = db()->query("SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS actor
            FROM audit_logs a
            JOIN users u ON u.id = a.actor_user_id
            ORDER BY a.created_at DESC
            LIMIT 300")->fetchAll();

        render('Audit-Log', 'audit', compact('logs'));
        break;

    default:
        http_response_code(404);
        echo 'Seite nicht gefunden.';
}
