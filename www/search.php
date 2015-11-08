<?php

include('function.php');

loggedIn();

include('includes/header.php');

menu();

if ($_GET['user'] != "") {

	// Passed variable
	$user = trim($_GET['user']);

	// Numeric value is passed so check to be sure that is it.
	if (!preg_match("/^[0-9]{1,10}$/", $user))
	  die("User not found.");

	$connection = connection();

	/** Be sure the ID exists. */
	$check_sql = mressf("SELECT id FROM users WHERE id = '%d'", $user);

	// Execute query
	$sql_result = mysql_query($check_sql,$connection)
	  or die("Error retrieving user." . mysql_error());

	// Check for results
	if (mysql_num_rows($sql_result) == "0") {

		echo "User not found.";
		exit();

	} else {

		// See if the user's key is already added.
		$connection = connection();

		// ID of the logged in user.
		$id = $_SESSION['s_id'];

		// ID for public key to add.
		$user_pub = $user;

		// Insert into the users profile.
		$sql_exist = mressf("SELECT user FROM uprofile WHERE user = '%d' AND upub_key = '%d'", $id, $user_pub);

		// Execute query
		$sql_result_exist = mysql_query($sql_exist,$connection)
		  or die("Error retrieving user." . mysql_error());

		if (mysql_num_rows($sql_result_exist) == "0") {

			// Insert the user's public key
			$sql_insert = mressf("INSERT INTO uprofile VALUES('', '%d', '%d')", $id, $user_pub);
	
			// Execute query
			$sql_result_insert = mysql_query($sql_insert,$connection)
			  or die("Error adding user." . mysql_error());
	
			if ($sql_result_insert) {
	
				echo "User's Public Key Successfully Added.";
				exit();
	
			} else {
	
				echo "User's Public Key was not added. Please go back and try again.";
				exit();
	
			}

		} else {

			echo "The user's key already exists.";		
			exit();

		}

	}

} elseif ($_POST['submit'] == "Search") {

// Alternating row colors
$rowclass = 1 - $rowclass;
$rowclass = 0;

	$user = trim(htmlentities($_POST['user']));

	if ($user == "")
	  die("Please enter a search term.");

	// Create db connection
	$connection = connection();
	
	// Select all users
	$id = $_SESSION['s_id'];
	$user = "%".$user."%";
	$sql = mressf("SELECT id,email,pub_key FROM users WHERE id != '%d' AND email LIKE '%s'", $id, $user);

	// Execute the query
	$sql_result = mysql_query($sql,$connection)
	  or die("Unable to execute query." . mysql_error());

	if (mysql_num_rows($sql_result) == "0")
	  die("No results for that user.");
	
	echo "<table><tr><th>Name</th><th>Email</th><th>Action</th></tr>";
	while ($row = mysql_fetch_array($sql_result)) {
	
		$id = $row['id'];
		$email = $row['email'];
		$pubkey = $row['pub_key'];
	
		// Sender name from Public Certificate
		$name = openssl_x509_parse($pubkey);
		$name = preg_grep("/CN/",$name);
		$name = implode("/", $name);
		$name = explode("/", $name);
		$name = preg_replace("/CN=/", "", $name[6]);
	
		echo "<tr>";
		echo "<td class='row$rowclass'>$name &nbsp;</td>";
		echo "<td class='row$rowclass'>$email &nbsp;</td>";
		echo "<td class='row$rowclass'><a href=\"";
		print $_SERVER['PHP_SELF'];
		echo "?user=$id\">Add</a> &nbsp;</td>";
		echo "</tr>";
	
	}
	
	echo "</table>";

} else {

?>

	<b>Search for a user's public key.</b><p>
	<form method="POST" action="<?php print $_SERVER['PHP_SELF']; ?>">
	<input type="text" name="user" value="">
	<input type="submit" name="submit" value="Search">

	</form>

<?php

}

include('includes/footer.php');

?>
