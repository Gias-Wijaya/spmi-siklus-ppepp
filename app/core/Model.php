<?php
declare(strict_types=1);

/** Model dasar — CRUD sederhana berbasis PDO. */
abstract class Model
{
    protected static string $table = '';
    protected static string $pk    = 'id';

    public static function find(int $id): ?array
    {
        if ($id <= 0 || static::$table === '') {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$pk . '` = ? LIMIT 1',
            [$id]
        );
    }

    public static function all(?string $orderBy = null): array
    {
        $orderBy = $orderBy ?: static::$pk . ' ASC';
        return Database::all('SELECT * FROM `' . static::$table . '` ORDER BY ' . $orderBy);
    }

    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return Database::update(static::$table, $data, static::$pk . ' = ?', [$id]);
    }

    public static function deleteById(int $id): int
    {
        return Database::stmt(
            'DELETE FROM `' . static::$table . '` WHERE `' . static::$pk . '` = ?',
            [$id]
        )->rowCount();
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        return (int)Database::scalar(
            'SELECT COUNT(*) FROM `' . static::$table . '` WHERE ' . $where,
            $params
        );
    }
}
