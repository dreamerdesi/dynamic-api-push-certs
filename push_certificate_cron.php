
<?php



// define your host information here

define('DB_HOST','your_host');
define('DB_USERNAME','your_username');
define('DB_PASSWORD','your_password');
define('DB_NAME','your_database');



$servername = DB_HOST;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;


// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

//Get dynamic multi network channel names from database (optional)
$channel_name ="SELECT name_of_your_channel FROM your_table";
$channel_name_result=$conn->query($channel_name);

 while ($channel_row = $channel_name_result->fetch_assoc()) {
 	$name = $channel_row['name_of_your_channel'];
        
    }

// grab push notifications certificates from dynamic folder locations on your server
define('YOUR_PUSH_CERTIFICATE',__DIR__."/".$channel_name."/your_pem_file.pem");

// grab your unsent push notifications from your tables

$your_push_notification = "SELECT * FROM your_push_notification_tables WHERE message_status = 'unsent' '";
$your_push_notification_result = $conn->query($your_push_notification);


	
if ($your_push_notification_result) {

	$ctx = stream_context_create();
	stream_context_set_option($ctx, 'ssl', 'local_cert', YOUR_PUSH_CERTIFICATE);

      // loop through the table 

	while ($your_new_row = $your_push_notification_result->fetch_assoc()) {

		$messageId = $your_new_row_row['id'];
		// Put your private key's passphrase here:
		$passphrase = 'here';

		// Put your alert message here:
		$message = $your_new_row['message'];
			
		// get token from your device id table if stored elsewhere
		$getToken = "SELECT * FROM your_token_table";
		$query = $conn->query($getToken);

		while ($row = $query->fetch_assoc()) {

			$deviceToken = $row['your_device_token'];
		
			// ////////////////////////////////////////////////////////////////////////////////

			stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);

			// Open a connection to the APNS server
			$fp = stream_socket_client(
				'ssl://gateway.sandbox.push.apple.com:2195', $err,
				$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

			if (!$fp)
				exit("Failed to connect: $err $errstr" . PHP_EOL);

			echo 'Connected to APNS' . PHP_EOL;

			// Create the payload body
			$body['aps'] = array(
				'alert' => $message,
				'sound' => 'default'
				);

			// Encode the payload as JSON
			$payload = json_encode($body);

			// Build the binary notification
			$msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
			
			// Send it to the server
			$result = fwrite($fp, $msg, strlen($msg));

			// We can check if an error has been returned while we are sending, but we also need to
			// check once more after we are done sending in case there was a delay with error response.
			checkAppleErrorResponse($fp);

			// Workaround to check if there were any errors during the last seconds of sending.
			// Pause for half a second.
			// Note I tested this with up to a 5 minute pause, and the error message was still available to be retrieved
			usleep(500000);

			checkAppleErrorResponse($fp);

			if (!$result) {

				echo 'Message not delivered' . PHP_EOL;

			} else {
				echo 'Message successfully delivered' . PHP_EOL;


				$updateStatus = "UPDATE your_push_notification SET status=sent WHERE id = $messageId";

				$conn->query($updateStatus);
			}


		}

	var_dump($messageId);

	

	}
			

// Close the connection to the server
fclose($fp);

}

mysqli_close($conn);


// FUNCTION to check if there is an error response from Apple
// Returns TRUE if there was and FALSE if there was not
function checkAppleErrorResponse($fp) {

//byte1=always 8, byte2=StatusCode, bytes3,4,5,6=identifier(rowID).
// Should return nothing if OK.

//NOTE: Make sure you set stream_set_blocking($fp, 0) or else fread will pause your script and wait
// forever when there is no response to be sent.

	stream_set_blocking($fp, 0);

	$apple_error_response = fread($fp, 6);

	if ($apple_error_response) {

// unpack the error response (first byte 'command" should always be 8)
		$error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);

		if ($error_response['status_code'] == '0') {
			$error_response['status_code'] = '0-No errors encountered';

		} else if ($error_response['status_code'] == '1') {
			$error_response['status_code'] = '1-Processing error';

		} else if ($error_response['status_code'] == '2') {
			$error_response['status_code'] = '2-Missing device token';

		} else if ($error_response['status_code'] == '3') {
			$error_response['status_code'] = '3-Missing topic';

		} else if ($error_response['status_code'] == '4') {
			$error_response['status_code'] = '4-Missing payload';

		} else if ($error_response['status_code'] == '5') {
			$error_response['status_code'] = '5-Invalid token size';

		} else if ($error_response['status_code'] == '6') {
			$error_response['status_code'] = '6-Invalid topic size';

		} else if ($error_response['status_code'] == '7') {
			$error_response['status_code'] = '7-Invalid payload size';

		} else if ($error_response['status_code'] == '8') {
			$error_response['status_code'] = '8-Invalid token';

		} else if ($error_response['status_code'] == '255') {
			$error_response['status_code'] = '255-None (unknown)';

		} else {
			$error_response['status_code'] = $error_response['status_code'].'-Not listed';

		}

		echo '<br><b>+ + + + + + ERROR</b> Response Command:<b>' . $error_response['command'] . '</b>&nbsp;&nbsp;&nbsp;Identifier:<b>' . $error_response['identifier'] . '</b>&nbsp;&nbsp;&nbsp;Status:<b>' . $error_response['status_code'] . '</b><br>';

		echo 'Identifier is the rowID (index) in the database that caused the problem, and Apple will disconnect you from server. To continue sending Push Notifications, just start at the next rowID after this Identifier.<br>';

		return true;
	}

	return false;
}
