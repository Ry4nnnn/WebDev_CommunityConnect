<?php
// 1. Connect to the database
require 'db_connect.php';

$message = ""; // Variable to hold success/error messages

// Variables to keep form data "sticky" (remembered) if an error occurs
$fullname_val = "";
$email_val = "";

// 2. Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 3. Sanitize and grab the data from the HTML form
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Keep the values to repopulate the form (using htmlspecialchars to prevent XSS attacks)
    $fullname_val = htmlspecialchars($fullname);
    $email_val = htmlspecialchars($email);

    // 4. RUBRIC REQUIREMENT: Advanced Backend Validation (Regex)
    
    // Check for empty fields first (Fastest check)
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = "<p style='color: red; text-align: center;'>All fields are required.</p>";
    } 
    // REGEX: Name should only contain letters and spaces
    elseif (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $message = "<p style='color: red; text-align: center;'>Name can only contain letters and spaces.</p>";
    }
    // Built-in Email Validation (Industry standard for emails)
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<p style='color: red; text-align: center;'>Invalid email format.</p>";
    } 
    // REGEX: Password must be min 6 chars, with at least one letter and one number
    elseif (!preg_match("/^(?=.*[A-Za-z])(?=.*\d).{6,}$/", $password)) {
        $message = "<p style='color: red; text-align: center;'>Password must be at least 6 characters long and include at least one number and one letter.</p>";
    } 
    // Verify passwords match
    elseif ($password !== $confirm_password) {
        $message = "<p style='color: red; text-align: center;'>Passwords do not match.</p>";
    } 
    else {
        // Validation passed! Now check if the email already exists in the database
        $stmt = $conn->prepare("SELECT Email FROM Users WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "<p style='color: red; text-align: center;'>Error: Email is already registered.</p>";
        } else {
            // 5. RUBRIC REQUIREMENT: Password Encryption
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 6. Secure Database Insertion (Prepared Statements)
            $insert_stmt = $conn->prepare("INSERT INTO Users (FullName, Email, PasswordHash, Role) VALUES (?, ?, ?, 'Resident')");
            $insert_stmt->bind_param("sss", $fullname, $email, $hashed_password);

            // Execute the query and check if it worked
            if ($insert_stmt->execute()) {
                // Clear the sticky values on success
                $fullname_val = "";
                $email_val = "";
                $message = "<p style='color: green; text-align: center;'>Registration successful! <a href='login.php'>Log in here</a>.</p>";
            } else {
                $message = "<p style='color: red; text-align: center;'>An error occurred. Please try again later.</p>";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CommunityConnect</title>
    <style>
        /* Clean UI */
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .register-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 320px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px;}
        button:hover { background-color: #218838; }
        label { font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>

<div class="register-container">
    <h2 style="text-align: center;">Resident Registration</h2>
    
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
    
    <p style="text-align: center; font-size: 14px; margin-top: 20px;">Already have an account? <br><a href="login.php">Log in here</a></p>
</div>

</body>
</html>