
<?php
// manager/team.php
require_once __DIR__ . '/../config/config.php';

if (!hasRole('manager') && !hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../includes/header.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get team members with their current appraisal status
    $query = "SELECT u.*, 
                     a.id as appraisal_id, 
                     a.status as appraisal_status, 
                     a.grade, 
                     a.total_score,
                     a.appraisal_period_from,
                     a.appraisal_period_to
              FROM users u
              LEFT JOIN appraisals a ON u.id = a.user_id 
                  AND a.id = (SELECT MAX(id) FROM appraisals WHERE user_id = u.id)
              WHERE u.direct_superior = ? AND u.is_active = 1
              ORDER BY u.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Team page error: " . $e->getMessage());
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
                    <p class="text-muted">You don't have any team members assigned to you yet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Current Appraisal</th>
                                <th>Last Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_members as $member): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['emp_number']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($member['position']); ?></td>
                                <td><?php echo htmlspecialchars($member['department']); ?></td>
                                <td>
                                    <?php if ($member['appraisal_status']): ?>
                                    <span class="badge <?php echo getStatusBadgeClass($member['appraisal_status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $member['appraisal_status'])); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php if ($member['appraisal_period_from']): ?>
                                        <?php echo formatDate($member['appraisal_period_from'], 'M Y'); ?> - 
                                        <?php echo formatDate($member['appraisal_period_to'], 'M Y'); ?>
                                        <?php endif; ?>
                                    </small>
                                    <?php else: ?>
                                    <span class="text-muted">No active appraisal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($member['grade']): ?>
                                    <span class="badge bg-secondary <?php echo getGradeColorClass($member['grade']); ?>">
                                        <?php echo $member['grade']; ?>
                                    </span>
                                    <?php if ($member['total_score']): ?>
                                    <br><small class="text-muted"><?php echo $member['total_score']; ?>%</small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($member['appraisal_status'] === 'submitted'): ?>
                                        <a href="review/review.php?id=<?php echo $member['appraisal_id']; ?>" 
                                           class="btn btn-warning" title="Review Appraisal">
                                            <i class="bi bi-clipboard-check"></i> Review
                                        </a>
                                        <?php elseif ($member['appraisal_status'] === 'in_review'): ?>
                                        <a href="review/review.php?id=<?php echo $member['appraisal_id']; ?>" 
                                           class="btn btn-info" title="Continue Review">
                                            <i class="bi bi-pencil"></i> Continue
                                        </a>
                                        <?php elseif ($member['appraisal_id']): ?>
                                        <a href="review/view.php?id=<?php echo $member['appraisal_id']; ?>" 
                                           class="btn btn-outline-primary" title="View Appraisal">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-secondary dropdown-toggle" 
                                                    type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="mailto:<?php echo htmlspecialchars($member['email']); ?>">
                                                    <i class="bi bi-envelope me-2"></i>Send Email
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="viewPerformanceHistory(<?php echo $member['id']; ?>)">
                                                    <i class="bi bi-graph-up me-2"></i>Performance History
                                                </a></li>
                                                <?php if (hasRole('admin')): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="../admin/users/edit.php?id=<?php echo $member['id']; ?>">
                                                    <i class="bi bi-pencil me-2"></i>Edit User
                                                </a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
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

<!-- Performance History Modal -->
<div class="modal fade" id="performanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Performance History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="performanceContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewPerformanceHistory(userId) {
    const modal = new bootstrap.Modal(document.getElementById('performanceModal'));
    const content = document.getElementById('performanceContent');
    
    // Show loading
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    modal.show();
    
    // Fetch performance history
    fetch('<?php echo BASE_URL; ?>/api/performance_history.php?user_id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<h6>' + data.user.name + ' - Performance History</h6>';
                
                if (data.appraisals.length === 0) {
                    html += '<p class="text-muted">No appraisal history found.</p>';
                } else {
                    html += '<div class="table-responsive"><table class="table table-sm">';
                    html += '<thead><tr><th>Period</th><th>Grade</th><th>Score</th><th>Status</th></tr></thead><tbody>';
                    
                    data.appraisals.forEach(function(appraisal) {
                        html += '<tr>';
                        html += '<td>' + appraisal.period + '</td>';
                        html += '<td>' + (appraisal.grade || '-') + '</td>';
                        html += '<td>' + (appraisal.total_score ? appraisal.total_score + '%' : '-') + '</td>';
                        html += '<td><span class="badge bg-' + (appraisal.status === 'completed' ? 'success' : 'secondary') + '">' + appraisal.status + '</span></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                }
                
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Failed to load performance history.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="alert alert-danger">An error occurred while loading data.</div>';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
