<?php

//session_start();

include('includes/function.php');

$username = trim($_POST['username']);
$upassword = escapeshellcmd($_POST['password']);

if ($username == "" || $upassword == "")
	die("Please ensure you typed the correct username and password!");

// Database connection
$connection = connection();

// Check to see if the user exists and provides the right password.
$sql = mressf("SELECT id,delivery,upass,pub_key,priv_key,timeout FROM users WHERE email = '%s'", $username);

// Execute the query
$sql_result = mysql_query($sql,$connection)
	or die("Unable to execute mysql query." . mysql_error());

if (mysql_num_rows($sql_result) != 0) {

	/**************   ACCOUNT LOCKOUT CHECK *****************/

	$check_time = cleanup(date("Y-m-d H:i:s"));

	// Retrieve lockout values
	$failed_sql = mressf("SELECT failed_login,TIMESTAMPDIFF(MINUTE,lockout,'%s') AS lockout FROM users WHERE email = '%s'", $check_time, $username);
	echo "$failed_sql<br />";

	$failed_sql_result = mysql_query($failed_sql)
		or die("Error checking for last logins.");

	// Fetch failed_login value
	$f = mysql_fetch_object($failed_sql_result);
	$failed_no = $f->failed_login;
	$locked_time = $f->lockout;
	
	echo "Locked: $locked_time  Time to lockout: $lockout_time<br />";
	if ($failed_no >= $failed_count && $locked_time < $lockout_time) {

		echo "<b>Sorry your account has been locked out.  After $lockout_time minutes, your account will be reset.</b>";
		exit();  

	// If the timeout is over, then reset the user's account so they can try again.
	} elseif ($failed_no >= $failed_count && $locked_time > $lockout_time) {

		// Unset values related to acccount lockouts.
		$remove_unlock = mressf("UPDATE users SET failed_login = '0', lockout = '2075-03-01 11:59:59' WHERE email = '%s'", $username);
		$remove_unlock_result = mysql_query($remove_unlock)
		  or die("Unable to reset account." . mysql_error());

		// Retrieve failed_login value
		$failed_sql = mressf("SELECT failed_login FROM users WHERE email = '%s'", $username);

		$failed_sql_result = mysql_query($failed_sql)
		  or die("Error checking for last logins.");

		// Fetch failed_login value
		$f = mysql_fetch_object($failed_sql_result);
		$failed_no = $f->failed_login;
	}
	
	/************** END ACCOUNT LOCKOUT CHECK *****************/

	// Unset values related to acccount lockouts.
	$remove_unlock = mressf("UPDATE users SET failed_login = '0', lockout = '2075-03-01 11:59:59' WHERE email = '%s'", $username);
	$remove_unlock_result = mysql_query($remove_unlock)
	  or die("Unable to reset account." . mysql_error());

	// Retrieved saltine and other data from the db
	$i = mysql_fetch_object($sql_result);

	// hashed and salted pass
	$pass = $i->upass;
	// Check there is a match
	$check_pass = generateHash($upassword,$pass);

	// Check pass
	if ($pass == $check_pass) {

		// Unset values related to acccount lockouts.
		$remove_unlock = mressf("UPDATE users SET failed_logins = '0', lockout = '2075-03-01 11:59:59' WHERE email = '%s'", $username);

		$remove_unlock_result = mysql_query($remove_unlock);

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

		if ($priv_pass == "" || $priv_tmp == "")
			die("Something has gone very wrong.");

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
		/** $t_check_timeout = date("YmdHi");

		$_SESSION['s_check_timeout_1'] = "";
		$_SESSION['s_check_timeout_1'] = $t_check_timeout;
		$_SESSION['s_timeout'] = "";
		$_SESSION['s_timeout'] = $timeout; */

		$_SESSION['s_preauth'] = "HAS_PREAUTH";
    	// Redirect to the login screen
    	header('Location: preauth.php');
		exit();

	} else {

		// Add one to the number of failed login attempts.
		$plus_one = $failed_no + 1;

		// Add the failed login attempt
		$update_failed_sql = mressf("UPDATE users SET failed_login = '%d' WHERE email = '%s'", $plus_one, $username);

		$update_result = mysql_query($update_failed_sql)
			or die("Error checking last logins." . mysql_error);

		if ($update_result) {

			// Number of attempts to login before lockout.
			$attempts = $failed_count - $plus_one;

			if ($attempts == "0") {

				$t_date = date("Y-m-d H:i:s");
				
				// Set the lockout
				$set_lockout_sql = mressf("UPDATE users SET lockout = '%s' WHERE email = '%s'", $t_date, $username);

				$set_lockout_result = mysql_query($set_lockout_sql)
		 		 or die("Error checking last logins.");

				echo "<b>Your account has been locked. You have to wait $lockout_time minutes before you can attempt to login again.</b>";

			} else {

				echo "<b>Invalid username or password. You have $attempts more tries before you account is locked.</b>";
				exit();

			} // end check attempts if statement

		} // end update_result check if statement

	} // end check pass if statement
        
} else {

	echo "Please ensure you typed the correct username and password!";
	exit();

}

?>
