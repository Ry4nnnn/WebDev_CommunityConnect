<?php
// Database server name.
// Since this project is running on XAMPP locally, the host is localhost.
$host = "localhost";

// Default XAMPP MySQL username is root.
$username = "root";

// Default XAMPP MySQL password is empty.
$password = "";

// Name of the database used for this CommunityConnect system.
$database = "CommunityConnect";

// Create the database connection using MySQLi.
// This connects PHP to the MySQL database.
$conn = new mysqli($host, $username, $password, $database);

// Check whether the database connection failed.
// If there is an error, the system will stop and display the error message.
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// This line can be used for testing the database connection.
// It should stay commented or be removed before final submission.
// echo "Connected successfully!";
?>
