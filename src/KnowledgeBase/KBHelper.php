<?php

namespace App\KnowledgeBase;

/**
 * Helper class for Knowledge Base functionality
 */
class KBHelper
{
    /**
     * Get competency level information including colors and labels
     * 
     * @param string|null $level The competency level key
     * @return array|null Competency information or null if not found
     */
    public static function getCompetencyInfo(?string $level): ?array
    {
        if ($level === null) {
            return null;
        }

        $competencies = [
            'basis' => [
                'label' => 'Basis',
                'color' => '#767171',
                'bg' => '#767171',
                'text' => '#ffffff',
                'desc' => 'Basismaßnahmen; durch jedes Rettungsdienstpersonal ausführbar'
            ],
            'rettsan' => [
                'label' => 'RettSan',
                'color' => '#00b0f0',
                'bg' => '#00b0f0',
                'text' => '#ffffff',
                'desc' => 'Durchführung durch RettSan bei Hinzuziehung/Nachsicht eines Arztes'
            ],
            'notsan_2c' => [
                'label' => 'NFS 2c',
                'color' => '#00b050',
                'bg' => '#00b050',
                'text' => '#ffffff',
                'desc' => 'Eigenständige Durchführung durch NotSan im Rahmen § 4 Abs. 2c NotSanG'
            ],
            'notsan_2a' => [
                'label' => 'NFS 2a',
                'color' => '#ffc000',
                'bg' => '#ffc000',
                'text' => '#000000',
                'desc' => 'Eigenverantwortliche Durchführung durch NotSan im Rahmen § 2a NotSanG'
            ],
            'notarzt' => [
                'label' => 'Notarzt',
                'color' => '#c00000',
                'bg' => '#c00000',
                'text' => '#ffffff',
                'desc' => 'Durchführung nur durch Notärzte vorgesehen'
            ]
        ];

        return $competencies[$level] ?? null;
    }

    /**
     * Get entry type label in German
     * 
     * @param string $type The entry type
     * @return string The localized label
     */
    public static function getTypeLabel(string $type): string
    {
        $types = [
            'general' => 'Allgemein',
            'medication' => 'Medikament',
            'measure' => 'Maßnahme'
        ];
        return $types[$type] ?? $type;
    }

    /**
     * Get type badge color
     * 
     * @param string $type The entry type
     * @return string CSS color value
     */
    public static function getTypeColor(string $type): string
    {
        $colors = [
            'general' => '#6c757d',     // secondary gray
            'medication' => '#17a2b8',  // info teal
            'measure' => '#28a745'      // success green
        ];
        return $colors[$type] ?? '#6c757d';
    }

    /**
     * Check if competency label color needs dark text
     * 
     * @param string|null $level The competency level key
     * @return bool True if dark text should be used
     */
    public static function competencyNeedsDarkText(?string $level): bool
    {
        // Only NFS 2a (yellow/orange) needs dark text
        return $level === 'notsan_2a';
    }

    /**
     * Sanitize HTML content for safe output
     * Allows only safe HTML tags used by CKEditor
     * 
     * @param string|null $content The HTML content to sanitize
     * @return string Sanitized HTML
     */
    public static function sanitizeContent(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        // Define allowed tags that CKEditor uses
        $allowedTags = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><table><thead><tbody><tr><th><td><span><div>';
        
        // Strip tags except allowed ones
        $sanitized = strip_tags($content, $allowedTags);
        
        // Remove potentially dangerous attributes
        // This is a basic sanitization - for production, consider using HTMLPurifier
        $sanitized = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $sanitized);
        $sanitized = preg_replace('/\s*javascript\s*:/i', '', $sanitized);
        $sanitized = preg_replace('/\s*data\s*:/i', '', $sanitized);
        $sanitized = preg_replace('/\s*vbscript\s*:/i', '', $sanitized);
        
        return $sanitized;
    }
}
