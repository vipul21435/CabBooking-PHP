<?php
/**
 * The MySQL connection, plus the helpers everything else should use instead of
 * building SQL by hand.
 *
 * Credentials come from the environment. mysqli is put into exception mode, so
 * a bad password fails here with a clear message instead of surfacing later as
 * a fatal on a broken handle.
 */

if (!defined('DB_SERVER')) {
    require_once __DIR__ . '/../initialize.php';
}

class DBConnection
{
    protected $host = DB_SERVER;
    protected $username = DB_USERNAME;
    protected $password = DB_PASSWORD;
    protected $database = DB_NAME;
    protected $port = DB_PORT;

    /** @var mysqli */
    public $conn;

    public function __construct()
    {
        if (isset($this->conn)) {
            return;
        }

        // Make mysqli raise exceptions rather than returning false and letting
        // the caller carry on with a broken handle.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database, $this->port);
            $this->conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            if (APP_DEBUG) {
                exit('Database connection failed: ' . $e->getMessage());
            }
            error_log('Database connection failed: ' . $e->getMessage());
            exit('The site is temporarily unavailable. Please try again shortly.');
        }
    }

    public function __destruct()
    {
        if ($this->conn instanceof mysqli) {
            @$this->conn->close();
        }
    }

    /**
     * Runs a parameterised statement and returns the mysqli_stmt.
     *
     * Types are inferred, so callers pass values and never a format string:
     *   $this->run('SELECT * FROM users WHERE id = ?', [$id]);
     *
     * @param string $sql
     * @param array  $params
     * @return mysqli_stmt
     */
    public function run(string $sql, array $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->conn->error);
        }

        if ($params) {
            $types = '';
            foreach ($params as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    /** Every matching row as an associative array. */
    public function fetchAll(string $sql, array $params = []): array
    {
        $result = $this->run($sql, $params)->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** The first matching row, or null. */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->run($sql, $params)->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return $row ?: null;
    }

    /** The first column of the first row, or null. */
    public function fetchValue(string $sql, array $params = [])
    {
        $row = $this->fetchOne($sql, $params);
        return $row ? reset($row) : null;
    }

    /** How many rows match. */
    public function count(string $sql, array $params = []): int
    {
        return (int) ($this->fetchValue($sql, $params) ?? 0);
    }

    /** Runs a write and reports how many rows it changed. */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->affected_rows;
    }

    public function lastInsertId(): int
    {
        return (int) $this->conn->insert_id;
    }

    /**
     * Turns a stored comma-separated id list into a placeholder list and its
     * parameters, so `id IN (...)` can be bound rather than interpolated.
     *
     * Non-numeric entries are dropped; an empty list yields a condition that
     * matches nothing, which is the safe reading of "no ids".
     *
     * @return array{0: string, 1: array}
     */
    public static function inList($csv): array
    {
        $ids = array_values(array_filter(
            array_map('trim', explode(',', (string) $csv)),
            static fn($value) => $value !== '' && ctype_digit($value)
        ));

        if (!$ids) {
            return ['NULL', []];
        }

        return [implode(', ', array_fill(0, count($ids), '?')), array_map('intval', $ids)];
    }

    /**
     * Builds a `SET a = ?, b = ?` clause from a whitelist of column names.
     *
     * Column names can only come from $allowed. Values only ever travel as
     * bound parameters, so an extra field in the form cannot become part of
     * the statement.
     *
     * @return array{0: string, 1: array} the clause and its parameters
     */
    public static function buildSet(array $input, array $allowed): array
    {
        $fragments = [];
        $params = [];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $input)) {
                continue;
            }
            $fragments[] = "`{$column}` = ?";
            $params[] = $input[$column];
        }

        return [implode(', ', $fragments), $params];
    }
}
