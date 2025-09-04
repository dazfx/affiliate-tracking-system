<?php
/**
 * Affiliate Tracking System - Queue Processor
 * 
 * This script processes pending postbacks from the queue.
 * It should be run periodically via cron job (recommended every minute).
 * 
 * Features:
 * - Processes postbacks in batches
 * - Handles retries for failed postbacks
 * - Updates statistics
 * - Sends notifications
 * - Integrates with external services
 */

// Set execution time limit
set_time_limit(60);

// Set memory limit
ini_set('memory_limit', '128M');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);

// Include database connection
require_once __DIR__ . '/admin/db.php';

// Constants
const BATCH_SIZE = 50; // Number of postbacks to process in one run
const MAX_RETRIES = 3; // Maximum number of retries for failed postbacks

// Process pending postbacks
process_pending_postbacks();

/**
 * Process pending postbacks from the queue
 */
function process_pending_postbacks() {
    global $pdo;
    
    try {
        // Get pending postbacks
        $stmt = $pdo->prepare("
            SELECT id, partner_id, data 
            FROM postback_queue 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT " . BATCH_SIZE
        );
        $stmt->execute();
        $pending_postbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pending_postbacks)) {
            echo "No pending postbacks to process.\n";
            return;
        }
        
        echo "Processing " . count($pending_postbacks) . " postbacks...\n";
        
        foreach ($pending_postbacks as $postback) {
            process_single_postback($postback);
        }
        
        echo "Finished processing postbacks.\n";
        
    } catch (Exception $e) {
        error_log('Error processing postbacks: ' . $e->getMessage());
        echo "Error: " . $e->getMessage() . "\n";
    }
}

/**
 * Process a single postback
 */
function process_single_postback($postback) {
    global $pdo;
    
    $queue_id = $postback['id'];
    $partner_id = $postback['partner_id'];
    $data = json_decode($postback['data'], true);
    
    if (!$data) {
        // Invalid data, mark as failed
        update_queue_status($queue_id, 'failed');
        error_log("Invalid data for queue ID: {$queue_id}");
        return;
    }
    
    try {
        // Mark as processing
        update_queue_status($queue_id, 'processing');
        
        // Get partner configuration
        $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
        $stmt->execute([$partner_id]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$partner) {
            // Partner not found, mark as failed
            update_queue_status($queue_id, 'failed');
            error_log("Partner not found for queue ID: {$queue_id}");
            return;
        }
        
        // Process the postback data
        $result = process_postback_data($data, $partner);
        
        if ($result['success']) {
            // Mark as completed
            update_queue_status($queue_id, 'completed');
            echo "Successfully processed queue ID: {$queue_id}\n";
        } else {
            // Handle failure
            handle_postback_failure($queue_id, $result['error']);
        }
        
    } catch (Exception $e) {
        handle_postback_failure($queue_id, $e->getMessage());
    }
}

/**
 * Process postback data and update statistics
 */
function process_postback_data($data, $partner) {
    global $pdo;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert or update detailed statistics
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
        
        // Commit transaction
        $pdo->commit();
        
        // Send notifications if enabled
        if ($partner['telegram_enabled']) {
            send_telegram_notification($data, $partner);
        }
        
        // Update Google Sheets if enabled
        if (!empty($partner['google_spreadsheet_id']) && !empty($partner['google_sheet_name'])) {
            update_google_sheet($data, $partner);
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update queue status
 */
function update_queue_status($queue_id, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE postback_queue SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $queue_id]);
    } catch (Exception $e) {
        error_log("Error updating queue status for ID {$queue_id}: " . $e->getMessage());
    }
}

/**
 * Handle postback failure
 */
function handle_postback_failure($queue_id, $error_message) {
    global $pdo;
    
    try {
        // Get current retry count
        $stmt = $pdo->prepare("SELECT retry_count FROM postback_queue WHERE id = ?");
        $stmt->execute([$queue_id]);
        $retry_count = $stmt->fetchColumn() ?: 0;
        
        if ($retry_count < MAX_RETRIES) {
            // Increment retry count and keep as pending
            $stmt = $pdo->prepare("UPDATE postback_queue SET retry_count = retry_count + 1, status = 'pending', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queue_id]);
            error_log("Retrying queue ID: {$queue_id} (attempt " . ($retry_count + 1) . "). Error: {$error_message}");
        } else {
            // Mark as failed after max retries
            update_queue_status($queue_id, 'failed');
            error_log("Failed to process queue ID: {$queue_id} after {$retry_count} retries. Error: {$error_message}");
        }
    } catch (Exception $e) {
        error_log("Error handling postback failure for ID {$queue_id}: " . $e->getMessage());
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
    $message = "🔔 *New Postback Processed*\n\n";
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log Telegram response
    if ($http_code !== 200) {
        error_log("Telegram notification failed for partner {$data['partner_id']}. HTTP Code: {$http_code}");
    }
}

/**
 * Update Google Sheet with postback data
 */
function update_google_sheet($data, $partner) {
    // This function would integrate with Google Sheets API
    // Implementation details would depend on the specific requirements
    // For now, we'll just log that it should be done
    
    error_log("Google Sheets update triggered for partner {$data['partner_id']}");
    
    // In a real implementation, you would:
    // 1. Use Google Sheets API client library
    // 2. Authenticate with service account
    // 3. Append data to the specified spreadsheet and sheet
    // 4. Handle errors appropriately
}

// Run cleanup for old completed/failed entries (optional)
function cleanup_old_entries() {
    global $pdo;
    
    try {
        // Delete completed entries older than 7 days
        $stmt = $pdo->prepare("DELETE FROM postback_queue WHERE status = 'completed' AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        
        // Delete failed entries older than 30 days
        $stmt = $pdo->prepare("DELETE FROM postback_queue WHERE status = 'failed' AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        
        echo "Cleanup completed.\n";
    } catch (Exception $e) {
        error_log('Error during cleanup: ' . $e->getMessage());
    }
}

// Uncomment the following line to run cleanup
// cleanup_old_entries();
?>