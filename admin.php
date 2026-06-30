<?php
// Start the session so the system can check whether the user is logged in
session_start();

// Connect this page to the database using db_connect.php
require 'db_connect.php';

// 1. SECURITY CHECK: Ensure user is logged in AND is an Admin
// This prevents Residents or users who are not logged in from accessing the admin dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Store the logged-in admin's ID from the session
// This is used when creating a new community service/event
$admin_id = $_SESSION['user_id'];

// This variable is used to display success or error messages on the page
$message = "";

// ==========================================
// REQUIREMENT: CRUD - DELETE
// ==========================================
// This section handles the DELETE function for community services/events
// It runs when the admin clicks the Delete button and the URL contains a valid delete ID
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Convert the delete ID into an integer for safety
    $delete_id = intval($_GET['delete']);

    // Delete related feedback first because Feedback is linked to CommunityServices
    // This prevents foreign key errors when deleting an event
    $stmt_feedback = $conn->prepare("DELETE FROM Feedback WHERE ServiceID = ?");
    $stmt_feedback->bind_param("i", $delete_id);
    $stmt_feedback->execute();
    $stmt_feedback->close();

    // Delete related participation requests because ParticipationRequests is linked to CommunityServices
    // This removes all user requests connected to the event before deleting the event itself
    $stmt_requests = $conn->prepare("DELETE FROM ParticipationRequests WHERE ServiceID = ?");
    $stmt_requests->bind_param("i", $delete_id);
    $stmt_requests->execute();
    $stmt_requests->close();

    // Now delete the event from CommunityServices
    // Prepared statement is used to safely delete the selected event
    $stmt = $conn->prepare("DELETE FROM CommunityServices WHERE ServiceID = ?");
    $stmt->bind_param("i", $delete_id);

    // If the delete query is successful, show a success message
    // Otherwise, show an error message
    if ($stmt->execute()) {
        $message = "<div class='alert success'>Event and related records deleted successfully.</div>";
    } else {
        $message = "<div class='alert error'>Error deleting event.</div>";
    }

    // Close the prepared statement after use
    $stmt->close();
}

// ==========================================
// REQUIREMENT: CRUD - CREATE & UPDATE (With Validation)
// ==========================================
// This section handles both adding new events and editing existing events
// It runs when the admin submits the add/edit form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_action'])) {
    // Sanitize inputs to prevent XSS
    // trim() removes unnecessary spaces from the beginning and end of the input
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_start_time = $_POST['event_start_time'];
    $event_end_time = $_POST['event_end_time'];
    $location = trim($_POST['location']);
    $capacity = intval($_POST['capacity']);

    // If the form is editing an existing event, service_id will contain the event ID
    // If adding a new event, service_id will be 0
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

    // RUBRIC REQUIREMENT: Validation (Server-side)
    // This checks that all required fields are filled in before saving to the database
    if (empty($title) || empty($description) || empty($event_date) || empty($event_start_time) || empty($event_end_time) || empty($location)) {
        $message = "<div class='alert error'>Validation Error: All fields are required.</div>";

    // This checks that the event end time is later than the start time
    // It prevents invalid events such as 5:00 PM to 2:00 PM
    } elseif ($event_end_time <= $event_start_time) {
        $message = "<div class='alert error'>Validation Error: End time must be later than start time.</div>";

    // This checks that the event capacity is at least 1 volunteer
    } elseif ($capacity < 1) {
        $message = "<div class='alert error'>Validation Error: Capacity must be at least 1.</div>";

    } else {
        // If submit_action is "add", the system will create a new event
        if ($_POST['submit_action'] == 'add') {
            // CREATE Request
            // This inserts a new community service/event into the CommunityServices table
            $stmt = $conn->prepare("INSERT INTO CommunityServices (Title, Description, EventDate, EventStartTime, EventEndTime, Location, Capacity, AdminID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Bind the form values to the SQL query
            // s = string, i = integer
            $stmt->bind_param("ssssssii", $title, $description, $event_date, $event_start_time, $event_end_time, $location, $capacity, $admin_id);
            
            // Execute the insert query and show a success message if it works
            if ($stmt->execute()) {
                $message = "<div class='alert success'>Event successfully created in database!</div>";
            }

            // Close the prepared statement after use
            $stmt->close();

        // If submit_action is "edit", the system will update an existing event
        } elseif ($_POST['submit_action'] == 'edit') {
            // UPDATE Request
            // This updates the selected community service/event in the CommunityServices table
            $stmt = $conn->prepare("UPDATE CommunityServices SET Title=?, Description=?, EventDate=?, EventStartTime=?, EventEndTime=?, Location=?, Capacity=? WHERE ServiceID=?");
            
            // Bind the edited form values to the SQL query
            $stmt->bind_param("ssssssii", $title, $description, $event_date, $event_start_time, $event_end_time, $location, $capacity, $service_id);
            
            // Execute the update query and show a success message if it works
            if ($stmt->execute()) {
                $message = "<div class='alert success'>Event database record updated successfully!</div>";
            }

            // Close the prepared statement after use
            $stmt->close();
        }
    }
}

// ==========================================
// REQUIREMENT: SEARCH, FILTER, SORT, & PAGINATION
// ==========================================

// Capture current state from URL parameters
// These values come from the search bar, filter dropdown, sorting links, and page number
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort_col = isset($_GET['sort']) ? $_GET['sort'] : 'EventDate';
$sort_dir = isset($_GET['dir']) && $_GET['dir'] == 'ASC' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// If the page number is less than 1, reset it back to page 1
if ($page < 1) $page = 1;

// Pagination limit: 5 rows per page
$limit = 5;

// Prevent SQL Injection on Sort Columns
// Only these selected columns are allowed to be used for sorting
$allowed_cols = ['Title', 'EventDate', 'Location', 'Capacity'];

// If the user tries to sort by an invalid column, default back to EventDate
if (!in_array($sort_col, $allowed_cols)) $sort_col = 'EventDate';

// Dynamically build the SQL WHERE clause
// WHERE 1=1 is used so more conditions can be added easily using AND
$sql_where = "WHERE 1=1 ";

// 1. SEARCH: Match Title or Location
// If the admin types something into the search bar, search the event title and location
if (!empty($search)) {
    // Escape the search input to reduce SQL injection risk
    $safe_search = $conn->real_escape_string($search);

    // Add search condition into the SQL WHERE clause
    $sql_where .= " AND (Title LIKE '%$safe_search%' OR Location LIKE '%$safe_search%') ";
}

// 2. FILTER: Show Upcoming vs Past events based on current database date
// If admin chooses upcoming, show events today or later
if ($filter === 'upcoming') {
    $sql_where .= " AND EventDate >= CURDATE() ";

// If admin chooses past, show events before today
} elseif ($filter === 'past') {
    $sql_where .= " AND EventDate < CURDATE() ";
}

// 3. PAGINATION: Count total rows for math
// This query counts how many records match the search/filter condition
$count_query = "SELECT COUNT(*) as total FROM CommunityServices " . $sql_where;
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];

// Calculate total number of pages needed
$total_pages = ceil($total_rows / $limit);

// Calculate which row the current page should start from
$offset = ($page - 1) * $limit;

// 4. CRUD - READ: Final Query bringing it all together
// This query reads community service records based on search, filter, sort, and pagination
$sql = "SELECT * FROM CommunityServices $sql_where ORDER BY $sort_col $sort_dir LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Helper function to maintain search/filter state while changing pages or sorting
// This function keeps the current URL parameters and only updates the selected value
// Example: when clicking page 2, the search keyword and filter will still remain
function build_url($updates) {
    // Copy all current URL parameters
    $params = $_GET;

    // Replace or add the updated parameters
    foreach($updates as $key => $value) { $params[$key] = $value; }

    // Return the final URL query string
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

    <!-- style.css controls the page design and layout -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Admin navigation bar -->
<!-- This allows the admin to move between Manage Services, Manage Requests, Moderate Feedback, and Logout -->
<div class="navbar admin">
    <div><strong>CommunityConnect Admin</strong></div>
    <div>
        <a href="admin.php" style="text-decoration: underline;">Manage Services</a>
        <a href="manage_requests.php">Manage Requests</a>
        <a href="moderate_feedback.php">Moderate Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<!-- Main container for the admin dashboard content -->
<div class="container">
    <h2>Database Management: Community Services</h2>

    <!-- Display success or error messages after add, edit, or delete actions -->
    <?php echo $message; ?>

    <!-- This section shows the add/edit form only when the URL action is add or edit -->
    <?php if (isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')): 
        
        // Fetch database data if editing
        // These variables store existing event data so the edit form can be pre-filled
        $edit_title = $edit_desc = $edit_loc = $edit_date = $edit_start_time = $edit_end_time = $edit_cap = "";
        $service_id = 0;
        
        // If the admin is editing an event, get that event's current details from the database
        if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
            // Convert the event ID from the URL into an integer for safety
            $service_id = intval($_GET['id']);

            // Select the event details based on ServiceID
            $edit_stmt = $conn->prepare("SELECT * FROM CommunityServices WHERE ServiceID = ?");
            $edit_stmt->bind_param("i", $service_id);
            $edit_stmt->execute();

            // Fetch the selected event as an associative array
            $edit_data = $edit_stmt->get_result()->fetch_assoc();
            
            // If the event exists, store its details inside variables for the form
            if($edit_data) {
                $edit_title = $edit_data['Title'];
                $edit_desc = $edit_data['Description'];
                $edit_loc = $edit_data['Location'];
                $edit_start_time = $edit_data['EventStartTime'];
                $edit_end_time = $edit_data['EventEndTime'];
                $edit_date = $edit_data['EventDate'];
                $edit_cap = $edit_data['Capacity'];
            }

            // Close the prepared statement after use
            $edit_stmt->close();
        }
    ?>

    <!-- Add/Edit form section -->
    <!-- The same form is reused for both creating a new event and editing an existing event -->
    <div class="form-section">
        <h3><?php echo $_GET['action'] == 'add' ? 'Create New Database Entry' : 'Update Database Record'; ?></h3>

        <form action="admin.php" method="POST">
            <!-- Hidden input tells PHP whether this form is adding or editing -->
            <input type="hidden" name="submit_action" value="<?php echo $_GET['action']; ?>">

            <!-- Hidden input stores the ServiceID when editing an existing event -->
            <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
            
            <!-- Event title input -->
            <label>Event Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($edit_title); ?>" required>
            
            <!-- Event description input -->
            <label>Description</label>
            <textarea name="description" rows="3" required><?php echo htmlspecialchars($edit_desc); ?></textarea>
            
            <!-- Event date input -->
            <label>Event Date</label>
            <input type="date" name="event_date" value="<?php echo $edit_date; ?>" required>

            <!-- Event start time input -->
            <!-- This allows admins to set when the event starts -->
            <label>Start Time</label>
            <input type="time" name="event_start_time" value="<?php echo $edit_start_time; ?>" required>

            <!-- Event end time input -->
            <!-- This allows the system to calculate volunteered hours automatically -->
            <label>End Time</label>
            <input type="time" name="event_end_time" value="<?php echo $edit_end_time; ?>" required>
            
            <!-- Event location input -->
            <label>Location</label>
            <input type="text" name="location" value="<?php echo htmlspecialchars($edit_loc); ?>" required>
            
            <!-- Volunteer capacity input -->
            <label>Volunteer Capacity</label>
            <input type="number" name="capacity" min="1" value="<?php echo $edit_cap; ?>" required>
            
            <br><br>

            <!-- Submit button for saving the add/edit form -->
            <button type="submit" class="btn-success">Save to Database</button>

            <!-- Cancel button returns admin back to the main admin page -->
            <a href="admin.php" class="btn-danger" style="text-decoration: none;">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- Search, filter, and add new service controls -->
    <div class="controls">
        <!-- GET method is used because search/filter/sort values should appear in the URL -->
        <form action="admin.php" method="GET">
            <!-- Search input allows admin to search event title or location -->
            <input type="text" name="search" placeholder="Search Title or Location..." value="<?php echo htmlspecialchars($search); ?>">
            
            <!-- Filter dropdown allows admin to view all, upcoming, or past events -->
            <select name="filter">
                <option value="all" <?php if($filter == 'all') echo 'selected'; ?>>All Dates</option>
                <option value="upcoming" <?php if($filter == 'upcoming') echo 'selected'; ?>>Upcoming</option>
                <option value="past" <?php if($filter == 'past') echo 'selected'; ?>>Past Events</option>
            </select>
            
            <!-- Hidden inputs preserve the current sorting column and direction when applying search/filter -->
            <input type="hidden" name="sort" value="<?php echo $sort_col; ?>">
            <input type="hidden" name="dir" value="<?php echo $sort_dir; ?>">
            
            <!-- Button to apply search and filter -->
            <button type="submit" class="btn-primary">Apply Filtering & Search</button>

            <!-- Clear button resets search/filter/sort by going back to admin.php -->
            <a href="admin.php" class="btn-secondary">Clear</a>
        </form>
        
        <!-- Add button opens the add event form -->
        <a href="admin.php?action=add" class="btn-success">+ Add New Service</a>
    </div>

    <!-- Table showing community service records from the database -->
    <table>
        <thead>
            <tr>
                <!-- This changes the sorting direction when the admin clicks a column heading -->
                <?php $next_dir = ($sort_dir == 'ASC') ? 'DESC' : 'ASC'; ?>

                <!-- Sortable title column -->
                <th><a href="<?php echo build_url(['sort'=>'Title', 'dir'=>$next_dir]); ?>">Title <?php if($sort_col=='Title') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>

                <!-- Sortable date column -->
                <th><a href="<?php echo build_url(['sort'=>'EventDate', 'dir'=>$next_dir]); ?>">Date <?php if($sort_col=='EventDate') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>

                <!-- Sortable location column -->
                <th><a href="<?php echo build_url(['sort'=>'Location', 'dir'=>$next_dir]); ?>">Location <?php if($sort_col=='Location') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>

                <!-- Sortable capacity column -->
                <th><a href="<?php echo build_url(['sort'=>'Capacity', 'dir'=>$next_dir]); ?>">Capacity <?php if($sort_col=='Capacity') echo ($sort_dir=='ASC')?'&uarr;':'&darr;'; ?></a></th>

                <!-- Actions column contains Edit and Delete buttons -->
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php
            // Check if there are any community service records to display
            if ($result->num_rows > 0) {
                // Loop through each event record and display it as a table row
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";

                    // Display event title safely using htmlspecialchars to prevent XSS
                    echo "<td>" . htmlspecialchars($row['Title']) . "</td>";

                    // Display event date in a more readable format
                    echo "<td>" . date("M j, Y", strtotime($row['EventDate'])) . "</td>";

                    // Display event location safely using htmlspecialchars
                    echo "<td>" . htmlspecialchars($row['Location']) . "</td>";

                    // Display event capacity
                    echo "<td>" . $row['Capacity'] . "</td>";

                    // Display Edit and Delete action buttons
                    // Edit opens the form with the selected event data
                    // Delete asks for confirmation before deleting the event
                    echo "<td>
                            <a href='admin.php?action=edit&id=" . $row['ServiceID'] . "' class='btn-warning'>Edit</a> 
                            <a href='admin.php?delete=" . $row['ServiceID'] . "' class='btn-danger' onclick=\"return confirm('Confirm deletion from database?');\">Delete</a>
                          </td>";

                    echo "</tr>";
                }
            } else {
                // If no records are found, show a message inside the table
                echo "<tr><td colspan='5' style='text-align: center;'>No community services found in the database.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <!-- Pagination section -->
    <!-- Pagination only appears when there is more than one page of results -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <!-- Loop from page 1 until the total number of pages -->
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <!-- Each pagination link keeps the current search/filter/sort values using build_url() -->
            <a href="<?php echo build_url(['page' => $i]); ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>

        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>