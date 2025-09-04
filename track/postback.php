<?php
/**
 * Affiliate Tracking System - Postback Handler
 * 
 * This script handles incoming postbacks from affiliate networks.
 * It validates the request, processes the data, and stores it for later processing.
 * 
 * Features:
 * - Partner validation
 * - IP whitelisting
 * - Data sanitization
 * - Queue-based processing
 * - Logging capabilities
 * - Telegram notifications
 * - Google Sheets integration
 */

// Set execution time limit
set_time_limit(30);

// Set memory limit
ini_set('memory_limit', '64M');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);

// Include database connection
require_once __DIR__ . '/admin/db.php';

// Get partner ID from query parameters
$partner_id = $_GET['pid'] ?? $_POST['pid'] ?? null;

// Validate partner ID
if (empty($partner_id)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Partner ID is required']);
    exit;
}

// Validate partner exists and get configuration
try {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$partner) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Partner not found']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
    error_log('Database error in postback.php: ' . $e->getMessage());
    exit;
}

// IP Whitelisting Check
if ($partner['ip_whitelist_enabled']) {
    $allowed_ips = json_decode($partner['allowed_ips'], true) ?: [];
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'IP not allowed']);
        
        // Log unauthorized access attempt
        if ($partner['logging_enabled']) {
            error_log("Unauthorized postback attempt from IP: {$client_ip} for partner: {$partner_id}");
        }
        
        exit;
    }
}

// Collect all request data
$postback_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'partner_id' => $partner_id,
    'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'method' => $_SERVER['REQUEST_METHOD'],
    'get_params' => $_GET,
    'post_params' => $_POST,
    'headers' => getallheaders()
];

// Extract ClickID
$clickid = null;
$clickid_keys = json_decode($partner['clickid_keys'], true) ?: ['clickid', 'cid'];
foreach ($clickid_keys as $key) {
    if (isset($_GET[$key])) {
        $clickid = trim($_GET[$key]);
        break;
    }
    if (isset($_POST[$key])) {
        $clickid = trim($_POST[$key]);
        break;
    }
}

// Extract Sum (conversion value)
$sum = null;
$sum_keys = json_decode($partner['sum_keys'], true) ?: ['sum', 'payout'];
foreach ($sum_keys as $key) {
    if (isset($_GET[$key])) {
        $sum = filter_var($_GET[$key], FILTER_VALIDATE_FLOAT);
        break;
    }
    if (isset($_POST[$key])) {
        $sum = filter_var($_POST[$key], FILTER_VALIDATE_FLOAT);
        break;
    }
}

// Apply sum mapping if configured
$sum_mapping = 0;
if ($sum !== null && !empty($partner['sum_mapping'])) {
    $mappings = json_decode($partner['sum_mapping'], true) ?: [];
    foreach ($mappings as $mapping) {
        if (isset($mapping['from']) && isset($mapping['to'])) {
            $from = filter_var($mapping['from'], FILTER_VALIDATE_FLOAT);
            $to = filter_var($mapping['to'], FILTER_VALIDATE_FLOAT);
            
            if ($from !== false && $to !== false && $sum == $from) {
                $sum_mapping = $to;
                break;
            }
        }
    }
}

// Collect extra parameters
$extra_params = [];
$all_params = array_merge($_GET, $_POST);
foreach ($all_params as $key => $value) {
    // Skip known system parameters
    if (!in_array($key, ['pid', ...$clickid_keys, ...$sum_keys])) {
        $extra_params[$key] = $value;
    }
}

// Prepare data for queue
$queue_data = [
    'partner_id' => $partner_id,
    'timestamp' => $postback_data['timestamp'],
    'click_id' => $clickid,
    'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
    'client_ip' => $postback_data['client_ip'],
    'user_agent' => $postback_data['user_agent'],
    'method' => $postback_data['method'],
    'sum' => $sum,
    'sum_mapping' => $sum_mapping,
    'extra_params' => $extra_params
];

// Add to processing queue
try {
    $stmt = $pdo->prepare("INSERT INTO postback_queue (partner_id, data, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$partner_id, json_encode($queue_data)]);
    $queue_id = $pdo->lastInsertId();
    
    // Log successful queue insertion
    if ($partner['logging_enabled']) {
        error_log("Postback queued for partner {$partner_id}, queue ID: {$queue_id}");
    }
    
    // Send immediate response
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Postback received and queued for processing',
        'queue_id' => $queue_id
    ]);
    
    // Process immediately if configured (optional)
    if (defined('PROCESS_IMMEDIATELY') && PROCESS_IMMEDIATELY) {
        process_postback_immediately($queue_data, $partner);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to queue postback']);
    error_log('Failed to queue postback: ' . $e->getMessage());
    exit;
}

/**
 * Process postback immediately (for testing/debugging)
 */
function process_postback_immediately($data, $partner) {
    global $pdo;
    
    // Update statistics
    try {
        $stmt = $pdo->prepare("
            INSERT INTO detailed_stats 
            (partner_id, timestamp, click_id, url, sum, sum_mapping, extra_params) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            timestamp = VALUES(timestamp),
            click_id = VALUES(click_id),
            url = VALUES(url),
            sum = VALUES(sum),
            sum_mapping = VALUES(sum_mapping),
            extra_params = VALUES(extra_params)
        ");
        $stmt->execute([
            $data['partner_id'],
            $data['timestamp'],
            $data['click_id'],
            $data['url'],
            $data['sum'],
            $data['sum_mapping'],
            json_encode($data['extra_params'])
        ]);
        
        // Update summary statistics
        $stmt = $pdo->prepare("
            INSERT INTO summary_stats 
            (partner_id, total_requests, successful_redirects) 
            VALUES (?, 1, 1)
            ON DUPLICATE KEY UPDATE 
            total_requests = total_requests + 1,
            successful_redirects = successful_redirects + 1
        ");
        $stmt->execute([$data['partner_id']]);
        
        // Send Telegram notification if enabled
        if ($partner['telegram_enabled']) {
            send_telegram_notification($data, $partner);
        }
        
        // Update Google Sheets if enabled
        if (!empty($partner['google_spreadsheet_id']) && !empty($partner['google_sheet_name'])) {
            update_google_sheet($data, $partner);
        }
        
    } catch (Exception $e) {
        error_log('Error processing postback immediately: ' . $e->getMessage());
    }
}

/**
 * Send Telegram notification
 */
function send_telegram_notification($data, $partner) {
    // Check if global or partner-specific Telegram is enabled
    $use_partner_telegram = $partner['partner_telegram_enabled'] && 
                           !empty($partner['partner_telegram_bot_token']) && 
                           !empty($partner['partner_telegram_channel_id']);
    
    if ($use_partner_telegram) {
        $bot_token = $partner['partner_telegram_bot_token'];
        $channel_id = $partner['partner_telegram_channel_id'];
    } else {
        // Get global settings
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('telegram_bot_token', 'telegram_channel_id', 'telegram_globally_enabled')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!($settings['telegram_globally_enabled'] ?? false)) {
            return; // Global Telegram not enabled
        }
        
        $bot_token = $settings['telegram_bot_token'] ?? '';
        $channel_id = $settings['telegram_channel_id'] ?? '';
    }
    
    if (empty($bot_token) || empty($channel_id)) {
        return; // Telegram not properly configured
    }
    
    // Check whitelist if enabled
    if ($partner['telegram_whitelist_enabled'] && !empty($partner['telegram_whitelist_keywords'])) {
        $keywords = json_decode($partner['telegram_whitelist_keywords'], true) ?: [];
        $message_content = json_encode($data);
        $whitelisted = false;
        
        foreach ($keywords as $keyword) {
            if (stripos($message_content, $keyword) !== false) {
                $whitelisted = true;
                break;
            }
        }
        
        if (!$whitelisted) {
            return; // Not in whitelist
        }
    }
    
    // Prepare message
    $message = "🔔 *New Postback Received*\n\n";
    $message .= "Partner: `{$data['partner_id']}`\n";
    $message .= "Time: `{$data['timestamp']}`\n";
    if (!empty($data['click_id'])) {
        $message .= "Click ID: `{$data['click_id']}`\n";
    }
    if (!empty($data['sum'])) {
        $message .= "Conversion Value: `{$data['sum']}`\n";
    }
    if (!empty($data['sum_mapping'])) {
        $message .= "Mapped Value: `{$data['sum_mapping']}`\n";
    }
    $message .= "IP: `{$data['client_ip']}`\n";
    
    // Send message via Telegram API
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $postData = [
        'chat_id' => $channel_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Log Telegram response if logging is enabled
    if ($partner['logging_enabled']) {
        error_log("Telegram notification sent for partner {$data['partner_id']}. Response: " . substr($response, 0, 100));
    }
}

/**
 * Update Google Sheet with postback data
 */
function update_google_sheet($data, $partner) {
    // This function would integrate with Google Sheets API
    // Implementation details would depend on the specific requirements
    // For now, we'll just log that it should be done
    
    if ($partner['logging_enabled']) {
        error_log("Google Sheets update triggered for partner {$data['partner_id']}");
    }
    
    // In a real implementation, you would:
    // 1. Use Google Sheets API client library
    // 2. Authenticate with service account
    // 3. Append data to the specified spreadsheet and sheet
    // 4. Handle errors appropriately
}
?>