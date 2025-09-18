
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
    $form_structure = $form->getFormStructure('employee');
    error_log("Original Form Structure: " . print_r($form_structure, true));

     // Simple visibility check function
    function isSectionVisibleToEmployee($section_id, $db) {
        $query = "SELECT visible_to FROM form_sections WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$section_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $visible_to = $result['visible_to'] ?? 'both';
        return ($visible_to === 'both' || $visible_to === 'employee');
    }
    // Filter sections by visibility (default to both if not set)
    $filtered_sections = [];
    foreach ($form_structure as $section) {
        $visible_to = strtolower($section['visible_to'] ?? 'both'); // fallback
        if ($visible_to === 'both' || $visible_to === 'employee') {
            $filtered_sections[] = $section;
        }
    }

    // If filtering removed everything (unlikely), fallback to full structure
    $form_structure = !empty($filtered_sections) ? $filtered_sections : $form_structure;


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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST Data: " . print_r($_POST, true));

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('continue.php', 'Invalid request. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? 'save';
    $appraisal_id = $appraisal->id; // IMPORTANT: defined once
    $success = true;

    try {

         // Handle file uploads first
        $uploaded_files = [];
        if (!empty($_FILES)) {
            foreach ($_FILES as $field_name => $file) {
                if ($file['error'] === UPLOAD_ERR_OK && strpos($field_name, 'attachment_') === 0) {
                    $question_id = str_replace('attachment_', '', $field_name);
                    
                    // Validate file
                    $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'txt'];
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $file_size = $file['size'];
                    
                    if (!in_array($file_ext, $allowed_types)) {
                        throw new Exception("Invalid file type for attachment. Allowed: " . implode(', ', $allowed_types));
                    }
                    
                    if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                        throw new Exception("File size too large. Maximum 5MB allowed.");
                    }
                    
                    // Create uploads directory if it doesn't exist
                    $upload_dir = __DIR__ . '/../../uploads/appraisals/' . $appraisal->id . '/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'q' . $question_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $file['name']);
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $uploaded_files[$question_id] = 'uploads/appraisals/' . $appraisal->id . '/' . $filename;
                    } else {
                        throw new Exception("Failed to upload file: " . $file['name']);
                    }
                }
            }
        }
        // Save responses - UPDATED VERSION
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = str_replace('question_', '', $key);
                $rating_key = 'rating_' . $question_id;
                $comment_key = 'comment_' . $question_id;
                
                $response = is_array($value) ? implode(', ', $value) : $value;
                $rating = $_POST[$rating_key] ?? null;
                $comment = $_POST[$comment_key] ?? null;
                $attachment = $uploaded_files[$question_id] ?? null;
                
                $appraisal->saveResponseWithAttachment($question_id, $response, $rating, $comment, $attachment);
            }
            // Handle standalone rating fields
            elseif (strpos($key, 'rating_') === 0) {
                $question_id = str_replace('rating_', '', $key);
                if (!isset($_POST['question_' . $question_id])) {
                    $comment_key = 'comment_' . $question_id;
                    $comment = $_POST[$comment_key] ?? null;
                    $attachment = $uploaded_files[$question_id] ?? null;
                    $appraisal->saveResponseWithAttachment($question_id, null, $value, $comment, $attachment);
                }
            }
            // Handle standalone comment fields
            elseif (strpos($key, 'comment_') === 0) {
                $question_id = str_replace('comment_', '', $key);
                if (!isset($_POST['question_' . $question_id]) && !isset($_POST['rating_' . $question_id])) {
                    $attachment = $uploaded_files[$question_id] ?? null;
                    $appraisal->saveResponseWithAttachment($question_id, null, null, $value, $attachment);
                }
            }
        }
        
        // Handle attachment-only fields
        foreach ($uploaded_files as $question_id => $filepath) {
            if (!isset($_POST['question_' . $question_id]) && 
                !isset($_POST['rating_' . $question_id]) && 
                !isset($_POST['comment_' . $question_id])) {
                $appraisal->saveResponseWithAttachment($question_id, null, null, null, $filepath);
            }
        }

        // 5) If anything failed, throw to return error to user
        if (!$success) {
            throw new Exception('One or more saves failed. Check logs for details.');
        }

        // 6) Update status if submitting
        if ($action === 'submit') {
            if (!$appraisal->updateStatus('submitted')) {
                throw new Exception('Failed to update status to submitted.');
            }
            logActivity($_SESSION['user_id'], 'SUBMIT', 'appraisals', $appraisal_id, null, null, 'Submitted appraisal for review');
            redirect('../index.php', 'Appraisal submitted successfully! Your manager will be notified.', 'success');
        } else {
            logActivity($_SESSION['user_id'], 'UPDATE', 'appraisals', $appraisal_id, null, null, 'Saved appraisal progress');
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




<form method="POST" action="" id="appraisalForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <?php foreach ($form_structure as $section_index => $section): ?>
        <?php 
    // Check if section is visible to employee
    if (!isSectionVisibleToEmployee($section['id'], $db)) {
        continue; // Skip this section for employee
    }
    ?>
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
// Replace the existing Cultural Values section with this:
if ($section['title'] === 'Cultural Values'): ?>
    <!-- Cultural Values section -->
    <div class="row">
        <?php foreach ($section['questions'] as $question): 
    // Initialize variables at the start of the loop
    $question_id = $question['id'];
    $existing_response = $existing_responses[$question_id] ?? [];
    $response_value = $existing_response['employee_response'] ?? '';
?>
    <div class="mb-4">
        <label class="form-label">
            <?php echo htmlspecialchars($question['text']); ?>
            <?php if ($question['is_required']): ?>
                <span class="text-danger">*</span>
            <?php endif; ?>
        </label>
        
        <?php if ($question['response_type'] === 'display'): ?>
            <!-- Display-only content -->
            <div class="form-control bg-light" style="min-height: 60px;">
                <?php echo nl2br(htmlspecialchars($question['description'])); ?>
            </div>
        <?php else: ?>
            <!-- Regular input fields -->
            <?php if ($question['response_type'] === 'textarea'): ?>
                <textarea class="form-control" 
                        name="question_<?php echo $question_id; ?>" 
                        rows="3"
                        <?php echo $question['is_required'] ? 'required' : ''; ?>
                ><?php echo htmlspecialchars($response_value); ?></textarea>
            <?php elseif ($question['response_type'] === 'text'): ?>
                <input type="text" 
                       class="form-control"
                       name="question_<?php echo $question_id; ?>"
                       value="<?php echo htmlspecialchars($response_value); ?>"
                       <?php echo $question['is_required'] ? 'required' : ''; ?>>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

    <!-- Overall Comments -->
    <?php
    // Find the overall comments question
    $overall_question = array_filter($section['questions'], function($q) {
        return $q['text'] === 'Overall Comments';
    });
    $overall_question = reset($overall_question);
    if ($overall_question):
        $existing_overall = $existing_responses[$overall_question['id']] ?? null;
    ?>
    <div class="mt-4">
        <label class="form-label"><strong>Overall Comments on Cultural Values</strong></label>
        <textarea class="form-control" 
                name="question_<?php echo $overall_question['id']; ?>" 
                rows="4"
                placeholder="Share your overall thoughts on how you demonstrate these cultural values..."
                <?php echo $overall_question['is_required'] ? 'required' : ''; ?>
        ><?php echo htmlspecialchars($existing_overall['employee_response'] ?? ''); ?></textarea>
    </div>
    <?php endif; ?>
<?php else: ?>
    <!-- Regular questions section remains the same -->
        
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
                    
                    /* case 'rating_10': ?>
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
                    <?php break; */

                     case 'attachment': ?>
                        <div class="mb-3">
                            <input type="file" class="form-control" name="attachment_<?php echo $question['id']; ?>" 
                                   id="attachment_<?php echo $question['id']; ?>"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx,.txt">
                            <div class="form-text">
                                Allowed formats: PDF, Word, Excel, Images, Text files (Max: 5MB)
                            </div>
                            <?php if (!empty($existing_response['employee_attachment'])): ?>
                            <div class="mt-2">
                                <small class="text-success">
                                    <i class="bi bi-paperclip me-1"></i>
                                    Current file: <?php echo htmlspecialchars(basename($existing_response['employee_attachment'])); ?>
                                    <a href="download.php?file=<?php echo urlencode($existing_response['employee_attachment']); ?>&type=employee" 
                                       class="text-primary ms-2" target="_blank">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Optional comments about the attachment..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;

                    case 'rating_10': ?>
                        <select class="form-select" name="rating_<?php echo $question['id']; ?>">
                            <option value="">Select rating...</option>
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($existing_response['employee_rating'] ?? '') == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> - <?php 
                                if ($i == 0) echo 'Not Applicable';
                                elseif ($i <= 2) echo 'Below standard: Below job requirements and significant improvement is needed';
                                elseif ($i <= 4) echo 'Below Average';
                                elseif ($i <= 6) echo 'Average';
                                elseif ($i <= 8) echo 'Good';
                                else echo 'Excellent';
                                ?>
                            </option>
                            <?php endfor; ?>
                        </select>   
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Comments (optional)..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    case 'display': ?>
                        <div class="alert alert-secondary">
                            <?php echo nl2br(htmlspecialchars($question['text'])); ?>
                        </div>
                        <?php if ($question['description']): ?>
                            <div class="text-muted small mb-2">
                                <?php echo nl2br(htmlspecialchars($question['description'])); ?>
                            </div>
                        <?php endif; ?>
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
// Add to your existing JavaScript
document.getElementById('appraisalForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let hasError = false;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            hasError = true;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (hasError) {
        e.preventDefault();
        alert('Please fill in all required fields');
    }
});
// Auto-save functionality
/* let autoSaveTimer;
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
}); */
let autoSaveTimer;
document.getElementById('appraisalForm').addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        const formData = new FormData(document.getElementById('appraisalForm'));
        
        fetch('continue.php?id=<?php echo $appraisal_id; ?>', {
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
    indicator.innerHTML = '<i class="bi bi-check-circle me-2"></i>Appraisal auto-saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
