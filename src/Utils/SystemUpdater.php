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
    private string $composerPendingFile;
    private string $githubRepo = 'EmergencyForge/intraRP';
    private string $githubApiUrl;
    private array $currentVersion;

    public function __construct()
    {
        $appRoot = dirname(dirname(__DIR__));
        $this->versionFile = $appRoot . '/system/updates/version.json';
        $this->composerPendingFile = $appRoot . '/system/updates/composer_pending.json';
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
     * 
     * @param bool $includePreRelease If true, include pre-release versions in the check
     */
    public function checkForUpdates(?bool $includePreRelease = null): array
    {
        try {
            // If not explicitly set, use current version's pre-release status
            if ($includePreRelease === null) {
                $includePreRelease = $this->isPreRelease();
            }

            $latestRelease = $this->fetchLatestRelease($includePreRelease);

            if (!$latestRelease) {
                return [
                    'available' => false,
                    'error' => true,
                    'message' => 'Konnte nicht auf GitHub-API zugreifen. Bitte prüfen Sie Ihre Internetverbindung oder versuchen Sie es später erneut (möglicherweise API-Ratenlimit erreicht).'
                ];
            }

            $latestVersion = $latestRelease['tag_name'];
            $currentVersion = $this->currentVersion['version'];

            $isNewer = $this->compareVersions($latestVersion, $currentVersion);
            $isLatestPreRelease = $latestRelease['prerelease'] ?? false;

            return [
                'available' => $isNewer,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'release_name' => $latestRelease['name'] ?? $latestVersion,
                'release_notes' => $latestRelease['body'] ?? 'Keine Release-Notizen verfügbar.',
                'published_at' => $latestRelease['published_at'] ?? null,
                'download_url' => $latestRelease['zipball_url'] ?? null,
                'html_url' => $latestRelease['html_url'] ?? null,
                'is_prerelease' => $isLatestPreRelease
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
     * 
     * @param bool $includePreRelease If true, include pre-release versions
     */
    private function fetchLatestRelease(bool $includePreRelease = false): ?array
    {
        // Always fetch from list to get both stable and pre-release versions
        return $this->fetchLatestReleaseFromList($includePreRelease);
    }

    /**
     * Fetch latest release from releases list
     * 
     * @param bool $includePreRelease If true, returns latest release (can be pre-release or stable).
     *                                 If false, returns latest stable release only.
     */
    private function fetchLatestReleaseFromList(bool $includePreRelease = false): ?array
    {
        $url = "{$this->githubApiUrl}/releases?per_page=20";

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

        $releases = json_decode($response, true);

        if (!is_array($releases) || empty($releases)) {
            return null;
        }

        // Filter out draft releases
        $releases = array_filter($releases, function ($release) {
            return !($release['draft'] ?? false);
        });

        if (empty($releases)) {
            return null;
        }

        // If including pre-releases, return the first (latest) non-draft release
        // This can be either a pre-release or stable version
        if ($includePreRelease) {
            return reset($releases);
        }

        // Otherwise, find the latest stable (non-prerelease) release
        foreach ($releases as $release) {
            if (!($release['prerelease'] ?? false)) {
                return $release;
            }
        }

        // If no stable release found, return the first release
        return reset($releases);
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
     * @param string $newVersion Version being installed
     * @param bool $isPreRelease Whether the new version is a pre-release
     * @return array Result of the update operation
     */
    public function downloadAndApplyUpdate(string $downloadUrl, string $newVersion, bool $isPreRelease = false): array
    {
        try {
            // Security: Validate download URL is from GitHub
            if (!preg_match('#^https://api\.github\.com/repos/' . preg_quote($this->githubRepo, '#') . '/zipball/#', $downloadUrl)) {
                throw new Exception('Ungültige Download-URL. Updates können nur von GitHub heruntergeladen werden.');
            }

            // Security: Validate version format
            // Allow up to 5 version segments (e.g., v0.5.4.3.1) plus optional pre-release suffix
            // Note: {0,4} means 0 to 4 additional segments after the first, totaling 1 to 5 segments
            if (!preg_match('/^v?\d+(\.\d+){0,4}(-[a-zA-Z0-9.-]+)?$/', $newVersion)) {
                throw new Exception('Ungültiges Versionsformat.');
            }

            $appRoot = dirname(dirname(__DIR__));

            // Check write permissions
            if (!is_writable($appRoot)) {
                throw new Exception('Keine Schreibberechtigung für das Anwendungsverzeichnis. Bitte Dateiberechtigungen prüfen.');
            }

            // Create temporary directory for update
            $tempDir = sys_get_temp_dir() . '/intrarp_update_' . bin2hex(random_bytes(8));
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception('Konnte temporäres Verzeichnis nicht erstellen. Bitte Berechtigungen für ' . sys_get_temp_dir() . ' prüfen.');
            }

            $zipFile = $tempDir . '/update.zip';
            $extractDir = $tempDir . '/extracted';

            // Step 1: Download update
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
                throw new Exception('Fehler beim Herunterladen des Updates. Bitte Internetverbindung prüfen.');
            }

            if (!file_put_contents($zipFile, $updateContent)) {
                throw new Exception('Konnte Update-Datei nicht speichern. Bitte Speicherplatz und Berechtigungen prüfen.');
            }

            // Step 2: Extract ZIP
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive PHP-Erweiterung nicht verfügbar. Bitte installieren Sie php-zip.');
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new Exception('Konnte ZIP-Datei nicht öffnen. Datei möglicherweise beschädigt.');
            }

            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                throw new Exception('Konnte ZIP-Datei nicht extrahieren. Bitte Speicherplatz und Berechtigungen prüfen.');
            }
            $zip->close();

            // GitHub zipballs extract to a subdirectory like "EmergencyForge-intraRP-abc123/"
            $extractedDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
            if (empty($extractedDirs)) {
                throw new Exception('Keine extrahierten Verzeichnisse gefunden. ZIP-Struktur ungültig.');
            }
            $sourceDir = $extractedDirs[0];

            // Step 3: Create backup
            $backupDir = $appRoot . '/system/updates/backup_' . date('Y-m-d_H-i-s');
            if (!is_writable(dirname($backupDir))) {
                throw new Exception('Keine Schreibberechtigung für Backup-Verzeichnis: ' . dirname($backupDir));
            }

            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception('Konnte Backup-Verzeichnis nicht erstellen: ' . $backupDir);
            }

            $filesToBackup = ['.htaccess', 'index.php', 'composer.json', 'composer.lock'];
            $dirsToBackup = ['src', 'assets', 'api'];

            foreach ($filesToBackup as $file) {
                if (file_exists($appRoot . '/' . $file)) {
                    if (!copy($appRoot . '/' . $file, $backupDir . '/' . $file)) {
                        throw new Exception('Konnte Datei nicht sichern: ' . $file);
                    }
                }
            }

            foreach ($dirsToBackup as $dir) {
                if (is_dir($appRoot . '/' . $dir)) {
                    try {
                        $this->recursiveCopy($appRoot . '/' . $dir, $backupDir . '/' . $dir);
                    } catch (Exception $e) {
                        throw new Exception('Konnte Verzeichnis nicht sichern: ' . $dir . ' - ' . $e->getMessage());
                    }
                }
            }

            // Backup only version.json from system directory (not the whole system/updates)
            if (!is_dir($backupDir . '/system')) {
                mkdir($backupDir . '/system', 0755, true);
            }
            if (file_exists($appRoot . '/system/updates/version.json')) {
                if (!is_dir($backupDir . '/system/updates')) {
                    mkdir($backupDir . '/system/updates', 0755, true);
                }
                copy($appRoot . '/system/updates/version.json', $backupDir . '/system/updates/version.json');
            }

            // Step 4: Apply update (copy files)
            // Exclude vendor, storage, and system/updates directories
            $excludeDirs = ['vendor', 'storage', 'system/updates'];
            $excludeFiles = ['.env', '.git', '.gitignore'];
            // For these directories, only copy new files (don't overwrite existing customizations)
            $preserveDirs = ['assets/img'];

            try {
                $this->copyUpdateFiles($sourceDir, $appRoot, $excludeDirs, $excludeFiles, $preserveDirs);
            } catch (Exception $e) {
                throw new Exception('Fehler beim Kopieren der Update-Dateien: ' . $e->getMessage() . ' - Backup verfügbar in: ' . $backupDir);
            }

            // Step 5: Update version.json
            if (!$this->updateVersionFile([
                'version' => $newVersion,
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => (int)($this->currentVersion['build_number'] ?? 0) + 1,
                'commit_hash' => 'auto-update',
                'prerelease' => $isPreRelease
            ])) {
                throw new Exception('Konnte version.json nicht aktualisieren. Update möglicherweise unvollständig.');
            }

            // Step 6: Mark that composer needs to run
            // Don't run composer immediately to avoid dependency issues with the current page load
            $composerStatus = [
                'pending' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'version' => $newVersion
            ];

            $dir = dirname($this->composerPendingFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!file_put_contents($this->composerPendingFile, json_encode($composerStatus, JSON_PRETTY_PRINT))) {
                // Non-critical: composer will need manual installation
                error_log('Warning: Could not write composer pending file: ' . $this->composerPendingFile);
            }

            // Step 7: Clear cache
            $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }

            // Clean up temp files
            $this->recursiveDelete($tempDir);

            return [
                'success' => true,
                'message' => 'Update erfolgreich installiert! Composer-Abhängigkeiten werden jetzt aktualisiert...',
                'version' => $newVersion,
                'backup_dir' => $backupDir,
                'composer_pending' => true
            ];
        } catch (Exception $e) {
            // Clean up temp files if they exist
            if (isset($tempDir) && is_dir($tempDir)) {
                try {
                    $this->recursiveDelete($tempDir);
                } catch (Exception $cleanupEx) {
                    // Ignore cleanup errors
                }
            }

            return [
                'success' => false,
                'error' => true,
                'message' => 'Fehler beim Update: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Recursively copy directory
     */
    private function recursiveCopy(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $dirIterator = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $subPath = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $subPath = str_replace('\\', '/', $subPath);
            $destPath = $dest . '/' . $subPath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }

    /**
     * Copy update files while excluding certain directories and files
     * 
     * @param string $source Source directory
     * @param string $dest Destination directory
     * @param array $excludeDirs Directories to completely skip
     * @param array $excludeFiles Files to completely skip
     * @param array $preserveDirs Directories where existing files should be preserved (only copy new files)
     */
    private function copyUpdateFiles(string $source, string $dest, array $excludeDirs, array $excludeFiles, array $preserveDirs = []): void
    {
        $dirIterator = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        $criticalFiles = ['composer.json', 'composer.lock'];
        $importantFiles = ['index.php', '.htaccess']; // Important but not critical - ensure they're overwritten
        $failedCriticalFiles = [];

        foreach ($iterator as $item) {
            // Get relative path from source directory
            $subPath = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $subPath = str_replace('\\', '/', $subPath); // Normalize to forward slashes

            // Check if path contains excluded directory
            $skip = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($subPath, $excludeDir) === 0) {
                    $skip = true;
                    break;
                }
            }

            // Check if file is excluded
            foreach ($excludeFiles as $excludeFile) {
                if ($subPath === $excludeFile || basename($subPath) === $excludeFile) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            $destPath = $dest . '/' . $subPath;

            // Check if path is in a preserve directory
            $inPreserveDir = false;
            foreach ($preserveDirs as $preserveDir) {
                if (strpos($subPath, $preserveDir) === 0) {
                    $inPreserveDir = true;
                    break;
                }
            }

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }

                // If in preserve directory, only copy if file doesn't exist
                if ($inPreserveDir) {
                    if (!file_exists($destPath)) {
                        if (!copy($item, $destPath)) {
                            throw new Exception('Konnte Datei nicht kopieren: ' . $subPath);
                        }
                    }
                } else {
                    // Normal behavior: overwrite existing files
                    // For critical and important files, ensure write permission and verify copy success
                    $isCriticalFile = in_array(basename($subPath), $criticalFiles) && dirname($subPath) === '.';
                    $isImportantFile = in_array(basename($subPath), $importantFiles) && dirname($subPath) === '.';

                    if (($isCriticalFile || $isImportantFile) && file_exists($destPath)) {
                        // Ensure file is writable before attempting to overwrite
                        if (!is_writable($destPath)) {
                            @chmod($destPath, 0644);
                            // If still not writable, log warning but continue
                            if (!is_writable($destPath)) {
                                error_log('Warning: Could not make file writable: ' . $destPath);
                            }
                        }
                    }

                    if (!copy($item, $destPath)) {
                        if ($isCriticalFile) {
                            $failedCriticalFiles[] = $subPath;
                        }
                        throw new Exception('Konnte Datei nicht kopieren: ' . $subPath);
                    }

                    // Verify critical files were actually updated
                    if ($isCriticalFile) {
                        if (filesize($destPath) !== filesize($item)) {
                            $failedCriticalFiles[] = $subPath . ' (Größe stimmt nicht überein)';
                        }
                    }

                    // Log verification for important files (non-critical)
                    if ($isImportantFile && !$isCriticalFile) {
                        if (filesize($destPath) !== filesize($item)) {
                            error_log('Warning: Important file may not have been updated correctly: ' . $subPath);
                        }
                    }
                }
            }
        }

        // Report any critical file failures
        if (!empty($failedCriticalFiles)) {
            throw new Exception('Kritische Dateien konnten nicht aktualisiert werden: ' . implode(', ', $failedCriticalFiles));
        }
    }

    /**
     * Recursively delete directory
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($dir);
    }

    /**
     * Run composer install after system update
     * 
     * @param string $appRoot Application root directory
     * @return array Result containing execution status and output
     */
    private function runComposerInstall(string $appRoot): array
    {
        // Check if composer is available
        $composerPath = $this->findComposerExecutable();

        if (!$composerPath) {
            return [
                'executed' => false,
                'success' => false,
                'error' => true,
                'message' => 'Composer-Executable nicht gefunden.'
            ];
        }

        try {
            // Use composer's --working-dir option for safer execution
            $command = sprintf(
                'timeout 600 %s install --working-dir=%s --no-dev --optimize-autoloader --no-interaction 2>&1',
                escapeshellarg($composerPath),
                escapeshellarg($appRoot)
            );

            // Execute composer command with timeout
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            $outputString = implode("\n", $output);

            // Check if timeout occurred (exit code 124)
            if ($returnCode === 124) {
                return [
                    'executed' => true,
                    'success' => false,
                    'message' => 'Composer-Installation hat zu lange gedauert (Timeout nach 10 Minuten).',
                    'output' => $outputString,
                    'return_code' => $returnCode
                ];
            }

            if ($returnCode === 0) {
                return [
                    'executed' => true,
                    'success' => true,
                    'message' => 'Composer-Abhängigkeiten erfolgreich installiert.',
                    'output' => $outputString
                ];
            } else {
                return [
                    'executed' => true,
                    'success' => false,
                    'error' => true,
                    'message' => 'Composer-Installation fehlgeschlagen.',
                    'output' => $outputString,
                    'return_code' => $returnCode
                ];
            }
        } catch (Exception $e) {
            return [
                'executed' => false,
                'success' => false,
                'error' => true,
                'message' => 'Fehler beim Ausführen von Composer: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Find composer executable on the system
     * 
     * @return string|null Path to composer executable or null if not found
     */
    private function findComposerExecutable(): ?string
    {
        // Try absolute paths first using is_executable for security
        $absolutePaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer'
        ];

        foreach ($absolutePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // For composer in PATH, use which command with strict validation
        $pathNames = ['composer', 'composer.phar'];

        foreach ($pathNames as $name) {
            // Strict validation: only alphanumeric, underscore, hyphen
            // Single dot allowed only for .phar extension at the end
            if (preg_match('/^[a-zA-Z0-9_-]+(\\.phar)?$/', $name)) {
                $output = [];
                $returnCode = 0;
                exec('which ' . escapeshellarg($name) . ' 2>/dev/null', $output, $returnCode);

                if ($returnCode === 0 && !empty($output)) {
                    $execPath = trim($output[0]);

                    // Use realpath to resolve any symlinks and path traversal
                    $realPath = realpath($execPath);

                    // Verify it's a real file, executable, and in safe directories
                    if ($realPath && file_exists($realPath) && is_executable($realPath)) {
                        // Only allow paths in standard bin directories
                        $safePaths = ['/usr/local/bin/', '/usr/bin/', '/bin/'];
                        foreach ($safePaths as $safePath) {
                            if (strpos($realPath, $safePath) === 0) {
                                return $realPath;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if composer installation is pending
     * 
     * @return array Status information
     */
    public function getComposerStatus(): array
    {
        if (!file_exists($this->composerPendingFile)) {
            return [
                'pending' => false,
                'message' => 'Keine ausstehende Composer-Installation.'
            ];
        }

        $content = file_get_contents($this->composerPendingFile);
        $status = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Corrupted file, remove it and return not pending
            if (file_exists($this->composerPendingFile) && !unlink($this->composerPendingFile)) {
                error_log('Warning: Could not remove corrupted composer pending file: ' . $this->composerPendingFile);
            }
            return [
                'pending' => false,
                'error' => true,
                'message' => 'Composer-Status-Datei war beschädigt und wurde entfernt.'
            ];
        }

        return array_merge(['pending' => true], $status ?? []);
    }

    /**
     * Execute pending composer installation
     * 
     * @return array Result of composer execution
     */
    public function executePendingComposerInstall(): array
    {
        if (!file_exists($this->composerPendingFile)) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Keine ausstehende Composer-Installation gefunden.'
            ];
        }

        $appRoot = dirname(dirname(__DIR__));

        // Run composer install
        $result = $this->runComposerInstall($appRoot);

        // Remove pending status file if successful
        if ($result['success']) {
            if (file_exists($this->composerPendingFile) && !unlink($this->composerPendingFile)) {
                error_log('Warning: Could not remove composer pending file after successful install: ' . $this->composerPendingFile);
            }
        }

        return $result;
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
     * First checks the prerelease flag in version.json, then falls back to version string pattern matching
     */
    public function isPreRelease(): bool
    {
        // Check if version.json has an explicit prerelease flag
        if (isset($this->currentVersion['prerelease'])) {
            return (bool)$this->currentVersion['prerelease'];
        }

        // Fall back to pattern matching in version string
        $version = $this->currentVersion['version'];
        return preg_match('/(alpha|beta|rc|dev)/i', $version) === 1;
    }

    /**
     * Check if a specific version string is a pre-release
     * 
     * @param string $version Version string to check
     * @return bool True if version is a pre-release
     */
    public function isVersionPreRelease(string $version): bool
    {
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
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h6>' . htmlspecialchars($matches[1]) . '</h6>';
            } elseif (preg_match('/^## (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h5>' . htmlspecialchars($matches[1]) . '</h5>';
            } elseif (preg_match('/^# (.+)$/', $line, $matches)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<h4>' . htmlspecialchars($matches[1]) . '</h4>';
            }
            // List items
            elseif (preg_match('/^[\*\-] (.+)$/', $line, $matches)) {
                if (!$inList) {
                    $output .= '<ul>';
                    $inList = true;
                }
                $output .= '<li>' . htmlspecialchars($matches[1]) . '</li>';
            }
            // Bold text
            elseif (preg_match('/\*\*(.+?)\*\*/', $line)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
                $output .= '<p>' . htmlspecialchars_decode($line) . '</p>';
            }
            // Regular text
            elseif (!empty($line)) {
                if ($inList) {
                    $output .= '</ul>';
                    $inList = false;
                }
                $output .= '<p>' . htmlspecialchars($line) . '</p>';
            }
        }

        if ($inList) {
            $output .= '</ul>';
        }

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
        // This ensures users see accurate update notifications after local upgrades
        $cachedVersion = $cacheData['current_version'] ?? null;
        $actualVersion = $this->currentVersion['version'] ?? null;

        if ($cachedVersion !== null && $actualVersion !== null && $cachedVersion !== $actualVersion) {
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
            'current_version' => $this->currentVersion['version'] ?? 'unknown',
            'data' => $data
        ];

        @file_put_contents($cacheFile, json_encode($cacheData));
    }

    /**
     * Check for updates with caching support
     * 
     * @param bool $forceRefresh If true, bypass cache and fetch fresh data
     * @param bool $includePreRelease If true, include pre-release versions in the check
     */
    public function checkForUpdatesCached(bool $forceRefresh = false, ?bool $includePreRelease = null): array
    {
        if (!$forceRefresh) {
            $cached = $this->getCachedUpdateCheck();

            if ($cached !== null) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $result = $this->checkForUpdates($includePreRelease);
        $this->cacheUpdateCheck($result);
        $result['cached'] = false;

        return $result;
    }

    /**
     * Clear the update check cache
     */
    public function clearCache(): bool
    {
        $cacheFile = sys_get_temp_dir() . '/intrarp_update_cache.json';

        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }

        return true;
    }
}
