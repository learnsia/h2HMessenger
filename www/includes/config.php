/*
*
* h2Hmessenger config file.  Edit this to suit your environment
*
*
*/

/* Change this to your DB settings  */
$db_user = "";
$db_pass = "";
$db_name = "";
$db_host = "";

/* Public account search */
$db_user_public = "";
$db_user_pass = "";

// The url and path to the h2h messenger web root, *NO* trailing slash.
// CHANGE THIS TO YOUR DOMAIN
$site_url = "";
// This doesn't need to be a real address for your domain.
$from_email = "";

// Lockout time
// The user will be able to login after the number of minutes below.
// Value is in minutes.
$lockout_time = "15";

// Failed logins
// Number of failed logins before the user is locked out.
$failed_count = "5";

// Change to 0 to disable two-factor auth
// Change to 1 for SMS two-factor auth and password.
// Change to 2 to enable two factor authentication for SMS AND voice phone call options.
$two_factor_both = "0";

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