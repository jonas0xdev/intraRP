<?php

if (!isset($_SESSION)) {
    session_start();
}

if (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) {

    $current_time = time();
    $timeout = 300; // 5 Minuten = 300 Sekunden

    $pin_verified = isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] === true;

    $last_activity = $_SESSION['pin_last_activity'] ?? 0;
    $is_timeout = ($current_time - $last_activity) > $timeout;

    if (!$pin_verified || $is_timeout) {
        $_SESSION['pin_return_url'] = $_SERVER['REQUEST_URI'];

        $_SESSION['pin_verified'] = false;

        header("Location: " . BASE_PATH . "enotf/lockscreen.php");
        exit();
    }

    $_SESSION['pin_last_activity'] = $current_time;
}
