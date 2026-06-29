<?php
// 1. Start session and connect to database
session_start();
require 'db_connect.php';

// 2. SECURITY CHECK: Make sure the user is logged in AND is a Resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    // If they aren't logged in, kick them back to the login page
    header("Location: login.php");
    exit();
}

// 3. CORE REQUIREMENT: Search Functionality
$search_term = "";
// We only want to show events that haven't happened yet (EventDate >= CURDATE)
$sql = "SELECT * FROM CommunityServices WHERE EventDate >= CURDATE()";

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    // Secure the search term to prevent basic SQL injection in the LIKE clause
    $safe_search = $conn->real_escape_string($search_term);
    $sql .= " AND (Title LIKE '%$safe_search%' OR Location LIKE '%$safe_search%')";
}

$sql .= " ORDER BY EventDate ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - CommunityConnect</title>
    <style>
        /* Clean and modern UI styling */
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; }
        .navbar { background-color: #007BFF; padding: 15px 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .navbar a:hover { text-decoration: underline; }
        .container { padding: 20px; max-width: 1000px; margin: 0 auto; }
        .search-bar { margin-bottom: 20px; display: flex; gap: 10px; }
        .search-bar input { padding: 10px; width: 300px; border: 1px solid #ccc; border-radius: 4px; }
        .search-bar button { padding: 10px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .search-bar button:hover { background-color: #218838; }
        
        /* Grid Layout for Events */
        .event-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .event-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .event-card h3 { margin-top: 0; color: #333; }
        .event-card p { color: #666; font-size: 14px; margin: 5px 0; }
        .btn-view { display: inline-block; margin-top: 15px; padding: 10px; background-color: #007BFF; color: white; text-decoration: none; border-radius: 4px; text-align: center; }
        .btn-view:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<div class="navbar">
    <div>
        <strong>CommunityConnect</strong> | Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
    </div>
    <div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_requests.php">My Requests</a>
        <a href="my_impact.php">My Impact (Add-on)</a> <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2>Available Community Services</h2>

    <form class="search-bar" action="user_dashboard.php" method="GET">
        <input type="text" name="search" placeholder="Search by title or location..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit">Search</button>
        <?php if(!empty($search_term)): ?>
            <a href="user_dashboard.php" style="padding: 10px; text-decoration: none; color: #dc3545;">Clear Search</a>
        <?php endif; ?>
    </form>

    <div class="event-grid">
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // Format the date nicely
                $formatted_date = date("F j, Y", strtotime($row['EventDate']));
                
                echo "<div class='event-card'>";
                echo "<h3>" . htmlspecialchars($row['Title']) . "</h3>";
                echo "<p><strong>Date:</strong> " . $formatted_date . "</p>";
                echo "<p><strong>Location:</strong> " . htmlspecialchars($row['Location']) . "</p>";
                echo "<p><strong>Capacity:</strong> " . htmlspecialchars($row['Capacity']) . " volunteers</p>";
                // This button will take them to the details page where they can actually submit the request!
                echo "<a href='event_details.php?id=" . $row['ServiceID'] . "' class='btn-view'>View Details & Join</a>";
                echo "</div>";
            }
        } else {
            echo "<p>No community services found matching your search.</p>";
        }
        ?>
    </div>
</div>

</body>
</html>