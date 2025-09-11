<?php
// manager/review/review.php
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
    
    // Update status to in_review if it's still submitted
    if ($appraisal_data['status'] === 'submitted') {
        $update_query = "UPDATE appraisals SET status = 'in_review' WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$appraisal_id]);
        $appraisal_data['status'] = 'in_review';
    }
    
    // Get form structure
    $form = new Form($db);
    $form->id = $appraisal_data['form_id'];
    $form_structure = $form->getFormStructure();
    
    // Get existing responses
    $appraisal = new Appraisal($db);
    $appraisal->id = $appraisal_id;
    $responses_stmt = $appraisal->getResponses();
    
    $responses = [];
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $responses[$response['question_id']] = $response;
    }
    
} catch (Exception $e) {
    error_log("Review appraisal error: " . $e->getMessage());
    redirect('pending.php', 'An error occurred. Please try again.', 'error');
}

// Handle form submission for manager feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('review.php?id=' . $appraisal_id, 'Invalid request. Please try again.', 'error');
    }
    
    try {
        // Save manager responses
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'manager_rating_') === 0) {
                $question_id = str_replace('manager_rating_', '', $key);
                $rating = !empty($value) ? intval($value) : null;
                $comment_key = 'manager_comment_' . $question_id;
                $comment = $_POST[$comment_key] ?? null;
                
                // Save manager rating and comment
                $query = "INSERT INTO responses (appraisal_id, question_id, manager_rating, manager_comments)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE 
                         manager_rating = VALUES(manager_rating),
                         manager_comments = VALUES(manager_comments)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$appraisal_id, $question_id, $rating, $comment]);
            }
        }
        
        logActivity($_SESSION['user_id'], 'UPDATE', 'appraisals', $appraisal_id, null, null, 
                   'Updated manager review for ' . $appraisal_data['employee_name']);
        
        redirect('review.php?id=' . $appraisal_id, 'Review progress saved successfully!', 'success');
        
    } catch (Exception $e) {
        error_log("Save manager review error: " . $e->getMessage());
        redirect('review.php?id=' . $appraisal_id, 'Failed to save review. Please try again.', 'error');
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-clipboard-check me-2"></i>Review Appraisal
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
                <a href="pending.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Pending
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Appraisal Summary -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h6>Employee Details</h6>
                <p>
                    <strong><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></small><br>
                    <?php echo htmlspecialchars($appraisal_data['position']); ?>
                </p>
            </div>
            <div class="col-md-4">
                <h6>Appraisal Period</h6>
                <p>
                    <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - 
                    <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                </p>
            </div>
            <div class="col-md-4">
                <h6>Form Type</h6>
                <p><?php echo htmlspecialchars($appraisal_data['form_title']); ?></p>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="" id="reviewForm">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
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
            <!-- Cultural Values Review -->
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
                            
                            <div class="manager-column">
                                <div class="column-header">Manager Feedback</div>
                                <?php if ($question_id): ?>
                                <textarea class="form-control" name="manager_comment_<?php echo $question_id; ?>" rows="3"
                                          placeholder="Provide your feedback on this cultural value..."><?php echo htmlspecialchars($response['manager_comments'] ?? ''); ?></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php else: ?>
            <!-- Performance Assessment and Other Sections -->
            <?php foreach ($section['questions'] as $question): 
                $response = $responses[$question['id']] ?? null;
            ?>
            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>
                
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
                        
                        <?php if (in_array($question['response_type'], ['rating_5', 'rating_10'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Your Rating</label>
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <?php $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10; ?>
                                    <input type="range" class="form-range" min="<?php echo $question['response_type'] === 'rating_5' ? '1' : '0'; ?>" max="<?php echo $max_rating; ?>" step="1"
                                           name="manager_rating_<?php echo $question['id']; ?>" 
                                           id="manager_rating_<?php echo $question['id']; ?>"
                                           value="<?php echo $response['manager_rating'] ?? ($question['response_type'] === 'rating_5' ? 3 : 5); ?>"
                                           oninput="updateRatingValue(this, 'manager_display_<?php echo $question['id']; ?>')">
                                </div>
                                <div class="col-4">
                                    <span class="badge bg-success fs-6" id="manager_display_<?php echo $question['id']; ?>">
                                        <?php echo $response['manager_rating'] ?? ($question['response_type'] === 'rating_5' ? 3 : 5); ?>
                                    </span>
                                    <small class="text-muted d-block"><?php echo $question['response_type'] === 'rating_5' ? '1-5 Scale' : '0-10 Scale'; ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <textarea class="form-control" name="manager_comment_<?php echo $question['id']; ?>" rows="3"
                                  placeholder="Provide your assessment and feedback..."><?php echo htmlspecialchars($response['manager_comments'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Action Buttons -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Save Progress:</strong> Your review progress is saved automatically. You can return anytime to continue.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Complete Review:</strong> Once all sections are reviewed, complete the appraisal with final grades.
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-save me-2"></i>Save Progress
                </button>
                <a href="complete.php?id=<?php echo $appraisal_id; ?>" class="btn btn-success">
                    <i class="bi bi-check-circle me-2"></i>Complete Review
                </a>
            </div>
        </div>
    </div>
</form>

<script>
// Initialize rating displays
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="range"]').forEach(function(slider) {
        const displayId = slider.getAttribute('oninput').match(/'([^']+)'/)[1];
        updateRatingValue(slider, displayId);
    });
});

// Auto-save functionality
let autoSaveTimer;
document.getElementById('reviewForm').addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        const formData = new FormData(document.getElementById('reviewForm'));
        
        fetch('review.php?id=<?php echo $appraisal_id; ?>', {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                console.log('Auto-saved successfully');
                showAutoSaveIndicator();
            }
        }).catch(error => {
            console.error('Auto-save failed:', error);
        });
    }, 30000);
});

function showAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'alert alert-success position-fixed';
    indicator.style.cssText = 'top: 80px; right: 20px; z-index: 9999; opacity: 0.9;';
    indicator.innerHTML = '<i class="bi bi-check-circle me-2"></i>Review auto-saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>