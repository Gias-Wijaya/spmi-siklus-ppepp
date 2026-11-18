<?php
declare(strict_types=1);

final class Document extends Model
{
    protected static string $table = 'documents';

    /** Segarkan status 🟢🟡🔴 seluruh dokumen yang berlaku. */
    public static function refreshStatusColors(): void
    {
        $days = (int)config('validity_warning_days', 30);
        Database::stmt(
            "UPDATE documents
                SET status_color = CASE
                        WHEN approval_status = 'archived'        THEN 'red'
                        WHEN expiry_date IS NULL                 THEN 'green'
                        WHEN expiry_date <  CURDATE()            THEN 'red'
                        WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$days} DAY) THEN 'yellow'
                        ELSE 'green'
                    END
              WHERE approval_status IN ('approved', 'archived')"
        );
    }

    /**
     * Pencarian + filter + paginasi.
     * @return array{rows:array,total:int}
     */
    public static function search(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(d.title LIKE ? OR d.doc_number LIKE ? OR d.version LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['category_id'])) {
            $where[]   = 'd.category_id = ?';
            $params[]  = (int)$filters['category_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['green', 'yellow', 'red'], true)) {
            $where[]   = 'd.status_color = ?';
            $params[]  = $filters['status'];
        }
        if (!empty($filters['approval']) && in_array(
            $filters['approval'],
            ['draft', 'submitted', 'approved', 'rejected', 'archived'],
            true
        )) {
            $where[]   = 'd.approval_status = ?';
            $params[]  = $filters['approval'];
        }
        if (!empty($filters['unit_id'])) {
            $where[]   = 'd.unit_id = ?';
            $params[]  = (int)$filters['unit_id'];
        }

        $clause = implode(' AND ', $where);

        $total = (int)Database::scalar(
            'SELECT COUNT(*) FROM documents d WHERE ' . $clause,
            $params
        );

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $rows = Database::all(
            'SELECT d.*, c.name AS category_name, s.name AS standard_name,
                    un.name AS unit_name, usr.name AS owner_name
               FROM documents d
               LEFT JOIN document_categories c ON c.id = d.category_id
               LEFT JOIN standards s            ON s.id = d.standard_id
               LEFT JOIN units un               ON un.id = d.unit_id
               LEFT JOIN users usr              ON usr.id = d.owner_user_id
              WHERE ' . $clause . '
              ORDER BY d.updated_at DESC
              LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public static function detail(int $id): ?array
    {
        return Database::fetch(
            'SELECT d.*, c.name AS category_name, s.name AS standard_name,
                    un.name AS unit_name, usr.name AS owner_name
               FROM documents d
               LEFT JOIN document_categories c ON c.id = d.category_id
               LEFT JOIN standards s            ON s.id = d.standard_id
               LEFT JOIN units un               ON un.id = d.unit_id
               LEFT JOIN users usr              ON usr.id = d.owner_user_id
              WHERE d.id = ?
              LIMIT 1',
            [$id]
        );
    }

    public static function pending(): array
    {
        return Database::all(
            "SELECT d.*, un.name AS unit_name, usr.name AS owner_name
               FROM documents d
               LEFT JOIN units un  ON un.id = d.unit_id
               LEFT JOIN users usr ON usr.id = d.owner_user_id
              WHERE d.approval_status = 'submitted'
              ORDER BY d.submitted_at ASC"
        );
    }

    public static function countsByColor(): array
    {
        $rows  = Database::all(
            "SELECT status_color, COUNT(*) AS total FROM documents
              WHERE approval_status IN ('approved','archived') GROUP BY status_color"
        );
        $count = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($rows as $row) {
            $count[$row['status_color']] = (int)$row['total'];
        }
        return $count;
    }

    /** Boleh diedit: Super Admin atau pemilik dokumen. */
    public static function canEdit(array $doc): bool
    {
        if (Auth::isSuperAdmin()) {
            return true;
        }
        return (int)($doc['owner_user_id'] ?? 0) === (int)(Auth::id() ?? 0);
    }
}
