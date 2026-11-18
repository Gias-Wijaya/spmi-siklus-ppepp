<?php
declare(strict_types=1);

final class DocumentVersion extends Model
{
    protected static string $table = 'document_versions';

    public static function forDocument(int $documentId): array
    {
        return Database::all(
            'SELECT v.*, su.name AS submitted_by_name, au.name AS approved_by_name
               FROM document_versions v
               LEFT JOIN users su ON su.id = v.submitted_by
               LEFT JOIN users au ON au.id = v.approved_by
              WHERE v.document_id = ?
              ORDER BY v.id DESC',
            [$documentId]
        );
    }
}
