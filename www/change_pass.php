<?php

include('function.php');

loggedIn();

menu();

include('includes/header.php');

/***  Corporate policies may require passwords to be changed on a regular basis so this is the beginning of a
      script to allow changing passwords...i should be using passphrase...since it is PKI. */

if ($_POST['change_pass'] == "Change Password") {

	// Old and new passwords
	$password = escapeshellcmd($_POST['password']);
        $confirm_pass = escapeshellcmd($_POST['confirm_pass']);
        $curr_password = escapeshellcmd($_POST['curr_password']);
	
	// Existing session variables for the user
	$username = $_SESSION['s_email'];
	$upassword = $_SESSION['s_pass'];
	$privatekey = $_SESSION['s_priv_tmp'];
	
	$connection = connection();

	// check that the user's old pass is correct.
	$sql = mressf("SELECT upass FROM users WHERE email = '%s'", $username);

	// Execute the query
	$res = mysql_query($sql,$connection)
	 or die("Error checking old password." . mysql_error());

	// Actually, the user's email doesn't exist, but here we print Invalid Password. hehehe
	if (mysql_num_rows($res) == "0")
	  die("Invalid Password.");

	// Get password object
	$i = mysql_fetch_object($res);
	$curr_pass = "$i->upass";

	// Ensure the passphrase match before being able to change their passphrase
	$check = generateHash($curr_password,$curr_pass);

	// The real password check.
	if ($check != $curr_pass)
	  die("Invalid Password.");

	// Check passphrase requirements on the new passphrase <-- Hey I used passphrase.  Note to self: stop commenting in the code to yourself.
	$test_pass = check_pass($password,$confirm_pass);

	if ($test_pass == "Strong.") {

	} else {

		echo "Your password doesn't meet all security requirements.<br />";
		echo "$test_pass";
		exit();

	}
	
	// Syntax to change passphrase. <- there is that word again.
	//  openssl rsa -in mykey.pem.new  -des3  -passin pass:cpassword -passout pass:password  -out userkey.pem.new
	/**** PHP Doesn't have a method to change the passphrase so this is the best option, I think */

	// Write existing private key to a file
	$username = preg_replace("/@/","", $username);
	$currPrivateKey = "/tmp/$username.txt";
	if (file_exists($currPrivateKey)) {

		unlink($currPrivateKey);

	}
	
	// Open the new file to write the existing private key to.
	$fh = fopen($currPrivateKey, 'w')
	 or die("Can't open file");
	fwrite($fh, $privatekey);
	chmod($currPrivateKey, 0400);
	fclose($fh);

	// Passphrase to pass to Openssl command to change the passphrase for the private key
	$upassword = escapeshellcmd($upassword);

	// new passphrase
	$new_password = $password;
	$new_password = escapeshellcmd($new_password);
	$new_password = generatePassphrase($new_password);

	// Temporary key files
	$no_pass_temp = "$currPrivateKey.tmp";
	$has_pass_temp = "$currPrivateKey.new";

	$remove_password = "";
	$change_password = "";

	// Remove the passphrase from the key and output the new temporary key.
	// system() function below
	$remove_password = "openssl rsa -in $currPrivateKey -passin pass:$upassword -out $no_pass_temp";

	// Prepare the command to safely pass the passphrases to the "system" command.
	// Add new passphrase to key
	// system() function below
	$change_password = "openssl rsa -in $no_pass_temp -passout pass:$new_password -out $has_pass_temp";

	// Remove passphrase from the current private key
	system($remove_password, $rem_retval);
	chmod($no_pass_temp, 0600);

	// Change passphrase
	system($change_password, $ch_retval);
	$change_password = "";
	$remove_password = "";

	// Delete existing private key from file
	unlink($currPrivateKey);
	
	// New password hash.
	$hashed_up = generateHash($password);

	// Check to be sure the OpenSSL command worked to change the passphrase.
	if ($ch_retval == "0" && $rem_retval == "0") {
		
		// Read in new Private Key contents to be stored as a variable to save into the user's
		// respective account.
		$privatekey = fopen($has_pass_temp,"r");
		$privateContents = fread($privatekey,filesize($has_pass_temp));
		fclose($privatekey);

		// Delete temporary key files
		unlink($no_pass_temp);
		unlink($has_pass_temp);

	        // salted hash to store into the db for authentication.
	        $hashed = generateHash($password,$hashed_up);
	
		// Updated to encrypt the phone number and SMS gateway of the user.
		$phone_no = $_SESSION['s_phone'];

		// This is already in the format user@smsgateway so it is just appended here since the @ sign is the delimiter.
		$sms_gateway = $_SESSION['s_sms_gateway'];

		/** Encrypt the private key and base64_encode it to store in the database.  With the new passphrase hash */
	        $sealed_priv = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $password, $new_password ."@".$privateContents."@".$sms_gateway, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
	
	        // DB connection
	        $connection = connection();
	
	        // Update user account information
	        $id = $_SESSION['s_id'];
	        $pass_sql = mressf("UPDATE users SET upass = '%s', priv_key = '%s' WHERE id = '%d'", $hashed, $sealed_priv, $id);

	        $pass_sql_result = mysql_query($pass_sql,$connection)
	          or die("Unable to execute mysql query." .mysql_error());
	
		        if ($pass_sql_result) {
		
		                echo "Your password has been changed.<br />";
		                echo "Click <a href=\"logout.php\">here</a> to login.";
		
				// Want to be sure these are set to null
				$upassword = "";
				$new_password = "";
				$_SESSION['s_priv_tmp'] = "";
				$_SESSION['s_pass'] = "";
		
				//remove all the variables in the session
				session_unset(); 
				$_SESSION = array();
				exit();
		
			} else {

				echo "Error changing your password. <a href=\"change_pass.php\">Try again</a>. 1";
				exit();
		
			}
	
		} else {

			// Delete new private key from disk
			unlink($has_pass_temp);
			echo "Error changing your password. <a href=\"change_pass.php\">Try again</a>.";
			exit();
	
		}

} else {

	// Display Password Change Form
?>
	<form method="post" action="<?php $_SERVER['PHP_SELF']; ?>">

	<table>	
	<tr>

                                        <td colspan="3"><strong>WARNING: If you lose your password, it cannot be recovered and all your messages will become unreadable.
</strong></td>
                                </tr>
				<tr>

					<td colspan="2"><strong>*Current Password*</strong><br />
					<input type="password" name="curr_password" value=""></td>

				</tr>

                                <tr>

                                        <td align="left"><strong>*Password:</strong><br />
                                        <input type="password" name="password" value=""></td>

                                        <td align="left"><strong>*Confirm Password:</strong><br />
                                        <input type="password" name="confirm_pass" value=""><br />

                                </tr>

                                <tr>

                                        <td colspan="3"><strong>WARNING: If you lose your password, it cannot be recovered and all your messages will become unreadable.</strong></td>
                                </tr>

                                <tr>

                                        <td colspan="3"><input type="submit" name="change_pass" value="Change Password"></td>
                                </tr>
        </form>


                </table></p>

<?php

} // end main if statement change_pass

include('includes/footer.php');

?>
