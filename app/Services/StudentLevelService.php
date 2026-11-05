<?php

namespace App\Services;

use InvalidArgumentException;

class StudentLevelService
{
    public static function getLevelFromSet(
        string $set,
        ?int $currentYear = null,
        int $levelMultiplier = 100,
        int $maxLevel = 400
    ): int {
        $entryYear = static::parseEntryYear($set);
        $currentYear ??= (int) now()->format('Y');
        $yearsIn = $currentYear - $entryYear;

        if ($yearsIn < 1) {
            return 0; // Student hasn't started yet
        }

        $level = $yearsIn * $levelMultiplier;

        return $maxLevel > 0 ? min($level, $maxLevel) : $level;
    }

    public static function getLevelLabel(
        string $set,
        ?int $currentYear = null,
        int $levelMultiplier = 100,
        int $maxLevel = 400
    ): string {
        $level = static::getLevelFromSet($set, $currentYear, $levelMultiplier, $maxLevel);

        return $level > 0 ? "{$level} Level" : 'Not yet enrolled';
    }

    public static function isActive(
        string $set,
        int $programmeDurationYears = 4,
        ?int $currentYear = null
    ): bool {
        $entryYear = static::parseEntryYear($set);
        $currentYear ??= (int) now()->format('Y');
        $yearsIn = $currentYear - $entryYear + 1;

        return $yearsIn >= 1 && $yearsIn <= $programmeDurationYears;
    }

    private static function parseEntryYear(string $set): int
    {
        if (! preg_match('/^(\d{4})[\/\-]\d{4}$/', trim($set), $matches)) {
            throw new InvalidArgumentException(
                "Invalid set format \"{$set}\". Expected format: YYYY/YYYY or YYYY-YYYY."
            );
        }

        return (int) $matches[1];
    }
}
