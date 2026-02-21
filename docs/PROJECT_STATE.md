# Projektstand (Plane Manager)

## Kurzüberblick
- Klassische PHP/MySQL App im `public/` Ordner (Front Controller: `public/index.php`)
- DB Schema in `sql/schema.sql`, Auto‑Migrations in `public/app/bootstrap.php`
- Rollen: `admin`, `pilot`, `accounting`, `board`, `member`, `member_passive`
- Berechtigungen: DB‑basiert (`permissions`, `role_permissions`), Admin hat immer alles
- Modules: `reservations`, `billing`, `audit` (in `public/Config.php`)

## Zentrale Features
- Reservierungen pro Flugzeug, Admin/Pilot darf reservieren (andere Rollen nicht).
- Durchführung erfassen mit mehreren Flügen pro Reservierung (Hobbs, Landungen).
- Flugzeuge/Flugzeug‑Gruppen, Gruppenrechte für Piloten (Admin darf alles).
- Abrechnung:
  - Rechnungserstellung aus offenen Flügen + **besonderen Positionen**
  - Gutschriften (Spesen) als Abzug
  - PDF/HTML Rechnung via Dompdf
  - E‑Mail Versand (Rechnung, Storno, Zahlungserinnerung) mit PDF‑Anhang
- News: HTML‑Beiträge, Rollensteuerung in `Config.php`

## Wichtige Seiten
- `index.php?page=reservations`
- `index.php?page=invoices` (Abrechnung)
- `index.php?page=credits` (Gutschriften)
- `index.php?page=positions` (Besondere Positionen)
- `index.php?page=manual_flight` (Flug händisch)
- `index.php?page=accounting` (Buchhaltung Hub)
- `index.php?page=aircraft_flights&aircraft_id=...` (Logbuch/Verrechenbar)

## Rechnungslogik (Kurz)
- Flüge: `reservation_flights` (billable + unverrechnet)
- Gutschriften: `credits` (invoice_id NULL = offen)
- **Besondere Positionen**: `flex_positions` (invoice_id NULL = offen)
- Rechnung = Flüge + Besondere Positionen – Gutschriften + MWST
- Storno: setzt `invoice_id` in Reservierungen, Gutschriften, Positionen zurück

## Tabellen (neue/erweiterte)
- `invoices.positions_total` (DECIMAL)
- `credits` (Gutschriften)
- `flex_positions` (besondere Positionen)

## Mail‑Templates
`public/templates/`:
- Rechnungen: `mail_invoice_*`
- Storno: `mail_invoice_cancel_*`
- Reminder: `mail_invoice_reminder_*`
- Reservierung neu/Update/Cancel: `mail_reservation_*`
Variablen: `variables.md`

## Dompdf
- Manuell in `public/vendor/dompdf`
- `public/setup.php` prüft Installation

## Konventionen
- Betrag mit Punkt als Dezimaltrenner
- Währungsausgabe: `CHF 123.45`
- UI‑Styles zentral in `public/assets/styles.css`

## Offene Themen / ToDos (laufend)
- UI‑Feinschliff an einzelnen Tabellen/Layouts
- Neue Features jeweils in `README.md` ergänzen
