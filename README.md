# Plane Manager 

Klassische PHP/MySQL-Reservations- und Abrechnungssoftware für eine Flugsportgruppe

## Enthaltene Funktionen
- Login mit Mehrfachrollen: `admin`, `pilot`, `accounting`
- Benutzerverwaltung (Admin): Benutzer anlegen, mehrere Rollen vergeben, aktiv/deaktiviert, Passwort setzen
- Reservierungen pro Flugzeug (anlegen, bearbeiten, löschen) inkl. Durchführung
- Durchführung erfassen mit mehreren Flügen pro Reservierung (Pilot, Von/Nach, Zeiten, Hobbs)
- Flugzeugverwaltung (Immatrikulation, Typ, Status, Basispreis)
- Preisverwaltung je Pilot und Flugzeug (`aircraft_user_rates`)
- Rechnungserzeugung aus offenen Stunden (pro Pilot)
- Zahlungsstatus (`open`, `part_paid`, `paid`, `overdue`)
- Rechnungsansicht (`invoice_pdf`) als HTML/PDF-Basis
- Meine Rechnungen: eigene offenen/bezahlten Rechnungen
- Audit-Log (nur Admin)
- Rollen-/Rechtematrix Vorlage: `docs/rollen_rechte_vorlage.csv`


## ToDo
- Rechnungshandling und Design



## Setup
1. Dateien, die auf den Webserver müssen, liegen unter `public/` und werden dort deployt.
2. `public/Config.example.php` nach `public/Config.php` kopieren und DB/SMTP eintragen.
3. SQL aus `sql/schema.sql` in MySQL/MariaDB importieren.
4. Browser öffnen: `index.php?page=install` und ersten Admin anlegen.
5. Danach mit dem neuen Admin anmelden.


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

## Hinweise
- Für echtes PDF in Produktion `dompdf/dompdf` oder `mpdf/mpdf` ergänzen und `invoice_pdf` als Download ausgeben.
- Optional: Für robusten SMTP-Versand `PHPMailer` ergänzen (Host, Port, User, Passwort aus `public/Config.php`).
- Cronjobs sind aktuell nicht erforderlich. Mail-Queue/Mahnläufe können später ergänzt werden.

## Module-Schalter (global)
In `public/Config.php` können Module für alle Benutzer global aktiviert/deaktiviert werden:

```php
'modules' => [
    'reservations' => true,
    'billing' => true,
],
```

- `reservations = false`:
  - Menü `Reservierungen` ausgeblendet
  - Seite `index.php?page=reservations` gesperrt
  - Dashboard: Kalender + zukünftige Reservierungen ausgeblendet

- `billing = false`:
  - Menüs `Meine Rechnungen`, `Preise`, `Abrechnung` ausgeblendet
  - Seiten `my_invoices`, `rates`, `invoices`, `invoice_pdf`, `manual_flight` gesperrt
  - Dashboard: Karte `Offene Rechnungen` ausgeblendet

## Hinweise für Entwicklung:
- `public/cleanup.php` (nur Admin) löscht Rechnungen/Rechnungspositionen und setzt Reservierungen auf "nicht verrechnet" zurück (`invoice_id = NULL`).

## Verzeichnis
- `public/index.php`: Front Controller
- `public/Config.php`: Konfiguration inkl. DB/SMTP Zugangsdaten
- `public/assets/styles.css`: alle Styles/Themes in einer CSS-Datei
- `public/app/bootstrap.php`: DB/Auth/Permissions/Helper
- `public/app/layout.php`: globales Layout
- `public/app/views/*`: Seiten
- `sql/schema.sql`: Datenbankmodell + Seed
