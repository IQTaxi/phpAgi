# Geocoding and Address Validation Guide

This document explains how the IQTaxi AGI system validates addresses using Google's geocoding APIs.

## Test Curl Commands for Athens Address

### 1. Google Maps Geocoding API v1 (geocodingApiVersion = 1)
```bash
# Greek address - URL encoded for proper transmission
curl -X GET "https://maps.googleapis.com/maps/api/geocode/json?address=%CE%A3%CF%84%CE%B1%CE%B4%CE%AF%CE%BF%CF%85%2010%20%CE%91%CE%B8%CE%AE%CE%BD%CE%B1&key=AIzaSyDeu4sIzoLUnS8xvSnUOn5zZGnOIaajgDY&language=el-GR"

# Decoded: address=Σταδίου 10 Αθήνα
```

### 2. Google Places API v1 (geocodingApiVersion = 2)

**For Windows CMD:**
```cmd
curl -X POST "https://places.googleapis.com/v1/places:searchText" -H "Content-Type: application/json" -H "X-Goog-Api-Key: AIzaSyDeu4sIzoLUnS8xvSnUOn5zZGnOIaajgDY" -H "X-Goog-FieldMask: places.displayName,places.formattedAddress,places.location,places.addressComponents" -d "{\"textQuery\":\"Σταδίου 10 Αθήνα\",\"languageCode\":\"el\",\"regionCode\":\"GR\",\"maxResultCount\":1}"
```

**For Linux/Mac Bash:**
```bash
curl -X POST "https://places.googleapis.com/v1/places:searchText" \
  -H "Content-Type: application/json" \
  -H "X-Goog-Api-Key: AIzaSyDeu4sIzoLUnS8xvSnUOn5zZGnOIaajgDY" \
  -H "X-Goog-FieldMask: places.displayName,places.formattedAddress,places.location,places.addressComponents" \
  -d '{
    "textQuery": "Σταδίου 10 Αθήνα",
    "languageCode": "el",
    "regionCode": "GR",
    "maxResultCount": 1
  }'
```

## Validation Logic in complete_agi_call_handler.php

### How the System Validates Addresses:

#### 1. **Location Precision Types** (lines ~1920-1927, ~2006-2020):
The system categorizes addresses by precision:

**For Google Maps Geocoding API v1:**
- Uses Google's actual `location_type` field from response
- **ROOFTOP**: Exact address with street number (most precise)
- **RANGE_INTERPOLATED**: Street-level precision
- **GEOMETRIC_CENTER**: Area/neighborhood level
- **APPROXIMATE**: City/region level (least precise)

**For Google Places API v1:**
- Google doesn't provide `location_type`, so system determines precision by analyzing `addressComponents`:
- **"ROOFTOP"**: Has both `street_number` + `route` components (most precise)
- **"RANGE_INTERPOLATED"**: Has `route` component only (street-level)
- **"GEOMETRIC_CENTER"**: Neither component present (area-level)

#### 2. **Validation Rules**:

**For PICKUP addresses** (lines ~1933-1936, ~2007-2011):
- **ALWAYS STRICT**: Only accepts `ROOFTOP` or `RANGE_INTERPOLATED`
- Rejects `GEOMETRIC_CENTER` and `APPROXIMATE`
- Reason: Driver needs precise location to find passenger

**For DROPOFF addresses** (lines ~1939-1944, ~2014-2020):
- **Depends on config**: `strictDropoffLocation` setting
- If `strictDropoffLocation = true`: Only `ROOFTOP` or `RANGE_INTERPOLATED`
- If `strictDropoffLocation = false`: Accepts all types including `GEOMETRIC_CENTER`

#### 3. **What Happens After Geocoding**:

**SUCCESS Path** (address accepted):
```php
$this->logMessage("Location accepted - type: {$location_type}, address: {$formatted_address}");
return [
    "address" => $formatted_address,
    "location_type" => $location_type,
    "latLng" => ["lat" => $lat, "lng" => $lng]
];
```

**FAILURE Path** (address rejected):
```php
$this->logMessage("Location rejected - type: {$location_type}, address: {$address}");
return null;
```

#### 4. **Next Steps After Validation**:

**If geocoding succeeds**:
- Address is stored in `$this->pickup_result` or `$this->dest_result`
- Coordinates stored in `$this->pickup_location` or `$this->dest_location`
- System proceeds to next collection step or confirmation

**If geocoding fails**:
- System plays "invalid_address" sound
- Allows user to retry (up to 3 attempts)
- After 3 failures, transfers to operator

#### 5. **Real API Responses and Used Fields**:

**Google Geocoding API v1** returns (fields marked **★** are used by the system):
```json
{
  "results": [{
    "formatted_address": "Σταδίου 10, Αθήνα 105 64, Ελλάδα",  // ★ USED for address storage
    "geometry": {
      "location": {
        "lat": 37.9779407,     // ★ USED for coordinates
        "lng": 23.7335347      // ★ USED for coordinates
      },
      "location_type": "ROOFTOP",  // ★ USED for validation (direct from Google)
      "viewport": { /* not used */ }
    },
    "place_id": "ChIJG8kQ_Tu9oRQRoz05TbQHErA",  // not used
    "types": ["street_address", "subpremise"],  // not used
    "address_components": [ /* not used for validation */ ]
  }],
  "status": "OK"
}
```

**Google Places API v1** returns (fields marked **★** are used by the system):
```json
{
  "places": [{
    "formattedAddress": "Σταδίου 10, Αθήνα 105 64",  // ★ USED for address storage
    "location": {
      "latitude": 37.9779407,   // ★ USED for coordinates
      "longitude": 23.733534700000003  // ★ USED for coordinates
    },
    "addressComponents": [  // ★ USED to determine precision
      {
        "longText": "10",
        "types": ["street_number"]  // ★ CHECKED for "ROOFTOP" precision
      },
      {
        "longText": "Σταδίου",
        "types": ["route"]          // ★ CHECKED for "RANGE_INTERPOLATED" precision
      },
      {
        "longText": "Αθήνα",
        "types": ["locality", "political"]  // not used for validation
      }
      /* other components not used for validation */
    ],
    "displayName": { /* not used */ }
  }]
}
```

**System Logic for Places API:**
- If has `street_number` + `route` → treated as "ROOFTOP"
- If has `route` only → treated as "RANGE_INTERPOLATED"
- If neither → treated as "GEOMETRIC_CENTER"

## Configuration Settings

### geocodingApiVersion
- `1` = Use Google Maps Geocoding API (legacy)
- `2` = Use Google Places API v1 (new)

### strictDropoffLocation
- `true` = Only accept precise locations for dropoff
  - **Geocoding API**: ROOFTOP, RANGE_INTERPOLATED only
  - **Places API**: Must have `street_number`+`route` OR `route` component
- `false` = Accept all location types for dropoff
  - **Geocoding API**: All types including GEOMETRIC_CENTER, APPROXIMATE
  - **Places API**: Accept addresses even without street components

### boundsRestrictionMode
- `0` or `null` = No geographic bounds validation
- `1` = Apply bounds only to pickup location
- `2` = Apply bounds only to dropoff location
- `3` = Apply bounds to both pickup and dropoff locations

## Flow Summary

1. User provides address via voice
2. System calls appropriate geocoding API based on `geocodingApiVersion`
3. API returns coordinates and precision type
4. System validates precision against rules (pickup always strict, dropoff depends on config)
5. If accepted: stores address and proceeds to next step
6. If rejected: plays error sound and allows retry (up to 3 attempts)
7. After 3 failures: transfers call to human operator

## Common Issues and Notes

### UTF-8 Encoding in Terminal Testing
- **Geocoding API**: Greek characters must be URL encoded in the query string
  - Example: `Σταδίου 10 Αθήνα` becomes `%CE%A3%CF%84%CE%B1%CE%B4%CE%AF%CE%BF%CF%85%2010%20%CE%91%CE%B8%CE%AE%CE%BD%CE%B1`
- **Places API**: Greek characters can be used directly in JSON payload
  - JSON handles UTF-8 properly when Content-Type is `application/json`
- The system handles Greek text natively in both APIs during actual operation

### ⚠️ API Behavior Differences Found in Testing
**Google Maps Geocoding API** with `Σταδίου 10 Αθήνα`:
- ✅ **Correctly returns**: `Σταδίου 10, Αθήνα 105 64, Ελλάδα` (Athens)
- ✅ **Location type**: `ROOFTOP`
- ✅ **Coordinates**: `37.9779407, 23.7335347`

**Google Places API** with `Σταδίου 10 Αθήνα`:
- ❌ **Incorrectly returns**: `Βογατσικού 10, Θεσσαλονίκη 546 22` (Thessaloniki!)
- ❌ **Wrong city entirely** - returns a business instead of the street address
- This shows Places API has different search logic and may prioritize businesses over street addresses

**Recommendation**: For address validation testing, use street addresses that don't conflict with business names, or use more specific addresses like `Σταδίου 10, Κέντρο Αθήνας, Ελλάδα`.

### API Key and Field Mask Requirements
- **Google Geocoding API**: Requires only API key parameter
- **Google Places API**: Requires API key AND specific field mask in header
- **Field Mask**: `places.displayName,places.formattedAddress,places.location,places.addressComponents`

### Regional Settings
- Both APIs use `regionCode: "GR"` to bias results toward Greece
- Language code `"el"` ensures Greek language responses when available

### System Behavior Summary
- **Pickup addresses**: ALWAYS require precise location (ROOFTOP/RANGE_INTERPOLATED equivalent)
- **Dropoff addresses**: Precision requirements depend on `strictDropoffLocation` config
- **Validation failures**: 3 retry attempts before operator transfer

The system checks the precision, validates against the rules, and either accepts the address (moving to next step) or rejects it (asking user to try again).