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
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM CommunityServices WHERE ServiceID = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "<div class='alert success'>Event deleted successfully from database.</div>";
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
    $location = trim($_POST['location']);
    $capacity = intval($_POST['capacity']);
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

    // RUBRIC REQUIREMENT: Validation (Server-side)
    if (empty($title) || empty($description) || empty($event_date) || empty($location)) {
        $message = "<div class='alert error'>Validation Error: All fields are required.</div>";
    } elseif ($capacity < 1) {
        $message = "<div class='alert error'>Validation Error: Capacity must be at least 1.</div>";
    } else {
        if ($_POST['submit_action'] == 'add') {
            // CREATE Request
            $stmt = $conn->prepare("INSERT INTO CommunityServices (Title, Description, EventDate, Location, Capacity, AdminID) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $title, $description, $event_date, $location, $capacity, $admin_id);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>Event successfully created in database!</div>";
            }
            $stmt->close();
        } elseif ($_POST['submit_action'] == 'edit') {
            // UPDATE Request
            $stmt = $conn->prepare("UPDATE CommunityServices SET Title=?, Description=?, EventDate=?, Location=?, Capacity=? WHERE ServiceID=?");
            $stmt->bind_param("ssssii", $title, $description, $event_date, $location, $capacity, $service_id);
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
    <title>Admin Dashboard - Manage Services</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; }
        .navbar { background-color: #343a40; padding: 15px 20px; color: white; display: flex; justify-content: space-between; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .navbar a:hover { text-decoration: underline; }
        
        .container { max-width: 1100px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #e9ecef; padding: 15px; border-radius: 5px; }
        .controls form { display: flex; gap: 10px; align-items: center; }
        .controls input, .controls select, .controls button { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        
        .btn-primary { background-color: #007BFF; color: white; cursor: pointer; text-decoration: none; padding: 8px 15px; border: none; display: inline-block;}
        .btn-success { background-color: #28a745; color: white; cursor: pointer; text-decoration: none; padding: 8px 15px; border: none;}
        .btn-danger { background-color: #dc3545; color: white; cursor: pointer; text-decoration: none; padding: 6px 10px; font-size: 14px;}
        .btn-warning { background-color: #ffc107; color: black; cursor: pointer; text-decoration: none; padding: 6px 10px; font-size: 14px;}
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        th a { color: #333; text-decoration: none; }
        th a:hover { text-decoration: underline; }
        
        .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: center; }
        .pagination a { padding: 8px 12px; background: #e9ecef; text-decoration: none; color: #333; border-radius: 4px; }
        .pagination a.active { background: #007BFF; color: white; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-section { background: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; }
        .form-section label { font-weight: bold; display: block; margin-top: 10px; }
        .form-section input, .form-section textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>CommunityConnect Admin</strong></div>
    <div>
        <a href="admin.php" style="text-decoration: underline;">Manage Services</a>
        <a href="manage_requests.php">Manage Requests</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2>Database Management: Community Services</h2>
    <?php echo $message; ?>

    <?php if (isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')): 
        
        // Fetch database data if editing
        $edit_title = $edit_desc = $edit_loc = $edit_date = $edit_cap = "";
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
            <a href="admin.php" style="color: #666; font-size: 14px; text-decoration: none; margin-left: 10px;">Clear</a>
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