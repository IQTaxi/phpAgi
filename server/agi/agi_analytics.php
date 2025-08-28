<?php
/**
 * AGI Call Analytics System - Complete Redesign
 * 
 * Professional analytics dashboard and API for automated taxi call system
 * Features:
 * - Comprehensive REST API with all CRUD operations
 * - Real-time dashboard with advanced search and filtering
 * - Call details with audio playback and interactive maps
 * - Per-hour analytics and detailed statistics
 * - CSV export functionality
 * - Real-time call tracking integration
 * 
 * Database: MariaDB/MySQL (automated_calls_analitycs table)
 * 
 * @author AGI Analytics System v2.0
 * @version 2.0.0
 */

class AGIAnalytics {
    private $db;
    private $table = 'automated_calls_analitycs';
    
    // Database configuration
    private $dbConfig = [
        'host' => '127.0.0.1',
        'dbname' => 'asterisk',
        'primary_user' => 'freepbxuser',
        'primary_pass' => 'WXS/NCr0WnbY',
        'fallback_user' => 'root',
        'fallback_pass' => '',
        'port' => '3306',
        'charset' => 'utf8mb4'
    ];
    
    public function __construct() {
        $this->loadEnvConfig();
        $this->connectDatabase();
        $this->createIndexesIfNeeded();
    }
    
    /**
     * Load database configuration from environment variables
     */
    private function loadEnvConfig() {
        $this->dbConfig['host'] = getenv('DB_HOST') ?: $this->dbConfig['host'];
        $this->dbConfig['dbname'] = getenv('DB_NAME') ?: $this->dbConfig['dbname'];
        $this->dbConfig['primary_user'] = getenv('DB_USER') ?: $this->dbConfig['primary_user'];
        $this->dbConfig['primary_pass'] = getenv('DB_PASS') ?: $this->dbConfig['primary_pass'];
        $this->dbConfig['fallback_user'] = getenv('DB_FALLBACK_USER') ?: $this->dbConfig['fallback_user'];
        $this->dbConfig['fallback_pass'] = getenv('DB_FALLBACK_PASS') ?: $this->dbConfig['fallback_pass'];
        $this->dbConfig['port'] = getenv('DB_PORT') ?: $this->dbConfig['port'];
    }
    
    /**
     * Enhanced database connection with fallback
     */
    private function connectDatabase() {
        $dsn = "mysql:host={$this->dbConfig['host']};port={$this->dbConfig['port']};dbname={$this->dbConfig['dbname']};charset={$this->dbConfig['charset']}";
        
        // Try primary connection
        try {
            $this->db = new PDO($dsn, $this->dbConfig['primary_user'], $this->dbConfig['primary_pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->db->query("SELECT 1");
            error_log("Analytics: Connected to database successfully");
            return;
        } catch (PDOException $e) {
            error_log("Analytics: Primary connection failed: " . $e->getMessage());
        }
        
        // Try fallback connection
        try {
            $this->db = new PDO($dsn, $this->dbConfig['fallback_user'], $this->dbConfig['fallback_pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->db->query("SELECT 1");
            error_log("Analytics: Connected with fallback credentials");
            return;
        } catch (PDOException $e) {
            error_log("Analytics: All connections failed: " . $e->getMessage());
            $this->sendErrorResponse('Database connection failed', 500);
        }
    }
    
    /**
     * Create additional indexes for better performance
     */
    private function createIndexesIfNeeded() {
        $indexes = [
            'idx_call_id' => "CREATE INDEX IF NOT EXISTS idx_call_id ON {$this->table} (call_id)",
            'idx_phone_extension' => "CREATE INDEX IF NOT EXISTS idx_phone_extension ON {$this->table} (phone_number, extension)",
            'idx_datetime_outcome' => "CREATE INDEX IF NOT EXISTS idx_datetime_outcome ON {$this->table} (call_start_time, call_outcome)",
            'idx_coordinates' => "CREATE INDEX IF NOT EXISTS idx_coordinates ON {$this->table} (pickup_lat, pickup_lng, destination_lat, destination_lng)"
        ];
        
        foreach ($indexes as $name => $sql) {
            try {
                $this->db->exec($sql);
            } catch (PDOException $e) {
                error_log("Analytics: Index creation failed for {$name}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Main request handler
     */
    public function handleRequest() {
        // Set CORS headers
        $this->setCORSHeaders();
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_GET['endpoint'] ?? '';
        $action = $_GET['action'] ?? '';
        
        // Handle CSV export
        if ($action === 'export') {
            $this->exportCSV();
            return;
        }
        
        // Handle audio file requests
        if ($action === 'audio') {
            $this->serveAudio();
            return;
        }
        
        // API endpoint routing
        if (!empty($endpoint)) {
            $this->handleAPI($method, $endpoint);
            return;
        }
        
        // Default: serve HTML dashboard
        $this->renderDashboard();
    }
    
    /**
     * Set CORS headers for API access
     */
    private function setCORSHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Endpoint, X-Action');
        header('Access-Control-Max-Age: 3600');
    }
    
    /**
     * API request handler
     */
    private function handleAPI($method, $endpoint) {
        header('Content-Type: application/json');
        
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGetAPI($endpoint);
                    break;
                case 'POST':
                    $this->handlePostAPI($endpoint);
                    break;
                case 'PUT':
                    $this->handlePutAPI($endpoint);
                    break;
                case 'DELETE':
                    $this->handleDeleteAPI($endpoint);
                    break;
                default:
                    $this->sendErrorResponse('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("Analytics API Error: " . $e->getMessage());
            $this->sendErrorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    // ===== GET API ENDPOINTS =====
    
    private function handleGetAPI($endpoint) {
        switch ($endpoint) {
            case 'calls':
                $this->apiGetCalls();
                break;
            case 'call':
                $this->apiGetCall();
                break;
            case 'search':
                $this->apiSearch();
                break;
            case 'analytics':
                $this->apiGetAnalytics();
                break;
            case 'dashboard':
                $this->apiGetDashboard();
                break;
            case 'hourly':
                $this->apiGetHourlyAnalytics();
                break;
            case 'daily':
                $this->apiGetDailyAnalytics();
                break;
            case 'realtime':
                $this->apiGetRealtimeStats();
                break;
            case 'recordings':
                $this->apiGetRecordings();
                break;
            default:
                $this->sendErrorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Get calls with advanced filtering and pagination
     */
    private function apiGetCalls() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(1000, max(1, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        // Advanced filtering
        $filters = [
            'phone' => 'phone_number LIKE ?',
            'extension' => 'extension = ?',
            'call_type' => 'call_type = ?',
            'outcome' => 'call_outcome = ?',
            'user_name' => 'user_name LIKE ?',
            'successful' => 'successful_registration = ?'
        ];
        
        foreach ($filters as $key => $condition) {
            if (!empty($_GET[$key])) {
                $where[] = $condition;
                $value = $_GET[$key];
                $params[] = in_array($key, ['phone', 'user_name']) ? "%{$value}%" : $value;
            }
        }
        
        // Date range filtering
        if (!empty($_GET['date_from'])) {
            $where[] = 'DATE(call_start_time) >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'DATE(call_start_time) <= ?';
            $params[] = $_GET['date_to'];
        }
        
        // Time range filtering
        if (!empty($_GET['time_from'])) {
            $where[] = 'TIME(call_start_time) >= ?';
            $params[] = $_GET['time_from'];
        }
        if (!empty($_GET['time_to'])) {
            $where[] = 'TIME(call_start_time) <= ?';
            $params[] = $_GET['time_to'];
        }
        
        // Duration filtering
        if (!empty($_GET['min_duration'])) {
            $where[] = 'call_duration >= ?';
            $params[] = intval($_GET['min_duration']);
        }
        if (!empty($_GET['max_duration'])) {
            $where[] = 'call_duration <= ?';
            $params[] = intval($_GET['max_duration']);
        }
        
        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        
        // Sorting
        $sortFields = [
            'call_start_time', 'call_duration', 'phone_number', 'extension', 
            'call_outcome', 'call_type', 'created_at', 'updated_at'
        ];
        $sort = in_array($_GET['sort'] ?? '', $sortFields) ? $_GET['sort'] : 'call_start_time';
        $direction = strtoupper($_GET['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        
        // Get data
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY {$sort} {$direction} LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $calls = $stmt->fetchAll();
        
        // Enhance data with additional info
        foreach ($calls as &$call) {
            $call = $this->enhanceCallData($call);
        }
        
        $this->sendResponse([
            'calls' => $calls,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters_applied' => !empty($where)
        ]);
    }
    
    /**
     * Get single call with full details
     */
    private function apiGetCall() {
        $id = $_GET['id'] ?? '';
        $call_id = $_GET['call_id'] ?? '';
        
        if (empty($id) && empty($call_id)) {
            $this->sendErrorResponse('ID or call_id required', 400);
            return;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . (!empty($id) ? "id = ?" : "call_id = ?");
        $stmt = $this->db->prepare($sql);
        $stmt->execute([!empty($id) ? $id : $call_id]);
        $call = $stmt->fetch();
        
        if (!$call) {
            $this->sendErrorResponse('Call not found', 404);
            return;
        }
        
        // Enhance with additional data
        $call = $this->enhanceCallData($call, true);
        
        $this->sendResponse($call);
    }
    
    /**
     * Advanced search functionality
     */
    private function apiSearch() {
        $query = $_GET['q'] ?? '';
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        
        if (empty($query)) {
            $this->sendResponse(['calls' => []]);
            return;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE 
                phone_number LIKE ? OR 
                call_id LIKE ? OR 
                unique_id LIKE ? OR 
                user_name LIKE ? OR 
                pickup_address LIKE ? OR 
                destination_address LIKE ? OR 
                extension LIKE ?
                ORDER BY call_start_time DESC 
                LIMIT {$limit}";
        
        $searchTerm = "%{$query}%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_fill(0, 7, $searchTerm));
        $calls = $stmt->fetchAll();
        
        foreach ($calls as &$call) {
            $call = $this->enhanceCallData($call);
        }
        
        $this->sendResponse(['calls' => $calls]);
    }
    
    /**
     * Get comprehensive analytics
     */
    private function apiGetAnalytics() {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $analytics = [
            'summary' => $this->getAnalyticsSummary($dateFrom, $dateTo),
            'outcomes' => $this->getOutcomeAnalytics($dateFrom, $dateTo),
            'hourly_distribution' => $this->getHourlyDistribution($dateFrom, $dateTo),
            'daily_trend' => $this->getDailyTrend($dateFrom, $dateTo),
            'extension_performance' => $this->getExtensionPerformance($dateFrom, $dateTo),
            'api_usage' => $this->getAPIUsageAnalytics($dateFrom, $dateTo),
            'geographic_data' => $this->getGeographicAnalytics($dateFrom, $dateTo),
            'call_duration_stats' => $this->getCallDurationStats($dateFrom, $dateTo),
            'language_stats' => $this->getLanguageStats($dateFrom, $dateTo)
        ];
        
        $this->sendResponse($analytics);
    }
    
    /**
     * Get dashboard data for main page
     */
    private function apiGetDashboard() {
        $dashboard = [
            'realtime_stats' => $this->getRealtimeStats(),
            'recent_calls' => $this->getRecentCalls(),
            'today_summary' => $this->getTodaySummary(),
            'active_calls' => $this->getActiveCalls(),
            'system_health' => $this->getSystemHealth()
        ];
        
        $this->sendResponse($dashboard);
    }
    
    /**
     * Get hourly analytics
     */
    private function apiGetHourlyAnalytics() {
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $sql = "SELECT 
                    HOUR(call_start_time) as hour,
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                    COUNT(CASE WHEN call_outcome = 'hangup' THEN 1 END) as hangup_calls,
                    COUNT(CASE WHEN call_outcome = 'operator_transfer' THEN 1 END) as operator_transfers,
                    AVG(call_duration) as avg_duration,
                    SUM(google_tts_calls + edge_tts_calls) as tts_usage,
                    SUM(google_stt_calls) as stt_usage
                FROM {$this->table} 
                WHERE DATE(call_start_time) = ?
                GROUP BY HOUR(call_start_time)
                ORDER BY hour";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        $hourlyData = $stmt->fetchAll();
        
        // Fill missing hours with zeros
        $hours = array_column($hourlyData, 'hour');
        $fullData = [];
        for ($h = 0; $h < 24; $h++) {
            $found = array_filter($hourlyData, fn($item) => $item['hour'] == $h);
            if ($found) {
                $fullData[] = reset($found);
            } else {
                $fullData[] = [
                    'hour' => $h,
                    'total_calls' => 0,
                    'successful_calls' => 0,
                    'hangup_calls' => 0,
                    'operator_transfers' => 0,
                    'avg_duration' => 0,
                    'tts_usage' => 0,
                    'stt_usage' => 0
                ];
            }
        }
        
        $this->sendResponse(['hourly_data' => $fullData, 'date' => $date]);
    }
    
    /**
     * Get daily analytics
     */
    private function apiGetDailyAnalytics() {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $sql = "SELECT 
                    DATE(call_start_time) as date,
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                    COUNT(CASE WHEN call_outcome = 'hangup' THEN 1 END) as hangup_calls,
                    AVG(call_duration) as avg_duration,
                    SUM(google_tts_calls + edge_tts_calls) as tts_usage,
                    SUM(google_stt_calls) as stt_usage,
                    COUNT(DISTINCT phone_number) as unique_callers
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?
                GROUP BY DATE(call_start_time)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        $dailyData = $stmt->fetchAll();
        
        $this->sendResponse(['daily_data' => $dailyData, 'date_range' => ['from' => $dateFrom, 'to' => $dateTo]]);
    }
    
    /**
     * Get real-time statistics
     */
    private function apiGetRealtimeStats() {
        $stats = [
            'active_calls' => $this->getActiveCalls(),
            'today_calls' => $this->getTodayCallCount(),
            'current_hour_calls' => $this->getCurrentHourCallCount(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'system_status' => 'operational'
        ];
        
        $this->sendResponse($stats);
    }
    
    /**
     * Get recording information for a call
     */
    private function apiGetRecordings() {
        $callId = $_GET['call_id'] ?? '';
        if (empty($callId)) {
            $this->sendErrorResponse('call_id required', 400);
            return;
        }
        
        $sql = "SELECT recording_path, log_file_path FROM {$this->table} WHERE call_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$callId]);
        $call = $stmt->fetch();
        
        if (!$call) {
            $this->sendErrorResponse('Call not found', 404);
            return;
        }
        
        $recordings = $this->getCallRecordings($call['recording_path']);
        $this->sendResponse(['recordings' => $recordings]);
    }
    
    // ===== POST/PUT/DELETE API ENDPOINTS =====
    
    private function handlePostAPI($endpoint) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($endpoint) {
            case 'call':
                $this->apiCreateCall($data);
                break;
            default:
                $this->sendErrorResponse('Endpoint not found', 404);
        }
    }
    
    private function handlePutAPI($endpoint) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($endpoint) {
            case 'call':
                $this->apiUpdateCall($data);
                break;
            default:
                $this->sendErrorResponse('Endpoint not found', 404);
        }
    }
    
    private function handleDeleteAPI($endpoint) {
        switch ($endpoint) {
            case 'call':
                $this->apiDeleteCall();
                break;
            default:
                $this->sendErrorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Create new call record
     */
    private function apiCreateCall($data) {
        if (empty($data)) {
            $this->sendErrorResponse('No data provided', 400);
            return;
        }
        
        // DEBUG: Log location data being received
        error_log("Analytics: Received data - pickup_address: " . ($data['pickup_address'] ?? 'NULL'));
        error_log("Analytics: Received data - pickup_lat: " . ($data['pickup_lat'] ?? 'NULL'));
        error_log("Analytics: Received data - pickup_lng: " . ($data['pickup_lng'] ?? 'NULL'));
        error_log("Analytics: Received data - destination_address: " . ($data['destination_address'] ?? 'NULL'));
        error_log("Analytics: Received data - destination_lat: " . ($data['destination_lat'] ?? 'NULL'));
        error_log("Analytics: Received data - destination_lng: " . ($data['destination_lng'] ?? 'NULL'));
        
        // Set default values
        $data = array_merge([
            'call_start_time' => date('Y-m-d H:i:s'),
            'call_outcome' => 'in_progress',
            'call_type' => 'immediate',
            'language_used' => 'el',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $data);
        
        // Clean and validate data
        $data = $this->cleanDataTypes($data);
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($data));
            $id = $this->db->lastInsertId();
            
            $this->sendResponse(['id' => $id, 'success' => true, 'message' => 'Call record created successfully']);
        } catch (PDOException $e) {
            error_log("Analytics: Create call error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to create call record: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update call record
     */
    private function apiUpdateCall($data) {
        if (empty($data['id']) && empty($data['call_id'])) {
            $this->sendErrorResponse('ID or call_id required', 400);
            return;
        }
        
        $id = $data['id'] ?? '';
        $callId = $data['call_id'] ?? '';
        unset($data['id'], $data['call_id']);
        
        // DEBUG: Log location data in update
        error_log("Analytics UPDATE: pickup_address: " . ($data['pickup_address'] ?? 'NULL'));
        error_log("Analytics UPDATE: pickup_lat: " . ($data['pickup_lat'] ?? 'NULL'));
        error_log("Analytics UPDATE: pickup_lng: " . ($data['pickup_lng'] ?? 'NULL'));
        error_log("Analytics UPDATE: destination_address: " . ($data['destination_address'] ?? 'NULL'));
        error_log("Analytics UPDATE: destination_lat: " . ($data['destination_lat'] ?? 'NULL'));
        error_log("Analytics UPDATE: destination_lng: " . ($data['destination_lng'] ?? 'NULL'));
        
        // Add updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Clean data
        $data = $this->cleanDataTypes($data);
        
        if (empty($data)) {
            $this->sendErrorResponse('No data to update', 400);
            return;
        }
        
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE " . (!empty($id) ? "id = ?" : "call_id = ?");
        
        $params = array_values($data);
        $params[] = !empty($id) ? $id : $callId;
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();
            
            $this->sendResponse(['success' => true, 'affected_rows' => $affected, 'message' => 'Call record updated successfully']);
        } catch (PDOException $e) {
            error_log("Analytics: Update call error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to update call record: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete call record
     */
    private function apiDeleteCall() {
        $id = $_GET['id'] ?? '';
        $callId = $_GET['call_id'] ?? '';
        
        if (empty($id) && empty($callId)) {
            $this->sendErrorResponse('ID or call_id required', 400);
            return;
        }
        
        $sql = "DELETE FROM {$this->table} WHERE " . (!empty($id) ? "id = ?" : "call_id = ?");
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([!empty($id) ? $id : $callId]);
            $affected = $stmt->rowCount();
            
            $this->sendResponse(['success' => true, 'deleted_rows' => $affected, 'message' => 'Call record deleted successfully']);
        } catch (PDOException $e) {
            error_log("Analytics: Delete call error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to delete call record: ' . $e->getMessage(), 500);
        }
    }
    
    // ===== DATA PROCESSING METHODS =====
    
    /**
     * Enhance call data with additional information
     */
    private function enhanceCallData($call, $detailed = false) {
        // Calculate derived fields
        $call['success_rate'] = $call['call_outcome'] === 'success' ? 100 : 0;
        $call['has_location_data'] = !empty($call['pickup_lat']) && !empty($call['pickup_lng']);
        $call['total_api_calls'] = 
            ($call['google_tts_calls'] ?? 0) + 
            ($call['edge_tts_calls'] ?? 0) + 
            ($call['google_stt_calls'] ?? 0) + 
            ($call['geocoding_api_calls'] ?? 0) + 
            ($call['user_api_calls'] ?? 0) + 
            ($call['registration_api_calls'] ?? 0);
        
        // Format timestamps
        $call['call_start_time_formatted'] = $this->formatTimestamp($call['call_start_time']);
        $call['call_end_time_formatted'] = $this->formatTimestamp($call['call_end_time']);
        $call['duration_formatted'] = $this->formatDuration($call['call_duration']);
        
        if ($detailed) {
            // Get recordings
            $call['recordings'] = $this->getCallRecordings($call['recording_path'] ?? '');
            
            // Get call log
            $call['call_log'] = $this->getCallLog($call['log_file_path'] ?? '');
            
            // Get related calls from same number
            $call['related_calls'] = $this->getRelatedCalls($call['phone_number'], $call['id']);
        }
        
        return $call;
    }
    
    /**
     * Clean and validate data types for database operations
     */
    private function cleanDataTypes($data) {
        // Define all valid database fields based on the table structure
        $validFields = [
            'id', 'call_id', 'unique_id', 'phone_number', 'extension', 
            'call_start_time', 'call_end_time', 'call_duration', 'call_outcome',
            'call_type', 'is_reservation', 'reservation_time', 'language_used', 
            'language_changed', 'initial_choice', 'confirmation_attempts', 'total_retries',
            'name_attempts', 'pickup_attempts', 'destination_attempts', 'reservation_attempts',
            'confirmed_default_address', 'pickup_address', 'pickup_lat', 'pickup_lng',
            'destination_address', 'destination_lat', 'destination_lng', 'google_tts_calls',
            'google_stt_calls', 'edge_tts_calls', 'geocoding_api_calls', 'user_api_calls',
            'registration_api_calls', 'date_parsing_api_calls', 'tts_processing_time',
            'stt_processing_time', 'geocoding_processing_time', 'total_processing_time',
            'successful_registration', 'operator_transfer_reason', 'error_messages',
            'recording_path', 'log_file_path', 'progress_json_path', 'tts_provider',
            'callback_mode', 'days_valid', 'user_name', 'registration_result',
            'api_response_time', 'created_at', 'updated_at'
        ];
        
        $booleanFields = [
            'is_reservation', 'language_changed', 'confirmed_default_address', 'successful_registration'
        ];
        
        $integerFields = [
            'call_duration', 'confirmation_attempts', 'total_retries', 'name_attempts', 
            'pickup_attempts', 'destination_attempts', 'reservation_attempts',
            'google_tts_calls', 'google_stt_calls', 'edge_tts_calls', 'geocoding_api_calls',
            'user_api_calls', 'registration_api_calls', 'date_parsing_api_calls',
            'tts_processing_time', 'stt_processing_time', 'geocoding_processing_time',
            'total_processing_time', 'api_response_time', 'callback_mode', 'days_valid'
        ];
        
        $floatFields = [
            'pickup_lat', 'pickup_lng', 'destination_lat', 'destination_lng'
        ];
        
        // First, filter out invalid fields
        $cleanedData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $validFields)) {
                $cleanedData[$key] = $value;
            } else {
                error_log("Analytics: Filtering out invalid field: {$key}");
            }
        }
        
        // Then process data types
        foreach ($cleanedData as $key => $value) {
            if (in_array($key, $booleanFields)) {
                $cleanedData[$key] = ($value === true || $value === 1 || $value === '1') ? 1 : 0;
            } elseif (in_array($key, $integerFields)) {
                $cleanedData[$key] = ($value === '' || $value === null) ? 0 : intval($value);
            } elseif (in_array($key, $floatFields)) {
                $cleanedData[$key] = ($value === '' || $value === null) ? null : floatval($value);
            } elseif ($value === '') {
                $cleanedData[$key] = null;
            }
        }
        
        return $cleanedData;
    }
    
    // ===== ANALYTICS HELPER METHODS =====
    
    private function getAnalyticsSummary($dateFrom, $dateTo) {
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                    COUNT(CASE WHEN call_outcome = 'hangup' THEN 1 END) as hangup_calls,
                    COUNT(CASE WHEN call_outcome = 'operator_transfer' THEN 1 END) as operator_transfers,
                    COUNT(CASE WHEN is_reservation = 1 THEN 1 END) as reservation_calls,
                    AVG(call_duration) as avg_duration,
                    MAX(call_duration) as max_duration,
                    MIN(call_duration) as min_duration,
                    COUNT(DISTINCT phone_number) as unique_callers,
                    COUNT(DISTINCT extension) as extensions_used
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        $summary = $stmt->fetch();
        
        // Calculate success rate
        $summary['success_rate'] = $summary['total_calls'] > 0 
            ? round(($summary['successful_calls'] / $summary['total_calls']) * 100, 2) 
            : 0;
        
        return $summary;
    }
    
    private function getOutcomeAnalytics($dateFrom, $dateTo) {
        $sql = "SELECT 
                    call_outcome, 
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$this->table} WHERE DATE(call_start_time) BETWEEN ? AND ?)), 2) as percentage
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?
                GROUP BY call_outcome 
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    private function getHourlyDistribution($dateFrom, $dateTo) {
        $sql = "SELECT 
                    HOUR(call_start_time) as hour,
                    COUNT(*) as count
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?
                GROUP BY HOUR(call_start_time)
                ORDER BY hour";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    private function getDailyTrend($dateFrom, $dateTo) {
        $sql = "SELECT 
                    DATE(call_start_time) as date,
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?
                GROUP BY DATE(call_start_time)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    private function getExtensionPerformance($dateFrom, $dateTo) {
        $sql = "SELECT 
                    extension,
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                    AVG(call_duration) as avg_duration,
                    ROUND((COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) * 100.0 / COUNT(*)), 2) as success_rate
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ? AND extension IS NOT NULL
                GROUP BY extension
                ORDER BY total_calls DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    private function getAPIUsageAnalytics($dateFrom, $dateTo) {
        $sql = "SELECT 
                    SUM(google_tts_calls) as google_tts_total,
                    SUM(edge_tts_calls) as edge_tts_total,
                    SUM(google_stt_calls) as google_stt_total,
                    SUM(geocoding_api_calls) as geocoding_total,
                    SUM(user_api_calls) as user_api_total,
                    SUM(registration_api_calls) as registration_total,
                    AVG(tts_processing_time) as avg_tts_time,
                    AVG(stt_processing_time) as avg_stt_time,
                    AVG(geocoding_processing_time) as avg_geocoding_time,
                    AVG(api_response_time) as avg_api_response_time
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetch();
    }
    
    private function getGeographicAnalytics($dateFrom, $dateTo) {
        $sql = "SELECT 
                    pickup_address,
                    destination_address,
                    pickup_lat,
                    pickup_lng,
                    destination_lat,
                    destination_lng,
                    COUNT(*) as frequency
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?
                    AND pickup_lat IS NOT NULL 
                    AND pickup_lng IS NOT NULL
                GROUP BY pickup_address, destination_address
                ORDER BY frequency DESC
                LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    private function getCallDurationStats($dateFrom, $dateTo) {
        $sql = "SELECT 
                    AVG(call_duration) as avg_duration,
                    MIN(call_duration) as min_duration,
                    MAX(call_duration) as max_duration,
                    STDDEV(call_duration) as std_deviation,
                    COUNT(CASE WHEN call_duration <= 30 THEN 1 END) as short_calls,
                    COUNT(CASE WHEN call_duration BETWEEN 31 AND 120 THEN 1 END) as medium_calls,
                    COUNT(CASE WHEN call_duration > 120 THEN 1 END) as long_calls
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetch();
    }
    
    private function getLanguageStats($dateFrom, $dateTo) {
        $sql = "SELECT 
                    language_used,
                    COUNT(*) as count,
                    COUNT(CASE WHEN language_changed = 1 THEN 1 END) as changed_count
                FROM {$this->table} 
                WHERE DATE(call_start_time) BETWEEN ? AND ?
                GROUP BY language_used
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    // ===== DASHBOARD HELPER METHODS =====
    
    private function getRealtimeStats() {
        return [
            'active_calls' => $this->getActiveCalls(),
            'calls_last_hour' => $this->getCallsLastHour(),
            'success_rate_today' => $this->getTodaySuccessRate(),
            'avg_duration_today' => $this->getTodayAvgDuration()
        ];
    }
    
    private function getRecentCalls($limit = 20) {
        $sql = "SELECT * FROM {$this->table} ORDER BY call_start_time DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        $calls = $stmt->fetchAll();
        
        foreach ($calls as &$call) {
            $call = $this->enhanceCallData($call);
        }
        
        return $calls;
    }
    
    private function getTodaySummary() {
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                    AVG(call_duration) as avg_duration,
                    SUM(google_tts_calls + edge_tts_calls) as tts_usage,
                    COUNT(DISTINCT phone_number) as unique_callers
                FROM {$this->table} 
                WHERE DATE(call_start_time) = CURDATE()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    private function getActiveCalls() {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE call_outcome = 'in_progress'";
        return intval($this->db->query($sql)->fetchColumn());
    }
    
    private function getTodayCallCount() {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE DATE(call_start_time) = CURDATE()";
        return intval($this->db->query($sql)->fetchColumn());
    }
    
    private function getCurrentHourCallCount() {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE call_start_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        return intval($this->db->query($sql)->fetchColumn());
    }
    
    private function getCallsLastHour() {
        return $this->getCurrentHourCallCount();
    }
    
    private function getTodaySuccessRate() {
        $sql = "SELECT 
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) * 100.0 / COUNT(*) as success_rate
                FROM {$this->table} 
                WHERE DATE(call_start_time) = CURDATE()";
        return round(floatval($this->db->query($sql)->fetchColumn()), 2);
    }
    
    private function getTodayAvgDuration() {
        $sql = "SELECT AVG(call_duration) FROM {$this->table} WHERE DATE(call_start_time) = CURDATE()";
        return round(floatval($this->db->query($sql)->fetchColumn()), 2);
    }
    
    private function getAverageResponseTime() {
        $sql = "SELECT AVG(api_response_time) FROM {$this->table} WHERE DATE(call_start_time) = CURDATE() AND api_response_time > 0";
        return round(floatval($this->db->query($sql)->fetchColumn()), 2);
    }
    
    private function getSystemHealth() {
        return [
            'database_status' => 'connected',
            'api_endpoints' => 'operational',
            'last_call' => $this->getLastCallTime(),
            'error_rate' => $this->getTodayErrorRate()
        ];
    }
    
    private function getLastCallTime() {
        $sql = "SELECT MAX(call_start_time) FROM {$this->table}";
        return $this->db->query($sql)->fetchColumn();
    }
    
    private function getTodayErrorRate() {
        $sql = "SELECT 
                    COUNT(CASE WHEN call_outcome = 'error' THEN 1 END) * 100.0 / COUNT(*) as error_rate
                FROM {$this->table} 
                WHERE DATE(call_start_time) = CURDATE()";
        return round(floatval($this->db->query($sql)->fetchColumn()), 2);
    }
    
    // ===== FILE HANDLING METHODS =====
    
    private function getCallRecordings($recordingPath) {
        if (empty($recordingPath) || !is_dir($recordingPath)) {
            return [];
        }
        
        $recordings = [];
        $patterns = ['*.wav', '*.mp3', '*.ogg'];
        
        foreach ($patterns as $pattern) {
            $files = glob($recordingPath . '/' . $pattern);
            foreach ($files as $file) {
                $recordings[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'duration' => $this->getAudioDuration($file),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'url' => $this->getAudioURL($file)
                ];
            }
        }
        
        return $recordings;
    }
    
    private function getCallLog($logPath) {
        if (empty($logPath) || !file_exists($logPath)) {
            return [];
        }
        
        $logContent = file_get_contents($logPath);
        $logLines = array_filter(array_map('trim', explode("\n", $logContent)));
        
        $parsedLog = [];
        foreach ($logLines as $line) {
            $parsedLog[] = [
                'timestamp' => $this->extractTimestamp($line),
                'level' => $this->extractLogLevel($line),
                'message' => $line
            ];
        }
        
        return $parsedLog;
    }
    
    private function getRelatedCalls($phoneNumber, $excludeId) {
        $sql = "SELECT * FROM {$this->table} WHERE phone_number = ? AND id != ? ORDER BY call_start_time DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$phoneNumber, $excludeId]);
        return $stmt->fetchAll();
    }
    
    private function getAudioDuration($filePath) {
        // This would require ffmpeg or similar tool to get actual duration
        // For now, return file size as approximate indicator
        return round(filesize($filePath) / 16000); // Rough estimate
    }
    
    private function getAudioURL($filePath) {
        // Generate URL for audio playback
        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath);
        return $relativePath;
    }
    
    private function extractTimestamp($logLine) {
        if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $logLine, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    private function extractLogLevel($logLine) {
        if (preg_match('/\[(ERROR|WARN|INFO|DEBUG)\]/', $logLine, $matches)) {
            return strtolower($matches[1]);
        }
        return 'info';
    }
    
    // ===== UTILITY METHODS =====
    
    private function formatTimestamp($timestamp) {
        return $timestamp ? date('M j, Y g:i A', strtotime($timestamp)) : '';
    }
    
    private function formatDuration($seconds) {
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . "m " . ($seconds % 60) . "s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            return "{$hours}h {$minutes}m {$secs}s";
        }
    }
    
    // ===== CSV EXPORT =====
    
    private function exportCSV() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="agi_analytics_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        $headers = [
            'ID', 'Call ID', 'Unique ID', 'Phone Number', 'Extension', 'Call Start Time', 'Call End Time', 
            'Duration (seconds)', 'Call Outcome', 'Call Type', 'Is Reservation', 'Reservation Time', 
            'Language Used', 'Language Changed', 'Initial Choice', 'Confirmation Attempts', 'Total Retries',
            'Name Attempts', 'Pickup Attempts', 'Destination Attempts', 'Reservation Attempts',
            'Confirmed Default Address', 'Pickup Address', 'Pickup Latitude', 'Pickup Longitude',
            'Destination Address', 'Destination Latitude', 'Destination Longitude', 'Google TTS Calls',
            'Google STT Calls', 'Edge TTS Calls', 'Geocoding API Calls', 'User API Calls', 
            'Registration API Calls', 'Date Parsing API Calls', 'TTS Processing Time (ms)', 
            'STT Processing Time (ms)', 'Geocoding Processing Time (ms)', 'Total Processing Time (ms)',
            'Successful Registration', 'Operator Transfer Reason', 'Error Messages', 'Recording Path',
            'Log File Path', 'Progress JSON Path', 'TTS Provider', 'Callback Mode', 'Days Valid',
            'User Name', 'Registration Result', 'API Response Time (ms)', 'Created At', 'Updated At'
        ];
        
        fputcsv($output, $headers);
        
        // Apply same filters as main query
        $where = [];
        $params = [];
        
        if (!empty($_GET['date_from'])) {
            $where[] = 'DATE(call_start_time) >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'DATE(call_start_time) <= ?';
            $params[] = $_GET['date_to'];
        }
        if (!empty($_GET['phone'])) {
            $where[] = 'phone_number LIKE ?';
            $params[] = '%' . $_GET['phone'] . '%';
        }
        if (!empty($_GET['extension'])) {
            $where[] = 'extension = ?';
            $params[] = $_GET['extension'];
        }
        if (!empty($_GET['outcome'])) {
            $where[] = 'call_outcome = ?';
            $params[] = $_GET['outcome'];
        }
        
        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY call_start_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch()) {
            $csvRow = [
                $row['id'], $row['call_id'], $row['unique_id'], $row['phone_number'], $row['extension'],
                $row['call_start_time'], $row['call_end_time'], $row['call_duration'], $row['call_outcome'],
                $row['call_type'], $row['is_reservation'] ? 'Yes' : 'No', $row['reservation_time'],
                $row['language_used'], $row['language_changed'] ? 'Yes' : 'No', $row['initial_choice'],
                $row['confirmation_attempts'], $row['total_retries'], $row['name_attempts'], 
                $row['pickup_attempts'], $row['destination_attempts'], $row['reservation_attempts'],
                $row['confirmed_default_address'] ? 'Yes' : 'No', $row['pickup_address'], 
                $row['pickup_lat'], $row['pickup_lng'], $row['destination_address'], 
                $row['destination_lat'], $row['destination_lng'], $row['google_tts_calls'],
                $row['google_stt_calls'], $row['edge_tts_calls'], $row['geocoding_api_calls'],
                $row['user_api_calls'], $row['registration_api_calls'], $row['date_parsing_api_calls'],
                $row['tts_processing_time'], $row['stt_processing_time'], $row['geocoding_processing_time'],
                $row['total_processing_time'], $row['successful_registration'] ? 'Yes' : 'No',
                $row['operator_transfer_reason'], $row['error_messages'], $row['recording_path'],
                $row['log_file_path'], $row['progress_json_path'], $row['tts_provider'], 
                $row['callback_mode'], $row['days_valid'], $row['user_name'], $row['registration_result'],
                $row['api_response_time'], $row['created_at'], $row['updated_at']
            ];
            
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
    }
    
    // ===== AUDIO SERVING =====
    
    private function serveAudio() {
        $file = $_GET['file'] ?? '';
        if (empty($file) || !file_exists($file)) {
            http_response_code(404);
            echo json_encode(['error' => 'Audio file not found']);
            return;
        }
        
        $mimeType = 'audio/wav';
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        switch (strtolower($extension)) {
            case 'mp3':
                $mimeType = 'audio/mpeg';
                break;
            case 'ogg':
                $mimeType = 'audio/ogg';
                break;
        }
        
        header("Content-Type: {$mimeType}");
        header('Content-Length: ' . filesize($file));
        header('Accept-Ranges: bytes');
        
        readfile($file);
        exit;
    }
    
    // ===== RESPONSE HELPERS =====
    
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    private function sendErrorResponse($message, $statusCode = 400) {
        http_response_code($statusCode);
        echo json_encode([
            'error' => $message,
            'status' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // ===== MODERN DASHBOARD HTML =====
    
    private function renderDashboard() {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGI Analytics Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid var(--gray-200);
            padding: 2rem;
            overflow-y: auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .search-section {
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-800);
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
        }
        
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
        }
        
        .header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }
        
        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .stat-card-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .stat-card-icon {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            font-size: 1rem;
        }
        
        .icon-primary {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }
        
        .icon-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .icon-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .icon-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .change-positive {
            color: var(--success);
        }
        
        .change-negative {
            color: var(--danger);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            height: 400px;
            display: flex;
            flex-direction: column;
        }
        
        .chart-canvas-container {
            flex: 1;
            position: relative;
            height: 300px;
            min-height: 250px;
        }
        
        .chart-canvas-container canvas {
            position: absolute !important;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .calls-table-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.875rem;
        }
        
        .table td {
            font-size: 0.875rem;
            color: var(--gray-800);
        }
        
        .table tbody tr:hover {
            background: var(--gray-50);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .badge-info {
            background: rgba(6, 182, 212, 0.1);
            color: var(--info);
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .pagination-info {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 0.75rem;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .call-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 0.5rem;
        }
        
        .detail-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-600);
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-size: 0.875rem;
            color: var(--gray-900);
            font-weight: 500;
        }
        
        .audio-player {
            margin: 1rem 0;
        }
        
        .map-container {
            height: 300px;
            border-radius: 0.5rem;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--gray-600);
        }
        
        .spinner {
            width: 1.5rem;
            height: 1.5rem;
            border: 2px solid var(--gray-300);
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .chart-container {
                height: 350px;
            }
            
            .chart-canvas-container {
                height: 250px;
                min-height: 200px;
            }
            
            .main-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                AGI Analytics
            </div>
            
            <div class="search-section">
                <form id="filterForm">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Phone, Call ID, User...">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Extension</label>
                        <input type="text" name="extension" class="form-control" placeholder="Extension">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Call Type</label>
                        <select name="call_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="immediate">Immediate</option>
                            <option value="reservation">Reservation</option>
                            <option value="operator">Operator</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Outcome</label>
                        <select name="outcome" class="form-control">
                            <option value="">All Outcomes</option>
                            <option value="success">Success</option>
                            <option value="hangup">Hangup</option>
                            <option value="operator_transfer">Operator Transfer</option>
                            <option value="error">Error</option>
                            <option value="in_progress">In Progress</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control">
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <button type="button" id="clearFilters" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </form>
                
                <div style="margin-top: 1rem;">
                    <button id="exportBtn" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Analytics Dashboard</h1>
                <div class="btn-group">
                    <button id="refreshBtn" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i> Refresh
                    </button>
                    <button id="realtimeBtn" class="btn btn-primary">
                        <i class="fas fa-play"></i> Real-time
                    </button>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid" id="statsGrid">
                <!-- Stats will be loaded here -->
            </div>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Hourly Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Calls per Hour</h3>
                        <select id="hourlyDateSelect" class="form-control" style="width: auto;">
                            <option value="">Today</option>
                        </select>
                    </div>
                    <div class="chart-canvas-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
                
                <!-- Outcomes Pie Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Call Outcomes</h3>
                    </div>
                    <div class="chart-canvas-container">
                        <canvas id="outcomesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Calls Table -->
            <div class="calls-table-container">
                <div class="table-header">
                    <h3 class="table-title">Recent Calls</h3>
                    <div class="btn-group">
                        <select id="limitSelect" class="form-control" style="width: auto;">
                            <option value="25">25 per page</option>
                            <option value="50" selected>50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table" id="callsTable">
                        <thead>
                            <tr>
                                <th>Phone</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>User</th>
                                <th>Location</th>
                                <th>APIs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="callsTableBody">
                            <!-- Calls will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Call Detail Modal -->
    <div class="modal" id="callDetailModal">
        <div class="modal-content" style="width: 90vw; max-width: 1000px;">
            <div class="modal-header">
                <h3 class="modal-title">Call Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="callDetailBody">
                <!-- Call details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        let currentPage = 1;
        let currentLimit = 50;
        let currentFilters = {};
        let realtimeInterval = null;
        let charts = {};
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboard();
            setupEventListeners();
            loadDateOptions();
        });
        
        // Setup event listeners
        function setupEventListeners() {
            // Filter form
            document.getElementById('filterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                applyFilters();
            });
            
            // Clear filters
            document.getElementById('clearFilters').addEventListener('click', function() {
                document.getElementById('filterForm').reset();
                currentFilters = {};
                currentPage = 1;
                loadCalls();
                updateURL();
            });
            
            // Export button
            document.getElementById('exportBtn').addEventListener('click', function() {
                exportCSV();
            });
            
            // Refresh button
            document.getElementById('refreshBtn').addEventListener('click', function() {
                loadDashboard();
            });
            
            // Real-time toggle
            document.getElementById('realtimeBtn').addEventListener('click', function() {
                toggleRealtime();
            });
            
            // Limit select
            document.getElementById('limitSelect').addEventListener('change', function() {
                currentLimit = parseInt(this.value);
                currentPage = 1;
                loadCalls();
            });
            
            // Hourly date select
            document.getElementById('hourlyDateSelect').addEventListener('change', function() {
                loadHourlyChart(this.value);
            });
            
            // Modal close on background click
            document.getElementById('callDetailModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        }
        
        // Load dashboard data
        function loadDashboard() {
            showLoading();
            loadStats();
            loadCalls();
            loadHourlyChart();
            loadOutcomesChart();
            hideLoading();
        }
        
        // Load statistics
        function loadStats() {
            fetch('?endpoint=dashboard')
                .then(function(response) { 
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json(); 
                })
                .then(function(data) { 
                    var stats = data.today_summary || data.realtime_stats || {};
                    renderStats(stats);
                })
                .catch(function(error) { 
                    console.error('Error loading stats:', error);
                    // Render with default/empty stats on error
                    renderStats({
                        total_calls: 0,
                        successful_calls: 0,
                        avg_duration: 0,
                        unique_callers: 0
                    });
                });
        }
        
        // Render statistics
        function renderStats(stats) {
            var statsGrid = document.getElementById('statsGrid');
            
            var totalCalls = stats.total_calls || 0;
            var successfulCalls = stats.successful_calls || 0;
            var successRate = totalCalls > 0 ? Math.round((successfulCalls / totalCalls) * 100) : 0;
            
            statsGrid.innerHTML = 
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">Total Calls Today</span>' +
                        '<div class="stat-card-icon icon-primary">' +
                            '<i class="fas fa-phone"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value">' + totalCalls.toLocaleString() + '</div>' +
                    '<div class="stat-change change-positive">' +
                        '<i class="fas fa-arrow-up"></i> Active' +
                    '</div>' +
                '</div>' +
                
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">Successful Calls</span>' +
                        '<div class="stat-card-icon icon-success">' +
                            '<i class="fas fa-check-circle"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value">' + successfulCalls.toLocaleString() + '</div>' +
                    '<div class="stat-change change-positive">' +
                        successRate + '% success rate' +
                    '</div>' +
                '</div>' +
                
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">Avg Duration</span>' +
                        '<div class="stat-card-icon icon-info">' +
                            '<i class="fas fa-clock"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value">' + Math.round(stats.avg_duration || 0) + 's</div>' +
                    '<div class="stat-change">' +
                        'Per call average' +
                    '</div>' +
                '</div>' +
                
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">Unique Callers</span>' +
                        '<div class="stat-card-icon icon-warning">' +
                            '<i class="fas fa-users"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value">' + (stats.unique_callers || 0) + '</div>' +
                    '<div class="stat-change">' +
                        'Different numbers' +
                    '</div>' +
                '</div>';
        }
        
        // Load calls table
        function loadCalls() {
            var params = new URLSearchParams();
            params.set('page', currentPage);
            params.set('limit', currentLimit);
            
            for (var key in currentFilters) {
                if (currentFilters.hasOwnProperty(key)) {
                    params.set(key, currentFilters[key]);
                }
            }
            
            fetch('?endpoint=calls&' + params.toString())
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    renderCalls(data.calls);
                    renderPagination(data.pagination);
                })
                .catch(function(error) {
                    console.error('Error loading calls:', error);
                    showError('Failed to load calls');
                });
        }
        
        // Render calls table
        function renderCalls(calls) {
            var tbody = document.getElementById('callsTableBody');
            
            if (calls.length === 0) {
                tbody.innerHTML = '<tr>' +
                        '<td colspan="9" style="text-align: center; padding: 2rem; color: var(--gray-600);">' +
                            '<i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>' +
                            'No calls found matching your criteria' +
                        '</td>' +
                    '</tr>';
                return;
            }
            
            tbody.innerHTML = calls.map(function(call) {
                return '<tr onclick="showCallDetail(\'' + (call.call_id || '') + '\')" style="cursor: pointer;">' +
                    '<td>' + (call.phone_number || 'N/A') + '</td>' +
                    '<td>' + formatDate(call.call_start_time) + '</td>' +
                    '<td>' + formatDuration(call.call_duration) + '</td>' +
                    '<td>' + renderStatusBadge(call.call_outcome) + '</td>' +
                    '<td>' + (call.is_reservation ? 'Reservation' : 'Immediate') + '</td>' +
                    '<td>' + truncate(call.user_name || 'N/A', 20) + '</td>' +
                    '<td>' + renderLocationInfo(call) + '</td>' +
                    '<td>' + renderAPIUsage(call) + '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); showCallDetail(\'' + (call.call_id || '') + '\')">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>';
            }).join('');
        }
        
        // Render status badge
        function renderStatusBadge(status) {
            var badges = {
                'success': 'badge-success',
                'hangup': 'badge-danger', 
                'operator_transfer': 'badge-warning',
                'error': 'badge-danger',
                'in_progress': 'badge-info'
            };
            
            var badgeClass = badges[status] || 'badge-info';
            var displayName = status.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            
            return '<span class="badge ' + badgeClass + '">' + displayName + '</span>';
        }
        
        // Render location info
        function renderLocationInfo(call) {
            if (call.pickup_address) {
                var hasCoords = call.pickup_lat && call.pickup_lng;
                var icon = hasCoords ? 'map-marker-alt' : 'map';
                return '<i class="fas fa-' + icon + '"></i> ' + truncate(call.pickup_address, 25);
            }
            return '<span style="color: var(--gray-400);">No location</span>';
        }
        
        // Render API usage
        function renderAPIUsage(call) {
            var tts = (call.google_tts_calls || 0) + (call.edge_tts_calls || 0);
            var stt = call.google_stt_calls || 0;
            var geo = call.geocoding_api_calls || 0;
            
            return '<small>TTS:' + tts + ' STT:' + stt + ' GEO:' + geo + '</small>';
        }
        
        // Load hourly chart
        function loadHourlyChart(date) {
            if (!date) date = '';
            
            var params = date ? '?endpoint=hourly&date=' + encodeURIComponent(date) : '?endpoint=hourly';
            
            fetch(params)
                .then(function(response) { 
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json(); 
                })
                .then(function(data) { 
                    var hourlyData = data.hourly_data || [];
                    renderHourlyChart(hourlyData);
                })
                .catch(function(error) { 
                    console.error('Error loading hourly chart:', error);
                    // Render empty chart on error
                    renderHourlyChart([]);
                });
        }
        
        // Render hourly chart
        function renderHourlyChart(hourlyData) {
            var ctx = document.getElementById('hourlyChart').getContext('2d');
            
            if (charts.hourly) {
                charts.hourly.destroy();
            }
            
            // Handle empty data - create 24-hour template with zeros
            if (!hourlyData || hourlyData.length === 0) {
                hourlyData = [];
                for (var h = 0; h < 24; h++) {
                    hourlyData.push({
                        hour: h,
                        total_calls: 0,
                        successful_calls: 0
                    });
                }
            }
            
            // Convert data for older browsers
            var hourLabels = [];
            var totalCallsData = [];
            var successfulCallsData = [];
            
            for (var i = 0; i < hourlyData.length; i++) {
                hourLabels.push(hourlyData[i].hour + ':00');
                totalCallsData.push(hourlyData[i].total_calls || 0);
                successfulCallsData.push(hourlyData[i].successful_calls || 0);
            }
            
            charts.hourly = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: hourLabels,
                    datasets: [{
                        label: 'Total Calls',
                        data: totalCallsData,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    }, {
                        label: 'Successful Calls',
                        data: successfulCallsData,
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(0, 0, 0, 0.8)',
                            borderWidth: 1
                        }
                    }
                }
            });
        }
        
        // Load outcomes chart
        function loadOutcomesChart() {
            fetch('?endpoint=analytics')
                .then(function(response) { 
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json(); 
                })
                .then(function(data) { 
                    var outcomes = data.outcomes || [];
                    renderOutcomesChart(outcomes);
                })
                .catch(function(error) { 
                    console.error('Error loading outcomes chart:', error);
                    // Render empty chart on error
                    renderOutcomesChart([]);
                });
        }
        
        // Render outcomes chart
        function renderOutcomesChart(outcomes) {
            var ctx = document.getElementById('outcomesChart').getContext('2d');
            
            if (charts.outcomes) {
                charts.outcomes.destroy();
            }
            
            var colors = [
                'rgb(16, 185, 129)',  // success - green
                'rgb(239, 68, 68)',   // hangup - red
                'rgb(245, 158, 11)',  // operator_transfer - yellow
                'rgb(6, 182, 212)',   // in_progress - cyan
                'rgb(139, 69, 19)'    // error - brown
            ];
            
            // Handle empty data
            if (!outcomes || outcomes.length === 0) {
                outcomes = [{
                    call_outcome: 'no_data',
                    count: 1
                }];
            }
            
            // Convert data for older browsers
            var labels = [];
            var dataValues = [];
            var backgroundColors = [];
            
            for (var i = 0; i < outcomes.length; i++) {
                var outcome = outcomes[i];
                if (outcome.call_outcome === 'no_data') {
                    labels.push('No Data Available');
                    dataValues.push(1);
                    backgroundColors.push('rgb(156, 163, 175)');
                } else {
                    labels.push(outcome.call_outcome.replace('_', ' ').toUpperCase());
                    dataValues.push(outcome.count || 0);
                    backgroundColors.push(colors[i % colors.length]);
                }
            }
            
            charts.outcomes = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dataValues,
                        backgroundColor: backgroundColors,
                        borderWidth: 3,
                        borderColor: '#fff',
                        hoverBorderWidth: 4,
                        hoverBorderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(0, 0, 0, 0.8)',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var percentage = Math.round((context.parsed / total) * 100);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Show call detail modal
        function showCallDetail(callId) {
            var modal = document.getElementById('callDetailModal');
            var body = document.getElementById('callDetailBody');
            
            body.innerHTML = '<div class="loading"><div class="spinner"></div>Loading call details...</div>';
            modal.classList.add('show');
            
            fetch('?endpoint=call&call_id=' + encodeURIComponent(callId))
                .then(function(response) { return response.json(); })
                .then(function(call) { renderCallDetail(call); })
                .catch(function(error) {
                    console.error('Error loading call detail:', error);
                    body.innerHTML = '<div style="color: var(--danger); text-align: center; padding: 2rem;">Failed to load call details</div>';
                });
        }
        
        // Render call detail
        function renderCallDetail(call) {
            var body = document.getElementById('callDetailBody');
            
            var html = '<div class="call-detail-grid">' +
                '<div class="detail-item">' +
                    '<div class="detail-label">Call ID</div>' +
                    '<div class="detail-value">' + (call.call_id || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">Phone Number</div>' +
                    '<div class="detail-value">' + (call.phone_number || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">Extension</div>' +
                    '<div class="detail-value">' + (call.extension || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">Duration</div>' +
                    '<div class="detail-value">' + formatDuration(call.call_duration) + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">Status</div>' +
                    '<div class="detail-value">' + renderStatusBadge(call.call_outcome) + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">User Name</div>' +
                    '<div class="detail-value">' + (call.user_name || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">Language</div>' +
                    '<div class="detail-value">' + (call.language_used || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">API Calls</div>' +
                    '<div class="detail-value">' + (call.total_api_calls || 0) + '</div>' +
                '</div>' +
            '</div>';
            
            // Add location information
            if (call.pickup_address) {
                html += '<h4 style="margin: 1.5rem 0 1rem;">Location Information</h4>' +
                       '<div class="call-detail-grid">' +
                           '<div class="detail-item" style="grid-column: 1 / -1;">' +
                               '<div class="detail-label">Pickup Address</div>' +
                               '<div class="detail-value">' + call.pickup_address + '</div>' +
                           '</div>';
                
                if (call.destination_address) {
                    html += '<div class="detail-item" style="grid-column: 1 / -1;">' +
                               '<div class="detail-label">Destination Address</div>' +
                               '<div class="detail-value">' + call.destination_address + '</div>' +
                           '</div>';
                }
                
                html += '</div>';
                
                // Add map if coordinates exist
                if (call.pickup_lat && call.pickup_lng) {
                    html += '<div class="map-container" id="callMap"></div>';
                }
            }
            
            // Add recordings section
            if (call.recordings && call.recordings.length > 0) {
                html += '<h4 style="margin: 1.5rem 0 1rem;">Recordings</h4>';
                for (var i = 0; i < call.recordings.length; i++) {
                    var recording = call.recordings[i];
                    var sizeKB = (recording.size / 1024).toFixed(1);
                    html += '<div class="audio-player">' +
                               '<strong>' + recording.filename + '</strong> (' + sizeKB + ' KB)<br>' +
                               '<audio controls style="width: 100%; margin-top: 0.5rem;">' +
                                   '<source src="?action=audio&file=' + encodeURIComponent(recording.path) + '" type="audio/wav">' +
                                   'Your browser does not support the audio element.' +
                               '</audio>' +
                           '</div>';
                }
            }
            
            // Add call log section
            if (call.call_log && call.call_log.length > 0) {
                html += '<h4 style="margin: 1.5rem 0 1rem;">Call Log</h4>' +
                       '<div style="background: var(--gray-50); padding: 1rem; border-radius: 0.5rem; max-height: 300px; overflow-y: auto;">' +
                           '<pre style="font-size: 0.75rem; color: var(--gray-800); white-space: pre-wrap;">';
                
                var logMessages = [];
                for (var i = 0; i < call.call_log.length; i++) {
                    logMessages.push(call.call_log[i].message);
                }
                html += logMessages.join('\\n');
                html += '</pre></div>';
            }
            
            body.innerHTML = html;
            
            // Initialize map after DOM is updated
            if (call.pickup_address && call.pickup_lat && call.pickup_lng) {
                setTimeout(function() {
                    var map = L.map('callMap').setView([call.pickup_lat, call.pickup_lng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                    
                    var pickupMarker = L.marker([call.pickup_lat, call.pickup_lng])
                        .addTo(map)
                        .bindPopup('Pickup: ' + (call.pickup_address || ''));
                        
                    if (call.destination_lat && call.destination_lng) {
                        var destMarker = L.marker([call.destination_lat, call.destination_lng])
                            .addTo(map)
                            .bindPopup('Destination: ' + (call.destination_address || ''));
                            
                        var group = new L.featureGroup([pickupMarker, destMarker]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    }
                }, 100);
            }
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('callDetailModal').classList.remove('show');
        }
        
        // Apply filters
        function applyFilters() {
            var form = document.getElementById('filterForm');
            var formData = new FormData(form);
            
            currentFilters = {};
            var entries = formData.entries();
            var entry = entries.next();
            while (!entry.done) {
                var key = entry.value[0];
                var value = entry.value[1];
                if (value.trim() !== '') {
                    currentFilters[key] = value;
                }
                entry = entries.next();
            }
            
            currentPage = 1;
            loadCalls();
            updateURL();
        }
        
        // Export CSV
        function exportCSV() {
            var params = new URLSearchParams();
            params.set('action', 'export');
            
            for (var key in currentFilters) {
                if (currentFilters.hasOwnProperty(key)) {
                    params.set(key, currentFilters[key]);
                }
            }
            
            window.open('?' + params.toString(), '_blank');
        }
        
        // Toggle real-time updates
        function toggleRealtime() {
            var btn = document.getElementById('realtimeBtn');
            
            if (realtimeInterval) {
                clearInterval(realtimeInterval);
                realtimeInterval = null;
                btn.innerHTML = '<i class="fas fa-play"></i> Real-time';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-primary');
            } else {
                realtimeInterval = setInterval(() => {
                    loadStats();
                    if (currentPage === 1) {
                        loadCalls();
                    }
                }, 10000); // Update every 10 seconds
                
                btn.innerHTML = '<i class="fas fa-stop"></i> Stop';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-danger');
            }
        }
        
        // Render pagination
        function renderPagination(pagination) {
            var container = document.getElementById('pagination');
            
            var showingFrom = ((pagination.page - 1) * pagination.limit) + 1;
            var showingTo = Math.min(pagination.page * pagination.limit, pagination.total);
            
            container.innerHTML = 
                '<div class="pagination-info">' +
                    'Showing ' + showingFrom + ' to ' + showingTo + ' of ' + pagination.total + ' results' +
                '</div>' +
                '<div class="btn-group">' +
                    (pagination.page > 1 ? 
                        '<button class="btn btn-secondary" onclick="goToPage(' + (pagination.page - 1) + ')">' +
                            '<i class="fas fa-chevron-left"></i> Previous' +
                        '</button>' 
                    : '') +
                    
                    (pagination.page < pagination.pages ? 
                        '<button class="btn btn-secondary" onclick="goToPage(' + (pagination.page + 1) + ')">' +
                            'Next <i class="fas fa-chevron-right"></i>' +
                        '</button>' 
                    : '') +
                '</div>';
        }
        
        // Go to page
        function goToPage(page) {
            currentPage = page;
            loadCalls();
            updateURL();
        }
        
        // Load date options
        function loadDateOptions() {
            var select = document.getElementById('hourlyDateSelect');
            var today = new Date();
            
            for (var i = 0; i < 7; i++) {
                var date = new Date(today);
                date.setDate(date.getDate() - i);
                var dateStr = date.toISOString().split('T')[0];
                var label = i === 0 ? 'Today' : i === 1 ? 'Yesterday' : dateStr;
                
                var option = new Option(label, dateStr);
                select.appendChild(option);
            }
        }
        
        // Utility functions
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
        
        function formatDuration(seconds) {
            if (!seconds || seconds === 0) return '0s';
            
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            
            if (mins > 0) {
                return `${mins}m ${secs}s`;
            }
            return `${seconds}s`;
        }
        
        function truncate(str, length) {
            if (!str) return '';
            return str.length > length ? str.substring(0, length) + '...' : str;
        }
        
        function showLoading() {
            // Could add a global loading indicator
        }
        
        function hideLoading() {
            // Could hide global loading indicator
        }
        
        function showError(message) {
            console.error(message);
            // Could show toast notification
        }
        
        function updateURL() {
            const params = new URLSearchParams({
                page: currentPage,
                limit: currentLimit,
                ...currentFilters
            });
            
            const newUrl = window.location.pathname + '?' + params.toString();
            window.history.pushState({}, '', newUrl);
        }
        
        // Load initial filters from URL
        const urlParams = new URLSearchParams(window.location.search);
        for (let [key, value] of urlParams.entries()) {
            if (['page', 'limit'].includes(key)) {
                if (key === 'page') currentPage = parseInt(value) || 1;
                if (key === 'limit') currentLimit = parseInt(value) || 50;
            } else {
                currentFilters[key] = value;
                const input = document.querySelector(`[name="${key}"]`);
                if (input) input.value = value;
            }
        }
    </script>
</body>
</html>
        <?php
    }
}

// Initialize and handle request
try {
    $analytics = new AGIAnalytics();
    $analytics->handleRequest();
} catch (Exception $e) {
    error_log("AGI Analytics Error: " . $e->getMessage());
    http_response_code(500);
    
    if (isset($_GET['endpoint'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'System error occurred',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo "<!DOCTYPE html><html><head><title>System Error</title></head><body>";
        echo "<h1>System Error</h1><p>The analytics system encountered an error. Please check the logs.</p></body></html>";
    }
}
?>