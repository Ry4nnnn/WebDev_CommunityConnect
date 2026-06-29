<?php
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert success'>If that email exists in our system, a password reset link has been sent to it.</div>";
    } else {
        $message = "<div class='alert error'>Please enter a valid email address.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - CommunityConnect</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-page">

<div class="auth-wrapper">
    <div class="logo-text">
        <h1>CommunityConnect</h1>
        <p>Building a Stronger and More Connected Community</p>
    </div>

    <div class="container-small">
        <h2>Reset Password</h2>

        <p style="font-size: 14px; color: #666; text-align: center;">
            Enter your email to receive a password reset link.
        </p>

        <?php echo $message; ?>

        <form action="forgot_password.php" method="POST">
            <label>Email Address:</label>
            <input type="email" name="email" required placeholder="Enter your email address">

            <button type="submit">Send Reset Link</button>
        </form>

        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>

</body>
</html>