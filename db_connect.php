<?php


$host = "localhost";
$username = "root";       // Default XAMPP username
$password = "";           // Default XAMPP password is empty
$database = "CommunityConnect"; // The name of the database we just created

// Create the connection using MySQLi
$conn = new mysqli($host, $username, $password, $database);

// Check if the connection failed
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// You can uncomment the line below just to test it, but delete it later!
// echo "Connected successfully!";
?>