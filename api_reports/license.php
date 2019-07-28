<?php
header("Access-Control-Allow-Origin: *");
	
date_default_timezone_set("Asia/Manila");

function query_time_server ($timeserver, $socket)
{
    $fp = fsockopen($timeserver,$socket,$err,$errstr,5);
    if($fp)
    {
        fputs($fp, "n");
        $timevalue = fread($fp, 49);
        fclose($fp); 
    }
    else
    {
        $timevalue = " ";
    }

    $ret = array();
    $ret[] = $timevalue;
    $ret[] = $err;     
    $ret[] = $errstr;  
    return($ret);
} 

$app_name = $_POST['app_name'];

$format = 'Y-m-d';
$timeserver = "ntp.pads.ufrj.br";
$timercvd = query_time_server($timeserver, 37);

$timevalue = bin2hex($timercvd[0]);
$timevalue = abs(HexDec('7fffffff') - HexDec($timevalue) - HexDec('7fffffff'));
$tmestamp = $timevalue - 2208988800;

$curr_date = date($format, $tmestamp);

$status['expired'] = true;

if ( $app_name == 'file_encrypter' ) {
	if ( $curr_date < '2018-12-27' ) {
		$status['expired'] = false;
	}
}

echo json_encode($status);


