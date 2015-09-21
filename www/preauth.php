<?php

include('function.php');

loggedIn();

if ($_SESSION['s_preauth'] == "HAS_PREAUTH") {

	header('Location: msg_main.php');
	exit();

}

if ($_POST['submit'] == "Submit") {

	$code = $_POST['code'];

	if (!preg_match("/^[0-9]{5}$/", $code))
	  die("Invalid code.");

	if ($code == $_SESSION['s_codeToEnter']) {

		// Prevent multiple auths in case the user returns to this page.
		$_SESSION['s_preauth'] = "HAS_PREAUTH";

		$_SESSION['s_codeToEnter'] = "";
		header('Location: msg_main.php');
		exit();

	} else {

		echo "Invalid Code Entered.";
		exit();

	}

} else {

	if ($_SESSION['s_delivery'] == "s") {

		// sms auth
		sms_random_code_auth();

	} else {
	
		// Phone auth
		$data = random_code();
		send_phone_auth($data);

	}

?>

	<b> Your security code has been sent and you will receive a call (there will be a two second delay before hearing the code).  Please enter the code in the box below.</b><p>
	<form method="post" action="<?php print $_SERVER['PHP_SELF']; ?>">

		One-time code: <input type="text" size="6" name="code">&nbsp;&nbsp;<input type="submit" name="submit" value="Submit">

	<form>



<?php

html_footer();

}

?>
