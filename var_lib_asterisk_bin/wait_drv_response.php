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

  $agi = new AGI();
  $agi->answer();
  
  #$cid = $agi->parse_callerid();
  #$agi->exec('Playback','custom/taxi_arrived_tts');
  #$agi->exec('SayDigits',2);
  $result = $agi->get_data('custom/taxi_arrived_tts', 1000, 20);
  $keys = $result['result'];

  while($keys != '1' && $keys != '2'){
    $result = $agi->get_data('beep', 1000, 20);
    $keys = $result['result'];
    #$agi->exec('SayDigits',$keys);
  } 

  #$agi->exec('dial', "SIP/27");

  $mUrl = "http://192.168.24.27/webapi/api/asterisk/get/$mCh/$mEx/$keys";
  $response = file_get_contents($mUrl);
  
  $agi->hangup();
?>