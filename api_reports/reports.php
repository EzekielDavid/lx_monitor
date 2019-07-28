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





if ( isset($_POST['action']) )
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

	var_dump($action);
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
	else if ( $action == "check_online_users_count" )
	{
		$stmt = $conn->query("select count(*) as online_users_count from [Management.CourierDriver] where is_login = 1");
		
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		echo json_encode($res[0], JSON_FORCE_OBJECT);
	}else if ( $action == "alarm" ) {
		
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
    //OFD, CNA, ULA, CLOSED, NDA
	else if ( $action == "waybill" )
	{
		var_dump($action);
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
	fwrite($file, "\n\0\n\0\n\0");
	fclose($file);
	$conn = null;
}



