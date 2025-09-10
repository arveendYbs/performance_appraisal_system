
<?php
// includes/functions.php
/**
 * Common utility functions
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 */
function hasRole($required_role) {
    if (!isLoggedIn()) return false;
    
    $user_role = $_SESSION['user_role'] ?? '';
    
    // Admin has access to everything
    if ($user_role === 'admin') return true;
    
    // Check specific role
    return $user_role === $required_role;
}

/**
 * Redirect with message
 */
function redirect($url, $message = '', $type = 'info') {
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: " . $url);
    exit();
}

/**
 * Display flash messages
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $alert_class = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        echo '<div class="alert ' . ($alert_class[$type] ?? 'alert-info') . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Calculate performance grade
 */
function calculateGrade($score) {
    if ($score >= 85) return 'A';
    if ($score >= 75) return 'B+';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'B-';
    return 'C';
}

/**
 * Get grade color class
 */
function getGradeColorClass($grade) {
    switch ($grade) {
        case 'A': return 'text-success';
        case 'B+': return 'text-success';
        case 'B': return 'text-primary';
        case 'B-': return 'text-warning';
        case 'C': return 'text-danger';
        default: return 'text-muted';
    }
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'bg-success';
        case 'submitted': return 'bg-info';
        case 'in_review': return 'bg-warning';
        case 'draft': return 'bg-secondary';
        case 'cancelled': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

/**
 * Log user activity
 */
function logActivity($user_id, $action, $table_name, $record_id = null, $old_values = null, $new_values = null, $details = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $audit = new AuditLog($db);
        $audit->log($user_id, $action, $table_name, $record_id, $old_values, $new_values, $details);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        redirect('/auth/login.php', 'Your session has expired. Please login again.', 'warning');
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $filename = pathinfo($original_filename, PATHINFO_FILENAME);
    return $filename . '_' . uniqid() . '.' . $extension;
}
?>