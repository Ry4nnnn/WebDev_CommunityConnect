<?php
// Start the session so the system can access logged-in user information
session_start();

// Connect this page to the database using db_connect.php
require 'db_connect.php';

// 1. SECURITY CHECK: Ensure user is logged in as a Resident
// This prevents Admin users or users who are not logged in from accessing this resident page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit();
}

// Store the logged-in resident's UserID from the session
// This UserID is used when submitting join requests and feedback
$user_id = $_SESSION['user_id'];

// This variable stores success or error messages that will be displayed on the page
$message = "";

// 2. GET EVENT ID: Securely grab the ID from the URL
// The event ID is passed through the URL, for example: event_details.php?id=1
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<h3>Error: No Event Selected. <a href='user_dashboard.php'>Go back</a></h3>");
}

// Convert the event ID from the URL into an integer for safety
$service_id = intval($_GET['id']);

// 3. HANDLE FORM SUBMISSIONS (Join Request or Feedback)
// This section runs when the user submits a form on this page
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action A: User clicked "Join Event" or "Request Again"
    // This checks whether the submitted form action is for joining an event
if (isset($_POST['action']) && $_POST['action'] == 'join') {

    // First, check whether the user already has a request for this event
    // This prevents the same user from submitting duplicate requests
    $check_stmt = $conn->prepare("SELECT RequestID, Status FROM ParticipationRequests WHERE UserID = ? AND ServiceID = ?");
    
    // Bind the logged-in user's ID and selected event ID into the SQL query
    $check_stmt->bind_param("ii", $user_id, $service_id);
    
    // Execute the query to check existing participation request
    $check_stmt->execute();
    
    // Get the result from the executed query
    $check_result = $check_stmt->get_result();

    // If the user has never requested this event before, insert a new Pending request
    if ($check_result->num_rows === 0) {
        // Prepare an INSERT query to create a new participation request
        $stmt = $conn->prepare("INSERT INTO ParticipationRequests (UserID, ServiceID, Status) VALUES (?, ?, 'Pending')");
        
        // Bind the UserID and ServiceID into the insert query
        $stmt->bind_param("ii", $user_id, $service_id);

        // Execute the insert query
        // If successful, show a success message
        if ($stmt->execute()) {
            $message = "<div class='alert success'>Your request to join has been submitted and is pending admin approval!</div>";
        } else {
            $message = "<div class='alert error'>An error occurred while submitting your request.</div>";
        }

        // Close the prepared statement after use
        $stmt->close();
    } 
    
    else {
        // If the user already has a request, fetch the existing request details
        $existing_request = $check_result->fetch_assoc();

        // If admin rejected the request, allow the user to request again by changing it back to Pending
        if ($existing_request['Status'] === 'Rejected') {
            // Store the existing request ID
            $request_id = $existing_request['RequestID'];

            // Prepare an UPDATE query to change the rejected request back to Pending
            // RequestDate is also updated to the current time
            $update_stmt = $conn->prepare("UPDATE ParticipationRequests SET Status = 'Pending', RequestDate = CURRENT_TIMESTAMP WHERE RequestID = ?");
            
            // Bind the RequestID into the update query
            $update_stmt->bind_param("i", $request_id);

            // Execute the update query
            // If successful, show a success message
            if ($update_stmt->execute()) {
                $message = "<div class='alert success'>Your request has been submitted again and is pending admin approval.</div>";
            } else {
                $message = "<div class='alert error'>An error occurred while resubmitting your request.</div>";
            }

            // Close the prepared statement after use
            $update_stmt->close();
        } 
        
        // If request is still Pending, do not allow duplicate request
        elseif ($existing_request['Status'] === 'Pending') {
            $message = "<div class='alert error'>You already have a pending request for this event.</div>";
        } 
        
        // If request is Approved, user should not request again
        elseif ($existing_request['Status'] === 'Approved') {
            $message = "<div class='alert error'>You have already been approved for this event.</div>";
        }
    }

    // Close the checking statement after use
    $check_stmt->close();
}
    
    // Action B: User submitted "Feedback" (Add-on Feature)
    // This section handles feedback submission if the form action is feedback
    if (isset($_POST['action']) && $_POST['action'] == 'feedback') {
        // Convert rating input into an integer
        $rating = intval($_POST['rating']);

        // Sanitize comment for XSS protection
        // htmlspecialchars() prevents harmful HTML or JavaScript from running
        $comment = htmlspecialchars($_POST['comment']);
        
        // Prepare an INSERT query to save the user's feedback into the Feedback table
        $stmt = $conn->prepare("INSERT INTO Feedback (UserID, ServiceID, Rating, Comment) VALUES (?, ?, ?, ?)");
        
        // Bind UserID, ServiceID, rating, and comment into the query
        $stmt->bind_param("iiis", $user_id, $service_id, $rating, $comment);
        
        // Execute the feedback insert query
        // If successful, show a success message
        if ($stmt->execute()) {
            $message = "<div class='alert success'>Thank you! Your feedback has been posted.</div>";
        }

        // Close the prepared statement after use
        $stmt->close();
    }
}

// 4. FETCH EVENT DETAILS
// Prepare a query to get all details of the selected event.
// This also counts approved requests so the page can show remaining slots.
$stmt = $conn->prepare("
    SELECT 
        c.*,
        COALESCE(approved.approved_count, 0) AS approved_count,
        (c.Capacity - COALESCE(approved.approved_count, 0)) AS remaining_slots
    FROM CommunityServices c
    LEFT JOIN (
        SELECT ServiceID, COUNT(*) AS approved_count
        FROM ParticipationRequests
        WHERE Status = 'Approved'
        GROUP BY ServiceID
    ) approved ON c.ServiceID = approved.ServiceID
    WHERE c.ServiceID = ?
");

// Bind the selected ServiceID into the query
$stmt->bind_param("i", $service_id);

// Execute the query
$stmt->execute();

// Get the event result from the database
$event_result = $stmt->get_result();

// If no event is found, stop the page and show an error message
if ($event_result->num_rows === 0) {
    die("<h3>Event not found. <a href='user_dashboard.php'>Go back</a></h3>");
}

// Fetch the event data as an associative array
$event = $event_result->fetch_assoc();

// Close the prepared statement after use
$stmt->close();

// Calculate event time and volunteer hours
// Convert the stored start time into readable format, example: 9:00 AM
$start_time = date("g:i A", strtotime($event['EventStartTime']));

// Convert the stored end time into readable format, example: 1:00 PM
$end_time = date("g:i A", strtotime($event['EventEndTime']));

// Calculate the duration by subtracting start time from end time
// The result is divided by 3600 because there are 3600 seconds in 1 hour
$duration_hours = (strtotime($event['EventEndTime']) - strtotime($event['EventStartTime'])) / 3600;

// Format the duration to remove unnecessary decimal zeros
// Example: 4.0 becomes 4, while 3.5 stays 3.5
$duration_text = rtrim(rtrim(number_format($duration_hours, 1), '0'), '.');

// Calculate remaining slots.
// Remaining slots = total capacity - approved requests.
$remaining_slots = max(0, intval($event['remaining_slots']));
$total_capacity = intval($event['Capacity']);

// 5. CHECK USER'S PARTICIPATION STATUS
// This checks whether the user has already requested to join this event
$status_stmt = $conn->prepare("SELECT Status FROM ParticipationRequests WHERE UserID = ? AND ServiceID = ?");

// Bind the logged-in user's ID and selected event ID
$status_stmt->bind_param("ii", $user_id, $service_id);

// Execute the query
$status_stmt->execute();

// Get the result of the participation status query
$status_result = $status_stmt->get_result();

// Default status is null, meaning the user has not requested this event yet
$participation_status = null;

// If a request exists, get the user's current participation status
if ($status_result->num_rows > 0) {
    $row = $status_result->fetch_assoc();
    $participation_status = $row['Status']; // Will be 'Pending', 'Approved', or 'Rejected'
}

// Close the prepared statement after use
$status_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title uses the selected event title safely -->
    <title><?php echo htmlspecialchars($event['Title']); ?> - Details</title>

    <!-- Link to external CSS file for page styling -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Resident navigation bar -->
<!-- Allows residents to move between dashboard, requests, impact, feedback, and logout -->
<div class="navbar">
    <div><strong>CommunityConnect</strong></div>
    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>
        <a href="profile.php">My Profile</a>
		<a href="feedback.php">Feedback</a>
        <a 
			href="logout.php" 
			onclick="return confirm('Are you sure you want to log out?');"
			style="background: #dc3545; padding: 5px 10px; border-radius: 4px;"
		>
			Log Out
		</a>
    </div>
</div>

<!-- Main content container -->
<div class="container">
    <!-- Back button returns the user to the dashboard -->
    <a href="user_dashboard.php" class="btn btn-back">&larr; Back to Dashboard</a>
    
    <!-- Display success or error messages after form submission -->
    <?php echo $message; ?>

    <!-- Display selected event title safely -->
    <h1><?php echo htmlspecialchars($event['Title']); ?></h1>
    
    <!-- Event information section -->
    <div class="event-meta">
		<!-- Display event date in readable format -->
		<p><strong>Date:</strong> <?php echo date("F j, Y", strtotime($event['EventDate'])); ?></p>

        <!-- Display event start and end time -->
		<p><strong>Time:</strong> <?php echo $start_time; ?> - <?php echo $end_time; ?></p>

        <!-- Display event location safely -->
		<p><strong>Location:</strong> <?php echo htmlspecialchars($event['Location']); ?></p>

        <!-- Display remaining volunteer slots -->
		<p><strong>Slots:</strong> <?php echo $remaining_slots; ?>/<?php echo $total_capacity; ?> remaining slots</p>

        <!-- Display estimated volunteer hours calculated from start and end time -->
		<p><strong>Estimated Volunteer Hours:</strong> <span class="hours-tag"><?php echo $duration_text; ?> volunteer hours</span></p>
	</div>
    
    <!-- Program description section -->
    <h3>About this program</h3>

    <!-- Display event description safely and keep line breaks using nl2br() -->
    <p style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($event['Description'])); ?></p>

    <!-- Horizontal line to separate event details from participation status -->
    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

    <!-- Participation status section -->
    <h3>Participation Status</h3>

    <!-- If user has not requested to join this event yet -->
	<?php if ($participation_status === null): ?>
		<p>You have not requested to join this event yet. Space is limited!</p>

		<!-- Explain how many volunteer hours this event can add after approval -->
		<p>This event will add approximately <strong><?php echo $duration_text; ?> volunteer hours</strong> to your impact after admin approval.</p>

		<?php if ($remaining_slots > 0): ?>
			<!-- Request to join form -->
			<form action="event_details.php?id=<?php echo $service_id; ?>" method="POST">
				<!-- Hidden action tells PHP this form is for joining an event -->
				<input type="hidden" name="action" value="join">

				<!-- Submit button for join request -->
				<button type="submit" class="btn btn-join">Request to Join</button>
			</form>
		<?php else: ?>
			<div class="alert error">
				This event is already full. No remaining slots are available.
			</div>
		<?php endif; ?>

    <!-- If user's previous request was rejected -->
	<?php elseif ($participation_status === 'Rejected'): ?>
		<p>Your previous request was: 
            <!-- Display rejected status badge -->
			<span class="badge Rejected">Rejected</span>
		</p>

        <!-- User is allowed to submit another request after rejection -->
		<p>You may submit another request for this event.</p>

        <!-- This message explains the impact hours after admin approval -->
		<p>This event will add approximately <strong>4 volunteer hours</strong> to your impact after admin approval.</p>

        <!-- Request again form -->
		<form action="event_details.php?id=<?php echo $service_id; ?>" method="POST">
            <!-- Hidden action tells PHP this form is for joining again -->
			<input type="hidden" name="action" value="join">

            <!-- Submit button for request again -->
			<button type="submit" class="btn btn-join">Request Again</button>
		</form>

    <!-- If the request exists and is either Pending or Approved -->
	<?php else: ?>
		<p>Your current status for this event is: 
            <!-- Display the current status using a badge -->
			<span class="badge <?php echo $participation_status; ?>">
				<?php echo $participation_status; ?>
			</span>
		</p>
	<?php endif; ?>

</div>

</body>
</html>
