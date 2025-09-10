
<?php
// employee/appraisal/continue.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get current appraisal
    $appraisal = new Appraisal($db);
    if (!$appraisal->getCurrentAppraisal($_SESSION['user_id'])) {
        redirect('start.php', 'No active appraisal found. Please start a new one.', 'warning');
    }
    
    // Check if already submitted
    if ($appraisal->status !== 'draft') {
        redirect('view.php?id=' . $appraisal->id, 'This appraisal has already been submitted.', 'info');
    }
    
    // Get form structure
    $form = new Form($db);
    $form->id = $appraisal->form_id;
    $form->readOne();
    $form_structure = $form->getFormStructure();
    
    // Get existing responses
    $existing_responses = [];
    $responses_stmt = $appraisal->getResponses();
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_responses[$response['question_id']] = $response;
    }
    
} catch (Exception $e) {
    error_log("Continue appraisal error: " . $e->getMessage());
    redirect('../index.php', 'An error occurred. Please try again.', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('continue.php', 'Invalid request. Please try again.', 'error');
    }
    
    $action = $_POST['action'] ?? 'save';
    
    try {
        // Save responses
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = str_replace('question_', '', $key);
                $rating_key = 'rating_' . $question_id;
                $comment_key = 'comment_' . $question_id;
                
                $response = is_array($value) ? implode(', ', $value) : $value;
                $rating = $_POST[$rating_key] ?? null;
                $comment = $_POST[$comment_key] ?? null;
                
                $appraisal->saveResponse($question_id, $response, $rating, $comment);
            }
        }
        
        // Update status if submitting
        if ($action === 'submit') {
            $appraisal->updateStatus('submitted');
            logActivity($_SESSION['user_id'], 'SUBMIT', 'appraisals', $appraisal->id, null, null, 
                       'Submitted appraisal for review');
            redirect('../index.php', 'Appraisal submitted successfully! Your manager will be notified.', 'success');
        } else {
            logActivity($_SESSION['user_id'], 'UPDATE', 'appraisals', $appraisal->id, null, null, 
                       'Saved appraisal progress');
            redirect('continue.php', 'Progress saved successfully!', 'success');
        }
        
    } catch (Exception $e) {
        error_log("Save appraisal error: " . $e->getMessage());
        redirect('continue.php', 'Failed to save. Please try again.', 'error');
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>My Appraisal
                </h1>
                <small class="text-muted">
                    Period: <?php echo formatDate($appraisal->appraisal_period_from); ?> - 
                    <?php echo formatDate($appraisal->appraisal_period_to); ?>
                </small>
            </div>
            <div>
                <span class="badge <?php echo getStatusBadgeClass($appraisal->status); ?> me-2">
                    <?php echo ucwords(str_replace('_', ' ', $appraisal->status)); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="" id="appraisalForm">
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
            <!-- Special handling for Cultural Values section -->
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
                    $existing_response = $existing_responses[$question_id] ?? null;
                ?>
                <div class="col-md-6 mb-4">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary me-2" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                <?php echo $cv['code']; ?>
                            </span>
                            <strong><?php echo $cv['title']; ?></strong>
                        </div>
                        <p class="small text-muted mb-3"><?php echo $cv['desc']; ?></p>
                        <?php if ($question_id): ?>
                        <textarea class="form-control" name="comment_<?php echo $question_id; ?>" rows="3"
                                  placeholder="Share your thoughts and examples on this cultural value..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-4">
                <label class="form-label"><strong>Overall Comments on Cultural Values</strong></label>
                <textarea class="form-control" name="cultural_values_overall" rows="4"
                          placeholder="Provide overall comments on how you demonstrate the company's cultural values..."></textarea>
            </div>
            
            <?php else: ?>
            <!-- Regular questions -->
            <?php foreach ($section['questions'] as $question): 
                $existing_response = $existing_responses[$question['id']] ?? null;
            ?>
            <div class="mb-4 question-item">
                <label class="form-label fw-bold">
                    <?php echo htmlspecialchars($question['text']); ?>
                    <?php if ($question['is_required']): ?><span class="text-danger">*</span><?php endif; ?>
                </label>
                
                <?php if ($question['description']): ?>
                <div class="text-muted small mb-2"><?php echo htmlspecialchars($question['description']); ?></div>
                <?php endif; ?>
                
                <?php switch ($question['response_type']): 
                    case 'text': ?>
                        <input type="text" class="form-control" name="question_<?php echo $question['id']; ?>"
                               value="<?php echo htmlspecialchars($existing_response['employee_response'] ?? ''); ?>"
                               <?php echo $question['is_required'] ? 'required' : ''; ?>>
                    <?php break;
                    
                    case 'textarea': ?>
                        <textarea class="form-control" name="question_<?php echo $question['id']; ?>" rows="4"
                                  <?php echo $question['is_required'] ? 'required' : ''; ?>><?php echo htmlspecialchars($existing_response['employee_response'] ?? ''); ?></textarea>
                    <?php break;
                    
                    case 'rating_5': ?>
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <input type="range" class="form-range" min="1" max="5" step="1"
                                       name="rating_<?php echo $question['id']; ?>" 
                                       id="rating_<?php echo $question['id']; ?>"
                                       value="<?php echo $existing_response['employee_rating'] ?? 3; ?>"
                                       oninput="updateRatingValue(this, 'rating_display_<?php echo $question['id']; ?>')">
                            </div>
                            <div class="col-md-4">
                                <span class="badge bg-primary fs-6" id="rating_display_<?php echo $question['id']; ?>">
                                    <?php echo $existing_response['employee_rating'] ?? 3; ?>
                                </span>
                                <small class="text-muted d-block">1=Poor, 5=Excellent</small>
                            </div>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Comments (optional)..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    
                    case 'rating_10': ?>
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <input type="range" class="form-range" min="0" max="10" step="1"
                                       name="rating_<?php echo $question['id']; ?>" 
                                       id="rating_<?php echo $question['id']; ?>"
                                       value="<?php echo $existing_response['employee_rating'] ?? 5; ?>"
                                       oninput="updateRatingValue(this, 'rating_display_<?php echo $question['id']; ?>')">
                            </div>
                            <div class="col-md-4">
                                <span class="badge bg-primary fs-6" id="rating_display_<?php echo $question['id']; ?>">
                                    <?php echo $existing_response['employee_rating'] ?? 5; ?>
                                </span>
                                <small class="text-muted d-block">0-10 Scale</small>
                            </div>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Comments (optional)..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    
                    case 'checkbox': 
                        $options = $question['options'] ?? [];
                        $selected = explode(', ', $existing_response['employee_response'] ?? '');
                    ?>
                        <div class="row">
                            <?php foreach ($options as $option): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="question_<?php echo $question['id']; ?>[]" 
                                           value="<?php echo htmlspecialchars($option); ?>"
                                           <?php echo in_array($option, $selected) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Additional comments or specify others..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    
                endswitch; ?>
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
                        <strong>Save Progress:</strong> Your progress is automatically saved. You can return anytime to continue.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Submit:</strong> Once submitted, you cannot make further changes until your manager's review.
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" name="action" value="save" class="btn btn-outline-primary">
                    <i class="bi bi-save me-2"></i>Save Progress
                </button>
                <button type="submit" name="action" value="submit" class="btn btn-success"
                        onclick="return confirm('Are you sure you want to submit this appraisal? You will not be able to make changes after submission.')">
                    <i class="bi bi-send me-2"></i>Submit for Review
                </button>
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
document.getElementById('appraisalForm').addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        // Create a hidden form to submit for auto-save
        const formData = new FormData(document.getElementById('appraisalForm'));
        formData.set('action', 'save');
        
        fetch('continue.php', {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                console.log('Auto-saved successfully');
                // Show a small indicator
                showAutoSaveIndicator();
            }
        }).catch(error => {
            console.error('Auto-save failed:', error);
        });
    }, 30000); // Auto-save after 30 seconds of inactivity
});

function showAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'alert alert-success position-fixed';
    indicator.style.cssText = 'top: 80px; right: 20px; z-index: 9999; opacity: 0.9;';
    indicator.innerHTML = '<i class="bi bi-check-circle me-2"></i>Auto-saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
