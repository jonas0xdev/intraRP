<?php
if ($daten['patsex'] === NULL) {
    echo '[1] Rett. Daten: Patienten-Geschlecht ist nicht gesetzt.<br>';
}

if (empty($daten['edatum'])) {
    echo '[1] Rett. Daten: Einsatzdatum ist nicht gesetzt.<br>';
}

if (empty($daten['ezeit'])) {
    echo '[1] Rett. Daten: Einsatzzeit ist nicht gesetzt.<br>';
}

if (empty($daten['eort'])) {
    echo '[1] Rett. Daten: Einsatzort ist nicht gesetzt.<br>';
}

if (empty($daten['awfrei_1']) && empty($daten['awfrei_2']) && empty($daten['awfrei_3'])) {
    echo '[2] Erstbefund: Atemwege ist nicht gesetzt.<br>';
}

if (empty($daten['zyanose_1']) && empty($daten['zyanose_2'])) {
    echo '[2] Erstbefund: Zyanose ist nicht gesetzt.<br>';
}

if ($daten['b_symptome'] === NULL) {
    echo '[2] Erstbefund: Beurteilung Atmung ist nicht gesetzt.<br>';
}

if ($daten['b_auskult'] === NULL) {
    echo '[2] Erstbefund: Auskultation ist nicht gesetzt.<br>';
}

if ($daten['c_kreislauf'] === NULL) {
    echo '[2] Erstbefund: Patientenzustand ist nicht gesetzt.<br>';
}

if ($daten['c_ekg'] === NULL) {
    echo '[2] Erstbefund: EKG ist nicht gesetzt.<br>';
}

if ($daten['d_bewusstsein'] === NULL) {
    echo '[2] Erstbefund: Bewusstseinslage ist nicht gesetzt.<br>';
}

if ($daten['d_ex_1'] === NULL) {
    echo '[2] Erstbefund: Extremitätenbewegung ist nicht gesetzt.<br>';
}

if ($daten['d_pupillenw_1'] === NULL || $daten['d_lichtreakt_1'] === NULL || $daten['d_pupillenw_2'] === NULL || $daten['d_lichtreakt_2'] === NULL) {
    echo '[2] Erstbefund: Pupillen sind nicht gesetzt.<br>';
}

if ($daten['d_gcs_1'] === NULL || $daten['d_gcs_2'] === NULL || $daten['d_gcs_3'] === NULL) {
    echo '[2] Erstbefund: GCS ist nicht gesetzt.<br>';
}

if ($daten['v_muster_k'] === NULL || $daten['v_muster_t'] === NULL || $daten['v_muster_a'] === NULL || $daten['v_muster_al'] === NULL || $daten['v_muster_bl'] === NULL || $daten['v_muster_w'] === NULL) {
    echo '[2] Erstbefund: Verletzungen sind nicht gesetzt.<br>';
}

if (empty($daten['spo2']) || empty($daten['atemfreq']) || empty($daten['rrsys']) || empty($daten['herzfreq']) || empty($daten['bz'])) {
    echo '[2] Erstbefund: Messwerte sind nicht gesetzt.<br>';
}

if ($daten['awsicherung_neu'] === NULL) {
    echo '[4] Maßnahmen: Atemwegssicherung ist nicht gesetzt.<br>';
}

if ($daten['b_beatmung'] === NULL) {
    echo '[4] Maßnahmen: Beatmung ist nicht gesetzt.<br>';
}

if ($daten['c_zugang'] === NULL) {
    echo '[4] Maßnahmen: Zugang ist nicht gesetzt.<br>';
}

if ($daten['medis'] === NULL) {
    echo '[4] Maßnahmen: Medikamente sind nicht gesetzt.<br>';
}

if ($daten['transportziel'] === NULL) {
    echo 'Abschluss: Transportziel ist nicht gesetzt.<br>';
}

if (empty($daten['pfname'])) {
    echo 'Abschluss: Kein Protokollant gesetzt.<br>';
}

if ($daten['prot_by'] === NULL) {
    echo 'Abschluss: Keine Protokollart gesetzt.<br>';
}
