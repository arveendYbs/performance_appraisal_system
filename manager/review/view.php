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
                    <span class="badge bg-secondary <?php echo getGradeColorClass($appraisal_data['grade']); ?> fs-6">
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
                    <strong>Avg Employee Rating:</strong> <?php echo $performance_stats['average_employee_rating']; ?><br>
                    <strong>Avg Manager Rating:</strong> <?php echo $performance_stats['average_manager_rating']; ?><br>
                    <strong>Questions Answered:</strong> <?php echo $performance_stats['answered_questions']; ?>/<?php echo $performance_stats['total_questions']; ?>
                </small>
                <?php else: ?>
                <small class="text-muted">No performance ratings available</small>
                <?php endif; ?>
            </div>
        </div>
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
        <?php if ($section['title'] === 'Cultural Values'): ?>
        <!-- Cultural Values Display -->
        <div class="row">
            <?php
            $cultural_values = [
                ['code' => 'H', 'title' => 'Hard Work', 'desc' => 'Commitment to diligence and perseverance in all aspects of Operations'],
                ['code' => 'H', 'title' => 'Honesty', 'desc' => 'Integrity in dealings with customers, partners and stakeholders'],
                ['code' => 'H', 'title' => 'Harmony', 'desc' => 'Fostering Collaborative relationships and a balanced work environment'],
                ['code' => 'C', 'title' => 'Customer Focus', 'desc' => 'Striving to be the "Only Supplier of Choice" by enhancing customer competitiveness'],
                ['code' => 'I', 'title' => 'Innovation', 'desc' => 'Embracing transformation and agility, as symbolized by their "Evolving with Momentum" theme'],
                ['code' => 'S', 'title' => 'Sustainability', 'desc' => 'Rooted in organic growth and long-term value creation, reflected in their visual metaphors']
            ];
            
            foreach ($cultural_values as $cv_index => $cv): 
                $question_id = $section['questions'][$cv_index]['id'] ?? null;
                $response = $responses[$question_id] ?? null;
            ?>
            <div class="col-md-6 mb-4">
                <div class="border rounded p-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-primary me-2" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                            <?php echo $cv['code']; ?>
                        </span>
                        <strong><?php echo $cv['title']; ?></strong>
                    </div>
                    <p class="small text-muted mb-3"><?php echo $cv['desc']; ?></p>
                    
                    <div class="review-layout">
                        <div class="employee-column">
                            <div class="column-header">Employee Response</div>
                            <?php if ($response && $response['employee_comments']): ?>
                                <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?>
                            <?php else: ?>
                                <em class="text-muted">No response provided</em>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($response && $response['manager_comments']): ?>
                        <div class="manager-column">
                            <div class="column-header">Manager Feedback</div>
                            <?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <!-- Regular Questions Display -->
        <?php foreach ($section['questions'] as $question): 
            $response = $responses[$question['id']] ?? null;
        ?>
        <div class="mb-4 pb-4 border-bottom">
            <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>
            
            <?php if ($question['description']): ?>
            <p class="text-muted small mb-3"><?php echo htmlspecialchars($question['description']); ?></p>
            <?php endif; ?>
            
            <div class="review-layout">
                <div class="employee-column">
                    <div class="column-header">Employee Response</div>
                    
                    <?php if (in_array($question['response_type'], ['rating_5', 'rating_10'])): ?>
                    <?php if ($response && $response['employee_rating'] !== null): ?>
                    <div class="mb-2">
                        <span class="badge bg-info me-2">Rating: <?php echo $response['employee_rating']; ?></span>
                        <span class="text-muted">
                            <?php 
                            $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                            $stars = round(($response['employee_rating'] / $max_rating) * 5);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $stars ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>';
                            }
                            ?>
                            (<?php echo round(($response['employee_rating'] / $max_rating) * 100); ?>%)
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($question['response_type'] === 'checkbox'): ?>
                        <?php if ($response && $response['employee_response']): ?>
                            <?php 
                            $selected_options = explode(', ', $response['employee_response']);
                            foreach ($selected_options as $option): 
                            ?>
                            <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($option); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <em class="text-muted">No options selected</em>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($response && ($response['employee_response'] || $response['employee_comments'])): ?>
                            <?php if ($response['employee_response']): ?>
                                <div class="mb-2"><?php echo nl2br(htmlspecialchars($response['employee_response'])); ?></div>
                            <?php endif; ?>
                            <?php if ($response['employee_comments']): ?>
                                <small><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <em class="text-muted">No response provided</em>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="manager-column">
                    <div class="column-header">Manager Assessment</div>
                    
                    <?php if ($response && ($response['manager_rating'] !== null || $response['manager_comments'] || $response['manager_response'])): ?>
                        <?php if ($response['manager_rating'] !== null): ?>
                        <div class="mb-2">
                            <span class="badge bg-success me-2">Rating: <?php echo $response['manager_rating']; ?></span>
                            <span class="text-muted">
                                <?php 
                                $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                                $stars = round(($response['manager_rating'] / $max_rating) * 5);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $stars ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>';
                                }
                                ?>
                                (<?php echo round(($response['manager_rating'] / $max_rating) * 100); ?>%)
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($response['manager_response']): ?>
                            <div class="mb-2"><?php echo nl2br(htmlspecialchars($response['manager_response'])); ?></div>
                        <?php endif; ?>
                        <?php if ($response['manager_comments']): ?>
                            <div><?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <em class="text-muted">No manager feedback provided</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
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