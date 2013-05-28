<?php

include('function.php');

loggedIn();

if ($_POST['to_email'] != "") {

  // Passed variable
	$user = trim($_POST['to_email']);

	// Numeric value is passed so check to be sure that is it.
	if (!preg_match("/^[0-9]{1,10}$/", $user))
	  die("User not found.");

	$pub_connection = mysql_connect("localhost",$db_user_public,$db_user_pass)
	  or die("Error with database connection.  Please try again.");

	mysql_select_db($db_name,$pub_connection)
	  or die("Error selecting database.");

	/** Be sure the ID exists. */
	$check_sql = mressf("SELECT email,pub_key FROM users WHERE id = '%d'", $user);

	// Execute query
	$sql_result = mysql_query($check_sql,$pub_connection)
	  or die("Error retrieving user." . mysql_error());

	$i = mysql_fetch_object($sql_result);

	// Pub certificate
	$pub_print = "$i->pub_key";

	// Check for results
	if (mysql_num_rows($sql_result) == "0") {

		echo "User not found.";
		exit();

	} else {

		echo "<b>Fingerprint for the above user is:</b> <br />";
		// Split into readable chunks of four
		print chunk_split(sha1_thumbprint($pub_print),4);
		exit();

	}

} else {

	die("No email address was provided.");

}

?>
