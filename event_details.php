<?php
session_start();
require 'db_connect.php';

// 1. SECURITY CHECK: Ensure user is logged in as a Resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// 2. GET EVENT ID: Securely grab the ID from the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<h3>Error: No Event Selected. <a href='user_dashboard.php'>Go back</a></h3>");
}
$service_id = intval($_GET['id']);

// 3. HANDLE FORM SUBMISSIONS (Join Request or Feedback)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action A: User clicked "Join Event" or "Request Again"
if (isset($_POST['action']) && $_POST['action'] == 'join') {

    // First, check whether the user already has a request for this event
    $check_stmt = $conn->prepare("SELECT RequestID, Status FROM ParticipationRequests WHERE UserID = ? AND ServiceID = ?");
    $check_stmt->bind_param("ii", $user_id, $service_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    // If the user has never requested this event before, insert a new Pending request
    if ($check_result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO ParticipationRequests (UserID, ServiceID, Status) VALUES (?, ?, 'Pending')");
        $stmt->bind_param("ii", $user_id, $service_id);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Your request to join has been submitted and is pending admin approval!</div>";
        } else {
            $message = "<div class='alert error'>An error occurred while submitting your request.</div>";
        }

        $stmt->close();
    } 
    
    else {
        $existing_request = $check_result->fetch_assoc();

        // If admin rejected the request, allow the user to request again by changing it back to Pending
        if ($existing_request['Status'] === 'Rejected') {
            $request_id = $existing_request['RequestID'];

            $update_stmt = $conn->prepare("UPDATE ParticipationRequests SET Status = 'Pending', RequestDate = CURRENT_TIMESTAMP WHERE RequestID = ?");
            $update_stmt->bind_param("i", $request_id);

            if ($update_stmt->execute()) {
                $message = "<div class='alert success'>Your request has been submitted again and is pending admin approval.</div>";
            } else {
                $message = "<div class='alert error'>An error occurred while resubmitting your request.</div>";
            }

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

    $check_stmt->close();
}
    
    // Action B: User submitted "Feedback" (Add-on Feature)
    if (isset($_POST['action']) && $_POST['action'] == 'feedback') {
        $rating = intval($_POST['rating']);
        $comment = htmlspecialchars($_POST['comment']); // Sanitize comment for XSS protection
        
        $stmt = $conn->prepare("INSERT INTO Feedback (UserID, ServiceID, Rating, Comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $service_id, $rating, $comment);
        if ($stmt->execute()) {
            $message = "<div class='alert success'>Thank you! Your feedback has been posted.</div>";
        }
        $stmt->close();
    }
}

// 4. FETCH EVENT DETAILS
$stmt = $conn->prepare("SELECT * FROM CommunityServices WHERE ServiceID = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$event_result = $stmt->get_result();

if ($event_result->num_rows === 0) {
    die("<h3>Event not found. <a href='user_dashboard.php'>Go back</a></h3>");
}
$event = $event_result->fetch_assoc();
$stmt->close();

// 5. CHECK USER'S PARTICIPATION STATUS
$status_stmt = $conn->prepare("SELECT Status FROM ParticipationRequests WHERE UserID = ? AND ServiceID = ?");
$status_stmt->bind_param("ii", $user_id, $service_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

$participation_status = null;

if ($status_result->num_rows > 0) {
    $row = $status_result->fetch_assoc();
    $participation_status = $row['Status']; // Will be 'Pending', 'Approved', or 'Rejected'
}

$status_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['Title']); ?> - Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="navbar">
    <div><strong>CommunityConnect</strong></div>
    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>
        <a href="my_impact.php">My Impact</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <a href="user_dashboard.php" class="btn btn-back">&larr; Back to Dashboard</a>
    
    <?php echo $message; ?>

    <h1><?php echo htmlspecialchars($event['Title']); ?></h1>
    
    <div class="event-meta">
        <p><strong>Date:</strong> <?php echo date("F j, Y", strtotime($event['EventDate'])); ?></p>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($event['Location']); ?></p>
        <p><strong>Capacity:</strong> <?php echo htmlspecialchars($event['Capacity']); ?> volunteers needed</p>
        <p><strong>Estimated Volunteer Hours:</strong> <span class="hours-tag">4 volunteer hours</span></p>
    </div>
    
    <h3>About this program</h3>
    <p style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($event['Description'])); ?></p>

    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

    <h3>Participation Status</h3>

    <?php if ($participation_status === null): ?>
		<p>You have not requested to join this event yet. Space is limited!</p>
		<p>This event will add approximately <strong>4 volunteer hours</strong> to your impact after admin approval.</p>

		<form action="event_details.php?id=<?php echo $service_id; ?>" method="POST">
			<input type="hidden" name="action" value="join">
			<button type="submit" class="btn btn-join">Request to Join</button>
		</form>

	<?php elseif ($participation_status === 'Rejected'): ?>
		<p>Your previous request was: 
			<span class="badge Rejected">Rejected</span>
		</p>

		<p>You may submit another request for this event.</p>
		<p>This event will add approximately <strong>4 volunteer hours</strong> to your impact after admin approval.</p>

		<form action="event_details.php?id=<?php echo $service_id; ?>" method="POST">
			<input type="hidden" name="action" value="join">
			<button type="submit" class="btn btn-join">Request Again</button>
		</form>

	<?php else: ?>
		<p>Your current status for this event is: 
			<span class="badge <?php echo $participation_status; ?>">
				<?php echo $participation_status; ?>
			</span>
		</p>
	<?php endif; ?>

    <div class="feedback-section">
        <h3>Submit Event Feedback</h3>
        <p style="font-size: 14px; color: #666;">Did you attend this event? Leave a rating and comment to help Harmony Community Association improve future programs!</p>
        
        <form class="feedback-form" action="event_details.php?id=<?php echo $service_id; ?>" method="POST">
            <input type="hidden" name="action" value="feedback">
            
            <label><strong>Rating (1-5):</strong></label>
            <select name="rating" required>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Good</option>
                <option value="3">3 - Average</option>
                <option value="2">2 - Poor</option>
                <option value="1">1 - Terrible</option>
            </select>
            
            <label><strong>Your Comment:</strong></label>
            <textarea name="comment" rows="4" required placeholder="Tell us about your experience..."></textarea>
            
            <button type="submit" class="btn btn-feedback">Submit Feedback</button>
        </form>
    </div>

</div>

</body>
</html>