<?php
// Start the session so the system can check who is logged in
session_start();

// Connect to the database
require 'db_connect.php';

// 1. SECURITY CHECK: Resident Only
// If the user is not logged in or is not a Resident, redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit();
}

// Store the current logged-in user's ID
$user_id = $_SESSION['user_id'];

// This variable stores success or error messages
$message = "";

// 2. HANDLE FEEDBACK SUBMISSION
// This runs when the resident submits feedback for a completed event
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['service_id'])) {
    $service_id = intval($_POST['service_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    // Validate rating and comment
    if ($rating < 1 || $rating > 5) {
        $message = "<div class='alert error'>Please select a valid rating from 1 to 5.</div>";
    } elseif (empty($comment)) {
        $message = "<div class='alert error'>Please enter your feedback comment.</div>";
    } else {

        // 3. CHECK IF USER IS ALLOWED TO GIVE FEEDBACK
        // User can only give feedback if:
        // - Their request was Approved
        // - The event date has already passed
        $check_sql = "SELECT pr.RequestID 
                      FROM ParticipationRequests pr
                      JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID
                      WHERE pr.UserID = ?
                      AND pr.ServiceID = ?
                      AND pr.Status = 'Approved'
                      AND cs.EventDate < CURDATE()";

        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $service_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            $message = "<div class='alert error'>You can only give feedback for approved events that have already passed.</div>";
        } else {

            // 4. CHECK IF USER ALREADY SUBMITTED FEEDBACK
            // This prevents duplicate feedback for the same event
            $duplicate_stmt = $conn->prepare("SELECT FeedbackID FROM Feedback WHERE UserID = ? AND ServiceID = ?");
            $duplicate_stmt->bind_param("ii", $user_id, $service_id);
            $duplicate_stmt->execute();
            $duplicate_result = $duplicate_stmt->get_result();

            if ($duplicate_result->num_rows > 0) {
                $message = "<div class='alert error'>You have already submitted feedback for this event.</div>";
            } else {

                // 5. INSERT FEEDBACK INTO DATABASE
                $insert_stmt = $conn->prepare("INSERT INTO Feedback (UserID, ServiceID, Rating, Comment) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("iiis", $user_id, $service_id, $rating, $comment);

                if ($insert_stmt->execute()) {
                    $message = "<div class='alert success'>Thank you! Your feedback has been submitted.</div>";
                } else {
                    $message = "<div class='alert error'>An error occurred while submitting your feedback.</div>";
                }

                $insert_stmt->close();
            }

            $duplicate_stmt->close();
        }

        $check_stmt->close();
    }
}

// 6. FETCH EVENTS ELIGIBLE FOR FEEDBACK
// This only shows events where:
// - User was approved
// - Event date has passed
// - Feedback status can also be checked
$sql = "SELECT cs.ServiceID, cs.Title, cs.EventDate, cs.Location,
               f.FeedbackID, f.Rating, f.Comment
        FROM ParticipationRequests pr
        JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID
        LEFT JOIN Feedback f ON cs.ServiceID = f.ServiceID AND f.UserID = ?
        WHERE pr.UserID = ?
        AND pr.Status = 'Approved'
        AND cs.EventDate < CURDATE()
        ORDER BY cs.EventDate DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Feedback - CommunityConnect</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- Resident Navigation Bar -->
<div class="navbar">
    <div><strong>CommunityConnect</strong></div>

    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>
        <a href="my_impact.php">My Impact</a>
        <a href="feedback.php" style="text-decoration: underline;">Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<!-- Main Content Container -->
<div class="container">
    <h2>Event Feedback</h2>

    <p style="color: #666;">
        You can only submit feedback for events that you were approved for and have already passed.
    </p>

    <!-- Display success or error message -->
    <?php echo $message; ?>

    <?php if ($result->num_rows > 0): ?>

        <?php while($row = $result->fetch_assoc()): ?>

            <div class="event-card" style="margin-bottom: 20px;">
                <h3><?php echo htmlspecialchars($row['Title']); ?></h3>

                <p><strong>Date:</strong> <?php echo date("F j, Y", strtotime($row['EventDate'])); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($row['Location']); ?></p>

                <?php if ($row['FeedbackID']): ?>

                    <!-- If feedback already exists, show submitted feedback instead of the form -->
                    <p><strong>Your Rating:</strong> 
                        <span class="stars">
                            <?php 
                                echo str_repeat('★', $row['Rating']) . str_repeat('☆', 5 - $row['Rating']); 
                            ?>
                        </span>
                    </p>

                    <p><strong>Your Feedback:</strong></p>
                    <p><i>"<?php echo nl2br(htmlspecialchars($row['Comment'])); ?>"</i></p>

                    <span class="badge Approved">Feedback Submitted</span>

                <?php else: ?>

                    <!-- Feedback form only shows if the user has not submitted feedback yet -->
                    <form action="feedback.php" method="POST" class="feedback-form">
                        <input type="hidden" name="service_id" value="<?php echo $row['ServiceID']; ?>">

                        <label>Rating:</label>
                        <select name="rating" required>
                            <option value="">Select rating</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Average</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Terrible</option>
                        </select>

                        <label>Feedback Comment:</label>
                        <textarea name="comment" rows="4" required placeholder="Write your feedback here..."></textarea>

                        <button type="submit" class="btn-feedback">Submit Feedback</button>
                    </form>

                <?php endif; ?>
            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p style="text-align: center; color: #666;">
            No completed approved events are available for feedback yet.
        </p>

    <?php endif; ?>
</div>

</body>
</html>