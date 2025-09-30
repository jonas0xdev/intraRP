<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/DocumentTemplateManager.php';

use App\Documents\DocumentTemplateManager;
use App\Auth\Permissions;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $manager = new DocumentTemplateManager($pdo);

    if (isset($input['id']) && $input['id']) {
        $manager->updateTemplate($input['id'], [
            'name' => $input['name'],
            'category' => $input['category'],
            'description' => $input['description'] ?? null,
            'template_file' => $input['template_file'] ?? null
        ]);

        // Lösche alte Felder
        $stmt = $pdo->prepare("DELETE FROM intra_dokument_template_fields WHERE template_id = :id");
        $stmt->execute(['id' => $input['id']]);

        $templateId = $input['id'];
    } else {
        // Create
        $templateFile = $input['template_file'] ?? strtolower(str_replace(' ', '_', $input['name'])) . '.html.twig';

        $templateId = $manager->createTemplate([
            'name' => $input['name'],
            'category' => $input['category'],
            'description' => $input['description'] ?? null,
            'template_file' => $templateFile
        ]);
    }

    // Füge Felder hinzu
    foreach ($input['fields'] as $field) {
        $field['is_required'] = !empty($field['is_required']) ? 1 : 0;
        $manager->addField($templateId, $field);
    }

    // Speichere Config mit geschlechtsspezifischen Labels
    $config = buildTemplateConfig($input['fields']);
    $stmt = $pdo->prepare("UPDATE intra_dokument_templates SET config = ? WHERE id = ?");
    $stmt->execute([json_encode($config), $templateId]);

    // Erstelle Template-Datei NUR wenn sie noch nicht existiert
    $templateCreated = createTemplateFile($templateId, $input);

    echo json_encode([
        'success' => true,
        'id' => $templateId,
        'template_created' => $templateCreated,
        'message' => $templateCreated
            ? 'Template erfolgreich erstellt'
            : 'Template aktualisiert (bestehende .twig-Datei wurde nicht überschrieben)'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function buildTemplateConfig($fields)
{
    $config = ['fields' => []];

    foreach ($fields as $field) {
        if ($field['field_type'] === 'select' && !empty($field['field_options'])) {
            $config['fields'][$field['field_name']] = [
                'type' => 'select',
                'label' => $field['field_label'],
                'options' => $field['field_options'],
                'gender_specific' => $field['gender_specific'] ?? false
            ];
        }
    }

    return $config;
}

function createTemplateFile($templateId, $data)
{
    $templatePath = __DIR__ . '/../../dokumente/templates/';
    if (!file_exists($templatePath)) {
        mkdir($templatePath, 0755, true);
    }

    $filename = $data['template_file'] ?? strtolower(str_replace(' ', '_', $data['name'])) . '.html.twig';
    $filepath = $templatePath . $filename;

    // WICHTIG: Prüfe ob Datei bereits existiert
    if (file_exists($filepath)) {
        error_log("Template-Datei existiert bereits: {$filepath} - wird nicht überschrieben");
        return false; // Datei wurde NICHT erstellt/überschrieben
    }

    $twig = generateTwigTemplate($data);
    file_put_contents($filepath, $twig);

    return true; // Datei wurde neu erstellt
}

function generateTwigTemplate($data)
{
    $template = <<<'TWIG'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ SYSTEM_NAME }}</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="{{ BASE_PATH }}assets/css/style.min.css" />
    <link rel="stylesheet" href="{{ BASE_PATH }}assets/css/dokumente.min.css" />
    <link rel="stylesheet" href="{{ BASE_PATH }}assets/_ext/lineawesome/css/line-awesome.min.css" />
    <link rel="stylesheet" href="{{ BASE_PATH }}assets/fonts/mavenpro/css/all.min.css" />
    <link rel="stylesheet" href="{{ BASE_PATH }}assets/fonts/freehand/css/all.min.css" />
    <!-- Bootstrap -->
    <link rel="stylesheet" href="{{ BASE_PATH }}vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="{{ BASE_PATH }}vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ BASE_PATH }}assets/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ BASE_PATH }}assets/favicon/favicon.svg" />
    <link rel="shortcut icon" href="{{ BASE_PATH }}assets/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ BASE_PATH }}assets/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="{{ SYSTEM_NAME }}" />
    <link rel="manifest" href="{{ BASE_PATH }}assets/favicon/site.webmanifest" />
    <!-- Metas -->
    <meta name="theme-color" content="{{ SYSTEM_COLOR }}" />
    <meta property="og:site_name" content="{{ RP_ORGTYPE }} {{ SERVER_CITY }}" />
    <meta property="og:url" content="{{ own_url }}" />
    <meta property="og:image" content="{{ META_IMAGE_URL }}" />
</head>
<body class="bg-secondary" data-page-amount="1">
    <div class="page-container">
        <div class="page shadow mx-auto mt-2 bg-light">
            <div class="col-4 float-end">
                <img src="{{ BASE_PATH }}assets/img/schrift_fw_schwarz.png" alt="Schriftzug Berufsfeuerwehr {{ SERVER_CITY }}" style="max-width:100%">
                <div class="my-4"></div>
                <p style="font-size:10pt">Datum</p>
                <p style="font-size:12pt;margin-top:-18px">{{ ausstellungsdatum }}</p>
            </div>
            
            <p style="font-size:10pt">{{ RP_ORGTYPE }} {{ SERVER_CITY }}<br>{{ RP_STREET }}<br>{{ RP_ZIP }} {{ SERVER_CITY }}</p>
            
            <p>{{ anrede_text }}<br>
                {{ erhalter }}<br>
                {{ RP_ZIP }} {{ SERVER_CITY }}
            </p>
            
            <div class="my-5"></div>
            
            <p style="font-size:15pt;font-weight:bolder" class="mb-3">Dokument</p>
            
            <div class="letter-content">

TWIG;

    foreach ($data['fields'] as $field) {
        $fieldName = $field['field_name'];

        if ($field['field_type'] === 'richtext' || $field['field_type'] === 'textarea') {
            $template .= "                <div class=\"mb-3\">\n";
            $template .= "                    <strong>{$field['field_label']}:</strong>\n";
            $template .= "                    <div>{{ {$fieldName}|raw }}</div>\n";
            $template .= "                </div>\n";
        } else {
            $template .= "                <p><strong>{$field['field_label']}:</strong> {{ {$fieldName} }}</p>\n";
        }
    }

    $template .= <<<'TWIG'
            </div>
            
            <div class="my-5"></div>
            
            <div class="row signatures">
                <div class="col">
                    <table>
                        <tbody>
                            <tr class="text-center" style="border-bottom: 2px solid #000">
                                <td class="signature">{{ issuer.lastname }}</td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="fw-bold">{{ issuer.fullname }}</span>
                                    {% if issuer.dienstgrad_badge %}
                                        <br><img src="{{ issuer.dienstgrad_badge }}" height="12px" width="auto" alt="Dienstgrad" />
                                    {% endif %}
                                    {{ issuer.dienstgrad_text }}
                                    {% if issuer.zusatz %}
                                        <br>{{ issuer.zusatz }}
                                    {% endif %}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col"></div>
            </div>
            
            <div class="document-styling">
                <div class="strich-1"></div>
                <div class="strich-2"></div>
                <div class="strich-3"></div>
            </div>
        </div>
    </div>
</body>
</html>
TWIG;

    return $template;
}
