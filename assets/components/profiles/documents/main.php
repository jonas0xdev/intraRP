<?php

use App\Auth\Permissions;
?>

<table class="table table-striped" id="documentTable">
    <thead>
        <th scope="col">Dokumenten-Typ</th>
        <th scope="col">#</th>
        <th scope="col">Ersteller</th>
        <th scope="col">Am</th>
        <th scope="col"></th>
    </thead>
    <tbody>
        <?php
        $query = "
    SELECT 
        pd.docid, 
        pd.ausstellerid, 
        pd.ausstellungsdatum, 
        pd.type, 
        pd.template_id,
        pd.aussteller_name, 
        u.discord_id AS user_id, 
        u.fullname, 
        u.aktenid,
        t.name as template_name,
        t.category as template_category,
        COALESCE(pd.aussteller_name, u.fullname, 'Unbekannt') as ersteller_name
    FROM intra_mitarbeiter_dokumente pd 
    LEFT JOIN intra_users u ON pd.ausstellerid = u.discord_id 
    LEFT JOIN intra_dokument_templates t ON pd.template_id = t.id
    WHERE pd.profileid = :profileid 
    ORDER BY pd.ausstellungsdatum DESC
";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['profileid' => $openedID]);
        $dokuresult = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $arten = [
            0 => "Ernennungsurkunde",
            1 => "Beförderungsurkunde",
            2 => "Entlassungsurkunde",
            3 => "Ausbildungsvertrag",
            5 => "Ausbildungszertifikat",
            6 => "Lehrgangszertifikat",
            7 => "Lehrgangszertifikat (Fachdienste)",
            10 => "Schriftliche Abmahnung",
            11 => "Vorläufige Dienstenthebung",
            12 => "Dienstentfernung",
            13 => "Außerordentliche Kündigung",
            99 => "Eigenes Dokument" // Fallback für Template-Dokumente
        ];

        foreach ($dokuresult as $doks) {
            $austdatum = date("d.m.Y", strtotime($doks['ausstellungsdatum']));

            // Dokumenttyp bestimmen
            if ($doks['type'] == 99 && !empty($doks['template_name'])) {
                $docart = $doks['template_name'];
            } else {
                $docart = isset($arten[$doks['type']]) ? $arten[$doks['type']] : 'Unbekannt';
            }

            $path = BASE_PATH . "assets/functions/docredir.php?docid=" . $doks['docid'];

            // Badge-Farbe bestimmen
            if ($doks['type'] == 99) {
                // Farbe nach Template-Kategorie
                $bg = match ($doks['template_category']) {
                    'urkunde' => 'text-bg-secondary',
                    'zertifikat' => 'text-bg-dark',
                    'schreiben' => 'text-bg-warning',
                    default => 'text-bg-info'
                };
            } elseif ($doks['type'] <= 3) {
                $bg = "text-bg-secondary";
            } elseif ($doks['type'] == 5 || $doks['type'] == 6 || $doks['type'] == 7) {
                $bg = "text-bg-dark";
            } elseif ($doks['type'] >= 10 && $doks['type'] <= 13) {
                $bg = "text-bg-danger";
            } else {
                $bg = "text-bg-secondary";
            }

            echo "<tr>";
            echo "<td><span class='badge $bg'>" . htmlspecialchars($docart) . "</span></td>";
            echo "<td>" . htmlspecialchars($doks['docid']) .  "</td>";
            echo "<td>" . htmlspecialchars($doks['ersteller_name']) . "</td>";
            echo "<td>" . htmlspecialchars($austdatum) . "</td>";
            echo "<td>";
            echo "<a href='$path' class='btn btn-sm btn-primary' target='_blank'>Ansehen</a>";

            if (Permissions::check('admin')) {
                echo " <a href='" . BASE_PATH . "admin/personal/dokument-delete.php?id={$doks['docid']}&pid=$openedID' class='btn btn-sm btn-danger'><i class='las la-trash'></i></a>";
            }

            echo "</td>";
            echo "</tr>";
        }
        ?>

    </tbody>
</table>