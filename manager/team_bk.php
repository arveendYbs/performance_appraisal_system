<?php
// manager/team.php
require_once __DIR__ . '/../config/config.php';

if (!hasRole('manager') && !hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get team members
    $query = "SELECT id, name, emp_number, position, department, site, email
              FROM users 
              WHERE direct_superior = ? AND is_active = 1
              ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Team view error: " . $e->getMessage());
    $team_members = [];
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-people me-2"></i>My Team
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($team_members)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted mb-3"></i>
                    <h5>No Team Members</h5>
                    <p class="text-muted">You don't have any direct reports assigned to you yet.</p>
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_members as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($member['emp_number']); ?></small><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($member['position']); ?></td>
                                <td><?php echo htmlspecialchars($member['department']); ?></td>
                                <td><?php echo htmlspecialchars($member['site']); ?></td>
                                <td>
                                    <a href="employee_history.php?user_id=<?php echo $member['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-clock-history me-1"></i>View History
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>