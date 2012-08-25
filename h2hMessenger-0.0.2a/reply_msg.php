<?php
include('function.php');

loggedIn();

html_header();

menu();

if ($_POST['SendReply'] == "Send Reply") {

	// Message variables
	$msg = strip_tags($_POST['the_message']);
        $subject = $_SESSION['s_subject'];

	// Set session variables to go back on errors and not lose updates.
	$_SESSION['s_msg'] = $msg;
	$_SESSION['s_subject'] = $subject;

	if (strlen($subject) > 50)
	  die("Subject is too long.  It should be a brief message.");

	// Current date and time
	$t_date = date("Y-m-d-H:i");

	// Sign the message before encrypting.
	$t_sign = sign_msg($msg);
	//print base64_encode($sealed);

	// Sender's public key
	$user_pub = $_SESSION['s_pub'];
	// Recepients Public key
	$recip_pub = $_SESSION['s_recip_pub'];

        if ($recip_pub == "")
          die("Please ensure you have added the user to your list of users to send messages.<br />Check that the user is the dropdown box on your main page.");

	/** Encrypt the message with the public key of the sender and receiver. */
	/**  In the ESCROW implementation of this app, the ESCROW key will be added automatically, as well */
	openssl_seal($msg."::".$t_sign."::".$t_date, $sealed, $ekeys, array($user_pub, $recip_pub));

	// Encode the binary data to store in the DB
	$sealed = base64_encode($sealed);

	// The $ekeys variable is the random password used to seal the message with RC4
	// Those are returned as arrays so each one is base64 encoded.
	// Retrieve private key and password.
	$key = $_SESSION['s_priv_tmp'];
	$pass = $_SESSION['s_pass'];

	// Encrypt the RC4 key that encrypts the message with the user's public key
	$seal_keys_0 = e_seal($ekeys[0],$user_pub);
	$seal_keys_1 = e_seal($ekeys[1],$recip_pub);
	
	// Encode the results to store in the database.
	$seal_keys_0 = base64_encode($seal_keys_0);
	$seal_keys_1 = base64_encode($seal_keys_1);

	// concatenate the two to store in the database.
	$keys_crypt = "@$seal_keys_0|$seal_keys_1";

	// Begin mysql connection
	$connection = connection();

	// Sender and Receipient's information
	$msg_id = $_SESSION['s_msg_id'];
	$userid = $_SESSION['s_id'];
	$recip_id = $_SESSION['s_sender_id'];
	$recip_email = $_SESSION['s_from'];
	$t_date = $t_date;

	// Insert the email message and details.  $subject should always be the last in details since the | is used as a delimiter.
	// of course, I could just put it in a separate table, but why? :D  Actually, maybe I will.
	$sql = mressf("INSERT INTO msg VALUES('','%d', '%d', '%s|%s|Re: %s', '%s%s', '1')",$userid, $recip_id, $t_date, $recip_email, $subject, $sealed, $keys_crypt);

	$sql_result = mysql_query($sql,$connection)
	  or die("Unable to execute query." .mysql_error());

	if ($sql_result) {

		// Unset recipient SESSION variables
		$_SESSION['s_recip_email'] = "";
		$_SESSION['s_recip_pub'] = "";
		$_SESSION['s_msg'] = "";
		$_SESSION['s_subject'] = "";
		$msg = "You have a secure message. Go to: https://".$site_url."/msg_main.php";
                $subject = "Secure message";
                echo "The message has been sent. ";
                $headers = "From: ".$from_email."\r\n";
                @mail($clean_recip_email, $subject, $msg, $headers);
		echo "The message has been sent. ";
		echo "<a href=\"msg_main.php\">Go back</a>";
		exit();

	} else {

		echo "Error sending message 2.";
		exit();

	}

	// free the keys from memory
	$_SESSION['s_recip_pub'] = "";
	openssl_free_key($key);

} else {

	/** Form to send message */

	// Passed variables
	$from = $_SESSION['s_from'];
	$recip = $_SESSION['s_from'];
	$subject = $_SESSION['s_subject'];
	$msg = $_SESSION['s_message'];
	$sender = $_SESSION['s_sender'];
	// Message date
	$t_date = $_SESSION['s_time'];

	// DB connection
	$connection = connection();

	$sender_id = $_SESSION['s_id'];
	$sql = mressf("SELECT up.upub_key FROM uprofile up, users u WHERE u.id = up.user AND up.upub_key = (SELECT id FROM users WHERE email = '%s') AND up.user = '%d'", $recip, $sender_id);

	$sql_query = mysql_query($sql)
	  or die("Unable to check to determine if public key exists in your keyring.");

	if (mysql_num_rows($sql_query) == "0")
	  die("The repient's key is not in your keyring, please add it before sending a message.");
?>

	<form method="POST" action="<?php $_SERVER['PHP_SELF']; ?>">

	<br />To: <?php echo "$recip"; ?> <br /><br />
	Subject: Re: <?php echo "$subject"; ?><br /><br />

	Message: <br />
	<textarea cols="100" rows="30" name="the_message"><?php

		echo "\n\n\n\n\n\n-----On $t_date, $from cried out.-----\n\n";
		$msg = preg_replace("/^/", "> ", $msg);
		print $msg;

		?></textarea><br />

	<input type="submit" name="SendReply" value="Send Reply">

	</form>

<?php

} // end main If statement

html_footer();

?>
