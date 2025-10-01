<?php
// employee/appraisal/view.php
require_once __DIR__ . '/../../config/config.php';

$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('index.php', 'Appraisal ID is required.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get appraisal details
    $query = "SELECT a.*, u.name as appraiser_name, f.title as form_title
              FROM appraisals a
              LEFT JOIN users u ON a.appraiser_id = u.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE a.id = ? AND a.user_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$appraisal_id, $_SESSION['user_id']]);
    $appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appraisal_data) {
        redirect('index.php', 'Appraisal not found.', 'error');
    }
    
    // Get form structure
    $form = new Form($db);
    $form->id = $appraisal_data['form_id'];
    $form_structure = $form->getFormStructure();
    
    // Get responses
    $appraisal = new Appraisal($db);
    $appraisal->id = $appraisal_id;
    $responses_stmt = $appraisal->getResponses();
    
    $responses = [];
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $responses[$response['question_id']] = $response;
    }
    
    // Calculate performance statistics for both employee and manager
    $performance_stats = [
        'total_rating_questions' => 0,
        'answered_rating_questions' => 0,
        'employee_total_score' => 0,
        'manager_total_score' => 0,
        'employee_average' => 0,
        'manager_average' => 0,
        'employee_percentage' => 0,
        'manager_percentage' => 0
    ];
    
    foreach ($form_structure as $section) {
        foreach ($section['questions'] as $question) {
            if (in_array($question['response_type'], ['rating_5', 'rating_10'])) {
                $performance_stats['total_rating_questions']++;
                $response = $responses[$question['id']] ?? null;
                
                if ($response && $response['employee_rating'] !== null) {
                    $performance_stats['answered_rating_questions']++;
                    
                    // Normalize to 5-point scale for consistent averaging
                    $emp_normalized = $question['response_type'] === 'rating_10' ? 
                        ($response['employee_rating'] ) : $response['employee_rating'];
                    $performance_stats['employee_total_score'] += $emp_normalized;
                    
                    // Add manager score if available
                    if ($response['manager_rating'] !== null) {
                        $mgr_normalized = $question['response_type'] === 'rating_10' ? 
                            ($response['manager_rating'] ) : $response['manager_rating'];
                        $performance_stats['manager_total_score'] += $mgr_normalized;
                    }
                }
            }
        }
    }
    
    if ($performance_stats['answered_rating_questions'] > 0) {
        $performance_stats['employee_average'] = round($performance_stats['employee_total_score'] / $performance_stats['answered_rating_questions'], 1);
        $performance_stats['employee_percentage'] = round(($performance_stats['employee_average'] / 10) * 100);
        
        if ($performance_stats['manager_total_score'] > 0) {
            $performance_stats['manager_average'] = round($performance_stats['manager_total_score'] / $performance_stats['answered_rating_questions'], 1);
            $performance_stats['manager_percentage'] = round(($performance_stats['manager_average'] / 10) * 100);
        }
    }
    
} catch (Exception $e) {
    error_log("View appraisal error: " . $e->getMessage());
    redirect('index.php', 'An error occurred. Please try again.', 'error');
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-eye me-2"></i>My Appraisal
                </h1>
                <small class="text-muted">
                    Period: <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - 
                    <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                </small>
            </div>
            <div>
               
                <?php if ($appraisal_data['status'] === 'draft'): ?>
                <a href="continue.php" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Continue
                </a>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?> me-2">
                    <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                </span>
                <?php if ($appraisal_data['status'] === 'completed'): ?>
                <button class="btn btn-outline-secondary me-2" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Print
                </button>
                <?php endif; ?>
                
                <a href="../" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Appraisal Summary -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Appraisal Summary</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h6>Form Information</h6>
                <p>
                    <strong><?php echo htmlspecialchars($appraisal_data['form_title']); ?></strong><br>
                    <small class="text-muted">Status: <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?></small>
                </p>
            </div>
            <div class="col-md-4">
                <h6>Timeline</h6>
                <p>
                    <strong>Created:</strong> <?php echo formatDate($appraisal_data['created_at'], 'M d, Y H:i'); ?><br>
                    <?php if ($appraisal_data['employee_submitted_at']): ?>
                    <strong>Submitted:</strong> <?php echo formatDate($appraisal_data['employee_submitted_at'], 'M d, Y H:i'); ?>
                    <?php else: ?>
                    <strong>Submitted:</strong> <span class="text-muted">Not submitted</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4">
                <h6>Performance Summary</h6>
                <?php if ($appraisal_data['status'] === 'completed' && $performance_stats['manager_average'] > 0): ?>
                    <!-- Show comparison when completed and manager has provided scores -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Your Average:</small>
                                <div>
                                    <span class="badge bg-<?php 
                                        if ($performance_stats['employee_percentage'] >= 80) echo 'primary';
                                        elseif ($performance_stats['employee_percentage'] >= 60) echo 'warning';
                                        else echo 'danger';
                                    ?> fs-6"><?php echo $performance_stats['employee_average']; ?>/10</span>
                                    <small class="text-muted ms-1">(<?php echo $performance_stats['employee_percentage']; ?>%)</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Manager's Average:</small>
                                <div>
                                    <span class="badge bg-<?php 
                                        if ($performance_stats['manager_percentage'] >= 80) echo 'success';
                                        elseif ($performance_stats['manager_percentage'] >= 60) echo 'warning';
                                        else echo 'danger';
                                    ?> fs-6"><?php echo $performance_stats['manager_average']; ?>/10</span>
                                    <small class="text-muted ms-1">(<?php echo $performance_stats['manager_percentage']; ?>%)</small>
                                </div>
                            </div>
                            <small class="text-muted d-block">
                                Rating Questions: <?php echo $performance_stats['answered_rating_questions']; ?>/<?php echo $performance_stats['total_rating_questions']; ?>
                            </small>
                        </div>
                    </div>
                <?php elseif ($performance_stats['answered_rating_questions'] > 0 && in_array($appraisal_data['status'], ['submitted', 'in_review'])): ?>
                    <!-- Show only employee score before manager review -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Your Average Score:</small>
                        <div>
                            <span class="badge bg-<?php 
                                if ($performance_stats['employee_percentage'] >= 80) echo 'primary';
                                elseif ($performance_stats['employee_percentage'] >= 60) echo 'warning';
                                else echo 'danger';
                            ?> fs-6"><?php echo $performance_stats['employee_average']; ?>/10</span>
                            <small class="text-muted ms-1">(<?php echo $performance_stats['employee_percentage']; ?>%)</small>
                        </div>
                    </div>
                    <small class="text-muted d-block">
                        Rating Questions: <?php echo $performance_stats['answered_rating_questions']; ?>/<?php echo $performance_stats['total_rating_questions']; ?>
                        <br><em>Awaiting manager review...</em>
                    </small>
                <?php elseif ($performance_stats['answered_rating_questions'] > 0): ?>
                    <!-- Show progress for draft -->
                    <p>
                        <small class="text-muted">
                            Rating Questions Answered: <?php echo $performance_stats['answered_rating_questions']; ?>/<?php echo $performance_stats['total_rating_questions']; ?>
                        </small>
                    </p>
                <?php else: ?>
                    <p><small class="text-muted">No performance scores yet</small></p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($appraisal_data['employee_submitted_at']): ?>
        <!-- Submission Details -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-success">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                        <div>
                            <h6 class="mb-1">Appraisal Agreed and Submitted</h6>
                            <small>
                                <strong>Date & Time:</strong> <?php echo formatDate($appraisal_data['employee_submitted_at'], 'l, F j, Y \a\t g:i A'); ?>
                                <?php if ($appraisal_data['appraiser_name']): ?>
                                <br><strong>Reviewer:</strong> <?php echo htmlspecialchars($appraisal_data['appraiser_name']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($appraisal_data['manager_reviewed_at']): ?>
        <div class="row">
            <div class="col-md-6">
                <h6>Review Status</h6>
                <p>
                    <span class="badge bg-success">Completed</span><br>
                    <small class="text-muted">Reviewed: <?php echo formatDate($appraisal_data['manager_reviewed_at'], 'M d, Y H:i'); ?></small>
                </p>
            </div>
            <?php if ($appraisal_data['total_score'] || $appraisal_data['grade']): ?>
            <div class="col-md-6">
                <h6>Final Results</h6>
                <p>
                    <?php if ($appraisal_data['grade']): ?>
                        <span class="badge bg-primary fs-6">Grade: <?php echo $appraisal_data['grade']; ?></span>
                    <?php endif; ?>
                    <?php if ($appraisal_data['total_score']): ?>
                        <br><strong>Overall Score:</strong> <?php echo $appraisal_data['total_score']; ?>%
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Appraisal Content -->
<?php foreach ($form_structure as $section_index => $section): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list-ul me-2"></i>
            Section <?php echo $section_index + 1; ?>: <?php echo htmlspecialchars($section['title']); ?>
        </h5>
        <?php if ($section['description']): ?>
            <small class="text-muted"><?php echo htmlspecialchars($section['description']); ?></small>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php 
        $overall_question = null;
        $overall_response = [];

        foreach ($section['questions'] as $question): 
            $response = $responses[$question['id']] ?? [];

            // For Cultural Values: defer "Overall Comments"
            if ($section['title'] === 'Cultural Values' && stripos($question['text'], 'Overall Comments') !== false) {
                $overall_question = $question;
                $overall_response = $response;
                continue;
            }
        ?>
        
        <?php if ($question['response_type'] === 'display'): ?>
            <!-- Display-only info -->
            <div class="mb-3">
                <strong><?php echo htmlspecialchars($question['text']); ?></strong>
                <?php if (!empty($question['description'])): ?>
                    <div class="mt-2">
                        <?php echo formatDescriptionAsBullets($question['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
             <?php elseif ($section['visible_to'] === 'reviewer'): ?>
            <!-- REVIEWER-ONLY SECTIONS (Pass Probation, etc.) - Show manager responses -->
            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-bold mb-3">
                    <?php echo htmlspecialchars($question['text']); ?>
                    <span class="badge bg-warning ms-2">Manager Assessment</span>
                </h6>

                <?php if ($question['description']): ?>
                    <p class="text-muted small mb-3"><?php echo formatDescriptionAsBullets($question['description']); ?></p>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label text-success fw-bold">Manager's Assessment:</label>
                        
                        <?php if ($question['response_type'] === 'radio' && !empty($response['manager_response'])): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Selected:</strong> <?php echo htmlspecialchars($response['manager_response']); ?>
                            </div>
                        <?php elseif ($question['response_type'] === 'checkbox' && !empty($response['manager_response'])): ?>
                            <div class="mb-2">
                                <?php 
                                $selected_options = explode(', ', $response['manager_response']);
                                foreach ($selected_options as $option): 
                                ?>
                                <span class="badge bg-success me-1 mb-1"><?php echo htmlspecialchars($option); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (!empty($response['manager_response'])): ?>
                            <div class="bg-success bg-opacity-10 p-3 rounded border-start border-success border-3">
                                <?php echo nl2br(htmlspecialchars($response['manager_response'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary">
                                <em>No assessment provided yet</em>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($response['manager_comments'])): ?>
                            <div class="mt-3">
                                <label class="form-label text-muted small">Additional Comments:</label>
                                <div class="bg-light p-2 rounded small">
                                    <?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Normal side-by-side layout -->
            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>
                <?php if (!empty($question['description'])): ?>
                    <div class="mt-2">
                        <?php echo formatDescriptionAsBullets($question['description']); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Employee side -->
                    <div class="col-md-6">
                        <h6 class="text-primary">Your Response:</h6>
                                <?php if (in_array($question['response_type'], ['rating_5', 'rating_10']) && 
                                            isset($response['employee_rating']) && $response['employee_rating'] !== null): ?>
                                        <div class="mb-3 p-2 bg-primary bg-opacity-10 rounded border-start border-primary border-3">
                                            <strong>Your Score: </strong>
                                            <span class="badge bg-primary fs-6"><?php echo $response['employee_rating']; ?></span>
                                            <?php 
                                            $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                                            $percentage = round(($response['employee_rating'] / $max_rating) * 100);
                                            ?>
                                            <span class="text-muted">/ <?php echo $max_rating; ?> (<?php echo $percentage; ?>%)</span>

                                            <div class="small text-muted mt-1">
                                                <?php
                                                if ($question['response_type'] === 'rating_5') {
                                                    $descriptions = [1 => 'Poor', 2 => 'Below Average', 3 => 'Average', 4 => 'Good', 5 => 'Excellent'];
                                                    echo $descriptions[$response['employee_rating']] ?? '';
                                                } else {
                                                    if ($response['employee_rating'] == 0) echo 'Not Applicable';
                                                    elseif ($response['employee_rating'] <= 2) echo 'Poor';
                                                    elseif ($response['employee_rating'] <= 4) echo 'Below Average';
                                                    elseif ($response['employee_rating'] <= 6) echo 'Average';
                                                    elseif ($response['employee_rating'] <= 8) echo 'Good';
                                                    else echo 'Excellent';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                        <div class="bg-light p-3 rounded">
                            <?php if ($question['response_type'] === 'checkbox'): ?>
                                <?php if (!empty($response['employee_response'])): ?>
                                    <?php foreach (explode(', ', $response['employee_response']) as $option): ?>
                                        <span class="badge bg-info me-1 mb-1"><?php echo htmlspecialchars($option); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em class="text-muted">No options selected</em>
                                <?php endif; ?>
                                
                            

                            <?php elseif ($question['response_type'] === 'attachment'): ?>
                                <?php if (!empty($response['employee_attachment'])): ?>
                                    <div class="mb-2">
                                        <i class="bi bi-paperclip me-2"></i>
                                        <strong>Attachment:</strong>
                                        <a href="download.php?file=<?php echo urlencode($response['employee_attachment']); ?>&type=employee" 
                                           class="text-primary ms-2" target="_blank">
                                            <?php echo htmlspecialchars(basename($response['employee_attachment'])); ?>
                                            <i class="bi bi-download ms-1"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($response['employee_comments'])): ?>
                                    <small><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></small>
                                <?php else: ?>
                                    <em class="text-muted">No attachment provided</em>
                                <?php endif; ?>

                            <?php else: ?>
                                <?php if (!empty($response['employee_response']) || !empty($response['employee_comments'])): ?>
                                    <?php if (!empty($response['employee_response'])): ?>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($response['employee_response'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($response['employee_comments'])): ?>
                                        <small><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em class="text-muted">No response provided</em>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Manager side -->
                    <div class="col-md-6">
                        <h6 class="text-success">Manager's Assessment:</h6>

                            <?php if (in_array($question['response_type'], ['rating_5', 'rating_10']) && 
                                    isset($response['manager_rating']) && $response['manager_rating'] !== null): ?>
                                <div class="mb-3 p-2 bg-success bg-opacity-10 rounded border-start border-success border-3">
                                    <strong>Manager Score: </strong>
                                    <span class="badge bg-success fs-6"><?php echo $response['manager_rating']; ?></span>
                                    <?php 
                                    $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                                    $percentage = round(($response['manager_rating'] / $max_rating) * 100);
                                    ?>
                                    <span class="text-muted">/ <?php echo $max_rating; ?> (<?php echo $percentage; ?>%)</span>

                                    <div class="small text-muted mt-1">
                                        <?php
                                        if ($question['response_type'] === 'rating_5') {
                                            $descriptions = [1 => 'Poor', 2 => 'Below Average', 3 => 'Average', 4 => 'Good', 5 => 'Excellent'];
                                            echo $descriptions[$response['manager_rating']] ?? '';
                                        } else {
                                            if ($response['manager_rating'] == 0) echo 'Not Applicable';
                                            elseif ($response['manager_rating'] <= 2) echo 'Poor';
                                            elseif ($response['manager_rating'] <= 4) echo 'Below Average';
                                            elseif ($response['manager_rating'] <= 6) echo 'Average';
                                            elseif ($response['manager_rating'] <= 8) echo 'Good';
                                            else echo 'Excellent';
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>


                            
                            <!-- Manager Comments Box (balanced with employee side) -->
                            <div class="bg-light p-3 rounded">
                                <?php if (!empty($response['manager_comments']) || !empty($response['manager_response'])): ?>
                                    <?php if (!empty($response['manager_response'])): ?>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($response['manager_response'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($response['manager_comments'])): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em class="text-muted">No manager feedback yet</em>
                                <?php endif; ?>
                            </div>
                        
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Cultural Values Overall Comments (side-by-side) -->
        <?php if ($section['title'] === 'Cultural Values' && $overall_question): ?>
        <div class="mt-4">
            <div class="row">
                <div class="col-md-6">
                    <h6><strong>Your Overall Comments on Cultural Values</strong></h6>
                    <div class="bg-light p-3 rounded">
                        <?php if (!empty($overall_response['employee_response'])): ?>
                            <?php echo nl2br(htmlspecialchars($overall_response['employee_response'])); ?>
                        <?php else: ?>
                            <em class="text-muted">No overall comments provided</em>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6><strong>Manager's Feedback on Cultural Values</strong></h6>
                    <?php if (!empty($overall_response['manager_comments'])): ?>
                        <div class="bg-success bg-opacity-10 p-3 rounded border-start border-success border-3">
                            <?php echo nl2br(htmlspecialchars($overall_response['manager_comments'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-light p-3 rounded">
                            <em class="text-muted">No manager feedback yet</em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>


<!-- Action Buttons -->
<?php if ($appraisal_data['status'] === 'draft'): ?>
<div class="card">
    <div class="card-body text-center">
        <h6>Continue Working on Your Appraisal</h6>
        <a href="continue.php" class="btn btn-primary">
            <i class="bi bi-pencil me-2"></i>Continue Editing
        </a>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .btn, .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        margin-bottom: 1rem !important;
    }
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: transparent !important;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>