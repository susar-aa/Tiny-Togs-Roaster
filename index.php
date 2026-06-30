<?php
session_start();

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Handle Login POST
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    require_once 'db.php';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $login_error = "Invalid username or password.";
        }
    } else {
        $login_error = "Please fill in all fields.";
    }
}

// Check authentication
$is_authenticated = isset($_SESSION['user_id']);

if (!$is_authenticated):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Tiny Togs Roster System</title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    
    <!-- SEO & Metadata -->
    <meta name="description" content="Tiny Togs Shift Management & Automated Monthly Roster System. Built with advanced constraint solvers.">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://tinytogs.roaster.suzxlabs.com/">
    <meta property="og:title" content="Tiny Togs Roster System">
    <meta property="og:description" content="Automated monthly shift scheduling, emergency swap coordinator, and leave management engine for Tiny Togs.">
    <meta property="og:image" content="https://tinytogs.roaster.suzxlabs.com/logo.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://tinytogs.roaster.suzxlabs.com/">
    <meta property="twitter:title" content="Tiny Togs Roster System">
    <meta property="twitter:description" content="Automated monthly shift scheduling, emergency swap coordinator, and leave management engine for Tiny Togs.">
    <meta property="twitter:image" content="https://tinytogs.roaster.suzxlabs.com/logo.jpg">
    <!-- Premium Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 1rem;
        }
        .login-card {
            background-color: rgba(255, 255, 255, 0.85);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .login-logo {
            font-family: "Outfit", sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }
        .login-logo span {
            color: var(--primary-color);
            font-weight: 400;
        }
        .login-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }
        .form-group {
            text-align: left;
            margin-bottom: 1.25rem;
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.4rem;
            display: block;
        }
        .login-error {
            background-color: rgba(255, 59, 48, 0.08);
            border: 1px solid rgba(255, 59, 48, 0.2);
            color: var(--danger-color);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo" style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 0.5rem;">
            <img src="logo.jpg" alt="Tiny Togs Logo" style="height: 38px; width: 38px; border-radius: 8px; object-fit: cover; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
            <div>Tiny Togs <span style="color: var(--primary-color);">Roster</span></div>
        </div>
        <div class="login-subtitle">Please sign in to access the system</div>
        
        <?php if ($login_error): ?>
            <div class="login-error"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="index.php">
            <input type="hidden" name="login" value="1">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter username" required autocomplete="username">
            </div>
            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">Sign In</button>
        </form>
        <div style="margin-top: 2rem; border-top: 1px solid rgba(0, 0, 0, 0.08); padding-top: 1.25rem; font-size: 0.78rem; color: var(--text-muted);">
            Developed by <a href="https://suzxlabs.com" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">suzxlabs</a>
        </div>
    </div>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiny Togs - Automated Monthly Shift Timetable System</title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    
    <!-- SEO & Metadata -->
    <meta name="description" content="Tiny Togs Shift Management & Automated Monthly Roster System. Built with advanced constraint solvers.">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://tinytogs.roaster.suzxlabs.com/">
    <meta property="og:title" content="Tiny Togs Roster System">
    <meta property="og:description" content="Automated monthly shift scheduling, emergency swap coordinator, and leave management engine for Tiny Togs.">
    <meta property="og:image" content="https://tinytogs.roaster.suzxlabs.com/logo.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://tinytogs.roaster.suzxlabs.com/">
    <meta property="twitter:title" content="Tiny Togs Roster System">
    <meta property="twitter:description" content="Automated monthly shift scheduling, emergency swap coordinator, and leave management engine for Tiny Togs.">
    <meta property="twitter:image" content="https://tinytogs.roaster.suzxlabs.com/logo.jpg">
    <!-- Premium Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- Include html2canvas and jsPDF for Exporting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body class="active-tab-roster-tab">
    <!-- Top Header -->
    <header>
        <div class="logo-section" style="display: flex; align-items: center; gap: 12px;">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 0.45rem;">Tiny Togs <span>Roster System</span></h1>
                <p style="margin: 2px 0 0 0;">Automated Shift Scheduling & Coverage Engine</p>
            </div>
        </div>
        
        <nav class="nav-tabs">
            <button class="tab-btn active" data-tab="roster-tab">Roster Board</button>
            <button class="tab-btn" data-tab="employees-tab">Employees</button>
            <button class="tab-btn" data-tab="calendar-tab">Calendar Settings</button>
            <button class="tab-btn" data-tab="leave-tab">Leave Planner</button>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <button class="tab-btn" data-tab="users-tab">User Management</button>
            <?php endif; ?>
        </nav>

        <!-- Month & Year Selector -->
        <div class="month-selector-group">
            <label for="select-year">Period:</label>
            <select id="select-year" class="period-select">
                <option value="2026" selected>2026</option>
                <option value="2027">2027</option>
            </select>
            <select id="select-month" class="period-select">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6" selected>June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>
            <div class="user-profile-widget" style="margin-left: 1.5rem; display: flex; align-items: center; gap: 0.5rem; border-left: 1px solid var(--border-color); padding-left: 1.5rem;">
                <span class="user-badge" style="font-weight: 600; font-size: 0.8rem; color: var(--text-main);">Hi, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="index.php?action=logout" class="btn btn-clear" style="min-height: auto; padding: 0.25rem 0.6rem; font-size: 0.75rem; text-decoration: none; border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">Sign Out</a>
            </div>
        </div>
    </header>

    <main>
        
        <!-- TAB 1: ROSTER BOARD -->
        <div id="roster-tab" class="tab-content active">
            <div class="dashboard-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Roster Board</h2>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                            View, generate, export, or execute emergency swaps for the monthly shift schedule.
                        </p>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <button class="btn btn-secondary" id="btn-undo" title="Undo last change" disabled style="display: inline-flex; align-items: center; gap: 6px; padding: 0.4rem 0.8rem; font-size: 0.85rem; height: 36px;">
                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"></path></svg>
                            Undo
                        </button>
                        <button class="btn btn-secondary" id="btn-redo" title="Redo next change" disabled style="display: inline-flex; align-items: center; gap: 6px; padding: 0.4rem 0.8rem; font-size: 0.85rem; height: 36px;">
                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3"></path></svg>
                            Redo
                        </button>
                        <button class="btn btn-clear" id="btn-clear-roster" style="height: 36px;">Clear Roster</button>
                    </div>
                </div>

                <!-- ADD THIS NEW PROGRESS BAR CONTAINER HERE -->
                <div id="roster-progress-container" style="display: none; margin-bottom: 1.5rem; padding: 1.25rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 0.5rem; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.03);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span id="progress-text" style="font-size: 0.85rem; font-weight: 600; color: var(--primary-color);">Initializing generation engine...</span>
                        <span id="progress-percent" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">0%</span>
                    </div>
                    <div style="width: 100%; background-color: #e2e8f0; border-radius: 9999px; height: 10px; overflow: hidden;">
                        <div id="progress-bar-fill" style="width: 0%; height: 100%; background-color: var(--primary-color); transition: width 0.4s ease-out, background-color 0.3s ease;"></div>
                    </div>
                </div>
                <!-- END OF NEW PROGRESS BAR -->

                <div id="export-wrapper" style="background-color: #ffffff; padding: 1.5rem; border-radius: 0.5rem;">
                    <!-- Generated Roster Table Container -->
                    <div id="roster-container">
                        <p style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No roster generated yet for the selected month. Click <strong>Generate Timetable</strong> to build one.
                        </p>
                    </div>

                    <!-- Shift & Calendar Color Legend -->
                    <h3 style="font-size: 1rem; font-weight: 600; margin-top: 2rem; color: #1e293b;">Shift & Day Legend</h3>
                    <div class="legend-container">
                        <div class="legend-item">
                            <span class="legend-color-box shift-F"></span>
                            <span>F (Full Day): 8:30am - 10:00pm</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box shift-M"></span>
                            <span>M (Morning): 8:30am - 5:30pm</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box shift-N"></span>
                            <span>N (Night): 1:00pm - 10:00pm</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box shift-Mw"></span>
                            <span>Mw (Morning Weekend): 8:30am - 8:30pm</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box shift-Nw"></span>
                            <span>Nw (Night Weekend): 11:00am - 10:00pm</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box shift-No"></span>
                            <span>No (Normal Cashier): 8:30am - 7:30pm</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box shift-Nh"></span>
                            <span>Nh (Night Cashier): 10:30am - 9:30pm</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box shift-Off"></span>
                            <span>Off Day (Leave/Day Off)</span>
                        </div>
                    </div>

                    <!-- Calendar Color legend -->
                    <div class="legend-container" style="background-color: #f8fafc; border: 1px solid var(--border-color); margin-top: 0.5rem;">
                        <div class="legend-item">
                            <span class="legend-color-box hl-weekend"></span>
                            <span>Weekend Column (Fri, Sat, Sun)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box hl-poya"></span>
                            <span>Poya Day Column</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box hl-public"></span>
                            <span>Public Holiday Column</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color-box hl-weekday"></span>
                            <span>Standard Weekday Column</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floating Action Bar -->
            <div class="floating-bar">
                <span class="floating-bar-label">Roster Actions:</span>
                <button class="btn btn-export-image" id="btn-export-image">Export Image</button>
                <button class="btn btn-export-pdf" id="btn-export-pdf">Export PDF</button>
                <button class="btn btn-primary" id="btn-generate-roster">Generate Timetable</button>
            </div>
        </div>

        <!-- TAB 2: EMPLOYEE DIRECTORY -->
        <div id="employees-tab" class="tab-content">
            <div class="grid-2">
                <!-- Left Panel: Add/Edit employee -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title" id="employee-form-title">Add Employee</h2>
                    </div>
                    <form id="form-employee">
                        <input type="hidden" name="emp_id" id="emp-id" value="">
                        
                        <div class="form-group">
                            <label for="emp-name" class="form-label">Employee Name</label>
                            <input type="text" name="name" id="emp-name" class="form-control" placeholder="e.g. John Doe" required>
                        </div>

                        <div class="form-group">
                            <label for="emp-skill" class="form-label">Skill Level (Tier)</label>
                            <select name="skill_level" id="emp-skill" class="form-control">
                                <option value="Normal">Normal</option>
                                <option value="Good">Good</option>
                            </select>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                The algorithm guarantees at least 1 "Good" employee works on *every* active shift block.
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="emp-gender" class="form-label">Gender</label>
                            <select name="gender" id="emp-gender" class="form-control">
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="emp-role" class="form-label">Employee Role</label>
                            <select name="role" id="emp-role" class="form-control">
                                <option value="Rotating">Rotating Staff</option>
                                <option value="Anchor">Anchor (Full Time)</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Manager">Manager</option>
                                <option value="Assistant_Manager">Assistant Manager</option>
                            </select>
                            <p class="field-hint">Defines the employee's shift patterns and constraints.</p>
                        </div>

                        <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                            <button type="button" class="btn btn-secondary" id="btn-cancel-edit" style="display: none;">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Employee</button>
                        </div>
                    </form>
                </div>

                <!-- Right Panel: Employee List -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Employee Registry</h2>
                        <span id="employee-count" class="badge badge-normal">0 Employees</span>
                    </div>
                    <div class="table-scroll-panel">
                        <table class="modern-table" id="table-employees">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Skill Level</th>
                                    <th>Role / Status</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <!-- TAB 3: CALENDAR SETTINGS -->
        <div id="calendar-tab" class="tab-content">
            <div class="dashboard-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Calendar & Holiday Manager</h2>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                            Click on any day in the grid to configure it as a Poya Day or Public Holiday. Standard weekends (Saturday & Sunday) are automatically detected by default.
                        </p>
                    </div>
                </div>
                <div class="calendar-picker-container" style="background: #f1f5f9; padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--border-color); margin-top: 1rem;">
                    <div class="calendar-picker" id="calendar-grid-admin">
                        <!-- Generated Dynamically via JS -->
                    </div>
                </div>
            </div>
        </div>


        <!-- TAB 4: LEAVE PLANNER -->
        <div id="leave-tab" class="tab-content">
            <div class="dashboard-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Leave Request Planner (Pre-Generation)</h2>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                            Approve employee time-off requests before generating the roster. These requests are locked in and count towards the 4-day quota.
                        </p>
                    </div>
                </div>
                
                <div class="grid-2">
                    <!-- Left: Select Employee -->
                    <div>
                        <h3 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; color: #475569;">1. Select an Employee</h3>
                        <div id="leave-employee-list" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 0.375rem; padding: 0.5rem; background-color: #f8fafc;">
                            <!-- Dynamically loaded -->
                        </div>
                    </div>
                    
                    <!-- Right: Interactive Calendar grid to toggle leaves -->
                    <div>
                        <h3 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; color: #475569;">
                            2. Toggle Time-Off Dates for: <span id="leave-active-employee-name" style="color: var(--primary-color); font-weight: 700;">Select Employee</span>
                        </h3>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                            Click on a day in the calendar below to toggle Approved Time Off. Quota: exactly 4 days per month.
                        </p>
                        
                        <div class="calendar-picker-container" style="background: #f1f5f9; padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--border-color);">
                            <div class="calendar-picker" id="leave-calendar-grid">
                                <!-- Generated Dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
        <!-- TAB 5: USER MANAGEMENT -->
        <div id="users-tab" class="tab-content">
            <div class="grid-2">
                <!-- Left Panel: Add/Edit User Form -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title" id="user-form-title">Create New User</h2>
                    </div>
                    <form id="form-user" style="margin-top: 1rem;">
                        <input type="hidden" id="user-id" value="">
                        
                        <div class="form-group">
                            <label for="user-username" class="form-label">Username</label>
                            <input type="text" id="user-username" class="form-control" placeholder="e.g. manager_lisa" required autocomplete="username">
                        </div>
                        
                        <div class="form-group">
                            <label for="user-password" class="form-label" id="label-user-password">Password</label>
                            <input type="password" id="user-password" class="form-control" placeholder="Enter password (min 6 characters)" required autocomplete="new-password">
                            <span id="password-help-text" style="font-size: 0.75rem; color: var(--text-muted); display: none; margin-top: 0.25rem;">Leave blank to keep current password.</span>
                        </div>

                        <div class="form-group">
                            <label for="user-role" class="form-label">User Level</label>
                            <select id="user-role" class="form-control">
                                <option value="Manager">Manager</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                            <button type="button" class="btn btn-secondary" id="btn-cancel-user-edit" style="display: none;">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="btn-save-user">Create User</button>
                        </div>
                    </form>
                </div>

                <!-- Right Panel: User Registry -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">User Registry</h2>
                    </div>
                    <div class="table-scroll-panel">
                        <table class="modern-table" id="table-users">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>User Level</th>
                                    <th>Created Date</th>
                                    <th style="text-align: right; width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Developer Credits Footer -->
    <footer class="app-footer">
        <p>&copy; 2026 Tiny Togs Shift Management System. All rights reserved.</p>
        <p>Developed by <a href="https://suzxlabs.com" target="_blank" rel="noopener noreferrer">suzxlabs</a></p>
    </footer>

    <!-- Post-Generation: Emergency Swap Modal -->
    <div class="modal-overlay" id="swap-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Emergency Leave & Swap</h3>
                <button class="modal-close" id="btn-close-modal">&times;</button>
            </div>
            <div style="font-size: 0.9rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                <p>Employee: <strong id="swap-modal-emp-name">-</strong></p>
                <p>Date: <strong id="swap-modal-date">-</strong></p>
                <p>Scheduled Shift: <strong id="swap-modal-shift-code">-</strong></p>
            </div>
            
            <form id="form-emergency-swap">
                <input type="hidden" id="swap-emp-id">
                <input type="hidden" id="swap-date">
                <input type="hidden" id="swap-original-shift">
                
                <div class="form-group">
                    <label for="swap-action-type" class="form-label">Action Type</label>
                    <select id="swap-action-type" class="form-control">
                        <option value="swap">Swap with Replacement Employee</option>
                        <option value="change">Change Shift Directly</option>
                    </select>
                </div>

                <div id="swap-action-swap-group">
                    <div class="form-group">
                        <label for="select-replacement" class="form-label">Choose Replacement Employee</label>
                        <select id="select-replacement" class="form-control">
                            <!-- Populated dynamically with employees who are OFF on this day and qualify -->
                        </select>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                            Note: The replacement employee will take over this shift (if they were OFF) or be upgraded to a Full Day (F) shift (if they were working the opposite shift), while the original employee is set to OFF.
                        </p>
                    </div>
                </div>

                <div id="swap-action-change-group" style="display: none;">
                    <div class="form-group">
                        <label for="select-new-shift" class="form-label">Select New Shift</label>
                        <select id="select-new-shift" class="form-control">
                            <!-- Populated dynamically from DB shifts table -->
                        </select>
                    </div>
                </div>

                <div id="swap-validation-warning" style="display: none; padding: 0.75rem; background-color: #fee2e2; border: 1px solid #fecaca; border-radius: 0.375rem; color: #991b1b; font-size: 0.8rem; margin-bottom: 1rem;">
                    <!-- Validation error text -->
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" id="btn-cancel-swap">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="btn-submit-swap">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Calendar Day Modal -->
    <div class="modal-overlay" id="calendar-edit-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Edit Calendar Day</h3>
                <button class="modal-close" id="btn-close-calendar-modal">&times;</button>
            </div>
            
            <form id="form-edit-calendar">
                <input type="hidden" id="cal-date-input">
                
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="text" id="cal-date-display" class="form-control" readonly style="background-color: #f1f5f9; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label for="cal-day-type" class="form-label">Day Classification Type</label>
                    <select id="cal-day-type" class="form-control">
                        <option value="Default">Default (Auto API / Weekend)</option>
                        <option value="Weekday">Force Regular Weekday</option>
                        <option value="Poya">Force Poya Day</option>
                        <option value="Public Holiday">Force Public Holiday</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cal-desc" class="form-label">Description / Holiday Name</label>
                    <input type="text" id="cal-desc" class="form-control" placeholder="e.g. Saturday, Wesak Poya">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" id="btn-cancel-calendar-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Desktop Recommendation Warning Overlay for Mobile -->
    <div class="mobile-warning-overlay">
        <div class="mobile-warning-card">
            <div class="mobile-warning-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 32px; height: 32px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h2>Desktop View Recommended</h2>
            <p>The Tiny Togs Roster Management System is optimized for larger displays. Please open this application on a desktop or laptop to view and manage the shift roster perfectly.</p>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        window.currentUserRole = <?= json_encode($_SESSION['role'] ?? 'Manager') ?>;
    </script>
    <!-- App JavaScript Logic -->
    <script src="app.js"></script>
</body>
</html>