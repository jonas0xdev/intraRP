<?php
try {
    // System-Templates einfügen
    $templates = [
        [14, 'Ernennungsurkunde', 'urkunde', 'ernennung.html.twig'],
        [2, 'Beförderungsurkunde', 'urkunde', 'befoerderung.html.twig'],
        [3, 'Entlassungsurkunde', 'urkunde', 'entlassung.html.twig'],
        [5, 'Ausbildungszertifikat', 'zertifikat', 'ausbildung.html.twig'],
        [6, 'Lehrgangszertifikat', 'zertifikat', 'lehrgang.html.twig'],
        [7, 'Lehrgangszertifikat Fachdienste', 'zertifikat', 'fachlehrgang.html.twig'],
        [10, 'Schriftliche Abmahnung', 'schreiben', 'abmahnung.html.twig'],
        [11, 'Vorläufige Dienstenthebung', 'schreiben', 'dienstenthebung.html.twig'],
        [12, 'Dienstentfernung', 'schreiben', 'dienstentfernung.html.twig'],
        [13, 'Außerordentliche Kündigung', 'schreiben', 'kuendigung.html.twig']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO intra_dokument_templates (id, name, category, is_system, template_file) 
        VALUES (?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            category = VALUES(category),
            is_system = 1,
            template_file = VALUES(template_file)
    ");

    foreach ($templates as $template) {
        $stmt->execute($template);
    }

    echo "✓ System-Templates eingefügt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
