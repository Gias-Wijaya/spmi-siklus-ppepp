<?php
declare(strict_types=1);

final class DocumentCategory extends Model
{
    protected static string $table = 'document_categories';

    public static function tree(): array
    {
        $all = Database::all('SELECT * FROM document_categories ORDER BY sort_order ASC, id ASC');
        $byParent = [];
        foreach ($all as $row) {
            $byParent[(int)($row['parent_id'] ?? 0)][] = $row;
        }
        return $byParent;
    }

    /** Daftar datar bertingkat untuk <select> (indentasi berdasarkan level). */
    public static function flat(): array
    {
        $rows = Database::all('SELECT * FROM document_categories ORDER BY sort_order ASC, id ASC');
        foreach ($rows as &$row) {
            $row['label'] = str_repeat('— ', max(0, (int)$row['level'] - 1)) . $row['name'];
        }
        return $rows;
    }
}
