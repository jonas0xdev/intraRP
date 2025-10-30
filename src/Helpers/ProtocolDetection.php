<?php

namespace App\Helpers;

class ProtocolDetection
{
    public static function isHttps(): bool
    {
        // Standard HTTPS check
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '') {
            return true;
        }

        // Check for forwarded protocol headers (common in load balancers)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // Check for CloudFlare
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $cfVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($cfVisitor['scheme']) && $cfVisitor['scheme'] === 'https') {
                return true;
            }
        }

        // Check for AWS load balancer
        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        // Check for other common proxy headers
        if (isset($_SERVER['HTTP_X_HTTPS']) && $_SERVER['HTTP_X_HTTPS'] === 'on') {
            return true;
        }

        // Check if running on standard HTTPS port
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }

        // Check for Azure App Service
        if (isset($_SERVER['HTTP_X_ARR_SSL']) && !empty($_SERVER['HTTP_X_ARR_SSL'])) {
            return true;
        }

        // Check for Google Cloud Load Balancer
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) && $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] === 'https') {
            return true;
        }

        return false;
    }

    public static function getProtocol(): string
    {
        return self::isHttps() ? 'https' : 'http';
    }

    public static function getBaseUrl(): string
    {
        $protocol = self::getProtocol();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    public static function getCurrentUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return $baseUrl . $uri;
    }

    public static function buildRedirectUri(string $path): string
    {
        $baseUrl = self::getBaseUrl();
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        // Ensure path starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Remove leading slash from base path if it exists
        $basePath = ltrim($basePath, '/');

        // Construct the full URL
        if (!empty($basePath)) {
            return $baseUrl . '/' . $basePath . $path;
        }

        return $baseUrl . $path;
    }

    public static function configureSecureSession(): void
    {
        if (self::isHttps()) {
            ini_set('session.cookie_samesite', 'None');
            ini_set('session.cookie_secure', '1');
        }
    }
}
