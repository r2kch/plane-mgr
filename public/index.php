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
    'rates' => 'billing',
    'invoices' => 'billing',
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
        $hasUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        if ($hasUsers) {
            flash('error', 'Installation bereits abgeschlossen.');
            header('Location: index.php?page=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=install');
                exit;
            }

            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($firstName === '' || $lastName === '' || $email === '' || strlen($password) < 8) {
                flash('error', 'Bitte alle Felder ausfüllen (Passwort min. 8 Zeichen).');
                header('Location: index.php?page=install');
                exit;
            }

            db()->beginTransaction();
            try {
                $stmt = db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
                $stmt->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int)db()->lastInsertId();

                $roleStmt = db()->prepare("SELECT id FROM roles WHERE name = 'admin'");
                $roleStmt->execute();
                $roleId = (int)$roleStmt->fetchColumn();
                db()->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);

                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                flash('error', 'Admin konnte nicht angelegt werden.');
                header('Location: index.php?page=install');
                exit;
            }
            flash('success', 'Admin wurde angelegt. Bitte einloggen.');
            header('Location: index.php?page=login');
            exit;
        }

        render('Installation', 'install');
        break;

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
            $invoiceCountStmt = db()->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND payment_status IN ('open', 'part_paid', 'overdue')");
            $invoiceCountStmt->execute([$dashboardUserId]);
            $counts['invoices_open'] = (int)$invoiceCountStmt->fetchColumn();
        }

        $upcomingReservations = [];
        $calendarStartDate = date('Y-m-d');
        $calendarEndDate = date('Y-m-d');
        $calendarDaysCount = 7;
        $calendarAircraft = [];
        $calendarReservationsByAircraft = [];
        $groupRestrictedPilot = is_group_restricted_pilot();
        $allowedAircraftIds = $groupRestrictedPilot ? permitted_aircraft_ids_for_user((int)current_user()['id']) : [];

        if ($showReservationsModule) {
            $upcomingSql = "SELECT r.id, r.user_id, r.starts_at, r.ends_at, r.notes, a.immatriculation,
                    CONCAT(u.first_name, ' ', u.last_name) AS pilot_name
                FROM reservations r
                JOIN aircraft a ON a.id = r.aircraft_id
                JOIN users u ON u.id = r.user_id
                WHERE r.status = 'booked' AND r.starts_at >= NOW()";
            $upcomingParams = [];
            $upcomingSql .= ' ORDER BY COALESCE(a.type, \'\') ASC, a.immatriculation ASC, r.starts_at ASC LIMIT 100';
            $upcomingStmt = db()->prepare($upcomingSql);
            $upcomingStmt->execute($upcomingParams);
            $upcomingReservations = $upcomingStmt->fetchAll();

            $calendarStartInput = (string)($_GET['calendar_start'] ?? date('Y-m-d'));
            $calendarStartTs = strtotime($calendarStartInput . ' 00:00:00');
            if ($calendarStartTs === false) {
                $calendarStartTs = strtotime(date('Y-m-d') . ' 00:00:00');
            }
            $calendarStartDate = date('Y-m-d', $calendarStartTs);
            $calendarEndDate = date('Y-m-d', strtotime($calendarStartDate . ' +' . ($calendarDaysCount - 1) . ' days'));
            $calendarStartBound = $calendarStartDate . ' 00:00:00';
            $calendarEndBound = $calendarEndDate . ' 23:59:59';

            $calendarAircraft = db()->query("SELECT id, immatriculation, type
                FROM aircraft
                WHERE status = 'active'
                ORDER BY immatriculation ASC")->fetchAll();
            foreach ($calendarAircraft as &$aircraftRow) {
                $aircraftRow['can_link'] = !$groupRestrictedPilot || in_array((int)$aircraftRow['id'], $allowedAircraftIds, true);
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

        render('Dashboard', 'dashboard', compact(
            'counts',
            'showReservationsModule',
            'showBillingModule',
            'upcomingReservations',
            'calendarStartDate',
            'calendarEndDate',
            'calendarDaysCount',
            'calendarAircraft',
            'calendarReservationsByAircraft'
        ));
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
        render('Flugzeuge', 'aircraft', compact('aircraft', 'openAircraftId', 'showNewAircraftForm'));
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
        require_role('admin');
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

            $flightOwnerStmt = db()->prepare("SELECT rf.id, rf.reservation_id
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

        render('Durchgeführte Flüge', 'aircraft_flights', compact('aircraft', 'flights', 'pilots', 'editFlightId'));
        break;

    case 'users':
        require_role('admin');
        $userSearch = trim((string)($_GET['q'] ?? ''));
        $openUserId = (int)($_GET['open_user_id'] ?? 0);
        $showNewUserForm = ((int)($_GET['new'] ?? 0)) === 1;
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
                $email = trim((string)($_POST['email'] ?? ''));
                $roles = array_values(array_filter((array)($_POST['roles'] ?? [])));
                $groupIds = array_values(array_unique(array_map(static fn($id): int => (int)$id, (array)($_POST['group_ids'] ?? []))));
                $groupIds = array_values(array_intersect($validGroupIds, $groupIds));
                $password = (string)($_POST['password'] ?? '');

                $validRoles = ['admin', 'pilot', 'accounting'];
                $roles = array_values(array_intersect($validRoles, $roles));

                if ($firstName === '' || $lastName === '' || $email === '' || count($roles) === 0 || strlen($password) < 8) {
                    flash('error', 'Ungültige Eingaben (Passwort min. 8 Zeichen).');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }

                try {
                    db()->beginTransaction();
                    $stmt = db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)');
                    $stmt->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
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
                $email = trim((string)($_POST['email'] ?? ''));
                $roles = array_values(array_filter((array)($_POST['roles'] ?? [])));
                $groupIds = array_values(array_unique(array_map(static fn($id): int => (int)$id, (array)($_POST['group_ids'] ?? []))));
                $groupIds = array_values(array_intersect($validGroupIds, $groupIds));
                $isActive = ((string)($_POST['is_active'] ?? '1')) === '1' ? 1 : 0;
                $newPassword = (string)($_POST['new_password'] ?? '');
                $validRoles = ['admin', 'pilot', 'accounting'];
                $roles = array_values(array_intersect($validRoles, $roles));

                if ($userId <= 0 || $firstName === '' || $lastName === '' || $email === '' || count($roles) === 0) {
                    flash('error', 'Ungültige Eingaben.');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }

                if ($userId === (int)current_user()['id'] && $isActive === 0) {
                    flash('error', 'Eigener Benutzer kann nicht deaktiviert werden.');
                    header('Location: ' . $usersPageUrl);
                    exit;
                }

                try {
                    db()->beginTransaction();
                    $stmt = db()->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, is_active = ? WHERE id = ?');
                    $stmt->execute([$firstName, $lastName, $email, $isActive, $userId]);

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

        $usersSql = "SELECT u.id, u.first_name, u.last_name, u.email, u.is_active,
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
        $usersSql .= ' GROUP BY u.id, u.first_name, u.last_name, u.email, u.is_active
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

        render('Benutzer', 'users', compact('users', 'userSearch', 'openUserId', 'showNewUserForm', 'allGroups', 'userGroupIdsByUser'));
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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['_csrf'] ?? null)) {
                flash('error', 'Ungültiger Request.');
                header('Location: index.php?page=reservations&month=' . urlencode($month));
                exit;
            }

            $action = $_POST['action'] ?? '';
            if ($action === 'create' && can('reservation.create')) {
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

                if (!$isAircraftPermittedForCurrentUser($aircraftId)) {
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

                db()->prepare("UPDATE reservations SET status = 'cancelled', cancelled_by = ? WHERE id = ?")
                    ->execute([(int)current_user()['id'], $reservationId]);
                audit_log('cancel', 'reservation', $reservationId);
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
                        (reservation_id, pilot_user_id, from_airfield, to_airfield, start_time, landing_time, landings_count, hobbs_start, hobbs_end, hobbs_hours)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

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
                JOIN user_aircraft_groups uag ON uag.group_id = a.aircraft_group_id
                WHERE uag.user_id = ?
                  AND a.status = 'active'
                  AND a.aircraft_group_id IS NOT NULL
                GROUP BY a.id, a.immatriculation, a.type, a.status, a.start_hobbs, a.start_landings
                ORDER BY a.immatriculation");
            $aircraftStmt->execute([$currentUserId]);
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
        if ($groupRestrictedPilot) {
            if ($permittedAircraftIds === []) {
                $sql .= ' AND 1 = 0';
            } else {
                $placeholders = implode(',', array_fill(0, count($permittedAircraftIds), '?'));
                $sql .= " AND r.aircraft_id IN ($placeholders)";
                foreach ($permittedAircraftIds as $id) {
                    $params[] = $id;
                }
            }
        }

        $sql .= ' ORDER BY r.starts_at';
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

                $stmt = db()->prepare("SELECT r.id,
                        COALESCE(SUM(rf.hobbs_hours), r.hours) AS billable_hours,
                        r.aircraft_id, a.immatriculation, a.base_hourly_rate
                    FROM reservations r
                    JOIN aircraft a ON a.id = r.aircraft_id
                    LEFT JOIN reservation_flights rf ON rf.reservation_id = r.id
                    WHERE r.user_id = ?
                      AND r.status = 'completed'
                      AND r.invoice_id IS NULL
                      AND DATE(r.starts_at) BETWEEN ? AND ?
                    GROUP BY r.id, r.hours, r.aircraft_id, a.immatriculation, a.base_hourly_rate");
                $stmt->execute([$userId, $from, $to]);
                $rows = $stmt->fetchAll();

                if (!$rows) {
                    flash('error', 'Keine abrechenbaren Flüge für Zeitraum.');
                    header('Location: index.php?page=invoices');
                    exit;
                }

                $invoiceNumber = 'R' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

                db()->beginTransaction();
                try {
                    $stmt = db()->prepare('INSERT INTO invoices (invoice_number, user_id, period_from, period_to, created_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$invoiceNumber, $userId, $from, $to, (int)current_user()['id']]);
                    $invoiceId = (int)db()->lastInsertId();

                    $total = 0.0;
                    $itemStmt = db()->prepare('INSERT INTO invoice_items (invoice_id, reservation_id, description, hours, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
                    $resStmt = db()->prepare('UPDATE reservations SET invoice_id = ? WHERE id = ?');

                    foreach ($rows as $row) {
                        $rate = user_rate_for_aircraft($userId, (int)$row['aircraft_id']) ?? (float)$row['base_hourly_rate'];
                        $billableHours = (float)$row['billable_hours'];
                        $lineTotal = round($billableHours * $rate, 2);
                        $total += $lineTotal;

                        $desc = sprintf('Flug %s (%s h Hobbs)', $row['immatriculation'], number_format($billableHours, 2, '.', ''));
                        $itemStmt->execute([$invoiceId, (int)$row['id'], $desc, $billableHours, $rate, $lineTotal]);
                        $resStmt->execute([$invoiceId, (int)$row['id']]);
                    }

                    db()->prepare('UPDATE invoices SET total_amount = ? WHERE id = ?')->execute([round($total, 2), $invoiceId]);
                    db()->commit();

                    audit_log('create', 'invoice', $invoiceId, ['invoice_number' => $invoiceNumber]);
                    flash('success', 'Rechnung erstellt: ' . $invoiceNumber);
                } catch (Throwable $e) {
                    db()->rollBack();
                    flash('error', 'Rechnung konnte nicht erstellt werden.');
                }
            }

            if ($action === 'generate_open_for_pilot') {
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($userId <= 0) {
                    flash('error', 'Ungültiger Pilot.');
                    header('Location: index.php?page=invoices');
                    exit;
                }

                $stmt = db()->prepare("SELECT r.id,
                        COALESCE(SUM(rf.hobbs_hours), r.hours) AS billable_hours,
                        r.aircraft_id, a.immatriculation, a.base_hourly_rate,
                        DATE(r.starts_at) AS start_date
                    FROM reservations r
                    JOIN aircraft a ON a.id = r.aircraft_id
                    LEFT JOIN reservation_flights rf ON rf.reservation_id = r.id
                    WHERE r.user_id = ?
                      AND r.status = 'completed'
                      AND r.invoice_id IS NULL
                    GROUP BY r.id, r.hours, r.aircraft_id, a.immatriculation, a.base_hourly_rate, DATE(r.starts_at)
                    ORDER BY r.starts_at ASC");
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll();

                if (!$rows) {
                    flash('error', 'Keine offenen Stunden für diesen Pilot.');
                    header('Location: index.php?page=invoices');
                    exit;
                }

                $periodFrom = (string)$rows[0]['start_date'];
                $periodTo = (string)$rows[count($rows) - 1]['start_date'];
                $invoiceNumber = 'R' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

                db()->beginTransaction();
                try {
                    $stmt = db()->prepare('INSERT INTO invoices (invoice_number, user_id, period_from, period_to, created_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$invoiceNumber, $userId, $periodFrom, $periodTo, (int)current_user()['id']]);
                    $invoiceId = (int)db()->lastInsertId();

                    $total = 0.0;
                    $itemStmt = db()->prepare('INSERT INTO invoice_items (invoice_id, reservation_id, description, hours, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
                    $resStmt = db()->prepare('UPDATE reservations SET invoice_id = ? WHERE id = ?');

                    foreach ($rows as $row) {
                        $rate = user_rate_for_aircraft($userId, (int)$row['aircraft_id']) ?? (float)$row['base_hourly_rate'];
                        $billableHours = (float)$row['billable_hours'];
                        $lineTotal = round($billableHours * $rate, 2);
                        $total += $lineTotal;

                        $desc = sprintf('Flug %s (%s h Hobbs)', $row['immatriculation'], number_format($billableHours, 2, '.', ''));
                        $itemStmt->execute([$invoiceId, (int)$row['id'], $desc, $billableHours, $rate, $lineTotal]);
                        $resStmt->execute([$invoiceId, (int)$row['id']]);
                    }

                    db()->prepare('UPDATE invoices SET total_amount = ? WHERE id = ?')->execute([round($total, 2), $invoiceId]);
                    db()->commit();

                    audit_log('create', 'invoice', $invoiceId, ['invoice_number' => $invoiceNumber, 'source' => 'open_pilot']);
                    flash('success', 'Rechnung erstellt: ' . $invoiceNumber);
                } catch (Throwable $e) {
                    db()->rollBack();
                    flash('error', 'Rechnung konnte nicht erstellt werden.');
                }
            }

            if ($action === 'status') {
                $invoiceId = (int)$_POST['invoice_id'];
                $status = (string)$_POST['payment_status'];
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

                db()->beginTransaction();
                try {
                    db()->prepare('UPDATE reservations SET invoice_id = NULL WHERE invoice_id = ?')->execute([$invoiceId]);
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
                    flash('success', 'Rechnung storniert. Stunden sind wieder unverrechnet.');
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash('error', 'Rechnung konnte nicht storniert werden.');
                }
            }

            if ($action === 'mail') {
                $invoiceId = (int)$_POST['invoice_id'];
                $stmt = db()->prepare('SELECT i.invoice_number, u.email FROM invoices i JOIN users u ON u.id = i.user_id WHERE i.id = ?');
                $stmt->execute([$invoiceId]);
                $invoice = $stmt->fetch();

                if ($invoice) {
                    $subject = 'Rechnung ' . $invoice['invoice_number'];
                    $message = "Ihre Rechnung {$invoice['invoice_number']} wurde erstellt.";
                    @mail($invoice['email'], $subject, $message);

                    db()->prepare('UPDATE invoices SET mailed_at = NOW() WHERE id = ?')->execute([$invoiceId]);
                    audit_log('mail', 'invoice', $invoiceId, ['to' => $invoice['email']]);
                    flash('success', 'E-Mail Versand angestossen (mail()).');
                }
            }

            header('Location: index.php?page=invoices');
            exit;
        }

        $invoiceStatusFilter = (string)($_GET['invoice_status'] ?? 'unpaid');
        $invoiceSearch = trim((string)($_GET['invoice_q'] ?? ''));
        $allowedInvoiceFilters = ['unpaid', 'all', 'open', 'part_paid', 'overdue', 'paid'];
        if (!in_array($invoiceStatusFilter, $allowedInvoiceFilters, true)) {
            $invoiceStatusFilter = 'unpaid';
        }

        $invoiceSql = "SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) AS customer_name
            FROM invoices i
            JOIN users u ON u.id = i.user_id
            WHERE 1=1";
        $invoiceParams = [];

        if ($invoiceStatusFilter === 'unpaid') {
            $invoiceSql .= " AND i.payment_status IN ('open', 'part_paid', 'overdue')";
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
                    (reservation_id, pilot_user_id, from_airfield, to_airfield, start_time, landing_time, landings_count, hobbs_start, hobbs_end, hobbs_hours)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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

    case 'invoice_pdf':
        require_role('admin', 'accounting');
        $invoiceId = (int)($_GET['id'] ?? 0);

        $stmt = db()->prepare('SELECT i.*, CONCAT(u.first_name, " ", u.last_name) AS customer_name, u.email
            FROM invoices i
            JOIN users u ON u.id = i.user_id
            WHERE i.id = ?');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            http_response_code(404);
            exit('Rechnung nicht gefunden.');
        }

        $itemsStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id');
        $itemsStmt->execute([$invoiceId]);
        $items = $itemsStmt->fetchAll();

        render('Rechnung ' . $invoice['invoice_number'], 'invoice_pdf', compact('invoice', 'items'));
        break;

    case 'my_invoices':
        $userId = (int)current_user()['id'];

        $openStmt = db()->prepare("SELECT *
            FROM invoices
            WHERE user_id = ?
              AND payment_status IN ('open', 'part_paid', 'overdue')
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
