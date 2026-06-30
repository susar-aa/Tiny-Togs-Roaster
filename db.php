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
?>
