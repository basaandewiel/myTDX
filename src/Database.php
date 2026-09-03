<?php
declare(strict_types=1);

namespace MyTDX;

class Database
{
    private \PDO $pdo;
    public string $prefix = '';

    public function __construct(string $dsn, string $user = '', string $pass = '', array $opts = [])
    {
        $defaults = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new \PDO($dsn, $user, $pass, $defaults + $opts);
    }

    public function sq(string $query, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        if (count($row) === 1) {
            return reset($row);
        }
        return $row;
    }

    public function sqa(string $query, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function dq(string $query, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    public function ex(string $query, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    public function affected(?\PDOStatement $stmt = null): int
    {
        if ($stmt !== null) {
            return $stmt->rowCount();
        }
        return 0;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }
}
