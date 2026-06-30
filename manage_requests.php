<?php
// Start the session so the system can check who is currently logged in.
// This is needed because only Admin users should access this page.
session_start();

// Connect this page to the database.
// db_connect.php contains the MySQL connection details.
require 'db_connect.php';

// 1. SECURITY CHECK: Admin Only
// If the user is not logged in or their role is not Admin,
// redirect them back to the login page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// This variable stores success or error messages.
// Example: request approved successfully or error updating request.
$message = "";

// 2. HANDLE STATUS UPDATES
// This section runs when the admin clicks Approve or Reject.
// The form sends request_id and new_status using POST.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id']) && isset($_POST['new_status'])) {
    // Convert request_id to integer for safety.
    $request_id = intval($_POST['request_id']);

    // Store the new status selected by the admin.
    $new_status = $_POST['new_status'];
    
    // Ensure only valid statuses are submitted.
    // This prevents invalid values from being inserted into the database.
    if (in_array($new_status, ['Approved', 'Rejected'])) {

        // Update the selected request status in the database.
        // Prepared statement is used to prevent SQL injection.
        $update_stmt = $conn->prepare("UPDATE ParticipationRequests SET Status = ? WHERE RequestID = ?");

        // Bind the new status and request ID into the SQL query.
        // "s" means string, "i" means integer.
        $update_stmt->bind_param("si", $new_status, $request_id);
        
        // Execute the update query.
        // If successful, display a success message.
        // If it fails, display an error message.
        if ($update_stmt->execute()) {
            $message = "<div class='alert success'>Request #$request_id successfully marked as $new_status.</div>";
        } else {
            $message = "<div class='alert error'>Error updating request status.</div>";
        }

        // Close the prepared statement after use.
        $update_stmt->close();
    }
}

// 3. GET FILTER AND SEARCH VALUES
// Default filter is Pending so admin sees new requests first.
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'Pending';

// Get the search keyword from the search bar.
// trim() removes extra spaces before and after the search input.
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Only allow valid filter values.
// This prevents users from manually changing the URL to invalid filter values.
$allowed_filters = ['Pending', 'Approved', 'Rejected', 'All'];

// If the filter value is invalid, reset it to Pending.
if (!in_array($filter, $allowed_filters)) {
    $filter = 'Pending';
}

// 4. PREPARE SQL QUERY
// This query joins three tables:
// ParticipationRequests = stores request details
// Users = stores resident name and email
// CommunityServices = stores event title and date
$sql = "SELECT pr.RequestID, u.FullName, u.Email, cs.Title, cs.EventDate, pr.Status, pr.RequestDate 
        FROM ParticipationRequests pr
        JOIN Users u ON pr.UserID = u.UserID
        JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID";

// These arrays are used to build the SQL query dynamically.
// They allow the page to support both filtering and searching.
$conditions = [];
$params = [];
$types = "";

// 5. FILTER BY STATUS
// If the selected filter is not All,
// add a condition to show only requests with that status.
if ($filter !== 'All') {
    $conditions[] = "pr.Status = ?";
    $params[] = $filter;
    $types .= "s";
}

// 6. SEARCH FUNCTION
// Admin can search by resident name, email, or event title.
if (!empty($search)) {
    // LIKE is used to allow partial matching.
    $conditions[] = "(u.FullName LIKE ? OR u.Email LIKE ? OR cs.Title LIKE ?)";

    // Add % before and after the search keyword.
    // Example: searching "tree" can match "Tree Planting Activity".
    $search_like = "%" . $search . "%";

    // The same search keyword is used for three different columns.
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;

    // Three string values are added, so the type is "sss".
    $types .= "sss";
}

// 7. ADD CONDITIONS TO SQL QUERY
// If there are any filter or search conditions,
// combine them using AND and add them to the SQL query.
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Sort requests by request date.
// ASC means the oldest requests appear first.
$sql .= " ORDER BY pr.RequestDate ASC";

// 8. EXECUTE QUERY USING PREPARED STATEMENT
// Prepare the final SQL query after adding filter/search conditions.
$stmt = $conn->prepare($sql);

// If there are filter/search parameters,
// bind them into the prepared statement.
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

// Execute the final query.
$stmt->execute();

// Store the result so it can be displayed in the HTML table.
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title shown on the browser tab -->
    <title>Manage Requests - Admin</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- Admin Navigation Bar -->
<!-- Allows the admin to switch between Manage Services, Manage Requests, Moderate Feedback, and Logout -->
<div class="navbar admin">
    <div><strong>CommunityConnect Admin</strong></div>

    <div>
        <a href="admin.php">Manage Services</a>

        <!-- Underlined because this is the current active page -->
        <a href="manage_requests.php" style="text-decoration: underline;">Manage Requests</a>

        <a href="moderate_feedback.php">Moderate Feedback</a>

        <a href="manage_users.php">Manage Users</a>

        <!-- Logout button ends the admin session -->
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
    <h2>Manage Participation Requests</h2>

    <!-- Display success or error message after approve/reject action -->
    <?php echo $message; ?>

    <!-- Search Form -->
    <!-- GET method is used so search/filter values appear in the URL -->
    <form action="manage_requests.php" method="GET" class="search-form">
        <!-- Keep the current filter when searching -->
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

        <!-- Search input for resident name, email, or event title -->
        <input 
            type="text" 
            name="search" 
            placeholder="Search resident name, email, or event title..."
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <!-- Submit button for search -->
        <button type="submit">Search</button>

        <!-- Show Clear button only if search is active -->
        <!-- Clear removes the search keyword but keeps the current filter -->
        <?php if (!empty($search)): ?>
            <a href="manage_requests.php?filter=<?php echo urlencode($filter); ?>" class="btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Filter Tabs -->
    <!-- These tabs allow admin to filter requests by status -->
    <div class="tabs">
        <a href="manage_requests.php?filter=Pending&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='Pending'?'active':''; ?>">Pending</a>

        <a href="manage_requests.php?filter=Approved&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='Approved'?'active':''; ?>">Approved</a>

        <a href="manage_requests.php?filter=Rejected&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='Rejected'?'active':''; ?>">Rejected</a>

        <a href="manage_requests.php?filter=All&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='All'?'active':''; ?>">All Requests</a>
    </div>

    <!-- Requests Table -->
    <!-- This table displays participation requests based on the selected filter/search -->
    <table>
        <thead>
            <tr>
                <th>Resident Name</th>
                <th>Event Title</th>
                <th>Event Date</th>
                <th>Applied On</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php
            // Check whether there are any request records to display.
            if ($result->num_rows > 0) {
                // Loop through each request record and display it as a table row.
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";

                    // Display resident name and email.
                    // htmlspecialchars() prevents XSS attacks when showing database content.
                    echo "<td>" . htmlspecialchars($row['FullName']) . "<br><small style='color:gray;'>" . htmlspecialchars($row['Email']) . "</small></td>";

                    // Display event title safely.
                    echo "<td>" . htmlspecialchars($row['Title']) . "</td>";

                    // Display event date in a readable format.
                    echo "<td>" . date("M j, Y", strtotime($row['EventDate'])) . "</td>";

                    // Display the date when the resident applied.
                    echo "<td>" . date("M j", strtotime($row['RequestDate'])) . "</td>";

                    // Display the request status using a badge.
                    // The badge style changes based on Pending, Approved, or Rejected.
                    echo "<td><span class='badge " . $row['Status'] . "'>" . $row['Status'] . "</span></td>";
                    
                    // Action Buttons section.
                    // Only Pending requests can be approved or rejected.
                    echo "<td>";

                    if ($row['Status'] === 'Pending') {
                        // Approve form.
                        // When clicked, it sends the RequestID and Approved status using POST.
                        echo "<form action='manage_requests.php' method='POST' style='display:inline-block; margin-right: 5px;'>
                                <input type='hidden' name='request_id' value='" . $row['RequestID'] . "'>
                                <input type='hidden' name='new_status' value='Approved'>
                                <button type='submit' class='btn-approve' onclick=\"return confirm('Approve this request?');\">Approve</button>
                              </form>";

                        // Reject form.
                        // When clicked, it sends the RequestID and Rejected status using POST.
                        echo "<form action='manage_requests.php' method='POST' style='display:inline-block;'>
                                <input type='hidden' name='request_id' value='" . $row['RequestID'] . "'>
                                <input type='hidden' name='new_status' value='Rejected'>
                                <button type='submit' class='btn-reject' onclick=\"return confirm('Reject this request?');\">Reject</button>
                              </form>";
                    } else {
                        // If the request is already Approved or Rejected,
                        // it cannot be processed again.
                        echo "<span style='color: #666; font-size: 14px;'>Processed</span>";
                    }

                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                // If no records match the selected filter/search,
                // display a message inside the table.
                echo "<tr><td colspan='6' style='text-align: center;'>No $filter requests found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

</body>
</html>