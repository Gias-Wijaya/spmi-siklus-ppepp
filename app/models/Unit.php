<?php
declare(strict_types=1);

final class Unit extends Model
{
    protected static string $table = 'units';

    public static function codeExists(string $code, int $exceptId = 0): bool
    {
        return (bool)Database::scalar(
            'SELECT COUNT(*) FROM units WHERE code = ? AND id <> ?',
            [$code, $exceptId]
        );
    }

    public static function active(): array
    {
        return Database::all('SELECT * FROM units WHERE is_active = 1 ORDER BY name ASC');
    }
}
