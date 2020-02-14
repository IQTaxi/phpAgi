<?php
	$calltoid = $_GET['phone'];
    	$callID = $_GET['callid'];
    	$wantResponse = $_GET['response']; 
	$UNIQUEID = uniqid();

	echo 'UNIQUEID = ' . $UNIQUEID . '<br/>';

	try{

		if($wantResponse != ''){
			#$cmd1 = 'echo -e "Channel: SIP/'.$calltoid .'@berofix_trunk\\nContext: msgWithResponse\\nExtension: 31'.$callID.'" > /tmp/'.$UNIQUEID.'.call';
			$cmd1 = 'echo -e "Channel: SIP/'.$calltoid .'\\nContext: msgWithResponse\\nExtension: 31'.$callID.'" > /tmp/'.$UNIQUEID.'.call';
			$cmd2 = 'mv /tmp/'.$UNIQUEID.'.call /var/spool/asterisk/outgoing/';
			exec($cmd1);
			exec($cmd2);
        	}else{
			#$cmd1 = 'echo -e "Channel: SIP/'.$calltoid .'@berofix_trunk\\nContext: msgWithResponse\\nExtension: 31'.$callID.'" > /tmp/'.$UNIQUEID.'.call';
			$cmd1 = 'echo -e "Channel: SIP/'.$calltoid .'\\nContext: justMsg\\nExtension: 32'.$callID.'" > /tmp/'.$UNIQUEID.'.call';
			$cmd2 = 'mv /tmp/'.$UNIQUEID.'.call /var/spool/asterisk/outgoing/';
			exec($cmd1);
			exec($cmd2);		
		}

	}catch (Exception $e){
		echo 'exception 4<br/>';
		//echo $e->getMessage();
	}
?>