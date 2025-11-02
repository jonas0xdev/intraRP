# Registrierungsmodus-Konfiguration

## Übersicht

Das intraRP-System unterstützt drei verschiedene Registrierungsmodi, die über die Konfigurationsvariable `REGISTRATION_MODE` in `/assets/config/config.php` gesteuert werden.

## Verfügbare Modi

### 1. Open (Offen)
```php
define('REGISTRATION_MODE', 'open');
```
- **Verhalten**: Registrierung ist für jeden möglich
- **Verwendung**: Standard-Modus für offene Systeme
- Neue Benutzer werden automatisch mit der Standard-Rolle erstellt

### 2. Code (Mit Einladungscode)
```php
define('REGISTRATION_MODE', 'code');
```
- **Verhalten**: Registrierung nur mit gültigem Einladungscode möglich
- **Verwendung**: Für geschlossene Gruppen mit kontrolliertem Zugang
- Administratoren können Codes über die Benutzerverwaltung generieren
- Jeder Code kann nur einmal verwendet werden

### 3. Closed (Geschlossen)
```php
define('REGISTRATION_MODE', 'closed');
```
- **Verhalten**: Keine Registrierung für neue Benutzer möglich
- **Verwendung**: Für vollständig geschlossene Systeme
- Nur bestehende Benutzer können sich einloggen

## Registrierungscodes verwalten

### Zugriff
Benutzer mit den Berechtigungen `admin` oder `users.manage` können Registrierungscodes verwalten unter:
```
/benutzer/registration-codes.php
```

### Funktionen
- **Code generieren**: Erstellt einen neuen 16-stelligen Einladungscode
- **Code löschen**: Löscht unbenutzte Codes
- **Übersicht**: Zeigt alle Codes mit Status (verfügbar/verwendet)

### Code-Verwendung
1. Administrator generiert einen Code
2. Code wird an neuen Benutzer weitergegeben
3. Benutzer meldet sich via Discord an
4. System erkennt, dass Benutzer neu ist
5. Benutzer wird zur Code-Eingabe weitergeleitet (`/auth/register-code.php`)
6. Nach erfolgreicher Code-Eingabe wird Konto erstellt
7. Code wird als verwendet markiert

## Datenbankstruktur

### Tabelle: intra_registration_codes
```sql
CREATE TABLE `intra_registration_codes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `code` varchar(255) NOT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `used_by` int(11) DEFAULT NULL,
    `used_at` timestamp NULL DEFAULT NULL,
    `is_used` tinyint(1) DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`),
    KEY `created_by` (`created_by`),
    KEY `used_by` (`used_by`)
);
```

## Implementierungsdetails

### Ablauf bei neuem Benutzer
1. Discord-OAuth-Callback prüft, ob Benutzer existiert
2. Falls nicht, wird `REGISTRATION_MODE` geprüft:
   - **open**: Benutzer wird sofort erstellt
   - **code**: Umleitung zu Code-Eingabe
   - **closed**: Fehlermeldung
3. Bei erfolgreicher Registrierung wird Session erstellt

### Dateien
- `/assets/config/config.php` - Konfiguration
- `/auth/callback.php` - OAuth-Callback mit Modus-Prüfung
- `/auth/register-code.php` - Code-Eingabe-Seite
- `/benutzer/registration-codes.php` - Code-Verwaltung
- `/assets/database/create_intra_registration_codes_02112025.php` - Datenbank-Migration

## Hinweise

- Der erste Benutzer wird immer als Admin erstellt (unabhängig vom Modus)
- Änderungen an `REGISTRATION_MODE` erfordern keine Neustarts
- Codes sind einmalig verwendbar
- Verwendete Codes werden zur Historie gespeichert
