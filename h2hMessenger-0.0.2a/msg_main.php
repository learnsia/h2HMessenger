<?php
/** Initial screen the user sees after login. */

include('function.php');

if ($_SESSION['s_preauth'] != "HAS_PREAUTH") {

	header('Location: logout.php');
	exit();

}

loggedIn();

html_header();

menu();

// Create db connection
$connection = connection();

// Select users public keys
$id = $_SESSION['s_id'];
$sql = mressf("SELECT u.id,p.upub_key,u.email FROM users u, uprofile p WHERE u.id = p.upub_key AND p.user = '%d'",$id);

// Execute the query
$sql_result = mysql_query($sql,$connection)
  or die("Unable to execute query." . mysql_error());

?>

<form method="POST" action="send.php">

Select a user to email:<br />

<select name="to_email" id="search_box" class="search_box"><br />

<?php

// Return info for the recipients, if the user has their public key.
while ($row = mysql_fetch_array($sql_result)) {

	// Recipient's info
	$id = $row['id'];
	$emailAddress = $row['email'];
	echo "<option value=\"$id\">$emailAddress</option>";

}

print $_SESSION['s_phone'];

/****** NOTE REMEMBER to change the above function to only retrieve keys the user has the public key for. */
/******  Right now, it is just me sooooooo.... :)  <--- I actually just wrote a note to myself and put a smiley...now i'm commenting on that,
  I really need to get out more.  */

?>

</select>

<ul id="results" class="update"></ul>

<input type="submit" value="Compose">
<input type="submit" value="Get Fingerprint" class="search_button" alt="Select an email above and check with the person to ensure it matches their fingerprint."/>

</form><br /><br />

<?php

// Close DB connection for recipients info query
mysql_close($connection);

// Create DB connection
$connection = connection();

// Sender's ID
$id = $_SESSION['s_id'];

// Retrieve messages for the logged in user
$msg_sql = mressf("SELECT * FROM msg WHERE recip = '%d' ORDER BY id DESC",$id);

// Execute query
$msg_sql_result = mysql_query($msg_sql,$connection)
  or die("Unable to execute query." . mysql_error());

// If there are messages, display those there.
if (mysql_num_rows($msg_sql_result) != "0" ) {

// TODO:  Add and option to remove access for an individual.
// In case they were sent a message by accident.

// Alternating row colors
$rowclass = 1 - $rowclass;
$rowclass = 0;

	echo "<table><tr><th align=left>Date</th><th align=left>From</th><th align=left>Subject</th></tr>";
	while ($row = mysql_fetch_array($msg_sql_result)) {

		// Email info
		$msg_id = $row['id'];
		$sender = $row['sender'];
		$email = $row['email'];
		$details = $row['msg_details'];
		$iread = $row['iread'];

		// Get Sender info
		$from = get_user($sender);
		$from = explode("|", $from);
		$from = $from[1];

		$details_tmp = explode("|", $details);

		$t_time = $details_tmp[0];
		$t_recip = $details_tmp[1];
		$t_subject = $details_tmp[2];

		echo "<tr>";

		if ($iread == "0") {

			echo "<td class='row$rowclass'>$t_time &nbsp;</td>";
			echo "<td class='row$rowclass'>$from &nbsp;</td>";
			echo "<td class='row$rowclass'> &nbsp;<a href=\"recip_msg.php?msg=$msg_id\">$t_subject</a> &nbsp;</td>";
			echo "<td class='row$rowclass' &nbsp;><b><a href=\"delete.php?msg=$msg_id\">Delete</a></b></td>";

		} else {

			echo "<td class='row$rowclass'><b>$t_time</b> &nbsp;</td>";
			echo "<td class='row$rowclass'> &nbsp;<b>$from</b></td>";
			echo "<td class='row$rowclass'> &nbsp;<b><a href=\"recip_msg.php?msg=$msg_id\"> &nbsp;$t_subject</a></b></td>";
			echo "<td class='row$rowclass'> &nbsp;<b><a href=\"delete.php?msg=$msg_id\">Delete</a></b></td>";

		}

		echo "</tr>";
		$rowclass = 1 - $rowclass;

	} // end while loop

	echo "</table>";

} else {

	echo "No messages.";

} // end num rows check

html_footer();

?>
