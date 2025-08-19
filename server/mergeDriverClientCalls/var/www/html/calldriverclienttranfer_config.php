<?php
/**
 * PHP Script to initiate two calls and transfer them together
 * Requires: php-curl, Asterisk Manager Interface (AMI) enabled
 * Config file: call_merge_config.ini
 */

class CallTransfer {
    private $ami_host;
    private $ami_port;
    private $ami_username;
    private $ami_password;
    private $outgoing_trunk;
    private $socket;
    private $events = array();
    private $config;
    
    public function __construct($config_file = 'call_merge_config.ini') {
        $this->loadConfig($config_file);
        $this->ami_host = $this->config['ami']['host'];
        $this->ami_port = $this->config['ami']['port'];
        $this->ami_username = $this->config['ami']['username'];
        $this->ami_password = $this->config['ami']['password'];
        $this->outgoing_trunk = $this->config['trunk']['outgoing'];
    }
    
    /**
     * Load configuration from INI file
     */
    private function loadConfig($config_file) {
        if (!file_exists($config_file)) {
            $this->createDefaultConfig($config_file);
            throw new Exception("Config file created at $config_file. Please edit it with your settings and try again.");
        }
        
        $this->config = parse_ini_file($config_file, true);
        
        if ($this->config === false) {
            throw new Exception("Failed to parse config file: $config_file");
        }
        
        // Validate required sections
        $required_sections = ['ami', 'trunk'];
        foreach ($required_sections as $section) {
            if (!isset($this->config[$section])) {
                throw new Exception("Missing required section '$section' in config file");
            }
        }
        
        // Validate required AMI settings
        $required_ami = ['host', 'port', 'username', 'password'];
        foreach ($required_ami as $key) {
            if (!isset($this->config['ami'][$key])) {
                throw new Exception("Missing required AMI setting '$key' in config file");
            }
        }
        
        // Validate required trunk settings
        if (!isset($this->config['trunk']['outgoing'])) {
            throw new Exception("Missing required trunk setting 'outgoing' in config file");
        }
        
        echo "Config loaded successfully from $config_file\n";
    }
    
    /**
     * Create default configuration file
     */
    private function createDefaultConfig($config_file) {
        $default_config = "; Call Transfer Configuration File
; Edit these settings according to your Asterisk setup

[ami]
; Asterisk Manager Interface settings
host = localhost
port = 5038
username = admin
password = amp111

[trunk]
; Outgoing trunk configuration
; Examples:
; outgoing = SIP/trunk-name
; outgoing = SIP/provider
; outgoing = PJSIP/trunk-name
; outgoing = IAX2/provider
outgoing = SIP

[settings]
; General settings
default_timeout = 30
default_wait_time = 8
max_transfer_attempts = 3
";
        
        if (file_put_contents($config_file, $default_config) === false) {
            throw new Exception("Failed to create default config file: $config_file");
        }
        
        echo "Default config file created at: $config_file\n";
    }
    
    /**
     * Connect to Asterisk Manager Interface
     */
    private function connectAMI() {
        $this->socket = fsockopen($this->ami_host, $this->ami_port, $errno, $errstr, 10);
        if (!$this->socket) {
            throw new Exception("Cannot connect to AMI: $errstr ($errno)");
        }
        
        // Set socket to non-blocking mode for event monitoring
        stream_set_blocking($this->socket, 0);
        
        // Read welcome message
        sleep(1);
        $welcome = fread($this->socket, 1024);
        
        // Login
        $login = "Action: Login\r\n";
        $login .= "Username: {$this->ami_username}\r\n";
        $login .= "Secret: {$this->ami_password}\r\n";
        $login .= "Events: call\r\n";
        $login .= "\r\n";
        
        fwrite($this->socket, $login);
        
        // Read login response
        sleep(1);
        $response = $this->readAMIResponse();
        if (strpos($response, 'Success') === false) {
            throw new Exception("AMI Login failed: $response");
        }
        
        return true;
    }
    
    /**
     * Read AMI response
     */
    private function readAMIResponse() {
        $response = '';
        $start_time = time();
        
        while (time() - $start_time < 5) {
            $line = fgets($this->socket);
            if ($line === false) {
                usleep(100000); // 0.1 second
                continue;
            }
            
            $response .= $line;
            if (trim($line) === '') {
                break;
            }
        }
        return $response;
    }
    
    /**
     * Monitor AMI events for call status
     */
    private function monitorEvents($actionid, $timeout = 30) {
        $start_time = time();
        $buffer = '';
        
        echo "Monitoring events for ActionID: $actionid (timeout: {$timeout}s)\n";
        
        while (time() - $start_time < $timeout) {
            $data = fread($this->socket, 4096);
            if ($data === false || $data === '') {
                usleep(200000); // 0.2 second
                continue;
            }
            
            $buffer .= $data;
            
            // Process complete events (ending with \r\n\r\n)
            while (($pos = strpos($buffer, "\r\n\r\n")) !== false) {
                $event_data = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 4);
                
                if (empty(trim($event_data))) continue;
                
                // Parse event
                $event = $this->parseEvent($event_data);
                if (!$event) continue;
                
                // Debug: Show all events for our ActionID
                if (isset($event['ActionID']) && $event['ActionID'] === $actionid) {
                    echo "DEBUG: Event for our ActionID: {$event['Event']}\n";
                    if (isset($event['Response'])) {
                        echo "DEBUG: Response: {$event['Response']}\n";
                    }
                }
                
                // Check for originate response
                if (isset($event['ActionID']) && $event['ActionID'] === $actionid) {
                    if (isset($event['Response'])) {
                        if ($event['Response'] === 'Success') {
                            echo "Call origination successful for ActionID: $actionid\n";
                        } else {
                            echo "Call origination failed: {$event['Response']}\n";
                            if (isset($event['Message'])) {
                                echo "Error message: {$event['Message']}\n";
                            }
                            return false;
                        }
                    }
                }
                
                // Monitor for different event types that indicate call answered
                $call_answered = false;
                
                // Method 1: Check for Bridge events (when call enters conference)
                if (isset($event['Event']) && $event['Event'] === 'BridgeEnter') {
                    if (isset($event['Channel']) && strpos($event['Channel'], $actionid) !== false) {
                        echo "Call entered bridge - assuming answered\n";
                        $call_answered = true;
                    }
                }
                
                // Method 2: Check for ConfbridgeJoin events
                if (isset($event['Event']) && $event['Event'] === 'ConfbridgeJoin') {
                    echo "Call joined conference - answered\n";
                    $call_answered = true;
                }
                
                // Method 3: Check for Newstate events
                if (isset($event['Event']) && $event['Event'] === 'Newstate') {
                    echo "Channel state change: {$event['ChannelState']} - {$event['ChannelStateDesc']}\n";
                    
                    // State 6 = Up (answered)
                    if ($event['ChannelState'] === '6') {
                        echo "Call answered (state 6) for ActionID: $actionid\n";
                        $call_answered = true;
                    }
                }
                
                // Method 4: Check for Dial events
                if (isset($event['Event']) && $event['Event'] === 'DialEnd') {
                    if (isset($event['DialStatus']) && $event['DialStatus'] === 'ANSWER') {
                        echo "Dial ended with ANSWER status\n";
                        $call_answered = true;
                    }
                }
                
                if ($call_answered) {
                    echo "Call confirmed as answered for ActionID: $actionid\n";
                    return true;
                }
                
                // Check for hangup events
                if (isset($event['Event']) && $event['Event'] === 'Hangup') {
                    echo "Hangup detected - call failed\n";
                    return false;
                }
            }
        }
        
        echo "Timeout waiting for call to be answered (waited {$timeout}s)\n";
        return false;
    }
    
    /**
     * Parse AMI event data
     */
    private function parseEvent($data) {
        $event = array();
        $lines = explode("\r\n", $data);
        
        foreach ($lines as $line) {
            if (strpos($line, ': ') !== false) {
                list($key, $value) = explode(': ', $line, 2);
                $event[trim($key)] = trim($value);
            }
        }
        
        return $event;
    }
    
    /**
     * Send AMI command
     */
    private function sendAMICommand($command) {
        fwrite($this->socket, $command);
        return $this->readAMIResponse();
    }
    
    /**
     * Initiate a call using AMI Originate and wait for answer
     */
    private function originateAndWait($number, $context, $extension, $priority = 1, $callerid = 'CallMerge') {
        $actionid = uniqid();
        
        // Construct channel based on trunk configuration
        $channel = $this->buildChannel($number);
        
        $originate = "Action: Originate\r\n";
        $originate .= "Channel: {$channel}\r\n";
        $originate .= "Context: {$context}\r\n";
        $originate .= "Exten: {$extension}\r\n";
        $originate .= "Priority: {$priority}\r\n";
        $originate .= "CallerID: {$callerid}\r\n";
        $originate .= "Timeout: 30000\r\n";
        $originate .= "ActionID: {$actionid}\r\n";
        $originate .= "Variable: CALL_ACTIONID={$actionid}\r\n";
        $originate .= "\r\n";
        
        echo "Initiating call to $number via $channel with ActionID: $actionid\n";
        fwrite($this->socket, $originate);
        
        // Wait for the call to be answered
        $answered = $this->monitorEvents($actionid, 30);
        
        return array(
            'actionid' => $actionid,
            'answered' => $answered,
            'number' => $number,
            'channel' => $channel
        );
    }
    
    /**
     * Simple originate without waiting (for second call)
     */
    private function originateCall($number, $context, $extension, $priority = 1, $callerid = 'CallMerge') {
        $actionid = uniqid();
        
        // Construct channel based on trunk configuration
        $channel = $this->buildChannel($number);
        
        $originate = "Action: Originate\r\n";
        $originate .= "Channel: {$channel}\r\n";
        $originate .= "Context: {$context}\r\n";
        $originate .= "Exten: {$extension}\r\n";
        $originate .= "Priority: {$priority}\r\n";
        $originate .= "CallerID: {$callerid}\r\n";
        $originate .= "Timeout: 30000\r\n";
        $originate .= "ActionID: {$actionid}\r\n";
        $originate .= "\r\n";
        
        echo "Initiating call to $number via $channel with ActionID: $actionid\n";
        $response = $this->sendAMICommand($originate);
        return $actionid;
    }
    
    /**
     * Build channel string based on trunk configuration
     */
    private function buildChannel($number) {
        $trunk = $this->outgoing_trunk;
        
        // Handle different trunk formats
        if (strpos($trunk, '/') !== false) {
            // Trunk already has format like "SIP/provider" or "PJSIP/trunk-name"
            return $trunk . '/' . $number;
        } else {
            // Simple trunk name like "SIP" or "PJSIP"
            return $trunk . '/' . $number;
        }
    }
    
    /**
     * Main function to transfer two calls sequentially with event monitoring
     */
    public function transferCalls($phone1, $phone2, $transfer_id = null) {
        try {
            // Generate transfer ID if not provided
            if (!$transfer_id) {
                $transfer_id = rand(1000, 9999);
            }
            
            // Connect to AMI
            $this->connectAMI();
            
            echo "Starting sequential call transfer: $phone1 -> $phone2 with transfer ID $transfer_id\n";
            
            // Step 1: Originate first call and wait for answer
            echo "Step 1: Calling $phone1 and waiting for answer...\n";
            $call1 = $this->originateAndWait($phone1, 'call-transfer-wait', $transfer_id, 1, 'Call Transfer');
            
            if (!$call1['answered']) {
                $this->closeAMI();
                return array(
                    'success' => false,
                    'error' => "First call to {$phone1} was not answered or failed"
                );
            }
            
            echo "Step 1 complete: $phone1 answered successfully\n";
            
            // Step 2: Small delay then originate second call
            echo "Step 2: Calling $phone2...\n";
            sleep(2);
            $actionid2 = $this->originateCall($phone2, 'call-transfer-bridge', $transfer_id, 1, 'Call Transfer');
            
            echo "Step 2 complete: Second call initiated - calls will be bridged\n";
            
            // Close AMI connection
            $this->closeAMI();
            
            return array(
                'success' => true,
                'transfer_id' => $transfer_id,
                'phone1' => $phone1,
                'phone2' => $phone2,
                'actionid1' => $call1['actionid'],
                'actionid2' => $actionid2,
                'method' => 'event_monitoring'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Simple time-based approach - more reliable
     */
    public function transferCallsSimple($phone1, $phone2, $transfer_id = null, $wait_time = null) {
        try {
            // Use config default if wait_time not provided
            if ($wait_time === null) {
                $wait_time = isset($this->config['settings']['default_wait_time']) ? 
                    (int)$this->config['settings']['default_wait_time'] : 8;
            }
            
            // Generate transfer ID if not provided
            if (!$transfer_id) {
                $transfer_id = rand(1000, 9999);
            }
            
            // Connect to AMI
            $this->connectAMI();
            
            echo "Starting simple timed call transfer: $phone1 -> $phone2 with transfer ID $transfer_id\n";
            echo "Using trunk: {$this->outgoing_trunk}\n";
            
            // Step 1: Originate first call
            echo "Step 1: Calling $phone1...\n";
            $actionid1 = $this->originateCall($phone1, 'call-transfer-wait', $transfer_id, 1, 'Call Transfer');
            
            echo "Step 1 complete: First call initiated (ActionID: $actionid1)\n";
            
            // Step 2: Wait for specified time to allow first call to be answered
            echo "Step 2: Waiting {$wait_time} seconds for first call to be answered...\n";
            sleep($wait_time);
            
            // Step 3: Originate second call
            echo "Step 3: Calling $phone2...\n";
            $actionid2 = $this->originateCall($phone2, 'call-transfer-bridge', $transfer_id, 1, 'Call Transfer');
            
            echo "Step 3 complete: Second call initiated - calls will be bridged (ActionID: $actionid2)\n";
            
            // Close AMI connection
            $this->closeAMI();
            
            return array(
                'success' => true,
                'transfer_id' => $transfer_id,
                'phone1' => $phone1,
                'phone2' => $phone2,
                'actionid1' => $actionid1,
                'actionid2' => $actionid2,
                'method' => 'simple_timed',
                'trunk' => $this->outgoing_trunk,
                'wait_time' => $wait_time
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Call file approach - most reliable for second call
     */
    public function transferCallsWithCallFile($phone1, $phone2, $transfer_id = null, $wait_time = null) {
        try {
            // Use config default if wait_time not provided
            if ($wait_time === null) {
                $wait_time = isset($this->config['settings']['default_wait_time']) ? 
                    (int)$this->config['settings']['default_wait_time'] : 8;
            }
            
            // Generate transfer ID if not provided
            if (!$transfer_id) {
                $transfer_id = rand(1000, 9999);
            }
            
            // Connect to AMI
            $this->connectAMI();
            
            echo "Starting call transfer with call file method: $phone1 -> $phone2 with transfer ID $transfer_id\n";
            echo "Using trunk: {$this->outgoing_trunk}\n";
            
            // Step 1: Originate first call
            echo "Step 1: Calling $phone1...\n";
            $actionid1 = $this->originateCall($phone1, 'call-transfer-wait', $transfer_id, 1, 'Call Transfer');
            
            echo "Step 1 complete: First call initiated (ActionID: $actionid1)\n";
            
            // Step 2: Wait for specified time
            echo "Step 2: Waiting {$wait_time} seconds for first call to be answered...\n";
            sleep($wait_time);
            
            // Step 3: Create call file for second call (more reliable)
            echo "Step 3: Creating call file for $phone2...\n";
            $this->createCallFile($phone2, 'call-transfer-bridge', $transfer_id);
            
            echo "Step 3 complete: Call file created for second call - calls will be bridged\n";
            
            // Close AMI connection
            $this->closeAMI();
            
            return array(
                'success' => true,
                'transfer_id' => $transfer_id,
                'phone1' => $phone1,
                'phone2' => $phone2,
                'actionid1' => $actionid1,
                'actionid2' => 'callfile',
                'method' => 'call_file',
                'trunk' => $this->outgoing_trunk,
                'wait_time' => $wait_time
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create Asterisk call file
     */
    private function createCallFile($number, $context, $extension) {
        // Construct channel based on trunk configuration
        $channel = $this->buildChannel($number);
        
        $callfile_content = "Channel: {$channel}\n";
        $callfile_content .= "Context: {$context}\n";
        $callfile_content .= "Extension: {$extension}\n";
        $callfile_content .= "Priority: 1\n";
        $callfile_content .= "CallerID: Conference Call\n";
        $callfile_content .= "MaxRetries: 2\n";
        $callfile_content .= "RetryTime: 60\n";
        $callfile_content .= "WaitTime: 30\n";
        
        $filename = '/tmp/call_' . uniqid() . '.call';
        $spool_file = '/var/spool/asterisk/outgoing/' . basename($filename);
        
        // Write to temp file first
        if (file_put_contents($filename, $callfile_content) === false) {
            throw new Exception("Failed to create call file");
        }
        
        // Move to spool directory (this triggers the call)
        if (!rename($filename, $spool_file)) {
            throw new Exception("Failed to move call file to spool directory");
        }
        
        echo "Call file created: $spool_file (Channel: $channel)\n";
        return $spool_file;
    }
    
    /**
     * Close AMI connection
     */
    private function closeAMI() {
        if ($this->socket) {
            $logoff = "Action: Logoff\r\n\r\n";
            fwrite($this->socket, $logoff);
            fclose($this->socket);
        }
    }
}

// Usage example
if (isset($_GET['phone1']) && isset($_GET['phone2'])) {
    // Web form submission via GET
    $phone1 = $_GET['phone1'];
    $phone2 = $_GET['phone2'];
    $transfer_id = isset($_GET['transfer_id']) ? $_GET['transfer_id'] : null;
    $method = isset($_GET['method']) ? $_GET['method'] : 'simple';
    
    $transfer = new CallTransfer();
    
    if ($method === 'simple') {
        $wait_time = isset($_GET['wait_time']) && $_GET['wait_time'] !== '' ? (int)$_GET['wait_time'] : null;
        $result = $transfer->transferCallsSimple($phone1, $phone2, $transfer_id, $wait_time);
    } elseif ($method === 'callfile') {
        $wait_time = isset($_GET['wait_time']) && $_GET['wait_time'] !== '' ? (int)$_GET['wait_time'] : null;
        $result = $transfer->transferCallsWithCallFile($phone1, $phone2, $transfer_id, $wait_time);
    } else {
        $result = $transfer->transferCalls($phone1, $phone2, $transfer_id);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} elseif (isset($_POST['phone1']) && isset($_POST['phone2'])) {
    // Web form submission via POST (backward compatibility)
    $phone1 = $_POST['phone1'];
    $phone2 = $_POST['phone2'];
    $transfer_id = isset($_POST['transfer_id']) ? $_POST['transfer_id'] : null;
    $method = isset($_POST['method']) ? $_POST['method'] : 'simple';
    
    $transfer = new CallTransfer();
    
    if ($method === 'simple') {
        $wait_time = isset($_POST['wait_time']) && $_POST['wait_time'] !== '' ? (int)$_POST['wait_time'] : null;
        $result = $transfer->transferCallsSimple($phone1, $phone2, $transfer_id, $wait_time);
    } elseif ($method === 'callfile') {
        $wait_time = isset($_POST['wait_time']) && $_POST['wait_time'] !== '' ? (int)$_POST['wait_time'] : null;
        $result = $transfer->transferCallsWithCallFile($phone1, $phone2, $transfer_id, $wait_time);
    } else {
        $result = $transfer->transferCalls($phone1, $phone2, $transfer_id);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} elseif (isset($argv[1]) && isset($argv[2])) {
    // Command line usage
    $phone1 = $argv[1];
    $phone2 = $argv[2];
    $transfer_id = isset($argv[3]) ? $argv[3] : null;
    $method = isset($argv[4]) ? $argv[4] : 'simple';
    
    $transfer = new CallTransfer();
    
    if ($method === 'simple') {
        $wait_time = isset($argv[5]) && $argv[5] !== '' ? (int)$argv[5] : null;
        $result = $transfer->transferCallsSimple($phone1, $phone2, $transfer_id, $wait_time);
    } elseif ($method === 'callfile') {
        $wait_time = isset($argv[5]) && $argv[5] !== '' ? (int)$argv[5] : null;
        $result = $transfer->transferCallsWithCallFile($phone1, $phone2, $transfer_id, $wait_time);
    } else {
        $result = $transfer->transferCalls($phone1, $phone2, $transfer_id);
    }
    
    if ($result['success']) {
        echo "Success! Transfer ID: {$result['transfer_id']}\n";
        echo "Method used: {$result['method']}\n";
    } else {
        echo "Error: {$result['error']}\n";
    }
    
} else {
    // Display usage
    echo "Call Transfer Script - Configuration-based Call Transfer\n";
    echo "=====================================================\n";
    echo "Config file: call_merge_config.ini (will be created automatically)\n\n";
    echo "Usage:\n";
    echo "Web GET: ?phone1=1234567890&phone2=0987654321&method=simple\n";
    echo "Web POST: POST phone1=1234567890&phone2=0987654321&method=simple\n";
    echo "CLI: php call_transfer.php 1234567890 0987654321 [transfer_id] [method] [wait_time]\n";
    echo "Methods: event, simple (default), callfile\n\n";
    echo "First run will create call_merge_config.ini - edit it with your settings!\n";
}

// HTML form for testing (only show if accessed via web browser)
if (!isset($_GET['phone1']) && !isset($_POST['phone1']) && !isset($argv[1])) {
    echo '
    <html>
    <head>
        <title>Call Transfer Test</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .config-info { background: #f0f8ff; padding: 15px; border-left: 4px solid #0066cc; margin: 20px 0; }
            .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
            code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <h1>Call Transfer Test - Configuration Based</h1>
        
        <div class="config-info">
            <h3>📁 Configuration File Required</h3>
            <p>This script uses <strong>call_merge_config.ini</strong> for settings:</p>
            <ul>
                <li>AMI host, port, username, password</li>
                <li>Outgoing trunk configuration</li>
                <li>Default timeouts and settings</li>
            </ul>
            <p>The config file will be created automatically on first run if it doesn\'t exist.</p>
        </div>
        
        <h3>How Call Transfer Works:</h3>
        <p>Instead of conference calling, this creates a direct bridge between two calls:</p>
        <ol>
            <li>Call the first number and put them on hold with music</li>
            <li>Call the second number</li>
            <li>Bridge the two calls together directly (no conference room needed)</li>
            <li>Both parties can talk privately</li>
        </ol>
        
        <h3>GET Method (URL Parameters):</h3>
        <p>Use this URL format:</p>
        <code>call_transfer.php?phone1=20&phone2=6974888710&method=simple&wait_time=8</code>
        
        <h3>POST Method (Form):</h3>
        <form method="POST">
            <label>Phone 1: <input type="text" name="phone1" placeholder="1234567890" required></label><br><br>
            <label>Phone 2: <input type="text" name="phone2" placeholder="0987654321" required></label><br><br>
            <label>Transfer ID (optional): <input type="text" name="transfer_id" placeholder="Auto-generated"></label><br><br>
            <label>Method: 
                <select name="method">
                    <option value="simple" selected>Simple Time-based (recommended)</option>
                    <option value="event">Event Monitoring</option>
                    <option value="callfile">Call File Method</option>
                </select>
            </label><br><br>
            <label>Wait Time (optional): <input type="number" name="wait_time" placeholder="From config" min="3" max="30"> seconds</label><br><br>
            <button type="submit">Transfer Calls</button>
        </form>
        
        <div class="warning">
            <h3>⚙️ Setup Instructions:</h3>
            <ol>
                <li>Run the script once to generate <code>call_merge_config.ini</code></li>
                <li>Edit the config file with your Asterisk settings</li>
                <li>Set your AMI credentials and outgoing trunk</li>
                <li>Add the new dialplan contexts to your FreePBX</li>
                <li>Test with the form above</li>
            </ol>
        </div>
        
        <h3>Dialplan Contexts Required:</h3>
        <ul>
            <li><strong>[call-transfer-wait]</strong> - Handles first call, plays music on hold</li>
            <li><strong>[call-transfer-bridge]</strong> - Handles second call, bridges with first</li>
        </ul>
        
        <h3>Configuration Examples:</h3>
        <h4>For SIP trunk:</h4>
        <code>outgoing = SIP/your-trunk-name</code><br>
        <h4>For PJSIP trunk:</h4>
        <code>outgoing = PJSIP/your-trunk-name</code><br>
        <h4>For IAX2 trunk:</h4>
        <code>outgoing = IAX2/your-provider</code><br>
        
        <h3>Transfer Methods:</h3>
        <ul>
            <li><strong>Simple Time-based (recommended):</strong> Waits a fixed time before calling second number</li>
            <li><strong>Event Monitoring:</strong> Waits for first call to be actually answered before calling second number</li>
            <li><strong>Call File Method:</strong> Uses Asterisk call files for the second call</li>
        </ul>
        
        <h3>GET Examples:</h3>
        <ul>
            <li><a href="?phone1=20&phone2=6974888710&method=simple&wait_time=8">Test with extensions 20 and 6974888710</a></li>
            <li><code>call_transfer.php?phone1=1234567890&phone2=0987654321&method=simple</code></li>
            <li><code>call_transfer.php?phone1=1234567890&phone2=0987654321&method=callfile&wait_time=10</code></li>
        </ul>
    </body>
    </html>';
}
?>