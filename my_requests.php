<?php
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// SQL JOIN to get the event details along with the user's request status
$sql = "SELECT c.Title, c.EventDate, c.Location, p.Status, p.RequestDate 
        FROM ParticipationRequests p 
        JOIN CommunityServices c ON p.ServiceID = c.ServiceID 
        WHERE p.UserID = ? 
        ORDER BY p.RequestDate DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - CommunityConnect</title>
    <link rel="stylesheet" href="style.css">
    
</head>
<body>

<div class="navbar">
    <div><strong>CommunityConnect</strong></div>
    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php" style="text-decoration: underline;">My Requests</a>
        <a href="my_impact.php">My Impact</a>
        <a href="feedback.php">Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2>My Participation Requests</h2>
    <p>Track the status of the community services you have applied to join.</p>

    <table>
        <thead>
            <tr>
                <th>Event Title</th>
                <th>Event Date</th>
                <th>Location</th>
                <th>Applied On</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['Title']) . "</td>";
                    echo "<td>" . date("M j, Y", strtotime($row['EventDate'])) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Location']) . "</td>";
                    echo "<td>" . date("M j, Y", strtotime($row['RequestDate'])) . "</td>";
                    echo "<td><span class='badge " . $row['Status'] . "'>" . $row['Status'] . "</span></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align: center;'>You have not requested to join any events yet.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

</body>
</html>