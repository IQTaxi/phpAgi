<?php
/**
 * Geocoding Validation Test Tool - EXACT MIRROR of complete_agi_call_handler.php
 * Tests both Google Maps Geocoding API v1 and Google Places API v1
 * Shows validation results matching the AGI system logic EXACTLY
 */

// Configuration - Update with your API key
$API_KEY = 'AIzaSyDeu4sIzoLUnS8xvSnUOn5zZGnOIaajgDY';

// Handle form submission
$result = null;
$error = null;

if ($_POST) {
    $address = $_POST['address'] ?? '';
    $api_type = $_POST['api_type'] ?? '1';
    $location_type = $_POST['location_type'] ?? 'pickup'; // pickup or dropoff
    $strict_dropoff = isset($_POST['strict_dropoff']);
    $extension_name = $_POST['extension_name'] ?? '';

    // Bounds and center bias parameters
    $bounds_restriction_mode = $_POST['bounds_restriction_mode'] ?? '0';
    $bounds = null;
    $center_bias = null;

    if (!empty($_POST['bounds_north']) && !empty($_POST['bounds_south']) &&
        !empty($_POST['bounds_east']) && !empty($_POST['bounds_west'])) {
        $bounds = [
            'north' => floatval($_POST['bounds_north']),
            'south' => floatval($_POST['bounds_south']),
            'east' => floatval($_POST['bounds_east']),
            'west' => floatval($_POST['bounds_west'])
        ];
    }

    if (!empty($_POST['center_lat']) && !empty($_POST['center_lng']) && !empty($_POST['center_radius'])) {
        $center_bias = [
            'lat' => floatval($_POST['center_lat']),
            'lng' => floatval($_POST['center_lng']),
            'radius' => intval($_POST['center_radius'])
        ];
    }

    if (empty($address)) {
        $error = "Please enter an address";
    } else {
        try {
            // Use the exact same function as AGI system with bounds/centerBias
            $result = getLatLngFromGoogle(
                $address,
                $location_type === 'pickup',
                $api_type,
                $API_KEY,
                $strict_dropoff,
                $extension_name,
                $bounds_restriction_mode,
                $bounds,
                $center_bias
            );

            if ($result) {
                $result['validation'] = [
                    'is_valid' => true,
                    'reason' => "Address accepted by AGI validation logic",
                    'location_precision' => $result['location_type']
                ];
            } else {
                $result = [
                    'api_type' => $api_type === '1' ? 'Google Maps Geocoding API v1' : 'Google Places API v1',
                    'address' => $address,
                    'lat' => null,
                    'lng' => null,
                    'location_type' => 'REJECTED',
                    'latLng' => ['lat' => null, 'lng' => null],
                    'bounds_applied' => false,
                    'center_bias_applied' => false,
                    'validation' => [
                        'is_valid' => false,
                        'reason' => "Address rejected by AGI validation logic (precision, bounds, or API failure)",
                        'location_precision' => 'REJECTED'
                    ]
                ];
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

/**
 * EXACT MIRROR of getLatLngFromGoogle() from complete_agi_call_handler.php
 */
function getLatLngFromGoogle($address, $is_pickup = true, $geocoding_version = '1', $api_key = '', $strict_dropoff = false, $extension_name = '', $bounds_restriction_mode = '0', $bounds = null, $center_bias = null) {
    // Handle special cases first
    $special_result = handleSpecialAddresses($address, $is_pickup, $extension_name);
    if ($special_result) {
        $special_result['api_type'] = $geocoding_version === '1' ? 'Google Maps Geocoding API v1' : 'Google Places API v1';
        $special_result['raw_response'] = ['special_address' => true];
        $special_result['bounds_applied'] = false;
        $special_result['center_bias_applied'] = false;
        return $special_result;
    }

    // EXACT bounds/centerBias logic from AGI system
    $boundsRestrictionMode = intval($bounds_restriction_mode);

    // Check if bounds/centerBias should be applied based on boundsRestrictionMode
    $applyRestrictions = false;
    if ($boundsRestrictionMode !== 0) {
        if ($boundsRestrictionMode == 1 && $is_pickup) {
            // Apply only to pickup
            $applyRestrictions = true;
        } elseif ($boundsRestrictionMode == 2 && !$is_pickup) {
            // Apply only to dropoff
            $applyRestrictions = true;
        } elseif ($boundsRestrictionMode == 3) {
            // Apply to both
            $applyRestrictions = true;
        }
    }

    // Get bounds and centerBias only if restrictions should be applied
    $final_bounds = $applyRestrictions ? $bounds : null;
    $final_center_bias = $applyRestrictions ? $center_bias : null;

    if ($geocoding_version === '2') {
        return getLatLngFromGooglePlacesV1($address, $is_pickup, $api_key, $strict_dropoff, $final_bounds, $final_center_bias);
    } else {
        return getLatLngFromGoogleGeocoding($address, $is_pickup, $api_key, $strict_dropoff, $final_bounds, $final_center_bias);
    }
}

/**
 * EXACT MIRROR of handleSpecialAddresses() from complete_agi_call_handler.php
 */
function handleSpecialAddresses($address, $is_pickup, $extension_name = '') {
    $normalized_address = removeDiacritics(strtolower(trim($address)));
    $center_addresses = ["κεντρο", "τοπικο", "κεντρο αθηνα", "αθηνα κεντρο", "κεντρο θεσσαλονικη", "θεσσαλονικη κεντρο"];

    // Check if address contains center terms (not exact match)
    $is_center = false;
    if (!$is_pickup) {
        foreach ($center_addresses as $center_term) {
            if (strpos($normalized_address, $center_term) !== false) {
                $is_center = true;
                break;
            }
        }
    }

    if ($is_center) {
        return [
            "address" => $address,
            "location_type" => "EXACT",
            "lat" => 0,
            "lng" => 0,
            "latLng" => ["lat" => 0, "lng" => 0]
        ];
    }

    // Handle airport for Cosmos extension
    if ($extension_name === 'Cosmos') {
        $airport_terms = ['αεροδομιο', 'αεροδρόμιο', 'airport'];
        foreach ($airport_terms as $term) {
            if (strpos($normalized_address, removeDiacritics($term)) !== false) {
                return [
                    "address" => "Αεροδρόμιο Αθηνών Ελευθέριος Βενιζέλος, Σπάτα",
                    "location_type" => "ROOFTOP",
                    "lat" => 37.9363405,
                    "lng" => 23.946668,
                    "latLng" => ["lat" => 37.9363405, "lng" => 23.946668]
                ];
            }
        }
    }

    return false;
}

/**
 * EXACT MIRROR of removeDiacritics() from complete_agi_call_handler.php
 */
function removeDiacritics($text) {
    // Greek character mapping for proper transliteration
    $greek_map = [
        'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => 'Th',
        'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'X', 'Ο' => 'O', 'Π' => 'P',
        'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'Ch', 'Ψ' => 'Ps', 'Ω' => 'O',
        'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => 'th',
        'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o', 'π' => 'p',
        'ρ' => 'r', 'σ' => 's', 'ς' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o',
        'ά' => 'a', 'έ' => 'e', 'ή' => 'h', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ώ' => 'o',
        'ΐ' => 'i', 'ΰ' => 'y', 'Ά' => 'A', 'Έ' => 'E', 'Ή' => 'H', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ώ' => 'O'
    ];

    // Apply Greek character mapping first
    $transliterated = strtr($text, $greek_map);

    // Then remove any remaining diacritical marks
    return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $transliterated);
}

/**
 * EXACT MIRROR of getLatLngFromGoogleGeocoding() from complete_agi_call_handler.php
 */
function getLatLngFromGoogleGeocoding($address, $is_pickup = true, $api_key = '', $strict_dropoff = false, $bounds = null, $centerBias = null) {
    $url = "https://maps.googleapis.com/maps/api/geocode/json";

    $params_array = [
        "address" => $address,
        "key" => $api_key,
        "language" => 'el-GR'
    ];

    // Add center bias if provided - INFLUENCES API RESULTS (EXACT AGI logic)
    if (!empty($centerBias) && isset($centerBias['lat']) && isset($centerBias['lng']) && isset($centerBias['radius'])) {
        // Use location bias to prefer results near center point
        $params_array["location"] = "{$centerBias['lat']},{$centerBias['lng']}";
        $params_array["radius"] = $centerBias['radius'];
    }

    $params = http_build_query($params_array);
    $response = makeHttpRequest($url . '?' . $params);

    if (!$response) return null;

    $data = json_decode($response, true);
    if (!$data || $data['status'] !== 'OK' || empty($data['results'])) return null;

    $result = $data['results'][0];

    // EXACT validation logic from AGI system FIRST
    $validation_result = validateLocationResult($result, $is_pickup, $strict_dropoff);
    if (!$validation_result) return null;

    // POST-PROCESSING bounds validation - REJECTS after API call (EXACT AGI logic)
    if (!empty($bounds)) {
        $lat = $result['geometry']['location']['lat'];
        $lng = $result['geometry']['location']['lng'];

        if ($lat < $bounds['south'] || $lat > $bounds['north'] ||
            $lng < $bounds['west'] || $lng > $bounds['east']) {
            // Result outside bounds - reject (post-processing validation)
            return null;
        }
    }

    return [
        'api_type' => 'Google Maps Geocoding API v1',
        'address' => $validation_result['address'],
        'lat' => $validation_result['latLng']['lat'],
        'lng' => $validation_result['latLng']['lng'],
        'location_type' => $validation_result['location_type'],
        'latLng' => $validation_result['latLng'],
        'bounds_applied' => !empty($bounds),
        'center_bias_applied' => !empty($centerBias),
        'raw_response' => $data
    ];
}

/**
 * EXACT MIRROR of getLatLngFromGooglePlacesV1() from complete_agi_call_handler.php
 */
function getLatLngFromGooglePlacesV1($address, $is_pickup = true, $api_key = '', $strict_dropoff = false, $bounds = null, $centerBias = null) {
    $url = "https://places.googleapis.com/v1/places:searchText";

    $headers = [
        "Content-Type: application/json",
        "X-Goog-Api-Key: " . $api_key,
        "X-Goog-FieldMask: places.displayName,places.formattedAddress,places.location,places.addressComponents"
    ];

    $data = [
        "textQuery" => $address,
        "languageCode" => 'el',
        "regionCode" => "GR",
        "maxResultCount" => 1
    ];

    // Add center bias if provided - INFLUENCES API RESULTS (EXACT AGI logic)
    if (!empty($centerBias) && isset($centerBias['lat']) && isset($centerBias['lng']) && isset($centerBias['radius'])) {
        // Use locationBias with circle to prefer results near center point
        $data["locationBias"] = [
            "circle" => [
                "center" => [
                    "latitude" => $centerBias['lat'],
                    "longitude" => $centerBias['lng']
                ],
                "radius" => $centerBias['radius']
            ]
        ];
    }

    $response = makeHttpRequest($url, 'POST', json_encode($data), $headers);
    if (!$response) return null;

    $responseData = json_decode($response, true);
    if (!$responseData || empty($responseData['places'])) return null;

    $place = $responseData['places'][0];

    // Determine location type based on components (EXACT AGI logic)
    $location_type = 'APPROXIMATE'; // Default
    $has_street_number = false;
    $has_route = false;

    if (!empty($place['addressComponents'])) {
        foreach ($place['addressComponents'] as $component) {
            foreach ($component['types'] as $type) {
                if ($type === 'street_number') $has_street_number = true;
                if ($type === 'route') $has_route = true;
            }
        }

        // Determine precision based on components
        if ($has_street_number && $has_route) {
            $location_type = 'ROOFTOP';
        } elseif ($has_route) {
            $location_type = 'RANGE_INTERPOLATED';
        } else {
            $location_type = 'GEOMETRIC_CENTER';
        }
    }

    // EXACT validation logic from AGI system FIRST
    if ($is_pickup) {
        // Pickup locations ALWAYS require precise location types
        if (!in_array($location_type, ['ROOFTOP', 'RANGE_INTERPOLATED'])) {
            return null;
        }
    } else {
        // Dropoff location validation based on config
        if ($strict_dropoff && !in_array($location_type, ['ROOFTOP', 'RANGE_INTERPOLATED'])) {
            return null;
        }
    }

    // POST-PROCESSING bounds validation - REJECTS after API call (EXACT AGI logic)
    if (!empty($bounds)) {
        $lat = $place['location']['latitude'];
        $lng = $place['location']['longitude'];

        if ($lat < $bounds['south'] || $lat > $bounds['north'] ||
            $lng < $bounds['west'] || $lng > $bounds['east']) {
            // Result outside bounds - reject (post-processing validation)
            return null;
        }
    }

    return [
        'api_type' => 'Google Places API v1',
        'address' => $place['formattedAddress'],
        'lat' => $place['location']['latitude'],
        'lng' => $place['location']['longitude'],
        'location_type' => $location_type,
        'latLng' => [
            'lat' => $place['location']['latitude'],
            'lng' => $place['location']['longitude']
        ],
        'has_street_number' => $has_street_number,
        'has_route' => $has_route,
        'bounds_applied' => !empty($bounds),
        'center_bias_applied' => !empty($centerBias),
        'raw_response' => $responseData
    ];
}

/**
 * EXACT MIRROR of validateLocationResult() from complete_agi_call_handler.php
 */
function validateLocationResult($result, $is_pickup, $strict_dropoff = false) {
    $location_type = $result['geometry']['location_type'];

    // Validate location type based on pickup/dropoff and config
    if ($is_pickup) {
        // Pickup locations ALWAYS require precise location types
        if (!in_array($location_type, ['ROOFTOP', 'RANGE_INTERPOLATED'])) {
            return null;
        }
    } else {
        // Dropoff location validation based on config
        if ($strict_dropoff && !in_array($location_type, ['ROOFTOP', 'RANGE_INTERPOLATED'])) {
            return null;
        }
    }

    return [
        "address" => $result['formatted_address'],
        "location_type" => $location_type,
        "latLng" => [
            "lat" => $result['geometry']['location']['lat'],
            "lng" => $result['geometry']['location']['lng']
        ]
    ];
}

/**
 * Make HTTP request
 */
function makeHttpRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Geocoding Test Tool'
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        throw new Exception("HTTP request failed: " . curl_error($ch));
    }

    if ($http_code !== 200) {
        throw new Exception("HTTP error: $http_code");
    }

    curl_close($ch);
    return $response;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geocoding Validation Test Tool</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        input[type="text"]:focus, input[type="number"]:focus {
            border-color: #4CAF50;
            outline: none;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin: 10px 0;
            flex-wrap: wrap;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        input[type="radio"], input[type="checkbox"] {
            margin-right: 5px;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        button:hover {
            background-color: #45a049;
        }

        .map-button {
            width: auto;
            padding: 8px 16px;
            font-size: 14px;
            margin: 10px 5px 0 0;
            background-color: #2196F3;
        }

        .map-button:hover {
            background-color: #1976D2;
        }

        .map-button.active {
            background-color: #FF5722;
        }

        .bounds-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }

        .bounds-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bounds-row label {
            min-width: 60px;
            margin: 0;
        }

        .bounds-row input {
            flex: 1;
            width: auto;
        }

        #map {
            height: 400px;
            width: 100%;
            margin: 20px 0;
            border: 2px solid #ddd;
            border-radius: 5px;
        }

        .map-instructions {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #2196F3;
        }

        .map-instructions.active {
            background: #fff3e0;
            border-left-color: #FF5722;
        }

        .result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 5px;
            border: 2px solid #ddd;
        }

        .result.valid {
            background-color: #e8f5e8;
            border-color: #4CAF50;
        }

        .result.invalid {
            background-color: #ffe8e8;
            border-color: #f44336;
        }

        .result.error {
            background-color: #fff3cd;
            border-color: #ffc107;
        }

        .result-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .result-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-item {
            margin-bottom: 10px;
        }

        .detail-label {
            font-weight: bold;
            color: #555;
        }

        .detail-value {
            margin-left: 10px;
        }

        .raw-response {
            margin-top: 20px;
        }

        .raw-response summary {
            cursor: pointer;
            font-weight: bold;
            color: #666;
        }

        .raw-response pre {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }

        .validation-status {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .validation-status.valid {
            background-color: #4CAF50;
            color: white;
        }

        .validation-status.invalid {
            background-color: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗺️ Geocoding Validation Test Tool</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Test address validation using the same logic as the IQTaxi AGI system
        </p>

        <form method="post">
            <div class="form-group">
                <label for="address">Address to Test:</label>
                <input type="text" id="address" name="address"
                       placeholder="e.g., Σταδίου 10 Αθήνα, κέντρο, airport"
                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>API Type (geocodingApiVersion):</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="api1" name="api_type" value="1"
                               <?= ($_POST['api_type'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label for="api1">1 - Google Maps Geocoding API v1</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="api2" name="api_type" value="2"
                               <?= ($_POST['api_type'] ?? '1') === '2' ? 'checked' : '' ?>>
                        <label for="api2">2 - Google Places API v1</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Location Type:</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="pickup" name="location_type" value="pickup"
                               <?= ($_POST['location_type'] ?? 'pickup') === 'pickup' ? 'checked' : '' ?>>
                        <label for="pickup">Pickup Address (Always Strict - ROOFTOP/RANGE_INTERPOLATED only)</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="dropoff" name="location_type" value="dropoff"
                               <?= ($_POST['location_type'] ?? 'pickup') === 'dropoff' ? 'checked' : '' ?>>
                        <label for="dropoff">Dropoff Address</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="radio-option">
                    <input type="checkbox" id="strict_dropoff" name="strict_dropoff"
                           <?= isset($_POST['strict_dropoff']) ? 'checked' : '' ?>>
                    <label for="strict_dropoff">Strict Dropoff Location (strictDropoffLocation = true)</label>
                </div>
            </div>

            <div class="form-group">
                <label for="extension_name">Extension Name (for special address handling):</label>
                <input type="text" id="extension_name" name="extension_name"
                       placeholder="e.g., Cosmos (for airport handling)"
                       value="<?= htmlspecialchars($_POST['extension_name'] ?? '') ?>">
                <small style="color: #666;">Enter "Cosmos" to test airport address handling</small>
            </div>

            <div class="form-group">
                <label>Bounds Restriction Mode (boundsRestrictionMode):</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="bounds_0" name="bounds_restriction_mode" value="0"
                               <?= ($_POST['bounds_restriction_mode'] ?? '0') === '0' ? 'checked' : '' ?>>
                        <label for="bounds_0">0 - Disabled (no bounds/center bias applied)</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="bounds_1" name="bounds_restriction_mode" value="1"
                               <?= ($_POST['bounds_restriction_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label for="bounds_1">1 - Apply to pickup addresses only</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="bounds_2" name="bounds_restriction_mode" value="2"
                               <?= ($_POST['bounds_restriction_mode'] ?? '0') === '2' ? 'checked' : '' ?>>
                        <label for="bounds_2">2 - Apply to dropoff addresses only</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="bounds_3" name="bounds_restriction_mode" value="3"
                               <?= ($_POST['bounds_restriction_mode'] ?? '0') === '3' ? 'checked' : '' ?>>
                        <label for="bounds_3">3 - Apply to both pickup and dropoff</label>
                    </div>
                </div>
            </div>

            <div id="bounds-section" style="margin-top: 20px;">
                <h4>🗺️ Geographic Restrictions</h4>

                <div style="margin-bottom: 20px;">
                    <h5>📍 Center Bias (bias results toward this point):</h5>
                    <div class="radio-group" style="gap: 10px;">
                        <input type="number" name="center_lat" id="center_lat" placeholder="Latitude" step="any"
                               value="<?= htmlspecialchars($_POST['center_lat'] ?? '') ?>" style="width: 120px;">
                        <input type="number" name="center_lng" id="center_lng" placeholder="Longitude" step="any"
                               value="<?= htmlspecialchars($_POST['center_lng'] ?? '') ?>" style="width: 120px;">
                        <input type="number" name="center_radius" id="center_radius" placeholder="Radius (meters)"
                               value="<?= htmlspecialchars($_POST['center_radius'] ?? '') ?>" style="width: 140px;">
                    </div>
                    <button type="button" id="select-center" class="map-button">📍 Click Map to Select Center</button>
                    <button type="button" id="clear-center" class="map-button" style="background-color: #757575;">🗑️ Clear Center</button>
                </div>

                <div style="margin-bottom: 20px;">
                    <h5>🔲 Geographic Bounds (reject results outside this area):</h5>
                    <div class="bounds-inputs">
                        <div class="bounds-row">
                            <label>North:</label>
                            <input type="number" name="bounds_north" id="bounds_north" placeholder="North Lat" step="any"
                                   value="<?= htmlspecialchars($_POST['bounds_north'] ?? '') ?>">
                        </div>
                        <div class="bounds-row">
                            <label>West:</label>
                            <input type="number" name="bounds_west" id="bounds_west" placeholder="West Lng" step="any"
                                   value="<?= htmlspecialchars($_POST['bounds_west'] ?? '') ?>">
                            <label>East:</label>
                            <input type="number" name="bounds_east" id="bounds_east" placeholder="East Lng" step="any"
                                   value="<?= htmlspecialchars($_POST['bounds_east'] ?? '') ?>">
                        </div>
                        <div class="bounds-row">
                            <label>South:</label>
                            <input type="number" name="bounds_south" id="bounds_south" placeholder="South Lat" step="any"
                                   value="<?= htmlspecialchars($_POST['bounds_south'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="button" id="select-bounds" class="map-button">🔲 Drag Map to Select Bounds</button>
                    <button type="button" id="clear-bounds" class="map-button" style="background-color: #757575;">🗑️ Clear Bounds</button>
                </div>

                <div class="map-instructions" id="map-instructions">
                    💡 <strong>Instructions:</strong> Use the buttons above to interact with the map. Click "📍 Click Map to Select Center" then click anywhere on the map to set center bias. Click "🔲 Drag Map to Select Bounds" then drag a rectangle on the map to set geographic bounds.
                </div>

                <div id="map"></div>

                <h4 style="margin-top: 30px;">🔍 Configuration Preview</h4>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                    Live preview of your center bias and bounds configuration:
                </p>
                <div id="preview-map" style="height: 300px; width: 100%; margin: 10px 0; border: 2px solid #ddd; border-radius: 5px;"></div>
            </div>

            <button type="submit">🔍 Test Address Validation (Exact AGI Logic)</button>
        </form>

        <?php if ($error): ?>
            <div class="result error">
                <div class="result-header">❌ Error</div>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php elseif ($result): ?>
            <div class="result <?= $result['validation']['is_valid'] ? 'valid' : 'invalid' ?>">
                <div class="validation-status <?= $result['validation']['is_valid'] ? 'valid' : 'invalid' ?>">
                    <?= $result['validation']['is_valid'] ? '✅ ADDRESS ACCEPTED' : '❌ ADDRESS REJECTED' ?>
                </div>

                <div class="result-header">
                    📍 Results from <?= htmlspecialchars($result['api_type']) ?>
                </div>

                <div class="result-details">
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Address:</span>
                            <span class="detail-value"><?= htmlspecialchars($result['address']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Coordinates:</span>
                            <span class="detail-value">
                                <?php if ($result['lat'] !== null && $result['lng'] !== null): ?>
                                    <?= $result['lat'] ?>, <?= $result['lng'] ?>
                                <?php else: ?>
                                    Not available (address rejected)
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Location Precision:</span>
                            <span class="detail-value"><?= htmlspecialchars($result['location_type'] ?? 'Unknown') ?></span>
                        </div>
                        <?php if (isset($result['has_street_number'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Has Street Number:</span>
                            <span class="detail-value"><?= $result['has_street_number'] ? 'Yes' : 'No' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Has Route:</span>
                            <span class="detail-value"><?= $result['has_route'] ? 'Yes' : 'No' ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Validation Status:</span>
                            <span class="detail-value"><?= $result['validation']['is_valid'] ? 'ACCEPTED' : 'REJECTED' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Reason:</span>
                            <span class="detail-value"><?= htmlspecialchars($result['validation']['reason']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Bounds Applied:</span>
                            <span class="detail-value"><?= isset($result['bounds_applied']) && $result['bounds_applied'] ? 'Yes' : 'No' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Center Bias Applied:</span>
                            <span class="detail-value"><?= isset($result['center_bias_applied']) && $result['center_bias_applied'] ? 'Yes' : 'No' ?></span>
                        </div>
                    </div>
                </div>


                <div class="raw-response">
                    <details>
                        <summary>📋 View Raw API Response</summary>
                        <pre><?= htmlspecialchars(json_encode($result['raw_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </details>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px;">
            <h3>📖 Exact AGI System Mirror - How This Works:</h3>

            <h4>🎯 Special Address Handling (checked FIRST):</h4>
            <ul>
                <li><strong>Center Addresses:</strong> "κέντρο", "τοπικό", "κέντρο αθήνα" etc. (dropoff only) → Returns EXACT with lat=0, lng=0</li>
                <li><strong>Airport (Cosmos extension):</strong> "αεροδρόμιο", "airport" → Returns Athens airport coordinates</li>
                <li><strong>Greek Diacritics:</strong> System removes accents for matching (e.g., "κέντρο" → "kentro")</li>
            </ul>

            <h4>🌐 API Geocoding (if not special address):</h4>
            <ul>
                <li><strong>Google Maps Geocoding API (v1):</strong> Uses location_type directly from Google response</li>
                <li><strong>Google Places API (v1):</strong> Analyzes addressComponents to determine precision:
                    <ul>
                        <li>Has street_number + route → "ROOFTOP"</li>
                        <li>Has route only → "RANGE_INTERPOLATED"</li>
                        <li>Neither → "GEOMETRIC_CENTER"</li>
                    </ul>
                </li>
            </ul>

            <h4>✅ Validation Rules (EXACT AGI logic):</h4>
            <ul>
                <li><strong>Pickup Addresses:</strong> ALWAYS require ROOFTOP or RANGE_INTERPOLATED (strict)</li>
                <li><strong>Dropoff Addresses:</strong>
                    <ul>
                        <li>strictDropoffLocation=false: Accept all precision levels</li>
                        <li>strictDropoffLocation=true: Only ROOFTOP or RANGE_INTERPOLATED</li>
                    </ul>
                </li>
                <li><strong>Rejection:</strong> If validation fails, function returns null (ADDRESS REJECTED)</li>
            </ul>

            <h4>🧪 Test Examples:</h4>
            <ul>
                <li><strong>"κέντρο"</strong> (dropoff) → Special address, always accepted</li>
                <li><strong>"airport"</strong> (Cosmos extension) → Special address, returns Athens airport</li>
                <li><strong>"Σταδίου 10 Αθήνα"</strong> → Regular geocoding validation</li>
                <li><strong>"Αθήνα"</strong> (pickup) → Likely rejected (GEOMETRIC_CENTER precision)</li>
            </ul>

            <p><strong>🔬 This tester uses the EXACT same functions as complete_agi_call_handler.php</strong></p>
        </div>
    </div>

    <script>
        // Initialize main interactive map centered on Athens, Greece
        var map = L.map('map').setView([37.9755, 23.7348], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Initialize preview map
        var previewMap = L.map('preview-map').setView([37.9755, 23.7348], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(previewMap);

        // Preview map elements
        var previewElements = {
            centerMarker: null,
            centerCircle: null,
            boundsRectangle: null
        };

        // Main map interaction state
        var mapState = {
            mode: 'none',
            centerMarker: null,
            centerCircle: null,
            boundsRectangle: null,
            isSelecting: false,
            boundsStartPoint: null
        };

        // Input field references
        var centerLatInput = document.getElementById('center_lat');
        var centerLngInput = document.getElementById('center_lng');
        var centerRadiusInput = document.getElementById('center_radius');
        var boundsNorthInput = document.getElementById('bounds_north');
        var boundsSouthInput = document.getElementById('bounds_south');
        var boundsEastInput = document.getElementById('bounds_east');
        var boundsWestInput = document.getElementById('bounds_west');

        // Update preview map with current configuration
        function updatePreview() {
            // Clear existing preview elements
            clearPreview();

            // Add center bias to preview if configured
            var lat = parseFloat(centerLatInput.value);
            var lng = parseFloat(centerLngInput.value);
            var radius = parseFloat(centerRadiusInput.value);

            if (!isNaN(lat) && !isNaN(lng) && !isNaN(radius)) {
                previewElements.centerMarker = L.marker([lat, lng]).addTo(previewMap)
                    .bindPopup(`Center Bias<br>Radius: ${radius}m`);
                previewElements.centerCircle = L.circle([lat, lng], {
                    radius: radius,
                    fillColor: '#2196F3',
                    color: '#1976D2',
                    weight: 2,
                    opacity: 0.8,
                    fillOpacity: 0.2
                }).addTo(previewMap);
            }

            // Add bounds to preview if configured
            var north = parseFloat(boundsNorthInput.value);
            var south = parseFloat(boundsSouthInput.value);
            var east = parseFloat(boundsEastInput.value);
            var west = parseFloat(boundsWestInput.value);

            if (!isNaN(north) && !isNaN(south) && !isNaN(east) && !isNaN(west)) {
                var bounds = L.latLngBounds([[south, west], [north, east]]);
                previewElements.boundsRectangle = L.rectangle(bounds, {
                    color: '#FF5722',
                    weight: 2,
                    fillOpacity: 0.2
                }).addTo(previewMap)
                .bindPopup(`Geographic Bounds<br>N:${north.toFixed(4)} S:${south.toFixed(4)}<br>E:${east.toFixed(4)} W:${west.toFixed(4)}`);
            }

            // Auto-fit preview map if we have elements
            setTimeout(function() {
                var elements = [];
                if (previewElements.centerMarker) elements.push(previewElements.centerMarker);
                if (previewElements.centerCircle) elements.push(previewElements.centerCircle);
                if (previewElements.boundsRectangle) elements.push(previewElements.boundsRectangle);

                if (elements.length > 0) {
                    var group = new L.featureGroup(elements);
                    previewMap.fitBounds(group.getBounds().pad(0.1));
                }
            }, 100);
        }

        // Clear preview map elements
        function clearPreview() {
            if (previewElements.centerMarker) {
                previewMap.removeLayer(previewElements.centerMarker);
                previewElements.centerMarker = null;
            }
            if (previewElements.centerCircle) {
                previewMap.removeLayer(previewElements.centerCircle);
                previewElements.centerCircle = null;
            }
            if (previewElements.boundsRectangle) {
                previewMap.removeLayer(previewElements.boundsRectangle);
                previewElements.boundsRectangle = null;
            }
        }

        // Button references and event handlers
        var selectCenterBtn = document.getElementById('select-center');
        var clearCenterBtn = document.getElementById('clear-center');
        var selectBoundsBtn = document.getElementById('select-bounds');
        var clearBoundsBtn = document.getElementById('clear-bounds');
        var instructionsDiv = document.getElementById('map-instructions');

        // Center selection mode
        selectCenterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            resetMapState();
            mapState.mode = 'center';
            this.classList.add('active');
            updateInstructions('Click anywhere on the map to set center bias point.');
            map.getContainer().style.cursor = 'crosshair';
        });

        // Clear center
        clearCenterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearCenter();
            updatePreview();
        });

        // Bounds selection mode
        selectBoundsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            resetMapState();
            mapState.mode = 'bounds';
            this.classList.add('active');
            updateInstructions('Click and drag on the map to draw a rectangle for geographic bounds.');
            map.getContainer().style.cursor = 'crosshair';
        });

        // Clear bounds
        clearBoundsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearBounds();
            updatePreview();
        });

        // Map interaction handlers
        map.on('click', function(e) {
            if (mapState.mode === 'center') {
                var radius = parseFloat(centerRadiusInput.value) || 50000;
                addCenterMarker(e.latlng.lat, e.latlng.lng, radius);
                resetMapState();
                updatePreview();
            }
        });

        map.on('mousedown', function(e) {
            if (mapState.mode === 'bounds') {
                mapState.isSelecting = true;
                mapState.boundsStartPoint = e.latlng;
                map.dragging.disable();
            }
        });

        map.on('mousemove', function(e) {
            if (mapState.mode === 'bounds' && mapState.isSelecting && mapState.boundsStartPoint) {
                updateBoundsPreview(mapState.boundsStartPoint, e.latlng);
            }
        });

        map.on('mouseup', function(e) {
            if (mapState.mode === 'bounds' && mapState.isSelecting) {
                mapState.isSelecting = false;
                map.dragging.enable();
                if (mapState.boundsStartPoint) {
                    finalizeBoundsSelection(mapState.boundsStartPoint, e.latlng);
                    resetMapState();
                    updatePreview();
                }
            }
        });

        // Helper functions for main map
        function addCenterMarker(lat, lng, radius) {
            clearCenter();
            mapState.centerMarker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`Center Bias<br>Radius: ${radius}m`);
            mapState.centerCircle = L.circle([lat, lng], {
                radius: radius,
                fillColor: '#2196F3',
                color: '#1976D2',
                weight: 2,
                opacity: 0.8,
                fillOpacity: 0.2
            }).addTo(map);

            centerLatInput.value = lat.toFixed(6);
            centerLngInput.value = lng.toFixed(6);
            centerRadiusInput.value = radius;
        }

        function clearCenter() {
            if (mapState.centerMarker) {
                map.removeLayer(mapState.centerMarker);
                mapState.centerMarker = null;
            }
            if (mapState.centerCircle) {
                map.removeLayer(mapState.centerCircle);
                mapState.centerCircle = null;
            }
            centerLatInput.value = '';
            centerLngInput.value = '';
            centerRadiusInput.value = '';
        }

        function updateBoundsPreview(start, end) {
            var bounds = L.latLngBounds([start, end]);
            if (mapState.boundsRectangle) {
                map.removeLayer(mapState.boundsRectangle);
            }
            mapState.boundsRectangle = L.rectangle(bounds, {
                color: '#FF5722',
                weight: 2,
                fillOpacity: 0.2
            }).addTo(map);
        }

        function finalizeBoundsSelection(start, end) {
            var north = Math.max(start.lat, end.lat);
            var south = Math.min(start.lat, end.lat);
            var east = Math.max(start.lng, end.lng);
            var west = Math.min(start.lng, end.lng);

            boundsNorthInput.value = north.toFixed(6);
            boundsSouthInput.value = south.toFixed(6);
            boundsEastInput.value = east.toFixed(6);
            boundsWestInput.value = west.toFixed(6);
        }

        function clearBounds() {
            if (mapState.boundsRectangle) {
                map.removeLayer(mapState.boundsRectangle);
                mapState.boundsRectangle = null;
            }
            boundsNorthInput.value = '';
            boundsSouthInput.value = '';
            boundsEastInput.value = '';
            boundsWestInput.value = '';
        }

        function resetMapState() {
            mapState.mode = 'none';
            mapState.isSelecting = false;
            mapState.boundsStartPoint = null;
            map.getContainer().style.cursor = '';
            map.dragging.enable();

            selectCenterBtn.classList.remove('active');
            selectBoundsBtn.classList.remove('active');

            updateInstructions('Use the buttons above to interact with the map. Click "📍 Click Map to Select Center" then click anywhere on the map to set center bias. Click "🔲 Drag Map to Select Bounds" then drag a rectangle on the map to set geographic bounds.');
        }

        function updateInstructions(text) {
            instructionsDiv.innerHTML = '💡 <strong>Instructions:</strong> ' + text;
            if (text.includes('Click') || text.includes('drag')) {
                instructionsDiv.classList.add('active');
            } else {
                instructionsDiv.classList.remove('active');
            }
        }

        // Input field change handlers to update both maps
        [centerLatInput, centerLngInput, centerRadiusInput].forEach(function(input) {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });

        [boundsNorthInput, boundsSouthInput, boundsEastInput, boundsWestInput].forEach(function(input) {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });

        // Initialize with existing values
        updatePreview();
    </script>
</body>
</html>