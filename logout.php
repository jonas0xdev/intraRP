<?php
session_start();
session_destroy();

require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';

header('Location: ' . BASE_PATH . 'login.php');
