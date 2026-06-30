<?php
// Start the session so the system can check who is logged in
session_start();

// Connect to the database
require 'db_connect.php';

// 1. SECURITY CHECK: Admin Only
// If the user is not logged in or is not an Admin, redirect them to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// This variable stores success or error messages
$message = "";

// 2. HANDLE DELETE FEEDBACK
// This runs when the admin clicks the Delete button for a feedback record
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    // Delete the selected feedback from the Feedback table
    $stmt = $conn->prepare("DELETE FROM Feedback WHERE FeedbackID = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "<div class='alert success'>Feedback successfully removed from the system.</div>";
    }

    $stmt->close();
}

// 3. FETCH ALL FEEDBACK
// This SQL query joins Feedback, Users, and CommunityServices
// so the admin can see who submitted the feedback and which event it belongs to
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

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Moderate Feedback - Admin</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- Admin Navigation Bar -->
<div class="navbar admin">
    <div><strong>CommunityConnect Admin</strong></div>

    <div>
        <a href="admin.php">Manage Services</a>
        <a href="manage_requests.php">Manage Requests</a>
        <a href="moderate_feedback.php" style="text-decoration: underline;">Moderate Feedback</a>
        <a href="manage_users.php">Manage Users</a>
        <a 
            href="logout.php" 
            onclick="return confirm('Are you sure you want to log out?');"
            style="background: #dc3545; padding: 5px 10px; border-radius: 4px;"
        >
            Log Out
        </a>
    </div>
</div>

<!-- Main Content Container -->
<div class="container">
    <h2>Community Feedback Moderation</h2>

    <p style="color: #666;">
        Review comments left by residents on past events. Delete inappropriate submissions.
    </p>
    
    <!-- Display success or error message -->
    <?php echo $message; ?>

    <!-- Feedback Table -->
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

                    // Generate visual stars based on rating
                    // Example: 4 rating becomes ★★★★☆
                    $stars = str_repeat('★', $row['Rating']) . str_repeat('☆', 5 - $row['Rating']);
                    
                    echo "<tr>";

                    // Display resident name
                    echo "<td><strong>" . htmlspecialchars($row['FullName']) . "</strong></td>";

                    // Display event title
                    echo "<td>" . htmlspecialchars($row['Title']) . "</td>";

                    // Display star rating
                    echo "<td class='stars'>" . $stars . "</td>";

                    // htmlspecialchars here is important because users typed this free-text comment
                    // This helps prevent XSS attacks
                    echo "<td><i>\"" . nl2br(htmlspecialchars($row['Comment'])) . "\"</i></td>";

                    // Display submitted date
                    echo "<td>" . date("M j, Y", strtotime($row['SubmittedAt'])) . "</td>";

                    // Delete button for admin moderation
                    echo "<td>
                            <a href='moderate_feedback.php?delete=" . $row['FeedbackID'] . "' 
                               class='btn-danger' 
                               onclick=\"return confirm('Delete this feedback permanently?');\">
                               Delete
                            </a>
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
