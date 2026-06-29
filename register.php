<?php
// 1. Connect to the database
require 'db_connect.php';

// This variable stores success or error messages
$message = "";

// These variables keep the form values after an error
$fullname_val = "";
$email_val = "";

// 2. Check if the register form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. Get and clean the form data
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Keep form values so user does not need to retype after error
    $fullname_val = htmlspecialchars($fullname);
    $email_val = htmlspecialchars($email);

    // 4. Backend validation
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = "<div class='alert error'>All fields are required.</div>";
    }

    // Name can only contain letters and spaces
    elseif (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $message = "<div class='alert error'>Name can only contain letters and spaces.</div>";
    }

    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert error'>Invalid email format.</div>";
    }

    // Password must contain at least 6 characters, 1 letter, and 1 number
    elseif (!preg_match("/^(?=.*[A-Za-z])(?=.*\d).{6,}$/", $password)) {
        $message = "<div class='alert error'>Password must be at least 6 characters long and include at least one letter and one number.</div>";
    }

    // Confirm password must match password
    elseif ($password !== $confirm_password) {
        $message = "<div class='alert error'>Passwords do not match.</div>";
    }

    else {
        // 5. Check if email already exists
        $stmt = $conn->prepare("SELECT Email FROM Users WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "<div class='alert error'>This email is already registered.</div>";
        } else {

            // 6. Hash the password before saving to database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 7. Insert new resident account into database
            $insert_stmt = $conn->prepare("INSERT INTO Users (FullName, Email, PasswordHash, Role) VALUES (?, ?, ?, 'Resident')");
            $insert_stmt->bind_param("sss", $fullname, $email, $hashed_password);

            if ($insert_stmt->execute()) {
                $fullname_val = "";
                $email_val = "";
                $message = "<div class='alert success'>Registration successful! <a href='login.php'>Log in here</a>.</div>";
            } else {
                $message = "<div class='alert error'>Database error: " . $conn->error . "</div>";
            }

            $insert_stmt->close();
        }

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

    <title>Register - CommunityConnect</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css?v=6">
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