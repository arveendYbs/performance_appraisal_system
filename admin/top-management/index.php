<?php
// top-management/index.php
require_once __DIR__ . '/../../config/config.php';

// Check if user is Top Management
if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isTopManagement()) {
    redirect(BASE_URL . '/index.php', 'Access denied. Top Management only.', 'error');
}

require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

// Get filter parameters
$company_filter = $_GET['company'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

try {
    // Get Top Management companies
    $top_mgmt_companies = $user->getTopManagementCompanies();
    
    // Build base query conditions
    $base_conditions = "JOIN users u ON a.user_id = u.id
                        JOIN companies c ON u.company_id = c.id
                        JOIN top_management_companies tmc ON c.id = tmc.company_id
                        WHERE tmc.user_id = ?";
    $base_params = [$_SESSION['user_id']];
    
    if ($company_filter) {
        $base_conditions .= " AND c.id = ?";
        $base_params[] = $company_filter;
    }
    
    if ($year_filter) {
        $base_conditions .= " AND YEAR(a.appraisal_period_from) = ?";
        $base_params[] = $year_filter;
    }
    
    // Overall Statistics
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as draft,
                        SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                        SUM(CASE WHEN a.status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM appraisals a
                    $base_conditions";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute($base_params);
    $overall_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistics by Department
    $dept_query = "SELECT 
                        u.department,
                        c.name as company_name,
                        COUNT(*) as total_appraisals,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN a.status = 'submitted' OR a.status = 'in_review' THEN 1 ELSE 0 END) as pending_review,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as not_submitted,
                        AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score
                   FROM appraisals a
                   $base_conditions
                   GROUP BY u.department, c.name
                   ORDER BY c.name, u.department";
    
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute($base_params);
    $dept_stats = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics by Company
    $company_query = "SELECT 
                        c.id,
                        c.name as company_name,
                        COUNT(*) as total_appraisals,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN a.status = 'submitted' OR a.status = 'in_review' THEN 1 ELSE 0 END) as pending_review,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as not_submitted,
                        AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score,
                        COUNT(DISTINCT u.id) as total_employees
                      FROM appraisals a
                      $base_conditions
                      GROUP BY c.id, c.name
                      ORDER BY c.name";
    
    $company_stmt = $db->prepare($company_query);
    $company_stmt->execute($base_params);
    $company_stats = $company_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grade Distribution
    $grade_query = "SELECT 
                        a.grade,
                        COUNT(*) as count
                    FROM appraisals a
                    $base_conditions
                    AND a.status = 'completed'
                    AND a.grade IS NOT NULL
                    GROUP BY a.grade
                    ORDER BY a.grade";
    
    $grade_stmt = $db->prepare($grade_query);
    $grade_stmt->execute($base_params);
    $grade_distribution = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent Activity
    $recent_query = "SELECT 
                        a.id,
                        a.status,
                        a.grade,
                        u.name as employee_name,
                        u.department,
                        c.name as company_name,
                        a.updated_at
                     FROM appraisals a
                     $base_conditions
                     ORDER BY a.updated_at DESC
                     LIMIT 10";
    
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute($base_params);
    $recent_activity = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Top Management Dashboard error: " . $e->getMessage());
    $overall_stats = [];
    $dept_stats = [];
    $company_stats = [];
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-graph-up me-2"></i>Top Management Dashboard
            <small class="text-muted">Comprehensive Overview</small>
        </h1>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company">
                            <option value="">All Companies</option>
                            <?php foreach ($top_mgmt_companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" 
                                    <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="?" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Overall Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Appraisals</h6>
                <h2 class="mb-0"><?php echo $overall_stats['total'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Completed</h6>
                <h2 class="mb-0 text-success"><?php echo $overall_stats['completed'] ?? 0; ?></h2>
                <small class="text-muted">
                    <?php 
                    $total = $overall_stats['total'] ?? 0;
                    $completed = $overall_stats['completed'] ?? 0;
                    echo $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%'; 
                    ?>
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Pending Review</h6>
                <h2 class="mb-0 text-warning">
                    <?php echo ($overall_stats['submitted'] ?? 0) + ($overall_stats['in_review'] ?? 0); ?>
                </h2>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Submitted</h6>
                <h2 class="mb-0 text-info"><?php echo $overall_stats['submitted'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-secondary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">In Review</h6>
                <h2 class="mb-0 text-secondary"><?php echo $overall_stats['in_review'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Not Submitted</h6>
                <h2 class="mb-0 text-danger"><?php echo $overall_stats['draft'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Statistics by Company -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Progress by Company</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th class="text-center">Employees</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">Pending Review</th>
                                <th class="text-center">Not Submitted</th>
                                <th class="text-center">Avg Score</th>
                                <th class="text-center">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($company_stats)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No data available</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($company_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['company_name']); ?></strong></td>
                                <td class="text-center"><?php echo $stat['total_employees']; ?></td>
                                <td class="text-center"><?php echo $stat['total_appraisals']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $stat['completed']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning"><?php echo $stat['pending_review']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $stat['not_submitted']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($stat['avg_score']): ?>
                                    <strong><?php echo round($stat['avg_score'], 1); ?>%</strong>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $progress = $stat['total_appraisals'] > 0 
                                        ? round(($stat['completed'] / $stat['total_appraisals']) * 100, 1) 
                                        : 0;
                                    ?>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo $progress; ?>%">
                                            <?php echo $progress; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics by Department -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Progress by Department</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Company</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">Pending Review</th>
                                <th class="text-center">Not Submitted</th>
                                <th class="text-center">Avg Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dept_stats)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No data available</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($dept_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['department']); ?></strong></td>
                                <td><?php echo htmlspecialchars($stat['company_name']); ?></td>
                                <td class="text-center"><?php echo $stat['total_appraisals']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $stat['completed']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning"><?php echo $stat['pending_review']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $stat['not_submitted']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($stat['avg_score']): ?>
                                    <strong><?php echo round($stat['avg_score'], 1); ?>%</strong>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grade Distribution and Recent Activity Row -->
<div class="row">
    <!-- Grade Distribution -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Grade Distribution</h5>
            </div>
            <div class="card-body">
                <?php if (empty($grade_distribution)): ?>
                <p class="text-muted text-center py-4">No completed appraisals yet</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th class="text-center">Count</th>
                                <th>Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_graded = array_sum(array_column($grade_distribution, 'count'));
                            foreach ($grade_distribution as $grade): 
                                $percentage = ($grade['count'] / $total_graded) * 100;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($grade['grade']); ?></strong></td>
                                <td class="text-center"><?php echo $grade['count']; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo round($percentage, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activity)): ?>
                <p class="text-muted text-center py-4">No recent activity</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_activity as $activity): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($activity['department']); ?> - 
                                    <?php echo htmlspecialchars($activity['company_name']); ?>
                                </small>
                            </div>
                            <span class="badge <?php echo getStatusBadgeClass($activity['status']); ?>">
                                <?php echo ucwords(str_replace('_', ' ', $activity['status'])); ?>
                            </span>
                        </div>
                        <small class="text-muted">
                            <?php echo ($activity['updated_at']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>