<?php
// 1. Start session and connect to database
// session_start() allows the page to access session variables such as user_id, full_name, and role.
session_start();

// Include the database connection file so this page can retrieve community service/event data.
require 'db_connect.php';

// 2. SECURITY CHECK: Make sure the user is logged in AND is a Resident
// This page is only for resident users.
// If the user is not logged in or is not a Resident, redirect them back to the login page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    // If they aren't logged in, kick them back to the login page
    header("Location: login.php");
    exit();
}

// ===============================
// ADD-ON FEATURE: EVENT REMINDER
// ===============================
// This reminder checks whether the logged-in resident has any approved upcoming events.
// If the resident has an approved event that has not happened yet,
// the system will display a reminder message on the dashboard.
$reminder_stmt = $conn->prepare("
    SELECT c.Title, c.EventDate, c.EventStartTime 
    FROM ParticipationRequests p 
    JOIN CommunityServices c ON p.ServiceID = c.ServiceID 
    WHERE p.UserID = ? 
    AND p.Status = 'Approved' 
    AND c.EventDate >= CURDATE() 
    ORDER BY c.EventDate ASC
");

// Bind the logged-in user's ID into the reminder query.
$reminder_stmt->bind_param("i", $_SESSION['user_id']);

// Execute the reminder query.
$reminder_stmt->execute();

// Store the reminder result so it can be displayed later in the HTML section.
$reminder_result = $reminder_stmt->get_result();

// Close the reminder prepared statement after use.
$reminder_stmt->close();

// 3. CORE REQUIREMENT: Search Functionality
// This variable stores the keyword entered by the user in the search bar.
$search_term = "";

// We only want to show events that haven't happened yet (EventDate >= CURDATE).
// CURDATE() gets today's date from MySQL.
// This means past events will not appear on the resident dashboard.
$sql = "SELECT * FROM CommunityServices WHERE EventDate >= CURDATE()";

// Check whether the user entered a search keyword.
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    // Store the search keyword after removing extra spaces.
    $search_term = trim($_GET['search']);

    // Secure the search term to prevent basic SQL injection in the LIKE clause.
    // real_escape_string() escapes special characters before using the input in SQL.
    $safe_search = $conn->real_escape_string($search_term);

    // Add search condition to the SQL query.
    // Users can search events by title or location.
    $sql .= " AND (Title LIKE '%$safe_search%' OR Location LIKE '%$safe_search%')";
}

// Sort the available events by nearest upcoming date first.
$sql .= " ORDER BY EventDate ASC";

// Run the final SQL query and store the result.
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title shown on the browser tab -->
    <title>Resident Dashboard - CommunityConnect</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Resident Navigation Bar -->
<!-- This navigation bar allows residents to move between dashboard, requests, impact, feedback, and logout -->
<div class="navbar">
    <div>
        <!-- Display the system name and welcome the logged-in resident -->
        <!-- htmlspecialchars() is used to prevent XSS when displaying the user's name -->
        <strong>CommunityConnect</strong> | Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
    </div>

    <div>
        <!-- Link to resident dashboard -->
        <a href="user_dashboard.php" style="text-decoration: underline;">Dashboard</a>

        <!-- Link to view the resident's submitted participation requests -->
        <a href="my_requests.php">My Requests</a>

        <!-- Link to My Impact add-on feature -->
        <a href="my_impact.php">My Impact (Add-on)</a>

        <!-- Link to feedback page where residents can review completed events -->
        <a href="feedback.php">Feedback</a>

        <!-- Logout button ends the session and returns user to login page -->
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<!-- Main Content Container -->
<div class="container">
    <h2>Available Community Services</h2>

    <!-- Event Reminder Section -->
    <!-- This section only appears if the resident has approved upcoming events -->
    <?php if ($reminder_result->num_rows > 0): ?>

        <!-- Loop through each approved upcoming event and display it as a reminder -->
        <?php while($reminder = $reminder_result->fetch_assoc()): ?>
            <div class="alert success" style="background-color: #cce5ff; color: #004085; border-color: #b8daff; margin-bottom: 15px;">
                <strong>🔔 Reminder:</strong> You have an upcoming event! 
                
                <!-- Display the approved event title safely -->
                <strong><?php echo htmlspecialchars($reminder['Title']); ?></strong> is on 

                <!-- Display the event date in readable format -->
                <strong><?php echo date("F j, Y", strtotime($reminder['EventDate'])); ?></strong> at 

                <!-- Display the event start time in readable format -->
                <strong><?php echo date("g:i A", strtotime($reminder['EventStartTime'])); ?></strong>.
            </div>
        <?php endwhile; ?>

    <?php endif; ?>

    <!-- Search form -->
    <!-- GET method is used so the search keyword appears in the URL -->
    <form class="search-bar" action="user_dashboard.php" method="GET">
        <!-- Search input allows users to search events by title or location -->
        <!-- The value keeps the search keyword inside the input after searching -->
        <input type="text" name="search" placeholder="Search by title or location..." value="<?php echo htmlspecialchars($search_term); ?>">

        <!-- Submit button for search -->
        <button type="submit">Search</button>

        <!-- Clear Search button only appears if the user has searched something -->
        <!-- Clicking it returns the user to the dashboard without a search keyword -->
        <?php if(!empty($search_term)): ?>
            <a href="user_dashboard.php" class="btn btn-danger">Clear Search</a>
        <?php endif; ?>
    </form>

    <!-- Event grid section -->
    <!-- This displays all available upcoming community services as cards -->
    <div class="event-grid">
        <?php
        // Check whether the SQL query returned any available services.
        if ($result->num_rows > 0) {
            // Loop through each community service/event record.
            while($row = $result->fetch_assoc()) {
                // Format the date nicely.
                // Example: 2026-07-05 becomes July 5, 2026.
                $formatted_date = date("F j, Y", strtotime($row['EventDate']));
                
                // Start event card.
                echo "<div class='event-card'>";

                // Display event title safely using htmlspecialchars() to prevent XSS.
                echo "<h3>" . htmlspecialchars($row['Title']) . "</h3>";

                // Display formatted event date.
                echo "<p><strong>Date:</strong> " . $formatted_date . "</p>";

                // Display event location safely.
                echo "<p><strong>Location:</strong> " . htmlspecialchars($row['Location']) . "</p>";

                // Display volunteer capacity safely.
                echo "<p><strong>Capacity:</strong> " . htmlspecialchars($row['Capacity']) . " volunteers</p>";

                // This button will take them to the details page where they can actually submit the request!
                // The ServiceID is passed through the URL so event_details.php knows which event to display.
                echo "<a href='event_details.php?id=" . $row['ServiceID'] . "' class='btn-view'>View Details & Join</a>";

                // End event card.
                echo "</div>";
            }
        } else {
            // If no events match the search or no upcoming events exist, show this message.
            echo "<p>No community services found matching your search.</p>";
        }
        ?>
    </div>
</div>

</body>
</html>