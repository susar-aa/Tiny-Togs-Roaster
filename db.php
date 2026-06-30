<?php
// db.php - Database connection for Tiny Togs Shift Management System

// Simple helper to load .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Split key and value
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Remove outer quotes if present
            if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Load .env from current directory
loadEnv(__DIR__ . '/.env');

// Helper to retrieve env variables with a default fallback
function getEnvVar($key, $default = '') {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

$host = getEnvVar('DB_HOST', 'localhost');
$db   = getEnvVar('DB_NAME', 'tiny_togs_roster');
$user = getEnvVar('DB_USER', 'root');
$pass = getEnvVar('DB_PASS', '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     header('Content-Type: application/json');
     echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
     exit;
}

function ensureInitialStateInHistory($pdo, $year, $month) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['roster_history_index'][$year][$month])) {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM roster_history WHERE year = ? AND month = ?");
        $stmt_count->execute([$year, $month]);
        if ($stmt_count->fetchColumn() == 0) {
            $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = "$year-$month_str-01";
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $end_date = "$year-$month_str-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("SELECT emp_id, date, shift_code, is_emergency_swap, swapped_with_emp_id FROM monthly_roster WHERE date BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            $current_state = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $state_json = json_encode($current_state);

            $stmt_ins = $pdo->prepare("INSERT INTO roster_history (year, month, history_index, state_json) VALUES (?, ?, 0, ?)");
            $stmt_ins->execute([$year, $month, $state_json]);
            $_SESSION['roster_history_index'][$year][$month] = 0;
        } else {
            $stmt_max = $pdo->prepare("SELECT MAX(history_index) FROM roster_history WHERE year = ? AND month = ?");
            $stmt_max->execute([$year, $month]);
            $_SESSION['roster_history_index'][$year][$month] = (int)$stmt_max->fetchColumn();
        }
    }
}

function saveRosterStateToHistory($pdo, $year, $month) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    ensureInitialStateInHistory($pdo, $year, $month);

    $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
    $start_date = "$year-$month_str-01";
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $end_date = "$year-$month_str-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("SELECT emp_id, date, shift_code, is_emergency_swap, swapped_with_emp_id FROM monthly_roster WHERE date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $current_state = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $state_json = json_encode($current_state);

    $current_idx = $_SESSION['roster_history_index'][$year][$month];

    $stmt_del = $pdo->prepare("DELETE FROM roster_history WHERE year = ? AND month = ? AND history_index > ?");
    $stmt_del->execute([$year, $month, $current_idx]);

    $new_idx = $current_idx + 1;
    $stmt_ins = $pdo->prepare("INSERT INTO roster_history (year, month, history_index, state_json) VALUES (?, ?, ?, ?)");
    $stmt_ins->execute([$year, $month, $new_idx, $state_json]);

    $_SESSION['roster_history_index'][$year][$month] = $new_idx;
}
?>
