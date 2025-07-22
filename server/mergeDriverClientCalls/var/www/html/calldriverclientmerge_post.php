<?php

/**
 * PHP Script to initiate two calls sequentially and merge them into a conference
 * Requires: php-curl, Asterisk Manager Interface (AMI) enabled
 */

class CallMerger
{
    private $ami_host;
    private $ami_port;
    private $ami_username;
    private $ami_password;
    private $socket;
    private $events = array();

    public function __construct($host = 'localhost', $port = 5038, $username = 'iqtaxi', $password = 'abc123!')
    {
        $this->ami_host = $host;
        $this->ami_port = $port;
        $this->ami_username = $username;
        $this->ami_password = $password;
    }

    /**
     * Connect to Asterisk Manager Interface
     */
    private function connectAMI()
    {
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
    private function readAMIResponse()
    {
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
    private function monitorEvents($actionid, $timeout = 30)
    {
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
    private function parseEvent($data)
    {
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
    private function sendAMICommand($command)
    {
        fwrite($this->socket, $command);
        return $this->readAMIResponse();
    }

    /**
     * Initiate a call using AMI Originate and wait for answer
     */
    private function originateAndWait($number, $context, $extension, $priority = 1, $callerid = 'CallMerge')
    {
        $actionid = uniqid();

        $originate = "Action: Originate\r\n";
        $originate .= "Channel: SIP/{$number}\r\n";
        $originate .= "Context: {$context}\r\n";
        $originate .= "Exten: {$extension}\r\n";
        $originate .= "Priority: {$priority}\r\n";
        $originate .= "CallerID: {$callerid}\r\n";
        $originate .= "Timeout: 30000\r\n";
        $originate .= "ActionID: {$actionid}\r\n";
        $originate .= "Variable: CALL_ACTIONID={$actionid}\r\n";
        $originate .= "\r\n";

        echo "Initiating call to $number with ActionID: $actionid\n";
        fwrite($this->socket, $originate);

        // Wait for the call to be answered
        $answered = $this->monitorEvents($actionid, 30);

        return array(
            'actionid' => $actionid,
            'answered' => $answered,
            'number' => $number
        );
    }

    /**
     * Simple originate without waiting (for second call)
     */
    private function originateCall($number, $context, $extension, $priority = 1, $callerid = 'CallMerge')
    {
        $actionid = uniqid();

        $originate = "Action: Originate\r\n";
        $originate .= "Channel: SIP/{$number}\r\n";
        $originate .= "Context: {$context}\r\n";
        $originate .= "Exten: {$extension}\r\n";
        $originate .= "Priority: {$priority}\r\n";
        $originate .= "CallerID: {$callerid}\r\n";
        $originate .= "Timeout: 30000\r\n";
        $originate .= "ActionID: {$actionid}\r\n";
        $originate .= "\r\n";

        $response = $this->sendAMICommand($originate);
        return $actionid;
    }

    /**
     * Main function to merge two calls sequentially
     */
    public function mergeCalls($phone1, $phone2, $conference_room = null)
    {
        try {
            // Generate conference room if not provided
            if (!$conference_room) {
                $conference_room = rand(1000, 9999);
            }

            // Connect to AMI
            $this->connectAMI();

            echo "Starting sequential call merge: $phone1 -> $phone2 in conference room $conference_room\n";

            // Step 1: Originate first call and wait for answer
            echo "Step 1: Calling $phone1 and waiting for answer...\n";
            $call1 = $this->originateAndWait($phone1, 'call-merge-wait', $conference_room, 1, 'Conference Call');

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
            $actionid2 = $this->originateCall($phone2, 'call-merge', $conference_room, 1, 'Conference Call');

            echo "Step 2 complete: Second call initiated\n";

            // Close AMI connection
            $this->closeAMI();

            return array(
                'success' => true,
                'conference_room' => $conference_room,
                'phone1' => $phone1,
                'phone2' => $phone2,
                'actionid1' => $call1['actionid'],
                'actionid2' => $actionid2
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Close AMI connection
     */
    private function closeAMI()
    {
        if ($this->socket) {
            $logoff = "Action: Logoff\r\n\r\n";
            fwrite($this->socket, $logoff);
            fclose($this->socket);
        }
    }
}

// Usage example
if (isset($_POST['phone1']) && isset($_POST['phone2'])) {
    // Web form submission
    $phone1 = $_POST['phone1'];
    $phone2 = $_POST['phone2'];
    $conference_room = isset($_POST['conference_room']) ? $_POST['conference_room'] : null;
    $method = isset($_POST['method']) ? $_POST['method'] : 'event';

    $merger = new CallMerger();

    if ($method === 'simple') {
        $wait_time = isset($_POST['wait_time']) ? (int)$_POST['wait_time'] : 8;
        $result = $merger->mergeCallsSimple($phone1, $phone2, $conference_room, $wait_time);
    } else {
        $result = $merger->mergeCalls($phone1, $phone2, $conference_room);
    }

    header('Content-Type: application/json');
    echo json_encode($result);
} elseif (isset($argv[1]) && isset($argv[2])) {
    // Command line usage
    $phone1 = $argv[1];
    $phone2 = $argv[2];
    $conference_room = isset($argv[3]) ? $argv[3] : null;
    $method = isset($argv[4]) ? $argv[4] : 'event';

    $merger = new CallMerger();

    if ($method === 'simple') {
        $wait_time = isset($argv[5]) ? (int)$argv[5] : 8;
        $result = $merger->mergeCallsSimple($phone1, $phone2, $conference_room, $wait_time);
    } else {
        $result = $merger->mergeCalls($phone1, $phone2, $conference_room);
    }

    if ($result['success']) {
        echo "Success! Conference room: {$result['conference_room']}\n";
    } else {
        echo "Error: {$result['error']}\n";
    }
} else {
    // Display usage
    echo "Usage:\n";
    echo "Web: POST phone1=1234567890&phone2=0987654321&method=simple\n";
    echo "CLI: php call_merge.php 1234567890 0987654321 [conference_room] [method] [wait_time]\n";
    echo "Methods: event (default), simple\n";
}

// HTML form for testing (only show if accessed via web browser)
if (!isset($_POST['phone1']) && !isset($argv[1])) {
    echo '
    <html>
    <head>
        <title>Call Merge Test</title>
    </head>
    <body>
        <h2>Call Merge Test</h2>
        <form method="POST">
            <label>Phone 1: <input type="text" name="phone1" placeholder="1234567890" required></label><br><br>
            <label>Phone 2: <input type="text" name="phone2" placeholder="0987654321" required></label><br><br>
            <label>Conference Room (optional): <input type="text" name="conference_room" placeholder="Auto-generated"></label><br><br>
            <label>Method: 
                <select name="method">
                    <option value="event">Event Monitoring (default)</option>
                    <option value="simple">Simple Time-based</option>
                </select>
            </label><br><br>
            <label>Wait Time (for simple method): <input type="number" name="wait_time" value="8" min="3" max="30"> seconds</label><br><br>
            <button type="submit">Merge Calls</button>
        </form>
        
        <h3>Usage Instructions:</h3>
        <ul>
            <li><strong>Event Monitoring:</strong> Waits for first call to be actually answered before calling second number (recommended)</li>
            <li><strong>Simple Time-based:</strong> Waits a fixed time (default 8 seconds) before calling second number</li>
        </ul>
    </body>
    </html>';
}
