<?php
/**
 * SQLite mysqli-compatibility layer.
 * Lets all existing $conn->query() / prepare() / fetch_assoc() code
 * work unchanged when MySQL is unavailable.
 */

// ── Result object ────────────────────────────────────────────────────────────
class SqliteResult {
    private array $rows;
    public int    $num_rows;

    public function __construct(array $rows) {
        $this->rows     = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array {
        return array_shift($this->rows) ?: null;
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array {
        return $this->rows;
    }
}

// ── Statement object ─────────────────────────────────────────────────────────
class SqliteStmt {
    private PDO          $pdo;
    private PDOStatement $stmt;
    private array        $params   = [];
    public  int          $insert_id = 0;
    public  int          $affected_rows = 0;
    public  string       $error     = '';

    public function __construct(PDO $pdo, string $sql) {
        $this->pdo  = $pdo;
        $this->stmt = $pdo->prepare($sql);
    }

    /** bind_param('ss', $a, $b) — types string is accepted but ignored (PDO handles it) */
    public function bind_param(string $types, mixed &...$params): void {
        $this->params = $params;
    }

    public function execute(): bool {
        try {
            $ok = $this->stmt->execute($this->params);
            if ($ok) {
                $this->insert_id = (int) $this->pdo->lastInsertId();
                $this->affected_rows = $this->stmt->rowCount();
            }
            return $ok;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function get_result(): SqliteResult {
        return new SqliteResult($this->stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function close(): void {}
}

// ── Connection object ────────────────────────────────────────────────────────
class SqliteConn {
    private PDO    $pdo;
    public string  $error          = '';
    public ?string $connect_error  = null;
    public int     $insert_id      = 0;

    public function __construct(string $db_file) {
        try {
            $this->pdo = new PDO('sqlite:' . $db_file);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        } catch (Throwable $e) {
            $this->connect_error = $e->getMessage();
        }
    }

    /**
     * Translate common MySQL-isms so SQLite doesn't choke.
     */
    private function translate(string $sql): string {
        // AUTO_INCREMENT → AUTOINCREMENT  (only inside CREATE TABLE)
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);
        // Remove ENGINE=..., DEFAULT CHARSET=..., COLLATE=..., COMMENT '...'
        $sql = preg_replace('/\bENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\bDEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\bCOLLATE\s*=?\s*[\w_]+/i', '', $sql);
        $sql = preg_replace('/\bCOMMENT\s+\'[^\']*\'/i', '', $sql);
        // Remove MySQL-only ON UPDATE CURRENT_TIMESTAMP
        $sql = preg_replace('/\bON UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);
        // Normalise INT(n) NOT NULL AUTOINCREMENT PRIMARY KEY → INTEGER PRIMARY KEY AUTOINCREMENT
        $sql = preg_replace('/\bINT\(\d+\)(\s+NOT NULL)?\s+AUTOINCREMENT\s+PRIMARY\s+KEY/i',
                            'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        // Remove KEY / INDEX declarations inside CREATE TABLE (SQLite ignores them anyway)
        $sql = preg_replace('/,\s*(UNIQUE\s+KEY|KEY|INDEX)\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
        // Remove UNIQUE KEY ... at end
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
        // MySQL ON DUPLICATE KEY UPDATE → INSERT OR REPLACE (simple case)
        if (preg_match('/ON DUPLICATE KEY UPDATE/i', $sql)) {
            $sql = preg_replace('/^INSERT\s+INTO\b/i', 'INSERT OR REPLACE INTO', $sql);
            $sql = preg_replace('/\s+ON DUPLICATE KEY UPDATE.+$/is', '', $sql);
        }
        // COALESCE is fine in SQLite — no change needed
        return $sql;
    }

    public function query(string $sql): SqliteResult|bool {
        try {
            $sql  = $this->translate($sql);
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) return false;
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $r    = new SqliteResult($rows);
            $this->insert_id = (int) $this->pdo->lastInsertId();
            return $r;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function prepare(string $sql): SqliteStmt {
        $sql = $this->translate($sql);
        return new SqliteStmt($this->pdo, $sql);
    }

    public function set_charset(string $charset): void {
        // SQLite is always UTF-8
    }

    public function real_escape_string(string $str): string {
        // Use PDO quote and strip the outer quotes
        $quoted = $this->pdo->quote($str);
        return substr($quoted, 1, -1);
    }

    public function begin_transaction(): bool {
        try {
            // Make sure auto-commit is off
            $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
            $this->pdo->beginTransaction();
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function commit(): bool {
        try {
            $this->pdo->commit();
            // Re-enable auto-commit
            $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function rollback(): bool {
        try {
            $this->pdo->rollBack();
            // Re-enable auto-commit
            $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function close(): void {}
}
?>
