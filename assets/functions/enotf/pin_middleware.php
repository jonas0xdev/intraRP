<?php

use App\Auth\Permissions;

if (!isset($_SESSION)) {
    session_start();
}

if (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) {

    // Benutzer mit admin oder edivi.view Berechtigung sind vom Lockscreen ausgenommen
    // Permissions::check() prüft automatisch auch auf 'full_admin'
    $is_exempt_user = Permissions::check(['edivi.view']);

    // Wenn Benutzer ausgenommen ist, Lockscreen-Logik überspringen
    if (!$is_exempt_user) {
        $current_time = time();
        $timeout = 300; // 5 Minuten = 300 Sekunden

        $pin_verified = isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] === true;

        $last_activity = $_SESSION['pin_last_activity'] ?? null;

        $is_timeout = ($last_activity === null || ($current_time - $last_activity) > $timeout);

        if (!$pin_verified || $is_timeout) {
            if (basename($_SERVER['PHP_SELF']) !== 'lockscreen.php') {
                $_SESSION['pin_return_url'] = $_SERVER['REQUEST_URI'];
            }

            $_SESSION['pin_verified'] = false;
            unset($_SESSION['pin_last_activity']);

            header("Location: " . BASE_PATH . "enotf/lockscreen.php");
            exit();
        }

        $_SESSION['pin_last_activity'] = $current_time;
    }
}
