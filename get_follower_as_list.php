<?php
// äüö UTF8-FTW
if(isset($argv[1]) && !empty($argv[1])) {
	$target_user = strtolower($argv[1]);
} else {
	echo 'Error: No username provided'.PHP_EOL;
	exit();
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_ENCODING, '');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:', 'Content-Type: application/json', 'Client-ID: kimne78kx3ncx6brgo4mv6wki5h1ko']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_AUTOREFERER, false);
curl_setopt($ch, CURLOPT_URL, 'https://gql.twitch.tv/gql');

$write_to_file = true;
if($write_to_file === true) {
	$handle = fopen(__DIR__ . DIRECTORY_SEPARATOR . $target_user.'.txt', 'wb');
}

$cursor = '';
$keep_running = true;
while($keep_running === true) {
	$gqlQuery = [[
		'operationName' => 'Followers',
		'variables' => [
			'limit' => 100,
			'login' => $target_user,
			'order' => 'DESC',
		],
		'extensions' => [
			'persistedQuery' => [
				'version' => 1,
				'sha256Hash' => 'deaf3a7c3227ae1bfb950a3d3a2ba8bd47a01a5b528c93ae603c20427e1d829d',
			],
		],
	]];
	if(!empty($cursor)) {
		$gqlQuery[0]['variables']['cursor'] = $cursor;
	}

	redo_request:
	// echo 'Requesting cursor: '.$cursor.PHP_EOL;
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gqlQuery, JSON_UNESCAPED_UNICODE));
	$curl_ouput = curl_exec($ch);
	$curl_info = curl_getinfo($ch);
	if($curl_info['http_code'] == 200) {
		$json_decode = json_decode($curl_ouput, true);
		if($json_decode !== null) {
			if(isset($json_decode[0], $json_decode[0]['data'], $json_decode[0]['data']['user'], $json_decode[0]['data']['user']['followers'], $json_decode[0]['data']['user']['followers']['edges']) && !is_null($json_decode[0]['data']['user']['followers']['edges']) && count($json_decode[0]['data']['user']['followers']['edges']) > 0) {
				$string = '';
				foreach($json_decode[0]['data']['user']['followers']['edges'] as $follow) {
					if(isset($follow['node']['login'])) {
						$string .= $follow['node']['login'].PHP_EOL;
					}

					// Update cursor
					$cursor = isset($follow['cursor']) ? $follow['cursor'] : '';
					if(empty($cursor)) {
						echo PHP_EOL.PHP_EOL.'Done!'.PHP_EOL;
						$keep_running = false;
					}
				}

				echo $string;

				if($write_to_file === true && strlen($string) > 0) {
					fwrite($handle, $string);
				}
			} else {
				echo PHP_EOL.PHP_EOL.'Done!'.PHP_EOL;
				$keep_running = false;
			}
		} else {
			echo 'Json_decode error ('.json_last_error_msg().') on: '.$curl_ouput.PHP_EOL;
			goto redo_request;
		}
	} elseif($curl_info['http_code'] == 502) {
		// Server error so retry
		echo '502 error'.PHP_EOL;
		goto redo_request;
	} elseif($curl_info['http_code'] == 503) {
		// Server error so retry
		echo '503 error'.PHP_EOL;
		goto redo_request;
	} elseif($curl_info['http_code'] == 0) {
		// Timeout so retry
		echo 'Timeout error'.PHP_EOL;
		echo 'Curl error: '.curl_error($ch).PHP_EOL;
		// exit();
		goto redo_request;
	} else {
		echo 'HTTP error: '.$curl_info['http_code'].PHP_EOL;
		goto redo_request;
	}
}

if($write_to_file === true) {
	fclose($handle);
}

