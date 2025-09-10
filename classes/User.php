<?php
// classes/User.php

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $emp_number;
    public $email;
    public $emp_email;
    public $position;
    public $direct_superior;
    public $department;
    public $date_joined;
    public $site;
    public $role;
    public $password;
    public $is_active;

   
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Authenticate user
     */
    public function login($email, $password) {
        $query = "SELECT id, name, emp_number, email, emp_email, position, direct_superior, 
                         department, date_joined, site, role, password, is_active
                  FROM " . $this->table_name . " 
                  WHERE (email = :email OR emp_email = :email) AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->emp_number = $row['emp_number'];
                $this->email = $row['email'];
                $this->emp_email = $row['emp_email'];
                $this->position = $row['position'];
                $this->direct_superior = $row['direct_superior'];
                $this->department = $row['department'];
                $this->date_joined = $row['date_joined'];
                $this->site = $row['site'];
                $this->role = $row['role'];
                
                return true;
            }
        }
        return false;
    }

    /**
     * Create new user
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET name = :name, emp_number = :emp_number, email = :email, 
                      emp_email = :emp_email, position = :position, 
                      direct_superior = :direct_superior, department = :department,
                      date_joined = :date_joined, site = :site, role = :role, 
                      password = :password";

        $stmt = $this->conn->prepare($query);

        $this->name = sanitize($this->name);
        $this->emp_number = sanitize($this->emp_number);
        $this->email = sanitize($this->email);
        $this->emp_email = sanitize($this->emp_email);
        $this->position = sanitize($this->position);
        $this->department = sanitize($this->department);
        $this->site = sanitize($this->site);
        $hashed_password = password_hash($this->password, HASH_ALGO);

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':emp_number', $this->emp_number);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':emp_email', $this->emp_email);
        $stmt->bindParam(':position', $this->position);
        $stmt->bindParam(':direct_superior', $this->direct_superior);
        $stmt->bindParam(':department', $this->department);
        $stmt->bindParam(':date_joined', $this->date_joined);
        $stmt->bindParam(':site', $this->site);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':password', $hashed_password);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Count total users
     */
    public function count() {
        $query = "SELECT COUNT(*) as total_rows FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'];
    }
}