<?php
	$calltoid = $_GET['phone'];
    	$callID = $_GET['callid'];
	$UNIQUEID = uniqid();

	echo 'UNIQUEID = ' . $UNIQUEID . '<br/>';

	try{
		$cmd1 = 'echo -e "Channel: SIP/'.$calltoid .'@berofix_trunk\\nContext: justMsg\\nExtension: 32'.$callID.'-'.$calltoid.'\\nMaxRetries: 3\\nRetryTime: 60\\nWaitTime: 30" > /tmp/'.$UNIQUEID.'.call';
		#$cmd1 = 'echo -e "Channel: SIP/'.$calltoid .'\\nContext: justMsg\\nExtension: 32'.$callID.'-'.$calltoid.'\\nMaxRetries: 3\\nRetryTime: 60\\nWaitTime: 30" > /tmp/'.$UNIQUEID.'.call';
		$cmd2 = 'mv /tmp/'.$UNIQUEID.'.call /var/spool/asterisk/outgoing/';
		exec($cmd1);
		exec($cmd2);
	}catch (Exception $e){
		echo 'exception 4<br/>';
		//echo $e->getMessage();
	}
?>
