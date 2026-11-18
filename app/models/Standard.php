<?php
declare(strict_types=1);

/** Standar SPMI (bidang) — dipakai untuk pengelompokan dokumen & instrumen. */
final class Standard extends Model
{
    protected static string $table = 'standards';

    public static function active(): array
    {
        return Database::all('SELECT * FROM standards WHERE is_active = 1 ORDER BY code ASC');
    }
}
