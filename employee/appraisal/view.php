
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
                    <i class="bi bi-clipboard-data me-2"></i>Appraisal Details
                </h1>
                <small class="text-muted">
                    Period: <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - 
                    <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                </small>
            </div>
            <div>
                <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?> me-2">
                    <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                </span>
                <a href="../" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Appraisal Summary -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <h6>Form Type</h6>
                <p><?php echo htmlspecialchars($appraisal_data['form_title']); ?></p>
            </div>
            <div class="col-md-3">
                <h6>Status</h6>
                <p>
                    <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?>">
                        <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                    </span>
                </p>
            </div>
            <?php if ($appraisal_data['grade']): ?>
            <div class="col-md-3">
                <h6>Grade</h6>
                <p>
                    <span class="badge bg-secondary <?php echo getGradeColorClass($appraisal_data['grade']); ?>">
                        <?php echo $appraisal_data['grade']; ?>
                    </span>
                </p>
            </div>
            <?php endif; ?>
            <?php if ($appraisal_data['total_score']): ?>
            <div class="col-md-3">
                <h6>Total Score</h6>
                <p><strong><?php echo $appraisal_data['total_score']; ?>%</strong></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <h6>Submitted</h6>
                <p><?php echo $appraisal_data['employee_submitted_at'] ? 
                    formatDate($appraisal_data['employee_submitted_at'], 'M d, Y H:i') : 'Not submitted'; ?></p>
            </div>
            <?php if ($appraisal_data['manager_reviewed_at']): ?>
            <div class="col-md-6">
                <h6>Reviewed By</h6>
                <p><?php echo htmlspecialchars($appraisal_data['appraiser_name']); ?><br>
                <small class="text-muted"><?php echo formatDate($appraisal_data['manager_reviewed_at'], 'M d, Y H:i'); ?></small></p>
            </div>
            <?php endif; ?>
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
    <!-- Cultural Values section -->
    <div class="row">
        <?php foreach ($section['questions'] as $question): 
            $question_id = $question['id'];
            $existing_response = $responses[$question_id] ?? [];
            $response_value = $existing_response['employee_response'] ?? '';
            
            // Skip overall comments question - it will be shown at the bottom
            if (stripos($question['text'], 'Overall Comments') !== false) continue;
        ?>
        <div class="col-md-6 mb-4">
            <div class="border rounded p-3 h-100">
                <div class="d-flex align-items-center mb-2">
                    <?php
                    $parts = explode(' - ', $question['text']);
                    $code = $parts[0] ?? '';
                    $title = $parts[1] ?? $question['text'];
                    ?>
                    <span class="badge bg-primary me-2" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        <?php echo htmlspecialchars($code); ?>
                    </span>
                    <strong><?php echo htmlspecialchars($title); ?></strong>
                </div>
                
                <p class="small text-muted mb-3">
                    <?php echo htmlspecialchars($question['description'] ?? ''); ?>
                </p>
                
                <div class="response-text border-start border-primary border-3 ps-3 mt-3">
                    <?php if (!empty($response_value)): ?>
                        <?php echo nl2br(htmlspecialchars($response_value)); ?>
                    <?php else: ?>
                        <em class="text-muted">No response provided</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Overall Comments -->
    <?php
    // Find the overall comments question
    $overall_question = array_filter($section['questions'], function($q) {
        return stripos($q['text'], 'Overall Comments') !== false;
    });
    $overall_question = reset($overall_question);
    
    if ($overall_question):
        $overall_id = $overall_question['id'];
        $overall_response = $responses[$overall_id] ?? [];
        $overall_value = $overall_response['employee_response'] ?? '';
    ?>
    <div class="mt-4">
        <h5 class="mb-3">Overall Comments on Cultural Values</h5>
        <div class="response-text border-start border-primary border-3 ps-3">
            <?php if (!empty($overall_value)): ?>
                <?php echo nl2br(htmlspecialchars($overall_value)); ?>
            <?php else: ?>
                <em class="text-muted">No overall comments provided</em>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
        <?php else: ?>
        <!-- Regular Questions Display -->
        <?php foreach ($section['questions'] as $question): 
            $response = $responses[$question['id']] ?? null;
        ?>
        <div class="mb-4 pb-4 border-bottom">
            <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">Your Response:</h6>
                    
                    <?php if (in_array($question['response_type'], ['rating_5', 'rating_10'])): ?>
                    <?php if ($response && $response['employee_rating'] !== null): ?>
                    <div class="mb-2">
                        <span class="badge bg-primary me-2">Rating: <?php echo $response['employee_rating']; ?></span>
                        <span class="text-muted">
                            <?php 
                            $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                            $stars = round(($response['employee_rating'] / $max_rating) * 5);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $stars ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>';
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="bg-light p-3 rounded">
                        <?php if ($question['response_type'] === 'checkbox'): ?>
                            <?php if ($response && $response['employee_response']): ?>
                                <?php 
                                $selected_options = explode(', ', $response['employee_response']);
                                foreach ($selected_options as $option): 
                                ?>
                                <span class="badge bg-info me-1 mb-1"><?php echo htmlspecialchars($option); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <em class="text-muted">No options selected</em>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($response && ($response['employee_response'] || $response['employee_comments'])): ?>
                                <?php if ($response['employee_response']): ?>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($response['employee_response'])); ?></p>
                                <?php endif; ?>
                                <?php if ($response['employee_comments']): ?>
                                    <small><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <em class="text-muted">No response provided</em>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6 class="text-success">Manager's Assessment:</h6>
                    
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
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="bg-success bg-opacity-10 p-3 rounded border-start border-success border-3">
                            <?php if ($response['manager_response']): ?>
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($response['manager_response'])); ?></p>
                            <?php endif; ?>
                            <?php if ($response['manager_comments']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-light p-3 rounded">
                            <em class="text-muted">No manager feedback yet</em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>