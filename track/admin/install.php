<?php
/**
 * Affiliate Tracking System - Installation Script
 * 
 * This script helps set up the Affiliate Tracking System by:
 * 1. Checking system requirements
 * 2. Creating database tables
 * 3. Setting up initial configuration
 * 4. Creating default admin user
 * 
 * Usage: Run this script once during initial installation.
 * IMPORTANT: Delete this file after successful installation for security.
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Define paths
define('ROOT_PATH', __DIR__ . '/../..');
define('CONFIG_FILE', __DIR__ . '/db.php');

// Check if already installed
if (file_exists(CONFIG_FILE) && filesize(CONFIG_FILE) > 100) {
    // Check if tables exist
    try {
        require_once CONFIG_FILE;
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'partners'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            die("System appears to be already installed. Please delete this file for security.");
        }
    } catch (Exception $e) {
        // Database connection failed, continue with installation
    }
}

// Installation steps
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// HTML Header
function renderHeader() {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Affiliate Tracking System - Installation</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f8f9fa;
            }
            .install-container {
                max-width: 800px;
                margin: 2rem auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                padding: 2rem;
            }
            .step-indicator {
                display: flex;
                justify-content: space-between;
                margin-bottom: 2rem;
                position: relative;
            }
            .step-indicator::before {
                content: "";
                position: absolute;
                top: 20px;
                left: 0;
                right: 0;
                height: 2px;
                background: #dee2e6;
                z-index: 1;
            }
            .step {
                text-align: center;
                position: relative;
                z-index: 2;
            }
            .step-circle {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #e9ecef;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 0.5rem;
                font-weight: bold;
            }
            .step.active .step-circle {
                background: #0d6efd;
                color: white;
            }
            .step.completed .step-circle {
                background: #198754;
                color: white;
            }
            .step-label {
                font-size: 0.875rem;
                color: #6c757d;
            }
            .step.active .step-label {
                color: #0d6efd;
                font-weight: 500;
            }
            .step.completed .step-label {
                color: #198754;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <div class="install-container">
            <h1 class="text-center mb-4">Affiliate Tracking System Installation</h1>
            
            <div class="step-indicator">
                <div class="step ' . ($GLOBALS['step'] >= 1 ? ($GLOBALS['step'] > 1 ? 'completed' : 'active') : '') . '">
                    <div class="step-circle">1</div>
                    <div class="step-label">Requirements</div>
                </div>
                <div class="step ' . ($GLOBALS['step'] >= 2 ? ($GLOBALS['step'] > 2 ? 'completed' : 'active') : '') . '">
                    <div class="step-circle">2</div>
                    <div class="step-label">Database</div>
                </div>
                <div class="step ' . ($GLOBALS['step'] >= 3 ? ($GLOBALS['step'] > 3 ? 'completed' : 'active') : '') . '">
                    <div class="step-circle">3</div>
                    <div class="step-label">Configuration</div>
                </div>
                <div class="step ' . ($GLOBALS['step'] >= 4 ? 'completed' : '') . '">
                    <div class="step-circle">4</div>
                    <div class="step-label">Finish</div>
                </div>
            </div>
    ';
}

// HTML Footer
function renderFooter() {
    echo '
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    ';
}

// Step 1: Requirements Check
function step1() {
    echo '<h2>System Requirements Check</h2>';
    
    $requirements = [
        'PHP Version >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'cURL Extension' => extension_loaded('curl'),
        'JSON Extension' => extension_loaded('json'),
        'Mbstring Extension' => extension_loaded('mbstring'),
        'Fileinfo Extension' => extension_loaded('fileinfo'),
        'Write Permissions (admin directory)' => is_writable(__DIR__),
        'Write Permissions (admin/db.php)' => is_writable(CONFIG_FILE) || !file_exists(CONFIG_FILE),
    ];
    
    $allGood = true;
    
    echo '<div class="table-responsive"><table class="table table-striped">';
    echo '<thead><tr><th>Requirement</th><th>Status</th></tr></thead><tbody>';
    
    foreach ($requirements as $requirement => $status) {
        echo '<tr>';
        echo '<td>' . $requirement . '</td>';
        echo '<td>';
        if ($status) {
            echo '<span class="badge bg-success">OK</span>';
        } else {
            echo '<span class="badge bg-danger">Missing</span>';
            $allGood = false;
        }
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
    
    if ($allGood) {
        echo '<div class="alert alert-success">All requirements met! You can proceed with the installation.</div>';
        echo '<a href="?step=2" class="btn btn-primary">Continue to Database Setup</a>';
    } else {
        echo '<div class="alert alert-danger">Some requirements are not met. Please fix the issues before continuing.</div>';
        echo '<button class="btn btn-secondary" onclick="location.reload()">Refresh</button>';
    }
}

// Step 2: Database Configuration
function step2() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save database configuration
        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        $db_char = $_POST['db_char'] ?? 'utf8mb4';
        
        // Create db.php file
        $db_config = "<?php
// Database configuration
define('DB_HOST', '" . addslashes($db_host) . "');
define('DB_NAME', '" . addslashes($db_name) . "');
define('DB_USER', '" . addslashes($db_user) . "');
define('DB_PASS', '" . addslashes($db_pass) . "');
define('DB_CHAR', '" . addslashes($db_char) . "');

// Create PDO connection
\$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHAR;
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
} catch (\\PDOException \$e) {
    error_log('Database connection failed: ' . \$e->getMessage());
    die('Database connection failed. Please check your configuration.');
}
";
        
        if (file_put_contents(CONFIG_FILE, $db_config)) {
            // Test database connection
            try {
                require_once CONFIG_FILE;
                // Connection successful, proceed to next step
                header('Location: ?step=3');
                exit;
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Database connection failed: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-danger">Failed to write database configuration file.</div>';
        }
    }
    
    echo '<h2>Database Configuration</h2>';
    echo '<p>Please enter your database connection details:</p>';
    
    echo '<form method="post">';
    echo '<div class="mb-3">';
    echo '<label for="db_host" class="form-label">Database Host</label>';
    echo '<input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>';
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label for="db_name" class="form-label">Database Name</label>';
    echo '<input type="text" class="form-control" id="db_name" name="db_name" required>';
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label for="db_user" class="form-label">Database User</label>';
    echo '<input type="text" class="form-control" id="db_user" name="db_user" required>';
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label for="db_pass" class="form-label">Database Password</label>';
    echo '<input type="password" class="form-control" id="db_pass" name="db_pass">';
    echo '</div>';
    
    echo '<div class="mb-3">';
    echo '<label for="db_char" class="form-label">Character Set</label>';
    echo '<input type="text" class="form-control" id="db_char" name="db_char" value="utf8mb4">';
    echo '</div>';
    
    echo '<button type="submit" class="btn btn-primary">Save Configuration</button>';
    echo '<a href="?step=1" class="btn btn-secondary ms-2">Back</a>';
    echo '</form>';
}

// Step 3: Database Setup
function step3() {
    try {
        require_once CONFIG_FILE;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Create database tables
            $sql = file_get_contents(ROOT_PATH . '/DATABASE_SCHEMA.sql');
            
            if ($pdo->exec($sql) !== false) {
                // Tables created successfully
                header('Location: ?step=4');
                exit;
            } else {
                echo '<div class="alert alert-danger">Failed to create database tables.</div>';
            }
        }
        
        echo '<h2>Database Setup</h2>';
        echo '<p>Creating database tables...</p>';
        
        // Check if tables already exist
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'partners'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo '<div class="alert alert-warning">Database tables already exist. Continuing to next step.</div>';
            echo '<a href="?step=4" class="btn btn-primary">Continue</a>';
        } else {
            echo '<form method="post">';
            echo '<button type="submit" class="btn btn-primary">Create Database Tables</button>';
            echo '<a href="?step=2" class="btn btn-secondary ms-2">Back</a>';
            echo '</form>';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Database connection failed: ' . $e->getMessage() . '</div>';
        echo '<a href="?step=2" class="btn btn-secondary">Back to Database Configuration</a>';
    }
}

// Step 4: Finish
function step4() {
    echo '<h2>Installation Complete</h2>';
    echo '<div class="alert alert-success">Congratulations! The Affiliate Tracking System has been successfully installed.</div>';
    
    echo '<h4>Next Steps:</h4>';
    echo '<ol>';
    echo '<li>Delete the <code>install.php</code> file for security reasons</li>';
    echo '<li>Access the admin panel at <a href="/track/admin/">/track/admin/</a></li>';
    echo '<li>Configure your first partner</li>';
    echo '<li>Set up cron jobs for postback processing</li>';
    echo '</ol>';
    
    echo '<div class="alert alert-warning">';
    echo '<strong>Security Notice:</strong> Please delete the install.php file immediately to prevent unauthorized access.';
    echo '</div>';
    
    echo '<a href="/track/admin/" class="btn btn-primary">Go to Admin Panel</a>';
}

// Main execution
renderHeader();

switch ($step) {
    case 1:
        step1();
        break;
    case 2:
        step2();
        break;
    case 3:
        step3();
        break;
    case 4:
        step4();
        break;
    default:
        step1();
}

renderFooter();
?>