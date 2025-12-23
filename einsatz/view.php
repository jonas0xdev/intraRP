<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

require __DIR__ . '/../assets/config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    Flash::error('Ungültige Einsatz-ID');
    header('Location: ' . BASE_PATH . 'index.php');
    exit();
}

// Preload incident for later checks (needed for update_core guard)
$incident = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM intra_fire_incidents WHERE id = ?");
    $stmt->execute([$id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // continue; handled below if null
}

// Handle actions: add/remove vehicle, add sitrep, finalize, update status, update core
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_vehicle' && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])) {
            if ($incident && $incident['finalized']) goto post_done;
            $vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
            $vehicle_name = trim($_POST['vehicle_name'] ?? '');
            $vehicle_identifier = trim($_POST['vehicle_identifier'] ?? '');
            $radio_name = trim($_POST['radio_name'] ?? '');
            $from_other = ($vehicle_id === null) ? 1 : 0;

            if ($vehicle_id !== null) {
                $dup = $pdo->prepare("SELECT COUNT(*) FROM intra_fire_incident_vehicles WHERE incident_id = ? AND vehicle_id = ?");
                $dup->execute([$id, $vehicle_id]);
                if ($dup->fetchColumn() > 0) {
                    Flash::error('Fahrzeug bereits hinzugefügt.');
                    goto post_done;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_vehicles (incident_id, vehicle_id, vehicle_name, vehicle_identifier, from_other_org, radio_name, created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$id, $vehicle_id, $vehicle_name ?: null, $vehicle_identifier ?: null, $from_other, $radio_name ?: null, $_SESSION['userid']]);
            Flash::success('Einsatzmittel hinzugefügt.');
        }

        if ($action === 'remove_vehicle' && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])) {
            if ($incident && $incident['finalized']) goto post_done;
            $rowId = (int)($_POST['vehicle_row_id'] ?? 0);
            if ($rowId > 0) {
                $del = $pdo->prepare("DELETE FROM intra_fire_incident_vehicles WHERE id = ? AND incident_id = ?");
                $del->execute([$rowId, $id]);
                Flash::success('Fahrzeug entfernt.');
            }
        }

        if ($action === 'add_sitrep' && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])) {
            if ($incident && $incident['finalized']) goto post_done;
            $rt_date = $_POST['rt_date'] ?? '';
            $rt_time = $_POST['rt_time'] ?? '';
            $text = trim($_POST['text'] ?? '');
            $vehicle_attached_id = !empty($_POST['sitrep_attached_vehicle_id']) ? (int)$_POST['sitrep_attached_vehicle_id'] : null;

            if ($rt_date && $rt_time && $text !== '' && $vehicle_attached_id) {
                $report_time = date('Y-m-d H:i:s', strtotime($rt_date . ' ' . $rt_time));
                $infoStmt = $pdo->prepare("SELECT v.radio_name, f.name AS sys_name FROM intra_fire_incident_vehicles v LEFT JOIN intra_fahrzeuge f ON v.vehicle_id = f.id WHERE v.id = ? AND v.incident_id = ?");
                $infoStmt->execute([$vehicle_attached_id, $id]);
                $vinfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
                $radio = $vinfo['radio_name'] ?? $vinfo['sys_name'] ?? null;
                $stmt = $pdo->prepare("INSERT INTO intra_fire_incident_sitreps (incident_id, report_time, text, vehicle_radio_name, vehicle_id, created_by) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$id, $report_time, $text, $radio ?: null, null, $_SESSION['userid']]);
                Flash::success('Lagemeldung gespeichert.');
            } else {
                Flash::error('Bitte Datum, Uhrzeit, Text und Fahrzeug vor Ort wählen.');
            }
        }

        if ($action === 'finalize' && Permissions::check(['admin', 'fire.incident.qm'])) {
            $stmt = $pdo->prepare("SELECT location, keyword, started_at, leader_id FROM intra_fire_incidents WHERE id = ?");
            $stmt->execute([$id]);
            $inc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($inc && $inc['location'] && $inc['keyword'] && $inc['started_at'] && !empty($inc['leader_id'])) {
                $upd = $pdo->prepare("UPDATE intra_fire_incidents SET finalized = 1, finalized_at = NOW(), finalized_by = ?, status = 'in_sichtung' WHERE id = ?");
                $upd->execute([$_SESSION['userid'], $id]);
                Flash::success('Protokoll zur QM-Sichtung markiert.');
            } else {
                Flash::error('Pflichtangaben fehlen für Abschluss (inkl. Einsatzleiter).');
            }
        }

        if ($action === 'set_status' && Permissions::check(['admin', 'fire.incident.qm'])) {
            $status = $_POST['status'] ?? 'in_sichtung';
            if (in_array($status, ['gesichtet', 'in_sichtung', 'negativ'], true)) {
                $upd = $pdo->prepare("UPDATE intra_fire_incidents SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$status, $_SESSION['userid'], $id]);
                Flash::success('Status aktualisiert.');
            }
        }

        if ($action === 'update_core' && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])) {
            if ($incident && !$incident['finalized']) {
                $loc = trim($_POST['edit_location'] ?? '');
                $keyw = trim($_POST['edit_keyword'] ?? '');
                $incno = trim($_POST['edit_incident_number'] ?? '');
                $date = $_POST['edit_date'] ?? '';
                $time = $_POST['edit_time'] ?? '';
                $leader = !empty($_POST['edit_leader_id']) ? (int)$_POST['edit_leader_id'] : null;
                $notes = trim($_POST['edit_notes'] ?? '');
                $owner_name = trim($_POST['edit_owner_name'] ?? '');
                $owner_contact = trim($_POST['edit_owner_contact'] ?? '');
                if ($incno === '' || $loc === '' || $keyw === '' || $date === '' || $time === '' || $leader === null) {
                    Flash::error('Bitte alle Pflichtfelder ausfüllen (Nummer, Ort, Stichwort, Beginn, Einsatzleiter).');
                } else {
                    $started = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
                    $upd = $pdo->prepare("UPDATE intra_fire_incidents SET incident_number = ?, location = ?, keyword = ?, started_at = ?, leader_id = ?, owner_type = NULL, owner_name = ?, owner_contact = ?, notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                    $upd->execute([$incno, $loc, $keyw, $started, $leader, $owner_name ?: null, $owner_contact ?: null, $notes ?: null, $_SESSION['userid'], $id]);
                    Flash::success('Einsatzdaten gespeichert.');
                }
            }
        }
    } catch (PDOException $e) {
        Flash::error('Fehler: ' . $e->getMessage());
    }
    post_done:
}

// Reload incident after possible changes
try {
    $stmt = $pdo->prepare("SELECT i.*, m.fullname AS leader_name FROM intra_fire_incidents i LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$incident) {
        Flash::error('Einsatz nicht gefunden');
        header('Location: ' . BASE_PATH . 'index.php');
        exit();
    }
} catch (PDOException $e) {
    Flash::error('Fehler beim Laden: ' . $e->getMessage());
}

// Load vehicles for selection and attached
$allVehicles = [];
$attachedVehicles = [];
try {
    $allVehicles = $pdo->query("SELECT id, name, identifier, veh_type FROM intra_fahrzeuge WHERE active = 1 ORDER BY priority ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT v.*, f.name AS sys_name, f.veh_type AS sys_type FROM intra_fire_incident_vehicles v LEFT JOIN intra_fahrzeuge f ON v.vehicle_id = f.id WHERE v.incident_id = ? ORDER BY v.id ASC");
    $stmt->execute([$id]);
    $attachedVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// Load sitreps
$sitreps = [];
try {
    $stmt = $pdo->prepare("SELECT s.*, f.name AS sys_name FROM intra_fire_incident_sitreps s LEFT JOIN intra_fahrzeuge f ON s.vehicle_id = f.id WHERE s.incident_id = ? ORDER BY s.report_time ASC");
    $stmt->execute([$id]);
    $sitreps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// Helper for date/time formatting
function fmt_dt(?string $ts): string
{
    if (!$ts) return '-';
    try {
        $dt = new DateTime($ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Einsatzprotokoll</h1>
            <div>
                <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                    <span class="me-2 align-middle text-muted small">QM-Status:
                        <?php
                        if (!$incident['finalized']) {
                            $badge = 'bg-secondary';
                            $statusText = 'Unfertig';
                        } else {
                            $badge = 'bg-danger';
                            $statusText = 'Ungesichtet';
                            if ($incident['status'] === 'gesichtet') {
                                $badge = 'bg-success';
                                $statusText = 'Gesichtet';
                            } elseif ($incident['status'] === 'negativ') {
                                $badge = 'bg-danger';
                                $statusText = 'Negativ';
                            }
                        }
                        ?>
                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($statusText) ?></span>
                    </span>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#qmActionsModal">
                        <i class="fa-solid fa-clipboard-check"></i> QM-Aktionen
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="mb-2 text-muted">Einsatznummer: <?= htmlspecialchars($incident['incident_number'] ?? '–') ?></div>
        <?php App\Helpers\Flash::render(); ?>

        <div class="intra__tile p-3 mb-3">
            <?php
            $canFinalize = false;
            $missingRequired = [];
            if ($incident) {
                if (empty($incident['incident_number'])) $missingRequired[] = 'Einsatznummer';
                if (empty($incident['location'])) $missingRequired[] = 'Einsatzort';
                if (empty($incident['keyword'])) $missingRequired[] = 'Einsatzstichwort';
                if (empty($incident['started_at'])) $missingRequired[] = 'Beginn (Datum & Uhrzeit)';
                if (empty($incident['leader_id'])) $missingRequired[] = 'Einsatzleiter';
                $canFinalize = empty($missingRequired) && !$incident['finalized'];
            }

            $dtStart = $incident['started_at'] ? new DateTime($incident['started_at'], new DateTimeZone('UTC')) : null;
            if ($dtStart) {
                $dtStart->setTimezone(new DateTimeZone('Europe/Berlin'));
            }
            $startDate = $dtStart ? $dtStart->format('Y-m-d') : '';
            $startTime = $dtStart ? $dtStart->format('H:i') : '';
            ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Ort</label>
                    <input type="text" class="form-control" name="edit_location" value="<?= htmlspecialchars($incident['location']) ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Stichwort</label>
                    <input type="text" class="form-control" name="edit_keyword" value="<?= htmlspecialchars($incident['keyword']) ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Einsatznummer</label>
                    <input type="text" class="form-control" name="edit_incident_number" value="<?= htmlspecialchars($incident['incident_number'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Beginn</label>
                    <div class="d-flex gap-2">
                        <input type="date" class="form-control" style="max-width: 160px;" name="edit_date" value="<?= $startDate ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                        <input type="time" class="form-control" style="max-width: 120px;" name="edit_time" value="<?= $startTime ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Einsatzleiter</label>
                    <select class="form-select" name="edit_leader_id" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                        <option value="">– auswählen –</option>
                        <?php foreach ($pdo->query("SELECT id, fullname FROM intra_mitarbeiter ORDER BY fullname ASC") as $l): ?>
                            <option value="<?= (int)$l['id'] ?>" <?= (int)$incident['leader_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <hr class="my-2">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Geschädigter – Name</label>
                    <input type="text" class="form-control" name="edit_owner_name" value="<?= htmlspecialchars($incident['owner_name'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Geschädigter – Kontakt</label>
                    <input type="text" class="form-control" name="edit_owner_contact" value="<?= htmlspecialchars($incident['owner_contact'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm">
                </div>
                <div class="col-md-12">
                    <small class="text-muted">Optional: Angaben zum Geschädigten (Name/Kontakt).</small>
                </div>
                <div class="col-12">
                    <hr class="my-2">
                </div>
                <div class="col-12">
                    <label class="form-label">Einsatzgeschehen</label>
                    <textarea class="form-control" name="edit_notes" rows="5" <?= $incident['finalized'] ? 'disabled' : '' ?> form="coreUpdateForm"><?= htmlspecialchars($incident['notes'] ?? '') ?></textarea>
                </div>
                <?php if (!$incident['finalized'] && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])): ?>
                    <div class="col-12 d-flex justify-content-end align-items-end">
                        <button type="submit" class="btn btn-primary" form="coreUpdateForm">Speichern</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])): ?>
            <form method="post" id="coreUpdateForm">
                <input type="hidden" name="action" value="update_core">
            </form>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="intra__tile p-3 mb-3">
                    <h4>Eingesetzte Mittel</h4>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Art</th>
                                <th>Rufname</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attachedVehicles as $av): ?>
                                <?php
                                $art = $av['sys_type'] ?: ($av['vehicle_name'] ?? '-');
                                $ruf = $av['radio_name'] ?: ($av['vehicle_identifier'] ?? ($av['sys_name'] ?? '-'));
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($art) ?></td>
                                    <td><?= htmlspecialchars($ruf) ?></td>
                                    <td class="text-end">
                                        <?php if (!$incident['finalized'] && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="remove_vehicle">
                                                <input type="hidden" name="vehicle_row_id" value="<?= (int)$av['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Entfernen</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (!$incident['finalized'] && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])): ?>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="action" value="add_vehicle">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label">Systemfahrzeug</label>
                                    <select name="vehicle_id" class="form-select" <?= $incident['finalized'] ? 'disabled' : '' ?>>
                                        <option value="">– optional –</option>
                                        <?php
                                        $attachedIds = array_filter(array_map(fn($x) => $x['vehicle_id'] ?? null, $attachedVehicles));
                                        $attachedIds = array_map('intval', $attachedIds);
                                        foreach ($allVehicles as $v):
                                            if (in_array((int)$v['id'], $attachedIds, true)) continue;
                                        ?>
                                            <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['veh_type'] ?? $v['name']) ?> (<?= htmlspecialchars($v['name']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Freitext Art</label>
                                    <input type="text" name="vehicle_name" class="form-control" placeholder="z.B. HLF" <?= $incident['finalized'] ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Freitext Rufname</label>
                                    <input type="text" name="radio_name" class="form-control" <?= $incident['finalized'] ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Freitext Identifier</label>
                                    <input type="text" name="vehicle_identifier" class="form-control" placeholder="Kennzeichen/ID" <?= $incident['finalized'] ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6 d-flex align-items-end justify-content-end">
                                    <button type="submit" class="btn btn-primary" <?= $incident['finalized'] ? 'disabled' : '' ?>>Hinzufügen</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="intra__tile p-3 mb-3">
                    <h4>Lagemeldungen</h4>
                    <ul class="list-group">
                        <?php foreach ($sitreps as $sr): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?= fmt_dt($sr['report_time']) ?></strong>
                                        <?php if ($sr['vehicle_radio_name']): ?>
                                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($sr['vehicle_radio_name']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($sr['sys_name']): ?>
                                            <span class="badge bg-info ms-2"><?= htmlspecialchars($sr['sys_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-2"><?= nl2br(htmlspecialchars($sr['text'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!$incident['finalized'] && Permissions::check(['admin', 'fire.incident.create', 'fire.incident.qm'])): ?>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="action" value="add_sitrep">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label">Datum</label>
                                    <input type="date" name="rt_date" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Uhrzeit</label>
                                    <input type="time" name="rt_time" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Durch</label>
                                    <select name="sitrep_attached_vehicle_id" class="form-select" required>
                                        <option value="">– auswählen –</option>
                                        <?php foreach ($attachedVehicles as $av): ?>
                                            <?php
                                            $art = $av['sys_type'] ?: ($av['vehicle_name'] ?? '-');
                                            $ruf = $av['radio_name'] ?: ($av['vehicle_identifier'] ?? ($av['sys_name'] ?? '-'));
                                            ?>
                                            <option value="<?= (int)$av['id'] ?>"><?= htmlspecialchars($ruf . ' (' . $art . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Text</label>
                                    <textarea name="text" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">Speichern</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
        <?php
        // Abschluss-Voraussetzungen bereits oben berechnet: $canFinalize, $missingRequired
        ?>
        <div class="modal fade" id="qmActionsModal" tabindex="-1" aria-labelledby="qmActionsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qmActionsModalLabel">QM-Aktionen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <h6 class="mb-2">Status setzen</h6>
                            <form method="post" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="action" value="set_status">
                                <select name="status" class="form-select" style="max-width: 240px;">
                                    <option value="in_sichtung" <?= $incident['status'] === 'in_sichtung' ? 'selected' : '' ?>>Ungesichtet</option>
                                    <option value="gesichtet" <?= $incident['status'] === 'gesichtet' ? 'selected' : '' ?>>Gesichtet</option>
                                </select>
                                <button class="btn btn-primary" type="submit">Speichern</button>
                            </form>
                        </div>

                        <hr>

                        <?php if (!$incident['finalized']): ?>
                            <div>
                                <h6 class="mb-2">Protokoll abschließen</h6>
                                <?php if (!$canFinalize): ?>
                                    <div class="alert alert-warning py-2">
                                        <div class="small">Abschluss nicht möglich. Bitte folgende Pflichtangaben ergänzen:</div>
                                        <ul class="small mb-0">
                                            <?php foreach ($missingRequired as $mr): ?>
                                                <li><?= htmlspecialchars($mr) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <form method="post" class="d-flex justify-content-between align-items-center">
                                    <input type="hidden" name="action" value="finalize">
                                    <div class="text-muted small">Markiert zur QM-Sichtung; Daten werden gesperrt.</div>
                                    <button type="submit" class="btn btn-success" <?= $canFinalize ? '' : 'disabled' ?>>Abschließen</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php include __DIR__ . '/../assets/components/footer.php'; ?>
</body>

</html>