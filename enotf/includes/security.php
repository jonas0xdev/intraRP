<?php

/**
 * Common security configuration for enotf modules
 * This file should be included at the beginning of enotf PHP files
 * before session_start() is called
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\ProtocolDetection;

// Configure secure session cookies if HTTPS is detected
ProtocolDetection::configureSecureSession();
