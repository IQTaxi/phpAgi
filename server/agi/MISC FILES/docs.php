<?php
session_start();

// Password protection
$correct_password = 'iqtaxi';
$login_required = true;

// Check if password is submitted
if (isset($_POST['password'])) {
    if ($_POST['password'] === $correct_password) {
        $_SESSION['docs_authenticated'] = true;
        $login_required = false;
    } else {
        $error_message = "Incorrect password. Please try again.";
    }
}

// Check if already authenticated
if (isset($_SESSION['docs_authenticated']) && $_SESSION['docs_authenticated'] === true) {
    $login_required = false;
}

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Serve YAML content if requested
if (isset($_GET['yaml']) && !$login_required) {
    header('Content-Type: application/x-yaml');
    header('Content-Disposition: inline; filename="agi_analytics_swagger.yaml"');
    
    $yaml_content = <<<'YAML'
openapi: 3.0.3
info:
  title: AGI Analytics API
  description: |
    Comprehensive API for the Automated Gateway Interface (AGI) Call Analytics System.
    This API provides endpoints for managing, analyzing, and reporting on taxi booking call data,
    including call records, analytics, recordings, and export functionality.
  version: 1.0.0
  contact:
    name: AGI Analytics System
    email: support@example.com
  license:
    name: MIT
    url: https://opensource.org/licenses/MIT

servers:
  - url: http://91.98.18.4
    description: Production server

tags:
  - name: calls
    description: Call data management and retrieval
  - name: analytics
    description: Analytics and statistics endpoints
  - name: export
    description: Data export functionality
  - name: recordings
    description: Audio recording management
  - name: system
    description: System utilities and health checks

paths:
  # ===== MAIN ENDPOINT WITH MULTIPLE OPERATIONS =====
  /agi_analytics.php:
    get:
      tags: [calls, analytics, recordings, system]
      summary: Main API endpoint with multiple operations
      description: |
        Main endpoint that handles multiple operations based on query parameters:
        - calls: Get paginated call records with filtering
        - call: Get detailed call information
        - analytics: Get analytics overview
        - dashboard: Get dashboard data
        - hourly: Get hourly analytics
        - daily: Get daily analytics
        - realtime: Get real-time statistics
        - locations: Get location heatmap data
        - search: Search calls
        - recordings: Get call recordings
        - server_time: Get server timestamp
      parameters:
        - name: endpoint
          in: query
          required: false
          description: |
            Operation type. If not specified, defaults to 'calls'
          schema:
            type: string
            enum:
              - calls
              - call
              - analytics
              - dashboard
              - hourly
              - daily
              - realtime
              - locations
              - search
              - recordings
              - server_time
            default: calls
        # Parameters for calls endpoint
        - name: page
          in: query
          description: Page number for pagination (calls endpoint)
          schema:
            type: integer
            minimum: 1
            default: 1
        - name: limit
          in: query
          description: Number of records per page (calls endpoint)
          schema:
            type: integer
            minimum: 1
            maximum: 1000
            default: 50
        - name: phone
          in: query
          description: Filter by phone number - partial match (calls endpoint)
          schema:
            type: string
        - name: extension
          in: query
          description: Filter by extension (calls endpoint)
          schema:
            type: string
        - name: outcome
          in: query
          description: Filter by call outcome (calls endpoint)
          schema:
            $ref: '#/components/schemas/CallOutcome'
        - name: type
          in: query
          description: Filter by call type (calls endpoint)
          schema:
            $ref: '#/components/schemas/CallType'
        - name: date_from
          in: query
          description: Start date filter YYYY-MM-DD (calls endpoint)
          schema:
            type: string
            format: date
        - name: date_to
          in: query
          description: End date filter YYYY-MM-DD (calls endpoint)
          schema:
            type: string
            format: date
        - name: search
          in: query
          description: Global search across multiple fields (calls endpoint)
          schema:
            type: string
        # Parameters for call endpoint
        - name: call_id
          in: query
          description: Unique call identifier (call endpoint)
          schema:
            type: string
        # Parameters for hourly endpoint
        - name: date
          in: query
          description: Specific date for hourly breakdown YYYY-MM-DD (hourly endpoint)
          schema:
            type: string
            format: date
        # Parameters for locations endpoint
        - name: minutes
          in: query
          description: Time period in minutes for location data (locations endpoint)
          schema:
            type: integer
            default: 60
        # Parameters for search endpoint
        - name: q
          in: query
          description: Search query (search endpoint)
          schema:
            type: string
      responses:
        '200':
          description: Successful response (varies by endpoint)
          content:
            application/json:
              schema:
                oneOf:
                  - $ref: '#/components/schemas/CallsResponse'
                  - $ref: '#/components/schemas/CallDetail'
                  - $ref: '#/components/schemas/AnalyticsResponse'
                  - $ref: '#/components/schemas/DashboardResponse'
                  - $ref: '#/components/schemas/HourlyAnalyticsResponse'
                  - $ref: '#/components/schemas/DailyAnalyticsResponse'
                  - $ref: '#/components/schemas/RealtimeStatsResponse'
                  - $ref: '#/components/schemas/LocationsResponse'
                  - $ref: '#/components/schemas/SearchResponse'
                  - $ref: '#/components/schemas/RecordingsResponse'
                  - $ref: '#/components/schemas/ServerTimeResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalServerError'
    post:
      tags: [calls, system]
      summary: POST operations (edit_call, delete_call, debug_edit)
      description: |
        Handle POST operations based on endpoint parameter:
        - edit_call: Update call record
        - delete_call: Delete call record
        - debug_edit: Debug edit functionality
      parameters:
        - name: endpoint
          in: query
          required: true
          description: POST operation type
          schema:
            type: string
            enum:
              - edit_call
              - delete_call
              - debug_edit
      requestBody:
        required: true
        content:
          application/json:
            schema:
              oneOf:
                - $ref: '#/components/schemas/EditCallRequest'
                - $ref: '#/components/schemas/DeleteCallRequest'
                - type: object
                  description: Debug request (can be any JSON object)
      responses:
        '200':
          description: Operation successful
          content:
            application/json:
              schema:
                oneOf:
                  - $ref: '#/components/schemas/SuccessResponse'
                  - $ref: '#/components/schemas/DebugResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalServerError'

  # ===== EXPORT AND AUDIO ENDPOINTS =====
  /agi_analytics.php/export:
    get:
      tags: [export]
      summary: Export data in various formats
      description: Export call data and analytics with API usage totals
      parameters:
        - name: action
          in: query
          required: true
          schema:
            type: string
            enum: [export]
        - name: format
          in: query
          required: true
          description: Export format
          schema:
            type: string
            enum: [csv, pdf, print]
        - name: date_from
          in: query
          description: Start date for export YYYY-MM-DD
          schema:
            type: string
            format: date
        - name: date_to
          in: query
          description: End date for export YYYY-MM-DD
          schema:
            type: string
            format: date
        - name: limit
          in: query
          description: Maximum number of records to export
          schema:
            type: integer
      responses:
        '200':
          description: Export successful
          content:
            text/csv:
              schema:
                type: string
                format: binary
              example: |
                Call ID,Phone Number,Extension,Start Time,Duration,Outcome
                CALL123,1234567890,101,2023-12-01 15:30:45,120,success
            application/pdf:
              schema:
                type: string
                format: binary
            text/html:
              schema:
                type: string
                description: Print-friendly HTML or PDF content
          headers:
            Content-Disposition:
              description: Filename for download
              schema:
                type: string
                example: 'attachment; filename="agi_analytics_export_2023-12-01_15-30-45.csv"'

  /agi_analytics.php/audio:
    get:
      tags: [recordings]
      summary: Stream audio files
      description: Stream audio recording files
      parameters:
        - name: action
          in: query
          required: true
          schema:
            type: string
            enum: [audio]
        - name: file
          in: query
          required: true
          description: Path to audio file
          schema:
            type: string
      responses:
        '200':
          description: Audio file streamed successfully
          content:
            audio/wav:
              schema:
                type: string
                format: binary
            audio/mp3:
              schema:
                type: string
                format: binary
            audio/ogg:
              schema:
                type: string
                format: binary
        '404':
          $ref: '#/components/responses/NotFound'

# ===== COMPONENTS =====
components:
  schemas:
    # ===== ENUMS =====
    CallOutcome:
      type: string
      enum:
        - success
        - operator_transfer
        - hangup
        - error
        - anonymous_blocked
        - user_blocked
        - in_progress
      description: Possible outcomes of a call

    CallType:
      type: string
      enum:
        - immediate
        - reservation
        - operator
      description: Type of call

    TTSProvider:
      type: string
      enum:
        - google
        - edge-tts
      description: Text-to-Speech provider used

    RecordingType:
      type: string
      enum:
        - confirmation
        - name
        - pickup
        - destination
        - reservation
        - welcome
        - dtmf
        - other
      description: Type of audio recording

    # ===== CORE MODELS =====
    CallRecord:
      type: object
      description: Complete call record from database
      properties:
        id:
          type: integer
          description: Database auto-increment ID
        call_id:
          type: string
          description: Unique call identifier
        unique_id:
          type: string
          description: Asterisk unique ID
        phone_number:
          type: string
          description: Caller's phone number
        extension:
          type: string
          description: Extension used
        call_start_time:
          type: string
          format: date-time
          description: When the call started
        call_end_time:
          type: string
          format: date-time
          nullable: true
          description: When the call ended
        call_duration:
          type: integer
          description: Call duration in seconds
        call_outcome:
          $ref: '#/components/schemas/CallOutcome'
        call_type:
          $ref: '#/components/schemas/CallType'
        is_reservation:
          type: boolean
          description: Whether this is a reservation call
        reservation_time:
          type: string
          format: date-time
          nullable: true
          description: Scheduled reservation time
        language_used:
          type: string
          default: el
          description: Language code used in call
        language_changed:
          type: boolean
          description: Whether language was changed during call
        initial_choice:
          type: string
          nullable: true
          description: User's initial menu choice
        confirmation_attempts:
          type: integer
          description: Number of confirmation attempts
        total_retries:
          type: integer
          description: Total retry attempts across all steps
        name_attempts:
          type: integer
          description: Attempts to collect customer name
        pickup_attempts:
          type: integer
          description: Attempts to collect pickup address
        destination_attempts:
          type: integer
          description: Attempts to collect destination
        reservation_attempts:
          type: integer
          description: Attempts to collect reservation time
        confirmed_default_address:
          type: boolean
          description: Whether default address was confirmed
        pickup_address:
          type: string
          nullable: true
          description: Pickup address text
        pickup_lat:
          type: number
          format: decimal
          nullable: true
          description: Pickup latitude
        pickup_lng:
          type: number
          format: decimal
          nullable: true
          description: Pickup longitude
        destination_address:
          type: string
          nullable: true
          description: Destination address text
        destination_lat:
          type: number
          format: decimal
          nullable: true
          description: Destination latitude
        destination_lng:
          type: number
          format: decimal
          nullable: true
          description: Destination longitude
        google_tts_calls:
          type: integer
          description: Number of Google TTS API calls made
        google_stt_calls:
          type: integer
          description: Number of Google STT API calls made
        edge_tts_calls:
          type: integer
          description: Number of Edge TTS API calls made
        geocoding_api_calls:
          type: integer
          description: Number of geocoding API calls made
        user_api_calls:
          type: integer
          description: Number of user-related API calls made
        registration_api_calls:
          type: integer
          description: Number of registration API calls made
        date_parsing_api_calls:
          type: integer
          description: Number of date parsing API calls made
        tts_processing_time:
          type: integer
          description: Total TTS processing time in milliseconds
        stt_processing_time:
          type: integer
          description: Total STT processing time in milliseconds
        geocoding_processing_time:
          type: integer
          description: Total geocoding processing time in milliseconds
        total_processing_time:
          type: integer
          description: Total processing time in milliseconds
        successful_registration:
          type: boolean
          description: Whether registration was successful
        operator_transfer_reason:
          type: string
          nullable: true
          description: Reason for operator transfer
        error_messages:
          type: string
          nullable: true
          description: Error messages encountered
        recording_path:
          type: string
          nullable: true
          description: Path to call recordings directory
        log_file_path:
          type: string
          nullable: true
          description: Path to call log file
        progress_json_path:
          type: string
          nullable: true
          description: Path to progress JSON file
        tts_provider:
          $ref: '#/components/schemas/TTSProvider'
        callback_mode:
          type: integer
          description: Callback mode setting
        days_valid:
          type: integer
          description: Days the booking is valid
        user_name:
          type: string
          nullable: true
          description: Customer's name
        registration_result:
          type: string
          nullable: true
          description: Result of registration attempt
        api_response_time:
          type: integer
          description: API response time in milliseconds
        created_at:
          type: string
          format: date-time
          description: Record creation timestamp
        updated_at:
          type: string
          format: date-time
          description: Record last update timestamp

    CallDetail:
      type: object
      description: Enhanced call record with additional details
      allOf:
        - $ref: '#/components/schemas/CallRecord'
        - type: object
          properties:
            recordings:
              type: array
              items:
                $ref: '#/components/schemas/Recording'
              description: Audio recordings associated with the call
            call_log:
              type: array
              items:
                type: string
              description: Call log entries
            related_calls:
              type: array
              items:
                $ref: '#/components/schemas/CallRecord'
              description: Related calls from same phone number
            duration_formatted:
              type: string
              description: Human-readable duration format
            call_start_time_formatted:
              type: string
              description: Formatted start time
            call_end_time_formatted:
              type: string
              description: Formatted end time

    Recording:
      type: object
      description: Audio recording information
      properties:
        filename:
          type: string
          description: Recording filename
        path:
          type: string
          description: Full path to recording file
        size:
          type: integer
          description: File size in bytes
        duration:
          type: number
          description: Audio duration in seconds
        created:
          type: string
          format: date-time
          description: File creation time
        url:
          type: string
          description: URL for accessing the recording
        type:
          $ref: '#/components/schemas/RecordingType'
        attempt:
          type: integer
          description: Attempt number for this recording type
        description:
          type: string
          description: Human-readable description of the recording content

    Location:
      type: object
      description: Geographic location point
      properties:
        lat:
          type: number
          format: decimal
          description: Latitude
        lng:
          type: number
          format: decimal
          description: Longitude
        intensity:
          type: number
          description: Heat intensity for heatmap
        type:
          type: string
          enum: [pickup, destination]
          description: Location type

    APIUsageTotals:
      type: object
      description: API usage statistics
      properties:
        total_calls:
          type: integer
          description: Total number of calls
        total_google_tts:
          type: integer
          description: Total Google TTS API calls
        total_google_stt:
          type: integer
          description: Total Google STT API calls
        total_edge_tts:
          type: integer
          description: Total Edge TTS API calls
        total_geocoding:
          type: integer
          description: Total geocoding API calls
        total_user_api:
          type: integer
          description: Total user API calls
        total_registration_api:
          type: integer
          description: Total registration API calls
        total_date_parsing_api:
          type: integer
          description: Total date parsing API calls
        total_tts_all:
          type: integer
          description: Combined TTS calls (all providers)
        total_api_calls_all:
          type: integer
          description: Grand total of all API calls

    # ===== REQUEST MODELS =====
    EditCallRequest:
      type: object
      description: Request to edit a call record
      properties:
        call_id:
          type: string
          description: Call ID to edit
        id:
          type: integer
          description: Database ID to edit (alternative to call_id)
        phone_number:
          type: string
          description: Updated phone number
        extension:
          type: string
          description: Updated extension
        call_type:
          $ref: '#/components/schemas/CallType'
        initial_choice:
          type: string
          description: Updated initial choice
        call_outcome:
          $ref: '#/components/schemas/CallOutcome'
        name:
          type: string
          description: Updated customer name (maps to user_name in DB)
        pickup_address:
          type: string
          description: Updated pickup address
        pickup_lat:
          type: number
          description: Updated pickup latitude
        pickup_lng:
          type: number
          description: Updated pickup longitude
        destination_address:
          type: string
          description: Updated destination address
        dest_lat:
          type: number
          description: Updated destination latitude (maps to destination_lat in DB)
        dest_lng:
          type: number
          description: Updated destination longitude (maps to destination_lng in DB)
        reservation_time:
          type: string
          format: date-time
          description: Updated reservation time
      anyOf:
        - required: [call_id]
        - required: [id]

    DeleteCallRequest:
      type: object
      description: Request to delete a call record
      properties:
        call_id:
          type: string
          description: Call ID to delete
        id:
          type: integer
          description: Database ID to delete (alternative to call_id)
      anyOf:
        - required: [call_id]
        - required: [id]

    # ===== RESPONSE MODELS =====
    CallsResponse:
      type: object
      description: Paginated calls response
      properties:
        calls:
          type: array
          items:
            $ref: '#/components/schemas/CallRecord'
        total:
          type: integer
          description: Total number of records
        page:
          type: integer
          description: Current page number
        limit:
          type: integer
          description: Records per page
        total_pages:
          type: integer
          description: Total number of pages

    AnalyticsResponse:
      type: object
      description: Analytics overview response
      properties:
        total_calls:
          type: integer
        successful_calls:
          type: integer
        failed_calls:
          type: integer
        operator_transfers:
          type: integer
        average_duration:
          type: number
        api_usage:
          $ref: '#/components/schemas/APIUsageTotals'

    DashboardResponse:
      type: object
      description: Dashboard data response
      properties:
        stats:
          type: object
          description: Key statistics
        recent_calls:
          type: array
          items:
            $ref: '#/components/schemas/CallRecord'
          description: Recent call records
        api_totals:
          $ref: '#/components/schemas/APIUsageTotals'

    HourlyAnalyticsResponse:
      type: object
      description: Hourly analytics response
      properties:
        hours:
          type: array
          items:
            type: object
            properties:
              hour:
                type: integer
                minimum: 0
                maximum: 23
              calls:
                type: integer
              successful:
                type: integer
              failed:
                type: integer
        date:
          type: string
          format: date
          description: Date for hourly breakdown

    DailyAnalyticsResponse:
      type: object
      description: Daily analytics response
      properties:
        days:
          type: array
          items:
            type: object
            properties:
              date:
                type: string
                format: date
              calls:
                type: integer
              successful:
                type: integer
              failed:
                type: integer

    RealtimeStatsResponse:
      type: object
      description: Real-time statistics response
      properties:
        active_calls:
          type: integer
          description: Currently active calls
        calls_last_hour:
          type: integer
        calls_today:
          type: integer
        success_rate:
          type: number
          description: Success rate percentage
        server_time:
          type: string
          format: date-time

    LocationsResponse:
      type: object
      description: Location heatmap data response
      properties:
        locations:
          type: array
          items:
            $ref: '#/components/schemas/Location'
        pickup_count:
          type: integer
          description: Number of pickup locations
        destination_count:
          type: integer
          description: Number of destination locations
        time_period_minutes:
          type: integer
          description: Time period for data in minutes

    RecordingsResponse:
      type: object
      description: Call recordings response
      properties:
        recordings:
          type: array
          items:
            $ref: '#/components/schemas/Recording'
        call_id:
          type: string
          description: Call ID the recordings belong to

    SearchResponse:
      type: object
      description: Search results response
      properties:
        results:
          type: array
          items:
            $ref: '#/components/schemas/CallRecord'
        total:
          type: integer
          description: Total matching records
        query:
          type: string
          description: Search query used

    ServerTimeResponse:
      type: object
      description: Server time response
      properties:
        timestamp:
          type: integer
          description: Unix timestamp in milliseconds
        formatted:
          type: string
          format: date-time
          description: ISO formatted date-time string

    SuccessResponse:
      type: object
      description: Generic success response
      properties:
        success:
          type: boolean
          example: true
        message:
          type: string
          description: Success message
        updated_rows:
          type: integer
          description: Number of rows affected (for edit/delete operations)

    ErrorResponse:
      type: object
      description: Generic error response
      properties:
        success:
          type: boolean
          example: false
        error:
          type: string
          description: Error message
        message:
          type: string
          description: Detailed error description

    DebugResponse:
      type: object
      description: Debug information response
      properties:
        success:
          type: boolean
        debug:
          type: boolean
          example: true
        data_received:
          type: object
          description: Data received in request
        db_connected:
          type: boolean
          description: Database connection status
        table:
          type: string
          description: Database table name

  # ===== REUSABLE RESPONSES =====
  responses:
    BadRequest:
      description: Bad request - invalid parameters
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'

    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            allOf:
              - $ref: '#/components/schemas/ErrorResponse'
              - type: object
                properties:
                  error:
                    example: "Resource not found"

    InternalServerError:
      description: Internal server error
      content:
        application/json:
          schema:
            allOf:
              - $ref: '#/components/schemas/ErrorResponse'
              - type: object
                properties:
                  error:
                    example: "Internal server error"


# ===== EXTERNAL DOCS =====
externalDocs:
  description: AGI Analytics System Documentation
  url: https://docs.example.com/agi-analytics
YAML;
    
    echo $yaml_content;
    exit();
}

// Show login form if not authenticated
if ($login_required) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGI Analytics API Documentation - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            margin-bottom: 5px;
            font-size: 28px;
        }
        .login-header p {
            color: #666;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1e3c72;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔒 API Documentation</h1>
            <p>AGI Analytics API Documentation</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button type="submit" class="btn">Access Documentation</button>
        </form>
    </div>
</body>
</html>
<?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGI Analytics API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.10.5/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5.10.5/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5.10.5/favicon-16x16.png" sizes="16x16" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }

        *,
        *:before,
        *:after {
            box-sizing: inherit;
        }

        body {
            margin: 0;
            background: #fafafa;
        }

        .swagger-ui .topbar {
            background-color: #1b1b1b;
            padding: 10px 0;
        }

        .swagger-ui .topbar .topbar-wrapper {
            max-width: 1460px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .swagger-ui .topbar .topbar-wrapper .link {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .swagger-ui .topbar .topbar-wrapper .link img {
            height: 40px;
            margin-right: 15px;
        }

        .swagger-ui .topbar .topbar-wrapper .link span {
            color: #fff;
            font-family: sans-serif;
            font-size: 1.5em;
            font-weight: bold;
        }

        .swagger-ui .topbar .topbar-wrapper .logout-link {
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid #fff;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .swagger-ui .topbar .topbar-wrapper .logout-link:hover {
            background-color: #fff;
            color: #1b1b1b;
        }

        .swagger-ui .topbar .topbar-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #swagger-ui {
            max-width: 1460px;
            margin: 50px auto;
        }
    </style>
</head>

<body>
    <div class="swagger-ui">
        <div class="topbar">
            <div class="topbar-wrapper">
                <a href="#" class="link">
                    <span>AGI Analytics API Documentation</span>
                </a>
                <a href="?logout=1" class="logout-link">Logout</a>
            </div>
        </div>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.10.5/swagger-ui-bundle.js" crossorigin></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.5/swagger-ui-standalone-preset.js" crossorigin></script>
    <script>
        window.onload = function() {
            // Begin Swagger UI call region
            const ui = SwaggerUIBundle({
                url: '<?php echo $_SERVER['PHP_SELF']; ?>?yaml=1',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: null,
                tryItOutEnabled: true,
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                onComplete: function() {
                    console.log("Swagger UI loaded successfully");
                },
                onFailure: function(data) {
                    console.error("Failed to load Swagger UI:", data);
                }
            });
            // End Swagger UI call region

            window.ui = ui;
        };
    </script>
</body>
</html>