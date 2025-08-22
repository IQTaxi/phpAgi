#!/usr/bin/php
<?php
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

class AGICallHandler {
    private $agi_env = [];
    private $uniqueid = '';
    private $extension = '';
    private $caller_id = '';
    private $caller_num = '';
    private $config = [];
    private $current_exten = '';
    private $filebase = '';
    private $log_prefix = '';
    private $phone_to_call = '';
    private $welcome_playback = '';
    private $api_key = '';
    private $client_token = '';
    private $register_base_url = '';
    private $max_retries = 3;
    private $name_result = '';
    private $pickup_result = '';
    private $pickup_location = [];
    private $dest_result = '';
    private $dest_location = [];
    
    public function __construct() {
        $this->setupAGIEnvironment();
        $this->setupFilePaths();
        $this->loadConfiguration();
        $this->checkExtensionExists();
    }
    
    /**
     * Initialize AGI environment by reading stdin
     */
    private function setupAGIEnvironment() {
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') break;
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $this->agi_env[trim($key)] = trim($value);
            }
        }
        
        $this->uniqueid = isset($this->agi_env['agi_uniqueid']) ? $this->agi_env['agi_uniqueid'] : '';
        $this->extension = isset($this->agi_env['agi_extension']) ? $this->agi_env['agi_extension'] : '';
        $this->caller_id = isset($this->agi_env['agi_callerid']) ? str_replace(['<', '>'], '', $this->agi_env['agi_callerid']) : '';
        $this->caller_num = isset($this->agi_env['agi_callerid']) ? $this->agi_env['agi_callerid'] : '';
        $this->current_exten = $this->extension;
    }
    
    /**
     * Create directory structure for call recordings and logs
     */
    private function setupFilePaths() {
        $this->filebase = "/tmp/auto_register_call/{$this->current_exten}/{$this->caller_num}/{$this->uniqueid}";
        $this->log_prefix = "[{$this->uniqueid}]";
        
        $recordings_dir = $this->filebase . "/recordings";
        if (!is_dir($recordings_dir)) {
            mkdir($recordings_dir, 0755, true);
        }
    }
    
    /**
     * Load extension configuration with API keys and settings
     */
    private function loadConfiguration() {
        $this->config = [
            "1234" => [
                "name" => "Test Extension",
                "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
                "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
                "registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
                "failCallTo" => "SIP/6979753028@vodafone_sip",
                "welcomePlayback" => "custom/welcome-v2"
            ],
            "4039" => [
                "name" => "iqtaxi.com",
                "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
                "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
                "registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
                "failCallTo" => "SIP/6974888710@vodafone_sip",
                "welcomePlayback" => "custom/welcome-v3"
            ],
            "4033" => [
                "name" => "Hermis-Peireas",
                "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
                "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
                "registerBaseUrl" => "http://79.129.41.206:8080/IQTaxiAPIV3",
                "failCallTo" => "SIP/2104115200@vodafone_sip",
                "welcomePlayback" => "custom/welcome-v3"
            ],
            "4036" => [
                "name" => "Cosmos",
                "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
                "clientToken" => "a0a5d57bc105156016549b9a5de4165a",
                "registerBaseUrl" => "http://18300.fortiddns.com:8000/IQTaxiApi",
                "failCallTo" => "SIP/2104118300@vodafone_sip",
                "welcomePlayback" => "custom/welcome-kosmos-2"
            ]
        ];
        
        if (isset($this->config[$this->extension])) {
            $config = $this->config[$this->extension];
            $this->phone_to_call = $config['failCallTo'];
            $this->welcome_playback = $config['welcomePlayback'];
            $this->api_key = $config['googleApiKey'];
            $this->client_token = $config['clientToken'];
            $this->register_base_url = $config['registerBaseUrl'];
        }
    }
    
    private function checkExtensionExists() {
        if (!isset($this->config[$this->extension])) {
            $this->logMessage("Extension {$this->extension} not found in config");
            $this->redirectToOperator();
            exit;
        }
    }
    
    /**
     * Log messages to file with timestamp
     */
    private function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "$timestamp - {$this->log_prefix} $message\n";
        error_log($log_entry, 3, "/tmp/asterisk_calls.log");
        if (!empty($this->filebase)) {
            error_log($log_entry, 3, "{$this->filebase}/log.txt");
        }
    }
    
    /**
     * Send AGI command and return response
     */
    private function agiCommand($command) {
        echo $command . "\n";
        return trim(fgets(STDIN));
    }
    
    /**
     * Save data to JSON progress file
     */
    private function saveJson($key, $value) {
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
    private function isAnonymousCaller() {
        return empty($this->caller_num) || 
               $this->caller_num === 'anonymous' || 
               $this->caller_num === '' || 
               $this->caller_num === 'unknown' ||
               strlen($this->caller_num) <= 5;
    }
    
    /**
     * Transfer call to human operator
     */
    private function redirectToOperator() {
        $this->logMessage("Redirecting to operator: {$this->phone_to_call}");
        $this->agiCommand("EXEC Dial \"{$this->phone_to_call},20\"");
        $this->agiCommand('HANGUP');
    }
    
    /**
     * Retrieve existing user data from IQTaxi API
     */
    private function getUserFromAPI($phone) {
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
        
        $response_data = $data['response'];
        $output = [];
        
        if (!empty($response_data['callerName'])) {
            $output['name'] = $response_data['callerName'];
        }
        
        if (!empty($response_data['doNotServe'])) {
            $output['doNotServe'] = $response_data['doNotServe'] ? '1' : '0';
        }
        
        $main_address = isset($response_data['mainAddresss']) ? $response_data['mainAddresss'] : null;
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
    
    /**
     * Convert speech to text using Google Cloud STT API
     */
    private function callGoogleSTT($wav_file) {
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
        
        $url = "https://speech.googleapis.com/v1/speech:recognize?key={$this->api_key}";
        $headers = ["Content-Type: application/json"];
        $body = [
            "config" => [
                "encoding" => "LINEAR16",
                "sampleRateHertz" => 8000,
                "languageCode" => "el-GR",
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
        
        return trim($transcript);
    }
    
    /**
     * Convert text to speech using Google Cloud TTS API
     */
    private function callGoogleTTS($text, $output_file) {
        $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->api_key}";
        $headers = ["Content-Type: application/json"];
        $data = [
            "input" => ["text" => $text],
            "voice" => ["languageCode" => "el-GR"],
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
        
        if ($http_code !== 200 || !$response) return false;
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['audioContent'])) return false;
        
        $audio_data = base64_decode($result['audioContent']);
        
        $mp3_file = $output_file . '.mp3';
        file_put_contents($mp3_file, $audio_data);
        
        $wav_file = $output_file . '.wav';
        $cmd = "ffmpeg -y -i \"$mp3_file\" -ac 1 -ar 8000 \"$wav_file\" 2>/dev/null";
        exec($cmd);
        
        unlink($mp3_file);
        
        return file_exists($wav_file) && filesize($wav_file) > 100;
    }
    
    /**
     * Geocode address using Google Maps API
     */
    private function getLatLngFromGoogle($address, $is_pickup = true) {
        $normalized_address = $this->removeDiacritics(strtolower(trim($address)));
        $center_addresses = ["κεντρο", "τοπικο", "κεντρο αθηνα", "κεντρο θεσσαλονικη"];
        
        if (!$is_pickup && in_array($normalized_address, $center_addresses)) {
            return [
                "address" => $address,
                "location_type" => "EXACT",
                "latLng" => ["lat" => 0, "lng" => 0]
            ];
        }
        
        if ($this->config[$this->extension]['name'] === 'Cosmos') {
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
        
        if ($http_code !== 200 || !$response) return null;
        
        $data = json_decode($response, true);
        if (!$data || $data['status'] !== 'OK' || empty($data['results'])) return null;
        
        $result = $data['results'][0];
        return [
            "address" => $result['formatted_address'],
            "location_type" => $result['geometry']['location_type'],
            "latLng" => [
                "lat" => $result['geometry']['location']['lat'],
                "lng" => $result['geometry']['location']['lng']
            ]
        ];
    }
    
    private function removeDiacritics($text) {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }
    
    /**
     * Register taxi call via IQTaxi API
     */
    private function registerCall() {
        $url = rtrim($this->register_base_url, '/') . "/api/Calls/RegisterNoLogin";
        $headers = [
            "Authorization: {$this->client_token}",
            "Content-Type: application/json; charset=UTF-8"
        ];
        
        $payload = [
            "callTimeStamp" => null,
            "callerPhone" => $this->caller_num,
            "customerName" => $this->name_result,
            "roadName" => $this->pickup_result,
            "latitude" => $this->pickup_location['latLng']['lat'],
            "longitude" => $this->pickup_location['latLng']['lng'],
            "destination" => $this->dest_result,
            "destLatitude" => isset($this->dest_location['latLng']['lat']) ? $this->dest_location['latLng']['lat'] : 0,
            "destLongitude" => isset($this->dest_location['latLng']['lng']) ? $this->dest_location['latLng']['lng'] : 0,
            "taxisNo" => 1,
            "comments" => "[ΑΥΤΟΜΑΤΟΠΟΙΗΜΕΝΗ ΚΛΗΣΗ]"
        ];
        
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
        
        $this->logMessage("Registration API HTTP code: {$http_code}");
        if ($curl_error) {
            $this->logMessage("Registration API cURL error: {$curl_error}");
        }
        
        if ($http_code !== 200 || !$response) {
            $this->logMessage("Registration API failed - HTTP: {$http_code}");
            return ["callOperator" => true, "msg" => "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας"];
        }
        
        $decoded_response = json_decode($response, true);
        $readable_response = $decoded_response ? json_encode($decoded_response, JSON_UNESCAPED_UNICODE) : $response;
        $this->logMessage("Registration API response: " . substr($readable_response, 0, 500));
        
        $data = json_decode($response, true);
        if (!$data) {
            $this->logMessage("Registration API - Invalid JSON response");
            return ["callOperator" => true, "msg" => "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας"];
        }
        
        $result_data = isset($data['result']) ? $data['result'] : [];
        $result_code = isset($result_data['resultCode']) ? $result_data['resultCode'] : -1;
        $msg = trim(isset($result_data['msg']) ? $result_data['msg'] : '');
        
        $this->logMessage("Registration API result - Code: {$result_code}, Message: {$msg}");
        
        if (empty($msg)) {
            $msg = "Κάτι πήγε στραβά με την καταχώρηση της διαδρομής σας";
        }
        
        $call_operator = ($result_code !== 0);
        $this->logMessage("Registration result - callOperator: " . ($call_operator ? 'true' : 'false'));
        
        return ["callOperator" => $call_operator, "msg" => $msg];
    }
    
    /**
     * Play audio file and wait for DTMF input
     */
    private function readDTMF($prompt_file, $digits = 1, $timeout = 10) {
        $response = $this->agiCommand("EXEC Read \"USER_CHOICE,{$prompt_file},{$digits},,1,{$timeout}\"");
        $choice_response = $this->agiCommand("GET VARIABLE USER_CHOICE");
        
        if (preg_match('/200 result=1 \((.+)\)/', $choice_response, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Main call flow orchestration
     */
    public function runCallFlow() {
        try {
            $this->logMessage("Starting call processing for {$this->caller_num}");
            
            if ($this->isAnonymousCaller()) {
                $this->logMessage("Anonymous caller detected");
                $this->agiCommand('EXEC Playback "custom/anonymous-v2"');
                $this->redirectToOperator();
                return;
            }
            
            $this->saveJson("phone", $this->caller_num);
            
            $this->logMessage("Playing welcome message");
            $user_choice = $this->readDTMF($this->welcome_playback, 1, 3);
            $this->logMessage("User choice: {$user_choice}");
            
            if ($user_choice == "3") {
                $this->logMessage("User selected operator");
                $this->redirectToOperator();
                return;
            }
            
            if ($user_choice == "2") {
                $this->logMessage("Reservation selected - not implemented, redirecting to operator");
                $this->redirectToOperator();
                return;
            }
            
            if ($user_choice != "1") {
                $this->logMessage("Invalid or no selection, redirecting to operator");
                $this->redirectToOperator();
                return;
            }
            
            $this->logMessage("ASAP call selected");
            
            $this->logMessage("Getting user data from API");
            $user_data = $this->getUserFromAPI($this->caller_num);
            
            if (isset($user_data['doNotServe']) && $user_data['doNotServe'] === '1') {
                $this->logMessage("User is blocked (doNotServe=1)");
                $this->redirectToOperator();
                return;
            }
            
            $has_name = !empty($user_data['name']);
            $has_pickup = !empty($user_data['pickup']) && !empty($user_data['latLng']);
            
            if ($has_name && $has_pickup) {
                $this->logMessage("Found existing user data: name={$user_data['name']}, pickup={$user_data['pickup']}");
                $this->name_result = $user_data['name'];
                
                $confirmation_text = "Γεια σας {$user_data['name']}. Θέλετε να χρησιμοποιήσετε τη διεύθυνση παραλαβής {$user_data['pickup']}? Πατήστε 1 για ναι ή 2 για να εισάγετε νέα διεύθυνση παραλαβής.";
                
                $confirm_file = "{$this->filebase}/pickup_confirm";
                $this->logMessage("Generating TTS for pickup address confirmation");
                $this->agiCommand('EXEC StartMusicOnHold');
                $tts_success = $this->callGoogleTTS($confirmation_text, $confirm_file);
                $this->agiCommand('EXEC StopMusicOnHold');
                
                if ($tts_success) {
                    $this->logMessage("Playing pickup address confirmation");
                    $choice = $this->readDTMF($confirm_file, 1, 10);
                    $this->logMessage("User pickup address choice: {$choice}");
                    
                    if ($choice == "1") {
                        $this->logMessage("User confirmed existing pickup address");
                        $this->pickup_result = $user_data['pickup'];
                        $this->pickup_location = [
                            "address" => $user_data['pickup'],
                            "latLng" => $user_data['latLng']
                        ];
                        $this->saveJson("name", $this->name_result);
                        $this->saveJson("pickup", $this->pickup_result);
                        $this->saveJson("pickupLocation", $this->pickup_location);
                    } else {
                        $this->logMessage("User wants to enter new pickup address");
                        $this->saveJson("name", $this->name_result);
                    }
                } else {
                    $this->logMessage("TTS failed, using existing pickup address as fallback");
                    $this->pickup_result = $user_data['pickup'];
                    $this->pickup_location = [
                        "address" => $user_data['pickup'],
                        "latLng" => $user_data['latLng']
                    ];
                    $this->saveJson("name", $this->name_result);
                    $this->saveJson("pickup", $this->pickup_result);
                    $this->saveJson("pickupLocation", $this->pickup_location);
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
            
            if (!$this->collectDestination()) {
                $this->redirectToOperator();
                return;
            }
            
            $this->confirmAndRegister();
            
        } catch (Exception $e) {
            $this->logMessage("Error in call flow: " . $e->getMessage());
            $this->redirectToOperator();
        }
    }
    
    /**
     * Collect customer name via speech recognition
     */
    private function collectName() {
        $this->logMessage("Starting name collection");
        
        for ($try = 1; $try <= $this->max_retries; $try++) {
            $this->logMessage("Name attempt {$try}/{$this->max_retries}");
            $this->agiCommand('EXEC Playback "custom/give-name-v2"');
            $this->agiCommand('EXEC Wait "1"');
            
            $recording_file = "{$this->filebase}/recordings/name_{$try}";
            $this->logMessage("Starting recording to: {$recording_file}");
            $record_result = $this->agiCommand("RECORD FILE \"{$recording_file}\" wav \"#\" 10000 0 BEEP");
            $this->logMessage("Record result: {$record_result}");
            
            $this->agiCommand('EXEC StartMusicOnHold');
            $name = $this->callGoogleSTT($recording_file . ".wav");
            $this->agiCommand('EXEC StopMusicOnHold');
            
            $this->logMessage("STT result for name: {$name}");
            
            if (!empty($name) && strlen(trim($name)) > 2) {
                $this->name_result = trim($name);
                $this->saveJson("name", $this->name_result);
                $this->logMessage("Name successfully captured: {$this->name_result}");
                return true;
            }
            
            if ($try < $this->max_retries) {
                $this->agiCommand('EXEC Playback "custom/invalid-v2"');
            }
        }
        
        $this->logMessage("Failed to capture name after {$this->max_retries} attempts");
        return false;
    }
    
    /**
     * Collect pickup address via speech recognition and geocoding
     */
    private function collectPickup() {
        $this->logMessage("Starting pickup collection");
        
        for ($try = 1; $try <= $this->max_retries; $try++) {
            $this->logMessage("Pickup attempt {$try}/{$this->max_retries}");
            $this->agiCommand('EXEC Playback "custom/give-pickup-address-v2"');
            $this->agiCommand('EXEC Wait "1"');
            
            $recording_file = "{$this->filebase}/recordings/pickup_{$try}";
            $this->logMessage("Starting recording to: {$recording_file}");
            $record_result = $this->agiCommand("RECORD FILE \"{$recording_file}\" wav \"#\" 10000 0 BEEP");
            $this->logMessage("Record result: {$record_result}");
            
            $this->agiCommand('EXEC StartMusicOnHold');
            $pickup = $this->callGoogleSTT($recording_file . ".wav");
            $this->logMessage("STT result for pickup: {$pickup}");
            
            if (!empty($pickup) && strlen(trim($pickup)) > 2) {
                $location = $this->getLatLngFromGoogle($pickup, true);
                $this->agiCommand('EXEC StopMusicOnHold');
                
                if ($location) {
                    $this->pickup_result = trim($pickup);
                    $this->pickup_location = $location;
                    $this->saveJson("pickup", $this->pickup_result);
                    $this->saveJson("pickupLocation", $this->pickup_location);
                    $this->logMessage("Pickup successfully captured: {$this->pickup_result}");
                    return true;
                }
            } else {
                $this->agiCommand('EXEC StopMusicOnHold');
            }
            
            if ($try < $this->max_retries) {
                $this->agiCommand('EXEC Playback "custom/invalid-v2"');
            }
        }
        
        $this->logMessage("Failed to capture pickup after {$this->max_retries} attempts");
        return false;
    }
    
    /**
     * Collect destination address via speech recognition and geocoding
     */
    private function collectDestination() {
        $this->logMessage("Starting destination collection");
        
        for ($try = 1; $try <= $this->max_retries; $try++) {
            $this->logMessage("Destination attempt {$try}/{$this->max_retries}");
            $this->agiCommand('EXEC Playback "custom/give-dest-address-v2"');
            $this->agiCommand('EXEC Wait "1"');
            
            $recording_file = "{$this->filebase}/recordings/dest_{$try}";
            $this->logMessage("Starting recording to: {$recording_file}");
            $record_result = $this->agiCommand("RECORD FILE \"{$recording_file}\" wav \"#\" 10000 0 BEEP");
            $this->logMessage("Record result: {$record_result}");
            
            $this->agiCommand('EXEC StartMusicOnHold');
            $dest = $this->callGoogleSTT($recording_file . ".wav");
            $this->logMessage("STT result for destination: {$dest}");
            
            if (!empty($dest) && strlen(trim($dest)) > 2) {
                $location = $this->getLatLngFromGoogle($dest, false);
                $this->agiCommand('EXEC StopMusicOnHold');
                
                if ($location) {
                    $this->dest_result = trim($dest);
                    $this->dest_location = $location;
                    $this->saveJson("destination", $this->dest_result);
                    $this->saveJson("destinationLocation", $this->dest_location);
                    $this->logMessage("Destination successfully captured: {$this->dest_result}");
                    return true;
                }
            } else {
                $this->agiCommand('EXEC StopMusicOnHold');
            }
            
            if ($try < $this->max_retries) {
                $this->agiCommand('EXEC Playback "custom/invalid-v2"');
            }
        }
        
        $this->logMessage("Failed to capture destination after {$this->max_retries} attempts");
        return false;
    }
    
    /**
     * Confirm collected data and register the call
     */
    private function confirmAndRegister() {
        for ($try = 1; $try <= 3; $try++) {
            $this->logMessage("Confirmation attempt {$try}/3");
            
            $confirm_text = "Παρακαλώ επιβεβαιώστε. Όνομα: {$this->name_result}. Παραλαβή: {$this->pickup_result}. Προορισμός: {$this->dest_result}";
            $confirm_file = "{$this->filebase}/confirm";
            
            $this->agiCommand('EXEC StartMusicOnHold');
            $tts_success = $this->callGoogleTTS($confirm_text, $confirm_file);
            $this->agiCommand('EXEC StopMusicOnHold');
            
            if ($tts_success) {
                $this->agiCommand("EXEC Playback \"{$confirm_file}\"");
            } else {
                $this->logMessage("TTS failed, proceeding without confirmation audio");
            }
            
            $this->logMessage("Waiting for user confirmation");
            $choice = $this->readDTMF("custom/options-v2", 1, 10);
            $this->logMessage("User choice: {$choice}");
            
            if ($choice == "0") {
                $this->logMessage("User confirmed, registering call");
                $result = $this->registerCall();
                
                if (!empty($result['msg'])) {
                    $register_file = "{$this->filebase}/register";
                    $this->logMessage("Generating TTS for message: {$result['msg']}");
                    $this->agiCommand('EXEC StartMusicOnHold');
                    $tts_success = $this->callGoogleTTS($result['msg'], $register_file);
                    $this->agiCommand('EXEC StopMusicOnHold');
                    
                    if ($tts_success) {
                        $this->logMessage("Playing TTS message to caller");
                        $this->agiCommand("EXEC Playback \"{$register_file}\"");
                    } else {
                        $this->logMessage("TTS generation failed, playing fallback message");
                        $this->agiCommand('EXEC Playback "custom/register-call-conf"');
                    }
                }
                
                if ($result['callOperator']) {
                    $this->logMessage("Transferring to operator due to registration issue");
                    $this->redirectToOperator();
                } else {
                    $this->logMessage("Registration successful - ending call normally");
                    $this->agiCommand('EXEC Wait "2"');
                    $this->agiCommand('HANGUP');
                }
                return;
                
            } elseif ($choice == "1") {
                if ($this->collectName()) {
                    continue;
                } else {
                    $this->redirectToOperator();
                    return;
                }
                
            } elseif ($choice == "2") {
                if ($this->collectPickup()) {
                    continue;
                } else {
                    $this->redirectToOperator();
                    return;
                }
                
            } elseif ($choice == "3") {
                if ($this->collectDestination()) {
                    continue;
                } else {
                    $this->redirectToOperator();
                    return;
                }
                
            } else {
                $this->agiCommand('EXEC Playback "custom/invalid-v2"');
            }
        }
        
        $this->logMessage("Too many invalid confirmation attempts");
        $this->redirectToOperator();
    }
}

// Initialize and run the call handler
try {
    $call_handler = new AGICallHandler();
    $call_handler->runCallFlow();
} catch (Exception $e) {
    error_log("Fatal error: " . $e->getMessage());
    echo "HANGUP\n";
}
?>