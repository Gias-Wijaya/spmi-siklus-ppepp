<?php
declare(strict_types=1);

/** Koneksi PDO tunggal + pembantu query. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c   = (array)config('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'],
                (int)$c['port'],
                $c['name'],
                $c['charset'] ?? 'utf8mb4'
            );
            self::$pdo = new PDO($dsn, (string)$c['user'], (string)$c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function stmt(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::stmt($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::stmt($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::stmt($sql, $params)->fetchColumn();
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql     = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES ('
                 . implode(',', array_fill(0, count($columns), '?')) . ')';
        self::stmt($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(',', array_map(static fn (string $c) => '`' . $c . '` = ?', array_keys($data)));
        $statement = self::stmt(
            'UPDATE `' . $table . '` SET ' . $set . ' WHERE ' . $where,
            array_merge(array_values($data), $whereParams)
        );
        return $statement->rowCount();
    }
}
