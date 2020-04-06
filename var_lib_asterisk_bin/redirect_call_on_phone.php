#!/usr/bin/php -q
<?php
  set_time_limit(30);
  require('phpagi.php');
  error_reporting(E_ALL);  

  $mPhone= trim($argv[1]);
  $agi = new AGI();
  $agi->answer();

  $modPhone = $mPhone;
#  $modPhone  = "+302310208100"; 

  if (strpos($modPhone, "+30") !== false) {
      $modPhone = str_replace("+30","",$modPhone);
#      $agi->exec('NoOp', '@@@'.$modPhone);
  }
  if (strpos($modPhone, "+") !== false) {
      $modPhone = str_replace("+","",$modPhone);
#      $agi->exec('NoOp', '!!!'.$modPhone);
  }

  $returnedPhoneToCall = file_get_contents("http://192.168.24.24/Webapi/api/Asterisk/GetRedirectPhone/".$modPhone);
# $returnedPhoneToCall = '6978527553@berofix_trunk';

  $agi->exec('NoOp', '@@'.$returnedPhoneToCall.'@@');
  $agi->exec('NoOp', $modPhone);
  
  do {
#    $agi->exec('dial', 'SIP/'.${returnedPhoneToCall});
#    $agi->exec('dial', 'SIP/26');
    $agi->exec('dial', 'SIP/2310314194@berofix_trunk');
#    $agi->exec('dial', 'SIP/6978527553@berofix_trunk');
    $dialstatus = $agi->get_variable('DIALSTATUS');

    if($dialstatus[data] != 'ANSWER'){
      $agi->set_music(true,"myholdclass"); //To start playing music while waiting
      sleep(3); //Time before each call
      $agi->exec('NoOp', 'Retry to call because it is : '.$dialstatus[data]); 
    } 
    $agi->exec('NoOp', $dialstatus);
  }while ( $dialstatus[data] != 'ANSWER' );
  
  $agi->hangup();
?>