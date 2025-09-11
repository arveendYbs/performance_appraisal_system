
<?php
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
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Authenticate user with email and password
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
                $this->is_active = $row['is_active'];
                
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
                      password = :password, is_active = :is_active";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->name = sanitize($this->name);
        $this->emp_number = sanitize($this->emp_number);
        $this->email = sanitize($this->email);
        $this->emp_email = sanitize($this->emp_email);
        $this->position = sanitize($this->position);
        $this->department = sanitize($this->department);
        $this->site = sanitize($this->site);
        
        // Hash password
        $hashed_password = password_hash($this->password, HASH_ALGO);

        // Bind parameters
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
        $stmt->bindParam(':is_active', $this->is_active);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Read users with pagination
     */
    public function read($page = 1, $records_per_page = RECORDS_PER_PAGE) {
        $from_record_num = ($records_per_page * $page) - $records_per_page;

        $query = "SELECT u.id, u.name, u.emp_number, u.email, u.emp_email, u.position, 
                         u.department, u.site, u.role, u.is_active, u.date_joined,
                         s.name as superior_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN " . $this->table_name . " s ON u.direct_superior = s.id
                  ORDER BY u.name ASC
                  LIMIT :from_record_num, :records_per_page";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
        $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Count total users
     */
    public function count() {
        $query = "SELECT COUNT(*) as total_rows FROM " . $this->table_name . " WHERE is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'];
    }

    /**
     * Get user by ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
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
            $this->is_active = $row['is_active'];
            $this->password = $row['password']; // For password verification
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    /**
     * Update user
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET name = :name, emp_number = :emp_number, email = :email, 
                      emp_email = :emp_email, position = :position,
                      direct_superior = :direct_superior, department = :department,
                      date_joined = :date_joined, site = :site, role = :role,
                      is_active = :is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->name = sanitize($this->name);
        $this->emp_number = sanitize($this->emp_number);
        $this->email = sanitize($this->email);
        $this->emp_email = sanitize($this->emp_email);
        $this->position = sanitize($this->position);
        $this->department = sanitize($this->department);
        $this->site = sanitize($this->site);

        // Bind parameters
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
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Update password
     */
    public function updatePassword($new_password) {
        $query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($new_password, HASH_ALGO);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    /**
     * Delete user (soft delete - set inactive)
     */
    public function delete() {
        $query = "UPDATE " . $this->table_name . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    /**
     * Get subordinates (team members)
     */
    public function getSubordinates() {
        $query = "SELECT id, name, emp_number, position, department, email, is_active 
                  FROM " . $this->table_name . " 
                  WHERE direct_superior = :id AND is_active = 1
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        $query = "SELECT id, name, emp_number, position, department 
                  FROM " . $this->table_name . " 
                  WHERE role = :role AND is_active = 1
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Get potential supervisors (admin and managers)
     */
    public function getPotentialSupervisors() {
        $query = "SELECT id, name, position, department 
                  FROM " . $this->table_name . " 
                  WHERE role IN ('admin', 'manager') AND is_active = 1
                  AND id != :id
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Search users
     */
    public function search($search_term, $page = 1, $records_per_page = RECORDS_PER_PAGE) {
        $from_record_num = ($records_per_page * $page) - $records_per_page;
        
        $query = "SELECT u.id, u.name, u.emp_number, u.email, u.position, 
                         u.department, u.site, u.role, u.is_active,
                         s.name as superior_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN " . $this->table_name . " s ON u.direct_superior = s.id
                  WHERE (u.name LIKE :search OR u.emp_number LIKE :search 
                         OR u.email LIKE :search OR u.position LIKE :search
                         OR u.department LIKE :search)
                  ORDER BY u.name ASC
                  LIMIT :from_record_num, :records_per_page";

        $stmt = $this->conn->prepare($query);
        
        $search_param = "%{$search_term}%";
        $stmt->bindParam(':search', $search_param);
        $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
        $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE (email = :email OR emp_email = :email)";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if employee number exists
     */
    public function empNumberExists($emp_number, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE emp_number = :emp_number";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':emp_number', $emp_number);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Get user statistics
     */
    public function getStats() {
        $stats = [];
        
        // Total users
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total'];
        
        // Users by role
        $query = "SELECT role, COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE is_active = 1 GROUP BY role";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Users by department
        $query = "SELECT department, COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE is_active = 1 GROUP BY department ORDER BY count DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }

    /**
     * Get user's full hierarchy path
     */
    public function getHierarchyPath() {
        $path = [];
        $current_id = $this->id;
        
        while ($current_id) {
            $query = "SELECT id, name, position, direct_superior FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $current_id);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) break;
            
            array_unshift($path, $user);
            $current_id = $user['direct_superior'];
        }
        
        return $path;
    }
    
   
}