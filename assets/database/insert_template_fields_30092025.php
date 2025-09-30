<?php
try {
    $qualiOptions = json_encode([
        ["value" => "0", "label" => "Brandmeister/-in", "label_m" => "Brandmeister", "label_w" => "Brandmeisterin"],
        ["value" => "1", "label" => "Gruppenführer/-in", "label_m" => "Gruppenführer", "label_w" => "Gruppenführerin"],
        ["value" => "2", "label" => "Zugführer/-in", "label_m" => "Zugführer", "label_w" => "Zugführerin"],
        ["value" => "3", "label" => "Leitstellen-Disponent/-in", "label_m" => "Leitstellen-Disponent", "label_w" => "Leitstellen-Disponentin"],
        ["value" => "4", "label" => "Sonderfahrzeug-Maschinist/-in", "label_m" => "Sonderfahrzeug-Maschinist", "label_w" => "Sonderfahrzeug-Maschinistin"],
        ["value" => "5", "label" => "Helfergrundmodul (SEG)", "label_m" => "Helfergrundmodul (SEG)", "label_w" => "Helfergrundmodul (SEG)"],
        ["value" => "6", "label" => "SEG-Sanitäter/-in", "label_m" => "SEG-Sanitäter", "label_w" => "SEG-Sanitäterin"],
        ["value" => "7", "label" => "Gruppenführer/-in-BevS", "label_m" => "Gruppenführer-BevS", "label_w" => "Gruppenführerin-BevS"],
        ["value" => "8", "label" => "HEMS-TC", "label_m" => "HEMS-TC", "label_w" => "HEMS-TC"],
        ["value" => "9", "label" => "Luftrettungspilot/-in", "label_m" => "Luftrettungspilot", "label_w" => "Luftrettungspilotin"]
    ]);

    // Lösche alte Felder falls vorhanden
    $pdo->exec("DELETE FROM intra_dokument_template_fields WHERE template_id IN (6, 7)");

    // Füge Qualifikations-Felder ein
    $stmt = $pdo->prepare("
        INSERT INTO intra_dokument_template_fields 
        (template_id, field_name, field_label, field_type, field_options, is_required, gender_specific, sort_order)
        VALUES (?, 'erhalter_quali', 'Qualifikation', 'select', ?, 1, 1, 1)
    ");

    $stmt->execute([6, $qualiOptions]); // Lehrgangszertifikat
    $stmt->execute([7, $qualiOptions]); // Fachlehrgang

    // Config in Templates speichern
    $configJson = json_encode([
        "fields" => [
            "erhalter_quali" => [
                "type" => "select",
                "label" => "Qualifikation",
                "options" => json_decode($qualiOptions, true)
            ]
        ]
    ]);

    $pdo->prepare("UPDATE intra_dokument_templates SET config = ? WHERE id IN (6, 7)")
        ->execute([$configJson]);

    echo "✓ Template-Felder eingefügt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
