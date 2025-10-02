<?php
// manager/review/complete.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/email.php';

if (!hasRole('manager') && !hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('pending.php', 'Appraisal ID is required.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get appraisal details and verify it belongs to manager's team
    $query = "SELECT a.*, u.name as employee_name, u.position, u.emp_number, f.title as form_title
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE a.id = ? AND u.direct_superior = ? 
              AND a.status IN ('submitted', 'in_review')";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$appraisal_id, $_SESSION['user_id']]);
    $appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appraisal_data) {
        redirect('pending.php', 'Appraisal not found or not available for review.', 'error');
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('complete.php?id=' . $appraisal_id, 'Invalid request. Please try again.', 'error');
        }
        
        $total_score = floatval($_POST['total_score'] ?? 0);
        $grade = sanitize($_POST['grade'] ?? '');
        $overall_comments = sanitize($_POST['overall_comments'] ?? '');
        
        if (empty($grade) || $total_score <= 0) {
            redirect('complete.php?id=' . $appraisal_id, 'Grade and total score are required.', 'error');
        }
        
        // Update appraisal with final scores and complete status
        // i added mananger comments and manager_submitted_at
        $query = "UPDATE appraisals 
                  SET status = 'completed', 
                      total_score = ?, 
                      grade = ?, 
                      manager_reviewed_at = NOW(),
                      
                      appraiser_id = ?
                  WHERE id = ?";
        
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$total_score, $grade, $_SESSION['user_id'], $appraisal_id])) {
            // Save overall manager comments if provided
            if (!empty($overall_comments)) {
                $comment_query = "INSERT INTO responses (appraisal_id, question_id, manager_comments) 
                                 VALUES (?, 0, ?) 
                                 ON DUPLICATE KEY UPDATE manager_comments = VALUES(manager_comments)";
                $comment_stmt = $db->prepare($comment_query);
                $comment_stmt->execute([$appraisal_id, $overall_comments]);
            }
            
            logActivity($_SESSION['user_id'], 'COMPLETE', 'appraisals', $appraisal_id,
                       ['status' => $appraisal_data['status']], 
                       ['status' => 'completed', 'grade' => $grade, 'total_score' => $total_score],
                       'Completed appraisal review for ' . $appraisal_data['employee_name']);
            
            // Send email notification to employee
            sendReviewCompletionEmails($appraisal_id);

            redirect('pending.php', 'Appraisal completed successfully! The employee has been notified.', 'success');
        } else {
            redirect('complete.php?id=' . $appraisal_id, 'Failed to complete appraisal. Please try again.', 'error');
        }
    }
    
    // Get performance scores to calculate total
    $scores_query = "SELECT r.manager_rating, fq.response_type, fs.section_title
                     FROM responses r
                     JOIN form_questions fq ON r.question_id = fq.id
                     JOIN form_sections fs ON fq.section_id = fs.id
                     WHERE r.appraisal_id = ? AND r.manager_rating IS NOT NULL
                     AND fq.response_type IN ('rating_5', 'rating_10')
                     AND fs.section_title = 'Performance Assessment'
                     ORDER BY fq.question_order";
    
    $stmt = $db->prepare($scores_query);
    $stmt->execute([$appraisal_id]);
    $performance_scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate suggested total score
    $total_points = 0;
    $max_points = 0;
    foreach ($performance_scores as $score) {
        $max_rating = $score['response_type'] === 'rating_5' ? 5 : 10;
        $total_points += $score['manager_rating'];
        $max_points += $max_rating;
    }
    
    $suggested_score = $max_points > 0 ? round(($total_points / $max_points) * 100, 1) : 0;
    $suggested_grade = calculateGrade($suggested_score);
    
} catch (Exception $e) {
    error_log("Complete appraisal error: " . $e->getMessage());
    redirect('pending.php', 'An error occurred. Please try again.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-check-circle me-2"></i>Complete Appraisal Review
                </h1>
                <small class="text-muted">
                    Employee: <?php echo htmlspecialchars($appraisal_data['employee_name']); ?> 
                    (<?php echo htmlspecialchars($appraisal_data['position']); ?>)
                </small>
            </div>
            <a href="review.php?id=<?php echo $appraisal_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Review
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Finalize Appraisal</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Final Step:</strong> Assign the final grade and total score to complete this appraisal review.
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Appraisal Details:</h6>
                        <ul class="list-unstyled">
                            <li><strong>Employee:</strong> <?php echo htmlspecialchars($appraisal_data['employee_name']); ?></li>
                            <li><strong>Position:</strong> <?php echo htmlspecialchars($appraisal_data['position']); ?></li>
                            <li><strong>Period:</strong> <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - <?php echo formatDate($appraisal_data['appraisal_period_to']); ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Performance Summary:</h6>
                        <?php if ($suggested_score > 0): ?>
                        <div class="alert alert-light">
                            <strong>Suggested Score:</strong> <?php echo $suggested_score; ?>%<br>
                            <strong>Suggested Grade:</strong> <span class="badge bg-secondary"><?php echo $suggested_grade; ?></span>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">No performance ratings provided yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="total_score" class="form-label">Total Score (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="total_score" name="total_score" 
                                       min="0" max="100" step="0.1" required
                                       value="<?php echo $suggested_score; ?>">
                                <div class="form-text">Overall performance percentage (0-100)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="grade" class="form-label">Final Grade <span class="text-danger">*</span></label>
                                <select class="form-select" id="grade" name="grade" required>
                                    <option value="">Select grade...</option>
                                    <option value="A" <?php echo $suggested_grade == 'A' ? 'selected' : ''; ?>>A - Excellent (≥85%)</option>
                                    <option value="B+" <?php echo $suggested_grade == 'B+' ? 'selected' : ''; ?>>B+ - Good (75-84%)</option>
                                    <option value="B" <?php echo $suggested_grade == 'B' ? 'selected' : ''; ?>>B - Satisfactory (60-74%)</option>
                                    <option value="B-" <?php echo $suggested_grade == 'B-' ? 'selected' : ''; ?>>B- - Need Improvement (50-59%)</option>
                                    <option value="C" <?php echo $suggested_grade == 'C' ? 'selected' : ''; ?>>C - Below Standard (≤49%)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="overall_comments" class="form-label">Overall Manager Comments</label>
                        <textarea class="form-control" id="overall_comments" name="overall_comments" rows="4"
                                  placeholder="Provide overall feedback on the employee's performance, strengths, areas for improvement, and development recommendations..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Once completed, the appraisal will be finalized and the employee will be notified. This action cannot be undone.
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="confirm_completion" required>
                        <label class="form-check-label" for="confirm_completion">
                            I confirm that I have thoroughly reviewed this appraisal and the scores are accurate
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="review.php?id=<?php echo $appraisal_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Review
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i>Complete Appraisal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-calculate grade based on score
document.getElementById('total_score').addEventListener('input', function() {
    const score = parseFloat(this.value);
    const gradeSelect = document.getElementById('grade');
    
    if (score >= 85) gradeSelect.value = 'A';
    else if (score >= 75) gradeSelect.value = 'B+';
    else if (score >= 60) gradeSelect.value = 'B';
    else if (score >= 50) gradeSelect.value = 'B-';
    else gradeSelect.value = 'C';
});

document.querySelector('form').addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to complete this appraisal? This action cannot be undone.')) {
        e.preventDefault();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>