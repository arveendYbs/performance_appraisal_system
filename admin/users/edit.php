<?php
// admin/users/edit.php
require_once __DIR__ . '/../../config/config.php';

// Check authentication and authorization
if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please login first.', 'warning');
}

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

// Get user ID from URL
$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    redirect('index.php', 'User ID is required.', 'error');
}

$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $user->id = $user_id;
    
    // Get user details
    if (!$user->readOne()) {
        redirect('index.php', 'User not found.', 'error');
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid request. Please try again.';
        } else {
            // Get and sanitize form data
            $name = sanitize($_POST['name'] ?? '');
            $emp_number = sanitize($_POST['emp_number'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $emp_email = sanitize($_POST['emp_email'] ?? '');
            $position = sanitize($_POST['position'] ?? '');
            $direct_superior = !empty($_POST['direct_superior']) ? $_POST['direct_superior'] : null;
            $department = sanitize($_POST['department'] ?? '');
            $date_joined = $_POST['date_joined'] ?? '';
            $site = sanitize($_POST['site'] ?? '');
            $role = $_POST['role'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validation
            if (empty($name) || empty($emp_number) || empty($email) || empty($position) || 
                empty($department) || empty($date_joined) || empty($site) || empty($role)) {
                $error_message = 'All fields are required except company email and direct superior.';
            } elseif (!validateEmail($email)) {
                $error_message = 'Invalid email format.';
            } elseif (!empty($emp_email) && !validateEmail($emp_email)) {
                $error_message = 'Invalid company email format.';
            } elseif ($user->emailExists($email, $user_id)) {
                $error_message = 'Email already exists.';
            } elseif ($user->empNumberExists($emp_number, $user_id)) {
                $error_message = 'Employee number already exists.';
            } elseif (!empty($new_password) && $new_password !== $confirm_password) {
                $error_message = 'Passwords do not match.';
            } elseif (!empty($new_password) && strlen($new_password) < 6) {
                $error_message = 'Password must be at least 6 characters long.';
            } else {
                // Update user details
                $old_values = [
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_active' => $user->is_active
                ];

                $user->name = $name;
                $user->emp_number = $emp_number;
                $user->email = $email;
                $user->emp_email = $emp_email;
                $user->position = $position;
                $user->direct_superior = $direct_superior;
                $user->department = $department;
                $user->date_joined = $date_joined;
                $user->site = $site;
                $user->role = $role;
                $user->is_active = $is_active;

                if ($user->update()) {
                    // Update password if provided
                    if (!empty($new_password)) {
                        $user->updatePassword($new_password);
                    }
                    
                    $new_values = [
                        'name' => $name,
                        'role' => $role,
                        'is_active' => $is_active
                    ];
                    
                    logActivity(
                        $_SESSION['user_id'],
                        'UPDATE',
                        'users',
                        $user_id,
                        $old_values,
                        $new_values,
                        'Updated user: ' . $name
                    );
                    
                    redirect('index.php', 'User updated successfully!', 'success');
                } else {
                    $error_message = 'Failed to update user. Please try again.';
                }
            }
        }
    }
    
    // Get potential supervisors
    $stmt = $db->query("SELECT id, name, position FROM users 
                        WHERE role IN ('admin', 'manager') 
                        AND is_active = 1 
                        AND id != $user_id 
                        ORDER BY name");
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("User edit error: " . $e->getMessage());
    $error_message = 'An error occurred. Please try again.';
}

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-pencil me-2"></i>Edit User
                </h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Users
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           value="<?php echo htmlspecialchars($user->name); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emp_number" class="form-label">Employee Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="emp_number" name="emp_number" required
                                           value="<?php echo htmlspecialchars($user->emp_number); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Email Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Personal Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo htmlspecialchars($user->email); ?>">
                                    <div class="form-text">Used for login</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emp_email" class="form-label">Company Email</label>
                                    <input type="email" class="form-control" id="emp_email" name="emp_email"
                                           value="<?php echo htmlspecialchars($user->emp_email ?? ''); ?>">
                                    <div class="form-text">Optional - can also be used for login</div>
                                </div>
                            </div>
                        </div>

                        <!-- Position and Superior -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="position" name="position" required
                                           value="<?php echo htmlspecialchars($user->position); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="direct_superior" class="form-label">Direct Superior</label>
                                    <select class="form-select" id="direct_superior" name="direct_superior">
                                        <option value="">Select supervisor...</option>
                                        <?php foreach ($supervisors as $supervisor): ?>
                                        <option value="<?php echo $supervisor['id']; ?>" 
                                                <?php echo ($user->direct_superior == $supervisor['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supervisor['name'] . ' - ' . $supervisor['position']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Department and Site -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="department" name="department" required
                                           value="<?php echo htmlspecialchars($user->department); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="site" class="form-label">Site <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="site" name="site" required
                                           value="<?php echo htmlspecialchars($user->site); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Date Joined and Role -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_joined" class="form-label">Date Joined <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_joined" name="date_joined" required
                                           value="<?php echo htmlspecialchars($user->date_joined); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select role...</option>
                                        <option value="admin" <?php echo ($user->role == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="manager" <?php echo ($user->role == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                        <option value="employee" <?php echo ($user->role == 'employee') ? 'selected' : ''; ?>>Employee</option>
                                        <option value="worker" <?php echo ($user->role == 'worker') ? 'selected' : ''; ?>>Worker</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?php echo $user->is_active ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Active (user can login and access the system)
                                </label>
                            </div>
                        </div>

                        <!-- Password Change Section -->
                        <hr>
                        <h6>Change Password</h6>
                        <p class="text-muted small">Leave blank to keep current password</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6">
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" minlength="6">
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);

    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>