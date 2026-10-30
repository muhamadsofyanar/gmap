<?php
namespace App\Services;

class LeadQualification
{
    public static function qualifies(array $lead): bool
    {
        foreach (['phone', 'whatsapp'] as $field) {
            $number = format_whatsapp((string)($lead[$field] ?? ''));
            if (preg_match('/^[1-9][0-9]{7,14}$/', $number)) return true;
        }
        return false;
    }

    public static function filter(array $leads, int $limit = 100): array
    {
        return array_slice(array_values(array_filter($leads, [self::class, 'qualifies'])), 0, max(0, $limit));
    }
}
