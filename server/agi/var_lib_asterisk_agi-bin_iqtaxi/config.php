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

// askForName configuration:
// true = Ask customer for their name during call (default behavior)
// false = Skip name collection, don't include name in registration API call

// announceName configuration:
// true = Announce customer name in greetings and confirmations (default behavior)
// false = Skip name announcement in TTS, even if name is available from API or user input
// Note: This works independently from askForName and getUser_enabled

// foreignRedirect configuration:
// true = Check if incoming number is from foreign country (not in allowed prefixes list) and redirect to operator
// false = Accept all international numbers and process normally (default behavior)
// When enabled, numbers > 10 digits that don't start with allowed prefixes (+30, +359, 0030) are redirected

// bypassWelcome configuration:
// true = Skip initial message and welcome message, immediately proceed as if user pressed 1 (ASAP mode)
// false = Play initial message and welcome message normally, wait for user input (default behavior)

// useGeocodingProxy configuration:
// true = Use proxy/caching server for geocoding requests instead of direct Google API calls
// false = Use direct Google API URLs (maps.googleapis.com and places.googleapis.com)

// geocodingProxyBaseUrl configuration:
// Base URL for the geocoding proxy server (e.g., "https://www.iqtaxi.com/IQ_WebAPIV3/api/IQTaxi/Proxy")
// The system will append "/geocode" for Geocoding API v1 and "/places" for Places API v2

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
        "boundsRestrictionMode" => null,
        "askForName" => true,
        "announceName" => true,
        "customFallCallTo" => false,
        "customFallCallToURL" => "https://www.iqtaxi.com/IQ_WebApiV3/api/asterisk/GetRedirectDrvPhoneFull/",
        "foreignRedirect" => false,
        "bypassWelcome" => false,
        "useGeocodingProxy" => true,
        "geocodingProxyBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3/api/IQTaxi/Proxy"
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
        "boundsRestrictionMode" => null,
        "askForName" => true,
        "announceName" => true,
        "customFallCallTo" => false,
        "customFallCallToURL" => "https://www.iqtaxi.com/IQ_WebApiV3/api/asterisk/GetRedirectDrvPhoneFull/",
        "foreignRedirect" => false,
        "bypassWelcome" => false,
        "useGeocodingProxy" => true,
        "geocodingProxyBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3/api/IQTaxi/Proxy"
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
        "boundsRestrictionMode" => null,
        "askForName" => true,
        "announceName" => true,
        "customFallCallTo" => false,
        "customFallCallToURL" => "https://www.iqtaxi.com/IQ_WebApiV3/api/asterisk/GetRedirectDrvPhoneFull/",
        "foreignRedirect" => false,
        "bypassWelcome" => false,
        "useGeocodingProxy" => true,
        "geocodingProxyBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3/api/IQTaxi/Proxy"
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
        "boundsRestrictionMode" => null,
        "askForName" => true,
        "announceName" => true,
        "customFallCallTo" => false,
        "customFallCallToURL" => "https://www.iqtaxi.com/IQ_WebApiV3/api/asterisk/GetRedirectDrvPhoneFull/",
        "foreignRedirect" => false,
        "bypassWelcome" => false,
        "useGeocodingProxy" => true,
        "geocodingProxyBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3/api/IQTaxi/Proxy"
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
		"askForName" => true,
		"announceName" => true,
		"requireLocality" => false,
		"foreignRedirect" => false,
	"bypassWelcome" => false,
        "useGeocodingProxy" => true,
        "geocodingProxyBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3/api/IQTaxi/Proxy"
    ]
];
}