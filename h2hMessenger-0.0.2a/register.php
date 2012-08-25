<?php

include('function.php');

if ($_POST['submit'] == "Register") {

        // Declare variables
        $email1 = stripslashes(htmlentities($_POST['email']));
        $password = $_POST['password'];
        $confirm_pass = $_POST['confirm_pass'];
        $phone = trim($_POST['phone']);
        $two_fa = trim($_POST['two_fa']);
        $sms_gateway = trim($_POST['sms_gw']);

        // Set session for variables.  These are echoed on the original registration form
        // in case of errors.  Some browsers will erase all fields.  This will prevent that from occuring.
        $_SESSION['s_email1'] = "$email1";
        $_SESSION['s_phone'] = "$phone";
        $_SESSION['s_two_fa'] = "$two_fa";

	/* Data Validation */

        // Ensure the user fills out all the appropriate fields.
        if ($email1 == "" || $password == "" || $confirm_pass == "" || $phone == "" || $two_fa == "" || $sms_gateway == "")
          die("<b>Please fill out all required fields!</b>");

	// Two factor values are stored as single characters in the db.
	if ($two_fa == "sms") {

		$two_fa = "s";

	} else {

		$two_fa = "v";

	}

	// The value of $two_fa mutated above
        $_SESSION['s_two_fa'] = "";
        $_SESSION['s_two_fa'] = "$two_fa";

	// Validate SMS gateway
	// SMS gateways must have a valid MX record.
	// The user's SMS gateway is stored encrypted in the DB.
	if ($sms_gateway != "no_mobile") {

		if (!getmxrr($sms_gateway,$mxhosts))
	  	  die("Invalid SMS Gateway.");

	}

	// Validate the email address.
	include('EmailAddressValidator.php');
	$validator = new EmailAddressValidator;

	if ($validator->check_email_address($email1)) { 

	} else {
 
		// Email not valid
		die("Invalid email address. <strong>$email1</strong>");

	}
	
        /* ################### PREVENT Duplicate accounts  ############################ */

	$connection = connection();

	// Query to check if email already exists
        $sql = mressf("SELECT email FROM users WHERE email = '%s'", $email1);

	// Execute query
	$sql_result = mysql_query($sql,$connection)
	  or die("Error validating account.");

       // See if there are any results
       if (mysql_num_rows($sql_result) == "0" ) {

       } else {

                echo "<b>This email address already exists. <strong>$email1</strong>";
                exit();

       }

	// Check password requirements
        $test_pass = check_pass($password,$confirm_pass);

        if ($test_pass == "Strong.") {

        } else {

                echo "Your password doesn't meet all security requirements.<br />";
                echo "$test_pass";
                exit();

        }

	// Ensure 10 digit numbers have the "1" prepended to it.
	if (strlen($phone) == "10") {

		$phone = "1".$phone;

	}

	// Phone validation.  The phone number is stored encrypted in the DB.
	$new_phone = validatePhone($phone,"<b>Phone number should be less than 15 digits.</b>");

	// Needed to send code via SMS.  This is appended to the phone number, if the user is using an sms gateway.
	if ($sms_gateway != "no_mobile") {

		$_SESSION['s_reg_gateway'] = "$new_phone" ."@". "$sms_gateway";

	}

	// These session variables will be passed to the Verify Code script below and passed to the
	// gen_cert() function, if the proper code is entered.
	$_SESSION['s_reg_password'] = $password;
	$_SESSION['s_phone'] = "";
	$_SESSION['s_phone'] = $new_phone;
	$_SESSION['s_sms_gateway'] = $sms_gateway;

	// Send the respective verification code to complete the registration.
	if ($two_fa == "s") {

		// Send sms auth code.
		sms_random_code_auth();

	} else {

		// Send code via voice
		$data = random_code();
		send_phone_auth($data);

	}

	echo "<form method=\"post\" action=\"";
	print $_SERVER['PHP_SELF'];
	echo "\">";
	echo "<b>Your one-time verification code has been sent.  Please enter it in the textbox below when it arrives and click \"Verify Code.\"</b><p>";
	echo "<input type=\"text\" size=\"6\" name=\"code\">&nbsp; <input type=\"submit\" name=\"submit\" value=\"Verify Code\">";
	exit();

} elseif ($_POST['submit'] == "Verify Code") {

	$code = $_POST['code'];

	// The code should be exactly 5 digits.
	if (!preg_match("/^[0-9]{5}$/", $code))
	  die("<b>Please enter a valid code.</b>");
	
	// $_SESSION['s_codeToEnter'] is set within the sms_random_code_auth() and send_phone_auth() functions.
	if ($code == $_SESSION['s_codeToEnter']) {

		// Pass the password the gen_cert() function.
		$password = $_SESSION['s_reg_password'];
	
		// Create the account.
		gen_cert($password);

	}

} else {


		/* Registration form */
		connection();

		// Query to retrieve SMS gateways
		$sms_sql = "SELECT gateway,provider FROM sms ORDER BY provider ASC";
	
		// Execute Query to retrieve SMS gateways
		$sms_sql_query = mysql_query($sms_sql)
		  or die("Error retrieving SMS gateways.");
	
		while($row = mysql_fetch_assoc($sms_sql_query)) {
	
	        	$gw_gateway = $row['gateway'];
	        	$gw_provider = $row['provider'];
	
	        	$sms_options .= "<option value=\"$gw_gateway\">$gw_provider - $gw_gateway</option>";
	
		}

?>

		The password must contain: <p>
		1) at least 12 characters<br />
		2) at least one lowercase letter<br />
		3) at least one UPPERCASE letter<br />
		4) at least one special character<br />
		4) at least one number<br />

		<center><table border="0">

	
			<form method="POST" action="<?php print $_SERVER['PHP_SELF']; ?>">

                                <tr>

                                        <td colspan="3" align="left"><strong><strong>*Email Address:</strong><br />
                                        <input type="text" name="email" size="45" value="<?php print $_SESSION['s_email1']; ?>"></td>

                                </tr>

				<tr>

					<td colspan="3"><strong>WARNING: If you lose your password, it cannot be recovered and all your messages will become unreadable.</strong></td>
				</tr>

                                <tr>

                                        <td align="left"><strong>*Password:</strong><br />
                                        <input type="password" name="password" value=""><br />

                                        <strong>*Confirm Password:</strong><br />
                                        <input type="password" name="confirm_pass" value=""><br />

				</tr>

				<tr>

					<td colspan="3"><strong>WARNING: If you lose your password, it cannot be recovered and all your messages will become unreadable.</strong></td>
				</tr>

				</tr>

<?php

			if ($two_factor_both == "1") {

?>
				<tr>

					<td colspan="3"><p><p><b>This system requires two-factor authentication. You can have a text message sent or you can have a voice phone call where you enter the code that is spoken to you.</b>
<br />

					<select name="two_fa">

						<option value="sms" SELECTED>Text Message</option>
						<option value="voice">Voice Code</option>

					</select>

					</td>

				</tr>

<?php 

			} else {

?>

				<tr>

					<td colspan="3"><p><p><b>This system requires two-factor authentication.  Each time you login, a code will be sent via text to the phone number you specify below.</b><br />
					<select name="two_fa">

						<?php

						if ($_SESSION['s_two_fa'] != "") {
						?>
							<option value="sms" SELECTED>Text Message</option>
						<?php

						} elseif ($_SESSION['s_two_fa'] == "Text Message") {

						?>

							<option value="sms" SELECTED>Text Message</option>

						<?php

						} else {

							echo "<option value=\"voice\" SELECTED>Voice Code</option>";
							echo "<option value=\"sms\">Text Message</option>";

						}

						?>

					</select></td>

				</tr>

<?php

			}

?>

				<tr>

                                        <td colspan="3" align="left"><strong>*Phone number where you will receive the code via a test message.</strong><br />
                                        <input type="text" name="phone" value="<?php print $_SESSION['s_phone']; ?>"> @ 

						<?php

							echo "<select name=\"sms_gw\">";
	        					echo "<option value=\"\" SELECTED>SELECT YOUR MOBILE PHONE PROVIDER</option>";
	        					echo "<option value=\"no_mobile\">I'm using voice authentication or I'm not using a mobile phone.</option>";
							print $sms_options; 
							echo "</select>";


						?><p></td>

				</tr>

				<tr>

					<td colspan="3"><strong>By clicking "Register" you are acknowledging that losing your password means your messages cannot be recovered.</strong></td>

				</tr>

				<tr>

					<td colspan="3"><input type="submit" name="submit" value="Register"></td>
				</tr>
			</form>

		</table></center></p>

<?php

html_footer();
}

?>
