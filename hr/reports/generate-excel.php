<?php
// hr/reports/generate-excel.php - FINAL FIXED VERSION
require_once __DIR__ . '/../../config/config.php';

// Check if user is HR
if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}
$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();
// Check required parameters
$user_id = $_GET['user_id'] ?? 0;
$year = $_GET['year'] ?? date('Y');

if (!$user->isHR()) {
    redirect(BASE_URL . '/index.php', 'Access denied. HR personnel only.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user information
    $user_query = "SELECT u.*, c.name as company_name,
                          sup.name as supervisor_name
                   FROM users u
                   LEFT JOIN companies c ON u.company_id = c.id
                   LEFT JOIN users sup ON u.direct_superior = sup.id
                   WHERE u.id = ?";
    $stmt = $db->prepare($user_query);
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        redirect('index.php', 'Employee not found.', 'error');
    }
    
    // Get all appraisals for the user in the selected year
    $appraisals_query = "SELECT a.*, f.title as form_title
                         FROM appraisals a
                         LEFT JOIN forms f ON a.form_id = f.id
                         WHERE a.user_id = ?
                         AND YEAR(a.appraisal_period_from) = ?
                         AND a.status = 'completed'
                         ORDER BY a.appraisal_period_from";
    
    $stmt = $db->prepare($appraisals_query);
    $stmt->execute([$user_id, $year]);
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($appraisals)) {
        redirect('index.php', 'No completed appraisals found for this employee in ' . $year, 'warning');
    }
    
    // Prepare data for Excel generation
    $report_data = [
        'employee' => $employee,
        'appraisals' => [],
        'year' => $year
    ];
    
    // Get detailed appraisal data including section scores
    foreach ($appraisals as $appraisal) {
        $appraisal_id = $appraisal['id'];
        
        // Get responses with section information
        $responses_query = "SELECT 
                                r.employee_rating,
                                r.manager_rating,
                                r.employee_comments,
                                r.manager_comments,
                                fs.id as section_id,
                                fs.section_title,
                                fs.section_order,
                                fq.question_order
                           FROM responses r
                           JOIN form_questions fq ON r.question_id = fq.id
                           JOIN form_sections fs ON fq.section_id = fs.id
                           WHERE r.appraisal_id = ?
                           AND fq.response_type IN ('rating_5', 'rating_10')
                           ORDER BY fs.section_order, fq.question_order";
        
        $stmt = $db->prepare($responses_query);
        $stmt->execute([$appraisal_id]);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize responses by section
        $sections = [];
        foreach ($responses as $response) {
            $section_id = $response['section_id'];
            $section_title = $response['section_title'];
            
            if (!isset($sections[$section_id])) {
                $sections[$section_id] = [
                    'title' => $section_title,
                    'section_order' => $response['section_order'],
                    'employee_score' => 0,
                    'manager_score' => 0,
                    'question_count' => 0
                ];
            }
            
            // Add scores (handle NULL values)
            $emp_score = $response['employee_rating'] ?? 0;
            $mgr_score = $response['manager_rating'] ?? 0;
            
            $sections[$section_id]['employee_score'] += $emp_score;
            $sections[$section_id]['manager_score'] += $mgr_score;
            $sections[$section_id]['question_count']++;
        }
        
        // Sort sections by order
        uasort($sections, function($a, $b) {
            return $a['section_order'] <=> $b['section_order'];
        });
        
        $report_data['appraisals'][] = [
            'id' => $appraisal_id,
            'form_title' => $appraisal['form_title'],
            'period_from' => $appraisal['appraisal_period_from'],
            'period_to' => $appraisal['appraisal_period_to'],
            'total_score' => $appraisal['total_score'],
            'grade' => $appraisal['grade'],
            'submitted_at' => $appraisal['employee_submitted_at'],
            'reviewed_at' => $appraisal['manager_reviewed_at'],
            'sections' => array_values($sections)
        ];
    }
    
    // Save data to temp JSON file for Python script
    $temp_json = tempnam(sys_get_temp_dir(), 'appraisal_report_');
    file_put_contents($temp_json, json_encode($report_data, JSON_PRETTY_PRINT));
    
    // Generate Excel file using Python
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $employee['name']);
    $output_filename = 'Appraisal_Report_' . $safe_name . '_' . $year . '.xlsx';
    $output_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $output_filename;
    
    // Call Python script to generate Excel
    $python_script = __DIR__ . DIRECTORY_SEPARATOR . 'generate-excel-report.py';
    
    // Check if Python script exists
    if (!file_exists($python_script)) {
        error_log("Python script not found: " . $python_script);
        unlink($temp_json);
        redirect('index.php', 'Excel generator script not found.', 'error');
    }
    
    // Use 'python' or 'python3' or 'py' depending on your system
    $python_cmd = 'py';  // Windows typically uses 'py'
    // For Linux/Mac, use: $python_cmd = 'python3';
    
    $command = $python_cmd . " " . escapeshellarg($python_script) . " " . 
               escapeshellarg($temp_json) . " " . 
               escapeshellarg($output_path) . " 2>&1";
    
    error_log("Executing: " . $command);
    $output = shell_exec($command);
    error_log("Python output: " . $output);
    
    // Clean up temp JSON
    unlink($temp_json);
    
    // Check if file was created
    if (!file_exists($output_path)) {
        error_log("Excel file not created. Output: " . $output);
        redirect('index.php', 'Failed to generate Excel report.', 'error');
    }
    
    // Verify file size
    $filesize = filesize($output_path);
    if ($filesize === 0) {
        error_log("Excel file is empty: " . $output_path);
        unlink($output_path);
        redirect('index.php', 'Generated Excel file is empty.', 'error');
    }
    
    error_log("Excel file ready: " . $output_path . " (" . $filesize . " bytes)");
    
    // CRITICAL: Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // CRITICAL: Set headers in correct order
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $output_filename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: max-age=0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($output_path);
    
    // Clean up
    unlink($output_path);
    
    // CRITICAL: Exit immediately
    exit();
    
} catch (Exception $e) {
    error_log("Excel Report Generation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    redirect('index.php', 'An error occurred: ' . $e->getMessage(), 'error');
}