<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CommunityConnect</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-page">

<div class="auth-wrapper">
    <div class="logo-text">
        <h1>CommunityConnect</h1>
        <p>Building a Stronger and More Connected Community</p>
    </div>

    <div class="container-small">
        <h2>Resident Registration</h2>
        
        <?php echo $message; ?>

        <form action="register.php" method="POST">
            <label>Full Name:</label>
            <input type="text" name="fullname" required placeholder="John Doe" value="<?php echo $fullname_val; ?>">
            
            <label>Email Address:</label>
            <input type="email" name="email" required placeholder="john@example.com" value="<?php echo $email_val; ?>">
            
            <label>Password:</label>
            <input type="password" name="password" required placeholder="Min. 6 chars, 1 letter, 1 number">
            
            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required placeholder="Type password again">
            
            <button type="submit">Register Account</button>
        </form>
        
        <div class="links">
            Already have an account? <br>
            <a href="login.php">Log in here</a>
        </div>
    </div>
</div>

</body>
</html>