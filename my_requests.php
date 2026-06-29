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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - CommunityConnect</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; }
        .navbar { background-color: #007BFF; padding: 15px 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .navbar a:hover { text-decoration: underline; }
        
        .container { max-width: 900px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 14px; font-weight: bold; color: white; display: inline-block; text-align: center;}
        .badge.Pending { background-color: #ffc107; color: black; }
        .badge.Approved { background-color: #28a745; }
        .badge.Rejected { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>CommunityConnect</strong></div>
    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php" style="text-decoration: underline;">My Requests</a>
        <a href="my_impact.php">My Impact</a>
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