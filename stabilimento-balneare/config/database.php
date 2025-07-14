<?php
// Configurazione database MariaDB
class Database {
    private $host = 'localhost';
    private $database = 'stabilimento_balneare';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct($host = null, $username = null, $password = null) {
        if ($host) $this->host = $host;
        if ($username) $this->username = $username;
        if ($password) $this->password = $password;
    }

    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new Exception("Errore connessione database: " . $e->getMessage());
            }
        }
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function lastInsertId() {
        return $this->connect()->lastInsertId();
    }

    public function beginTransaction() {
        return $this->connect()->beginTransaction();
    }

    public function commit() {
        return $this->connect()->commit();
    }

    public function rollback() {
        return $this->connect()->rollback();
    }
}
?>