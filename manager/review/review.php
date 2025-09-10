
<?php
// manager/review/pending.php

require_once __DIR__ . '/../../config/config.php';

if (!hasRole('manager') && !hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $appraisal = new Appraisal($db);
    $pending_stmt = $appraisal->getPendingForManager($_SESSION['user_id']);
    $pending_appraisals = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Pending reviews error: " . $e->getMessage());
    $pending_appraisals = [];
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-clipboard-check me-2"></i>Pending Appraisal Reviews
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($pending_appraisals)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clipboard-check display-1 text-muted mb-3"></i>
                    <h5>No Pending Reviews</h5>
                    <p class="text-muted">All team member appraisals have been reviewed.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Appraisal Period</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_appraisals as $appraisal_data): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($appraisal_data['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($appraisal_data['position']); ?></td>
                                <td>
                                    <small>
                                        <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> -<br>
                                        <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo formatDate($appraisal_data['employee_submitted_at'], 'M d, Y H:i'); ?></small>
                                </td>
                                <td>
                                    <a href="review.php?id=<?php echo $appraisal_data['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-clipboard-check me-2"></i>Start Review
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>