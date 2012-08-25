<?php

/*******************************************************************************************

Edit below as needed for your environment.

*******************************************************************************************/

// Implements its own session_save_handler()
require_once 'SecureSession.php';

// change the default session folder in a temporary dir
$sessionPath = '/var/rand/data';
session_save_path($sessionPath);
session_start();

if (empty($_SESSION['time'])) {
    $_SESSION['time'] = time();
} 

/* Change this to your DB settings  */
$db_user = "user";
$db_pass = "password";
$db_name = "DATABASE";
$db_host = "HOST";

// The url and path to the h2h messenger web root, *NO* trailing slash.
// CHANGE THIS TO YOUR DOMAIN
$site_url = "secure.sukkha.info/enc";
// This doesn't need to be a real address for your domain.
$from_email = "noreply@sukkha.info";

// Lockout time
// The user will be able to login after the number of minutes below.
// Value is in minutes.
$lockout_time = "15";

// Failed logins
// Number of failed logins before the user is locked out.
$failed_count = "5";

// Change to 1 to enable two factor authentication for SMS AND voice phone call options.
$two_factor_both = "1";

// Dial pattern.
// I use Junction networks for my outbound calling and a "1" has to be prepended to all
// calls.  Add what is required for your implementation such as "91", assuming 9 is required
// to be dialed for your dialplan.
// NOT YET IMPLEMENTED.
// The current system works by requiring a "1" to be prepended to the number.
//$dial_pattern = "1";

// Caller ID
// If supported, by your voip provider enter your caller id to be displayed when placing a
// call for authentication.
$caller_id = "YOUR NUMBER";

/*******************************************************************************************

Edit below as needed for your environment.

*******************************************************************************************/

// Not yet implemented
/**function check_timeout() {

	// Check if it time to timeout the session.

	// Set current time
	$t_check_timeout_2 = date("YmdHi");

	$_SESSION['s_check_timeout_2'] = "";
	$_SESSION['s_check_timeout_2'] = $t_check_timeout_2;

	// Existing time
	$t_check_timeout_1 = $_SESSION['s_check_timeout_1'];

	// Subtract time
	$t_subtract_time = $t_check_timeout_2 - $t_check_timeout_1;

	if ($t_subtract_time > $_SESSION['s_timeout']) {

		// Want to be sure these are set to null
		$_SESSION['s_priv_tmp'] = "";
		$_SESSION['s_pass'] = "";

		//remove all the variables in the session
		session_unset();
		session_destroy();

	} else {

		$_SESSION['s_check_timeout_1'] = $t_check_timeout_2;

	}

}*/

// Check to see if the user has been authenticated.
function loggedIn() {

	//check_timeout();

	if ($_SESSION['s_authed'] != "USER_AUTHENTICATED") {

       		session_start();
		session_destroy();
		header("Location: index.php");
		die("You must be logged in.");

	}

}

// Menu displayed when the user has authenticated.
function menu() {

	$logged_in = $_SESSION['s_email'];

	echo "Logged in as $logged_in:  <center><a href=\"msg_main.php\">Home</a> &nbsp;&nbsp; <b>||</b> &nbsp;&nbsp;<a href=\"change_pass.php\">Change Passphrase</a>&nbsp;&nbsp; <b>||</b> &nbsp;&nbsp;<a href=\"search.php\">Search for Keys</a> &nbsp;&nbsp; <b>||</b> &nbsp;&nbsp;<a href=\"logout.php\">Logout</a></center>";
	echo "<hr>";

}

// Timezone
date_default_timezone_set("UTC");

// DB Connection
function connection() {

	global $db_user, $db_pass, $db_name, $db_host;

	$connection = mysql_connect($db_host,$db_user,$db_pass)
          or die("Unable to connect to MySQL DB.");

        mysql_select_db($db_name, $connection)
          or die("Unable to select database.");

	return $connection;

}

/*** USER AUTHENTICATION HASH ***/
// The passphrase will remain the same until the user changes it
function generateHash($upassword, $salt = null) {

define('SALT_LENGTH', 23);

    if ($salt === null) {

        $salt = substr(md5(uniqid(rand(), true)), 0, SALT_LENGTH);

    } else {

        $salt = substr($salt, 0, SALT_LENGTH);

    }

    return $salt . hash('sha512',$salt . $upassword);

} // end generateHash function

/**** This passphrase is stored encrypted in the DB with the user's private key.
	It is also set as a session variable, but encrypted, until it is needed.
	PHPSec handles the encryption of the session variable with an encrypted key
	that is stored as a cookie on the client and is changed every thirty seconds.

NOTE That the resulting hash is the passphrase for the private key.  Good idea or bad idea?  I don't know.  This hash and the
private key are encrypted with the passphrase the user uses to login.

*/

// Generate a unique private passphrase
// The passphrase will remain the same until the user changes it
function generatePassphrase($upassword, $salt = null) {

define('P_SALT_LENGTH', 23);

    if ($salt === null) {

        $salt = substr(md5(uniqid(rand(), true)), 0, P_SALT_LENGTH);

    } else {

        $salt = substr($salt, 0, P_SALT_LENGTH);

    }

    // sha512
    return $salt . hash('sha512',$salt . $upassword);

} // end generateHash function

function gen_cert($upassword) {

	// Values automatically populated for the SSL certificate for anonymity
        $country = "YY";
        $state = "XX";
        $city = "Somewhere";
	$orgName = "no org";
	$orgUnitName = "no org unit";
        $businessname = "mind your own business";
        $commonName = "no name";

	// Get email address
	$emailAddress = $_SESSION['s_email1'];

        /** Create Private and Public Key pairs */
	/** sumadhuracool at gmail dot com 23-Jun-2011 04:22 => http://www.php.net/manual/en/function.openssl-public-encrypt.php */
        $dn = array("countryName" => $country, "stateOrProvinceName" => $state, "localityName" => $city, "organizationName" =>$orgName, "organizationalUnitName" => $orgUnitName, "commonName" => $commonName, "emailAddress" => $emailAddress);

	// Users may be required to change their passphrase on a routine basis, due to organizational policies.
	// PHP currently doesn't have an option to allow users to change their private key passphrase
	// Accordingly, the passphrase has to be changed via the commandline. :-/
        // Password to insert into the database.
	$upassword = escapeshellcmd($upassword);

	// Password to encrypt the private key and its passphrase while stored in the database.
	$enc_pass = $upassword;

	// Private Key credentials
        $privkeypass = generatePassphrase($upassword);

	// Keys expire after 5 years
        $numberofdays = 1826;

        // Create the 2048-bit RSA key
	/** sumadhuracool at gmail dot com 23-Jun-2011 04:22 => http://www.php.net/manual/en/function.openssl-public-encrypt.php */
        $privkey = openssl_pkey_new(array('private_key_bits' => 2048,'private_key_type' => OPENSSL_KEYTYPE_RSA));
        $csr = openssl_csr_new($dn, $privkey);
        $sscert = openssl_csr_sign($csr, null, $privkey, $numberofdays);
        openssl_x509_export($sscert, $publickey);
        openssl_pkey_export($privkey, $privatekey, $privkeypass);
        openssl_csr_export($csr, $csrStr);

	// Here is where the hash from generatePassphrase (passphrase for the private key) and the private key are delimited by an @
	// and encrypted using the unsalted and unhashed passphrase (the cleartext passphrase) the user uses to authenticate, $enc_pass.
	// This is done because the hashed password is stored in cleartext in the DB.  Accordingly, if the DB was jacked, then the
	// 'acker would have the passphrase to decrypt the private key and its accompanying passphrase.
	// With this setup, they'd have to try and crack the hash to get the cleartext passphrase, to do their deed.
	// The hash for authentication is salted, which makes rainbow tables computationally infeasible.

	// Updated to encrypt the phone number and SMS gateway of the user.
	$phone_no = $_SESSION['s_phone'];
	$sms_gateway = $_SESSION['s_sms_gateway'];

        /** Encrypt the private key and base64_encode it to store in the database. */
        $sealed_priv = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $enc_pass, $privkeypass."@".$privatekey."@".$phone_no."@".$sms_gateway, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));

        //return $encryptedPrivate;

	// Generate hashed pass to store in db, this is the salted and hashed passphrase for the user to authenticate.
        $hashed = generateHash($upassword);

	// DB connection
        $connection = connection();

	// Insert user account information
	$clean_email = mysql_real_escape_string($emailAddress);
	$clean_two_fa = mysql_real_escape_string($_SESSION['s_two_fa']);
	// I will be 100 on 2075-03-01. hehehe
        $sql = "INSERT INTO users VALUES('','$emailAddress', '$clean_two_fa', '$hashed', '$sealed_priv', '$publickey', '10', '0', '2075-03-01 12:00:00')";

	$sql_result = mysql_query($sql,$connection)
          or die("Unable to execute mysql query." .mysql_error());

        if ($sql_result) {

		echo "Your account has been successfully created.<p>";
		echo "Click <a href=\"index.php\">here</a> to login.";

		$_SESSION['s_businessname'] = "";
		$_SESSION['s_first_name'] = "";
		$_SESSION['s_last_name'] = "";
		$_SESSION['s_city'] = "";
		$_SESSION['s_state'] = "";
		$_SESSION['s_email1'] = "";
		$_SESSION['s_country'] = "";
		$_SESSION['s_reg_password'] = "";
	        $_SESSION['s_reg_email'] = "";
		$_SESSION['s_phone'] = "";
		$_SESSION['s_two_fa'] = "";
		$_SESSION['s_codeToEnter'] = "";
		$_SESSION['s_reg_gateway'] = "";
		$_SESSION['s_sms_gateway'] = "";
		$_SESSION = array();
		exit();

        } else {

                echo "Error creating account.";

        }

} // end gen_cert() function

function encrypt($message) {

	/** Encrypt the message base64_encode it to store in the database. */
        $sealed_message = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $_SESSION['s_pass'], $message, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));

	return $sealed_message;

}

function decrypt($message) {

	/** Decrypt the message base64_decode. */
	$unseal_message = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $_SESSION['s_pass'], base64_decode($message), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));

	return $unseal_message;

}
function priv_tmp_decrypt($private_key,$pass) {

	$unseal_priv = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $pass, base64_decode($private_key), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));

	return $unseal_priv;

}

function sign_msg($msg) {

	// Just pass the key as defined above
	$privatekey = openssl_get_privatekey($_SESSION['s_priv_tmp'],$_SESSION['s_pass']);

	openssl_sign($msg, $binary_signature, $privatekey, OPENSSL_ALGO_SHA1);

	$t_signature = base64_encode($binary_signature);

	return $t_signature;

}

function verify_msg($msg,$binary_signature,$public_key) {

	// Check signature
	$ok = openssl_verify($msg, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);

	if ($ok == 1) {

		$ok = "1";

	} elseif ($ok == 0) {
    
		$ok = "0";

	} else {

		$ok = "2";
	
	}

	return $ok;

}

// Encrypt the RC4 key that encrypts the message with the user's public key
function e_seal($source,$pub_key) {

		// Need to credit the author I got this function from.
		/** sumadhuracool at gmail dot com 23-Jun-2011 04:22 => http://www.php.net/manual/en/function.openssl-public-encrypt.php */
		//Encryption with public key
                //path holds the certificate path present in the system               
                openssl_get_publickey($pub_key);
                $j=0;
                $x=strlen($source)/10;
                $y=floor($x);
                for($i=0;$i<$y;$i++)
                {
                $crypttext='';
               
                openssl_public_encrypt(substr($source,$j,10),$crypttext,$pub_key);$j=$j+10;
                $crt.=$crypttext;
                $crt.=":::";
                }
                if((strlen($source)%10)>0)
                {
                openssl_public_encrypt(substr($source,$j),$crypttext,$pub_key);
                $crt.=$crypttext;
                }   
                return($crt);
               
}

//Decryption with private key
/** sumadhuracool at gmail dot com 23-Jun-2011 04:22 => http://www.php.net/manual/en/function.openssl-public-encrypt.php */
function d_seal($crypttext,$priv_key,$privKey) {
// Need to credit the author I got this function from.
                $res1= openssl_get_privatekey($priv_key,$privKey);
                $tt=explode(":::",$crypttext);
                $cnt=count($tt);
                $i=0;
                while($i<$cnt)
                {
                openssl_private_decrypt($tt[$i],$str1,$res1);
                $str.=$str1;
                $i++;
                }
                return $str;

} // end function d_seal

function get_user($id) {

	$conn = connection();

	// Retrieve user information
	$clean_id = mysql_real_escape_string($id);
        $sql = "SELECT id,email,pub_key FROM users WHERE id = '$clean_id'";

	// Execute the query
	$sql_result = mysql_query($sql,$conn)
	  or die("Unable to retrieve email.");

	// Retrieve user information
	$i = mysql_fetch_object($sql_result);

	$uid = $i->id;
	$email = $i->email;
	$pubkey = $i->pub_key;
	
	$values = "$uid|$email|$pubkey";
	
	return $values;

} // end function get_user

// Check password strength
function check_pass($password,$confirm_pass) {

	if ($password != $confirm_pass) {
          
          $error .= "The passwords don't match!";

        }

        if (strlen($password) < 12) {

          $error .= "Passwords must be at least 12 characters! <br />";

        }

        if(!preg_match("/[0-9]+/", $password) ) {
        
          $error .= "Password must include at least one number! <br />";
        
        }

        if(!preg_match("/[a-z]+/", $password) ) {
        
          $error .= "Password must include at least one lowercase letter! <br />";

        }

        if(!preg_match("/[A-Z]+/", $password) ) {
        
          $error .= "Password must include at least one CAPS! <br />";

        }

        if(!preg_match("/\W+/", $password) ) {

          $error .= "Password must include at least one symbol! <br />";
        
        }

        if ($error) {

               $result = "Password validation failure: $error";

        } else {
        
               $result = "Strong.";

        }

	return $result;


} // end check_pass

function validatePhone($string,$message) {

	// Remove non-numerical characters.
	$numbersOnly = preg_replace("/[^0-9]/", "", $string);

	// Double check for numbers.
	if (!is_numeric($numbersOnly))
	  die($message);
	
	// valid phone number should be under 15 digits.
	$numberOfDigits = strlen($numbersOnly);

	if ($numberOfDigits < 15) {
	
	} else {
	
		die($message);
	
	}

	return $numbersOnly;

} // end function validatePhone

// Send SMS for authentication
function sms_random_code_auth() {

	// Generate a 5 digit number
        // http://elementdesignllc.com/2011/06/generate-random-10-digit-number-in-php/
        $random = substr(number_format(time() * rand(),0,'',''),0,5);

        // Set as a session variable to be passed to the check to determine if the code is valid.
        $_SESSION['s_codeToEnter'] = $random;

	// send code if this is the registration process
	if ($_SESSION['s_reg_gateway'] != "") {
	
		// If they are registering, then use this number.
		$phone_no = $_SESSION['s_reg_gateway'];

	} else {

		// Otherwise, use the one already defined.
		$phone_no = $_SESSION['s_sms_gateway'];

	}

	// Send the sms code
	$send_sms_code = mail($phone_no, "Code", "Your code is:" . $random, "From: noreply@sukkha.info");

	if (!$send_sms_code)
	  die("<b>Error sending SMS code.</b>");

} // end sms_random_code function

// Performs voice call for authentication
function random_code() {
global $caller_id;

	// Generate a 5 digit number
        // http://elementdesignllc.com/2011/06/generate-random-10-digit-number-in-php/
        $random = substr(number_format(time() * rand(),0,'',''),0,5);

        // Set as a session variable to be passed to the check to determine if the code is valid.
        $_SESSION['s_codeToEnter'] = $random;

	if ($_SESSION['s_reg_phone'] != "") {
	
		// If they are registering, then use this number.
		$phone_no = $_SESSION['s_reg_phone'];

	} else {

		// Otherwise, use the one already defined.
		$phone_no = $_SESSION['s_phone'];

	}

        // Random numbers to put into the call file
        $num1 = $random[0];
        $num2 = $random[1];
        $num3 = $random[2];
        $num4 = $random[3];
        $num5 = $random[4];

        // Callout file
        $call_data = "Channel: IAX2/jnctn_out/$phone_no\n";
        $call_data .= "Callerid:". $caller_id."\n";
        $call_data .= "Application: Background\n";
        // Reads "Please enter your one time password <pause for one second> <reads code with one second delays>.  Your one time password is <reads code again>. Goodbye" then hangs up the call.
        $call_data .= "Data: silence/2&en/please-enter-the&digits/1&en/time&vm-password&silence/1&digits/$num1&silence/1&digits/$num2&silence/1&digits/$num3&silence/1&digits/$num4&silence/1&digits/$num5&silence/2&en/your&vm-password&en/is&digits/$num1&digits/$num2&digits/$num3&digits/$num4&digits/$num5&en/goodbye";

        $num1 = "";
        $num2 = "";
        $num3 = "";
        $num4 = "";
        $num5 = "";

	return $call_data;

} // end random_code function

function send_phone_auth($data) {

	$call_data = $data;

	// Generate a 15 digit number for the file.
        $rand_file = md5(substr(number_format(time() * rand(),0,'',''),0,15));

        // write the callout file
        $myFile = "/tmp/$rand_file.call";

        $fh = fopen($myFile, 'w')
          or die("Unable to process one-time code.");
        fwrite($fh, $call_data);
        fclose($fh);

        $old = "/tmp/$rand_file.call";
        $new = "/var/spool/asterisk/outgoing/$rand_file.call";
        rename($old, $new)
          or die("Unable to process one-time code at this time. Please try again later.");

        // Emptying these variables to be on the safe side.
        $random = "";
        $rand_file = "";
        $myFile = "";
        $old = "";
        $new = "";
        $call_data = "";

} // end send_phone_auth

// sprintf and mysql_real_escape_string together.
// http://www.jaygilford.com/php/sprintf-and-mysql_real_escape_string-all-in-one-function/
function mressf() {

	$args = func_get_args();
	if (count($args) < 2)
	  return false;
	$query = array_shift($args);
	$args = array_map('mysql_real_escape_string', $args);
	array_unshift($args, $query);
	$query = call_user_func_array('sprintf', $args);
	return $query;

}


// Clean data before inserting into db query
function cleanup($value) {

        $value = mysql_real_escape_string($value);

        return $value;

}

function html_header() {

?>
<!DOCTYPE html>
<!--[if lt IE 7 ]><html class="ie ie6" lang="en"> <![endif]-->
<!--[if IE 7 ]><html class="ie ie7" lang="en"> <![endif]-->
<!--[if IE 8 ]><html class="ie ie8" lang="en"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="en"> <!--<![endif]-->
<head>
<style type="text/css">
        .row0 {
            background-color: #CACAFF;
        }
        
        .row1 {
            background-color: #ffffff;
        }
        </style>

        <!-- Basic Page Needs
  ================================================== -->
        <meta charset="utf-8">
        <title>h2H Messenger</title>
        <meta name="description" content="">
        <meta name="author" content="">

        <!-- Mobile Specific Metas
  ================================================== -->
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

        <!-- CSS
  ================================================== -->
        <link rel="stylesheet" href="stylesheets/base.css">
        <link rel="stylesheet" href="stylesheets/skeleton.css">
        <link rel="stylesheet" href="stylesheets/layout.css">

        <!--[if lt IE 9]>
                <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <!-- Favicons
        ================================================== -->
        <link rel="shortcut icon" href="images/favicon.ico">
        <link rel="apple-touch-icon" href="images/apple-touch-icon.png">
        <link rel="apple-touch-icon" sizes="72x72" href="images/apple-touch-icon-72x72.png">
        <link rel="apple-touch-icon" sizes="114x114" href="images/apple-touch-icon-114x114.png">

</head>
<body>

<div class="container">
                <div class="sixteen columns">
                        <hr />
                </div>
                <div class="one-half column">
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
&nbsp;
<p>
                </div>
                <div class="one-half column">

<?

}

function html_footer() {

	
	?>

	</div>
                <!--div class="one-third column">
&nbsp;
                </div-->

        </div><!-- container -->


<!-- End Document
================================================== -->
</body>
</html>

	<?

}

?>
