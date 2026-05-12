<?php
require_once 'database.php';

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->createSessionsTable();
    }

    private function createSessionsTable() {
        $query = "CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(32) PRIMARY KEY,
            data LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        mysqli_query($this->conn, $query);
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $id = mysqli_real_escape_string($this->conn, $id);
        $query = "SELECT data FROM sessions WHERE id = '$id'";
        $result = mysqli_query($this->conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['data'];
        }
        return '';
    }

    public function write(string $id, string $data): bool {
        $id = mysqli_real_escape_string($this->conn, $id);
        $data = mysqli_real_escape_string($this->conn, $data);

        $check_query = "SELECT id FROM sessions WHERE id = '$id' LIMIT 1";
        $check_result = mysqli_query($this->conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $query = "UPDATE sessions SET data = '$data', updated_at = NOW() WHERE id = '$id'";
        } else {
            $query = "INSERT INTO sessions (id, data) VALUES ('$id', '$data')";
        }

        return mysqli_query($this->conn, $query) ? true : false;
    }

    public function destroy(string $id): bool {
        $id = mysqli_real_escape_string($this->conn, $id);
        $query = "DELETE FROM sessions WHERE id = '$id'";
        return mysqli_query($this->conn, $query) ? true : false;
    }

    public function gc(int $max_lifetime): int|false {
        $query = "DELETE FROM sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL $max_lifetime SECOND)";
        mysqli_query($this->conn, $query);
        return 0;
    }
}

$handler = new DatabaseSessionHandler($conn);
session_set_save_handler($handler, true);
session_start();
?>
