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
    private $language = 'el'; // Default to Greek
    private $translations = [];
    
    // Database configuration
    private $dbConfig = [
        'host' => '127.0.0.1',
        'dbname' => 'asterisk',
        'primary_user' => 'freepbxuser',
        'primary_pass' => 'nFDuTRLJSY0n',
        'fallback_user' => 'root',
        'fallback_pass' => '',
        'port' => '3306',
        'charset' => 'utf8mb4'
    ];
    
    public function __construct() {
        // Keep server in UTC for universal compatibility
        date_default_timezone_set('UTC');
        
        $this->initializeLanguage();
        $this->loadTranslations();
        $this->loadEnvConfig();
        $this->connectDatabase();
        $this->createTableIfNeeded();
        $this->createIndexesIfNeeded();
        $this->ensureRegistrationIdColumn();
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
     * Initialize language from URL parameter or default
     */
    private function initializeLanguage() {
        // Check URL parameter first
        $lang = $_GET['lang'] ?? 'el';
        
        // Validate language (only allow 'el' for Greek and 'en' for English)
        if (in_array($lang, ['el', 'en'])) {
            $this->language = $lang;
        } else {
            $this->language = 'el'; // Default to Greek
        }
    }
    
    /**
     * Load translations for the selected language
     */
    private function loadTranslations() {
        $this->translations = [
            'el' => [
                // Header and Navigation
                'dashboard_title' => 'Πίνακας Αναλυτικών Στοιχείων',
                'analytics_dashboard' => 'Πίνακας Αναλυτικών Στοιχείων',
                'realtime_monitoring' => 'Παρακολούθηση σε πραγματικό χρόνο και αναλυτικά στοιχεία κλήσεων',
                
                // Buttons and Actions
                'filters' => 'Φίλτρα',
                'export' => 'Εξαγωγή',
                'refresh' => 'Ανανέωση',
                'stop' => 'Στοπ',
                'live' => 'Ζωντανά',
                'search' => 'Αναζήτηση',
                'clear_all' => 'Καθαρισμός Όλων',
                'apply_filters' => 'Εφαρμογή Φίλτρων',
                'edit' => 'Επεξεργασία',
                'delete' => 'Διαγραφή',
                'refresh_action' => 'Ανανέωση',
                
                // Filter Modal
                'advanced_filters' => 'Προχωρημένα Φίλτρα',
                'auto_filtering_enabled' => 'Αυτόματο φιλτράρισμα ενεργοποιημένο',
                
                // Form Labels
                'phone_number' => 'Τηλέφωνο',
                'extension' => 'Εσωτερικό',
                'call_type' => 'Τύπος Κλήσης',
                'outcome' => 'Αποτέλεσμα',
                'date_from' => 'Ημερομηνία Από',
                'date_to' => 'Ημερομηνία Έως',
                'user' => 'Χρήστης',
                'location' => 'Τοποθεσία',
                
                // Call Types
                'all_types' => 'Όλοι οι Τύποι',
                'immediate' => 'Άμεση',
                'reservation' => 'Κράτηση',
                'operator' => 'Τηλεφωνητής',
                
                // Outcomes
                'all_outcomes' => 'Όλα τα Αποτελέσματα',
                'success' => 'Επιτυχία',
                'hangup' => 'Τερματισμός',
                'operator_transfer' => 'Μεταφορά σε Τηλεφωνητή',
                'error' => 'Σφάλμα',
                'in_progress' => 'Σε Εξέλιξη',
                
                // Chart Titles
                'calls_per_hour' => 'Κλήσεις ανά Ώρα',
                'location_heatmap' => 'Χάρτης Τοποθεσιών',
                'today' => 'Σήμερα',
                
                // Heatmap Controls
                'last_30_minutes' => '🕐 Τελευταία 30 λεπτά',
                'last_1_hour' => '🕐 Τελευταία 1 ώρα',
                'last_3_hours' => '🕒 Τελευταίες 3 ώρες',
                'last_6_hours' => '🕕 Τελευταίες 6 ώρες',
                'last_12_hours' => '🕙 Τελευταίες 12 ώρες',
                'last_24_hours' => '🌅 Τελευταίες 24 ώρες',
                'pickups' => 'Παραλαβές',
                'destinations' => 'Προορισμοί',
                
                // Table Headers
                'recent_calls' => 'Πρόσφατες Κλήσεις',
                'phone' => 'Τηλέφωνο',
                'time' => 'Ώρα',
                'duration' => 'Διάρκεια',
                'status' => 'Κατάσταση',
                'type' => 'Τύπος',
                'apis' => 'APIs',
                'actions' => 'Ενέργειες',
                
                // Pagination
                'per_page' => 'ανά σελίδα',
                '25_per_page' => '25 ανά σελίδα',
                '50_per_page' => '50 ανά σελίδα',
                '100_per_page' => '100 ανά σελίδα',
                
                // Heatmap States
                'loading_location_data' => 'Φόρτωση δεδομένων τοποθεσίας...',
                'fetching_locations' => 'Ανάκτηση τοποθεσιών κλήσεων από τη βάση δεδομένων',
                'no_location_data' => 'Δεν υπάρχουν δεδομένα τοποθεσίας',
                'waiting_for_calls' => 'Αναμονή για κλήσεις με δεδομένα τοποθεσίας...',
                'try_longer_period' => 'Δοκιμάστε να επιλέξετε μεγαλύτερο χρονικό διάστημα ή ελέγξτε αργότερα',
                'activity_level' => 'Επίπεδο Δραστηριότητας',
                'low' => 'Χαμηλό',
                'high' => 'Υψηλό',
                
                // Call Details
                'call_details' => 'Λεπτομέρειες Κλήσης',
                
                // Export and System Messages
                'csv_export' => 'Εξαγωγή CSV',
                'export_complete' => 'Η εξαγωγή ολοκληρώθηκε',
                'system_error' => 'Σφάλμα Συστήματος',
                'database_error' => 'Σφάλμα Βάσης Δεδομένων',
                'loading' => 'Φόρτωση...',
                'processing' => 'Επεξεργασία...',
                'no_data' => 'Δεν υπάρχουν δεδομένα',
                
                // Chart Labels
                'total_calls' => 'Συνολικές Κλήσεις',
                'successful_calls' => 'Επιτυχημένες Κλήσεις',
                'failed_calls' => 'Αποτυχημένες Κλήσεις',
                'average_duration' => 'Μέση Διάρκεια',
                'total_duration' => 'Συνολική Διάρκεια',
                
                // Status Values (for table data)
                'completed' => 'Ολοκληρώθηκε',
                'answered' => 'Απαντήθηκε',
                'busy' => 'Κατειλημμένο',
                'no_answer' => 'Δεν Απαντά',
                'failed' => 'Απέτυχε',
                'cancelled' => 'Ακυρώθηκε',
                'ongoing' => 'Σε Εξέλιξη',
                
                // Call Details Modal
                'call_information' => 'Πληροφορίες Κλήσης',
                'call_log' => 'Ιστορικό Κλήσης',
                'call_id' => 'ID Κλήσης',
                'unique_id' => 'Μοναδικό ID',
                'caller_info' => 'Στοιχεία Καλούντος',
                'call_flow' => 'Ροή Κλήσης',
                'timeline' => 'Χρονολόγιο',
                'recordings' => 'Ηχογραφήσεις',
                'user_recordings' => 'Ηχογραφήσεις Χρήστη',
                'system_recordings' => 'Ηχογραφήσεις Συστήματος',
                'user_input_audio' => 'Ηχογραφημένη Είσοδος Χρήστη',
                'technical_details' => 'Τεχνικές Λεπτομέρειες',
                'call_start' => 'Έναρξη Κλήσης',
                'call_end' => 'Τέλος Κλήσης',
                'call_outcome' => 'Αποτέλεσμα Κλήσης',
                'customer_name' => 'Όνομα Πελάτη',
                'pickup_location' => 'Τοποθεσία Παραλαβής',
                'destination_location' => 'Τοποθεσία Προορισμού',
                'reservation_time' => 'Ώρα Κράτησης',
                'notes' => 'Σημειώσεις',
                'api_calls' => 'Κλήσεις API',
                'error_messages' => 'Μηνύματα Σφάλματος',
                'system_logs' => 'Αρχεία Συστήματος',
                
                // Duration Units
                'seconds_short' => 'δ',
                'minutes_short' => 'λ',
                'hours_short' => 'ω',
                'days_short' => 'η',
                
                // Additional Status Terms
                'active' => 'Ενεργό',
                'inactive' => 'Ανενεργό',
                'pending' => 'Εκκρεμές',
                'processing' => 'Επεξεργάζεται',
                'connecting' => 'Συνδέεται',
                'ringing' => 'Χτυπά',
                'talking' => 'Ομιλία',
                
                // Time and Date
                'from' => 'Από',
                'to' => 'Έως',
                'at' => 'στις',
                'duration_label' => 'Διάρκεια',
                'start_time' => 'Ώρα Έναρξης',
                'end_time' => 'Ώρα Τέλους',
                
                // Statistics Labels
                'total_calls_today' => 'Συνολικές Κλήσεις Σήμερα',
                'active' => 'Ενεργές',
                'successful_calls' => 'Επιτυχημένες Κλήσεις',
                'success_rate' => 'ποσοστό επιτυχίας',
                'avg_duration' => 'Μέση Διάρκεια',
                'per_call_average' => 'Μέσος όρος ανά κλήση',
                'unique_callers' => 'Μοναδικοί Καλούντες',
                'different_numbers' => 'Διαφορετικοί αριθμοί',
                'yes' => 'Ναι',
                'no' => 'Όχι',
                
                // Export Dialog
                'export_data' => 'Εξαγωγή Δεδομένων',
                'csv_export' => 'Εξαγωγή CSV',
                'pdf_export' => 'Εξαγωγή PDF', 
                'print_view' => 'Προβολή Εκτύπωσης',
                'download_data_spreadsheet' => 'Κατέβασμα δεδομένων ως αρχείο υπολογιστικού φύλλου (.csv)',
                'best_for_data_analysis' => 'Καλύτερο για ανάλυση δεδομένων και Excel',
                'generate_formatted_pdf' => 'Δημιουργία μορφοποιημένης αναφοράς PDF',
                'best_for_presentations' => 'Καλύτερο για παρουσιάσεις και αναφορές',
                'open_print_friendly' => 'Άνοιγμα φιλικής προς εκτύπωση μορφής',
                'best_for_printing' => 'Καλύτερο για άμεση εκτύπωση',
                'export_options' => 'Επιλογές Εξαγωγής',
                'date_range' => 'Εύρος Ημερομηνιών',
                'to' => 'έως',
                'include_current_filters' => 'Συμπερίληψη τρεχόντων φίλτρων',
                'apply_current_search_filters' => 'Εφαρμογή ενεργών φίλτρων αναζήτησης στην εξαγωγή',
                'records_limit' => 'Όριο Εγγραφών',
                'last_100_records' => 'Τελευταίες 100 εγγραφές',
                'last_500_records' => 'Τελευταίες 500 εγγραφές', 
                'last_1000_records' => 'Τελευταίες 1000 εγγραφές',
                'all_records' => 'Όλες οι εγγραφές',
                'edit_call' => 'Επεξεργασία Κλήσης',
                
                // Call Details Modal Fields
                'phone_number_label' => 'Αριθμός Τηλεφώνου',
                'extension_label' => 'Εσωτερικό',
                'duration_label' => 'Διάρκεια',
                'status_label' => 'Κατάσταση',
                'user_name_label' => 'Όνομα Χρήστη',
                'language_label' => 'Γλώσσα',
                'api_calls_label' => 'Κλήσεις API',
                'location_information' => 'Πληροφορίες Τοποθεσίας',
                'pickup_address_label' => 'Διεύθυνση Παραλαβής',
                'destination_address_label' => 'Διεύθυνση Προορισμού',
                'confirmation_audio' => 'Ήχος Επιβεβαίωσης',
                'system_generated_confirmation' => 'Μήνυμα επιβεβαίωσης που δημιουργήθηκε από το σύστημα για επαλήθευση των στοιχείων κράτησης.',
                'customer_name_recording' => 'Ηχογράφηση Ονόματος Πελάτη',
                'pickup_address_recording' => 'Ηχογράφηση Διεύθυνσης Παραλαβής',
                'destination_recording' => 'Ηχογράφηση Προορισμού',
                'reservation_time_recording' => 'Ηχογράφηση Ώρας Κράτησης',
                'user_said_name' => 'Πελάτης είπε το όνομά του',
                'user_said_pickup' => 'Πελάτης είπε τη διεύθυνση παραλαβής',
                'user_said_destination' => 'Πελάτης είπε τη διεύθυνση προορισμού',
                'user_said_reservation' => 'Πελάτης είπε την ώρα κράτησης',
                'welcome_message' => 'Μήνυμα Καλωσορίσματος',
                'dtmf_input_recording' => 'Ηχογράφηση Εισαγωγής DTMF',
                'call_recording' => 'Ηχογράφηση Κλήσης',
                'attempt' => 'Προσπάθεια',
                'kb_size' => 'KB',
                'bytes_size' => 'bytes',
                'audio_not_supported' => 'Ο φυλλομετρητής σας δεν υποστηρίζει το στοιχείο ήχου.',
                
                // Placeholders
                'placeholder_search' => 'Τηλέφωνο, ID Κλήσης, Χρήστης...',
                'placeholder_phone' => 'Αριθμός τηλεφώνου',
                'placeholder_extension' => 'Εσωτερικό'
            ],
            'en' => [
                // Header and Navigation
                'dashboard_title' => 'Analytics Dashboard',
                'analytics_dashboard' => 'Analytics Dashboard',
                'realtime_monitoring' => 'Real-time call monitoring and analytics',
                
                // Buttons and Actions
                'filters' => 'Filters',
                'export' => 'Export',
                'refresh' => 'Refresh',
                'stop' => 'Stop',
                'live' => 'Live',
                'search' => 'Search',
                'clear_all' => 'Clear All',
                'apply_filters' => 'Apply Filters',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'refresh_action' => 'Refresh',
                
                // Filter Modal
                'advanced_filters' => 'Advanced Filters',
                'auto_filtering_enabled' => 'Auto-filtering enabled',
                
                // Form Labels
                'phone_number' => 'Phone Number',
                'extension' => 'Extension',
                'call_type' => 'Call Type',
                'outcome' => 'Outcome',
                'date_from' => 'Date From',
                'date_to' => 'Date To',
                'user' => 'User',
                'location' => 'Location',
                
                // Call Types
                'all_types' => 'All Types',
                'immediate' => 'Immediate',
                'reservation' => 'Reservation',
                'operator' => 'Operator',
                
                // Outcomes
                'all_outcomes' => 'All Outcomes',
                'success' => 'Success',
                'hangup' => 'Hangup',
                'operator_transfer' => 'Operator Transfer',
                'error' => 'Error',
                'in_progress' => 'In Progress',
                
                // Chart Titles
                'calls_per_hour' => 'Calls per Hour',
                'location_heatmap' => 'Location Heatmap',
                'today' => 'Today',
                
                // Heatmap Controls
                'last_30_minutes' => '🕐 Last 30 minutes',
                'last_1_hour' => '🕐 Last 1 hour',
                'last_3_hours' => '🕒 Last 3 hours',
                'last_6_hours' => '🕕 Last 6 hours',
                'last_12_hours' => '🕙 Last 12 hours',
                'last_24_hours' => '🌅 Last 24 hours',
                'pickups' => 'Pickups',
                'destinations' => 'Destinations',
                
                // Table Headers
                'recent_calls' => 'Recent Calls',
                'phone' => 'Phone',
                'time' => 'Time',
                'duration' => 'Duration',
                'status' => 'Status',
                'type' => 'Type',
                'apis' => 'APIs',
                'actions' => 'Actions',
                
                // Pagination
                'per_page' => 'per page',
                '25_per_page' => '25 per page',
                '50_per_page' => '50 per page',
                '100_per_page' => '100 per page',
                
                // Heatmap States
                'loading_location_data' => 'Loading location data...',
                'fetching_locations' => 'Fetching call locations from database',
                'no_location_data' => 'No location data available',
                'waiting_for_calls' => 'Waiting for calls with location data...',
                'try_longer_period' => 'Try selecting a longer time period or check back later',
                'activity_level' => 'Activity Level',
                'low' => 'Low',
                'high' => 'High',
                
                // Call Details
                'call_details' => 'Call Details',
                
                // Export and System Messages
                'csv_export' => 'CSV Export',
                'export_complete' => 'Export completed',
                'system_error' => 'System Error',
                'database_error' => 'Database Error',
                'loading' => 'Loading...',
                'processing' => 'Processing...',
                'no_data' => 'No data available',
                
                // Chart Labels
                'total_calls' => 'Total Calls',
                'successful_calls' => 'Successful Calls',
                'failed_calls' => 'Failed Calls',
                'average_duration' => 'Average Duration',
                'total_duration' => 'Total Duration',
                
                // Status Values (for table data)
                'completed' => 'Completed',
                'answered' => 'Answered',
                'busy' => 'Busy',
                'no_answer' => 'No Answer',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
                'ongoing' => 'Ongoing',
                
                // Call Details Modal
                'call_information' => 'Call Information',
                'call_log' => 'Call Log',
                'call_id' => 'Call ID',
                'unique_id' => 'Unique ID',
                'caller_info' => 'Caller Information',
                'call_flow' => 'Call Flow',
                'timeline' => 'Timeline',
                'recordings' => 'Recordings',
                'user_recordings' => 'User Recordings',
                'system_recordings' => 'System Recordings',
                'user_input_audio' => 'User Voice Input',
                'technical_details' => 'Technical Details',
                'call_start' => 'Call Start',
                'call_end' => 'Call End',
                'call_outcome' => 'Call Outcome',
                'customer_name' => 'Customer Name',
                'pickup_location' => 'Pickup Location',
                'destination_location' => 'Destination Location',
                'reservation_time' => 'Reservation Time',
                'notes' => 'Notes',
                'api_calls' => 'API Calls',
                'error_messages' => 'Error Messages',
                'system_logs' => 'System Logs',
                
                // Duration Units
                'seconds_short' => 's',
                'minutes_short' => 'm',
                'hours_short' => 'h',
                'days_short' => 'd',
                
                // Additional Status Terms
                'active' => 'Active',
                'inactive' => 'Inactive',
                'pending' => 'Pending',
                'processing' => 'Processing',
                'connecting' => 'Connecting',
                'ringing' => 'Ringing',
                'talking' => 'Talking',
                
                // Time and Date
                'from' => 'From',
                'to' => 'To',
                'at' => 'at',
                'duration_label' => 'Duration',
                'start_time' => 'Start Time',
                'end_time' => 'End Time',
                
                // Statistics Labels
                'total_calls_today' => 'Total Calls Today',
                'active' => 'Active',
                'successful_calls' => 'Successful Calls',
                'success_rate' => 'success rate',
                'avg_duration' => 'Avg Duration',
                'per_call_average' => 'Per call average',
                'unique_callers' => 'Unique Callers',
                'different_numbers' => 'Different numbers',
                'yes' => 'Yes',
                'no' => 'No',
                
                // Export Dialog
                'export_data' => 'Export Data',
                'csv_export' => 'CSV Export',
                'pdf_export' => 'PDF Export',
                'print_view' => 'Print View',
                'download_data_spreadsheet' => 'Download data as spreadsheet file (.csv)',
                'best_for_data_analysis' => 'Best for data analysis and Excel',
                'generate_formatted_pdf' => 'Generate formatted PDF report',
                'best_for_presentations' => 'Best for presentations and reports',
                'open_print_friendly' => 'Open print-friendly format',
                'best_for_printing' => 'Best for immediate printing',
                'export_options' => 'Export Options',
                'date_range' => 'Date Range',
                'to' => 'to',
                'include_current_filters' => 'Include current filters',
                'apply_current_search_filters' => 'Apply currently active search filters to export',
                'records_limit' => 'Records Limit',
                'last_100_records' => 'Last 100 records',
                'last_500_records' => 'Last 500 records',
                'last_1000_records' => 'Last 1000 records',
                'all_records' => 'All records',
                'edit_call' => 'Edit Call',
                
                // Call Details Modal Fields
                'phone_number_label' => 'Phone Number',
                'extension_label' => 'Extension',
                'duration_label' => 'Duration',
                'status_label' => 'Status',
                'user_name_label' => 'User Name',
                'language_label' => 'Language',
                'api_calls_label' => 'API Calls',
                'location_information' => 'Location Information',
                'pickup_address_label' => 'Pickup Address',
                'destination_address_label' => 'Destination Address',
                'confirmation_audio' => 'Confirmation Audio',
                'system_generated_confirmation' => 'System-generated confirmation message for booking verification.',
                'customer_name_recording' => 'Customer Name Recording',
                'pickup_address_recording' => 'Pickup Address Recording',
                'destination_recording' => 'Destination Recording',
                'reservation_time_recording' => 'Reservation Time Recording',
                'user_said_name' => 'Customer said their name',
                'user_said_pickup' => 'Customer said pickup address',
                'user_said_destination' => 'Customer said destination',
                'user_said_reservation' => 'Customer said reservation time',
                'welcome_message' => 'Welcome Message',
                'dtmf_input_recording' => 'DTMF Input Recording',
                'call_recording' => 'Call Recording',
                'attempt' => 'Attempt',
                'kb_size' => 'KB',
                'bytes_size' => 'bytes',
                'audio_not_supported' => 'Your browser does not support the audio element.',
                
                // Placeholders
                'placeholder_search' => 'Phone, Call ID, User...',
                'placeholder_phone' => 'Phone number',
                'placeholder_extension' => 'Extension'
            ]
        ];
    }
    
    /**
     * Get translated text for the current language
     */
    private function t($key, $fallback = null) {
        $translation = $this->translations[$this->language][$key] ?? $this->translations['en'][$key] ?? $fallback ?? $key;
        return $translation;
    }
    
    /**
     * Translate status values
     */
    private function translateStatus($status) {
        $statusMap = [
            'success' => $this->t('success'),
            'hangup' => $this->t('hangup'),
            'operator_transfer' => $this->t('operator_transfer'),
            'error' => $this->t('error'),
            'in_progress' => $this->t('in_progress'),
            'completed' => $this->t('completed'),
            'answered' => $this->t('answered'),
            'failed' => $this->t('failed'),
            'ongoing' => $this->t('ongoing'),
            'busy' => $this->t('busy'),
            'no_answer' => $this->t('no_answer'),
            'cancelled' => $this->t('cancelled'),
            'active' => $this->t('active'),
            'inactive' => $this->t('inactive'),
            'pending' => $this->t('pending'),
            'processing' => $this->t('processing'),
            'connecting' => $this->t('connecting'),
            'ringing' => $this->t('ringing'),
            'talking' => $this->t('talking')
        ];
        
        return $statusMap[$status] ?? $status;
    }
    
    /**
     * Translate call type values
     */
    private function translateCallType($type) {
        // Handle null/empty call type
        if (empty($type) || $type === null) {
            return 'N/A';
        }
        
        $typeMap = [
            'immediate' => $this->t('immediate'),
            'reservation' => $this->t('reservation'),
            'operator' => $this->t('operator')
        ];
        
        return $typeMap[$type] ?? $type;
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
     * Create the table if it doesn't exist
     */
    private function createTableIfNeeded() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
            `call_id` VARCHAR(255) UNIQUE,
            `unique_id` VARCHAR(255),
            `phone_number` VARCHAR(50),
            `extension` VARCHAR(20),
            `call_start_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `call_end_time` TIMESTAMP NULL,
            `call_duration` INT(11) DEFAULT 0,
            `call_outcome` ENUM('success','operator_transfer','hangup','error','anonymous_blocked','user_blocked','in_progress') DEFAULT 'success',
            `call_type` ENUM('immediate','reservation','operator') DEFAULT NULL,
            `is_reservation` TINYINT(1) DEFAULT 0,
            `reservation_time` TIMESTAMP NULL,
            `language_used` VARCHAR(5) DEFAULT 'el',
            `language_changed` TINYINT(1) DEFAULT 0,
            `initial_choice` VARCHAR(5),
            `confirmation_attempts` INT(11) DEFAULT 0,
            `total_retries` INT(11) DEFAULT 0,
            `name_attempts` INT(11) DEFAULT 0,
            `pickup_attempts` INT(11) DEFAULT 0,
            `destination_attempts` INT(11) DEFAULT 0,
            `reservation_attempts` INT(11) DEFAULT 0,
            `confirmed_default_address` TINYINT(1) DEFAULT 0,
            `pickup_address` TEXT,
            `pickup_lat` DECIMAL(10, 8),
            `pickup_lng` DECIMAL(11, 8),
            `destination_address` TEXT,
            `destination_lat` DECIMAL(10, 8),
            `destination_lng` DECIMAL(11, 8),
            `google_tts_calls` INT(11) DEFAULT 0,
            `google_stt_calls` INT(11) DEFAULT 0,
            `edge_tts_calls` INT(11) DEFAULT 0,
            `geocoding_api_calls` INT(11) DEFAULT 0,
            `user_api_calls` INT(11) DEFAULT 0,
            `registration_api_calls` INT(11) DEFAULT 0,
            `date_parsing_api_calls` INT(11) DEFAULT 0,
            `tts_processing_time` INT(11) DEFAULT 0,
            `stt_processing_time` INT(11) DEFAULT 0,
            `geocoding_processing_time` INT(11) DEFAULT 0,
            `total_processing_time` INT(11) DEFAULT 0,
            `successful_registration` TINYINT(1) DEFAULT 0,
            `operator_transfer_reason` TEXT,
            `error_messages` TEXT,
            `recording_path` TEXT,
            `log_file_path` TEXT,
            `progress_json_path` TEXT,
            `tts_provider` ENUM('google','edge-tts') DEFAULT 'google',
            `callback_mode` INT(11) DEFAULT 1,
            `days_valid` INT(11) DEFAULT 7,
            `user_name` VARCHAR(255),
            `registration_id` VARCHAR(50),
            `registration_result` TEXT,
            `api_response_time` INT(11) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_call_id (call_id),
            INDEX idx_registration_id (registration_id),
            INDEX idx_phone_number (phone_number),
            INDEX idx_extension (extension),
            INDEX idx_call_start_time (call_start_time),
            INDEX idx_call_outcome (call_outcome),
            INDEX idx_call_type (call_type),
            INDEX idx_created_at (created_at),
            INDEX idx_pickup_coords (pickup_lat, pickup_lng)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $this->db->exec($sql);
            error_log("Analytics: Table {$this->table} created or verified successfully");
        } catch (PDOException $e) {
            error_log("Analytics: Failed to create table {$this->table}: " . $e->getMessage());
            throw $e;
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
     * Ensure registration_id column exists
     */
    private function ensureRegistrationIdColumn() {
        try {
            // Check if column exists
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$this->table} LIKE 'registration_id'");
            $stmt->execute();
            $column = $stmt->fetch();

            if (!$column) {
                // Column doesn't exist, create it
                $sql = "ALTER TABLE {$this->table} ADD COLUMN registration_id VARCHAR(50) AFTER user_name";
                $this->db->exec($sql);
                error_log("Analytics: registration_id column added successfully");

                // Create index for the new column
                $indexSql = "CREATE INDEX idx_registration_id ON {$this->table} (registration_id)";
                $this->db->exec($indexSql);
                error_log("Analytics: idx_registration_id index created successfully");
            }
        } catch (PDOException $e) {
            error_log("Analytics: Failed to ensure registration_id column: " . $e->getMessage());
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
        
        // Handle export
        if ($action === 'export') {
            $format = $_GET['format'] ?? 'csv';
            switch ($format) {
                case 'csv':
                    $this->exportCSV();
                    break;
                case 'pdf':
                    $this->exportPDF();
                    break;
                case 'print':
                    $this->exportPrint();
                    break;
                default:
                    $this->exportCSV();
                    break;
            }
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
            case 'call_by_registration_id':
                $this->apiGetCallByRegistrationId();
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
            case 'locations':
                $this->apiGetLocations();
                break;
            case 'server_time':
                $this->apiGetServerTime();
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
        
        // Handle general search field
        if (!empty($_GET['search'])) {
            $searchTerm = $_GET['search'];
            $where[] = '(phone_number LIKE ? OR call_id LIKE ? OR unique_id LIKE ? OR user_name LIKE ? OR pickup_address LIKE ? OR destination_address LIKE ? OR registration_id LIKE ?)';
            $searchPattern = "%{$searchTerm}%";
            $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);
        }
        
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
     * Get call by registration ID from registration_result JSON
     */
    private function apiGetCallByRegistrationId() {
        $registration_id = $_GET['registration_id'] ?? '';

        if (empty($registration_id)) {
            $this->sendErrorResponse('registration_id required', 400);
            return;
        }

        $sql = "SELECT * FROM {$this->table} WHERE registration_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$registration_id]);
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
                extension LIKE ? OR
                registration_id LIKE ?
                ORDER BY call_start_time DESC
                LIMIT {$limit}";

        $searchTerm = "%{$query}%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_fill(0, 8, $searchTerm));
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
        try {
            $dashboard = [
                'realtime_stats' => $this->getRealtimeStats(),
                'recent_calls' => $this->getRecentCalls(),
                'today_summary' => $this->getTodaySummary(),
                'active_calls' => $this->getActiveCalls(),
                'system_health' => $this->getSystemHealth()
            ];
            
            $this->sendResponse($dashboard);
        } catch (Exception $e) {
            error_log("Dashboard API Error: " . $e->getMessage());
            error_log("Dashboard API Trace: " . $e->getTraceAsString());
            $this->sendErrorResponse('Dashboard data error: ' . $e->getMessage(), 500);
        }
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
            $found = array_filter($hourlyData, function($item) use ($h) { return $item['hour'] == $h; });
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
            case 'delete_call':
                $this->apiDeleteCall($data);
                break;
            case 'edit_call':
                $this->apiEditCall($data);
                break;
            case 'debug_edit':
                $this->apiDebugEdit($data);
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
            'call_type' => null,
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
    private function apiDeleteCall($data = null) {
        // Handle both GET and POST requests
        $id = $data['id'] ?? $_GET['id'] ?? '';
        $callId = $data['call_id'] ?? $_GET['call_id'] ?? '';
        
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

    private function apiEditCall($data) {
        // Check database connection
        if (!$this->db) {
            error_log("Analytics: No database connection in apiEditCall");
            $this->sendErrorResponse('Database connection failed', 500);
            return;
        }
        
        $id = $data['id'] ?? '';
        $callId = $data['call_id'] ?? '';
        
        if (empty($id) && empty($callId)) {
            $this->sendErrorResponse('ID or call_id required', 400);
            return;
        }
        
        // Build the update query dynamically based on provided fields
        $fields = [];
        $values = [];
        $allowedFields = [
            'phone_number', 'extension', 'call_type', 'initial_choice', 'call_outcome',
            'name', 'pickup_address', 'pickup_lat', 'pickup_lng', 
            'destination_address', 'dest_lat', 'dest_lng', 'reservation_time'
        ];
        
        // Map frontend field names to database column names
        $fieldMap = [
            'name' => 'user_name',                    // Frontend 'name' maps to DB 'user_name'
            'dest_lat' => 'destination_lat',          // Frontend 'dest_lat' maps to DB 'destination_lat'
            'dest_lng' => 'destination_lng',          // Frontend 'dest_lng' maps to DB 'destination_lng'
            'pickup_address' => 'pickup_address',     // These are correct
            'destination_address' => 'destination_address',
            'pickup_lat' => 'pickup_lat', 
            'pickup_lng' => 'pickup_lng'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                // Use mapped field name if available, otherwise use original
                $dbField = $fieldMap[$field] ?? $field;
                $fields[] = "$dbField = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            $this->sendErrorResponse('No valid fields to update', 400);
            return;
        }
        
        // Add updated timestamp (if column exists)
        try {
            $checkCol = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'updated_at'");
            if ($checkCol && $checkCol->rowCount() > 0) {
                $fields[] = "updated_at = NOW()";
            }
        } catch (Exception $e) {
            // Column check failed, skip adding updated_at
            error_log("Analytics: Could not check for updated_at column: " . $e->getMessage());
        }
        
        // Add the WHERE condition
        $values[] = !empty($id) ? $id : $callId;
        $whereField = !empty($id) ? "id" : "call_id";
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE $whereField = ?";
        
        // Debug logging (can be removed in production)
        error_log("Analytics: Update SQL: " . $sql);
        error_log("Analytics: Update values: " . json_encode($values));
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $affected = $stmt->rowCount();
            
            if ($affected > 0) {
                $this->sendResponse(['success' => true, 'updated_rows' => $affected, 'message' => 'Call record updated successfully']);
            } else {
                $this->sendErrorResponse('No call found with the provided ID', 404);
            }
        } catch (PDOException $e) {
            error_log("Analytics: Update call error: " . $e->getMessage());
            error_log("Analytics: SQL that failed: " . $sql);
            error_log("Analytics: Values that failed: " . json_encode($values));
            $this->sendErrorResponse('Failed to update call record: ' . $e->getMessage(), 500);
        }
    }

    private function apiDebugEdit($data) {
        error_log("Analytics: Debug edit data: " . json_encode($data));
        error_log("Analytics: Database connection status: " . ($this->db ? 'Connected' : 'Not connected'));
        error_log("Analytics: Table name: " . $this->table);
        
        $this->sendResponse([
            'success' => true, 
            'debug' => true,
            'data_received' => $data,
            'db_connected' => $this->db ? true : false,
            'table' => $this->table
        ]);
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
        
        // Calculate live duration for in-progress calls using server time
        if ($call['call_outcome'] === 'in_progress' && !empty($call['call_start_time'])) {
            $startTime = strtotime($call['call_start_time']);
            $currentTime = time(); // Server time
            $call['call_duration'] = $currentTime - $startTime; // Override with live duration
            $call['is_live'] = true; // Flag to indicate this is a live call
        } else {
            $call['is_live'] = false;
        }
        
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
            'callback_mode', 'days_valid', 'user_name', 'registration_id', 'registration_result',
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
    
    /**
     * Get current server time
     */
    private function apiGetServerTime() {
        try {
            $sql = "SELECT NOW() as server_time";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            
            $this->sendResponse([
                'server_time' => $result['server_time'],
                'timestamp' => strtotime($result['server_time']) * 1000 // JavaScript timestamp
            ]);
        } catch (Exception $e) {
            error_log("Server Time API Error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to get server time: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get locations for heatmap
     */
    private function apiGetLocations() {
        $minutes = intval($_GET['minutes'] ?? 30);
        $dateFrom = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        $sql = "SELECT 
                    pickup_address,
                    pickup_lat,
                    pickup_lng,
                    destination_address,
                    destination_lat,
                    destination_lng,
                    call_outcome,
                    call_start_time
                FROM {$this->table}
                WHERE call_start_time >= ?
                    AND (pickup_lat IS NOT NULL OR destination_lat IS NOT NULL)
                ORDER BY call_start_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateFrom]);
        $locations = [];
        
        while ($row = $stmt->fetch()) {
            if ($row['pickup_lat'] && $row['pickup_lng']) {
                $locations[] = [
                    'lat' => floatval($row['pickup_lat']),
                    'lng' => floatval($row['pickup_lng']),
                    'type' => 'pickup',
                    'address' => $row['pickup_address'],
                    'outcome' => $row['call_outcome'],
                    'time' => $row['call_start_time']
                ];
            }
            if ($row['destination_lat'] && $row['destination_lng']) {
                $locations[] = [
                    'lat' => floatval($row['destination_lat']),
                    'lng' => floatval($row['destination_lng']),
                    'type' => 'destination',
                    'address' => $row['destination_address'],
                    'outcome' => $row['call_outcome'],
                    'time' => $row['call_start_time']
                ];
            }
        }
        
        $this->sendResponse([
            'locations' => $locations,
            'count' => count($locations),
            'period_minutes' => $minutes,
            'from' => $dateFrom
        ]);
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
        try {
            return [
                'active_calls' => $this->getActiveCalls(),
                'calls_last_hour' => $this->getCallsLastHour(),
                'success_rate_today' => $this->getTodaySuccessRate(),
                'avg_duration_today' => $this->getTodayAvgDuration()
            ];
        } catch (Exception $e) {
            error_log("getRealtimeStats Error: " . $e->getMessage());
            return [
                'active_calls' => 0,
                'calls_last_hour' => 0,
                'success_rate_today' => 0,
                'avg_duration_today' => 0
            ];
        }
    }
    
    private function getRecentCalls($limit = 20) {
        try {
            $sql = "SELECT * FROM {$this->table} ORDER BY call_start_time DESC LIMIT ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            $calls = $stmt->fetchAll();
            
            if (!$calls) {
                return [];
            }
            
            foreach ($calls as &$call) {
                $call = $this->enhanceCallData($call);
            }
            
            return $calls;
        } catch (Exception $e) {
            error_log("getRecentCalls Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getTodaySummary() {
        try {
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
            $result = $stmt->fetch();
            
            // Return default values if no data
            if (!$result) {
                return [
                    'total_calls' => 0,
                    'successful_calls' => 0,
                    'avg_duration' => 0,
                    'tts_usage' => 0,
                    'unique_callers' => 0
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("getTodaySummary Error: " . $e->getMessage());
            return [
                'total_calls' => 0,
                'successful_calls' => 0,
                'avg_duration' => 0,
                'tts_usage' => 0,
                'unique_callers' => 0
            ];
        }
    }
    
    private function getActiveCalls() {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE call_outcome = 'in_progress'";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getActiveCalls query failed: " . implode(" ", $this->db->errorInfo()));
                return 0;
            }
            return intval($result->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log("getActiveCalls Error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getTodayCallCount() {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE DATE(call_start_time) = CURDATE()";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getTodayCallCount query failed: " . implode(" ", $this->db->errorInfo()));
                return 0;
            }
            return intval($result->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log("getTodayCallCount Error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getCurrentHourCallCount() {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE call_start_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getCurrentHourCallCount query failed: " . implode(" ", $this->db->errorInfo()));
                return 0;
            }
            return intval($result->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log("getCurrentHourCallCount Error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getCallsLastHour() {
        return $this->getCurrentHourCallCount();
    }
    
    private function getTodaySuccessRate() {
        try {
            $sql = "SELECT 
                        COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as success_rate
                    FROM {$this->table} 
                    WHERE DATE(call_start_time) = CURDATE()";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getTodaySuccessRate query failed: " . implode(" ", $this->db->errorInfo()));
                return 0;
            }
            $value = $result->fetchColumn();
            return round(floatval($value ?: 0), 2);
        } catch (Exception $e) {
            error_log("getTodaySuccessRate Error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getTodayAvgDuration() {
        try {
            $sql = "SELECT AVG(call_duration) FROM {$this->table} WHERE DATE(call_start_time) = CURDATE()";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getTodayAvgDuration query failed: " . implode(" ", $this->db->errorInfo()));
                return 0;
            }
            $value = $result->fetchColumn();
            return round(floatval($value ?: 0), 2);
        } catch (Exception $e) {
            error_log("getTodayAvgDuration Error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getAverageResponseTime() {
        try {
            $sql = "SELECT AVG(api_response_time) FROM {$this->table} WHERE DATE(call_start_time) = CURDATE() AND api_response_time > 0";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getAverageResponseTime query failed: " . implode(" ", $this->db->errorInfo()));
                return 0;
            }
            $value = $result->fetchColumn();
            return round(floatval($value ?: 0), 2);
        } catch (Exception $e) {
            error_log("getAverageResponseTime Error: " . $e->getMessage());
            return 0;
        }
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
        try {
            $sql = "SELECT MAX(call_start_time) FROM {$this->table}";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getLastCallTime query failed: " . implode(" ", $this->db->errorInfo()));
                return null;
            }
            return $result->fetchColumn() ?: null;
        } catch (Exception $e) {
            error_log("getLastCallTime Error: " . $e->getMessage());
            return null;
        }
    }
    
    private function getTodayErrorRate() {
        try {
            $sql = "SELECT 
                        COUNT(CASE WHEN call_outcome = 'error' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as error_rate
                    FROM {$this->table} 
                    WHERE DATE(call_start_time) = CURDATE()";
            $result = $this->db->query($sql);
            if ($result === false) {
                error_log("getTodayErrorRate query failed: " . implode(" ", $this->db->errorInfo()));
                return 0;
            }
            $value = $result->fetchColumn();
            return round(floatval($value ?: 0), 2);
        } catch (Exception $e) {
            error_log("getTodayErrorRate Error: " . $e->getMessage());
            return 0;
        }
    }
    
    // ===== FILE HANDLING METHODS =====
    
    private function getCallRecordings($recordingPath) {
        if (empty($recordingPath) || !is_dir($recordingPath)) {
            return [];
        }

        $recordings = [];
        $patterns = ['*.wav', '*.mp3', '*.ogg'];

        // First get system recordings from main directory
        foreach ($patterns as $pattern) {
            $files = glob($recordingPath . '/' . $pattern);
            foreach ($files as $file) {
                $filename = basename($file);
                $recordings[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => filesize($file),
                    'duration' => $this->getAudioDuration($file),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'url' => $this->getAudioURL($file),
                    'type' => $this->getRecordingType($filename),
                    'attempt' => $this->getRecordingAttempt($filename),
                    'is_user_input' => false
                ];
            }
        }

        // Now get user input recordings from recordings subdirectory
        $userRecordingsPath = $recordingPath . '/recordings';
        if (is_dir($userRecordingsPath)) {
            foreach ($patterns as $pattern) {
                $files = glob($userRecordingsPath . '/' . $pattern);
                foreach ($files as $file) {
                    $filename = basename($file);
                    $recordings[] = [
                        'filename' => $filename,
                        'path' => $file,
                        'size' => filesize($file),
                        'duration' => $this->getAudioDuration($file),
                        'created' => date('Y-m-d H:i:s', filemtime($file)),
                        'url' => $this->getAudioURL($file),
                        'type' => $this->getRecordingType($filename),
                        'attempt' => $this->getRecordingAttempt($filename),
                        'is_user_input' => true
                    ];
                }
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
            if (empty($line)) continue;
            
            // Extract timestamp and clean the message
            $timestamp = $this->extractTimestamp($line);
            $level = $this->extractLogLevel($line);
            $cleanMessage = $line;
            
            // Remove timestamp from message if found
            if ($timestamp) {
                $cleanMessage = preg_replace('/^\[?\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]?\s*/', '', $line);
            }
            
            // Categorize message type
            $category = 'general';
            if (strpos($line, 'TTS') !== false || strpos($line, 'Playing') !== false) {
                $category = 'tts';
            } elseif (strpos($line, 'API') !== false || strpos($line, 'Registration') !== false) {
                $category = 'api';
            } elseif (strpos($line, 'User') !== false || strpos($line, 'choice') !== false || strpos($line, 'DTMF') !== false) {
                $category = 'user_input';
            } elseif (strpos($line, 'Error') !== false || strpos($line, 'Failed') !== false) {
                $category = 'error';
                $level = 'error';
            } elseif (strpos($line, 'Redirecting') !== false || strpos($line, 'operator') !== false) {
                $category = 'operator';
            } elseif (strpos($line, 'pickup') !== false || strpos($line, 'destination') !== false || strpos($line, 'address') !== false) {
                $category = 'location';
            }
            
            $parsedLog[] = [
                'timestamp' => $timestamp,
                'level' => $level,
                'category' => $category,
                'message' => $cleanMessage,
                'original' => $line
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
        // Try to get actual duration using ffprobe if available
        $duration = 0;
        if (function_exists('shell_exec')) {
            $cmd = "ffprobe -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 \"$filePath\" 2>/dev/null";
            $result = shell_exec($cmd);
            if ($result && is_numeric(trim($result))) {
                $duration = (float)trim($result);
            }
        }
        
        // Fallback to file size estimation if ffprobe not available
        if ($duration == 0) {
            // Estimate based on 16kHz 16-bit mono WAV (32KB per second)
            $duration = max(1, round(filesize($filePath) / 32000));
        }
        
        return $duration;
    }
    
    private function getAudioURL($filePath) {
        // Generate URL for audio playback
        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath);
        return $relativePath;
    }
    
    private function getRecordingType($filename) {
        $lower = strtolower($filename);

        // Check for confirmation files first (like pickup_confirm.wav)
        if (strpos($lower, 'confirm') !== false) return 'confirmation';

        // Check for specific user recording patterns (name_1.wav, pickup_2.wav, etc.)
        if (preg_match('/^name_\d+\./i', $filename)) return 'name';
        if (preg_match('/^pickup_\d+\./i', $filename)) return 'pickup';
        if (preg_match('/^dest_\d+\./i', $filename)) return 'destination';
        if (preg_match('/^reservation_\d+\./i', $filename)) return 'reservation';

        // Check for other patterns
        if (strpos($lower, 'name') !== false) return 'name';
        if (strpos($lower, 'pickup') !== false && strpos($lower, 'confirm') === false) return 'pickup';
        if (strpos($lower, 'dest') !== false) return 'destination';
        if (strpos($lower, 'reservation') !== false || strpos($lower, 'date') !== false) return 'reservation';
        if (strpos($lower, 'welcome') !== false || strpos($lower, 'greeting') !== false) return 'welcome';
        if (strpos($lower, 'dtmf') !== false || strpos($lower, 'choice') !== false) return 'dtmf';

        return 'other';
    }
    
    private function getRecordingAttempt($filename) {
        // Extract attempt number from filename (e.g., "name_2.wav" -> 2)
        if (preg_match('/_(\d+)\./', $filename, $matches)) {
            return (int)$matches[1];
        }
        return 1; // Default to attempt 1 if no number found
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
        if (!$timestamp) return '';
        
        $time = strtotime($timestamp);
        if ($this->language === 'el') {
            // Greek date format: dd/mm/yyyy HH:mm
            return date('d/m/Y H:i', $time);
        } else {
            // English date format: Mon j, Y g:i A
            return date('M j, Y g:i A', $time);
        }
    }
    
    private function formatDuration($seconds) {
        $secondsUnit = $this->t('seconds_short');
        $minutesUnit = $this->t('minutes_short');
        $hoursUnit = $this->t('hours_short');
        
        if ($seconds < 60) {
            return "{$seconds}{$secondsUnit}";
        } elseif ($seconds < 3600) {
            $mins = floor($seconds / 60);
            $secs = $seconds % 60;
            return $secs > 0 ? "{$mins}{$minutesUnit} {$secs}{$secondsUnit}" : "{$mins}{$minutesUnit}";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            
            $result = "{$hours}{$hoursUnit}";
            if ($minutes > 0) {
                $result .= " {$minutes}{$minutesUnit}";
            }
            if ($secs > 0) {
                $result .= " {$secs}{$secondsUnit}";
            }
            return $result;
        }
    }
    
    // ===== CSV EXPORT =====
    
    private function exportCSV() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="agi_analytics_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for proper encoding in Excel
        fputs($output, "\xEF\xBB\xBF");
        
        // CSV Headers (translated)
        $headers = [
            'ID', 
            $this->t('call_id', 'Call ID'), 
            $this->t('unique_id', 'Unique ID'), 
            $this->t('phone_number', 'Phone Number'), 
            $this->t('extension', 'Extension'), 
            $this->t('call_start', 'Call Start Time'), 
            $this->t('call_end', 'Call End Time'), 
            $this->t('duration_label', 'Duration') . ' (' . $this->t('seconds_short', 's') . ')', 
            $this->t('call_outcome', 'Call Outcome'), 
            $this->t('call_type', 'Call Type'), 
            $this->t('reservation', 'Is Reservation'), 
            $this->t('reservation_time', 'Reservation Time'), 
            $this->language === 'el' ? 'Γλώσσα που Χρησιμοποιήθηκε' : 'Language Used', 
            $this->language === 'el' ? 'Αλλαγή Γλώσσας' : 'Language Changed', 
            $this->language === 'el' ? 'Αρχική Επιλογή' : 'Initial Choice', 
            $this->language === 'el' ? 'Προσπάθειες Επιβεβαίωσης' : 'Confirmation Attempts', 
            $this->language === 'el' ? 'Συνολικές Επαναλήψεις' : 'Total Retries',
            $this->language === 'el' ? 'Προσπάθειες Ονόματος' : 'Name Attempts', 
            $this->language === 'el' ? 'Προσπάθειες Παραλαβής' : 'Pickup Attempts', 
            $this->language === 'el' ? 'Προσπάθειες Προορισμού' : 'Destination Attempts', 
            $this->language === 'el' ? 'Προσπάθειες Κράτησης' : 'Reservation Attempts',
            $this->language === 'el' ? 'Επιβεβαίωση Προεπιλεγμένης Διεύθυνσης' : 'Confirmed Default Address', 
            $this->t('pickup_location', 'Pickup Address'), 
            $this->language === 'el' ? 'Γεωγραφικό Πλάτος Παραλαβής' : 'Pickup Latitude', 
            $this->language === 'el' ? 'Γεωγραφικό Μήκος Παραλαβής' : 'Pickup Longitude',
            $this->t('destination_location', 'Destination Address'), 
            $this->language === 'el' ? 'Γεωγραφικό Πλάτος Προορισμού' : 'Destination Latitude', 
            $this->language === 'el' ? 'Γεωγραφικό Μήκος Προορισμού' : 'Destination Longitude', 
            $this->language === 'el' ? 'Κλήσεις Google TTS' : 'Google TTS Calls',
            $this->language === 'el' ? 'Κλήσεις Google STT' : 'Google STT Calls', 
            $this->language === 'el' ? 'Κλήσεις Edge TTS' : 'Edge TTS Calls', 
            $this->language === 'el' ? 'Κλήσεις Geocoding API' : 'Geocoding API Calls', 
            $this->language === 'el' ? 'Κλήσεις User API' : 'User API Calls', 
            $this->language === 'el' ? 'Κλήσεις Registration API' : 'Registration API Calls', 
            $this->language === 'el' ? 'Κλήσεις Date Parsing API' : 'Date Parsing API Calls', 
            $this->language === 'el' ? 'Χρόνος Επεξεργασίας TTS (ms)' : 'TTS Processing Time (ms)', 
            $this->language === 'el' ? 'Χρόνος Επεξεργασίας STT (ms)' : 'STT Processing Time (ms)', 
            $this->language === 'el' ? 'Χρόνος Επεξεργασίας Geocoding (ms)' : 'Geocoding Processing Time (ms)', 
            $this->language === 'el' ? 'Συνολικός Χρόνος Επεξεργασίας (ms)' : 'Total Processing Time (ms)',
            $this->language === 'el' ? 'Επιτυχημένη Εγγραφή' : 'Successful Registration', 
            $this->language === 'el' ? 'Αιτία Μεταφοράς σε Τηλεφωνητή' : 'Operator Transfer Reason', 
            $this->t('error_messages', 'Error Messages'), 
            $this->language === 'el' ? 'Διαδρομή Ηχογράφησης' : 'Recording Path',
            $this->language === 'el' ? 'Διαδρομή Αρχείου Log' : 'Log File Path', 
            $this->language === 'el' ? 'Διαδρομή Progress JSON' : 'Progress JSON Path', 
            $this->language === 'el' ? 'Πάροχος TTS' : 'TTS Provider', 
            $this->language === 'el' ? 'Λειτουργία Callback' : 'Callback Mode', 
            $this->language === 'el' ? 'Ημέρες Ισχύος' : 'Days Valid',
            $this->language === 'el' ? 'Όνομα Χρήστη' : 'User Name', 
            $this->language === 'el' ? 'Αποτέλεσμα Εγγραφής' : 'Registration Result', 
            $this->language === 'el' ? 'Χρόνος Απόκρισης API (ms)' : 'API Response Time (ms)', 
            $this->language === 'el' ? 'Δημιουργήθηκε στις' : 'Created At', 
            $this->language === 'el' ? 'Ενημερώθηκε στις' : 'Updated At'
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
        
        // Add limit if specified
        if (!empty($_GET['limit']) && $_GET['limit'] !== 'all' && is_numeric($_GET['limit'])) {
            $sql .= " LIMIT " . intval($_GET['limit']);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        // Initialize totals counters
        $totalCalls = 0;
        $totalGoogleTTS = 0;
        $totalGoogleSTT = 0;
        $totalEdgeTTS = 0;
        $totalGeocoding = 0;
        $totalUserAPI = 0;
        $totalRegistrationAPI = 0;
        $totalDateParsingAPI = 0;
        
        while ($row = $stmt->fetch()) {
            $csvRow = [
                $row['id'], $row['call_id'], $row['unique_id'], $row['phone_number'], $row['extension'],
                $row['call_start_time'], $row['call_end_time'], $row['call_duration'], 
                $this->translateStatus($row['call_outcome']),
                $this->translateCallType($row['call_type']), 
                $row['is_reservation'] ? ($this->language === 'el' ? 'Ναι' : 'Yes') : ($this->language === 'el' ? 'Όχι' : 'No'), 
                $row['reservation_time'],
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
                $row['callback_mode'], $row['days_valid'], $row['user_name'], $row['registration_id'], $row['registration_result'],
                $row['api_response_time'], $row['created_at'], $row['updated_at']
            ];
            
            fputcsv($output, $csvRow);
            
            // Accumulate totals
            $totalCalls++;
            $totalGoogleTTS += (int)$row['google_tts_calls'];
            $totalGoogleSTT += (int)$row['google_stt_calls'];
            $totalEdgeTTS += (int)$row['edge_tts_calls'];
            $totalGeocoding += (int)$row['geocoding_api_calls'];
            $totalUserAPI += (int)$row['user_api_calls'];
            $totalRegistrationAPI += (int)$row['registration_api_calls'];
            $totalDateParsingAPI += (int)$row['date_parsing_api_calls'];
        }
        
        // Add summary rows
        fputcsv($output, []); // Empty row for separation
        fputcsv($output, ['=== SUMMARY ===']);
        fputcsv($output, ['Total Calls', $totalCalls]);
        fputcsv($output, ['Total Google TTS Calls', $totalGoogleTTS]);
        fputcsv($output, ['Total Google STT Calls', $totalGoogleSTT]);
        fputcsv($output, ['Total Edge TTS Calls', $totalEdgeTTS]);
        fputcsv($output, ['Total Geocoding API Calls', $totalGeocoding]);
        fputcsv($output, ['Total User API Calls', $totalUserAPI]);
        fputcsv($output, ['Total Registration API Calls', $totalRegistrationAPI]);
        fputcsv($output, ['Total Date Parsing API Calls', $totalDateParsingAPI]);
        fputcsv($output, ['Total TTS Calls (All)', $totalGoogleTTS + $totalEdgeTTS]);
        fputcsv($output, ['Total API Calls (All)', $totalGoogleTTS + $totalGoogleSTT + $totalEdgeTTS + $totalGeocoding + $totalUserAPI + $totalRegistrationAPI + $totalDateParsingAPI]);
        
        fclose($output);
        exit;
    }
    
    // ===== PDF EXPORT =====
    
    private function exportPDF() {
        // Get data using same filters as CSV
        $exportResult = $this->getExportData();
        $data = $exportResult['data'];
        $totals = $exportResult['totals'];
        
        header('Content-Type: text/html; charset=utf-8');
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Call Analytics Report - <?= date('Y-m-d H:i:s') ?></title>
            <style>
                @media print {
                    body { margin: 0; padding: 20px; }
                    .no-print { display: none !important; }
                    .download-info { display: none !important; }
                }
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: white; 
                    color: #000;
                }
                .download-info {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px;
                    border-radius: 12px;
                    text-align: center;
                    margin-bottom: 30px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                }
                .download-info h3 {
                    margin: 0 0 10px 0;
                    font-size: 20px;
                }
                .download-info p {
                    margin: 10px 0;
                    opacity: 0.9;
                }
                .btn {
                    padding: 12px 24px;
                    margin: 5px;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: bold;
                    text-decoration: none;
                    display: inline-block;
                    font-size: 14px;
                    transition: all 0.3s ease;
                }
                .btn-primary { 
                    background: #fff; 
                    color: #667eea; 
                    box-shadow: 0 2px 8px rgba(255,255,255,0.3);
                }
                .btn-secondary { 
                    background: rgba(255,255,255,0.2); 
                    color: white; 
                    border: 1px solid rgba(255,255,255,0.3);
                }
                .btn:hover { 
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    border-bottom: 3px solid #2563eb;
                    padding-bottom: 20px;
                }
                .header h1 { 
                    color: #2563eb; 
                    margin: 0; 
                    font-size: 28px;
                }
                .header p { 
                    color: #666; 
                    margin: 5px 0; 
                    font-size: 14px;
                }
                .stats { 
                    display: flex; 
                    gap: 20px; 
                    margin: 20px 0; 
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .stat-card { 
                    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                    padding: 15px; 
                    border-radius: 8px; 
                    text-align: center; 
                    min-width: 120px;
                    border: 1px solid #e5e7eb;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .stat-number { 
                    font-size: 24px; 
                    font-weight: bold; 
                    color: #2563eb; 
                }
                .stat-label { 
                    color: #666; 
                    font-size: 12px; 
                    text-transform: uppercase; 
                    margin-top: 5px;
                    font-weight: 600;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 20px; 
                    font-size: 11px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 8px; 
                    text-align: left; 
                }
                th { 
                    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                    font-weight: bold; 
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                tr:nth-child(even) { 
                    background-color: #f9f9f9; 
                }
                tr:hover {
                    background-color: #f0f9ff;
                }
                .success { color: #10b981; font-weight: bold; }
                .failed { color: #ef4444; font-weight: bold; }
                .warning { color: #f59e0b; font-weight: bold; }
                
                .footer {
                    margin-top: 30px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #666;
                    border-top: 1px solid #e5e7eb;
                    padding-top: 20px;
                }
            </style>
            <script>
                window.onload = function() {
                    // Auto-trigger print dialog for PDF generation after a short delay
                    setTimeout(function() {
                        window.print();
                    }, 1000);
                }
            </script>
        </head>
        <body>
            <div class="download-info no-print">
                <h3>📄 PDF Download Instructions</h3>
                <p>Your browser's print dialog will open automatically. To save as PDF:</p>
                <p><strong>1.</strong> Choose "Save as PDF" or "Microsoft Print to PDF" as destination<br>
                   <strong>2.</strong> Click "Save" and choose your download location</p>
                <button class="btn btn-primary" onclick="window.print()">🖨️ Open Print Dialog</button>
                <button class="btn btn-secondary" onclick="window.close()">✕ Close Window</button>
            </div>
            
            <div class="header">
                <h1>📊 Call Analytics Report</h1>
                <p>Generated on <?= date('F j, Y \a\t g:i A') ?></p>
                <?php if (!empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <p>Period: 
                    <?= !empty($_GET['date_from']) ? date('M j, Y', strtotime($_GET['date_from'])) : 'Beginning' ?>
                    - 
                    <?= !empty($_GET['date_to']) ? date('M j, Y', strtotime($_GET['date_to'])) : 'Present' ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($_GET['limit']) && $_GET['limit'] !== 'all'): ?>
                <p><small>Limited to <?= intval($_GET['limit']) ?> most recent records</small></p>
                <?php endif; ?>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= count($data) ?></div>
                    <div class="stat-label">Total Calls</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number success"><?= count(array_filter($data, function($r) { return $r['call_outcome'] === 'successful_registration'; })) ?></div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number failed"><?= count(array_filter($data, function($r) { return in_array($r['call_outcome'], ['hangup', 'error']); })) ?></div>
                    <div class="stat-label">Failed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number warning"><?= count(array_filter($data, function($r) { return $r['call_outcome'] === 'operator_transfer'; })) ?></div>
                    <div class="stat-label">Transferred</div>
                </div>
            </div>
            
            <!-- API Usage Statistics -->
            <div class="api-stats">
                <h3 style="margin: 2rem 0 1rem; color: #333; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">📊 API Usage Summary</h3>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number" style="color: #e74c3c;"><?= number_format($totals['total_google_stt']) ?></div>
                        <div class="stat-label">Total STT Calls</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #3498db;"><?= number_format($totals['total_tts_all']) ?></div>
                        <div class="stat-label">Total TTS Calls</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #f39c12;"><?= number_format($totals['total_geocoding']) ?></div>
                        <div class="stat-label">Total Geocoding</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #9b59b6;"><?= number_format($totals['total_api_calls_all']) ?></div>
                        <div class="stat-label">Total API Calls</div>
                    </div>
                </div>
                <div class="stats" style="margin-top: 1rem;">
                    <div class="stat-card">
                        <div class="stat-number" style="color: #27ae60;"><?= number_format($totals['total_google_tts']) ?></div>
                        <div class="stat-label">Google TTS</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #2ecc71;"><?= number_format($totals['total_edge_tts']) ?></div>
                        <div class="stat-label">Edge TTS</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #1abc9c;"><?= number_format($totals['total_user_api']) ?></div>
                        <div class="stat-label">User API</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #34495e;"><?= number_format($totals['total_registration_api']) ?></div>
                        <div class="stat-label">Registration API</div>
                    </div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Phone</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Pickup Location</th>
                        <th>Destination</th>
                        <th>Lang</th>
                        <th>API Calls</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
                            No data found for the selected criteria
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($data as $row): ?>
                    <tr>
                        <td><?= date('M j, g:i A', strtotime($row['call_start_time'])) ?></td>
                        <td><?= htmlspecialchars($row['phone_number']) ?></td>
                        <td><?= $this->formatDuration($row['call_duration']) ?></td>
                        <td class="<?= $this->getOutcomeClass($row['call_outcome']) ?>">
                            <?= ucwords(str_replace('_', ' ', $row['call_outcome'])) ?>
                        </td>
                        <td><?= htmlspecialchars(mb_substr($row['pickup_address'] ?? 'N/A', 0, 25) . (mb_strlen($row['pickup_address'] ?? '') > 25 ? '...' : '')) ?></td>
                        <td><?= htmlspecialchars(mb_substr($row['destination_address'] ?? 'N/A', 0, 25) . (mb_strlen($row['destination_address'] ?? '') > 25 ? '...' : '')) ?></td>
                        <td><?= strtoupper($row['language_used'] ?? 'EN') ?></td>
                        <td style="font-size: 9px;">
                            TTS: <?= ($row['google_tts_calls'] ?? 0) + ($row['edge_tts_calls'] ?? 0) ?><br>
                            STT: <?= $row['google_stt_calls'] ?? 0 ?><br>
                            GEO: <?= $row['geocoding_api_calls'] ?? 0 ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p>📞 Call Analytics System Report | Generated: <?= date('Y-m-d H:i:s') ?> | Total Records: <?= $totals['total_calls'] ?> | Total API Calls: <?= number_format($totals['total_api_calls_all']) ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // ===== PRINT EXPORT =====
    
    private function exportPrint() {
        // Same as PDF but without auto-print
        $exportResult = $this->getExportData();
        $data = $exportResult['data'];
        $totals = $exportResult['totals'];
        
        header('Content-Type: text/html; charset=utf-8');
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Call Analytics Report - <?= date('Y-m-d H:i:s') ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #2563eb; margin: 0; }
                .header p { color: #666; margin: 5px 0; }
                .stats { display: flex; gap: 20px; margin: 20px 0; justify-content: center; }
                .stat-card { background: #f8fafc; padding: 15px; border-radius: 8px; text-align: center; min-width: 120px; }
                .stat-number { font-size: 24px; font-weight: bold; color: #2563eb; }
                .stat-label { color: #666; font-size: 12px; text-transform: uppercase; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8fafc; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .success { color: #10b981; font-weight: bold; }
                .failed { color: #ef4444; font-weight: bold; }
                .warning { color: #f59e0b; font-weight: bold; }
                .print-controls { margin: 20px 0; text-align: center; }
                .btn { padding: 12px 24px; margin: 0 10px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
                .btn-primary { background: #2563eb; color: white; }
                .btn-secondary { background: #6b7280; color: white; }
                .btn:hover { opacity: 0.9; }
                @media print {
                    .print-controls { display: none; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body>
            <div class="print-controls">
                <button class="btn btn-primary" onclick="window.print()">🖨️ Print Report</button>
                <button class="btn btn-secondary" onclick="window.close()">✕ Close Window</button>
            </div>
            
            <div class="header">
                <h1>📊 Call Analytics Report</h1>
                <p>Generated on <?= date('F j, Y \a\t g:i A') ?></p>
                <?php if (!empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                <p>Period: 
                    <?= !empty($_GET['date_from']) ? date('M j, Y', strtotime($_GET['date_from'])) : 'Beginning' ?>
                    - 
                    <?= !empty($_GET['date_to']) ? date('M j, Y', strtotime($_GET['date_to'])) : 'Present' ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= count($data) ?></div>
                    <div class="stat-label">Total Calls</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number success"><?= count(array_filter($data, function($r) { return $r['call_outcome'] === 'successful_registration'; })) ?></div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number failed"><?= count(array_filter($data, function($r) { return in_array($r['call_outcome'], ['hangup', 'error']); })) ?></div>
                    <div class="stat-label">Failed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number warning"><?= count(array_filter($data, function($r) { return $r['call_outcome'] === 'operator_transfer'; })) ?></div>
                    <div class="stat-label">Transferred</div>
                </div>
            </div>
            
            <!-- API Usage Statistics -->
            <div class="api-stats">
                <h3 style="margin: 2rem 0 1rem; color: #333; border-bottom: 2px solid #ddd; padding-bottom: 0.5rem;">📊 API Usage Summary</h3>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number" style="color: #e74c3c;"><?= number_format($totals['total_google_stt']) ?></div>
                        <div class="stat-label">Total STT Calls</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #3498db;"><?= number_format($totals['total_tts_all']) ?></div>
                        <div class="stat-label">Total TTS Calls</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #f39c12;"><?= number_format($totals['total_geocoding']) ?></div>
                        <div class="stat-label">Total Geocoding</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #9b59b6;"><?= number_format($totals['total_api_calls_all']) ?></div>
                        <div class="stat-label">Total API Calls</div>
                    </div>
                </div>
                <div class="stats" style="margin-top: 1rem;">
                    <div class="stat-card">
                        <div class="stat-number" style="color: #27ae60;"><?= number_format($totals['total_google_tts']) ?></div>
                        <div class="stat-label">Google TTS</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #2ecc71;"><?= number_format($totals['total_edge_tts']) ?></div>
                        <div class="stat-label">Edge TTS</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #1abc9c;"><?= number_format($totals['total_user_api']) ?></div>
                        <div class="stat-label">User API</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #34495e;"><?= number_format($totals['total_registration_api']) ?></div>
                        <div class="stat-label">Registration API</div>
                    </div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Phone Number</th>
                        <th>Duration</th>
                        <th>Outcome</th>
                        <th>Pickup Address</th>
                        <th>Destination Address</th>
                        <th>Language</th>
                        <th>API Calls</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($data as $row): ?>
                    <tr>
                        <td><?= date('M j, g:i A', strtotime($row['call_start_time'])) ?></td>
                        <td><?= htmlspecialchars($row['phone_number']) ?></td>
                        <td><?= $this->formatDuration($row['call_duration']) ?></td>
                        <td class="<?= $this->getOutcomeClass($row['call_outcome']) ?>">
                            <?= ucwords(str_replace('_', ' ', $row['call_outcome'])) ?>
                        </td>
                        <td><?= htmlspecialchars($row['pickup_address'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['destination_address'] ?? 'N/A') ?></td>
                        <td><?= strtoupper($row['language_used'] ?? 'EN') ?></td>
                        <td style="font-size: 11px;">
                            TTS: <?= ($row['google_tts_calls'] ?? 0) + ($row['edge_tts_calls'] ?? 0) ?><br>
                            STT: <?= $row['google_stt_calls'] ?? 0 ?><br>
                            GEO: <?= $row['geocoding_api_calls'] ?? 0 ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Helper method to get export data (reused by both PDF and print)
    private function getExportData() {
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
        
        // Add limit if specified
        if (!empty($_GET['limit']) && $_GET['limit'] !== 'all' && is_numeric($_GET['limit'])) {
            $sql .= " LIMIT " . intval($_GET['limit']);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $data = $stmt->fetchAll();
        
        // Calculate totals
        $totals = [
            'total_calls' => count($data),
            'total_google_tts' => 0,
            'total_google_stt' => 0,
            'total_edge_tts' => 0,
            'total_geocoding' => 0,
            'total_user_api' => 0,
            'total_registration_api' => 0,
            'total_date_parsing_api' => 0
        ];
        
        foreach ($data as $row) {
            $totals['total_google_tts'] += (int)$row['google_tts_calls'];
            $totals['total_google_stt'] += (int)$row['google_stt_calls'];
            $totals['total_edge_tts'] += (int)$row['edge_tts_calls'];
            $totals['total_geocoding'] += (int)$row['geocoding_api_calls'];
            $totals['total_user_api'] += (int)$row['user_api_calls'];
            $totals['total_registration_api'] += (int)$row['registration_api_calls'];
            $totals['total_date_parsing_api'] += (int)$row['date_parsing_api_calls'];
        }
        
        $totals['total_tts_all'] = $totals['total_google_tts'] + $totals['total_edge_tts'];
        $totals['total_api_calls_all'] = $totals['total_google_tts'] + $totals['total_google_stt'] + 
                                        $totals['total_edge_tts'] + $totals['total_geocoding'] + 
                                        $totals['total_user_api'] + $totals['total_registration_api'] + 
                                        $totals['total_date_parsing_api'];
        
        return ['data' => $data, 'totals' => $totals];
    }
    
    // Helper method to get CSS class for outcome
    private function getOutcomeClass($outcome) {
        switch ($outcome) {
            case 'successful_registration':
                return 'success';
            case 'hangup':
            case 'error':
                return 'failed';
            case 'operator_transfer':
                return 'warning';
            default:
                return '';
        }
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
<html lang="<?php echo $this->language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $this->t('dashboard_title'); ?></title>
    <link rel="icon" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMzYjgyZjYiIHN0cm9rZS13aWR0aD0iMiI+PHBhdGggZD0iTTMgM3YxOGwxOC0xOEgzeiIvPjxwYXRoIGQ9Im0xMCAxMCA0IDQiLz48L3N2Zz4K" type="image/svg+xml">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/gr.js"></script>
    <script>
        // Load leaflet heat plugin and ensure it's ready
        window.heatPluginReady = false;
        document.addEventListener('DOMContentLoaded', function() {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet.heat/dist/leaflet-heat.js';
            script.onload = function() {
                console.log('Leaflet heat plugin loaded successfully');
                window.heatPluginReady = true;
                // Trigger a re-render if heatmap was attempted before plugin loaded
                if (window.pendingHeatmapData) {
                    renderLocationHeatmap(window.pendingHeatmapData);
                    window.pendingHeatmapData = null;
                }
            };
            script.onerror = function() {
                console.error('Failed to load leaflet heat plugin');
            };
            document.head.appendChild(script);
        });
    </script>
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
        
        .language-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            background: white;
            border-radius: 25px;
            padding: 0.25rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .lang-btn {
            border: none;
            background: transparent;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--gray-600);
            transition: all 0.2s ease;
            min-width: 40px;
        }

        .lang-btn:hover {
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .lang-btn.active {
            background: var(--primary);
            color: white;
        }

        .filter-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 9999;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .filter-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .filter-toggle i {
            font-size: 1rem;
        }
        
        .filter-modal-content {
            max-width: 800px;
            width: 90vw;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-row .form-group {
            margin-bottom: 0;
        }
        
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-top: 1px solid var(--gray-200);
            margin: 0 -2rem -2rem -2rem;
            border-radius: 0 0 1rem 1rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-section {
            border-top: 1px solid var(--gray-200);
            padding-top: 1.5rem;
        }

        .form-section h4 {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        /* Recording Styles */
        .recording-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }

        .recording-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .recording-header {
            margin-bottom: 0.75rem;
        }

        .recording-info {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .recording-icon {
            font-size: 1.25rem;
            margin-top: 0.125rem;
        }

        .recording-details {
            flex: 1;
        }

        .recording-title {
            color: var(--gray-800);
            font-size: 1rem;
            display: block;
            margin-bottom: 0.25rem;
        }

        .recording-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
        }

        .recording-filename {
            color: var(--gray-600);
            font-family: 'Courier New', monospace;
            background: var(--gray-100);
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.8125rem;
        }

        .recording-size {
            color: var(--gray-500);
        }

        .recording-duration {
            color: var(--primary);
            font-weight: 600;
        }

        .recording-attempt {
            background: var(--warning);
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-weight: 600;
        }

        .recording-description {
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            padding-left: 2rem;
        }

        .recording-player {
            width: 100%;
            margin-top: 0.5rem;
            border-radius: 0.5rem;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: white;
            border-right: 1px solid var(--gray-200);
            padding: 2rem;
            overflow-y: auto;
            z-index: 9997;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .sidebar.active {
            display: block;
            transform: translateX(0);
        }
        
        .sidebar-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 9999;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }
        
        .sidebar-backdrop.active {
            display: block;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            flex-direction: column;
        }
        
        .filter-status {
            padding: 0.25rem 0;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .filter-status i {
            color: #28a745;
        }
        
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
        }
        
        .header {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            width: 100%;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-left {
            flex: 1;
            min-width: 250px;
        }
        
        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .dashboard-title i {
            color: rgba(255, 255, 255, 0.9);
            flex-shrink: 0;
        }
        
        .dashboard-subtitle {
            font-size: 1.1rem;
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-weight: 300;
            line-height: 1.4;
        }
        
        .header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
            flex-shrink: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .header-actions-mobile {
            display: none;
            position: relative;
        }
        
        .mobile-menu-toggle {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-menu-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        .mobile-menu-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            padding: 0.75rem;
            min-width: 200px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .mobile-menu-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .mobile-menu-dropdown .btn {
            width: 100%;
            margin-bottom: 0.75rem;
            justify-content: flex-start;
            text-align: left;
            color: #374151;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }
        
        .mobile-menu-dropdown .btn:last-child {
            margin-bottom: 0;
        }
        
        .mobile-menu-dropdown .btn:hover {
            background: #f3f4f6;
            transform: translateY(-1px);
        }
        
        /* Responsive behavior */
        @media (max-width: 768px) {
            .header-content {
                align-items: center;
            }
            
            .dashboard-title {
                font-size: 2rem;
                gap: 0.5rem;
            }
            
            .dashboard-subtitle {
                font-size: 1rem;
            }
            
            .header-actions {
                display: none;
            }
            
            .header-actions-mobile {
                display: block;
            }
        }
        
        @media (max-width: 480px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
            }
            
            .header-right {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .dashboard-title {
                font-size: 1.75rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                text-align: left;
            }
            
            .dashboard-title i {
                font-size: 1.5rem;
            }
        }
        
        .connection-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
            animation: pulse 2s infinite;
        }

        .status-indicator.online {
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
            animation: pulse 2s infinite;
        }

        .status-indicator.offline {
            background: #6b7280;
            box-shadow: 0 0 0 2px rgba(107, 114, 128, 0.3);
            animation: none;
        }
        
        .status-text {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
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
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card:hover::before {
            opacity: 1;
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
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            font-size: 1.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
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
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin: 1rem 0 0.5rem 0;
            line-height: 1;
            background: linear-gradient(135deg, var(--gray-900), var(--gray-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-change {
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .change-positive {
            color: var(--success);
        }
        
        .change-negative {
            color: var(--danger);
        }
        
        .change-neutral {
            color: var(--gray-500);
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
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--gray-200);
            height: 450px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--success));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .chart-container:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .chart-container:hover::before {
            opacity: 1;
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
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .chart-title i {
            color: var(--primary);
        }
        
        .calls-table-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-top: 2rem;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            border-bottom: 2px solid var(--gray-100);
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .table-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-header h3 i {
            color: #1e3a8a;
        }
        
        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .table-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-group-left {
            display: flex;
            gap: 0.5rem;
        }

        .btn-group-right {
            display: flex;
            align-items: center;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .btn-outline-primary {
            color: #3b82f6;
            border: 1px solid #3b82f6;
            background-color: transparent;
        }

        .btn-outline-primary:hover {
            color: white;
            background-color: #3b82f6;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .btn-success {
            color: white;
            background-color: #10b981;
            border: 1px solid #10b981;
        }

        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }

        .form-control-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            border-radius: 0.375rem;
            border: 1px solid var(--gray-300);
            transition: border-color 0.2s ease-in-out;
        }

        .form-control-sm:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .date-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .date-input-wrapper .date-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: auto;
            cursor: pointer;
            z-index: 2;
        }

        .date-input-wrapper .date-icon:hover {
            color: #3b82f6;
        }

        .date-picker {
            padding-right: 35px;
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
            padding: 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }
        
        .table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            font-weight: 700;
            color: var(--gray-900);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table td {
            font-size: 0.925rem;
            color: var(--gray-800);
            line-height: 1.5;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background: #f8fafc;
            transform: translateX(2px);
            box-shadow: inset 3px 0 0 #1e3a8a;
        }
        
        .table tbody tr:nth-child(even) {
            background: rgba(248, 250, 252, 0.5);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
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
        
        .location-info {
            font-size: 0.8rem;
            line-height: 1.4;
        }
        
        .location-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
            gap: 0.25rem;
        }
        
        .location-item:last-child {
            margin-bottom: 0;
        }
        
        .location-label {
            font-weight: 600;
            color: var(--gray-600);
            min-width: 30px;
        }
        
        .location-address {
            color: var(--gray-800);
        }
        
        .no-location {
            color: var(--gray-400);
            font-style: italic;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
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
        
        .modal-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .live-duration {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes flashGreen {
            0% { background-color: transparent; transform: scale(1); }
            50% { background-color: rgba(16, 185, 129, 0.3); transform: scale(1.05); }
            100% { background-color: transparent; transform: scale(1); }
        }
        
        @keyframes plusOne {
            0% { opacity: 0; transform: translateY(0) scale(0.8); }
            30% { opacity: 1; transform: translateY(-20px) scale(1.2); color: #10b981; }
            100% { opacity: 0; transform: translateY(-40px) scale(0.8); }
        }
        
        .flash-update {
            animation: flashGreen 1s ease-in-out;
        }
        
        .plus-one {
            position: absolute;
            top: 50%;
            right: 10px;
            font-size: 1.2rem;
            font-weight: bold;
            pointer-events: none;
            animation: plusOne 2s ease-out;
            z-index: 10;
        }
        
        .stat-card {
            position: relative;
            overflow: visible;
        }
        
        #locationHeatmap {
            min-height: 400px;
            border-radius: 0.5rem;
        }
        
        #heatmapContainer {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .heatmap-container {
            position: relative;
        }
        
        .heatmap-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .heatmap-controls select {
            min-width: 150px;
        }

        /* Enhanced marker cluster styles */
        .marker-cluster-small {
            background-color: rgba(59, 130, 246, 0.8);
        }
        .marker-cluster-small div {
            background-color: rgba(59, 130, 246, 0.9);
            color: white;
            font-weight: bold;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            font-size: 12px;
        }

        .marker-cluster-medium {
            background-color: rgba(245, 158, 11, 0.8);
        }
        .marker-cluster-medium div {
            background-color: rgba(245, 158, 11, 0.9);
            color: white;
            font-weight: bold;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            font-size: 14px;
        }

        .marker-cluster-large {
            background-color: rgba(239, 68, 68, 0.8);
        }
        .marker-cluster-large div {
            background-color: rgba(239, 68, 68, 0.9);
            color: white;
            font-weight: bold;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            font-size: 16px;
        }

        .marker-cluster {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 2px solid white;
        }
        
        .heatmap-fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 10000;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.3);
        }

        .heatmap-fullscreen .chart-header {
            padding: 1.5rem 2rem;
            border-bottom: 2px solid #e2e8f0;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .heatmap-fullscreen .chart-title {
            color: white;
            font-size: 1.5rem;
        }

        .heatmap-fullscreen .chart-title i {
            color: rgba(255, 255, 255, 0.9);
        }

        .heatmap-fullscreen .heatmap-controls {
            gap: 1.5rem;
        }

        .heatmap-fullscreen .heatmap-stats {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .heatmap-fullscreen .heatmap-stats .stat-item {
            color: white;
        }

        .heatmap-fullscreen .heatmap-fullscreen-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .heatmap-fullscreen .heatmap-fullscreen-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .heatmap-fullscreen .heatmap-wrapper {
            flex: 1;
            height: calc(100vh - 120px) !important;
            margin: 2rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .fullscreen-close-btn {
            position: absolute;
            top: 1.5rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            z-index: 10001;
        }

        .fullscreen-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.1);
        }

        .fullscreen-close-btn i {
            font-size: 1.1rem;
        }
        
        .heatmap-fullscreen .heatmap-fullscreen-btn i {
            transform: rotate(45deg);
        }
        
        
        .heatmap-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            align-items: center;
            position: relative;
        }
        
        .heatmap-fullscreen-btn {
            background: transparent;
            border: 1px solid #ddd;
            color: #6c757d;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: auto;
            transition: all 0.2s;
        }
        
        .heatmap-fullscreen-btn:hover {
            background: #f8f9fa;
            color: #495057;
            border-color: #adb5bd;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--gray-600);
        }
        
        .heatmap-wrapper {
            position: relative;
            height: 400px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .heatmap-state {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            z-index: 10;
        }
        
        .loading-spinner {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .spinner-ring {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid var(--gray-200);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
            opacity: 0.7;
        }
        
        .heatmap-state h4 {
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .heatmap-state p {
            color: var(--gray-500);
            margin-bottom: 1rem;
        }
        
        .empty-suggestions {
            padding: 0.75rem 1rem;
            background: var(--gray-50);
            border-radius: 0.375rem;
            border-left: 4px solid var(--info);
        }
        
        .empty-suggestions small {
            color: var(--gray-600);
        }
        
        .heatmap-legend {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.8rem;
            z-index: 1000;
        }
        
        .legend-title {
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--gray-700);
        }
        
        .legend-gradient {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .legend-bar {
            width: 60px;
            height: 8px;
            background: linear-gradient(to right, #3b82f6, #06d6a0, #ffd23f, #f72585);
            border-radius: 4px;
        }
        
        .legend-low, .legend-high {
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        
        .text-success { color: var(--success) !important; }
        .text-danger { color: var(--danger) !important; }
        
        .sidebar-close-btn {
            background: none;
            border: none;
            color: var(--gray-500);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar-close-btn:hover {
            background: var(--gray-100);
            color: var(--gray-700);
            transform: scale(1.1);
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
        
        /* Export Modal Styles */
        .export-options {
            margin-bottom: 2rem;
        }
        
        .export-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .export-option:hover {
            border-color: var(--primary-color);
            background: var(--gray-50);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .export-option-icon {
            font-size: 2rem;
            margin-right: 1rem;
            width: 60px;
            text-align: center;
        }
        
        .export-option[data-format="csv"] .export-option-icon {
            color: #10B981;
        }
        
        .export-option[data-format="pdf"] .export-option-icon {
            color: #EF4444;
        }
        
        .export-option[data-format="print"] .export-option-icon {
            color: #8B5CF6;
        }
        
        .export-option-content {
            flex: 1;
        }
        
        .export-option-content h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .export-option-content p {
            margin: 0 0 0.25rem 0;
            color: var(--gray-600);
        }
        
        .export-option-action {
            font-size: 1.25rem;
            color: var(--gray-400);
        }
        
        .export-filters {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
        }
        
        .export-filters h5 {
            margin: 0 0 1rem 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .date-range-inputs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .date-range-inputs span {
            color: var(--gray-600);
            font-weight: 500;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .date-range-inputs input {
            flex: 1;
            min-width: 160px;
            max-width: 200px;
        }
        
        /* Mobile responsiveness for date inputs */
        @media (max-width: 600px) {
            .date-range-inputs {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .date-range-inputs span {
                text-align: center;
                order: 1;
            }
            
            .date-range-inputs input:first-of-type {
                order: 0;
            }
            
            .date-range-inputs input:last-of-type {
                order: 2;
            }
            
            .date-range-inputs input {
                min-width: unset;
                max-width: unset;
                width: 100%;
            }
        }
        
        .export-filters label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .export-filters label input[type="checkbox"] {
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Language Toggle Button -->
    <div class="language-toggle">
        <button onclick="switchLanguage('el')" class="lang-btn <?php echo $this->language === 'el' ? 'active' : ''; ?>">ΕΛ</button>
        <button onclick="switchLanguage('en')" class="lang-btn <?php echo $this->language === 'en' ? 'active' : ''; ?>">EN</button>
    </div>

    <!-- Filter Modal -->
    <div class="modal" id="filterModal">
        <div class="modal-content filter-modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-filter"></i>
                    <?php echo $this->t('advanced_filters'); ?>
                </h3>
                <button class="modal-close" id="filterModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="filterForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('search'); ?></label>
                            <input type="text" name="search" class="form-control" placeholder="<?php echo $this->t('placeholder_search'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('phone_number'); ?></label>
                            <input type="text" name="phone" class="form-control" placeholder="<?php echo $this->t('placeholder_phone'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('extension'); ?></label>
                            <input type="text" name="extension" class="form-control" placeholder="<?php echo $this->t('placeholder_extension'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('call_type'); ?></label>
                            <select name="call_type" class="form-control">
                                <option value=""><?php echo $this->t('all_types'); ?></option>
                                <option value="immediate"><?php echo $this->t('immediate'); ?></option>
                                <option value="reservation"><?php echo $this->t('reservation'); ?></option>
                                <option value="operator"><?php echo $this->t('operator'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('outcome'); ?></label>
                            <select name="outcome" class="form-control">
                                <option value=""><?php echo $this->t('all_outcomes'); ?></option>
                                <option value="success"><?php echo $this->t('success'); ?></option>
                                <option value="hangup"><?php echo $this->t('hangup'); ?></option>
                                <option value="operator_transfer"><?php echo $this->t('operator_transfer'); ?></option>
                                <option value="error"><?php echo $this->t('error'); ?></option>
                                <option value="in_progress"><?php echo $this->t('in_progress'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('date_from'); ?></label>
                            <div class="date-input-wrapper">
                                <input type="text" name="date_from" class="form-control date-picker" id="dateFromFilter"
                                       placeholder="ΗΗ/ΜΜ/ΕΕΕΕ" maxlength="10" autocomplete="off">
                                <i class="fas fa-calendar-alt date-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('date_to'); ?></label>
                            <div class="date-input-wrapper">
                                <input type="text" name="date_to" class="form-control date-picker" id="dateToFilter"
                                       placeholder="ΗΗ/ΜΜ/ΕΕΕΕ" maxlength="10" autocomplete="off">
                                <i class="fas fa-calendar-alt date-icon"></i>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <div class="filter-status">
                    <small class="text-muted">
                        <i class="fas fa-magic"></i> <?php echo $this->t('auto_filtering_enabled'); ?>
                    </small>
                </div>
                <div class="modal-actions">
                    <button type="button" id="clearFilters" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo $this->t('clear_all'); ?>
                    </button>
                    <button type="submit" form="filterForm" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?php echo $this->t('apply_filters'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="app-container">
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="dashboard-title">
                            <i class="fas fa-chart-line"></i>
                            <?php echo $this->t('analytics_dashboard'); ?>
                        </h1>
                        <p class="dashboard-subtitle"><?php echo $this->t('realtime_monitoring'); ?></p>
                    </div>
                    <div class="header-right">
                        <div class="header-actions">
                            <button id="refreshBtn" class="btn btn-secondary">
                                <i class="fas fa-refresh"></i> <?php echo $this->t('refresh'); ?>
                            </button>
                            <button id="realtimeBtn" class="btn btn-danger">
                                <i class="fas fa-stop"></i> <?php echo $this->t('stop'); ?>
                            </button>
                        </div>
                        <div class="header-actions-mobile">
                            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="mobile-menu-dropdown" id="mobileMenuDropdown">
                                <button id="refreshBtnMobile" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> <?php echo $this->t('refresh'); ?>
                                </button>
                                <button id="realtimeBtnMobile" class="btn btn-danger">
                                    <i class="fas fa-stop"></i> <?php echo $this->t('stop'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="connection-status">
                            <span class="status-indicator online"></span>
                            <span class="status-text"><?php echo $this->t('live'); ?></span>
                        </div>
                    </div>
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
                        <h3 class="chart-title"><?php echo $this->t('calls_per_hour'); ?></h3>
                        <select id="hourlyDateSelect" class="form-control" style="width: auto;">
                            <option value=""><?php echo $this->t('today'); ?></option>
                        </select>
                    </div>
                    <div class="chart-canvas-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
                
                <!-- Location Heatmap -->
                <div class="chart-container heatmap-container">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-map-marked-alt"></i> <?php echo $this->t('location_heatmap'); ?>
                        </h3>
                        <div class="heatmap-controls">
                            <select id="heatmapDuration" class="form-control">
                                <option value="30"><?php echo $this->t('last_30_minutes'); ?></option>
                                <option value="60"><?php echo $this->t('last_1_hour'); ?></option>
                                <option value="180"><?php echo $this->t('last_3_hours'); ?></option>
                                <option value="360"><?php echo $this->t('last_6_hours'); ?></option>
                                <option value="720"><?php echo $this->t('last_12_hours'); ?></option>
                                <option value="1440"><?php echo $this->t('last_24_hours'); ?></option>
                            </select>
                            <select id="heatmapMode" class="form-control">
                                <option value="heatmap">🔥 Heatmap</option>
                                <option value="clusters">📍 Clustered Markers</option>
                                <option value="markers">🎯 Individual Markers</option>
                            </select>
                            <div id="heatmapStats" class="heatmap-stats">
                                <span class="stat-item">
                                    <i class="fas fa-map-pin text-success"></i>
                                    <span id="pickupCount">0</span> <?php echo $this->t('pickups'); ?>
                                </span>
                                <span class="stat-item">
                                    <i class="fas fa-flag-checkered text-danger"></i>
                                    <span id="destinationCount">0</span> <?php echo $this->t('destinations'); ?>
                                </span>
                                <button id="heatmapFullscreen" class="heatmap-fullscreen-btn" title="Fullscreen">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="heatmapContainer" class="heatmap-wrapper">
                        <!-- Loading State -->
                        <div id="heatmapLoading" class="heatmap-state">
                            <div class="loading-spinner">
                                <div class="spinner-ring"></div>
                            </div>
                            <h4><?php echo $this->t('loading_location_data'); ?></h4>
                            <p><?php echo $this->t('fetching_locations'); ?></p>
                        </div>
                        
                        <!-- Empty State -->
                        <div id="heatmapEmpty" class="heatmap-state" style="display: none;">
                            <div class="empty-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h4><?php echo $this->t('no_location_data'); ?></h4>
                            <p><?php echo $this->t('waiting_for_calls'); ?></p>
                            <div class="empty-suggestions">
                                <small>
                                    <i class="fas fa-info-circle"></i>
                                    <?php echo $this->t('try_longer_period'); ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Map Container -->
                        <div id="locationHeatmap" style="height: 100%; opacity: 0; transition: opacity 0.3s ease;"></div>
                        
                        <!-- Map Legend -->
                        <div id="heatmapLegend" class="heatmap-legend" style="display: none;">
                            <div class="legend-title"><?php echo $this->t('activity_level'); ?></div>
                            <div class="legend-gradient">
                                <span class="legend-low"><?php echo $this->t('low'); ?></span>
                                <div class="legend-bar"></div>
                                <span class="legend-high"><?php echo $this->t('high'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calls Table -->
            <div class="calls-table-container">
                <div class="table-header">
                    <h3 class="table-title"><?php echo $this->t('recent_calls'); ?></h3>
                    <div class="table-actions">
                        <div class="btn-group-left">
                            <button id="filterToggle" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-filter"></i> <?php echo $this->t('filters'); ?>
                            </button>
                            <button id="exportBtn" class="btn btn-success btn-sm">
                                <i class="fas fa-download"></i> <?php echo $this->t('export'); ?>
                            </button>
                        </div>
                        <div class="btn-group-right">
                            <select id="limitSelect" class="form-control form-control-sm" style="width: auto; min-width: 120px;">
                                <option value="25"><?php echo $this->t('25_per_page'); ?></option>
                                <option value="50" selected><?php echo $this->t('50_per_page'); ?></option>
                                <option value="100"><?php echo $this->t('100_per_page'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table" id="callsTable">
                        <thead>
                            <tr>
                                <th><?php echo $this->t('phone'); ?></th>
                                <th><?php echo $this->t('time'); ?></th>
                                <th><?php echo $this->t('duration'); ?></th>
                                <th><?php echo $this->t('status'); ?></th>
                                <th><?php echo $this->t('type'); ?></th>
                                <th><?php echo $this->t('user'); ?></th>
                                <th><?php echo $this->t('location'); ?></th>
                                <th><?php echo $this->t('apis'); ?></th>
                                <th><?php echo $this->t('actions'); ?></th>
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
                <h3 class="modal-title"><?php echo $this->t('call_details'); ?></h3>
                <div class="modal-actions">
                    <button class="btn btn-sm btn-primary" onclick="refreshCallDetail()" title="<?php echo $this->t('refresh_action'); ?>">
                        <i class="fas fa-sync-alt"></i> <?php echo $this->t('refresh_action'); ?>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="editCallWithAuth()" title="<?php echo $this->t('edit'); ?>">
                        <i class="fas fa-edit"></i> <?php echo $this->t('edit'); ?>
                    </button>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
            </div>
            <div class="modal-body" id="callDetailBody">
                <!-- Call details will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Export Modal -->
    <div class="modal" id="exportModal">
        <div class="modal-content" style="width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-download"></i> <?php echo $this->t('export_data'); ?></h3>
                <button class="modal-close" id="exportModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="export-options">
                    <div class="export-option" data-format="csv">
                        <div class="export-option-icon">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <div class="export-option-content">
                            <h4><?php echo $this->t('csv_export'); ?></h4>
                            <p><?php echo $this->t('download_data_spreadsheet'); ?></p>
                            <small class="text-muted"><?php echo $this->t('best_for_data_analysis'); ?></small>
                        </div>
                        <div class="export-option-action">
                            <i class="fas fa-download"></i>
                        </div>
                    </div>
                    
                    <div class="export-option" data-format="pdf">
                        <div class="export-option-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="export-option-content">
                            <h4><?php echo $this->t('pdf_export'); ?></h4>
                            <p><?php echo $this->t('generate_formatted_pdf'); ?></p>
                            <small class="text-muted"><?php echo $this->t('best_for_presentations'); ?></small>
                        </div>
                        <div class="export-option-action">
                            <i class="fas fa-download"></i>
                        </div>
                    </div>
                    
                    <div class="export-option" data-format="print">
                        <div class="export-option-icon">
                            <i class="fas fa-print"></i>
                        </div>
                        <div class="export-option-content">
                            <h4><?php echo $this->t('print_view'); ?></h4>
                            <p><?php echo $this->t('open_print_friendly'); ?></p>
                            <small class="text-muted"><?php echo $this->t('best_for_printing'); ?></small>
                        </div>
                        <div class="export-option-action">
                            <i class="fas fa-print"></i>
                        </div>
                    </div>
                </div>
                
                <div class="export-filters">
                    <h5><i class="fas fa-filter"></i> <?php echo $this->t('export_options'); ?></h5>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php echo $this->t('date_range'); ?></label>
                            <div class="date-range-inputs">
                                <input type="datetime-local" id="exportDateFrom" class="form-control">
                                <span><?php echo $this->t('to'); ?></span>
                                <input type="datetime-local" id="exportDateTo" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="includeCurrentFilters" checked>
                                <?php echo $this->t('include_current_filters'); ?>
                            </label>
                            <small class="text-muted"><?php echo $this->t('apply_current_search_filters'); ?></small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php echo $this->t('records_limit'); ?></label>
                            <select id="exportLimit" class="form-control">
                                <option value="100"><?php echo $this->t('last_100_records'); ?></option>
                                <option value="500"><?php echo $this->t('last_500_records'); ?></option>
                                <option value="1000"><?php echo $this->t('last_1000_records'); ?></option>
                                <option value="all" selected><?php echo $this->t('all_records'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Call Modal -->
    <div class="modal" id="editCallModal">
        <div class="modal-content" style="width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> <?php echo $this->t('edit_call', 'Edit Call'); ?></h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editCallForm">
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="editPhone">Phone Number</label>
                            <input type="text" id="editPhone" class="form-control" placeholder="Phone number">
                        </div>
                        <div class="form-group">
                            <label for="editExtension">Extension</label>
                            <input type="text" id="editExtension" class="form-control" placeholder="Extension">
                        </div>
                        <div class="form-group">
                            <label for="editCallType">Call Type</label>
                            <select id="editCallType" class="form-control">
                                <option value="">Select type</option>
                                <option value="immediate">Immediate</option>
                                <option value="reservation">Reservation</option>
                                <option value="operator">Operator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editInitialChoice">Initial Choice</label>
                            <select id="editInitialChoice" class="form-control">
                                <option value="">Select choice</option>
                                <option value="1">1 - Immediate</option>
                                <option value="2">2 - Reservation</option>
                                <option value="3">3 - Operator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editCallOutcome">Call Outcome</label>
                            <select id="editCallOutcome" class="form-control">
                                <option value="">Select outcome</option>
                                <option value="success">Success</option>
                                <option value="hangup">Hangup</option>
                                <option value="operator_transfer">Operator Transfer</option>
                                <option value="error">Error/Failed</option>
                                <option value="anonymous_blocked">Anonymous Blocked</option>
                                <option value="user_blocked">User Blocked</option>
                                <option value="in_progress">In Progress</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editName">Name</label>
                            <input type="text" id="editName" class="form-control" placeholder="Customer name">
                        </div>
                    </div>
                    
                    <div class="form-section" style="margin-top: 1.5rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--gray-700); font-size: 1rem;">Pickup Address</h4>
                        <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="editPickupAddress">Pickup Address</label>
                                <input type="text" id="editPickupAddress" class="form-control" placeholder="Pickup address">
                            </div>
                            <div class="form-group">
                                <label for="editPickupLat">Latitude</label>
                                <input type="number" step="any" id="editPickupLat" class="form-control" placeholder="Latitude">
                            </div>
                            <div class="form-group">
                                <label for="editPickupLng">Longitude</label>
                                <input type="number" step="any" id="editPickupLng" class="form-control" placeholder="Longitude">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section" style="margin-top: 1.5rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--gray-700); font-size: 1rem;">Destination</h4>
                        <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="editDestAddress">Destination Address</label>
                                <input type="text" id="editDestAddress" class="form-control" placeholder="Destination address">
                            </div>
                            <div class="form-group">
                                <label for="editDestLat">Latitude</label>
                                <input type="number" step="any" id="editDestLat" class="form-control" placeholder="Latitude">
                            </div>
                            <div class="form-group">
                                <label for="editDestLng">Longitude</label>
                                <input type="number" step="any" id="editDestLng" class="form-control" placeholder="Longitude">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section" style="margin-top: 1.5rem;">
                        <div class="form-group">
                            <label for="editReservationTime">Reservation Time</label>
                            <input type="datetime-local" id="editReservationTime" class="form-control">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCallEdit()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
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
        // Initialize global heatmap variables
        window.heatmapInstance = null;
        window.heatmapLayer = null;
        window.markersLayer = null;
        window.mapUserInteracted = false;
        window.mapInteractionTimer = null;
        window.lastMapCenter = null;
        window.lastMapZoom = null;
        window.mapMovementTimeout = 30000; // 30 seconds
        
        // Language switching function
        function switchLanguage(lang) {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        }
        
        // Language constants for JavaScript
        const LANG = {
            current: '<?php echo $this->language; ?>',
            translations: {
                seconds_short: '<?php echo $this->t('seconds_short'); ?>',
                minutes_short: '<?php echo $this->t('minutes_short'); ?>',
                hours_short: '<?php echo $this->t('hours_short'); ?>',
                loading: '<?php echo $this->t('loading'); ?>',
                processing: '<?php echo $this->t('processing'); ?>',
                success: '<?php echo $this->t('success'); ?>',
                error: '<?php echo $this->t('error'); ?>',
                hangup: '<?php echo $this->t('hangup'); ?>',
                operator_transfer: '<?php echo $this->t('operator_transfer'); ?>',
                in_progress: '<?php echo $this->t('in_progress'); ?>',
                completed: '<?php echo $this->t('completed'); ?>',
                answered: '<?php echo $this->t('answered'); ?>',
                failed: '<?php echo $this->t('failed'); ?>',
                ongoing: '<?php echo $this->t('ongoing'); ?>',
                today: '<?php echo $this->t('today'); ?>',
                total_calls: '<?php echo $this->t('total_calls'); ?>',
                successful_calls: '<?php echo $this->t('successful_calls'); ?>',
                total_calls_today: '<?php echo $this->t('total_calls_today'); ?>',
                active: '<?php echo $this->t('active'); ?>',
                success_rate: '<?php echo $this->t('success_rate'); ?>',
                avg_duration: '<?php echo $this->t('avg_duration'); ?>',
                per_call_average: '<?php echo $this->t('per_call_average'); ?>',
                unique_callers: '<?php echo $this->t('unique_callers'); ?>',
                busy: '<?php echo $this->t('busy'); ?>',
                no_answer: '<?php echo $this->t('no_answer'); ?>',
                cancelled: '<?php echo $this->t('cancelled'); ?>',
                immediate: '<?php echo $this->t('immediate'); ?>',
                reservation: '<?php echo $this->t('reservation'); ?>',
                operator: '<?php echo $this->t('operator'); ?>',
                different_numbers: '<?php echo $this->t('different_numbers'); ?>',
                phone_number_label: '<?php echo $this->t('phone_number_label'); ?>',
                extension_label: '<?php echo $this->t('extension_label'); ?>',
                duration_label: '<?php echo $this->t('duration_label'); ?>',
                status_label: '<?php echo $this->t('status_label'); ?>',
                user_name_label: '<?php echo $this->t('user_name_label'); ?>',
                language_label: '<?php echo $this->t('language_label'); ?>',
                api_calls_label: '<?php echo $this->t('api_calls_label'); ?>',
                location_information: '<?php echo $this->t('location_information'); ?>',
                pickup_address_label: '<?php echo $this->t('pickup_address_label'); ?>',
                destination_address_label: '<?php echo $this->t('destination_address_label'); ?>',
                confirmation_audio: '<?php echo $this->t('confirmation_audio'); ?>',
                system_generated_confirmation: '<?php echo $this->t('system_generated_confirmation'); ?>',
                stop: '<?php echo $this->t('stop'); ?>',
                live: '<?php echo $this->t('live'); ?>'
            }
        };
        
        // Global functions
        function showFilterStatus(message, className) {
            const status = document.querySelector('.filter-status small');
            if (status) {
                status.innerHTML = '<i class="fas fa-magic"></i> ' + message;
                status.className = 'text-muted ' + (className || '');
                
                // Clear any existing timeout
                if (window.filterStatusTimeout) {
                    clearTimeout(window.filterStatusTimeout);
                }
                
                // Set new timeout to reset status
                window.filterStatusTimeout = setTimeout(() => {
                    status.innerHTML = '<i class="fas fa-magic"></i> <?php echo $this->t('auto_filtering_enabled'); ?>';
                    status.className = 'text-muted';
                }, 2000);
            }
        }
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            loadDashboard();
            setupEventListeners();
            loadDateOptions();
            startLiveDurationUpdater();

            // Auto-filters are set up in setupEventListeners

            // Wait a bit to ensure the modal elements are ready
            setTimeout(function() {
                setupGreekDatePickers();
            }, 500);

            // Check for id parameter to auto-open call details
            const urlParams = new URLSearchParams(window.location.search);
            const callId = urlParams.get('id');
            if (callId) {
                // Small delay to ensure dashboard is loaded
                setTimeout(function() {
                    showCallDetail(callId);
                }, 1000);
            }
        });
        
        // Time offset between client and server (in milliseconds)
        let serverTimeOffset = 0;
        let serverTimeSynced = false;
        
        // Initialize server time sync (with callback support)
        function syncServerTime(callback) {
            fetch('?endpoint=server_time')
                .then(response => response.json())
                .then(data => {
                    serverTimeOffset = data.timestamp - Date.now();
                    serverTimeSynced = true;
                    console.log('Server time synced. Offset:', serverTimeOffset + 'ms');
                    if (callback) callback();
                })
                .catch(error => {
                    console.log('Failed to sync server time:', error);
                    serverTimeOffset = 0; // Use client time as fallback
                    serverTimeSynced = true; // Set to true even on failure to prevent blocking
                    if (callback) callback();
                });
        }
        
        // Get current server time
        function getServerTime() {
            return Date.now() + serverTimeOffset;
        }
        
        // Wait for server time sync before calculation
        function getServerTimeSync(callback) {
            if (serverTimeSynced) {
                callback(getServerTime());
            } else {
                // Wait up to 2 seconds for sync, then fallback to client time
                let attempts = 0;
                const checkSync = setInterval(() => {
                    attempts++;
                    if (serverTimeSynced || attempts > 20) { // 20 * 100ms = 2s max wait
                        clearInterval(checkSync);
                        callback(getServerTime());
                    }
                }, 100);
            }
        }
        
        // Live duration updater for in-progress calls
        function startLiveDurationUpdater() {
            // Initial server time sync
            syncServerTime();
            
            // Re-sync server time every 5 minutes to handle any drift
            setInterval(syncServerTime, 5 * 60 * 1000);
            
            setInterval(function() {
                var currentTime = getServerTime();
                
                // Just refresh the calls data every 5 seconds for live calls
                // This ensures we get accurate server-calculated durations
                if (document.querySelectorAll('.live-duration').length > 0) {
                    // Only refresh if there are live calls
                    loadCalls();
                }
                
                // Update detail view if open
                var detailDuration = document.querySelector('.live-duration-detail');
                if (detailDuration) {
                    var startTime = parseInt(detailDuration.getAttribute('data-start'));
                    if (startTime) {
                        var duration = Math.floor((currentTime - startTime) / 1000);
                        detailDuration.innerHTML = formatDuration(duration, true);
                        detailDuration.style.color = '#ef4444';
                        detailDuration.style.fontWeight = 'bold';
                    }
                }
            }, 5000); // Update every 5 seconds
        }
        
        // Setup event listeners
        function setupEventListeners() {
            // Filter modal toggle
            const filterToggle = document.getElementById('filterToggle');
            if (filterToggle) {
                filterToggle.addEventListener('click', function() {
                    document.getElementById('filterModal').classList.add('show');
                    // Re-initialize date pickers when modal opens
                    setTimeout(function() {
                        setupGreekDatePickers();
                    }, 100);
                });
            }
            
            // Filter modal close
            const filterModalClose = document.getElementById('filterModalClose');
            if (filterModalClose) {
                filterModalClose.addEventListener('click', function() {
                    document.getElementById('filterModal').classList.remove('show');
                });
            }

            // Close modal on backdrop click
            const filterModal = document.getElementById('filterModal');
            if (filterModal) {
                filterModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            }
            
            // Export Modal Event Listeners
            const exportModalClose = document.getElementById('exportModalClose');
            if (exportModalClose) {
                exportModalClose.addEventListener('click', function() {
                    document.getElementById('exportModal').classList.remove('show');
                });
            }

            // Close export modal on backdrop click
            const exportModal = document.getElementById('exportModal');
            if (exportModal) {
                exportModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            }
            
            // Export option click handlers
            document.querySelectorAll('.export-option').forEach(function(option) {
                option.addEventListener('click', function() {
                    const format = this.getAttribute('data-format');
                    performExport(format);
                });
            });
            
            // Edit Modal Event Listeners
            const editCallModal = document.getElementById('editCallModal');
            if (editCallModal) {
                editCallModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeEditModal();
                    }
                });
            }
            
            // Heatmap duration change
            const heatmapDuration = document.getElementById('heatmapDuration');
            if (heatmapDuration) {
                heatmapDuration.addEventListener('change', function() {
                    console.log('Dropdown changed to:', this.value);
                    loadLocationHeatmap();
                });
            }

            // Heatmap mode change
            const heatmapMode = document.getElementById('heatmapMode');
            if (heatmapMode) {
                heatmapMode.addEventListener('change', function() {
                    console.log('Visualization mode changed to:', this.value);
                    loadLocationHeatmap();
                });
            }

            // Heatmap fullscreen toggle
            const heatmapFullscreen = document.getElementById('heatmapFullscreen');
            if (heatmapFullscreen) {
                heatmapFullscreen.addEventListener('click', function() {
                    var container = document.querySelector('.heatmap-container');
                    var icon = this.querySelector('i');

                    if (container.classList.contains('heatmap-fullscreen')) {
                        exitHeatmapFullscreen();
                    } else {
                        enterHeatmapFullscreen();
                    }
                });
            }
            
            // Enhanced fullscreen helper functions
            function enterHeatmapFullscreen() {
                var container = document.querySelector('.heatmap-container');
                var icon = document.querySelector('#heatmapFullscreen i');

                // Store current map state before fullscreen
                if (window.heatmapInstance) {
                    window.preFullscreenCenter = window.heatmapInstance.getCenter();
                    window.preFullscreenZoom = window.heatmapInstance.getZoom();
                }

                container.classList.add('heatmap-fullscreen');
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
                document.getElementById('heatmapFullscreen').title = 'Exit Fullscreen';

                // Add close button
                var closeBtn = document.createElement('button');
                closeBtn.className = 'fullscreen-close-btn';
                closeBtn.id = 'fullscreenCloseBtn';
                closeBtn.innerHTML = '<i class="fas fa-times"></i>';
                closeBtn.title = 'Close Fullscreen';
                closeBtn.onclick = exitHeatmapFullscreen;
                container.appendChild(closeBtn);

                // Disable body scroll
                document.body.style.overflow = 'hidden';

                // Re-initialize map and layers for fullscreen
                setTimeout(function() {
                    if (window.heatmapInstance) {
                        window.heatmapInstance.invalidateSize();

                        // Restore map position
                        if (window.preFullscreenCenter && window.preFullscreenZoom) {
                            window.heatmapInstance.setView(window.preFullscreenCenter, window.preFullscreenZoom);
                        }

                        // Refresh clusters if in cluster mode
                        if (window.markersLayer && window.markersLayer.refreshClusters) {
                            window.markersLayer.refreshClusters();
                        }
                    }
                }, 300);
            }

            function exitHeatmapFullscreen() {
                var container = document.querySelector('.heatmap-container');
                var icon = document.querySelector('#heatmapFullscreen i');

                // Store fullscreen map state
                if (window.heatmapInstance) {
                    window.postFullscreenCenter = window.heatmapInstance.getCenter();
                    window.postFullscreenZoom = window.heatmapInstance.getZoom();
                }

                container.classList.remove('heatmap-fullscreen');
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
                document.getElementById('heatmapFullscreen').title = 'Fullscreen';

                // Remove close button
                var closeBtn = document.getElementById('fullscreenCloseBtn');
                if (closeBtn) {
                    closeBtn.remove();
                }

                // Re-enable body scroll
                document.body.style.overflow = '';

                // Re-initialize map and preserve user position
                setTimeout(function() {
                    if (window.heatmapInstance) {
                        window.heatmapInstance.invalidateSize();

                        // Preserve the map position from fullscreen
                        if (window.postFullscreenCenter && window.postFullscreenZoom) {
                            window.heatmapInstance.setView(window.postFullscreenCenter, window.postFullscreenZoom);
                            // Update stored position to prevent auto-repositioning
                            window.lastMapCenter = window.postFullscreenCenter;
                            window.lastMapZoom = window.postFullscreenZoom;
                        }

                        // Refresh clusters if in cluster mode
                        if (window.markersLayer && window.markersLayer.refreshClusters) {
                            window.markersLayer.refreshClusters();
                        }
                    }
                }, 300);
            }
            
            // ESC key to exit fullscreen
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.querySelector('.heatmap-container.heatmap-fullscreen')) {
                    exitHeatmapFullscreen();
                }
            });
            
            // Filter form
            const filterForm = document.getElementById('filterForm');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                // Close modal
                document.getElementById('filterModal').classList.remove('show');
                // Apply filters
                applyFilters();
                });
            }
            
            // Removed auto-apply filters - filters are now only applied when user clicks "Apply Filters" button
            
            // Clear filters
            const clearFilters = document.getElementById('clearFilters');
            if (clearFilters) {
                clearFilters.addEventListener('click', function() {
                console.log('Clearing filters...');
                document.getElementById('filterForm').reset();
                currentFilters = {};
                currentPage = 1;
                
                // Show feedback
                showFilterStatus('Filters cleared!', 'text-success');
                
                // Close modal
                document.getElementById('filterModal').classList.remove('show');
                
                // Reload calls
                loadCalls();
                updateURL();
                });
            }
            
            // Export button - show modal
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    showExportModal();
                });
            }
            
            // Refresh button
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    loadDashboard();
                });
            }
            
            // Real-time toggle
            const realtimeBtn = document.getElementById('realtimeBtn');
            if (realtimeBtn) {
                realtimeBtn.addEventListener('click', function() {
                    toggleRealtime();
                });
            }

            // Mobile Menu Toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdown = document.getElementById('mobileMenuDropdown');
                    dropdown.classList.toggle('show');
                });
            }
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                const dropdown = document.getElementById('mobileMenuDropdown');
                const toggle = document.getElementById('mobileMenuToggle');
                
                if (!dropdown.contains(e.target) && !toggle.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
            
            // Mobile button event listeners (duplicate functionality)
            const exportBtnMobile = document.getElementById('exportBtnMobile');
            if (exportBtnMobile) {
                exportBtnMobile.addEventListener('click', function() {
                    document.getElementById('mobileMenuDropdown').classList.remove('show');
                    showExportModal();
                });
            }

            const refreshBtnMobile = document.getElementById('refreshBtnMobile');
            if (refreshBtnMobile) {
                refreshBtnMobile.addEventListener('click', function() {
                    document.getElementById('mobileMenuDropdown').classList.remove('show');
                    loadDashboard();
                });
            }
            
            const realtimeBtnMobile = document.getElementById('realtimeBtnMobile');
            if (realtimeBtnMobile) {
                realtimeBtnMobile.addEventListener('click', function() {
                    document.getElementById('mobileMenuDropdown').classList.remove('show');
                    toggleRealtime();
                });
            }
            
            // Limit select
            const limitSelect = document.getElementById('limitSelect');
            if (limitSelect) {
                limitSelect.addEventListener('change', function() {
                    currentLimit = parseInt(this.value);
                    currentPage = 1;
                    loadCalls();
                });
            }

            // Hourly date select
            const hourlyDateSelect = document.getElementById('hourlyDateSelect');
            if (hourlyDateSelect) {
                hourlyDateSelect.addEventListener('change', function() {
                    loadHourlyChart(this.value);
                });
            }

            // Modal close on background click
            const callDetailModal = document.getElementById('callDetailModal');
            if (callDetailModal) {
                callDetailModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }
        }
        
        // Load dashboard data
        function loadDashboard() {
            showLoading();
            loadStats();
            loadCalls();
            loadHourlyChart();
            loadLocationHeatmap();
            hideLoading();
            
            // Auto-start real-time updates (as requested by user)
            setTimeout(() => {
                startRealtime();
            }, 1000);
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
        // Track previous stats for change detection
        let previousStats = {};
        
        // Show +1 animation for stat updates
        function showStatIncrease(statElement, increase) {
            if (increase > 0) {
                // Flash the stat card
                statElement.closest('.stat-card').classList.add('flash-update');
                setTimeout(() => {
                    statElement.closest('.stat-card').classList.remove('flash-update');
                }, 1000);
                
                // Create +1 animation
                const plusOne = document.createElement('div');
                plusOne.className = 'plus-one';
                plusOne.textContent = '+' + increase;
                
                const statCard = statElement.closest('.stat-card');
                statCard.style.position = 'relative';
                statCard.appendChild(plusOne);
                
                setTimeout(() => {
                    if (statCard.contains(plusOne)) {
                        statCard.removeChild(plusOne);
                    }
                }, 2000);
            }
        }
        
        function renderStats(stats) {
            var statsGrid = document.getElementById('statsGrid');
            
            var totalCalls = stats.total_calls || 0;
            var successfulCalls = stats.successful_calls || 0;
            var successRate = totalCalls > 0 ? Math.round((successfulCalls / totalCalls) * 100) : 0;
            
            // Calculate changes
            const totalCallsIncrease = previousStats.total_calls ? (totalCalls - previousStats.total_calls) : 0;
            const successfulCallsIncrease = previousStats.successful_calls ? (successfulCalls - previousStats.successful_calls) : 0;
            const uniqueCallersIncrease = previousStats.unique_callers ? ((stats.unique_callers || 0) - previousStats.unique_callers) : 0;
            
            // Show animation for increases
            function showPlusAnimation(elementId, increase) {
                if (increase > 0) {
                    const element = document.getElementById(elementId);
                    if (element) {
                        const plusElement = document.createElement('div');
                        plusElement.textContent = '+' + increase;
                        plusElement.className = 'plus-one-animation';
                        element.style.position = 'relative';
                        element.appendChild(plusElement);
                        
                        setTimeout(() => {
                            if (plusElement.parentNode) {
                                plusElement.parentNode.removeChild(plusElement);
                            }
                        }, 2000);
                    }
                }
            }
            
            statsGrid.innerHTML = 
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">' + LANG.translations.total_calls_today + '</span>' +
                        '<div class="stat-card-icon icon-primary">' +
                            '<i class="fas fa-phone"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value" id="totalCallsStat">' + totalCalls.toLocaleString() + '</div>' +
                    '<div class="stat-change change-positive">' +
                        '<i class="fas fa-arrow-up"></i> ' + LANG.translations.active +
                    '</div>' +
                '</div>' +
                
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">' + LANG.translations.successful_calls + '</span>' +
                        '<div class="stat-card-icon icon-success">' +
                            '<i class="fas fa-check-circle"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value" id="successfulCallsStat">' + successfulCalls.toLocaleString() + '</div>' +
                    '<div class="stat-change change-positive">' +
                        successRate + '% ' + LANG.translations.success_rate +
                    '</div>' +
                '</div>' +
                
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">' + LANG.translations.avg_duration + '</span>' +
                        '<div class="stat-card-icon icon-info">' +
                            '<i class="fas fa-clock"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value">' + formatDuration(Math.round(stats.avg_duration || 0)) + '</div>' +
                    '<div class="stat-change">' +
                        LANG.translations.per_call_average +
                    '</div>' +
                '</div>' +
                
                '<div class="stat-card">' +
                    '<div class="stat-card-header">' +
                        '<span class="stat-card-title">' + LANG.translations.unique_callers + '</span>' +
                        '<div class="stat-card-icon icon-warning">' +
                            '<i class="fas fa-users"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stat-value" id="uniqueCallersStat">' + (stats.unique_callers || 0) + '</div>' +
                    '<div class="stat-change">' +
                        LANG.translations.different_numbers +
                    '</div>' +
                '</div>';
            
            // Show animations for increases (after a brief delay to let DOM update)
            setTimeout(() => {
                showPlusAnimation('totalCallsStat', totalCallsIncrease);
                showPlusAnimation('successfulCallsStat', successfulCallsIncrease);
                showPlusAnimation('uniqueCallersStat', uniqueCallersIncrease);
            }, 100);
            
            // Store current stats for next comparison
            previousStats = {
                total_calls: totalCalls,
                successful_calls: successfulCalls,
                unique_callers: stats.unique_callers || 0
            };
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
                // Use server-calculated duration (already correct for live calls)
                var duration = call.call_duration;
                var durationCell = '';
                if (call.call_outcome === 'in_progress' && call.call_start_time) {
                    // Parse UTC timestamp correctly for live duration calculation
                    var utcDate = call.call_start_time.includes('T') ? 
                        new Date(call.call_start_time) : 
                        new Date(call.call_start_time.replace(' ', 'T') + 'Z');
                    var startTime = utcDate.getTime();
                    // Use server-calculated duration, but still mark for live updates
                    durationCell = '<td class="live-duration" data-start="' + startTime + '" data-server-duration="' + duration + '">' + formatDuration(duration, true) + '</td>';
                } else {
                    durationCell = '<td>' + formatDuration(call.call_duration) + '</td>';
                }
                
                return '<tr onclick="showCallDetail(\'' + (call.call_id || '') + '\')" style="cursor: pointer;">' +
                    '<td>' + (call.phone_number || 'N/A') + '</td>' +
                    '<td>' + formatDate(call.call_start_time) + '</td>' +
                    durationCell +
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
            if (!status) {
                status = 'unknown';
            }

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
            var html = '';
            
            if (call.pickup_address) {
                var hasPickupCoords = call.pickup_lat && call.pickup_lng;
                var pickupIcon = hasPickupCoords ? 'map-marker-alt' : 'map-pin';
                html += '<div class="location-item pickup-location">' +
                       '<i class="fas fa-' + pickupIcon + ' text-success"></i> ' +
                       '<span class="location-label">From:</span> ' +
                       '<span class="location-address">' + truncate(call.pickup_address, 20) + '</span>' +
                       '</div>';
            }
            
            if (call.destination_address) {
                var hasDestCoords = call.destination_lat && call.destination_lng;
                var destIcon = hasDestCoords ? 'flag-checkered' : 'flag';
                html += '<div class="location-item destination-location">' +
                       '<i class="fas fa-' + destIcon + ' text-danger"></i> ' +
                       '<span class="location-label">To:</span> ' +
                       '<span class="location-address">' + truncate(call.destination_address, 20) + '</span>' +
                       '</div>';
            }
            
            if (html === '') {
                return '<span class="no-location"><i class="fas fa-map text-muted"></i> No location</span>';
            }
            
            return '<div class="location-info">' + html + '</div>';
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
            
            // Don't destroy existing chart, update it instead to prevent flashing
            var shouldUpdate = charts.hourly && typeof charts.hourly.update === 'function';
            
            if (!shouldUpdate && charts.hourly) {
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
                // Convert UTC hour to local time for display
                var utcHour = hourlyData[i].hour;
                var utcDate = new Date();
                utcDate.setUTCHours(utcHour, 0, 0, 0);
                var localHour = utcDate.getHours();
                
                hourLabels.push(localHour + ':00');
                totalCallsData.push(hourlyData[i].total_calls || 0);
                successfulCallsData.push(hourlyData[i].successful_calls || 0);
            }
            
            // Update existing chart if possible to prevent flashing
            if (shouldUpdate) {
                charts.hourly.data.labels = hourLabels;
                charts.hourly.data.datasets[0].data = totalCallsData;
                charts.hourly.data.datasets[1].data = successfulCallsData;
                charts.hourly.update('none'); // 'none' animation mode for smooth updates
                return;
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
        
        // Start real-time updates
        function startRealtime() {
            var btn = document.getElementById('realtimeBtn');
            var statusIndicator = document.querySelector('.status-indicator');
            var statusText = document.querySelector('.status-text');

            if (!realtimeInterval) {
                console.log('Starting real-time updates...');
                realtimeInterval = setInterval(() => {
                    console.log('Real-time update triggered');
                    loadStats();
                    if (currentPage === 1) {
                        loadCalls();
                    }
                    loadHourlyChart(); // Also refresh the hourly chart
                    loadLocationHeatmap();
                }, 10000); // Update every 10 seconds

                btn.innerHTML = '<i class="fas fa-stop"></i> ' + LANG.translations.stop;
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-danger');

                // Update status indicator
                if (statusIndicator) {
                    statusIndicator.classList.remove('offline');
                    statusIndicator.classList.add('online');
                }
                if (statusText) {
                    statusText.textContent = LANG.translations.live;
                }

                console.log('Real-time updates enabled');
            }
        }
        
        // Load location heatmap
        function loadLocationHeatmap() {
            var duration = document.getElementById('heatmapDuration').value || 30;
            
            // Always show loading state when changing duration
            console.log('Loading heatmap for duration:', duration, 'minutes');
            showHeatmapLoading();
            
            console.log('Fetching location data for duration:', duration, 'minutes');
            fetch('?endpoint=locations&minutes=' + duration)
                .then(function(response) { 
                    console.log('Response received:', response.status);
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json(); 
                })
                .then(function(data) { 
                    console.log('Location data received:', data);
                    renderLocationHeatmap(data);
                })
                .catch(function(error) { 
                    console.error('Error loading location data:', error);
                    // Only show empty state if there's no existing heatmap
                    if (!window.heatmapInstance) {
                        showHeatmapEmpty();
                    }
                });
        }
        
        // Show loading state
        function showHeatmapLoading() {
            document.getElementById('heatmapLoading').style.display = 'flex';
            document.getElementById('heatmapEmpty').style.display = 'none';
            document.getElementById('locationHeatmap').style.opacity = '0';
            document.getElementById('heatmapLegend').style.display = 'none';
        }
        
        // Update location stats
        function updateLocationStats(data) {
            var pickupCount = 0;
            var destinationCount = 0;
            
            if (data.locations) {
                data.locations.forEach(function(loc) {
                    if (loc.type === 'pickup') pickupCount++;
                    else if (loc.type === 'destination') destinationCount++;
                });
            }
            
            document.getElementById('pickupCount').textContent = pickupCount;
            document.getElementById('destinationCount').textContent = destinationCount;
        }
        
        // Enhanced render location heatmap with clustering and position retention
        function renderLocationHeatmap(data) {
            console.log('renderLocationHeatmap called with data:', data);
            var container = document.getElementById('locationHeatmap');
            var loading = document.getElementById('heatmapLoading');
            var empty = document.getElementById('heatmapEmpty');
            var legend = document.getElementById('heatmapLegend');

            // Update stats
            updateLocationStats(data);

            if (!data.locations || data.locations.length === 0) {
                console.log('No locations found, showing empty state');
                showHeatmapEmpty();
                return;
            }

            console.log('Found', data.locations.length, 'locations to display');

            // Hide loading and empty states
            loading.style.display = 'none';
            empty.style.display = 'none';

            // Show map and legend smoothly
            if (container.style.opacity !== '1') {
                container.style.transition = 'opacity 0.3s ease';
                container.style.opacity = '1';
            }
            legend.style.display = 'block';

            // Initialize map if not exists
            if (!window.heatmapInstance) {
                try {
                    window.heatmapInstance = L.map('locationHeatmap').setView([37.9838, 23.7275], 11);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(window.heatmapInstance);

                    // Store initial center and zoom
                    window.lastMapCenter = window.heatmapInstance.getCenter();
                    window.lastMapZoom = window.heatmapInstance.getZoom();

                    // Add map interaction listeners for position retention
                    window.heatmapInstance.on('movestart', function() {
                        window.mapUserInteracted = true;
                        if (window.mapInteractionTimer) {
                            clearTimeout(window.mapInteractionTimer);
                        }
                    });

                    window.heatmapInstance.on('moveend', function() {
                        window.lastMapCenter = window.heatmapInstance.getCenter();
                        window.lastMapZoom = window.heatmapInstance.getZoom();

                        // Set timer to allow auto-repositioning after 30 seconds of no interaction
                        if (window.mapInteractionTimer) {
                            clearTimeout(window.mapInteractionTimer);
                        }
                        window.mapInteractionTimer = setTimeout(function() {
                            window.mapUserInteracted = false;
                            console.log('Map interaction timeout - auto-repositioning re-enabled');
                        }, window.mapMovementTimeout);
                    });

                    // Dynamic radius update for heatmap layers
                    window.heatmapInstance.on('zoomend', function() {
                        if (window.heatmapLayer && window.heatmapLayer.options && typeof window.heatmapLayer.redraw === 'function') {
                            var currentZoom = window.heatmapInstance.getZoom();
                            var newRadius = Math.max(15, Math.min(40, 25 + (18 - currentZoom) * 2));
                            window.heatmapLayer.options.radius = newRadius;
                            window.heatmapLayer.redraw();
                        }
                    });
                } catch (e) {
                    console.error('Error initializing map:', e);
                    showHeatmapEmpty();
                    return;
                }
            }

            // Get visualization mode
            var mode = document.getElementById('heatmapMode') ? document.getElementById('heatmapMode').value : 'heatmap';
            console.log('Using visualization mode:', mode);

            // Clear existing layers
            if (window.heatmapLayer) {
                try {
                    window.heatmapInstance.removeLayer(window.heatmapLayer);
                } catch(e) {
                    console.log('Error removing layer:', e);
                }
                window.heatmapLayer = null;
            }
            if (window.markersLayer) {
                try {
                    window.heatmapInstance.removeLayer(window.markersLayer);
                } catch(e) {
                    console.log('Error removing markers layer:', e);
                }
                window.markersLayer = null;
            }

            // Prepare data based on visualization mode
            var bounds = [];

            if (mode === 'heatmap' && typeof L !== 'undefined' && L.heatLayer && typeof L.heatLayer === 'function') {
                // Heatmap mode
                console.log('Creating heatmap visualization');

                var locationCount = {};
                var heatData = [];

                // Count frequency of each location
                data.locations.forEach(function(loc) {
                    var key = loc.lat + ',' + loc.lng;
                    locationCount[key] = (locationCount[key] || 0) + 1;
                });

                // Create heat data with intensity based on frequency
                var processedLocations = {};
                data.locations.forEach(function(loc) {
                    var key = loc.lat + ',' + loc.lng;
                    if (!processedLocations[key]) {
                        var frequency = locationCount[key];
                        var intensity = Math.min(1.0, Math.max(0.3, frequency / 3));
                        heatData.push([loc.lat, loc.lng, intensity]);
                        bounds.push([loc.lat, loc.lng]);
                        processedLocations[key] = true;
                    }
                });

                // Create heatmap layer
                window.heatmapLayer = L.heatLayer(heatData, {
                    radius: 25,
                    blur: 20,
                    minOpacity: 0.4,
                    maxZoom: 22,
                    max: 1.0,
                    gradient: {
                        0.0: '#0066ff',    // Blue for low activity
                        0.2: '#00ccff',    // Light blue
                        0.4: '#00ff99',    // Green
                        0.6: '#ffff00',    // Yellow
                        0.8: '#ff6600',    // Orange
                        1.0: '#ff0000'     // Red for high activity
                    }
                }).addTo(window.heatmapInstance);

            } else if (mode === 'clusters') {
                // Clustered markers mode
                console.log('Creating clustered markers visualization');

                window.markersLayer = L.markerClusterGroup({
                    chunkedLoading: true,
                    maxClusterRadius: 50,
                    iconCreateFunction: function(cluster) {
                        var count = cluster.getChildCount();
                        var size = count < 10 ? 'small' : count < 100 ? 'medium' : 'large';
                        return L.divIcon({
                            html: '<div><span>' + count + '</span></div>',
                            className: 'marker-cluster marker-cluster-' + size,
                            iconSize: new L.Point(40, 40)
                        });
                    }
                });

                data.locations.forEach(function(loc) {
                    var color = loc.type === 'pickup' ? '#10b981' : '#ef4444';
                    var iconHtml = loc.type === 'pickup' ? '📍' : '🏁';

                    var marker = L.marker([loc.lat, loc.lng], {
                        icon: L.divIcon({
                            html: '<div style="background-color: ' + color + '; width: 25px; height: 25px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; font-size: 12px;">' + iconHtml + '</div>',
                            iconSize: [25, 25],
                            iconAnchor: [12, 12],
                            className: 'custom-marker-icon'
                        })
                    }).bindPopup('<strong>' + (loc.type === 'pickup' ? '📍 Pickup' : '🏁 Destination') + '</strong><br>' + (loc.address || 'No address'));

                    window.markersLayer.addLayer(marker);
                    bounds.push([loc.lat, loc.lng]);
                });

                window.heatmapInstance.addLayer(window.markersLayer);

            } else {
                // Individual markers mode
                console.log('Creating individual markers visualization');

                window.markersLayer = L.layerGroup();

                data.locations.forEach(function(loc) {
                    var color = loc.type === 'pickup' ? '#10b981' : '#ef4444';
                    var marker = L.circleMarker([loc.lat, loc.lng], {
                        radius: 12,
                        fillColor: color,
                        color: '#333',
                        weight: 3,
                        opacity: 1,
                        fillOpacity: 0.9
                    }).bindPopup('<strong>' + (loc.type === 'pickup' ? '📍 Pickup' : '🏁 Destination') + '</strong><br>' + (loc.address || 'No address'));

                    window.markersLayer.addLayer(marker);
                    bounds.push([loc.lat, loc.lng]);
                });

                window.heatmapInstance.addLayer(window.markersLayer);
            }

            // Auto-fit bounds only if user hasn't interacted with map recently
            if (bounds.length > 0 && !window.mapUserInteracted) {
                console.log('Auto-fitting bounds to', bounds.length, 'points');
                var latLngBounds = L.latLngBounds(bounds);
                window.heatmapInstance.fitBounds(latLngBounds, { padding: [50, 50] });

                // Update stored position
                window.lastMapCenter = window.heatmapInstance.getCenter();
                window.lastMapZoom = window.heatmapInstance.getZoom();
            } else if (window.mapUserInteracted) {
                console.log('User has interacted with map recently - preserving current view');
            }
        }
        
        // Show empty heatmap message
        function showHeatmapEmpty() {
            document.getElementById('heatmapLoading').style.display = 'none';
            document.getElementById('heatmapEmpty').style.display = 'flex';
            document.getElementById('locationHeatmap').style.opacity = '0';
            document.getElementById('heatmapLegend').style.display = 'none';
            // Reset stats
            updateLocationStats({locations: []});
        }
        
        // Removed loadOutcomesChart - replaced with heatmap
        
        // Removed renderOutcomesChart - replaced with heatmap
        
        // Global variable to store current call ID
        var currentCallId = null;

        // Get user recording description
        function getUserRecordingDescription(filename, type, attempt) {
            attempt = attempt || 1;

            // Determine language - fallback to Greek if LANG is not available
            var currentLang = (typeof LANG !== 'undefined' && LANG.current) ? LANG.current : 'el';
            var attemptText = '';
            if (attempt > 1) {
                if (typeof LANG !== 'undefined' && LANG.translations && LANG.translations.attempt) {
                    attemptText = ' - ' + LANG.translations.attempt + ' ' + attempt;
                } else {
                    attemptText = currentLang === 'el' ? ' - Προσπάθεια ' + attempt : ' - Attempt ' + attempt;
                }
            }

            // Default titles if translations are missing
            var defaultTitles = {
                name: currentLang === 'el' ? 'Πελάτης είπε το όνομά του' : 'Customer said their name',
                pickup: currentLang === 'el' ? 'Πελάτης είπε τη διεύθυνση παραλαβής' : 'Customer said pickup address',
                destination: currentLang === 'el' ? 'Πελάτης είπε τη διεύθυνση προορισμού' : 'Customer said destination',
                reservation: currentLang === 'el' ? 'Πελάτης είπε την ώρα κράτησης' : 'Customer said reservation time',
                default: currentLang === 'el' ? 'Ηχογραφημένη Είσοδος Χρήστη' : 'User Voice Input'
            };

            switch (type) {
                case 'name':
                    return {
                        title: defaultTitles.name + attemptText,
                        description: currentLang === 'el' ?
                            'Ο πελάτης είπε το όνομά του. Αυτή η ηχογράφηση χρησιμοποιείται για αναγνώριση ομιλίας και επιβεβαίωση ταυτότητας.' :
                            'Customer said their name. This recording is used for speech recognition and identity confirmation.'
                    };
                case 'pickup':
                    return {
                        title: defaultTitles.pickup + attemptText,
                        description: currentLang === 'el' ?
                            'Ο πελάτης είπε τη διεύθυνση παραλαβής. Επεξεργάζεται για τον προσδιορισμό της ακριβούς τοποθεσίας.' :
                            'Customer said the pickup address. Processed to determine exact location.'
                    };
                case 'destination':
                case 'dest':
                    return {
                        title: defaultTitles.destination + attemptText,
                        description: currentLang === 'el' ?
                            'Ο πελάτης είπε τον προορισμό του ταξιδιού. Χρησιμοποιείται για τον υπολογισμό της διαδρομής.' :
                            'Customer said the destination of the trip. Used for route calculation.'
                    };
                case 'reservation':
                    return {
                        title: defaultTitles.reservation + attemptText,
                        description: currentLang === 'el' ?
                            'Ο πελάτης είπε την ημερομηνία και ώρα κράτησης για μελλοντικό ταξίδι.' :
                            'Customer said the reservation date and time for a future trip.'
                    };
                default:
                    return {
                        title: defaultTitles.default + attemptText,
                        description: currentLang === 'el' ?
                            'Ηχογραφημένη απάντηση του πελάτη.' :
                            'Customer voice input recording.'
                    };
            }
        }

        // Get recording description based on filename and type
        function getRecordingDescription(filename, type, attempt) {
            attempt = attempt || 1;

            // Determine language - fallback to Greek if LANG is not available
            var currentLang = (typeof LANG !== 'undefined' && LANG.current) ? LANG.current : 'el';
            var attemptText = '';
            if (attempt > 1) {
                if (typeof LANG !== 'undefined' && LANG.translations && LANG.translations.attempt) {
                    attemptText = ' (' + LANG.translations.attempt + ' ' + attempt + ')';
                } else {
                    attemptText = currentLang === 'el' ? ' (Προσπάθεια ' + attempt + ')' : ' (Attempt ' + attempt + ')';
                }
            }

            // Default values if translations are missing
            var defaultConfirmationTitle = currentLang === 'el' ? 'Ήχος Επιβεβαίωσης' : 'Confirmation Audio';
            var defaultConfirmationDesc = currentLang === 'el' ?
                'Μήνυμα επιβεβαίωσης που δημιουργήθηκε από το σύστημα για επαλήθευση των στοιχείων κράτησης.' :
                'System-generated confirmation message for booking verification.';

            switch (type) {
                case 'confirmation':
                    return {
                        title: defaultConfirmationTitle + attemptText,
                        description: defaultConfirmationDesc
                    };
                case 'name':
                    return {
                        title: (currentLang === 'el' ? 'Ηχογράφηση Ονόματος Πελάτη' : 'Customer Name Recording') + attemptText,
                        description: currentLang === 'el' ?
                            'Ηχογραφημένο όνομα του πελάτη κατά τη διάρκεια της κλήσης. Χρησιμοποιείται για αναγνώριση και εξατομίκευση στο σύστημα κράτησης ταξί.' :
                            'Customer\'s spoken name recorded during the call. Used for identification and personalization in the taxi booking system.'
                    };
                case 'pickup':
                    return {
                        title: (currentLang === 'el' ? 'Ηχογράφηση Διεύθυνσης Παραλαβής' : 'Pickup Address Recording') + attemptText,
                        description: currentLang === 'el' ?
                            'Προφορική τοποθεσία παραλαβής του πελάτη. Επεξεργάζεται μέσω αναγνώρισης ομιλίας και γεωκωδικοποίησης για τον ακριβή προσδιορισμό των συντεταγμένων.' :
                            'Customer\'s spoken pickup location. Processed through speech-to-text and geocoding to determine exact pickup coordinates.'
                    };
                case 'destination':
                    return {
                        title: (currentLang === 'el' ? 'Ηχογράφηση Προορισμού' : 'Destination Recording') + attemptText,
                        description: currentLang === 'el' ?
                            'Προφορική διεύθυνση προορισμού του πελάτη. Επεξεργάζεται για τον προσδιορισμό της τοποθεσίας παράδοσης για την κράτηση ταξί.' :
                            'Customer\'s spoken destination address. Processed to determine the drop-off location for the taxi booking.'
                    };
                case 'reservation':
                    return {
                        title: (currentLang === 'el' ? 'Ηχογράφηση Ώρας Κράτησης' : 'Reservation Time Recording') + attemptText,
                        description: currentLang === 'el' ?
                            'Προτιμώμενη ώρα του πελάτη για την κράτηση ταξί. Επεξεργάζεται για την εξαγωγή πληροφοριών ημερομηνίας και ώρας.' :
                            'Customer\'s preferred time for taxi booking. Processed to extract date and time information for scheduled pickup.'
                    };
                case 'welcome':
                    return {
                        title: (currentLang === 'el' ? 'Μήνυμα Καλωσορίσματος' : 'Welcome Message') + attemptText,
                        description: currentLang === 'el' ?
                            'Μήνυμα καλωσορίσματος του συστήματος που παίζει στην αρχή της κλήσης για να καθοδηγήσει τους πελάτες.' :
                            'System greeting played at the start of the call to guide customers through the booking process.'
                    };
                case 'dtmf':
                    return {
                        title: (currentLang === 'el' ? 'Ηχογράφηση Εισαγωγής DTMF' : 'DTMF Input Recording') + attemptText,
                        description: currentLang === 'el' ?
                            'Ηχογράφηση των επιλογών πλήκτρων του πελάτη κατά τη διάρκεια της πλοήγησης στο διαδραστικό μενού.' :
                            'Recording of customer\'s button press choices during interactive menu navigation.'
                    };
                default:
                    return {
                        title: (currentLang === 'el' ? 'Ηχογράφηση Κλήσης' : 'Call Recording') + attemptText,
                        description: currentLang === 'el' ?
                            'Ηχογράφηση από τη συνεδρία κλήσης του πελάτη. Περιέχει προφορική αλληλεπίδραση με το αυτοματοποιημένο σύστημα κράτησης ταξί.' :
                            'Audio recording from the customer call session. Contains spoken interaction with the automated taxi booking system.'
                    };
            }
        }

        // Get recording icon based on type
        function getRecordingIcon(filename, type) {
            switch (type) {
                case 'confirmation':
                    return '<i class="fas fa-check-circle text-success"></i>';
                case 'name':
                    return '<i class="fas fa-user text-primary"></i>';
                case 'pickup':
                    return '<i class="fas fa-map-marker-alt text-success"></i>';
                case 'destination':
                    return '<i class="fas fa-flag-checkered text-danger"></i>';
                case 'reservation':
                    return '<i class="fas fa-calendar-alt text-warning"></i>';
                case 'welcome':
                    return '<i class="fas fa-volume-up text-info"></i>';
                case 'dtmf':
                    return '<i class="fas fa-keypad text-secondary"></i>';
                default:
                    return '<i class="fas fa-microphone text-gray-600"></i>';
            }
        }

        // Format audio duration
        function formatAudioDuration(seconds) {
            if (!seconds || seconds <= 0) return '';
            var mins = Math.floor(seconds / 60);
            var secs = Math.floor(seconds % 60);
            return mins > 0 ? mins + 'm ' + secs + 's' : secs + 's';
        }

        // Show call detail modal
        function showCallDetail(callId) {
            currentCallId = callId;
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

        // Show call detail by registration ID
        function showCallDetailByRegistrationId(registrationId) {
            var modal = document.getElementById('callDetailModal');
            var body = document.getElementById('callDetailBody');

            body.innerHTML = '<div class="loading"><div class="spinner"></div>Loading call details...</div>';
            modal.classList.add('show');

            fetch('?endpoint=call_by_registration_id&registration_id=' + encodeURIComponent(registrationId))
                .then(function(response) { return response.json(); })
                .then(function(call) {
                    currentCallId = call.call_id; // Set for other operations
                    renderCallDetail(call);
                })
                .catch(function(error) {
                    console.error('Error loading call detail by registration ID:', error);
                    body.innerHTML = '<div style="color: var(--danger); text-align: center; padding: 2rem;">Failed to load call details</div>';
                });
        }

        // Render call detail
        function renderCallDetail(call) {
            var body = document.getElementById('callDetailBody');
            
            var html = '<div class="call-detail-grid">' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.call_id + '</div>' +
                    '<div class="detail-value">' + (call.call_id || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.phone_number_label + '</div>' +
                    '<div class="detail-value">' + (call.phone_number || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.extension_label + '</div>' +
                    '<div class="detail-value">' + (call.extension || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.duration_label + '</div>' +
                    '<div class="detail-value" ' + 
                        (call.call_outcome === 'in_progress' ? 'class="live-duration-detail" data-start="' + new Date(call.call_start_time).getTime() + '" data-server-duration="' + call.call_duration + '"' : '') + '>' + 
                        formatDuration(call.call_duration, call.call_outcome === 'in_progress') + 
                    '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.status_label + '</div>' +
                    '<div class="detail-value">' + renderStatusBadge(call.call_outcome) + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.user_name_label + '</div>' +
                    '<div class="detail-value">' + (call.user_name || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.language_label + '</div>' +
                    '<div class="detail-value">' + (call.language_used || 'N/A') + '</div>' +
                '</div>' +
                '<div class="detail-item">' +
                    '<div class="detail-label">' + LANG.translations.api_calls_label + '</div>' +
                    '<div class="detail-value">' + (call.total_api_calls || 0) + '</div>' +
                '</div>' +
            '</div>';
            
            // Add location information
            if (call.pickup_address) {
                html += '<h4 style="margin: 1.5rem 0 1rem;">' + LANG.translations.location_information + '</h4>' +
                       '<div class="call-detail-grid">' +
                           '<div class="detail-item" style="grid-column: 1 / -1;">' +
                               '<div class="detail-label">' + LANG.translations.pickup_address_label + '</div>' +
                               '<div class="detail-value">' + call.pickup_address + '</div>' +
                           '</div>';
                
                if (call.destination_address) {
                    html += '<div class="detail-item" style="grid-column: 1 / -1;">' +
                               '<div class="detail-label">' + LANG.translations.destination_address_label + '</div>' +
                               '<div class="detail-value">' + call.destination_address + '</div>' +
                           '</div>';
                }
                
                html += '</div>';
                
                // Add map if coordinates exist
                if (call.pickup_lat && call.pickup_lng) {
                    html += '<div class="map-container" id="callMap"></div>';
                }
            }
            
            // Add recordings section - separate user and system recordings
            if (call.recordings && call.recordings.length > 0) {
                // Separate user input recordings from system recordings
                var userRecordings = [];
                var systemRecordings = [];

                for (var i = 0; i < call.recordings.length; i++) {
                    if (call.recordings[i].is_user_input) {
                        userRecordings.push(call.recordings[i]);
                    } else {
                        systemRecordings.push(call.recordings[i]);
                    }
                }

                // Display User Recordings first if any
                if (userRecordings.length > 0) {
                    html += '<h4 style="margin: 1.5rem 0 1rem; color: #4caf50;"><i class="fas fa-user-circle"></i> ' + ((typeof LANG !== 'undefined' && LANG.translations && LANG.translations.user_recordings) ? LANG.translations.user_recordings : 'Ηχογραφήσεις Χρήστη') + '</h4>';

                    // Sort user recordings by type and attempt
                    var sortedUserRecordings = userRecordings.sort(function(a, b) {
                        var typeOrder = ['name', 'pickup', 'destination', 'reservation'];
                        var aIndex = typeOrder.indexOf(a.type) !== -1 ? typeOrder.indexOf(a.type) : 999;
                        var bIndex = typeOrder.indexOf(b.type) !== -1 ? typeOrder.indexOf(b.type) : 999;
                        if (aIndex !== bIndex) return aIndex - bIndex;
                        return (a.attempt || 1) - (b.attempt || 1);
                    });

                    for (var i = 0; i < sortedUserRecordings.length; i++) {
                        var recording = sortedUserRecordings[i];
                        var sizeKB = (recording.size / 1024).toFixed(1);
                        var description = getUserRecordingDescription(recording.filename, recording.type, recording.attempt);
                        var icon = '🎤';

                        html += '<div class="recording-item" style="border-left: 3px solid #4caf50;">' +
                                   '<div class="recording-header">' +
                                       '<div class="recording-info">' +
                                           '<span class="recording-icon">' + icon + '</span>' +
                                           '<div class="recording-details">' +
                                               '<strong class="recording-title">' + description.title + '</strong>' +
                                               '<div class="recording-meta">' +
                                                   '<span class="recording-filename">' + recording.filename + '</span>' +
                                                   '<span class="recording-size">' + sizeKB + ' ' + ((typeof LANG !== 'undefined' && LANG.translations && LANG.translations.kb_size) ? LANG.translations.kb_size : 'KB') + '</span>' +
                                                   (recording.duration ? '<span class="recording-duration">' + formatAudioDuration(recording.duration) + '</span>' : '') +
                                                   (recording.attempt > 1 ? '<span class="recording-attempt" style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px;">' + ((typeof LANG !== 'undefined' && LANG.translations && LANG.translations.attempt) ? LANG.translations.attempt : 'Προσπάθεια') + ' ' + recording.attempt + '</span>' : '') +
                                               '</div>' +
                                           '</div>' +
                                       '</div>' +
                                   '</div>' +
                                   '<div class="recording-description" style="color: #666; font-style: italic;">' + description.description + '</div>' +
                                   '<audio controls class="recording-player" preload="none" style="width: 100%; margin-top: 10px;">' +
                                       '<source src="?action=audio&file=' + encodeURIComponent(recording.path) + '" type="audio/wav">' +
                                       ((typeof LANG !== 'undefined' && LANG.translations && LANG.translations.audio_not_supported) ? LANG.translations.audio_not_supported : 'Ο φυλλομετρητής σας δεν υποστηρίζει το στοιχείο ήχου.') +
                                   '</audio>' +
                               '</div>';
                    }
                }

                // Display System Recordings if any
                if (systemRecordings.length > 0) {
                    html += '<h4 style="margin: 1.5rem 0 1rem;"><i class="fas fa-microphone"></i> ' + ((typeof LANG !== 'undefined' && LANG.translations && LANG.translations.system_recordings) ? LANG.translations.system_recordings : 'Ηχογραφήσεις Συστήματος') + '</h4>';

                    // Sort system recordings by type
                    var sortedSystemRecordings = systemRecordings.sort(function(a, b) {
                        var typeOrder = ['welcome', 'confirmation', 'dtmf', 'other'];
                        var aIndex = typeOrder.indexOf(a.type) !== -1 ? typeOrder.indexOf(a.type) : 999;
                        var bIndex = typeOrder.indexOf(b.type) !== -1 ? typeOrder.indexOf(b.type) : 999;
                        if (aIndex !== bIndex) return aIndex - bIndex;
                        return (a.attempt || 1) - (b.attempt || 1);
                    });

                    for (var i = 0; i < sortedSystemRecordings.length; i++) {
                        var recording = sortedSystemRecordings[i];
                        var sizeKB = (recording.size / 1024).toFixed(1);
                        var description = getRecordingDescription(recording.filename, recording.type, recording.attempt);
                        var icon = getRecordingIcon(recording.filename, recording.type);

                        html += '<div class="recording-item">' +
                                   '<div class="recording-header">' +
                                       '<div class="recording-info">' +
                                           '<span class="recording-icon">' + icon + '</span>' +
                                           '<div class="recording-details">' +
                                               '<strong class="recording-title">' + description.title + '</strong>' +
                                               '<div class="recording-meta">' +
                                                   '<span class="recording-filename">' + recording.filename + '</span>' +
                                                   '<span class="recording-size">' + sizeKB + ' ' + ((typeof LANG !== 'undefined' && LANG.translations && LANG.translations.kb_size) ? LANG.translations.kb_size : 'KB') + '</span>' +
                                                   (recording.duration ? '<span class="recording-duration">' + formatAudioDuration(recording.duration) + '</span>' : '') +
                                               '</div>' +
                                           '</div>' +
                                       '</div>' +
                                   '</div>' +
                                   '<div class="recording-description">' + description.description + '</div>' +
                                   '<audio controls class="recording-player" preload="none">' +
                                       '<source src="?action=audio&file=' + encodeURIComponent(recording.path) + '" type="audio/wav">' +
                                       ((typeof LANG !== 'undefined' && LANG.translations && LANG.translations.audio_not_supported) ? LANG.translations.audio_not_supported : 'Ο φυλλομετρητής σας δεν υποστηρίζει το στοιχείο ήχου.') +
                                   '</audio>' +
                               '</div>';
                    }
                }
            }
            
            // Add enhanced call log section
            if (call.call_log && call.call_log.length > 0) {
                html += '<h4 style="margin: 1.5rem 0 1rem;">' + LANG.translations.call_log + '</h4>' +
                       '<div style="background: var(--gray-50); padding: 0; border-radius: 0.5rem; max-height: 500px; overflow-y: auto; border: 1px solid var(--gray-200);">';
                
                // Group logs by category for better organization
                var categoryColors = {
                    'user_input': '#3b82f6',
                    'tts': '#10b981',
                    'api': '#f59e0b',
                    'location': '#8b5cf6',
                    'error': '#ef4444',
                    'operator': '#ec4899',
                    'general': '#6b7280'
                };
                
                var categoryIcons = {
                    'user_input': '👤',
                    'tts': '🔊',
                    'api': '🔌',
                    'location': '📍',
                    'error': '❌',
                    'operator': '📞',
                    'general': '📝'
                };
                
                for (var i = 0; i < call.call_log.length; i++) {
                    var log = call.call_log[i];
                    var category = log.category || 'general';
                    var color = categoryColors[category] || '#6b7280';
                    var icon = categoryIcons[category] || '📝';
                    var timestamp = log.timestamp ? '<span style="color: #9ca3af; font-size: 0.7rem;">' + log.timestamp + '</span> ' : '';
                    var bgColor = (i % 2 === 0) ? '#f9fafb' : '#ffffff';
                    
                    html += '<div style="padding: 0.5rem 1rem; border-bottom: 1px solid var(--gray-100); background: ' + bgColor + ';">' +
                           '<div style="display: flex; align-items: flex-start; gap: 0.5rem;">' +
                           '<span style="font-size: 1rem; min-width: 1.5rem;">' + icon + '</span>' +
                           '<div style="flex: 1; min-width: 0;">' +
                           timestamp +
                           '<span style="color: ' + color + '; font-size: 0.8rem; line-height: 1.4; word-wrap: break-word;">' + 
                           (log.message || log.original || '') + '</span>' +
                           '</div></div></div>';
                }
                
                html += '</div>';
            }
            
            body.innerHTML = html;
            
            // Initialize map after DOM is updated
            if (call.pickup_address && call.pickup_lat && call.pickup_lng) {
                setTimeout(function() {
                    var map = L.map('callMap').setView([call.pickup_lat, call.pickup_lng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

                    // Create custom icons for pickup (green) and destination (red)
                    var pickupIcon = L.divIcon({
                        html: '<div style="background-color: #28a745; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10],
                        className: 'custom-marker-icon'
                    });

                    var destinationIcon = L.divIcon({
                        html: '<div style="background-color: #dc3545; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10],
                        className: 'custom-marker-icon'
                    });

                    var pickupMarker = L.marker([call.pickup_lat, call.pickup_lng], {icon: pickupIcon})
                        .addTo(map)
                        .bindPopup('Pickup: ' + (call.pickup_address || ''));

                    if (call.destination_lat && call.destination_lng) {
                        var destMarker = L.marker([call.destination_lat, call.destination_lng], {icon: destinationIcon})
                            .addTo(map)
                            .bindPopup('Destination: ' + (call.destination_address || ''));

                        // Get route from OSRM (free service)
                        var pickupLng = parseFloat(call.pickup_lng);
                        var pickupLat = parseFloat(call.pickup_lat);
                        var destLng = parseFloat(call.destination_lng);
                        var destLat = parseFloat(call.destination_lat);

                        var osrmUrl = 'https://router.project-osrm.org/route/v1/driving/' +
                                     pickupLng + ',' + pickupLat + ';' +
                                     destLng + ',' + destLat +
                                     '?overview=full&geometries=geojson';

                        fetch(osrmUrl)
                            .then(response => response.json())
                            .then(data => {
                                if (data.routes && data.routes.length > 0) {
                                    var route = data.routes[0];
                                    var coordinates = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);

                                    // Calculate distance and duration
                                    var distance = (route.distance / 1000).toFixed(1); // km
                                    var duration = Math.round(route.duration / 60); // minutes

                                    // Create polyline
                                    var polyline = L.polyline(coordinates, {
                                        color: '#3388ff',
                                        weight: 4,
                                        opacity: 0.7
                                    }).addTo(map);

                                    // Add click event to polyline
                                    polyline.on('click', function(e) {
                                        L.popup()
                                            .setLatLng(e.latlng)
                                            .setContent('<div><strong>Route Information</strong><br>' +
                                                       'Distance: ' + distance + ' km<br>' +
                                                       'Estimated Time: ' + duration + ' minutes</div>')
                                            .openOn(map);
                                    });

                                    // Fit map to show both markers and route
                                    var group = new L.featureGroup([pickupMarker, destMarker, polyline]);
                                    map.fitBounds(group.getBounds().pad(0.1));
                                } else {
                                    // Fallback if OSRM fails - just fit to markers
                                    var group = new L.featureGroup([pickupMarker, destMarker]);
                                    map.fitBounds(group.getBounds().pad(0.1));
                                }
                            })
                            .catch(error => {
                                console.log('OSRM routing failed:', error);
                                // Fallback if OSRM fails - just fit to markers
                                var group = new L.featureGroup([pickupMarker, destMarker]);
                                map.fitBounds(group.getBounds().pad(0.1));
                            });
                    }
                }, 100);
            }
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('callDetailModal').classList.remove('show');
            currentCallId = null;
        }

        // Refresh call detail
        function refreshCallDetail() {
            if (currentCallId) {
                showCallDetail(currentCallId);
            }
        }

        // Edit call with password protection
        function editCallWithAuth() {
            const password = prompt('Enter password to edit call:');
            if (password === null) return; // User cancelled

            // MD5 hash of "iqtaxiedit" is: 0c8e002237b0b9bc54a3987de63e9896
            const expectedHash = '0c8e002237b0b9bc54a3987de63e9896';
            const inputHash = CryptoJS.MD5(password).toString();

            if (inputHash === expectedHash) {
                editCall();
            } else {
                alert('Incorrect password');
            }
        }

        // Edit call - open comprehensive edit modal
        function editCall() {
            if (!currentCallId) {
                alert('No call selected');
                return;
            }

            // Fetch current call data to populate the form
            fetch('?endpoint=call&call_id=' + encodeURIComponent(currentCallId))
                .then(function(response) { return response.json(); })
                .then(function(call) {
                    populateEditForm(call);
                    document.getElementById('editCallModal').classList.add('show');
                })
                .catch(function(error) {
                    console.error('Error loading call for edit:', error);
                    alert('Failed to load call data for editing');
                });
        }

        // Populate edit form with current call data
        function populateEditForm(call) {
            document.getElementById('editPhone').value = call.phone_number || '';
            document.getElementById('editExtension').value = call.extension || '';
            document.getElementById('editCallType').value = call.call_type || '';
            document.getElementById('editInitialChoice').value = call.initial_choice || '';
            document.getElementById('editCallOutcome').value = call.call_outcome || '';
            document.getElementById('editName').value = call.user_name || call.name || '';  // Try user_name first, fallback to name
            document.getElementById('editPickupAddress').value = call.pickup_address || '';
            document.getElementById('editPickupLat').value = call.pickup_lat || '';
            document.getElementById('editPickupLng').value = call.pickup_lng || '';
            document.getElementById('editDestAddress').value = call.destination_address || '';
            document.getElementById('editDestLat').value = call.destination_lat || call.dest_lat || '';  // Try destination_lat first
            document.getElementById('editDestLng').value = call.destination_lng || call.dest_lng || '';  // Try destination_lng first
            
            // Format reservation time for datetime-local input
            if (call.reservation_time) {
                const date = new Date(call.reservation_time);
                const formatted = date.getFullYear() + '-' + 
                    String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(date.getDate()).padStart(2, '0') + 'T' + 
                    String(date.getHours()).padStart(2, '0') + ':' + 
                    String(date.getMinutes()).padStart(2, '0');
                document.getElementById('editReservationTime').value = formatted;
            } else {
                document.getElementById('editReservationTime').value = '';
            }
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editCallModal').classList.remove('show');
        }

        // Save call edits
        function saveCallEdit() {
            if (!currentCallId) {
                alert('No call selected');
                return;
            }

            // Collect form data - start with minimal data for debugging
            const formData = {
                call_id: currentCallId,
                call_outcome: document.getElementById('editCallOutcome').value
            };

            // Add other fields only if they have values
            const phone = document.getElementById('editPhone').value.trim();
            if (phone) formData.phone_number = phone;
            
            const extension = document.getElementById('editExtension').value.trim();
            if (extension) formData.extension = extension;
            
            const callType = document.getElementById('editCallType').value;
            if (callType) formData.call_type = callType;
            
            const initialChoice = document.getElementById('editInitialChoice').value;
            if (initialChoice) formData.initial_choice = initialChoice;
            
            const name = document.getElementById('editName').value.trim();
            if (name) formData.name = name;
            
            const pickupAddress = document.getElementById('editPickupAddress').value.trim();
            if (pickupAddress) formData.pickup_address = pickupAddress;
            
            const pickupLat = parseFloat(document.getElementById('editPickupLat').value);
            if (!isNaN(pickupLat)) formData.pickup_lat = pickupLat;
            
            const pickupLng = parseFloat(document.getElementById('editPickupLng').value);
            if (!isNaN(pickupLng)) formData.pickup_lng = pickupLng;
            
            const destAddress = document.getElementById('editDestAddress').value.trim();
            if (destAddress) formData.destination_address = destAddress;
            
            const destLat = parseFloat(document.getElementById('editDestLat').value);
            if (!isNaN(destLat)) formData.dest_lat = destLat;
            
            const destLng = parseFloat(document.getElementById('editDestLng').value);
            if (!isNaN(destLng)) formData.dest_lng = destLng;
            
            const reservationTime = document.getElementById('editReservationTime').value;
            if (reservationTime) formData.reservation_time = reservationTime;

            // Debug logging
            console.log('Form data to be sent:', formData);

            // Send update request
            fetch('?endpoint=edit_call', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(function(response) { 
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json(); 
            })
            .then(function(result) {
                if (result.success) {
                    alert('Call updated successfully');
                    closeEditModal();
                    refreshCallDetail(); // Refresh the current call detail
                    loadCalls(); // Refresh the calls table
                    loadStats(); // Refresh statistics
                } else {
                    alert('Failed to update call: ' + (result.error || result.message || 'Unknown error'));
                }
            })
            .catch(function(error) {
                console.error('Error updating call:', error);
                console.error('Full error details:', error);
                alert('Failed to update call: ' + error.message);
            });
        }
        
        // Export Modal Functions
        function showExportModal() {
            const modal = document.getElementById('exportModal');
            modal.classList.add('show');
            
            // Set default date range to last 30 days
            const now = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(now.getDate() - 30);
            
            document.getElementById('exportDateTo').value = formatDateTimeLocal(now);
            document.getElementById('exportDateFrom').value = formatDateTimeLocal(thirtyDaysAgo);
        }
        
        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }
        
        function performExport(format) {
            // Get export options
            const dateFrom = document.getElementById('exportDateFrom').value;
            const dateTo = document.getElementById('exportDateTo').value;
            const includeFilters = document.getElementById('includeCurrentFilters').checked;
            const limit = document.getElementById('exportLimit').value;
            
            // Build parameters
            const params = new URLSearchParams();
            params.set('action', 'export');
            params.set('format', format);
            
            if (dateFrom) {
                params.set('date_from', dateFrom.split('T')[0]);
            }
            if (dateTo) {
                params.set('date_to', dateTo.split('T')[0]);
            }
            if (limit !== 'all') {
                params.set('limit', limit);
            }
            
            // Include current filters if checked
            if (includeFilters) {
                for (const key in currentFilters) {
                    if (currentFilters.hasOwnProperty(key)) {
                        params.set(key, currentFilters[key]);
                    }
                }
            }
            
            // Close modal
            document.getElementById('exportModal').classList.remove('show');
            
            if (format === 'print') {
                // Open in new window for printing
                window.open('?' + params.toString(), '_blank');
            } else if (format === 'csv') {
                // Download CSV
                window.location.href = '?' + params.toString();
            } else if (format === 'pdf') {
                // Generate PDF
                window.open('?' + params.toString(), '_blank');
            }
        }

        // Setup Greek date pickers with dd/mm/yyyy format
        function setupGreekDatePickers() {
            // Initialize date from picker
            if (document.getElementById('dateFromFilter')) {
                flatpickr("#dateFromFilter", {
                    dateFormat: "d/m/Y",  // dd/mm/yyyy format
                    locale: "gr",         // Greek locale
                    allowInput: true,
                    clickOpens: true,
                    disableMobile: true,  // Force desktop version on mobile
                    minDate: "2020-01-01", // Allow dates from 2020 onwards
                    maxDate: "today", // Maximum date is today
                    onChange: function(selectedDates, dateStr, instance) {
                        // Update date to minimum date when date from changes
                        if (selectedDates.length > 0) {
                            var dateToPicker = document.getElementById('dateToFilter')._flatpickr;
                            if (dateToPicker) {
                                dateToPicker.set('minDate', selectedDates[0]);
                            }
                        }
                    }
                });

                // Make calendar icon clickable
                var dateFromIcon = document.querySelector('#dateFromFilter').parentElement.querySelector('.date-icon');
                if (dateFromIcon) {
                    dateFromIcon.style.pointerEvents = 'auto';
                    dateFromIcon.style.cursor = 'pointer';
                    dateFromIcon.addEventListener('click', function() {
                        var picker = document.getElementById('dateFromFilter')._flatpickr;
                        if (picker) picker.open();
                    });
                }
            }

            // Initialize date to picker
            if (document.getElementById('dateToFilter')) {
                flatpickr("#dateToFilter", {
                    dateFormat: "d/m/Y",  // dd/mm/yyyy format
                    locale: "gr",         // Greek locale
                    allowInput: true,
                    clickOpens: true,
                    disableMobile: true,  // Force desktop version on mobile
                    minDate: "2020-01-01", // Allow dates from 2020 onwards
                    maxDate: "today", // Maximum date is today
                    onChange: function(selectedDates, dateStr, instance) {
                        // Update date from maximum date when date to changes
                        if (selectedDates.length > 0) {
                            var dateFromPicker = document.getElementById('dateFromFilter')._flatpickr;
                            if (dateFromPicker) {
                                dateFromPicker.set('maxDate', selectedDates[0]);
                            }
                        }
                    }
                });

                // Make calendar icon clickable
                var dateToIcon = document.querySelector('#dateToFilter').parentElement.querySelector('.date-icon');
                if (dateToIcon) {
                    dateToIcon.style.pointerEvents = 'auto';
                    dateToIcon.style.cursor = 'pointer';
                    dateToIcon.addEventListener('click', function() {
                        var picker = document.getElementById('dateToFilter')._flatpickr;
                        if (picker) picker.open();
                    });
                }
            }
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
                    // Convert dd/mm/yyyy to yyyy-mm-dd for date fields
                    if ((key === 'date_from' || key === 'date_to') && value.includes('/')) {
                        var parts = value.split('/');
                        if (parts.length === 3) {
                            var day = parts[0].padStart(2, '0');
                            var month = parts[1].padStart(2, '0');
                            var year = parts[2];
                            value = year + '-' + month + '-' + day;
                        }
                    }
                    currentFilters[key] = value;
                }
                entry = entries.next();
            }

            console.log('Applying filters:', currentFilters);

            currentPage = 1;
            loadCalls();
            updateURL();

            // Show success feedback
            showFilterStatus('Filters applied!', 'text-success');

            // Close the modal
            document.getElementById('filterModal').classList.remove('show');
        }
        
        // Toggle real-time updates
        function toggleRealtime() {
            var btn = document.getElementById('realtimeBtn');
            var statusIndicator = document.querySelector('.status-indicator');
            var statusText = document.querySelector('.status-text');

            if (realtimeInterval) {
                clearInterval(realtimeInterval);
                realtimeInterval = null;
                btn.innerHTML = '<i class="fas fa-play"></i> Real-time';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-primary');

                // Update status indicator to show stopped state
                if (statusIndicator) {
                    statusIndicator.classList.remove('online');
                    statusIndicator.classList.add('offline');
                }
                if (statusText) {
                    statusText.textContent = 'Stopped';
                }
            } else {
                startRealtime();
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
            
            // Parse as UTC timestamp from server
            let date;
            if (dateStr.includes('T')) {
                // Already in ISO format
                date = new Date(dateStr);
            } else {
                // MySQL datetime format - append UTC indicator
                date = new Date(dateStr.replace(' ', 'T') + 'Z');
            }
            
            // Convert to user's local timezone automatically
            const locale = LANG.current === 'el' ? 'el-GR' : 'en-US';
            const options = { 
                day: '2-digit', 
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit', 
                minute: '2-digit',
                hour12: LANG.current === 'en'
            };
            
            return date.toLocaleString(locale, options);
        }
        
        
        function formatDuration(seconds, isLive) {
            const s_unit = LANG.translations.seconds_short;
            const m_unit = LANG.translations.minutes_short;
            const h_unit = LANG.translations.hours_short;
            
            if (!seconds || seconds === 0) {
                return isLive ? LANG.translations.processing : `0${s_unit}`;
            }
            
            if (seconds < 60) {
                return `${seconds}${s_unit}`;
            } else if (seconds < 3600) {
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return secs > 0 ? `${mins}${m_unit} ${secs}${s_unit}` : `${mins}${m_unit}`;
            } else {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                
                let result = `${hours}${h_unit}`;
                if (minutes > 0) {
                    result += ` ${minutes}${m_unit}`;
                }
                if (secs > 0) {
                    result += ` ${secs}${s_unit}`;
                }
                return result;
            }
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