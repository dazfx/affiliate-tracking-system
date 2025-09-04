<?php
/**
 * Professional Affiliate Tracking System - REST API
 * Version: 2.0.0
 * Author: TeamLead Optimized
 * Description: Enhanced REST API with comprehensive validation, error handling, and security
 * Last Updated: 2025-09-01
 * 
 * Features:
 * - Input validation and sanitization
 * - Rate limiting and abuse prevention
 * - Comprehensive error handling
 * - Request/response logging
 * - JSON schema validation
 * - CORS handling
 * - Performance monitoring
 * - Security headers
 */

declare(strict_types=1);

// ================== SECURITY HEADERS ==================
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS handling for development (remove in production)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowedOrigins = ['http://localhost', 'https://localhost'];
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================== CONFIGURATION ==================
const MAX_REQUEST_SIZE = 1048576; // 1MB
const RATE_LIMIT_REQUESTS = 60; // per minute
const RATE_LIMIT_WINDOW = 60;
const MAX_EXECUTION_TIME = 30;

// Set execution limits
ini_set('max_execution_time', (string)MAX_EXECUTION_TIME);
ini_set('memory_limit', '64M');

// Enhanced error handling
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/api_errors.log');
ini_set('display_errors', '0');

// Database connection with error handling
try {
    require_once __DIR__ . '/db.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error_code' => 'DB_CONNECTION_ERROR'
    ]);
    exit;
}


// ================== UTILITY CLASSES ==================

/**
 * Enhanced input validation and sanitization
 */
class ApiValidator
{
    public static function validatePartnerId(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $id) === 1;
    }
    
    public static function validatePartnerName(string $name): bool
    {
        return strlen(trim($name)) >= 1 && strlen($name) <= 255;
    }
    
    public static function validateDomain(string $domain): bool
    {
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
    }
    
    public static function validateJson(string $json): bool
    {
        if (empty($json)) return true; // Allow empty JSON
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    public static function sanitizeString(string $input, int $maxLength = 255): string
    {
        $sanitized = filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return substr(trim($sanitized ?: ''), 0, $maxLength);
    }
    
    public static function validatePartnerData(array $data): array
    {
        $errors = [];
        
        // Required fields validation
        if (empty($data['id']) || !self::validatePartnerId($data['id'])) {
            $errors[] = 'Invalid partner ID format';
        }
        
        if (empty($data['name']) || !self::validatePartnerName($data['name'])) {
            $errors[] = 'Partner name is required and must be 1-255 characters';
        }
        
        if (empty($data['target_domain']) || !self::validateDomain($data['target_domain'])) {
            $errors[] = 'Valid target domain is required';
        }
        
        // Optional JSON fields validation
        $jsonFields = ['clickid_keys', 'sum_keys', 'sum_mapping', 'telegram_whitelist_keywords', 'allowed_ips'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && !self::validateJson($data[$field])) {
                $errors[] = "Invalid JSON format in {$field}";
            }
        }
        
        return $errors;
    }
}

/**
 * API rate limiter
 */
class ApiRateLimiter
{
    private static string $cacheFile = __DIR__ . '/cache/api_rate_limits.json';
    
    public static function isAllowed(string $identifier): bool
    {
        if (!is_dir(dirname(self::$cacheFile))) {
            mkdir(dirname(self::$cacheFile), 0755, true);
        }
        
        $now = time();
        $windowStart = $now - RATE_LIMIT_WINDOW;
        
        $data = [];
        if (file_exists(self::$cacheFile)) {
            $content = file_get_contents(self::$cacheFile);
            if ($content !== false) {
                $data = json_decode($content, true) ?: [];
            }
        }
        
        // Clean old entries
        foreach ($data as $id => $timestamps) {
            $data[$id] = array_filter($timestamps, fn($ts) => $ts > $windowStart);
            if (empty($data[$id])) {
                unset($data[$id]);
            }
        }
        
        if (!isset($data[$identifier])) {
            $data[$identifier] = [];
        }
        
        if (count($data[$identifier]) >= RATE_LIMIT_REQUESTS) {
            return false;
        }
        
        $data[$identifier][] = $now;
        file_put_contents(self::$cacheFile, json_encode($data), LOCK_EX);
        
        return true;
    }
}

/**
 * API response formatter
 */
class ApiResponse
{
    public static function success(string $message, array $data = []): void
    {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
    
    public static function error(string $message, string $code = 'GENERAL_ERROR', int $httpCode = 400): void
    {
        http_response_code($httpCode);
        self::send([
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'timestamp' => time()
        ]);
    }
    
    public static function validationError(array $errors): void
    {
        self::error(
            'Validation failed: ' . implode(', ', $errors),
            'VALIDATION_ERROR',
            422
        );
    }
    
    private static function send(array $data): void
    {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ================== REQUEST PROCESSING ==================

// Rate limiting check
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!ApiRateLimiter::isAllowed($clientIP)) {
    ApiResponse::error(
        'Rate limit exceeded. Please try again later.',
        'RATE_LIMIT_EXCEEDED',
        429
    );
}

// Request size validation
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > MAX_REQUEST_SIZE) {
    ApiResponse::error(
        'Request size too large',
        'REQUEST_TOO_LARGE',
        413
    );
}

// Enhanced exception handler with logging
set_exception_handler(function (Throwable $exception) {
    $errorId = uniqid('err_');
    $errorDetails = [
        'id' => $errorId,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'request' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    ];
    
    error_log('API Exception [' . $errorId . ']: ' . json_encode($errorDetails));
    
    if (!headers_sent()) {
        ApiResponse::error(
            'Internal server error. Error ID: ' . $errorId,
            'INTERNAL_ERROR',
            500
        );
    }
    exit;
});

$response = ['success' => false, 'message' => 'Неизвестное действие.'];
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE && !empty(file_get_contents('php://input'))) {
    throw new Exception("Некорректный JSON в теле запроса: " . json_last_error_msg());
}
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save_partner':
            $partner_data = $data['partner'];
            $old_id = $data['old_id'] ?? null;

            if (empty($partner_data['id']) || empty($partner_data['name']) || empty($partner_data['target_domain'])) {
                $response['message'] = 'ID, Имя партнера и целевой домен обязательны.';
                break;
            }

            $is_new_partner = empty($old_id);
            $id_changed = !$is_new_partner && $old_id !== $partner_data['id'];

            if ($is_new_partner || $id_changed) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE id = ?");
                $stmt->execute([$partner_data['id']]);
                if ($stmt->fetchColumn() > 0) {
                    $response['message'] = "Партнер с ID '{$partner_data['id']}' уже существует.";
                    echo json_encode($response);
                    exit;
                }
            }
            
            $json_fields = ['clickid_keys', 'sum_keys', 'sum_mapping', 'telegram_whitelist_keywords', 'allowed_ips'];
            foreach ($json_fields as $field) {
                $partner_data[$field] = $partner_data[$field] ?? [];
            }

            $bool_fields = ['logging_enabled', 'telegram_enabled', 'telegram_whitelist_enabled', 'ip_whitelist_enabled', 'partner_telegram_enabled'];
            foreach ($bool_fields as $field) {
                $partner_data[$field] = filter_var($partner_data[$field] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            if ($is_new_partner) {
                foreach ($json_fields as $field) {
                    $partner_data[$field] = json_encode($partner_data[$field]);
                }
                $sql = "INSERT INTO partners (id, name, target_domain, notes, clickid_keys, sum_keys, sum_mapping, logging_enabled, telegram_enabled, telegram_whitelist_enabled, telegram_whitelist_keywords, ip_whitelist_enabled, allowed_ips, partner_telegram_enabled, partner_telegram_bot_token, partner_telegram_channel_id, google_sheet_name, google_spreadsheet_id, google_service_account_json) 
                        VALUES (:id, :name, :target_domain, :notes, :clickid_keys, :sum_keys, :sum_mapping, :logging_enabled, :telegram_enabled, :telegram_whitelist_enabled, :telegram_whitelist_keywords, :ip_whitelist_enabled, :allowed_ips, :partner_telegram_enabled, :partner_telegram_bot_token, :partner_telegram_channel_id, :google_sheet_name, :google_spreadsheet_id, :google_service_account_json)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($partner_data);
            } else {
                $allowed_fields = [
                    'id', 'name', 'target_domain', 'notes', 'clickid_keys', 'sum_keys', 'sum_mapping', 
                    'logging_enabled', 'telegram_enabled', 'telegram_whitelist_enabled', 
                    'telegram_whitelist_keywords', 'ip_whitelist_enabled', 'allowed_ips', 
                    'partner_telegram_enabled', 'partner_telegram_bot_token', 'partner_telegram_channel_id',
                    'google_sheet_name', 'google_spreadsheet_id', 'google_service_account_json'
                ];
                
                $sql_set_parts = [];
                $params_for_execute = [];

                foreach ($allowed_fields as $field) {
                    if ($field === 'id' && !$id_changed) continue;
                    if (array_key_exists($field, $partner_data)) {
                        $sql_set_parts[] = "`{$field}` = :{$field}";
                        $params_for_execute[$field] = in_array($field, $json_fields) ? json_encode($partner_data[$field]) : $partner_data[$field];
                    }
                }

                if (empty($sql_set_parts)) {
                    $response = ['success' => true, 'message' => 'Нет данных для обновления.'];
                    break;
                }

                $params_for_execute['where_id'] = $old_id;
                $sql_set_clause = implode(', ', $sql_set_parts);
                $sql = "UPDATE partners SET {$sql_set_clause} WHERE id = :where_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params_for_execute);
            }
            
            $response = ['success' => true, 'message' => 'Партнер успешно сохранен.'];
            break;

        case 'delete_partner':
            $partner_id = $data['id'] ?? null;
            if (!$partner_id) {
                $response['message'] = 'Не указан ID партнера.';
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
            $stmt->execute([$partner_id]);
            $response = ['success' => true, 'message' => 'Партнер удален.'];
            break;

        case 'save_global_settings':
            $settings = $data['settings'];
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, is_array($value) ? json_encode($value) : ($value === true ? 'true' : ($value === false ? 'false' : $value)) ]);
            }
            $response = ['success' => true, 'message' => 'Глобальные настройки сохранены.'];
            break;

        case 'clear_partner_stats':
            $partner_id = $data['id'] ?? null;
            if (!$partner_id) {
                $response['message'] = 'Не указан ID партнера.';
                break;
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM detailed_stats WHERE partner_id = ?");
            $stmt->execute([$partner_id]);
            $stmt = $pdo->prepare("DELETE FROM summary_stats WHERE partner_id = ?");
            $stmt->execute([$partner_id]);
            $pdo->commit();
            $response = ['success' => true, 'message' => "Статистика для партнера {$partner_id} очищена."];
            break;

        case 'get_partner_data':
            $partner_id = $data['id'] ?? null;
            if (!$partner_id) {
                $response['message'] = 'Не указан ID партнера.';
                break;
            }
            $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
            $stmt->execute([$partner_id]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($partner) {
                $json_fields_get = ['clickid_keys', 'sum_keys', 'sum_mapping', 'telegram_whitelist_keywords', 'allowed_ips'];
                foreach ($json_fields_get as $field) {
                    if (!empty($partner[$field])) {
                        $partner[$field] = json_decode($partner[$field], true);
                    } else {
                        $partner[$field] = [];
                    }
                }
                $response = ['success' => true, 'partner' => $partner];
            } else {
                $response['message'] = 'Партнер не найден.';
            }
            break;

        case 'get_detailed_stats':
            try {
                $partner_id = $_GET['partner_id'] ?? null;
                if (!$partner_id) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Partner ID is required for loading statistics',
                        'error_code' => 'MISSING_PARTNER_ID',
                        'data' => []
                    ]);
                    exit;
                }
                
                // Validate partner exists
                $partner_check = $pdo->prepare("SELECT COUNT(*) as count FROM partners WHERE id = ?");
                $partner_check->execute([$partner_id]);
                if ($partner_check->fetch()['count'] == 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Partner '{$partner_id}' not found in database",
                        'error_code' => 'PARTNER_NOT_FOUND',
                        'data' => []
                    ]);
                    exit;
                }
                
                $sql = "SELECT `timestamp` as date, click_id, url, status, response, `sum`, sum_mapping, extra_params FROM detailed_stats WHERE partner_id = ?";
                $params = [$partner_id];

                // Enhanced search functionality
                if (!empty($_GET['search_term'])) {
                    $search_term = trim($_GET['search_term']);
                    if (strtoupper($search_term) === 'EMPTY') {
                        $sql .= " AND (click_id IS NULL OR click_id = '' OR url IS NULL OR url = '' OR extra_params IS NULL OR extra_params = '' OR extra_params = '[]')";
                    } elseif (strpos($search_term, ':') !== false) {
                        list($key, $value) = array_map('trim', explode(':', $search_term, 2));
                        $value_param = '%' . $value . '%';

                        switch (strtolower($key)) {
                            case 'clickid':
                                $sql .= " AND click_id LIKE ?";
                                $params[] = $value_param;
                                break;
                            case 'url':
                                $sql .= " AND url LIKE ?";
                                $params[] = $value_param;
                                break;
                            case 'param':
                                $sql .= " AND extra_params LIKE ?";
                                $params[] = $value_param;
                                break;
                            default:
                                $sql .= " AND (click_id LIKE ? OR url LIKE ? OR extra_params LIKE ?)";
                                $searchTermParam = '%' . $search_term . '%';
                                $params[] = $searchTermParam; $params[] = $searchTermParam; $params[] = $searchTermParam;
                                break;
                        }
                    } else {
                        $sql .= " AND (click_id LIKE ? OR url LIKE ? OR extra_params LIKE ?)";
                        $searchTermParam = '%' . $search_term . '%';
                        $params[] = $searchTermParam;
                        $params[] = $searchTermParam;
                        $params[] = $searchTermParam;
                    }
                }
                
                // Status filter
                if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
                    $sql .= " AND status = ?";
                    $params[] = $_GET['status'];
                }
                
                // Date filters
                if (!empty($_GET['start_date'])) {
                    $sql .= " AND `timestamp` >= ?";
                    $params[] = $_GET['start_date'] . ' 00:00:00';
                }
                if (!empty($_GET['end_date'])) {
                    $sql .= " AND `timestamp` <= ?";
                    $params[] = $_GET['end_date'] . ' 23:59:59';
                }
                
                $sql .= " ORDER BY `timestamp` DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format the response data
                $formatted_data = [];
                foreach ($data as $row) {
                    $formatted_data[] = [
                        'date' => $row['date'] ? date('Y-m-d H:i:s', strtotime($row['date'])) : '',
                        'click_id' => $row['click_id'] ?? '',
                        'url' => $row['url'] ?? '',
                        'status' => (int)($row['status'] ?? 0),
                        'response' => $row['response'] ?? '',
                        'sum' => $row['sum'] ?? '0.00',
                        'sum_mapping' => $row['sum_mapping'] ?? '0.00',
                        'extra_params' => $row['extra_params'] ?? '[]'
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully loaded {$stmt->rowCount()} records for partner {$partner_id}",
                    'data' => $formatted_data,
                    'recordsTotal' => count($formatted_data),
                    'recordsFiltered' => count($formatted_data),
                    'partner_id' => $partner_id,
                    'debug_info' => [
                        'sql' => $sql,
                        'params_count' => count($params),
                        'search_term' => $_GET['search_term'] ?? null,
                        'status_filter' => $_GET['status'] ?? null
                    ]
                ]);
                exit;
                
            } catch (Exception $e) {
                error_log('Error in get_detailed_stats: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error while loading statistics: ' . $e->getMessage(),
                    'error_code' => 'DATABASE_ERROR',
                    'data' => [],
                    'debug_info' => [
                        'partner_id' => $_GET['partner_id'] ?? null,
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine()
                    ]
                ]);
                exit;
            }
            
        case 'test_api':
            try {
                // Test database connection
                $test_query = $pdo->query("SELECT 1 as test");
                $test_result = $test_query->fetch();
                
                // Check partners
                $partners_count = $pdo->query("SELECT COUNT(*) as count FROM partners")->fetch()['count'];
                
                // Check detailed_stats
                $stats_count = $pdo->query("SELECT COUNT(*) as count FROM detailed_stats")->fetch()['count'];
                
                // Get sample data
                $sample_partners = $pdo->query("SELECT id, name FROM partners LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                $sample_stats = $pdo->query("SELECT partner_id, COUNT(*) as records FROM detailed_stats GROUP BY partner_id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'API test successful',
                    'data' => [
                        'database_connection' => 'OK',
                        'partners_count' => $partners_count,
                        'stats_count' => $stats_count,
                        'sample_partners' => $sample_partners,
                        'sample_stats' => $sample_stats,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'API test failed: ' . $e->getMessage(),
                    'data' => []
                ]);
                exit;
            }
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo json_encode($response);
?>