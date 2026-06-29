<?php
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div style='color: green; font-weight: bold; margin-bottom: 15px;'>If that email exists in our system, a password reset link has been sent to it.</div>";
    } else {
        $message = "<div style='color: red; font-weight: bold; margin-bottom: 15px;'>Please enter a valid email address.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 300px; text-align: center; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #007BFF; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <h2>Reset Password</h2>
    <p style="font-size: 14px; color: #666;">Enter your email to receive a password reset link.</p>
    <?php echo $message; ?>
    <form action="forgot_password.php" method="POST">
        <input type="email" name="email" required placeholder="Enter your email address">
        <button type="submit">Send Reset Link</button>
    </form>
    <br>
    <a href="login.php" style="font-size: 14px; color: #007BFF;">Back to Login</a>
</div>
</body>
</html>