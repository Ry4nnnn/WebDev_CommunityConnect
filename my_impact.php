<?php
// Start the session so the system can check who is currently logged in.
// This is required because the My Impact page is only for logged-in Residents.
session_start();

// Connect this page to the MySQL database.
// db_connect.php contains the database connection settings.
require 'db_connect.php';

// Security check: Resident only.
// If the user is not logged in or their role is not Resident,
// they will be redirected back to the login page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit();
}

// Store the logged-in resident's UserID.
// This is used to get only this user's requests, completed events, and volunteer hours.
$user_id = $_SESSION['user_id'];

// Get counts for the dashboard statistics.
// These variables will store the number of requests by status.
$total_requests = 0;
$approved = 0;
$pending = 0;
$rejected = 0;

// This query counts how many requests the logged-in user has for each status.
// Example: Approved = 2, Pending = 1, Rejected = 1.
$stmt = $conn->prepare("SELECT Status, COUNT(*) as count FROM ParticipationRequests WHERE UserID = ? GROUP BY Status");

// Bind the logged-in user's ID into the query.
// "i" means integer.
$stmt->bind_param("i", $user_id);

// Execute the query.
$stmt->execute();

// Get the result from the database.
$result = $stmt->get_result();

// Loop through each status count returned from the database.
while ($row = $result->fetch_assoc()) {
    // Add all request counts together to get total requests.
    $total_requests += $row['count'];

    // Store the number of approved requests.
    if ($row['Status'] == 'Approved') {
        $approved = $row['count'];
    }

    // Store the number of pending requests.
    if ($row['Status'] == 'Pending') {
        $pending = $row['count'];
    }

    // Store the number of rejected requests.
    if ($row['Status'] == 'Rejected') {
        $rejected = $row['count'];
    }
}

// Close the prepared statement after use.
$stmt->close();

// Calculate completed events and volunteered hours.
// Only approved events that have already passed are counted.
// This means future approved events will not count as completed yet.
$completed_stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS completed_events,
        COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(cs.EventEndTime, cs.EventStartTime)) / 3600), 0) AS total_hours
    FROM ParticipationRequests pr
    JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID
    WHERE pr.UserID = ?
    AND pr.Status = 'Approved'
    AND TIMESTAMP(cs.EventDate, cs.EventEndTime) < NOW()
");

// Bind the logged-in user's ID into the query.
$completed_stmt->bind_param("i", $user_id);

// Execute the completed events query.
$completed_stmt->execute();

// Get the result from the database.
$completed_result = $completed_stmt->get_result();

// Fetch the completed event count and total volunteered hours.
$completed_row = $completed_result->fetch_assoc();

// Store the number of completed events.
$completed_events = $completed_row['completed_events'];

// Store the total volunteered hours.
// round() is used to make the hours cleaner, for example 3.5 hours.
$total_hours = round($completed_row['total_hours'], 1);

// Close the prepared statement after use.
$completed_stmt->close();

// Fetch completed event details for the Events Completed section.
// This query gets the event title, date, time, location, and calculated hours.
// Only approved events that have already ended will appear in this section.
$events_stmt = $conn->prepare("
    SELECT 
        cs.Title,
        cs.EventDate,
        cs.EventStartTime,
        cs.EventEndTime,
        cs.Location,
        ROUND(TIME_TO_SEC(TIMEDIFF(cs.EventEndTime, cs.EventStartTime)) / 3600, 1) AS hours
    FROM ParticipationRequests pr
    JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID
    WHERE pr.UserID = ?
    AND pr.Status = 'Approved'
    AND TIMESTAMP(cs.EventDate, cs.EventEndTime) < NOW()
    ORDER BY cs.EventDate DESC
");

// Bind the logged-in user's ID into the query.
$events_stmt->bind_param("i", $user_id);

// Execute the completed event details query.
$events_stmt->execute();

// Store the completed events result for display in the table.
$completed_events_result = $events_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title shown on the browser tab -->
    <title>My Impact - CommunityConnect</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css?v=8">

    <!-- Add-on feature: Chart.js is used to display the application status breakdown -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<!-- Resident Navigation Bar -->
<!-- Allows residents to navigate between dashboard, requests, impact, feedback, and logout -->
<div class="navbar">
    <div><strong>CommunityConnect</strong></div>

    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>

        <!-- Underlined because this is the current active page -->
        <a href="my_impact.php" style="text-decoration: underline;">My Impact</a>

        <a href="feedback.php">Feedback</a>

        <!-- Logout button ends the resident session -->
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<!-- Main Content Container -->
<div class="container">
    <h2 style="text-align: center;">Personal Sustainability Impact</h2>

    <!-- Short description explaining the purpose of this page -->
    <p style="text-align: center; color: #666;">
        Tracking your contribution to SDG 11: Sustainable Cities and Communities.
    </p>

<!-- Layout section for chart and statistics -->
<!-- The chart is displayed on the left, while the statistic boxes are displayed on the right -->
<div class="impact-layout">

    <!-- Chart container for application status breakdown -->
    <div class="chart-container impact-chart">
            <h3 style="text-align: center;">Application Status Breakdown</h3>

            <!-- Canvas element where Chart.js will draw the doughnut chart -->
            <canvas id="impactChart"></canvas>
        </div>

        <!-- Statistics section showing completed events, volunteered hours, and pending applications -->
        <div class="stats-grid impact-stats">

            <!-- Shows the number of approved events that have already ended -->
            <div class="stat-box">
                <h3>Events Completed</h3>
                <p class="number"><?php echo $completed_events; ?></p>
            </div>

            <!-- Shows total volunteered hours from completed approved events -->
            <div class="stat-box success">
                <h3>Volunteered Hours</h3>
                <p class="number"><?php echo $total_hours; ?> hrs</p>
            </div>

            <!-- Shows the number of requests still waiting for admin approval -->
            <div class="stat-box">
                <h3>Pending Applications</h3>
                <p class="number" style="color: #ffc107;"><?php echo $pending; ?></p>
            </div>
        </div>

    </div>

    <br><br>

    <!-- Events Completed table section -->
    <h2>Events Completed</h2>

    <!-- Explanation for what appears in this section -->
    <p style="color: #666;">
        This section only shows events that you joined, were approved for and have already passed.
    </p>

    <!-- Table displaying completed approved events -->
    <table>
        <thead>
            <tr>
                <th>Event Title</th>
                <th>Date</th>
                <th>Time</th>
                <th>Location</th>
                <th>Volunteered Hours</th>
            </tr>
        </thead>

        <tbody>
            <!-- Check whether the user has any completed approved events -->
            <?php if ($completed_events_result->num_rows > 0): ?>

                <!-- Loop through each completed event and display it as a table row -->
                <?php while($event = $completed_events_result->fetch_assoc()): ?>
                    <tr>
                        <!-- Display event title safely to prevent XSS -->
                        <td><?php echo htmlspecialchars($event['Title']); ?></td>

                        <!-- Display event date in a readable format -->
                        <td><?php echo date("M j, Y", strtotime($event['EventDate'])); ?></td>

                        <!-- Display event start and end time in 12-hour format -->
                        <td>
                            <?php echo date("g:i A", strtotime($event['EventStartTime'])); ?>
                            -
                            <?php echo date("g:i A", strtotime($event['EventEndTime'])); ?>
                        </td>

                        <!-- Display event location safely to prevent XSS -->
                        <td><?php echo htmlspecialchars($event['Location']); ?></td>

                        <!-- Display volunteered hours for this specific completed event -->
                        <td>
                            <span class="hours-tag">
                                <?php echo rtrim(rtrim(number_format($event['hours'], 1), '0'), '.'); ?> hrs
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>

                <!-- Message shown if the resident has not completed any approved events yet -->
                <tr>
                    <td colspan="5" style="text-align: center;">
                        You have not completed any approved events yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // Javascript to render the Chart.js visual.
    // This finds the canvas element with the ID impactChart.
    const ctx = document.getElementById('impactChart').getContext('2d');

    // Create a new Chart.js doughnut chart.
    // The chart displays the number of Approved, Pending, and Rejected requests.
    const impactChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            // Labels shown in the chart legend.
            labels: ['Approved', 'Pending', 'Rejected'],

            datasets: [{
                // The data comes from PHP variables calculated earlier.
                data: [<?php echo $approved; ?>, <?php echo $pending; ?>, <?php echo $rejected; ?>],

                // Colors for Approved, Pending, and Rejected sections.
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],

                // Makes the chart segment slightly pop out when hovered.
                hoverOffset: 4
            }]
        },
        options: {
            // Makes the chart resize based on screen size.
            responsive: true,

            plugins: {
                // Places the chart legend at the bottom.
                legend: { position: 'bottom' }
            }
        }
    });
</script>

</body>
</html>