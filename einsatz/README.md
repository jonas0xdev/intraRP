# Einsatz-System Struktur

## Übersicht

Das Einsatz-System wurde modular aufgebaut, um die Wartbarkeit und Übersichtlichkeit zu verbessern.

## Dateistruktur

```
einsatz/
├── create.php          # Formular zum Anlegen neuer Einsätze
├── view.php            # Hauptseite zur Anzeige eines Einsatzes (lädt Tab-Dateien)
├── actions.php         # Zentrale Verarbeitung aller POST-Aktionen
│
├── tabs/               # Separate Dateien für jeden Tab
│   ├── stammdaten.php      # Seite 1: Stammdaten (Ort, Stichwort, etc.)
│   ├── bericht.php         # Seite 2: Einsatzbericht (ASU-Protokolle)
│   ├── fahrzeuge.php       # Seite 3: Beteiligte Fahrzeuge
│   ├── lagemeldungen.php   # Seite 4: Lagemeldungen
│   └── abschluss.php       # Seite 5: Einsatz abschließen
│
└── admin/
    ├── list.php        # Übersicht aller Einsätze
    └── view.php        # Admin-Ansicht
```

## Komponenten

### view.php (Hauptdatei)

- Lädt Einsatzdaten aus der Datenbank
- Stellt Variablen für alle Tab-Dateien bereit
- Definiert Hilfsfunktionen (`fmt_dt()`, `fmt_elapsed()`)
- Bindet alle Tab-Dateien per `include` ein
- Enthält die Sidebar-Navigation und Modals

### actions.php (Action-Handler)

- Verarbeitet alle POST-Requests zentral
- Aktionen:
  - `add_vehicle` - Fahrzeug hinzufügen
  - `remove_vehicle` - Fahrzeug entfernen
  - `add_sitrep` - Lagemeldung hinzufügen
  - `update_core` - Stammdaten aktualisieren
  - `finalize` - Einsatz abschließen
  - `set_status` - QM-Status ändern
- Leitet nach Verarbeitung zurück zu view.php

### Tab-Dateien

Jede Tab-Datei enthält:

- Nur den HTML-Inhalt für den jeweiligen Tab
- Formulare senden an `actions.php`
- Nutzen Variablen aus view.php ($incident, $id, etc.)
- Sind eigenständig wartbar

## Variablen

Folgende Variablen stehen in allen Tab-Dateien zur Verfügung:

```php
$id                 // int - Einsatz-ID
$incident           // array - Alle Einsatzdaten
$attachedVehicles   // array - Zugeordnete Fahrzeuge
$allVehicles        // array - Alle verfügbaren Fahrzeuge
$sitreps            // array - Alle Lagemeldungen
$asuProtocols       // array - Alle ASU-Protokolle
$pdo                // PDO - Datenbankverbindung
fmt_dt($timestamp)  // function - Formatiert Zeitstempel
fmt_elapsed($secs)  // function - Formatiert Sekunden als MM:SS
```

## Berechtigungen

- **admin** - Voller Zugriff
- **fire.incident.create** - Einsätze erstellen und bearbeiten
- **fire.incident.qm** - QM-Funktionen (Status ändern, abschließen)

## Navigation

Sidebar-Navigation mit folgenden Menüpunkten:

1. Einsatz anlegen (create.php)
2. Einsatzprotokoll (view.php) - aktuell aktiv
3. Einsatzliste (admin/list.php)
4. Zurück zur Startseite

## Workflow

1. **Einsatz erstellen** (create.php)

   - Pflichtfelder ausfüllen
   - Weiterleitung zu view.php

2. **Einsatz bearbeiten** (view.php)

   - Tab 1: Stammdaten pflegen
   - Tab 2: ASU-Protokolle einsehen
   - Tab 3: Fahrzeuge hinzufügen/entfernen
   - Tab 4: Lagemeldungen erfassen
   - Tab 5: Einsatz abschließen (wenn alle Pflichtfelder ausgefüllt)

3. **Nach Abschluss**
   - Daten sind gesperrt (nur noch lesbar)
   - QM kann Status ändern (gesichtet/negativ)

## Hinweise für Entwickler

- Alle Formulare senden per POST an `actions.php`
- `actions.php` leitet immer zurück zu `view.php?id=X`
- Flash-Messages werden in der Session gespeichert
- Tab-Dateien haben keine eigene Logik (nur Darstellung)
- Änderungen an einem Tab betreffen nicht die anderen
