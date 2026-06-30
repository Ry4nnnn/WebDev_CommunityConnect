<?php
// This variable stores success or error messages.
// The message will be displayed later in the HTML section.
$message = "";

// Check if the form has been submitted using the POST method.
// This block only runs after the user clicks the "Send Reset Link" button.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the email entered by the user.
    // trim() removes extra spaces before and after the email.
    $email = trim($_POST['email']);

    // Validate whether the entered email is in a proper email format.
    // FILTER_VALIDATE_EMAIL checks if the input looks like a real email address.
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // If the email format is valid, show a success message.
        // This is a simulated password reset message.
        // The system does not actually send an email in this project.
        $message = "<div class='alert success'>If that email exists in our system, a password reset link has been sent to it.</div>";
    } else {
        // If the email format is invalid, show an error message.
        $message = "<div class='alert error'>Please enter a valid email address.</div>";
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
    <title>Forgot Password - CommunityConnect</title>

    <!-- Link to the external CSS file for consistent page styling -->
    <link rel="stylesheet" href="style.css">
</head>

<!-- auth-page class is used to apply the login/register/forgot password page design -->
<body class="auth-page">

<!-- Main wrapper for the authentication page layout -->
<div class="auth-wrapper">

    <!-- Website logo/title section -->
    <div class="logo-text">
        <h1>CommunityConnect</h1>
        <p>Building a Stronger and More Connected Community</p>
    </div>

    <!-- Small container used for the forgot password form -->
    <div class="container-small">
        <h2>Reset Password</h2>

        <!-- Short instruction telling the user what to do -->
        <p style="font-size: 14px; color: #666; text-align: center;">
            Enter your email to receive a password reset link.
        </p>

        <!-- Display success or error message after form submission -->
        <?php echo $message; ?>

        <!-- Forgot password form -->
        <!-- When submitted, the form sends data back to forgot_password.php using POST -->
        <form action="forgot_password.php" method="POST">

            <!-- Email input field -->
            <label>Email Address:</label>
            <input type="email" name="email" required placeholder="Enter your email address">

            <!-- Submit button for the form -->
            <button type="submit">Send Reset Link</button>
        </form>

        <!-- Link section for returning to the login page -->
        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>

</body>
</html>