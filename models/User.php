<?php
require_once __DIR__ . '/../connection.php';

class User {
    private $link;
    private $table_name = "users";

    public function __construct() {
        global $link;
        $this->link = $link;
    }

    public function register($name, $email, $phone, $password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO " . $this->table_name . " (name, email, phone, password) VALUES (?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->link, $query);
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $hashed_password);
        
        try {
            return mysqli_stmt_execute($stmt);
        } catch(mysqli_sql_exception $e) {
            return false;
        }
    }

    public function login($email, $password) {
        $query = "SELECT id, name, email, password FROM " . $this->table_name . " WHERE email = ?";
        $stmt = mysqli_prepare($this->link, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }
        return false;
    }

    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = ?";
        $stmt = mysqli_prepare($this->link, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_num_rows($result) > 0;
    }
}
?>