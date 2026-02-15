# Deployment (FTP, ohne SSH)

Diese Anleitung ist für generisches Webhosting mit FTP-Zugang.
Ziel: Nur **ein Verzeichnis** auf den Webspace laden: `public/`.

## 1) Dompdf bereitstellen

Lade Dompdf manuell von GitHub und kopiere den Ordner nach:

- `public/vendor/dompdf`

Wichtig:
- Die Datei `public/vendor/dompdf/autoload.inc.php` muss vorhanden sein.
- Ohne diese Datei ist PDF nicht verfügbar.

## 2) Dateien hochladen

Per FTP auf den Webserver hochladen:

- Nur `public/` (inkl. `index.php`, `assets/`, `app/`, `templates/`, `setup.php`)

Nicht hochladen:

- `.git/`
- lokale Docker-Dateien (optional)
- lokale DB-Dateien
- `.DS_Store`

## 3) Config auf Server anlegen

Auf dem Server in `public/`:

1. `Config.example.php` nach `Config.php` kopieren
2. Werte setzen:
   - DB-Zugang
   - Rechnungsdaten (Verein, IBAN, MWST etc.)
   - Modul-Schalter

## 4) Webroot prüfen

Der Webroot (Document Root) muss auf `public/` zeigen.

Falls dein Hoster keinen Webroot-Wechsel erlaubt:
- gesamten Inhalt von `public/` in den Host-Webroot legen

## 5) PHP-Erweiterungen prüfen

Pflicht:

- `pdo_mysql`
- `gd` (für PDF/Logo mit Dompdf)
- `mbstring`
- `dom` / `xml`

Wenn `gd` fehlt, kann Dompdf mit Bildern/Logo fehlschlagen.

## 6) Rechte / Verzeichnisse

Sicherstellen, dass der Webserver schreiben darf auf:

- `public/storage/invoices/` (falls dort Rechnungen abgelegt werden)

## 7) Funktionstest nach Upload

1. `setup.php` aufrufen und Status prüfen
2. ersten Admin erstellen
3. Login testen
4. Rechnung erzeugen
5. `Rechnung HTML` öffnen
6. `Rechnung PDF` öffnen

Wenn PDF fehlschlägt:

- Prüfen, ob `public/vendor/dompdf/autoload.inc.php` vorhanden ist
- Prüfen, ob `gd` aktiv ist
- Prüfen, ob `public/logo.png` existiert

## 8) Updates einspielen

Bei neuem Release:

1. Code aktualisieren
2. Nur `public/` per FTP überschreiben
3. Browser-Cache leeren (Hard Reload)
