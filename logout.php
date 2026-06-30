<?php
// Start or resume the current session.
// This is needed so PHP can access the logged-in user's session data.
session_start();

// Remove all session variables.
// This clears stored user data such as user_id, full_name, and role.
session_unset();

// Destroy the current session completely.
// After this, the user is no longer considered logged in.
session_destroy();

// Redirect the user back to the login page after logging out.
header("Location: login.php");

// Stop the script after redirecting.
// This prevents any further code from running.
exit();
?>
