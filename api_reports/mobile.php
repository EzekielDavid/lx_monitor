<?php

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





if ( isset($_GET['action']) )
{
	$action = $_GET['action'];
	if ( $action == "download_image" )
	{
		header('Content-type: image/jpeg');
		echo file_get_contents($_GET['path']);
	}
}


else
{

	header("Access-Control-Allow-Origin: *");


	$action = $_POST['action'];
	if ( $action == "login" || $action == "test" || $action == "get_users" || $action == "logout" || $action == "check_online_users_count" || $action == "get_user_status" )
	{
		$db = "Management";
	}
	else
	{
		$db = "TPS";
	}

	$file = fopen("logs.txt", "a");
	try {
		$conn = new PDO("sqlsrv:server=192.168.30.28,1433;Database=$db","sa","pr0ject Zer0");
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	} catch (Exception $e) {
		die(print_r($e->getMessage()));
		fwrite($file,$e->getMessage());
	}

	if ( $action == "error_report" )
	{
		$about = $_POST['about'];
		$message = $_POST['message'];
		
		fwrite($file, $about . "-----" . $message);
	}
	else if ( $action == "get_time" )
	{
		$format = $_POST['format'];
		$timeserver = "utcnist.colorado.edu";
		$timercvd = query_time_server($timeserver, 37);

		$timevalue = bin2hex($timercvd[0]);
		$timevalue = abs(HexDec('7fffffff') - HexDec($timevalue) - HexDec('7fffffff'));
		$tmestamp = $timevalue - 2208988800;
		//h:i:s
		$curr_date = date($format, $tmestamp);
		echo $curr_date;
	}
	else if ( $action == "get_users" )
	{
		
		$stmt = $conn->query("select CourierID as id, EmployeeName as name from [Management.CourierDriver] ORDER BY  name");
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		echo json_encode($res);
	}
	else if ( $action == "check_trip" )
	{
		$courier_id = $_POST['courier_id'];
		
		$stmt = $conn->query("select waybill from CourierCurrentTrip where on_trip = 1 and CourierID = '$courier_id'");
		
		fwrite($file, "select waybill from CourierCurrentTrip where on_trip = 1 and CourierID = '$courier_id'");
		
		
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
			 
		echo json_encode($res, JSON_FORCE_OBJECT);
	}
	else if ( $action == "check_online_users_count" )
	{
		$stmt = $conn->query("select count(*) as online_users_count from [Management.CourierDriver] where is_login = 1");
		
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		echo json_encode($res[0], JSON_FORCE_OBJECT);
	}
	else if ( $action == "logout" )
	{
		$id = $_POST['id'];
		$stmt = $conn->prepare("update [Management.CourierDriver] set is_login = 0 where CourierID = '$id'");
		
		$result['result'] = $stmt->execute();
		
		
		echo json_encode($result, JSON_FORCE_OBJECT);
	}
	else if ( $action == "cancel_trip" )
	{
		$courier_id = $_POST['courier_id'];
		$stmt = $conn->prepare("update CourierCurrentTrip set waybill = null, on_trip = 0 where CourierID = '$courier_id'");
		
		fwrite($file, "update CourierCurrentTrip set waybill = null, on_trip = 0 where CourierID = '$courier_id'");
		
		echo $stmt->execute();
		
	} else if ( $action == "alarm" ) {
		
		$courier_id = $_POST['courier_id'];
		$status = $_POST['status'];
		$longitude = $_POST['longitude'];
		$latitude = $_POST['latitude'];
			
		$stmt = $conn->prepare("insert into CourierDriverRealtimeLocation (CourierDriverID, longitude, latitude) values($courier_id, $longitude, $latitude)");	
		$result['result'] = $stmt->execute();

		fwrite($file, "insert into CourierDriverRealtimeLocation (CourierDriverID, longitude, latitude) values($courier_id, $longitude, $latitude)");
	
		$id = $conn->lastInsertId();

		$stmt = $conn->prepare("insert into alarm(CourierDriverID, status) values('$id', '$status')");
		
		fwrite($file, "insert into alarm(CourierDriverID, status) values('$id', '$status')");
		
		$result['result'] = $result['result'] && $stmt->execute();

		 echo "1";
		
	}
	else if ( $action == "login" )
	{
		$login_type = $_POST['login_type'];
		if ( $login_type == "facerecog" )
		{
			$image = $_POST['image'];
			$unique_id = rand(1000, 9999) . rand(1000, 9999);
			$filename = 'cached_images/'.$unique_id.'.jpg';
			$image_file = fopen($filename, 'w');
			fwrite($image_file, base64_decode($image));
			fclose($image_file);
			$image_rotate = imagecreatefromjpeg($filename);
			$rotated_image = imagerotate($image_rotate, 90, 0);
			imagejpeg($rotated_image, $filename);
			imagedestroy($image_rotate);
			imagedestroy($rotated_image);
			
			fwrite($file, "select CourierID, EmployeeName, Picture from [Management.CourierDriver] where Picture != 'NULL'\r\n\0");
			$stmt = $conn->query("select CourierID, EmployeeName, Picture from [Management.CourierDriver] where Picture != 'NULL'");
			$found = false;
			
			
			fwrite($file, "done querying in face recognition\n\0");
			while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
			{
				$image = $res['Picture'];
				fwrite($file, $image);
				$result = trim(shell_exec("python face_matcher.py $filename $image"));
				fwrite($file, $result);
				if ( $result === "True" )
				{
					
					$raw_image_data = file_get_contents($image);
					$res['courier_image'] = base64_encode($raw_image_data);
					fwrite($file, "returning true");
					$found = true;
					echo json_encode($res, JSON_FORCE_OBJECT);
					break;
				}
				
			}
			if($found == false)
			{
				fwrite($file, "no matched {}\r\n\0");
				echo "{}";
			}
			
			fwrite($file, "done facerecog");
			
		}
		else if ( $login_type == "userpass" )
		{
			$user = $_POST['user'];
			$pass = $_POST['pass'];
			fwrite($file, "select [Management.CourierDriver].CourierID, EmployeeName, Picture, on_trip, waybill from [Management.CourierDriver] inner join [TPS].[dbo].[CourierCurrentTrip] on [Management.CourierDriver].CourierID = [TPS].[dbo].[CourierCurrentTrip].CourierID where Username = '$user' and Password = '$pass'\r\n\0");

			$stmt = $conn->query("select [Management.CourierDriver].CourierID, EmployeeName, Picture, on_trip, waybill from [Management.CourierDriver] inner join
			[TPS].[dbo].[CourierCurrentTrip] on [Management.CourierDriver].CourierID = [TPS].[dbo].[CourierCurrentTrip].CourierID where Username = '$user' and Password = '$pass'");
			
			
			
			$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$test = json_encode($res[0], JSON_FORCE_OBJECT);
			fwrite($file, $test);
			if ( count($res) > 0 ) {
				$stmt = $conn->query("update Management.dbo.[Management.CourierDriver] set is_login = 1 where [Management.CourierDriver].CourierID = " . $res[0]['CourierID']);
				
				fwrite($file, "update Management.dbo.[Management.CourierDriver] set is_login = 1 where [Management.CourierDriver].CourierID = " . $res[0]['CourierID'] . "\r\n\0");

				echo $test;
			}
			else
				echo "{}"; 
		}
	}
 //OFD, CNA, ULA, CLOSED, NDA
	else if ( $action == "trips" ) {
		$courier_id = $_POST['courier_id'];
		$stmt = $conn->query("
		 select [TPS].[dbo].[trips].waybill, date, status, [TPS].[dbo].[Encoding.Trans].CAddress as consignee_address, case when Status != 'OFD' then 1 else 0 end as is_del, dateposted, addr as destination from [TPS].[dbo].[trips] 
 inner join (select * from (select waybill, Status, TRY_CONVERT(datetime, DatePosted, 20) as DatePosted 
 from [TPS].[dbo].[Tracing]) as a where DatePosted is not null 
 and TRY_convert(date, DatePosted, 20) = TRY_convert(date, getdate())
 ) as b on b.waybill = [TPS].[dbo].[trips].waybill 
  inner join [TPS].[dbo].[Encoding.Trans]  on b.waybill = [TPS].[dbo].[Encoding.Trans].Waybill 
 where courier_id = '$courier_id' 
 and (Select convert(date, getdate())) = (select convert(date, date))

");

		
		fwrite($file, "
select  b.waybill, b.date, [TPS].[dbo].[Encoding.Trans].CAddress as consignee_address, b.status, b.is_del, b.is_del,
 b.destination, b.dateposted from (select [TPS].[dbo].[trips].waybill, date, status, case when Status != 'OFD' then 1 else 0 end as is_del, dateposted, addr as destination from [TPS].[dbo].[trips] 
 inner join (select * from (select waybill, Status, TRY_CONVERT(datetime, DatePosted, 20) as DatePosted 
 from [TPS].[dbo].[Tracing]) as a where  a.status !='DEL' and DatePosted is not null 
 and TRY_convert(date, DatePosted, 20) = TRY_convert(date, getdate())
 ) as b on b.waybill = [TPS].[dbo].[trips].waybill 
 where courier_id = '$courier_id' 
 and (Select convert(date, getdate())) = (select convert(date, date)) and status <> 'OFD' union all select [TPS].[dbo].[trips].waybill, 
 date, status, case when Status != 'OFD' then 1 else 0 end as is_del, dateposted,  addr as destination from [TPS].[dbo].[trips] 
 inner join (select * from (select waybill, Status, TRY_CONVERT(datetime, DatePosted, 20) as DatePosted from [TPS].[dbo].[Tracing]) as a 
 where DatePosted is not null and TRY_convert(date, DatePosted, 20) = convert(date, getdate())) as b on b.waybill = [TPS].[dbo].[trips].waybill 
 where courier_id = '$courier_id'  and (Select convert(date, getdate())) = (select convert(date, date))
  and status = 'OFD' 
 and [TPS].[dbo].[trips].waybill not in (select [TPS].[dbo].[trips].waybill from [TPS].[dbo].[trips] 
 inner join (select * from (select waybill,Status, TRY_CONVERT(datetime, DatePosted, 20) as DatePosted from [TPS].[dbo].[Tracing]) as a 
 where DatePosted is not null and convert(date, DatePosted) = convert(date, getdate())
 ) as b on b.waybill = [TPS].[dbo].[trips].waybill 
 where courier_id = '$courier_id' and (Select convert(date, getdate())) = (select convert(date, date)) and status <> 'OFD')) as b 
 inner join [TPS].[dbo].[Encoding.Trans]  on b.waybill = [TPS].[dbo].[Encoding.Trans].Waybill order by date asc;

");
		
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$prev = $res[0];
			$ret = array();
			array_push($ret,  $prev);
			$b = 0;
			for ( $a = 1; $a < count($res); $a++ ){
				
				//echo 'testsetsetest';
				$curr = $res[$a];
				//echo $prev['waybill'] . "<br>";
				if ( $prev['waybill'] == $curr['waybill'] ) {
					$prevTime  =  strtotime($prev['dateposted']);
					$currTime = strtotime($curr['dateposted']);
					//if ( $prev['waybill'] == '362051147' ) {
						
						
					//}
					if ( $prevTime < $currTime ) {
						//echo $curr['waybill'] . " ";
						//echo $prev['dateposted'] . " < " . $curr['dateposted'] . "<br>";
						$ret[$b] = $curr;
						$prev = $curr;
						
					}
				} else {
					$b++;
					array_push($ret,  $curr);
					$prev = $curr;
				}
			}
		
		if ( count($res) > 0 )
			echo json_encode($ret);
		else
			echo "[]";
	}
	else if ( $action == "waybill" )
	{
		$waybill = $_POST['waybill'];
		fwrite($file,"select SCompanyName as shipper_company_name, SAddress as shipper_address,
		SProvinceCity as shipper_province, SContactName as shipper_name, CContactName as consignee_name,
		CAddress as consignee_address, CProvinceCity as consignee_province, 
		TotalPackages as shipment_total_packages,
		TotalActualWt as shipment_actual_weight, TotalDimensionWt as shipment_dimension_weight, 
		ChargeableWt as shipment_chargeable_weight, TotalCBM as shipment_total_cbm from [TPS].[dbo].[Encoding.Trans]
		where Waybill = '$waybill' \r\n\0 ");

		$stmt = $conn->query("select SCompanyName as shipper_company_name, SAddress as shipper_address,
		SProvinceCity as shipper_province, SContactName as shipper_name, CContactName as consignee_name,
		CAddress as consignee_address, CProvinceCity as consignee_province, 
		TotalPackages as shipment_total_packages,
		TotalActualWt as shipment_actual_weight, TotalDimensionWt as shipment_dimension_weight, 
		ChargeableWt as shipment_chargeable_weight, TotalCBM as shipment_total_cbm from [TPS].[dbo].[Encoding.Trans] where Waybill = '$waybill' 
		 ");
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
		if ( count($res) > 0 )
			echo json_encode($res[0], JSON_FORCE_OBJECT);
		else
			echo "{}";
	}
	else if ( $action == "performance" )
	{
		$from = $_POST['from'];
		$to = $_POST['to'];
		$courier = $_POST['courier'];
		fwrite($file,"SELECT 
		concat(count(*), '-Waybill number') as waybill_count,
		concat(count(case when [Status]='DEL' then 1 end), '-DELIVERED') as DEL
		concat(count(case when [Status]='CNA' then 1 end), '-Consignee not around') as CNA,
		concat(count(case when [Status]='NDA' then 1 end), '-No delivery attempts') as NDA,
		concat(count(case when [Status]='CLOSED' then 1 end), '-Establishment Closed') as CLOSED,
		concat(count(case when [Status]='PNR' then 1 end), '-Payment not ready') as PNR,
		concat(count(case when [Status]='RTA' then 1 end), '-Refuse to accept') as RTA
		FROM [TPS].[dbo].[Tracing] where [Courier] = '$courier' and DeliveryDate >= '$from' and DeliveryDate <= '$to'\r\n\0");

		$stmt = $conn->query("SELECT 
		concat(count(*), '-Waybill number') as waybill_count,
		concat(count(case when [Status]='DEL' then 1 end), '-DELIVERED') as DEL,
		concat(count(case when [Status]='CNA' then 1 end), '-Consignee not around') as CNA,
		concat(count(case when [Status]='NDA' then 1 end), '-No delivery attempts') as NDA,
		concat(count(case when [Status]='CLOSED' then 1 end), '-Establishment Closed') as CLOSED,
		concat(count(case when [Status]='PNR' then 1 end), '-Payment not ready') as PNR,
		concat(count(case when [Status]='RTA' then 1 end), '-Refuse to accept') as RTA
		FROM [TPS].[dbo].[Tracing] where [Courier] = '$courier' and DeliveryDate >= '$from' and DeliveryDate <= '$to'");	
		
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		echo json_encode($res[0], JSON_FORCE_OBJECT);
	}
	else if ( $action == "get_user_status" )
	{
		$stmt = $conn->query("select (select count(*) from [Management.CourierDriver]) as user_count, (select count(*) from [Management.CourierDriver] where is_login = 1) as online_users,
(select count(*) from TPS.dbo.CourierCurrentTrip where on_trip = 1) as intransit_users, (select count(*) from TPS.dbo.CourierCurrentTrip where on_trip = 0) as standby_users");
		
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo json_encode($res[0], JSON_FORCE_OBJECT);
	}
	else if ( $action == "location" )
	{
		$type = $_POST['type'];
		if ( $type == "get" )
		{
			$date = $_POST['date'];
			if ( isset($_POST['batch_location']) )
			{
				$batch_location = json_decode($_POST['batch_location'], true);
				if ( count($batch_location) > 0 )
				{
					$query = "select * from [TPS].[dbo].[CourierDriverRealtimeLocation] where '$date' = cast(date_time as Date) and (";
					foreach($batch_location as $key => $val)
					{
						$query .= " (CourierDriverID = '$key' and ID > '$val') or";
					}
					$query = substr($query, 0, strlen($query) - 2). ")";
					$stmt = $conn->query($query);
					$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
					fwrite($file, $query);
					
					
					
					echo json_encode($res);
				}
				else
				{
					echo "[]";
				}
				
			}
			else
			{
				$search_type = $_POST['search_type'];
				
				$query = "select * from [TPS].[dbo].[CourierDriverRealtimeLocation] where '$date' = cast(date_time as Date)";
				if ( $search_type == 'user_and_date' )
				{
					$user_id = $_POST['user_id'];
					$query = $query . " and CourierDriverID = '$user_id'";
					
				}
				
				$stmt = $conn->query($query);	
				$res['location'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
				$stmt = $conn->query("select distinct CourierDriverID, b.EmployeeName as name, b.Branch as branch, 
				(case when c.waybill = 'NULL' then 0 else 1 end) as on_trip from [TPS].[dbo].[CourierDriverRealtimeLocation] a 
				inner join Management.dbo.[Management.CourierDriver] b on
				a.CourierDriverID = b.CourierID inner join [TPS].[dbo].[CourierCurrentTrip] c on b.CourierID = c.CourierID where '$date' = cast(date_time as Date)");
				$res['info'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
				echo json_encode($res);
			}
			
		}
		else if ( $type == "put" )
		{
			$courier_id = $_POST['courier_id'];
			$longitude = $_POST['longitude'];
			$latitude = $_POST['latitude'];
			
			$stmt = $conn->prepare("insert into CourierDriverRealtimeLocation (CourierDriverID, longitude, latitude) values($courier_id, $longitude, $latitude)");	
			$result['result'] = $stmt->execute();
			fwrite($file, "insert into CourierDriverRealtimeLocation (CourierDriverID, longitude, latitude) values($courier_id, $longitude, $latitude)");
			echo json_encode($result, JSON_FORCE_OBJECT);
		}
		
	}
	else if ( $action == "start_trip" )
	{
		$courier_id = $_POST['courier_id'];
		$waybill = $_POST['waybill'];
		
		$stmt = $conn->prepare("update CourierCurrentTrip set on_trip = 1, waybill = '$waybill' where CourierID = '$courier_id'");

		fwrite($file, "update CourierCurrentTrip set on_trip = 1, waybill = '$waybill' where CourierID = '$courier_id'");
		
		$result['result'] = $stmt->execute();
		
		echo json_encode($result, JSON_FORCE_OBJECT);
	}
	else if ( $action == "submit_waybill" )
	{
		$waybill = $_POST['waybill'];
		$status = $_POST['status'];
		$courier_name = $_POST['courier_name'];
		$receiver_name = $_POST['receiver_name'];
		$relationship = $_POST['relationship'];
		$signature_image = $_POST['signature_image'];
		$photo_image = $_POST['photo_image'];
		$actual_time = $_POST['actual_time'];
		$courier_id = $_POST['courier_id'];
		$remarks = $_POST['remarks'];
		$result['result'] = FALSE;
		if ( $status == "PNR2" )
		{
			echo json_encode($result, JSON_FORCE_OBJECT);
			die();
		}
		////////       NTP
		$timeserver = "utcnist.colorado.edu";
		$timercvd = query_time_server($timeserver, 37);

		$timevalue = bin2hex($timercvd[0]);
		$timevalue = abs(HexDec('7fffffff') - HexDec($timevalue) - HexDec('7fffffff'));
		$tmestamp = $timevalue - 2208988800;

		$curr_date = date("m/d/Y h:i:s A", $tmestamp);

		////////////
		$stmt = $conn->prepare("insert into Tracing (Waybill, Status, Courier, Receiver,
		DeliveryDate, Relationship, ActualTime, PostedBy, DatePosted, Remarks) values('$waybill', '$status', '$courier_name', '$receiver_name', 
		'$curr_date', '$relationship', '$actual_time', '$courier_name', '$curr_date', '$remarks')");
		
		fwrite($file, "insert into Tracing (Waybill, Status, Courier, Receiver,
		DeliveryDate, Relationship, ActualTime, PostedBy, DatePosted, Remarks) values('$waybill', '$status', '$courier_name', '$receiver_name', '$curr_date', 
		'$relationship', '$actual_time', '$courier_name', '$curr_date, '$remarks')");
		
		
		$result['result'] = $stmt->execute();
		switch($status)
			{
				case "CMO":
					$stat_name = 'Consignee Move Out';
					$stat = 12;
					break;
				case "CNA":
					$stat_name = 'Consignee not around';
					$stat = 13;
					break;
				case "CNU":
					$stat_name = 'Consignee Unknowed';
					$stat = 14;
					break;
				case "NDA":
					$stat_name = 'No Delivery Attempt';
					$stat = 15;
					break;
				case "NRP":
					$stat_name = 'No Requirements Provided';
					$stat = 16;
					break;
				case "PNR":
					$stat_name = 'Payment Not Ready';
					$stat = 17;
					break;
				case "RTA":
					$stat_name = 'Refuse to Accept';
					$stat = 18;
					break;
				case "RTS":
					$stat_name = 'Refuse to Sender';
					$stat = 19;
					break;
				case "ULA":
					$stat_name = 'Unlocated Address';
					$stat = 20;
					break;
				case "CRU":
					$stat_name = 'Customer Return Unit';
					$stat = 21;
					break;
				case "CNR":
					$stat_name = 'CRU Not Ready';
					$stat = 22;
					break;
				case "NSP":
					$stat_name = 'CLOSED';
					$stat = 23;
					break;
				case "LOST":
					$stat_name = 'LOST';
					$stat = 24;
					break;
				case "OFD":
					$stat_name = 'Out for Delivery';
					$stat = 4;
					break;
				case "CLOSED":
					$stat_name = 'CLOSED';
					$stat = 26;
					break;
				case "DEL":
					$stat_name = 'Delivered';
					$stat = 5; 	
					break;
				case "DONE":
					$stat_name = 'DONE';
					$stat = 27;
					break;
					
			}
				
		$stmt = $conn->prepare("insert into [Encoding.ActivityLog] (Waybill, Activity, PostedBy, DatePosted) 
		values('$waybill', '$stat_name', '$courier_name', '$curr_date')");
		
		fwrite($file, "insert into [Encoding.ActivityLog] (Waybill, Activity, PostedBy, DatePosted) 
		values('$waybill', '$stat_name', '$courier_name', '$curr_date')");
		
		$result['result'] = $result['result'] & $stmt->execute();
		
			
		
		$stmt = $conn->prepare("update [Forwarding.Deliver] set Status = '$stat'
		where waybill = '$waybill'");
		
		fwrite($file, "update [Forwarding.Deliver] set Status = '$stat'
		where waybill = '$waybill'");
	
		$result['result'] = $result['result'] & $stmt->execute();
		
		
		if ( $status == 'DEL' || $status == 'DONE' )
		{
			$stmt = $conn->prepare("update trips set is_active = 0 where waybill = '$waybill'");
			
			fwrite($file, "update trips set is_active = 0 where waybill = '$waybill'");
		}
		else
		{
			$stmt = $conn->prepare("update trips set status_id = (select id from stat where Name = '$status') where waybill = '$waybill'");
			
			fwrite($file, "update trips set status_id = (select id from stat where Name = '$status') where waybill = '$waybill'");
		}
			
		
		$result['result'] = $result['result'] & $stmt->execute();
		
		
		$stmt = $conn->prepare("update CourierCurrentTrip set waybill = NULL, on_trip = 0 where CourierID = '$courier_id'");
		
		fwrite($file, "update CourierCurrentTrip set waybill = NULL, on_trip = 0 where CourierID = '$courier_id'");
		
		$result['result'] = $result['result'] && $stmt->execute();
		
		
		///////temp
		$result['result'] = TRUE;
		echo json_encode($result, JSON_FORCE_OBJECT);
	}
	
	else if ( $action == "countTripStatus" )
	{
		$dateStart = $_POST['date_start'];
		$dateEnd =$_POST['date_end'];
		$stmt = $conn->query("SELECT COUNT(TracingID) AS Count, Status FROM TPS.dbo.Tracing
		where DatePosted >= '$dateEnd' 
		AND DatePosted <= '$dateStart' 
		AND Status = 'CANCEL'
		OR Status = 'DEL'
		OR Status = 'Closed'
		OR Status = 'Lost'
		OR Status = 'REACHED'
		OR Status = 'DELAYED'
		OR Status = 'RECVD'
		OR Status = 'RTS'
		OR	Status = 'RTA'
		OR Status = 'OFD'
		Group By Status");
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo json_encode($res);	
	}	
	else if ( $action == "loadAlarm" )
	{
		$id = $_POST['alarm_id'] !=="isFirst"?$_POST['alarm_id']:null;
		if(!empty($id)){
			$stmt = $conn->query("SELECT * FROM TPS.dbo.alarm AL			
			INNER JOIN TPS.dbo.CourierDriverRealtimeLocation CDL
			ON CDL.ID = AL.courierdriverid
			INNER JOIN [Management].[dbo].[Management.CourierDriver] CD
			ON CDL.CourierDriverID = CD.CourierID
			where AL.id > '$id'
			where AL.status_callback = 0
			ORDER BY AL.id 
			");
		} else {
			$stmt = $conn->query("SELECT * FROM TPS.dbo.alarm AL			
			INNER JOIN TPS.dbo.CourierDriverRealtimeLocation CDL 
			INNER JOIN [Management].[dbo].[Management.CourierDriver] CD
			ON CDL.CourierDriverID = CD.CourierID
			ON CDL.ID = AL.courierdriverid 
			where AL.status_callback = 0
			ORDER BY AL.id 
			");
		}

		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo json_encode($res);	
	}	
	else if ($action =="alarmCallback"){
		$alarm_id = $_POST['alarm_id'];
		$stmt = $conn->prepare("update  TPS.dbo.alarm  set status_callback = 1 where id = '$alarm_id'");
		$result['result'] = $stmt->execute();
		echo json_encode($result, JSON_FORCE_OBJECT);
	}
	fwrite($file, "\n\0\n\0\n\0");
	fclose($file);
	$conn = null;
}



