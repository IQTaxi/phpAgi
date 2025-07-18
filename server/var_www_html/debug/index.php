<?php
// Προστατευμένος Διαχειριστής Καταγραφών Κλήσεων & Αναλυτικών
// Τοποθετήστε αυτό το αρχείο στο /var/www/html/debug/ (ή μετακινήστε στο /var/www/html/)

session_start();

// Ενεργοποίηση αναφοράς σφαλμάτων για παραγωγή
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ρυθμίσεις
$CALL_RECORDS_PATH = '/tmp/auto_register_call/4037';
$ANALYTICS_PATH = '/tmp/analytics';

// Κωδικοί πρόσβασης (MD5)
$CALL_RECORDS_PASSWORD_HASH = md5('iqtaxi_call'); // f8e8a9b9e3c1f7d2a5b4c8e1d9f7a3b6
$ANALYTICS_PASSWORD_HASH = md5('iqtaxi_analytics'); // a7b9d2c5f8e1a4b7c9e2f5a8b1d4c7e0

// Έλεγχος εξουσιοδότησης για συγκεκριμένες ενότητες
function isCallRecordsAuthorized() {
    return isset($_SESSION['call_records_auth']) && $_SESSION['call_records_auth'] === true;
}

function isAnalyticsAuthorized() {
    return isset($_SESSION['analytics_auth']) && $_SESSION['analytics_auth'] === true;
}

// Διαχείριση εξουσιοδότησης
if (isset($_POST['auth_action'])) {
    $section = $_POST['section'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($section === 'call_records' && md5($password) === $CALL_RECORDS_PASSWORD_HASH) {
        $_SESSION['call_records_auth'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($section === 'analytics' && md5($password) === $ANALYTICS_PASSWORD_HASH) {
        $_SESSION['analytics_auth'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $auth_error = 'Λάθος κωδικός πρόσβασης. Παρακαλώ δοκιμάστε ξανά.';
    }
}

// Διαχείριση αποσύνδεσης
if (isset($_GET['logout'])) {
    $section = $_GET['logout'];
    if ($section === 'call_records') {
        unset($_SESSION['call_records_auth']);
    } elseif ($section === 'analytics') {
        unset($_SESSION['analytics_auth']);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Βοηθητικές Συναρτήσεις
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}

function formatTimestamp($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

function getPhoneNumbers($path) {
    $phoneNumbers = array();
    if (!is_dir($path) || !is_readable($path)) {
        return $phoneNumbers;
    }
    
    try {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..' && is_dir($path . '/' . $item)) {
                $phoneNumbers[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log("Σφάλμα ανάγνωσης αριθμών τηλεφώνου: " . $e->getMessage());
    }
    
    return $phoneNumbers;
}

function getCallSessions($phonePath) {
    $sessions = array();
    if (!is_dir($phonePath) || !is_readable($phonePath)) {
        return $sessions;
    }
    
    try {
        $items = scandir($phonePath);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..' && is_dir($phonePath . '/' . $item)) {
                $sessions[] = array(
                    'name' => $item,
                    'path' => $phonePath . '/' . $item,
                    'timestamp' => filemtime($phonePath . '/' . $item)
                );
            }
        }
        // Ταξινόμηση κατά ημερομηνία (φθίνουσα)
        usort($sessions, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
    } catch (Exception $e) {
        error_log("Σφάλμα ανάγνωσης συνεδριών κλήσης: " . $e->getMessage());
    }
    
    return $sessions;
}

function getSessionFiles($sessionPath) {
    $files = array();
    if (!is_dir($sessionPath) || !is_readable($sessionPath)) {
        return $files;
    }
    
    try {
        $items = scandir($sessionPath);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $fullPath = $sessionPath . '/' . $item;
                $extension = pathinfo($item, PATHINFO_EXTENSION);
                $files[] = array(
                    'name' => $item,
                    'path' => $fullPath,
                    'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                    'type' => is_dir($fullPath) ? 'directory' : $extension,
                    'modified' => filemtime($fullPath),
                    'is_audio' => ($extension === 'wav' || $extension === 'wav16'),
                    'is_log' => ($extension === 'txt' || $item === 'log.txt'),
                    'is_json' => ($extension === 'json')
                );
            }
        }
    } catch (Exception $e) {
        error_log("Σφάλμα ανάγνωσης αρχείων συνεδρίας: " . $e->getMessage());
    }
    
    return $files;
}

function getRecordingFiles($recordingsPath) {
    $recordings = array();
    if (!is_dir($recordingsPath) || !is_readable($recordingsPath)) {
        return $recordings;
    }
    
    try {
        $items = scandir($recordingsPath);
        foreach ($items as $item) {
            $extension = pathinfo($item, PATHINFO_EXTENSION);
            if ($extension === 'wav' || $extension === 'wav16') {
                $fullPath = $recordingsPath . '/' . $item;
                $recordings[] = array(
                    'name' => $item,
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath)
                );
            }
        }
    } catch (Exception $e) {
        error_log("Σφάλμα ανάγνωσης ηχογραφήσεων: " . $e->getMessage());
    }
    
    return $recordings;
}

function getAnalyticsFiles($path) {
    $files = array();
    if (!is_dir($path) || !is_readable($path)) {
        return $files;
    }
    
    try {
        $items = scandir($path);
        foreach ($items as $item) {
            if (pathinfo($item, PATHINFO_EXTENSION) === 'html') {
                $fullPath = $path . '/' . $item;
                
                // Determine if it's a daily report
                $isDaily = (strpos($item, 'daily_taxi_report') !== false);
                $date = '';
                
                if ($isDaily) {
                    // Extract date from filename
                    if (preg_match('/daily_taxi_report_(\d{4}-\d{2}-\d{2})/', $item, $matches)) {
                        $date = $matches[1];
                    }
                }
                
                $files[] = array(
                    'name' => $item,
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath),
                    'is_daily' => $isDaily,
                    'date' => $date
                );
            }
        }
        
        // Sort by modified time (newest first)
        usort($files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
    } catch (Exception $e) {
        error_log("Σφάλμα ανάγνωσης αρχείων αναλυτικών: " . $e->getMessage());
    }
    
    return $files;
}

// Διαχείριση αιτημάτων AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'search_phone':
                if (!isCallRecordsAuthorized()) {
                    echo json_encode(array('error' => 'Μη εξουσιοδοτημένη πρόσβαση'));
                    exit;
                }
                $query = $_GET['query'] ?? '';
                $phoneNumbers = getPhoneNumbers($CALL_RECORDS_PATH);
                $results = array_filter($phoneNumbers, function($phone) use ($query) {
                    return stripos($phone, $query) !== false;
                });
                echo json_encode(array_values($results));
                exit;
                
            case 'get_sessions':
                if (!isCallRecordsAuthorized()) {
                    echo json_encode(array('error' => 'Μη εξουσιοδοτημένη πρόσβαση'));
                    exit;
                }
                $phone = $_GET['phone'] ?? '';
                $phonePath = $CALL_RECORDS_PATH . '/' . $phone;
                $sessions = getCallSessions($phonePath);
                echo json_encode($sessions);
                exit;
                
            case 'get_session_files':
                if (!isCallRecordsAuthorized()) {
                    echo json_encode(array('error' => 'Μη εξουσιοδοτημένη πρόσβαση'));
                    exit;
                }
                $phone = $_GET['phone'] ?? '';
                $session = $_GET['session'] ?? '';
                $sessionPath = $CALL_RECORDS_PATH . '/' . $phone . '/' . $session;
                $files = getSessionFiles($sessionPath);
                echo json_encode($files);
                exit;
                
            case 'get_recordings':
                if (!isCallRecordsAuthorized()) {
                    echo json_encode(array('error' => 'Μη εξουσιοδοτημένη πρόσβαση'));
                    exit;
                }
                $phone = $_GET['phone'] ?? '';
                $session = $_GET['session'] ?? '';
                $recordingsPath = $CALL_RECORDS_PATH . '/' . $phone . '/' . $session . '/recordings';
                $recordings = getRecordingFiles($recordingsPath);
                echo json_encode($recordings);
                exit;
                
            case 'get_analytics':
                if (!isAnalyticsAuthorized()) {
                    echo json_encode(array('error' => 'Μη εξουσιοδοτημένη πρόσβαση'));
                    exit;
                }
                $analyticsFiles = getAnalyticsFiles($ANALYTICS_PATH);
                echo json_encode($analyticsFiles);
                exit;
                
            case 'get_log_content':
                if (!isCallRecordsAuthorized()) {
                    echo json_encode(array('error' => 'Μη εξουσιοδοτημένη πρόσβαση'));
                    exit;
                }
                $filePath = $_GET['file'] ?? '';
                
                // Έλεγχος ασφάλειας
                $realPath = realpath($filePath);
                $allowedPaths = [
                    realpath($CALL_RECORDS_PATH),
                    realpath('/tmp/auto_register_call')
                ];
                
                $pathAllowed = false;
                foreach ($allowedPaths as $allowedPath) {
                    if ($allowedPath && strpos($realPath, $allowedPath) === 0) {
                        $pathAllowed = true;
                        break;
                    }
                }
                
                if ($pathAllowed && file_exists($filePath) && is_readable($filePath)) {
                    $content = file_get_contents($filePath);
                    echo json_encode(array('content' => $content, 'filename' => basename($filePath)));
                } else {
                    echo json_encode(array('error' => 'Το αρχείο δεν είναι προσβάσιμο'));
                }
                exit;
                
            case 'get_analytics_content':
                if (!isAnalyticsAuthorized()) {
                    echo json_encode(array('error' => 'Μη εξουσιοδοτημένη πρόσβαση'));
                    exit;
                }
                $filePath = $_GET['file'] ?? '';
                
                // Security check
                $realPath = realpath($filePath);
                $allowedPath = realpath($ANALYTICS_PATH);
                
                if ($allowedPath && strpos($realPath, $allowedPath) === 0 && 
                    file_exists($filePath) && is_readable($filePath)) {
                    $content = file_get_contents($filePath);
                    echo json_encode(array('content' => $content, 'filename' => basename($filePath)));
                } else {
                    echo json_encode(array('error' => 'Το αρχείο δεν είναι προσβάσιμο'));
                }
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(array('error' => $e->getMessage()));
        exit;
    }
}

// Διαχείριση παροχής αρχείων για ήχο
if (isset($_GET['serve_audio'])) {
    if (!isCallRecordsAuthorized()) {
        http_response_code(403);
        echo "Μη εξουσιοδοτημένη πρόσβαση";
        exit;
    }
    
    $filePath = $_GET['serve_audio'];
    
    // Έλεγχος ασφάλειας - διασφάλιση ότι η διαδρομή είναι εντός επιτρεπόμενων καταλόγων
    $realPath = realpath($filePath);
    $allowedPaths = [
        realpath($CALL_RECORDS_PATH),
        realpath('/tmp/auto_register_call')
    ];
    
    $pathAllowed = false;
    foreach ($allowedPaths as $allowedPath) {
        if ($allowedPath && strpos($realPath, $allowedPath) === 0) {
            $pathAllowed = true;
            break;
        }
    }
    
    if ($pathAllowed && file_exists($filePath)) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if ($extension === 'wav' || $extension === 'wav16') {
            // Καθαρισμός οποιασδήποτε προηγούμενης εξόδου
            if (ob_get_level()) ob_end_clean();
            
            header('Content-Type: audio/wav');
            header('Content-Length: ' . filesize($filePath));
            header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            header('Accept-Ranges: bytes');
            header('Cache-Control: public, max-age=3600');
            
            // Ανάγνωση αρχείου σε κομμάτια για καλύτερη απόδοση
            $handle = fopen($filePath, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
            exit;
        }
    }
    
    http_response_code(404);
    echo "Το αρχείο ήχου δεν βρέθηκε ή δεν είναι προσβάσιμο";
    exit;
}

// Διαχείριση παροχής αρχείων αναλυτικών
if (isset($_GET['serve_analytics'])) {
    if (!isAnalyticsAuthorized()) {
        http_response_code(403);
        echo "Μη εξουσιοδοτημένη πρόσβαση";
        exit;
    }
    
    $filePath = $_GET['serve_analytics'];
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'html') {
        header('Content-Type: text/html');
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        echo "Το αρχείο αναλυτικών δεν βρέθηκε ή δεν είναι προσβάσιμο";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχειριστής Καταγραφών Κλήσεων & Αναλυτικών</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffd700 0%, #ffeb3b 50%, #fff59d 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(255, 193, 7, 0.3);
            border: 2px solid #ffc107;
        }

        .header h1 {
            color: #4a5568;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-align: center;
        }

        .header p {
            color: #718096;
            text-align: center;
            font-size: 1.1rem;
        }

        .auth-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(255, 193, 7, 0.3);
            border: 2px solid #ffc107;
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .auth-form h3 {
            color: #4a5568;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .auth-form input[type="password"] {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #ffc107;
            border-radius: 10px;
            font-size: 1rem;
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }

        .auth-form input[type="password"]:focus {
            outline: none;
            border-color: #ff9800;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
        }

        .analytics-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2);
            border: 1px solid #ffc107;
        }

        .daily-reports-grid, .other-reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .report-viewer-content {
            background: white;
            border-radius: 8px;
            padding: 0;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #eee;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .report-viewer-iframe {
            flex: 1;
            border: none;
            width: 100%;
            height: 100%;
            min-height: 500px;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
            border-color: #ffc107;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .report-title {
            font-weight: 600;
            color: #2d3748;
        }

        .report-date {
            font-size: 0.8rem;
            color: #718096;
            white-space: nowrap;
            margin-left: 10px;
        }

        .report-actions {
            display: flex;
            justify-content: flex-end;
        }

        .report-viewer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ffc107;
        }

        .auth-form button {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .auth-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        }

        .auth-error {
            background: #fee;
            color: #c53030;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #feb2b2;
        }

        .logout-btn {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: #c53030;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .nav-tab {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #ffc107;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2);
            position: relative;
        }

        .nav-tab:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
        }

        .nav-tab.active {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: white;
            border-color: #ff9800;
        }

        .nav-tab.locked {
            background: #f5f5f5;
            color: #999;
            border-color: #ddd;
            cursor: not-allowed;
        }

        .nav-tab.locked:hover {
            transform: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .lock-icon {
            margin-left: 5px;
            opacity: 0.7;
        }

        .content-section {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(255, 193, 7, 0.3);
            border: 2px solid #ffc107;
        }

        .content-section.active {
            display: block;
        }

        .search-box {
            position: relative;
            margin-bottom: 30px;
        }

        .search-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #ffc107;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #ff9800;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #ffc107;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-result-item {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 1px solid #fff3cd;
        }

        .search-result-item:hover {
            background: #fff3cd;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2);
            transition: transform 0.3s ease;
            border: 1px solid #ffc107;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #fff3cd;
        }

        .card-title {
            font-size: 1.3rem;
            color: #2d3748;
            font-weight: 600;
        }

        .badge {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge.advanced {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }

        .sessions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .session-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.2);
            transition: all 0.3s ease;
            border-left: 4px solid #ffc107;
            border: 1px solid #ffc107;
        }

        .session-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255, 193, 7, 0.3);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .session-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            word-break: break-all;
        }

        .session-date {
            background: linear-gradient(135deg, #fff3cd, #ffecb3);
            color: #4a5568;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            border: 1px solid #ffc107;
        }

        .session-time {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .session-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .content-section-wrapper {
            margin: 25px 0;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .section-title {
            color: #2d3748;
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(255, 193, 7, 0.3);
        }

        .clean-audio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }

        .clean-audio-card {
            background: white;
            border: 2px solid #4caf50;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .clean-audio-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .audio-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .audio-icon {
            font-size: 1.2rem;
        }

        .audio-description {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 10px;
            font-style: italic;
            line-height: 1.3;
        }

        .audio-meta {
            color: #888;
            font-size: 0.8rem;
            margin-bottom: 12px;
        }

        .clean-play-btn {
            background: linear-gradient(135deg, #4caf50, #388e3c);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }

        .clean-play-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }

        .clean-play-btn.playing {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }

        .system-files-list, .directories-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .system-file-item, .directory-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .system-file-item:hover, .directory-item:hover {
            border-color: #ffc107;
            transform: translateX(5px);
        }

        .file-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .file-details {
            flex: 1;
            min-width: 0;
        }

        .file-meta-inline {
            color: #888;
            font-size: 0.8rem;
            display: block;
            margin-top: 2px;
        }

        .system-file-btn, .directory-btn {
            background: linear-gradient(135deg, #718096, #4a5568);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .system-file-btn:hover, .directory-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(74, 85, 104, 0.4);
        }

        .directory-btn {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }

        .directory-btn:hover {
            box-shadow: 0 3px 10px rgba(255, 152, 0, 0.4);
        }

        .file-card {
            background: #fffde7;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .file-card:hover {
            border-color: #ff9800;
            background: #fff8e1;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
        }

        .file-card.audio {
            border-color: #4caf50;
            background: #f1f8e9;
        }

        .file-card.audio:hover {
            border-color: #388e3c;
            background: #e8f5e8;
        }

        .file-card.directory {
            border-color: #ff9800;
            background: #fff3e0;
        }

        .file-card.directory:hover {
            border-color: #f57c00;
            background: #ffe0b2;
        }

        .file-card.log {
            border-color: #2196f3;
            background: #e3f2fd;
        }

        .file-card.log:hover {
            border-color: #1976d2;
            background: #bbdefb;
        }

        .file-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .file-icon-large {
            font-size: 2rem;
            flex-shrink: 0;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: 700;
            color: #2d3748;
            font-size: 1.1rem;
            word-break: break-word;
            margin-bottom: 5px;
        }

        .file-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 15px;
            gap: 10px;
        }

        .file-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #718096, #4a5568);
        }

        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(74, 85, 104, 0.4);
        }

        .btn-small {
            padding: 8px 14px;
            font-size: 0.85rem;
            border-radius: 6px;
        }

        .audio-section {
            margin-top: 30px;
            background: linear-gradient(135deg, #f1f8e9, #e8f5e8);
            border: 2px solid #4caf50;
            border-radius: 15px;
            padding: 25px;
        }

        .audio-section h4 {
            color: #2e7d32;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .audio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .audio-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.2);
            border: 2px solid #4caf50;
            transition: all 0.3s ease;
        }

        .audio-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }

        .audio-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .audio-info {
            flex: 1;
            min-width: 0;
        }

        .audio-name {
            font-weight: 700;
            color: #2d3748;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .audio-controls {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .play-btn {
            background: linear-gradient(135deg, #4caf50, #388e3c);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .play-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .play-btn.playing {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }

        .audio-player {
            margin-top: 15px;
            width: 100%;
            border-radius: 8px;
        }

        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: linear-gradient(135deg, #fff3cd, #ffecb3);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            border: 2px solid #ffc107;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .stat-label {
            color: #718096;
            font-weight: 600;
        }

        .stat-value {
            color: #2d3748;
            font-weight: 700;
            margin-left: 5px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .loading {
            text-align: center;
            padding: 50px;
            color: #718096;
            font-size: 1.1rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #4a5568;
        }

        .error-message {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            color: #c62828;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px solid #ef5350;
            font-weight: 500;
        }

        .log-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .log-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 85%;
            max-height: 85%;
            overflow: auto;
            position: relative;
            border: 2px solid #ffc107;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ffc107;
        }

        .log-close {
            background: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .log-close:hover {
            background: #c62828;
            transform: translateY(-2px);
        }

        .log-text {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
            border: 2px solid #dee2e6;
        }

        .recordings-section {
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            .sessions-grid,
            .analytics-grid {
                grid-template-columns: 1fr;
            }

            .file-grid,
            .audio-grid {
                grid-template-columns: 1fr;
            }

            .log-content {
                max-width: 95%;
                max-height: 90%;
                padding: 20px;
            }

            .stats-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 Διαχειριστής Καταγραφών Κλήσεων & Αναλυτικών</h1>
            <p>Ασφαλής πρόσβαση σε ηχογραφήσεις κλήσεων και αναλυτικές αναφορές</p>
        </div>

        <!-- Ενότητες εξουσιοδότησης -->
        <?php if (!isCallRecordsAuthorized() && !isAnalyticsAuthorized()): ?>
            <div class="auth-section">
                <div class="auth-form">
                    <h3>🔐 Απαιτείται Πρόσβαση</h3>
                    <p style="margin-bottom: 30px; color: #718096;">Παρακαλώ επιλέξτε μια ενότητα και εισάγετε τον κωδικό πρόσβασης για να συνεχίσετε.</p>
                    
                    <?php if (isset($auth_error)): ?>
                        <div class="auth-error"><?php echo htmlspecialchars($auth_error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" style="margin-bottom: 30px;">
                        <input type="hidden" name="auth_action" value="1">
                        <input type="hidden" name="section" value="call_records">
                        <h4 style="color: #4a5568; margin-bottom: 15px;">📞 Πρόσβαση σε Καταγραφές Κλήσεων</h4>
                        <input type="password" name="password" placeholder="Εισάγετε κωδικό καταγραφών κλήσεων" required>
                        <button type="submit">Πρόσβαση σε Καταγραφές Κλήσεων</button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="auth_action" value="1">
                        <input type="hidden" name="section" value="analytics">
                        <h4 style="color: #4a5568; margin-bottom: 15px;">📊 Πρόσβαση σε Αναλυτικά</h4>
                        <input type="password" name="password" placeholder="Εισάγετε κωδικό αναλυτικών" required>
                        <button type="submit">Πρόσβαση σε Αναλυτικά</button>
                    </form>
                </div>
            </div>
        <?php else: ?>

        <div class="nav-tabs">
            <button class="nav-tab <?php echo isCallRecordsAuthorized() ? 'active' : 'locked'; ?>" 
                    onclick="<?php echo isCallRecordsAuthorized() ? "switchTab('calls')" : ''; ?>">
                📞 Καταγραφές Κλήσεων
                <?php if (isCallRecordsAuthorized()): ?>
                    <a href="?logout=call_records" class="logout-btn">Αποσύνδεση</a>
                <?php else: ?>
                    <span class="lock-icon">🔒</span>
                <?php endif; ?>
            </button>
            <button class="nav-tab <?php echo isAnalyticsAuthorized() && !isCallRecordsAuthorized() ? 'active' : (isAnalyticsAuthorized() ? '' : 'locked'); ?>" 
                    onclick="<?php echo isAnalyticsAuthorized() ? "switchTab('analytics')" : ''; ?>">
                📊 Αναλυτικά
                <?php if (isAnalyticsAuthorized()): ?>
                    <a href="?logout=analytics" class="logout-btn">Αποσύνδεση</a>
                <?php else: ?>
                    <span class="lock-icon">🔒</span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Ενότητα Καταγραφών Κλήσεων -->
        <?php if (isCallRecordsAuthorized()): ?>
        <div id="calls-section" class="content-section active">
            <div class="search-box">
                <input type="text" class="search-input" id="phone-search" 
                       placeholder="🔍 Αναζήτηση αριθμού τηλεφώνου..." 
                       oninput="searchPhones(this.value)">
                <div class="search-results" id="search-results"></div>
            </div>
            
            <div id="selected-phone" class="empty-state">
                <h3>Αναζήτηση αριθμού τηλεφώνου</h3>
                <p>Εισάγετε έναν αριθμό τηλεφώνου στο παραπάνω πεδίο αναζήτησης για να δείτε τις καταγραφές κλήσεων</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ενότητα Αναλυτικών -->
        <?php if (isAnalyticsAuthorized()): ?>
        <div id="analytics-section" class="content-section <?php echo !isCallRecordsAuthorized() ? 'active' : ''; ?>">
            <div id="analytics-content">
                <div class="loading">Φόρτωση αρχείων αναλυτικών...</div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Modal Αρχείου Καταγραφής -->
    <div class="log-modal" id="log-modal">
        <div class="log-content">
            <div class="log-header">
                <h3 id="log-title">📄 Αρχείο Καταγραφής</h3>
                <button class="log-close" onclick="closeLogModal()">✕ Κλείσιμο</button>
            </div>
            <div class="log-text" id="log-text"></div>
        </div>
    </div>

    <script>
        let currentPhone = '';
        
        function switchTab(tab) {
            // Ενημέρωση καρτελών πλοήγησης
            document.querySelectorAll('.nav-tab').forEach(t => {
                if (!t.classList.contains('locked')) {
                    t.classList.remove('active');
                }
            });
            event.target.classList.add('active');
            
            // Ενημέρωση ενοτήτων περιεχομένου
            document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
            document.getElementById(tab + '-section').classList.add('active');
            
            if (tab === 'analytics') {
                loadAnalytics();
            }
        }
        
        function searchPhones(query) {
            const resultsDiv = document.getElementById('search-results');
            
            if (query.length < 3) {
                resultsDiv.style.display = 'none';
                return;
            }
            
            fetch(`?action=search_phone&query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(phones => {
                    if (phones.error) {
                        console.error('Σφάλμα αναζήτησης:', phones.error);
                        resultsDiv.style.display = 'none';
                        return;
                    }
                    
                    if (phones.length > 0) {
                        resultsDiv.innerHTML = phones.map(phone => 
                            `<div class="search-result-item" onclick="selectPhone('${phone}')">${phone}</div>`
                        ).join('');
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="search-result-item">Δεν βρέθηκαν αριθμοί τηλεφώνου που να ταιριάζουν με την αναζήτησή σας.</div>';
                        resultsDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Σφάλμα αναζήτησης:', error);
                    resultsDiv.style.display = 'none';
                });
        }
        
        function selectPhone(phone) {
            currentPhone = phone;
            document.getElementById('phone-search').value = phone;
            document.getElementById('search-results').style.display = 'none';
            loadPhoneSessions(phone);
        }
        
        function loadPhoneSessions(phone) {
            const container = document.getElementById('selected-phone');
            container.innerHTML = '<div class="loading">Φόρτωση συνεδριών κλήσης...</div>';
            
            fetch(`?action=get_sessions&phone=${encodeURIComponent(phone)}`)
                .then(response => response.json())
                .then(sessions => {
                    if (sessions.error) {
                        container.innerHTML = `
                            <div class="error-message">
                                Σφάλμα φόρτωσης συνεδριών: ${sessions.error}
                            </div>
                        `;
                        return;
                    }
                    
                    if (sessions.length > 0) {
                        container.innerHTML = `
                            <h2>📱 Τηλέφωνο: ${phone}</h2>
                            <div class="sessions-grid" id="sessions-grid"></div>
                        `;
                        
                        const grid = document.getElementById('sessions-grid');
                        sessions.forEach(session => {
                            const sessionCard = createSessionCard(phone, session);
                            grid.appendChild(sessionCard);
                        });
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <h3>Δεν βρέθηκαν συνεδρίες κλήσης</h3>
                                <p>Δεν υπάρχουν διαθέσιμες καταγραφές κλήσεων για τον αριθμό τηλεφώνου ${phone}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Σφάλμα φόρτωσης συνεδριών:', error);
                    container.innerHTML = `
                        <div class="error-message">
                            Σφάλμα δικτύου κατά τη φόρτωση συνεδριών. Παρακαλώ δοκιμάστε ξανά.
                        </div>
                    `;
                });
        }
        
        function createSessionCard(phone, session) {
            const card = document.createElement('div');
            card.className = 'session-card';
            
            const date = new Date(session.timestamp * 1000);
            
            card.innerHTML = `
                <div class="session-header">
                    <div class="session-title">📞 ${session.name}</div>
                    <div class="session-date">${date.toLocaleDateString('el-GR')}</div>
                </div>
                <div class="session-time">
                    <strong>Ώρα:</strong> ${date.toLocaleString('el-GR')}
                </div>
                <div class="session-actions">
                    <button class="btn btn-small" onclick="loadSessionDetails('${phone}', '${session.name}', this)">
                        📂 Προβολή Αρχείων
                    </button>
                </div>
                <div class="session-details" style="display: none; margin-top: 20px;"></div>
            `;
            
            return card;
        }
        
        function loadSessionDetails(phone, session, button) {
            const detailsDiv = button.parentElement.nextElementSibling;
            
            if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
                detailsDiv.innerHTML = '<div class="loading">Φόρτωση λεπτομερειών συνεδρίας...</div>';
                detailsDiv.style.display = 'block';
                button.innerHTML = '🔼 Απόκρυψη Αρχείων';
                
                // Load session files
                fetch(`?action=get_session_files&phone=${encodeURIComponent(phone)}&session=${encodeURIComponent(session)}`)
                    .then(response => response.json())
                    .then(files => {
                        if (files.error) {
                            detailsDiv.innerHTML = '<div class="error-message">Σφάλμα: ' + files.error + '</div>';
                            return;
                        }
                        detailsDiv.innerHTML = createSessionDetailsHTML(phone, session, files);
                        
                        // Auto-load recordings if recordings folder exists
                        const recordingsButton = detailsDiv.querySelector('button[onclick*="loadRecordings"]');
                        if (recordingsButton) {
                            setTimeout(() => {
                                recordingsButton.click();
                            }, 300);
                        }
                    })
                    .catch(error => {
                        console.error('Σφάλμα φόρτωσης λεπτομερειών συνεδρίας:', error);
                        detailsDiv.innerHTML = '<div class="error-message">Σφάλμα δικτύου κατά τη φόρτωση αρχείων. Παρακαλώ δοκιμάστε ξανά.</div>';
                    });
            } else {
                detailsDiv.style.display = 'none';
                button.innerHTML = '📂 Προβολή Αρχείων';
            }
        }
        
        function getWavFileDescription(fileName) {
            const descriptions = {
                'confirm.wav': 'Ήχος επιβεβαίωσης για όνομα, αφετηρία και προορισμό',
                'register.wav': 'Ήχος που ακούει ο χρήστης όταν καταγράφει την κλήση',
                'user_prompt.wav': 'Ήχος που ακούει ο χρήστης όταν βρίσκουμε το όνομα και τη διεύθυνση αφετηρίας από τον αριθμό τηλεφώνου'
            };
            return descriptions[fileName] || '';
        }

        function getRecordingFileDescription(fileName) {
            const descriptions = {
                'name_1.wav16': 'Χρήστης λέει το όνομά του - προσπάθεια 1',
                'name_2.wav16': 'Χρήστης λέει το όνομά του - προσπάθεια 2',
                'pickup_1.wav16': 'Χρήστης λέει τη διεύθυνση αφετηρίας - προσπάθεια 1',
                'pickup_2.wav16': 'Χρήστης λέει τη διεύθυνση αφετηρίας - προσπάθεια 2',
                'dest_1.wav16': 'Χρήστης λέει τη διεύθυνση προορισμού - προσπάθεια 1',
                'dest_2.wav16': 'Χρήστης λέει τη διεύθυνση προορισμού - προσπάθεια 2'
            };
            return descriptions[fileName] || '';
        }
        
        function createSessionDetailsHTML(phone, session, files) {
            // Separate files by type
            const audioFiles = files.filter(f => f.is_audio);
            const logFiles = files.filter(f => f.is_log);
            const jsonFiles = files.filter(f => f.is_json);
            const directoryFiles = files.filter(f => f.type === 'directory');
            const otherFiles = files.filter(f => !f.is_audio && !f.is_log && !f.is_json && f.type !== 'directory');
            
            // Combine non-audio files for system files section
            const systemFiles = [...logFiles, ...jsonFiles, ...otherFiles];
            
            let html = '';
            
            // Session statistics - simplified
            html += `
                <div class="stats-row">
                    <div class="stat-item">
                        <span class="stat-label">📂 Συνολικά:</span>
                        <span class="stat-value">${files.length}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">🎵 Ήχος:</span>
                        <span class="stat-value">${audioFiles.length}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">⚙️ Σύστημα:</span>
                        <span class="stat-value">${systemFiles.length}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">📁 Φάκελοι:</span>
                        <span class="stat-value">${directoryFiles.length}</span>
                    </div>
                </div>
            `;
            
            // Audio files section - cleaner design
            if (audioFiles.length > 0) {
                html += `
                    <div class="content-section-wrapper">
                        <h4 class="section-title">🎵 Αρχεία Συστήματος Ήχου</h4>
                        <div class="clean-audio-grid">
                `;
                
                audioFiles.forEach(file => {
                    const size = formatFileSize(file.size);
                    const date = new Date(file.modified * 1000).toLocaleString('el-GR');
                    const description = getWavFileDescription(file.name);
                    
                    html += `
                        <div class="clean-audio-card">
                            <div class="audio-title">
                                <span class="audio-icon">🎵</span>
                                <strong>${escapeHtml(file.name)}</strong>
                            </div>
                            <div class="audio-description">${description}</div>
                            <div class="audio-meta">${size} • ${date}</div>
                            <button class="clean-play-btn" onclick="playAudio('${escapeHtml(file.path)}', this)">
                                ▶️ Αναπαραγωγή
                            </button>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            // System files section (non-audio, non-directory)
            if (systemFiles.length > 0) {
                html += `
                    <div class="content-section-wrapper">
                        <h4 class="section-title">⚙️ Αρχεία Συστήματος</h4>
                        <div class="system-files-list">
                `;
                
                systemFiles.forEach(file => {
                    const size = formatFileSize(file.size);
                    const date = new Date(file.modified * 1000).toLocaleString('el-GR');
                    let icon = '📄';
                    let actionBtn = '';
                    
                    if (file.is_json) {
                        icon = '⚙️';
                        actionBtn = `<button class="system-file-btn" onclick="viewJsonFile('${escapeHtml(file.path)}', '${escapeHtml(file.name)}')">Προβολή</button>`;
                    } else if (file.is_log) {
                        icon = '📋';
                        actionBtn = `<button class="system-file-btn" onclick="viewLogFile('${escapeHtml(file.path)}', '${escapeHtml(file.name)}')">Προβολή</button>`;
                    }
                    
                    html += `
                        <div class="system-file-item">
                            <span class="file-icon">${icon}</span>
                            <div class="file-details">
                                <strong>${escapeHtml(file.name)}</strong>
                                <span class="file-meta-inline">${size} • ${date}</span>
                            </div>
                            ${actionBtn}
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            // Directories section
            if (directoryFiles.length > 0) {
                html += `
                    <div class="content-section-wrapper">
                        <h4 class="section-title">📁 Φάκελοι</h4>
                        <div class="directories-list">
                `;
                
                directoryFiles.forEach(file => {
                    const date = new Date(file.modified * 1000).toLocaleString('el-GR');
                    
                    html += `
                        <div class="directory-item">
                            <span class="file-icon">📁</span>
                            <div class="file-details">
                                <strong>${escapeHtml(file.name)}</strong>
                                <span class="file-meta-inline">${date}</span>
                            </div>
                            <button class="directory-btn" onclick="loadRecordings('${escapeHtml(phone)}', '${escapeHtml(session)}', this)">
                                Άνοιγμα
                            </button>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            html += '<div class="recordings-section"></div>';
            
            return html;
        }
        
        function viewLogFile(filePath, fileName) {
            fetch(`?action=get_log_content&file=${encodeURIComponent(filePath)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Σφάλμα φόρτωσης αρχείου: ' + data.error);
                        return;
                    }
                    
                    document.getElementById('log-title').textContent = '📄 ' + fileName;
                    document.getElementById('log-text').textContent = data.content;
                    document.getElementById('log-modal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Σφάλμα φόρτωσης αρχείου:', error);
                    alert('Σφάλμα φόρτωσης αρχείου. Παρακαλώ δοκιμάστε ξανά.');
                });
        }

        function viewJsonFile(filePath, fileName) {
            fetch(`?action=get_log_content&file=${encodeURIComponent(filePath)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Σφάλμα φόρτωσης JSON αρχείου: ' + data.error);
                        return;
                    }
                    
                    // Try to format JSON nicely
                    let content = data.content;
                    try {
                        const jsonData = JSON.parse(content);
                        content = JSON.stringify(jsonData, null, 2);
                    } catch (e) {
                        // If not valid JSON, show as is
                        console.log('Not valid JSON, showing as text');
                    }
                    
                    document.getElementById('log-title').textContent = '🗂️ ' + fileName;
                    document.getElementById('log-text').textContent = content;
                    document.getElementById('log-modal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Σφάλμα φόρτωσης JSON αρχείου:', error);
                    alert('Σφάλμα φόρτωσης JSON αρχείου. Παρακαλώ δοκιμάστε ξανά.');
                });
        }
        
        function closeLogModal() {
            document.getElementById('log-modal').style.display = 'none';
        }
        
        function loadRecordings(phone, session, button) {
            const recordingsDiv = button.closest('.session-details').querySelector('.recordings-section');
            
            recordingsDiv.innerHTML = '<div class="loading">Φόρτωση ηχογραφήσεων...</div>';
            
            fetch(`?action=get_recordings&phone=${encodeURIComponent(phone)}&session=${encodeURIComponent(session)}`)
                .then(response => response.json())
                .then(recordings => {
                    if (recordings.error) {
                        recordingsDiv.innerHTML = '<div class="error-message">Σφάλμα: ' + recordings.error + '</div>';
                        return;
                    }
                    
                    if (recordings.length > 0) {
                        let html = `
                            <div class="content-section-wrapper">
                                <h4 class="section-title">🎵 Ηχογραφήσεις Χρήστη (${recordings.length} αρχεία)</h4>
                                <div class="clean-audio-grid">
                        `;
                        
                        recordings.forEach(recording => {
                            const size = formatFileSize(recording.size);
                            const date = new Date(recording.modified * 1000).toLocaleString('el-GR');
                            const description = getRecordingFileDescription(recording.name);
                            
                            html += `
                                <div class="clean-audio-card">
                                    <div class="audio-title">
                                        <span class="audio-icon">🎤</span>
                                        <strong>${escapeHtml(recording.name)}</strong>
                                    </div>
                                    <div class="audio-description">${description}</div>
                                    <div class="audio-meta">${size} • ${date}</div>
                                    <button class="clean-play-btn" onclick="playAudio('${escapeHtml(recording.path)}', this)">
                                        ▶️ Αναπαραγωγή
                                    </button>
                                </div>
                            `;
                        });
                        
                        html += `
                                </div>
                            </div>
                        `;
                        recordingsDiv.innerHTML = html;
                    } else {
                        recordingsDiv.innerHTML = `
                            <div class="content-section-wrapper">
                                <p style="text-align: center; color: #718096;">📁 Δεν βρέθηκαν ηχογραφήσεις σε αυτόν τον φάκελο.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    recordingsDiv.innerHTML = '<div class="error-message">Σφάλμα δικτύου: ' + error.message + '</div>';
                });
        }
        
        function playAudio(filePath, button) {
            // Remove existing audio players in this container
            const container = button.closest('.audio-item, .file-card, .clean-audio-card');
            const existingPlayer = container.querySelector('audio');
            
            if (existingPlayer) {
                existingPlayer.remove();
                button.textContent = '▶️ Αναπαραγωγή';
                button.classList.remove('playing');
                return;
            }
            
            // Create new audio player
            const audioPlayer = document.createElement('audio');
            audioPlayer.className = 'audio-player';
            audioPlayer.controls = true;
            audioPlayer.preload = 'none';
            
            // Set source with cache busting and error handling
            const audioSrc = `?serve_audio=${encodeURIComponent(filePath)}&t=${Date.now()}`;
            audioPlayer.src = audioSrc;
            
            // Add event listeners
            audioPlayer.addEventListener('loadstart', () => {
                button.textContent = '⏳ Φόρτωση...';
                button.disabled = true;
            });
            
            audioPlayer.addEventListener('canplay', () => {
                button.textContent = '⏸️ Παύση';
                button.disabled = false;
                button.classList.add('playing');
            });
            
            audioPlayer.addEventListener('ended', () => {
                button.textContent = '▶️ Αναπαραγωγή';
                button.classList.remove('playing');
                audioPlayer.remove();
            });
            
            audioPlayer.addEventListener('error', (e) => {
                console.error('Audio error:', e);
                button.textContent = '❌ Σφάλμα';
                button.disabled = false;
                button.classList.remove('playing');
                alert('Σφάλμα φόρτωσης αρχείου ήχου. Το αρχείο μπορεί να είναι κατεστραμμένο ή μη προσβάσιμο.');
                audioPlayer.remove();
            });
            
            // Add player to container
            container.appendChild(audioPlayer);
            
            // Load and play
            audioPlayer.load();
            audioPlayer.play().catch(e => {
                console.error('Playback error:', e);
                button.textContent = '❌ Σφάλμα';
                button.classList.remove('playing');
                alert('Σφάλμα αναπαραγωγής αρχείου ήχου.');
            });
        }
        
        function loadAnalytics() {
            const container = document.getElementById('analytics-content');
            container.innerHTML = '<div class="loading">Φόρτωση αρχείων αναλυτικών...</div>';
            
            fetch('?action=get_analytics')
                .then(response => response.json())
                .then(files => {
                    if (files.error) {
                        container.innerHTML = `
                            <div class="error-message">
                                Σφάλμα φόρτωσης αναλυτικών: ${files.error}
                            </div>
                            <div class="empty-state">
                                <h3>Αδυναμία φόρτωσης αναλυτικών</h3>
                                <p>Παρακαλώ δοκιμάστε να ανανεώσετε τη σελίδα ή επικοινωνήστε με την υποστήριξη εάν το πρόβλημα συνεχίζει.</p>
                            </div>
                        `;
                        return;
                    }
                    
                    if (files.length > 0) {
                        container.innerHTML = createAnalyticsHTML(files);
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <h3>Δεν βρέθηκαν αρχεία αναλυτικών</h3>
                                <p>Δεν υπάρχουν διαθέσιμες αναλυτικές αναφορές αυτή τη στιγμή.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    container.innerHTML = `
                        <div class="error-message">
                            Σφάλμα δικτύου: ${error.message}
                        </div>
                    `;
                });
        }
        
        function createAnalyticsHTML(files) {
            // Separate daily reports from other files
            const dailyReports = files.filter(f => f.is_daily);
            const otherReports = files.filter(f => !f.is_daily);
            
            let html = `
                <h2>📊 Αναλυτικές Αναφορές</h2>
                <p style="margin-bottom: 30px; color: #718096;">Βρέθηκαν ${files.length} αναλυτικές αναφορές</p>
                
                <div class="analytics-section">
                    <h3>📅 Ημερήσιες Αναφορές</h3>
            `;
            
            if (dailyReports.length > 0) {
                html += `<div class="daily-reports-grid">`;
                
                dailyReports.forEach(report => {
                    const date = report.date || formatDate(report.modified);
                    const modifiedDate = new Date(report.modified * 1000).toLocaleString('el-GR');
                    
                    html += `
                        <div class="report-card">
                            <div class="report-header">
                                <div class="report-title">📅 Αναφορά ${date}</div>
                                <div class="report-date">${modifiedDate}</div>
                            </div>
                            <div class="report-actions">
                                <button class="btn btn-small" onclick="viewAnalyticsReport('${report.path}', 'Αναφορά ${date}')">
                                    👁️ Προβολή
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                html += `</div>`;
            } else {
                html += `
                    <div class="empty-state" style="margin: 20px 0;">
                        <p>Δεν βρέθηκαν ημερήσιες αναφορές</p>
                    </div>
                `;
            }
            
            html += `</div>`; // Close analytics-section
            
            // Other reports section
            if (otherReports.length > 0) {
                html += `
                    <div class="analytics-section" style="margin-top: 30px;">
                        <h3>📌 Άλλες Αναφορές</h3>
                        <div class="other-reports-grid">
                `;
                
                otherReports.forEach(report => {
                    const modifiedDate = new Date(report.modified * 1000).toLocaleString('el-GR');
                    
                    html += `
                        <div class="report-card">
                            <div class="report-header">
                                <div class="report-title">📄 ${report.name.replace('.html', '')}</div>
                                <div class="report-date">${modifiedDate}</div>
                            </div>
                            <div class="report-actions">
                                <button class="btn btn-small" onclick="viewAnalyticsReport('${report.path}', '${report.name.replace('.html', '')}')">
                                    👁️ Προβολή
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            // Add the report viewer container
            html += `
            <div id="report-viewer-container" style="display: none; margin-top: 30px;">
                <div class="report-viewer-header">
                    <h3 id="report-viewer-title"></h3>
                    <button class="btn btn-small" onclick="closeReportViewer()">✕ Κλείσιμο</button>
                </div>
                <div id="report-viewer-content" class="report-viewer-content"></div>
            </div>
        `;
            
            return html;
        }
        
        function formatDate(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString('el-GR');
        }
        
        function closeReportViewer() {
            document.getElementById('report-viewer-container').style.display = 'none';
        }
        
        function viewAnalyticsReport(filePath, title) {
            const container = document.getElementById('report-viewer-container');
            const contentDiv = document.getElementById('report-viewer-content');
            const titleDiv = document.getElementById('report-viewer-title');
            
            container.style.display = 'block';
            titleDiv.textContent = title;
            
            contentDiv.innerHTML = '<div class="loading">Φόρτωση αναφοράς...</div>';
            
            container.scrollIntoView({ behavior: 'smooth' });
            
            const iframe = document.createElement('iframe');
            iframe.style.width = '100%';
            iframe.style.height = '90vh';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '8px';
            iframe.style.backgroundColor = 'white';
            
            fetch(`?action=get_analytics_content&file=${encodeURIComponent(filePath)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        contentDiv.innerHTML = `<div class="error-message">Σφάλμα: ${data.error}</div>`;
                        return;
                    }
                    
                    contentDiv.innerHTML = '';
                    contentDiv.appendChild(iframe);
                    
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write(data.content);
                    iframeDoc.close();
                })
                .catch(error => {
                    contentDiv.innerHTML = `<div class="error-message">Σφάλμα δικτύου: ${error.message}</div>`;
                });
        }
        
        function getFileIcon(type, name, isLog, isJson) {
            if (type === 'directory') return '📁';
            if (type === 'wav' || type === 'wav16') return '🎵';
            if (isLog || type === 'txt' || name === 'log.txt') return '📋';
            if (isJson || type === 'json') return '⚙️';
            if (name === 'recordings') return '📁';
            return '📄';
        }
        
        function formatFileSize(size) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let power = size > 0 ? Math.floor(Math.log(size) / Math.log(1024)) : 0;
            return (size / Math.pow(1024, power)).toFixed(2) + ' ' + units[power];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-box')) {
                document.getElementById('search-results').style.display = 'none';
            }
        });

        // Close modal when clicking outside
        document.getElementById('log-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogModal();
            }
        });

        // Initialize analytics if user has access and no call records access
        <?php if (isAnalyticsAuthorized() && !isCallRecordsAuthorized()): ?>
        document.addEventListener('DOMContentLoaded', function() {
            loadAnalytics();
        });
        <?php endif; ?>
    </script>
</body>
</html>