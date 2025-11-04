<?php

if (!isset($_SESSION)) {
    session_start();
}

if (defined('ENOTF_REQUIRE_USER_AUTH') && ENOTF_REQUIRE_USER_AUTH === true) {
    
    $user_authenticated = isset($_SESSION['userid']) && !empty($_SESSION['userid']);
    
    if (!$user_authenticated) {
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Only set redirect URL if not already on login or loggedout pages
        if (strpos($script_name, '/enotf/login.php') === false && 
            strpos($script_name, '/enotf/loggedout.php') === false) {
            // After authentication, user should go to eNOTF login to enter crew info
            $_SESSION['redirect_url'] = BASE_PATH . 'enotf/login.php';
        }
        
        header("Location: " . BASE_PATH . "login.php?redirect=enotf");
        exit();
    }
}
