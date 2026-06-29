<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get counts for the dashboard statistics
$total_requests = 0;
$approved = 0;
$pending = 0;
$rejected = 0;

$stmt = $conn->prepare("SELECT Status, COUNT(*) as count FROM ParticipationRequests WHERE UserID = ? GROUP BY Status");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $total_requests += $row['count'];

    if ($row['Status'] == 'Approved') {
        $approved = $row['count'];
    }

    if ($row['Status'] == 'Pending') {
        $pending = $row['count'];
    }

    if ($row['Status'] == 'Rejected') {
        $rejected = $row['count'];
    }
}

$stmt->close();

// Calculate completed events and volunteered hours
// Only approved events that have already passed are counted
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

$completed_stmt->bind_param("i", $user_id);
$completed_stmt->execute();
$completed_result = $completed_stmt->get_result();
$completed_row = $completed_result->fetch_assoc();

$completed_events = $completed_row['completed_events'];
$total_hours = round($completed_row['total_hours'], 1);

$completed_stmt->close();

// Fetch completed event details for the Events Completed section
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

$events_stmt->bind_param("i", $user_id);
$events_stmt->execute();
$completed_events_result = $events_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Impact - CommunityConnect</title>

    <link rel="stylesheet" href="style.css?v=8">

    <!-- Add-on feature: Chart.js is used to display the application status breakdown -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div class="navbar">
    <div><strong>CommunityConnect</strong></div>

    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>
        <a href="my_impact.php" style="text-decoration: underline;">My Impact</a>
        <a href="feedback.php">Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2 style="text-align: center;">Personal Sustainability Impact</h2>

    <p style="text-align: center; color: #666;">
        Tracking your contribution to SDG 11: Sustainable Cities and Communities.
    </p>

<div class="impact-layout">

    <div class="chart-container impact-chart">
            <h3 style="text-align: center;">Application Status Breakdown</h3>
            <canvas id="impactChart"></canvas>
        </div>

        <div class="stats-grid impact-stats">
            <div class="stat-box">
                <h3>Events Completed</h3>
                <p class="number"><?php echo $completed_events; ?></p>
            </div>

            <div class="stat-box success">
                <h3>Volunteered Hours</h3>
                <p class="number"><?php echo $total_hours; ?> hrs</p>
            </div>

            <div class="stat-box">
                <h3>Pending Applications</h3>
                <p class="number" style="color: #ffc107;"><?php echo $pending; ?></p>
            </div>
        </div>

    </div>

    <br><br>

    <h2>Events Completed</h2>

    <p style="color: #666;">
        This section only shows events that you joined, were approved for and have already passed.
    </p>

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
            <?php if ($completed_events_result->num_rows > 0): ?>
                <?php while($event = $completed_events_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($event['Title']); ?></td>

                        <td><?php echo date("M j, Y", strtotime($event['EventDate'])); ?></td>

                        <td>
                            <?php echo date("g:i A", strtotime($event['EventStartTime'])); ?>
                            -
                            <?php echo date("g:i A", strtotime($event['EventEndTime'])); ?>
                        </td>

                        <td><?php echo htmlspecialchars($event['Location']); ?></td>

                        <td>
                            <span class="hours-tag">
                                <?php echo rtrim(rtrim(number_format($event['hours'], 1), '0'), '.'); ?> hrs
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
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
    // Javascript to render the Chart.js visual
    const ctx = document.getElementById('impactChart').getContext('2d');

    const impactChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Pending', 'Rejected'],
            datasets: [{
                data: [<?php echo $approved; ?>, <?php echo $pending; ?>, <?php echo $rejected; ?>],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>

</body>
</html>