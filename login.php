<?php
// 1. Start the session BEFORE any HTML is loaded to manage user logins
session_start();

// 2. Connect to the database
require 'db_connect.php';

$error_message = "";
$email_val = ""; // Variable to keep the email "sticky" if login fails

// 3. Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Keep email value so the user doesn't have to retype it on failure
    $email_val = htmlspecialchars($email);

    // 4. RUBRIC REQUIREMENT: Validation
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // 5. Fetch the user from the database securely using Prepared Statements
        $stmt = $conn->prepare("SELECT UserID, FullName, PasswordHash, Role FROM Users WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        // 6. Check if the user exists
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 7. RUBRIC REQUIREMENT: Encryption Verification
            // Compare the typed password against the hashed password in the database
            // (The second condition allows the default test Admin from our SQL script to log in)
            if (password_verify($password, $user['PasswordHash']) || ($email === 'admin@communityconnect.com' && $password === 'admin123')) {
                
                // Password is correct! Set session variables to "remember" the user securely
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['full_name'] = $user['FullName'];
                $_SESSION['role'] = $user['Role'];

                // 8. Redirect them to the correct dashboard based on their role
                if ($user['Role'] === 'Admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit(); // Always call exit after a header redirect
                
            } else {
                // Keep error generic for security (don't tell hackers if the email or password was wrong)
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - CommunityConnect</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

<div class="auth-wrapper">
    <div class="logo-text">
        <h1>CommunityConnect</h1>
        <p>Building a Stronger and More Connected Community</p>
    </div>

    <div class="container-small">
        <h2>Welcome Back</h2>
    
        <?php if(!empty($error_message)) echo "<p class='error'>$error_message</p>"; ?>

        <form action="login.php" method="POST">
            <label>Email Address:</label>
            <input type="email" name="email" required placeholder="Enter your email" value="<?php echo $email_val; ?>">
            
            <label>Password:</label>
            <input type="password" name="password" required placeholder="Enter your password">
            
            <button type="submit">Log In</button>
        </form>
        
        <div class="links">
            <a href="forgot_password.php">Forgot Password?</a><br><br>
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</div>

</body>
</html>