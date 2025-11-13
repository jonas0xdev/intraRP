# System Update Feature - Documentation

## Übersicht

Das intraRP Update-System ermöglicht es Administratoren, einfach auf neue Versionen zu prüfen und diese herunterzuladen. Das System ist sicher, benutzerfreundlich und integriert sich nahtlos in die bestehende Administrationsoberfläche.

## Funktionen

- **Automatische Update-Prüfung**: Prüft auf neue Versionen direkt von GitHub Releases
- **Version-Tracking**: Verfolgt die aktuelle Version und Build-Informationen
- **Release-Notizen**: Zeigt detaillierte Informationen über neue Versionen an
- **Sichere Downloads**: Bietet direkte Download-Links zu offiziellen GitHub Releases
- **Audit-Logging**: Protokolliert alle Update-Prüfungen für die Nachverfolgbarkeit
- **Benutzerfreundliche UI**: Moderne, intuitive Benutzeroberfläche in den Einstellungen
- **Update-Dringlichkeit**: Intelligente Bewertung der Update-Priorität
- **Versions-Alter-Tracking**: Zeigt das Alter der installierten Version an
- **Pre-Release-Erkennung**: Erkennt und markiert Entwicklerversionen
- **Caching**: Vermeidet API-Rate-Limits durch intelligentes Caching
- **Optimierte Release-Notizen**: Verbesserte Darstellung von Markdown-Inhalten

## Verwendung

### Zugriff auf das Update-System

1. Melden Sie sich als Administrator an
2. Navigieren Sie zu `/settings/system/` in Ihrem Browser
3. Die Update-Seite zeigt die aktuelle Version an

### Auf Updates prüfen

1. Klicken Sie auf den Button "Auf Updates prüfen"
2. Das System kontaktiert GitHub und prüft auf neue Releases
3. Wenn ein Update verfügbar ist, werden folgende Informationen angezeigt:
   - Neue Versionsnummer
   - Release-Name
   - Veröffentlichungsdatum
   - Release-Notizen
   - Download-Link

### Update-Installation

**Wichtig**: Updates werden **nicht automatisch** installiert. Dies ist eine Sicherheitsmaßnahme.

Der empfohlene Update-Prozess:

1. **Backup erstellen**: Sichern Sie alle Daten und die Datenbank
2. **Release-Notizen lesen**: Überprüfen Sie, ob Breaking Changes vorhanden sind
3. **Update herunterladen**: Laden Sie das Release von GitHub herunter
4. **Dateien extrahieren**: Entpacken Sie die heruntergeladenen Dateien
5. **Dateien ersetzen**: Ersetzen Sie die alten Dateien mit den neuen
6. **Migrations ausführen**: Falls vorhanden, führen Sie `composer db:migrate` aus
7. **Testen**: Überprüfen Sie, ob alles funktioniert

## Technische Details

### Komponenten

#### SystemUpdater-Klasse (`src/Utils/SystemUpdater.php`)

Die Hauptklasse für Update-Operationen:

```php
use App\Utils\SystemUpdater;

$updater = new SystemUpdater();

// Aktuelle Version abrufen
$version = $updater->getCurrentVersion();

// Auf Updates prüfen
$updateInfo = $updater->checkForUpdates();

// Alle Releases abrufen
$releases = $updater->getAllReleases(10);
```

**Methoden**:
- `getCurrentVersion()`: Gibt die aktuelle Version zurück
- `checkForUpdates()`: Prüft auf verfügbare Updates
- `checkForUpdatesCached()`: Prüft auf Updates mit Caching (empfohlen)
- `getAllReleases($limit)`: Gibt eine Liste aller Releases zurück
- `updateVersionFile($versionData)`: Aktualisiert die version.json
- `getVersionAge()`: Gibt das Alter der Version in Tagen zurück
- `isPreRelease()`: Prüft, ob es sich um eine Pre-Release-Version handelt
- `isUpdateRecommended()`: Prüft, ob ein Update empfohlen wird
- `getUpdateUrgency()`: Gibt die Dringlichkeit eines Updates zurück ('none', 'low', 'medium', 'high', 'critical')
- `getFormattedReleaseNotes($markdown)`: Konvertiert Markdown in formatiertes HTML

#### Version-Tracking (`system/updates/version.json`)

Speichert Informationen über die aktuelle Version:

```json
{
  "version": "v0.5.0",
  "updated_at": "2025-11-04 00:00:00",
  "build_number": "0",
  "commit_hash": "initial"
}
```

Diese Datei wird automatisch vom GitHub Workflow aktualisiert, wenn ein neuer Tag erstellt wird.

#### Update-Seite (`settings/system/index.php`)

Die Benutzeroberfläche für Administratoren mit folgenden Sicherheitsfeatures:
- Session-Validierung
- Admin-Permissions-Check
- CSRF-Schutz durch POST-Requests
- XSS-Schutz durch `htmlspecialchars()`

### GitHub Integration

Das System nutzt die GitHub API, um auf Release-Informationen zuzugreifen:

- **API Endpoint**: `https://api.github.com/repos/EmergencyForge/intraRP`
- **Rate Limit**: 60 Anfragen pro Stunde (unauthentifiziert)
- **API Version**: GitHub REST API (latest)

### Automatisierung mit GitHub Actions

Die Datei `.github/workflows/update-version.yml` automatisiert die Version-Aktualisierung:

1. Wird bei jedem neuen Git-Tag ausgelöst
2. Extrahiert die Versionsnummer aus dem Tag
3. Erstellt/aktualisiert `system/updates/version.json`
4. Committet die Änderungen zurück zum Repository

## Geschützte Dateien und Verzeichnisse

Das Update-System schützt automatisch bestimmte Dateien und Verzeichnisse vor Überschreibung:

### Vollständig ausgeschlossene Verzeichnisse
Diese Verzeichnisse werden beim Update komplett übersprungen:
- **`vendor/`**: Composer-Abhängigkeiten (werden separat über `composer install` aktualisiert)
- **`storage/`**: Benutzerdaten, Uploads und Logs
- **`system/updates/`**: Update-Backups und Versionsinformationen

### Geschützte Dateien
- **`.env`**: Umgebungskonfiguration mit sensiblen Daten
- **`.git`**: Git-Repository-Informationen
- **`.gitignore`**: Git-Ignore-Konfiguration

### Intelligenter Bildschutz (`assets/img/`)

Das Update-System verwendet einen intelligenten Ansatz für Bilder:
- **Existierende Bilder bleiben erhalten**: Angepasste Logos, Wappen, Wallpapers und Dienstgrad-Abzeichen werden nicht überschrieben
- **Neue Bilder werden hinzugefügt**: Wenn ein Update neue Bilddateien enthält (z.B. neue Dienstgrad-Abzeichen), werden diese automatisch hinzugefügt
- **Keine Datenverluste**: Ihre Anpassungen bleiben sicher, während Sie von neuen Features profitieren

#### Beispiele für geschützte Bilder:
- `assets/img/defaultLogo.png/webp` - System-Logo
- `assets/img/wappen_small.png` - Wappen
- `assets/img/wallpaper.png/webp` - Hintergrundbild
- `assets/img/schriftzug_*.png` - Organisationsschriftzüge
- `assets/img/dienstgrade/` - Dienstgrad-Abzeichen

**Tipp**: Das System-Logo kann über die System-Konfiguration (`/settings/system/config.php`) angepasst werden, ohne Dateien direkt zu ersetzen.

## Sicherheit

### Sicherheitsmaßnahmen

1. **Keine automatische Installation**: Updates werden niemals automatisch installiert
2. **Admin-Only**: Nur Administratoren können auf das Update-System zugreifen
3. **Sichere Temporäre Pfade**: Verwendet kryptographisch sichere Zufallswerte
4. **Validierte API-Requests**: Alle externen Anfragen verwenden sichere Kontexte
5. **Audit-Logging**: Alle Update-Checks werden protokolliert
6. **XSS-Schutz**: Alle Benutzereingaben werden escaped
7. **CSRF-Schutz**: POST-Requests erforderlich für Aktionen
8. **Schutz angepasster Inhalte**: Benutzerdefinierte Bilder und Konfigurationen bleiben erhalten

### Best Practices

- Erstellen Sie immer ein Backup vor Updates
- Testen Sie Updates zuerst in einer Entwicklungsumgebung
- Lesen Sie die Release-Notizen sorgfältig
- Überprüfen Sie die Kompatibilität mit Ihren Anpassungen
- Führen Sie Updates außerhalb der Hauptnutzungszeiten durch

## Fehlerbehebung

### Problem: "Keine Updates verfügbar" obwohl neue Version existiert

**Ursache**: Möglicherweise wurde noch kein GitHub Release erstellt

**Lösung**: 
- Überprüfen Sie https://github.com/EmergencyForge/intraRP/releases
- Stellen Sie sicher, dass die Version als Release markiert ist

### Problem: "Fehler beim Prüfen auf Updates"

**Ursache**: Netzwerkprobleme oder GitHub API nicht erreichbar

**Lösung**:
- Überprüfen Sie Ihre Internetverbindung
- Prüfen Sie, ob GitHub erreichbar ist
- Warten Sie ein paar Minuten und versuchen Sie es erneut

### Problem: API Rate Limit erreicht

**Ursache**: Zu viele Anfragen an die GitHub API

**Lösung**:
- Warten Sie eine Stunde
- Authentifizieren Sie API-Requests für höheres Limit (60 → 5000/Stunde)

## Zukünftige Erweiterungen

Mögliche zukünftige Verbesserungen:

- Automatische Update-Benachrichtigungen im Dashboard
- Geplante automatische Update-Checks
- Rollback-Funktionalität
- Changelog-Viewer
- Update-Historie
- Beta/Preview-Channel für frühen Zugriff

## Support

Bei Problemen oder Fragen:

1. Öffnen Sie ein Issue auf GitHub
2. Überprüfen Sie die Dokumentation unter https://docs.intrarp.de
3. Kontaktieren Sie das Support-Team

---

**Version**: 1.0.0  
**Letzte Aktualisierung**: 04.11.2025  
**Autor**: intraRP Team
