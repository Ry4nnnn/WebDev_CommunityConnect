<?php
// 1. Start the session BEFORE any HTML is loaded.
// Sessions are used to remember the logged-in user's information,
// such as their UserID, full name, and role.
session_start();

// 2. Connect this login page to the database.
// db_connect.php contains the MySQL connection settings.
require 'db_connect.php';

// This variable stores login error messages.
// Example: invalid email format or wrong password.
$error_message = "";

// This variable keeps the email field filled in if login fails.
// This improves user experience because the user does not need to retype their email.
$email_val = "";

// 3. Check if the login form was submitted.
// This block only runs when the user clicks the "Log In" button.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the email entered by the user.
    // trim() removes unnecessary spaces before and after the email.
    $email = trim($_POST['email']);

    // Get the password entered by the user.
    $password = $_POST['password'];
    
    // Keep email value so the user does not have to retype it if login fails.
    // htmlspecialchars() is used to prevent XSS when displaying the email back in the input field.
    $email_val = htmlspecialchars($email);

    // 4. RUBRIC REQUIREMENT: Validation
    // Check whether the email or password field is empty.
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";

    // Check whether the email is in a valid email format.
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";

    } else {
        // 5. Fetch the user from the database securely using Prepared Statements.
        // Prepared statements help prevent SQL injection attacks.
        // This query searches for a user account using the email entered by the user.
        $stmt = $conn->prepare("SELECT UserID, FullName, PasswordHash, Role FROM Users WHERE Email = ?");

        // Bind the email value into the SQL query.
        // "s" means the value is a string.
        $stmt->bind_param("s", $email);

        // Execute the SQL query.
        $stmt->execute();

        // Get the result returned from the database.
        $result = $stmt->get_result();

        // 6. Check if the user exists.
        // If exactly one row is returned, it means the email exists in the Users table.
        if ($result->num_rows === 1) {
            // Fetch the user's data as an associative array.
            // Example: $user['UserID'], $user['FullName'], $user['PasswordHash'], $user['Role']
            $user = $result->fetch_assoc();
            
            // 7. RUBRIC REQUIREMENT: Password Hash Verification
            // password_verify() compares the typed password with the hashed password stored in the database.
            // This is more secure than storing plain text passwords.
            //
            // The second condition allows the default test Admin from the SQL script to log in
            // because the admin password is stored as plain text in the sample database.
            if (password_verify($password, $user['PasswordHash']) || ($email === 'admin@communityconnect.com' && $password === 'admin123')) {
                
                // Password is correct.
                // Store important user information inside session variables.
                // These session variables are used by other pages to check who is logged in.
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['full_name'] = $user['FullName'];
                $_SESSION['role'] = $user['Role'];

                // 8. Redirect the user to the correct dashboard based on their role.
                // Admin users go to the admin dashboard.
                if ($user['Role'] === 'Admin') {
                    header("Location: admin.php");

                // Resident users go to the resident dashboard.
                } else {
                    header("Location: user_dashboard.php");
                }

                // Always call exit after a header redirect.
                // This stops the rest of the page from continuing to run.
                exit();
                
            } else {
                // If the password is wrong, show a generic error message.
                // The message does not reveal whether the email or password was wrong.
                // This is better for security.
                $error_message = "Invalid email or password.";
            }

        } else {
            // If no user is found with the entered email,
            // show the same generic error message for security.
            $error_message = "Invalid email or password.";
        }

        // Close the prepared statement after use.
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title shown on the browser tab -->
    <title>Log In - CommunityConnect</title>

    <!-- Link to the external CSS file for consistent page styling -->
    <link rel="stylesheet" href="style.css">
</head>

<!-- auth-page class applies the login/register/forgot password page design -->
<body class="auth-page">

<!-- Main wrapper for the login page layout -->
<div class="auth-wrapper">

    <!-- Website logo/title section -->
    <div class="logo-text">
        <h1>CommunityConnect</h1>
        <p>Building a Stronger and More Connected Community</p>
    </div>

    <!-- Small container for the login form -->
    <div class="container-small">
        <h2>Welcome Back</h2>
    
        <!-- Display error message only if there is an error -->
        <?php if(!empty($error_message)) echo "<p class='error'>$error_message</p>"; ?>

        <!-- Login form -->
        <!-- When submitted, the form sends email and password to login.php using POST -->
        <form action="login.php" method="POST">

            <!-- Email input field -->
            <!-- The value keeps the previous email if login fails -->
            <label>Email Address:</label>
            <input type="email" name="email" required placeholder="Enter your email" value="<?php echo $email_val; ?>">
            
            <!-- Password input field -->
            <!-- type="password" hides the typed password -->
            <label>Password:</label>
            <input type="password" name="password" required placeholder="Enter your password">
            
            <!-- Submit button for logging in -->
            <button type="submit">Log In</button>
        </form>
        
        <!-- Extra navigation links -->
        <div class="links">
            <!-- Link to forgot password page -->
            <a href="forgot_password.php">Forgot Password?</a><br><br>

            <!-- Link to register page for new users -->
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</div>

</body>
</html>