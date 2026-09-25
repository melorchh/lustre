<?php
// ============================================================================
// mysqli compatibility shim on top of PDO (PostgreSQL).
//
// The application code was written against PHP's mysqli API (query / prepare /
// bind_param / store_result / bind_result / get_result / fetch_assoc / ...).
// Vercel runs vercel-php, which has no mysqli driver and no MySQL server, so we
// re-implement just the mysqli surface actually used by the codebase on top of
// PDO + pgsql. See the TODO/verification notes in plans/vercel-migration.md.
//
// Design notes:
//   * ERRMODE_SILENT everywhere: errors never throw; statements return false
//     and populate ->error, just like mysqli_report(MYSQLI_REPORT_OFF).
//   * All bound values are sent as text (PDO::PARAM_STR); PostgreSQL casts the
//     unknown/target types implicitly for the "?": placeholders as long as the
//     PHP driver is NOT in emulate-prepare mode (native prepare => server-side
//     type inference). Passing a PHP null binds SQL NULL automatically.
//   * SELECT results are fully buffered at execute()/query() time so that
//     num_rows, store_result(), get_result(), bind_result()+fetch() and
//     data_seek() all behave like mysqli with buffered results.
// ============================================================================

if (class_exists('DbConn', false)) {
    return;
}

class DbResult
{
    public $num_rows = 0;
    private $rows = [];
    private $idx = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc()
    {
        if ($this->idx >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->idx++];
    }

    public function data_seek($n)
    {
        $this->idx = min(max((int)$n, 0), $this->num_rows);
        return true;
    }

    public function close()
    {
    }

    public function free()
    {
    }
}

class DbStmt
{
    /** @var DbConn */
    private $conn;
    private $sql;
    private $values = [];
    public $types = '';
    private $numParams = 0;
    private $isSelect = false;
    private $rows = [];
    private $bindRefs = [];
    private $executed = false;

    public $num_rows = 0;
    public $affected_rows = 0;
    public $insert_id = 0;
    public $error = '';

    public function __construct(DbConn $conn, $sql)
    {
        $this->conn = $conn;
        $this->sql = (string)$sql;
        $cmd = strtolower(ltrim($this->sql));
        $this->isSelect = strpos($cmd, 'select') === 0
            || strpos($cmd, 'show') === 0
            || strpos($cmd, 'with') === 0;
    }

    public function bind_param($types)
    {
        $args = func_get_args();
        array_shift($args);
        $this->types = (string)$types;
        $this->values = array_values($args);
        $this->numParams = count($this->values);
        return true;
    }

    public function bind_result(&$a = null, &$b = null, &$c = null, &$d = null, &$e = null, &$f = null, &$g = null, &$h = null, &$i = null, &$j = null, &$k = null, &$l = null)
    {
        $this->bindRefs = [&$a, &$b, &$c, &$d, &$e, &$f, &$g, &$h, &$i, &$j, &$k, &$l];
        return true;
    }

    public function execute($params = null)
    {
        if ($params !== null) {
            if (is_array($params)) {
                $this->values = array_values($params);
            } else {
                $this->values = [$params];
            }
            $this->numParams = count($this->values);
        }
        $this->num_rows = 0;
        $this->affected_rows = 0;
        $this->insert_id = 0;
        $this->error = '';
        $this->rows = [];
        $this->bindRefs = [];

        try {
            $stmt = $this->conn->getPdo()->prepare($this->sql);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
        if (!$stmt) {
            $info = $this->conn->getPdo()->errorInfo();
            $this->error = isset($info[2]) ? $info[2] : 'statement prepare failed';
            return false;
        }

        foreach ($this->values as $i => $val) {
            $type = ($val === null) ? PDO::PARAM_NULL : PDO::PARAM_STR;
            if (!$stmt->bindValue($i + 1, $val, $type)) {
                $this->error = 'param binding failed';
                $stmt->closeCursor();
                return false;
            }
        }

        try {
            $exec = $stmt->execute();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $stmt->closeCursor();
            return false;
        }
        if ($exec === false) {
            $info = $stmt->errorInfo();
            $this->error = isset($info[2]) ? $info[2] : 'statement execution failed';
            $stmt->closeCursor();
            return false;
        }

        $this->affected_rows = $stmt->rowCount();

        if ($this->isSelect) {
            $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->num_rows = count($this->rows);
        } else {
            $table = $this->insertTable($this->sql);
            if ($table !== null) {
                $this->insert_id = $this->lastInsertId($table);
            }
        }
        $stmt->closeCursor();
        $this->executed = true;
        return true;
    }

    private function insertTable($sql)
    {
        if (preg_match('/^\s*INSERT\s+INTO\s+([A-Za-z0-9_\.]+)/i', $sql, $m)) {
            $t = strtolower(str_replace(['`', '"'], '', $m[1]));
            if (strpos($t, '.') !== false) {
                $t = substr($t, strrpos($t, '.') + 1);
            }
            return $t;
        }
        return null;
    }

    private function lastInsertId($table)
    {
        try {
            return (int)$this->conn->getPdo()->lastInsertId($table . '_id_seq');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function get_result()
    {
        if (!$this->executed || !$this->isSelect) {
            return false;
        }
        return new DbResult($this->rows);
    }

    public function store_result()
    {
        return $this->executed && $this->isSelect;
    }

    public function fetch()
    {
        if (!$this->bindRefs || !$this->rows) {
            return false;
        }
        $row = array_shift($this->rows);
        $vals = array_values($row);
        foreach ($this->bindRefs as $n => &$ref) {
            $ref = ($n < count($vals)) ? $vals[$n] : null;
        }
        unset($ref);
        $this->num_rows = count($this->rows);
        return true;
    }

    public function close()
    {
    }
}

class DbConn
{
    /** @var \PDO */
    private $pdo;
    public $insert_id = 0;
    public $affected_rows = 0;
    public $error = '';
    public $connect_error = '';

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function prepare($sql)
    {
        return new DbStmt($this, $sql);
    }

    public function query($sql)
    {
        $this->error = '';
        $this->affected_rows = 0;
        $this->insert_id = 0;

        try {
            $stmt = $this->pdo->query($sql);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->connect_error = $this->error;
            return false;
        }
        if ($stmt === false) {
            $info = $this->pdo->errorInfo();
            $this->error = isset($info[2]) ? $info[2] : 'query failed';
            $this->connect_error = $this->error;
            return false;
        }

        $cmd = strtolower(ltrim($sql));
        if (strpos($cmd, 'select') === 0 || strpos($cmd, 'show') === 0 || strpos($cmd, 'with') === 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();
            return new DbResult($rows);
        }

        $this->affected_rows = $stmt->rowCount();
        $table = $this->insertTable($sql);
        if ($table !== null) {
            $this->insert_id = $this->lastInsertId($table);
        }
        $stmt->closeCursor();
        return true;
    }

    public function real_escape_string($str)
    {
        $q = $this->pdo->quote((string)$str);
        return $q === false ? (string)$str : substr($q, 1, -1);
    }

    public function escape_string($str)
    {
        return $this->real_escape_string($str);
    }

    public function close()
    {
    }
}