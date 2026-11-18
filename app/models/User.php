<?php
declare(strict_types=1);

final class User extends Model
{
    protected static string $table = 'users';

    public static function search(?string $q = '', ?string $role = ''): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($q !== null && $q !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.nip LIKE ?)';
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($role !== null && $role !== '' && in_array($role, ['super_admin', 'anggota'], true)) {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }

        $sql = 'SELECT u.*, un.name AS unit_name
                  FROM users u
                  LEFT JOIN units un ON un.id = u.unit_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY u.role = "super_admin" DESC, u.name ASC';

        return Database::all($sql, $params);
    }

    public static function emailExists(string $email, int $exceptId = 0): bool
    {
        return (bool)Database::scalar(
            'SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?',
            [$email, $exceptId]
        );
    }

    /** @return array<int,int> daftar id Super Admin aktif */
    public static function superAdminIds(): array
    {
        return array_map(
            'intval',
            array_column(
                Database::all("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1"),
                'id'
            )
        );
    }

    /** Pengguna aktif — pilihan penanggung jawab rencana aksi (M4). */
    public static function activeAll(): array
    {
        return Database::all(
            'SELECT u.id, u.name, u.role, un.name AS unit_name
               FROM users u
               LEFT JOIN units un ON un.id = u.unit_id
              WHERE u.is_active = 1
              ORDER BY u.role = "super_admin" DESC, u.name ASC'
        );
    }
}
