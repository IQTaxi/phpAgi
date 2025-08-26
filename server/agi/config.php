<?php
// callbackMode configuration:
// 1 = Normal mode: reads API response message and plays TTS, then closes call
// 2 = Callback mode: sends callBackURL to server, waits for register_info.json, 
//     reads status and carNo from file, announces via TTS

// strictDropoffLocation configuration:
// false = Accept all Google Maps location types for dropoff (ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE)
// true = Only accept precise locations for dropoff (ROOFTOP, RANGE_INTERPOLATED only)
// Note: Pickup locations ALWAYS require precise location types (ROOFTOP, RANGE_INTERPOLATED)

class AGICallHandlerConfig
{
 public $globalConfiguration = [
    "1234" => [
        "name" => "Test Extension",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
        "registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
        "failCallTo" => "PJSIP/6979753028@vodafone_sip",
        "welcomePlayback" => "custom/welcome-v2",
        "tts" => "google",
        "daysValid" => 7,
        "defaultLanguage" => "el",
        "callbackMode" => 1,
        "callbackUrl" => "http://192.168.1.100/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false
    ],
    "4039" => [
        "name" => "iqtaxi.com",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
        "registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
        "failCallTo" => "PJSIP/6974888710@vodafone_sip",
        "welcomePlayback" => "custom/welcome-v3",
        "tts" => "google",
        "daysValid" => 7,
        "defaultLanguage" => "el",
        "callbackMode" => 1,
        "callbackUrl" => "http://192.168.1.100/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false
    ],
    "4033" => [
        "name" => "Hermis-Peireas",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
        "registerBaseUrl" => "http://79.129.41.206:8080/IQTaxiAPIV3",
        "failCallTo" => "PJSIP/2104115200@vodafone_sip",
        "welcomePlayback" => "custom/welcome-v3",
        "tts" => "edge-tts",
        "daysValid" => 30,
        "defaultLanguage" => "el",
        "callbackMode" => 2,
        "callbackUrl" => "http://79.129.41.206/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false
    ],
    "4036" => [
        "name" => "Cosmos",
        "googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
        "clientToken" => "a0a5d57bc105156016549b9a5de4165a",
        "registerBaseUrl" => "http://18300.fortiddns.com:8000/IQTaxiApi",
        "failCallTo" => "PJSIP/2104118300@vodafone_sip",
        "welcomePlayback" => "custom/welcome-kosmos-2",
        "tts" => "edge-tts",
        "daysValid" => 30,
        "defaultLanguage" => "el",
        "callbackMode" => 2,
        "callbackUrl" => "https://18300.fortiddns.com/callback.php",
        "repeatTimes" => 10,
        "strictDropoffLocation" => false
    ],
    "5001" => [
		"name" => "iqtaxi.com",
		"googleApiKey" => "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw",
		"clientToken" => "cc03e747a6afbbcbf8be7668acfebee5",
		"registerBaseUrl" => "https://www.iqtaxi.com/IQ_WebAPIV3",
		"failCallTo" => "IAX2/6974888710@IQTaxiGlobalAsterisk",
		"welcomePlayback" => "custom/welcome-v3",
		"tts" => "google",
		"daysValid" => 7,
		"defaultLanguage" => "el",
		"callbackMode" => 1,
		"callbackUrl" => "http://192.168.1.100/callback.php",
		"repeatTimes" => 10,
		"strictDropoffLocation" => false
    ]
];
}