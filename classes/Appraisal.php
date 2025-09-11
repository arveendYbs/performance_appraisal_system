
<?php
class Appraisal {
    private $conn;
    private $table_name = "appraisals";

    public $id;
    public $user_id;
    public $form_id;
    public $appraiser_id;
    public $appraisal_period_from;
    public $appraisal_period_to;
    public $status;
    public $total_score;
    public $performance_score;
    public $grade;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new appraisal
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET user_id = :user_id, form_id = :form_id, 
                      appraisal_period_from = :period_from, 
                      appraisal_period_to = :period_to,
                      status = 'draft'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':form_id', $this->form_id);
        $stmt->bindParam(':period_from', $this->appraisal_period_from);
        $stmt->bindParam(':period_to', $this->appraisal_period_to);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->user_id = $row['user_id'];
            $this->form_id = $row['form_id'];
            $this->appraiser_id = $row['appraiser_id'];
            $this->appraisal_period_from = $row['appraisal_period_from'];
            $this->appraisal_period_to = $row['appraisal_period_to'];
            $this->status = $row['status'];
            $this->total_score = $row['total_score'];
            $this->performance_score = $row['performance_score'];
            $this->grade = $row['grade'];
            return true;
        }
        return false;
    }
    /**
     * Get user's current appraisal
     */
    public function getCurrentAppraisal($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND status IN ('draft', 'submitted', 'in_review')
                  ORDER BY created_at DESC 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->form_id = $row['form_id'];
            $this->appraiser_id = $row['appraiser_id'];
            $this->appraisal_period_from = $row['appraisal_period_from'];
            $this->appraisal_period_to = $row['appraisal_period_to'];
            $this->status = $row['status'];
            $this->total_score = $row['total_score'];
            $this->performance_score = $row['performance_score'];
            $this->grade = $row['grade'];
            return true;
        }
        return false;
    }


    /**
     * Get pending appraisals for manager
     */
    public function getPendingForManager($manager_id) {
        $query = "SELECT a.id, a.user_id, a.appraisal_period_from, a.appraisal_period_to, 
                         a.status, a.employee_submitted_at, u.name, u.emp_number, u.position
                  FROM " . $this->table_name . " a
                  JOIN users u ON a.user_id = u.id
                  WHERE u.direct_superior = :manager_id 
                  AND a.status IN ('submitted', 'in_review')
                  ORDER BY a.employee_submitted_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':manager_id', $manager_id);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Update appraisal status
     */
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status";

        if ($status === 'submitted') {
            $query .= ", employee_submitted_at = NOW()";
        } elseif ($status === 'completed') {
            $query .= ", manager_reviewed_at = NOW()";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Save response
     */
    public function saveResponse($question_id, $employee_response = null, $employee_rating = null, 
                                $employee_comments = null, $manager_response = null, 
                                $manager_rating = null, $manager_comments = null) {
        
        $query = "INSERT INTO responses 
                  (appraisal_id, question_id, employee_response, employee_rating, 
                   employee_comments, manager_response, manager_rating, manager_comments)
                  VALUES (:appraisal_id, :question_id, :employee_response, :employee_rating,
                          :employee_comments, :manager_response, :manager_rating, :manager_comments)
                  ON DUPLICATE KEY UPDATE
                  employee_response = COALESCE(VALUES(employee_response), employee_response),
                  employee_rating = COALESCE(VALUES(employee_rating), employee_rating),
                  employee_comments = COALESCE(VALUES(employee_comments), employee_comments),
                  manager_response = COALESCE(VALUES(manager_response), manager_response),
                  manager_rating = COALESCE(VALUES(manager_rating), manager_rating),
                  manager_comments = COALESCE(VALUES(manager_comments), manager_comments)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':appraisal_id', $this->id);
        $stmt->bindParam(':question_id', $question_id);
        $stmt->bindParam(':employee_response', $employee_response);
        $stmt->bindParam(':employee_rating', $employee_rating);
        $stmt->bindParam(':employee_comments', $employee_comments);
        $stmt->bindParam(':manager_response', $manager_response);
        $stmt->bindParam(':manager_rating', $manager_rating);
        $stmt->bindParam(':manager_comments', $manager_comments);

        return $stmt->execute();
    }

    /**
     * Get appraisal responses
     */
    public function getResponses() {
        $query = "SELECT r.*, fq.question_text, fq.response_type, fs.section_title
                  FROM responses r
                  JOIN form_questions fq ON r.question_id = fq.id
                  JOIN form_sections fs ON fq.section_id = fs.id
                  WHERE r.appraisal_id = :appraisal_id
                  ORDER BY fs.section_order, fq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':appraisal_id', $this->id);
        $stmt->execute();

        return $stmt;
    }
}