
<?php
// admin/users/index.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$page = $_GET['page'] ?? 1;
$records_per_page = 10;

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    
    // Get users with pagination
    $stmt = $user->read($page, $records_per_page);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $total_records = $user->count();
    $total_pages = ceil($total_records / $records_per_page);
    
} catch (Exception $e) {
    error_log("Users index error: " . $e->getMessage());
    $users = [];
    $total_pages = 1;
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-people me-2"></i>User Management
            </h1>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-2"></i>Add New User
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted mb-3"></i>
                    <h5>No Users Found</h5>
                    <p class="text-muted mb-3">Start by creating your first user.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Site</th>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Employment</th>
                                <th>Superior</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user_data): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user_data['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($user_data['emp_number']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($user_data['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user_data['position']); ?></td>
                                <td><?php echo htmlspecialchars($user_data['department']); ?></td>
                                <td><?php echo htmlspecialchars($user_data['site']); ?></td>
                                <td><?php echo htmlspecialchars($user_data['company_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user_data['role'] == 'admin' ? 'danger' : ($user_data['role'] == 'manager' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($user_data['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user_data['is_confirmed']): ?>
                                        <span class="badge bg-success">Confirmed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Probation</span>
                                    <?php endif; ?>


                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($user_data['superior_name'] ?? 'None'); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user_data['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $user_data['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="view.php?id=<?php echo $user_data['id']; ?>" 
                                           class="btn btn-outline-info" title="View User">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $user_data['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($user_data['id'] != $_SESSION['user_id']): ?>
                                        <a href="delete.php?id=<?php echo $user_data['id']; ?>" 
                                           class="btn btn-outline-danger" title="Delete User"
                                           onclick="return confirmDelete('Are you sure you want to delete this user?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="User pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
