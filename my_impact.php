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
    if ($row['Status'] == 'Approved') $approved = $row['count'];
    if ($row['Status'] == 'Pending') $pending = $row['count'];
    if ($row['Status'] == 'Rejected') $rejected = $row['count'];
}
$stmt->close();

// Calculate dummy hours (Assume 4 hours per approved event)
$total_hours = $approved * 4;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Impact - CommunityConnect</title>

    <link rel="stylesheet" href="style.css">
    <!-- add-on feature -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="navbar">
    <div><strong>CommunityConnect</strong></div>
    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>
        <a href="my_impact.php" style="text-decoration: underline;">My Impact</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2 style="text-align: center;">Personal Sustainability Impact</h2>
    <p style="text-align: center; color: #666;">Tracking your contribution to SDG 11: Sustainable Cities and Communities.</p>

    <div class="stats-grid">
        <div class="stat-box">
            <h3>Events Joined</h3>
            <p class="number"><?php echo $approved; ?></p>
        </div>

        <div class="stat-box success">
            <h3>Estimated Volunteer Hours</h3>
            <p class="number"><?php echo $total_hours; ?> hrs</p>
        </div>

        <div class="stat-box">
            <h3>Pending Applications</h3>
            <p class="number" style="color: #ffc107;"><?php echo $pending; ?></p>
        </div>
    </div>

    <div class="chart-container">
        <h3 style="text-align: center;">Application Status Breakdown</h3>
        <canvas id="impactChart"></canvas>
    </div>
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