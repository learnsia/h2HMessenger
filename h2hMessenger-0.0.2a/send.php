<?php

include('function.php');

loggedIn();

menu();

html_header();

if ($_POST['submit'] == "Send" || $_POST['submit'] == "Reply") {

	// Message variables
	$msg = strip_tags($_POST['the_message']);
	$subject = strip_tags($_POST['subject']);

	// Set session variables to go back on errors and not lose updates.
	$_SESSION['s_msg'] = $msg;
	$_SESSION['s_subject'] = $subject;

	if ($subject == "")
	  die("Please include a subject.");
	if ($msg == "")
	  die("Please include a message.");

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

	if ($user_pub == "" || $recip_pub == "")
	  die("Please ensure you have added the user to your list of users to send messages.<br />Check that the user is the dropdown box on your main page.");

	/** Encrypt the message with the public key of the sender and receiver. */
	openssl_seal($msg."::".$t_sign."::".$t_date, $sealed, $ekeys, array($user_pub, $recip_pub));

	// Encode the binary data to store in the DB
	$sealed = base64_encode($sealed);

	// The $ekeys variable is the random password used to seal the message with RC4
	// Those are returned as arrays so each one is base64 encoded.

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
	$userid = $_SESSION['s_id'];
	$recip_id = $_SESSION['s_recip_id'];
	$recip_email = $_SESSION['s_recip_email'];

	// Sender's Email
	$sender_email = $_SESSION['s_email'];

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
		@mail($recip_email, $subject, $msg, $headers);
		echo "<a href=\"msg_main.php\">Go back</a>";
		exit();

	} else {

		echo "Error sending message 2.";
		exit();

	}

} else {

	/** Form to send message */

	// Passed userid
	$to_email = $_POST['to_email'];

	// Check passed ID
	if (!preg_match("/^[0-9]{1,10}$/", $to_email))
	  die("Please enter a valid email address.");

	$_SESSION['s_to_email'] = "";
	$_SESSION['s_to_email'] = $to_email;

	// Prepare MySQL connection
	$connection = connection();

	// Retrieve the ID for the user
	/** TODO: WHEN WORKING WITH MULTIPLE USERS UPDATE THIS TO CHECK TO ENSURE THE USER
	HAS THE USER'S PUBLIC KEY BEFORE PRESENTING THE FORM. IF I DECIDE TO SUPPORT MULTIPLE USERS*/
	$sql = mressf("SELECT id,email,pub_key FROM users WHERE id = '%d'", $to_email);

	// Execute the query
	$sql_result = mysql_query($sql,$connection)
	  or die("Unable to execute query." .mysql_error());

	// Retrieve public key for user to send email to
	$i = mysql_fetch_object($sql_result);

	$_SESSION['s_recip_pub'] = "$i->pub_key";

	if ($_SESSION['s_recip_pub'] == "")
	  die("Something went wrong.  Please select the receipient again. <a href=\"main_msg.php\">Back</a>");

	// Retrieve the respective user's email and id in order to send the message.
	$t_email = "$i->email";
	$_SESSION['s_recip_email'] = "$i->email";
	$t_id = "$i->id";
	$_SESSION['s_recip_id'] = "$i->id";

?>

	<form method="POST" action="<?php $_SERVER['PHP_SELF']; ?>">

	To: <?php echo "$t_email"; ?> <br /><br />
	Subject: <input type="text" size="50" name="subject" value="<?php print $_SESSION['s_subject']; ?>"><br /><br />

	Message: <br />
	<textarea cols="100" rows="30" name="the_message"><?php print $_SESSION['s_msg']; ?></textarea><br />

	<input type="submit" name="submit" value="Send">

	</form>

<?php

} // end main If statement

html_footer();

?>
