<?php
date_default_timezone_set('Asia/Seoul');
class SendPush
{
	private $url;
	private $serverKey; // 서버키

    function __construct()
    {
		$this->url = 'https://fcm.googleapis.com/fcm/send';
		$this->serverKey = '';
    }

    public function send($message , $re_nums){
		echo "send() $re_nums";

		if(isset($re_nums)){
			// 배열인 경우
			for($i=0;$i<count($re_nums);$i++){
				$alert_set = "yes";
				$token = dataValue("Admin_mem","token","phone = '".$re_nums[$i]."'");
				$user_id = dataValue("Admin_mem","id","phone = '".$re_nums[$i]."'");
				$phone_device = dataValue("Admin_mem","phone_device","phone = '".$re_nums[$i]."'");

				if(isset($user_id)){
					$alert_set = dataValue("user_phone_set","alarm_ok","user_id = '".$user_id."'");
					$sound_set = dataValue("user_phone_set","sound_ok","user_id = '".$user_id."'");
					$vib_set = dataValue("user_phone_set","vibration_ok","user_id = '".$user_id."'");
					$badge_set = dataValue("user_phone_set","icon_num_ok","user_id = '".$user_id."'");
					$badge_count = dataValue("user_alarm_new","COUNT(*)","phone = '".$re_nums[$i]."' AND read_ok = 'no'");
					$message = array(
						"body" => $message['body'],
						"title" => $message['title'],
						"setSound" => $sound_set,
						"setVib"=>$vib_set,
						"setBad"=>$badge_set,
						"setbadCount"=>$badge_count,
					);
				}

				if(!isset($token)){
					echo "Fail : ".$re_nums[$i]." 해당 번호는 토큰이 존재하지 않습니다. 푸시메시지 보내는데 실패했습니다.<br><br>\n\n";
					continue;
				}
				$tokens = array(
					0=>$token
				);

				if($alert_set == "yes"){
					if($phone_device == "android"){
						$this->send_notification($tokens, $message, $re_nums[$i]);
					}
					else if($phone_device == "ios"){
						$this->send_notification_ios($tokens, $message, $re_nums[$i]);
					}
				} else{
					echo "Fail : ".$re_nums[$i]." 해당 번호는 설정에서 알림사용를 no로 설정했습니다. 푸시메시지 보내지 않았습니다.<br><br>\n\n";
					continue;
				}
			}
		}
	}

	private function send_notification($tokens, $message, $re_num)
	{
		$url = $this->url;
		$fields = array(
			'registration_ids' => $tokens,
			'data' => $message
		);

		$key = $this->serverKey;
		$headers = array(
			'Authorization:key =' . $key,
			'Content-Type: application/json'
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
		$result = curl_exec($ch);

		if ($result === FALSE) {
			die('Curl failed: ' . curl_error($ch));
		}
		curl_close($ch);
		echo "Result : ".$re_num." ".$result."<br><br>\n\n";
		//return $result;
	}

	private function send_notification_ios($tokens, $message, $re_num)
	{
		$url = $this->url;

		$notification = array(
			'title' =>$message['title'] ,
			'text' => $message['body'],
			"badge" => (($message['setBad']=="yes") ? $message['setbadCount'] : 0),
			"sound"=> (($message['setSound']=="yes") ? "default" : "")
		);

		$data = array(
			"body" => array(
				"message"=>"",
				"type"=>1,
				"quota"=>12,
			),
			"title" => "",
		);

		$fields = array(
			'registration_ids' => $tokens,
			'notification' => $notification,
			'priority'=>'high',
			'data' => $data
		);

		$key = $this->serverKey;
		$headers = array(
			'Authorization:key =' . $key,
			'Content-Type: application/json'
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
		$result = curl_exec($ch);

		if ($result === FALSE) {
			die('Curl failed: ' . curl_error($ch));
		}
		curl_close($ch);
		echo "Result : ".$re_num." ".$result."<br><br>\n\n";
		//return $result;
	}

	private function debug($str) {
		echo "DEBUG: " . $str . "<br>\n";
	}

	private function error($str) {
		echo "ERROR: " . $str . "<br>\n";
	}
}
?>