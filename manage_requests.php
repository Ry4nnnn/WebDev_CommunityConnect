<?php
// Start the session so the system can check who is logged in
session_start();

// Connect to the database
require 'db_connect.php';

// 1. SECURITY CHECK: Admin Only
// If the user is not logged in or is not an Admin, redirect them back to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// This variable stores success or error messages
$message = "";

// 2. HANDLE STATUS UPDATES
// This runs when the admin clicks Approve or Reject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id']) && isset($_POST['new_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['new_status'];
    
    // Ensure only valid statuses are submitted
    if (in_array($new_status, ['Approved', 'Rejected'])) {

        // Update the selected request status in the database
        $update_stmt = $conn->prepare("UPDATE ParticipationRequests SET Status = ? WHERE RequestID = ?");
        $update_stmt->bind_param("si", $new_status, $request_id);
        
        if ($update_stmt->execute()) {
            $message = "<div class='alert success'>Request #$request_id successfully marked as $new_status.</div>";
        } else {
            $message = "<div class='alert error'>Error updating request status.</div>";
        }

        $update_stmt->close();
    }
}

// 3. GET FILTER AND SEARCH VALUES
// Default filter is Pending, so admin sees new requests first
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'Pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Only allow valid filter values to prevent unwanted input
$allowed_filters = ['Pending', 'Approved', 'Rejected', 'All'];

if (!in_array($filter, $allowed_filters)) {
    $filter = 'Pending';
}

// 4. PREPARE SQL QUERY
// This query joins ParticipationRequests, Users, and CommunityServices
// so the admin can see resident details, event details, and request status
$sql = "SELECT pr.RequestID, u.FullName, u.Email, cs.Title, cs.EventDate, pr.Status, pr.RequestDate 
        FROM ParticipationRequests pr
        JOIN Users u ON pr.UserID = u.UserID
        JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID";

$conditions = [];
$params = [];
$types = "";

// 5. FILTER BY STATUS
// Example: Pending, Approved, Rejected, or All
if ($filter !== 'All') {
    $conditions[] = "pr.Status = ?";
    $params[] = $filter;
    $types .= "s";
}

// 6. SEARCH FUNCTION
// Admin can search by resident name, email, or event title
if (!empty($search)) {
    $conditions[] = "(u.FullName LIKE ? OR u.Email LIKE ? OR cs.Title LIKE ?)";
    $search_like = "%" . $search . "%";

    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;

    $types .= "sss";
}

// 7. ADD CONDITIONS TO SQL QUERY
// If there is a filter or search term, add WHERE conditions
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Sort requests by request date
$sql .= " ORDER BY pr.RequestDate ASC";

// 8. EXECUTE QUERY USING PREPARED STATEMENT
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Requests - Admin</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- Admin Navigation Bar -->
<div class="navbar admin">
    <div><strong>CommunityConnect Admin</strong></div>

    <div>
        <a href="admin.php">Manage Services</a>
        <a href="manage_requests.php" style="text-decoration: underline;">Manage Requests</a>
        <a href="moderate_feedback.php">Moderate Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<!-- Main Content Container -->
<div class="container">
    <h2>Manage Participation Requests</h2>

    <!-- Display success or error message -->
    <?php echo $message; ?>

    <!-- Search Form -->
    <form action="manage_requests.php" method="GET" class="search-form">
        <!-- Keep the current filter when searching -->
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

        <input 
            type="text" 
            name="search" 
            placeholder="Search resident name, email, or event title..."
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <button type="submit">Search</button>

        <!-- Show Clear button only if search is active -->
        <?php if (!empty($search)): ?>
            <a href="manage_requests.php?filter=<?php echo urlencode($filter); ?>" class="btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Filter Tabs -->
    <div class="tabs">
        <a href="manage_requests.php?filter=Pending&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='Pending'?'active':''; ?>">Pending</a>

        <a href="manage_requests.php?filter=Approved&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='Approved'?'active':''; ?>">Approved</a>

        <a href="manage_requests.php?filter=Rejected&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='Rejected'?'active':''; ?>">Rejected</a>

        <a href="manage_requests.php?filter=All&search=<?php echo urlencode($search); ?>" class="<?php echo $filter=='All'?'active':''; ?>">All Requests</a>
    </div>

    <!-- Requests Table -->
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
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";

                    // Using htmlspecialchars to prevent XSS attacks
                    echo "<td>" . htmlspecialchars($row['FullName']) . "<br><small style='color:gray;'>" . htmlspecialchars($row['Email']) . "</small></td>";

                    echo "<td>" . htmlspecialchars($row['Title']) . "</td>";
                    echo "<td>" . date("M j, Y", strtotime($row['EventDate'])) . "</td>";
                    echo "<td>" . date("M j", strtotime($row['RequestDate'])) . "</td>";

                    // Status badge will be styled based on Pending, Approved, or Rejected
                    echo "<td><span class='badge " . $row['Status'] . "'>" . $row['Status'] . "</span></td>";
                    
                    // Action Buttons
                    // Only pending requests can be approved or rejected
                    echo "<td>";

                    if ($row['Status'] === 'Pending') {
                        echo "<form action='manage_requests.php' method='POST' style='display:inline-block; margin-right: 5px;'>
                                <input type='hidden' name='request_id' value='" . $row['RequestID'] . "'>
                                <input type='hidden' name='new_status' value='Approved'>
                                <button type='submit' class='btn-approve' onclick=\"return confirm('Approve this request?');\">Approve</button>
                              </form>";

                        echo "<form action='manage_requests.php' method='POST' style='display:inline-block;'>
                                <input type='hidden' name='request_id' value='" . $row['RequestID'] . "'>
                                <input type='hidden' name='new_status' value='Rejected'>
                                <button type='submit' class='btn-reject' onclick=\"return confirm('Reject this request?');\">Reject</button>
                              </form>";
                    } else {
                        echo "<span style='color: #666; font-size: 14px;'>Processed</span>";
                    }

                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='text-align: center;'>No $filter requests found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

</body>
</html>