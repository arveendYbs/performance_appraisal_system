<?php
// manager/review/view.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('manager') && !hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('pending.php', 'Appraisal ID is required.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get appraisal details and verify it belongs to manager's team or is completed
    $query = "SELECT a.*, u.name as employee_name, u.position, u.emp_number, u.department, u.site,
                     f.title as form_title, f.form_type,
                     appraiser.name as appraiser_name
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN forms f ON a.form_id = f.id
              LEFT JOIN users appraiser ON a.appraiser_id = appraiser.id
              WHERE a.id = ? AND (u.direct_superior = ? OR a.status = 'completed')";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$appraisal_id, $_SESSION['user_id']]);
    $appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appraisal_data) {
        redirect('pending.php', 'Appraisal not found or access denied.', 'error');
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
    
    // Calculate performance statistics
    $performance_stats = [
        'total_questions' => 0,
        'answered_questions' => 0,
        'average_employee_rating' => 0,
        'average_manager_rating' => 0,
        'total_employee_score' => 0,
        'total_manager_score' => 0,
        'max_possible_score' => 0
    ];
    
    foreach ($form_structure as $section) {
        if ($section['title'] === 'Performance Assessment') {
            foreach ($section['questions'] as $question) {
                if (in_array($question['response_type'], ['rating_5', 'rating_10'])) {
                    $performance_stats['total_questions']++;
                    $response = $responses[$question['id']] ?? null;
                    
                    $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                    $performance_stats['max_possible_score'] += $max_rating;
                    
                    if ($response) {
                        if ($response['employee_rating'] !== null) {
                            $performance_stats['answered_questions']++;
                            $performance_stats['total_employee_score'] += $response['employee_rating'];
                        }
                        if ($response['manager_rating'] !== null) {
                            $performance_stats['total_manager_score'] += $response['manager_rating'];
                        }
                    }
                }
            }
            break;
        }
    }
    
    if ($performance_stats['total_questions'] > 0) {
        $performance_stats['average_employee_rating'] = round($performance_stats['total_employee_score'] / $performance_stats['total_questions'], 1);
        $performance_stats['average_manager_rating'] = round($performance_stats['total_manager_score'] / $performance_stats['total_questions'], 1);
    }
    
} catch (Exception $e) {
    error_log("View appraisal error: " . $e->getMessage());
    redirect('pending.php', 'An error occurred. Please try again.', 'error');
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-eye me-2"></i>Appraisal Details
                </h1>
                <small class="text-muted">
                    Employee: <?php echo htmlspecialchars($appraisal_data['employee_name']); ?> 
                    (<?php echo htmlspecialchars($appraisal_data['position']); ?>)
                </small>
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
                <a href="../team.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Team
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
            <div class="col-md-3">
                <h6>Employee Information</h6>
                <p>
                    <strong><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></small><br>
                    <?php echo htmlspecialchars($appraisal_data['position']); ?><br>
                    <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['department']); ?> | <?php echo htmlspecialchars($appraisal_data['site']); ?></small>
                </p>
            </div>
            <div class="col-md-3">
                <h6>Appraisal Period</h6>
                <p>
                    <strong><?php echo formatDate($appraisal_data['appraisal_period_from'], 'M Y'); ?> - 
                    <?php echo formatDate($appraisal_data['appraisal_period_to'], 'M Y'); ?></strong><br>
                    <small class="text-muted">
                        <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - 
                        <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                    </small>
                </p>
            </div>
            <div class="col-md-3">
                <h6>Form Type</h6>
                <p>
                    <?php echo htmlspecialchars($appraisal_data['form_title']); ?><br>
                    <span class="badge bg-<?php echo $appraisal_data['form_type'] == 'management' ? 'primary' : ($appraisal_data['form_type'] == 'general' ? 'info' : 'success'); ?>">
                        <?php echo ucfirst($appraisal_data['form_type']); ?> Staff
                    </span>
                </p>
            </div>
            <div class="col-md-3">
                <h6>Performance Summary</h6>
                <?php if ($appraisal_data['grade']): ?>
                <p>
                    <span class="badge bg-light <?php echo getGradeColorClass($appraisal_data['grade']); ?> fs-6">
                        Grade: <?php echo $appraisal_data['grade']; ?>
                    </span><br>
                    <?php if ($appraisal_data['total_score']): ?>
                    <strong><?php echo $appraisal_data['total_score']; ?>%</strong>
                    <?php endif; ?>
                </p>
                <?php else: ?>
                <p class="text-muted">Not yet graded</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-4">
                <h6>Timeline</h6>
                <ul class="list-unstyled">
                    <li><small><strong>Created:</strong> <?php echo formatDate($appraisal_data['created_at'], 'M d, Y H:i'); ?></small></li>
                    <?php if ($appraisal_data['employee_submitted_at']): ?>
                    <li><small><strong>Submitted:</strong> <?php echo formatDate($appraisal_data['employee_submitted_at'], 'M d, Y H:i'); ?></small></li>
                    <?php endif; ?>
                    <?php if ($appraisal_data['manager_reviewed_at']): ?>
                    <li><small><strong>Completed:</strong> <?php echo formatDate($appraisal_data['manager_reviewed_at'], 'M d, Y H:i'); ?></small></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-4">
                <h6>Reviewer</h6>
                <p>
                    <?php if ($appraisal_data['appraiser_name']): ?>
                        <?php echo htmlspecialchars($appraisal_data['appraiser_name']); ?>
                    <?php else: ?>
                        <span class="text-muted">Not assigned</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4">
                <h6>Performance Statistics</h6>
                <?php if ($performance_stats['total_questions'] > 0): ?>
                <small>
                    <strong>Avg Employee Score:</strong> <?php echo $performance_stats['average_employee_rating']; ?><br>
                    <strong>Avg Manager Score:</strong> <?php echo $performance_stats['average_manager_rating']; ?><br>
                    <strong>Questions Answered:</strong> <?php echo $performance_stats['answered_questions']; ?>/<?php echo $performance_stats['total_questions']; ?>
                </small>
                <?php else: ?>
                <small class="text-muted">No performance scores available</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Appraisal Content -->

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

        <?php else: ?>
            <!-- Normal side-by-side layout -->
            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>

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
<?php if ($appraisal_data['status'] !== 'completed' && $appraisal_data['direct_superior'] == $_SESSION['user_id']): ?>
<div class="card">
    <div class="card-body text-center">
        <h6>Manager Actions</h6>
        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            <?php if ($appraisal_data['status'] === 'submitted' || $appraisal_data['status'] === 'in_review'): ?>
            <a href="review.php?id=<?php echo $appraisal_id; ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-2"></i>Continue Review
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .btn, .card-header, .no-print {
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