<?php
/**
 * Aggregated Analytics Dashboard
 *
 * Fetches and displays analytics from multiple AGI servers
 * Aggregates call statistics across all configured extensions and servers
 *
 * Features:
 * - Multi-server data aggregation
 * - Monthly analytics filtering
 * - Excel export functionality
 * - Visual statistics dashboard
 *
 * @version 1.0.0
 */

// Set timezone
date_default_timezone_set('Europe/Athens');

// Server and Extension Configuration
// Each server has one or more extensions to monitor
$SERVER_CONFIGS = [
    [
        'name' => 'Global',
        'url' => 'https://www.iqtaxi.com/AsteriskConsoles/AsteriskGlobal/api/proxy/agi_analytics.php',
        'extensions' => [
            ['id' => '7001', 'label' => 'IqTaxi-Demo']
        ]
    ],
    [
        'name' => 'Cosmos',
        'url' => 'https://www.iqtaxi.com/AsteriskConsoles/AsteriskCosmos/api/proxy/agi_analytics.php',
        'extensions' => [
            ['id' => '4037', 'label' => 'Cosmos']
        ]
    ],
    [
        'name' => 'Perakis',
        'url' => 'https://www.iqtaxi.com/AsteriskConsoles/AsteriskPerakis/api/proxy/agi_analytics.php',
        'extensions' => [
            ['id' => '4031', 'label' => 'Hermis-Peireas'],
            ['id' => '4036', 'label' => 'Parthenon'],
            ['id' => '4037', 'label' => 'Chania'],
            ['id' => '4038', 'label' => 'Iraklio-Candia']
        ]
    ]
];

/**
 * Fetch analytics data from a remote server
 */
function fetchAnalytics($serverUrl, $extension, $dateFrom, $dateTo) {
    // Build URL with endpoint parameter (not path)
    $url = $serverUrl . '?' . http_build_query([
        'endpoint' => 'analytics',
        'extension' => $extension,
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 seconds to establish connection
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        error_log("Failed to fetch from $url: $error (HTTP $httpCode)");
        error_log("Response body: " . substr($response, 0, 500));
        return [
            'error' => true,
            'message' => $error ?: "HTTP $httpCode",
            'url' => $url,
            'response' => substr($response, 0, 200)
        ];
    }

    $data = json_decode($response, true);

    // Log if JSON decode fails
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for $url: " . json_last_error_msg());
        error_log("Response: " . substr($response, 0, 500));
        return [
            'error' => true,
            'message' => 'JSON decode error: ' . json_last_error_msg(),
            'url' => $url,
            'response' => substr($response, 0, 200)
        ];
    }

    return $data;
}

/**
 * Generate Excel file from aggregated data
 */
function generateExcel($aggregatedData, $month, $year) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="aggregated_analytics_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    echo '<x:Name>Analytics</x:Name>';
    echo '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    echo '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '</head><body>';

    echo '<table border="1">';
    echo '<tr style="background-color: #4CAF50; color: white; font-weight: bold;">';
    echo '<th>Server</th>';
    echo '<th>Extension</th>';
    echo '<th>Extension Name</th>';
    echo '<th>Total Calls</th>';
    echo '<th>Successful Calls</th>';
    echo '<th>Success Rate (%)</th>';
    echo '<th>Hangup Calls</th>';
    echo '<th>Operator Transfers</th>';
    echo '<th>Reservation Calls</th>';
    echo '<th>Avg Duration (s)</th>';
    echo '<th>Unique Callers</th>';
    echo '</tr>';

    // Individual server/extension rows
    foreach ($aggregatedData['details'] as $detail) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($detail['server']) . '</td>';
        echo '<td>' . htmlspecialchars($detail['extension']) . '</td>';
        echo '<td>' . htmlspecialchars($detail['extension_label']) . '</td>';
        echo '<td>' . number_format($detail['total_calls']) . '</td>';
        echo '<td>' . number_format($detail['successful_calls']) . '</td>';
        echo '<td>' . number_format($detail['success_rate'], 2) . '%</td>';
        echo '<td>' . number_format($detail['hangup_calls']) . '</td>';
        echo '<td>' . number_format($detail['operator_transfers']) . '</td>';
        echo '<td>' . number_format($detail['reservation_calls']) . '</td>';
        echo '<td>' . number_format($detail['avg_duration'], 2) . '</td>';
        echo '<td>' . number_format($detail['unique_callers']) . '</td>';
        echo '</tr>';
    }

    // Total summary row
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<td colspan="3">TOTAL</td>';
    echo '<td>' . number_format($aggregatedData['totals']['total_calls']) . '</td>';
    echo '<td>' . number_format($aggregatedData['totals']['successful_calls']) . '</td>';
    echo '<td>' . number_format($aggregatedData['totals']['success_rate'], 2) . '%</td>';
    echo '<td>' . number_format($aggregatedData['totals']['hangup_calls']) . '</td>';
    echo '<td>' . number_format($aggregatedData['totals']['operator_transfers']) . '</td>';
    echo '<td>' . number_format($aggregatedData['totals']['reservation_calls']) . '</td>';
    echo '<td>' . number_format($aggregatedData['totals']['avg_duration'], 2) . '</td>';
    echo '<td>' . number_format($aggregatedData['totals']['unique_callers']) . '</td>';
    echo '</tr>';

    echo '</table>';
    echo '</body></html>';
    exit;
}

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'fetch') {
        $month = intval($_GET['month'] ?? date('n'));
        $year = intval($_GET['year'] ?? date('Y'));

        // Calculate date range for the selected month
        $dateFrom = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
        $dateTo = date('Y-m-d', mktime(23, 59, 59, $month + 1, 0, $year));

        $aggregatedData = [
            'totals' => [
                'total_calls' => 0,
                'successful_calls' => 0,
                'hangup_calls' => 0,
                'operator_transfers' => 0,
                'reservation_calls' => 0,
                'avg_duration' => 0,
                'unique_callers' => 0,
                'success_rate' => 0
            ],
            'details' => []
        ];

        $totalDuration = 0;
        $callsWithDuration = 0;

        // Fetch data from each server/extension
        foreach ($SERVER_CONFIGS as $server) {
            foreach ($server['extensions'] as $ext) {
                $data = fetchAnalytics($server['url'], $ext['id'], $dateFrom, $dateTo);

                // Check if there was an error
                if (isset($data['error']) && $data['error'] === true) {
                    // Add error detail row with full error info
                    $aggregatedData['details'][] = [
                        'server' => $server['name'],
                        'extension' => $ext['id'],
                        'extension_label' => $ext['label'],
                        'total_calls' => 0,
                        'successful_calls' => 0,
                        'success_rate' => 0,
                        'hangup_calls' => 0,
                        'operator_transfers' => 0,
                        'reservation_calls' => 0,
                        'avg_duration' => 0,
                        'unique_callers' => 0,
                        'status' => 'error',
                        'error' => $data['message'] ?? 'Failed to fetch data',
                        'error_url' => $data['url'] ?? '',
                        'error_response' => $data['response'] ?? ''
                    ];
                    continue;
                }

                if ($data && isset($data['summary'])) {
                    $summary = $data['summary'];

                    // Add to totals
                    $aggregatedData['totals']['total_calls'] += intval($summary['total_calls'] ?? 0);
                    $aggregatedData['totals']['successful_calls'] += intval($summary['successful_calls'] ?? 0);
                    $aggregatedData['totals']['hangup_calls'] += intval($summary['hangup_calls'] ?? 0);
                    $aggregatedData['totals']['operator_transfers'] += intval($summary['operator_transfers'] ?? 0);
                    $aggregatedData['totals']['reservation_calls'] += intval($summary['reservation_calls'] ?? 0);
                    $aggregatedData['totals']['unique_callers'] += intval($summary['unique_callers'] ?? 0);

                    // Track duration for averaging
                    if (isset($summary['avg_duration']) && $summary['avg_duration'] > 0) {
                        $totalDuration += floatval($summary['avg_duration']) * intval($summary['total_calls'] ?? 0);
                        $callsWithDuration += intval($summary['total_calls'] ?? 0);
                    }

                    // Add detail row
                    $aggregatedData['details'][] = [
                        'server' => $server['name'],
                        'extension' => $ext['id'],
                        'extension_label' => $ext['label'],
                        'total_calls' => intval($summary['total_calls'] ?? 0),
                        'successful_calls' => intval($summary['successful_calls'] ?? 0),
                        'success_rate' => floatval($summary['success_rate'] ?? 0),
                        'hangup_calls' => intval($summary['hangup_calls'] ?? 0),
                        'operator_transfers' => intval($summary['operator_transfers'] ?? 0),
                        'reservation_calls' => intval($summary['reservation_calls'] ?? 0),
                        'avg_duration' => floatval($summary['avg_duration'] ?? 0),
                        'unique_callers' => intval($summary['unique_callers'] ?? 0),
                        'status' => 'success'
                    ];
                } else {
                    // Add error detail row
                    $aggregatedData['details'][] = [
                        'server' => $server['name'],
                        'extension' => $ext['id'],
                        'extension_label' => $ext['label'],
                        'total_calls' => 0,
                        'successful_calls' => 0,
                        'success_rate' => 0,
                        'hangup_calls' => 0,
                        'operator_transfers' => 0,
                        'reservation_calls' => 0,
                        'avg_duration' => 0,
                        'unique_callers' => 0,
                        'status' => 'error',
                        'error' => 'No data returned from server'
                    ];
                }
            }
        }

        // Calculate overall average duration
        if ($callsWithDuration > 0) {
            $aggregatedData['totals']['avg_duration'] = $totalDuration / $callsWithDuration;
        }

        // Calculate overall success rate
        if ($aggregatedData['totals']['total_calls'] > 0) {
            $aggregatedData['totals']['success_rate'] =
                ($aggregatedData['totals']['successful_calls'] / $aggregatedData['totals']['total_calls']) * 100;
        }

        $aggregatedData['date_range'] = [
            'from' => $dateFrom,
            'to' => $dateTo,
            'month' => $month,
            'year' => $year
        ];

        echo json_encode($aggregatedData);
        exit;
    }
}

/**
 * Fetch all data and return aggregated results
 */
function fetchAllData($month, $year) {
    global $SERVER_CONFIGS;

    // Calculate date range for the selected month
    $dateFrom = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
    $dateTo = date('Y-m-d', mktime(23, 59, 59, $month + 1, 0, $year));

    $aggregatedData = [
        'totals' => [
            'total_calls' => 0,
            'successful_calls' => 0,
            'hangup_calls' => 0,
            'operator_transfers' => 0,
            'reservation_calls' => 0,
            'avg_duration' => 0,
            'unique_callers' => 0,
            'success_rate' => 0
        ],
        'details' => []
    ];

    $totalDuration = 0;
    $callsWithDuration = 0;

    // Fetch data from each server/extension
    foreach ($SERVER_CONFIGS as $server) {
        foreach ($server['extensions'] as $ext) {
            $data = fetchAnalytics($server['url'], $ext['id'], $dateFrom, $dateTo);

            // Check if there was an error
            if (isset($data['error']) && $data['error'] === true) {
                // Add error detail row with full error info
                $aggregatedData['details'][] = [
                    'server' => $server['name'],
                    'extension' => $ext['id'],
                    'extension_label' => $ext['label'],
                    'total_calls' => 0,
                    'successful_calls' => 0,
                    'success_rate' => 0,
                    'hangup_calls' => 0,
                    'operator_transfers' => 0,
                    'reservation_calls' => 0,
                    'avg_duration' => 0,
                    'unique_callers' => 0,
                    'status' => 'error',
                    'error' => $data['message'] ?? 'Failed to fetch data'
                ];
                continue;
            }

            if ($data && isset($data['summary'])) {
                $summary = $data['summary'];

                // Add to totals
                $aggregatedData['totals']['total_calls'] += intval($summary['total_calls'] ?? 0);
                $aggregatedData['totals']['successful_calls'] += intval($summary['successful_calls'] ?? 0);
                $aggregatedData['totals']['hangup_calls'] += intval($summary['hangup_calls'] ?? 0);
                $aggregatedData['totals']['operator_transfers'] += intval($summary['operator_transfers'] ?? 0);
                $aggregatedData['totals']['reservation_calls'] += intval($summary['reservation_calls'] ?? 0);
                $aggregatedData['totals']['unique_callers'] += intval($summary['unique_callers'] ?? 0);

                // Track duration for averaging
                if (isset($summary['avg_duration']) && $summary['avg_duration'] > 0) {
                    $totalDuration += floatval($summary['avg_duration']) * intval($summary['total_calls'] ?? 0);
                    $callsWithDuration += intval($summary['total_calls'] ?? 0);
                }

                // Add detail row
                $aggregatedData['details'][] = [
                    'server' => $server['name'],
                    'extension' => $ext['id'],
                    'extension_label' => $ext['label'],
                    'total_calls' => intval($summary['total_calls'] ?? 0),
                    'successful_calls' => intval($summary['successful_calls'] ?? 0),
                    'success_rate' => floatval($summary['success_rate'] ?? 0),
                    'hangup_calls' => intval($summary['hangup_calls'] ?? 0),
                    'operator_transfers' => intval($summary['operator_transfers'] ?? 0),
                    'reservation_calls' => intval($summary['reservation_calls'] ?? 0),
                    'avg_duration' => floatval($summary['avg_duration'] ?? 0),
                    'unique_callers' => intval($summary['unique_callers'] ?? 0),
                    'status' => 'success'
                ];
            } else {
                // Add error detail row
                $aggregatedData['details'][] = [
                    'server' => $server['name'],
                    'extension' => $ext['id'],
                    'extension_label' => $ext['label'],
                    'total_calls' => 0,
                    'successful_calls' => 0,
                    'success_rate' => 0,
                    'hangup_calls' => 0,
                    'operator_transfers' => 0,
                    'reservation_calls' => 0,
                    'avg_duration' => 0,
                    'unique_callers' => 0,
                    'status' => 'error',
                    'error' => 'No data returned from server'
                ];
            }
        }
    }

    // Calculate overall average duration
    if ($callsWithDuration > 0) {
        $aggregatedData['totals']['avg_duration'] = $totalDuration / $callsWithDuration;
    }

    // Calculate overall success rate
    if ($aggregatedData['totals']['total_calls'] > 0) {
        $aggregatedData['totals']['success_rate'] =
            ($aggregatedData['totals']['successful_calls'] / $aggregatedData['totals']['total_calls']) * 100;
    }

    return $aggregatedData;
}

// Handle Excel export - can be called directly with month/year params
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $month = intval($_GET['month'] ?? date('n'));
    $year = intval($_GET['year'] ?? date('Y'));

    $aggregatedData = fetchAllData($month, $year);
    generateExcel($aggregatedData, $month, $year);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'SF Pro Text', 'Helvetica Neue', sans-serif;
            background: #f5f5f7;
            min-height: 100vh;
            padding: 0;
            color: #1d1d1f;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 22px;
        }

        /* Navigation Bar */
        nav {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 1000;
            animation: slideDown 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.022em;
            color: #1d1d1f;
        }

        /* Hero Section */
        header {
            padding: 80px 0 60px;
            text-align: center;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        h1 {
            font-size: 56px;
            line-height: 1.07143;
            font-weight: 600;
            letter-spacing: -0.005em;
            color: #1d1d1f;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 21px;
            line-height: 1.381;
            font-weight: 400;
            letter-spacing: 0.011em;
            color: #6e6e73;
        }

        /* Controls Section */
        .controls {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            margin: 48px auto;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: flex-end;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
            min-width: 160px;
        }

        .control-group label {
            font-size: 14px;
            font-weight: 500;
            letter-spacing: -0.016em;
            color: #6e6e73;
        }

        select {
            padding: 12px 16px;
            border: 1px solid #d2d2d7;
            border-radius: 12px;
            font-size: 17px;
            font-family: inherit;
            color: #1d1d1f;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            -webkit-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%236e6e73' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        select:hover {
            border-color: #86868b;
        }

        select:focus {
            border-color: #0071e3;
            outline: none;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        button {
            padding: 12px 24px;
            border: none;
            border-radius: 980px;
            font-size: 17px;
            font-weight: 400;
            font-family: inherit;
            color: #ffffff;
            background: #0071e3;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        button:hover {
            background: #0077ed;
            transform: scale(1.02);
        }

        button:active {
            transform: scale(0.98);
        }

        button.export-btn {
            background: #1d1d1f;
        }

        button.export-btn:hover {
            background: #424245;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        button:disabled:hover {
            background: #0071e3;
            transform: none;
        }

        button.export-btn:disabled:hover {
            background: #1d1d1f;
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin: 48px auto;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .card:nth-child(1) { animation-delay: 0.2s; }
        .card:nth-child(2) { animation-delay: 0.3s; }
        .card:nth-child(3) { animation-delay: 0.4s; }
        .card:nth-child(4) { animation-delay: 0.5s; }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        }

        .card-title {
            font-size: 14px;
            font-weight: 500;
            letter-spacing: -0.016em;
            color: #6e6e73;
            margin-bottom: 12px;
        }

        .card-value {
            font-size: 48px;
            line-height: 1.08349;
            font-weight: 600;
            letter-spacing: -0.003em;
            color: #1d1d1f;
            margin-bottom: 4px;
        }

        .card-subtitle {
            font-size: 14px;
            line-height: 1.42859;
            font-weight: 400;
            letter-spacing: -0.016em;
            color: #6e6e73;
        }

        /* Table Container */
        .table-container {
            background: #ffffff;
            border-radius: 18px;
            padding: 40px;
            margin: 48px auto 80px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            overflow-x: auto;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.6s both;
        }

        .table-header {
            margin-bottom: 32px;
        }

        .table-title {
            font-size: 32px;
            line-height: 1.125;
            font-weight: 600;
            letter-spacing: -0.003em;
            color: #1d1d1f;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        thead {
            background: #f5f5f7;
        }

        thead tr {
            border-radius: 12px;
        }

        th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 500;
            font-size: 12px;
            letter-spacing: -0.01em;
            color: #6e6e73;
            text-transform: uppercase;
            border-bottom: 1px solid #d2d2d7;
            background: #f5f5f7;
        }

        th:first-child {
            border-top-left-radius: 12px;
        }

        th:last-child {
            border-top-right-radius: 12px;
        }

        td {
            padding: 20px;
            font-size: 17px;
            line-height: 1.47059;
            font-weight: 400;
            letter-spacing: -0.022em;
            color: #1d1d1f;
            border-bottom: 1px solid #d2d2d7;
            transition: background 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        tbody tr {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        tbody tr:hover {
            background: #f5f5f7;
        }

        tbody tr.total-row {
            background: #f5f5f7;
            font-weight: 600;
        }

        tbody tr.total-row td {
            padding: 24px 20px;
            border-top: 2px solid #1d1d1f;
            border-bottom: none;
        }

        tbody tr.total-row:hover {
            background: #e8e8ed;
        }

        /* Loading & Status */
        .loading {
            text-align: center;
            padding: 80px 20px;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto 24px;
            border: 3px solid #d2d2d7;
            border-top-color: #0071e3;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .loading p {
            font-size: 17px;
            line-height: 1.47059;
            font-weight: 400;
            letter-spacing: -0.022em;
            color: #6e6e73;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .error-message {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px 24px;
            border-radius: 12px;
            margin: 20px 0;
            font-size: 15px;
            line-height: 1.47059;
        }

        .success-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .success-indicator.high {
            background: #30d158;
        }

        .success-indicator.medium {
            background: #ff9f0a;
        }

        .success-indicator.low {
            background: #ff3b30;
        }

        .number-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 1068px) {
            h1 {
                font-size: 48px;
            }

            .card-value {
                font-size: 40px;
            }

            .table-title {
                font-size: 28px;
            }
        }

        @media (max-width: 734px) {
            .container {
                padding: 0 16px;
            }

            .nav-content {
                padding: 16px;
            }

            header {
                padding: 48px 0 40px;
            }

            h1 {
                font-size: 32px;
            }

            .subtitle {
                font-size: 17px;
            }

            .controls {
                flex-direction: column;
                align-items: stretch;
                padding: 24px;
            }

            .control-group {
                width: 100%;
            }

            select, button {
                width: 100%;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 24px;
            }

            .card-value {
                font-size: 36px;
            }

            .table-container {
                padding: 24px 16px;
                margin: 32px auto 48px;
            }

            .table-title {
                font-size: 24px;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="nav-content">
            <div class="logo">Analytics</div>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Section -->
        <header>
            <h1>Call Analytics</h1>
            <p class="subtitle">Comprehensive insights across all servers and extensions</p>
        </header>

        <!-- Controls Section -->
        <div class="controls">
            <div class="control-group">
                <label for="month">Select Month</label>
                <select id="month">
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>

            <div class="control-group">
                <label for="year">Select Year</label>
                <select id="year"></select>
            </div>

            <button id="fetchBtn" onclick="fetchData()">
                Fetch Analytics
            </button>

            <button class="export-btn" id="exportBtn" onclick="exportToExcel()" disabled>
                Export to Excel
            </button>
        </div>

        <!-- Summary Cards Section -->
        <div id="summarySection" style="display: none;">
            <div class="summary-cards">
                <div class="card">
                    <div class="card-title">Total Calls</div>
                    <div class="card-value" id="totalCalls">0</div>
                    <div class="card-subtitle">All servers combined</div>
                </div>

                <div class="card">
                    <div class="card-title">Successful Calls</div>
                    <div class="card-value" id="successfulCalls">0</div>
                    <div class="card-subtitle" id="successRate">0% success rate</div>
                </div>

                <div class="card">
                    <div class="card-title">Average Duration</div>
                    <div class="card-value" id="avgDuration">0s</div>
                    <div class="card-subtitle">Call duration</div>
                </div>

                <div class="card">
                    <div class="card-title">Unique Callers</div>
                    <div class="card-value" id="uniqueCallers">0</div>
                    <div class="card-subtitle">Different phone numbers</div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Detailed Analytics</div>
            </div>
            <div id="tableContent">
                <div class="loading">
                    <p>Select a month and year, then click Fetch Analytics</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize year dropdown
        const yearSelect = document.getElementById('year');
        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;

        for (let year = currentYear; year >= 2020; year--) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearSelect.appendChild(option);
        }

        // Set current month and year as default
        document.getElementById('month').value = currentMonth;
        document.getElementById('year').value = currentYear;

        let currentData = null;

        async function fetchData() {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            const fetchBtn = document.getElementById('fetchBtn');
            const exportBtn = document.getElementById('exportBtn');
            const tableContent = document.getElementById('tableContent');
            const summarySection = document.getElementById('summarySection');

            // Show loading state
            fetchBtn.disabled = true;
            exportBtn.disabled = true;
            summarySection.style.display = 'none';
            tableContent.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Fetching analytics from all servers</p>
                </div>
            `;

            try {
                const response = await fetch(`?action=fetch&month=${month}&year=${year}`);
                const data = await response.json();
                currentData = { data, month, year };

                // Update summary cards
                document.getElementById('totalCalls').textContent = data.totals.total_calls.toLocaleString();
                document.getElementById('successfulCalls').textContent = data.totals.successful_calls.toLocaleString();
                document.getElementById('successRate').textContent = data.totals.success_rate.toFixed(2) + '% success rate';
                document.getElementById('avgDuration').textContent = data.totals.avg_duration.toFixed(1) + 's';
                document.getElementById('uniqueCallers').textContent = data.totals.unique_callers.toLocaleString();

                summarySection.style.display = 'block';

                // Build table
                let tableHTML = `
                    <table>
                        <thead>
                            <tr>
                                <th>Server</th>
                                <th>Extension</th>
                                <th>Extension Name</th>
                                <th class="number-cell">Total Calls</th>
                                <th class="number-cell">Successful</th>
                                <th class="number-cell">Success Rate</th>
                                <th class="number-cell">Hangup</th>
                                <th class="number-cell">Operator</th>
                                <th class="number-cell">Reservations</th>
                                <th class="number-cell">Avg Duration</th>
                                <th class="number-cell">Unique Callers</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                // Add detail rows
                data.details.forEach(detail => {
                    if (detail.status === 'error') {
                        // Show error row
                        tableHTML += `
                            <tr style="background: #fff3cd;">
                                <td>${detail.server}</td>
                                <td><strong>${detail.extension}</strong></td>
                                <td>${detail.extension_label}</td>
                                <td colspan="8" style="color: #856404;">
                                    <strong>Error:</strong> ${detail.error || 'Unknown error'}
                                    ${detail.error_url ? `<br><small style="font-size: 13px;">URL: ${detail.error_url}</small>` : ''}
                                    ${detail.error_response ? `<br><small style="font-size: 13px;">Response: ${detail.error_response}</small>` : ''}
                                </td>
                            </tr>
                        `;
                    } else {
                        // Show normal data row
                        const successRateClass = detail.success_rate >= 70 ? 'high' : detail.success_rate >= 40 ? 'medium' : 'low';

                        tableHTML += `
                            <tr>
                                <td>${detail.server}</td>
                                <td><strong>${detail.extension}</strong></td>
                                <td>${detail.extension_label}</td>
                                <td class="number-cell">${detail.total_calls.toLocaleString()}</td>
                                <td class="number-cell">${detail.successful_calls.toLocaleString()}</td>
                                <td class="number-cell">
                                    <span class="success-indicator ${successRateClass}"></span>
                                    ${detail.success_rate.toFixed(2)}%
                                </td>
                                <td class="number-cell">${detail.hangup_calls.toLocaleString()}</td>
                                <td class="number-cell">${detail.operator_transfers.toLocaleString()}</td>
                                <td class="number-cell">${detail.reservation_calls.toLocaleString()}</td>
                                <td class="number-cell">${detail.avg_duration.toFixed(1)}s</td>
                                <td class="number-cell">${detail.unique_callers.toLocaleString()}</td>
                            </tr>
                        `;
                    }
                });

                // Add total row
                const totalSuccessRateClass = data.totals.success_rate >= 70 ? 'high' : data.totals.success_rate >= 40 ? 'medium' : 'low';
                tableHTML += `
                        <tr class="total-row">
                            <td colspan="3"><strong>TOTAL</strong></td>
                            <td class="number-cell"><strong>${data.totals.total_calls.toLocaleString()}</strong></td>
                            <td class="number-cell"><strong>${data.totals.successful_calls.toLocaleString()}</strong></td>
                            <td class="number-cell">
                                <span class="success-indicator ${totalSuccessRateClass}"></span>
                                <strong>${data.totals.success_rate.toFixed(2)}%</strong>
                            </td>
                            <td class="number-cell"><strong>${data.totals.hangup_calls.toLocaleString()}</strong></td>
                            <td class="number-cell"><strong>${data.totals.operator_transfers.toLocaleString()}</strong></td>
                            <td class="number-cell"><strong>${data.totals.reservation_calls.toLocaleString()}</strong></td>
                            <td class="number-cell"><strong>${data.totals.avg_duration.toFixed(1)}s</strong></td>
                            <td class="number-cell"><strong>${data.totals.unique_callers.toLocaleString()}</strong></td>
                        </tr>
                    </tbody>
                </table>
                `;

                tableContent.innerHTML = tableHTML;
                exportBtn.disabled = false;

            } catch (error) {
                tableContent.innerHTML = `
                    <div class="error-message">
                        <strong>Error:</strong> Failed to fetch analytics data. ${error.message}
                    </div>
                `;
            } finally {
                fetchBtn.disabled = false;
            }
        }

        function exportToExcel() {
            if (!currentData) return;

            const month = currentData.month;
            const year = currentData.year;
            window.location.href = `?export=excel&month=${month}&year=${year}`;
        }

        // Allow pressing Enter in the selects to fetch data
        document.getElementById('month').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') fetchData();
        });
        document.getElementById('year').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') fetchData();
        });
    </script>
</body>
</html>
