<?php
/**
 * AGI Call Analytics System - Optimized Rewrite
 *
 * Key optimizations:
 * - DateBoundaryHelper: Converts dates to UTC before query (enables index usage)
 * - Combined dashboard query: 12+ queries reduced to 1-2
 * - File-based caching for dashboard stats
 * - Proper timezone handling without DATE_ADD in WHERE clauses
 *
 * @version 3.0.0
 */

// ============================================================================
// CONFIGURATION & CONSTANTS
// ============================================================================

define('CACHE_DIR', '/tmp/agi_analytics_cache');
define('CACHE_DASHBOARD_TTL', 60);      // 60 seconds for dashboard stats
define('CACHE_HOURLY_TTL', 30);         // 30 seconds for today's hourly data
define('CACHE_HISTORICAL_TTL', 300);    // 5 minutes for historical data

// ============================================================================
// DATE BOUNDARY HELPER CLASS - KEY FIX FOR INDEX USAGE
// ============================================================================

class DateBoundaryHelper {
    private $greeceTimezone;
    private $utcTimezone;

    public function __construct() {
        $this->greeceTimezone = new DateTimeZone('Europe/Athens');
        $this->utcTimezone = new DateTimeZone('UTC');
    }

    /**
     * Convert Greece date range to UTC boundaries for database queries
     * This allows MySQL to use the idx_call_start_time index
     */
    public function convertToUTCBoundaries(?string $dateFrom, ?string $dateTo): array {
        $utcFrom = null;
        $utcTo = null;

        if ($dateFrom) {
            // If date includes time, use it; otherwise add 00:00:00
            $timeStr = (strpos($dateFrom, ' ') !== false || strpos($dateFrom, 'T') !== false)
                ? $dateFrom
                : $dateFrom . ' 00:00:00';
            $dt = new DateTime($timeStr, $this->greeceTimezone);
            $dt->setTimezone($this->utcTimezone);
            $utcFrom = $dt->format('Y-m-d H:i:s');
        }

        if ($dateTo) {
            // If date includes time, use it; otherwise add 23:59:59
            $timeStr = (strpos($dateTo, ' ') !== false || strpos($dateTo, 'T') !== false)
                ? $dateTo
                : $dateTo . ' 23:59:59';
            $dt = new DateTime($timeStr, $this->greeceTimezone);
            $dt->setTimezone($this->utcTimezone);
            $utcTo = $dt->format('Y-m-d H:i:s');
        }

        return ['utc_from' => $utcFrom, 'utc_to' => $utcTo];
    }

    /**
     * Get today's boundaries in UTC
     */
    public function getTodayUTCBoundaries(): array {
        $today = new DateTime('now', $this->greeceTimezone);
        $todayStr = $today->format('Y-m-d');
        return $this->convertToUTCBoundaries($todayStr, $todayStr);
    }

    /**
     * Get this week's boundaries in UTC (Sunday to Saturday)
     */
    public function getWeekUTCBoundaries(): array {
        $now = new DateTime('now', $this->greeceTimezone);
        $dayOfWeek = (int)$now->format('w'); // 0=Sunday, 6=Saturday
        $startOfWeek = clone $now;
        $startOfWeek->modify("-{$dayOfWeek} days");
        return $this->convertToUTCBoundaries($startOfWeek->format('Y-m-d'), $now->format('Y-m-d'));
    }

    /**
     * Get this month's boundaries in UTC
     */
    public function getMonthUTCBoundaries(): array {
        $now = new DateTime('now', $this->greeceTimezone);
        $startOfMonth = new DateTime($now->format('Y-m-01'), $this->greeceTimezone);
        return $this->convertToUTCBoundaries($startOfMonth->format('Y-m-d'), $now->format('Y-m-d'));
    }

    /**
     * Get last N minutes boundaries in UTC
     */
    public function getLastMinutesUTCBoundaries(int $minutes): array {
        $now = new DateTime('now', $this->greeceTimezone);
        $past = clone $now;
        $past->modify("-{$minutes} minutes");

        $nowUtc = clone $now;
        $nowUtc->setTimezone($this->utcTimezone);
        $pastUtc = clone $past;
        $pastUtc->setTimezone($this->utcTimezone);

        return [
            'utc_from' => $pastUtc->format('Y-m-d H:i:s'),
            'utc_to' => $nowUtc->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Convert UTC timestamp to Greece local time for display
     */
    public function utcToLocal(string $utcTimestamp): string {
        if (empty($utcTimestamp) || $utcTimestamp === '0000-00-00 00:00:00') {
            return $utcTimestamp;
        }
        $dt = new DateTime($utcTimestamp, $this->utcTimezone);
        $dt->setTimezone($this->greeceTimezone);
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Get current Greece timezone offset in hours
     */
    public function getGreeceOffset(): int {
        $dt = new DateTime('now', $this->greeceTimezone);
        return intval($dt->getOffset() / 3600);
    }
}

// ============================================================================
// CACHE CLASS
// ============================================================================

class AnalyticsCache {
    private $cacheDir;
    private $enabled = true;

    public function __construct() {
        $this->cacheDir = CACHE_DIR;
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        $this->enabled = is_writable($this->cacheDir);
    }

    private function getCacheFile(string $key, ?string $extension = null): string {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $extPart = $extension ? "_ext{$extension}" : "";
        return $this->cacheDir . "/{$safeKey}{$extPart}.cache";
    }

    public function get(string $key, ?string $extension = null) {
        if (!$this->enabled) return null;

        $file = $this->getCacheFile($key, $extension);
        if (!file_exists($file)) return null;

        $content = @file_get_contents($file);
        if ($content === false) return null;

        $data = @unserialize($content);
        if ($data === false || !isset($data['expires']) || !isset($data['value'])) {
            return null;
        }

        if (time() > $data['expires']) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, $value, int $ttl, ?string $extension = null): bool {
        if (!$this->enabled) return false;

        $file = $this->getCacheFile($key, $extension);
        $data = [
            'expires' => time() + $ttl,
            'value' => $value
        ];

        return @file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function delete(string $key, ?string $extension = null): bool {
        $file = $this->getCacheFile($key, $extension);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function clearAll(): void {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}

// ============================================================================
// MAIN ANALYTICS CLASS
// ============================================================================

class AGIAnalytics {
    private $db;
    private $table = 'automated_calls_analitycs';
    private $language = 'el';
    private $translations = [];
    private $globalExtensionFilter = null;
    private $dateHelper;
    private $cache;

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
        date_default_timezone_set('Europe/Athens');

        $this->dateHelper = new DateBoundaryHelper();
        $this->cache = new AnalyticsCache();

        $this->initializeLanguage();
        $this->loadTranslations();
        $this->loadEnvConfig();
        $this->initializeGlobalFilters();
        $this->connectDatabase();
        $this->createTableIfNeeded();
        $this->createIndexesIfNeeded();
        $this->ensureRegistrationIdColumn();
    }

    private function loadEnvConfig() {
        $this->dbConfig['host'] = getenv('DB_HOST') ?: $this->dbConfig['host'];
        $this->dbConfig['dbname'] = getenv('DB_NAME') ?: $this->dbConfig['dbname'];
        $this->dbConfig['primary_user'] = getenv('DB_USER') ?: $this->dbConfig['primary_user'];
        $this->dbConfig['primary_pass'] = getenv('DB_PASS') ?: $this->dbConfig['primary_pass'];
        $this->dbConfig['fallback_user'] = getenv('DB_FALLBACK_USER') ?: $this->dbConfig['fallback_user'];
        $this->dbConfig['fallback_pass'] = getenv('DB_FALLBACK_PASS') ?: $this->dbConfig['fallback_pass'];
        $this->dbConfig['port'] = getenv('DB_PORT') ?: $this->dbConfig['port'];
    }

    private function initializeGlobalFilters() {
        if (!empty($_GET['extension'])) {
            $this->globalExtensionFilter = $_GET['extension'];
        }
    }

    private function getExtensionFilterClause($prefix = '') {
        if ($this->globalExtensionFilter === null) {
            return ['', []];
        }
        $field = $prefix ? "{$prefix}.extension" : "extension";
        return ["{$field} = ?", [$this->globalExtensionFilter]];
    }

    private function initializeLanguage() {
        $lang = $_GET['lang'] ?? 'el';
        if (in_array($lang, ['el', 'en'])) {
            $this->language = $lang;
        } else {
            $this->language = 'el';
        }
    }

    private function loadTranslations() {
        $this->translations = [
            'el' => [
                'dashboard_title' => 'Πίνακας Αναλυτικών Στοιχείων',
                'analytics_dashboard' => 'Πίνακας Αναλυτικών Στοιχείων',
                'realtime_monitoring' => 'Παρακολούθηση σε πραγματικό χρόνο και αναλυτικά στοιχεία κλήσεων',
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
                'advanced_filters' => 'Προχωρημένα Φίλτρα',
                'auto_filtering_enabled' => 'Αυτόματο φιλτράρισμα ενεργοποιημένο',
                'phone_number' => 'Τηλέφωνο',
                'extension' => 'Εσωτερικό',
                'call_type' => 'Τύπος Κλήσης',
                'outcome' => 'Αποτέλεσμα',
                'date_from' => 'Ημερομηνία Από',
                'date_to' => 'Ημερομηνία Έως',
                'user' => 'Χρήστης',
                'location' => 'Τοποθεσία',
                'all_types' => 'Όλοι οι Τύποι',
                'immediate' => 'Άμεση',
                'reservation' => 'Κράτηση',
                'operator' => 'Τηλεφωνητής',
                'all_outcomes' => 'Όλα τα Αποτελέσματα',
                'success' => 'Επιτυχία',
                'hangup' => 'Τερματισμός',
                'operator_transfer' => 'Μεταφορά σε Τηλεφωνητή',
                'error' => 'Σφάλμα',
                'in_progress' => 'Σε Εξέλιξη',
                'calls_per_hour' => 'Κλήσεις ανά Ώρα',
                'location_heatmap' => 'Χάρτης Τοποθεσιών',
                'today' => 'Σήμερα',
                'yesterday' => 'Χθες',
                'last_30_minutes' => '🕐 Τελευταία 30 λεπτά',
                'last_1_hour' => '🕐 Τελευταία 1 ώρα',
                'last_3_hours' => '🕒 Τελευταίες 3 ώρες',
                'last_6_hours' => '🕕 Τελευταίες 6 ώρες',
                'last_12_hours' => '🕙 Τελευταίες 12 ώρες',
                'last_24_hours' => '🌅 Τελευταίες 24 ώρες',
                'pickups' => 'Παραλαβές',
                'destinations' => 'Προορισμοί',
                'recent_calls' => 'Πρόσφατες Κλήσεις',
                'phone' => 'Τηλέφωνο',
                'time' => 'Ώρα',
                'duration' => 'Διάρκεια',
                'status' => 'Κατάσταση',
                'type' => 'Τύπος',
                'apis' => 'APIs',
                'actions' => 'Ενέργειες',
                'per_page' => 'ανά σελίδα',
                '25_per_page' => '25 ανά σελίδα',
                '50_per_page' => '50 ανά σελίδα',
                '100_per_page' => '100 ανά σελίδα',
                'loading_location_data' => 'Φόρτωση δεδομένων τοποθεσίας...',
                'fetching_locations' => 'Ανάκτηση τοποθεσιών κλήσεων από τη βάση δεδομένων',
                'no_location_data' => 'Δεν υπάρχουν δεδομένα τοποθεσίας',
                'waiting_for_calls' => 'Αναμονή για κλήσεις με δεδομένα τοποθεσίας...',
                'try_longer_period' => 'Δοκιμάστε να επιλέξετε μεγαλύτερο χρονικό διάστημα ή ελέγξτε αργότερα',
                'activity_level' => 'Επίπεδο Δραστηριότητας',
                'low' => 'Χαμηλό',
                'high' => 'Υψηλό',
                'call_details' => 'Λεπτομέρειες Κλήσης',
                'csv_export' => 'Εξαγωγή CSV',
                'export_complete' => 'Η εξαγωγή ολοκληρώθηκε',
                'system_error' => 'Σφάλμα Συστήματος',
                'database_error' => 'Σφάλμα Βάσης Δεδομένων',
                'loading' => 'Φόρτωση...',
                'processing' => 'Επεξεργασία...',
                'no_data' => 'Δεν υπάρχουν δεδομένα',
                'total_calls' => 'Συνολικές Κλήσεις',
                'successful_calls' => 'Επιτυχημένες Κλήσεις',
                'failed_calls' => 'Αποτυχημένες Κλήσεις',
                'average_duration' => 'Μέση Διάρκεια',
                'total_duration' => 'Συνολική Διάρκεια',
                'completed' => 'Ολοκληρώθηκε',
                'answered' => 'Απαντήθηκε',
                'busy' => 'Κατειλημμένο',
                'no_answer' => 'Δεν Απαντά',
                'failed' => 'Απέτυχε',
                'cancelled' => 'Ακυρώθηκε',
                'ongoing' => 'Σε Εξέλιξη',
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
                'seconds_short' => 'δ',
                'minutes_short' => 'λ',
                'hours_short' => 'ω',
                'days_short' => 'η',
                'active' => 'Ενεργό',
                'inactive' => 'Ανενεργό',
                'pending' => 'Εκκρεμές',
                'connecting' => 'Συνδέεται',
                'ringing' => 'Χτυπά',
                'talking' => 'Ομιλία',
                'from' => 'Από',
                'to' => 'Έως',
                'at' => 'στις',
                'duration_label' => 'Διάρκεια',
                'start_time' => 'Ώρα Έναρξης',
                'end_time' => 'Ώρα Τέλους',
                'total_calls_today' => 'Συνολικές Κλήσεις Σήμερα',
                'success_rate' => 'ποσοστό επιτυχίας',
                'failed_count' => 'Αποτυχίες',
                'avg_duration' => 'Μέση Διάρκεια',
                'per_call_average' => 'Μέσος όρος ανά κλήση',
                'unique_callers' => 'Μοναδικοί Καλούντες',
                'different_numbers' => 'Διαφορετικοί αριθμοί',
                'weekly_stats' => 'Εβδομαδιαίες Στατιστικές (από Κυριακή)',
                'monthly_stats' => 'Μηνιαίες Στατιστικές',
                'yes' => 'Ναι',
                'no' => 'Όχι',
                'export_data' => 'Εξαγωγή Δεδομένων',
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
                'include_current_filters' => 'Συμπερίληψη τρεχόντων φίλτρων',
                'apply_current_search_filters' => 'Εφαρμογή ενεργών φίλτρων αναζήτησης στην εξαγωγή',
                'records_limit' => 'Όριο Εγγραφών',
                'last_100_records' => 'Τελευταίες 100 εγγραφές',
                'last_500_records' => 'Τελευταίες 500 εγγραφές',
                'last_1000_records' => 'Τελευταίες 1000 εγγραφές',
                'last_5000_records' => 'Τελευταίες 5000 εγγραφές',
                'all_records' => 'Όλες οι εγγραφές',
                'edit_call' => 'Επεξεργασία Κλήσης',
                'phone_number_label' => 'Αριθμός Τηλεφώνου',
                'extension_label' => 'Εσωτερικό',
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
                'placeholder_search' => 'Τηλέφωνο, ID Κλήσης, Χρήστης...',
                'placeholder_phone' => 'Αριθμός τηλεφώνου',
                'placeholder_extension' => 'Εσωτερικό',
                'xlsx_export' => 'Εξαγωγή Excel',
                'download_excel_file' => 'Κατέβασμα αρχείου Excel (.xlsx)',
                'compatible_with_excel' => 'Συμβατό με Microsoft Excel και Google Sheets',
                'export_type' => 'Τύπος Εξαγωγής',
                'call_data' => 'Δεδομένα Κλήσεων',
                'summary_statistics' => 'Συνοπτικά Στατιστικά',
                'export_all_call_records' => 'Εξαγωγή όλων των εγγραφών κλήσεων με λεπτομέρειες',
                'export_aggregated_stats' => 'Εξαγωγή συγκεντρωτικών στατιστικών και αναφοράς',
                'export_filters' => 'Φίλτρα Εξαγωγής',
                'date_from' => 'Από Ημερομηνία',
                'date_to' => 'Έως Ημερομηνία',
                'outcome_filter' => 'Αποτέλεσμα',
                'all_outcomes' => 'Όλα τα αποτελέσματα',
                'select_extension' => 'Επιλογή εσωτερικού',
                'all_extensions' => 'Όλα τα εσωτερικά',
                'export_now' => 'Εξαγωγή Τώρα',
                'preparing_export' => 'Προετοιμασία εξαγωγής...',
                'export_format' => 'Μορφή Εξαγωγής'
            ],
            'en' => [
                'dashboard_title' => 'Analytics Dashboard',
                'analytics_dashboard' => 'Analytics Dashboard',
                'realtime_monitoring' => 'Real-time call monitoring and analytics',
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
                'advanced_filters' => 'Advanced Filters',
                'auto_filtering_enabled' => 'Auto-filtering enabled',
                'phone_number' => 'Phone Number',
                'extension' => 'Extension',
                'call_type' => 'Call Type',
                'outcome' => 'Outcome',
                'date_from' => 'Date From',
                'date_to' => 'Date To',
                'user' => 'User',
                'location' => 'Location',
                'all_types' => 'All Types',
                'immediate' => 'Immediate',
                'reservation' => 'Reservation',
                'operator' => 'Operator',
                'all_outcomes' => 'All Outcomes',
                'success' => 'Success',
                'hangup' => 'Hangup',
                'operator_transfer' => 'Operator Transfer',
                'error' => 'Error',
                'in_progress' => 'In Progress',
                'calls_per_hour' => 'Calls per Hour',
                'location_heatmap' => 'Location Heatmap',
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last_30_minutes' => '🕐 Last 30 minutes',
                'last_1_hour' => '🕐 Last 1 hour',
                'last_3_hours' => '🕒 Last 3 hours',
                'last_6_hours' => '🕕 Last 6 hours',
                'last_12_hours' => '🕙 Last 12 hours',
                'last_24_hours' => '🌅 Last 24 hours',
                'pickups' => 'Pickups',
                'destinations' => 'Destinations',
                'recent_calls' => 'Recent Calls',
                'phone' => 'Phone',
                'time' => 'Time',
                'duration' => 'Duration',
                'status' => 'Status',
                'type' => 'Type',
                'apis' => 'APIs',
                'actions' => 'Actions',
                'per_page' => 'per page',
                '25_per_page' => '25 per page',
                '50_per_page' => '50 per page',
                '100_per_page' => '100 per page',
                'loading_location_data' => 'Loading location data...',
                'fetching_locations' => 'Fetching call locations from database',
                'no_location_data' => 'No location data available',
                'waiting_for_calls' => 'Waiting for calls with location data...',
                'try_longer_period' => 'Try selecting a longer time period or check back later',
                'activity_level' => 'Activity Level',
                'low' => 'Low',
                'high' => 'High',
                'call_details' => 'Call Details',
                'csv_export' => 'CSV Export',
                'export_complete' => 'Export completed',
                'system_error' => 'System Error',
                'database_error' => 'Database Error',
                'loading' => 'Loading...',
                'processing' => 'Processing...',
                'no_data' => 'No data available',
                'total_calls' => 'Total Calls',
                'successful_calls' => 'Successful Calls',
                'failed_calls' => 'Failed Calls',
                'average_duration' => 'Average Duration',
                'total_duration' => 'Total Duration',
                'completed' => 'Completed',
                'answered' => 'Answered',
                'busy' => 'Busy',
                'no_answer' => 'No Answer',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
                'ongoing' => 'Ongoing',
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
                'seconds_short' => 's',
                'minutes_short' => 'm',
                'hours_short' => 'h',
                'days_short' => 'd',
                'active' => 'Active',
                'inactive' => 'Inactive',
                'pending' => 'Pending',
                'connecting' => 'Connecting',
                'ringing' => 'Ringing',
                'talking' => 'Talking',
                'from' => 'From',
                'to' => 'To',
                'at' => 'at',
                'duration_label' => 'Duration',
                'start_time' => 'Start Time',
                'end_time' => 'End Time',
                'total_calls_today' => 'Total Calls Today',
                'success_rate' => 'success rate',
                'failed_count' => 'Failures',
                'avg_duration' => 'Avg Duration',
                'per_call_average' => 'Per call average',
                'unique_callers' => 'Unique Callers',
                'different_numbers' => 'Different numbers',
                'weekly_stats' => 'Weekly Stats (since Sunday)',
                'monthly_stats' => 'Monthly Stats',
                'yes' => 'Yes',
                'no' => 'No',
                'export_data' => 'Export Data',
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
                'include_current_filters' => 'Include current filters',
                'apply_current_search_filters' => 'Apply currently active search filters to export',
                'records_limit' => 'Records Limit',
                'last_100_records' => 'Last 100 records',
                'last_500_records' => 'Last 500 records',
                'last_1000_records' => 'Last 1000 records',
                'last_5000_records' => 'Last 5000 records',
                'all_records' => 'All records',
                'edit_call' => 'Edit Call',
                'phone_number_label' => 'Phone Number',
                'extension_label' => 'Extension',
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
                'placeholder_search' => 'Phone, Call ID, User...',
                'placeholder_phone' => 'Phone number',
                'placeholder_extension' => 'Extension',
                'xlsx_export' => 'Excel Export',
                'download_excel_file' => 'Download Excel file (.xlsx)',
                'compatible_with_excel' => 'Compatible with Microsoft Excel and Google Sheets',
                'export_type' => 'Export Type',
                'call_data' => 'Call Data',
                'summary_statistics' => 'Summary Statistics',
                'export_all_call_records' => 'Export all call records with details',
                'export_aggregated_stats' => 'Export aggregated statistics and report',
                'export_filters' => 'Export Filters',
                'date_from' => 'Date From',
                'date_to' => 'Date To',
                'outcome_filter' => 'Outcome',
                'all_outcomes' => 'All outcomes',
                'select_extension' => 'Select extension',
                'all_extensions' => 'All extensions',
                'export_now' => 'Export Now',
                'preparing_export' => 'Preparing export...',
                'export_format' => 'Export Format'
            ]
        ];
    }

    private function t($key, $fallback = null) {
        return $this->translations[$this->language][$key] ?? $this->translations['en'][$key] ?? $fallback ?? $key;
    }

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

    private function translateCallType($type) {
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

    // ========================================================================
    // DATABASE CONNECTION
    // ========================================================================

    private function connectDatabase() {
        $dsn = "mysql:host={$this->dbConfig['host']};port={$this->dbConfig['port']};dbname={$this->dbConfig['dbname']};charset={$this->dbConfig['charset']}";

        try {
            $this->db = new PDO($dsn, $this->dbConfig['primary_user'], $this->dbConfig['primary_pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->db->query("SELECT 1");
            return;
        } catch (PDOException $e) {
            error_log("Analytics: Primary connection failed: " . $e->getMessage());
        }

        try {
            $this->db = new PDO($dsn, $this->dbConfig['fallback_user'], $this->dbConfig['fallback_pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->db->query("SELECT 1");
            return;
        } catch (PDOException $e) {
            error_log("Analytics: All connections failed: " . $e->getMessage());
            $this->sendErrorResponse('Database connection failed', 500);
        }
    }

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
        } catch (PDOException $e) {
            error_log("Analytics: Failed to create table: " . $e->getMessage());
            throw $e;
        }
    }

    private function createIndexesIfNeeded() {
        $indexes = [
            'idx_phone_extension' => "CREATE INDEX IF NOT EXISTS idx_phone_extension ON {$this->table} (phone_number, extension)",
            'idx_datetime_outcome' => "CREATE INDEX IF NOT EXISTS idx_datetime_outcome ON {$this->table} (call_start_time, call_outcome)",
            'idx_coordinates' => "CREATE INDEX IF NOT EXISTS idx_coordinates ON {$this->table} (pickup_lat, pickup_lng, destination_lat, destination_lng)"
        ];

        foreach ($indexes as $name => $sql) {
            try {
                $this->db->exec($sql);
            } catch (PDOException $e) {
                // Index might already exist
            }
        }
    }

    private function ensureRegistrationIdColumn() {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$this->table} LIKE 'registration_id'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN registration_id VARCHAR(50) AFTER user_name");
                $this->db->exec("CREATE INDEX idx_registration_id ON {$this->table} (registration_id)");
            }
        } catch (PDOException $e) {
            error_log("Analytics: Failed to ensure registration_id column: " . $e->getMessage());
        }
    }

    // ========================================================================
    // REQUEST HANDLER
    // ========================================================================

    public function handleRequest() {
        $this->setCORSHeaders();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_GET['endpoint'] ?? '';
        $action = $_GET['action'] ?? '';

        if ($action === 'export') {
            $format = $_GET['format'] ?? 'csv';
            switch ($format) {
                case 'csv': $this->exportCSV(); break;
                case 'xlsx': $this->exportXLSX(); break;
                case 'pdf': $this->exportPDF(); break;
                case 'print': $this->exportPrint(); break;
                default: $this->exportCSV(); break;
            }
            return;
        }

        if ($action === 'audio') {
            $this->serveAudio();
            return;
        }

        if (!empty($endpoint)) {
            $this->handleAPI($method, $endpoint);
            return;
        }

        $this->renderDashboard();
    }

    private function setCORSHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Endpoint, X-Action');
        header('Access-Control-Max-Age: 3600');
    }

    private function handleAPI($method, $endpoint) {
        header('Content-Type: application/json');

        try {
            switch ($method) {
                case 'GET': $this->handleGetAPI($endpoint); break;
                case 'POST': $this->handlePostAPI($endpoint); break;
                case 'PUT': $this->handlePutAPI($endpoint); break;
                case 'DELETE': $this->handleDeleteAPI($endpoint); break;
                default: $this->sendErrorResponse('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("Analytics API Error: " . $e->getMessage());
            $this->sendErrorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    // ========================================================================
    // GET API ENDPOINTS
    // ========================================================================

    private function handleGetAPI($endpoint) {
        switch ($endpoint) {
            case 'calls': $this->apiGetCalls(); break;
            case 'call': $this->apiGetCall(); break;
            case 'call_by_registration_id': $this->apiGetCallByRegistrationId(); break;
            case 'search': $this->apiSearch(); break;
            case 'analytics': $this->apiGetAnalytics(); break;
            case 'dashboard': $this->apiGetDashboard(); break;
            case 'hourly': $this->apiGetHourlyAnalytics(); break;
            case 'daily': $this->apiGetDailyAnalytics(); break;
            case 'realtime': $this->apiGetRealtimeStats(); break;
            case 'locations': $this->apiGetLocations(); break;
            case 'server_time': $this->apiGetServerTime(); break;
            case 'recordings': $this->apiGetRecordings(); break;
            case 'getFinalPickUpRec': $this->apiGetFinalPickUpRec(); break;
            case 'cleanup_stale': $this->apiCleanupStaleCalls(); break;
            default: $this->sendErrorResponse('Endpoint not found', 404);
        }
    }

    /**
     * OPTIMIZED: Get calls with proper UTC date filtering (uses index!)
     */
    private function apiGetCalls() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(1000, max(1, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Apply global extension filter
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        if ($extWhere) {
            $where[] = $extWhere;
            $params = array_merge($params, $extParams);
        }

        // General search
        if (!empty($_GET['search'])) {
            $searchTerm = $_GET['search'];
            $where[] = '(phone_number LIKE ? OR call_id LIKE ? OR unique_id LIKE ? OR user_name LIKE ? OR pickup_address LIKE ? OR destination_address LIKE ? OR registration_id LIKE ?)';
            $searchPattern = "%{$searchTerm}%";
            $params = array_merge($params, array_fill(0, 7, $searchPattern));
        }

        // Advanced filters
        $filters = [
            'phone' => 'phone_number LIKE ?',
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

        // OPTIMIZED: Date filtering with UTC conversion (enables index usage!)
        if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
            $bounds = $this->dateHelper->convertToUTCBoundaries(
                $_GET['date_from'] ?? null,
                $_GET['date_to'] ?? null
            );
            if ($bounds['utc_from']) {
                $where[] = 'call_start_time >= ?';
                $params[] = $bounds['utc_from'];
            }
            if ($bounds['utc_to']) {
                $where[] = 'call_start_time <= ?';
                $params[] = $bounds['utc_to'];
            }
        }

        // Time range filtering (still needs function but less critical)
        $tzOffset = $this->dateHelper->getGreeceOffset();
        if (!empty($_GET['time_from'])) {
            $where[] = "TIME(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) >= ?";
            $params[] = $_GET['time_from'];
        }
        if (!empty($_GET['time_to'])) {
            $where[] = "TIME(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) <= ?";
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
        $sortFields = ['call_start_time', 'call_duration', 'phone_number', 'extension', 'call_outcome', 'call_type', 'created_at', 'updated_at'];
        $sort = in_array($_GET['sort'] ?? '', $sortFields) ? $_GET['sort'] : 'call_start_time';
        $direction = strtoupper($_GET['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        // Get data with UTC timestamps (convert in PHP for display)
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY {$sort} {$direction} LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $calls = $stmt->fetchAll();

        // Convert UTC timestamps to local time in PHP
        foreach ($calls as &$call) {
            $call['call_start_time'] = $this->dateHelper->utcToLocal($call['call_start_time']);
            if ($call['call_end_time']) {
                $call['call_end_time'] = $this->dateHelper->utcToLocal($call['call_end_time']);
            }
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

        // Convert UTC to local
        $call['call_start_time'] = $this->dateHelper->utcToLocal($call['call_start_time']);
        if ($call['call_end_time']) {
            $call['call_end_time'] = $this->dateHelper->utcToLocal($call['call_end_time']);
        }

        $call = $this->enhanceCallData($call, true);
        $this->sendResponse($call);
    }

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

        $call['call_start_time'] = $this->dateHelper->utcToLocal($call['call_start_time']);
        if ($call['call_end_time']) {
            $call['call_end_time'] = $this->dateHelper->utcToLocal($call['call_end_time']);
        }

        $call = $this->enhanceCallData($call, true);
        $this->sendResponse($call);
    }

    private function apiSearch() {
        $query = $_GET['q'] ?? '';
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));

        if (empty($query)) {
            $this->sendResponse(['calls' => []]);
            return;
        }

        $sql = "SELECT * FROM {$this->table} WHERE
                phone_number LIKE ? OR call_id LIKE ? OR unique_id LIKE ? OR
                user_name LIKE ? OR pickup_address LIKE ? OR destination_address LIKE ? OR
                extension LIKE ? OR registration_id LIKE ?
                ORDER BY call_start_time DESC LIMIT {$limit}";

        $searchTerm = "%{$query}%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_fill(0, 8, $searchTerm));
        $calls = $stmt->fetchAll();

        foreach ($calls as &$call) {
            $call['call_start_time'] = $this->dateHelper->utcToLocal($call['call_start_time']);
            if ($call['call_end_time']) {
                $call['call_end_time'] = $this->dateHelper->utcToLocal($call['call_end_time']);
            }
            $call = $this->enhanceCallData($call);
        }

        $this->sendResponse(['calls' => $calls]);
    }

    /**
     * OPTIMIZED: Dashboard with combined query and caching
     */
    private function apiGetDashboard() {
        try {
            // Try cache first
            $cacheKey = 'dashboard_stats';
            $cached = $this->cache->get($cacheKey, $this->globalExtensionFilter);
            if ($cached !== null) {
                $this->sendResponse($cached);
                return;
            }

            // Combined optimized query for all dashboard stats
            $dashboard = [
                'realtime_stats' => $this->getRealtimeStats(),
                'recent_calls' => $this->getRecentCalls(),
                'today_summary' => null,
                'weekly_summary' => null,
                'monthly_summary' => null,
                'active_calls' => 0,
                'system_health' => $this->getSystemHealth()
            ];

            // Get combined stats in single query
            $combinedStats = $this->getCombinedDashboardStats();
            $dashboard['today_summary'] = $combinedStats['today'];
            $dashboard['weekly_summary'] = $combinedStats['week'];
            $dashboard['monthly_summary'] = $combinedStats['month'];
            $dashboard['active_calls'] = $combinedStats['active_calls'];

            // Cache for 60 seconds
            $this->cache->set($cacheKey, $dashboard, CACHE_DASHBOARD_TTL, $this->globalExtensionFilter);

            $this->sendResponse($dashboard);
        } catch (Exception $e) {
            error_log("Dashboard API Error: " . $e->getMessage());
            $this->sendErrorResponse('Dashboard data error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * OPTIMIZED: Single combined query for all dashboard period stats
     * Reduces 12+ queries to just 1
     */
    private function getCombinedDashboardStats(): array {
        $today = $this->dateHelper->getTodayUTCBoundaries();
        $week = $this->dateHelper->getWeekUTCBoundaries();
        $month = $this->dateHelper->getMonthUTCBoundaries();

        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $extCondition = $extWhere ? "AND {$extWhere}" : "";

        // Combined query using CASE WHEN for all periods
        $sql = "SELECT
            -- Today stats
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN 1 END) as today_total,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_outcome = 'success' THEN 1 END) as today_success,
            AVG(CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN call_duration END) as today_avg_duration,
            SUM(CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN google_tts_calls + edge_tts_calls ELSE 0 END) as today_tts,
            COUNT(DISTINCT CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN phone_number END) as today_unique,

            -- Week stats
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN 1 END) as week_total,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_outcome = 'success' THEN 1 END) as week_success,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_outcome = 'hangup' THEN 1 END) as week_hangup,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_outcome = 'operator_transfer' THEN 1 END) as week_operator,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_type = 'reservation' THEN 1 END) as week_reservation,
            AVG(CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN call_duration END) as week_avg_duration,
            COUNT(DISTINCT CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN phone_number END) as week_unique,

            -- Month stats
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN 1 END) as month_total,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_outcome = 'success' THEN 1 END) as month_success,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_outcome = 'hangup' THEN 1 END) as month_hangup,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_outcome = 'operator_transfer' THEN 1 END) as month_operator,
            COUNT(CASE WHEN call_start_time >= ? AND call_start_time <= ? AND call_type = 'reservation' THEN 1 END) as month_reservation,
            AVG(CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN call_duration END) as month_avg_duration,
            COUNT(DISTINCT CASE WHEN call_start_time >= ? AND call_start_time <= ? THEN phone_number END) as month_unique,

            -- Active calls (no date filter)
            COUNT(CASE WHEN call_outcome = 'in_progress' THEN 1 END) as active_calls,
            MAX(call_start_time) as last_call_time

        FROM {$this->table}
        WHERE call_start_time >= ? {$extCondition}";

        // Build params: today (5x2), week (7x2), month (7x2), then month start for WHERE
        $params = [
            // Today (5 metrics x 2 boundaries each)
            $today['utc_from'], $today['utc_to'],
            $today['utc_from'], $today['utc_to'],
            $today['utc_from'], $today['utc_to'],
            $today['utc_from'], $today['utc_to'],
            $today['utc_from'], $today['utc_to'],
            // Week (7 metrics x 2 boundaries each)
            $week['utc_from'], $week['utc_to'],
            $week['utc_from'], $week['utc_to'],
            $week['utc_from'], $week['utc_to'],
            $week['utc_from'], $week['utc_to'],
            $week['utc_from'], $week['utc_to'],
            $week['utc_from'], $week['utc_to'],
            $week['utc_from'], $week['utc_to'],
            // Month (7 metrics x 2 boundaries each)
            $month['utc_from'], $month['utc_to'],
            $month['utc_from'], $month['utc_to'],
            $month['utc_from'], $month['utc_to'],
            $month['utc_from'], $month['utc_to'],
            $month['utc_from'], $month['utc_to'],
            $month['utc_from'], $month['utc_to'],
            $month['utc_from'], $month['utc_to'],
            // WHERE clause
            $month['utc_from']
        ];

        if ($extParams) {
            $params = array_merge($params, $extParams);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'today' => [
                'total_calls' => (int)($row['today_total'] ?? 0),
                'successful_calls' => (int)($row['today_success'] ?? 0),
                'avg_duration' => round((float)($row['today_avg_duration'] ?? 0), 2),
                'tts_usage' => (int)($row['today_tts'] ?? 0),
                'unique_callers' => (int)($row['today_unique'] ?? 0)
            ],
            'week' => [
                'total_calls' => (int)($row['week_total'] ?? 0),
                'successful_calls' => (int)($row['week_success'] ?? 0),
                'hangup_calls' => (int)($row['week_hangup'] ?? 0),
                'operator_calls' => (int)($row['week_operator'] ?? 0),
                'reservation_calls' => (int)($row['week_reservation'] ?? 0),
                'avg_duration' => round((float)($row['week_avg_duration'] ?? 0), 2),
                'unique_callers' => (int)($row['week_unique'] ?? 0)
            ],
            'month' => [
                'total_calls' => (int)($row['month_total'] ?? 0),
                'successful_calls' => (int)($row['month_success'] ?? 0),
                'hangup_calls' => (int)($row['month_hangup'] ?? 0),
                'operator_calls' => (int)($row['month_operator'] ?? 0),
                'reservation_calls' => (int)($row['month_reservation'] ?? 0),
                'avg_duration' => round((float)($row['month_avg_duration'] ?? 0), 2),
                'unique_callers' => (int)($row['month_unique'] ?? 0)
            ],
            'active_calls' => (int)($row['active_calls'] ?? 0),
            'last_call_time' => $row['last_call_time'] ? $this->dateHelper->utcToLocal($row['last_call_time']) : null
        ];
    }

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
     * OPTIMIZED: Hourly analytics with UTC date boundaries
     */
    private function apiGetHourlyAnalytics() {
        $date = $_GET['date'] ?? date('Y-m-d');

        // Convert date to UTC boundaries
        $bounds = $this->dateHelper->convertToUTCBoundaries($date, $date);

        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $tzOffset = $this->dateHelper->getGreeceOffset();

        $sql = "SELECT
                    HOUR(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) as hour,
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                    COUNT(CASE WHEN call_outcome = 'hangup' THEN 1 END) as hangup_calls,
                    COUNT(CASE WHEN call_outcome = 'operator_transfer' THEN 1 END) as operator_transfers,
                    AVG(call_duration) as avg_duration,
                    SUM(google_tts_calls + edge_tts_calls) as tts_usage,
                    SUM(google_stt_calls) as stt_usage
                FROM {$this->table}
                WHERE {$whereClause}
                GROUP BY HOUR(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR))
                ORDER BY hour";

        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $hourlyData = $stmt->fetchAll();

        // Fill missing hours
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
     * OPTIMIZED: Daily analytics with UTC boundaries
     */
    private function apiGetDailyAnalytics() {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');

        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        $tzOffset = $this->dateHelper->getGreeceOffset();

        $sql = "SELECT
                    DATE(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) as date,
                    COUNT(*) as total_calls,
                    COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                    COUNT(CASE WHEN call_outcome = 'hangup' THEN 1 END) as hangup_calls,
                    AVG(call_duration) as avg_duration,
                    SUM(google_tts_calls + edge_tts_calls) as tts_usage,
                    SUM(google_stt_calls) as stt_usage,
                    COUNT(DISTINCT phone_number) as unique_callers
                FROM {$this->table}
                WHERE call_start_time >= ? AND call_start_time <= ?
                GROUP BY DATE(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR))
                ORDER BY date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bounds['utc_from'], $bounds['utc_to']]);
        $dailyData = $stmt->fetchAll();

        $this->sendResponse(['daily_data' => $dailyData, 'date_range' => ['from' => $dateFrom, 'to' => $dateTo]]);
    }

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
     * OPTIMIZED: Locations with UTC boundaries
     */
    private function apiGetLocations() {
        $minutes = intval($_GET['minutes'] ?? 30);
        $bounds = $this->dateHelper->getLastMinutesUTCBoundaries($minutes);

        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "(pickup_lat IS NOT NULL OR destination_lat IS NOT NULL)"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT
                    pickup_address, pickup_lat, pickup_lng,
                    destination_address, destination_lat, destination_lng,
                    call_outcome, call_start_time
                FROM {$this->table}
                WHERE {$whereClause}
                ORDER BY call_start_time DESC";

        $params = array_merge([$bounds['utc_from']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $locations = [];

        while ($row = $stmt->fetch()) {
            $localTime = $this->dateHelper->utcToLocal($row['call_start_time']);
            if ($row['pickup_lat'] && $row['pickup_lng']) {
                $locations[] = [
                    'lat' => floatval($row['pickup_lat']),
                    'lng' => floatval($row['pickup_lng']),
                    'type' => 'pickup',
                    'address' => $row['pickup_address'],
                    'outcome' => $row['call_outcome'],
                    'time' => $localTime
                ];
            }
            if ($row['destination_lat'] && $row['destination_lng']) {
                $locations[] = [
                    'lat' => floatval($row['destination_lat']),
                    'lng' => floatval($row['destination_lng']),
                    'type' => 'destination',
                    'address' => $row['destination_address'],
                    'outcome' => $row['call_outcome'],
                    'time' => $localTime
                ];
            }
        }

        $this->sendResponse([
            'locations' => $locations,
            'count' => count($locations),
            'period_minutes' => $minutes,
            'from' => $this->dateHelper->utcToLocal($bounds['utc_from'])
        ]);
    }

    private function apiGetServerTime() {
        try {
            $greekDateTime = new DateTime('now', new DateTimeZone('Europe/Athens'));
            $this->sendResponse([
                'server_time' => $greekDateTime->format('Y-m-d H:i:s'),
                'timestamp' => $greekDateTime->getTimestamp() * 1000
            ]);
        } catch (Exception $e) {
            $this->sendErrorResponse('Failed to get server time: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clean up stale calls that are stuck in pending/in_progress status
     * Only affects calls older than 3 hours to avoid touching active calls
     */
    private function apiCleanupStaleCalls() {
        try {
            // Calculate cutoff time: 3 hours ago in UTC
            $cutoffTime = new DateTime('now', new DateTimeZone('UTC'));
            $cutoffTime->modify('-3 hours');
            $cutoffStr = $cutoffTime->format('Y-m-d H:i:s');

            // First, get count of stale calls for reporting
            $countSql = "SELECT
                COUNT(CASE WHEN call_outcome = 'in_progress' THEN 1 END) as in_progress_count,
                COUNT(CASE WHEN call_outcome = 'pending' THEN 1 END) as pending_count
                FROM {$this->table}
                WHERE call_start_time < ?
                AND call_outcome IN ('in_progress', 'pending')";

            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$cutoffStr]);
            $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

            $totalStale = ($counts['in_progress_count'] ?? 0) + ($counts['pending_count'] ?? 0);

            if ($totalStale === 0) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'No stale calls found',
                    'updated' => 0,
                    'details' => ['in_progress' => 0, 'pending' => 0]
                ]);
                return;
            }

            // Update in_progress calls to 'hangup' (they never completed)
            $updateInProgressSql = "UPDATE {$this->table}
                SET call_outcome = 'hangup',
                    call_end_time = COALESCE(call_end_time, call_start_time)
                WHERE call_start_time < ?
                AND call_outcome = 'in_progress'";

            $stmt1 = $this->db->prepare($updateInProgressSql);
            $stmt1->execute([$cutoffStr]);
            $inProgressUpdated = $stmt1->rowCount();

            // Update pending calls to hangup
            $updatePendingSql = "UPDATE {$this->table}
                SET call_outcome = 'hangup',
                    call_end_time = COALESCE(call_end_time, call_start_time)
                WHERE call_start_time < ?
                AND call_outcome = 'pending'";

            $stmt2 = $this->db->prepare($updatePendingSql);
            $stmt2->execute([$cutoffStr]);
            $pendingUpdated = $stmt2->rowCount();

            $totalUpdated = $inProgressUpdated + $pendingUpdated;

            $this->sendResponse([
                'success' => true,
                'message' => "Cleaned up {$totalUpdated} stale calls",
                'updated' => $totalUpdated,
                'details' => [
                    'in_progress_to_hangup' => $inProgressUpdated,
                    'pending_to_hangup' => $pendingUpdated
                ],
                'cutoff_time' => $this->dateHelper->utcToLocal($cutoffStr)
            ]);

        } catch (Exception $e) {
            $this->sendErrorResponse('Cleanup failed: ' . $e->getMessage(), 500);
        }
    }

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

    private function apiGetFinalPickUpRec() {
        $uniqueId = $_GET['id'] ?? '';
        if (empty($uniqueId)) {
            $this->sendErrorResponse('id parameter required', 400);
            return;
        }

        $baseDir = "/var/auto_register_call";
        $pattern = "{$baseDir}/*/*/{$uniqueId}/recordings/pickup_*.wav";
        $pickupFiles = glob($pattern);

        if (empty($pickupFiles)) {
            $this->sendErrorResponse('No pickup recording found', 404);
            return;
        }

        usort($pickupFiles, function($a, $b) {
            preg_match('/pickup_(\d+)\.wav$/', $a, $matchesA);
            preg_match('/pickup_(\d+)\.wav$/', $b, $matchesB);
            $numA = isset($matchesA[1]) ? intval($matchesA[1]) : 0;
            $numB = isset($matchesB[1]) ? intval($matchesB[1]) : 0;
            return $numB - $numA;
        });

        $finalPickupFile = $pickupFiles[0];

        if (!file_exists($finalPickupFile) || !is_readable($finalPickupFile)) {
            $this->sendErrorResponse('Pickup recording file not accessible', 404);
            return;
        }

        header('Content-Type: audio/wav');
        header('Content-Length: ' . filesize($finalPickupFile));
        header('Content-Disposition: inline; filename="' . basename($finalPickupFile) . '"');
        header('Accept-Ranges: bytes');
        readfile($finalPickupFile);
        exit;
    }

    // ========================================================================
    // POST/PUT/DELETE API ENDPOINTS
    // ========================================================================

    private function handlePostAPI($endpoint) {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'call': $this->apiCreateCall($data); break;
            case 'delete_call': $this->apiDeleteCall($data); break;
            case 'edit_call': $this->apiEditCall($data); break;
            default: $this->sendErrorResponse('Endpoint not found', 404);
        }
    }

    private function handlePutAPI($endpoint) {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($endpoint) {
            case 'call': $this->apiUpdateCall($data); break;
            default: $this->sendErrorResponse('Endpoint not found', 404);
        }
    }

    private function handleDeleteAPI($endpoint) {
        switch ($endpoint) {
            case 'call': $this->apiDeleteCall(); break;
            default: $this->sendErrorResponse('Endpoint not found', 404);
        }
    }

    private function apiCreateCall($data) {
        if (empty($data)) {
            $this->sendErrorResponse('No data provided', 400);
            return;
        }

        $data = array_merge([
            'call_start_time' => date('Y-m-d H:i:s'),
            'call_outcome' => 'in_progress',
            'call_type' => null,
            'language_used' => 'el',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $data);

        $data = $this->cleanDataTypes($data);

        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($data));
            $id = $this->db->lastInsertId();

            // Clear dashboard cache on new call
            $this->cache->delete('dashboard_stats', $this->globalExtensionFilter);

            $this->sendResponse(['id' => $id, 'success' => true, 'message' => 'Call record created successfully']);
        } catch (PDOException $e) {
            error_log("Analytics: Create call error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to create call record: ' . $e->getMessage(), 500);
        }
    }

    private function apiUpdateCall($data) {
        if (empty($data['id']) && empty($data['call_id'])) {
            $this->sendErrorResponse('ID or call_id required', 400);
            return;
        }

        $id = $data['id'] ?? '';
        $callId = $data['call_id'] ?? '';
        unset($data['id'], $data['call_id']);

        $data['updated_at'] = date('Y-m-d H:i:s');
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

            // Clear dashboard cache on update
            $this->cache->delete('dashboard_stats', $this->globalExtensionFilter);

            $this->sendResponse(['success' => true, 'affected_rows' => $affected, 'message' => 'Call record updated successfully']);
        } catch (PDOException $e) {
            error_log("Analytics: Update call error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to update call record: ' . $e->getMessage(), 500);
        }
    }

    private function apiDeleteCall($data = null) {
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

            // Clear dashboard cache on delete
            $this->cache->delete('dashboard_stats', $this->globalExtensionFilter);

            $this->sendResponse(['success' => true, 'deleted_rows' => $affected, 'message' => 'Call record deleted successfully']);
        } catch (PDOException $e) {
            error_log("Analytics: Delete call error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to delete call record: ' . $e->getMessage(), 500);
        }
    }

    private function apiEditCall($data) {
        if (!$this->db) {
            $this->sendErrorResponse('Database connection failed', 500);
            return;
        }

        $id = $data['id'] ?? '';
        $callId = $data['call_id'] ?? '';

        if (empty($id) && empty($callId)) {
            $this->sendErrorResponse('ID or call_id required', 400);
            return;
        }

        $fields = [];
        $values = [];
        $allowedFields = [
            'phone_number', 'extension', 'call_type', 'initial_choice', 'call_outcome',
            'name', 'pickup_address', 'pickup_lat', 'pickup_lng',
            'destination_address', 'dest_lat', 'dest_lng', 'reservation_time'
        ];

        $fieldMap = [
            'name' => 'user_name',
            'dest_lat' => 'destination_lat',
            'dest_lng' => 'destination_lng'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $dbField = $fieldMap[$field] ?? $field;
                $fields[] = "$dbField = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            $this->sendErrorResponse('No valid fields to update', 400);
            return;
        }

        try {
            $checkCol = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'updated_at'");
            if ($checkCol && $checkCol->rowCount() > 0) {
                $fields[] = "updated_at = NOW()";
            }
        } catch (Exception $e) {
            // Skip
        }

        $values[] = !empty($id) ? $id : $callId;
        $whereField = !empty($id) ? "id" : "call_id";

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE $whereField = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $affected = $stmt->rowCount();

            // Clear cache
            $this->cache->delete('dashboard_stats', $this->globalExtensionFilter);

            if ($affected > 0) {
                $this->sendResponse(['success' => true, 'updated_rows' => $affected, 'message' => 'Call record updated successfully']);
            } else {
                $this->sendErrorResponse('No call found with the provided ID', 404);
            }
        } catch (PDOException $e) {
            error_log("Analytics: Edit call error: " . $e->getMessage());
            $this->sendErrorResponse('Failed to update call record: ' . $e->getMessage(), 500);
        }
    }

    // ========================================================================
    // DATA PROCESSING METHODS
    // ========================================================================

    private function enhanceCallData($call, $detailed = false) {
        if (empty($call['call_id']) || $call['call_id'] === 'UNDEFINED') {
            $call['call_id'] = $call['unique_id'] ?? ('call_' . ($call['id'] ?? uniqid()));
        }

        $call['success_rate'] = $call['call_outcome'] === 'success' ? 100 : 0;
        $call['has_location_data'] = !empty($call['pickup_lat']) && !empty($call['pickup_lng']);
        $call['total_api_calls'] =
            ($call['google_tts_calls'] ?? 0) +
            ($call['edge_tts_calls'] ?? 0) +
            ($call['google_stt_calls'] ?? 0) +
            ($call['geocoding_api_calls'] ?? 0) +
            ($call['user_api_calls'] ?? 0) +
            ($call['registration_api_calls'] ?? 0);

        // Live duration for in-progress calls
        if ($call['call_outcome'] === 'in_progress' && !empty($call['call_start_time'])) {
            $startTime = (new DateTime($call['call_start_time'], new DateTimeZone('Europe/Athens')))->getTimestamp();
            $currentTime = (new DateTime('now', new DateTimeZone('Europe/Athens')))->getTimestamp();
            $call['call_duration'] = $currentTime - $startTime;
            $call['is_live'] = true;
        } else {
            $call['is_live'] = false;
        }

        $call['call_start_time_formatted'] = $call['call_start_time'];
        $call['call_end_time_formatted'] = $call['call_end_time'];
        $call['duration_formatted'] = $this->formatDuration($call['call_duration']);

        if ($detailed) {
            $call['recordings'] = $this->getCallRecordings($call['recording_path'] ?? '');
            $call['call_log'] = $this->getCallLog($call['log_file_path'] ?? '');
            $call['related_calls'] = $this->getRelatedCalls($call['phone_number'], $call['id']);
        }

        return $call;
    }

    private function cleanDataTypes($data) {
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

        $booleanFields = ['is_reservation', 'language_changed', 'confirmed_default_address', 'successful_registration'];
        $integerFields = [
            'call_duration', 'confirmation_attempts', 'total_retries', 'name_attempts',
            'pickup_attempts', 'destination_attempts', 'reservation_attempts',
            'google_tts_calls', 'google_stt_calls', 'edge_tts_calls', 'geocoding_api_calls',
            'user_api_calls', 'registration_api_calls', 'date_parsing_api_calls',
            'tts_processing_time', 'stt_processing_time', 'geocoding_processing_time',
            'total_processing_time', 'api_response_time', 'callback_mode', 'days_valid'
        ];
        $floatFields = ['pickup_lat', 'pickup_lng', 'destination_lat', 'destination_lng'];

        $cleanedData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $validFields)) {
                $cleanedData[$key] = $value;
            }
        }

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

    // ========================================================================
    // DASHBOARD HELPER METHODS
    // ========================================================================

    private function getRealtimeStats() {
        try {
            return [
                'active_calls' => $this->getActiveCalls(),
                'calls_last_hour' => $this->getCurrentHourCallCount(),
                'success_rate_today' => $this->getTodaySuccessRate(),
                'avg_duration_today' => $this->getTodayAvgDuration()
            ];
        } catch (Exception $e) {
            error_log("getRealtimeStats Error: " . $e->getMessage());
            return ['active_calls' => 0, 'calls_last_hour' => 0, 'success_rate_today' => 0, 'avg_duration_today' => 0];
        }
    }

    private function getRecentCalls($limit = 20) {
        try {
            list($extWhere, $extParams) = $this->getExtensionFilterClause();
            $whereClause = $extWhere ? "WHERE {$extWhere}" : "";
            $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY call_start_time DESC LIMIT ?";
            $params = array_merge($extParams, [$limit]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $calls = $stmt->fetchAll();

            if (!$calls) return [];

            foreach ($calls as &$call) {
                $call['call_start_time'] = $this->dateHelper->utcToLocal($call['call_start_time']);
                if ($call['call_end_time']) {
                    $call['call_end_time'] = $this->dateHelper->utcToLocal($call['call_end_time']);
                }
                $call = $this->enhanceCallData($call);
            }

            return $calls;
        } catch (Exception $e) {
            error_log("getRecentCalls Error: " . $e->getMessage());
            return [];
        }
    }

    private function getActiveCalls() {
        try {
            list($extWhere, $extParams) = $this->getExtensionFilterClause();
            $whereConditions = ["call_outcome = 'in_progress'"];
            if ($extWhere) $whereConditions[] = $extWhere;
            $whereClause = implode(' AND ', $whereConditions);
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($extParams);
            return intval($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log("getActiveCalls Error: " . $e->getMessage());
            return 0;
        }
    }

    private function getTodayCallCount() {
        try {
            $bounds = $this->dateHelper->getTodayUTCBoundaries();
            list($extWhere, $extParams) = $this->getExtensionFilterClause();
            $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
            if ($extWhere) $whereConditions[] = $extWhere;
            $whereClause = implode(' AND ', $whereConditions);
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}";
            $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return intval($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log("getTodayCallCount Error: " . $e->getMessage());
            return 0;
        }
    }

    private function getCurrentHourCallCount() {
        try {
            $bounds = $this->dateHelper->getLastMinutesUTCBoundaries(60);
            list($extWhere, $extParams) = $this->getExtensionFilterClause();
            $whereConditions = ["call_start_time >= ?"];
            if ($extWhere) $whereConditions[] = $extWhere;
            $whereClause = implode(' AND ', $whereConditions);
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}";
            $params = array_merge([$bounds['utc_from']], $extParams);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return intval($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log("getCurrentHourCallCount Error: " . $e->getMessage());
            return 0;
        }
    }

    private function getTodaySuccessRate() {
        try {
            $bounds = $this->dateHelper->getTodayUTCBoundaries();
            list($extWhere, $extParams) = $this->getExtensionFilterClause();
            $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
            if ($extWhere) $whereConditions[] = $extWhere;
            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as success_rate
                    FROM {$this->table} WHERE {$whereClause}";

            $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return round(floatval($stmt->fetchColumn() ?: 0), 2);
        } catch (Exception $e) {
            error_log("getTodaySuccessRate Error: " . $e->getMessage());
            return 0;
        }
    }

    private function getTodayAvgDuration() {
        try {
            $bounds = $this->dateHelper->getTodayUTCBoundaries();
            list($extWhere, $extParams) = $this->getExtensionFilterClause();
            $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
            if ($extWhere) $whereConditions[] = $extWhere;
            $whereClause = implode(' AND ', $whereConditions);
            $sql = "SELECT AVG(call_duration) FROM {$this->table} WHERE {$whereClause}";
            $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return round(floatval($stmt->fetchColumn() ?: 0), 2);
        } catch (Exception $e) {
            error_log("getTodayAvgDuration Error: " . $e->getMessage());
            return 0;
        }
    }

    private function getAverageResponseTime() {
        try {
            $bounds = $this->dateHelper->getTodayUTCBoundaries();
            list($extWhere, $extParams) = $this->getExtensionFilterClause();
            $whereConditions = ["call_start_time >= ?", "call_start_time <= ?", "api_response_time > 0"];
            if ($extWhere) $whereConditions[] = $extWhere;
            $whereClause = implode(' AND ', $whereConditions);
            $sql = "SELECT AVG(api_response_time) FROM {$this->table} WHERE {$whereClause}";
            $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return round(floatval($stmt->fetchColumn() ?: 0), 2);
        } catch (Exception $e) {
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
            $time = $result->fetchColumn();
            return $time ? $this->dateHelper->utcToLocal($time) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function getTodayErrorRate() {
        try {
            $bounds = $this->dateHelper->getTodayUTCBoundaries();
            $sql = "SELECT COUNT(CASE WHEN call_outcome = 'error' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as error_rate
                    FROM {$this->table} WHERE call_start_time >= ? AND call_start_time <= ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$bounds['utc_from'], $bounds['utc_to']]);
            return round(floatval($stmt->fetchColumn() ?: 0), 2);
        } catch (Exception $e) {
            return 0;
        }
    }

    // ========================================================================
    // ANALYTICS HELPER METHODS (with UTC boundaries)
    // ========================================================================

    private function getAnalyticsSummary($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

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
                WHERE {$whereClause}";

        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch();

        $summary['success_rate'] = $summary['total_calls'] > 0
            ? round(($summary['successful_calls'] / $summary['total_calls']) * 100, 2) : 0;
        return $summary;
    }

    private function getOutcomeAnalytics($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        $sql = "SELECT call_outcome, COUNT(*) as count,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$this->table} WHERE call_start_time >= ? AND call_start_time <= ?)), 2) as percentage
                FROM {$this->table} WHERE call_start_time >= ? AND call_start_time <= ?
                GROUP BY call_outcome ORDER BY count DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bounds['utc_from'], $bounds['utc_to'], $bounds['utc_from'], $bounds['utc_to']]);
        return $stmt->fetchAll();
    }

    private function getHourlyDistribution($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        $tzOffset = $this->dateHelper->getGreeceOffset();
        $sql = "SELECT HOUR(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) as hour, COUNT(*) as count
                FROM {$this->table} WHERE call_start_time >= ? AND call_start_time <= ?
                GROUP BY HOUR(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) ORDER BY hour";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bounds['utc_from'], $bounds['utc_to']]);
        return $stmt->fetchAll();
    }

    private function getDailyTrend($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        $tzOffset = $this->dateHelper->getGreeceOffset();
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT DATE(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) as date,
                COUNT(*) as total_calls, COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls
                FROM {$this->table} WHERE {$whereClause}
                GROUP BY DATE(DATE_ADD(call_start_time, INTERVAL {$tzOffset} HOUR)) ORDER BY date";
        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function getExtensionPerformance($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?", "extension IS NOT NULL"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT extension, COUNT(*) as total_calls,
                COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                AVG(call_duration) as avg_duration,
                ROUND((COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) * 100.0 / COUNT(*)), 2) as success_rate
                FROM {$this->table} WHERE {$whereClause} GROUP BY extension ORDER BY total_calls DESC";
        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function getAPIUsageAnalytics($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT SUM(google_tts_calls) as google_tts_total, SUM(edge_tts_calls) as edge_tts_total,
                SUM(google_stt_calls) as google_stt_total, SUM(geocoding_api_calls) as geocoding_total,
                SUM(user_api_calls) as user_api_total, SUM(registration_api_calls) as registration_total,
                AVG(tts_processing_time) as avg_tts_time, AVG(stt_processing_time) as avg_stt_time,
                AVG(geocoding_processing_time) as avg_geocoding_time, AVG(api_response_time) as avg_api_response_time
                FROM {$this->table} WHERE {$whereClause}";
        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function getGeographicAnalytics($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?", "pickup_lat IS NOT NULL", "pickup_lng IS NOT NULL"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT pickup_address, destination_address, pickup_lat, pickup_lng, destination_lat, destination_lng, COUNT(*) as frequency
                FROM {$this->table} WHERE {$whereClause} GROUP BY pickup_address, destination_address ORDER BY frequency DESC LIMIT 50";
        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function getCallDurationStats($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT AVG(call_duration) as avg_duration, MIN(call_duration) as min_duration,
                MAX(call_duration) as max_duration, STDDEV(call_duration) as std_deviation,
                COUNT(CASE WHEN call_duration <= 30 THEN 1 END) as short_calls,
                COUNT(CASE WHEN call_duration BETWEEN 31 AND 120 THEN 1 END) as medium_calls,
                COUNT(CASE WHEN call_duration > 120 THEN 1 END) as long_calls
                FROM {$this->table} WHERE {$whereClause}";
        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function getLanguageStats($dateFrom, $dateTo) {
        $bounds = $this->dateHelper->convertToUTCBoundaries($dateFrom, $dateTo);
        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        $whereConditions = ["call_start_time >= ?", "call_start_time <= ?"];
        if ($extWhere) $whereConditions[] = $extWhere;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT language_used, COUNT(*) as count, COUNT(CASE WHEN language_changed = 1 THEN 1 END) as changed_count
                FROM {$this->table} WHERE {$whereClause} GROUP BY language_used ORDER BY count DESC";
        $params = array_merge([$bounds['utc_from'], $bounds['utc_to']], $extParams);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ========================================================================
    // FILE HANDLING METHODS
    // ========================================================================

    private function getCallRecordings($recordingPath) {
        if (empty($recordingPath) || !is_dir($recordingPath)) return [];
        $recordings = [];
        $patterns = ['*.wav', '*.mp3', '*.ogg'];

        foreach ($patterns as $pattern) {
            $files = glob($recordingPath . '/' . $pattern);
            foreach ($files as $file) {
                $filename = basename($file);
                $recordings[] = ['filename' => $filename, 'path' => $file, 'size' => filesize($file),
                    'duration' => $this->getAudioDuration($file), 'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'url' => $this->getAudioURL($file), 'type' => $this->getRecordingType($filename),
                    'attempt' => $this->getRecordingAttempt($filename), 'is_user_input' => false];
            }
        }

        $userPath = $recordingPath . '/recordings';
        if (is_dir($userPath)) {
            foreach ($patterns as $pattern) {
                $files = glob($userPath . '/' . $pattern);
                foreach ($files as $file) {
                    $filename = basename($file);
                    $recordings[] = ['filename' => $filename, 'path' => $file, 'size' => filesize($file),
                        'duration' => $this->getAudioDuration($file), 'created' => date('Y-m-d H:i:s', filemtime($file)),
                        'url' => $this->getAudioURL($file), 'type' => $this->getRecordingType($filename),
                        'attempt' => $this->getRecordingAttempt($filename), 'is_user_input' => true];
                }
            }
        }
        return $recordings;
    }

    private function getCallLog($logPath) {
        if (empty($logPath) || !file_exists($logPath)) return [];
        $logContent = file_get_contents($logPath);
        $logLines = array_filter(array_map('trim', explode("\n", $logContent)));
        $parsedLog = [];

        foreach ($logLines as $line) {
            if (empty($line)) continue;
            $timestamp = $this->extractTimestamp($line);
            $level = $this->extractLogLevel($line);
            $cleanMessage = $timestamp ? preg_replace('/^\[?\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\]?\s*/', '', $line) : $line;
            $category = 'general';
            if (strpos($line, 'TTS') !== false || strpos($line, 'Playing') !== false) $category = 'tts';
            elseif (strpos($line, 'API') !== false || strpos($line, 'Registration') !== false) $category = 'api';
            elseif (strpos($line, 'User') !== false || strpos($line, 'choice') !== false || strpos($line, 'DTMF') !== false) $category = 'user_input';
            elseif (strpos($line, 'Error') !== false || strpos($line, 'Failed') !== false) { $category = 'error'; $level = 'error'; }
            elseif (strpos($line, 'Redirecting') !== false || strpos($line, 'operator') !== false) $category = 'operator';
            elseif (strpos($line, 'pickup') !== false || strpos($line, 'destination') !== false || strpos($line, 'address') !== false) $category = 'location';
            $parsedLog[] = ['timestamp' => $timestamp, 'level' => $level, 'category' => $category, 'message' => $cleanMessage, 'original' => $line];
        }
        return $parsedLog;
    }

    private function getRelatedCalls($phoneNumber, $excludeId) {
        $sql = "SELECT * FROM {$this->table} WHERE phone_number = ? AND id != ? ORDER BY call_start_time DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$phoneNumber, $excludeId]);
        $calls = $stmt->fetchAll();
        foreach ($calls as &$call) {
            $call['call_start_time'] = $this->dateHelper->utcToLocal($call['call_start_time']);
            if ($call['call_end_time']) $call['call_end_time'] = $this->dateHelper->utcToLocal($call['call_end_time']);
        }
        return $calls;
    }

    private function getAudioDuration($filePath) {
        $duration = 0;
        if (function_exists('shell_exec')) {
            $cmd = "ffprobe -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 \"$filePath\" 2>/dev/null";
            $result = shell_exec($cmd);
            if ($result && is_numeric(trim($result))) $duration = (float)trim($result);
        }
        if ($duration == 0) $duration = max(1, round(filesize($filePath) / 32000));
        return $duration;
    }

    private function getAudioURL($filePath) {
        return str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', $filePath);
    }

    private function getRecordingType($filename) {
        $lower = strtolower($filename);
        if (strpos($lower, 'confirm') !== false) return 'confirmation';
        if (preg_match('/^name_\d+\./i', $filename)) return 'name';
        if (preg_match('/^pickup_\d+\./i', $filename)) return 'pickup';
        if (preg_match('/^dest_\d+\./i', $filename)) return 'destination';
        if (preg_match('/^reservation_\d+\./i', $filename)) return 'reservation';
        if (strpos($lower, 'name') !== false) return 'name';
        if (strpos($lower, 'pickup') !== false && strpos($lower, 'confirm') === false) return 'pickup';
        if (strpos($lower, 'dest') !== false) return 'destination';
        if (strpos($lower, 'reservation') !== false || strpos($lower, 'date') !== false) return 'reservation';
        if (strpos($lower, 'welcome') !== false || strpos($lower, 'greeting') !== false) return 'welcome';
        if (strpos($lower, 'dtmf') !== false || strpos($lower, 'choice') !== false) return 'dtmf';
        return 'other';
    }

    private function getRecordingAttempt($filename) {
        if (preg_match('/_(\d+)\./', $filename, $matches)) return (int)$matches[1];
        return 1;
    }

    private function extractTimestamp($logLine) {
        if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $logLine, $matches)) return $matches[1];
        return '';
    }

    private function extractLogLevel($logLine) {
        if (preg_match('/\[(ERROR|WARN|INFO|DEBUG)\]/', $logLine, $matches)) return strtolower($matches[1]);
        return 'info';
    }

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    private function formatDuration($seconds) {
        $s = $this->t('seconds_short'); $m = $this->t('minutes_short'); $h = $this->t('hours_short');
        if ($seconds < 60) return "{$seconds}{$s}";
        if ($seconds < 3600) { $mins = floor($seconds / 60); $secs = $seconds % 60; return $secs > 0 ? "{$mins}{$m} {$secs}{$s}" : "{$mins}{$m}"; }
        $hours = floor($seconds / 3600); $mins = floor(($seconds % 3600) / 60); $secs = $seconds % 60;
        $result = "{$hours}{$h}";
        if ($mins > 0) $result .= " {$mins}{$m}";
        if ($secs > 0) $result .= " {$secs}{$s}";
        return $result;
    }

    private function serveAudio() {
        $file = $_GET['file'] ?? '';
        if (empty($file) || !file_exists($file)) { http_response_code(404); echo json_encode(['error' => 'Audio file not found']); return; }
        $mimeType = 'audio/wav';
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'mp3') $mimeType = 'audio/mpeg';
        elseif ($ext === 'ogg') $mimeType = 'audio/ogg';
        header("Content-Type: {$mimeType}");
        header('Content-Length: ' . filesize($file));
        header('Accept-Ranges: bytes');
        readfile($file);
        exit;
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function sendErrorResponse($message, $statusCode = 400) {
        http_response_code($statusCode);
        echo json_encode(['error' => $message, 'status' => $statusCode, 'timestamp' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
        exit;
    }

    // ========================================================================
    // EXPORT FUNCTIONS (with fixed UTC boundaries)
    // ========================================================================

    private function getExportData() {
        $where = [];
        $params = [];

        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        if ($extWhere) { $where[] = $extWhere; $params = array_merge($params, $extParams); }

        // FIXED: Use UTC boundaries instead of DATE_ADD in WHERE clause
        if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
            $bounds = $this->dateHelper->convertToUTCBoundaries($_GET['date_from'] ?? null, $_GET['date_to'] ?? null);
            if ($bounds['utc_from']) { $where[] = "call_start_time >= ?"; $params[] = $bounds['utc_from']; }
            if ($bounds['utc_to']) { $where[] = "call_start_time <= ?"; $params[] = $bounds['utc_to']; }
        }

        if (!empty($_GET['phone'])) { $where[] = 'phone_number LIKE ?'; $params[] = '%' . $_GET['phone'] . '%'; }
        if (!empty($_GET['extension']) && !$this->globalExtensionFilter) { $where[] = 'extension = ?'; $params[] = $_GET['extension']; }
        if (!empty($_GET['outcome'])) { $where[] = 'call_outcome = ?'; $params[] = $_GET['outcome']; }

        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause} ORDER BY id DESC";

        if (!empty($_GET['limit']) && $_GET['limit'] !== 'all' && is_numeric($_GET['limit'])) {
            $sql .= " LIMIT " . intval($_GET['limit']);
        } elseif (isset($_GET['limit']) && $_GET['limit'] === 'all') {
            $sql .= " LIMIT 5000";
        } else {
            $sql .= " LIMIT 5000";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        // Convert UTC to local for display in exports
        foreach ($data as &$row) {
            $row['call_start_time'] = $this->dateHelper->utcToLocal($row['call_start_time']);
            if ($row['call_end_time']) $row['call_end_time'] = $this->dateHelper->utcToLocal($row['call_end_time']);
        }

        $totals = ['total_calls' => count($data), 'total_google_tts' => 0, 'total_google_stt' => 0, 'total_edge_tts' => 0,
            'total_geocoding' => 0, 'total_user_api' => 0, 'total_registration_api' => 0, 'total_date_parsing_api' => 0];
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
        $totals['total_api_calls_all'] = $totals['total_google_tts'] + $totals['total_google_stt'] + $totals['total_edge_tts'] +
            $totals['total_geocoding'] + $totals['total_user_api'] + $totals['total_registration_api'] + $totals['total_date_parsing_api'];

        return ['data' => $data, 'totals' => $totals];
    }

    private function getExportSummaryData() {
        $where = [];
        $params = [];

        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        if ($extWhere) { $where[] = $extWhere; $params = array_merge($params, $extParams); }

        if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
            $bounds = $this->dateHelper->convertToUTCBoundaries($_GET['date_from'] ?? null, $_GET['date_to'] ?? null);
            if ($bounds['utc_from']) { $where[] = "call_start_time >= ?"; $params[] = $bounds['utc_from']; }
            if ($bounds['utc_to']) { $where[] = "call_start_time <= ?"; $params[] = $bounds['utc_to']; }
        }

        if (!empty($_GET['phone'])) { $where[] = 'phone_number LIKE ?'; $params[] = '%' . $_GET['phone'] . '%'; }
        if (!empty($_GET['extension']) && !$this->globalExtensionFilter) { $where[] = 'extension = ?'; $params[] = $_GET['extension']; }
        if (!empty($_GET['outcome'])) { $where[] = 'call_outcome = ?'; $params[] = $_GET['outcome']; }

        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);

        $sql = "SELECT COUNT(*) as total_calls, COUNT(CASE WHEN call_outcome = 'success' THEN 1 END) as successful_calls,
                COUNT(CASE WHEN call_outcome = 'hangup' THEN 1 END) as hangup_calls,
                COUNT(CASE WHEN call_outcome = 'operator_transfer' THEN 1 END) as operator_transfers,
                COUNT(CASE WHEN is_reservation = 1 THEN 1 END) as reservation_calls,
                COUNT(CASE WHEN is_reservation = 0 OR is_reservation IS NULL THEN 1 END) as immediate_calls,
                COUNT(CASE WHEN call_outcome = 'success' AND is_reservation = 1 THEN 1 END) as successful_reservations,
                COUNT(CASE WHEN call_outcome = 'success' AND (is_reservation = 0 OR is_reservation IS NULL) THEN 1 END) as successful_immediate,
                AVG(call_duration) as avg_duration, MAX(call_duration) as max_duration, MIN(call_duration) as min_duration,
                COUNT(DISTINCT phone_number) as unique_callers, COUNT(DISTINCT extension) as extensions_used,
                SUM(google_tts_calls) as google_tts_total, SUM(edge_tts_calls) as edge_tts_total,
                SUM(google_stt_calls) as google_stt_total, SUM(geocoding_api_calls) as geocoding_total,
                SUM(user_api_calls) as user_api_total, SUM(registration_api_calls) as registration_total
                FROM {$this->table} WHERE {$whereClause}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch();
        $summary['success_rate'] = $summary['total_calls'] > 0 ? round(($summary['successful_calls'] / $summary['total_calls']) * 100, 2) : 0;
        $summary['total_api_calls'] = ($summary['google_tts_total'] ?? 0) + ($summary['edge_tts_total'] ?? 0) +
            ($summary['google_stt_total'] ?? 0) + ($summary['geocoding_total'] ?? 0) +
            ($summary['user_api_total'] ?? 0) + ($summary['registration_total'] ?? 0);
        return $summary;
    }

    private function getExportWhereClause() {
        $where = [];
        $params = [];

        list($extWhere, $extParams) = $this->getExtensionFilterClause();
        if ($extWhere) { $where[] = $extWhere; $params = array_merge($params, $extParams); }

        if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
            $bounds = $this->dateHelper->convertToUTCBoundaries($_GET['date_from'] ?? null, $_GET['date_to'] ?? null);
            if ($bounds['utc_from']) { $where[] = "call_start_time >= ?"; $params[] = $bounds['utc_from']; }
            if ($bounds['utc_to']) { $where[] = "call_start_time <= ?"; $params[] = $bounds['utc_to']; }
        }

        if (!empty($_GET['phone'])) { $where[] = 'phone_number LIKE ?'; $params[] = '%' . $_GET['phone'] . '%'; }
        if (!empty($_GET['extension']) && !$this->globalExtensionFilter) { $where[] = 'extension = ?'; $params[] = $_GET['extension']; }
        if (!empty($_GET['outcome'])) { $where[] = 'call_outcome = ?'; $params[] = $_GET['outcome']; }

        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        return [$whereClause, $params];
    }

    private function getExportCallsStatement() {
        list($whereClause, $params) = $this->getExportWhereClause();
        // Check if limit param exists - '0' means no limit
        $limit = isset($_GET['limit']) && $_GET['limit'] !== '' ? intval($_GET['limit']) : 1000;
        $limitClause = $limit > 0 ? "LIMIT {$limit}" : "";

        $sql = "SELECT id, phone_number, extension, call_outcome, call_duration, call_start_time, call_end_time,
                user_name, language_used, is_reservation, reservation_time,
                pickup_address, pickup_lat, pickup_lng, destination_address, destination_lat, destination_lng,
                google_tts_calls, edge_tts_calls, google_stt_calls, geocoding_api_calls, user_api_calls, registration_api_calls
                FROM {$this->table} WHERE {$whereClause} ORDER BY call_start_time DESC {$limitClause}";

        // Try to use unbuffered query for streaming large datasets (reduces memory)
        // This may not work on all MySQL configurations, so we handle it gracefully
        try {
            $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } catch (Exception $e) {
            // Unbuffered queries not supported, continue with buffered
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function formatCallRow($call) {
        $call['call_start_time'] = $this->dateHelper->utcToLocal($call['call_start_time']);
        if ($call['call_end_time']) $call['call_end_time'] = $this->dateHelper->utcToLocal($call['call_end_time']);
        if ($call['reservation_time']) $call['reservation_time'] = $this->dateHelper->utcToLocal($call['reservation_time']);
        return $call;
    }

    private function exportCSV() {
        if (ob_get_level()) ob_end_clean();
        $exportType = $_GET['export_type'] ?? 'summary';

        if ($exportType === 'calls') {
            $this->exportCallsCSVStreaming();
        } else {
            $this->exportSummaryCSV();
        }
    }

    private function exportCallsCSVStreaming() {
        try {
            // Increase limits for large exports
            set_time_limit(600);
            @ini_set('memory_limit', '512M');

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="call_data_' . date('Y-m-d_H-i-s') . '.csv"');
            header('Cache-Control: no-cache, must-revalidate');

            // Open output stream
            $output = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($output, "\xEF\xBB\xBF");

            // Header row
            fputcsv($output, ['ID', 'Phone Number', 'Extension', 'Outcome', 'Duration (s)', 'Start Time', 'End Time',
                'Customer Name', 'Language', 'Reservation', 'Reservation Time', 'Pickup Address', 'Pickup Lat',
                'Pickup Lng', 'Destination Address', 'Dest Lat', 'Dest Lng', 'TTS Calls', 'STT Calls',
                'Geocoding Calls', 'User API', 'Registration API']);

            // Stream data row by row
            $stmt = $this->getExportCallsStatement();
            $rowCount = 0;

            while ($call = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $call = $this->formatCallRow($call);

                fputcsv($output, [
                    $call['id'],
                    $call['phone_number'] ?? '',
                    $call['extension'] ?? '',
                    $call['call_outcome'] ?? '',
                    $call['call_duration'] ?? 0,
                    $call['call_start_time'] ?? '',
                    $call['call_end_time'] ?? '',
                    $call['user_name'] ?? '',
                    $call['language_used'] ?? '',
                    $call['is_reservation'] ? 'Yes' : 'No',
                    $call['reservation_time'] ?? '',
                    $call['pickup_address'] ?? '',
                    $call['pickup_lat'] ?? '',
                    $call['pickup_lng'] ?? '',
                    $call['destination_address'] ?? '',
                    $call['destination_lat'] ?? '',
                    $call['destination_lng'] ?? '',
                    ($call['google_tts_calls'] ?? 0) + ($call['edge_tts_calls'] ?? 0),
                    $call['google_stt_calls'] ?? 0,
                    $call['geocoding_api_calls'] ?? 0,
                    $call['user_api_calls'] ?? 0,
                    $call['registration_api_calls'] ?? 0
                ]);

                $rowCount++;

                // Flush output buffer periodically to prevent memory buildup
                if ($rowCount % 1000 === 0) {
                    flush();
                    if (ob_get_level()) ob_flush();
                }
            }

            fclose($output);
            $stmt->closeCursor();

            // Re-enable buffered queries
            try {
                $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            } catch (Exception $e) {}

        } catch (Exception $e) {
            error_log("CSV Export Error: " . $e->getMessage());
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
                header('Content-Type: text/plain');
            }
            echo "Export failed: " . $e->getMessage();
        }
        exit;
    }

    private function exportSummaryCSV() {
        $data = $this->getExportSummaryData();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analytics_summary_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";
        echo '"Call Analytics Summary Report - Timezone: Europe/Athens"' . "\r\n";
        echo '"Generated: ' . date('F d, Y H:i:s') . '"' . "\r\n\r\n";
        echo '"Call Statistics"' . "\r\n";
        echo '"Total Calls","' . number_format($data['total_calls']) . '"' . "\r\n";
        echo '"Successful Calls","' . number_format($data['successful_calls']) . '"' . "\r\n";
        echo '"Success Rate","' . number_format($data['success_rate'], 2) . '%"' . "\r\n";
        echo '"Hangup Calls","' . number_format($data['hangup_calls']) . '"' . "\r\n";
        echo '"Operator Transfers","' . number_format($data['operator_transfers']) . '"' . "\r\n\r\n";
        echo '"Booking Breakdown"' . "\r\n";
        echo '"Immediate/ASAP Calls (Total)","' . number_format($data['immediate_calls'] ?? 0) . '"' . "\r\n";
        echo '"Immediate/ASAP Calls (Successful)","' . number_format($data['successful_immediate'] ?? 0) . '"' . "\r\n";
        echo '"Reservation Calls (Total)","' . number_format($data['reservation_calls']) . '"' . "\r\n";
        echo '"Reservation Calls (Successful)","' . number_format($data['successful_reservations'] ?? 0) . '"' . "\r\n";
        $totalSuccessfulBookings = ($data['successful_immediate'] ?? 0) + ($data['successful_reservations'] ?? 0);
        echo '"TOTAL SUCCESSFUL BOOKINGS","' . number_format($totalSuccessfulBookings) . '"' . "\r\n";
        echo '"Average Duration","' . number_format($data['avg_duration'] ?? 0, 2) . ' seconds"' . "\r\n";
        echo '"Max Duration","' . number_format($data['max_duration'] ?? 0, 2) . ' seconds"' . "\r\n";
        echo '"Min Duration","' . number_format($data['min_duration'] ?? 0, 2) . ' seconds"' . "\r\n";
        echo '"Unique Callers","' . number_format($data['unique_callers']) . '"' . "\r\n";
        echo '"Extensions Used","' . number_format($data['extensions_used']) . '"' . "\r\n\r\n";
        echo '"API Usage Statistics"' . "\r\n";
        echo '"Google TTS Calls","' . number_format($data['google_tts_total'] ?? 0) . '"' . "\r\n";
        echo '"Edge TTS Calls","' . number_format($data['edge_tts_total'] ?? 0) . '"' . "\r\n";
        echo '"Google STT Calls","' . number_format($data['google_stt_total'] ?? 0) . '"' . "\r\n";
        echo '"Geocoding API Calls","' . number_format($data['geocoding_total'] ?? 0) . '"' . "\r\n";
        echo '"User API Calls","' . number_format($data['user_api_total'] ?? 0) . '"' . "\r\n";
        echo '"Registration API Calls","' . number_format($data['registration_total'] ?? 0) . '"' . "\r\n";
        echo '"TOTAL API CALLS","' . number_format($data['total_api_calls']) . '"' . "\r\n";
        exit;
    }

    private function exportXLSX() {
        if (ob_get_level()) ob_end_clean();
        $exportType = $_GET['export_type'] ?? 'summary';

        if ($exportType === 'calls') {
            $this->exportCallsXLSX();
        } else {
            $this->exportSummaryXLSX();
        }
    }

    private function exportCallsXLSX() {
        $tempFile = null;
        $sheetTempFile = null;

        try {
            // Increase limits for large exports
            set_time_limit(600);
            @ini_set('memory_limit', '512M');

            $filename = 'call_data_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Create temp files for chunked processing
            $tempDir = sys_get_temp_dir();
            $tempFile = $tempDir . '/' . uniqid('xlsx_') . '.xlsx';
            $sheetTempFile = $tempDir . '/' . uniqid('sheet_') . '.xml';

            $zip = new ZipArchive();
            if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                // Fallback to CSV if ZIP fails
                $_GET['format'] = 'csv';
                $this->exportCallsCSVStreaming();
                return;
            }

        // Add static XLSX structure files (2 sheets: Call Data + Summary)
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Call Data" sheetId="1" r:id="rId1"/><sheet name="Summary" sheetId="2" r:id="rId2"/></sheets>
</workbook>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF6366F1"/></patternFill></fill></fills>
<borders count="1"><border/></borders>
<cellStyleXfs count="1"><xf/></cellStyleXfs>
<cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf fontId="1" applyFont="1"/></cellXfs>
</styleSheet>');

        // Initialize statistics for summary sheet
        $stats = [
            'total' => 0, 'success' => 0, 'hangup' => 0, 'operator_transfer' => 0, 'error' => 0,
            'reservations' => 0, 'immediate' => 0,
            'successful_reservations' => 0, 'successful_immediate' => 0,
            'total_duration' => 0, 'min_duration' => PHP_INT_MAX,
            'max_duration' => 0, 'unique_phones' => [], 'unique_extensions' => [],
            'by_hour' => array_fill(0, 24, 0), 'by_day' => [],
            'total_tts' => 0, 'total_stt' => 0, 'total_geocoding' => 0
        ];

        // Write sheet data to temp file (streaming with inline strings)
        $sheetFile = fopen($sheetTempFile, 'w');
        fwrite($sheetFile, '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');

        // Header row using inline strings
        $headers = ['ID', 'Phone Number', 'Extension', 'Outcome', 'Duration (s)', 'Start Time', 'End Time',
            'Customer Name', 'Language', 'Reservation', 'Reservation Time', 'Pickup Address', 'Pickup Lat',
            'Pickup Lng', 'Destination Address', 'Dest Lat', 'Dest Lng', 'TTS Calls', 'STT Calls',
            'Geocoding Calls', 'User API', 'Registration API'];

        fwrite($sheetFile, '<row r="1">');
        $col = 'A';
        foreach ($headers as $h) {
            fwrite($sheetFile, '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . htmlspecialchars($h, ENT_XML1) . '</t></is></c>');
            $col++;
        }
        fwrite($sheetFile, '</row>');

        // Stream data rows using unbuffered query
        $stmt = $this->getExportCallsStatement();
        $rowNum = 2;
        $chunkCount = 0;

        while ($call = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Collect statistics BEFORE formatting (use raw UTC times for hour calculation)
            $stats['total']++;
            $outcome = strtolower($call['call_outcome'] ?? '');
            if ($outcome === 'success') $stats['success']++;
            elseif ($outcome === 'hangup') $stats['hangup']++;
            elseif ($outcome === 'operator_transfer') $stats['operator_transfer']++;
            elseif ($outcome === 'error' || $outcome === 'failed') $stats['error']++;

            if ($call['is_reservation']) {
                $stats['reservations']++;
                if ($outcome === 'success') $stats['successful_reservations']++;
            } else {
                $stats['immediate']++;
                if ($outcome === 'success') $stats['successful_immediate']++;
            }

            $duration = intval($call['call_duration'] ?? 0);
            $stats['total_duration'] += $duration;
            if ($duration > 0) {
                if ($duration < $stats['min_duration']) $stats['min_duration'] = $duration;
                if ($duration > $stats['max_duration']) $stats['max_duration'] = $duration;
            }

            if ($call['phone_number']) $stats['unique_phones'][$call['phone_number']] = true;
            if ($call['extension']) $stats['unique_extensions'][$call['extension']] = true;

            // Hour distribution (convert UTC to local for proper grouping)
            if ($call['call_start_time']) {
                $localTime = $this->dateHelper->utcToLocal($call['call_start_time']);
                $hour = intval(date('G', strtotime($localTime)));
                $stats['by_hour'][$hour]++;
                $day = date('Y-m-d', strtotime($localTime));
                $stats['by_day'][$day] = ($stats['by_day'][$day] ?? 0) + 1;
            }

            // API usage totals
            $stats['total_tts'] += intval($call['google_tts_calls'] ?? 0) + intval($call['edge_tts_calls'] ?? 0);
            $stats['total_stt'] += intval($call['google_stt_calls'] ?? 0);
            $stats['total_geocoding'] += intval($call['geocoding_api_calls'] ?? 0);
            $stats['total_user_api'] = ($stats['total_user_api'] ?? 0) + intval($call['user_api_calls'] ?? 0);
            $stats['total_reg_api'] = ($stats['total_reg_api'] ?? 0) + intval($call['registration_api_calls'] ?? 0);

            // Format row for display
            $call = $this->formatCallRow($call);

            $rowXml = '<row r="' . $rowNum . '">';

            // Helper function to write cell with inline string
            $writeInlineStr = function($col, $value) use ($rowNum) {
                $value = (string)$value;
                if ($value === '') return '<c r="' . $col . $rowNum . '"/>';
                return '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . htmlspecialchars($value, ENT_XML1) . '</t></is></c>';
            };

            $writeNum = function($col, $value) use ($rowNum) {
                $value = $value ?? '';
                if ($value === '') return '<c r="' . $col . $rowNum . '"/>';
                return '<c r="' . $col . $rowNum . '"><v>' . $value . '</v></c>';
            };

            // Build row
            $rowXml .= $writeNum('A', $call['id']);
            $rowXml .= $writeInlineStr('B', $call['phone_number'] ?? '');
            $rowXml .= $writeInlineStr('C', $call['extension'] ?? '');
            $rowXml .= $writeInlineStr('D', $call['call_outcome'] ?? '');
            $rowXml .= $writeNum('E', $call['call_duration'] ?? 0);
            $rowXml .= $writeInlineStr('F', $call['call_start_time'] ?? '');
            $rowXml .= $writeInlineStr('G', $call['call_end_time'] ?? '');
            $rowXml .= $writeInlineStr('H', $call['user_name'] ?? '');
            $rowXml .= $writeInlineStr('I', $call['language_used'] ?? '');
            $rowXml .= $writeInlineStr('J', $call['is_reservation'] ? 'Yes' : 'No');
            $rowXml .= $writeInlineStr('K', $call['reservation_time'] ?? '');
            $rowXml .= $writeInlineStr('L', $call['pickup_address'] ?? '');
            $rowXml .= $writeNum('M', $call['pickup_lat'] ?? '');
            $rowXml .= $writeNum('N', $call['pickup_lng'] ?? '');
            $rowXml .= $writeInlineStr('O', $call['destination_address'] ?? '');
            $rowXml .= $writeNum('P', $call['destination_lat'] ?? '');
            $rowXml .= $writeNum('Q', $call['destination_lng'] ?? '');
            $rowXml .= $writeNum('R', ($call['google_tts_calls'] ?? 0) + ($call['edge_tts_calls'] ?? 0));
            $rowXml .= $writeNum('S', $call['google_stt_calls'] ?? 0);
            $rowXml .= $writeNum('T', $call['geocoding_api_calls'] ?? 0);
            $rowXml .= $writeNum('U', $call['user_api_calls'] ?? 0);
            $rowXml .= $writeNum('V', $call['registration_api_calls'] ?? 0);

            $rowXml .= '</row>';
            fwrite($sheetFile, $rowXml);

            $rowNum++;
            $chunkCount++;

            // Periodically flush and free memory
            if ($chunkCount % 5000 === 0) {
                fflush($sheetFile);
            }
        }

        fwrite($sheetFile, '</sheetData></worksheet>');
        fclose($sheetFile);

            $stmt->closeCursor();
            try {
                $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            } catch (Exception $e) {}

            // Add data sheet to zip
            $zip->addFile($sheetTempFile, 'xl/worksheets/sheet1.xml');

            // Add export parameters to stats for summary sheet
            $stats['export_params'] = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'limit' => isset($_GET['limit']) && $_GET['limit'] !== '' ? intval($_GET['limit']) : 1000,
                'outcome' => $_GET['outcome'] ?? null
            ];

            // Generate Summary sheet (sheet2.xml) with collected statistics
            $summaryXml = $this->generateSummarySheetXml($stats);
            $zip->addFromString('xl/worksheets/sheet2.xml', $summaryXml);

            $zip->close();

            // Output the file
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Content-Length: ' . filesize($tempFile));

            readfile($tempFile);

        } catch (Exception $e) {
            error_log("XLSX Export Error: " . $e->getMessage());
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
                header('Content-Type: text/plain');
            }
            echo "Export failed: " . $e->getMessage();
        } finally {
            // Clean up temp files
            if ($tempFile) @unlink($tempFile);
            if ($sheetTempFile) @unlink($sheetTempFile);
        }
        exit;
    }

    private function generateSummarySheetXml($stats) {
        // Calculate derived statistics
        $avgDuration = $stats['total'] > 0 ? $stats['total_duration'] / $stats['total'] : 0;
        $successRate = $stats['total'] > 0 ? ($stats['success'] / $stats['total']) * 100 : 0;
        $uniquePhones = count($stats['unique_phones']);
        $uniqueExtensions = count($stats['unique_extensions']);

        // Fix min duration if no records
        if ($stats['min_duration'] === PHP_INT_MAX) $stats['min_duration'] = 0;

        // Find peak hour
        $peakHour = 0;
        $peakHourCount = 0;
        foreach ($stats['by_hour'] as $hour => $count) {
            if ($count > $peakHourCount) {
                $peakHour = $hour;
                $peakHourCount = $count;
            }
        }

        // Find busiest day
        $busiestDay = '';
        $busiestDayCount = 0;
        foreach ($stats['by_day'] as $day => $count) {
            if ($count > $busiestDayCount) {
                $busiestDay = $day;
                $busiestDayCount = $count;
            }
        }

        // Total API calls
        $totalApiCalls = $stats['total_tts'] + $stats['total_stt'] + $stats['total_geocoding'] +
                         ($stats['total_user_api'] ?? 0) + ($stats['total_reg_api'] ?? 0);

        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $row = 1;
        // Title
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="1"><is><t>Call Analytics Summary Report</t></is></c></row>';
        $row++;
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>Generated: ' . date('F d, Y H:i:s') . ' (Europe/Athens)</t></is></c></row>';
        $row++;

        // Export Parameters Section
        $exportParams = $stats['export_params'] ?? [];
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>Export Parameters:</t></is></c></row>';
        $row++;
        $dateFrom = $exportParams['date_from'] ?? 'Not specified';
        $dateTo = $exportParams['date_to'] ?? 'Not specified';
        $limit = $exportParams['limit'] ?? 1000;
        $limitText = $limit === 0 ? 'All records' : $limit . ' records';
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>  Date From: ' . htmlspecialchars($dateFrom) . '</t></is></c></row>';
        $row++;
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>  Date To: ' . htmlspecialchars($dateTo) . '</t></is></c></row>';
        $row++;
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>  Limit: ' . $limitText . '</t></is></c></row>';
        $row++;
        if (!empty($exportParams['outcome'])) {
            $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>  Outcome Filter: ' . htmlspecialchars($exportParams['outcome']) . '</t></is></c></row>';
            $row++;
        }
        $row++;

        // Overview Section
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>📊 Overview</t></is></c></row>';
        $row++;

        $overviewData = [
            ['Total Calls Exported', $stats['total']],
            ['Unique Phone Numbers', $uniquePhones],
            ['Unique Extensions', $uniqueExtensions],
            ['Date Range', count($stats['by_day']) . ' days'],
        ];
        foreach ($overviewData as $d) {
            $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($d[0]) . '</t></is></c>';
            $xml .= '<c r="B' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($d[1]) . '</t></is></c></row>';
            $row++;
        }
        $row++;

        // Call Outcomes Section
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>📞 Call Outcomes</t></is></c></row>';
        $row++;

        $outcomeData = [
            ['Successful Calls', $stats['success'], $stats['total'] > 0 ? number_format(($stats['success']/$stats['total'])*100, 1) . '%' : '0%'],
            ['Hangups', $stats['hangup'], $stats['total'] > 0 ? number_format(($stats['hangup']/$stats['total'])*100, 1) . '%' : '0%'],
            ['Operator Transfers', $stats['operator_transfer'], $stats['total'] > 0 ? number_format(($stats['operator_transfer']/$stats['total'])*100, 1) . '%' : '0%'],
            ['Errors/Failed', $stats['error'], $stats['total'] > 0 ? number_format(($stats['error']/$stats['total'])*100, 1) . '%' : '0%'],
        ];
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>Outcome</t></is></c>';
        $xml .= '<c r="B' . $row . '" t="inlineStr" s="2"><is><t>Count</t></is></c>';
        $xml .= '<c r="C' . $row . '" t="inlineStr" s="2"><is><t>Percentage</t></is></c></row>';
        $row++;
        foreach ($outcomeData as $d) {
            $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($d[0]) . '</t></is></c>';
            $xml .= '<c r="B' . $row . '"><v>' . $d[1] . '</v></c>';
            $xml .= '<c r="C' . $row . '" t="inlineStr"><is><t>' . $d[2] . '</t></is></c></row>';
            $row++;
        }
        $row++;

        // Booking Types Section
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>📅 Booking Types</t></is></c>';
        $xml .= '<c r="B' . $row . '" t="inlineStr" s="2"><is><t>Total</t></is></c>';
        $xml .= '<c r="C' . $row . '" t="inlineStr" s="2"><is><t>Successful</t></is></c></row>';
        $row++;

        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>Immediate/ASAP Bookings</t></is></c>';
        $xml .= '<c r="B' . $row . '"><v>' . $stats['immediate'] . '</v></c>';
        $xml .= '<c r="C' . $row . '"><v>' . $stats['successful_immediate'] . '</v></c></row>';
        $row++;
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>Reservations (Future)</t></is></c>';
        $xml .= '<c r="B' . $row . '"><v>' . $stats['reservations'] . '</v></c>';
        $xml .= '<c r="C' . $row . '"><v>' . $stats['successful_reservations'] . '</v></c></row>';
        $row++;
        $totalSuccessfulBookings = $stats['successful_immediate'] + $stats['successful_reservations'];
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>TOTAL SUCCESSFUL BOOKINGS</t></is></c>';
        $xml .= '<c r="B' . $row . '" s="2"><v>' . ($stats['immediate'] + $stats['reservations']) . '</v></c>';
        $xml .= '<c r="C' . $row . '" s="2"><v>' . $totalSuccessfulBookings . '</v></c></row>';
        $row += 2;

        // Duration Statistics Section
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>⏱️ Call Duration Statistics</t></is></c></row>';
        $row++;

        $durationData = [
            ['Total Call Time', gmdate('H:i:s', $stats['total_duration'])],
            ['Average Duration', number_format($avgDuration, 1) . ' seconds'],
            ['Minimum Duration', $stats['min_duration'] . ' seconds'],
            ['Maximum Duration', $stats['max_duration'] . ' seconds'],
        ];
        foreach ($durationData as $d) {
            $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($d[0]) . '</t></is></c>';
            $xml .= '<c r="B' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($d[1]) . '</t></is></c></row>';
            $row++;
        }
        $row++;

        // Peak Activity Section
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>📈 Peak Activity</t></is></c></row>';
        $row++;

        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>Peak Hour</t></is></c>';
        $xml .= '<c r="B' . $row . '" t="inlineStr"><is><t>' . sprintf('%02d:00 - %02d:59', $peakHour, $peakHour) . ' (' . $peakHourCount . ' calls)</t></is></c></row>';
        $row++;
        if ($busiestDay) {
            $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>Busiest Day</t></is></c>';
            $xml .= '<c r="B' . $row . '" t="inlineStr"><is><t>' . $busiestDay . ' (' . $busiestDayCount . ' calls)</t></is></c></row>';
            $row++;
        }
        $row++;

        // API Usage Section
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>🔌 API Usage</t></is></c></row>';
        $row++;

        $apiData = [
            ['Text-to-Speech (TTS) Calls', $stats['total_tts']],
            ['Speech-to-Text (STT) Calls', $stats['total_stt']],
            ['Geocoding API Calls', $stats['total_geocoding']],
            ['User API Calls', $stats['total_user_api'] ?? 0],
            ['Registration API Calls', $stats['total_reg_api'] ?? 0],
            ['TOTAL API CALLS', $totalApiCalls],
        ];
        foreach ($apiData as $d) {
            $style = strpos($d[0], 'TOTAL') !== false ? ' s="2"' : '';
            $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"' . $style . '><is><t>' . htmlspecialchars($d[0]) . '</t></is></c>';
            $xml .= '<c r="B' . $row . '"' . $style . '><v>' . $d[1] . '</v></c></row>';
            $row++;
        }
        $row++;

        // Hourly Distribution Section
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>🕐 Hourly Distribution</t></is></c></row>';
        $row++;
        $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr" s="2"><is><t>Hour</t></is></c>';
        $xml .= '<c r="B' . $row . '" t="inlineStr" s="2"><is><t>Calls</t></is></c>';
        $xml .= '<c r="C' . $row . '" t="inlineStr" s="2"><is><t>Bar</t></is></c></row>';
        $row++;

        $maxHourly = max($stats['by_hour']) ?: 1;
        for ($h = 0; $h < 24; $h++) {
            $count = $stats['by_hour'][$h];
            $barLength = $maxHourly > 0 ? round(($count / $maxHourly) * 20) : 0;
            $bar = str_repeat('█', $barLength);
            $xml .= '<row r="' . $row . '"><c r="A' . $row . '" t="inlineStr"><is><t>' . sprintf('%02d:00', $h) . '</t></is></c>';
            $xml .= '<c r="B' . $row . '"><v>' . $count . '</v></c>';
            $xml .= '<c r="C' . $row . '" t="inlineStr"><is><t>' . $bar . '</t></is></c></row>';
            $row++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function exportSummaryXLSX() {
        $data = $this->getExportSummaryData();
        $filename = 'analytics_summary_' . date('Y-m-d_H-i-s') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $zip = new ZipArchive();
        $tempFile = sys_get_temp_dir() . '/' . uniqid('xlsx_') . '.xlsx';

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $_GET['format'] = 'csv';
            $this->exportSummaryCSV();
            return;
        }

        // Standard XLSX structure files
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Summary" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF6366F1"/></patternFill></fill></fills>
<borders count="1"><border/></borders>
<cellStyleXfs count="1"><xf/></cellStyleXfs>
<cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf fontId="1" applyFont="1"/></cellXfs>
</styleSheet>');

        // Summary data as simple key-value pairs
        $sheetXml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $sheetXml .= '<row r="1"><c r="A1" t="inlineStr" s="1"><is><t>Call Analytics Summary Report</t></is></c></row>';
        $sheetXml .= '<row r="2"><c r="A2" t="inlineStr"><is><t>Generated: ' . date('F d, Y H:i:s') . ' (Europe/Athens)</t></is></c></row>';
        $sheetXml .= '<row r="4"><c r="A4" t="inlineStr" s="2"><is><t>Call Statistics</t></is></c></row>';

        $rows = [
            ['Total Calls', $data['total_calls']],
            ['Successful Calls', $data['successful_calls']],
            ['Success Rate', number_format($data['success_rate'], 2) . '%'],
            ['Hangup Calls', $data['hangup_calls']],
            ['Operator Transfers', $data['operator_transfers']],
            ['', ''],
            ['--- BOOKING BREAKDOWN ---', ''],
            ['Immediate/ASAP Calls (Total)', $data['immediate_calls']],
            ['Immediate/ASAP Calls (Successful)', $data['successful_immediate']],
            ['Reservation Calls (Total)', $data['reservation_calls']],
            ['Reservation Calls (Successful)', $data['successful_reservations']],
            ['TOTAL SUCCESSFUL BOOKINGS', ($data['successful_immediate'] ?? 0) + ($data['successful_reservations'] ?? 0)],
            ['', ''],
            ['Average Duration', number_format($data['avg_duration'] ?? 0, 2) . ' sec'],
            ['Max Duration', number_format($data['max_duration'] ?? 0, 2) . ' sec'],
            ['Min Duration', number_format($data['min_duration'] ?? 0, 2) . ' sec'],
            ['Unique Callers', $data['unique_callers']],
            ['Extensions Used', $data['extensions_used']],
        ];

        $rowNum = 5;
        foreach ($rows as $r) {
            $sheetXml .= '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="inlineStr"><is><t>' . htmlspecialchars($r[0]) . '</t></is></c>';
            $sheetXml .= '<c r="B' . $rowNum . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$r[1]) . '</t></is></c></row>';
            $rowNum++;
        }

        $rowNum++;
        $sheetXml .= '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="inlineStr" s="2"><is><t>API Usage Statistics</t></is></c></row>';
        $rowNum++;

        $apiRows = [
            ['Google TTS Calls', $data['google_tts_total'] ?? 0],
            ['Edge TTS Calls', $data['edge_tts_total'] ?? 0],
            ['Google STT Calls', $data['google_stt_total'] ?? 0],
            ['Geocoding API Calls', $data['geocoding_total'] ?? 0],
            ['User API Calls', $data['user_api_total'] ?? 0],
            ['Registration API Calls', $data['registration_total'] ?? 0],
            ['TOTAL API CALLS', $data['total_api_calls']],
        ];

        foreach ($apiRows as $r) {
            $sheetXml .= '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="inlineStr"><is><t>' . htmlspecialchars($r[0]) . '</t></is></c>';
            $sheetXml .= '<c r="B' . $rowNum . '"><v>' . $r[1] . '</v></c></row>';
            $rowNum++;
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }

    private function exportPDF() {
        $data = $this->getExportSummaryData();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Call Analytics Report</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:20px;} .header{text-align:center;border-bottom:3px solid #6366f1;padding-bottom:20px;margin-bottom:30px;}';
        echo '.header h1{color:#6366f1;margin:0;font-size:28px;} .stats{display:flex;gap:20px;justify-content:center;flex-wrap:wrap;margin:20px 0;}';
        echo '.stat-card{background:linear-gradient(135deg,#f8fafc 0%,#e2e8f0 100%);padding:15px;border-radius:8px;text-align:center;min-width:120px;border:1px solid #e5e7eb;}';
        echo '.stat-number{font-size:24px;font-weight:bold;color:#6366f1;} .stat-label{color:#666;font-size:12px;text-transform:uppercase;margin-top:5px;font-weight:600;}';
        echo '.table{width:100%;border-collapse:collapse;margin-top:20px;} .table th,.table td{border:1px solid #e5e7eb;padding:10px;text-align:left;}';
        echo '.table th{background:#f8fafc;font-weight:600;} @media print{body{margin:0;padding:20px;}.no-print{display:none!important;}}';
        echo '.download-info{background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);color:white;padding:20px;border-radius:12px;text-align:center;margin-bottom:30px;}';
        echo '.download-info button{background:white;color:#6366f1;border:none;padding:10px 20px;border-radius:6px;font-weight:600;cursor:pointer;}</style></head>';
        echo '<body><div class="download-info no-print"><h3>PDF Report Ready</h3><p>Use Ctrl+P (or Cmd+P) to print or save as PDF</p><button onclick="window.print()">Print / Save PDF</button></div>';
        echo '<div class="header"><h1>Call Analytics Report</h1><p>Generated: ' . date('F d, Y H:i:s') . ' (Europe/Athens)</p></div>';
        echo '<div class="stats">';
        echo '<div class="stat-card"><div class="stat-number">' . number_format($data['total_calls']) . '</div><div class="stat-label">Total Calls</div></div>';
        echo '<div class="stat-card"><div class="stat-number">' . number_format($data['successful_calls']) . '</div><div class="stat-label">Successful</div></div>';
        echo '<div class="stat-card"><div class="stat-number">' . number_format($data['success_rate'], 1) . '%</div><div class="stat-label">Success Rate</div></div>';
        echo '<div class="stat-card"><div class="stat-number">' . number_format($data['unique_callers']) . '</div><div class="stat-label">Unique Callers</div></div>';
        echo '</div>';
        echo '<table class="table"><tr><th colspan="2">Call Statistics</th></tr>';
        echo '<tr><td>Hangup Calls</td><td>' . number_format($data['hangup_calls']) . '</td></tr>';
        echo '<tr><td>Operator Transfers</td><td>' . number_format($data['operator_transfers']) . '</td></tr>';
        echo '<tr><td>Average Duration</td><td>' . number_format($data['avg_duration'] ?? 0, 2) . ' seconds</td></tr>';
        echo '</table>';
        $totalSuccessfulBookings = ($data['successful_immediate'] ?? 0) + ($data['successful_reservations'] ?? 0);
        echo '<table class="table"><tr><th colspan="2">Booking Breakdown</th></tr>';
        echo '<tr><td>Immediate/ASAP Calls (Total)</td><td>' . number_format($data['immediate_calls'] ?? 0) . '</td></tr>';
        echo '<tr><td>Immediate/ASAP Calls (Successful)</td><td>' . number_format($data['successful_immediate'] ?? 0) . '</td></tr>';
        echo '<tr><td>Reservation Calls (Total)</td><td>' . number_format($data['reservation_calls']) . '</td></tr>';
        echo '<tr><td>Reservation Calls (Successful)</td><td>' . number_format($data['successful_reservations'] ?? 0) . '</td></tr>';
        echo '<tr style="background:#e0e7ff;font-weight:bold;"><td>TOTAL SUCCESSFUL BOOKINGS</td><td>' . number_format($totalSuccessfulBookings) . '</td></tr>';
        echo '</table>';
        echo '<table class="table"><tr><th colspan="2">API Usage</th></tr>';
        echo '<tr><td>Google TTS</td><td>' . number_format($data['google_tts_total'] ?? 0) . '</td></tr>';
        echo '<tr><td>Edge TTS</td><td>' . number_format($data['edge_tts_total'] ?? 0) . '</td></tr>';
        echo '<tr><td>Google STT</td><td>' . number_format($data['google_stt_total'] ?? 0) . '</td></tr>';
        echo '<tr><td>Geocoding</td><td>' . number_format($data['geocoding_total'] ?? 0) . '</td></tr>';
        echo '<tr><td>Registration</td><td>' . number_format($data['registration_total'] ?? 0) . '</td></tr>';
        echo '<tr><th>Total API Calls</th><th>' . number_format($data['total_api_calls']) . '</th></tr>';
        echo '</table></body></html>';
        exit;
    }

    private function exportPrint() {
        $this->exportPDF();
    }

    // ========================================================================
    // DASHBOARD RENDERING
    // ========================================================================

    private function renderDashboard() {
        $lang = $this->language;
        ?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $this->t('dashboard_title'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/gr.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        window.heatPluginReady = false;
        document.addEventListener('DOMContentLoaded', function() {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet.heat/dist/leaflet-heat.js';
            script.onload = function() { window.heatPluginReady = true; if (window.pendingHeatmapData) { renderLocationHeatmap(window.pendingHeatmapData); window.pendingHeatmapData = null; } };
            document.head.appendChild(script);
        });
    </script>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --success: #10b981;
            --success-light: #34d399;
            --warning: #f59e0b;
            --warning-light: #fbbf24;
            --danger: #ef4444;
            --danger-light: #f87171;
            --info: #06b6d4;
            --info-light: #22d3ee;

            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f5f9;
            --bg-card: #ffffff;
            --bg-glass: rgba(0, 0, 0, 0.02);

            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #94a3b8;

            --border-color: #e2e8f0;
            --border-light: #cbd5e1;

            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-glow: 0 0 40px rgba(99, 102, 241, 0.1);

            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;

            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --header-height: 65px;

            --transition-fast: 150ms ease;
            --transition-normal: 250ms ease;
            --transition-slow: 350ms ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================
           LAYOUT - HEADER ONLY (NO SIDEBAR)
           ============================================ */
        .app-layout {
            min-height: 100vh;
        }

        .main-content {
            min-height: 100vh;
        }

        .top-header {
            height: var(--header-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .header-title h1 {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
        }

        .header-title p {
            display: none;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: nowrap;
        }

        .header-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition-fast);
            white-space: nowrap;
        }

        .header-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--border-light);
        }

        .header-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .header-btn i {
            font-size: 0.875rem;
        }

        .header-btn-text {
            display: inline;
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: var(--radius-lg);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--success);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .live-indicator:hover {
            background: rgba(16, 185, 129, 0.15);
        }

        .live-indicator.offline {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .live-indicator.offline:hover {
            background: rgba(239, 68, 68, 0.15);
        }

        .live-indicator.offline .live-dot {
            background: var(--danger);
            animation: none;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            animation: livePulse 2s ease-in-out infinite;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        .lang-switcher {
            display: flex;
            background: var(--bg-tertiary);
            border-radius: var(--radius-lg);
            padding: 0.125rem;
            border: 1px solid var(--border-color);
        }

        .lang-btn {
            padding: 0.375rem 0.625rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .lang-btn:hover {
            color: var(--text-primary);
        }

        .lang-btn.active {
            background: var(--primary);
            color: white;
        }

        .header-divider {
            width: 1px;
            height: 24px;
            background: var(--border-color);
            margin: 0 0.25rem;
        }

        /* Mobile drawer for header controls */
        .mobile-menu-btn {
            display: none;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            cursor: pointer;
            flex-shrink: 0;
        }

        .mobile-menu-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .mobile-drawer {
            display: none;
            position: fixed;
            top: var(--header-height);
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            z-index: 99;
            box-shadow: var(--shadow-lg);
            transform: translateY(-100%);
            opacity: 0;
            transition: all var(--transition-normal);
        }

        .mobile-drawer.open {
            transform: translateY(0);
            opacity: 1;
        }

        .mobile-drawer-content {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .mobile-drawer-row {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .mobile-drawer .header-btn {
            flex: 1;
            min-width: 100px;
            justify-content: center;
        }

        .mobile-overlay {
            display: none;
            position: fixed;
            top: var(--header-height);
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 98;
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-normal);
        }

        .mobile-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .content-wrapper {
            padding: 1.25rem 1.5rem 1.5rem;
            max-width: 1800px;
            margin: 0 auto;
        }

        /* ============================================
           BUTTONS
           ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius-lg);
            border: none;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--bg-card);
            border-color: var(--primary);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
        }

        .btn-ghost:hover {
            background: var(--bg-glass);
            color: var(--text-primary);
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: var(--radius-lg);
        }

        /* ============================================
           STATS CARDS
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        /* Compact Stats Grid */
        .stats-compact {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .period-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 0.75rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .period-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .period-card.today::before { background: linear-gradient(90deg, #3b82f6, #8b5cf6); }
        .period-card.weekly::before { background: linear-gradient(90deg, #10b981, #059669); }
        .period-card.monthly::before { background: linear-gradient(90deg, #f59e0b, #d97706); }

        .period-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .period-header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .period-title {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .period-title i { font-size: 0.85rem; }
        .period-card.today .period-title i { color: #3b82f6; }
        .period-card.weekly .period-title i { color: #10b981; }
        .period-card.monthly .period-title i { color: #f59e0b; }

        .period-summary {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .period-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .period-rate {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.15rem 0.4rem;
            border-radius: var(--radius-sm);
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .period-header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .period-toggle {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            background: var(--bg-tertiary);
            color: var(--text-muted);
            transition: all 0.2s ease;
            font-size: 0.7rem;
        }

        .period-toggle:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .period-card.expanded .period-toggle {
            transform: rotate(180deg);
        }

        /* Collapsed state - hide details */
        .period-details {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: all 0.3s ease;
            margin-top: 0;
        }

        .period-card.expanded .period-details {
            max-height: 200px;
            opacity: 1;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }

        .period-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.4rem;
        }

        .period-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.4rem 0.25rem;
            background: var(--bg-tertiary);
            border-radius: var(--radius-sm);
        }

        .period-stat-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .period-stat-label {
            font-size: 0.6rem;
            color: var(--text-muted);
            text-align: center;
        }

        .period-stat.success .period-stat-value { color: var(--success); }
        .period-stat.danger .period-stat-value { color: var(--danger); }
        .period-stat.warning .period-stat-value { color: var(--warning); }
        .period-stat.info .period-stat-value { color: var(--info); }

        /* Active calls badge */
        .active-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.15rem 0.4rem;
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border-radius: var(--radius-sm);
            font-size: 0.65rem;
            font-weight: 600;
        }

        .active-badge i {
            font-size: 0.5rem;
            animation: pulse 1.5s infinite;
        }

        @media (max-width: 900px) {
            .stats-compact {
                grid-template-columns: 1fr;
            }
            .period-stats {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 500px) {
            .period-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Legacy stat-card for skeleton loading */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-footer {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* ============================================
           CARDS & PANELS
           ============================================ */
        .card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--primary-light);
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            background: var(--bg-glass);
        }

        /* ============================================
           DASHBOARD GRID
           ============================================ */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-container {
            height: 280px;
            position: relative;
        }

        /* ============================================
           HEATMAP
           ============================================ */
        .heatmap-wrapper {
            height: 300px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            position: relative;
            background: var(--bg-tertiary);
        }

        .heatmap-controls {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .heatmap-select {
            flex: 1;
            min-width: 150px;
        }

        .heatmap-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .heatmap-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .heatmap-stat i {
            font-size: 1rem;
        }

        .heatmap-stat.pickup i { color: var(--success); }
        .heatmap-stat.dest i { color: var(--danger); }

        .heatmap-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--bg-tertiary);
        }

        /* ============================================
           TABLE
           ============================================ */
        .table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        .data-table th {
            text-align: left;
            padding: 1rem 1.25rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background: var(--bg-glass);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .data-table td {
            padding: 1rem 1.25rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: background var(--transition-fast);
            cursor: pointer;
        }

        .data-table tbody tr:hover {
            background: var(--bg-glass);
        }

        .data-table tbody tr:hover td {
            color: var(--text-primary);
        }

        .cell-phone {
            font-family: 'Monaco', 'Consolas', monospace;
            font-weight: 600;
            color: var(--text-primary);
        }

        .cell-location {
            max-width: 200px;
        }

        .location-line {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        .location-line:last-child {
            margin-bottom: 0;
        }

        .location-line i {
            width: 14px;
            font-size: 0.7rem;
        }

        .location-line.pickup i { color: var(--success); }
        .location-line.dest i { color: var(--danger); }

        .cell-apis {
            white-space: nowrap;
        }

        .cell-apis .api-badge {
            margin-right: 0.35rem;
        }

        .cell-apis .api-badge:last-child {
            margin-right: 0;
        }


        .api-badge {
            display: inline-flex;
            align-items: center;
            font-size: 0.65rem;
            padding: 0.25rem 0.4rem;
            border-radius: var(--radius-sm);
            background: var(--bg-tertiary);
            color: var(--text-muted);
            font-weight: 600;
            font-family: 'Roboto Mono', monospace;
            white-space: nowrap;
            line-height: 1;
        }

        /* ============================================
           BADGES
           ============================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: var(--radius-2xl);
            white-space: nowrap;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success-light);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger-light);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning-light);
        }

        .badge-info {
            background: rgba(6, 182, 212, 0.15);
            color: var(--info-light);
        }

        .badge-purple {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
        }

        .badge i {
            font-size: 0.625rem;
        }

        /* ============================================
           PAGINATION
           ============================================ */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .pagination-info {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .pagination {
            display: flex;
            gap: 0.375rem;
        }

        .pagination button {
            min-width: 36px;
            height: 36px;
            padding: 0 0.75rem;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .pagination button:hover:not(:disabled) {
            background: var(--bg-glass);
            border-color: var(--border-light);
            color: var(--text-primary);
        }

        .pagination button.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ============================================
           FORM ELEMENTS
           ============================================ */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            transition: all var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .form-section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section-title i {
            color: var(--primary-light);
        }

        /* ============================================
           MODALS
           ============================================ */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }

        .modal-container {
            position: relative;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-2xl);
            max-width: 95vw;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            animation: modalEnter 0.2s ease-out;
        }

        @keyframes modalEnter {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-10px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-title i {
            color: var(--primary-light);
        }

        .modal-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
        }

        .modal-close:hover {
            background: var(--bg-glass);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            background: var(--bg-glass);
        }

        /* ============================================
           CALL DETAIL
           ============================================ */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Compact Call Detail View */
        .cd-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }

        .cd-phone {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .cd-meta {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
            align-items: center;
        }

        .cd-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .cd-item {
            background: var(--bg-tertiary);
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .cd-item-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.2rem;
        }

        .cd-item-value {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .cd-locations {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .cd-loc {
            background: var(--bg-tertiary);
            padding: 0.6rem 0.75rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .cd-loc-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }

        .cd-loc-title i { margin-right: 0.3rem; }
        .cd-loc-title.pickup i { color: var(--success); }
        .cd-loc-title.dest i { color: var(--danger); }

        .cd-loc-addr {
            font-size: 0.8rem;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .cd-section {
            margin-bottom: 0.75rem;
        }

        .cd-section-title {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .cd-api-badges {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .cd-api-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            font-weight: 500;
        }

        .cd-map {
            height: 180px;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .cd-recordings {
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .cd-rec-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
            background: var(--bg-secondary);
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .cd-rec-item:last-child { border-bottom: none; margin-bottom: 0; }

        .cd-rec-label {
            color: var(--text-primary);
            font-weight: 500;
            display: block;
            margin-bottom: 0.25rem;
        }

        .cd-rec-desc {
            color: var(--text-secondary);
            font-style: italic;
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .cd-rec-audio {
            width: 100%;
            height: 32px;
            display: block;
        }

        .cd-log {
            background: var(--gray-900);
            color: var(--gray-300);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-family: 'Roboto Mono', monospace;
            font-size: 0.7rem;
            max-height: 150px;
            overflow-y: auto;
            line-height: 1.5;
        }

        .cd-log-entry { margin-bottom: 0.15rem; }
        .cd-log-time { color: var(--gray-500); }
        .cd-log-api { color: #3b82f6; }
        .cd-log-error { color: #ef4444; }
        .cd-log-user { color: #8b5cf6; }
        .cd-log-tts { color: #10b981; }

        @media (max-width: 640px) {
            .cd-grid { grid-template-columns: repeat(2, 1fr); }
            .cd-locations { grid-template-columns: 1fr; }
        }

        .location-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 600px) {
            .location-cards {
                grid-template-columns: 1fr;
            }
        }

        .location-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
        }

        .location-card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .location-card.pickup .location-card-header { color: var(--success); }
        .location-card.destination .location-card-header { color: var(--danger); }

        .location-card-address {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .location-card-coords {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-family: monospace;
        }

        .map-container {
            height: 280px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        /* ============================================
           RECORDINGS
           ============================================ */
        .recordings-section {
            margin-top: 1.5rem;
        }

        .recordings-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .recordings-title i {
            color: var(--primary-light);
        }

        .recording-item {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all var(--transition-fast);
        }

        .recording-item:hover {
            border-color: var(--border-light);
        }

        .recording-header {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .recording-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            background: var(--bg-glass);
            color: var(--primary-light);
            flex-shrink: 0;
        }

        .recording-icon.user { color: var(--info); background: rgba(6, 182, 212, 0.1); }
        .recording-icon.system { color: var(--success); background: rgba(16, 185, 129, 0.1); }

        .recording-info {
            flex: 1;
            min-width: 0;
        }

        .recording-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .recording-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .recording-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .recording-player {
            width: 100%;
            height: 36px;
            border-radius: var(--radius-md);
        }

        /* ============================================
           EXPORT MODAL - ENHANCED
           ============================================ */
        .export-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            background: var(--bg-tertiary);
            border-radius: var(--radius-lg);
            padding: 0.25rem;
        }

        .export-tab {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .export-tab:hover {
            color: var(--text-primary);
        }

        .export-tab.active {
            background: var(--bg-secondary);
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .export-type-info {
            margin-bottom: 1.25rem;
        }

        .export-type-desc {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: rgba(99, 102, 241, 0.05);
            border: 1px solid rgba(99, 102, 241, 0.1);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .export-type-desc i {
            color: var(--primary);
        }

        .export-section {
            margin-bottom: 1.25rem;
        }

        .export-section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .export-section-title i {
            color: var(--primary);
            font-size: 0.875rem;
        }

        .export-filters-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .export-format-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .export-format-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all var(--transition-fast);
            text-align: center;
        }

        .export-format-option:hover {
            border-color: var(--border-light);
            background: var(--bg-glass);
        }

        .export-format-option.active {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .export-format-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: var(--bg-secondary);
        }

        .export-format-icon.xlsx { color: #217346; }
        .export-format-icon.csv { color: var(--success); }
        .export-format-icon.pdf { color: var(--danger); }
        .export-format-icon.print { color: var(--info); }

        .export-format-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .export-format-desc {
            font-size: 0.7rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        @media (max-width: 480px) {
            .export-filters-grid,
            .export-format-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================
           LOADING STATES
           ============================================ */
        .loading-spinner {
            width: 36px;
            height: 36px;
            border: 3px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .loading-spinner.sm {
            width: 20px;
            height: 20px;
            border-width: 2px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .skeleton {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: skeleton 1.5s ease-in-out infinite;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }

        @keyframes skeleton {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .skeleton-text {
            height: 1rem;
            border-radius: var(--radius-sm);
        }

        .skeleton-title {
            height: 1.5rem;
            width: 60%;
            border-radius: var(--radius-sm);
        }

        .skeleton-chart {
            height: 250px;
        }

        .skeleton-row {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .skeleton-cell {
            height: 1rem;
            flex: 1;
            border-radius: var(--radius-sm);
        }

        .loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: inherit;
        }

        /* ============================================
           UTILITIES
           ============================================ */
        .text-success { color: var(--success-light); }
        .text-danger { color: var(--danger-light); }
        .text-warning { color: var(--warning-light); }
        .text-info { color: var(--info-light); }
        .text-muted { color: var(--text-muted); }
        .text-primary { color: var(--primary-light); }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .header-controls {
                display: none;
            }

            .mobile-menu-btn {
                display: flex;
            }

            .mobile-drawer {
                display: block;
            }

            .mobile-overlay {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-footer {
                display: none;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .card-header select {
                width: 100%;
            }

            .heatmap-controls {
                flex-direction: column;
            }

            .heatmap-select {
                width: 100%;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem;
                font-size: 0.8rem;
            }

            .cell-location,
            .cell-apis {
                display: none;
            }

            .pagination-wrapper {
                flex-direction: column;
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .top-header {
                padding: 0 1rem;
            }

            .header-title h1 {
                font-size: 1rem;
            }

            .modal-container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0;
                border-radius: var(--radius-xl) var(--radius-xl) 0 0;
                max-height: 95vh;
            }

            .modal-body {
                max-height: calc(95vh - 140px);
            }

            .detail-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .location-cards {
                grid-template-columns: 1fr;
            }
        }

        /* Hide scrollbar but allow scrolling */
        .modal-body::-webkit-scrollbar,
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track,
        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .modal-body::-webkit-scrollbar-thumb,
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover,
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: var(--border-light);
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h1><?php echo $this->t('analytics_dashboard'); ?></h1>
                    </div>
                </div>

                <div class="header-controls">
                    <button class="header-btn" id="filterToggle">
                        <i class="fas fa-filter"></i>
                        <span class="header-btn-text"><?php echo $this->t('filters'); ?></span>
                    </button>
                    <button class="header-btn" id="exportBtn">
                        <i class="fas fa-download"></i>
                        <span class="header-btn-text"><?php echo $this->t('export'); ?></span>
                    </button>
                    <button class="header-btn" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i>
                        <span class="header-btn-text"><?php echo $this->t('refresh'); ?></span>
                    </button>
                    <div class="header-divider"></div>
                    <div class="live-indicator" id="realtimeBtn" title="<?php echo $lang === 'el' ? 'Κλικ για εναλλαγή' : 'Click to toggle'; ?>">
                        <span class="live-dot"></span>
                        <span><?php echo $this->t('live'); ?></span>
                    </div>
                    <div class="header-divider"></div>
                    <div class="lang-switcher">
                        <button class="lang-btn <?php echo $lang === 'el' ? 'active' : ''; ?>" onclick="switchLanguage('el')">EL</button>
                        <button class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>" onclick="switchLanguage('en')">EN</button>
                    </div>
                </div>
            </header>

            <!-- Mobile Drawer -->
            <div class="mobile-drawer" id="mobileDrawer">
                <div class="mobile-drawer-content">
                    <div class="mobile-drawer-row">
                        <button class="header-btn" id="mobileFilterToggle">
                            <i class="fas fa-filter"></i>
                            <span><?php echo $this->t('filters'); ?></span>
                        </button>
                        <button class="header-btn" id="mobileExportBtn">
                            <i class="fas fa-download"></i>
                            <span><?php echo $this->t('export'); ?></span>
                        </button>
                    </div>
                    <div class="mobile-drawer-row">
                        <button class="header-btn" id="mobileRefreshBtn">
                            <i class="fas fa-sync-alt"></i>
                            <span><?php echo $this->t('refresh'); ?></span>
                        </button>
                        <div class="live-indicator" id="mobileRealtimeBtn" style="flex:1;justify-content:center;">
                            <span class="live-dot"></span>
                            <span><?php echo $this->t('live'); ?></span>
                        </div>
                    </div>
                    <div class="mobile-drawer-row">
                        <div class="lang-switcher" style="flex:1;">
                            <button class="lang-btn <?php echo $lang === 'el' ? 'active' : ''; ?>" onclick="switchLanguage('el')" style="flex:1;">EL</button>
                            <button class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>" onclick="switchLanguage('en')" style="flex:1;">EN</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-wrapper">
                <!-- Stats Grid - Compact -->
                <div class="stats-compact" id="statsGrid">
                    <div class="stat-card skeleton" style="height:52px;"></div>
                    <div class="stat-card skeleton" style="height:52px;"></div>
                    <div class="stat-card skeleton" style="height:52px;"></div>
                </div>

                <!-- Charts Grid -->
                <div class="dashboard-grid">
                    <!-- Hourly Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-area"></i> <?php echo $this->t('calls_per_hour'); ?></h3>
                            <select id="hourlyDateSelect" class="form-control" style="width:auto;min-width:140px;padding:0.5rem;"></select>
                        </div>
                        <div class="card-body">
                            <div class="chart-container"><canvas id="hourlyChart"></canvas></div>
                        </div>
                    </div>

                    <!-- Location Heatmap -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-map-marked-alt"></i> <?php echo $this->t('location_heatmap'); ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="heatmap-controls">
                                <select id="heatmapDuration" class="form-control heatmap-select">
                                    <option value="30"><?php echo $this->t('last_30_minutes'); ?></option>
                                    <option value="60"><?php echo $this->t('last_1_hour'); ?></option>
                                    <option value="180"><?php echo $this->t('last_3_hours'); ?></option>
                                    <option value="360"><?php echo $this->t('last_6_hours'); ?></option>
                                    <option value="720"><?php echo $this->t('last_12_hours'); ?></option>
                                    <option value="1440"><?php echo $this->t('last_24_hours'); ?></option>
                                </select>
                                <select id="heatmapMode" class="form-control heatmap-select">
                                    <option value="heatmap">🔥 Heatmap</option>
                                    <option value="clusters">📍 Clusters</option>
                                    <option value="markers">🎯 Markers</option>
                                </select>
                            </div>
                            <div class="heatmap-stats">
                                <div class="heatmap-stat pickup">
                                    <i class="fas fa-map-pin"></i>
                                    <span><strong id="pickupCount">0</strong> <?php echo $this->t('pickups'); ?></span>
                                </div>
                                <div class="heatmap-stat dest">
                                    <i class="fas fa-flag-checkered"></i>
                                    <span><strong id="destinationCount">0</strong> <?php echo $this->t('destinations'); ?></span>
                                </div>
                            </div>
                            <div id="heatmapContainer" class="heatmap-wrapper">
                                <div id="heatmapLoading" class="heatmap-loading">
                                    <div class="loading-spinner"></div>
                                    <p style="margin-top:1rem;color:var(--text-muted);"><?php echo $this->t('loading_location_data'); ?></p>
                                </div>
                                <div id="heatmapEmpty" style="display:none;position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                                    <i class="fas fa-map-marker-alt" style="font-size:3rem;color:var(--text-muted);margin-bottom:1rem;"></i>
                                    <p style="color:var(--text-muted);"><?php echo $this->t('no_location_data'); ?></p>
                                </div>
                                <div id="locationHeatmap" style="height:100%;opacity:0;transition:opacity 0.3s ease;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calls Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-phone-alt"></i> <?php echo $this->t('recent_calls'); ?></h3>
                        <select id="limitSelect" class="form-control" style="width:auto;min-width:140px;padding:0.5rem;">
                            <option value="25"><?php echo $this->t('25_per_page'); ?></option>
                            <option value="50" selected><?php echo $this->t('50_per_page'); ?></option>
                            <option value="100"><?php echo $this->t('100_per_page'); ?></option>
                        </select>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table" id="callsTable">
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="callsTableBody"></tbody>
                        </table>
                    </div>
                    <div class="pagination-wrapper">
                        <div class="pagination-info" id="paginationInfo"></div>
                        <div class="pagination" id="pagination"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Filter Modal -->
    <div class="modal" id="filterModal">
        <div class="modal-backdrop" onclick="closeFilterModal()"></div>
        <div class="modal-container" style="width:600px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-filter"></i> <?php echo $this->t('advanced_filters'); ?></h3>
                <button class="modal-close" onclick="closeFilterModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo $this->t('phone_number'); ?></label>
                        <input type="text" id="filterPhone" class="form-control" placeholder="<?php echo $this->t('placeholder_phone'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $this->t('extension'); ?></label>
                        <input type="text" id="filterExtension" class="form-control" placeholder="<?php echo $this->t('placeholder_extension'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo $this->t('call_type'); ?></label>
                        <select id="filterCallType" class="form-control">
                            <option value=""><?php echo $this->t('all_types'); ?></option>
                            <option value="immediate"><?php echo $this->t('immediate'); ?></option>
                            <option value="reservation"><?php echo $this->t('reservation'); ?></option>
                            <option value="operator"><?php echo $this->t('operator'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $this->t('outcome'); ?></label>
                        <select id="filterOutcome" class="form-control">
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
                        <input type="text" id="filterDateFrom" class="form-control flatpickr" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $this->t('date_to'); ?></label>
                        <input type="text" id="filterDateTo" class="form-control flatpickr" placeholder="YYYY-MM-DD">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="clearFilters()"><?php echo $this->t('clear_all'); ?></button>
                <button class="btn btn-primary" onclick="applyFilters()"><?php echo $this->t('apply_filters'); ?></button>
            </div>
        </div>
    </div>

    <!-- Call Detail Modal -->
    <div class="modal" id="callDetailModal">
        <div class="modal-backdrop" onclick="closeModal()"></div>
        <div class="modal-container" style="width:90vw;max-width:1000px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-phone-alt"></i> <?php echo $this->t('call_details'); ?></h3>
                <div class="modal-actions">
                    <button class="btn btn-sm btn-ghost" onclick="refreshCallDetail()" title="<?php echo $this->t('refresh'); ?>"><i class="fas fa-sync-alt"></i></button>
                    <button class="btn btn-sm btn-ghost" onclick="editCall()" title="<?php echo $this->t('edit'); ?>"><i class="fas fa-edit"></i></button>
                    <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="modal-body" id="callDetailBody"></div>
        </div>
    </div>

    <!-- Edit Call Modal -->
    <div class="modal" id="editCallModal">
        <div class="modal-backdrop" onclick="closeEditModal()"></div>
        <div class="modal-container" style="width:650px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> <?php echo $this->t('edit_call'); ?></h3>
                <button class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="editCallForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('phone_number'); ?></label>
                            <input type="text" id="editPhone" class="form-control" placeholder="<?php echo $this->t('placeholder_phone'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('extension'); ?></label>
                            <input type="text" id="editExtension" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('call_type'); ?></label>
                            <select id="editCallType" class="form-control">
                                <option value=""><?php echo $this->t('all_types'); ?></option>
                                <option value="immediate"><?php echo $this->t('immediate'); ?></option>
                                <option value="reservation"><?php echo $this->t('reservation'); ?></option>
                                <option value="operator"><?php echo $this->t('operator'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('outcome'); ?></label>
                            <select id="editCallOutcome" class="form-control">
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
                            <label class="form-label">Initial Choice</label>
                            <select id="editInitialChoice" class="form-control">
                                <option value="">Select</option>
                                <option value="1">1 - Immediate</option>
                                <option value="2">2 - Reservation</option>
                                <option value="3">3 - Operator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('customer_name'); ?></label>
                            <input type="text" id="editName" class="form-control">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-map-pin"></i> <?php echo $this->t('pickup_location'); ?></div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('pickup_address_label'); ?></label>
                            <input type="text" id="editPickupAddress" class="form-control">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" id="editPickupLat" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" id="editPickupLng" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-flag-checkered"></i> <?php echo $this->t('destination_location'); ?></div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('destination_address_label'); ?></label>
                            <input type="text" id="editDestAddress" class="form-control">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" id="editDestLat" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" id="editDestLng" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-calendar-alt"></i> <?php echo $this->t('reservation_time'); ?></div>
                        <div class="form-group">
                            <input type="datetime-local" id="editReservationTime" class="form-control">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCallEdit()"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal" id="exportModal">
        <div class="modal-backdrop" onclick="document.getElementById('exportModal').classList.remove('active')"></div>
        <div class="modal-container" style="width:600px;max-width:95vw;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-download"></i> <?php echo $this->t('export_data'); ?></h3>
                <button class="modal-close" id="exportModalClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <!-- Export Type Tabs -->
                <div class="export-tabs">
                    <button class="export-tab active" data-type="calls">
                        <i class="fas fa-table"></i>
                        <span><?php echo $this->t('call_data'); ?></span>
                    </button>
                    <button class="export-tab" data-type="summary">
                        <i class="fas fa-chart-bar"></i>
                        <span><?php echo $this->t('summary_statistics'); ?></span>
                    </button>
                </div>

                <!-- Export Type Descriptions -->
                <div class="export-type-info" id="exportTypeInfo">
                    <div class="export-type-desc" data-type="calls">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $this->t('export_all_call_records'); ?>
                    </div>
                    <div class="export-type-desc" data-type="summary" style="display:none;">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $this->t('export_aggregated_stats'); ?>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="export-section" id="exportFiltersSection">
                    <div class="export-section-title">
                        <i class="fas fa-filter"></i> <?php echo $this->t('export_filters'); ?>
                    </div>
                    <div class="export-filters-grid">
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('date_from'); ?></label>
                            <input type="text" id="exportDateFrom" class="form-control" placeholder="DD MMM YYYY">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('date_to'); ?></label>
                            <input type="text" id="exportDateTo" class="form-control" placeholder="DD MMM YYYY">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('outcome_filter'); ?></label>
                            <select id="exportOutcome" class="form-control">
                                <option value=""><?php echo $this->t('all_outcomes'); ?></option>
                                <option value="success"><?php echo $this->t('success'); ?></option>
                                <option value="hangup"><?php echo $this->t('hangup'); ?></option>
                                <option value="operator_transfer"><?php echo $this->t('operator_transfer'); ?></option>
                                <option value="error"><?php echo $this->t('error'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo $this->t('records_limit'); ?></label>
                            <select id="exportLimit" class="form-control">
                                <option value="0" selected><?php echo $this->t('all_records'); ?></option>
                                <option value="100"><?php echo $this->t('last_100_records'); ?></option>
                                <option value="500"><?php echo $this->t('last_500_records'); ?></option>
                                <option value="1000"><?php echo $this->t('last_1000_records'); ?></option>
                                <option value="5000"><?php echo $this->t('last_5000_records'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Format Section -->
                <div class="export-section">
                    <div class="export-section-title">
                        <i class="fas fa-file-export"></i> <?php echo $this->t('export_format'); ?>
                    </div>
                    <div class="export-format-grid">
                        <div class="export-format-option active" data-format="xlsx">
                            <div class="export-format-icon xlsx"><i class="fas fa-file-excel"></i></div>
                            <div class="export-format-name"><?php echo $this->t('xlsx_export'); ?></div>
                            <div class="export-format-desc"><?php echo $this->t('compatible_with_excel'); ?></div>
                        </div>
                        <div class="export-format-option" data-format="csv">
                            <div class="export-format-icon csv"><i class="fas fa-file-csv"></i></div>
                            <div class="export-format-name"><?php echo $this->t('csv_export'); ?></div>
                            <div class="export-format-desc"><?php echo $this->t('best_for_data_analysis'); ?></div>
                        </div>
                        <div class="export-format-option" data-format="pdf">
                            <div class="export-format-icon pdf"><i class="fas fa-file-pdf"></i></div>
                            <div class="export-format-name"><?php echo $this->t('pdf_export'); ?></div>
                            <div class="export-format-desc"><?php echo $this->t('best_for_presentations'); ?></div>
                        </div>
                        <div class="export-format-option" data-format="print">
                            <div class="export-format-icon print"><i class="fas fa-print"></i></div>
                            <div class="export-format-name"><?php echo $this->t('print_view'); ?></div>
                            <div class="export-format-desc"><?php echo $this->t('best_for_printing'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('exportModal').classList.remove('active')">Cancel</button>
                <button type="button" class="btn btn-primary" id="exportNowBtn">
                    <i class="fas fa-download"></i> <?php echo $this->t('export_now'); ?>
                </button>
            </div>
        </div>
    </div>

    <script>
    const LANG = {
        current: '<?php echo $this->language; ?>',
        translations: {
            seconds_short: '<?php echo $this->t('seconds_short'); ?>',
            minutes_short: '<?php echo $this->t('minutes_short'); ?>',
            hours_short: '<?php echo $this->t('hours_short'); ?>',
            loading: '<?php echo $this->t('loading'); ?>',
            success: '<?php echo $this->t('success'); ?>',
            error: '<?php echo $this->t('error'); ?>',
            hangup: '<?php echo $this->t('hangup'); ?>',
            operator_transfer: '<?php echo $this->t('operator_transfer'); ?>',
            in_progress: '<?php echo $this->t('in_progress'); ?>',
            immediate: '<?php echo $this->t('immediate'); ?>',
            reservation: '<?php echo $this->t('reservation'); ?>',
            operator: '<?php echo $this->t('operator'); ?>',
            no_location: '<?php echo $this->language === 'el' ? 'Χωρίς τοποθεσία' : 'No location'; ?>',
            today: '<?php echo $this->t('today'); ?>',
            total_calls: '<?php echo $this->t('total_calls'); ?>',
            successful_calls: '<?php echo $this->t('successful_calls'); ?>',
            total_calls_today: '<?php echo $this->t('total_calls_today'); ?>',
            active: '<?php echo $this->t('active'); ?>',
            success_rate: '<?php echo $this->t('success_rate'); ?>',
            failed_count: '<?php echo $this->t('failed_count'); ?>',
            avg_duration: '<?php echo $this->t('avg_duration'); ?>',
            unique_callers: '<?php echo $this->t('unique_callers'); ?>',
            stop: '<?php echo $this->t('stop'); ?>',
            live: '<?php echo $this->t('live'); ?>',
            weekly_stats: '<?php echo $this->t('weekly_stats'); ?>',
            monthly_stats: '<?php echo $this->t('monthly_stats'); ?>',
            user_recordings: '<?php echo $this->t('user_recordings'); ?>',
            system_recordings: '<?php echo $this->t('system_recordings'); ?>',
            kb_size: '<?php echo $this->t('kb_size'); ?>',
            attempt: '<?php echo $this->t('attempt'); ?>',
            call_log: '<?php echo $this->t('call_log'); ?>',
            api_calls_label: '<?php echo $this->t('api_calls_label'); ?>'
        }
    };
    let currentCallId = null;

    let currentPage = 1, currentLimit = 50, currentFilters = {}, realtimeInterval = null, charts = {};
    window.heatmapInstance = null; window.heatmapLayer = null; window.markersLayer = null;

    function switchLanguage(lang) { const url = new URL(window.location.href); url.searchParams.set('lang', lang); window.location.href = url.toString(); }
    function formatDuration(seconds, isLive = false) {
        if (!seconds || seconds < 0) return '0' + LANG.translations.seconds_short;
        const s = LANG.translations.seconds_short, m = LANG.translations.minutes_short, h = LANG.translations.hours_short;
        if (seconds < 60) return seconds + s + (isLive ? ' 🔴' : '');
        if (seconds < 3600) { const mins = Math.floor(seconds / 60), secs = seconds % 60; return mins + m + (secs > 0 ? ' ' + secs + s : '') + (isLive ? ' 🔴' : ''); }
        const hours = Math.floor(seconds / 3600), mins = Math.floor((seconds % 3600) / 60);
        return hours + h + (mins > 0 ? ' ' + mins + m : '') + (isLive ? ' 🔴' : '');
    }
    function formatDate(dateStr) {
        if (!dateStr || dateStr === '0000-00-00 00:00:00') return '-';
        const d = new Date(dateStr.includes('T') ? dateStr : dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        const months = LANG.current === 'el'
            ? ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαΐ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ']
            : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const day = String(d.getDate()).padStart(2, '0');
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        const time = d.toTimeString().slice(0, 8);
        return `${day} ${month} ${year} ${time}`;
    }
    function truncate(str, len) { if (!str) return 'N/A'; return str.length > len ? str.substr(0, len) + '...' : str; }

    function loadStats() {
        fetch('?endpoint=dashboard').then(r => r.json()).then(data => {
            const today = data.today_summary || {};
            const week = data.weekly_summary || data.week_summary || {};
            const month = data.monthly_summary || data.month_summary || {};
            const active = data.active_calls || 0;

            const todayRate = today.total_calls > 0 ? ((today.successful_calls / today.total_calls) * 100).toFixed(1) : 0;
            const weekRate = week.total_calls > 0 ? ((week.successful_calls / week.total_calls) * 100).toFixed(1) : 0;
            const monthRate = month.total_calls > 0 ? ((month.successful_calls / month.total_calls) * 100).toFixed(1) : 0;

            const todayFailed = (today.total_calls || 0) - (today.successful_calls || 0);
            const weekFailed = (week.total_calls || 0) - (week.successful_calls || 0);
            const monthFailed = (month.total_calls || 0) - (month.successful_calls || 0);

            const isGr = LANG.current === 'el';
            const L = {
                today: isGr ? 'Σήμερα' : 'Today',
                week: isGr ? 'Εβδομάδα' : 'Week',
                month: isGr ? 'Μήνας' : 'Month',
                success: isGr ? 'Επιτυχία' : 'Success',
                failed: isGr ? 'Αποτυχία' : 'Failed',
                duration: isGr ? 'Διάρκεια' : 'Duration',
                unique: isGr ? 'Μοναδικοί' : 'Unique',
                active: isGr ? 'Ενεργές' : 'Active',
                calls: isGr ? 'κλήσεις' : 'calls'
            };

            const renderCard = (period, icon, label, total, rate, successCount, failedCount, avgDur, uniqueCount, activeBadge = '') => `
                <div class="period-card ${period}">
                    <div class="period-header" onclick="this.parentElement.classList.toggle('expanded')">
                        <div class="period-header-left">
                            <div class="period-title"><i class="fas ${icon}"></i> ${label}</div>
                            <div class="period-summary">
                                <span class="period-total">${total.toLocaleString()}</span>
                                <span class="period-rate">${rate}%</span>
                                ${activeBadge}
                            </div>
                        </div>
                        <div class="period-header-right">
                            <span class="period-toggle"><i class="fas fa-chevron-down"></i></span>
                        </div>
                    </div>
                    <div class="period-details">
                        <div class="period-stats">
                            <div class="period-stat success">
                                <span class="period-stat-value">${successCount.toLocaleString()}</span>
                                <span class="period-stat-label">${L.success}</span>
                            </div>
                            <div class="period-stat danger">
                                <span class="period-stat-value">${failedCount.toLocaleString()}</span>
                                <span class="period-stat-label">${L.failed}</span>
                            </div>
                            <div class="period-stat info">
                                <span class="period-stat-value">${formatDuration(Math.round(avgDur))}</span>
                                <span class="period-stat-label">${L.duration}</span>
                            </div>
                            <div class="period-stat warning">
                                <span class="period-stat-value">${uniqueCount.toLocaleString()}</span>
                                <span class="period-stat-label">${L.unique}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const activeBadge = active > 0 ? `<span class="active-badge"><i class="fas fa-circle"></i> ${active}</span>` : '';

            document.getElementById('statsGrid').innerHTML =
                renderCard('today', 'fa-calendar-day', L.today, today.total_calls || 0, todayRate, today.successful_calls || 0, todayFailed, today.avg_duration || 0, today.unique_callers || 0, activeBadge) +
                renderCard('weekly', 'fa-calendar-week', L.week, week.total_calls || 0, weekRate, week.successful_calls || 0, weekFailed, week.avg_duration || 0, week.unique_callers || 0) +
                renderCard('monthly', 'fa-calendar-alt', L.month, month.total_calls || 0, monthRate, month.successful_calls || 0, monthFailed, month.avg_duration || 0, month.unique_callers || 0);

        }).catch(e => console.error('Error loading stats:', e));
    }

    function loadCalls() {
        const params = new URLSearchParams({ page: currentPage, limit: currentLimit, ...currentFilters });
        const url = new URL(window.location.href); url.searchParams.forEach((v, k) => { if (k !== 'endpoint' && k !== 'page' && k !== 'limit') params.set(k, v); });
        fetch('?endpoint=calls&' + params.toString()).then(r => r.json()).then(data => { renderCalls(data.calls); renderPagination(data.pagination); }).catch(e => console.error('Error loading calls:', e));
    }

    function renderCalls(calls) {
        const tbody = document.getElementById('callsTableBody');
        if (!calls || calls.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:3rem;">
                <div style="color:var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size:3rem;display:block;margin-bottom:1rem;opacity:0.5;"></i>
                    <p style="font-size:1rem;margin-bottom:0.5rem;">${LANG.current === 'el' ? 'Δεν βρέθηκαν κλήσεις' : 'No calls found'}</p>
                    <p style="font-size:0.8rem;opacity:0.7;">${LANG.current === 'el' ? 'Δοκιμάστε να αλλάξετε τα φίλτρα' : 'Try adjusting your filters'}</p>
                </div>
            </td></tr>`;
            return;
        }
        tbody.innerHTML = calls.map(call => {
            const badges = { success: 'badge-success', hangup: 'badge-danger', operator_transfer: 'badge-warning', error: 'badge-danger', in_progress: 'badge-purple' };
            const badgeIcons = { success: 'fa-check', hangup: 'fa-phone-slash', operator_transfer: 'fa-headset', error: 'fa-exclamation', in_progress: 'fa-spinner fa-spin' };
            const badgeClass = badges[call.call_outcome] || 'badge-info';
            const badgeIcon = badgeIcons[call.call_outcome] || 'fa-info';
            const statusText = LANG.translations[call.call_outcome] || call.call_outcome;
            const durCell = call.call_outcome === 'in_progress'
                ? `<td><span class="live-badge text-danger">${formatDuration(call.call_duration, true)}</span></td>`
                : `<td>${formatDuration(call.call_duration)}</td>`;
            let locHtml = '';
            if (call.pickup_address) locHtml += `<div class="location-line pickup"><i class="fas fa-map-pin"></i> <span class="truncate">${truncate(call.pickup_address, 22)}</span></div>`;
            if (call.destination_address) locHtml += `<div class="location-line dest"><i class="fas fa-flag-checkered"></i> <span class="truncate">${truncate(call.destination_address, 22)}</span></div>`;
            if (!locHtml) locHtml = `<span class="text-muted" style="font-size:0.75rem;">${LANG.translations.no_location}</span>`;
            const tts = (call.google_tts_calls || 0) + (call.edge_tts_calls || 0);
            const typeLabel = call.is_reservation ? LANG.translations.reservation : LANG.translations.immediate;
            const typeBadge = call.is_reservation ? 'badge-warning' : 'badge-info';
            return `<tr onclick="showCallDetail('${call.call_id || ''}')">
                <td><span class="cell-phone">${call.phone_number || 'N/A'}</span></td>
                <td style="white-space:nowrap;">${formatDate(call.call_start_time)}</td>
                ${durCell}
                <td><span class="badge ${badgeClass}"><i class="fas ${badgeIcon}"></i> ${statusText}</span></td>
                <td><span class="badge ${typeBadge}">${typeLabel}</span></td>
                <td>${truncate(call.user_name, 18) || '<span class="text-muted">-</span>'}</td>
                <td class="cell-location">${locHtml}</td>
                <td class="cell-apis">
                    <span class="api-badge">TTS ${tts}</span>
                    <span class="api-badge">STT ${call.google_stt_calls || 0}</span>
                    <span class="api-badge">GEO ${call.geocoding_api_calls || 0}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-ghost" onclick="event.stopPropagation();showCallDetail('${call.call_id || ''}')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderPagination(pagination) {
        if (!pagination) return;
        const container = document.getElementById('pagination');
        const infoContainer = document.getElementById('paginationInfo');
        const start = (pagination.page - 1) * pagination.limit + 1;
        const end = Math.min(pagination.page * pagination.limit, pagination.total);
        if (infoContainer) {
            infoContainer.textContent = LANG.current === 'el'
                ? `Εμφάνιση ${start}-${end} από ${pagination.total} κλήσεις`
                : `Showing ${start}-${end} of ${pagination.total} calls`;
        }
        let html = '';
        if (pagination.page > 1) html += `<button onclick="goToPage(${pagination.page - 1})" title="Previous"><i class="fas fa-chevron-left"></i></button>`;
        const rangeStart = Math.max(1, pagination.page - 2), rangeEnd = Math.min(pagination.pages, pagination.page + 2);
        if (rangeStart > 1) {
            html += `<button onclick="goToPage(1)">1</button>`;
            if (rangeStart > 2) html += `<button disabled>...</button>`;
        }
        for (let i = rangeStart; i <= rangeEnd; i++) {
            html += `<button class="${i === pagination.page ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
        }
        if (rangeEnd < pagination.pages) {
            if (rangeEnd < pagination.pages - 1) html += `<button disabled>...</button>`;
            html += `<button onclick="goToPage(${pagination.pages})">${pagination.pages}</button>`;
        }
        if (pagination.page < pagination.pages) html += `<button onclick="goToPage(${pagination.page + 1})" title="Next"><i class="fas fa-chevron-right"></i></button>`;
        container.innerHTML = html;
    }

    function goToPage(page) { currentPage = page; loadCalls(); }

    function getUserRecordingDescription(type, attempt) {
        attempt = attempt || 1;
        const isGr = LANG.current === 'el';
        const attemptText = attempt > 1 ? ` (${LANG.translations.attempt} ${attempt})` : '';

        const data = {
            name: {
                title: (isGr ? 'Πελάτης είπε το όνομά του' : 'Customer said their name') + attemptText,
                description: isGr
                    ? 'Ο πελάτης είπε το όνομά του. Αυτή η ηχογράφηση χρησιμοποιείται για αναγνώριση ομιλίας και επιβεβαίωση ταυτότητας.'
                    : 'Customer said their name. This recording is used for speech recognition and identity confirmation.'
            },
            pickup: {
                title: (isGr ? 'Πελάτης είπε τη διεύθυνση παραλαβής' : 'Customer said pickup address') + attemptText,
                description: isGr
                    ? 'Ο πελάτης είπε τη διεύθυνση παραλαβής. Επεξεργάζεται για τον προσδιορισμό της ακριβούς τοποθεσίας.'
                    : 'Customer said the pickup address. Processed to determine exact location.'
            },
            destination: {
                title: (isGr ? 'Πελάτης είπε τη διεύθυνση προορισμού' : 'Customer said destination') + attemptText,
                description: isGr
                    ? 'Ο πελάτης είπε τον προορισμό του ταξιδιού. Χρησιμοποιείται για τον υπολογισμό της διαδρομής.'
                    : 'Customer said the destination of the trip. Used for route calculation.'
            },
            dest: {
                title: (isGr ? 'Πελάτης είπε τη διεύθυνση προορισμού' : 'Customer said destination') + attemptText,
                description: isGr
                    ? 'Ο πελάτης είπε τον προορισμό του ταξιδιού. Χρησιμοποιείται για τον υπολογισμό της διαδρομής.'
                    : 'Customer said the destination of the trip. Used for route calculation.'
            },
            reservation: {
                title: (isGr ? 'Πελάτης είπε την ώρα κράτησης' : 'Customer said reservation time') + attemptText,
                description: isGr
                    ? 'Ο πελάτης είπε την ημερομηνία και ώρα κράτησης για μελλοντικό ταξίδι.'
                    : 'Customer said the reservation date and time for a future trip.'
            },
            other: {
                title: (isGr ? 'Ηχογραφημένη Είσοδος Χρήστη' : 'User Voice Input') + attemptText,
                description: isGr
                    ? 'Ηχογραφημένη απάντηση του πελάτη.'
                    : 'Customer voice input recording.'
            }
        };
        return data[type] || data.other;
    }

    function getRecordingDescription(type) {
        const typeLower = (type || '').toLowerCase();
        const isGr = LANG.current === 'el';

        const data = {
            confirmation: {
                title: isGr ? 'Ήχος Επιβεβαίωσης' : 'Confirmation Audio',
                description: isGr
                    ? 'Μήνυμα που διαβάζει τα στοιχεία της παραγγελίας (όνομα, διεύθυνση παραλαβής, προορισμός) για επιβεβαίωση από τον πελάτη.'
                    : 'Message that reads back order details (name, pickup address, destination) for customer confirmation.'
            },
            welcome: {
                title: isGr ? 'Μήνυμα Καλωσορίσματος' : 'Welcome Message',
                description: isGr
                    ? 'Μήνυμα καλωσορίσματος του συστήματος που παίζει στην αρχή της κλήσης για να καθοδηγήσει τους πελάτες.'
                    : 'System greeting played at the start of the call to guide customers through the booking process.'
            },
            dtmf: {
                title: isGr ? 'Ηχογράφηση Εισαγωγής DTMF' : 'DTMF Input Recording',
                description: isGr
                    ? 'Ηχογράφηση των επιλογών πλήκτρων του πελάτη κατά τη διάρκεια της πλοήγησης στο διαδραστικό μενού.'
                    : "Recording of customer's button press choices during interactive menu navigation."
            },
            goodbye: {
                title: isGr ? 'Μήνυμα Αποχαιρετισμού' : 'Goodbye Message',
                description: isGr
                    ? 'Μήνυμα αποχαιρετισμού που αναπαράγεται στο τέλος της κλήσης.'
                    : 'Farewell message played at the end of the call.'
            },
            error: {
                title: isGr ? 'Μήνυμα Σφάλματος' : 'Error Message',
                description: isGr
                    ? 'Μήνυμα που ενημερώνει τον πελάτη για σφάλμα ή πρόβλημα κατά τη διάρκεια της κλήσης.'
                    : 'Message informing the customer about an error or problem during the call.'
            },
            retry: {
                title: isGr ? 'Μήνυμα Επανάληψης' : 'Retry Prompt',
                description: isGr
                    ? 'Μήνυμα που ζητά από τον πελάτη να επαναλάβει την είσοδό του.'
                    : 'Message asking the customer to repeat their input.'
            },
            language: {
                title: isGr ? 'Επιλογή Γλώσσας' : 'Language Selection',
                description: isGr
                    ? 'Μήνυμα που επιτρέπει στον πελάτη να επιλέξει τη γλώσσα της κλήσης.'
                    : 'Message allowing the customer to choose the call language.'
            },
            menu: {
                title: isGr ? 'Μενού Επιλογών' : 'Menu Options',
                description: isGr
                    ? 'Μήνυμα με τις διαθέσιμες επιλογές μενού για τον πελάτη.'
                    : 'Message with available menu options for the customer.'
            },
            hold: {
                title: isGr ? 'Μήνυμα Αναμονής' : 'Hold Message',
                description: isGr
                    ? 'Μήνυμα που αναπαράγεται ενώ ο πελάτης περιμένει.'
                    : 'Message played while the customer is waiting.'
            },
            transfer: {
                title: isGr ? 'Μεταφορά Κλήσης' : 'Call Transfer',
                description: isGr
                    ? 'Μήνυμα που ενημερώνει τον πελάτη για τη μεταφορά της κλήσης σε χειριστή.'
                    : 'Message informing the customer about call transfer to an operator.'
            },
            tts: {
                title: isGr ? 'Σύνθεση Ομιλίας' : 'Text-to-Speech',
                description: isGr
                    ? 'Μήνυμα που δημιουργήθηκε από σύνθεση ομιλίας.'
                    : 'Message generated by text-to-speech synthesis.'
            },
            prompt: {
                title: isGr ? 'Ερώτηση Συστήματος' : 'System Prompt',
                description: isGr
                    ? 'Ερώτηση ή οδηγία από το σύστημα προς τον πελάτη.'
                    : 'Question or instruction from the system to the customer.'
            },
            name: {
                title: isGr ? 'Ηχογράφηση Ονόματος Πελάτη' : 'Customer Name Recording',
                description: isGr
                    ? 'Ηχογραφημένο όνομα του πελάτη κατά τη διάρκεια της κλήσης. Χρησιμοποιείται για αναγνώριση.'
                    : "Customer's spoken name recorded during the call. Used for identification."
            },
            pickup: {
                title: isGr ? 'Ηχογράφηση Διεύθυνσης Παραλαβής' : 'Pickup Address Recording',
                description: isGr
                    ? 'Προφορική τοποθεσία παραλαβής του πελάτη. Επεξεργάζεται μέσω αναγνώρισης ομιλίας και γεωκωδικοποίησης.'
                    : "Customer's spoken pickup location. Processed through speech-to-text and geocoding."
            },
            destination: {
                title: isGr ? 'Ηχογράφηση Προορισμού' : 'Destination Recording',
                description: isGr
                    ? 'Προφορική διεύθυνση προορισμού του πελάτη. Επεξεργάζεται για τον προσδιορισμό της τοποθεσίας παράδοσης.'
                    : "Customer's spoken destination address. Processed to determine the drop-off location."
            },
            reservation: {
                title: isGr ? 'Ηχογράφηση Ώρας Κράτησης' : 'Reservation Time Recording',
                description: isGr
                    ? 'Προτιμώμενη ώρα του πελάτη για την κράτηση ταξί.'
                    : "Customer's preferred time for taxi booking."
            },
            other: {
                title: isGr ? 'Ηχογράφηση Κλήσης' : 'Call Recording',
                description: isGr
                    ? 'Ηχογράφηση από τη συνεδρία κλήσης του πελάτη.'
                    : 'Audio recording from the customer call session.'
            }
        };
        return data[typeLower] || data.other;
    }

    function getRecordingIcon(type, isUser) {
        if (isUser) {
            const icons = { name: 'fa-user', pickup: 'fa-map-marker-alt', destination: 'fa-flag-checkered', reservation: 'fa-calendar' };
            return icons[type] || 'fa-microphone';
        }
        const icons = { confirmation: 'fa-check-circle', welcome: 'fa-hand-wave', dtmf: 'fa-keyboard' };
        return icons[type] || 'fa-volume-up';
    }

    function renderRecordings(recordings, isUserInput = false) {
        if (!recordings || recordings.length === 0) return `<p class="text-muted">${LANG.current === 'el' ? 'Δεν υπάρχουν ηχογραφήσεις' : 'No recordings available'}</p>`;
        return recordings.map(rec => {
            const icon = getRecordingIcon(rec.type, isUserInput);
            const info = isUserInput ? getUserRecordingDescription(rec.type, rec.attempt) : getRecordingDescription(rec.type);
            const size = rec.size ? `${Math.round(rec.size / 1024)} ${LANG.translations.kb_size}` : '';
            const duration = rec.duration ? formatDuration(Math.round(rec.duration)) : '';
            return `
                <div class="recording-item" ${isUserInput ? 'style="border-left: 3px solid #4caf50;"' : ''}>
                    <div class="recording-header">
                        <div class="recording-info">
                            <i class="fas ${icon} recording-icon ${isUserInput ? 'text-primary' : 'text-success'}"></i>
                            <div class="recording-details">
                                <span class="recording-title">${info.title}</span>
                                <div class="recording-meta">
                                    <span class="recording-filename">${rec.filename}</span>
                                    ${size ? `<span class="recording-size">${size}</span>` : ''}
                                    ${duration ? `<span class="recording-duration">${duration}</span>` : ''}
                                    ${rec.attempt > 1 ? `<span class="recording-attempt">${LANG.translations.attempt} ${rec.attempt}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="recording-description" style="color: var(--text-secondary); font-style: italic; margin: 0.5rem 0; font-size: 0.85rem;">${info.description}</div>
                    <audio controls class="recording-player">
                        <source src="?action=audio&file=${encodeURIComponent(rec.path)}" type="audio/wav">
                        ${LANG.current === 'el' ? 'Δεν υποστηρίζεται η αναπαραγωγή' : 'Audio not supported'}
                    </audio>
                </div>
            `;
        }).join('');
    }

    function showCallDetail(callId) {
        if (!callId) return;
        currentCallId = callId;
        document.getElementById('callDetailBody').innerHTML = `<div style="text-align:center;padding:2rem;"><div class="loading-spinner"></div><p>${LANG.translations.loading}...</p></div>`;
        document.getElementById('callDetailModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        fetch('?endpoint=call&call_id=' + encodeURIComponent(callId)).then(r => r.json()).then(call => {
            const badges = { success: 'badge-success', hangup: 'badge-danger', operator_transfer: 'badge-warning', error: 'badge-danger', in_progress: 'badge-info' };
            const badgeClass = badges[call.call_outcome] || 'badge-info';
            const statusText = LANG.translations[call.call_outcome] || call.call_outcome;

            const userRecordings = (call.recordings || []).filter(r => r.is_user_input);
            const systemRecordings = (call.recordings || []).filter(r => !r.is_user_input);
            const hasMap = call.pickup_lat && call.pickup_lng;

            // Compact recordings renderer
            const renderCompactRecs = (recs, isUser) => {
                if (!recs.length) return '';
                return recs.slice(0, 5).map(rec => {
                    const recType = rec.recording_type || rec.type || rec.filename || 'other';
                    const info = isUser
                        ? getUserRecordingDescription(recType, rec.attempt_number || rec.attempt || 1)
                        : getRecordingDescription(recType);
                    const filePath = rec.file_path || rec.path || rec.filename || '';
                    const borderStyle = isUser ? 'border-left: 3px solid #4caf50;' : '';
                    return `<div class="cd-rec-item" style="${borderStyle}">
                        <span class="cd-rec-label">${info.title}</span>
                        <div class="cd-rec-desc">${info.description}</div>
                        <audio class="cd-rec-audio" controls preload="none" src="?action=audio&file=${encodeURIComponent(filePath)}"></audio>
                    </div>`;
                }).join('') + (recs.length > 5 ? `<div class="cd-rec-item text-muted" style="text-align:center;">+${recs.length - 5} ${LANG.current === 'el' ? 'ακόμα...' : 'more...'}</div>` : '');
            };

            // Parse log - handle both array and string formats
            let logEntries = [];
            if (call.call_log) {
                if (Array.isArray(call.call_log)) {
                    logEntries = call.call_log;
                } else if (typeof call.call_log === 'string') {
                    // Split by newlines if it's a string
                    logEntries = call.call_log.split('\n').filter(l => l.trim()).map(line => ({ message: line }));
                }
            }

            document.getElementById('callDetailBody').innerHTML = `
                <div class="cd-header">
                    <span class="cd-phone"><i class="fas fa-phone-alt text-primary"></i> ${call.phone_number || 'N/A'}</span>
                    <div class="cd-meta">
                        <span class="badge ${badgeClass}">${statusText}</span>
                        ${call.is_reservation ? `<span class="badge badge-warning"><i class="fas fa-calendar-alt"></i></span>` : ''}
                    </div>
                </div>

                <div class="cd-grid">
                    <div class="cd-item"><div class="cd-item-label">${LANG.current === 'el' ? 'Πελάτης' : 'Customer'}</div><div class="cd-item-value">${call.user_name || '-'}</div></div>
                    <div class="cd-item"><div class="cd-item-label">${LANG.current === 'el' ? 'Διάρκεια' : 'Duration'}</div><div class="cd-item-value ${call.call_outcome === 'in_progress' ? 'text-danger' : ''}">${formatDuration(call.call_duration, call.call_outcome === 'in_progress')}</div></div>
                    <div class="cd-item"><div class="cd-item-label">${LANG.current === 'el' ? 'Έναρξη' : 'Start'}</div><div class="cd-item-value">${formatDate(call.call_start_time)}</div></div>
                    <div class="cd-item"><div class="cd-item-label">Ext</div><div class="cd-item-value">${call.extension || '-'}</div></div>
                </div>

                ${call.reservation_time ? `<div class="cd-item" style="background:#fef3c7;margin-bottom:1rem;"><div class="cd-item-label"><i class="fas fa-calendar"></i> ${LANG.current === 'el' ? 'Κράτηση' : 'Reservation'}</div><div class="cd-item-value">${formatDate(call.reservation_time)}</div></div>` : ''}

                <div class="cd-locations">
                    <div class="cd-loc"><div class="cd-loc-title pickup"><i class="fas fa-map-pin"></i> ${LANG.current === 'el' ? 'Παραλαβή' : 'Pickup'}</div><div class="cd-loc-addr">${call.pickup_address || '-'}</div></div>
                    <div class="cd-loc"><div class="cd-loc-title dest"><i class="fas fa-flag-checkered"></i> ${LANG.current === 'el' ? 'Προορισμός' : 'Destination'}</div><div class="cd-loc-addr">${call.destination_address || '-'}</div></div>
                </div>

                ${hasMap ? `<div class="cd-map" id="callDetailMap"></div>` : ''}

                <div class="cd-section">
                    <div class="cd-section-title"><i class="fas fa-plug"></i> APIs</div>
                    <div class="cd-api-badges">
                        <span class="cd-api-badge">TTS ${(call.google_tts_calls || 0) + (call.edge_tts_calls || 0)}</span>
                        <span class="cd-api-badge">STT ${call.google_stt_calls || 0}</span>
                        <span class="cd-api-badge">GEO ${call.geocoding_api_calls || 0}</span>
                        <span class="cd-api-badge">REG ${call.registration_api_calls || 0}</span>
                    </div>
                </div>

                ${userRecordings.length > 0 ? `<div class="cd-section"><div class="cd-section-title"><i class="fas fa-microphone"></i> ${LANG.translations.user_recordings}</div><div class="cd-recordings">${renderCompactRecs(userRecordings, true)}</div></div>` : ''}

                ${systemRecordings.length > 0 ? `<div class="cd-section"><div class="cd-section-title"><i class="fas fa-volume-up"></i> ${LANG.translations.system_recordings}</div><div class="cd-recordings">${renderCompactRecs(systemRecordings, false)}</div></div>` : ''}

                ${logEntries.length > 0 ? `<div class="cd-section"><div class="cd-section-title"><i class="fas fa-scroll"></i> Log</div><div class="cd-log">${logEntries.map(log => `<div class="cd-log-entry">${log.timestamp ? `<span class="cd-log-time">[${log.timestamp}]</span> ` : ''}<span class="cd-log-${log.category || 'general'}">${log.message || log}</span></div>`).join('')}</div></div>` : ''}
            `;

            if (call.pickup_lat && call.pickup_lng) {
                setTimeout(() => {
                    const map = L.map('callDetailMap').setView([call.pickup_lat, call.pickup_lng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

                    // Create custom icons
                    const pickupIcon = L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background:#10b981;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-map-pin" style="color:white;font-size:14px;"></i></div>',
                        iconSize: [32, 32],
                        iconAnchor: [16, 32]
                    });

                    const destIcon = L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background:#ef4444;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-flag-checkered" style="color:white;font-size:14px;"></i></div>',
                        iconSize: [32, 32],
                        iconAnchor: [16, 32]
                    });

                    // Add pickup marker
                    L.marker([call.pickup_lat, call.pickup_lng], { icon: pickupIcon })
                        .addTo(map)
                        .bindPopup(`<b style="color:#10b981;">${LANG.current === 'el' ? 'Παραλαβή' : 'Pickup'}</b><br>${call.pickup_address || ''}`);

                    if (call.destination_lat && call.destination_lng) {
                        // Add destination marker
                        L.marker([call.destination_lat, call.destination_lng], { icon: destIcon })
                            .addTo(map)
                            .bindPopup(`<b style="color:#ef4444;">${LANG.current === 'el' ? 'Προορισμός' : 'Destination'}</b><br>${call.destination_address || ''}`);

                        // Fetch route from OSRM
                        const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${call.pickup_lng},${call.pickup_lat};${call.destination_lng},${call.destination_lat}?overview=full&geometries=geojson`;

                        fetch(osrmUrl)
                            .then(response => response.json())
                            .then(data => {
                                if (data.routes && data.routes.length > 0) {
                                    const route = data.routes[0];
                                    const routeCoords = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);

                                    // Draw the route polyline
                                    const routeLine = L.polyline(routeCoords, {
                                        color: '#6366f1',
                                        weight: 5,
                                        opacity: 0.8,
                                        smoothFactor: 1,
                                        lineCap: 'round',
                                        lineJoin: 'round'
                                    }).addTo(map);

                                    // Add route shadow for depth effect
                                    L.polyline(routeCoords, {
                                        color: '#4f46e5',
                                        weight: 8,
                                        opacity: 0.3,
                                        smoothFactor: 1
                                    }).addTo(map).bringToBack();

                                    // Calculate distance and duration
                                    const distanceKm = (route.distance / 1000).toFixed(1);
                                    const durationMin = Math.round(route.duration / 60);

                                    // Add route info popup at midpoint
                                    const midIndex = Math.floor(routeCoords.length / 2);
                                    if (routeCoords[midIndex]) {
                                        const routeInfo = LANG.current === 'el'
                                            ? `<b>Διαδρομή</b><br>Απόσταση: ${distanceKm} km<br>Χρόνος: ~${durationMin} λεπτά`
                                            : `<b>Route</b><br>Distance: ${distanceKm} km<br>Duration: ~${durationMin} min`;
                                        L.popup()
                                            .setLatLng(routeCoords[midIndex])
                                            .setContent(routeInfo)
                                            .openOn(map);
                                    }

                                    // Fit bounds to include the route
                                    map.fitBounds(routeLine.getBounds(), { padding: [50, 50] });
                                } else {
                                    // Fallback: just fit to markers if no route found
                                    const bounds = L.latLngBounds([[call.pickup_lat, call.pickup_lng], [call.destination_lat, call.destination_lng]]);
                                    map.fitBounds(bounds, { padding: [50, 50] });
                                }
                            })
                            .catch(err => {
                                console.log('Could not fetch route:', err);
                                // Fallback: draw straight line
                                L.polyline([[call.pickup_lat, call.pickup_lng], [call.destination_lat, call.destination_lng]], {
                                    color: '#6366f1',
                                    weight: 3,
                                    opacity: 0.6,
                                    dashArray: '10, 10'
                                }).addTo(map);
                                const bounds = L.latLngBounds([[call.pickup_lat, call.pickup_lng], [call.destination_lat, call.destination_lng]]);
                                map.fitBounds(bounds, { padding: [50, 50] });
                            });
                    }
                }, 100);
            }
        }).catch(e => {
            console.error('Error loading call detail:', e);
            document.getElementById('callDetailBody').innerHTML = `<div style="text-align:center;padding:2rem;color:var(--danger);"><i class="fas fa-exclamation-triangle" style="font-size:2rem;"></i><p>Error loading call details</p></div>`;
        });
    }

    function refreshCallDetail() { if (currentCallId) showCallDetail(currentCallId); }

    function editCall() {
        if (!currentCallId) return;
        fetch('?endpoint=call&call_id=' + encodeURIComponent(currentCallId)).then(r => r.json()).then(call => {
            document.getElementById('editPhone').value = call.phone_number || '';
            document.getElementById('editExtension').value = call.extension || '';
            document.getElementById('editCallType').value = call.is_reservation ? 'reservation' : 'immediate';
            document.getElementById('editInitialChoice').value = call.initial_choice || '';
            document.getElementById('editCallOutcome').value = call.call_outcome || '';
            document.getElementById('editName').value = call.user_name || '';
            document.getElementById('editPickupAddress').value = call.pickup_address || '';
            document.getElementById('editPickupLat').value = call.pickup_lat || '';
            document.getElementById('editPickupLng').value = call.pickup_lng || '';
            document.getElementById('editDestAddress').value = call.destination_address || '';
            document.getElementById('editDestLat').value = call.destination_lat || '';
            document.getElementById('editDestLng').value = call.destination_lng || '';
            document.getElementById('editReservationTime').value = call.reservation_time ? call.reservation_time.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('editCallModal').classList.add('active');
        });
    }

    function closeEditModal() { document.getElementById('editCallModal').classList.remove('active'); }

    function saveCallEdit() {
        if (!currentCallId) return;
        const data = {
            call_id: currentCallId,
            phone_number: document.getElementById('editPhone').value,
            extension: document.getElementById('editExtension').value,
            is_reservation: document.getElementById('editCallType').value === 'reservation' ? 1 : 0,
            initial_choice: document.getElementById('editInitialChoice').value,
            call_outcome: document.getElementById('editCallOutcome').value,
            user_name: document.getElementById('editName').value,
            pickup_address: document.getElementById('editPickupAddress').value,
            pickup_lat: document.getElementById('editPickupLat').value || null,
            pickup_lng: document.getElementById('editPickupLng').value || null,
            destination_address: document.getElementById('editDestAddress').value,
            destination_lat: document.getElementById('editDestLat').value || null,
            destination_lng: document.getElementById('editDestLng').value || null,
            reservation_time: document.getElementById('editReservationTime').value ? document.getElementById('editReservationTime').value.replace('T', ' ') + ':00' : null
        };
        fetch('?endpoint=call', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json()).then(result => {
            if (result.success) {
                closeEditModal();
                showCallDetail(currentCallId);
                loadCalls();
            } else {
                alert('Error: ' + (result.error || 'Failed to update'));
            }
        }).catch(e => {
            console.error('Error saving call:', e);
            alert('Error saving call');
        });
    }

    function closeModal() {
        document.getElementById('callDetailModal').classList.remove('active');
        document.body.style.overflow = '';
        currentCallId = null;
    }

    function loadHourlyChart(date) {
        const url = new URL(window.location.href);
        url.searchParams.set('endpoint', 'hourly');
        if (date) url.searchParams.set('date', date);
        fetch(url.toString()).then(r => r.json()).then(data => {
            const hourly = data.hourly_data || [];
            const labels = [], totals = [], success = [];
            for (let h = 0; h < 24; h++) {
                labels.push(h + ':00');
                const found = hourly.find(x => x.hour == h);
                totals.push(found ? found.total_calls : 0);
                success.push(found ? found.successful_calls : 0);
            }
            if (charts.hourly) charts.hourly.destroy();
            charts.hourly = new Chart(document.getElementById('hourlyChart').getContext('2d'), {
                type: 'line', data: { labels, datasets: [
                    { label: LANG.translations.total_calls, data: totals, borderColor: 'rgb(59,130,246)', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.4 },
                    { label: LANG.translations.successful_calls, data: success, borderColor: 'rgb(16,185,129)', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.4 }
                ]}, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
            });
        }).catch(e => console.error('Error loading hourly chart:', e));
    }

    function loadLocationHeatmap() {
        const duration = document.getElementById('heatmapDuration').value || 30;
        const url = new URL(window.location.href);
        url.searchParams.set('endpoint', 'locations');
        url.searchParams.set('minutes', duration);
        document.getElementById('heatmapLoading').style.display = 'flex';
        document.getElementById('heatmapEmpty').style.display = 'none';
        document.getElementById('locationHeatmap').style.opacity = '0';

        fetch(url.toString()).then(r => r.json()).then(data => {
            let pickups = 0, dests = 0;
            (data.locations || []).forEach(l => { if (l.type === 'pickup') pickups++; else dests++; });
            document.getElementById('pickupCount').textContent = pickups;
            document.getElementById('destinationCount').textContent = dests;

            if (!data.locations || data.locations.length === 0) {
                document.getElementById('heatmapLoading').style.display = 'none';
                document.getElementById('heatmapEmpty').style.display = 'flex';
                return;
            }
            document.getElementById('heatmapLoading').style.display = 'none';
            document.getElementById('locationHeatmap').style.opacity = '1';

            if (!window.heatmapInstance) {
                window.heatmapInstance = L.map('locationHeatmap').setView([37.9838, 23.7275], 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(window.heatmapInstance);
            }

            if (window.heatmapLayer) { window.heatmapInstance.removeLayer(window.heatmapLayer); window.heatmapLayer = null; }
            if (window.markersLayer) { window.heatmapInstance.removeLayer(window.markersLayer); window.markersLayer = null; }

            const mode = document.getElementById('heatmapMode').value;
            if (mode === 'heatmap' && window.heatPluginReady && L.heatLayer) {
                const heatData = data.locations.map(l => [l.lat, l.lng, 1]);
                window.heatmapLayer = L.heatLayer(heatData, { radius: 25, blur: 15, maxZoom: 17 }).addTo(window.heatmapInstance);
            } else if (mode === 'clusters') {
                window.markersLayer = L.markerClusterGroup();
                data.locations.forEach(l => {
                    const marker = L.marker([l.lat, l.lng]).bindPopup(`<strong>${l.type}</strong><br>${l.address || ''}`);
                    window.markersLayer.addLayer(marker);
                });
                window.heatmapInstance.addLayer(window.markersLayer);
            } else {
                window.markersLayer = L.layerGroup();
                data.locations.forEach(l => {
                    const color = l.type === 'pickup' ? 'green' : 'red';
                    const marker = L.circleMarker([l.lat, l.lng], { radius: 6, fillColor: color, color: '#fff', weight: 1, opacity: 1, fillOpacity: 0.8 }).bindPopup(`<strong>${l.type}</strong><br>${l.address || ''}`);
                    window.markersLayer.addLayer(marker);
                });
                window.markersLayer.addTo(window.heatmapInstance);
            }

            if (data.locations.length > 0) {
                const bounds = L.latLngBounds(data.locations.map(l => [l.lat, l.lng]));
                window.heatmapInstance.fitBounds(bounds, { padding: [20, 20] });
            }
        }).catch(e => {
            console.error('Error loading locations:', e);
            document.getElementById('heatmapLoading').style.display = 'none';
            document.getElementById('heatmapEmpty').style.display = 'flex';
        });
    }

    function populateDateSelect() {
        const select = document.getElementById('hourlyDateSelect');
        const today = new Date();
        for (let i = 0; i < 7; i++) {
            const d = new Date(today);
            d.setDate(d.getDate() - i);
            const opt = document.createElement('option');
            opt.value = d.toISOString().split('T')[0];
            opt.textContent = i === 0 ? LANG.translations.today : formatDateDisplay(d.toISOString());
            select.appendChild(opt);
        }
        select.addEventListener('change', function() { loadHourlyChart(this.value); });
    }

    function startRealtime() {
        if (!realtimeInterval) {
            realtimeInterval = setInterval(() => {
                loadStats();
                if (currentPage === 1) loadCalls();
                if (document.getElementById('hourlyDateSelect').selectedIndex === 0) loadHourlyChart();
                loadLocationHeatmap();
            }, 10000);
            // Update all live indicators
            document.querySelectorAll('#realtimeBtn, #mobileRealtimeBtn').forEach(el => {
                el.classList.remove('offline');
                const textSpan = el.querySelector('span:not(.live-dot)');
                if (textSpan) textSpan.textContent = LANG.translations.live;
            });
        }
    }

    function stopRealtime() {
        if (realtimeInterval) {
            clearInterval(realtimeInterval);
            realtimeInterval = null;
            // Update all live indicators
            document.querySelectorAll('#realtimeBtn, #mobileRealtimeBtn').forEach(el => {
                el.classList.add('offline');
                const textSpan = el.querySelector('span:not(.live-dot)');
                if (textSpan) textSpan.textContent = LANG.translations.stop;
            });
        }
    }

    function openFilterModal() { document.getElementById('filterModal').classList.add('active'); }
    function closeFilterModal() { document.getElementById('filterModal').classList.remove('active'); }

    function applyFilters() {
        currentFilters = {};
        const phone = document.getElementById('filterPhone').value;
        const ext = document.getElementById('filterExtension').value;
        const type = document.getElementById('filterCallType').value;
        const outcome = document.getElementById('filterOutcome').value;
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;
        if (phone) currentFilters.phone = phone;
        if (ext) currentFilters.extension = ext;
        if (type) currentFilters.call_type = type;
        if (outcome) currentFilters.outcome = outcome;
        if (dateFrom) currentFilters.date_from = dateFrom;
        if (dateTo) currentFilters.date_to = dateTo;
        currentPage = 1;
        loadCalls();
        closeFilterModal();
    }

    function clearFilters() {
        document.getElementById('filterPhone').value = '';
        document.getElementById('filterExtension').value = '';
        document.getElementById('filterCallType').value = '';
        document.getElementById('filterOutcome').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        currentFilters = {};
        currentPage = 1;
        loadCalls();
    }

    // Database cleanup confirmation
    function showCleanupConfirmation() {
        const msg = LANG.current === 'el'
            ? 'Εκκαθάριση βάσης δεδομένων\n\nΑυτό θα ενημερώσει όλες τις εκκρεμείς/ενεργές κλήσεις που είναι παλαιότερες από 3 ώρες:\n• Σε εξέλιξη → Διακοπή\n• Εκκρεμεί → Διακοπή\n\nΣυνέχεια;'
            : 'Database Cleanup\n\nThis will update all stale pending/in_progress calls older than 3 hours:\n• In Progress → Hangup\n• Pending → Hangup\n\nContinue?';

        if (confirm(msg)) {
            performCleanup();
        }
    }

    function performCleanup() {
        const btn = document.getElementById('refreshBtn');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        fetch('?endpoint=cleanup_stale')
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    const details = data.details || {};
                    const msg = LANG.current === 'el'
                        ? `Εκκαθάριση ολοκληρώθηκε!\n\nΕνημερώθηκαν ${data.updated} κλήσεις:\n• Σε εξέλιξη → Διακοπή: ${details.in_progress_to_hangup || 0}\n• Εκκρεμεί → Διακοπή: ${details.pending_to_hangup || 0}\n\nΌριο χρόνου: ${data.cutoff_time}`
                        : `Cleanup complete!\n\nUpdated ${data.updated} calls:\n• In Progress → Hangup: ${details.in_progress_to_hangup || 0}\n• Pending → Hangup: ${details.pending_to_hangup || 0}\n\nCutoff time: ${data.cutoff_time}`;
                    alert(msg);
                    loadStats(); loadCalls(); loadHourlyChart(); loadLocationHeatmap();
                } else {
                    alert(LANG.current === 'el' ? 'Σφάλμα: ' + (data.error || JSON.stringify(data)) : 'Error: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(err => {
                console.error('Cleanup error:', err);
                alert((LANG.current === 'el' ? 'Σφάλμα: ' : 'Error: ') + err.message);
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
    }

    // Export state
    let exportType = 'calls';
    let exportFormat = 'xlsx';

    function performExport() {
        const params = new URLSearchParams({ action: 'export', format: exportFormat, export_type: exportType });

        // Add filters from export modal
        const dateFrom = document.getElementById('exportDateFrom').value;
        const dateTo = document.getElementById('exportDateTo').value;
        const outcome = document.getElementById('exportOutcome').value;
        const limit = document.getElementById('exportLimit').value;

        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (outcome) params.set('outcome', outcome);
        // Always pass limit - 0 means no limit (all records)
        params.set('limit', limit || '0');

        // Also include URL params like extension
        const url = new URL(window.location.href);
        url.searchParams.forEach((v, k) => {
            if (!['endpoint', 'action', 'format', 'export_type'].includes(k) && !params.has(k)) {
                params.set(k, v);
            }
        });

        window.open('?' + params.toString(), '_blank');
        document.getElementById('exportModal').classList.remove('active');
    }

    function closeMobileDrawer() {
        const drawer = document.getElementById('mobileDrawer');
        const overlay = document.getElementById('mobileOverlay');
        drawer.classList.remove('open');
        overlay.classList.remove('active');
    }

    function openMobileDrawer() {
        const drawer = document.getElementById('mobileDrawer');
        const overlay = document.getElementById('mobileOverlay');
        drawer.classList.add('open');
        overlay.classList.add('active');
    }

    // Format date to DD MMM YYYY (e.g., "01 Dec 2025")
    function formatDateDisplay(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        const months = LANG.current === 'el'
            ? ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαΐ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ']
            : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const day = String(d.getDate()).padStart(2, '0');
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        return `${day} ${month} ${year}`;
    }

    // Format datetime to DD MMM YYYY HH:MM:SS
    function formatDateTimeDisplay(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        const months = LANG.current === 'el'
            ? ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαΐ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ']
            : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const day = String(d.getDate()).padStart(2, '0');
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        const time = d.toTimeString().slice(0, 8);
        return `${day} ${month} ${year} ${time}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Flatpickr config: display DD MMM YYYY, send YYYY-MM-DD to API
        const fpConfig = {
            dateFormat: 'Y-m-d',  // Value sent to API (unambiguous)
            altInput: true,       // Show alternate format to user
            altFormat: 'd M Y',   // Display format: "01 Dec 2025"
            locale: LANG.current === 'el' ? 'gr' : 'en'
        };
        flatpickr('.flatpickr', fpConfig);
        // Also init export date pickers
        flatpickr('#exportDateFrom', fpConfig);
        flatpickr('#exportDateTo', fpConfig);
        populateDateSelect();
        loadStats();
        loadCalls();
        loadHourlyChart();
        loadLocationHeatmap();
        startRealtime();

        // Mobile drawer handlers
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileOverlay = document.getElementById('mobileOverlay');

        mobileMenuBtn.addEventListener('click', openMobileDrawer);
        mobileOverlay.addEventListener('click', closeMobileDrawer);

        // Header button handlers (both desktop and mobile)
        const filterToggle = document.getElementById('filterToggle');
        const mobileFilterToggle = document.getElementById('mobileFilterToggle');
        const exportBtn = document.getElementById('exportBtn');
        const mobileExportBtn = document.getElementById('mobileExportBtn');
        const refreshBtn = document.getElementById('refreshBtn');
        const mobileRefreshBtn = document.getElementById('mobileRefreshBtn');
        const realtimeBtn = document.getElementById('realtimeBtn');
        const mobileRealtimeBtn = document.getElementById('mobileRealtimeBtn');

        filterToggle.addEventListener('click', () => openFilterModal());
        mobileFilterToggle.addEventListener('click', () => { closeMobileDrawer(); openFilterModal(); });

        exportBtn.addEventListener('click', () => document.getElementById('exportModal').classList.add('active'));
        mobileExportBtn.addEventListener('click', () => { closeMobileDrawer(); document.getElementById('exportModal').classList.add('active'); });

        const refreshAll = () => { loadStats(); loadCalls(); loadHourlyChart(); loadLocationHeatmap(); };
        // Refresh with cleanup support: Ctrl+Click or long-press triggers database cleanup
        function handleRefreshClick(e, isMobile = false) {
            if (e.ctrlKey || e.metaKey) {
                // Ctrl+Click (or Cmd+Click on Mac) = cleanup
                e.preventDefault();
                showCleanupConfirmation();
            } else {
                // Normal click = refresh
                if (isMobile) closeMobileDrawer();
                refreshAll();
            }
        }

        refreshBtn.addEventListener('click', (e) => handleRefreshClick(e, false));
        mobileRefreshBtn.addEventListener('click', (e) => handleRefreshClick(e, true));

        // Long-press on refresh button to clean up stale calls (for touch devices)
        let longPressTimer = null;
        const longPressDuration = 1500; // 1.5 seconds
        let longPressTriggered = false;

        function setupLongPress(btn, isMobile = false) {
            btn.addEventListener('mousedown', (e) => {
                if (e.ctrlKey || e.metaKey) return; // Skip if Ctrl is held
                longPressTriggered = false;
                longPressTimer = setTimeout(() => {
                    longPressTriggered = true;
                    showCleanupConfirmation();
                }, longPressDuration);
            });
            btn.addEventListener('mouseup', (e) => {
                clearTimeout(longPressTimer);
                if (longPressTriggered) e.preventDefault();
            });
            btn.addEventListener('mouseleave', () => clearTimeout(longPressTimer));
            // Touch support for mobile
            btn.addEventListener('touchstart', (e) => {
                longPressTriggered = false;
                longPressTimer = setTimeout(() => {
                    longPressTriggered = true;
                    e.preventDefault();
                    showCleanupConfirmation();
                }, longPressDuration);
            }, { passive: false });
            btn.addEventListener('touchend', (e) => {
                clearTimeout(longPressTimer);
                if (longPressTriggered) {
                    e.preventDefault();
                }
            });
            btn.addEventListener('touchcancel', () => clearTimeout(longPressTimer));
        }

        setupLongPress(refreshBtn, false);
        setupLongPress(mobileRefreshBtn, true);

        const toggleRealtime = () => realtimeInterval ? stopRealtime() : startRealtime();
        realtimeBtn.addEventListener('click', toggleRealtime);
        mobileRealtimeBtn.addEventListener('click', () => { toggleRealtime(); });

        // Limit select
        document.getElementById('limitSelect').addEventListener('change', function() {
            currentLimit = parseInt(this.value);
            currentPage = 1;
            loadCalls();
        });

        // Heatmap controls
        document.getElementById('heatmapDuration').addEventListener('change', loadLocationHeatmap);
        document.getElementById('heatmapMode').addEventListener('change', loadLocationHeatmap);

        // Export modal handlers
        document.getElementById('exportModalClose').addEventListener('click', () => {
            document.getElementById('exportModal').classList.remove('active');
        });

        // Export tabs
        document.querySelectorAll('.export-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.export-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                exportType = this.dataset.type;

                // Show/hide appropriate description
                document.querySelectorAll('.export-type-desc').forEach(d => d.style.display = 'none');
                document.querySelector(`.export-type-desc[data-type="${exportType}"]`).style.display = 'flex';

                // Show/hide filters section (only for calls)
                document.getElementById('exportFiltersSection').style.display = exportType === 'calls' ? 'block' : 'none';
            });
        });

        // Export format selection
        document.querySelectorAll('.export-format-option').forEach(opt => {
            opt.addEventListener('click', function() {
                document.querySelectorAll('.export-format-option').forEach(o => o.classList.remove('active'));
                this.classList.add('active');
                exportFormat = this.dataset.format;
            });
        });

        // Export Now button
        document.getElementById('exportNowBtn').addEventListener('click', performExport);

        // Modal backdrop click to close
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', function() {
                this.closest('.modal').classList.remove('active');
            });
        });
    });
    </script>
</body>
</html>
        <?php
    }
}

// Initialize and run
$analytics = new AGIAnalytics();
$analytics->handleRequest();
