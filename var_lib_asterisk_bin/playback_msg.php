#!/usr/bin/php -q
<?php
  set_time_limit(30);
  require('phpagi.php');
  error_reporting(E_ALL);  
  
  $mExtention=$argv[1];
  $mChannel=$argv[2];
  $hashPos = strpos($mChannel, '-');
  $mCh = substr($mChannel, 4, $hashPos-4);
  $mEx = substr($mExtention, 3, strlen($mExtention));
  $mUrl = "http://192.168.24.27/webapi/api/asterisk/UpdateDBForMessage/$mEx/$mCh";
  $response = file_get_contents($mUrl);

  $agi = new AGI();
  $agi->answer();
  
  #$cid = $agi->parse_callerid();
  $agi->exec('Playback','custom/taxi_arrived_tts');
  #$agi->exec('Playback','beep');	
  #$agi->exec('SayDigits',2);

  #$agi->exec('dial', "SIP/27");
  
  $agi->hangup();
?>