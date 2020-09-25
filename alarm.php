<?php 
//$debugging = "yes";
$alarm_info = parse_ini_file("alarm_info.ini");

$pro_name = $alarm_info['pro_name'];
$svr_name = $alarm_info['svr_name'];

echo "pro_name : $pro_name\n";
echo "svr_name : $svr_name\n";

$active_row = dataValuesArr('svr_register','active_ok',"pro_name='$pro_name' AND svr_name='$svr_name' LIMIT 1");
if(empty($active_row) || $active_row[0]['active_ok'] == 'no')
	exit;

echo "alarm start~\n";

$system_row = dataValuesArr('system_log','*',"pro_name='$pro_name' AND svr_name='$svr_name' ORDER BY joindate DESC LIMIT 1");
if(empty($system_row))
	exit;

$system_log_rowid = $system_row[0]["rowid"]; 
$load_avg = $system_row[0]["load_avg"];
$load_avg = explode(" ",$load_avg);
$cpu = $system_row[0]["cpu"];
$memory = $system_row[0]["memory"];
$io_wait = $system_row[0]["io_wait"];
$network_rx_error = $system_row[0]["network_rx_error"];
$network_tx_error = $system_row[0]["network_tx_error"];
$query_sec = $system_row[0]["query_sec"];
$http_cnt = $system_row[0]["http_cnt"];
$ping_sec = $system_row[0]["ping_sec"];
$db_live_ok = $system_row[0]["db_live_ok"];
$http_live_ok = $system_row[0]["http_live_ok"];

$config_rows[0] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $load_avg[2] AND alarm_div = 1 ORDER BY alarm_config_id DESC LIMIT 1");	//LOAD 3번째
$config_rows[1] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $cpu AND alarm_div = 2 ORDER BY alarm_config_id DESC LIMIT 1");	//CPU
$config_rows[2] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $memory AND alarm_div = 3 ORDER BY alarm_config_id DESC LIMIT 1");	//memory
$config_rows[3] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $io_wait AND alarm_div = 4 ORDER BY alarm_config_id DESC LIMIT 1");	//io_wait
$config_rows[4] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $network_rx_error AND alarm_div = 5 ORDER BY alarm_config_id DESC LIMIT 1");	//network_rx_error
$config_rows[5] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $network_tx_error AND alarm_div = 6 ORDER BY alarm_config_id DESC LIMIT 1");	//network_tx_error
$config_rows[6] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $query_sec AND alarm_div = 7 ORDER BY alarm_config_id DESC LIMIT 1");	//db-query
$config_rows[7] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $http_cnt AND alarm_div = 8 ORDER BY alarm_config_id DESC LIMIT 1");	//http
$config_rows[8] = dataValuesArr("alarm_config", "*", "alarm_threshold <= $ping_sec AND alarm_div = 9 ORDER BY alarm_config_id DESC LIMIT 1");	//ping
$config_rows[9] = dataValuesArr("alarm_config", "*", "alarm_threshold = '$db_live_ok' AND alarm_div = 10 ORDER BY alarm_config_id DESC LIMIT 1");	//db live
$config_rows[10] = dataValuesArr("alarm_config", "*", "alarm_threshold = '$http_live_ok' AND alarm_div = 11 ORDER BY alarm_config_id DESC LIMIT 1");	//http live

for($j = 0; $j < count($config_rows); $j++)
{
	if(!empty($config_rows[$j][0]))
	{
		$alarm_config_id = $config_rows[$j][0]['alarm_config_id'];
		$alarm_div = $config_rows[$j][0]['alarm_div'];
		$alarm_title = "[$pro_name-$svr_name] - ";
		$alarm_title = $alarm_title. $config_rows[$j][0]['alarm_title'];
		switch($alarm_div)
		{
			case  1: $alarm_contents = "Current : $load_avg[0] $load_avg[1] $load_avg[2]";  break;
			case  2: $alarm_contents = "Current : $cpu";  break;
			case  3: $alarm_contents = "Current : $memory";  break;
			case  4: $alarm_contents = "Current : $io_wait";  break;
			case  5: $alarm_contents = "Current : $network_rx_error";  break;
			case  6: $alarm_contents = "Current : $network_tx_error";  break;
			case  7: $alarm_contents = "Current : $query_sec";  break;
			case  8: $alarm_contents = "Current : $http_cnt";  break;
			case  9: $alarm_contents = "Current : $ping_sec";  break;
			case  10: $alarm_contents = "Current : $db_live_ok";  break;
			case  11: $alarm_contents = "Current : $http_live_ok";  break;
		}
		$stat = $config_rows[$j][0]['stat'];
		$re_send_time = $config_rows[$j][0]['re_send_time'];

		// system_stat 인써트
		$system_stat["system_log_rowid"] = $system_log_rowid;
		$system_stat["alarm_div"] = $alarm_div;
		$system_stat["stat"] = $stat;
		$system_stat["ip"] = "127.0.0.1";
		$system_stat["joindate"] = date("Y-m-d H:i:s", time());
		dataInsert("system_stat", $system_stat);

		$list_row = dataValuesArr("alarm_list", "*", "pro_name='$pro_name' AND svr_name='$svr_name' AND alarm_config_id=$alarm_config_id");
		if(!empty($list_row)) // 알람 리스트에 있으면
		{
			$alarm_auth_id = $list_row[0]['alarm_auth_id'];
			$alarm_list_id = $list_row[0]['alarm_list_id'];
			echo "alarm_auth_id : $alarm_auth_id";
			echo "stat : $stat";
			$auth_user_rows = dataValuesArr("alarm_auth_user_stat A, alarm_auth_user B", "B.phone", "A.alarm_auth_id=B.alarm_auth_id AND A.pro_name = '$pro_name' AND A.svr_name = '$svr_name ' AND A.alarm_list_id = '$alarm_list_id' ");
			$auth_user_rows_cnt = count($auth_user_rows);
			for($c = 0; $c < $auth_user_rows_cnt; $c++) // 알람 권한 유저 리스트
			{
				 $num_list[$c] = $auth_user_rows[$c]["phone"];
			}
			if($auth_user_rows_cnt > 0)
			{
				// 알람 보내는 주기 설정
				$time_diff_row = dataValuesArr("alarm_sender", "COUNT(*) cn, TIMESTAMPDIFF(MINUTE, joindate, NOW()) diff_hour", "pro_name = '$pro_name' AND svr_name = '$svr_name' AND alarm_div = '$alarm_div' AND alarm_stat = '$stat' ORDER BY joindate DESC LIMIT 1");
				print_r($time_diff_row);
				echo "re_send_time : $re_send_time";
				echo "auth_user_rows_cnt : $auth_user_rows_cnt";
				if($time_diff_row[0]['cn'] == 0 || $time_diff_row[0]['diff_hour'] >= $re_send_time)
				{
					// alarm_sender 데이터 인써트.
					$alarm_sender["alarm_list_id"] = $alarm_list_id;
					$alarm_sender["pro_name"] = $pro_name;
					$alarm_sender["svr_name"] = $svr_name;
					$alarm_sender["alarm_div"] = $alarm_div;
  					$alarm_sender["alarm_title"] = $alarm_title;
					$alarm_sender["alarm_contents"] = $alarm_contents;
					$alarm_sender["alarm_stat"] = $stat;
					$alarm_sender["stat"] = 2;
					$alarm_sender["ip"] = "127.0.0.1";
					$alarm_sender["joindate"] = date("Y-m-d H:i:s", time());
					dataInsert("alarm_sender", $alarm_sender);
					$alarm_sender_rows = dataValuesArr("alarm_sender", "LAST_INSERT_ID() AS alarm_sender_id", "1");

					for($k = 0; $k < count($num_list); $k++)
					{
						// user_alarm_new 데이터 인써트.
						$user_alarm_new["alarm_sender_rowid"] = $alarm_sender_rows[0]['alarm_sender_id'];
						$user_alarm_new["phone"] = $num_list[$k];
						$user_alarm_new["ip"] = "127.0.0.1";
						$user_alarm_new["joindate"] = date("Y-m-d H:i:s", time());
						dataInsert("user_alarm_new", $user_alarm_new);
					}

					$sms = new SendPush();
					$alarm_title = $alarm_title. " ($pro_name-$svr_name)";
					$msg = array("body" => $alarm_contents, "title" => $alarm_title);
					$sms->send($msg, $num_list);
				}
			}
		}
	}
}	
?>
