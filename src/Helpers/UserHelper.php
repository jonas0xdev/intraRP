<?php

namespace App\Helpers;

use PDO;

class UserHelper
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get user's fullname from linked Mitarbeiter profile based on Discord ID
     * Falls back to intra_users.fullname if no Mitarbeiter profile is linked
     * 
     * @param string $discordId The Discord ID of the user
     * @return string|null The fullname or null if not found
     */
    public function getFullnameByDiscordId(string $discordId): ?string
    {
        // First try to get fullname from Mitarbeiter profile
        $stmt = $this->pdo->prepare("
            SELECT fullname 
            FROM intra_mitarbeiter 
            WHERE discordtag = :discord_id 
            LIMIT 1
        ");
        $stmt->execute(['discord_id' => $discordId]);
        $mitarbeiter = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($mitarbeiter && !empty($mitarbeiter['fullname'])) {
            return $mitarbeiter['fullname'];
        }

        // Fallback to intra_users table
        $stmt = $this->pdo->prepare("
            SELECT fullname 
            FROM intra_users 
            WHERE discord_id = :discord_id 
            LIMIT 1
        ");
        $stmt->execute(['discord_id' => $discordId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user['fullname'] ?? null;
    }

    /**
     * Get user's fullname from session
     * This is a convenience method that uses the Discord ID from session
     * 
     * @return string The fullname, defaults to 'Unknown' if not found
     */
    public function getCurrentUserFullname(): string
    {
        if (!isset($_SESSION['discordtag'])) {
            return 'Unknown';
        }

        $fullname = $this->getFullnameByDiscordId($_SESSION['discordtag']);
        return $fullname ?? 'Unknown';
    }

    /**
     * Get user's fullname for actions/operations
     * Returns 'Admin #ID' if no profile is linked, allowing the user to continue working
     * 
     * @return string The fullname or 'Admin #ID' if not found
     */
    public function getCurrentUserFullnameForAction(): string
    {
        if (!isset($_SESSION['discordtag'])) {
            $userId = $_SESSION['userid'] ?? 'Unknown';
            return 'Admin #' . $userId;
        }

        $fullname = $this->getFullnameByDiscordId($_SESSION['discordtag']);

        if ($fullname === null) {
            $userId = $_SESSION['userid'] ?? 'Unknown';
            return 'Admin #' . $userId;
        }

        return $fullname;
    }

    /**
     * Check if current user has a linked Mitarbeiter profile
     * 
     * @return bool True if user has linked profile, false otherwise
     */
    public function hasLinkedProfile(): bool
    {
        if (!isset($_SESSION['discordtag'])) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM intra_mitarbeiter 
            WHERE discordtag = :discord_id 
            LIMIT 1
        ");
        $stmt->execute(['discord_id' => $_SESSION['discordtag']]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check if the system is new (no Mitarbeiter profiles exist)
     * 
     * @return bool True if system is new, false otherwise
     */
    public function isNewSystem(): bool
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter");
        return $stmt->fetchColumn() === 0;
    }
}
