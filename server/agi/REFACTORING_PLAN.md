# AGI Call Handler Refactoring Plan

## Overview
This document outlines the complete refactoring of `complete_agi_call_handler.php` (4,022 lines) into a well-organized, maintainable structure with service classes.

## Current Issues
- Single monolithic class with 4,022 lines
- Code duplication in confirmation flows, data collection, and status handling
- No separation of concerns (AGI, TTS, STT, geocoding, API calls all mixed)
- Magic numbers and strings scattered throughout
- Inconsistent error handling

## Refactored Structure

### 1. Constants Class (`CallHandlerConstants`)
**Purpose**: Centralize all magic numbers, status codes, and configuration values

```php
class CallHandlerConstants
{
    // DTMF Choices
    const DTMF_IMMEDIATE = '1';
    const DTMF_RESERVATION = '2';
    const DTMF_OPERATOR = '3';
    const DTMF_LANGUAGE_EN = '9';
    const DTMF_CONFIRM_YES = '0';
    const DTMF_CONFIRM_NO = '1';

    // Call Outcomes
    const OUTCOME_SUCCESS = 'success';
    const OUTCOME_HANGUP = 'hangup';
    const OUTCOME_OPERATOR_TRANSFER = 'operator_transfer';
    const OUTCOME_ERROR = 'error';
    const OUTCOME_ANONYMOUS_BLOCKED = 'anonymous_blocked';
    const OUTCOME_USER_BLOCKED = 'user_blocked';
    const OUTCOME_IN_PROGRESS = 'in_progress';

    // IQTaxi API Status Codes
    const STATUS_SEARCHING = -1;
    const STATUS_COMING = 1;
    const STATUS_ARRIVED = 2;
    const STATUS_ACCEPTED = 10;
    const STATUS_NO_TAXI = 20;
    const STATUS_CANCELLED_PASSENGER = 30;
    const STATUS_COMPLETED = 100;
    const STATUS_ON_WAY = 255;

    // Final statuses
    const FINAL_STATUSES = [30, 31, 32, 100, 255];

    // Modes
    const MODE_ALL_DISABLED = 0;
    const MODE_ASAP_ONLY = 1;
    const MODE_RESERVATION_ONLY = 2;
    const MODE_ALL_ENABLED = 3;
}
```

### 2. Logger Service (`LoggerService`)
**Purpose**: Centralized logging with UTF-8 support and unified formatting

**Methods**:
- `log($message, $level, $category)` - Main logging method
- `writeUnifiedLog()` - Write to consolidated log with emojis
- `writeIndividualLog()` - Write to per-call log
- `formatUnifiedMessage()` - Format messages with icons
- `humanizeStepName()` - Convert technical names to readable
- `isAnalyticsMessage()` - Filter out analytics noise

**Eliminates**: 200+ lines of duplicated logging code

### 3. Analytics Service (`AnalyticsService`)
**Purpose**: Real-time call analytics tracking with database and HTTP updates

**Properties**:
- `analytics_data` - Complete analytics record
- `db_connection` - Direct MySQL connection
- `call_finalized` - Prevent duplicate finalization

**Methods**:
- `trackStep($step)` - Track call flow transitions
- `setCallType($type)` - Set immediate/reservation type
- `setCallOutcome($outcome, $reason)` - Set final outcome
- `setPickupAddress($address, $lat, $lng)` - Track addresses
- `trackTTSCall($provider, $time)` - Track TTS usage
- `trackSTTCall($time)` - Track STT usage
- `trackGeocodingCall($time)` - Track geocoding
- `trackRegistrationAPICall()` - Track registration
- `finalizeCall()` - Complete analytics with totals

**Eliminates**: 400+ lines of analytics tracking scattered throughout

### 4. AGI Service (`AGIService`)
**Purpose**: Asterisk Gateway Interface communication

**Methods**:
- `command($cmd)` - Send AGI command with hangup detection
- `startMusicOnHold()` / `stopMusicOnHold()`
- `playback($file)` - Play audio file
- `hangup()` - Hang up call
- `wait($seconds)` - Wait
- `dial($number, $timeout)` - Dial number
- `channelStatus()` - Check channel status
- `readDTMF($prompt, $digits, $timeout)` - Read DTMF with hangup handling
- `readDTMFWithoutExit()` - Read DTMF without auto-exit (for retries)
- `record($filename, $timeout, $silence)` - Record audio

**Eliminates**: Duplicate AGI command handling and hangup detection logic

### 5. Language Service (`LanguageService`)
**Purpose**: Localization and sound file management

**Methods**:
- `setLanguage($lang)` / `getCurrentLanguage()`
- `getLanguageConfig()` - Get TTS/STT codes for each language
- `getSoundFile($name, $lang_override)` - Get localized sound files
- `checkSoundFileExists($path)` - Check if sound exists (WAV/MP3)
- `getText($key)` - Get localized text strings
- `getStatusMessage($type, $car_no, $status, $time)` - Get status messages

**Eliminates**: Duplicate language code mapping and text retrieval

### 6. TTS Service (`TTSService`)
**Purpose**: Text-to-speech synthesis

**Methods**:
- `synthesize($text, $output_file)` - Main TTS method (routes to provider)
- `callGoogleTTS($text, $output_file)` - Google Cloud TTS
- `callEdgeTTS($text, $output_file)` - Edge TTS
- `processAudioFile($content, $output)` - Process Google TTS audio

**Eliminates**: Duplicate TTS provider handling

### 7. STT Service (`STTService`)
**Purpose**: Speech-to-text recognition

**Methods**:
- `recognize($wav_file)` - Main STT method
- `filterProfanity($text)` - Filter inappropriate words

**Eliminates**: Scattered STT logic

### 8. Geocoding Service (`GeocodingService`)
**Purpose**: Address geocoding with Google APIs

**Methods**:
- `geocode($address, $is_pickup)` - Main geocoding (routes to API version)
- `geocodeWithGeocodingAPI()` - Google Maps Geocoding API (v1)
- `geocodeWithPlacesAPI()` - Google Places API (v1/new)
- `handleSpecialAddresses()` - Handle center, airport, etc.
- `validateLocationResult()` - Validate Geocoding API result
- `validatePlacesApiResult()` - Validate Places API result
- `removeDiacritics($text)` - Greek transliteration

**Eliminates**: 400+ lines of duplicate geocoding logic

### 9. IQTaxi API Service (`IQTaxiAPIService`)
**Purpose**: Communication with IQTaxi backend

**Methods**:
- `getUserData($phone)` - Get user information
- `parseUserData($response)` - Parse user data
- `registerCall($payload, $callback_url)` - Register taxi call
- `processRegistrationResponse()` - Process registration response
- `parseDate($text, $language)` - Parse reservation dates

**Eliminates**: Scattered API communication code

### 10. Data Collection Service (`DataCollectionService`)
**Purpose**: Collect user information via voice

**Methods**:
- `collectName()` - Collect customer name (3 retries)
- `collectPickup()` - Collect pickup address (3 retries)
- `collectDestination()` - Collect destination address (3 retries)
- `collectReservationTime()` - Collect reservation date/time (3 retries)
- `confirmReservationTime($parsed_date)` - Confirm time with user
- `selectFromMultipleDates($parsed_date)` - Let user choose from 2 dates
- `isInvalidTime($parsed_date)` - Validate time has hour/minute

**Eliminates**: 600+ lines of duplicate collection logic

### 11. Confirmation Service (`ConfirmationService`)
**Purpose**: Data confirmation and call registration

**Methods**:
- `confirmAndRegister()` - Main confirmation flow (immediate)
- `confirmReservationAndRegister()` - Reservation confirmation flow
- `generateConfirmationText()` - Generate confirmation message
- `generateReservationConfirmationText()` - Generate reservation message
- `processConfirmedCall($result)` - Handle successful registration
- `handleCallbackMode()` - Handle callback mode 2
- `handleNormalMode($result)` - Handle callback mode 1

**Eliminates**: 500+ lines of duplicate confirmation code

### 12. Status Monitoring Service (`StatusMonitoringService`)
**Purpose**: Monitor taxi status updates (callback mode)

**Methods**:
- `monitorStatusUpdates()` - Main monitoring loop
- `processStatusUpdate()` - Process status changes
- `playStatusMessage($type, $car_no, $status, $time)` - Play TTS status
- `updateLastValues()` - Track last known status

**Eliminates**: 300+ lines of status monitoring logic

### 13. Main Call Handler (`AGICallHandler`)
**Purpose**: Orchestrate call flow using services

**Reduced to ~800 lines** (from 4,022 lines)

**Core Methods**:
- `__construct()` - Initialize all services
- `runCallFlow()` - Main call orchestration
- `handleImmediateCall()` - Handle ASAP calls
- `handleReservationFlow()` - Handle reservations
- `redirectToOperator($lang_override)` - Transfer to operator
- `getInitialUserChoice()` - Get welcome menu selection
- `filterPhoneNumberPrefix($phone)` - Clean phone numbers
- `saveJson($key, $value)` - Save progress to JSON

**Key Improvements**:
- Services injected via constructor
- Clear separation of concerns
- Each method has single responsibility
- Easy to test individual services
- Easy to add new features

## Code Reduction Summary

| Component | Original Lines | Refactored Lines | Reduction |
|-----------|---------------|------------------|-----------|
| Constants | Scattered | 80 | +80 (new) |
| Logger | ~300 (duplicated) | 150 | -150 |
| Analytics | ~400 (scattered) | 200 | -200 |
| AGI Communication | ~200 (duplicated) | 120 | -80 |
| Language/Localization | ~300 (duplicated) | 150 | -150 |
| TTS Service | ~200 (duplicated) | 120 | -80 |
| STT Service | ~150 (duplicated) | 80 | -70 |
| Geocoding | ~400 (duplicated) | 250 | -150 |
| IQTaxi API | ~300 (scattered) | 150 | -150 |
| Data Collection | ~600 (duplicated) | 350 | -250 |
| Confirmation | ~500 (duplicated) | 300 | -200 |
| Status Monitoring | ~300 (duplicated) | 180 | -120 |
| Main Handler | ~4022 | 800 | -3222 |
| **TOTAL** | **4,022** | **~3,000** | **-1,022** |

## Benefits

### Maintainability
- Each service has single responsibility
- Easy to locate bugs (e.g., "geocoding issue" → check `GeocodingService`)
- Changes to one service don't affect others

### Testability
- Each service can be unit tested independently
- Mock services for integration tests
- No need to test entire 4,000 line class

### Readability
- Clear service names (`TTSService`, not buried in main class)
- Consistent method naming (`synthesize()`, `recognize()`, `geocode()`)
- Constants with semantic names (`STATUS_ACCEPTED` not `10`)

### Extensibility
- Easy to add new TTS provider (extend `TTSService`)
- Easy to add new geocoding API (extend `GeocodingService`)
- Easy to add new language (update `LanguageService`)

### Performance
- No change (same algorithms, just organized)
- Possible improvement from service reuse

## Implementation Steps

1. **Phase 1**: Extract Constants
   - Create `CallHandlerConstants` class
   - Replace all magic numbers in original

2. **Phase 2**: Extract Utilities
   - Create `LoggerService`
   - Create `AnalyticsService`
   - Update main handler to use services

3. **Phase 3**: Extract External Services
   - Create `AGIService`
   - Create `TTSService`
   - Create `STTService`
   - Create `GeocodingService`
   - Create `IQTaxiAPIService`

4. **Phase 4**: Extract Business Logic
   - Create `LanguageService`
   - Create `DataCollectionService`
   - Create `ConfirmationService`
   - Create `StatusMonitoringService`

5. **Phase 5**: Refactor Main Handler
   - Inject all services
   - Remove extracted code
   - Update method calls to use services
   - Simplify flow orchestration

6. **Phase 6**: Testing
   - PHP syntax validation: `php -l complete_agi_call_handler2.php`
   - Compare file sizes
   - Test with sample calls
   - Verify analytics still work
   - Verify all call outcomes function correctly

## File Structure

```
var_lib_asterisk_agi-bin_iqtaxi/
├── complete_agi_call_handler.php      (Original - 4,022 lines)
├── complete_agi_call_handler2.php     (Refactored - ~3,000 lines)
├── config.php                          (Existing config)
└── REFACTORING_PLAN.md                (This document)
```

## Backward Compatibility

- **100% functionally equivalent** to original
- Same AGI commands
- Same API calls
- Same analytics structure
- Same log format
- Same configuration format
- Can be deployed as drop-in replacement by renaming

## Next Steps

1. Review this refactoring plan
2. Approve service structure
3. Implement refactored version
4. Test with production scenarios
5. Deploy to staging
6. Monitor for issues
7. Deploy to production

## Notes

- All 4,022 lines of functionality preserved
- No behavior changes
- Only structural improvements
- Can revert to original if issues arise
- Original file kept as backup
