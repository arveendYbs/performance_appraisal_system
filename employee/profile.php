
<?php
// employee/profile.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $user->id = $_SESSION['user_id'];
    
    if (!$user->readOne()) {
        redirect('index.php', 'User not found.', 'error');
    }
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid request. Please try again.';
        } else {
            $email = sanitize($_POST['email'] ?? '');
            $emp_email = sanitize($_POST['emp_email'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate email
            if (empty($email) || !validateEmail($email)) {
                $error_message = 'Valid email is required.';
            } elseif (!empty($emp_email) && !validateEmail($emp_email)) {
                $error_message = 'Invalid company email format.';
            } else {
                $updates = [];
                
                // Update emails if changed
                if ($email !== $user->email || $emp_email !== $user->emp_email) {
                    $query = "UPDATE users SET email = ?, emp_email = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$email, $emp_email, $user->id]);
                    
                    $_SESSION['user_email'] = $email;
                    $updates[] = 'email updated';
                }
                
                // Update password if provided
                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        $error_message = 'Current password is required to set new password.';
                    } elseif ($new_password !== $confirm_password) {
                        $error_message = 'New passwords do not match.';
                    } elseif (strlen($new_password) < 6) {
                        $error_message = 'New password must be at least 6 characters.';
                    } elseif (!password_verify($current_password, $user->password)) {
                        $error_message = 'Current password is incorrect.';
                    } else {
                        $hashed_password = password_hash($new_password, HASH_ALGO);
                        $query = "UPDATE users SET password = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$hashed_password, $user->id]);
                        
                        $updates[] = 'password updated';
                    }
                }
                
                if (empty($error_message) && !empty($updates)) {
                    logActivity($_SESSION['user_id'], 'UPDATE', 'users', $user->id, null,
                               ['updates' => $updates], 'Updated profile: ' . implode(', ', $updates));
                    
                    $success_message = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $user->readOne();
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error_message = 'An error occurred. Please try again.';
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-person-circle me-2"></i>My Profile
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card">
            <div class="card-body text-center">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                     style="width: 80px; height: 80px;">
                    <i class="bi bi-person-fill text-white" style="font-size: 2rem;"></i>
                </div>
                <h5><?php echo htmlspecialchars($user->name); ?></h5>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user->position); ?></p>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user->department); ?></p>
                <span class="badge bg-<?php echo $user->role == 'admin' ? 'danger' : ($user->role == 'manager' ? 'warning' : 'info'); ?>">
                    <?php echo ucfirst($user->role); ?>
                </span>
            </div>
        </div>
        
        <!-- Basic Info Card -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
            </div>
            <div class="card-body">
                <p><strong>Employee Number:</strong><br><?php echo htmlspecialchars($user->emp_number); ?></p>
                <p><strong>Department:</strong><br><?php echo htmlspecialchars($user->department); ?></p>
                <p><strong>Site:</strong><br><?php echo htmlspecialchars($user->site); ?></p>
                <p><strong>Date Joined:</strong><br><?php echo formatDate($user->date_joined); ?></p>
                
                <?php if ($user->direct_superior): ?>
                <?php
                try {
                    $supervisor_query = "SELECT name, position FROM users WHERE id = ?";
                    $stmt = $db->prepare($supervisor_query);
                    $stmt->execute([$user->direct_superior]);
                    $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $supervisor = null;
                }
                ?>
                <p><strong>Direct Superior:</strong><br>
                <?php echo $supervisor ? htmlspecialchars($supervisor['name'] . ' - ' . $supervisor['position']) : 'N/A'; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Update Profile Form -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-pencil me-2"></i>Update Profile</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Personal Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo htmlspecialchars($user->email); ?>">
                        <div class="form-text">Used for login</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="emp_email" class="form-label">Company Email</label>
                        <input type="email" class="form-control" id="emp_email" name="emp_email"
                               value="<?php echo htmlspecialchars($user->emp_email ?? ''); ?>">
                        <div class="form-text">Optional - can also be used for login</div>
                    </div>
                    
                    <hr>
                    <h6>Change Password</h6>
                    <p class="text-muted small">Leave blank to keep current password</p>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const currentPassword = document.getElementById('current_password');
    
    function validatePasswords() {
        if (newPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
        
        // Require current password if new password is provided
        if (newPassword.value && !currentPassword.value) {
            currentPassword.setCustomValidity('Current password required');
        } else {
            currentPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    currentPassword.addEventListener('input', validatePasswords);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
