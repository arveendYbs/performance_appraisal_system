<?php


require_once 'config/config.php';


// Find and replace the existing query with this:
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Debug: Log the current user's ID
    error_log("Manager ID: " . $_SESSION['user_id']);
    
    // Get team members with their latest appraisal status
    $query = "SELECT 
        u.id,
        u.name,
        u.emp_number,
        u.position,
        u.department,
        u.date_joined,
        COALESCE(a.status, 'pending') as appraisal_status,
        a.id as appraisal_id
    FROM users u
    LEFT JOIN (
        SELECT user_id, MAX(created_at) as latest_date
        FROM appraisals
        GROUP BY user_id
    ) latest ON u.id = latest.user_id
    LEFT JOIN appraisals a ON latest.user_id = a.user_id 
        AND latest.latest_date = a.created_at
    WHERE u.direct_superior = :manager_id 
        AND u.is_active = 1
    ORDER BY u.name";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':manager_id', $_SESSION['user_id']);
    $stmt->execute();


    // Debug: Count results
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Number of team members found: " . count($team_members));
    
    // Debug: Print team members
    foreach ($team_members as $member) {
        error_log("Member: " . $member['name'] . " (ID: " . $member['id'] . ")");
    }
        echo "<pre>";
print_r($team_members);
echo "</pre>";
exit;
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
}