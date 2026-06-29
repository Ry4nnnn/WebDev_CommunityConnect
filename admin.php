<?php
session_start();
require 'db_connect.php';

// 1. SECURITY CHECK: Ensure user is logged in AND is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = "";

// ==========================================
// REQUIREMENT: CRUD - DELETE
// ==========================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    // Delete related feedback first because Feedback is linked to CommunityServices
    $stmt_feedback = $conn->prepare("DELETE FROM Feedback WHERE ServiceID = ?");
    $stmt_feedback->bind_param("i", $delete_id);
    $stmt_feedback->execute();
    $stmt_feedback->close();

    // Delete related participation requests because ParticipationRequests is linked to CommunityServices
    $stmt_requests = $conn->prepare("DELETE FROM ParticipationRequests WHERE ServiceID = ?");
    $stmt_requests->bind_param("i", $delete_id);
    $stmt_requests->execute();
    $stmt_requests->close();

    // Now delete the event from CommunityServices
    $stmt = $conn->prepare("DELETE FROM CommunityServices WHERE ServiceID = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "<div class='alert success'>Event and related records deleted successfully.</div>";
    } else {
        $message = "<div class='alert error'>Error deleting event.</div>";
    }

    $stmt->close();
}

// ==========================================
// REQUIREMENT: CRUD - CREATE & UPDATE (With Validation)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_action'])) {
    // Sanitize inputs to prevent XSS
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_start_time = $_POST['event_start_time'];
    $event_end_time = $_POST['event_end_time'];
    $location = trim($_POST['location']);
    $capacity = intval($_POST['capacity']);
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

    // RUBRIC REQUIREMENT: Validation (Server-side)
    if (empty($title) || empty($description) || empty($event_date) || empty($event_start_time) || empty($event_end_time) || empty($location)) {
        $message = "<div class='alert error'>Validation Error: All fields are required.</div>";
    } elseif ($event_end_time <= $event_start_time) {
        $message = "<div class='alert error'>Validation Error: End time must be later than start time.</div>";
    } elseif ($capacity < 1) {
        $message = "<div class='alert error'>Validation Error: Capacity must be at least 1.</div>";
    } else {
        if ($_POST['submit_action'] == 'add') {
            // CREATE Request
            $stmt = $conn->prepare("INSERT INTO CommunityServices (Title, Description, EventDate, EventStartTime, EventEndTime, Location, Capacity, AdminID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssii", $title, $description, $event_date, $event_start_time, $event_end_time, $location, $capacity, $admin_id);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>Event successfully created in database!</div>";
            }
            $stmt->close();
        } elseif ($_POST['submit_action'] == 'edit') {
            // UPDATE Request
            $stmt = $conn->prepare("UPDATE CommunityServices SET Title=?, Description=?, EventDate=?, EventStartTime=?, EventEndTime=?, Location=?, Capacity=? WHERE ServiceID=?");
            $stmt->bind_param("ssssssii", $title, $description, $event_date, $event_start_time, $event_end_time, $location, $capacity, $service_id);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>Event database record updated successfully!</div>";
            }
            $stmt->close();
        }
    }
}

// ==========================================
// REQUIREMENT: SEARCH, FILTER, SORT, & PAGINATION
// ==========================================

// Capture current state from URL parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort_col = isset($_GET['sort']) ? $_GET['sort'] : 'EventDate';
$sort_dir = isset($_GET['dir']) && $_GET['dir'] == 'ASC' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 5; // Pagination limit: 5 rows per page

// Prevent SQL Injection on Sort Columns
$allowed_cols = ['Title', 'EventDate', 'Location', 'Capacity'];
if (!in_array($sort_col, $allowed_cols)) $sort_col = 'EventDate';

// Dynamically build the SQL WHERE clause
$sql_where = "WHERE 1=1 ";

// 1. SEARCH: Match Title or Location
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $sql_where .= " AND (Title LIKE '%$safe_search%' OR Location LIKE '%$safe_search%') ";
}

// 2. FILTER: Show Upcoming vs Past events based on current database date
if ($filter === 'upcoming') {
    $sql_where .= " AND EventDate >= CURDATE() ";
} elseif ($filter === 'past') {
    $sql_where .= " AND EventDate < CURDATE() ";
}

// 3. PAGINATION: Count total rows for math
$count_query = "SELECT COUNT(*) as total FROM CommunityServices " . $sql_where;
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$offset = ($page - 1) * $limit;

// 4. CRUD - READ: Final Query bringing it all together
$sql = "SELECT * FROM CommunityServices $sql_where ORDER BY $sort_col $sort_dir LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Helper function to maintain search/filter state while changing pages or sorting
function build_url($updates) {
    $params = $_GET;
    foreach($updates as $key => $value) { $params[$key] = $value; }
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Services</title>
    <!-- style.css -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="navbar admin">
    <div><strong>CommunityConnect Admin</strong></div>
    <div>
        <a href="admin.php" style="text-decoration: underline;">Manage Services</a>
        <a href="manage_requests.php">Manage Requests</a>
        <a href="moderate_feedback.php">Moderate Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2>Database Management: Community Services</h2>
    <?php echo $message; ?>

    <?php if (isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')): 
        
        // Fetch database data if editing
        $edit_title = $edit_desc = $edit_loc = $edit_date = $edit_start_time = $edit_end_time = $edit_cap = "";
        $service_id = 0;
        
        if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
            $service_id = intval($_GET['id']);
            $edit_stmt = $conn->prepare("SELECT * FROM CommunityServices WHERE ServiceID = ?");
            $edit_stmt->bind_param("i", $service_id);
            $edit_stmt->execute();
            $edit_data = $edit_stmt->get_result()->fetch_assoc();
            
            if($edit_data) {
                $edit_title = $edit_data['Title'];
                $edit_desc = $edit_data['Description'];
                $edit_loc = $edit_data['Location'];
                $edit_start_time = $edit_data['EventStartTime'];
                $edit_end_time = $edit_data['EventEndTime'];
                $edit_date = $edit_data['EventDate'];
                $edit_cap = $edit_data['Capacity'];
            }
            $edit_stmt->close();
        }
    ?>

    <div class="form-section">
        <h3><?php echo $_GET['action'] == 'add' ? 'Create New Database Entry' : 'Update Database Record'; ?></h3>

        <form action="admin.php" method="POST">
            <input type="hidden" name="submit_action" value="<?php echo $_GET['action']; ?>">
            <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
            
            <label>Event Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($edit_title); ?>" required>
            
            <label>Description</label>
            <textarea name="description" rows="3" required><?php echo htmlspecialchars($edit_desc); ?></textarea>
            
            <label>Event Date</label>
            <input type="date" name="event_date" value="<?php echo $edit_date; ?>" required>

            <label>Start Time</label>
            <input type="time" name="event_start_time" value="<?php echo $edit_start_time; ?>" required>

            <label>End Time</label>
            <input type="time" name="event_end_time" value="<?php echo $edit_end_time; ?>" required>
            
            <label>Location</label>
            <input type="text" name="location" value="<?php echo htmlspecialchars($edit_loc); ?>" required>
            
            <label>Volunteer Capacity</label>
            <input type="number" name="capacity" min="1" value="<?php echo $edit_cap; ?>" required>
            
            <br><br>
            <button type="submit" class="btn-success">Save to Database</button>
            <a href="admin.php" class="btn-danger" style="text-decoration: none;">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <div class="controls">
        <form action="admin.php" method="GET">
            <input type="text" name="search" placeholder="Search Title or Location..." value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="filter">
                <option value="all" <?php if($filter == 'all') echo 'selected'; ?>>All Dates</option>
                <option value="upcoming" <?php if($filter == 'upcoming') echo 'selected'; ?>>Upcoming</option>
                <option value="past" <?php if($filter == 'past') echo 'selected'; ?>>Past Events</option>
            </select>
            
            <input type="hidden" name="sort" value="<?php echo $sort_col; ?>">
            <input type="hidden" name="dir" value="<?php echo $sort_dir; ?>">
            
            <button type="submit" class="btn-primary">Apply Filtering & Search</button>
            <a href="admin.php" class="btn-secondary">Clear</a>
        </form>
        
        <a href="admin.php?action=add" class="btn-success">+ Add New Service</a>
    </div>

    <table>
        <thead>
            <tr>
                <?php $next_dir = ($sort_dir == 'ASC') ? 'DESC' : 'ASC'; ?>
                <th><a href="<?php echo build_url(['sort'=>'Title', 'dir'=>$next_dir]); ?>">Title <?php if($sort_col=='Title') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>
                <th><a href="<?php echo build_url(['sort'=>'EventDate', 'dir'=>$next_dir]); ?>">Date <?php if($sort_col=='EventDate') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>
                <th><a href="<?php echo build_url(['sort'=>'Location', 'dir'=>$next_dir]); ?>">Location <?php if($sort_col=='Location') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>
                <th><a href="<?php echo build_url(['sort'=>'Capacity', 'dir'=>$next_dir]); ?>">Capacity <?php if($sort_col=='Capacity') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['Title']) . "</td>";
                    echo "<td>" . date("M j, Y", strtotime($row['EventDate'])) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Location']) . "</td>";
                    echo "<td>" . $row['Capacity'] . "</td>";
                    echo "<td>
                            <a href='admin.php?action=edit&id=" . $row['ServiceID'] . "' class='btn-warning'>Edit</a> 
                            <a href='admin.php?delete=" . $row['ServiceID'] . "' class='btn-danger' onclick=\"return confirm('Confirm deletion from database?');\">Delete</a>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align: center;'>No community services found in the database.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="<?php echo build_url(['page' => $i]); ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>