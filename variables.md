# Invoice Template Variables

Diese Variablen stehen in `public/templates/invoice_layout.php` zur Verfügung.

## Basisobjekte

- `$invoice` (array)
  - `id`
  - `invoice_number`
  - `user_id`
  - `period_from` (`Y-m-d`)
  - `period_to` (`Y-m-d`)
  - `total_amount`
  - `payment_status`
  - `created_at`

- `$items` (array von Positionszeilen)
  - `id`
  - `invoice_id`
  - `reservation_id`
  - `flight_date` (`Y-m-d`, optional)
  - `aircraft_type` (optional)
  - `aircraft_immatriculation` (optional)
  - `from_airfield` (optional)
  - `to_airfield` (optional)
  - `description`
  - `hours`
  - `unit_price`
  - `line_total`

- `$credits` (array von Gutschriftenzeilen)
  - `id`
  - `credit_date` (`Y-m-d`)
  - `amount` (positiver Betrag, wird als Abzug verwendet)
  - `description`
  - `notes` (optional)

- `$summary` (array, Summenbereich Rechnung)
  - `flights_subtotal`
  - `credits_total`
  - `vat_amount`
  - `total_amount`

## Empfänger/Kunde

- `$customerAddress` (array)
  - `name`
  - `street_line`
  - `city_line`
  - `country`
  - `email`
  - `phone`

## Rechnungs-Meta

- `$invoiceMeta` (array)
  - `title`
  - `currency`
  - `payment_target_days`
  - `due_date` (`d.m.Y`)

## Absender

- `$issuer` (array)
  - `name`
  - `street`
  - `house_number`
  - `postal_code`
  - `city`
  - `country`
  - `email`
  - `phone`
  - `website`

## Bank

- `$bank` (array)
  - `recipient`
  - `iban`
  - `bic` (optional)
  - `bank_name`
  - `bank_address`

## MWST

- `$vat` (array)
  - `enabled` (bool)
  - `rate_percent`
  - `uid`

## Logo

- `$logoPublicPath` (string)
  - Relativer Web-Pfad, z. B. `logo.png`
  - Wenn leer, wird kein Logo angezeigt.

## Wichtige Config-Keys

Diese Werte kommen aus `public/Config.php`:

- `invoice.title`
- `invoice.currency`
- `invoice.payment_target_days`
- `invoice.logo_path`
- `invoice.vat.enabled`
- `invoice.vat.rate_percent`
- `invoice.vat.uid`
- `invoice.issuer.*`
- `invoice.bank.*`

## Mail-Template Variablen

Diese Platzhalter können in folgenden Dateien verwendet werden:

- `public/templates/mail_invoice_subject.txt`
- `public/templates/mail_invoice_body.txt`
- `public/templates/mail_invoice_cancel_subject.txt`
- `public/templates/mail_invoice_cancel_body.txt`
- `public/templates/mail_invoice_reminder_subject.txt`
- `public/templates/mail_invoice_reminder_body.txt`
- `public/templates/mail_reservation_subject.txt`
- `public/templates/mail_reservation_body.txt`
- `public/templates/mail_reservation_update_subject.txt`
- `public/templates/mail_reservation_update_body.txt`
- `public/templates/mail_reservation_cancel_subject.txt`
- `public/templates/mail_reservation_cancel_body.txt`

Verfügbare Platzhalter:

- `{issuer.name}`
- `{issuer.full}`
- `{invoice.invoice_number}`
- `{invoiceMeta.due_date}`
- `{customer.first_name}`
- `{customer.last_name}`
- `{customer.name}`

Zusätzliche Alias-Platzhalter:

- `{NUMMER}` (gleich wie `{invoice.invoice_number}`)
- `{Vorname}` (gleich wie `{customer.first_name}`)
- `{zahlbar bis}` (gleich wie `{invoiceMeta.due_date}`)

Zusätzliche Platzhalter für Reservierungs-Mails:

- `{reservation.id}`
- `{reservation.aircraft}`
- `{reservation.immatriculation}`
- `{reservation.aircraft_type}`
- `{reservation.start}` (`d.m.Y H:i`)
- `{reservation.end}` (`d.m.Y H:i`)
- `{reservation.notes}`
- `{reservation.notes_or_dash}`
