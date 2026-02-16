# Plane Manager 

Klassische PHP/MySQL-Reservations- und Abrechnungssoftware für eine Flugsportgruppe

## Enthaltene Funktionen
- Login mit Mehrfachrollen: `admin`, `pilot`, `accounting`
- Benutzerverwaltung (Admin): Benutzer anlegen, mehrere Rollen vergeben, aktiv/deaktiviert, Passwort setzen
- Reservierungen pro Flugzeug (anlegen, bearbeiten, löschen) inkl. Durchführung
- Reservierungs-Benachrichtigungen per E-Mail an den Pilot:
  - bei Neu und Änderung mit ICS-Anhang (Kalenderimport)
  - bei Storno ohne ICS-Anhang
- Durchführung erfassen mit mehreren Flügen pro Reservierung (Pilot, Von/Nach, Zeiten, Hobbs)
- Flugzeugverwaltung (Immatrikulation, Typ, Status, Basispreis)
- Preisverwaltung je Pilot und Flugzeug (`aircraft_user_rates`)
- Gutschriften (Spesen/Barausgaben) für Admin/Buchhaltung:
  - Erfassung/Bearbeitung/Löschung unverrechneter Gutschriften
  - automatische Verrechnung als Abzug in der nächsten Rechnung
- Rechnungserzeugung aus offenen Stunden (pro Pilot)
- Rechnungsstorno setzt verknüpfte Reservierungen und Gutschriften wieder auf unverrechnet zurück
- Zahlungsstatus (`open`, `paid`, `overdue`)
- Rechnungsansicht (`invoice_pdf`) als HTML/PDF-Basis
- Meine Rechnungen: eigene offenen/bezahlten Rechnungen
- SMTP Versand für:
  - Rechnung beim Erzeugen (optional per Checkbox)
  - Storno-Mail beim Stornieren
  - Zahlungserinnerung bei `overdue`
  - jeweils mit PDF-Anhang
- Audit-Log (nur Admin)
- Rollen-/Rechtematrix Vorlage: `docs/rollen_rechte_vorlage.csv`


## Setup
siehe [DEPLOY.md](DEPLOY.md) (nur `public/` hochladen) und `public/setup.php` aufrufen


## Lokale Entwicklung mit Docker
- Starten: `docker compose up -d --build`
- Stoppen: `docker compose down`
- App: `http://localhost:8888`
- DB Host (für lokale Tools): `127.0.0.1`, Port `3307`
- DB Name: `plane_mgr`
- DB User: `plane`
- DB Passwort: `planepass`
- Root Passwort: `rootpass`
- Das Schema wird beim ersten Start automatisch aus `sql/schema.sql` importiert.


## Module-Schalter (global)
In `public/Config.php` können Module für alle Benutzer global aktiviert/deaktiviert werden:

```php
'modules' => [
    'reservations' => true,
    'billing' => true,
    'audit' => true,
],
```

- `reservations = false`:
  - Menü `Reservierungen` ausgeblendet
  - Seite `index.php?page=reservations` gesperrt
  - Dashboard: Kalender + zukünftige Reservierungen ausgeblendet

- `billing = false`:
  - Menüs `Meine Rechnungen`, `Preise`, `Abrechnung`, `Gutschrift` ausgeblendet
  - Seiten `my_invoices`, `rates`, `invoices`, `invoice_pdf`, `manual_flight`, `accounting_flights`, `credits` gesperrt
  - Dashboard: Karte `Offene Rechnungen` ausgeblendet

- `audit = false`:
  - Menü `Audit-Log` ausgeblendet
  - Seite `index.php?page=audit` gesperrt

## Hinweise für Entwicklung:
- `public/cleanup.php` (nur Admin) löscht Rechnungen/Rechnungspositionen und setzt Reservierungen auf "nicht verrechnet" zurück (`invoice_id = NULL`).
- `public/debug.php` enthält einen SMTP-Test (Testmail an frei wählbare Adresse).

## SMTP Konfiguration
In `public/Config.php`:

```php
'smtp' => [
    'enabled' => true, // false = kein Versand
    'host' => 'mail.example.com',
    'port' => 25,
    'user' => '',
    'pass' => '',
    'from' => 'bill@example.com',
    'from_name' => 'Plane Manager Notification',
],
```

- Wenn `user`/`pass` leer sind: Versand ohne SMTP-Authentifizierung.
- Wenn `user`/`pass` gesetzt sind: Versand mit `AUTH LOGIN`.
- Wenn `enabled = false`: Es werden keine E-Mails versendet.
- Reservierungs-Mails und Rechnungs-Mails nutzen denselben SMTP-Block.

## Mail-Templates
Editierbare Dateien im Dateisystem:
- Rechnung:
  - `public/templates/mail_invoice_subject.txt`
  - `public/templates/mail_invoice_body.txt`
- Storno:
  - `public/templates/mail_invoice_cancel_subject.txt`
  - `public/templates/mail_invoice_cancel_body.txt`
- Zahlungserinnerung:
  - `public/templates/mail_invoice_reminder_subject.txt`
  - `public/templates/mail_invoice_reminder_body.txt`
- Reservierung neu:
  - `public/templates/mail_reservation_subject.txt`
  - `public/templates/mail_reservation_body.txt`
- Reservierung geändert:
  - `public/templates/mail_reservation_update_subject.txt`
  - `public/templates/mail_reservation_update_body.txt`
- Reservierung storniert:
  - `public/templates/mail_reservation_cancel_subject.txt`
  - `public/templates/mail_reservation_cancel_body.txt`

Verfügbare Platzhalter sind in `variables.md` dokumentiert.

## Verzeichnis
- `public/index.php`: Front Controller
- `public/Config.php`: Konfiguration inkl. DB/SMTP Zugangsdaten
- `public/assets/styles.css`: alle Styles/Themes in einer CSS-Datei
- `public/app/bootstrap.php`: DB/Auth/Permissions/Helper
- `public/app/layout.php`: globales Layout
- `public/app/views/*`: Seiten
- `sql/schema.sql`: Datenbankmodell + Seed
