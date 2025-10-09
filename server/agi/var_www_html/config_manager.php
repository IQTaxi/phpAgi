<?php
// Configuration Manager for AGI Call Handler
// Manages /var/lib/asterisk/agi-bin/iqtaxi/config.php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

class ConfigManager {
    private $configPath;
    private $config;
    
    public function __construct() {
        $this->configPath = '/var/lib/asterisk/agi-bin/iqtaxi/config.php';
        $this->loadConfig();
    }
    
    private function loadConfig() {
        if (file_exists($this->configPath)) {
            include $this->configPath;
            if (class_exists('AGICallHandlerConfig')) {
                $this->config = (new AGICallHandlerConfig())->globalConfiguration;
            } else {
                $this->config = [];
            }
        } else {
            $this->config = [];
        }
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function getExtension($extension) {
        return $this->config[$extension] ?? null;
    }
    
    private function createConfigFile() {
        $configContent = "<?php\n";
        $configContent .= "// callbackMode configuration:\n";
        $configContent .= "// 1 = Normal mode: reads API response message and plays TTS, then closes call\n";
        $configContent .= "// 2 = Callback mode: sends callBackURL to server, waits for register_info.json, \n";
        $configContent .= "//     reads status and carNo from file, announces via TTS\n\n";
        $configContent .= "// strictDropoffLocation configuration:\n";
        $configContent .= "// false = Accept all Google Maps location types for dropoff (ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE)\n";
        $configContent .= "// true = Only accept precise locations for dropoff (ROOFTOP, RANGE_INTERPOLATED only)\n";
        $configContent .= "// Note: Pickup locations ALWAYS require precise location types (ROOFTOP, RANGE_INTERPOLATED)\n\n";
        $configContent .= "// geocodingApiVersion configuration:\n";
        $configContent .= "// 1 = Use Google Maps Geocoding API (current/legacy version)\n";
        $configContent .= "// 2 = Use Google Places API v1 (new version with searchText endpoint)\n\n";
        $configContent .= "// bounds configuration:\n";
        $configContent .= "// null = Search in all areas (default behavior)\n";
        $configContent .= "// Object with north, south, east, west coordinates = Post-processing validation bounds\n";
        $configContent .= "// Example: {\"north\": 38.1, \"south\": 37.8, \"east\": 24.0, \"west\": 23.5}\n";
        $configContent .= "// Used for post-processing validation to reject results outside bounds\n\n";
        $configContent .= "// centerBias configuration:\n";
        $configContent .= "// null = No center bias (default behavior)\n";
        $configContent .= "// Object with lat, lng, radius = Bias API results toward a center point\n";
        $configContent .= "// Example: {\"lat\": 37.9755, \"lng\": 23.7348, \"radius\": 50000} (radius in meters)\n";
        $configContent .= "// Used by both Google Geocoding API v1 and Places API v2 to bias location results\n\n";
        $configContent .= "// boundsRestrictionMode configuration:\n";
        $configContent .= "// null or 0 = No restriction (bounds and centerBias are not applied)\n";
        $configContent .= "// 1 = Apply bounds and centerBias only to pickup location\n";
        $configContent .= "// 2 = Apply bounds and centerBias only to dropoff location\n";
        $configContent .= "// 3 = Apply bounds and centerBias to both pickup and dropoff locations\n\n";
        $configContent .= "// confirmation_mode configuration:\n";
        $configContent .= "// 1 = Full TTS confirmation: reads name, pickup, and dropoff addresses via TTS before asking for confirmation\n";
        $configContent .= "// 2 = Quick confirmation: only plays confirmation prompt without reading details (just 'press 0 to confirm')\n\n";
        $configContent .= "// getUser_enabled configuration:\n";
        $configContent .= "// true = Check server for existing user data (name and pickup address) to skip collection\n";
        $configContent .= "// false = Always ask user for their information (name and addresses)\n\n";
        $configContent .= "// askForName configuration:\n";
        $configContent .= "// true = Ask customer for their name during call (default behavior)\n";
        $configContent .= "// false = Skip name collection, don't include name in registration API call\n\n";
        $configContent .= "// foreignRedirect configuration:\n";
        $configContent .= "// true = Check if incoming number is from foreign country (not in allowed prefixes list) and redirect to operator\n";
        $configContent .= "// false = Accept all international numbers and process normally (default behavior)\n";
        $configContent .= "// When enabled, numbers > 10 digits that don't start with allowed prefixes (+30, +359, 0030) are redirected\n\n";
        $configContent .= "class AGICallHandlerConfig\n{\n";
        $configContent .= " public \$globalConfiguration = [\n";
        $configContent .= "];\n}\n";
        
        // Create directory if it doesn't exist
        $configDir = dirname($this->configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        return file_put_contents($this->configPath, $configContent);
    }
    
    public function saveConfig($newConfig) {
        if (!file_exists($this->configPath)) {
            $this->createConfigFile();
        }
        
        $configContent = "<?php\n";
        $configContent .= "// callbackMode configuration:\n";
        $configContent .= "// 1 = Normal mode: reads API response message and plays TTS, then closes call\n";
        $configContent .= "// 2 = Callback mode: sends callBackURL to server, waits for register_info.json, \n";
        $configContent .= "//     reads status and carNo from file, announces via TTS\n\n";
        $configContent .= "// strictDropoffLocation configuration:\n";
        $configContent .= "// false = Accept all Google Maps location types for dropoff (ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE)\n";
        $configContent .= "// true = Only accept precise locations for dropoff (ROOFTOP, RANGE_INTERPOLATED only)\n";
        $configContent .= "// Note: Pickup locations ALWAYS require precise location types (ROOFTOP, RANGE_INTERPOLATED)\n\n";
        $configContent .= "// geocodingApiVersion configuration:\n";
        $configContent .= "// 1 = Use Google Maps Geocoding API (current/legacy version)\n";
        $configContent .= "// 2 = Use Google Places API v1 (new version with searchText endpoint)\n\n";
        $configContent .= "// bounds configuration:\n";
        $configContent .= "// null = Search in all areas (default behavior)\n";
        $configContent .= "// Object with north, south, east, west coordinates = Post-processing validation bounds\n";
        $configContent .= "// Example: {\"north\": 38.1, \"south\": 37.8, \"east\": 24.0, \"west\": 23.5}\n";
        $configContent .= "// Used for post-processing validation to reject results outside bounds\n\n";
        $configContent .= "// centerBias configuration:\n";
        $configContent .= "// null = No center bias (default behavior)\n";
        $configContent .= "// Object with lat, lng, radius = Bias API results toward a center point\n";
        $configContent .= "// Example: {\"lat\": 37.9755, \"lng\": 23.7348, \"radius\": 50000} (radius in meters)\n";
        $configContent .= "// Used by both Google Geocoding API v1 and Places API v2 to bias location results\n\n";
        $configContent .= "// boundsRestrictionMode configuration:\n";
        $configContent .= "// null or 0 = No restriction (bounds and centerBias are not applied)\n";
        $configContent .= "// 1 = Apply bounds and centerBias only to pickup location\n";
        $configContent .= "// 2 = Apply bounds and centerBias only to dropoff location\n";
        $configContent .= "// 3 = Apply bounds and centerBias to both pickup and dropoff locations\n\n";
        $configContent .= "// confirmation_mode configuration:\n";
        $configContent .= "// 1 = Full TTS confirmation: reads name, pickup, and dropoff addresses via TTS before asking for confirmation\n";
        $configContent .= "// 2 = Quick confirmation: only plays confirmation prompt without reading details (just 'press 0 to confirm')\n\n";
        $configContent .= "// getUser_enabled configuration:\n";
        $configContent .= "// true = Check server for existing user data (name and pickup address) to skip collection\n";
        $configContent .= "// false = Always ask user for their information (name and addresses)\n\n";
        $configContent .= "// askForName configuration:\n";
        $configContent .= "// true = Ask customer for their name during call (default behavior)\n";
        $configContent .= "// false = Skip name collection, don't include name in registration API call\n\n";
        $configContent .= "// foreignRedirect configuration:\n";
        $configContent .= "// true = Check if incoming number is from foreign country (not in allowed prefixes list) and redirect to operator\n";
        $configContent .= "// false = Accept all international numbers and process normally (default behavior)\n";
        $configContent .= "// When enabled, numbers > 10 digits that don't start with allowed prefixes (+30, +359, 0030) are redirected\n\n";
        $configContent .= "class AGICallHandlerConfig\n{\n";
        $configContent .= " public \$globalConfiguration = [\n";

        foreach ($newConfig as $extension => $extensionConfig) {
            $configContent .= "    \"$extension\" => [\n";
            foreach ($extensionConfig as $key => $value) {
                if (is_string($value)) {
                    $configContent .= "        \"$key\" => \"$value\",\n";
                } elseif (is_bool($value)) {
                    $boolValue = $value ? 'true' : 'false';
                    $configContent .= "        \"$key\" => $boolValue,\n";
                } elseif (is_numeric($value)) {
                    $configContent .= "        \"$key\" => $value,\n";
                } elseif (is_array($value)) {
                    // Check if it's an associative array (object) or indexed array
                    $isAssoc = (array_keys($value) !== range(0, count($value) - 1));
                    
                    if ($isAssoc || empty($value)) {
                        // Handle as object/associative array (like bounds)
                        if (empty($value)) {
                            $configContent .= "        \"$key\" => null,\n";
                        } else {
                            $arrayContent = '[';
                            $arrayItems = [];
                            foreach ($value as $k => $v) {
                                if (is_numeric($v)) {
                                    $arrayItems[] = "\"$k\" => $v";
                                } else {
                                    $arrayItems[] = "\"$k\" => \"" . addslashes($v) . "\"";
                                }
                            }
                            $arrayContent .= implode(', ', $arrayItems);
                            $arrayContent .= ']';
                            $configContent .= "        \"$key\" => $arrayContent,\n";
                        }
                    } else {
                        // Handle as indexed array
                        $arrayContent = '[';
                        if (!empty($value)) {
                            $arrayItems = [];
                            foreach ($value as $item) {
                                $arrayItems[] = '"' . addslashes($item) . '"';
                            }
                            $arrayContent .= implode(', ', $arrayItems);
                        }
                        $arrayContent .= ']';
                        $configContent .= "        \"$key\" => $arrayContent,\n";
                    }
                } elseif (is_null($value)) {
                    $configContent .= "        \"$key\" => null,\n";
                }
            }
            $configContent .= "    ],\n";
        }
        
        $configContent .= "];\n}\n";
        
        return file_put_contents($this->configPath, $configContent);
    }
    
    public function updateExtension($extension, $config) {
        $this->config[$extension] = $config;
        return $this->saveConfig($this->config);
    }
    
    public function addExtension($extension, $config) {
        $this->config[$extension] = $config;
        return $this->saveConfig($this->config);
    }
    
    public function importConfig($configData) {
        try {
            $newConfig = json_decode($configData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
            
            $result = $this->saveConfig($newConfig);
            if ($result !== false) {
                $this->config = $newConfig;
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function exportConfig() {
        return json_encode($this->config, JSON_PRETTY_PRINT);
    }
    
}

/**
 * Convert WAV file to Asterisk-compatible format (8kHz, mono, 16-bit PCM)
 */
function convertToAsteriskFormat($inputPath) {
    // First check if ffmpeg is available
    exec('which ffmpeg', $output, $returnCode);
    if ($returnCode !== 0) {
        // ffmpeg not available, try to use the original file if it's already a WAV
        if (strtolower(pathinfo($inputPath, PATHINFO_EXTENSION)) === 'wav') {
            return $inputPath; // Return original file
        }
        return false;
    }
    
    // Create a temporary file for the converted audio
    $tempFile = tempnam(sys_get_temp_dir(), 'asterisk_wav_') . '.wav';
    
    // Use ffmpeg to convert to Asterisk format
    $command = "ffmpeg -y -i " . escapeshellarg($inputPath) . 
              " -ar 8000 -ac 1 -acodec pcm_s16le -f wav " . 
              escapeshellarg($tempFile) . " 2>&1";
    
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
        return $tempFile;
    }
    
    // Log error for debugging
    error_log("FFmpeg conversion failed. Command: $command, Output: " . implode("\n", $output));
    
    // Cleanup on failure
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    // Fallback: return original file if it's a WAV
    if (strtolower(pathinfo($inputPath, PATHINFO_EXTENSION)) === 'wav') {
        return $inputPath;
    }
    
    return false;
}

// Handle audio file serving (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'serve_audio') {
    $soundPath = $_GET['soundPath'] ?? '';
    $soundFile = $_GET['soundFile'] ?? '';
    
    if (empty($soundPath) || empty($soundFile)) {
        http_response_code(400);
        echo 'Missing parameters';
        exit;
    }
    
    // Sanitize and validate the file path
    $fullPath = realpath($soundPath . '/' . $soundFile);
    $soundPath = realpath($soundPath);
    
    // Security check: ensure the file is within the sound directory
    if (!$fullPath || !$soundPath || strpos($fullPath, $soundPath) !== 0) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    
    // Check if file exists and is readable
    if (!file_exists($fullPath) || !is_readable($fullPath)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
    // Get file extension and set appropriate MIME type
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'wav' => 'audio/wav',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg'
    ];
    
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    
    // Set headers for audio streaming
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($fullPath));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=3600');
    
    // Handle range requests for better audio streaming
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        $fileSize = filesize($fullPath);
        
        if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $start = intval($matches[1]);
            $end = $matches[2] ? intval($matches[2]) : $fileSize - 1;
            
            if ($start < $fileSize && $end < $fileSize) {
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
                header('Content-Length: ' . ($end - $start + 1));
                
                $file = fopen($fullPath, 'rb');
                fseek($file, $start);
                echo fread($file, $end - $start + 1);
                fclose($file);
                exit;
            }
        }
    }
    
    // Serve the complete file
    readfile($fullPath);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $configManager = new ConfigManager();
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_configs':
            echo json_encode(['success' => true, 'configs' => $configManager->getConfig()]);
            exit;
            
        case 'get_extension':
            $extension = $_POST['extension'];
            $config = $configManager->getExtension($extension);
            echo json_encode(['success' => $config !== null, 'config' => $config]);
            exit;
            
        case 'save_extension':
            $extension = $_POST['extension'];
            $config = json_decode($_POST['config'], true);
            $result = $configManager->updateExtension($extension, $config);
            echo json_encode(['success' => $result !== false]);
            exit;
            
        case 'add_extension':
            $extension = $_POST['extension'];
            $config = json_decode($_POST['config'], true);
            $result = $configManager->addExtension($extension, $config);
            echo json_encode(['success' => $result !== false]);
            exit;
            
        case 'export_config':
            $exportData = $configManager->exportConfig();
            echo json_encode(['success' => true, 'data' => $exportData]);
            exit;
            
        case 'import_config':
            $configData = $_POST['configData'];
            $result = $configManager->importConfig($configData);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'list_sounds':
            $soundPath = $_POST['soundPath'] ?? '';
            $language = $_POST['language'] ?? 'el';
            
            if (empty($soundPath)) {
                echo json_encode(['success' => false, 'message' => 'Sound path is required']);
                exit;
            }
            
            // Check if path exists first
            if (!file_exists($soundPath)) {
                echo json_encode(['success' => false, 'message' => "Directory does not exist: $soundPath"]);
                exit;
            }
            
            // Check if it's a directory
            if (!is_dir($soundPath)) {
                echo json_encode(['success' => false, 'message' => "Path is not a directory: $soundPath"]);
                exit;
            }
            
            // Check if directory is readable
            if (!is_readable($soundPath)) {
                echo json_encode(['success' => false, 'message' => "Directory is not readable. Check permissions for: $soundPath"]);
                exit;
            }
            
            $sounds = [];
            $debugInfo = [];
            
            // Try multiple patterns to find sound files
            $patterns = [
                $soundPath . '/*.' . $language . '.{wav,mp3,ogg}',  // name.el.wav
                $soundPath . '/*_{' . $language . '}.{wav,mp3,ogg}', // name_el.wav
                $soundPath . '/' . $language . '/*.{wav,mp3,ogg}',   // el/name.wav
                $soundPath . '/*.{wav,mp3,ogg}'                      // all audio files
            ];
            
            foreach ($patterns as $pattern) {
                $files = glob($pattern, GLOB_BRACE);
                if ($files) {
                    $debugInfo[] = "Found " . count($files) . " files with pattern: $pattern";
                    foreach ($files as $file) {
                        if (is_file($file) && is_readable($file)) {
                            $fileName = basename($file);
                            $sounds[] = [
                                'name' => $fileName,
                                'path' => dirname($file)
                            ];
                        }
                    }
                }
            }
            
            // Remove duplicates
            $sounds = array_values(array_unique($sounds, SORT_REGULAR));
            
            // Sort sounds by name
            usort($sounds, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            // Add debug information for troubleshooting
            $response = [
                'success' => true, 
                'sounds' => $sounds,
                'debug' => [
                    'soundPath' => $soundPath,
                    'language' => $language,
                    'is_readable' => is_readable($soundPath),
                    'is_writable' => is_writable($soundPath),
                    'total_files_found' => count($sounds),
                    'search_patterns' => $patterns,
                    'pattern_results' => $debugInfo
                ]
            ];
            
            echo json_encode($response);
            exit;
            
        case 'upload_sound':
            if (!isset($_FILES['soundFile'])) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit;
            }
            
            $soundPath = $_POST['soundPath'] ?? '';
            $soundName = $_POST['soundName'] ?? '';
            $language = $_POST['language'] ?? 'el';
            $uploadedFile = $_FILES['soundFile'];
            
            if (empty($soundPath) || empty($soundName)) {
                echo json_encode(['success' => false, 'message' => 'Sound path and name are required']);
                exit;
            }
            
            // Check if path exists and is writable
            if (!file_exists($soundPath)) {
                echo json_encode(['success' => false, 'message' => "Upload directory does not exist: $soundPath"]);
                exit;
            }
            
            if (!is_dir($soundPath)) {
                echo json_encode(['success' => false, 'message' => "Upload path is not a directory: $soundPath"]);
                exit;
            }
            
            if (!is_writable($soundPath)) {
                echo json_encode(['success' => false, 'message' => "Upload directory is not writable. Check permissions for: $soundPath"]);
                exit;
            }
            
            // Check file type - only WAV allowed
            $allowedTypes = ['audio/wav', 'audio/wave'];
            $allowedExtensions = ['wav'];
            $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            
            if (!in_array($uploadedFile['type'], $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only WAV files are allowed.']);
                exit;
            }
            
            // Auto-convert WAV file to 8kHz mono if needed
            $tempPath = $uploadedFile['tmp_name'];
            
            try {
                $convertedPath = convertToAsteriskFormat($tempPath);
                
                if (!$convertedPath) {
                    echo json_encode(['success' => false, 'message' => 'Failed to process WAV file. Please ensure ffmpeg is installed or use an 8kHz mono WAV file.']);
                    exit;
                }
                
                // Use the converted file for upload
                $tempPath = $convertedPath;
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error processing audio file: ' . $e->getMessage()]);
                exit;
            }
            
            // Create filename with language suffix
            $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $soundName) . '_' . $language . '.' . $fileExtension;
            $targetPath = $soundPath . '/' . $fileName;
            
            // Check if file already exists
            if (file_exists($targetPath)) {
                echo json_encode(['success' => false, 'message' => 'A sound with this name already exists for this language']);
                exit;
            }
            
            // Upload the file (using converted file if conversion occurred)
            if (copy($tempPath, $targetPath)) {
                // Set appropriate permissions
                chmod($targetPath, 0644);
                
                // Cleanup temporary converted file if it exists
                if ($tempPath !== $uploadedFile['tmp_name']) {
                    unlink($tempPath);
                }
                
                echo json_encode(['success' => true, 'message' => 'Sound uploaded and converted to 8kHz mono successfully', 'filename' => $fileName, 'stored_as' => $targetPath]);
            } else {
                // Cleanup temporary converted file if it exists
                if ($tempPath !== $uploadedFile['tmp_name']) {
                    unlink($tempPath);
                }
                echo json_encode(['success' => false, 'message' => 'Failed to upload sound file']);
            }
            exit;
    }
}

$configManager = new ConfigManager();
$currentConfig = $configManager->getConfig();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="AGI Configuration Manager" data-el="Διαχειριστής Ρυθμίσεων AGI">AGI Configuration Manager</title>
    <style>
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
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
            min-height: 100vh;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            color: var(--gray-900);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }
        
        .header-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .language-switcher {
            display: flex;
            background: var(--gray-100);
            border-radius: 0.5rem;
            padding: 0.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .language-switcher button {
            padding: 0.5rem 0.75rem;
            border: none;
            background: transparent;
            color: var(--gray-600);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 0.375rem;
            min-width: 40px;
        }

        .language-switcher button.active {
            background: var(--primary);
            color: white;
        }

        .language-switcher button:hover:not(.active) {
            background: var(--gray-200);
            color: var(--gray-800);
        }
        
        .content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .actions {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        @media (min-width: 768px) {
            .actions {
                justify-content: flex-start;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .header {
                padding: 1.5rem 2rem;
            }
            
            .content {
                padding: 2rem;
            }
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.25rem;
            border: 1px solid transparent;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 120px;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        .btn-success:hover {
            background: #059669;
            border-color: #059669;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
            border-color: var(--warning);
        }

        .btn-warning:hover {
            background: #d97706;
            border-color: #d97706;
        }

        .btn-info {
            background: var(--info);
            color: white;
            border-color: var(--info);
        }

        .btn-info:hover {
            background: #0891b2;
            border-color: #0891b2;
        }

        .btn-outline-primary {
            background: white;
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Selection Screen Styles */
        .selection-screen {
            text-align: center;
        }
        
        .no-configs {
            padding: 3rem 1rem;
            background: #f8f8f8;
            border: 2px solid #cccccc;
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .no-configs h2 {
            color: #000000;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .no-configs p {
            color: #333333;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        
        .configs-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-top: 2.5rem;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .configs-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 2rem;
                padding: 1.5rem;
            }
        }
        
        .config-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-align: left;
        }

        .config-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .config-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .config-card:hover::before {
            opacity: 1;
        }
        
        .config-card h3 {
            color: var(--gray-900);
            margin-bottom: 1.25rem;
            font-size: 1.25rem;
            font-weight: 700;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .config-card .config-info {
            display: grid;
            gap: 0.875rem;
        }
        
        .config-card .config-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: 0.5rem;
            border-left: 3px solid var(--gray-200);
            transition: all 0.2s ease;
        }

        .config-card .config-row:hover {
            background: var(--gray-100);
            border-left-color: var(--primary);
        }

        .config-card .label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .config-card .value {
            color: var(--gray-900);
            font-weight: 500;
            font-size: 0.875rem;
            text-align: right;
            max-width: 50%;
            word-break: break-all;
            background: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid var(--gray-200);
        }
        
        /* Edit Screen Styles */
        .edit-screen {
            display: none;
        }
        
        .edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 2rem;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            position: relative;
            overflow: hidden;
        }

        .edit-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
        }

        .edit-header h2 {
            color: var(--gray-900);
            font-size: 1.25rem;
            margin: 0;
            font-weight: 700;
        }
        
        .edit-header .buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .config-form {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-top: 1rem;
        }
        
        .form-grid {
            display: grid;
            gap: 2rem;
        }
        
        .form-group {
            display: grid;
            gap: 0.75rem;
            background: var(--gray-50);
            padding: 1.25rem;
            border-radius: 0.5rem;
            border-left: 4px solid var(--gray-200);
            transition: all 0.2s ease;
        }

        .form-group:hover {
            background: var(--gray-100);
            border-left-color: var(--primary);
        }

        @media (min-width: 768px) {
            .form-group {
                grid-template-columns: 280px 1fr;
                gap: 1.25rem;
                align-items: start;
            }

            .edit-header h2 {
                font-size: 1.5rem;
            }
        }
        
        .form-label-container {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            margin: 0;
        }

        .form-label.missing-key {
            color: var(--danger);
            font-weight: 700;
        }

        .form-label.missing-key::before {
            content: '⚠️ ';
            margin-right: 0.25rem;
        }

        .form-description {
            font-size: 0.75rem;
            color: var(--gray-600);
            line-height: 1.4;
            padding: 0.5rem 0.75rem;
            background: var(--gray-50);
            border-radius: 0.375rem;
            border-left: 3px solid var(--gray-200);
            margin: 0;
        }

        .form-description.missing-key {
            background: #fef2f2;
            border-left-color: var(--danger);
            color: #991b1b;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
            color: var(--gray-900);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input.missing-key {
            border-color: var(--danger);
            background-color: #fef2f2;
        }

        .form-input.missing-key:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        select.form-input {
            cursor: pointer;
        }
        
        input[type="checkbox"].form-input {
            width: auto;
            margin: 0;
            transform: scale(1.5);
            cursor: pointer;
        }
        
        /* Tags styles */
        .tags-container {
            width: 100%;
        }
        
        .tags-wrapper {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            background-color: white;
            min-height: 2.5rem;
        }

        .tags-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background-color: var(--primary);
            color: white;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .tag-remove {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0;
            margin-left: 0.25rem;
            font-size: 1rem;
            line-height: 1;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .tag-remove:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .tag-input {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 0.25rem 0 !important;
            margin: 0 !important;
            flex: 1;
            min-width: 120px;
        }
        
        .tags-wrapper.missing-key {
            border-color: var(--danger);
            background-color: #fef2f2;
        }

        .tags-wrapper.missing-key:focus-within {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .alert-success {
            color: #065f46;
            background-color: #d1fae5;
            border-color: var(--success);
        }

        .alert-danger {
            color: #991b1b;
            background-color: #fee2e2;
            border-color: var(--danger);
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip::after {
            content: '?';
            display: inline-block;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            background: var(--gray-600);
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            margin-left: 0.5rem;
            cursor: help;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 320px;
            background-color: var(--gray-900);
            color: white;
            text-align: left;
            border-radius: 0.5rem;
            padding: 1rem;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -160px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.875rem;
            line-height: 1.4;
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: var(--gray-900) transparent transparent transparent;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Import/Export Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .modal-content h2 {
            margin-bottom: 1.5rem;
            color: var(--gray-900);
            font-weight: 700;
        }

        .close {
            color: var(--gray-400);
            float: right;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: var(--gray-600);
        }
        
        .form-textarea {
            width: 100%;
            min-height: 300px;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-family: 'Courier New', monospace;
            resize: vertical;
            background: white;
            color: var(--gray-900);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .modal-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .file-input {
            display: none;
        }
        
        .file-input-label {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1rem;
            background: #f0f0f0;
            border: 2px solid #000000;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            color: #000000;
            transition: all 0.2s ease;
        }
        
        .file-input-label:hover {
            background: #e0e0e0;
        }
        
        /* Mobile improvements */
        @media (max-width: 767px) {
            .tooltip .tooltiptext {
                width: 280px;
                margin-left: -140px;
                font-size: 0.8125rem;
            }
            
            .form-input {
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .actions {
                position: sticky;
                top: 0;
                background: #ffffff;
                padding: 1rem 0;
                border-bottom: 2px solid #eeeeee;
                z-index: 100;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 1rem;
            }
        }
        
        /* Sound Browser Styles */
        .language-tabs {
            display: flex;
            border-bottom: 2px solid #000000;
            margin-bottom: 1.5rem;
            gap: 0;
        }
        
        .tab-button {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #000000;
            border-bottom: none;
            background-color: #ffffff;
            color: #000000;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .tab-button:first-child {
            border-top-left-radius: 6px;
        }
        
        .tab-button:last-child {
            border-top-right-radius: 6px;
        }
        
        .tab-button.active {
            background-color: #000000;
            color: #ffffff;
        }
        
        .tab-button:not(.active):hover {
            background-color: #f5f5f5;
        }
        
        .sound-browser-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .sound-upload-section {
            border: 2px solid #000000;
            border-radius: 6px;
            padding: 1.5rem;
        }
        
        .sound-upload-section h3 {
            margin: 0 0 1rem 0;
            color: #000000;
            font-weight: 600;
        }
        
        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .sounds-list-section h3 {
            margin: 0 0 1rem 0;
            color: #000000;
            font-weight: 600;
        }
        
        .sounds-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #000000;
            border-radius: 6px;
            padding: 1rem;
        }
        
        .sound-item {
            border: 2px solid #000000;
            border-radius: 6px;
            padding: 1rem;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }
        
        .sound-item:hover {
            background-color: #f5f5f5;
            transform: translateY(-2px);
        }
        
        .sound-name {
            font-weight: 600;
            color: #000000;
            margin-bottom: 0.5rem;
            word-break: break-all;
        }
        
        .sound-controls {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            min-width: 60px;
        }
        
        .sound-playing {
            background-color: #e6ffe6 !important;
            border-color: #00aa00 !important;
        }
        
        @media (max-width: 768px) {
            .sounds-list {
                grid-template-columns: 1fr;
            }
            
            .sound-browser-content {
                gap: 1rem;
            }
        }
        
        /* Card-based Administrative Areas */
        .cards-container {
            width: 100%;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .area-card {
            position: relative;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background-color: var(--input-bg);
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
        }
        
        .area-card:hover {
            border-color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .area-card.selected {
            border-color: var(--accent-color);
            background-color: rgba(147, 197, 253, 0.1);
            box-shadow: 0 0 0 3px rgba(147, 197, 253, 0.2);
        }
        
        .area-checkbox {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--accent-color);
        }
        
        .area-label {
            display: flex;
            align-items: center;
            padding: 16px 50px 16px 16px;
            cursor: pointer;
            height: 100%;
            min-height: 70px;
        }
        
        .area-icon {
            font-size: 28px;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .area-text {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
            line-height: 1.4;
        }
        
        .area-card.selected .area-text {
            color: var(--accent-color);
            font-weight: 600;
        }
        
        .area-card:first-child {
            grid-column: 1 / -1;
            background-color: rgba(255, 99, 71, 0.05);
            border-color: #ff6347;
        }
        
        .area-card:first-child:hover {
            border-color: #ff4500;
            background-color: rgba(255, 99, 71, 0.1);
        }
        
        .area-card:first-child.selected {
            border-color: #ff4500;
            background-color: rgba(255, 99, 71, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 99, 71, 0.2);
        }
        
        .area-card:first-child .area-text {
            font-style: italic;
            color: #ff4500;
        }
        
        .clear-all-btn {
            padding: 8px 16px;
            background-color: var(--button-bg);
            color: var(--button-text);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .clear-all-btn:hover {
            background-color: var(--button-hover-bg);
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .area-label {
                padding: 12px 40px 12px 12px;
                min-height: 60px;
            }
            
            .area-icon {
                font-size: 24px;
                margin-right: 8px;
            }
            
            .area-text {
                font-size: 13px;
            }
        }
    </style>
    
    <!-- Leaflet CSS for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    
    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1 data-en="AGI Configuration Manager" data-el="Διαχειριστής Ρυθμίσεων AGI">AGI Configuration Manager</h1>
                <div class="header-controls">
                    <div class="language-switcher">
                        <button onclick="switchLanguage('en')" class="active" id="btn-en" data-en="English" data-el="English">English</button>
                        <button onclick="switchLanguage('el')" id="btn-el" data-en="Greek" data-el="Ελληνικά">Ελληνικά</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div id="alert-container"></div>
            
            <!-- Selection Screen -->
            <div id="selection-screen" class="selection-screen">
                <div class="actions">
                    <button class="btn btn-info" onclick="showExportModal()" data-en="Export Config" data-el="Εξαγωγή Ρυθμίσεων">Export Config</button>
                    <button class="btn btn-warning" onclick="showImportModal()" data-en="Import Config" data-el="Εισαγωγή Ρυθμίσεων">Import Config</button>
                </div>
                
                <div id="no-configs" class="no-configs" style="display: none;">
                    <h2 data-en="No Configurations Found" data-el="Δεν Βρέθηκαν Ρυθμίσεις">No Configurations Found</h2>
                    <p data-en="There are no configurations available. Click the button below to create your first configuration." data-el="Δεν υπάρχουν διαθέσιμες ρυθμίσεις. Κάντε κλικ στο κουμπί παρακάτω για να δημιουργήσετε την πρώτη σας ρύθμιση.">
                        There are no configurations available. Click the button below to create your first configuration.
                    </p>
                    <button class="btn btn-primary" onclick="showAddExtensionForm()" data-en="Add First Configuration" data-el="Προσθήκη Πρώτης Ρύθμισης">
                        Add First Configuration
                    </button>
                </div>
                
                <div id="multiple-configs" style="display: none;">
                    <h2 data-en="Select Configuration to Edit" data-el="Επιλέξτε Ρύθμιση για Επεξεργασία">Select Configuration to Edit</h2>
                    <div class="configs-grid" id="configs-grid">
                        <!-- Config cards will be loaded here -->
                    </div>
                    <div style="margin-top: 2rem;">
                        <button class="btn btn-outline-primary" onclick="showAddExtensionForm()" data-en="Add New Configuration" data-el="Προσθήκη Νέας Ρύθμισης">
                            Add New Configuration
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Edit Screen -->
            <div id="edit-screen" class="edit-screen">
                <div class="edit-header">
                    <h2 id="edit-title" data-en="Edit Configuration" data-el="Επεξεργασία Ρυθμίσεων">Edit Configuration</h2>
                    <div class="buttons">
                        <button class="btn btn-outline-primary" onclick="goBack()" data-en="Back to List" data-el="Πίσω στη Λίστα">Back to List</button>
                        <button class="btn btn-success" onclick="saveConfiguration()" data-en="Save Changes" data-el="Αποθήκευση Αλλαγών">Save Changes</button>
                    </div>
                </div>
                
                <div class="config-form">
                    <div id="missing-keys-info" style="display: none;" class="alert alert-danger">
                        <strong data-en="⚠️ Missing Keys Detected" data-el="⚠️ Εντοπίστηκαν Κλειδιά που Λείπουν">⚠️ Missing Keys Detected</strong><br>
                        <span data-en="Some configuration keys were missing and have been added with default values. Fields marked with ⚠️ are missing keys. Save to apply changes." data-el="Κάποια κλειδιά ρυθμίσεων έλειπαν και έχουν προστεθεί με προεπιλεγμένες τιμές. Τα πεδία που είναι σημειωμένα με ⚠️ είναι κλειδιά που λείπουν. Αποθηκεύστε για να εφαρμόσετε τις αλλαγές.">Some configuration keys were missing and have been added with default values. Fields marked with ⚠️ are missing keys. Save to apply changes.</span>
                    </div>
                    <div class="form-grid" id="config-form-fields">
                        <!-- Form fields will be generated here -->
                    </div>
                </div>
            </div>
            
            <!-- Add Extension Form -->
            <div id="add-extension-form" style="display: none;">
                <div class="edit-header">
                    <h2 data-en="Add New Configuration" data-el="Προσθήκη Νέας Ρύθμισης">Add New Configuration</h2>
                    <button class="btn btn-outline-primary" onclick="cancelAdd()" data-en="Cancel" data-el="Ακύρωση">Cancel</button>
                </div>
                
                <div class="config-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" data-en="Extension Number:" data-el="Αριθμός Extension:">Extension Number:</label>
                            <input type="text" id="new-extension-number" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" data-en="Extension Name:" data-el="Όνομα Extension:">Extension Name:</label>
                            <input type="text" id="new-extension-name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <div></div>
                            <button class="btn btn-primary" onclick="createExtension()" data-en="Create Configuration" data-el="Δημιουργία Ρύθμισης">Create Configuration</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('exportModal')">&times;</span>
            <h2 data-en="Export Configuration" data-el="Εξαγωγή Ρυθμίσεων">Export Configuration</h2>
            <p data-en="Copy the configuration data below:" data-el="Αντιγράψτε τα δεδομένα ρύθμισης παρακάτω:">Copy the configuration data below:</p>
            <textarea id="exportData" class="form-textarea" readonly></textarea>
            <div class="modal-buttons">
                <button class="btn btn-info" onclick="copyToClipboard()" data-en="Copy to Clipboard" data-el="Αντιγραφή στο Clipboard">Copy to Clipboard</button>
                <button class="btn btn-success" onclick="downloadConfig()" data-en="Download as File" data-el="Λήψη ως Αρχείο">Download as File</button>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('importModal')">&times;</span>
            <h2 data-en="Import Configuration" data-el="Εισαγωγή Ρυθμίσεων">Import Configuration</h2>
            <div class="file-input-wrapper">
                <input type="file" id="configFile" class="file-input" accept=".json" onchange="loadConfigFile()">
                <label for="configFile" class="file-input-label" data-en="Choose File" data-el="Επιλογή Αρχείου">Choose File</label>
            </div>
            <p data-en="Or paste configuration data below:" data-el="Ή επικολλήστε τα δεδομένα ρύθμισης παρακάτω:">Or paste configuration data below:</p>
            <textarea id="importData" class="form-textarea" placeholder="Paste JSON configuration data here..."></textarea>
            <div class="modal-buttons">
                <button class="btn btn-outline-primary" onclick="closeModal('importModal')" data-en="Cancel" data-el="Ακύρωση">Cancel</button>
                <button class="btn btn-warning" onclick="importConfiguration()" data-en="Import Configuration" data-el="Εισαγωγή Ρυθμίσεων">Import Configuration</button>
            </div>
        </div>
    </div>

    <!-- Sound Browser Modal -->
    <div id="soundBrowserModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" onclick="closeModal('soundBrowserModal')">&times;</span>
            <h2 data-en="Sound Browser" data-el="Περιήγηση Ήχων">Sound Browser</h2>
            
            <div class="language-tabs">
                <button id="sound-tab-el" class="tab-button active" onclick="switchSoundTab('el')" data-en="Greek (el)" data-el="Ελληνικά (el)">Greek (el)</button>
                <button id="sound-tab-en" class="tab-button" onclick="switchSoundTab('en')" data-en="English (en)" data-el="Αγγλικά (en)">English (en)</button>
                <button id="sound-tab-bg" class="tab-button" onclick="switchSoundTab('bg')" data-en="Bulgarian (bg)" data-el="Βουλγαρικά (bg)">Bulgarian (bg)</button>
            </div>
            
            <div class="sound-browser-content">
                <div class="sound-upload-section">
                    <h3 data-en="Upload New Sound" data-el="Ανέβασμα Νέου Ήχου">Upload New Sound</h3>
                    <div class="upload-form">
                        <div class="form-group">
                            <label data-en="Sound Name:" data-el="Όνομα Ήχου:">Sound Name:</label>
                            <input type="text" id="newSoundName" class="form-input" placeholder="Enter sound name...">
                        </div>
                        <div class="file-input-wrapper">
                            <input type="file" id="soundFile" class="file-input" accept=".wav,.mp3,.ogg">
                            <label for="soundFile" class="file-input-label" data-en="Choose Sound File" data-el="Επιλογή Αρχείου Ήχου">Choose Sound File</label>
                        </div>
                        <button class="btn btn-primary" onclick="uploadSound()" data-en="Upload Sound" data-el="Ανέβασμα Ήχου">Upload Sound</button>
                    </div>
                </div>
                
                <div class="sounds-list-section">
                    <h3 data-en="Available Sounds" data-el="Διαθέσιμοι Ήχοι">Available Sounds</h3>
                    <div id="soundsList" class="sounds-list">
                        <!-- Sounds will be loaded here -->
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button class="btn btn-outline-primary" onclick="closeModal('soundBrowserModal')" data-en="Close" data-el="Κλείσιμο">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentLanguage = 'en';
        let currentConfigs = <?php echo json_encode($currentConfig ?: []); ?>;
        let selectedExtension = null;
        let currentExtensionConfig = null;
        
        // Define the complete expected configuration structure with default values
        const expectedConfigStructure = {
            name: '',
            googleApiKey: 'AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw',
            clientToken: '',
            registerBaseUrl: '',
            failCallTo: '',
            soundPath: '/var/sounds/iqtaxi',
            tts: 'google',
            daysValid: 7,
            defaultLanguage: 'el',
            callbackMode: 1,
            callbackUrl: '',
            repeatTimes: 10,
            strictDropoffLocation: false,
            geocodingApiVersion: 1,
            initialMessageSound: '',
            redirectToOperator: false,
            autoCallCentersMode: 3,
            maxRetries: 5,
            bounds: null,
            centerBias: null,
            boundsRestrictionMode: null,
            confirmation_mode: 1,
            getUser_enabled: true,
            askForName: true,
            customFallCallTo: false,
            customFallCallToURL: "https://www.iqtaxi.com/IQ_WebApiV3/api/asterisk/GetRedirectDrvPhoneFull/",
            foreignRedirect: false
        };
        
        const translations = {
            en: {
                // Field labels
                'name': 'Name',
                'googleApiKey': 'Google API Key',
                'clientToken': 'Client Token',
                'registerBaseUrl': 'Register Base URL',
                'failCallTo': 'Fail Call To',
                'soundPath': 'Sound Path',
                'tts': 'TTS Provider',
                'daysValid': 'Days Valid',
                'defaultLanguage': 'Default Language',
                'callbackMode': 'Callback Mode',
                'callbackUrl': 'Callback URL',
                'repeatTimes': 'Callback Check Duration',
                'strictDropoffLocation': 'Strict Dropoff Location',
                'geocodingApiVersion': 'Geocoding API Version',
                'initialMessageSound': 'Initial Message Sound',
                'redirectToOperator': 'Redirect To Operator',
                'autoCallCentersMode': 'Auto Call Centers Mode',
                'maxRetries': 'Max Retries',
                'bounds': 'Geographic Bounds',
                'centerBias': 'Center Bias',
                'boundsRestrictionMode': 'Bounds Restriction Mode',
                'confirmation_mode': 'Confirmation Mode',
                'getUser_enabled': 'Get User Enabled',
                'askForName': 'Ask For Name',
                'customFallCallTo': 'Custom Fall Call To',
                'customFallCallToURL': 'Custom Fall Call To URL',
                'foreignRedirect': 'Foreign Number Redirect',
                // Tooltips
                'name_tooltip': 'Human readable name for this extension',
                'googleApiKey_tooltip': 'Google Maps/Places API key for geocoding',
                'clientToken_tooltip': 'Authentication token for the taxi service API',
                'registerBaseUrl_tooltip': 'Base URL for the taxi booking API',
                'failCallTo_tooltip': 'Phone number/channel to redirect when call fails',
                'soundPath_tooltip': 'Directory path where sound files are stored',
                'tts_tooltip': 'Text-to-speech provider (google, edge-tts)',
                'daysValid_tooltip': 'Number of days a booking remains valid',
                'defaultLanguage_tooltip': 'Default language for this extension (el=Greek, en=English, bg=Bulgarian)',
                'callbackMode_tooltip': 'Mode 1: Normal TTS, Mode 2: Callback with status file',
                'callbackUrl_tooltip': 'URL for callback mode notifications',
                'repeatTimes_tooltip': 'In Callback Mode 2: How many times to check for server response (checks every 3 seconds). Example: 10 = wait up to 30 seconds for taxi assignment. After this, transfers to operator if no taxi found',
                'strictDropoffLocation_tooltip': 'Require precise GPS coordinates for dropoff location',
                'geocodingApiVersion_tooltip': 'API version: 1=Geocoding API, 2=Places API',
                'initialMessageSound_tooltip': 'Sound file name to play before welcome message',
                'redirectToOperator_tooltip': 'Automatically redirect to operator after initial message',
                'autoCallCentersMode_tooltip': 'Call center mode: 0=All disabled (redirect to operator), 1=ASAP calls only, 2=Reservations only, 3=All enabled',
                'maxRetries_tooltip': 'Maximum number of retry attempts for name, pickup, destination and reservation collection',
                'bounds_tooltip': 'Set geographic bounds for post-processing validation. Results outside bounds will be rejected. Leave empty to accept all areas.',
                'centerBias_tooltip': 'Set center point and radius to bias API search results. Enter coordinates and radius in meters.',
                'boundsRestrictionMode_tooltip': 'Control when bounds/centerBias apply: 0/null=Never, 1=Pickup only, 2=Dropoff only, 3=Both locations',
                'confirmation_mode_tooltip': 'Mode 1: Reads name, pickup, dropoff via TTS and waits for confirmation. Mode 2: Only plays confirmation prompt without TTS details',
                'getUser_enabled_tooltip': 'Enable to check server for existing user data (name and address). Disable to always ask for new user information',
                'askForName_tooltip': 'Enable to ask customer for their name during call. Disable to skip name collection.',
                'customFallCallTo_tooltip': 'Enable custom API-based fallback number retrieval when redirecting to operator',
                'customFallCallToURL_tooltip': 'Base URL for custom fallback API. Caller number will be appended to this URL',
                'foreignRedirect_tooltip': 'Enable to redirect foreign numbers (not in allowed prefixes list) to operator. When enabled, numbers > 10 digits without allowed prefixes (+30, +359, 0030) are redirected',
                // Messages
                'config_saved': 'Configuration saved successfully!',
                'config_error': 'Error saving configuration!',
                'extension_added': 'Extension added successfully!',
                'extension_number_required': 'Extension number is required!',
                'extension_exists': 'Extension already exists!',
                'export_success': 'Configuration exported successfully!',
                'import_success': 'Configuration imported successfully!',
                'import_error': 'Error importing configuration. Please check the format.',
                'copy_success': 'Copied to clipboard!',
                'copy_error': 'Could not copy to clipboard.',
                'no_data': 'No configuration data to export.',
                'missing_key_suffix': '(Missing)',
                'missing_key_tooltip': 'This configuration key was missing and has been added with default value. Save to apply changes.',
                'missing_keys_detected': 'Missing configuration keys detected and added with default values.',
                'missing_keys_applied': 'Missing keys have been added to the configuration.',
                // Card labels
                'card_name': 'Name',
                'card_language': 'Language', 
                'card_tts_provider': 'TTS Provider',
                'card_call_mode': 'Call Mode',
                'card_callback_mode': 'Callback Mode',
                'card_sound_path': 'Sound Path',
                'card_unnamed': 'Unnamed',
                // Call modes
                'call_mode_all_disabled': 'All Disabled',
                'call_mode_asap_only': 'ASAP Only',
                'call_mode_reservations_only': 'Reservations Only', 
                'call_mode_all_enabled': 'All Enabled',
                // Select options
                'select_normal_mode': 'Normal Mode',
                'select_callback_mode': 'Callback Mode',
                'select_geocoding_api': 'Geocoding API',
                'select_places_api': 'Places API',
                'select_all_disabled_operator': 'All Disabled (Redirect to Operator)',
                'select_asap_calls_only': 'ASAP Calls Only',
                'select_reservations_only': 'Reservations Only',
                'select_all_enabled': 'All Enabled',
                'select_bounds_none': 'No Restrictions',
                'select_bounds_pickup_only': 'Apply to Pickup Only',
                'select_bounds_dropoff_only': 'Apply to Dropoff Only',
                'select_bounds_both': 'Apply to Both Locations',
                'select_confirmation_mode_1': 'Full TTS Confirmation (reads name, pickup, dropoff)',
                'select_confirmation_mode_2': 'Quick Confirmation (press 0 to confirm)',
                // Sound browser
                'browse_sounds': 'Browse Sounds',
                'sound_browser_title': 'Sound Browser',
                'upload_sound': 'Upload Sound',
                'sound_name': 'Sound Name',
                'choose_sound_file': 'Choose Sound File',
                'upload_sound_btn': 'Upload',
                'play_sound': 'Play',
                'stop_sound': 'Stop',
                'select_sound': 'Select',
                'no_sounds_found': 'No sounds found in this directory',
                'upload_success': 'Sound uploaded successfully!',
                'upload_error': 'Error uploading sound file.',
                'invalid_sound_path': 'Please enter a valid sound path first.',
                'loading': 'Loading...',
                'loading_sounds': 'Loading sounds...',
                'error_loading_sounds': 'Error loading sounds',
                'error_playing_sound': 'Error playing sound file',
                'media_error_network': 'Network error while loading sound',
                'media_error_decode': 'Error decoding sound file',
                'media_error_not_supported': 'Sound file format not supported',
                'error_uploading_sound': 'Error uploading sound',
                'geographic_bounds': 'Geographic Bounds',
                'no_bounds_set': 'No bounds set - searches all areas',
                'map_instructions': 'Map Instructions: Click and drag to draw a rectangle on the map to set geographic bounds',
                'center_bias': 'Center Bias',
                'no_center_bias': 'No center bias - searches globally',
                'center_bias_map': 'Center Bias: Click map to set center, Drag marker to move',
                'edit_title_prefix': 'Edit Configuration - Extension',
                'save_changes_title': 'Save Changes',
                'back_to_list_title': 'Back to List'
            },
            el: {
                // Field labels
                'name': 'Όνομα',
                'googleApiKey': 'Κλειδί Google API',
                'clientToken': 'Token Πελάτη',
                'registerBaseUrl': 'Βασικό URL Εγγραφής',
                'failCallTo': 'Ανακατεύθυνση σε Αποτυχία',
                'soundPath': 'Διαδρομή Ήχων',
                'tts': 'Πάροχος TTS',
                'daysValid': 'Ημέρες Ισχύος',
                'defaultLanguage': 'Προεπιλεγμένη Γλώσσα',
                'callbackMode': 'Λειτουργία Callback',
                'callbackUrl': 'URL Callback',
                'repeatTimes': 'Διάρκεια Ελέγχου Callback',
                'strictDropoffLocation': 'Αυστηρή Τοποθεσία Προορισμού',
                'geocodingApiVersion': 'Έκδοση API Γεωκωδικοποίησης',
                'initialMessageSound': 'Ήχος Αρχικού Μηνύματος',
                'redirectToOperator': 'Ανακατεύθυνση σε Χειριστή',
                'autoCallCentersMode': 'Λειτουργία Αυτόματου Call Center',
                'maxRetries': 'Μέγιστες Επαναλήψεις',
                'bounds': 'Γεωγραφικά Όρια',
                'centerBias': 'Κεντρική Προκατάληψη',
                'boundsRestrictionMode': 'Λειτουργία Περιορισμού Ορίων',
                'confirmation_mode': 'Λειτουργία Επιβεβαίωσης',
                'getUser_enabled': 'Ενεργοποίηση Λήψης Χρήστη',
                'askForName': 'Ερώτηση για Όνομα',
                'customFallCallTo': 'Προσαρμοσμένη Εφεδρική Κλήση',
                'customFallCallToURL': 'URL Προσαρμοσμένης Εφεδρικής Κλήσης',
                'foreignRedirect': 'Ανακατεύθυνση Αλλοδαπών Αριθμών',
                // Tooltips
                'name_tooltip': 'Αναγνωρίσιμο όνομα για αυτό το extension',
                'googleApiKey_tooltip': 'Κλειδί API Google Maps/Places για γεωκωδικοποίηση',
                'clientToken_tooltip': 'Token ταυτοποίησης για το API της υπηρεσίας ταξί',
                'registerBaseUrl_tooltip': 'Βασικό URL για το API κράτησης ταξί',
                'failCallTo_tooltip': 'Αριθμός τηλεφώνου/κανάλι για ανακατεύθυνση σε αποτυχία',
                'soundPath_tooltip': 'Διαδρομή καταλόγου όπου αποθηκεύονται τα αρχεία ήχου',
                'tts_tooltip': 'Πάροχος μετατροπής κειμένου σε ομιλία (google, edge-tts)',
                'daysValid_tooltip': 'Αριθμός ημερών που μια κράτηση παραμένει έγκυρη',
                'defaultLanguage_tooltip': 'Προεπιλεγμένη γλώσσα για αυτό το extension (el=Ελληνικά, en=Αγγλικά, bg=Βουλγαρικά)',
                'callbackMode_tooltip': 'Λειτουργία 1: Κανονικό TTS, Λειτουργία 2: Callback με αρχείο κατάστασης',
                'callbackUrl_tooltip': 'URL για ειδοποιήσεις callback mode',
                'repeatTimes_tooltip': 'Σε Λειτουργία Callback 2: Πόσες φορές θα ελέγξει για απάντηση από τον διακομιστή (έλεγχος κάθε 3 δευτερόλεπτα). Παράδειγμα: 10 = αναμονή έως 30 δευτερόλεπτα για ανάθεση ταξί. Μετά, μεταφέρει σε χειριστή αν δεν βρεθεί ταξί',
                'strictDropoffLocation_tooltip': 'Απαίτηση ακριβών συντεταγμένων GPS για τον προορισμό',
                'geocodingApiVersion_tooltip': 'Έκδοση API: 1=Geocoding API, 2=Places API',
                'initialMessageSound_tooltip': 'Όνομα αρχείου ήχου που θα παιχτεί πριν το μήνυμα καλωσορίσματος',
                'redirectToOperator_tooltip': 'Αυτόματη ανακατεύθυνση σε χειριστή μετά το αρχικό μήνυμα',
                'autoCallCentersMode_tooltip': 'Λειτουργία call center: 0=Όλα απενεργοποιημένα (ανακατεύθυνση σε χειριστή), 1=Μόνο άμεσες κλήσεις, 2=Μόνο κρατήσεις, 3=Όλα ενεργοποιημένα',
                'maxRetries_tooltip': 'Μέγιστος αριθμός επαναλήψεων για συλλογή ονόματος, παραλαβής, προορισμού και κράτησης',
                'bounds_tooltip': 'Ορίστε γεωγραφικά όρια για επικύρωση αποτελεσμάτων. Αποτελέσματα εκτός ορίων θα απορρίπτονται. Αφήστε κενό για αποδοχή όλων των περιοχών.',
                'centerBias_tooltip': 'Ορίστε κεντρικό σημείο και ακτίνα για προκατάληψη αποτελεσμάτων API. Εισάγετε συντεταγμένες και ακτίνα σε μέτρα.',
                'boundsRestrictionMode_tooltip': 'Έλεγχος εφαρμογής ορίων/κέντρου: 0/null=Ποτέ, 1=Μόνο παραλαβή, 2=Μόνο προορισμός, 3=Και τα δύο',
                'confirmation_mode_tooltip': 'Λειτουργία 1: Διαβάζει όνομα, παραλαβή, προορισμό μέσω TTS και περιμένει επιβεβαίωση. Λειτουργία 2: Μόνο αναπαράγει μήνυμα επιβεβαίωσης χωρίς TTS λεπτομέρειες',
                'getUser_enabled_tooltip': 'Ενεργοποιήστε για έλεγχο στον διακομιστή για υπάρχοντα δεδομένα χρήστη (όνομα και διεύθυνση). Απενεργοποιήστε για να ζητάτε πάντα νέα στοιχεία χρήστη',
                'askForName_tooltip': 'Ενεργοποιήστε για να ζητάτε όνομα πελάτη κατά τη διάρκεια της κλήσης. Απενεργοποιήστε για να παραλείπετε τη συλλογή ονόματος.',
                'customFallCallTo_tooltip': 'Ενεργοποιήστε την ανάκτηση προσαρμοσμένου αριθμού εφεδρείας μέσω API κατά την ανακατεύθυνση σε χειριστή',
                'customFallCallToURL_tooltip': 'Βασικό URL για προσαρμοσμένο API εφεδρείας. Ο αριθμός καλούντος θα προστεθεί στο τέλος αυτού του URL',
                'foreignRedirect_tooltip': 'Ενεργοποιήστε για ανακατεύθυνση αλλοδαπών αριθμών (που δεν είναι στη λίστα επιτρεπόμενων προθεμάτων) στον χειριστή. Όταν ενεργοποιηθεί, αριθμοί > 10 ψηφία χωρίς επιτρεπόμενα προθέματα (+30, +359, 0030) ανακατευθύνονται',
                // Messages
                'config_saved': 'Οι ρυθμίσεις αποθηκεύτηκαν επιτυχώς!',
                'config_error': 'Σφάλμα στην αποθήκευση των ρυθμίσεων!',
                'extension_added': 'Το extension προστέθηκε επιτυχώς!',
                'extension_number_required': 'Ο αριθμός extension είναι υποχρεωτικός!',
                'extension_exists': 'Το extension υπάρχει ήδη!',
                'export_success': 'Οι ρυθμίσεις εξήχθησαν επιτυχώς!',
                'import_success': 'Οι ρυθμίσεις εισήχθησαν επιτυχώς!',
                'import_error': 'Σφάλμα στην εισαγωγή ρυθμίσεων. Ελέγξτε τη μορφή.',
                'copy_success': 'Αντιγράφηκε στο clipboard!',
                'copy_error': 'Δεν μπόρεσε να αντιγραφεί στο clipboard.',
                'no_data': 'Δεν υπάρχουν δεδομένα ρύθμισης για εξαγωγή.',
                'missing_key_suffix': '(Λείπει)',
                'missing_key_tooltip': 'Αυτό το κλειδί ρυθμίσεων έλειπε και έχει προστεθεί με προεπιλεγμένη τιμή. Αποθηκεύστε για να εφαρμόσετε τις αλλαγές.',
                'missing_keys_detected': 'Εντοπίστηκαν κλειδιά ρυθμίσεων που έλειπαν και προστέθηκαν με προεπιλεγμένες τιμές.',
                'missing_keys_applied': 'Τα κλειδιά που έλειπαν έχουν προστεθεί στη ρύθμιση.',
                // Card labels
                'card_name': 'Όνομα',
                'card_language': 'Γλώσσα', 
                'card_tts_provider': 'Πάροχος TTS',
                'card_call_mode': 'Λειτουργία Κλήσεων',
                'card_callback_mode': 'Λειτουργία Callback',
                'card_sound_path': 'Διαδρομή Ήχων',
                'card_unnamed': 'Χωρίς Όνομα',
                // Call modes
                'call_mode_all_disabled': 'Όλα Απενεργοποιημένα',
                'call_mode_asap_only': 'Μόνο Άμεσες',
                'call_mode_reservations_only': 'Μόνο Κρατήσεις', 
                'call_mode_all_enabled': 'Όλα Ενεργοποιημένα',
                // Select options
                'select_normal_mode': 'Κανονική Λειτουργία',
                'select_callback_mode': 'Λειτουργία Callback',
                'select_geocoding_api': 'Geocoding API',
                'select_places_api': 'Places API',
                'select_all_disabled_operator': 'Όλα Απενεργοποιημένα (Ανακατεύθυνση σε Χειριστή)',
                'select_asap_calls_only': 'Μόνο Άμεσες Κλήσεις',
                'select_reservations_only': 'Μόνο Κρατήσεις',
                'select_all_enabled': 'Όλα Ενεργοποιημένα',
                'select_bounds_none': 'Χωρίς Περιορισμούς',
                'select_bounds_pickup_only': 'Εφαρμογή μόνο στην Παραλαβή',
                'select_bounds_dropoff_only': 'Εφαρμογή μόνο στον Προορισμό',
                'select_bounds_both': 'Εφαρμογή και στις Δύο Τοποθεσίες',
                'select_confirmation_mode_1': 'Πλήρης Επιβεβαίωση TTS (διαβάζει όνομα, παραλαβή, προορισμό)',
                'select_confirmation_mode_2': 'Γρήγορη Επιβεβαίωση (πατήστε 0 για επιβεβαίωση)',
                // Sound browser
                'browse_sounds': 'Περιήγηση Ήχων',
                'sound_browser_title': 'Περιήγηση Ήχων',
                'upload_sound': 'Ανέβασμα Ήχου',
                'sound_name': 'Όνομα Ήχου',
                'choose_sound_file': 'Επιλογή Αρχείου Ήχου',
                'upload_sound_btn': 'Ανέβασμα',
                'play_sound': 'Αναπαραγωγή',
                'stop_sound': 'Στάση',
                'select_sound': 'Επιλογή',
                'no_sounds_found': 'Δεν βρέθηκαν ήχοι σε αυτόν τον κατάλογο',
                'upload_success': 'Ο ήχος ανέβηκε επιτυχώς!',
                'upload_error': 'Σφάλμα στο ανέβασμα του αρχείου ήχου.',
                'invalid_sound_path': 'Παρακαλώ εισάγετε πρώτα έγκυρη διαδρομή ήχου.',
                'loading': 'Φόρτωση...',
                'loading_sounds': 'Φόρτωση ήχων...',
                'error_loading_sounds': 'Σφάλμα στη φόρτωση ήχων',
                'error_playing_sound': 'Σφάλμα στην αναπαραγωγή αρχείου ήχου',
                'media_error_network': 'Σφάλμα δικτύου κατά τη φόρτωση ήχου',
                'media_error_decode': 'Σφάλμα αποκωδικοποίησης αρχείου ήχου',
                'media_error_not_supported': 'Η μορφή αρχείου ήχου δεν υποστηρίζεται',
                'error_uploading_sound': 'Σφάλμα στο ανέβασμα ήχου',
                'geographic_bounds': 'Γεωγραφικά Όρια',
                'no_bounds_set': 'Δεν έχουν οριστεί όρια - αναζήτηση σε όλες τις περιοχές',
                'map_instructions': 'Οδηγίες Χάρτη: Κάντε κλικ και σύρετε για να σχεδιάσετε ένα ορθογώνιο στον χάρτη για να ορίσετε γεωγραφικά όρια',
                'center_bias': 'Κεντρική Προκατάληψη',
                'no_center_bias': 'Δεν υπάρχει κεντρική προκατάληψη - αναζήτηση παγκοσμίως',
                'center_bias_map': 'Κεντρική Προκατάληψη: Κάντε κλικ στο χάρτη για να ορίσετε κέντρο, Σύρετε τον δείκτη για να μετακινηθείτε',
                'edit_title_prefix': 'Επεξεργασία Ρυθμίσεων - Extension',
                'save_changes_title': 'Αποθήκευση Αλλαγών',
                'back_to_list_title': 'Πίσω στη Λίστα'
            }
        };
        
        function switchLanguage(lang) {
            currentLanguage = lang;

            // Update active button
            document.querySelectorAll('.language-switcher button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById('btn-' + lang).classList.add('active');

            // Update all translatable elements
            document.querySelectorAll('[data-en][data-el]').forEach(element => {
                element.textContent = element.getAttribute('data-' + lang);
            });

            // Update placeholders
            document.querySelectorAll('textarea[placeholder]').forEach(element => {
                if (lang === 'el') {
                    element.placeholder = 'Επικολλήστε τα δεδομένα JSON ρυθμίσεων εδώ...';
                } else {
                    element.placeholder = 'Paste JSON configuration data here...';
                }
            });

            // Refresh dynamic content based on current screen
            const selectionScreen = document.getElementById('selection-screen');
            const editScreen = document.getElementById('edit-screen');

            // Get computed styles to check actual visibility
            const selectionVisible = selectionScreen && window.getComputedStyle(selectionScreen).display !== 'none';
            const editVisible = editScreen && window.getComputedStyle(editScreen).display !== 'none';

            if (selectionVisible) {
                // Refresh config cards on selection screen
                loadConfigCards();
            }

            if (editVisible) {
                // Refresh form fields on edit screen
                loadConfigForm();

                // Update edit title with proper translation
                const editTitle = document.getElementById('edit-title');
                if (selectedExtension && currentExtensionConfig) {
                    const editTitleText = `${getTranslation('edit_title_prefix')} ${selectedExtension}`;
                    editTitle.textContent = editTitleText;

                    // Also update the page title
                    const saveChanges = getTranslation('save_changes_title');
                    const backToList = getTranslation('back_to_list_title');
                    const pageTitleText = `${saveChanges} | ${backToList} - Extension ${selectedExtension}`;
                    document.title = pageTitleText;
                }
            }
        }
        
        function getTranslation(key) {
            return translations[currentLanguage][key] || key;
        }
        
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        function initializeScreen() {
            const configCount = Object.keys(currentConfigs).length;

            if (configCount === 0) {
                // No configs - show add first config screen
                document.getElementById('no-configs').style.display = 'block';
                document.getElementById('multiple-configs').style.display = 'none';
            } else {
                // Always show selection screen, even for single extension
                document.getElementById('no-configs').style.display = 'none';
                document.getElementById('multiple-configs').style.display = 'block';
                loadConfigCards();
            }
        }
        
        function loadConfigCards() {
            const container = document.getElementById('configs-grid');
            container.innerHTML = '';
            
            Object.entries(currentConfigs).forEach(([extension, config]) => {
                const card = document.createElement('div');
                card.className = 'config-card';
                card.onclick = () => selectExtension(extension);
                
                // Create info rows for key config values
                const infoRows = [
                    { label: getTranslation('card_name'), value: config.name || getTranslation('card_unnamed') },
                    { label: getTranslation('card_language'), value: config.defaultLanguage || 'el' },
                    { label: getTranslation('card_tts_provider'), value: config.tts || 'google' },
                    { label: getTranslation('card_call_mode'), value: getCallModeText(config.autoCallCentersMode) },
                    { label: getTranslation('card_callback_mode'), value: config.callbackMode || '1' },
                    { label: getTranslation('card_sound_path'), value: config.soundPath || '/var/sounds/iqtaxi' }
                ].map(row => 
                    `<div class="config-row">
                        <span class="label">${row.label}:</span>
                        <span class="value">${row.value}</span>
                    </div>`
                ).join('');
                
                card.innerHTML = `
                    <h3>Extension ${extension}</h3>
                    <div class="config-info">
                        ${infoRows}
                    </div>
                `;
                container.appendChild(card);
            });
        }
        
        function getCallModeText(mode) {
            switch(parseInt(mode)) {
                case 0: return getTranslation('call_mode_all_disabled');
                case 1: return getTranslation('call_mode_asap_only');
                case 2: return getTranslation('call_mode_reservations_only');
                case 3: return getTranslation('call_mode_all_enabled');
                default: return getTranslation('call_mode_all_enabled');
            }
        }
        
        function selectExtension(extension) {
            selectedExtension = extension;
            const originalConfig = { ...currentConfigs[extension] };
            currentExtensionConfig = normalizeConfig(originalConfig);
            
            // Check for missing keys and show alert
            const missingKeys = getMissingKeys(originalConfig);
            const hasMissingKeys = missingKeys.length > 0;
            
            document.getElementById('selection-screen').style.display = 'none';
            document.getElementById('edit-screen').style.display = 'block';
            document.getElementById('add-extension-form').style.display = 'none';
            
            // Set edit title using translation
            const editTitleText = `${getTranslation('edit_title_prefix')} ${extension}`;
            document.getElementById('edit-title').textContent = editTitleText;

            // Update page title to show Save Changes and Back to List
            const saveChanges = getTranslation('save_changes_title') ||
                (currentLanguage === 'el' ? 'Αποθήκευση Αλλαγών' : 'Save Changes');
            const backToList = getTranslation('back_to_list_title') ||
                (currentLanguage === 'el' ? 'Πίσω στη Λίστα' : 'Back to List');
            const pageTitleText = `${saveChanges} | ${backToList} - Extension ${extension}`;
            document.title = pageTitleText;
            
            // Show/hide missing keys info box
            document.getElementById('missing-keys-info').style.display = hasMissingKeys ? 'block' : 'none';
            
            loadConfigForm();
        }
        
        function normalizeConfig(config) {
            const normalizedConfig = { ...expectedConfigStructure };
            
            // Copy existing values
            Object.keys(config).forEach(key => {
                if (normalizedConfig.hasOwnProperty(key)) {
                    normalizedConfig[key] = config[key];
                }
            });
            
            return normalizedConfig;
        }
        
        function getMissingKeys(config) {
            const missingKeys = [];
            Object.keys(expectedConfigStructure).forEach(key => {
                if (!config.hasOwnProperty(key)) {
                    missingKeys.push(key);
                }
            });
            return missingKeys;
        }
        
        function loadConfigForm() {
            const container = document.getElementById('config-form-fields');
            container.innerHTML = '';
            
            Object.entries(currentExtensionConfig).forEach(([key, value]) => {
                const formGroup = createFormField(key, value);
                container.appendChild(formGroup);
            });
        }
        
        function createFormField(key, value) {
            const formGroup = document.createElement('div');
            formGroup.className = 'form-group';
            formGroup.setAttribute('data-key', key);
            
            // Check if this key was missing from the original config
            const originalConfig = currentConfigs[selectedExtension];
            const isMissingKey = !originalConfig.hasOwnProperty(key);
            
            const labelContainer = document.createElement('div');
            labelContainer.className = 'form-label-container';

            const label = document.createElement('label');
            label.className = 'form-label' + (isMissingKey ? ' missing-key' : '');
            label.textContent = `${getTranslation(key)}${isMissingKey ? ' ' + getTranslation('missing_key_suffix') : ''}`;

            const description = document.createElement('div');
            description.className = 'form-description' + (isMissingKey ? ' missing-key' : '');
            description.textContent = getTranslation(key + '_tooltip') + (isMissingKey ? ' ' + getTranslation('missing_key_tooltip') : '');

            labelContainer.appendChild(label);
            labelContainer.appendChild(description);

            const input = createInput(key, value, isMissingKey);

            formGroup.appendChild(labelContainer);
            formGroup.appendChild(input);
            
            return formGroup;
        }
        
        function createInput(key, value, isMissingKey = false) {
            const fieldType = getFieldType(key, value);
            const inputClass = 'form-input' + (isMissingKey ? ' missing-key' : '');
            
            if (fieldType === 'checkbox') {
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = inputClass;
                input.checked = value;
                input.onchange = () => updateConfigValue(key, input.checked);
                return input;
            } else if (fieldType === 'select') {
                const select = document.createElement('select');
                select.className = inputClass;
                select.innerHTML = getSelectOptions(key);
                select.value = value;
                select.onchange = () => updateConfigValue(key, fieldType === 'number' ? parseInt(select.value) : select.value);
                return select;
            } else if (fieldType === 'bounds') {
                const container = document.createElement('div');
                container.className = 'bounds-container';
                
                // Create map container
                const mapContainer = document.createElement('div');
                mapContainer.id = 'bounds-map-' + key;
                mapContainer.style.height = '400px';
                mapContainer.style.width = '100%';
                mapContainer.style.border = '2px solid #dee2e6';
                mapContainer.style.borderRadius = '8px';
                mapContainer.style.marginBottom = '15px';
                
                // Create bounds display
                const boundsDisplay = document.createElement('div');
                boundsDisplay.className = 'bounds-display';
                
                const coordsText = document.createElement('div');
                coordsText.className = 'bounds-coords';
                coordsText.style.fontFamily = 'monospace';
                coordsText.style.padding = '10px';
                coordsText.style.backgroundColor = '#f8f9fa';
                coordsText.style.border = '1px solid #dee2e6';
                coordsText.style.borderRadius = '4px';
                coordsText.style.marginBottom = '10px';
                
                const updateCoordsDisplay = (bounds) => {
                    if (bounds && bounds.north) {
                        coordsText.innerHTML = '📍 <strong>' + getTranslation('geographic_bounds') + ':</strong><br>' +
                            'North: ' + bounds.north.toFixed(6) + ' | South: ' + bounds.south.toFixed(6) + '<br>' +
                            'East: ' + bounds.east.toFixed(6) + ' | West: ' + bounds.west.toFixed(6);
                    } else {
                        coordsText.innerHTML = '<em>🌍 ' + getTranslation('no_bounds_set') + '</em>';
                    }
                };
                
                updateCoordsDisplay(value);
                
                // Add map instructions
                const mapInstructions = document.createElement('div');
                mapInstructions.style.padding = '10px';
                mapInstructions.style.backgroundColor = '#e3f2fd';
                mapInstructions.style.border = '1px solid #90caf9';
                mapInstructions.style.borderRadius = '4px';
                mapInstructions.style.marginBottom = '10px';
                mapInstructions.innerHTML = '🗺️ <strong>' + getTranslation('map_instructions') + '</strong>';
                
                // Buttons for preset bounds
                const presetsContainer = document.createElement('div');
                presetsContainer.className = 'bounds-presets';
                presetsContainer.style.marginBottom = '10px';
                
                const createPresetButton = (label, bounds) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-primary btn-sm';
                    btn.style.marginRight = '5px';
                    btn.style.marginBottom = '5px';
                    btn.textContent = label;
                    btn.onclick = () => {
                        updateConfigValue(key, bounds);
                        updateCoordsDisplay(bounds);
                        // Update manual input fields
                        const inputs = manualContainer.querySelectorAll('input[type="number"]');
                        if (inputs.length === 4 && bounds) {
                            inputs[0].value = bounds.north;
                            inputs[1].value = bounds.south;
                            inputs[2].value = bounds.east;
                            inputs[3].value = bounds.west;
                        }
                        // Update map rectangle
                        if (window['boundsMap_' + key]) {
                            window['boundsMap_' + key].setRectangle(bounds);
                        }
                    };
                    return btn;
                };
                
                // Attica bounds (Athens region)
                const atticaBounds = {
                    north: 38.35,
                    south: 37.70, 
                    east: 24.15,
                    west: 23.25
                };
                
                // Thessaloniki bounds (Central Macedonia)
                const thessalonikiBounds = {
                    north: 40.85,
                    south: 40.45,
                    east: 23.25,
                    west: 22.65
                };
                
                presetsContainer.appendChild(createPresetButton('🏛️ Set Attica Bounds', atticaBounds));
                presetsContainer.appendChild(createPresetButton('🌆 Set Thessaloniki Bounds', thessalonikiBounds));
                
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.className = 'btn btn-outline-secondary btn-sm';
                clearBtn.textContent = '🚫 Clear Bounds';
                clearBtn.onclick = () => {
                    updateConfigValue(key, null);
                    updateCoordsDisplay(null);
                    // Clear manual input fields
                    const inputs = manualContainer.querySelectorAll('input[type="number"]');
                    inputs.forEach(input => input.value = '');
                    // Clear map rectangle
                    if (window['boundsMap_' + key]) {
                        window['boundsMap_' + key].clearRectangle();
                    }
                };
                
                presetsContainer.appendChild(clearBtn);
                
                // Manual input fields
                const manualContainer = document.createElement('div');
                manualContainer.className = 'bounds-manual';
                manualContainer.style.marginTop = '15px';
                manualContainer.style.padding = '10px';
                manualContainer.style.border = '1px solid #dee2e6';
                manualContainer.style.borderRadius = '4px';
                manualContainer.style.backgroundColor = '#fafafa';
                
                const manualTitle = document.createElement('h6');
                manualTitle.textContent = '✏️ Manual Bounds Entry';
                manualTitle.style.marginBottom = '10px';
                
                const coordsGrid = document.createElement('div');
                coordsGrid.style.display = 'grid';
                coordsGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                coordsGrid.style.gap = '10px';
                
                const createCoordInput = (label, field) => {
                    const wrapper = document.createElement('div');
                    const labelEl = document.createElement('label');
                    labelEl.textContent = label;
                    labelEl.style.fontSize = '12px';
                    labelEl.style.fontWeight = 'bold';
                    
                    const input = document.createElement('input');
                    input.type = 'number';
                    input.step = '0.000001';
                    input.className = 'form-input';
                    input.style.fontSize = '12px';
                    input.value = value && value[field] ? value[field] : '';
                    input.placeholder = `${label} coordinate`;
                    
                    input.onchange = () => {
                        // Get current bounds from config
                        const currentBounds = currentConfigs[selectedExtension][key] || {};
                        
                        if (input.value === '') {
                            // If clearing a field, set entire bounds to null
                            updateConfigValue(key, null);
                            updateCoordsDisplay(null);
                            // Clear other inputs
                            const otherInputs = manualContainer.querySelectorAll('input[type="number"]');
                            otherInputs.forEach(inp => {
                                if (inp !== input) inp.value = '';
                            });
                        } else {
                            // Update the specific coordinate
                            const newBounds = { ...currentBounds };
                            newBounds[field] = parseFloat(input.value);
                            
                            // Only update if all 4 coordinates are filled
                            if (newBounds.north !== undefined && newBounds.south !== undefined && 
                                newBounds.east !== undefined && newBounds.west !== undefined) {
                                updateConfigValue(key, newBounds);
                                updateCoordsDisplay(newBounds);
                            }
                        }
                    };
                    
                    wrapper.appendChild(labelEl);
                    wrapper.appendChild(input);
                    return wrapper;
                };
                
                coordsGrid.appendChild(createCoordInput('North', 'north'));
                coordsGrid.appendChild(createCoordInput('South', 'south'));
                coordsGrid.appendChild(createCoordInput('East', 'east'));
                coordsGrid.appendChild(createCoordInput('West', 'west'));
                
                manualContainer.appendChild(manualTitle);
                manualContainer.appendChild(coordsGrid);
                
                boundsDisplay.appendChild(coordsText);
                
                // Assemble the container
                container.appendChild(mapInstructions);
                container.appendChild(mapContainer);
                container.appendChild(boundsDisplay);
                container.appendChild(presetsContainer);
                container.appendChild(manualContainer);
                
                // Initialize map after DOM is ready
                setTimeout(() => {
                    initializeBoundsMap(mapContainer.id, key, value, updateCoordsDisplay, manualContainer);
                }, 100);
                
                return container;
            } else if (fieldType === 'centerBias') {
                const container = document.createElement('div');
                container.className = 'center-bias-container';
                
                // Create display
                const display = document.createElement('div');
                display.className = 'center-bias-display';
                display.style.fontFamily = 'monospace';
                display.style.padding = '10px';
                display.style.backgroundColor = '#f8f9fa';
                display.style.border = '1px solid #dee2e6';
                display.style.borderRadius = '4px';
                display.style.marginBottom = '15px';
                
                const updateCoordsDisplay = (centerBias) => {
                    if (centerBias && centerBias.lat) {
                        display.innerHTML = '🎯 <strong>' + getTranslation('center_bias') + ':</strong><br>' +
                            'Latitude: ' + centerBias.lat.toFixed(6) + ' | Longitude: ' + centerBias.lng.toFixed(6) + '<br>' +
                            'Radius: ' + centerBias.radius + ' meters';
                    } else {
                        display.innerHTML = '<em>🌐 ' + getTranslation('no_center_bias') + '</em>';
                    }
                };
                
                updateCoordsDisplay(value);
                
                // Preset buttons
                const presetsContainer = document.createElement('div');
                presetsContainer.style.marginBottom = '15px';
                
                const createPresetButton = (label, centerBias) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-primary btn-sm';
                    btn.style.marginRight = '5px';
                    btn.style.marginBottom = '5px';
                    btn.textContent = label;
                    btn.onclick = () => {
                        updateConfigValue(key, centerBias);
                        updateCoordsDisplay(centerBias);
                        // Update manual inputs
                        const inputs = manualContainer.querySelectorAll('input[type="number"]');
                        if (inputs.length === 3 && centerBias) {
                            inputs[0].value = centerBias.lat;
                            inputs[1].value = centerBias.lng;
                            inputs[2].value = centerBias.radius;
                        }
                        // Update map if available
                        if (window.centerBiasMaps && window.centerBiasMaps[key]) {
                            window.centerBiasMaps[key].updateFromData(centerBias);
                        }
                    };
                    return btn;
                };
                
                // Athens center (Syntagma Square)
                const athensCenter = { lat: 37.9755, lng: 23.7348, radius: 25000 };
                // Thessaloniki center (White Tower)  
                const thessalonikiCenter = { lat: 40.6264, lng: 22.9481, radius: 15000 };
                
                presetsContainer.appendChild(createPresetButton('🏛️ Athens Center (25km)', athensCenter));
                presetsContainer.appendChild(createPresetButton('🌆 Thessaloniki Center (15km)', thessalonikiCenter));
                
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.className = 'btn btn-outline-secondary btn-sm';
                clearBtn.textContent = '🚫 Clear Center Bias';
                clearBtn.onclick = () => {
                    updateConfigValue(key, null);
                    updateCoordsDisplay(null);
                    const inputs = manualContainer.querySelectorAll('input[type="number"]');
                    inputs.forEach(input => input.value = '');
                    // Update map if available
                    if (window.centerBiasMaps && window.centerBiasMaps[key]) {
                        window.centerBiasMaps[key].updateFromData(null);
                    }
                };
                
                presetsContainer.appendChild(clearBtn);
                
                // Map container
                const mapContainer = document.createElement('div');
                mapContainer.className = 'center-bias-map';
                mapContainer.id = 'centerBiasMap_' + key + '_' + Date.now();
                mapContainer.style.height = '350px';
                mapContainer.style.border = '1px solid #dee2e6';
                mapContainer.style.borderRadius = '4px';
                mapContainer.style.marginBottom = '15px';
                
                // Manual input fields
                const manualContainer = document.createElement('div');
                manualContainer.className = 'center-bias-manual';
                manualContainer.style.padding = '10px';
                manualContainer.style.border = '1px solid #dee2e6';
                manualContainer.style.borderRadius = '4px';
                manualContainer.style.backgroundColor = '#fafafa';
                
                const manualTitle = document.createElement('h6');
                manualTitle.textContent = '✏️ Manual Center Bias Entry';
                manualTitle.style.marginBottom = '10px';
                
                const coordsGrid = document.createElement('div');
                coordsGrid.style.display = 'grid';
                coordsGrid.style.gridTemplateColumns = 'repeat(3, 1fr)';
                coordsGrid.style.gap = '10px';
                
                const createCoordInput = (label, field) => {
                    const wrapper = document.createElement('div');
                    const labelEl = document.createElement('label');
                    labelEl.textContent = label;
                    labelEl.style.fontSize = '12px';
                    labelEl.style.fontWeight = 'bold';
                    
                    const input = document.createElement('input');
                    input.type = 'number';
                    if (field === 'radius') {
                        input.step = '1000';
                        input.placeholder = 'meters';
                    } else {
                        input.step = '0.000001';
                        input.placeholder = 'decimal degrees';
                    }
                    input.className = 'form-input';
                    input.style.fontSize = '12px';
                    input.value = value && value[field] ? value[field] : '';
                    
                    input.onchange = () => {
                        const currentCenterBias = currentConfigs[selectedExtension][key] || {};
                        
                        if (input.value === '') {
                            updateConfigValue(key, null);
                            updateCoordsDisplay(null);
                            const otherInputs = manualContainer.querySelectorAll('input[type="number"]');
                            otherInputs.forEach(inp => {
                                if (inp !== input) inp.value = '';
                            });
                            // Update map
                            if (window.centerBiasMaps && window.centerBiasMaps[key]) {
                                window.centerBiasMaps[key].updateFromData(null);
                            }
                        } else {
                            const newCenterBias = { ...currentCenterBias };
                            newCenterBias[field] = field === 'radius' ? parseInt(input.value) : parseFloat(input.value);
                            
                            if (newCenterBias.lat !== undefined && newCenterBias.lng !== undefined && 
                                newCenterBias.radius !== undefined) {
                                updateConfigValue(key, newCenterBias);
                                updateCoordsDisplay(newCenterBias);
                                // Update map - use more efficient updateRadius if only radius changed
                                if (window.centerBiasMaps && window.centerBiasMaps[key]) {
                                    if (field === 'radius' && currentCenterBias.lat === newCenterBias.lat && 
                                        currentCenterBias.lng === newCenterBias.lng) {
                                        window.centerBiasMaps[key].updateRadius(newCenterBias.radius);
                                    } else {
                                        window.centerBiasMaps[key].updateFromData(newCenterBias);
                                    }
                                }
                            }
                        }
                    };
                    
                    wrapper.appendChild(labelEl);
                    wrapper.appendChild(input);
                    return wrapper;
                };
                
                coordsGrid.appendChild(createCoordInput('Latitude', 'lat'));
                coordsGrid.appendChild(createCoordInput('Longitude', 'lng'));
                coordsGrid.appendChild(createCoordInput('Radius (m)', 'radius'));
                
                manualContainer.appendChild(manualTitle);
                manualContainer.appendChild(coordsGrid);
                
                container.appendChild(display);
                container.appendChild(presetsContainer);
                container.appendChild(mapContainer);
                container.appendChild(manualContainer);
                
                // Initialize map after DOM is ready
                setTimeout(() => {
                    initializeCenterBiasMap(mapContainer.id, key, value, updateCoordsDisplay, manualContainer);
                }, 100);
                
                return container;
            } else if (fieldType === 'array') {
                const container = document.createElement('div');
                container.className = 'tags-container';
                
                const tagsWrapper = document.createElement('div');
                tagsWrapper.className = 'tags-wrapper';
                
                const input = document.createElement('input');
                input.type = 'text';
                input.className = inputClass + ' tag-input';
                input.placeholder = 'Enter area name and press Enter (e.g., Attica)';
                
                // Add missing key styling if needed
                if (isMissingKey) {
                    tagsWrapper.classList.add('missing-key');
                }
                
                // Add tag on Enter
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const tagValue = input.value.trim();
                        const currentValue = currentExtensionConfig[key] || [];
                        if (tagValue && !currentValue.includes(tagValue)) {
                            const newValue = [...currentValue, tagValue];
                            updateConfigValue(key, newValue);
                            input.value = '';
                        }
                    }
                });
                
                // Add input to wrapper first
                tagsWrapper.appendChild(input);
                
                // Render existing tags
                const renderTags = () => {
                    const existingTags = tagsWrapper.querySelectorAll('.tag');
                    existingTags.forEach(tag => tag.remove());
                    
                    const currentValue = currentExtensionConfig[key] || [];
                    if (Array.isArray(currentValue)) {
                        currentValue.forEach((tag, index) => {
                            const tagElement = document.createElement('span');
                            tagElement.className = 'tag';
                            tagElement.innerHTML = `
                                ${tag}
                                <button type="button" class="tag-remove" onclick="removeTag('${key}', ${index})">×</button>
                            `;
                            tagsWrapper.insertBefore(tagElement, input);
                        });
                    }
                };
                
                // Now render tags
                renderTags();
                container.appendChild(tagsWrapper);
                
                // Store render function for external use
                container.renderTags = renderTags;
                
                return container;
            } else if (key === 'soundPath') {
                // Special handling for soundPath - add sound browser button
                const container = document.createElement('div');
                container.style.display = 'flex';
                container.style.gap = '0.5rem';
                container.style.alignItems = 'center';
                
                const input = document.createElement('input');
                input.type = fieldType;
                input.className = inputClass;
                input.value = value;
                input.onchange = () => updateConfigValue(key, input.value);
                
                const browseButton = document.createElement('button');
                browseButton.type = 'button';
                browseButton.className = 'btn btn-outline-primary btn-sm';
                browseButton.textContent = '🎵';
                browseButton.title = getTranslation('browse_sounds') || 'Browse Sounds';
                browseButton.onclick = () => openSoundBrowser(input.value);
                
                // Only show button if path is valid/not empty
                if (value && value.trim()) {
                    browseButton.style.display = 'inline-block';
                } else {
                    browseButton.style.display = 'none';
                }
                
                // Update button visibility when input changes
                input.addEventListener('input', () => {
                    if (input.value && input.value.trim()) {
                        browseButton.style.display = 'inline-block';
                    } else {
                        browseButton.style.display = 'none';
                    }
                });
                
                container.appendChild(input);
                container.appendChild(browseButton);
                return container;
            } else if (key === 'initialMessageSound') {
                // Special handling for initialMessageSound - add sound browser button
                const container = document.createElement('div');
                container.style.display = 'flex';
                container.style.gap = '0.5rem';
                container.style.alignItems = 'center';
                
                const input = document.createElement('input');
                input.type = fieldType;
                input.className = inputClass;
                input.value = value;
                input.onchange = () => updateConfigValue(key, input.value);
                
                const browseButton = document.createElement('button');
                browseButton.type = 'button';
                browseButton.className = 'btn btn-outline-primary btn-sm';
                browseButton.textContent = '🎵';
                browseButton.title = getTranslation('browse_sounds') || 'Browse Sounds';
                browseButton.onclick = () => {
                    // Get soundPath from current config to browse sounds
                    const soundPath = currentConfigs[selectedExtension]?.soundPath || '/var/sounds/iqtaxi';
                    openSoundBrowser(soundPath);
                };
                
                // Always show button for initialMessageSound (unlike soundPath)
                browseButton.style.display = 'inline-block';
                
                container.appendChild(input);
                container.appendChild(browseButton);
                return container;
            } else {
                const input = document.createElement('input');
                input.type = fieldType;
                input.className = inputClass;
                input.value = value;
                input.onchange = () => updateConfigValue(key, fieldType === 'number' ? parseInt(input.value) : input.value);
                return input;
            }
        }
        
        function getFieldType(key, value) {
            if (typeof value === 'boolean') return 'checkbox';
            if (key === 'tts') return 'select';
            if (key === 'defaultLanguage') return 'select';
            if (key === 'callbackMode') return 'select';
            if (key === 'confirmation_mode') return 'select';
            if (key === 'geocodingApiVersion') return 'select';
            if (key === 'autoCallCentersMode') return 'select';
            if (key === 'boundsRestrictionMode') return 'select';
            if (key === 'bounds') return 'bounds';
            if (key === 'centerBias') return 'centerBias';
            if (key === 'getUser_enabled') return 'checkbox';
            if (key === 'customFallCallTo') return 'checkbox';
            if (typeof value === 'number') return 'number';
            return 'text';
        }
        
        function getSelectOptions(key) {
            switch(key) {
                case 'tts':
                    return '<option value="google">Google</option><option value="edge-tts">Edge TTS</option>';
                case 'defaultLanguage':
                    return '<option value="el">Greek (el)</option><option value="en">English (en)</option><option value="bg">Bulgarian (bg)</option>';
                case 'callbackMode':
                    return `<option value="1">1 - ${getTranslation('select_normal_mode')}</option><option value="2">2 - ${getTranslation('select_callback_mode')}</option>`;
                case 'confirmation_mode':
                    return `<option value="1">1 - ${getTranslation('select_confirmation_mode_1')}</option><option value="2">2 - ${getTranslation('select_confirmation_mode_2')}</option>`;
                case 'geocodingApiVersion':
                    return `<option value="1">1 - ${getTranslation('select_geocoding_api')}</option><option value="2">2 - ${getTranslation('select_places_api')}</option>`;
                case 'autoCallCentersMode':
                    return `<option value="0">0 - ${getTranslation('select_all_disabled_operator')}</option><option value="1">1 - ${getTranslation('select_asap_calls_only')}</option><option value="2">2 - ${getTranslation('select_reservations_only')}</option><option value="3">3 - ${getTranslation('select_all_enabled')}</option>`;
                case 'boundsRestrictionMode':
                    return `<option value="">None - ${getTranslation('select_bounds_none')}</option><option value="1">1 - ${getTranslation('select_bounds_pickup_only')}</option><option value="2">2 - ${getTranslation('select_bounds_dropoff_only')}</option><option value="3">3 - ${getTranslation('select_bounds_both')}</option>`;
                case 'bounds':
                    return ''; // Bounds will be handled by map interface
                case 'centerBias':
                    return ''; // CenterBias will be handled by custom interface
                default:
                    return '';
            }
        }
        
        function updateConfigValue(key, value) {
            currentExtensionConfig[key] = value;
            // Re-render tags if this is an array field
            const container = document.querySelector(`[data-key="${key}"] .tags-container`);
            if (container && container.renderTags) {
                container.renderTags();
            }
        }
        
        function removeTag(key, index) {
            const currentValue = currentExtensionConfig[key] || [];
            if (Array.isArray(currentValue) && index >= 0 && index < currentValue.length) {
                const newValue = [...currentValue];
                newValue.splice(index, 1);
                updateConfigValue(key, newValue);
            }
        }
        
        function saveConfiguration() {
            // Check if we're saving missing keys
            const originalConfig = currentConfigs[selectedExtension];
            const missingKeys = getMissingKeys(originalConfig);
            const hasMissingKeys = missingKeys.length > 0;
            
            fetch('config_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_extension&extension=${selectedExtension}&config=${encodeURIComponent(JSON.stringify(currentExtensionConfig))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentConfigs[selectedExtension] = currentExtensionConfig;
                    
                    // Show appropriate success message
                    if (hasMissingKeys) {
                        showAlert(getTranslation('config_saved') + ' ' + getTranslation('missing_keys_applied'), 'success');
                        // Hide the missing keys info box after saving
                        document.getElementById('missing-keys-info').style.display = 'none';
                    } else {
                        showAlert(getTranslation('config_saved'), 'success');
                    }
                } else {
                    showAlert(getTranslation('config_error'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert(getTranslation('config_error'), 'danger');
            });
        }
        
        function goBack() {
            document.getElementById('selection-screen').style.display = 'block';
            document.getElementById('edit-screen').style.display = 'none';
            document.getElementById('add-extension-form').style.display = 'none';

            // Restore original page title using the same translation as the h1 element
            const titleElement = document.querySelector('h1[data-en][data-el]');
            const originalTitle = titleElement ?
                titleElement.getAttribute('data-' + currentLanguage) :
                (currentLanguage === 'el' ? 'Διαχειριστής Ρυθμίσεων AGI' : 'AGI Configuration Manager');
            document.title = originalTitle;

            // Refresh the selection screen
            initializeScreen();
        }
        
        function showAddExtensionForm() {
            document.getElementById('selection-screen').style.display = 'none';
            document.getElementById('edit-screen').style.display = 'none';
            document.getElementById('add-extension-form').style.display = 'block';
        }
        
        function cancelAdd() {
            document.getElementById('new-extension-number').value = '';
            document.getElementById('new-extension-name').value = '';
            
            document.getElementById('selection-screen').style.display = 'block';
            document.getElementById('edit-screen').style.display = 'none';
            document.getElementById('add-extension-form').style.display = 'none';
            
            initializeScreen();
        }
        
        function createExtension() {
            const extension = document.getElementById('new-extension-number').value.trim();
            const name = document.getElementById('new-extension-name').value.trim();
            
            if (!extension) {
                showAlert(getTranslation('extension_number_required'), 'danger');
                return;
            }
            
            if (currentConfigs[extension]) {
                showAlert(getTranslation('extension_exists'), 'danger');
                return;
            }
            
            const newConfig = {
                name: name,
                googleApiKey: "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
                clientToken: "",
                registerBaseUrl: "",
                failCallTo: "",
                soundPath: "/var/sounds/iqtaxi",
                tts: "google",
                daysValid: 7,
                defaultLanguage: "el",
                callbackMode: 1,
                callbackUrl: "",
                repeatTimes: 10,
                strictDropoffLocation: false,
                geocodingApiVersion: 1,
                initialMessageSound: "",
                redirectToOperator: false,
                autoCallCentersMode: 3,
                maxRetries: 5,
                bounds: null,
            centerBias: null,
            boundsRestrictionMode: null,
            customFallCallTo: false,
            customFallCallToURL: "https://www.iqtaxi.com/IQ_WebApiV3/api/asterisk/GetRedirectDrvPhoneFull/"
            };
            
            fetch('config_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_extension&extension=${extension}&config=${encodeURIComponent(JSON.stringify(newConfig))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentConfigs[extension] = newConfig;
                    showAlert(getTranslation('extension_added'), 'success');
                    
                    // Clear form
                    document.getElementById('new-extension-number').value = '';
                    document.getElementById('new-extension-name').value = '';
                    
                    // Go to edit the new extension
                    selectExtension(extension);
                } else {
                    showAlert(getTranslation('config_error'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert(getTranslation('config_error'), 'danger');
            });
        }
        
        // Export/Import Functions
        function showExportModal() {
            if (Object.keys(currentConfigs).length === 0) {
                showAlert(getTranslation('no_data'), 'danger');
                return;
            }
            
            fetch('config_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=export_config'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('exportData').value = data.data;
                    document.getElementById('exportModal').style.display = 'block';
                    showAlert(getTranslation('export_success'), 'success');
                } else {
                    showAlert(getTranslation('config_error'), 'danger');
                }
            });
        }
        
        function showImportModal() {
            document.getElementById('importData').value = '';
            document.getElementById('configFile').value = '';
            document.getElementById('importModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function copyToClipboard() {
            const exportData = document.getElementById('exportData');
            exportData.select();
            exportData.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                showAlert(getTranslation('copy_success'), 'success');
            } catch (err) {
                showAlert(getTranslation('copy_error'), 'danger');
            }
        }
        
        function downloadConfig() {
            const exportData = document.getElementById('exportData').value;
            const blob = new Blob([exportData], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'agi_config_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        function loadConfigFile() {
            const file = document.getElementById('configFile').files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('importData').value = e.target.result;
                };
                reader.readAsText(file);
            }
        }
        
        function importConfiguration() {
            const configData = document.getElementById('importData').value.trim();
            
            if (!configData) {
                showAlert(getTranslation('import_error'), 'danger');
                return;
            }
            
            fetch('config_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=import_config&configData=${encodeURIComponent(configData)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(getTranslation('import_success'), 'success');
                    closeModal('importModal');
                    // Reload the page to refresh with new config
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert(getTranslation('import_error'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert(getTranslation('import_error'), 'danger');
            });
        }
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            initializeScreen();
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            };
        });
        
        // Sound Browser Functions
        let currentSoundPath = '';
        let currentSoundLanguage = 'el';
        let currentAudio = null;
        
        function openSoundBrowser(soundPath) {
            if (!soundPath || !soundPath.trim()) {
                showAlert(getTranslation('invalid_sound_path'), 'error');
                return;
            }
            
            currentSoundPath = soundPath;
            document.getElementById('soundBrowserModal').style.display = 'block';
            loadSounds();
            
            // Update translatable elements
            document.querySelectorAll('[data-en][data-el]').forEach(element => {
                element.textContent = element.getAttribute('data-' + currentLanguage);
            });
        }
        
        function switchSoundTab(language) {
            currentSoundLanguage = language;
            
            // Update active tab
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById('sound-tab-' + language).classList.add('active');
            
            // Reload sounds for selected language
            loadSounds();
        }
        
        function loadSounds() {
            const soundsList = document.getElementById('soundsList');
            soundsList.innerHTML = '<div style="text-align: center; padding: 2rem;">' + getTranslation('loading_sounds') + '</div>';
            
            fetch('config_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=list_sounds&soundPath=${encodeURIComponent(currentSoundPath)}&language=${currentSoundLanguage}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySounds(data.sounds, data.debug);
                } else {
                    soundsList.innerHTML = `<div style="text-align: center; padding: 2rem; color: #666;">${data.message || getTranslation('no_sounds_found')}</div>`;
                }
            })
            .catch(error => {
                console.error('Error loading sounds:', error);
                soundsList.innerHTML = `<div style="text-align: center; padding: 2rem; color: #cc0000;">` + getTranslation('error_loading_sounds') + `</div>`;
            });
        }
        
        function displaySounds(sounds, debug) {
            const soundsList = document.getElementById('soundsList');
            
            if (!sounds || sounds.length === 0) {
                let debugHtml = '';
                if (debug) {
                    debugHtml = `
                        <div style="margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 4px; text-align: left; font-family: monospace; font-size: 0.8rem;">
                            <strong>Debug Information:</strong><br>
                            Path: ${debug.soundPath}<br>
                            Language: ${debug.language}<br>
                            Directory readable: ${debug.is_readable ? 'Yes' : 'No'}<br>
                            Directory writable: ${debug.is_writable ? 'Yes' : 'No'}<br>
                            Files found: ${debug.total_files_found}<br>
                            <br><strong>Search patterns:</strong><br>
                            ${debug.search_patterns.map(pattern => `• ${pattern}`).join('<br>')}
                            <br><br><strong>Results:</strong><br>
                            ${debug.pattern_results.length > 0 ? debug.pattern_results.join('<br>') : 'No files found with any pattern'}
                        </div>
                    `;
                }
                
                soundsList.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: #666;">
                        ${getTranslation('no_sounds_found')}
                        ${debugHtml}
                    </div>`;
                return;
            }
            
            soundsList.innerHTML = sounds.map(sound => `
                <div class="sound-item" id="sound-${sound.name}">
                    <div class="sound-name">${sound.name}</div>
                    <div class="sound-controls">
                        <button class="btn btn-outline-primary btn-sm" onclick="playSound('${sound.path}', '${sound.name}')" id="play-${sound.name}">
                            ${getTranslation('play_sound')}
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="selectSound('${sound.name}')">
                            ${getTranslation('select_sound')}
                        </button>
                    </div>
                </div>
            `).join('');
        }
        
        function playSound(soundPath, soundName) {
            const playButton = document.getElementById('play-' + soundName);
            const soundItem = document.getElementById('sound-' + soundName);
            
            // Stop any currently playing audio
            if (currentAudio) {
                currentAudio.pause();
                currentAudio.currentTime = 0;
                
                // Reset all play buttons and sound items
                document.querySelectorAll('.sound-item').forEach(item => {
                    item.classList.remove('sound-playing');
                });
                document.querySelectorAll('[id^="play-"]').forEach(btn => {
                    btn.textContent = getTranslation('play_sound');
                    btn.classList.remove('btn-warning');
                    btn.classList.add('btn-outline-primary');
                });
            }
            
            // If clicking the same sound that was playing, just stop it
            if (currentAudio && currentAudio.src.includes(soundName)) {
                currentAudio = null;
                return;
            }
            
            // Create and play new audio using PHP endpoint
            const audioUrl = `config_manager.php?action=serve_audio&soundPath=${encodeURIComponent(soundPath)}&soundFile=${encodeURIComponent(soundName)}`;
            currentAudio = new Audio(audioUrl);
            
            currentAudio.addEventListener('loadstart', () => {
                playButton.textContent = getTranslation('loading');
            });
            
            currentAudio.addEventListener('canplay', () => {
                soundItem.classList.add('sound-playing');
                playButton.textContent = getTranslation('stop_sound');
                playButton.classList.remove('btn-outline-primary');
                playButton.classList.add('btn-warning');
                currentAudio.play();
            });
            
            currentAudio.addEventListener('ended', () => {
                soundItem.classList.remove('sound-playing');
                playButton.textContent = getTranslation('play_sound');
                playButton.classList.remove('btn-warning');
                playButton.classList.add('btn-outline-primary');
                currentAudio = null;
            });
            
            currentAudio.addEventListener('error', (e) => {
                soundItem.classList.remove('sound-playing');
                playButton.textContent = getTranslation('play_sound');
                playButton.classList.remove('btn-warning');
                playButton.classList.add('btn-outline-primary');
                
                // More detailed error message
                let errorMsg = getTranslation('error_playing_sound');
                if (currentAudio.error) {
                    switch(currentAudio.error.code) {
                        case MediaError.MEDIA_ERR_NETWORK:
                            errorMsg = getTranslation('media_error_network');
                            break;
                        case MediaError.MEDIA_ERR_DECODE:
                            errorMsg = getTranslation('media_error_decode');
                            break;
                        case MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED:
                            errorMsg = getTranslation('media_error_not_supported');
                            break;
                        default:
                            errorMsg = getTranslation('error_playing_sound');
                    }
                }
                
                showAlert(errorMsg, 'error');
                console.error('Audio error:', currentAudio.error, 'URL:', audioUrl);
                currentAudio = null;
            });
        }
        
        function selectSound(soundName) {
            // Find the soundPath input and update its value
            const soundPathInputs = document.querySelectorAll('input[type="text"]');
            let soundPathInput = null;
            
            soundPathInputs.forEach(input => {
                if (input.value === currentSoundPath) {
                    soundPathInput = input;
                }
            });
            
            if (soundPathInput) {
                // Extract base name without language suffix and extension
                // e.g., "test_el.wav" becomes "test"
                let baseName = soundName;
                baseName = baseName.replace(/\.(wav|mp3|ogg)$/i, ''); // Remove extension
                baseName = baseName.replace(/_[a-z]{2}$/i, ''); // Remove language suffix (_el, _en, etc.)
                
                // Update the initialMessageSound field instead of soundPath
                const initialMessageSoundInputs = document.querySelectorAll('input[type="text"]');
                initialMessageSoundInputs.forEach(input => {
                    const label = input.closest('.form-group')?.querySelector('label');
                    if (label && label.textContent.includes(getTranslation('initialMessageSound'))) {
                        input.value = baseName;
                        updateConfigValue('initialMessageSound', baseName);
                    }
                });
            }
            
            closeModal('soundBrowserModal');
            showAlert(`Selected sound: ${baseName}`, 'success');
        }
        
        function uploadSound() {
            const soundFile = document.getElementById('soundFile').files[0];
            const soundName = document.getElementById('newSoundName').value.trim();
            
            if (!soundFile) {
                showAlert(getTranslation('choose_sound_file'), 'error');
                return;
            }
            
            if (!soundName) {
                showAlert(getTranslation('sound_name'), 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_sound');
            formData.append('soundFile', soundFile);
            formData.append('soundName', soundName);
            formData.append('soundPath', currentSoundPath);
            formData.append('language', currentSoundLanguage);
            
            // Show loading state
            const uploadButton = document.querySelector('button[onclick="uploadSound()"]');
            const originalText = uploadButton.textContent;
            uploadButton.textContent = 'Uploading...';
            uploadButton.disabled = true;
            
            fetch('config_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const memoryMessage = `${getTranslation('upload_success')}\nFile stored in memory as: ${data.filename}`;
                    showAlert(memoryMessage, 'success');
                    document.getElementById('newSoundName').value = '';
                    document.getElementById('soundFile').value = '';
                    loadSounds(); // Reload the sounds list
                } else {
                    showAlert(data.message || getTranslation('upload_error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error uploading sound:', error);
                showAlert(getTranslation('upload_error'), 'error');
            })
            .finally(() => {
                uploadButton.textContent = originalText;
                uploadButton.disabled = false;
            });
        }
        
        // Map initialization function for bounds
        function initializeBoundsMap(mapId, key, initialBounds, updateCoordsDisplay, manualContainer) {
            // Initialize the map centered on Greece
            const map = L.map(mapId).setView([38.5, 23.7], 6);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            let currentRectangle = null;
            
            // Function to update bounds from rectangle
            const updateBoundsFromRectangle = (bounds) => {
                const newBounds = {
                    north: bounds.getNorth(),
                    south: bounds.getSouth(),
                    east: bounds.getEast(),
                    west: bounds.getWest()
                };
                
                // Update config value
                updateConfigValue(key, newBounds);
                updateCoordsDisplay(newBounds);
                
                // Update manual input fields
                const inputs = manualContainer.querySelectorAll('input[type="number"]');
                if (inputs.length === 4) {
                    inputs[0].value = newBounds.north.toFixed(6);
                    inputs[1].value = newBounds.south.toFixed(6);
                    inputs[2].value = newBounds.east.toFixed(6);
                    inputs[3].value = newBounds.west.toFixed(6);
                }
            };
            
            // If there are initial bounds, draw them
            if (initialBounds && initialBounds.north) {
                const bounds = [[initialBounds.south, initialBounds.west], [initialBounds.north, initialBounds.east]];
                currentRectangle = L.rectangle(bounds, {
                    color: '#2196F3',
                    weight: 2,
                    fillOpacity: 0.2
                }).addTo(map);
                
                map.fitBounds(bounds);
            }
            
            // Add drawing controls
            const drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);
            
            const drawControl = new L.Control.Draw({
                draw: {
                    polygon: false,
                    polyline: false,
                    circle: false,
                    marker: false,
                    circlemarker: false,
                    rectangle: {
                        shapeOptions: {
                            color: '#2196F3',
                            weight: 2,
                            fillOpacity: 0.2
                        }
                    }
                },
                edit: {
                    featureGroup: drawnItems,
                    remove: true
                }
            });
            map.addControl(drawControl);
            
            // Handle rectangle creation
            map.on(L.Draw.Event.CREATED, function(e) {
                const layer = e.layer;
                
                // Remove existing rectangle
                if (currentRectangle) {
                    map.removeLayer(currentRectangle);
                }
                
                currentRectangle = layer;
                drawnItems.addLayer(layer);
                
                updateBoundsFromRectangle(layer.getBounds());
            });
            
            // Handle rectangle editing
            map.on(L.Draw.Event.EDITED, function(e) {
                e.layers.eachLayer(function(layer) {
                    updateBoundsFromRectangle(layer.getBounds());
                });
            });
            
            // Handle rectangle deletion
            map.on(L.Draw.Event.DELETED, function(e) {
                currentRectangle = null;
                updateConfigValue(key, null);
                updateCoordsDisplay(null);
                
                // Clear manual inputs
                const inputs = manualContainer.querySelectorAll('input[type="number"]');
                inputs.forEach(input => input.value = '');
            });
            
            // Store map reference for preset buttons
            window['boundsMap_' + key] = {
                map: map,
                setRectangle: function(bounds) {
                    // Remove existing rectangle
                    if (currentRectangle) {
                        map.removeLayer(currentRectangle);
                        drawnItems.removeLayer(currentRectangle);
                    }
                    
                    // Create new rectangle
                    const leafletBounds = [[bounds.south, bounds.west], [bounds.north, bounds.east]];
                    currentRectangle = L.rectangle(leafletBounds, {
                        color: '#2196F3',
                        weight: 2,
                        fillOpacity: 0.2
                    }).addTo(map);
                    
                    drawnItems.addLayer(currentRectangle);
                    map.fitBounds(leafletBounds);
                },
                clearRectangle: function() {
                    if (currentRectangle) {
                        map.removeLayer(currentRectangle);
                        drawnItems.removeLayer(currentRectangle);
                        currentRectangle = null;
                    }
                }
            };
        }
        
        function initializeCenterBiasMap(mapId, key, initialCenterBias, updateCoordsDisplay, manualContainer) {
            // Initialize the map centered on Greece
            const map = L.map(mapId).setView([38.5, 23.7], 6);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            let currentMarker = null;
            let currentCircle = null;
            let isDrawing = false;
            
            // Function to update centerBias from marker and circle
            const updateCenterBiasFromMap = (latlng, radius) => {
                const newCenterBias = {
                    lat: latlng.lat,
                    lng: latlng.lng,
                    radius: radius
                };
                
                // Update config value
                updateConfigValue(key, newCenterBias);
                updateCoordsDisplay(newCenterBias);
                
                // Update manual input fields
                const inputs = manualContainer.querySelectorAll('input[type="number"]');
                if (inputs.length === 3) {
                    inputs[0].value = newCenterBias.lat.toFixed(6);
                    inputs[1].value = newCenterBias.lng.toFixed(6);
                    inputs[2].value = newCenterBias.radius;
                }
            };
            
            // Function to create/update marker and circle
            const createCenterBias = (latlng, radius) => {
                // Remove existing marker and circle
                if (currentMarker) {
                    map.removeLayer(currentMarker);
                }
                if (currentCircle) {
                    map.removeLayer(currentCircle);
                }
                
                // Create marker
                currentMarker = L.marker(latlng, {
                    draggable: true,
                    icon: L.divIcon({
                        className: 'center-bias-marker',
                        html: '<div style="background-color: #ff6b6b; border: 2px solid white; border-radius: 50%; width: 16px; height: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [16, 16],
                        iconAnchor: [8, 8]
                    })
                }).addTo(map);
                
                // Create circle
                currentCircle = L.circle(latlng, {
                    radius: radius,
                    color: '#ff6b6b',
                    weight: 2,
                    fillOpacity: 0.1
                }).addTo(map);
                
                // Make marker draggable and update circle on drag
                currentMarker.on('dragend', function(e) {
                    const newLatLng = e.target.getLatLng();
                    const currentRadius = currentCircle.getRadius();
                    currentCircle.setLatLng(newLatLng);
                    updateCenterBiasFromMap(newLatLng, currentRadius);
                });
                
                // Fit map to circle
                map.fitBounds(currentCircle.getBounds());
            };
            
            // If there's initial centerBias, draw it
            if (initialCenterBias && initialCenterBias.lat) {
                const latlng = L.latLng(initialCenterBias.lat, initialCenterBias.lng);
                createCenterBias(latlng, initialCenterBias.radius);
            }
            
            // Click to set center point
            map.on('click', function(e) {
                if (isDrawing) return;
                
                const defaultRadius = 10000; // 10km default
                createCenterBias(e.latlng, defaultRadius);
                updateCenterBiasFromMap(e.latlng, defaultRadius);
            });
            
            // Store map reference and helper functions
            if (!window.centerBiasMaps) {
                window.centerBiasMaps = {};
            }
            
            window.centerBiasMaps[key] = {
                map: map,
                slider: null,
                valueSpan: null,
                updateFromData: function(centerBias) {
                    if (centerBias && centerBias.lat) {
                        const latlng = L.latLng(centerBias.lat, centerBias.lng);
                        createCenterBias(latlng, centerBias.radius);
                        // Update slider and value display
                        if (this.slider) {
                            this.slider.value = centerBias.radius;
                        }
                        if (this.valueSpan) {
                            this.valueSpan.textContent = centerBias.radius + 'm';
                        }
                    } else {
                        // Clear marker and circle
                        if (currentMarker) {
                            map.removeLayer(currentMarker);
                            currentMarker = null;
                        }
                        if (currentCircle) {
                            map.removeLayer(currentCircle);
                            currentCircle = null;
                        }
                        // Reset slider to default
                        if (this.slider) {
                            this.slider.value = 10000;
                        }
                        if (this.valueSpan) {
                            this.valueSpan.textContent = '10000m';
                        }
                    }
                },
                clear: function() {
                    if (currentMarker) {
                        map.removeLayer(currentMarker);
                        currentMarker = null;
                    }
                    if (currentCircle) {
                        map.removeLayer(currentCircle);
                        currentCircle = null;
                    }
                    // Reset slider to default
                    if (this.slider) {
                        this.slider.value = 10000;
                    }
                    if (this.valueSpan) {
                        this.valueSpan.textContent = '10000m';
                    }
                },
                updateRadius: function(newRadius) {
                    if (currentCircle) {
                        currentCircle.setRadius(newRadius);
                        map.fitBounds(currentCircle.getBounds());
                    }
                    if (this.slider) {
                        this.slider.value = newRadius;
                    }
                    if (this.valueSpan) {
                        this.valueSpan.textContent = newRadius + 'm';
                    }
                }
            };
            
            // Add custom controls
            const customControl = L.control({ position: 'topright' });
            customControl.onAdd = function(map) {
                const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                div.style.backgroundColor = 'white';
                div.style.padding = '5px';
                div.style.fontSize = '12px';
                div.innerHTML = '<strong>🎯 ' + getTranslation('center_bias_map') + '</strong>';
                return div;
            };
            customControl.addTo(map);
            
            // Radius adjustment control
            const radiusControl = L.control({ position: 'bottomright' });
            radiusControl.onAdd = function(map) {
                const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                div.style.backgroundColor = 'white';
                div.style.padding = '8px';
                div.innerHTML = `
                    <label style="font-size: 12px; font-weight: bold;">Radius (m):</label><br>
                    <input type="range" id="radiusSlider_${key}" min="1000" max="50000" step="1000" value="${initialCenterBias?.radius || 10000}" style="width: 120px;">
                    <br><span id="radiusValue_${key}" style="font-size: 11px;">${initialCenterBias?.radius || 10000}m</span>
                `;
                
                const slider = div.querySelector(`#radiusSlider_${key}`);
                const valueSpan = div.querySelector(`#radiusValue_${key}`);
                
                // Store references for external updates
                window.centerBiasMaps[key].slider = slider;
                window.centerBiasMaps[key].valueSpan = valueSpan;
                
                slider.addEventListener('input', function() {
                    const newRadius = parseInt(this.value);
                    valueSpan.textContent = newRadius + 'm';
                    
                    if (currentMarker && currentCircle) {
                        currentCircle.setRadius(newRadius);
                        map.fitBounds(currentCircle.getBounds());
                        updateCenterBiasFromMap(currentMarker.getLatLng(), newRadius);
                    }
                });
                
                return div;
            };
            radiusControl.addTo(map);
        }
    </script>
</body>
</html>