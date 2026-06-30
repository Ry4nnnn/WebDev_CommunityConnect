<?php
// Start the PHP session.
// Sessions are used to remember who is logged in while moving between pages.
session_start();

// Include the database connection file.
// This allows this page to communicate with the MySQL database.
require 'db_connect.php';

// 1. SECURITY CHECK: Resident Only
// This page is only for logged-in Residents.
// If the user is not logged in or the user role is not Resident,
// the system redirects them back to the login page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit();
}

// Store the logged-in user's UserID from the session.
// This ID is used to check which events the user joined
// and to save feedback under the correct user.
$user_id = $_SESSION['user_id'];

// This variable is used to store success or error messages.
// The message will be displayed later in the HTML section.
$message = "";

// 2. HANDLE FEEDBACK SUBMISSION
// This section runs only when the feedback form is submitted.
// It checks whether the request method is POST and whether service_id exists.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['service_id'])) {
    // Convert the selected event/service ID into an integer for safety.
    $service_id = intval($_POST['service_id']);

    // Convert the rating into an integer.
    // The rating should be between 1 and 5.
    $rating = intval($_POST['rating']);

    // Remove unnecessary spaces from the feedback comment.
    $comment = trim($_POST['comment']);

    // Validate rating and comment.
    // This prevents invalid rating values from being submitted.
    if ($rating < 1 || $rating > 5) {
        $message = "<div class='alert error'>Please select a valid rating from 1 to 5.</div>";

    // This checks that the comment field is not empty.
    } elseif (empty($comment)) {
        $message = "<div class='alert error'>Please enter your feedback comment.</div>";

    } else {

        // 3. CHECK IF USER IS ALLOWED TO GIVE FEEDBACK
        // User can only give feedback if:
        // - Their request was Approved
        // - The event date has already passed
        //
        // This query checks the ParticipationRequests table and CommunityServices table.
        // It makes sure the logged-in user was approved for this selected event.
        $check_sql = "SELECT pr.RequestID 
                      FROM ParticipationRequests pr
                      JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID
                      WHERE pr.UserID = ?
                      AND pr.ServiceID = ?
                      AND pr.Status = 'Approved'
                      AND cs.EventDate < CURDATE()";

        // Prepare the SQL statement to prevent SQL injection.
        $check_stmt = $conn->prepare($check_sql);

        // Bind the logged-in user's ID and selected event ID into the query.
        $check_stmt->bind_param("ii", $user_id, $service_id);

        // Execute the query.
        $check_stmt->execute();

        // Get the result of the query.
        $check_result = $check_stmt->get_result();

        // If no matching record is found, the user is not allowed to give feedback.
        // This means the user is either not approved or the event has not passed yet.
        if ($check_result->num_rows === 0) {
            $message = "<div class='alert error'>You can only give feedback for approved events that have already passed.</div>";
        } else {

            // 4. CHECK IF USER ALREADY SUBMITTED FEEDBACK
            // This prevents the same user from giving feedback multiple times
            // for the same event.
            $duplicate_stmt = $conn->prepare("SELECT FeedbackID FROM Feedback WHERE UserID = ? AND ServiceID = ?");

            // Bind UserID and ServiceID into the duplicate-check query.
            $duplicate_stmt->bind_param("ii", $user_id, $service_id);

            // Execute the duplicate-check query.
            $duplicate_stmt->execute();

            // Get the result from the duplicate-check query.
            $duplicate_result = $duplicate_stmt->get_result();

            // If a feedback record already exists, show an error message.
            if ($duplicate_result->num_rows > 0) {
                $message = "<div class='alert error'>You have already submitted feedback for this event.</div>";
            } else {

                // 5. INSERT FEEDBACK INTO DATABASE
                // If the user is eligible and has not submitted feedback before,
                // insert the feedback into the Feedback table.
                $insert_stmt = $conn->prepare("INSERT INTO Feedback (UserID, ServiceID, Rating, Comment) VALUES (?, ?, ?, ?)");

                // Bind the values into the insert query.
                // i = integer, s = string
                $insert_stmt->bind_param("iiis", $user_id, $service_id, $rating, $comment);

                // Execute the insert query.
                // If successful, show a success message.
                // If not, show an error message.
                if ($insert_stmt->execute()) {
                    $message = "<div class='alert success'>Thank you! Your feedback has been submitted.</div>";
                } else {
                    $message = "<div class='alert error'>An error occurred while submitting your feedback.</div>";
                }

                // Close the insert statement after use.
                $insert_stmt->close();
            }

            // Close the duplicate-check statement after use.
            $duplicate_stmt->close();
        }

        // Close the eligibility-check statement after use.
        $check_stmt->close();
    }
}

// 6. FETCH EVENTS ELIGIBLE FOR FEEDBACK
// This section fetches events that the user can give feedback for.
// It only shows events where:
// - The user was approved
// - The event date has passed
// - Feedback status can also be checked
//
// LEFT JOIN Feedback is used so the page can check whether feedback
// has already been submitted for each event.
$sql = "SELECT cs.ServiceID, cs.Title, cs.EventDate, cs.Location,
               f.FeedbackID, f.Rating, f.Comment
        FROM ParticipationRequests pr
        JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID
        LEFT JOIN Feedback f ON cs.ServiceID = f.ServiceID AND f.UserID = ?
        WHERE pr.UserID = ?
        AND pr.Status = 'Approved'
        AND cs.EventDate < CURDATE()
        ORDER BY cs.EventDate DESC";

// Prepare the SQL query.
$stmt = $conn->prepare($sql);

// Bind the logged-in user's ID twice.
// First one is for checking feedback records.
// Second one is for checking participation requests.
$stmt->bind_param("ii", $user_id, $user_id);

// Execute the query.
$stmt->execute();

// Store the eligible events result.
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
<!-- This navigation bar allows the resident to move between main pages -->
<div class="navbar">
    <div><strong>CommunityConnect</strong></div>

    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>
        <a href="my_impact.php">My Impact</a>

        <!-- Feedback link is underlined because this is the current page -->
        <a href="feedback.php" style="text-decoration: underline;">Feedback</a>

        <!-- Logout button ends the user's session -->
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<!-- Main Content Container -->
<div class="container">
    <h2>Event Feedback</h2>

    <!-- Short instruction explaining when feedback is allowed -->
    <p style="color: #666;">
        You can only submit feedback for events that you were approved for and have already passed.
    </p>

    <!-- Display success or error message after submitting feedback -->
    <?php echo $message; ?>

    <!-- Check if there are eligible completed approved events -->
    <?php if ($result->num_rows > 0): ?>

        <!-- Loop through each eligible event -->
        <?php while($row = $result->fetch_assoc()): ?>

            <!-- Card for each completed approved event -->
            <div class="event-card" style="margin-bottom: 20px;">
                <!-- Display event title safely to prevent XSS -->
                <h3><?php echo htmlspecialchars($row['Title']); ?></h3>

                <!-- Display event date in readable format -->
                <p><strong>Date:</strong> <?php echo date("F j, Y", strtotime($row['EventDate'])); ?></p>

                <!-- Display event location safely -->
                <p><strong>Location:</strong> <?php echo htmlspecialchars($row['Location']); ?></p>

                <!-- If FeedbackID exists, it means the user already submitted feedback -->
                <?php if ($row['FeedbackID']): ?>

                    <!-- If feedback already exists, show submitted feedback instead of the form -->
                    <p><strong>Your Rating:</strong> 
                        <span class="stars">
                            <?php 
                                // Display filled stars based on rating.
                                // Example: rating 4 becomes ★★★★☆
                                echo str_repeat('★', $row['Rating']) . str_repeat('☆', 5 - $row['Rating']); 
                            ?>
                        </span>
                    </p>

                    <!-- Display the user's submitted feedback comment -->
                    <p><strong>Your Feedback:</strong></p>

                    <!-- nl2br() keeps line breaks, htmlspecialchars() prevents XSS -->
                    <p><i>"<?php echo nl2br(htmlspecialchars($row['Comment'])); ?>"</i></p>

                    <!-- Badge showing feedback has already been submitted -->
                    <span class="badge Approved">Feedback Submitted</span>

                <?php else: ?>

                    <!-- Feedback form only shows if the user has not submitted feedback yet -->
                    <form action="feedback.php" method="POST" class="feedback-form">
                        <!-- Hidden input stores the event/service ID for this feedback -->
                        <input type="hidden" name="service_id" value="<?php echo $row['ServiceID']; ?>">

                        <!-- Rating dropdown from 1 to 5 -->
                        <label>Rating:</label>
                        <select name="rating" required>
                            <option value="">Select rating</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Average</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Terrible</option>
                        </select>

                        <!-- Textarea for user feedback comment -->
                        <label>Feedback Comment:</label>
                        <textarea name="comment" rows="4" required placeholder="Write your feedback here..."></textarea>

                        <!-- Submit button sends the feedback form -->
                        <button type="submit" class="btn-feedback">Submit Feedback</button>
                    </form>

                <?php endif; ?>
            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <!-- Message shown when the user has no completed approved events -->
        <p style="text-align: center; color: #666;">
            No completed approved events are available for feedback yet.
        </p>

    <?php endif; ?>
</div>

</body>
</html>