<?php
// callbackMode configuration:
// 1 = Normal mode: reads API response message and plays TTS, then closes call
// 2 = Callback mode: sends callBackURL to server, waits for register_info.json, 
//     reads status and carNo from file, announces via TTS

// strictDropoffLocation configuration:
// false = Accept all Google Maps location types for dropoff (ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE)
// true = Only accept precise locations for dropoff (ROOFTOP, RANGE_INTERPOLATED only)
// Note: Pickup locations ALWAYS require precise location types (ROOFTOP, RANGE_INTERPOLATED)

// geocodingApiVersion configuration:
// 1 = Use Google Maps Geocoding API (current/legacy version)
// 2 = Use Google Places API v1 (new version with searchText endpoint)

// bounds configuration:
// null = No geographic bounds validation (default behavior)
// Object with north, south, east, west coordinates = Post-processing validation bounds
// Example: ["north" => 38.1, "south" => 37.8, "east" => 24.0, "west" => 23.5]
// Used for post-processing validation to reject results outside bounds
//
// centerBias configuration:
// null = No center bias (default behavior)
// Object with lat, lng, radius = Bias API results toward a center point
// Example: ["lat" => 37.9755, "lng" => 23.7348, "radius" => 50000] (radius in meters)
// Used by both Google Geocoding API v1 and Places API v2 to bias location results
//
// boundsRestrictionMode configuration:
// null or 0 = No restriction (bounds and centerBias are not applied)
// 1 = Apply bounds and centerBias only to pickup location
// 2 = Apply bounds and centerBias only to dropoff location
// 3 = Apply bounds and centerBias to both pickup and dropoff locations

class AGICallHandlerConfig
{
 public $globalConfiguration = [
    "1234" => [
        "name" => "Test Extension",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
        "registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
        "failCallTo" => "PJSIP/6979753028@vodafone_sip",
        "soundPath" => "/var/sounds/iqtaxi",
        "tts" => "google",
        "daysValid" => 7,
        "defaultLanguage" => "el",
        "callbackMode" => 1,
        "callbackUrl" => "http://192.168.1.100/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false,
        "geocodingApiVersion" => 1,
        "initialMessageSound" => "strike",
        "redirectToOperator" => false,
        "autoCallCentersMode" => 3,
        "maxRetries" => 5,
        "bounds" => null,
        "centerBias" => null,
        "boundsRestrictionMode" => null
    ],
    "4039" => [
        "name" => "iqtaxi.com",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
        "registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
        "failCallTo" => "PJSIP/6974888710@vodafone_sip",
        "soundPath" => "/var/sounds/iqtaxi",
        "tts" => "google",
        "daysValid" => 7,
        "defaultLanguage" => "el",
        "callbackMode" => 1,
        "callbackUrl" => "http://192.168.1.100/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false,
        "geocodingApiVersion" => 1,
        "initialMessageSound" => "strike",
        "redirectToOperator" => false,
        "autoCallCentersMode" => 3,
        "maxRetries" => 5,
        "bounds" => null,
        "centerBias" => null,
        "boundsRestrictionMode" => null
    ],
    "4033" => [
        "name" => "Hermis-Peireas",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
        "registerBaseUrl" => "http://79.129.41.206:8080/IQTaxiAPIV3",
        "failCallTo" => "PJSIP/2104115200@vodafone_sip",
        "soundPath" => "/var/sounds/iqtaxi",
        "tts" => "edge-tts",
        "daysValid" => 30,
        "defaultLanguage" => "el",
        "callbackMode" => 2,
        "callbackUrl" => "http://79.129.41.206/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false,
        "geocodingApiVersion" => 1,
        "initialMessageSound" => "strike",
        "redirectToOperator" => false,
        "autoCallCentersMode" => 3,
        "maxRetries" => 5,
        "bounds" => null,
        "centerBias" => null,
        "boundsRestrictionMode" => null
    ],
    "4036" => [
        "name" => "Cosmos",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "a0a5d57bc105156016549b9a5de4165a",
        "registerBaseUrl" => "http://18300.fortiddns.com:8000/IQTaxiApi",
        "failCallTo" => "PJSIP/2104118300@vodafone_sip",
        "soundPath" => "/var/sounds/iqtaxi",
        "tts" => "edge-tts",
        "daysValid" => 30,
        "defaultLanguage" => "el",
        "callbackMode" => 2,
        "callbackUrl" => "https://18300.fortiddns.com/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false,
        "geocodingApiVersion" => 1,
        "initialMessageSound" => "strike",
        "redirectToOperator" => false,
        "autoCallCentersMode" => 3,
        "maxRetries" => 5,
        "bounds" => null,
        "centerBias" => null,
        "boundsRestrictionMode" => null
    ],
    "5001" => [
		"name" => "iqtaxi.com",
		"googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
		"clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
		"registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
		"failCallTo" => "IAX2/6974888710@IQTaxiGlobalAsterisk",
		"soundPath" => "/var/sounds/iqtaxi",
		"tts" => "google",
		"daysValid" => 7,
		"defaultLanguage" => "el",
		"callbackMode" => 1,
		"callbackUrl" => "http://192.168.1.100/callback.php",
		"repeatTimes" => 10,
		"strictDropoffLocation" => false,
		"geocodingApiVersion" => 1,
		"initialMessageSound" => "strike",
		"redirectToOperator" => false,
		"autoCallCentersMode" => 3,
		"maxRetries" => 5,
		"bounds" => null,
		"centerBias" => null,
		"boundsRestrictionMode" => null,
		"requireLocality" => false
    ]
];
}