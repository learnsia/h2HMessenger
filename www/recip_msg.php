<?php

include('includes/function.php');

loggedIn();

html_header();

menu();

// Message id
$msg_id = trim($_GET['msg']);

if (!preg_match("/^[0-9]{1,10}$/", $msg_id)) 
  die("Message not found.");

$_SESSION['s_msg_id'] = "";
$_SESSION['s_msg_id'] = $msg_id;

?>

<script type="text/javascript">
function openWin()
{
readWindow=window.open('','','width=200,height=100');
readWindow.focus();
}
</script>

</head>
<body>

<?php

// check to be sure the user has access to this message.
$connection = connection();

$id = $_SESSION['s_id'];

// Query to be sure the user has access to this message.
$sql = mressf("SELECT * FROM msg WHERE id = '%d' AND recip = '%d'", $msg_id, $id);

// Execute query.
$sql_result = mysql_query($sql,$connection)
  or die("Unable to execute query." . mysql_error());

// If there are no results, the user doesn't have access.
if (mysql_num_rows($sql_result) == 0) {

	die("Message not found.");
	mysql_close($connection);

// Otherwise, keep going.
} else {

	/** Decrypt email message */
	// Prepare to retrieve the message information
	$i = mysql_fetch_object($sql_result);

	// Variables for displaying the message
	$details = $i->msg_details;
	$sealed_msg = $i->msg;

	// Sender's ID, this will be used to retrieve their public key
	// to verify the message signature.
	$t_sender = $i->sender;

	// Get Sender info
	$from = get_user($t_sender);
	$from = explode("|", $from);
	$sender_pubkey = $from[2];
	$from_sender = $from[1];

	// Message details from the db
	$details_tmp = explode("|", $details);

	// Message detail variables
	$t_time = $details_tmp[0];
	$t_recip = $details_tmp[1];
	$t_subject = $details_tmp[2];

	$_SESSION['s_time'] = ""; 
	$_SESSION['s_time'] = $t_time; 

	/** Decrypt email message */
	$key = $_SESSION['s_priv_tmp'];
	$pass = $_SESSION['s_pass'];

	// Sealed message split from the encrypted key
	$split_msg = explode("@", $sealed_msg);

	// Sealed message
	$sealed = base64_decode($split_msg[0]);

	// Sealed key for the recipient
	$seal_key = "$split_msg[1]";
	$keys_crypt = explode("|", $seal_key);

	// decode the rc4 key
	$d_seal_keys1 = base64_decode($keys_crypt[1]);

	// unseal the rc4 key
	$d_seal_keys1 = d_seal($d_seal_keys1,$key,$pass);

	// Prepare the receipient's private key to unseal the encrypted message.
	$pkeyid = openssl_get_privatekey($key,$pass);

	// unseal the message
	if (openssl_open($sealed, $unseal, $d_seal_keys1, $pkeyid)) {
	
		// Unset the read flag
		$connection = connection();
		
		// Unset the message as being read
		$unset_sql = mressf("UPDATE msg SET iread = '0' WHERE id = '%d'",$msg_id);

		$unset_sql_result = mysql_query($unset_sql,$connection);

		// Format the message to display
		echo "<br />From: $from_sender on $t_time<br />";
		echo "Subject: $t_subject<br />";
		
		// Session variables for sender and subject info
		// Need the sender's id for the reply message.
		$_SESSION['s_sender_id'] = "";
		$_SESSION['s_sender_id'] = $t_sender;

		// Sender Email
		$_SESSION['s_from'] = "";
		$_SESSION['s_from'] = $from_sender;

		// Email subject
		$_SESSION['s_subject'] = "";
		$_SESSION['s_subject'] = $t_subject;

		// Sender Public Key
		$_SESSION['s_recip_pub'] = "";
		$_SESSION['s_recip_pub'] = $sender_pubkey;

		// Split message
		$unseal = explode("::", $unseal);
		$message = $unseal[0];

		// Sealed message signature
		$sign_sealed = '';
		$sign_sealed = base64_decode($unseal[1]);

		// Sender name from Public Certificate
		$sender_name = openssl_x509_parse($sender_pubkey);
		$sender_name = preg_grep("/CN/",$sender_name);
		$sender_name = implode("/", $sender_name);
		$sender_name = explode("/", $sender_name);
		$sender_name = preg_replace("/CN=/", "", $sender_name[6]);

		// Begin checking of the message integrity
		$t_sig = verify_msg($message,$sign_sealed,$sender_pubkey);

		if ($t_sig == 1) {

			$ok = "<br /><b>Good Signature at $unseal[2] UTC from $from_sender!</b>";

		} elseif ($t_sig == 0) {

			$ok = "Bad Signature.";

		} else {

			$ok = "Fatal error checking signature.";

		}

		// Displays the signature status
		echo "<p>$ok<p>";

		// The encrypted message
		echo "<form method=\"POST\" action=\"reply_msg.php\">";
	        echo "<textarea readonly=\"readonly\" name=\"the_message\">$message</textarea><br />";
		echo "<input type=\"submit\" name=\"submit\" value=\"Reply\">";
		echo "</form>";
		
		// This is the message that will be passed to the reply script
		$_SESSION['s_message'] = "";
		$_SESSION['s_message'] = $message;

		openssl_free_key($key);

	} else {

		echo "Error opening the message.";

	}

}

html_footer();

?>
