<?php
declare(strict_types=1);

final class DocumentApproval extends Model
{
    protected static string $table = 'document_approvals';

    public static function forDocument(int $documentId): array
    {
        return Database::all(
            'SELECT a.*, u.name AS approver_name
               FROM document_approvals a
               LEFT JOIN users u ON u.id = a.approver_user_id
              WHERE a.document_id = ?
              ORDER BY a.step ASC, a.id ASC',
            [$documentId]
        );
    }
}
