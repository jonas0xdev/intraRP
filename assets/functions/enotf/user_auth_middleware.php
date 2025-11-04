<?php

if (!isset($_SESSION)) {
    session_start();
}

if (defined('ENOTF_REQUIRE_USER_AUTH') && ENOTF_REQUIRE_USER_AUTH === true) {
    
    $user_authenticated = isset($_SESSION['userid']) && !empty($_SESSION['userid']);
    
    if (!$user_authenticated) {
        if (basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'loggedout.php') {
            $_SESSION['redirect_url'] = BASE_PATH . 'enotf/login.php';
        }
        
        header("Location: " . BASE_PATH . "login.php?redirect=enotf");
        exit();
    }
}
