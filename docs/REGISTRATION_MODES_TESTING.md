# Test-Szenarien für Registrierungsmodi

## Vorbereitung
1. Sicherstellen, dass die Datenbank-Migration ausgeführt wurde
2. Discord-OAuth-Konfiguration ist korrekt eingerichtet

## Test-Szenario 1: Open Mode (Offen)

### Konfiguration
```php
define('REGISTRATION_MODE', 'open');
```

### Test-Schritte
1. Abmelden (falls angemeldet)
2. Login-Seite aufrufen (`/login.php`)
3. Erwartetes Ergebnis: Login-Button sichtbar, keine Warnmeldungen
4. Mit neuem Discord-Account anmelden
5. Erwartetes Ergebnis: Benutzer wird automatisch erstellt und eingeloggt
6. Dashboard sollte erreichbar sein

### Erfolgskriterien
- ✅ Neuer Benutzer kann sich ohne Code registrieren
- ✅ Benutzer erhält Standard-Rolle
- ✅ Keine Umleitung zur Code-Eingabe

---

## Test-Szenario 2: Code Mode (Mit Einladungscode)

### Konfiguration
```php
define('REGISTRATION_MODE', 'code');
```

### Test-Schritte

#### Teil 1: Code-Generierung
1. Als Administrator anmelden
2. Zur Seite `/benutzer/registration-codes.php` navigieren
3. Erwartetes Ergebnis: Seite zeigt aktuellen Modus "Code - Registrierung nur mit Code"
4. Auf "Code generieren" klicken
5. Erwartetes Ergebnis: Neuer Code wird erstellt und angezeigt
6. Code notieren (z.B. `abc123def456789`)

#### Teil 2: Registration mit Code
1. Abmelden
2. Login-Seite aufrufen
3. Erwartetes Ergebnis: Hinweis "Neue Benutzer benötigen einen Registrierungscode"
4. Mit neuem Discord-Account anmelden
5. Erwartetes Ergebnis: Umleitung zu `/auth/register-code.php`
6. Generierten Code eingeben
7. Erwartetes Ergebnis: Benutzer wird erstellt und eingeloggt
8. Code sollte als "Verwendet" markiert sein

#### Teil 3: Ungültiger Code
1. Abmelden
2. Mit anderem neuen Discord-Account anmelden
3. Bei Code-Eingabe einen ungültigen Code eingeben
4. Erwartetes Ergebnis: Fehlermeldung "Ungültiger oder bereits verwendeter Registrierungscode"

#### Teil 4: Bereits verwendeter Code
1. Versuchen, den bereits verwendeten Code aus Teil 2 zu nutzen
2. Erwartetes Ergebnis: Fehlermeldung "Ungültiger oder bereits verwendeter Registrierungscode"

### Erfolgskriterien
- ✅ Administrator kann Codes generieren
- ✅ Neue Benutzer benötigen gültigen Code
- ✅ Code kann nur einmal verwendet werden
- ✅ Verwendete Codes werden korrekt markiert
- ✅ Ungültige Codes werden abgelehnt

---

## Test-Szenario 3: Closed Mode (Geschlossen)

### Konfiguration
```php
define('REGISTRATION_MODE', 'closed');
```

### Test-Schritte
1. Abmelden (falls angemeldet)
2. Login-Seite aufrufen
3. Erwartetes Ergebnis: Warnung "Registrierung für neue Benutzer ist derzeit geschlossen"
4. Mit neuem Discord-Account anmelden
5. Erwartetes Ergebnis: Fehlermeldung "Registrierung ist derzeit geschlossen"
6. Mit bestehendem Discord-Account anmelden
7. Erwartetes Ergebnis: Erfolgreiches Login

### Erfolgskriterien
- ✅ Neue Benutzer können sich nicht registrieren
- ✅ Bestehende Benutzer können sich weiterhin anmelden
- ✅ Klare Fehlermeldung für neue Benutzer

---

## Test-Szenario 4: Code-Verwaltung

### Test-Schritte
1. Als Administrator anmelden
2. Zur Seite `/benutzer/registration-codes.php` navigieren
3. Mehrere Codes generieren (z.B. 5 Stück)
4. Erwartetes Ergebnis: Alle Codes werden in der Tabelle angezeigt
5. Einen unbenutzten Code löschen
6. Erwartetes Ergebnis: Code wird erfolgreich gelöscht
7. Versuchen, einen verwendeten Code zu löschen
8. Erwartetes Ergebnis: Fehlermeldung oder keine Löschung

### Erfolgskriterien
- ✅ Codes werden korrekt aufgelistet
- ✅ Unbenutzte Codes können gelöscht werden
- ✅ Verwendete Codes können nicht gelöscht werden
- ✅ Anzeige von Creator, Verwendung-Status und Datum

---

## Edge Cases

### Test: Erster Benutzer
1. Leere Datenbank (keine Benutzer)
2. Beliebigen Registrierungsmodus setzen
3. Mit Discord anmelden
4. Erwartetes Ergebnis: Erster Benutzer wird als Admin erstellt (unabhängig vom Modus)

### Test: Modusänderung während Registrierung
1. Modus auf 'code' setzen
2. Code generieren und Registrierungsprozess starten
3. Nach Code-Eingabe, aber vor Abschluss Modus auf 'closed' ändern
4. Erwartetes Ergebnis: Registrierung sollte mit dem Code abgeschlossen werden

### Test: Session-Persistenz
1. Modus auf 'code' setzen
2. Registrierung starten (aber nicht Code eingeben)
3. Browser schließen und neu öffnen
4. Erwartetes Ergebnis: Session sollte zurückgesetzt sein

---

## Rollback-Plan

Falls Probleme auftreten:
1. `REGISTRATION_MODE` auf `'open'` zurücksetzen
2. Bestehende Benutzer können weiterhin normal einloggen
3. Bei Bedarf Tabelle `intra_registration_codes` leeren:
   ```sql
   TRUNCATE TABLE intra_registration_codes;
   ```
