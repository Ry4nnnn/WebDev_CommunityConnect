<?php
// Redirect the user to the login page.
// This is usually used in index.php so that when users visit the main folder,
// they are automatically sent to login.php.
header("Location: login.php");

// Stop the script immediately after redirecting.
// This prevents any other code from running after the redirect.
exit();
?>
