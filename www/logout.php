<?php
include('function.php');
// Want to be sure these are set to null
$_SESSION['s_priv_tmp'] = "";
$_SESSION['s_pass'] = "";

//remove all the variables in the session
// The PHPSec libraries handles destroying the SESSION
// variables, when this is called.
session_unset(); 
$_SESSION = array();

// Redirect to login screen
header('Location: index.php');
exit();

?>
