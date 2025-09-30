<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "admin/login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "admin/index.php");
}

// Lade Dienstgrade und RD-Qualifikationen für Auswahlfelder
$dienstgradeStmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_dienstgrade WHERE archive = 0 ORDER BY priority ASC");
$dienstgrade = $dienstgradeStmt->fetchAll(PDO::FETCH_ASSOC);

$rdQualisStmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_rdquali WHERE trainable = 1 AND none = 0 ORDER BY priority ASC");
$rdQualis = $rdQualisStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Administration &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/admin.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/lineawesome/css/line-awesome.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="<?= BASE_PATH ?>vendor/components/jquery/jquery.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="<?= BASE_PATH ?>assets/favicon/favicon.svg" />
    <link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_PATH ?>assets/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="<?php echo SYSTEM_NAME ?>" />
    <link rel="manifest" href="<?= BASE_PATH ?>assets/favicon/site.webmanifest" />
    <!-- Metas -->
    <meta name="theme-color" content="<?php echo SYSTEM_COLOR ?>" />
    <meta property="og:site_name" content="<?php echo SERVER_NAME ?>" />
    <meta property="og:url" content="https://<?php echo SYSTEM_URL . BASE_PATH ?>/dashboard.php" />
    <meta property="og:title" content="<?php echo SYSTEM_NAME ?> - Intranet <?php echo SERVER_CITY ?>" />
    <meta property="og:image" content="<?php echo META_IMAGE_URL ?>" />
    <meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />
    <style>
        .field-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .field-item .btn-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }

        .field-list {
            min-height: 100px;
        }

        .drag-handle {
            cursor: move;
            color: #6c757d;
            margin-right: 0.5rem;
        }

        .template-preview {
            border: 1px solid #dee2e6;
            padding: 2rem;
            border-radius: 0.375rem;
        }

        .option-item {
            padding: 1rem;
            border-radius: 0.375rem;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container mt-5">
            <h1 class="mb-4">Dokumenten-Templates verwalten</h1>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Template erstellen/bearbeiten</h5>
                        </div>
                        <div class="card-body">
                            <form id="templateForm">
                                <input type="hidden" id="templateId" name="templateId">

                                <div class="mb-3">
                                    <label for="templateName" class="form-label">Template-Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="templateName" name="name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="templateCategory" class="form-label">Kategorie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="templateCategory" name="category" required>
                                        <option value="">Bitte wählen</option>
                                        <option value="urkunde">Urkunde</option>
                                        <option value="zertifikat">Zertifikat</option>
                                        <option value="schreiben">Schreiben</option>
                                        <option value="sonstiges">Sonstiges</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="templateDescription" class="form-label">Beschreibung</label>
                                    <textarea class="form-control" id="templateDescription" name="description" rows="2"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="templateFile" class="form-label">Template-Dateiname</label>
                                    <input type="text" class="form-control" id="templateFile" name="template_file"
                                        pattern="[a-z_]+\.html\.twig"
                                        placeholder="z.B. mein_dokument.html.twig">
                                    <small class="text-muted">Format: kleinbuchstaben_mit_unterstrichen.html.twig (wird automatisch generiert, falls leer)</small>
                                </div>

                                <hr class="my-4">

                                <h6 class="mb-3">Formularfelder</h6>

                                <div id="fieldList" class="field-list"></div>

                                <button type="button" class="btn btn-secondary btn-sm" id="addFieldBtn">
                                    + Feld hinzufügen
                                </button>

                                <hr class="my-4">

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Template speichern</button>
                                    <button type="button" class="btn btn-outline-secondary" id="previewBtn">Vorschau</button>
                                    <button type="button" class="btn btn-outline-danger" id="resetBtn">Zurücksetzen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Vorhandene Templates</h5>
                        </div>
                        <div class="card-body">
                            <div id="templateList" class="list-group"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Vorschau -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Formular-Vorschau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent" class="template-preview"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Feld-Konfiguration -->
    <div class="modal fade" id="fieldModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feld konfigurieren</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="fieldForm">
                        <input type="hidden" id="fieldIndex">

                        <div class="mb-3">
                            <label for="fieldLabel" class="form-label">Feld-Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fieldLabel" required>
                        </div>

                        <div class="mb-3">
                            <label for="fieldName" class="form-label">Feld-Name (technisch) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fieldName" required
                                pattern="[a-z_]+" title="Nur Kleinbuchstaben und Unterstriche">
                            <small class="text-muted">Nur Kleinbuchstaben und Unterstriche erlaubt</small>
                        </div>

                        <div class="mb-3">
                            <label for="fieldType" class="form-label">Feld-Typ <span class="text-danger">*</span></label>
                            <select class="form-select" id="fieldType" required>
                                <option value="text">Textfeld</option>
                                <option value="textarea">Mehrzeiliger Text</option>
                                <option value="richtext">Rich-Text Editor</option>
                                <option value="date">Datum</option>
                                <option value="number">Zahl</option>
                                <option value="select">Auswahlfeld (manuell)</option>
                                <option value="db_dg">Dienstgrad-Auswahl (aus DB)</option>
                                <option value="db_rdq">RD-Qualifikation (aus DB)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="genderSpecificContainer" style="display: none;">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="genderSpecific">
                                <label class="form-check-label" for="genderSpecific">
                                    Geschlechtsspezifische Optionen
                                </label>
                                <small class="form-text text-muted d-block">
                                    Aktivieren für männlich/weiblich/neutral Varianten
                                </small>
                            </div>
                        </div>

                        <div id="optionsContainer" class="mb-3" style="display: none;">
                            <label class="form-label">Auswahloptionen</label>
                            <div id="optionsList"></div>
                            <button type="button" class="btn btn-sm btn-secondary" id="addOptionBtn">
                                + Option hinzufügen
                            </button>
                        </div>

                        <div class="alert alert-info" id="dbFieldInfo" style="display: none;">
                            <strong>Hinweis:</strong> Dieses Feld wird automatisch mit Daten aus der Datenbank befüllt.
                            Die geschlechtsspezifischen Varianten werden automatisch berücksichtigt.
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="fieldRequired">
                            <label class="form-check-label" for="fieldRequired">
                                Pflichtfeld
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" id="saveFieldBtn">Feld speichern</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_PATH = '<?= BASE_PATH ?>';

        // Datenbank-Daten für Auswahlfelder
        const DIENSTGRADE = <?= json_encode($dienstgrade) ?>;
        const RD_QUALIS = <?= json_encode($rdQualis) ?>;

        let fields = [];
        let editingFieldIndex = null;
        let templates = [];

        const fieldModal = new bootstrap.Modal(document.getElementById('fieldModal'));
        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));

        document.getElementById('addFieldBtn').addEventListener('click', () => {
            editingFieldIndex = null;
            document.getElementById('fieldForm').reset();
            document.getElementById('fieldIndex').value = '';
            document.getElementById('optionsList').innerHTML = '';
            document.getElementById('genderSpecific').checked = false;
            updateOptionsVisibility();
            fieldModal.show();
        });

        document.getElementById('fieldType').addEventListener('change', updateOptionsVisibility);
        document.getElementById('genderSpecific').addEventListener('change', updateGenderFields);
        document.getElementById('addOptionBtn').addEventListener('click', () => addOption());
        document.getElementById('saveFieldBtn').addEventListener('click', saveField);
        document.getElementById('templateForm').addEventListener('submit', saveTemplate);
        document.getElementById('previewBtn').addEventListener('click', showPreview);
        document.getElementById('resetBtn').addEventListener('click', resetForm);

        function updateOptionsVisibility() {
            const fieldType = document.getElementById('fieldType').value;
            const optionsContainer = document.getElementById('optionsContainer');
            const genderContainer = document.getElementById('genderSpecificContainer');
            const dbFieldInfo = document.getElementById('dbFieldInfo');

            if (fieldType === 'select') {
                optionsContainer.style.display = 'block';
                genderContainer.style.display = 'block';
                dbFieldInfo.style.display = 'none';
            } else if (fieldType === 'db_dg' || fieldType === 'db_rdq') {
                optionsContainer.style.display = 'none';
                genderContainer.style.display = 'none';
                dbFieldInfo.style.display = 'block';
            } else {
                optionsContainer.style.display = 'none';
                genderContainer.style.display = 'none';
                dbFieldInfo.style.display = 'none';
            }
        }

        function updateGenderFields() {
            const isGenderSpecific = document.getElementById('genderSpecific').checked;
            const genderInputs = document.querySelectorAll('.gender-inputs');

            genderInputs.forEach(group => {
                group.style.display = isGenderSpecific ? 'block' : 'none';
            });
        }

        function addOption(value = '', label = '', label_m = '', label_w = '', hasGender = false) {
            const optionsList = document.getElementById('optionsList');
            const isGenderSpecific = document.getElementById('genderSpecific').checked;

            const optionDiv = document.createElement('div');
            optionDiv.className = 'option-item mb-3';
            optionDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Option</strong>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.option-item').remove()">Löschen</button>
            </div>
            <div class="mb-2">
                <label class="form-label small">Wert (z.B. 0, 1, 2)</label>
                <input type="text" class="form-control form-control-sm" value="${value}" data-option-value>
            </div>
            <div class="mb-2">
                <label class="form-label small">Label ${isGenderSpecific ? 'Neutral/Allgemein' : ''}</label>
                <input type="text" class="form-control form-control-sm" placeholder="z.B. Brandmeister${isGenderSpecific ? '/-in' : ''}" value="${label}" data-option-label>
            </div>
            <div class="gender-inputs row" style="display: ${isGenderSpecific ? 'block' : 'none'}">
                <div class="col-6">
                    <label class="form-label small">Label Männlich</label>
                    <input type="text" class="form-control form-control-sm" placeholder="z.B. Brandmeister" value="${label_m}" data-option-label-m>
                </div>
                <div class="col-6">
                    <label class="form-label small">Label Weiblich</label>
                    <input type="text" class="form-control form-control-sm" placeholder="z.B. Brandmeisterin" value="${label_w}" data-option-label-w>
                </div>
            </div>
        `;
            optionsList.appendChild(optionDiv);
        }

        function saveField() {
            const fieldLabel = document.getElementById('fieldLabel').value;
            const fieldName = document.getElementById('fieldName').value;
            const fieldType = document.getElementById('fieldType').value;
            const fieldRequired = document.getElementById('fieldRequired').checked;
            const genderSpecific = document.getElementById('genderSpecific').checked;

            if (!fieldLabel || !fieldName) {
                alert('Bitte alle Pflichtfelder ausfüllen');
                return;
            }

            let options = null;

            // Bei DB-Feldern generiere automatisch Optionen
            if (fieldType === 'db_dg') {
                options = DIENSTGRADE.map(dg => ({
                    value: dg.id,
                    label: dg.name,
                    label_m: dg.name_m,
                    label_w: dg.name_w
                }));
            } else if (fieldType === 'db_rdq') {
                options = RD_QUALIS.map(rd => ({
                    value: rd.id,
                    label: rd.name,
                    label_m: rd.name_m,
                    label_w: rd.name_w
                }));
            } else if (fieldType === 'select') {
                options = [];
                const optionContainers = document.querySelectorAll('#optionsList .option-item');
                optionContainers.forEach(container => {
                    const value = container.querySelector('[data-option-value]').value;
                    const label = container.querySelector('[data-option-label]').value;

                    if (value && label) {
                        const option = {
                            value,
                            label
                        };

                        if (genderSpecific) {
                            const label_m = container.querySelector('[data-option-label-m]').value;
                            const label_w = container.querySelector('[data-option-label-w]').value;
                            option.label_m = label_m || label;
                            option.label_w = label_w || label;
                        }

                        options.push(option);
                    }
                });
            }

            const field = {
                field_label: fieldLabel,
                field_name: fieldName,
                field_type: fieldType,
                is_required: fieldRequired,
                gender_specific: genderSpecific || (fieldType === 'db_dg' || fieldType === 'db_rdq'),
                field_options: options,
                sort_order: editingFieldIndex !== null ? fields[editingFieldIndex].sort_order : fields.length
            };

            if (editingFieldIndex !== null) {
                fields[editingFieldIndex] = field;
            } else {
                fields.push(field);
            }

            renderFields();
            fieldModal.hide();
        }

        function renderFields() {
            const fieldList = document.getElementById('fieldList');
            fieldList.innerHTML = '';

            if (fields.length === 0) {
                fieldList.innerHTML = '<p class="text-muted">Noch keine Felder hinzugefügt</p>';
                return;
            }

            fields.forEach((field, index) => {
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'field-item';
                fieldDiv.innerHTML = `
                <button type="button" class="btn btn-sm btn-danger btn-remove" onclick="removeField(${index})">×</button>
                <div class="d-flex align-items-center mb-2">
                    <span class="drag-handle">☰</span>
                    <strong>${field.field_label}</strong>
                    ${field.is_required ? '<span class="badge bg-danger ms-2">Pflichtfeld</span>' : ''}
                    ${field.gender_specific ? '<span class="badge bg-info ms-2">Geschlechtsspezifisch</span>' : ''}
                    ${(field.field_type === 'db_dg' || field.field_type === 'db_rdq') ? '<span class="badge bg-success ms-2">DB-Feld</span>' : ''}
                </div>
                <div class="text-muted small">
                    Typ: ${getFieldTypeLabel(field.field_type)} | 
                    Name: ${field.field_name}
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="editField(${index})">
                    Bearbeiten
                </button>
            `;
                fieldList.appendChild(fieldDiv);
            });
        }

        function removeField(index) {
            if (confirm('Feld wirklich löschen?')) {
                fields.splice(index, 1);
                renderFields();
            }
        }

        function editField(index) {
            editingFieldIndex = index;
            const field = fields[index];

            document.getElementById('fieldLabel').value = field.field_label;
            document.getElementById('fieldName').value = field.field_name;
            document.getElementById('fieldType').value = field.field_type;
            document.getElementById('fieldRequired').checked = field.is_required;
            document.getElementById('genderSpecific').checked = field.gender_specific || false;

            updateOptionsVisibility();

            document.getElementById('optionsList').innerHTML = '';
            if (field.field_type === 'select' && field.field_options) {
                field.field_options.forEach(opt => {
                    addOption(
                        opt.value,
                        opt.label,
                        opt.label_m || opt.label,
                        opt.label_w || opt.label,
                        field.gender_specific
                    );
                });
            }

            updateGenderFields();
            fieldModal.show();
        }

        function getFieldTypeLabel(type) {
            const types = {
                text: 'Textfeld',
                textarea: 'Mehrzeiliger Text',
                richtext: 'Rich-Text',
                date: 'Datum',
                number: 'Zahl',
                select: 'Auswahlfeld',
                db_dg: 'Dienstgrad (DB)',
                db_rdq: 'RD-Qualifikation (DB)'
            };
            return types[type] || type;
        }

        async function saveTemplate(e) {
            e.preventDefault();

            const templateName = document.getElementById('templateName').value;
            let templateFile = document.getElementById('templateFile').value;

            if (!templateFile) {
                templateFile = templateName.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '') + '.html.twig';
            }

            const formData = {
                name: templateName,
                category: document.getElementById('templateCategory').value,
                description: document.getElementById('templateDescription').value,
                template_file: templateFile,
                fields: fields
            };

            const templateId = document.getElementById('templateId').value;

            try {
                const response = await fetch(BASE_PATH + 'api/documents/save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: templateId || null,
                        ...formData
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Template erfolgreich gespeichert!');
                    loadTemplates();
                    resetForm();
                } else {
                    alert('Fehler beim Speichern: ' + result.error);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Fehler beim Speichern: ' + error.message);
            }
        }

        async function loadTemplates() {
            try {
                const response = await fetch(BASE_PATH + 'api/documents/list.php');
                templates = await response.json();
                renderTemplateList();
            } catch (error) {
                console.error('Fehler beim Laden der Templates:', error);
            }
        }

        function renderTemplateList() {
            const list = document.getElementById('templateList');
            list.innerHTML = '';

            if (!templates || templates.length === 0) {
                list.innerHTML = '<p class="text-muted">Keine Templates vorhanden</p>';
                return;
            }

            templates.forEach(template => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${template.name}</strong>
                        <br>
                        <small class="text-muted">${template.category}</small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(${template.id}, event)">
                            Löschen
                        </button>
                    </div>
                </div>
            `;
                item.addEventListener('click', (e) => {
                    if (e.target.tagName !== 'BUTTON') {
                        loadTemplate(template.id);
                    }
                });
                list.appendChild(item);
            });
        }

        async function loadTemplate(id) {
            try {
                const response = await fetch(BASE_PATH + `api/documents/get.php?id=${id}`);
                const template = await response.json();

                document.getElementById('templateId').value = template.id;
                document.getElementById('templateName').value = template.name;
                document.getElementById('templateCategory').value = template.category;
                document.getElementById('templateDescription').value = template.description || '';
                document.getElementById('templateFile').value = template.template_file || '';

                fields = template.fields || [];
                renderFields();
            } catch (error) {
                alert('Fehler beim Laden des Templates: ' + error.message);
            }
        }

        async function deleteTemplate(id, event) {
            event.stopPropagation();

            if (!confirm('Template wirklich löschen?')) {
                return;
            }

            try {
                const response = await fetch(BASE_PATH + `api/documents/delete.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();

                if (result.success) {
                    loadTemplates();
                } else {
                    alert('Fehler beim Löschen: ' + result.error);
                }
            } catch (error) {
                alert('Fehler beim Löschen: ' + error.message);
            }
        }

        function resetForm() {
            document.getElementById('templateForm').reset();
            document.getElementById('templateId').value = '';
            document.getElementById('templateFile').value = '';
            fields = [];
            renderFields();
        }

        function showPreview() {
            const preview = document.getElementById('previewContent');
            preview.innerHTML = '<h5>' + (document.getElementById('templateName').value || 'Unbenanntes Template') + '</h5>';

            fields.forEach(field => {
                preview.innerHTML += renderFieldPreview(field);
            });

            previewModal.show();
        }

        function renderFieldPreview(field) {
            const required = field.is_required ? '<span class="text-danger">*</span>' : '';
            let html = `<div class="mb-3">
            <label class="form-label">${field.field_label} ${required}</label>`;

            switch (field.field_type) {
                case 'text':
                    html += `<input type="text" class="form-control" ${field.is_required ? 'required' : ''}>`;
                    break;
                case 'textarea':
                    html += `<textarea class="form-control" rows="3" ${field.is_required ? 'required' : ''}></textarea>`;
                    break;
                case 'richtext':
                    html += `<textarea class="form-control" rows="5" ${field.is_required ? 'required' : ''}></textarea>
                         <small class="text-muted">Rich-Text Editor würde hier angezeigt</small>`;
                    break;
                case 'date':
                    html += `<input type="date" class="form-control" ${field.is_required ? 'required' : ''}>`;
                    break;
                case 'number':
                    html += `<input type="number" class="form-control" ${field.is_required ? 'required' : ''}>`;
                    break;
                case 'select':
                case 'db_dg':
                case 'db_rdq':
                    html += `<select class="form-select" ${field.is_required ? 'required' : ''}>
                    <option value="">Bitte wählen</option>`;
                    if (field.field_options) {
                        field.field_options.forEach(opt => {
                            html += `<option value="${opt.value}">${opt.label}</option>`;
                        });
                    }
                    html += `</select>`;
                    if (field.field_type !== 'select') {
                        html += `<small class="text-muted">Daten aus Datenbank</small>`;
                    }
                    break;
            }

            html += '</div>';
            return html;
        }

        loadTemplates();
    </script>
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>