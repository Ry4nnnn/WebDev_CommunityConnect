<?php
// Start the session so the system can check who is currently logged in.
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
// This is used to get only this user's profile, requests, completed events, and volunteer hours.
$user_id = $_SESSION['user_id'];

// This variable stores success or error messages.
$message = "";

// Folder where profile pictures will be saved.
// __DIR__ gives the real folder path of this PHP file.
$upload_dir = __DIR__ . "/uploads/profile_pictures/";

// This is the path saved into the database and used by <img src="">
$upload_url = "uploads/profile_pictures/";

// Create the upload folder automatically if it does not exist.
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ===============================
// FETCH USER PROFILE INFORMATION
// ===============================
// This gets the logged-in user's full name, email, and profile picture.
$profile_stmt = $conn->prepare("SELECT FullName, Email, ProfilePicture FROM Users WHERE UserID = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$user = $profile_result->fetch_assoc();
$profile_stmt->close();

// ===============================
// HANDLE EDIT PROFILE FORM
// ===============================
// This runs when the user submits the Edit Profile form.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {

    // Get the new full name entered by the user.
    $new_fullname = trim($_POST['full_name']);

    // Start with the current profile picture.
    // If the user uploads a new one, this value will be replaced.
    $profile_picture_path = $user['ProfilePicture'];

    // Validate full name.
    if (empty($new_fullname)) {
        $message = "<div class='alert error'>Full name cannot be empty.</div>";

    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $new_fullname)) {
        $message = "<div class='alert error'>Full name can only contain letters and spaces.</div>";

    } else {

        // Check whether the user uploaded a new profile picture.
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {

            // If there is an upload error, show an error message.
            if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                $message = "<div class='alert error'>Error uploading profile picture.</div>";

            } else {
                // Store uploaded file information.
                $file_tmp = $_FILES['profile_picture']['tmp_name'];
                $file_size = $_FILES['profile_picture']['size'];

                // Check whether the uploaded file is really an image.
                $image_info = getimagesize($file_tmp);

                if ($image_info === false) {
                    $message = "<div class='alert error'>Please upload a valid image file.</div>";

                } else {
                    // Get the image type.
                    $mime_type = $image_info['mime'];

                    // Allowed image types.
                    $allowed_types = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];

                    // Check image type.
                    if (!array_key_exists($mime_type, $allowed_types)) {
                        $message = "<div class='alert error'>Only JPG, PNG, and WEBP images are allowed.</div>";

                    // Limit image size to 5MB.
                    } elseif ($file_size > 5 * 1024 * 1024) {
                        $message = "<div class='alert error'>Profile picture must be less than 5MB.</div>";
                    } else {
                        // Create a unique filename for the image.
                        $extension = $allowed_types[$mime_type];
                        $new_filename = "user_" . $user_id . "_" . time() . "." . $extension;
                        $target_file = $upload_dir . $new_filename;
                        $database_file_path = $upload_url . $new_filename;

                        // Move the uploaded image into the uploads/profile_pictures folder.
                        if (move_uploaded_file($file_tmp, $target_file)) {

                            // Delete old profile picture if it exists.
                            if (!empty($user['ProfilePicture']) && file_exists($user['ProfilePicture'])) {
                                unlink($user['ProfilePicture']);
                            }

                            // Save the relative image path into database.
                            $profile_picture_path = $database_file_path;

                        } else {
                            $message = "<div class='alert error'>Failed to save uploaded image.</div>";
                        }
                    }
                }
            }
        }

        // If there is no error message, update the user's profile.
        if (empty($message)) {
            $update_stmt = $conn->prepare("UPDATE Users SET FullName = ?, ProfilePicture = ? WHERE UserID = ?");
            $update_stmt->bind_param("ssi", $new_fullname, $profile_picture_path, $user_id);

            if ($update_stmt->execute()) {
                // Update session name so navbar also shows the new name if used elsewhere.
                $_SESSION['full_name'] = $new_fullname;

                $message = "<div class='alert success'>Profile updated successfully.</div>";

                // Refresh user data so the new name and picture show immediately.
                $user['FullName'] = $new_fullname;
                $user['ProfilePicture'] = $profile_picture_path;

            } else {
                $message = "<div class='alert error'>Database error while updating profile.</div>";
            }

            $update_stmt->close();
        }
    }
}

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
$total_hours = round($completed_row['total_hours'], 1);

// Close the prepared statement after use.
$completed_stmt->close();

// Fetch completed event details for the Events Completed section.
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
    <title>My Profile & Impact - CommunityConnect</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css?v=9">

    <!-- Add-on feature: Chart.js is used to display the application status breakdown -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<!-- Resident Navigation Bar -->
<div class="navbar">
    <div><strong>CommunityConnect</strong></div>

    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>

        <!-- Underlined because this is the current active page -->
        <a href="profile.php" style="text-decoration: underline;">My Profile</a>

        <a href="feedback.php">Feedback</a>

        <!-- Logout button ends the resident session -->
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
    <h2 style="text-align: center;">My Profile & Impact</h2>

    <p style="text-align: center; color: #666;">
        View your profile and track your contribution to SDG 11: Sustainable Cities and Communities.
    </p>

    <!-- Display success or error message -->
    <?php echo $message; ?>

    <!-- Profile Section -->
    <div class="profile-card">

        <!-- Profile picture area -->
        <div class="profile-picture-section">
            <?php if (!empty($user['ProfilePicture'])): ?>
                <img 
                    src="<?php echo htmlspecialchars($user['ProfilePicture']); ?>" 
                    alt="Profile Picture" 
                    class="profile-picture"
                >
            <?php else: ?>
                <div class="profile-placeholder">👤</div>
            <?php endif; ?>
        </div>

        <!-- Profile information area -->
        <div class="profile-info">
            <h3>Profile Information</h3>

            <p>
                <strong>Full Name:</strong><br>
                <?php echo htmlspecialchars($user['FullName']); ?>
            </p>

            <p>
                <strong>Email Address:</strong><br>
                <?php echo htmlspecialchars($user['Email']); ?>
            </p>

            <!-- Edit Profile button -->
            <?php if (!isset($_GET['edit'])): ?>
                <a href="profile.php?edit=1" class="btn-primary">Edit Profile</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <!-- This form only appears after the user clicks Edit Profile -->
    <?php if (isset($_GET['edit'])): ?>
        <div class="edit-profile-box">
            <h3>Edit Profile</h3>

            <form action="profile.php?edit=1" method="POST" enctype="multipart/form-data">
                <label>Full Name:</label>
                <input 
                    type="text" 
                    name="full_name" 
                    value="<?php echo htmlspecialchars($user['FullName']); ?>" 
                    required
                >

                <label>Upload New Profile Picture:</label>
                <input 
                    type="file" 
                    name="profile_picture" 
                    accept="image/*"
                >

                <p style="font-size: 13px; color: #666;">
                    Leave the image field empty if you only want to change your name.
                    Allowed file types: JPG, PNG, WEBP. Maximum size: 5MB.
                </p>

                <button type="submit" name="update_profile" class="btn-primary">Save Changes</button>
                <a href="profile.php" class="btn-secondary">Cancel</a>
            </form>
        </div>
    <?php endif; ?>

    <br>

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