<?php
declare(strict_types=1);

final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    public static function recent(int $limit = 20): array
    {
        $limit = max(1, $limit);
        return Database::all(
            'SELECT a.*, u.name AS user_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              ORDER BY a.id DESC
              LIMIT ' . $limit
        );
    }

    public static function paginate(int $page, int $perPage, string $userId = '', string $action = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($userId !== '') {
            $where[]  = 'a.user_id = ?';
            $params[] = (int)$userId;
        }
        if ($action !== '') {
            $where[]  = 'a.action = ?';
            $params[] = $action;
        }

        $clause  = implode(' AND ', $where);
        $total   = (int)Database::scalar('SELECT COUNT(*) FROM audit_logs a WHERE ' . $clause, $params);
        $perPage = max(1, $perPage);
        $offset  = (max(1, $page) - 1) * $perPage;

        $rows = Database::all(
            'SELECT a.*, u.name AS user_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE ' . $clause . '
              ORDER BY a.id DESC
              LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public static function actions(): array
    {
        return array_column(
            Database::all('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC'),
            'action'
        );
    }
}
