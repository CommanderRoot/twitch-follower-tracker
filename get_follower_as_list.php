<?php
// äüö UTF8-FTW
function convert_time_to_pacific(string $time_string): string {
	$dateTime = new DateTime(date('c', strtotime($time_string)), new DateTimeZone(date_default_timezone_get()));
	$dateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));

	$return = $dateTime->format('Y-m-d h:i:s A T');
	return $return;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_ENCODING, '');
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:', 'Accept: application/vnd.twitchtv.v5+json', 'Client-ID: 1rr2wks0n53qby34wanxhirvlo50359'));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_AUTOREFERER, false);


if(isset($argv[1]) && !empty($argv[1])) {
	$target_user = strtolower($argv[1]);
} else {
	echo 'Error: No username provided'.PHP_EOL;
	exit();
}

// Get User ID from username (login)
$target_user_id = '';
$request_url = 'https://api.twitch.tv/kraken/users?login='.rawurlencode($target_user);
curl_setopt($ch, CURLOPT_URL, $request_url);
$curl_ouput = curl_exec($ch);
$curl_info = curl_getinfo($ch);
if($curl_info['http_code'] == 200) {
	$json_decode = json_decode($curl_ouput, true);
	if($json_decode !== null) {
		foreach($json_decode['users'] as $user) {
			if($user['name'] == $target_user) {
				$target_user_id = $user['_id'];
				break;
			}
		}
	}
}
if(empty($target_user_id)) {
	echo 'Error while getting ID for user: '.$curl_ouput.PHP_EOL;
	exit();
}


$show_timestamp = false;
$write_to_file = true;
$cursor = '';

// Following
// $request_url = 'https://api.twitch.tv/kraken/users/'.rawurlencode($target_user_id).'/follows/channels?sortby=created_at&direction=desc&limit=100&offset=0&cursor=';
// Follower
$request_url = 'https://api.twitch.tv/kraken/channels/'.rawurlencode($target_user_id).'/follows?direction=desc&limit=100&cursor=';

if($write_to_file === true) {
	$handle = fopen(__DIR__ . DIRECTORY_SEPARATOR . $target_user.'.txt', 'wb');
}

$keep_running = true;
while($keep_running === true) {
	redo_request:
	// echo 'Request: '.$request_url.$cursor.PHP_EOL;
	curl_setopt($ch, CURLOPT_URL, $request_url.$cursor);
	$curl_ouput = curl_exec($ch);
	$curl_info = curl_getinfo($ch);
	if($curl_info['http_code'] == 200) {
		$json_decode = json_decode($curl_ouput, true);
		if($json_decode !== NULL) {
			if(isset($json_decode['follows']) && count($json_decode['follows']) > 0) {
				foreach($json_decode['follows'] as $follow) {
					if(isset($follow['user']['name'])) {
						$string = '';
						$string .= $follow['user']['name'];
						if($show_timestamp === true) {
							$string .= '	'.convert_time_to_pacific($follow['created_at']);
						}
						$string .= PHP_EOL;
	
						echo $string;

						if($write_to_file === true) {
							fwrite($handle, $string);
						}
					} elseif(isset($follow['channel']['name'])) {
						$string = '';
						$string .= $follow['channel']['name'];
						if($show_timestamp === true) {
							$string .= '	'.convert_time_to_pacific($follow['created_at']);
						}
						$string .= PHP_EOL;

						echo $string;

						if($write_to_file === true) {
							fwrite($handle, $string);
						}
					}
				}

				// Update cursor
				$cursor = isset($json_decode['_cursor']) ? $json_decode['_cursor'] : '';
				if(empty($cursor)) {
					echo PHP_EOL.PHP_EOL.'Done!'.PHP_EOL;
					$keep_running = false;
				}
			} else {
				echo PHP_EOL.PHP_EOL.'Done!'.PHP_EOL;
				$keep_running = false;
			}
		} else {
			echo 'Json_decode Error'.PHP_EOL;
			goto redo_request;
		}
	} elseif($curl_info['http_code'] == 502) {
		// Server Error also retry
		echo '502 Error'.PHP_EOL;
		goto redo_request;
	} elseif($curl_info['http_code'] == 503) {
		// Server Error also retry
		echo '503 Error'.PHP_EOL;
		goto redo_request;
	} elseif($curl_info['http_code'] == 0) {
		// Timeout also retry
		echo 'Timeout Error'.PHP_EOL;
		echo 'Curl error: '.curl_error($ch).PHP_EOL;
		// exit();
		goto redo_request;
	} elseif($curl_info['http_code'] == 404) {
		// Deleted
		echo 'Deleted'.PHP_EOL;
		exit();
	} elseif($curl_info['http_code'] == 422) {
		// TOS'd
		echo 'TOS\'d'.PHP_EOL;
		exit();
	} else {
		echo 'HTTP ERROR: '.$curl_info['http_code'].PHP_EOL;
		goto redo_request;
	}
}

if($write_to_file === true) {
	fclose($handle);
}

