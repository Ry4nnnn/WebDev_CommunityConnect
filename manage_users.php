<?php
// Start the session so the system can check whether the user is logged in
session_start();

// Connect this page to the database using db_connect.php
require 'db_connect.php';

// Security check: Admin only
// If the user is not logged in or is not an Admin, redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Store logged-in admin ID
$admin_id = $_SESSION['user_id'];

// This variable stores success or error messages
$message = "";

// ===============================
// DELETE USER ACCOUNT
// ===============================
// This runs when admin clicks the Delete button
if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {

    // Convert selected user ID into integer for safety
    $delete_user_id = intval($_GET['delete_user']);

    // Prevent admin from deleting their own account
    if ($delete_user_id == $admin_id) {
        $message = "<div class='alert error'>You cannot delete your own admin account.</div>";

    } else {
        // Check whether the selected user exists
        $check_stmt = $conn->prepare("SELECT UserID, FullName, Role FROM Users WHERE UserID = ?");
        $check_stmt->bind_param("i", $delete_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            $message = "<div class='alert error'>User account not found.</div>";

        } else {
            $user_data = $check_result->fetch_assoc();

            // Prevent admin from deleting other admin accounts
            // This avoids accidentally removing important admin access
            if ($user_data['Role'] === 'Admin') {
                $message = "<div class='alert error'>Admin accounts cannot be deleted from this page.</div>";

            } else {
                // Use transaction so all delete steps succeed together
                // If one delete fails, everything will be cancelled
                $conn->begin_transaction();

                try {
                    // Delete user's feedback first because Feedback table is linked to Users
                    $delete_feedback = $conn->prepare("DELETE FROM Feedback WHERE UserID = ?");
                    $delete_feedback->bind_param("i", $delete_user_id);

                    if (!$delete_feedback->execute()) {
                        throw new Exception("Failed to delete user feedback.");
                    }

                    $delete_feedback->close();

                    // Delete user's participation requests because ParticipationRequests is linked to Users
                    $delete_requests = $conn->prepare("DELETE FROM ParticipationRequests WHERE UserID = ?");
                    $delete_requests->bind_param("i", $delete_user_id);

                    if (!$delete_requests->execute()) {
                        throw new Exception("Failed to delete user requests.");
                    }

                    $delete_requests->close();

                    // Delete the user account
                    $delete_user = $conn->prepare("DELETE FROM Users WHERE UserID = ? AND Role = 'Resident'");
                    $delete_user->bind_param("i", $delete_user_id);

                    if (!$delete_user->execute()) {
                        throw new Exception("Failed to delete user account.");
                    }

                    $delete_user->close();

                    // Confirm all delete steps
                    $conn->commit();

                    $message = "<div class='alert success'>Resident account deleted successfully.</div>";

                } catch (Exception $e) {
                    // Cancel all delete actions if any error happens
                    $conn->rollback();

                    $message = "<div class='alert error'>Error deleting user account. Please try again.</div>";
                }
            }
        }

        $check_stmt->close();
    }
}

// ===============================
// SEARCH AND FILTER USERS
// ===============================

// Get search keyword from URL
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// Get role filter from URL
$role_filter = isset($_GET['role']) ? $_GET['role'] : "all";

// Only allow valid role filter values
$allowed_roles = ["all", "Admin", "Resident"];

if (!in_array($role_filter, $allowed_roles)) {
    $role_filter = "all";
}

// Build WHERE condition
$sql_where = "WHERE 1=1 ";

// Search by full name or email
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $sql_where .= " AND (u.FullName LIKE '%$safe_search%' OR u.Email LIKE '%$safe_search%') ";
}

// Filter by role
if ($role_filter !== "all") {
    $safe_role = $conn->real_escape_string($role_filter);
    $sql_where .= " AND u.Role = '$safe_role' ";
}

// Fetch users with useful account statistics
$sql = "
    SELECT 
        u.UserID,
        u.FullName,
        u.Email,
        u.Role,
        u.CreatedAt,

        (
            SELECT COUNT(*) 
            FROM ParticipationRequests pr 
            WHERE pr.UserID = u.UserID
        ) AS total_requests,

        (
            SELECT COUNT(*) 
            FROM ParticipationRequests pr 
            WHERE pr.UserID = u.UserID 
            AND pr.Status = 'Approved'
        ) AS approved_requests,

        (
            SELECT COUNT(*) 
            FROM Feedback f 
            WHERE f.UserID = u.UserID
        ) AS total_feedback

    FROM Users u
    $sql_where
    ORDER BY u.CreatedAt DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- This viewport line helps make the website responsive on phones, tablets, and laptops -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Users - Admin</title>

    <!-- External CSS file used for consistent styling across all pages -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- Admin navigation bar -->
<div class="navbar admin">
    <div><strong>CommunityConnect Admin</strong></div>

    <div>
        <a href="admin.php">Manage Services</a>
        <a href="manage_requests.php">Manage Requests</a>
        <a href="moderate_feedback.php">Moderate Feedback</a>
        <a href="manage_users.php" style="text-decoration: underline;">Manage Users</a>

        <a 
            href="logout.php" 
            onclick="return confirm('Are you sure you want to log out?');"
            style="background: #dc3545; padding: 5px 10px; border-radius: 4px;"
        >
            Log Out
        </a>
    </div>
</div>

<!-- Main container -->
<div class="container">
    <h2>User Account Management</h2>

    <p style="color: #666;">
        View registered users, search accounts, filter by role, and remove resident accounts if needed.
    </p>

    <!-- Display success or error message -->
    <?php echo $message; ?>

    <!-- Search and filter controls -->
    <div class="controls">
        <form action="manage_users.php" method="GET">
            <input 
                type="text" 
                name="search" 
                placeholder="Search by name or email..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <select name="role">
                <option value="all" <?php if($role_filter == "all") echo "selected"; ?>>All Roles</option>
                <option value="Resident" <?php if($role_filter == "Resident") echo "selected"; ?>>Residents</option>
                <option value="Admin" <?php if($role_filter == "Admin") echo "selected"; ?>>Admins</option>
            </select>

            <button type="submit" class="btn-primary">Search / Filter</button>
            <a href="manage_users.php" class="btn-secondary">Clear</a>
        </form>
    </div>

    <!-- Users table -->
    <table class="manage-users-table">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Requests</th>
                <th>Approved</th>
                <th>Feedback</th>
                <th>Registered On</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>

                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['UserID']; ?></td>

                        <td><?php echo htmlspecialchars($row['FullName']); ?></td>

                        <td><?php echo htmlspecialchars($row['Email']); ?></td>

                        <td><?php echo htmlspecialchars($row['Role']); ?></td>

                        <td><?php echo $row['total_requests']; ?></td>

                        <td><?php echo $row['approved_requests']; ?></td>

                        <td><?php echo $row['total_feedback']; ?></td>

                        <td><?php echo date("M j, Y", strtotime($row['CreatedAt'])); ?></td>

                        <td>
                            <?php if ($row['Role'] === 'Resident'): ?>
                                <a 
                                    href="manage_users.php?delete_user=<?php echo $row['UserID']; ?>" 
                                    class="btn-danger"
                                    onclick="return confirm('Are you sure you want to delete this resident account? This will also delete their requests and feedback.');"
                                >
                                    Delete
                                </a>
                            <?php else: ?>
                                <span class="protected-text">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9" style="text-align: center;">
                        No users found.
                    </td>
                </tr>

            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>