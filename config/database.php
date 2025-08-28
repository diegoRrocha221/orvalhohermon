<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'diego780_orvalho';
    private $username = 'diego780_orvalho';
    private $password = 'Security.4uall!';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            $this->conn->set_charset("utf8");
        } catch(Exception $exception) {
            echo "Erro na conexão: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>