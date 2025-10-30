<?php

namespace App\Support;

use PDO;
use PDOException;
use Exception;
use App\Utils\AuditLogger;

class SupportPasswordManager
{

    private PDO $db;
    private AuditLogger $auditLogger;
    private int $max_duration_minutes = 60;
    private int $token_length = 32;

    public function __construct(PDO $db, AuditLogger $auditLogger)
    {
        $this->db = $db;
        $this->auditLogger = $auditLogger;
    }

    public function generateSupportPassword(int $admin_user_id, string $ticket_id, int $duration_minutes = 30, ?string $notes = null): array
    {

        if (!$this->hasFullAdminPermission($admin_user_id)) {
            throw new Exception("Keine Berechtigung: Nur full_admin Benutzer können Support-Passwörter erstellen.");
        }

        $ticket_id = trim($ticket_id);
        if (empty($ticket_id)) {
            throw new Exception("Ticket-ID darf nicht leer sein.");
        }

        $duration_minutes = min($duration_minutes, $this->max_duration_minutes);

        $token = $this->generateSecureToken();
        $hashed_password = password_hash($ticket_id, PASSWORD_ARGON2ID);

        $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_minutes} minutes"));

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO intra_support_passwords 
            (support_token, hashed_password, ticket_id, created_by, expires_at, user_agent, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $token,
            $hashed_password,
            $ticket_id,
            $admin_user_id,
            $expires_at,
            $user_agent,
            $notes
        ]);

        $this->auditLogger->log(
            $admin_user_id,
            'Support-Passwort erstellt',
            "Token: " . substr($token, 0, 8) . "..., Ticket-ID: {$ticket_id}, Gültig bis: {$expires_at}, Notizen: " . ($notes ?? 'keine'),
            'Support-System',
            1
        );

        return [
            'token' => $token,
            'ticket_id' => $ticket_id,
            'expires_at' => $expires_at,
            'expires_in_minutes' => $duration_minutes
        ];
    }

    public function authenticateSupport(string $token, string $password)
    {

        $stmt = $this->db->prepare("
            SELECT id, hashed_password, expires_at, used, created_by, ticket_id
            FROM intra_support_passwords 
            WHERE support_token = ?
        ");
        $stmt->execute([$token]);
        $support_pw = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$support_pw) {
            $this->logFailedSupportLogin($token, 'Invalid token');
            return false;
        }

        if ($support_pw['used']) {
            $this->logFailedSupportLogin($token, 'Token already used');
            return false;
        }

        if (strtotime($support_pw['expires_at']) < time()) {
            $this->logFailedSupportLogin($token, 'Token expired');
            return false;
        }

        if (!password_verify($password, $support_pw['hashed_password'])) {
            $this->logFailedSupportLogin($token, 'Invalid password');
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE intra_support_passwords 
            SET used = TRUE, used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$support_pw['id']]);

        $session_id = $this->createSupportSession($support_pw['id']);

        $this->auditLogger->log(
            $support_pw['created_by'],
            'Support-Zugang verwendet',
            "Token: " . substr($token, 0, 8) . "..., Ticket-ID: {$support_pw['ticket_id']}",
            'Support-System',
            1
        );

        return [
            'session_id' => $session_id,
            'support_password_id' => $support_pw['id'],
            'created_by' => $support_pw['created_by'],
            'expires_at' => $support_pw['expires_at']
        ];
    }

    private function createSupportSession(int $support_password_id): string
    {

        $session_id = bin2hex(random_bytes(32));
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO intra_support_sessions 
            (support_password_id, session_id, user_agent, last_activity)
            VALUES (?, ?, ?, NOW())
        ");

        $stmt->execute([
            $support_password_id,
            $session_id,
            $user_agent
        ]);

        return $session_id;
    }

    public function validateSupportSession(string $session_id): array|false
    {

        $stmt = $this->db->prepare("
            SELECT ss.id, ss.support_password_id, ss.login_time, 
                   sp.expires_at, sp.created_by
            FROM intra_support_sessions ss
            INNER JOIN intra_support_passwords sp ON ss.support_password_id = sp.id
            WHERE ss.session_id = ? 
            AND ss.logout_time IS NULL
            AND sp.expires_at > NOW()
        ");

        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $this->updateSessionActivity($session['id']);
            return $session;
        }

        return false;
    }

    public function endSupportSession(string $session_id): bool
    {

        $stmt = $this->db->prepare("
            UPDATE intra_support_sessions 
            SET logout_time = NOW()
            WHERE session_id = ?
        ");

        return $stmt->execute([$session_id]);
    }

    public function logSupportAction(
        string $session_id,
        string $action_type,
        string $description,
        ?string $table = null,
        ?int $record_id = null,
        $old_value = null,
        $new_value = null
    ): bool {

        $stmt = $this->db->prepare("
            SELECT ss.id, sp.created_by 
            FROM intra_support_sessions ss
            INNER JOIN intra_support_passwords sp ON ss.support_password_id = sp.id
            WHERE ss.session_id = ?
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO intra_support_actions_log 
            (support_session_id, action_type, action_description, affected_table, 
             affected_record_id, old_value, new_value)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $session['id'],
            $action_type,
            $description,
            $table,
            $record_id,
            json_encode($old_value),
            json_encode($new_value)
        ]);

        $details = $description;
        if ($table && $record_id) {
            $details .= " | Tabelle: {$table}, ID: {$record_id}";
        }

        $this->auditLogger->log(
            $session['created_by'],
            'Support: ' . $action_type,
            $details,
            'Support-System',
            1
        );

        if ($result) {
            $this->db->prepare("
                UPDATE intra_support_sessions 
                SET actions_performed = actions_performed + 1 
                WHERE id = ?
            ")->execute([$session['id']]);
        }

        return $result;
    }

    public function getAdminSupportPasswords(int $admin_user_id): array
    {

        $stmt = $this->db->prepare("
            SELECT id, support_token, ticket_id, 
                   created_at, 
                   DATE_FORMAT(expires_at, '%Y-%m-%dT%H:%i:%s') as expires_at,
                   used, used_at, notes,
                   (SELECT COUNT(*) FROM intra_support_sessions WHERE support_password_id = sp.id) as session_count
            FROM intra_support_passwords sp
            WHERE created_by = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");

        $stmt->execute([$admin_user_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            if (!empty($row['expires_at'])) {
                $row['expires_at'] = $row['expires_at'] . 'Z';
            }
        }

        return $results;
    }

    public function getSupportStatistics(int $support_password_id): array
    {

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_sessions,
                SUM(actions_performed) as total_actions,
                MIN(login_time) as first_login,
                MAX(COALESCE(logout_time, last_activity)) as last_activity
            FROM intra_support_sessions
            WHERE support_password_id = ?
        ");

        $stmt->execute([$support_password_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes($this->token_length));
    }

    private function hasFullAdminPermission(int $user_id): bool
    {
        $stmt = $this->db->prepare("
            SELECT full_admin 
            FROM intra_users 
            WHERE id = ? AND full_admin = 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->rowCount() > 0;
    }

    private function updateSessionActivity(int $session_db_id): void
    {
        $stmt = $this->db->prepare("
            UPDATE intra_support_sessions 
            SET last_activity = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$session_db_id]);
    }

    private function logFailedSupportLogin(string $token, string $reason): void
    {
        $this->auditLogger->log(
            0,
            'Support-Login fehlgeschlagen',
            "Token: " . substr($token, 0, 8) . "..., Grund: {$reason}",
            'Support-System',
            1
        );
    }

    public function cleanupExpiredPasswords(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM intra_support_passwords 
            WHERE expires_at < NOW() 
            AND used = FALSE
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
