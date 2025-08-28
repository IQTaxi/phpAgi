#!/usr/bin/php
<?php
include 'config.php';

/**
 * Automated Taxi Call Registration System
 * 
 * Production-ready AGI handler for FreePBX/Asterisk that processes
 * incoming taxi booking calls with speech recognition and API integration.
 * 
 * Features:
 * - Greek speech-to-text recognition via Google Cloud STT
 * - Text-to-speech confirmation via Google Cloud TTS
 * - Address geocoding via Google Maps API
 * - Call registration via IQTaxi API
 * - User data management with pickup address confirmation
 * 
 * @author AGI Call Handler
 * @version Production 1.0
 */
class AGICallHandler
{
    // === PROPERTIES ===
    private $agi_env = [];
    private $uniqueid = '';
    private $extension = '';
    private $caller_id = '';
    private $caller_num = '';
    private $config = [];
    private $current_exten = '';
    private $filebase = '';
    private $log_prefix = '';
    
    // Configuration properties
    private $phone_to_call = '';
    private $welcome_playback = '';
    private $api_key = '';
    private $client_token = '';
    private $register_base_url = '';
    private $tts_provider = 'google';
    private $days_valid = 7;
    private $current_language = 'el';
    private $default_language = 'el';
    
    // Call data properties
    private $max_retries = 3;
    private $name_result = '';
    private $pickup_result = '';
    private $pickup_location = [];
    private $dest_result = '';
    private $dest_location = [];
    private $reservation_result = '';
    private $reservation_timestamp = '';
    private $is_reservation = false;

    // === ANALYTICS PROPERTIES ===
    private $analytics_data = [];
    private $call_start_time;
    private $step_start_time;
    private $current_step = 'initialization';
    private $stt_calls = 0;
    private $tts_calls = 0;
    private $stt_total_time = 0;
    private $tts_total_time = 0;
    private $attempt_counts = [];
    private $analytics_url = 'http://127.0.0.1/agi_analytics.php';
    private $start_time;
    private $db_connection = null;

    // === INITIALIZATION ===
    
    public function __construct()
    {
        $this->setupAGIEnvironment();
        $this->setupFilePaths();
        $this->loadConfiguration();
        $this->checkExtensionExists();
        $this->initializeAnalytics();
    }

    /**
     * Initialize AGI environment by reading stdin
     */
    private function setupAGIEnvironment()
    {
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') break;
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $this->agi_env[trim($key)] = trim($value);
            }
        }

        $this->uniqueid = $this->agi_env['agi_uniqueid'] ?? '';
        $this->extension = $this->agi_env['agi_extension'] ?? '';
        $this->caller_id = isset($this->agi_env['agi_callerid']) ? str_replace(['<', '>'], '', $this->agi_env['agi_callerid']) : '';
        $this->caller_num = $this->agi_env['agi_callerid'] ?? '';
        $this->current_exten = $this->extension;
    }

    /**
     * Create directory structure for call recordings and logs
     */
    private function setupFilePaths()
    {
        $this->filebase = "/var/auto_register_call/{$this->current_exten}/{$this->caller_num}/{$this->uniqueid}";
        $this->log_prefix = "[{$this->uniqueid}]";

        $recordings_dir = $this->filebase . "/recordings";
        if (!is_dir($recordings_dir)) {
            mkdir($recordings_dir, 0755, true);
        }
    }

    /**
     * Load extension configuration with API keys and settings
     */
    private function loadConfiguration()
    {
        $this->config = (new AGICallHandlerConfig())->globalConfiguration;

        if (isset($this->config[$this->extension])) {
            $config = $this->config[$this->extension];
            $this->phone_to_call = $config['failCallTo'];
            $this->welcome_playback = $config['welcomePlayback'];
            $this->api_key = $config['googleApiKey'];
            $this->client_token = $config['clientToken'];
            $this->register_base_url = $config['registerBaseUrl'];
            $this->tts_provider = $config['tts'] ?? 'google';
            $this->days_valid = intval($config['daysValid'] ?? 7);
            $this->default_language = $config['defaultLanguage'] ?? 'el';
            $this->current_language = $this->default_language;
        }
    }

    private function checkExtensionExists()
    {
        if (!isset($this->config[$this->extension])) {
            $this->logMessage("Extension {$this->extension} not found in config");
            $this->redirectToOperator();
            exit(0); // Exit with success code after transferring to operator
        }
    }
    
    /**
     * Initialize enhanced analytics tracking with real-time updates
     */
    private function initializeAnalytics()
    {
        $this->start_time = microtime(true);
        $this->step_start_time = $this->start_time;
        $this->setupDatabaseConnection();
        $this->analytics_data = [
            'call_id' => $this->uniqueid,
            'unique_id' => $this->uniqueid,
            'phone_number' => $this->caller_num,
            'extension' => $this->extension,
            'call_start_time' => date('Y-m-d H:i:s'),
            'call_outcome' => 'in_progress',
            'call_type' => 'immediate',
            'is_reservation' => 0,
            'language_used' => 'el',
            'language_changed' => 0,
            'tts_provider' => $this->tts_provider,
            'callback_mode' => intval($this->config[$this->extension]['callbackMode'] ?? 1),
            'days_valid' => intval($this->days_valid),
            'recording_path' => $this->filebase,
            'log_file_path' => $this->filebase . "/log.txt",
            'progress_json_path' => $this->filebase . "/progress.json",
            'google_tts_calls' => 0,
            'google_stt_calls' => 0,
            'edge_tts_calls' => 0,
            'geocoding_api_calls' => 0,
            'user_api_calls' => 0,
            'registration_api_calls' => 0,
            'date_parsing_api_calls' => 0,
            'tts_processing_time' => 0,
            'stt_processing_time' => 0,
            'geocoding_processing_time' => 0,
            'total_processing_time' => 0,
            'confirmation_attempts' => 0,
            'total_retries' => 0,
            'name_attempts' => 0,
            'pickup_attempts' => 0,
            'destination_attempts' => 0,
            'reservation_attempts' => 0,
            'confirmed_default_address' => 0,
            'successful_registration' => 0
        ];
        
        // Insert initial record immediately and track pickup
        $this->trackStep('call_start');
        $this->createAnalyticsRecord();
    }

    // === ENHANCED ANALYTICS METHODS ===
    
    /**
     * Setup direct database connection for real-time analytics
     */
    private function setupDatabaseConnection()
    {
        try {
            $dsn = "mysql:host=127.0.0.1;port=3306;dbname=asterisk;charset=utf8mb4";
            
            // Try primary credentials first
            try {
                $this->db_connection = new PDO($dsn, 'freepbxuser', 'WXS/NCr0WnbY');
            } catch (PDOException $e) {
                // Fallback to root
                $this->db_connection = new PDO($dsn, 'root', '');
            }
            
            $this->db_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db_connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $this->logMessage("Analytics DB connection established");
        } catch (PDOException $e) {
            $this->logMessage("Analytics DB connection failed: " . $e->getMessage());
            $this->db_connection = null;
        }
    }
    
    /**
     * Track each step of the call flow with timestamps and duration
     */
    private function trackStep($step)
    {
        $now = microtime(true);
        $step_duration = $now - $this->step_start_time;
        
        // Log step transition
        $this->logMessage("STEP: {$this->current_step} -> {$step} (took " . round($step_duration * 1000) . "ms)");
        
        // Update analytics with step info (only valid fields)
        // Note: current_step and last_step_time don't exist in database, so we skip them
        
        // Real-time database update if possible
        $this->updateAnalyticsInDB();
        
        $this->current_step = $step;
        $this->step_start_time = $now;
    }
    
    /**
     * Direct database update for real-time analytics
     */
    private function updateAnalyticsInDB()
    {
        if (!$this->db_connection) return;
        
        try {
            $stmt = $this->db_connection->prepare(
                "UPDATE automated_calls_analitycs SET 
                call_outcome = ?, 
                google_tts_calls = ?,
                google_stt_calls = ?,
                pickup_address = ?,
                pickup_lat = ?,
                pickup_lng = ?,
                destination_address = ?,
                destination_lat = ?,
                destination_lng = ?,
                call_end_time = ?
                WHERE call_id = ?"
            );
            
            $stmt->execute([
                $this->analytics_data['call_outcome'] ?? 'in_progress',
                $this->analytics_data['google_tts_calls'] ?? 0,
                $this->analytics_data['google_stt_calls'] ?? 0,
                $this->analytics_data['pickup_address'] ?? null,
                $this->analytics_data['pickup_lat'] ?? null,
                $this->analytics_data['pickup_lng'] ?? null,
                $this->analytics_data['destination_address'] ?? null,
                $this->analytics_data['destination_lat'] ?? null,
                $this->analytics_data['destination_lng'] ?? null,
                $this->analytics_data['call_end_time'] ?? null,
                $this->analytics_data['call_id']
            ]);
            
        } catch (PDOException $e) {
            $this->logMessage("DB analytics update failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get all step durations for JSON storage
     */
    private function getStepDurations()
    {
        $durations = [];
        foreach ($this->analytics_data as $key => $value) {
            if (strpos($key, 'step_duration_') === 0) {
                $durations[$key] = $value;
            }
        }
        return $durations;
    }
    
    private function createAnalyticsRecord()
    {
        $this->logMessage("ANALYTICS: Creating initial record");
        $this->sendAnalyticsData('call', 'POST');
    }
    
    private function updateAnalyticsRecord()
    {
        $this->logMessage("ANALYTICS: Updating record");
        $this->sendAnalyticsData('call', 'PUT');
    }
    
    private function setCallType($type)
    {
        $this->trackStep('call_type_selected');
        $this->analytics_data['call_type'] = $type;
        if ($type === 'reservation') {
            $this->analytics_data['is_reservation'] = 1;
        }
        $this->updateAnalyticsRecord();
    }
    
    private function setCallOutcome($outcome, $reason = '')
    {
        $this->trackStep('call_outcome_set');
        $this->analytics_data['call_outcome'] = $outcome;
        if (!empty($reason)) {
            $this->analytics_data['operator_transfer_reason'] = $reason;
        }
        $this->updateAnalyticsRecord();
    }
    
    private function setLanguage($language, $changed = false)
    {
        $this->analytics_data['language_used'] = $language;
        $this->analytics_data['language_changed'] = $changed ? 1 : 0;
        $this->updateAnalyticsRecord();
    }
    
    private function setInitialChoice($choice)
    {
        $this->analytics_data['initial_choice'] = $choice;
        $this->updateAnalyticsRecord();
    }
    
    private function setUserInfo($name = '', $confirmedDefaultAddress = false)
    {
        if (!empty($name)) {
            $this->analytics_data['user_name'] = $name;
        }
        $this->analytics_data['confirmed_default_address'] = $confirmedDefaultAddress ? 1 : 0;
        $this->updateAnalyticsRecord();
    }
    
    private function setPickupAddress($address, $lat = null, $lng = null)
    {
        $this->analytics_data['pickup_address'] = $address;
        if ($lat !== null && $lng !== null) {
            $this->analytics_data['pickup_lat'] = $lat;
            $this->analytics_data['pickup_lng'] = $lng;
        }
        $this->updateAnalyticsRecord();
    }
    
    private function setDestinationAddress($address, $lat = null, $lng = null)
    {
        $this->analytics_data['destination_address'] = $address;
        if ($lat !== null && $lng !== null) {
            $this->analytics_data['destination_lat'] = $lat;
            $this->analytics_data['destination_lng'] = $lng;
        }
        $this->updateAnalyticsRecord();
    }
    
    private function setReservationTime($timestamp)
    {
        $this->analytics_data['reservation_time'] = date('Y-m-d H:i:s', $timestamp);
        $this->updateAnalyticsRecord();
    }
    
    private function trackTTSCall($provider = 'google', $processingTime = 0)
    {
        if ($provider === 'google') {
            $this->analytics_data['google_tts_calls']++;
        } else {
            $this->analytics_data['edge_tts_calls']++;
        }
        $this->analytics_data['tts_processing_time'] += $processingTime;
        $this->updateAnalyticsRecord();
    }
    
    private function trackSTTCall($processingTime = 0)
    {
        $this->analytics_data['google_stt_calls']++;
        $this->analytics_data['stt_processing_time'] += $processingTime;
        $this->updateAnalyticsRecord();
    }
    
    private function trackGeocodingCall($processingTime = 0)
    {
        $this->analytics_data['geocoding_api_calls']++;
        $this->analytics_data['geocoding_processing_time'] += $processingTime;
        $this->updateAnalyticsRecord();
    }
    
    private function trackUserAPICall()
    {
        $this->analytics_data['user_api_calls']++;
        $this->updateAnalyticsRecord();
    }
    
    private function trackRegistrationAPICall($successful = false, $result = '', $responseTime = 0)
    {
        $this->analytics_data['registration_api_calls']++;
        $this->analytics_data['successful_registration'] = $successful ? 1 : 0;
        $this->analytics_data['registration_result'] = $result;
        $this->analytics_data['api_response_time'] = intval($responseTime);
        $this->updateAnalyticsRecord();
    }
    
    private function trackDateParsingAPICall()
    {
        $this->analytics_data['date_parsing_api_calls']++;
        $this->updateAnalyticsRecord();
    }
    
    private function trackAttempt($type)
    {
        switch ($type) {
            case 'confirmation':
                $this->analytics_data['confirmation_attempts']++;
                break;
            case 'name':
                $this->analytics_data['name_attempts']++;
                $this->analytics_data['total_retries']++;
                break;
            case 'pickup':
                $this->analytics_data['pickup_attempts']++;
                $this->analytics_data['total_retries']++;
                break;
            case 'destination':
                $this->analytics_data['destination_attempts']++;
                $this->analytics_data['total_retries']++;
                break;
            case 'reservation':
                $this->analytics_data['reservation_attempts']++;
                $this->analytics_data['total_retries']++;
                break;
        }
        $this->updateAnalyticsRecord();
    }
    
    private function addErrorMessage($message)
    {
        $existing = $this->analytics_data['error_messages'] ?? '';
        $this->analytics_data['error_messages'] = $existing . ($existing ? "\\n" : '') . $message;
    }
    
    private function finalizeCall()
    {
        $this->logMessage("ANALYTICS: Starting finalizeCall()");
        
        // Calculate total call duration
        $endTime = microtime(true);
        $this->analytics_data['call_duration'] = round($endTime - $this->start_time);
        $this->analytics_data['call_end_time'] = date('Y-m-d H:i:s');
        
        // Calculate total processing time
        $this->analytics_data['total_processing_time'] = round(
            $this->analytics_data['tts_processing_time'] + 
            $this->analytics_data['stt_processing_time'] + 
            $this->analytics_data['geocoding_processing_time']
        );
        
        $this->logMessage("ANALYTICS: About to send data with call_id: " . $this->analytics_data['call_id']);
        
        // Send to analytics via HTTP
        $this->sendAnalyticsData('call', 'PUT');
        
        $this->logMessage("ANALYTICS: finalizeCall() completed");
    }
    
    private function sendAnalyticsData($endpoint = 'call', $method = 'POST')
    {
        $this->logMessage("ANALYTICS: Starting sendAnalyticsData() - Method: {$method}");
        
        try {
            // Build URL with endpoint parameter for new analytics system
            $url = $this->analytics_url . '?endpoint=' . urlencode($endpoint);
            $this->logMessage("ANALYTICS: Sending to URL: " . $url);
            
            $json_data = json_encode($this->analytics_data, JSON_UNESCAPED_UNICODE);
            $this->logMessage("ANALYTICS: JSON data length: " . strlen($json_data));
            
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_POSTFIELDS => $json_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json_data),
                    'User-Agent: AGI-Call-Handler/1.0'
                ]
            ];
            
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
            } elseif ($method === 'PUT') {
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            } elseif ($method === 'DELETE') {
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                unset($options[CURLOPT_POSTFIELDS]);
            }
            
            curl_setopt_array($ch, $options);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $response_info = curl_getinfo($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $this->logMessage("ANALYTICS CURL Error: {$curl_error}");
                $this->addErrorMessage("Analytics communication failed: {$curl_error}");
            } else {
                $this->logMessage("ANALYTICS {$method} - HTTP: {$http_code}, Response time: {$response_info['total_time']}s");
                $this->logMessage("ANALYTICS Response: " . substr($response, 0, 300));
                
                if ($http_code >= 200 && $http_code < 300) {
                    $this->logMessage("ANALYTICS: Successfully sent data to analytics system");
                } else {
                    $this->logMessage("ANALYTICS Warning: HTTP {$http_code} - {$response}");
                    $this->addErrorMessage("Analytics HTTP error: {$http_code}");
                }
            }
        } catch (Exception $e) {
            $this->logMessage("ANALYTICS Exception: " . $e->getMessage());
            $this->addErrorMessage("Analytics exception: " . $e->getMessage());
        }
        
        $this->logMessage("ANALYTICS: sendAnalyticsData() completed");
    }
    
    

    // === LANGUAGE AND LOCALIZATION ===

    /**
     * Get language configuration mapping
     */
    private function getLanguageConfig()
    {
        return [
            'el' => [
                'tts_code' => 'el-GR',
                'stt_code' => 'el-GR',
                'edge_voice' => 'el-GR-AthinaNeural'
            ],
            'en' => [
                'tts_code' => 'en-US',
                'stt_code' => 'en-US',
                'edge_voice' => 'en-US-JennyNeural'
            ],
            'bg' => [
                'tts_code' => 'bg-BG',
                'stt_code' => 'bg-BG',
                'edge_voice' => 'bg-BG-BorislavNeural'
            ]
        ];
    }

    /**
     * Get sound file path for current language with fallback to iqtaxi
     * Supports both WAV and MP3 formats (WAV preferred)
     */
    private function getSoundFile($sound_name)
    {
        $sound_path = $this->config[$this->extension]['soundPath'] ?? '/var/sounds/iqtaxi';
        $primary_file = "{$sound_path}/{$sound_name}_{$this->current_language}";
        
        // Check if primary file exists (WAV preferred, then MP3)
        $file_exists = $this->checkSoundFileExists($primary_file);
        
        if ($file_exists || $sound_path === '/var/sounds/iqtaxi') {
            return $primary_file;
        } else {
            $fallback_file = "/var/sounds/iqtaxi/{$sound_name}_{$this->current_language}";
            $this->logMessage("Sound file not found at {$primary_file}, using fallback: {$fallback_file}");
            return $fallback_file;
        }
    }

    /**
     * Check if sound file exists in supported formats (WAV preferred, then MP3)
     */
    private function checkSoundFileExists($file_path)
    {
        // Check WAV first (preferred format), then MP3
        $formats = ['.wav', '.mp3'];
        
        foreach ($formats as $format) {
            if (file_exists($file_path . $format)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get localized text for current language
     */
    private function getLocalizedText($key)
    {
        $texts = [
            'anonymous_message' => [
                'el' => 'Παρακαλούμε καλέστε από έναν αριθμό που δεν είναι ανώνυμος',
                'en' => 'Please call from a number that is not anonymous',
                'bg' => 'Моля, обадете се от номер, който не е анонимен'
            ],
            'greeting_with_name' => [
                'el' => 'Γεια σας {name}. Θέλετε να χρησιμοποιήσετε τη διεύθυνση παραλαβής {address}? Πατήστε 1 για ναι ή 2 για να εισάγετε νέα διεύθυνση παραλαβής.',
                'en' => 'Hello {name}. Would you like to use the pickup address {address}? Press 1 for yes or 2 to enter a new pickup address.',
                'bg' => 'Здравейте {name}. Искате ли да използвате адреса за вземане {address}? Натиснете 1 за да или 2, за да въведете нов адрес за вземане.'
            ],
            'confirmation_text' => [
                'el' => 'Παρακαλώ επιβεβαιώστε. Όνομα: {name}. Παραλαβή: {pickup}. Προορισμός: {destination}',
                'en' => 'Please confirm. Name: {name}. Pickup: {pickup}. Destination: {destination}',
                'bg' => 'Моля потвърдете. Име: {name}. Вземане: {pickup}. Дестинация: {destination}'
            ],
            'reservation_confirmation_text' => [
                'el' => 'Παρακαλώ επιβεβαιώστε. Όνομα: {name}. Παραλαβή: {pickup}. Προορισμός: {destination}. Ώρα ραντεβού: {time}',
                'en' => 'Please confirm. Name: {name}. Pickup: {pickup}. Destination: {destination}. Reservation time: {time}',
                'bg' => 'Моля потвърдете. Име: {name}. Вземане: {pickup}. Дестинация: {destination}. Час на резервацията: {time}'
            ],
            'reservation_time_confirmation' => [
                'el' => 'Το ραντεβού είναι για {time}, πατήστε 0 για επιβεβαίωση ή 1 για να προσπαθήσετε ξανά',
                'en' => 'The reservation is for {time}, press 0 to confirm or 1 to try again',
                'bg' => 'Резервацията е за {time}, натиснете 0 за потвърждение или 1, за да опитате отново'
            ],
            'registration_error' => [
                'el' => 'Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας',
                'en' => 'Something went wrong with registering your route',
                'bg' => 'Нещо се обърка с регистрирането на вашия маршрут'
            ],
            'automated_call_comment' => [
                'el' => '[ΑΥΤΟΜΑΤΟΠΟΙΗΜΕΝΗ ΚΛΗΣΗ]',
                'en' => '[AUTOMATED CALL]',
                'bg' => '[АВТОМАТИЗИРАНО ОБАЖДАНЕ]'
            ],
            'automated_reservation_comment' => [
                'el' => '[ΑΥΤΟΜΑΤΟΠΟΙΗΜΕΝΗ ΚΡΑΤΗΣΗ - {time}]',
                'en' => '[AUTOMATED RESERVATION - {time}]',
                'bg' => '[АВТОМАТИЗИРАНА РЕЗЕРВАЦИЯ - {time}]'
            ]
        ];

        if (isset($texts[$key][$this->current_language])) {
            return $texts[$key][$this->current_language];
        }
        
        return $texts[$key]['el'] ?? $key;
    }

    // === LOGGING AND UTILITIES ===

    /**
     * Log messages to file with timestamp
     */
    private function logMessage($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "$timestamp - {$this->log_prefix} $message\n";
        
        // Always log to main asterisk calls log
        error_log($log_entry, 3, "/var/log/auto_register_call/asterisk_calls.log");
        
        // Only log non-analytics messages to individual call log
        if (!empty($this->filebase) && !$this->isAnalyticsMessage($message)) {
            error_log($log_entry, 3, "{$this->filebase}/log.txt");
        }
    }
    
    /**
     * Check if message is analytics-related
     */
    private function isAnalyticsMessage($message)
    {
        $analytics_keywords = [
            'ANALYTICS:',
            'STEP:',
            'Channel status response:',
            'Analytics DB connection',
            'sendAnalyticsData',
            'updateAnalyticsRecord',
            'createAnalyticsRecord',
            'finalizeCall',
            'trackStep',
            'trackAttempt',
            'trackSTTCall',
            'trackTTSCall',
            'DB analytics',
            'ANALYTICS CURL',
            'ANALYTICS Warning',
            'ANALYTICS Response',
            'ANALYTICS Exception',
            'HTTP:', // HTTP response codes from analytics
            'Response time:', // Analytics timing info
            'Successfully sent data to analytics'
        ];
        
        foreach ($analytics_keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Send AGI command and return response
     */
    private function agiCommand($command)
    {
        echo $command . "\n";
        return trim(fgets(STDIN));
    }

    /**
     * Start music on hold for better user experience during processing
     */
    private function startMusicOnHold()
    {
        $this->agiCommand('EXEC StartMusicOnHold');
    }

    /**
     * Stop music on hold when processing is complete
     */
    private function stopMusicOnHold()
    {
        $this->agiCommand('EXEC StopMusicOnHold');
    }

    /**
     * Save data to JSON progress file
     */
    private function saveJson($key, $value)
    {
        $json_file = $this->filebase . "/progress.json";
        $data = [];

        if (file_exists($json_file)) {
            $json_content = file_get_contents($json_file);
            $data = json_decode($json_content, true);
            if ($data === null) $data = [];
        }

        $keys = explode('.', $key);
        $current = &$data;
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($current[$keys[$i]])) {
                $current[$keys[$i]] = [];
            }
            $current = &$current[$keys[$i]];
        }
        $current[$keys[count($keys) - 1]] = $value;

        file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Check if caller is anonymous or blocked
     */
    private function isAnonymousCaller()
    {
        return empty($this->caller_num) ||
            $this->caller_num === 'anonymous' ||
            $this->caller_num === '' ||
            $this->caller_num === 'unknown' ||
            strlen($this->caller_num) <= 5;
    }

    /**
     * Transfer call to human operator
     */
    private function redirectToOperator()
    {
        $this->trackStep('operator_transfer');
        $this->logMessage("Redirecting to operator: {$this->phone_to_call}");
        
        // Ensure call outcome is set if not already
        if ($this->analytics_data['call_outcome'] === 'in_progress') {
            $this->setCallOutcome('operator_transfer', 'Call transferred to operator');
        }
        
        $this->finalizeCall();
        $this->agiCommand("EXEC \"Dial\" \"{$this->phone_to_call},20\"");
        $this->agiCommand('HANGUP');
    }

    /**
     * Play audio file and wait for DTMF input
     */
    private function readDTMF($prompt_file, $digits = 1, $timeout = 10)
    {
        $response = $this->agiCommand("EXEC \"Read\" \"USER_CHOICE,{$prompt_file},{$digits},,1,{$timeout}\"");
        $choice_response = $this->agiCommand("GET VARIABLE USER_CHOICE");

        if (preg_match('/200 result=1 \((.+)\)/', $choice_response, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private function removeDiacritics($text)
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }

    // === API INTEGRATION ===

    /**
     * Retrieve existing user data from IQTaxi API
     */
    private function getUserFromAPI($phone)
    {
        $this->trackUserAPICall();
        
        $url = rtrim($this->register_base_url, '/') . "/api/Calls/checkCallerID/{$phone}";
        $headers = [
            "Authorization: {$this->client_token}",
            "Content-Type: application/json; charset=UTF-8"
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) return [];

        $data = json_decode($response, true);
        if (!$data || $data['result']['result'] !== 'SUCCESS') return [];

        return $this->parseUserAPIResponse($data['response']);
    }

    private function parseUserAPIResponse($response_data)
    {
        $output = [];

        if (!empty($response_data['callerName'])) {
            $output['name'] = $response_data['callerName'];
        }

        if (!empty($response_data['doNotServe'])) {
            $output['doNotServe'] = $response_data['doNotServe'] ? '1' : '0';
        }

        $main_address = $response_data['mainAddresss'] ?? null;
        if ($main_address) {
            if (!empty($main_address['address'])) {
                $output['pickup'] = $main_address['address'];
            }
            if (isset($main_address['lat']) && isset($main_address['lng'])) {
                $output['latLng'] = [
                    'lat' => $main_address['lat'],
                    'lng' => $main_address['lng']
                ];
            }
        }

        return $output;
    }

    // === TEXT-TO-SPEECH SERVICES ===

    /**
     * Convert text to speech using Google Cloud TTS API
     */
    private function callGoogleTTS($text, $output_file)
    {
        $startTime = microtime(true);
        
        $lang_config = $this->getLanguageConfig();
        $language_code = $lang_config[$this->current_language]['tts_code'] ?? 'el-GR';

        $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->api_key}";
        $headers = ["Content-Type: application/json"];
        $data = [
            "input" => ["text" => $text],
            "voice" => ["languageCode" => $language_code],
            "audioConfig" => [
                "audioEncoding" => "MP3",
                "speakingRate" => 1.0,
                "pitch" => 0.0,
                "volumeGainDb" => 0.0
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $this->trackTTSCall('google', $processingTime);

        if ($http_code !== 200 || !$response) return false;

        $result = json_decode($response, true);
        if (!$result || !isset($result['audioContent'])) return false;

        $processingTime = (microtime(true) - $startTime) * 1000;
        $this->trackTTSCall('google', $processingTime);

        return $this->processAudioFile($result['audioContent'], $output_file);
    }

    /**
     * Convert text to speech using Edge TTS
     */
    private function callEdgeTTS($text, $output_file)
    {
        $startTime = microtime(true);
        
        $lang_config = $this->getLanguageConfig();
        $edge_voice = $lang_config[$this->current_language]['edge_voice'] ?? 'el-GR-AthinaNeural';

        $temp_file = $output_file . '_temp.wav';
        $wav_file = $output_file . '.wav';
        $escaped_text = escapeshellarg($text);
        $escaped_temp = escapeshellarg($temp_file);
        $escaped_output = escapeshellarg($wav_file);

        $cmd = "edge-tts --voice {$edge_voice} --text {$escaped_text} --write-media {$escaped_temp} 2>/dev/null";
        $this->logMessage("Edge TTS command: {$cmd}");

        exec($cmd, $output, $return_code);
        
        // Convert to Asterisk-compatible format
        if ($return_code === 0 && file_exists($temp_file)) {
            $convert_cmd = "ffmpeg -y -i {$escaped_temp} -ac 1 -ar 8000 -acodec pcm_s16le {$escaped_output} 2>/dev/null";
            $this->logMessage("Audio conversion command: {$convert_cmd}");
            exec($convert_cmd, $convert_output, $convert_return);
            $this->logMessage("Audio conversion return code: {$convert_return}");
            
            unlink($temp_file);
            $return_code = $convert_return;
        }
        
        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $this->trackTTSCall('edge', $processingTime);

        if ($return_code !== 0) {
            $this->logMessage("Edge TTS failed with return code: {$return_code}");
            return false;
        }

        if (!file_exists($wav_file) || filesize($wav_file) <= 100) {
            $this->logMessage("Edge TTS output file invalid or too small");
            return false;
        }

        return $this->convertAudioFile($wav_file);
    }

    /**
     * Convert text to speech using the configured TTS provider
     */
    private function callTTS($text, $output_file)
    {
        if ($this->tts_provider === 'edge-tts') {
            return $this->callEdgeTTS($text, $output_file);
        } else {
            return $this->callGoogleTTS($text, $output_file);
        }
    }

    private function processAudioFile($audio_content, $output_file)
    {
        $audio_data = base64_decode($audio_content);
        $mp3_file = $output_file . '.mp3';
        file_put_contents($mp3_file, $audio_data);

        $wav_file = $output_file . '.wav';
        // Convert to Asterisk-compatible format: mono, 8kHz, 16-bit PCM
        $cmd = "ffmpeg -y -i \"$mp3_file\" -ac 1 -ar 8000 -acodec pcm_s16le \"$wav_file\" 2>/dev/null";
        $this->logMessage("Audio conversion command: {$cmd}");
        exec($cmd, $output, $return_code);
        $this->logMessage("Audio conversion return code: {$return_code}");

        unlink($mp3_file);
        
        $file_exists = file_exists($wav_file);
        $file_size = $file_exists ? filesize($wav_file) : 0;
        $this->logMessage("Audio file exists: " . ($file_exists ? 'yes' : 'no') . ", size: {$file_size} bytes");
        
        return $file_exists && $file_size > 100;
    }

    private function convertAudioFile($wav_file)
    {
        $escaped_output = escapeshellarg($wav_file);
        $cmd_convert = "ffmpeg -y -i {$escaped_output} -ac 1 -ar 8000 {$escaped_output}_converted.wav 2>/dev/null";
        exec($cmd_convert);

        if (file_exists($wav_file . '_converted.wav')) {
            unlink($wav_file);
            rename($wav_file . '_converted.wav', $wav_file);
        }

        return file_exists($wav_file) && filesize($wav_file) > 100;
    }

    // === SPEECH-TO-TEXT SERVICE ===

    /**
     * Convert speech to text using Google Cloud STT API
     */
    private function callGoogleSTT($wav_file)
    {
        $startTime = microtime(true);
        
        $this->logMessage("STT: Checking file: {$wav_file}");

        if (!file_exists($wav_file)) {
            $this->logMessage("STT: File does not exist");
            return '';
        }

        $file_size = filesize($wav_file);
        $this->logMessage("STT: File size: {$file_size} bytes");

        if ($file_size < 1000) {
            $this->logMessage("STT: File too small, likely empty recording");
            return '';
        }

        $audio_content = base64_encode(file_get_contents($wav_file));
        $lang_config = $this->getLanguageConfig();
        $language_code = $lang_config[$this->current_language]['stt_code'] ?? 'el-GR';

        $url = "https://speech.googleapis.com/v1/speech:recognize?key={$this->api_key}";
        $headers = ["Content-Type: application/json"];
        $body = [
            "config" => [
                "encoding" => "LINEAR16",
                "sampleRateHertz" => 8000,
                "languageCode" => $language_code,
                "profanityFilter" => false
            ],
            "audio" => ["content" => $audio_content]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $this->trackSTTCall($processingTime);

        if ($http_code !== 200 || !$response) {
            $this->logMessage("STT: HTTP error: {$http_code}");
            return '';
        }

        $result = json_decode($response, true);
        if (!$result || empty($result['results'])) {
            $this->logMessage("STT: No results in response");
            return '';
        }

        $transcript = '';
        foreach ($result['results'] as $res) {
            foreach ($res['alternatives'] as $alt) {
                $transcript .= $alt['transcript'] . ' ';
            }
        }

        $processingTime = (microtime(true) - $startTime) * 1000;
        $this->trackSTTCall($processingTime);

        return trim($transcript);
    }

    // === GEOCODING SERVICE ===

    /**
     * Geocode address using Google Maps API
     */
    private function getLatLngFromGoogle($address, $is_pickup = true)
    {
        // Handle special cases first
        if ($this->handleSpecialAddresses($address, $is_pickup)) {
            return $this->handleSpecialAddresses($address, $is_pickup);
        }

        $startTime = microtime(true);

        $url = "https://maps.googleapis.com/maps/api/geocode/json";
        $params = http_build_query([
            "address" => $address,
            "key" => $this->api_key,
            "language" => "el-GR"
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $this->trackGeocodingCall($processingTime);

        if ($http_code !== 200 || !$response) return null;

        $data = json_decode($response, true);
        if (!$data || $data['status'] !== 'OK' || empty($data['results'])) return null;

        return $this->validateLocationResult($data['results'][0], $is_pickup);
    }

    private function handleSpecialAddresses($address, $is_pickup)
    {
        $normalized_address = $this->removeDiacritics(strtolower(trim($address)));
        $center_addresses = ["κεντρο", "τοπικο", "κεντρο αθηνα", "κεντρο θεσσαλονικη"];

        if (!$is_pickup && in_array($normalized_address, $center_addresses)) {
            return [
                "address" => $address,
                "location_type" => "EXACT",
                "latLng" => ["lat" => 0, "lng" => 0]
            ];
        }

        // Handle airport for Cosmos extension
        if (isset($this->config[$this->extension]['name']) && $this->config[$this->extension]['name'] === 'Cosmos') {
            $airport_terms = ['αεροδομιο', 'αεροδρόμιο', 'airport'];
            foreach ($airport_terms as $term) {
                if (strpos($normalized_address, $this->removeDiacritics($term)) !== false) {
                    return [
                        "address" => "Αεροδρόμιο Αθηνών Ελευθέριος Βενιζέλος, Σπάτα",
                        "location_type" => "ROOFTOP",
                        "latLng" => ["lat" => 37.9363405, "lng" => 23.946668]
                    ];
                }
            }
        }

        return false;
    }

    private function validateLocationResult($result, $is_pickup)
    {
        $location_type = $result['geometry']['location_type'];
        
        // Validate location type based on pickup/dropoff and config
        if ($is_pickup) {
            // Pickup locations ALWAYS require precise location types
            if (!in_array($location_type, ['ROOFTOP', 'RANGE_INTERPOLATED'])) {
                $this->logMessage("Pickup location rejected - type: {$location_type}, address: {$result['formatted_address']}");
                return null;
            }
        } else {
            // Dropoff location validation based on config
            $strict_dropoff = $this->config[$this->extension]['strictDropoffLocation'] ?? false;
                
            if ($strict_dropoff && !in_array($location_type, ['ROOFTOP', 'RANGE_INTERPOLATED'])) {
                $this->logMessage("Dropoff location rejected (strict mode) - type: {$location_type}, address: {$result['formatted_address']}");
                return null;
            }
        }
        
        $this->logMessage("Location accepted - type: {$location_type}, address: {$result['formatted_address']}");
        return [
            "address" => $result['formatted_address'],
            "location_type" => $location_type,
            "latLng" => [
                "lat" => $result['geometry']['location']['lat'],
                "lng" => $result['geometry']['location']['lng']
            ]
        ];
    }

    // === CALL REGISTRATION ===

    /**
     * Register taxi call via IQTaxi API
     */
    private function registerCall()
    {
        $startTime = microtime(true);
        
        $url = rtrim($this->register_base_url, '/') . "/api/Calls/RegisterNoLogin";
        $headers = [
            "Authorization: {$this->client_token}",
            "Content-Type: application/json; charset=UTF-8"
        ];

        $payload = $this->buildRegistrationPayload();
        $this->addCallbackUrlToPayload($payload);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $this->logMessage("Registration API URL: {$url}");
        $this->logMessage("Registration payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $responseTime = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds

        $result = $this->processRegistrationResponse($response, $http_code, $curl_error);
        
        // Track registration API call
        $successful = !$result['callOperator'];
        $this->trackRegistrationAPICall($successful, $result['msg'], $responseTime);

        return $result;
    }

    private function buildRegistrationPayload()
    {
        return [
            "callTimeStamp" => $this->is_reservation ? $this->reservation_timestamp : null,
            "callerPhone" => $this->caller_num,
            "customerName" => $this->name_result,
            "roadName" => $this->pickup_result,
            "latitude" => $this->pickup_location['latLng']['lat'],
            "longitude" => $this->pickup_location['latLng']['lng'],
            "destination" => $this->dest_result,
            "destLatitude" => $this->dest_location['latLng']['lat'] ?? 0,
            "destLongitude" => $this->dest_location['latLng']['lng'] ?? 0,
            "taxisNo" => 1,
            "comments" => $this->getCallComment(),
            "referencePath" => $this->filebase,
            "daysValid" => $this->days_valid
        ];
    }

    private function addCallbackUrlToPayload(&$payload)
    {
        $callback_mode = $this->config[$this->extension]['callbackMode'] ?? 1;
        if ($callback_mode == 2) {
            $callback_url = $this->config[$this->extension]['callbackUrl'] ?? '';
            if (!empty($callback_url)) {
                $payload["callBackURL"] = $callback_url . "?path=" . urlencode($this->filebase);
            }
        }
    }

    private function getCallComment()
    {
        if ($this->is_reservation) {
            return str_replace('{time}', $this->reservation_result, $this->getLocalizedText('automated_reservation_comment'));
        } else {
            return $this->getLocalizedText('automated_call_comment');
        }
    }

    private function processRegistrationResponse($response, $http_code, $curl_error)
    {
        $this->logMessage("Registration API HTTP code: {$http_code}");
        if ($curl_error) {
            $this->logMessage("Registration API cURL error: {$curl_error}");
        }

        if ($http_code !== 200 || !$response) {
            $this->logMessage("Registration API failed - HTTP: {$http_code}");
            return ["callOperator" => true, "msg" => $this->getLocalizedText('registration_error')];
        }

        $decoded_response = json_decode($response, true);
        $readable_response = $decoded_response ? json_encode($decoded_response, JSON_UNESCAPED_UNICODE) : $response;
        $this->logMessage("Registration API response: " . substr($readable_response, 0, 500));

        $data = json_decode($response, true);
        if (!$data) {
            $this->logMessage("Registration API - Invalid JSON response");
            return ["callOperator" => true, "msg" => $this->getLocalizedText('registration_error')];
        }

        $result_data = $data['result'] ?? [];
        $result_code = $result_data['resultCode'] ?? -1;
        $msg = trim($result_data['msg'] ?? '');

        $this->logMessage("Registration API result - Code: {$result_code}, Message: {$msg}");

        if (empty($msg)) {
            $msg = $this->getLocalizedText('registration_error');
        }

        $call_operator = ($result_code !== 0);
        $this->logMessage("Registration result - callOperator: " . ($call_operator ? 'true' : 'false'));

        return ["callOperator" => $call_operator, "msg" => $msg];
    }

    // === DATE PARSING SERVICE ===

    /**
     * Parse date from text using date recognition service
     */
    private function parseDateFromText($text)
    {
        $this->trackDateParsingAPICall();
        
        $url = "https://www.iqtaxi.com/DateRecognizers/api/Recognize/Date";
        $headers = ["Content-Type: application/json"];
        $body = [
            "input" => $text,
            "key" => $this->api_key,
            "translateFrom" => "en",
            "translateTo" => "en",
            "matchLang" => "en-US"
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            $this->logMessage("Date parsing HTTP error: {$http_code}");
            return null;
        }

        $result = json_decode($response, true);
        if (!$result || empty($result['bestMatch'])) {
            $this->logMessage("No date match found in response");
            return null;
        }

        return $result;
    }

    // === DATA COLLECTION METHODS ===

    /**
     * Collect customer name via speech recognition
     */
    private function collectName()
    {
        $this->trackStep('collecting_name');
        $this->logMessage("Starting name collection");

        for ($try = 1; $try <= $this->max_retries; $try++) {
            $this->trackAttempt('name');
            $this->logMessage("Name attempt {$try}/{$this->max_retries}");
            
            // Check channel status before attempting any operation
            $status_result = $this->agiCommand('CHANNEL STATUS');
            $this->logMessage("Channel status before name: {$status_result}");
            if (strpos($status_result, '6') !== false) { // Channel is up
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('name') . '"');
            } else {
                $this->logMessage("Channel is not up, aborting name collection");
                return false;
            }

            $recording_file = "{$this->filebase}/recordings/name_{$try}";
            $this->logMessage("Starting recording to: {$recording_file}");
            $record_result = $this->agiCommand("RECORD FILE \"{$recording_file}\" wav \"#\" 10000 0 BEEP");
            $this->logMessage("Record result: {$record_result}");
            
            // Check if recording failed due to dead channel
            if (strpos($record_result, 'dead channel') !== false || strpos($record_result, '511') !== false) {
                $this->logMessage("Recording failed due to dead channel, aborting name collection");
                return false;
            }
            
            // Check if recording file was created
            $wav_file = $recording_file . ".wav";
            if (file_exists($wav_file)) {
                $file_size = filesize($wav_file);
                $this->logMessage("Recording file created: {$wav_file} ({$file_size} bytes)");
            } else {
                $this->logMessage("Recording file NOT created: {$wav_file}");
            }

            $this->startMusicOnHold();
            $name = $this->callGoogleSTT($recording_file . ".wav");
            $this->stopMusicOnHold();

            $this->logMessage("STT result for name: '{$name}' (length: " . strlen(trim($name)) . ")");

            if (!empty($name) && strlen(trim($name)) > 1) {
                $this->name_result = trim($name);
                $this->setUserInfo($this->name_result);
                $this->saveJson("name", $this->name_result);
                $this->logMessage("Name successfully captured: {$this->name_result}");
                return true;
            } else {
                $this->logMessage("Name rejected - empty or too short");
            }

            if ($try < $this->max_retries) {
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('invalid_input') . '"');
            }
        }

        $this->logMessage("Failed to capture name after {$this->max_retries} attempts");
        return false;
    }

    /**
     * Collect pickup address via speech recognition and geocoding
     */
    private function collectPickup()
    {
        $this->trackStep('collecting_pickup');
        $this->logMessage("Starting pickup collection");

        for ($try = 1; $try <= $this->max_retries; $try++) {
            $this->trackAttempt('pickup');
            $this->logMessage("Pickup attempt {$try}/{$this->max_retries}");
            
            // Check channel status before attempting any operation
            $status_result = $this->agiCommand('CHANNEL STATUS');
            $this->logMessage("Channel status before pickup: {$status_result}");
            if (strpos($status_result, '6') !== false) { // Channel is up
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('pick_up') . '"');
            } else {
                $this->logMessage("Channel is not up, aborting pickup collection");
                return false;
            }

            $recording_file = "{$this->filebase}/recordings/pickup_{$try}";
            $this->logMessage("Starting recording to: {$recording_file}");
            $record_result = $this->agiCommand("RECORD FILE \"{$recording_file}\" wav \"#\" 10000 0 BEEP");
            $this->logMessage("Record result: {$record_result}");
            
            // Check if recording failed due to dead channel
            if (strpos($record_result, 'dead channel') !== false || strpos($record_result, '511') !== false) {
                $this->logMessage("Recording failed due to dead channel, aborting pickup collection");
                return false;
            }

            $this->startMusicOnHold();
            $pickup = $this->callGoogleSTT($recording_file . ".wav");
            $this->logMessage("STT result for pickup: {$pickup}");

            if (!empty($pickup) && strlen(trim($pickup)) > 2) {
                $location = $this->getLatLngFromGoogle($pickup, true);
                $this->stopMusicOnHold();

                if ($location) {
                    $this->pickup_result = trim($pickup);
                    $this->pickup_location = $location;
                    $this->setPickupAddress(
                        $this->pickup_result,
                        $location['latLng']['lat'] ?? null,
                        $location['latLng']['lng'] ?? null
                    );
                    $this->saveJson("pickup", $this->pickup_result);
                    $this->saveJson("pickupLocation", $this->pickup_location);
                    $this->logMessage("Pickup successfully captured: {$this->pickup_result}");
                    return true;
                }
            } else {
                $this->stopMusicOnHold();
            }

            if ($try < $this->max_retries) {
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('invalid_input') . '"');
            }
        }

        $this->logMessage("Failed to capture pickup after {$this->max_retries} attempts");
        return false;
    }

    /**
     * Collect destination address via speech recognition and geocoding
     */
    private function collectDestination()
    {
        $this->trackStep('collecting_destination');
        $this->logMessage("Starting destination collection");

        for ($try = 1; $try <= $this->max_retries; $try++) {
            $this->trackAttempt('destination');
            $this->logMessage("Destination attempt {$try}/{$this->max_retries}");
            
            // Check channel status before attempting any operation
            $status_result = $this->agiCommand('CHANNEL STATUS');
            $this->logMessage("Channel status before destination: {$status_result}");
            if (strpos($status_result, '6') !== false) { // Channel is up
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('drop_off') . '"');
            } else {
                $this->logMessage("Channel is not up, aborting destination collection");
                return false;
            }

            $recording_file = "{$this->filebase}/recordings/dest_{$try}";
            $this->logMessage("Starting recording to: {$recording_file}");
            $record_result = $this->agiCommand("RECORD FILE \"{$recording_file}\" wav \"#\" 10000 0 BEEP");
            $this->logMessage("Record result: {$record_result}");
            
            // Check if recording failed due to dead channel
            if (strpos($record_result, 'dead channel') !== false || strpos($record_result, '511') !== false) {
                $this->logMessage("Recording failed due to dead channel, aborting destination collection");
                return false;
            }

            $this->startMusicOnHold();
            $dest = $this->callGoogleSTT($recording_file . ".wav");
            $this->logMessage("STT result for destination: {$dest}");

            if (!empty($dest) && strlen(trim($dest)) > 2) {
                $location = $this->getLatLngFromGoogle($dest, false);
                $this->stopMusicOnHold();

                if ($location) {
                    $this->dest_result = trim($dest);
                    $this->dest_location = $location;
                    $this->setDestinationAddress(
                        $this->dest_result,
                        $location['latLng']['lat'] ?? null,
                        $location['latLng']['lng'] ?? null
                    );
                    $this->saveJson("destination", $this->dest_result);
                    $this->saveJson("destinationLocation", $this->dest_location);
                    $this->logMessage("Destination successfully captured: {$this->dest_result}");
                    return true;
                }
            } else {
                $this->stopMusicOnHold();
            }

            if ($try < $this->max_retries) {
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('invalid_input') . '"');
            }
        }

        $this->logMessage("Failed to capture destination after {$this->max_retries} attempts");
        return false;
    }

    /**
     * Collect reservation time via speech recognition and date parsing
     */
    private function collectReservationTime()
    {
        $this->trackStep('collecting_reservation_time');
        $this->logMessage("Starting reservation time collection");

        for ($try = 1; $try <= $this->max_retries; $try++) {
            $this->trackAttempt('reservation');
            $this->logMessage("Reservation time attempt {$try}/{$this->max_retries}");
            
            // Check channel status before attempting any operation
            $status_result = $this->agiCommand('CHANNEL STATUS');
            $this->logMessage("Channel status before reservation: {$status_result}");
            if (strpos($status_result, '6') !== false) { // Channel is up
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('date_input') . '"');
            } else {
                $this->logMessage("Channel is not up, aborting reservation collection");
                return false;
            }

            $recording_file = "{$this->filebase}/recordings/reservation_{$try}";
            $this->logMessage("Starting recording to: {$recording_file}");
            $record_result = $this->agiCommand("RECORD FILE \"{$recording_file}\" wav \"#\" 10000 0 BEEP");
            $this->logMessage("Record result: {$record_result}");
            
            // Check if recording failed due to dead channel
            if (strpos($record_result, 'dead channel') !== false || strpos($record_result, '511') !== false) {
                $this->logMessage("Recording failed due to dead channel, aborting reservation collection");
                return false;
            }

            $this->startMusicOnHold();
            $reservation_speech = $this->callGoogleSTT($recording_file . ".wav");
            $this->logMessage("STT result for reservation: {$reservation_speech}");

            if (!empty($reservation_speech) && strlen(trim($reservation_speech)) > 2) {
                $parsed_date = $this->parseDateFromText(trim($reservation_speech));
                $this->stopMusicOnHold();

                if ($parsed_date && !empty($parsed_date['formattedBestMatch'])) {
                    if ($this->confirmReservationTime($parsed_date)) {
                        return true;
                    }
                }
            } else {
                $this->stopMusicOnHold();
            }

            if ($try < $this->max_retries) {
                $this->agiCommand('EXEC Playback "' . $this->getSoundFile('invalid_input') . '"');
            }
        }

        $this->logMessage("Failed to capture reservation time after {$this->max_retries} attempts");
        return false;
    }

    private function confirmReservationTime($parsed_date)
    {
        $this->reservation_result = $parsed_date['formattedBestMatch'];
        $this->reservation_timestamp = $parsed_date['bestMatchUnixTimestamp'];
        $this->setReservationTime($this->reservation_timestamp);

        $confirmation_text = str_replace('{time}', $this->reservation_result, $this->getLocalizedText('reservation_time_confirmation'));
        $confirm_file = "{$this->filebase}/confirmdate";

        $this->startMusicOnHold();
        $tts_success = $this->callTTS($confirmation_text, $confirm_file);
        $this->stopMusicOnHold();

        if ($tts_success) {
            $choice = $this->readDTMF($confirm_file, 1, 10);
            $this->logMessage("User reservation time choice: {$choice}");

            if ($choice == "0") {
                $this->logMessage("User confirmed reservation time: {$this->reservation_result}");
                $this->saveJson("reservation", $this->reservation_result);
                $this->saveJson("reservationStamp", $this->reservation_timestamp);
                return true;
            }
        }

        return false;
    }

    // === CONFIRMATION AND PROCESSING ===

    /**
     * Confirm collected data and register the call
     */
    private function confirmAndRegister()
    {
        $this->trackStep('confirming_data');
        for ($try = 1; $try <= 3; $try++) {
            $this->trackAttempt('confirmation');
            $this->logMessage("Confirmation attempt {$try}/3");

            if ($this->generateAndPlayConfirmation()) {
                $choice = $this->readDTMF($this->getSoundFile('options'), 1, 10);
                $this->logMessage("User choice: {$choice}");

                if ($choice == "0") {
                    $this->processConfirmedCall();
                    return;
                } elseif ($choice == "1") {
                    if (!$this->collectName()) {
                        $this->setCallOutcome('operator_transfer', 'Failed to collect name');
                        $this->redirectToOperator();
                        return;
                    }
                } elseif ($choice == "2") {
                    if (!$this->collectPickup()) {
                        $this->setCallOutcome('operator_transfer', 'Failed to collect pickup');
                        $this->redirectToOperator();
                        return;
                    }
                } elseif ($choice == "3") {
                    if (!$this->collectDestination()) {
                        $this->setCallOutcome('operator_transfer', 'Failed to collect destination');
                        $this->redirectToOperator();
                        return;
                    }
                } else {
                    $this->agiCommand('EXEC Playback "' . $this->getSoundFile('invalid_input') . '"');
                }
            }
        }

        $this->logMessage("Too many invalid confirmation attempts");
        $this->setCallOutcome('operator_transfer', 'Too many invalid confirmation attempts');
        $this->redirectToOperator();
    }

    private function generateAndPlayConfirmation()
    {
        $confirm_text = str_replace(
            ['{name}', '{pickup}', '{destination}'], 
            [$this->name_result, $this->pickup_result, $this->dest_result], 
            $this->getLocalizedText('confirmation_text')
        );
        $confirm_file = "{$this->filebase}/confirm";

        $this->startMusicOnHold();
        $tts_success = $this->callTTS($confirm_text, $confirm_file);
        $this->stopMusicOnHold();

        if ($tts_success) {
            $this->agiCommand("EXEC Playback \"{$confirm_file}\"");
            return true;
        } else {
            $this->logMessage("TTS failed, proceeding without confirmation audio");
            return true;
        }
    }

    private function processConfirmedCall()
    {
        $this->trackStep('registering_call');
        $this->logMessage("User confirmed, registering call");
        $this->startMusicOnHold();
        $result = $this->registerCall();
        $this->stopMusicOnHold();

        $callback_mode = $this->config[$this->extension]['callbackMode'] ?? 1;
        
        if ($callback_mode == 2) {
            $this->handleCallbackMode();
        } else {
            $this->handleNormalMode($result);
        }

        if ($result['callOperator']) {
            $this->logMessage("Transferring to operator due to registration issue");
            $this->redirectToOperator();
        } else {
            $this->logMessage("Registration successful - ending call normally");
            $this->setCallOutcome('success');
            $this->finalizeCall();
            $this->agiCommand('EXEC Wait "1"');
            $this->agiCommand('HANGUP');
        }
    }

    private function handleCallbackMode()
    {
        $register_info_file = $this->filebase . "/register_info.json";
        $repeat_times = $this->config[$this->extension]['repeatTimes'] ?? 10;
        
        if (!file_exists($register_info_file)) {
            $this->logMessage("Playing waiting for registration sound");
            $this->agiCommand('EXEC Playback "' . $this->getSoundFile('waiting_register') . '"');
            
            // Wait and retry to check if register_info.json exists
            for ($i = 0; $i < $repeat_times; $i++) {
                $this->logMessage("Waiting for register_info.json - attempt " . ($i + 1) . "/{$repeat_times}");
                
                $this->startMusicOnHold();
                $this->agiCommand('EXEC Wait "3"');
                $this->stopMusicOnHold();
                
                if (file_exists($register_info_file)) {
                    $this->logMessage("register_info.json found, starting status monitoring");
                    break;
                }
                
                if ($i == $repeat_times - 1) {
                    $this->logMessage("register_info.json not found after {$repeat_times} attempts");
                    return;
                } else {
                    // Play waiting message again before next attempt
                    $this->logMessage("Still waiting, playing waiting sound again");
                    $this->agiCommand('EXEC Playback "' . $this->getSoundFile('waiting_register') . '"');
                }
            }
        }
        $this->monitorStatusUpdates();
    }

    private function handleNormalMode($result)
    {
        if (!empty($result['msg'])) {
            $register_file = "{$this->filebase}/register";
            $this->logMessage("Generating TTS for message: {$result['msg']}");
            $this->startMusicOnHold();
            $tts_success = $this->callTTS($result['msg'], $register_file);
            $this->stopMusicOnHold();

            if ($tts_success) {
                $this->logMessage("Playing TTS message to caller");
                $this->agiCommand("EXEC Playback \"{$register_file}\"");
            } else {
                $this->logMessage("TTS generation failed, playing fallback message");
                $sound_path = $this->config[$this->extension]['soundPath'] ?? '/var/sounds/iqtaxi';
                $this->agiCommand('EXEC Playback "' . $sound_path . '/register-call-conf_' . $this->current_language . '"');
            }
        }
    }

    // === RESERVATION FLOW ===

    /**
     * Handle the reservation flow when user selects option 2
     */
    private function handleReservationFlow()
    {
        $this->trackStep('reservation_flow');
        $this->is_reservation = true;
        $this->logMessage("Starting reservation flow");

        $this->logMessage("Getting user data from API");
        $this->startMusicOnHold();
        $user_data = $this->getUserFromAPI($this->caller_num);
        $this->stopMusicOnHold();

        if (isset($user_data['doNotServe']) && $user_data['doNotServe'] === '1') {
            $this->logMessage("User is blocked (doNotServe=1)");
            $this->setCallOutcome('user_blocked', 'User is blocked (doNotServe=1)');
            $this->redirectToOperator();
            return;
        }

        $this->processUserDataForReservation($user_data);

        if (!$this->collectDestination()) {
            $this->redirectToOperator();
            return;
        }

        if (!$this->collectReservationTime()) {
            $this->redirectToOperator();
            return;
        }

        $this->confirmReservationAndRegister();
    }

    private function processUserDataForReservation($user_data)
    {
        $has_name = !empty($user_data['name']);
        $has_pickup = !empty($user_data['pickup']) && !empty($user_data['latLng']);

        if ($has_name && $has_pickup) {
            $this->logMessage("Found existing user data: name={$user_data['name']}, pickup={$user_data['pickup']}");
            $this->name_result = $user_data['name'];

            if ($this->confirmExistingPickupAddress($user_data)) {
                $this->pickup_result = $user_data['pickup'];
                $this->pickup_location = [
                    "address" => $user_data['pickup'],
                    "latLng" => $user_data['latLng']
                ];
                $this->setUserInfo($this->name_result, true);
                $this->setPickupAddress(
                    $this->pickup_result,
                    $user_data['latLng']['lat'] ?? null,
                    $user_data['latLng']['lng'] ?? null
                );
                $this->saveJson("name", $this->name_result);
                $this->saveJson("pickup", $this->pickup_result);
                $this->saveJson("pickupLocation", $this->pickup_location);
            } else {
                $this->setUserInfo($this->name_result, false);
                $this->saveJson("name", $this->name_result);
            }
        } else {
            if (!$this->collectName()) {
                $this->redirectToOperator();
                return;
            }

            if (!$this->collectPickup()) {
                $this->redirectToOperator();
                return;
            }
        }

        if (empty($this->pickup_result)) {
            $this->logMessage("Pickup address not set, collecting now");
            if (!$this->collectPickup()) {
                $this->redirectToOperator();
                return;
            }
        }
    }

    private function confirmExistingPickupAddress($user_data)
    {
        $confirmation_text = str_replace(['{name}', '{address}'], [$user_data['name'], $user_data['pickup']], $this->getLocalizedText('greeting_with_name'));

        $confirm_file = "{$this->filebase}/pickup_confirm";
        $this->logMessage("Generating TTS for pickup address confirmation");
        $this->logMessage("TTS text: {$confirmation_text}");
        $this->logMessage("TTS output file: {$confirm_file}");
        
        $this->startMusicOnHold();
        $tts_success = $this->callTTS($confirmation_text, $confirm_file);
        $this->logMessage("TTS generation completed, success: " . ($tts_success ? 'true' : 'false'));
        $this->stopMusicOnHold();

        if ($tts_success) {
            $this->logMessage("Playing pickup address confirmation");
            
            // Check if audio file exists and is valid
            $audio_file = $confirm_file . '.wav';
            if (!file_exists($audio_file) || filesize($audio_file) < 100) {
                $this->logMessage("TTS audio file invalid or missing, assuming user wants new address");
                return false;
            }
            
            $choice = $this->readDTMF($confirm_file, 1, 10);
            $this->logMessage("User pickup address choice: {$choice}");

            if ($choice == "1") {
                $this->logMessage("User confirmed existing pickup address");
                return true;
            } else {
                $this->logMessage("User wants to enter new pickup address");
                return false;
            }
        } else {
            $this->logMessage("TTS failed, using existing pickup address as fallback");
            return true;
        }
    }

    /**
     * Confirm reservation data and register the call
     */
    private function confirmReservationAndRegister()
    {
        for ($try = 1; $try <= 3; $try++) {
            $this->logMessage("Reservation confirmation attempt {$try}/3");

            if ($this->generateAndPlayReservationConfirmation()) {
                $choice = $this->readDTMF($this->getSoundFile('options'), 1, 10);
                $this->logMessage("User choice: {$choice}");

                if ($choice == "0") {
                    $this->processConfirmedReservation();
                    return;
                } elseif ($choice == "1") {
                    if (!$this->collectName()) {
                        $this->redirectToOperator();
                        return;
                    }
                } elseif ($choice == "2") {
                    if (!$this->collectPickup()) {
                        $this->redirectToOperator();
                        return;
                    }
                } elseif ($choice == "3") {
                    if (!$this->collectDestination()) {
                        $this->redirectToOperator();
                        return;
                    }
                } else {
                    $this->agiCommand('EXEC Playback "' . $this->getSoundFile('invalid_input') . '"');
                }
            }
        }

        $this->logMessage("Too many invalid reservation confirmation attempts");
        $this->redirectToOperator();
    }

    private function generateAndPlayReservationConfirmation()
    {
        $confirm_text = str_replace(
            ['{name}', '{pickup}', '{destination}', '{time}'], 
            [$this->name_result, $this->pickup_result, $this->dest_result, $this->reservation_result], 
            $this->getLocalizedText('reservation_confirmation_text')
        );
        $confirm_file = "{$this->filebase}/confirm_reservation";

        $this->startMusicOnHold();
        $tts_success = $this->callTTS($confirm_text, $confirm_file);
        $this->stopMusicOnHold();

        if ($tts_success) {
            $this->agiCommand("EXEC Playback \"{$confirm_file}\"");
            return true;
        } else {
            $this->logMessage("TTS failed, proceeding without confirmation audio");
            return true;
        }
    }

    private function processConfirmedReservation()
    {
        $this->logMessage("User confirmed, registering reservation");
        $this->startMusicOnHold();
        $result = $this->registerCall();
        $this->stopMusicOnHold();

        if (!empty($result['msg'])) {
            $register_file = "{$this->filebase}/register";
            $this->logMessage("Generating TTS for message: {$result['msg']}");
            $this->startMusicOnHold();
            $tts_success = $this->callTTS($result['msg'], $register_file);
            $this->stopMusicOnHold();

            if ($tts_success) {
                $this->logMessage("Playing TTS message to caller");
                $this->agiCommand("EXEC Playback \"{$register_file}\"");
            } else {
                $this->logMessage("TTS generation failed, playing fallback message");
                $sound_path = $this->config[$this->extension]['soundPath'] ?? '/var/sounds/iqtaxi';
                $this->agiCommand('EXEC Playback "' . $sound_path . '/register-call-conf_' . $this->current_language . '"');
            }
        }

        if ($result['callOperator']) {
            $this->logMessage("Transferring to operator due to registration issue");
            $this->redirectToOperator();
        } else {
            $this->logMessage("Reservation registration successful - ending call normally");
            $this->setCallOutcome('success');
            $this->finalizeCall();
            $this->agiCommand('EXEC Wait "1"');
            $this->agiCommand('HANGUP');
        }
    }

    // === STATUS MONITORING (CALLBACK MODE) ===

    /**
     * Monitor status updates from register_info.json and play TTS announcements
     */
    private function monitorStatusUpdates()
    {
        $this->logMessage("Callback mode enabled - starting status monitoring");
        $register_info_file = $this->filebase . "/register_info.json";
        $repeat_times = $this->config[$this->extension]['repeatTimes'] ?? 10;
        
        $last_status = null;
        $last_car_no = null;
        $last_time = null;
        $status_file = "{$this->filebase}/status_update";
        $has_announced = false;
        
        for ($i = 0; $i < $repeat_times; $i++) {
            $this->logMessage("Status check attempt " . ($i + 1) . "/{$repeat_times}");
            
            if (file_exists($register_info_file)) {
                $register_info = json_decode(file_get_contents($register_info_file), true);
                
                if ($register_info && isset($register_info['status'])) {
                    $current_status = $register_info['status'];
                    $current_car_no = isset($register_info['carNo']) ? trim($register_info['carNo']) : '';
                    $current_time = isset($register_info['time']) ? intval($register_info['time']) : 0;
                    
                    if ($this->processStatusUpdate($current_status, $current_car_no, $current_time, $status_file, $has_announced, $last_status, $last_car_no, $last_time)) {
                        break;
                    }
                } else {
                    $this->logMessage("Invalid register_info.json format");
                }
            } else {
                $this->logMessage("register_info.json not found, waiting...");
            }
            
            if ($i < $repeat_times - 1) {
                $this->agiCommand('EXEC Wait "3"');
            }
        }
        
        $this->logMessage("Status monitoring completed");
    }

    private function processStatusUpdate($current_status, $current_car_no, $current_time, $status_file, &$has_announced, &$last_status, &$last_car_no, &$last_time)
    {
        // Handle no taxi found
        if ($current_status == 20) {
            $this->playStatusMessage('no_taxi_found', '', $status_file);
            return true;
        }
        
        // Handle driver acceptance
        if ($current_status == 10 && !empty($current_car_no) && !$has_announced) {
            $this->logMessage("Driver accepted with car: {$current_car_no}");
            $this->playStatusMessage('driver_accepted', $current_car_no, $status_file);
            $has_announced = true;
            $this->updateLastValues($last_status, $last_car_no, $last_time, $current_status, $current_car_no, $current_time);
            return false;
        }
        
        // Handle status updates after driver acceptance
        if ($has_announced && ($current_status != $last_status || $current_car_no != $last_car_no || $current_time != $last_time)) {
            $this->logMessage("Status update: {$current_status}, CarNo: {$current_car_no}, Time: {$current_time}");
            $this->playStatusMessage('status_update', $current_car_no, $status_file, $current_status, $current_time);
            $this->updateLastValues($last_status, $last_car_no, $last_time, $current_status, $current_car_no, $current_time);
            
            if (in_array($current_status, [30, 31, 32, 100])) {
                $this->logMessage("Final status reached: {$current_status}");
                return true;
            }
        } else if ($has_announced && file_exists($status_file . '.wav')) {
            $this->logMessage("No status change, replaying last message");
            $this->agiCommand("EXEC Playback \"{$status_file}\"");
        }
        
        // Handle searching status
        if (!$has_announced && $current_status == -1) {
            $this->playStatusMessage('searching', '', $status_file);
        }
        
        return false;
    }

    private function playStatusMessage($type, $car_no = '', $status_file = '', $status = null, $time = 0)
    {
        $status_message = $this->getLocalizedStatusMessage($type, $car_no, $status, $time);
        
        $this->logMessage("Generating TTS for {$type}: {$status_message}");
        $this->startMusicOnHold();
        $tts_success = $this->callTTS($status_message, $status_file);
        $this->stopMusicOnHold();
        
        if ($tts_success) {
            $this->logMessage("Playing {$type} message to caller");
            $this->agiCommand("EXEC Playback \"{$status_file}\"");
        }
    }

    private function updateLastValues(&$last_status, &$last_car_no, &$last_time, $current_status, $current_car_no, $current_time)
    {
        $last_status = $current_status;
        $last_car_no = $current_car_no;
        $last_time = $current_time;
    }

    /**
     * Get localized status message for TTS
     */
    private function getLocalizedStatusMessage($type, $car_no = '', $status = null, $time = 0)
    {
        $status_names = [
            'el' => [
                -1 => 'αναζητούμε ταξί για εσάς',
                1 => 'έρχεται προς εσάς',
                2 => 'έχει φτάσει στη διεύθυνσή σας',
                3 => 'σας παραλαμβάνει',
                8 => 'σας παραδίδει στον προορισμό',
                10 => 'δέχτηκε την κλήση σας',
                20 => 'δεν βρέθηκε διαθέσιμο ταξί',
                30 => 'η κλήση ακυρώθηκε από τον επιβάτη',
                31 => 'η κλήση ακυρώθηκε από τον οδηγό',
                32 => 'η κλήση ακυρώθηκε από το σύστημα',
                40 => 'καταγράφηκε η πληρωμή',
                50 => 'καταγράφηκε ο χρόνος του οδηγού',
                60 => 'αναμένουμε απάντηση από τον οδηγό',
                70 => 'ο οδηγός απάντησε',
                80 => 'τροποποιήθηκε η κράτηση',
                100 => 'η διαδρομή ολοκληρώθηκε',
                101 => 'νέο μήνυμα',
                255 => 'είναι καθ\' οδόν'
            ],
            'en' => [
                -1 => 'we are searching for a taxi for you',
                1 => 'is coming to you',
                2 => 'has arrived at your address',
                3 => 'is picking you up',
                8 => 'is dropping you off at your destination',
                10 => 'accepted your call',
                20 => 'no available taxi was found',
                30 => 'the call was cancelled by the passenger',
                31 => 'the call was cancelled by the driver',
                32 => 'the call was cancelled by the system',
                40 => 'payment was registered',
                50 => 'driver time was registered',
                60 => 'waiting for driver response',
                70 => 'driver responded',
                80 => 'reservation was modified',
                100 => 'the trip was completed',
                101 => 'new message',
                255 => 'is on the way'
            ],
            'bg' => [
                -1 => 'търсим такси за вас',
                1 => 'идва при вас',
                2 => 'пристигна на вашия адрес',
                3 => 'ви взема',
                8 => 'ви оставя на дестинацията',
                10 => 'прие вашето повикване',
                20 => 'не беше намерено налично такси',
                30 => 'повикването беше отменено от пътника',
                31 => 'повикването беше отменено от шофьора',
                32 => 'повикването беше отменено от системата',
                40 => 'плащането беше регистрирано',
                50 => 'времето на шофьора беше регистрирано',
                60 => 'чакаме отговор от шофьора',
                70 => 'шофьорът отговори',
                80 => 'резервацията беше променена',
                100 => 'пътуването приключи',
                101 => 'ново съобщение',
                255 => 'е в движение'
            ]
        ];
        
        $lang_status = $status_names[$this->current_language] ?? $status_names['el'];
        
        switch ($type) {
            case 'searching':
                return $lang_status[-1];
                
            case 'no_taxi_found':
                return $lang_status[20];
                
            case 'driver_accepted':
                if ($this->current_language == 'en') {
                    return "Taxi number {$car_no} " . $lang_status[10];
                } else if ($this->current_language == 'bg') {
                    return "Такси номер {$car_no} " . $lang_status[10];
                } else {
                    return "Το ταξί με αριθμό {$car_no} " . $lang_status[10];
                }
                
            case 'status_update':
                $status_text = $lang_status[$status] ?? 'άγνωστη κατάσταση';
                
                if ($this->current_language == 'en') {
                    $message = "Taxi {$car_no} {$status_text}";
                    if ($time > 0) {
                        $message .= " in {$time} minutes";
                    }
                } else if ($this->current_language == 'bg') {
                    $message = "Такси {$car_no} {$status_text}";
                    if ($time > 0) {
                        $message .= " за {$time} минути";
                    }
                } else {
                    $message = "Το ταξί {$car_no} {$status_text}";
                    if ($time > 0) {
                        $message .= " σε {$time} λεπτά";
                    }
                }
                
                return $message;
                
            default:
                return '';
        }
    }

    // === MAIN CALL FLOW ===

    /**
     * Detect if caller hung up 
     */
    private function checkHangup()
    {
        $response = $this->agiCommand('CHANNEL STATUS');
        $this->logMessage("Channel status response: {$response}");
        
        // Only consider it a hangup if we get result=6 (down/hangup)
        // result=0 can happen during normal operation, so don't treat it as hangup
        if (strpos($response, '200 result=6') !== false) {
            $this->logMessage("Hangup detected (channel down)");
            $this->setCallOutcome('hangup', 'User hung up');
            $this->finalizeCall();
            // Exit immediately when hangup is detected
            exit(0);
        }
        return false;
    }
    
    /**
     * Main call flow orchestration
     */
    public function runCallFlow()
    {
        // Set up signal handler for hangups
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGHUP, function($signo) {
                $this->logMessage("Received SIGHUP - call was hung up");
                $this->setCallOutcome('hangup', 'Call was hung up');
                $this->finalizeCall();
                exit(0);
            });
        }
        
        try {
            $this->logMessage("Starting call processing for {$this->caller_num}");

            if ($this->isAnonymousCaller()) {
                $this->setCallOutcome('anonymous_blocked', 'Anonymous caller blocked');
                $this->handleAnonymousCaller();
                return;
            }

            $this->saveJson("phone", $this->caller_num);

            $user_choice = $this->getInitialUserChoice();
            $this->setInitialChoice($user_choice);
            if ($user_choice === '') {
                $this->logMessage("No selection received (likely hangup), ending call");
                $this->setCallOutcome('hangup', 'No DTMF selection');
                $this->finalizeCall();
                $this->agiCommand('HANGUP');
                return;
            }

            if ($user_choice == "3") {
                $this->logMessage("User selected operator");
                $this->setCallType('operator');
                $this->setCallOutcome('operator_transfer', 'User selected operator');
                $this->redirectToOperator();
                return;
            }

            if ($user_choice == "2") {
                $this->logMessage("Reservation selected");
                $this->setCallType('reservation');
                $this->handleReservationFlow();
                return;
            }

            if ($user_choice != "1") {
                $this->logMessage("Invalid or no selection, redirecting to operator");
                $this->setCallOutcome('operator_transfer', 'Invalid or no selection');
                $this->redirectToOperator();
                return;
            }

            $this->logMessage("Processing choice '1' - setting call type to immediate");
            $this->setCallType('immediate');
            $this->logMessage("About to call handleImmediateCall()");
            $this->handleImmediateCall();
            $this->logMessage("handleImmediateCall() completed");
            
        } catch (Exception $e) {
            $this->logMessage("Error in call flow: " . $e->getMessage());
            $this->setCallOutcome('error', $e->getMessage());
            $this->addErrorMessage($e->getMessage());
            $this->redirectToOperator();
        }
    }

    private function handleAnonymousCaller()
    {
        $this->logMessage("Anonymous caller detected");
        $this->setCallOutcome('anonymous_blocked', 'Anonymous caller blocked');
        $this->agiCommand('EXEC Playback "' . $this->getSoundFile('anonymous') . '"');
        $this->redirectToOperator();
    }

    private function getInitialUserChoice()
    {
        $this->logMessage("Playing welcome message");
        $user_choice = $this->readDTMF($this->getSoundFile('welcome'), 1, 5);
        $this->logMessage("User choice: {$user_choice}");

        if ($user_choice == "9") {
            $this->logMessage("User selected language change to English");
            $this->current_language = 'en';
            $this->setLanguage('en', true);
            $this->saveJson("language", $this->current_language);
            
            $user_choice = $this->readDTMF($this->getSoundFile('welcome'), 1, 5);
            $this->logMessage("User choice after language change: {$user_choice}");
        } else {
            $this->setLanguage($this->current_language, false);
        }

        $this->setInitialChoice($user_choice);
        return $user_choice;
    }

    private function handleImmediateCall()
    {
        $this->trackStep('immediate_call_flow');
        $this->logMessage("ASAP call selected");

        $this->logMessage("Getting user data from API");
        $this->startMusicOnHold();
        $user_data = $this->getUserFromAPI($this->caller_num);
        $this->stopMusicOnHold();

        if (isset($user_data['doNotServe']) && $user_data['doNotServe'] === '1') {
            $this->logMessage("User is blocked (doNotServe=1)");
            $this->setCallOutcome('user_blocked', 'User is blocked (doNotServe=1)');
            $this->redirectToOperator();
            return;
        }

        $this->processUserDataForImmediateCall($user_data);
        $this->confirmAndRegister();
    }

    private function processUserDataForImmediateCall($user_data)
    {
        $has_name = !empty($user_data['name']);
        $has_pickup = !empty($user_data['pickup']) && !empty($user_data['latLng']);

        if ($has_name && $has_pickup) {
            $this->processExistingUserData($user_data);
        } else {
            $this->collectNewUserData();
        }

        if (empty($this->pickup_result)) {
            $this->logMessage("Pickup address not set, collecting now");
            if (!$this->collectPickup()) {
                $this->redirectToOperator();
                return;
            }
        }

        if (!$this->collectDestination()) {
            $this->redirectToOperator();
            return;
        }
    }

    private function processExistingUserData($user_data)
    {
        $this->logMessage("Found existing user data: name={$user_data['name']}, pickup={$user_data['pickup']}");
        $this->name_result = $user_data['name'];

        if ($this->confirmExistingPickupAddress($user_data)) {
            $this->pickup_result = $user_data['pickup'];
            $this->pickup_location = [
                "address" => $user_data['pickup'],
                "latLng" => $user_data['latLng']
            ];
            // Update analytics with confirmed default address
            $this->setUserInfo($this->name_result, true);
            $this->setPickupAddress(
                $this->pickup_result,
                $user_data['latLng']['lat'] ?? null,
                $user_data['latLng']['lng'] ?? null
            );
            $this->saveJson("name", $this->name_result);
            $this->saveJson("pickup", $this->pickup_result);
            $this->saveJson("pickupLocation", $this->pickup_location);
        } else {
            // User rejected default address
            $this->setUserInfo($this->name_result, false);
            $this->saveJson("name", $this->name_result);
        }
    }

    private function collectNewUserData()
    {
        if (!$this->collectName()) {
            $this->redirectToOperator();
            return;
        }

        if (!$this->collectPickup()) {
            $this->redirectToOperator();
            return;
        }
    }
}

// === MAIN EXECUTION ===

// Initialize and run the call handler
try {
    error_log("AGI: Starting main execution");
    $call_handler = new AGICallHandler();
    error_log("AGI: Call handler created, starting runCallFlow");
    $call_handler->runCallFlow();
    error_log("AGI: runCallFlow completed successfully, exiting with code 0");
    exit(0); // Exit successfully after call flow completes
} catch (Exception $e) {
    error_log("Fatal error in AGI Call Handler: " . $e->getMessage());
    error_log("AGI: Stack trace: " . $e->getTraceAsString());
    echo "HANGUP\n";
    exit(1); // Exit with error code on exception
} catch (Error $e) {
    error_log("Fatal PHP error in AGI Call Handler: " . $e->getMessage());
    error_log("AGI: Stack trace: " . $e->getTraceAsString());
    echo "HANGUP\n";
    exit(1); // Exit with error code on PHP error
}
?>