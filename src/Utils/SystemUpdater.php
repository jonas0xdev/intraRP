<?php

namespace App\Utils;

use Exception;

/**
 * SystemUpdater
 * 
 * Handles system update operations including checking for updates,
 * downloading updates from GitHub releases, and applying them.
 */
class SystemUpdater
{
    private string $versionFile;
    private string $githubRepo = 'EmergencyForge/intraRP';
    private string $githubApiUrl;
    private array $currentVersion;

    public function __construct()
    {
        $this->versionFile = __DIR__ . '/../../system/updates/version.json';
        $this->githubApiUrl = "https://api.github.com/repos/{$this->githubRepo}";
        $this->loadCurrentVersion();
    }

    /**
     * Load current version from version.json
     */
    private function loadCurrentVersion(): void
    {
        if (!file_exists($this->versionFile)) {
            $this->currentVersion = [
                'version' => 'v0.5.0',
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => '0',
                'commit_hash' => 'initial'
            ];
            return;
        }

        $content = file_get_contents($this->versionFile);
        $this->currentVersion = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse version.json: ' . json_last_error_msg());
        }
    }

    /**
     * Get current version information
     */
    public function getCurrentVersion(): array
    {
        return $this->currentVersion;
    }

    /**
     * Check for available updates from GitHub releases
     */
    public function checkForUpdates(): array
    {
        try {
            $latestRelease = $this->fetchLatestRelease();
            
            if (!$latestRelease) {
                return [
                    'available' => false,
                    'message' => 'Keine Updates verfügbar oder Repository nicht erreichbar.'
                ];
            }

            $latestVersion = $latestRelease['tag_name'];
            $currentVersion = $this->currentVersion['version'];

            $isNewer = $this->compareVersions($latestVersion, $currentVersion);

            return [
                'available' => $isNewer,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'release_name' => $latestRelease['name'] ?? $latestVersion,
                'release_notes' => $latestRelease['body'] ?? 'Keine Release-Notizen verfügbar.',
                'published_at' => $latestRelease['published_at'] ?? null,
                'download_url' => $latestRelease['zipball_url'] ?? null,
                'html_url' => $latestRelease['html_url'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => true,
                'message' => 'Fehler beim Prüfen auf Updates: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fetch latest release from GitHub API
     */
    private function fetchLatestRelease(): ?array
    {
        $url = "{$this->githubApiUrl}/releases/latest";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: intraRP-Updater',
                    'Accept: application/vnd.github+json'
                ],
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return null;
        }

        $release = json_decode($response, true);
        
        return $release ?? null;
    }

    /**
     * Compare two version strings
     * Returns true if $version1 is newer than $version2
     */
    private function compareVersions(string $version1, string $version2): bool
    {
        // Remove 'v' prefix if present
        $v1 = ltrim($version1, 'v');
        $v2 = ltrim($version2, 'v');

        return version_compare($v1, $v2, '>');
    }

    /**
     * Download and apply update
     * 
     * @param string $downloadUrl URL to download the update from
     * @return array Result of the update operation
     */
    public function downloadAndApplyUpdate(string $downloadUrl): array
    {
        try {
            // Create temporary directory for update
            $tempDir = sys_get_temp_dir() . '/intrarp_update_' . bin2hex(random_bytes(8));
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception('Konnte temporäres Verzeichnis nicht erstellen.');
            }

            $zipFile = $tempDir . '/update.zip';

            // Download update
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: intraRP-Updater'
                    ],
                    'timeout' => 300
                ]
            ]);

            $updateContent = @file_get_contents($downloadUrl, false, $context);
            
            if ($updateContent === false) {
                throw new Exception('Fehler beim Herunterladen des Updates.');
            }

            file_put_contents($zipFile, $updateContent);

            return [
                'success' => true,
                'message' => 'Update wurde heruntergeladen. Bitte folgen Sie den Anweisungen zur manuellen Installation.',
                'zip_file' => $zipFile,
                'temp_dir' => $tempDir,
                'note' => 'HINWEIS: Automatische Installation ist aus Sicherheitsgründen deaktiviert. Bitte laden Sie das Update manuell herunter und installieren Sie es gemäß der Dokumentation.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Fehler beim Update: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update version.json file
     * 
     * @param array $versionData New version data
     */
    public function updateVersionFile(array $versionData): bool
    {
        try {
            $json = json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            $dir = dirname($this->versionFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($this->versionFile, $json);
            $this->currentVersion = $versionData;
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get all available releases from GitHub
     * 
     * @param int $limit Maximum number of releases to fetch
     */
    public function getAllReleases(int $limit = 10): array
    {
        try {
            $url = "{$this->githubApiUrl}/releases?per_page={$limit}";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: intraRP-Updater',
                        'Accept: application/vnd.github+json'
                    ],
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return [];
            }

            $releases = json_decode($response, true);
            
            return $releases ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Check if current version is a pre-release (beta, alpha, rc)
     */
    public function isPreRelease(): bool
    {
        $version = $this->currentVersion['version'];
        return preg_match('/(alpha|beta|rc|dev)/i', $version) === 1;
    }

    /**
     * Get version age in days
     */
    public function getVersionAge(): int
    {
        if (!isset($this->currentVersion['updated_at'])) {
            return 0;
        }

        $updatedAt = strtotime($this->currentVersion['updated_at']);
        $now = time();
        
        return (int) floor(($now - $updatedAt) / 86400);
    }

    /**
     * Check if update is recommended based on version age
     */
    public function isUpdateRecommended(): bool
    {
        $age = $this->getVersionAge();
        
        // Recommend update if version is older than 90 days
        return $age > 90;
    }

    /**
     * Get update urgency level
     * Returns: 'none', 'low', 'medium', 'high', 'critical'
     */
    public function getUpdateUrgency(): string
    {
        $updateInfo = $this->checkForUpdates();
        
        if (!$updateInfo['available']) {
            return 'none';
        }

        $age = $this->getVersionAge();
        $currentVersion = ltrim($this->currentVersion['version'], 'v');
        $latestVersion = ltrim($updateInfo['latest_version'], 'v');

        // Parse versions
        $currentParts = explode('.', $currentVersion);
        $latestParts = explode('.', $latestVersion);

        // Major version change = high urgency
        if (($latestParts[0] ?? 0) > ($currentParts[0] ?? 0)) {
            return 'high';
        }

        // Minor version change with old version = medium urgency
        if (($latestParts[1] ?? 0) > ($currentParts[1] ?? 0)) {
            return $age > 60 ? 'medium' : 'low';
        }

        // Patch version change
        if (($latestParts[2] ?? 0) > ($currentParts[2] ?? 0)) {
            return $age > 30 ? 'medium' : 'low';
        }

        return 'low';
    }

    /**
     * Get formatted release notes as HTML
     */
    public function getFormattedReleaseNotes(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $output = '';
        $inList = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Headers
            if (preg_match('/^### (.+)$/', $line, $matches)) {
                if ($inList) { $output .= '</ul>'; $inList = false; }
                $output .= '<h6>' . htmlspecialchars($matches[1]) . '</h6>';
            } elseif (preg_match('/^## (.+)$/', $line, $matches)) {
                if ($inList) { $output .= '</ul>'; $inList = false; }
                $output .= '<h5>' . htmlspecialchars($matches[1]) . '</h5>';
            } elseif (preg_match('/^# (.+)$/', $line, $matches)) {
                if ($inList) { $output .= '</ul>'; $inList = false; }
                $output .= '<h4>' . htmlspecialchars($matches[1]) . '</h4>';
            }
            // List items
            elseif (preg_match('/^[\*\-] (.+)$/', $line, $matches)) {
                if (!$inList) { $output .= '<ul>'; $inList = true; }
                $output .= '<li>' . htmlspecialchars($matches[1]) . '</li>';
            }
            // Bold text
            elseif (preg_match('/\*\*(.+?)\*\*/', $line)) {
                if ($inList) { $output .= '</ul>'; $inList = false; }
                $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
                $output .= '<p>' . htmlspecialchars_decode($line) . '</p>';
            }
            // Regular text
            elseif (!empty($line)) {
                if ($inList) { $output .= '</ul>'; $inList = false; }
                $output .= '<p>' . htmlspecialchars($line) . '</p>';
            }
        }

        if ($inList) { $output .= '</ul>'; }
        
        return $output;
    }

    /**
     * Cache update check results to avoid rate limiting
     */
    private function getCachedUpdateCheck(): ?array
    {
        $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';
        
        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = json_decode(file_get_contents($cacheFile), true);
        
        if (!$cacheData || !isset($cacheData['timestamp'])) {
            return null;
        }

        // Cache valid for 1 hour
        if (time() - $cacheData['timestamp'] > 3600) {
            return null;
        }

        // Invalidate cache if current version has changed
        if (isset($cacheData['current_version']) && $cacheData['current_version'] !== $this->currentVersion['version']) {
            return null;
        }

        return $cacheData['data'] ?? null;
    }

    /**
     * Save update check results to cache
     */
    private function cacheUpdateCheck(array $data): void
    {
        $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';
        
        $cacheData = [
            'timestamp' => time(),
            'current_version' => $this->currentVersion['version'],
            'data' => $data
        ];

        @file_put_contents($cacheFile, json_encode($cacheData));
    }

    /**
     * Check for updates with caching support
     */
    public function checkForUpdatesCached(): array
    {
        $cached = $this->getCachedUpdateCheck();
        
        if ($cached !== null) {
            $cached['cached'] = true;
            return $cached;
        }

        $result = $this->checkForUpdates();
        $this->cacheUpdateCheck($result);
        $result['cached'] = false;

        return $result;
    }
}
