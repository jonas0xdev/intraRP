<?php

if (!isset($_SESSION)) {
    session_start();
}

if (defined('ENOTF_REQUIRE_USER_AUTH') && ENOTF_REQUIRE_USER_AUTH === true) {
    
    $user_authenticated = isset($_SESSION['userid']) && !empty($_SESSION['userid']);
    
    if (!$user_authenticated) {
        // Get the full request URI for proper redirection after login
        $current_path = $_SERVER['REQUEST_URI'] ?? '';
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Only set redirect URL if not already on login or loggedout pages
        if (strpos($script_name, '/enotf/login.php') === false && 
            strpos($script_name, '/enotf/loggedout.php') === false) {
            $_SESSION['redirect_url'] = BASE_PATH . 'enotf/login.php';
        }
        
        header("Location: " . BASE_PATH . "login.php?redirect=enotf");
        exit();
    }
}
