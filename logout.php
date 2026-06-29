<?php
// 1. Resume the current active session (find the keycard)
session_start();

// 2. Unset all session variables (erase the data on the keycard)
session_unset();

// 3. Destroy the session completely (shred the keycard)
session_destroy();

// 4. Kick the user back to the login page
header("Location: login.php");
exit(); // Always use exit after a redirect to stop the script
?>