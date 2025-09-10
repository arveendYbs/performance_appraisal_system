
<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'];
?>

<div class="sidebar" id="sidebar">
    <div class="logo">
        <h4><i class="bi bi-clipboard-data"></i> PAS</h4>
        <small>Performance Appraisal System</small>
    </div>
    
    <nav class="nav flex-column">
        <!-- Dashboard -->
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/index.php" 
               class="nav-link <?php echo ($current_page == 'index.php' && strpos($current_path, '/admin/') === false && strpos($current_path, '/employee/') === false && strpos($current_path, '/manager/') === false) ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>

        <?php if (hasRole('admin')): ?>
        <!-- Administration Section -->
        <div class="nav-header">Administration</div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/forms/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/forms/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> Manage Forms
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/sections/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/sections/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-list-ul"></i> Form Sections
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/questions/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/questions/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-question-circle"></i> Questions
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/users/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/users/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Manage Users
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/audit/" 
               class="nav-link <?php echo (strpos($current_path, '/admin/audit/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> Audit Logs
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasRole('manager') || hasRole('admin')): ?>
        <!-- Management Section -->
        <div class="nav-header">Management</div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/review/pending.php" 
               class="nav-link <?php echo (strpos($current_path, '/manager/review/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-check"></i> Review Appraisals
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/team.php" 
               class="nav-link <?php echo ($current_page == 'team.php') ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> My Team
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/manager/reports.php" 
               class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i> Reports
            </a>
        </div>
        <?php endif; ?>

        <!-- Employee Section -->
        <div class="nav-header">Employee</div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/employee/appraisal/" 
               class="nav-link <?php echo (strpos($current_path, '/employee/appraisal/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-data"></i> My Appraisal
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/employee/history.php" 
               class="nav-link <?php echo ($current_page == 'history.php' && strpos($current_path, '/employee/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> Appraisal History
            </a>
        </div>
        
        <div class="nav-item">
            <a href="<?php echo BASE_URL; ?>/employee/profile.php" 
               class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="bi bi-person"></i> My Profile
            </a>
        </div>
    </nav>
</div>

<div class="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-link mobile-toggle me-2" id="sidebar-toggle" type="button">
            <i class="bi bi-list fs-4"></i>
        </button>
        <h5 class="mb-0">
            <?php
            $page_titles = [
                'index.php' => 'Dashboard',
                'forms' => 'Form Management',
                'sections' => 'Section Management', 
                'questions' => 'Question Management',
                'users' => 'User Management',
                'audit' => 'Audit Logs',
                'pending.php' => 'Pending Reviews',
                'team.php' => 'My Team',
                'reports.php' => 'Reports',
                'appraisal' => 'My Appraisal',
                'history.php' => 'Appraisal History',
                'profile.php' => 'My Profile'
            ];
            
            $current_title = 'Dashboard';
            foreach ($page_titles as $key => $title) {
                if (strpos($current_path, $key) !== false || $current_page == $key) {
                    $current_title = $title;
                    break;
                }
            }
            echo $current_title;
            ?>
        </h5>
    </div>
    
    <div class="dropdown">
        <button class="btn btn-link dropdown-toggle d-flex align-items-center p-0" type="button" data-bs-toggle="dropdown">
            <div class="me-3 text-end">
                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_position']); ?></small>
            </div>
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-person-fill text-white"></i>
            </div>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <div class="dropdown-header">
                    <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_email']); ?></small>
                </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/employee/profile.php">
                <i class="bi bi-person me-2"></i>My Profile
            </a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/employee/appraisal/">
                <i class="bi bi-clipboard-data me-2"></i>My Appraisal
            </a></li>
            <?php if (hasRole('admin')): ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/">
                <i class="bi bi-gear me-2"></i>Administration
            </a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/auth/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <!-- Flash messages -->
    <?php displayFlashMessage(); ?>
