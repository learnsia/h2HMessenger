<?php
/** Initial screen the user sees after login. */

include('function.php');

loggedIn();

menu();

$msg_id = trim($_GET['msg']);

// Check passed ID
if (!preg_match("/^[0-9]{1,10}$/", $msg_id))
  die("Invalid message ID1.");

// Create db connection
$connection = connection();

// Ensure the user is authorized to delete the email.
$id = $_SESSION['s_id'];

// Ensure the user is authorized to delete the message.
$sql = mressf("SELECT id FROM msg WHERE id = '%d' AND recip = '%d'", $msg_id, $id);

// Execute the query
$sql_result = mysql_query($sql)
  or die("Unable to execute query." . mysql_error());

// Stop executing if the user isn't authorized to this msg.
if (mysql_num_rows($sql_result) == "0")
  die("Invalid message ID.");

// Delete access to the message
// Note that the recipient's value is changed to "0".
$delete_sql = mressf("UPDATE msg SET recip = '0' WHERE id = '%d' AND recip = '%d'", $msg_id, $id);

// Execute the query
$delete_sql_result = mysql_query($delete_sql)
  or die("Unable to delete access to this message." . mysql_error());

if ($delete_sql_result) {

	header('Location: msg_main.php');
	exit();

} else {

	echo "<b>Fatal error! Unable to delete access to this message.</b>";
	exit();

}

?>
