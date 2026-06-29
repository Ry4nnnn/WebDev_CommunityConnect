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
    
    // Action A: User clicked "Join Event"
    if (isset($_POST['action']) && $_POST['action'] == 'join') {
        $stmt = $conn->prepare("INSERT INTO ParticipationRequests (UserID, ServiceID, Status) VALUES (?, ?, 'Pending')");
        $stmt->bind_param("ii", $user_id, $service_id);
        if ($stmt->execute()) {
            $message = "<div class='alert success'>Your request to join has been submitted and is pending admin approval!</div>";
        } else {
            $message = "<div class='alert error'>An error occurred while submitting your request.</div>";
        }
        $stmt->close();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['Title']); ?> - Details</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; }
        .navbar { background-color: #007BFF; padding: 15px 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .navbar a:hover { text-decoration: underline; }
        
        .container { max-width: 800px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .event-meta { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .event-meta p { margin: 5px 0; font-size: 16px; }
        
        .btn { padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; display: inline-block; text-decoration: none; }
        .btn-join { background-color: #28a745; color: white; }
        .btn-join:hover { background-color: #218838; }
        .btn-back { background-color: #6c757d; color: white; margin-bottom: 20px; }
        
        .badge { padding: 8px 15px; border-radius: 20px; font-weight: bold; color: white; display: inline-block; }
        .badge.Pending { background-color: #ffc107; color: black; }
        .badge.Approved { background-color: #28a745; }
        .badge.Rejected { background-color: #dc3545; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Feedback Section Styling */
        .feedback-section { margin-top: 40px; border-top: 2px solid #eee; padding-top: 20px; }
        .feedback-form select, .feedback-form textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-feedback { background-color: #17a2b8; color: white; }
    </style>
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
    </div>
    
    <h3>About this program</h3>
    <p style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($event['Description'])); ?></p>

    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

    <h3>Participation Status</h3>
    <?php if ($participation_status === null): ?>
        <p>You have not requested to join this event yet. Space is limited!</p>
        <form action="event_details.php?id=<?php echo $service_id; ?>" method="POST">
            <input type="hidden" name="action" value="join">
            <button type="submit" class="btn btn-join">Request to Join</button>
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