#!/usr/bin/php -q
<?php
set_time_limit(30);
error_reporting(E_ALL);

$logFile = '/var/log/asterisk/redirect_call_debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " === Script started ===\n", FILE_APPEND);
file_put_contents($logFile, "argv: " . print_r($argv, true) . "\n", FILE_APPEND);

ob_start();
error_reporting(0);
require('/var/lib/asterisk/agi-bin/phpagi.php');
file_put_contents($logFile, "phpagi.php loaded OK\n", FILE_APPEND);

$agi = new AGI();
$ob_output = ob_get_clean();
error_reporting(E_ALL);
file_put_contents($logFile, "AGI object created\n", FILE_APPEND);
if ($ob_output) {
  file_put_contents($logFile, "Captured output during AGI init: [$ob_output]\n", FILE_APPEND);
}
file_put_contents($logFile, "AGI request keys: " . implode(', ', array_keys($agi->request)) . "\n", FILE_APPEND);
file_put_contents($logFile, "AGI request: " . print_r($agi->request, true) . "\n", FILE_APPEND);

$mPhone = isset($agi->request['agi_arg_1']) ? trim($agi->request['agi_arg_1']) : '';
file_put_contents($logFile, "mPhone from agi_arg_1: [$mPhone]\n", FILE_APPEND);

if ($mPhone === '') {
  file_put_contents($logFile, "ERROR: No phone argument received\n", FILE_APPEND);
  $agi->hangup();
  exit(0);
}

$modPhone = $mPhone;
#  $modPhone  = "+302310208100";

if (strpos($modPhone, "+30") !== false) {
  $modPhone = str_replace("+30", "", $modPhone);
  #      $agi->exec('NoOp', '@@@'.$modPhone);
}
if (strpos($modPhone, "+") !== false) {
  $modPhone = str_replace("+", "", $modPhone);
  #      $agi->exec('NoOp', '!!!'.$modPhone);
}

file_put_contents($logFile, "modPhone after cleanup: [$modPhone]\n", FILE_APPEND);
$agi->exec('NoOp', 'Calling API for phone: ' . $modPhone);

$apiUrl = "http://www.iqtaxi.cn/IQTaxiAPIV3/api/Asterisk/GetRedirectPhone/" . $modPhone . "/true/70";
file_put_contents($logFile, "Calling API: $apiUrl\n", FILE_APPEND);

$returnedPhoneToCall = @file_get_contents($apiUrl);
file_put_contents($logFile, "API response: [$returnedPhoneToCall]\n", FILE_APPEND);
# $returnedPhoneToCall = '6978527553@berofix_trunk';

if ($returnedPhoneToCall === false || $returnedPhoneToCall === '') {
  file_put_contents($logFile, "ERROR: API failed or returned empty\n", FILE_APPEND);
  $agi->exec('NoOp', 'ERROR: API returned no phone number for ' . $modPhone);
  $agi->hangup();
  exit(0);
}

$decoded = json_decode($returnedPhoneToCall);
if ($decoded !== null) {
  $returnedPhoneToCall = $decoded;
}
$returnedPhoneToCall = trim($returnedPhoneToCall);
# $returnedPhoneToCall = str_replace('@vodafone', '@vodafone_sip', $returnedPhoneToCall);
$agi->exec('NoOp', '@@' . $returnedPhoneToCall . '@@');
$agi->exec('NoOp', $modPhone);

do {
  # ,30,r -> 
  # 30 :dial timeout 
  # r  :hear ringtone
  #    m  :hear music on hold
  $agi->exec('dial', 'SIP/' . $returnedPhoneToCall . ',30,r');
  #    $agi->exec('dial', 'SIP/26');
  #  $agi->exec('dial', 'SIP/2310314194@berofix_trunk');
  #    $agi->exec('dial', 'SIP/6978527553@berofix_trunk');
  $dialstatus = $agi->get_variable('DIALSTATUS');

  if ($dialstatus['data'] != 'ANSWER') {
    $agi->set_music(true, "myholdclass"); //To start playing music while waiting
    sleep(3); //Time before each call
    $agi->exec('NoOp', 'Retry to call because it is : ' . $dialstatus['data']);
  }
  $agi->exec('NoOp', $dialstatus);
} while ($dialstatus['data'] != 'ANSWER');

$agi->hangup();
?>