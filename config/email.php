<?php
/**
 * Email Configuration and Helper Functions
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email configuration constants
define('SMTP_FROM', 'arveend@ybsinternational.com');
define('SMTP_FROM_NAME', 'Performance Appraisal System');

// SMTP configurations
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'arveend@ybsinternational.com');
define('SMTP_PASSWORD', 'Ybs@2025!');
define('SMTP_ENCRYPTION', 'tls');

function sendEmail($to, $subject, $message, $recipient_name = '', $options = []) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $recipient_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = getEmailTemplate($message, $subject, $recipient_name);

        $mail->send();
        logEmail($to, $recipient_name, $subject, $options['email_type'] ?? 'general', 
                 $options['appraisal_id'] ?? null, $options['user_id'] ?? null, true);
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        logEmail($to, $recipient_name, $subject, $options['email_type'] ?? 'general',
                 $options['appraisal_id'] ?? null, $options['user_id'] ?? null, false);
        return false;
    }
}

/**
 * Get HTML email template
 */
function getEmailTemplate($content, $subject, $recipient_name) {
    $greeting = $recipient_name ? "Hi {$recipient_name}," : "Hello,";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { 
                display: inline-block; 
                padding: 12px 24px; 
                background-color: #0d6efd; 
                color: white !important; 
                text-decoration: none; 
                border-radius: 5px;
                margin: 15px 0;
                font-weight: bold;
            }
            .info-box {
                background-color: #e7f3ff;
                border-left: 4px solid #0d6efd;
                padding: 15px;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h2 style='margin: 0;'>Performance Appraisal System</h2>
            </div>
            <div class='content'>
                <p>{$greeting}</p>
                {$content}
            </div>
            <div class='footer'>
                <p>This is an automated email from the Performance Appraisal System.</p>
                <p>Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " YBS International. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Log email sending attempt to database
 * FIXED: Get database connection properly
 */
function logEmail($to, $recipient_name, $subject, $email_type, $appraisal_id = null, $user_id = null, $success = true) {
    try {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            error_log("logEmail: Database connection not available");
            return false;
        }
        
        $query = "INSERT INTO email_logs (recipient_email, recipient_name, subject, email_type, 
                  related_appraisal_id, related_user_id, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $status = $success ? 'sent' : 'failed';
        
        $stmt->execute([
            $to,
            $recipient_name,
            $subject,
            $email_type,
            $appraisal_id,
            $user_id,
            $status
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Email logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send appraisal submission notification
 * Notifies: Employee, Manager, and HR personnel
 * FIXED: Get database connection properly
 */
function sendAppraisalSubmissionEmails($appraisal_id) {
    try {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            error_log("sendAppraisalSubmissionEmails: Database connection failed");
            return false;
        }
        
        // Get appraisal and user details
        $query = "SELECT a.*, u.name as employee_name, u.email as employee_email, 
                         u.company_id, m.name as manager_name, m.email as manager_email
                  FROM appraisals a
                  JOIN users u ON a.user_id = u.id
                  LEFT JOIN users m ON u.direct_superior = m.id
                  WHERE a.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$appraisal_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            error_log("sendAppraisalSubmissionEmails: Appraisal not found - ID: {$appraisal_id}");
            return false;
        }
        
        $base_url = BASE_URL;
        
        // 1. Send email to employee
        $employee_message = "
            <div class='info-box'>
                <p><strong>✓ Your appraisal has been successfully submitted!</strong></p>
            </div>
            <p><strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "</p>
            <p>Your manager will review your appraisal and provide feedback soon. You will receive another notification once the review is complete.</p>
            <p style='text-align: center;'>
                <a href='{$base_url}/employee/appraisal/view.php?id={$appraisal_id}' class='button'>View My Appraisal</a>
            </p>
        ";
        
        $employee_sent = sendEmail(
            $data['employee_email'],
            'Appraisal Submitted Successfully',
            $employee_message,
            $data['employee_name'],
            [
                'email_type' => 'appraisal_submitted_employee',
                'appraisal_id' => $appraisal_id,
                'user_id' => $data['user_id']
            ]
        );
        
        error_log("Employee email sent to {$data['employee_email']}: " . ($employee_sent ? 'Success' : 'Failed'));
        
        // 2. Send email to manager
        if (!empty($data['manager_email'])) {
            $manager_message = "
                <div class='info-box'>
                    <p><strong>📋 New appraisal ready for your review</strong></p>
                </div>
                <p><strong>{$data['employee_name']}</strong> has submitted their performance appraisal for your review.</p>
                <p><strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "</p>
                <p>Please review and provide your assessment at your earliest convenience.</p>
                <p style='text-align: center;'>
                    <a href='{$base_url}/manager/review/review.php?id={$appraisal_id}' class='button'>Review Appraisal Now</a>
                </p>
            ";
            
            $manager_sent = sendEmail(
                $data['manager_email'],
                'New Appraisal Pending Your Review - ' . $data['employee_name'],
                $manager_message,
                $data['manager_name'],
                [
                    'email_type' => 'appraisal_submitted_manager',
                    'appraisal_id' => $appraisal_id
                ]
            );
            
            error_log("Manager email sent to {$data['manager_email']}: " . ($manager_sent ? 'Success' : 'Failed'));
        }
        
        // 3. Send emails to all HR personnel responsible for this company
        $hr_query = "SELECT DISTINCT u.name, u.email
                     FROM hr_companies hc
                     JOIN users u ON hc.user_id = u.id
                     WHERE hc.company_id = ? AND u.is_hr = TRUE AND u.is_active = TRUE";
        
        $hr_stmt = $db->prepare($hr_query);
        $hr_stmt->execute([$data['company_id']]);
        
        $hr_count = 0;
        while ($hr = $hr_stmt->fetch(PDO::FETCH_ASSOC)) {
            $hr_message = "
                <div class='info-box'>
                    <p><strong>📊 HR Notification: New Appraisal Submitted</strong></p>
                </div>
                <p>A new performance appraisal has been submitted and requires attention.</p>
                <p>
                    <strong>Employee:</strong> {$data['employee_name']}<br>
                    <strong>Manager:</strong> {$data['manager_name']}<br>
                    <strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "
                </p>
                <p>You are receiving this notification as HR personnel responsible for this company.</p>
                <p style='text-align: center;'>
                    <a href='{$base_url}/hr/appraisals/view.php?id={$appraisal_id}' class='button'>View Appraisal</a>
                </p>
            ";
            
            $hr_sent = sendEmail(
                $hr['email'],
                'Employee Appraisal Submitted - HR Notification',
                $hr_message,
                $hr['name'],
                [
                    'email_type' => 'appraisal_submitted_hr',
                    'appraisal_id' => $appraisal_id
                ]
            );
            
            error_log("HR email sent to {$hr['email']}: " . ($hr_sent ? 'Success' : 'Failed'));
            $hr_count++;
        }
        
        error_log("sendAppraisalSubmissionEmails completed for appraisal ID {$appraisal_id}. HR emails sent: {$hr_count}");
        
        return true;
        
    } catch (Exception $e) {
        error_log("Appraisal submission email error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Send manager review completion notification
 * Notifies: Employee and HR personnel
 * FIXED: Get database connection properly
 */
function sendReviewCompletionEmails($appraisal_id) {
    try {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            error_log("sendReviewCompletionEmails: Database connection failed");
            return false;
        }
        
        // Get appraisal details
        $query = "SELECT a.*, u.name as employee_name, u.email as employee_email,
                         u.company_id, m.name as manager_name
                  FROM appraisals a
                  JOIN users u ON a.user_id = u.id
                  LEFT JOIN users m ON a.appraiser_id = m.id
                  WHERE a.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$appraisal_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            error_log("sendReviewCompletionEmails: Appraisal not found - ID: {$appraisal_id}");
            return false;
        }
        
        $base_url = BASE_URL;
        
        // 1. Send email to employee
        $employee_message = "
            <div class='info-box'>
                <p><strong>✓ Your appraisal review is complete!</strong></p>
            </div>
            <p>Your performance appraisal has been reviewed and completed by your manager.</p>
            <p>
                <strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "<br>
                <strong>Reviewed by:</strong> {$data['manager_name']}
            </p>";
        
        if (!empty($data['grade'])) {
            $employee_message .= "
            <div class='info-box'>
                <p style='margin: 0; font-size: 18px;'><strong>Final Grade: {$data['grade']}</strong></p>
            </div>";
        }
        
        $employee_message .= "
            <p>Please log in to view your complete appraisal results and manager feedback.</p>
            <p style='text-align: center;'>
                <a href='{$base_url}/employee/appraisal/view.php?id={$appraisal_id}' class='button'>View My Results</a>
            </p>
        ";
        
        $employee_sent = sendEmail(
            $data['employee_email'],
            'Your Appraisal Review is Complete',
            $employee_message,
            $data['employee_name'],
            [
                'email_type' => 'review_completed_employee',
                'appraisal_id' => $appraisal_id,
                'user_id' => $data['user_id']
            ]
        );
        
        error_log("Employee completion email sent to {$data['employee_email']}: " . ($employee_sent ? 'Success' : 'Failed'));
        
        // 2. Send emails to HR personnel
        $hr_query = "SELECT DISTINCT u.name, u.email
                     FROM hr_companies hc
                     JOIN users u ON hc.user_id = u.id
                     WHERE hc.company_id = ? AND u.is_hr = TRUE AND u.is_active = TRUE";
        
        $hr_stmt = $db->prepare($hr_query);
        $hr_stmt->execute([$data['company_id']]);
        
        $hr_count = 0;
        while ($hr = $hr_stmt->fetch(PDO::FETCH_ASSOC)) {
            $hr_message = "
                <div class='info-box'>
                    <p><strong>✓ HR Notification: Appraisal Review Completed</strong></p>
                </div>
                <p>A performance appraisal review has been completed.</p>
                <p>
                    <strong>Employee:</strong> {$data['employee_name']}<br>
                    <strong>Reviewed by:</strong> {$data['manager_name']}<br>
                    <strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "
                </p>";
            
            if (!empty($data['grade'])) {
                $hr_message .= "<p><strong>Final Grade:</strong> {$data['grade']}</p>";
            }
            
            $hr_message .= "
                <p style='text-align: center;'>
                    <a href='{$base_url}/hr/appraisals/view.php?id={$appraisal_id}' class='button'>View Complete Appraisal</a>
                </p>
            ";
            
            $hr_sent = sendEmail(
                $hr['email'],
                'Appraisal Review Completed - HR Notification',
                $hr_message,
                $hr['name'],
                [
                    'email_type' => 'review_completed_hr',
                    'appraisal_id' => $appraisal_id
                ]
            );
            
            error_log("HR completion email sent to {$hr['email']}: " . ($hr_sent ? 'Success' : 'Failed'));
            $hr_count++;
        }
        
        error_log("sendReviewCompletionEmails completed for appraisal ID {$appraisal_id}. HR emails sent: {$hr_count}");
        
        return true;
        
    } catch (Exception $e) {
        error_log("Review completion email error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}