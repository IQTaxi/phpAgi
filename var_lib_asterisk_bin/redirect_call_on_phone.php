#!/usr/bin/php -q
<?php
  set_time_limit(30);
  require('phpagi.php');
  error_reporting(E_ALL);  

  $mPhone= trim($argv[1]);
  $agi = new AGI();
  $agi->answer();

  $returnedPhoneToCall = file_get_contents("http://192.168.24.24/Webapi/api/Asterisk/GetRedirectPhone/".$mPhone);
# $returnedPhoneToCall = '6978527553@berofix_trunk';

  $mTempVar = "http://192.168.24.24/Webapi/api/Asterisk/GetRedirectPhone/".$mPhone;
  $agi->exec('NoOp', '@@'.$returnedPhoneToCall.'@@');
  $agi->exec('NoOp', $mPhone);
  $agi->exec('NoOp', $mTempVar);

  $agi->exec('dial', 'SIP/'.${returnedPhoneToCall});
#  $agi->exec('dial', 'SIP/26');

  $agi->hangup();
?>