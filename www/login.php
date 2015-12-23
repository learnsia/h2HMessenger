<?php
include('includes/function.php');

$username = trim($_POST['username']);
$upassword = escapeshellcmd($_POST['password']);
$request_IP = $_SERVER['REMOTE_ADDR'];

if ($username == "" || $upassword == "") {
	die("Error: Please supply both a user name and a password!");
}

// Database connection
$connection = connection();

// Check to see if the user exists and provides the right password.
$sql = mressf("SELECT id,delivery,upass,pub_key,priv_key,timeout FROM users WHERE email = '%s'", $username);

// Execute the query
$sql_result = mysql_query($sql,$connection)
	or die("Unable to execute mysql query." . mysql_error());


// Check to see if the source IP of the request has been rate-limited, if so, throw and error and die.

// Look for the Source IP of the request in our DB, and calculate the timestamp returned + 15 minutes ( the default ban duration).
$login_attempts = mysql_query("SELECT *,TIMESTAMPDIFF(MINUTE,NOW(),ADDTIME(timestamp,'00:15:00')) AS timeleft FROM failed_logins WHERE IP_address = '" . $request_IP . "'",$connection);

if(mysql_num_rows($login_attempts) != 0) {

	// Fetch failed_login value
	$obj = mysql_fetch_object($login_attempts);

	if ($obj->attempts >= $failed_count) {

		echo("<b>Error: Too many login attempts have been made, please try again in " . $obj->timeleft . " minutes.</b>");
		exit();
	}
}


if (mysql_num_rows($sql_result) != 0) {

	// Retrieved saltine and other data from the db
	$i = mysql_fetch_object($sql_result);

	// hashed and salted pass
	$pass = $i->upass;
	// Check there is a match
	$check_pass = generateHash($upassword,$pass);

	// Check pass
	if ($pass == $check_pass) {

		// Variables that will be used during the duration of the session
		$id = "$i->id";
		$delivery = "$i->delivery";
		$priv_tmp = "$i->priv_key";
		$pub = "$i->pub_key";
		$timeout = "$i->timeout";

		// Private key session variable
		$_SESSION['s_priv_tmp'] = "";

		// Decrypt private key
		$priv_tmp = priv_tmp_decrypt($priv_tmp,$upassword);

		// Split the Passphrase from the Private key
		$priv_tmp = explode("@",$priv_tmp);
		$priv_pass = $priv_tmp[0];
		$priv_key = $priv_tmp[1];
		$priv_phone_no = $priv_tmp[2];

		if ($priv_pass == "" || $priv_tmp == "") {
			die("Something has gone very wrong.");
		}

		// Session variables that will be used.
		$_SESSION['s_id'] = "";
		$_SESSION['s_email'] = "";
		$_SESSION['s_pass'] = "";
		$_SESSION['s_pub'] = "";
		$_SESSION['s_authed'] = "";
		$_SESSION['s_phone'] = "";
		$_SESSION['s_delivery'] = "";
		$_SESSION['s_fprint'] = "";

		// Session variables to be used throughout the login session
		$_SESSION['s_id'] = $id;
		$_SESSION['s_delivery'] = $delivery;
		$_SESSION['s_email'] = $username;
		$_SESSION['s_pass'] = $priv_pass;
		$_SESSION['s_priv_tmp'] = $priv_key;
		$_SESSION['s_pub'] = $pub;
		$_SESSION['s_phone'] = $priv_phone_no;
		$_SESSION['s_authed'] = "USER_AUTHENTICATED";

		// SHA1 fingerprint
		$fprint = sha1_thumbprint($pub);
		$fprint = chunk_split($fprint,4);
		$_SESSION['s_fprint'] = $fprint;

		// If the user uses SMS then we prepare the code to be sent to that address
		if ($_SESSION['s_delivery'] == "s") {

			$priv_sms = $priv_tmp[3];
			$_SESSION['s_sms_gateway'] = "$priv_phone_no"."@"."$priv_sms";

		}

		// Set initial login time
		// $t_check_timeout = date("YmdHi");

		//$_SESSION['s_check_timeout_1'] = "";
		//$_SESSION['s_check_timeout_1'] = $t_check_timeout;
		//$_SESSION['s_timeout'] = "";
		//$_SESSION['s_timeout'] = $timeout;

		$_SESSION['s_preauth'] = "HAS_PREAUTH";
    	// Redirect to the login screen
    	header('Location: preauth.php');
		exit();
	} else {
		// bad creds, update DB and throw error
		record_failed_login();
		echo("Please ensure you typed the correct username and password!");
	} // end check pass if statement
        
} else {
	// no creds, update db and throw error.
	record_failed_login();
	echo "Please ensure you typed the correct username and password!";
	exit();

}

?>