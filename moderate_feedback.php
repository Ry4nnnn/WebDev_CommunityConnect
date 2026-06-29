<?php
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$message = "";

// HANDLE DELETE FEEDBACK
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM Feedback WHERE FeedbackID = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "<div class='alert success'>Feedback successfully removed from the system.</div>";
    }
    $stmt->close();
}

// FETCH ALL FEEDBACK (JOIN with Users and Services)
$sql = "SELECT f.FeedbackID, f.Rating, f.Comment, f.SubmittedAt, u.FullName, cs.Title 
        FROM Feedback f
        JOIN Users u ON f.UserID = u.UserID
        JOIN CommunityServices cs ON f.ServiceID = cs.ServiceID
        ORDER BY f.SubmittedAt DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderate Feedback - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; }
        .navbar { background-color: #343a40; padding: 15px 20px; color: white; display: flex; justify-content: space-between; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .navbar a:hover { text-decoration: underline; }
        .container { max-width: 1100px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        
        .stars { color: #ffc107; font-size: 18px; letter-spacing: 2px;}
        .btn-danger { background-color: #dc3545; color: white; padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px;}
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>CommunityConnect Admin</strong></div>
    <div>
        <a href="admin.php">Manage Services</a>
        <a href="manage_requests.php">Manage Requests</a>
        <a href="moderate_feedback.php" style="text-decoration: underline;">Moderate Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2>Community Feedback Moderation</h2>
    <p style="color: #666;">Review comments left by residents on past events. Delete inappropriate submissions.</p>
    
    <?php echo $message; ?>

    <table>
        <thead>
            <tr>
                <th width="15%">Resident Name</th>
                <th width="20%">Event Title</th>
                <th width="10%">Rating</th>
                <th width="40%">Comment</th>
                <th width="10%">Date</th>
                <th width="5%">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // Generate visual stars
                    $stars = str_repeat('★', $row['Rating']) . str_repeat('☆', 5 - $row['Rating']);
                    
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($row['FullName']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($row['Title']) . "</td>";
                    echo "<td class='stars'>" . $stars . "</td>";
                    // htmlspecialchars here is CRITICAL because users typed this free-text
                    echo "<td><i>\"" . nl2br(htmlspecialchars($row['Comment'])) . "\"</i></td>";
                    echo "<td>" . date("M j, Y", strtotime($row['SubmittedAt'])) . "</td>";
                    echo "<td>
                            <a href='moderate_feedback.php?delete=" . $row['FeedbackID'] . "' class='btn-danger' onclick=\"return confirm('Delete this feedback permanently?');\">Delete</a>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='text-align: center;'>No feedback has been submitted yet.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

</body>
</html>