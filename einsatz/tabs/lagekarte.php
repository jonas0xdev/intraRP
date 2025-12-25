<?php
// Ensure required variables are available from parent context
if (!isset($incident, $pdo, $id)) {
    die('Error: Required context not available');
}

// Load existing markers for this incident
$markers = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            mit.fullname AS created_by_name,
            v.name AS vehicle_name
        FROM intra_fire_incident_map_markers m
        LEFT JOIN intra_mitarbeiter mit ON m.created_by = mit.id
        LEFT JOIN intra_fahrzeuge v ON m.vehicle_id = v.id
        WHERE m.incident_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$id]);
    $markers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $markers = [];
}

// Load assigned vehicles with tactical symbols configured
$assignedVehicles = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.name,
            v.grundzeichen,
            v.organisation,
            v.fachaufgabe,
            v.einheit,
            v.symbol,
            v.typ,
            v.text,
            v.tz_name
        FROM intra_fire_incident_vehicles iv
        JOIN intra_fahrzeuge v ON iv.vehicle_id = v.id
        WHERE iv.incident_id = ? 
        AND v.grundzeichen IS NOT NULL 
        AND v.grundzeichen != ''
        ORDER BY v.name ASC
    ");
    $stmt->execute([$id]);
    $assignedVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table columns might not exist yet
    $assignedVehicles = [];
}
?>

<style>
    .map-wrapper {
        position: relative;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        background: #1a1a1a;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        border: 2px solid #333;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }

    .map-container {
        position: relative;
        width: 100%;
        height: 800px;
        overflow: hidden;
        cursor: grab;
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        background: #0fa8d2;
    }

    .map-container.panning {
        cursor: grabbing;
    }

    .map-viewport {
        position: relative;
        width: 100%;
        height: 100%;
        transform-origin: 0 0;
        transition: transform 0.1s ease-out;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }

    .map-viewport.no-transition {
        transition: none;
    }

    .map-image {
        width: 100%;
        height: auto;
        display: block;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        pointer-events: none;
        -webkit-user-drag: none;
        -khtml-user-drag: none;
        -moz-user-drag: none;
        -o-user-drag: none;
    }

    .map-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 5px;
        background: rgba(0, 0, 0, 0.7);
        padding: 10px;
        border-radius: 8px;
    }

    .map-controls button {
        width: 40px;
        height: 40px;
        border: none;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .map-controls button:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .map-controls .zoom-level {
        text-align: center;
        color: white;
        font-size: 12px;
        padding: 5px 0;
    }

    .map-marker {
        position: absolute;
        cursor: pointer;
        z-index: 10;
        transition: transform 0.2s;
        pointer-events: auto;
    }

    .map-marker:hover {
        transform: scale(1.2);
        z-index: 20;
    }

    .map-marker-icon {
        font-size: 10px;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.4));
    }

    .map-marker-icon svg {
        width: 12px;
        height: 12px;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5));
    }

    .map-marker-label {
        position: absolute;
        top: -10px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.85);
        color: white;
        padding: 1px 4px;
        border-radius: 2px;
        font-size: 6px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s;
        z-index: 9999;
    }

    .map-marker:hover .map-marker-label {
        opacity: 1;
    }

    .marker-legend {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .legend-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .legend-item.active {
        background: rgba(13, 110, 253, 0.3);
        border: 1px solid rgba(13, 110, 253, 0.5);
    }

    .legend-icon {
        font-size: 24px;
        background: white;
        padding: 4px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        min-height: 32px;
    }

    .marker-info-box {
        position: absolute;
        background: rgba(0, 0, 0, 0.95);
        color: white;
        padding: 15px;
        border-radius: 8px;
        max-width: 300px;
        z-index: 100;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        pointer-events: none;
    }

    .marker-info-box.show {
        pointer-events: auto;
    }

    #selectedMarkerIcon {
        font-size: 64px;
        background: white;
        padding: 8px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 96px;
        min-height: 96px;
    }

    #selectedMarkerIcon svg {
        width: 64px !important;
        height: 64px !important;
        display: block;
    }
</style>

<div class="card bg-dark">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fa-solid fa-map-marked-alt me-2"></i>Lagekarte
        </h5>
        <div>
            <button type="button" class="btn btn-sm btn-outline-light" id="toggleMarkerMode">
                <i class="fa-solid fa-plus me-1"></i>Marker hinzufügen
            </button>
            <button type="button" class="btn btn-sm btn-outline-light" id="refreshMap">
                <i class="fa-solid fa-sync-alt me-1"></i>Aktualisieren
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if ($incident['finalized']): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-lock me-2"></i>
                Dieser Einsatz ist abgeschlossen. Die Lagekarte kann nicht mehr bearbeitet werden.
            </div>
        <?php endif; ?>

        <!-- Marker Type Legend -->
        <div class="mb-3">
            <h6>Taktische Zeichen auswählen:</h6>
        </div>

        <?php if (!empty($assignedVehicles)): ?>
            <!-- Assigned Vehicle Markers Grid -->
            <div class="mb-2 text-muted small"><strong>Zugewiesene Fahrzeuge:</strong></div>
            <div class="marker-legend mb-3" id="vehicleMarkerLegend">
                <?php foreach ($assignedVehicles as $vehicle): ?>
                    <div class="legend-item"
                        data-type="vehicle_<?= htmlspecialchars($vehicle['id']) ?>"
                        data-vehicle-id="<?= htmlspecialchars($vehicle['id']) ?>"
                        data-tz-grundzeichen="<?= htmlspecialchars($vehicle['grundzeichen']) ?>"
                        <?php if (!empty($vehicle['organisation'])): ?>data-tz-organisation="<?= htmlspecialchars($vehicle['organisation']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['fachaufgabe'])): ?>data-tz-fachaufgabe="<?= htmlspecialchars($vehicle['fachaufgabe']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['einheit'])): ?>data-tz-einheit="<?= htmlspecialchars($vehicle['einheit']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['symbol'])): ?>data-tz-symbol="<?= htmlspecialchars($vehicle['symbol']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['typ'])): ?>data-tz-typ="<?= htmlspecialchars($vehicle['typ']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['text'])): ?>data-tz-text="<?= htmlspecialchars($vehicle['text']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['tz_name'])): ?>data-tz-name="<?= htmlspecialchars($vehicle['tz_name']) ?>" <?php endif; ?>>
                        <span class="legend-icon" data-tz-icon></span>
                        <span><?= htmlspecialchars($vehicle['name']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Standard Markers Grid -->
        <div class="mb-2 text-muted small"><strong>Standard-Marker:</strong></div>
        <div class="marker-legend" id="markerLegend">

            <div class="legend-item" data-type="Einsatzleiter"
                data-tz-grundzeichen="person"
                data-tz-fachaufgabe="fuehrung"
                data-tz-organisation="fuehrung"
                data-tz-text="EL">
                <span class="legend-icon" data-tz-icon></span>
                <span>Einsatzleiter</span>
            </div>
            <div class="legend-item" data-type="Bereitstellungsraum"
                data-tz-grundzeichen="stelle"
                data-tz-organisation="fuehrung"
                data-tz-symbol="fahrzeug">
                <span class="legend-icon" data-tz-icon></span>
                <span>Bereitstellungsraum</span>
            </div>
            <div class="legend-item" data-type="Verletzte Person"
                data-tz-grundzeichen="ohne"
                data-tz-symbol="person-verletzt">
                <span class="legend-icon" data-tz-icon></span>
                <span>Verletzte Person</span>
            </div>
            <div class="legend-item" data-type="Vermisste Person"
                data-tz-grundzeichen="ohne"
                data-tz-symbol="person-vermisst">
                <span class="legend-icon" data-tz-icon></span>
                <span>Vermisste Person</span>
            </div>
            <div class="legend-item" data-type="custom" data-color="#6c757d">
                <span class="legend-icon"></span>
                <span>Eigener Marker</span>
            </div>
        </div>

        <!-- Map Container -->
        <div class="map-wrapper">
            <div class="map-controls">
                <button id="zoomIn" title="Hineinzoomen"><i class="fa-solid fa-plus"></i></button>
                <div class="zoom-level" id="zoomLevel">100%</div>
                <button id="zoomOut" title="Herauszoomen"><i class="fa-solid fa-minus"></i></button>
                <button id="zoomReset" title="Zoom zurücksetzen"><i class="fa-solid fa-home"></i></button>
            </div>
            <div class="map-container" id="mapContainer">
                <div class="map-viewport" id="mapViewport">
                    <img src="<?= BASE_PATH ?>assets/img/map/GTAV_ATLUS_8192x8192.png"
                        alt="GTA Map"
                        class="map-image"
                        id="mapImage"
                        draggable="false">
                    <!-- Markers will be dynamically added here -->
                </div>
            </div>
        </div>

        <!-- Marker List -->
        <div class="mt-4">
            <h6 class="mb-3">Platzierte Marker</h6>
            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Typ</th>
                            <th>Beschreibung</th>
                            <th>Erstellt von</th>
                            <th>Fahrzeug</th>
                            <th>Zeitstempel</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="markerTableBody">
                        <?php if (empty($markers)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Noch keine Marker platziert
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($markers as $marker): ?>
                                <tr data-marker-id="<?= $marker['id'] ?>">
                                    <td>
                                        <?= htmlspecialchars($marker['marker_type']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($marker['description'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($marker['created_by_name'] ?? 'Unbekannt') ?></td>
                                    <td><?= htmlspecialchars($marker['vehicle_name'] ?? '-') ?></td>
                                    <td><?= fmt_dt($marker['created_at']) ?></td>
                                    <td>
                                        <?php if (!$incident['finalized']): ?>
                                            <button class="btn btn-sm btn-outline-danger delete-marker-btn"
                                                data-marker-id="<?= $marker['id'] ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Marker Creation Modal -->
<div class="modal fade" id="markerModal" tabindex="-1" aria-labelledby="markerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="markerModalLabel">Marker hinzufügen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="markerForm">
                    <input type="hidden" id="markerPosX" name="pos_x">
                    <input type="hidden" id="markerPosY" name="pos_y">
                    <input type="hidden" id="markerType" name="marker_type">

                    <div class="mb-3">
                        <label class="form-label">Marker-Typ</label>
                        <div class="text-center mb-2">
                            <span id="selectedMarkerIcon">📌</span>
                        </div>
                        <p class="text-center text-muted small" id="selectedMarkerText">
                            Bitte wählen Sie einen Marker-Typ aus der Legende
                        </p>
                    </div>

                    <div class="mb-3">
                        <label for="markerDescription" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="markerDescription" name="description" rows="3"
                            placeholder="Optionale Beschreibung..."></textarea>
                    </div>

                    <!-- Text field for tactical symbols -->
                    <div class="mb-3" id="textFieldContainer" style="display: none;">
                        <label for="markerText" class="form-label">Text-Beschriftung</label>
                        <input type="text" class="form-control" id="markerText" name="text"
                            placeholder="z.B. LF20, RTW 1/82-1">
                        <small class="text-muted">Wird auf dem taktischen Zeichen angezeigt</small>
                    </div>

                    <!-- Name field for tactical symbols -->
                    <div class="mb-3" id="nameFieldContainer" style="display: none;">
                        <label for="markerName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="markerName" name="name"
                            placeholder="z.B. Einsatzabschnitt Nord">
                        <small class="text-muted">Name des taktischen Zeichens</small>
                    </div>

                    <!-- Typ field for tactical symbols -->
                    <div class="mb-3" id="typFieldContainer" style="display: none;">
                        <label for="markerTyp" class="form-label">Typ</label>
                        <input type="text" class="form-control" id="markerTyp" name="typ"
                            placeholder="z.B. HLF20, RTW, DLK23/12">
                        <small class="text-muted">Fahrzeugtyp oder Typ des taktischen Zeichens</small>
                    </div>

                    <!-- Custom Tactical Symbol Fields -->
                    <div id="customTacticalFields" style="display: none;">
                        <hr>
                        <h6 class="mb-3">Benutzerdefiniertes taktisches Zeichen</h6>

                        <div class="mb-3">
                            <label for="customGrundzeichen" class="form-label">Grundzeichen <span class="text-danger">*</span></label>
                            <select class="form-select" id="customGrundzeichen">
                                <option value="">-- Bitte wählen --</option>
                                <option value="abrollbehaelter">Abrollbehälter</option>
                                <option value="amphibienfahrzeug">Amphibienfahrzeug</option>
                                <option value="anhaenger">Anhänger allgemein</option>
                                <option value="anhaenger-lkw">Anhänger von Lkw gezogen</option>
                                <option value="anhaenger-pkw">Anhänger von Pkw gezogen</option>
                                <option value="anlass">Anlass, Ereignis</option>
                                <option value="befehlsstelle">Befehlsstelle</option>
                                <option value="fahrzeug">Fahrzeug</option>
                                <option value="flugzeug">Flugzeug</option>
                                <option value="gebaeude">Gebäude</option>
                                <option value="gefahr">Gefahr</option>
                                <option value="gefahr-akut">Gefahr (akut)</option>
                                <option value="gefahr-vermutet">Gefahr (vermutet)</option>
                                <option value="hubschrauber">Hubschrauber</option>
                                <option value="ohne">Kein Grundzeichen</option>
                                <option value="kettenfahrzeug">Kettenfahrzeug</option>
                                <option value="kraftfahrzeug-gelaendegaengig">Kraftfahrzeug geländegängig</option>
                                <option value="kraftfahrzeug-landgebunden">Kraftfahrzeug landgebunden</option>
                                <option value="massnahme">Maßnahme</option>
                                <option value="person">Person</option>
                                <option value="rollcontainer">Rollcontainer</option>
                                <option value="schienenfahrzeug">Schienenfahrzeug</option>
                                <option value="stelle">Stelle, Einrichtung</option>
                                <option value="ortsfeste-stelle">Stelle, Einrichtung (ortsfest)</option>
                                <option value="taktische-formation">Taktische Formation</option>
                                <option value="wasserfahrzeug">Wasserfahrzeug</option>
                                <option value="wechselbehaelter">Wechselbehälter/Container</option>
                                <option value="zweirad">Zweirad, Kraftrad</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customOrganisation" class="form-label">Organisation</label>
                            <select class="form-select" id="customOrganisation">
                                <option value="">-- Keine --</option>
                                <option value="bundeswehr">Bundeswehr</option>
                                <option value="feuerwehr">Feuerwehr</option>
                                <option value="fuehrung">Führung</option>
                                <option value="gefahrenabwehr">Gefahrenabwehr</option>
                                <option value="hilfsorganisation">Hilfsorganisationen</option>
                                <option value="polizei">Polizei</option>
                                <option value="thw">THW</option>
                                <option value="zivil">Zivile Einheiten</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customFachaufgabe" class="form-label">Fachaufgabe</label>
                            <select class="form-select" id="customFachaufgabe">
                                <option value="">-- Keine --</option>
                                <option value="abwehr-wassergefahren">Abwehr von Wassergefahren</option>
                                <option value="aerztliche-versorgung">Ärztliche Versorgung</option>
                                <option value="beleuchtung">Beleuchtung</option>
                                <option value="bergung">Bergung</option>
                                <option value="umweltschaeden-gewaesser">Beseitigung von Umweltschäden auf Gewässern</option>
                                <option value="betreuung">Betreuung</option>
                                <option value="brandbekaempfung">Brandbekämpfung</option>
                                <option value="dekontamination">Dekontamination</option>
                                <option value="dekontamination-geraete">Dekontamination Geräte</option>
                                <option value="dekontamination-personen">Dekontamination Personen</option>
                                <option value="wasserfahrzeuge">Einsatz von Wasserfahrzeugen</option>
                                <option value="einsatzeinheit">Einsatzeinheit</option>
                                <option value="entschaerfen">Entschärfung, Kampfmittelräumung</option>
                                <option value="erkundung">Erkundung</option>
                                <option value="fuehrung">Führung, Leitung, Stab</option>
                                <option value="abc">Gefahrenabwehr bei Gefährlichen Stoffen (ABC)</option>
                                <option value="heben">Heben von Lasten</option>
                                <option value="iuk">Information und Kommunikation</option>
                                <option value="instandhaltung">Instandhaltung</option>
                                <option value="krankenhaus">Krankenhaus</option>
                                <option value="messen">Messen, Spüren</option>
                                <option value="pumpen">Pumpen, Lenzen</option>
                                <option value="raeumen">Räumen, Beseitigung von Hindernissen</option>
                                <option value="hoehenrettung">Rettung aus Höhen und Tiefen</option>
                                <option value="rettungswesen">Rettungswesen, Sanitätswesen</option>
                                <option value="schlachten">Schlachten</option>
                                <option value="seelsorge">Seelsorge</option>
                                <option value="sprengen">Sprengen</option>
                                <option value="rettungshunde">Suchen und Orten mit Rettungshunden</option>
                                <option value="technische-hilfeleistung">Technische Hilfeleistung</option>
                                <option value="transport">Transport</option>
                                <option value="unterbringung">Unterbringung</option>
                                <option value="verpflegung">Verpflegung</option>
                                <option value="versorgung-brauchwasser">Versorgung mit Brauchwasser</option>
                                <option value="versorgung-elektrizitaet">Versorgung mit Elektrizität</option>
                                <option value="versorgung-trinkwasser">Versorgung mit Trinkwasser</option>
                                <option value="verbrauchsgueter">Versorgung mit Verbrauchsgütern</option>
                                <option value="logistik">Versorgung, Logistik</option>
                                <option value="veterinaerwesen">Veterinärwesen</option>
                                <option value="warnen">Warnen</option>
                                <option value="wasserrettung">Wasserrettung</option>
                                <option value="wasserversorgung">Wasserversorgung und -förderung</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customEinheit" class="form-label">Einheit</label>
                            <select class="form-select" id="customEinheit">
                                <option value="">-- Keine --</option>
                                <option value="trupp">Trupp</option>
                                <option value="staffel">Staffel</option>
                                <option value="gruppe">Gruppe</option>
                                <option value="bereitschaft">Bereitschaft</option>
                                <option value="zug">Zug</option>
                                <option value="verband">Verband</option>
                                <option value="grossverband">Großverband</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customSymbol" class="form-label">Symbol</label>
                            <select class="form-select" id="customSymbol">
                                <option value="">-- Kein Symbol --</option>
                                <option value="abc">Gefährliche Stoffe (ABC)</option>
                                <option value="bagger">Bagger</option>
                                <option value="beleuchtung">Beleuchtung</option>
                                <option value="bergung">Bergung</option>
                                <option value="dekontamination">Dekontamination</option>
                                <option value="dekontamination-geraete">Dekontamination (Geräte)</option>
                                <option value="dekontamination-personen">Dekontamination (Personen)</option>
                                <option value="drehleiter">Drehleiter</option>
                                <option value="drohne">Drohne</option>
                                <option value="entstehungsbrand">Entstehungsbrand</option>
                                <option value="fortentwickelter-brand">Fortentwickelter Brand</option>
                                <option value="vollbrand">Vollbrand</option>
                                <option value="geraete">Geräte</option>
                                <option value="hebegeraet">Hebegerät</option>
                                <option value="hubschrauber">Hubschrauber</option>
                                <option value="person">Person</option>
                                <option value="person-gerettet">Person gerettet</option>
                                <option value="person-tot">Person tot</option>
                                <option value="person-verletzt">Person verletzt</option>
                                <option value="person-vermisst">Person vermisst</option>
                                <option value="person-verschuettet">Person verschüttet</option>
                                <option value="pumpe">Pumpe</option>
                                <option value="raeumgeraet">Räumgerät</option>
                                <option value="sammeln">Sammeln</option>
                                <option value="sammelplatz-betroffene">Sammelplatz für Betroffene</option>
                                <option value="technische-hilfeleistung">Technische Hilfeleistung</option>
                                <option value="transport">Transport</option>
                                <option value="wasser">Wasser</option>
                                <option value="zelt">Zelt</option>
                                <option value="zerstoert">zerstört</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customTyp" class="form-label">Typ</label>
                            <input type="text" class="form-control" id="customTyp"
                                placeholder="z.B. HLF20, RTW, DLK23/12">
                            <small class="form-text text-muted">Fahrzeugtyp oder Typ des taktischen Zeichens</small>
                        </div>

                        <div class="text-center mb-3">
                            <button type="button" class="btn btn-sm btn-outline-info" id="previewCustomSymbol">
                                <i class="fa-solid fa-eye me-1"></i>Vorschau
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="saveMarkerBtn">
                    <i class="fa-solid fa-save me-1"></i>Marker speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Load taktische-zeichen library from CDN -->
<script type="module">
    import {
        erzeugeTaktischesZeichen
    } from 'https://esm.sh/taktische-zeichen-core@0.10.0';

    // Make it globally available
    window.erzeugeTaktischesZeichen = erzeugeTaktischesZeichen;

    // Initialize tactical symbols immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initializeTacticalSymbols, 50);
        });
    } else {
        // DOM already loaded
        setTimeout(initializeTacticalSymbols, 50);
    }
</script>

<script>
    // Configuration
    const incidentId = <?= $id ?>;
    const isFinalized = <?= $incident['finalized'] ? 'true' : 'false' ?>;
    const existingMarkers = <?= json_encode($markers) ?>;

    // State
    let markerMode = false;
    let selectedMarkerType = null;
    let pendingMarkerPosition = null;

    // Pan & Zoom State
    let scale = 1;
    let translateX = 0;
    let translateY = 0;
    let isPanning = false;
    let startPanX = 0;
    let startPanY = 0;
    const MIN_SCALE = 0.5;
    const MAX_SCALE = 5;
    const ZOOM_STEP = 0.2;
    const MAP_STATE_KEY = `lagekarte_state_${incidentId}`;

    // Load saved map state
    function loadMapState() {
        try {
            const savedState = localStorage.getItem(MAP_STATE_KEY);
            if (savedState) {
                const state = JSON.parse(savedState);
                scale = state.scale || 1;
                translateX = state.translateX || 0;
                translateY = state.translateY || 0;
            }
        } catch (e) {
            console.error('Error loading map state:', e);
        }
    }

    // Save map state
    function saveMapState() {
        try {
            const state = {
                scale,
                translateX,
                translateY
            };
            localStorage.setItem(MAP_STATE_KEY, JSON.stringify(state));
        } catch (e) {
            console.error('Error saving map state:', e);
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadMapState(); // Load saved state first
        initializeMap();
        initializePanZoom();
        loadMarkers();

        // Update marker positions on window resize
        window.addEventListener('resize', updateMarkerPositions);
    });

    function initializeTacticalSymbols() {
        if (!window.erzeugeTaktischesZeichen) {
            console.warn('Tactical symbols library not loaded yet, retrying...');
            setTimeout(initializeTacticalSymbols, 100);
            return;
        }

        const legendItems = document.querySelectorAll('[data-tz-icon]');
        legendItems.forEach(iconContainer => {
            const item = iconContainer.closest('.legend-item');
            if (!item) return;

            const grundzeichen = item.dataset.tzGrundzeichen;
            const organisation = item.dataset.tzOrganisation;
            const fachaufgabe = item.dataset.tzFachaufgabe;
            const einheit = item.dataset.tzEinheit;
            const symbol = item.dataset.tzSymbol;
            const typ = item.dataset.tzTyp;
            const text = item.dataset.tzText;
            const name = item.dataset.tzName;

            if (!grundzeichen) return;

            try {
                const config = {
                    grundzeichen
                };
                if (organisation) config.organisation = organisation;
                if (fachaufgabe) config.fachaufgabe = fachaufgabe;
                if (einheit) config.einheit = einheit;
                if (symbol) config.symbol = symbol;
                if (typ) config.typ = typ;
                if (text) config.text = text;
                if (name) config.name = name;

                const tz = window.erzeugeTaktischesZeichen(config);
                if (iconContainer) {
                    iconContainer.innerHTML = tz.toString();

                    // Style the SVG
                    const svg = iconContainer.querySelector('svg');
                    if (svg) {
                        svg.style.width = '32px';
                        svg.style.height = '32px';
                    }
                }
            } catch (e) {
                console.error('Error creating tactical symbol:', e, item.dataset);
                if (iconContainer) {
                    iconContainer.textContent = '📌';
                }
            }
        });
    }

    function initializePanZoom() {
        const mapContainer = document.getElementById('mapContainer');
        const mapViewport = document.getElementById('mapViewport');
        const zoomInBtn = document.getElementById('zoomIn');
        const zoomOutBtn = document.getElementById('zoomOut');
        const zoomResetBtn = document.getElementById('zoomReset');
        const zoomLevelDisplay = document.getElementById('zoomLevel');

        // Apply saved state immediately
        updateTransform(false);

        // Zoom buttons
        zoomInBtn.addEventListener('click', () => {
            zoomAt(mapContainer.offsetWidth / 2, mapContainer.offsetHeight / 2, ZOOM_STEP);
        });

        zoomOutBtn.addEventListener('click', () => {
            zoomAt(mapContainer.offsetWidth / 2, mapContainer.offsetHeight / 2, -ZOOM_STEP);
        });

        zoomResetBtn.addEventListener('click', () => {
            scale = 1;
            translateX = 0;
            translateY = 0;
            updateTransform();
            saveMapState(); // Save reset state
        });

        // Mouse wheel zoom
        mapContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            const rect = mapContainer.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            const delta = e.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
            zoomAt(mouseX, mouseY, delta);
        }, {
            passive: false
        });

        // Pan with mouse drag
        mapContainer.addEventListener('mousedown', (e) => {
            if (markerMode) return; // Don't pan in marker mode

            e.preventDefault(); // Prevent text selection
            isPanning = true;
            startPanX = e.clientX - translateX;
            startPanY = e.clientY - translateY;
            mapContainer.classList.add('panning');
            mapViewport.classList.add('no-transition');
        });

        document.addEventListener('mousemove', (e) => {
            if (!isPanning) return;
            e.preventDefault(); // Prevent text selection during drag
            translateX = e.clientX - startPanX;
            translateY = e.clientY - startPanY;
            updateTransform(false);
        });

        document.addEventListener('mouseup', () => {
            if (isPanning) {
                isPanning = false;
                mapContainer.classList.remove('panning');
                mapViewport.classList.remove('no-transition');
            }
        });

        // Touch support for mobile
        let touchStartX = 0;
        let touchStartY = 0;
        let lastTouchDistance = 0;

        mapContainer.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1 && !markerMode) {
                isPanning = true;
                touchStartX = e.touches[0].clientX - translateX;
                touchStartY = e.touches[0].clientY - translateY;
                mapViewport.classList.add('no-transition');
            } else if (e.touches.length === 2) {
                isPanning = false;
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                lastTouchDistance = Math.sqrt(dx * dx + dy * dy);
            }
        });

        mapContainer.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (e.touches.length === 1 && isPanning) {
                translateX = e.touches[0].clientX - touchStartX;
                translateY = e.touches[0].clientY - touchStartY;
                updateTransform(false);
            } else if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const delta = (distance - lastTouchDistance) * 0.01;

                const rect = mapContainer.getBoundingClientRect();
                const centerX = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left;
                const centerY = (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top;

                zoomAt(centerX, centerY, delta);
                lastTouchDistance = distance;
            }
        }, {
            passive: false
        });

        mapContainer.addEventListener('touchend', () => {
            isPanning = false;
            mapViewport.classList.remove('no-transition');
        });

        function zoomAt(mouseX, mouseY, delta) {
            const oldScale = scale;
            scale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale + delta));

            if (scale !== oldScale) {
                // Adjust translation to zoom towards mouse position
                const scaleDiff = scale / oldScale;
                translateX = mouseX - (mouseX - translateX) * scaleDiff;
                translateY = mouseY - (mouseY - translateY) * scaleDiff;
                updateTransform();
            }
        }

        function updateTransform(withTransition = true) {
            if (!withTransition) {
                mapViewport.classList.add('no-transition');
            }
            mapViewport.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
            zoomLevelDisplay.textContent = `${Math.round(scale * 100)}%`;

            if (!withTransition) {
                // Force reflow
                mapViewport.offsetHeight;
                mapViewport.classList.remove('no-transition');
            }

            // Save state after transformation
            saveMapState();
        }
    }

    function initializeMap() {
        const mapContainer = document.getElementById('mapContainer');
        const toggleBtn = document.getElementById('toggleMarkerMode');
        const refreshBtn = document.getElementById('refreshMap');
        const legendItems = document.querySelectorAll('.legend-item');

        // Toggle marker mode
        if (!isFinalized) {
            toggleBtn.addEventListener('click', function() {
                markerMode = !markerMode;
                this.innerHTML = markerMode ?
                    '<i class="fa-solid fa-times me-1"></i>Abbrechen' :
                    '<i class="fa-solid fa-plus me-1"></i>Marker hinzufügen';
                this.classList.toggle('btn-outline-light');
                this.classList.toggle('btn-warning');

                // Deselect marker type when exiting marker mode
                if (!markerMode) {
                    selectedMarkerType = null;
                    legendItems.forEach(item => item.classList.remove('active'));
                }
            });

            // Legend item selection
            legendItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (!markerMode) {
                        // Enable marker mode when selecting type
                        toggleBtn.click();
                    }

                    legendItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');

                    // Store tactical symbol data
                    const iconElement = this.querySelector('.legend-icon');
                    const nameSpans = this.querySelectorAll('span');
                    const vehicleNameSpan = nameSpans.length > 1 ? nameSpans[1] : null;

                    selectedMarkerType = {
                        type: this.dataset.type,
                        icon: iconElement.innerHTML, // Store full SVG or emoji
                        color: this.dataset.color,
                        vehicleId: this.dataset.vehicleId,
                        vehicleName: vehicleNameSpan ? vehicleNameSpan.textContent.trim() : null,
                        grundzeichen: this.dataset.tzGrundzeichen,
                        organisation: this.dataset.tzOrganisation,
                        fachaufgabe: this.dataset.tzFachaufgabe,
                        einheit: this.dataset.tzEinheit,
                        symbol: this.dataset.tzSymbol,
                        typ: this.dataset.tzTyp,
                        text: this.dataset.tzText,
                        name: this.dataset.tzName
                    };
                });
            });

            // Click on map to place marker (adjusted for pan/zoom)
            mapContainer.addEventListener('click', function(e) {
                if (!markerMode || !selectedMarkerType || isPanning) {
                    return;
                }

                const rect = mapContainer.getBoundingClientRect();
                const viewport = document.getElementById('mapViewport');
                const img = document.getElementById('mapImage');

                // Get click position relative to container
                const clickX = e.clientX - rect.left;
                const clickY = e.clientY - rect.top;

                // Transform click coordinates back to original image space
                // First remove translation, then remove scale
                const imageX = (clickX - translateX) / scale;
                const imageY = (clickY - translateY) / scale;

                // Convert to percentage of image dimensions
                const x = (imageX / img.offsetWidth * 100).toFixed(2);
                const y = (imageY / img.offsetHeight * 100).toFixed(2);

                // Validate coordinates are within bounds
                if (x < 0 || x > 100 || y < 0 || y > 100) {
                    return;
                }

                pendingMarkerPosition = {
                    x,
                    y
                };
                showMarkerModal();
            });
        } else {
            toggleBtn.disabled = true;
            toggleBtn.title = 'Einsatz ist abgeschlossen';
        }

        // Refresh button
        refreshBtn.addEventListener('click', function() {
            location.reload();
        });
    }

    function showMarkerModal() {
        const modal = new bootstrap.Modal(document.getElementById('markerModal'));
        const customFields = document.getElementById('customTacticalFields');
        const textFieldContainer = document.getElementById('textFieldContainer');
        const nameFieldContainer = document.getElementById('nameFieldContainer');
        const typFieldContainer = document.getElementById('typFieldContainer');

        // Update modal with selected marker type
        const iconContainer = document.getElementById('selectedMarkerIcon');
        iconContainer.innerHTML = selectedMarkerType.icon; // Use innerHTML to support SVG

        document.getElementById('selectedMarkerText').textContent = `Marker-Typ: ${selectedMarkerType.type}`;
        document.getElementById('markerType').value = selectedMarkerType.type;
        document.getElementById('markerPosX').value = pendingMarkerPosition.x;
        document.getElementById('markerPosY').value = pendingMarkerPosition.y;

        // Show/hide text, name, and typ fields if marker has grundzeichen
        if (selectedMarkerType.grundzeichen) {
            textFieldContainer.style.display = 'block';
            nameFieldContainer.style.display = 'block';
            typFieldContainer.style.display = 'block';
            // Pre-fill with values from selected marker (e.g. from vehicle)
            document.getElementById('markerText').value = selectedMarkerType.text || '';
            document.getElementById('markerName').value = selectedMarkerType.name || '';
            document.getElementById('markerTyp').value = selectedMarkerType.typ || '';
        } else {
            textFieldContainer.style.display = 'none';
            nameFieldContainer.style.display = 'none';
            typFieldContainer.style.display = 'none';
        }

        // Show/hide custom tactical symbol fields
        if (selectedMarkerType.type === 'custom') {
            customFields.style.display = 'block';
            // Clear previous values
            document.getElementById('customGrundzeichen').value = '';
            document.getElementById('customOrganisation').value = '';
            document.getElementById('customFachaufgabe').value = '';
            document.getElementById('customEinheit').value = '';
            document.getElementById('customSymbol').value = '';
            document.getElementById('customTyp').value = '';
        } else {
            customFields.style.display = 'none';
        }

        modal.show();
    }

    // Preview custom tactical symbol
    document.getElementById('previewCustomSymbol').addEventListener('click', function() {
        const grundzeichen = document.getElementById('customGrundzeichen').value.trim();
        const organisation = document.getElementById('customOrganisation').value.trim();
        const fachaufgabe = document.getElementById('customFachaufgabe').value.trim();
        const einheit = document.getElementById('customEinheit').value.trim();
        const symbol = document.getElementById('customSymbol').value.trim();
        const typ = document.getElementById('customTyp').value.trim();
        const text = document.getElementById('markerText').value.trim();
        const name = document.getElementById('markerName').value.trim();

        if (!grundzeichen) {
            showAlert('Bitte geben Sie mindestens ein Grundzeichen ein!', 'warning');
            return;
        }

        if (!window.erzeugeTaktischesZeichen) {
            showAlert('Fehler: Taktische Zeichen Bibliothek noch nicht geladen. Bitte einen Moment warten und erneut versuchen.', 'warning');
            return;
        }

        try {
            const config = {
                grundzeichen
            };
            if (organisation) config.organisation = organisation;
            if (fachaufgabe) config.fachaufgabe = fachaufgabe;
            if (einheit) config.einheit = einheit;
            if (symbol) config.symbol = symbol;
            if (typ) config.typ = typ;
            if (text) config.text = text;
            if (name) config.name = name;

            const tz = window.erzeugeTaktischesZeichen(config);
            const iconContainer = document.getElementById('selectedMarkerIcon');
            if (!iconContainer) {
                showAlert('Fehler: Vorschau-Container nicht gefunden. Bitte Modal neu öffnen.', 'danger');
                return;
            }
            iconContainer.innerHTML = tz.toString();

            // Ensure SVG in modal is larger
            const svg = iconContainer.querySelector('svg');
            if (svg) {
                svg.style.width = '64px';
                svg.style.height = '64px';
            }

            // Update selectedMarkerType with custom values
            selectedMarkerType.icon = tz.toString();
            selectedMarkerType.grundzeichen = grundzeichen;
            selectedMarkerType.organisation = organisation || undefined;
            selectedMarkerType.fachaufgabe = fachaufgabe || undefined;
            selectedMarkerType.einheit = einheit || undefined;
            selectedMarkerType.symbol = symbol || undefined;
            selectedMarkerType.typ = typ || undefined;
        } catch (e) {
            showAlert('Fehler beim Erstellen des taktischen Zeichens:\n' + e.message + '\n\nBitte überprüfen Sie die eingegebenen Werte.', 'danger');
            console.error('Error creating custom tactical symbol:', e);
        }
    });

    // Save marker
    document.getElementById('saveMarkerBtn').addEventListener('click', async function() {
        const form = document.getElementById('markerForm');
        const formData = new FormData(form);
        formData.append('incident_id', incidentId);
        formData.append('action', 'create');

        // Add vehicle info if this is a vehicle marker
        if (selectedMarkerType.vehicleId) {
            formData.append('vehicle_id', selectedMarkerType.vehicleId);
        }

        // Add tactical symbol data if available
        if (selectedMarkerType.grundzeichen) {
            formData.append('grundzeichen', selectedMarkerType.grundzeichen);
        }
        if (selectedMarkerType.organisation) {
            formData.append('organisation', selectedMarkerType.organisation);
        }
        if (selectedMarkerType.fachaufgabe) {
            formData.append('fachaufgabe', selectedMarkerType.fachaufgabe);
        }
        if (selectedMarkerType.einheit) {
            formData.append('einheit', selectedMarkerType.einheit);
        }
        if (selectedMarkerType.symbol) {
            formData.append('symbol', selectedMarkerType.symbol);
        }
        if (selectedMarkerType.typ) {
            formData.append('typ', selectedMarkerType.typ);
        }

        // Add text - use form value if provided, otherwise use selected marker's text
        const textValue = document.getElementById('markerText').value.trim();
        if (textValue) {
            formData.append('text', textValue);
        } else if (selectedMarkerType.text) {
            formData.append('text', selectedMarkerType.text);
        }

        // Add name - use form value if provided, otherwise use selected marker's name
        const nameValue = document.getElementById('markerName').value.trim();
        if (nameValue) {
            formData.append('name', nameValue);
        } else if (selectedMarkerType.name) {
            formData.append('name', selectedMarkerType.name);
        }

        try {
            const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('markerModal')).hide();

                // Reset form
                form.reset();

                // Reload page to show new marker
                location.reload();
            } else {
                console.error('API Error:', result);
                showAlert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'), 'danger');
            }
        } catch (error) {
            console.error('Error saving marker:', error);
            showAlert('Fehler beim Speichern des Markers: ' + error.message, 'danger');
        }
    });

    // Delete marker
    document.querySelectorAll('.delete-marker-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const confirmed = await showConfirm(
                'Marker löschen',
                'Möchten Sie diesen Marker wirklich löschen?',
                'Ja, löschen',
                'Abbrechen'
            );

            if (!confirmed) {
                return;
            }

            const markerId = this.dataset.markerId;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('marker_id', markerId);

            try {
                const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    showAlert('Fehler beim Löschen: ' + (result.error || 'Unbekannter Fehler'), 'danger');
                }
            } catch (error) {
                console.error('Error deleting marker:', error);
                showAlert('Fehler beim Löschen des Markers', 'danger');
            }
        });
    });

    // Load and display markers on map
    function loadMarkers() {
        const mapViewport = document.getElementById('mapViewport');
        const img = document.getElementById('mapImage');

        existingMarkers.forEach(marker => {
            const markerEl = createMarkerElement(marker);
            mapViewport.appendChild(markerEl);
        });

        // Update positions after markers are added
        if (img.complete) {
            updateMarkerPositions();
        } else {
            img.addEventListener('load', updateMarkerPositions);
        }
    }

    function createMarkerElement(marker) {
        const markerEl = document.createElement('div');
        markerEl.className = 'map-marker';
        markerEl.dataset.markerId = marker.id;
        markerEl.dataset.posX = marker.pos_x;
        markerEl.dataset.posY = marker.pos_y;

        // Position will be set by updateMarkerPositions()
        markerEl.style.transform = 'translate(-50%, -100%)';

        const icon = document.createElement('div');
        icon.className = 'map-marker-icon';

        // Try to generate tactical symbol if data is available
        if (marker.grundzeichen && window.erzeugeTaktischesZeichen) {
            try {
                const config = {
                    grundzeichen: marker.grundzeichen
                };
                if (marker.organisation) config.organisation = marker.organisation;
                if (marker.fachaufgabe) config.fachaufgabe = marker.fachaufgabe;
                if (marker.einheit) config.einheit = marker.einheit;
                if (marker.symbol) config.symbol = marker.symbol;
                if (marker.typ) config.typ = marker.typ;
                if (marker.text) config.text = marker.text;
                if (marker.name) config.name = marker.name;

                const tz = window.erzeugeTaktischesZeichen(config);
                icon.innerHTML = tz.toString();
            } catch (e) {
                console.error('Error creating tactical symbol for marker:', e);
                icon.textContent = getFallbackIcon(marker.marker_type);
            }
        } else {
            // Fallback to emoji icons
            icon.textContent = getFallbackIcon(marker.marker_type);
        }

        const label = document.createElement('div');
        label.className = 'map-marker-label';
        // For vehicle markers, show vehicle name instead of vehicle_X
        if (marker.marker_type && marker.marker_type.startsWith('vehicle_') && marker.vehicle_name) {
            label.textContent = marker.vehicle_name;
        } else {
            label.textContent = marker.description || marker.marker_type;
        }

        markerEl.appendChild(label);
        markerEl.appendChild(icon);

        // Make marker draggable if not finalized
        if (!isFinalized) {
            makeMarkerDraggable(markerEl);
        }

        return markerEl;
    }

    function makeMarkerDraggable(markerEl) {
        let isDragging = false;
        let dragOffsetX, dragOffsetY;

        markerEl.addEventListener('mousedown', function(e) {
            // Don't start dragging if clicking on delete button or in marker mode
            if (e.target.classList.contains('delete-marker-btn') || markerMode) {
                return;
            }

            isDragging = true;
            markerEl.style.cursor = 'grabbing';
            markerEl.style.zIndex = '1000';

            // Get current position in pixels
            const currentLeft = parseFloat(markerEl.style.left) || 0;
            const currentTop = parseFloat(markerEl.style.top) || 0;

            // Calculate offset from marker position to mouse click in viewport space
            const viewport = document.getElementById('mapViewport');
            const viewportRect = viewport.getBoundingClientRect();

            // Mouse position in viewport space (accounting for scale and translate)
            const mouseViewportX = (e.clientX - viewportRect.left - translateX) / scale;
            const mouseViewportY = (e.clientY - viewportRect.top - translateY) / scale;

            // Calculate offset from marker center to mouse
            dragOffsetX = mouseViewportX - currentLeft;
            dragOffsetY = mouseViewportY - currentTop;

            e.preventDefault();
            e.stopPropagation();
        });

        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;

            const viewport = document.getElementById('mapViewport');
            const viewportRect = viewport.getBoundingClientRect();

            // Calculate mouse position in viewport space
            const mouseViewportX = (e.clientX - viewportRect.left - translateX) / scale;
            const mouseViewportY = (e.clientY - viewportRect.top - translateY) / scale;

            // Set new position
            const newX = mouseViewportX - dragOffsetX;
            const newY = mouseViewportY - dragOffsetY;

            markerEl.style.left = newX + 'px';
            markerEl.style.top = newY + 'px';
        });

        document.addEventListener('mouseup', async function() {
            if (!isDragging) return;

            isDragging = false;
            markerEl.style.cursor = 'pointer';
            markerEl.style.zIndex = '10';

            // Calculate percentage position
            const img = document.getElementById('mapImage');
            const pixelX = parseFloat(markerEl.style.left);
            const pixelY = parseFloat(markerEl.style.top);

            const percentX = (pixelX / img.offsetWidth * 100).toFixed(2);
            const percentY = (pixelY / img.offsetHeight * 100).toFixed(2);

            // Update dataset
            markerEl.dataset.posX = percentX;
            markerEl.dataset.posY = percentY;

            // Save to server
            const markerId = markerEl.dataset.markerId;
            try {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('marker_id', markerId);
                formData.append('pos_x', percentX);
                formData.append('pos_y', percentY);

                const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (!result.success) {
                    console.error('Failed to update marker position:', result.error);
                    showAlert('Fehler beim Verschieben des Markers: ' + result.error, 'danger');
                    // Revert position
                    updateMarkerPositions();
                }
            } catch (error) {
                console.error('Error updating marker position:', error);
                showAlert('Fehler beim Verschieben des Markers', 'danger');
                // Revert position
                updateMarkerPositions();
            }
        });
    }

    function updateMarkerPositions() {
        const img = document.getElementById('mapImage');
        const markers = document.querySelectorAll('.map-marker');

        markers.forEach(marker => {
            const posX = parseFloat(marker.dataset.posX);
            const posY = parseFloat(marker.dataset.posY);

            // Calculate pixel position based on actual image dimensions
            const pixelX = (posX / 100) * img.offsetWidth;
            const pixelY = (posY / 100) * img.offsetHeight;

            marker.style.left = pixelX + 'px';
            marker.style.top = pixelY + 'px';
        });
    }

    function getFallbackIcon(markerType) {
        const icons = {
            kraftfahrzeug: '🚗',
            loeschfahrzeug: '🚒',
            drehleiter: '🚒',
            tankloesch: '🚒',
            rettungswagen: '🚑',
            einsatzleitung: '🎯',
            bereitstellung: '📍',
            sammelplatz: '🏥',
            brandstelle: '🔥',
            gefahrstoff: '☢️',
            einsturz: '⚠️',
            'person-verletzt': '👤',
            'person-vermisst': '👤',
            wasserentnahme: '💧',
            hydrant: '💧',
            custom: '📝',
            other: '📌'
        };
        return icons[markerType] || '📌';
    }
</script>