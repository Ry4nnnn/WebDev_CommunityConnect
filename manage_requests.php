<?php
session_start();
require 'db_connect.php';

// 1. SECURITY CHECK: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$message = "";

// 2. HANDLE STATUS UPDATES
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id']) && isset($_POST['new_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['new_status'];
    
    // Ensure only valid statuses are submitted
    if (in_array($new_status, ['Approved', 'Rejected'])) {
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

// 3. FETCH ALL REQUESTS (Using SQL JOIN to get names and titles)
// We join 3 tables: ParticipationRequests, Users, and CommunityServices
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'Pending';

$sql = "SELECT pr.RequestID, u.FullName, u.Email, cs.Title, cs.EventDate, pr.Status, pr.RequestDate 
        FROM ParticipationRequests pr
        JOIN Users u ON pr.UserID = u.UserID
        JOIN CommunityServices cs ON pr.ServiceID = cs.ServiceID ";

if ($filter !== 'All') {
    $sql .= " WHERE pr.Status = '$filter' ";
}

$sql .= " ORDER BY pr.RequestDate ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Requests - Admin</title>
    <style>
        /* Reusing the clean Admin CSS */
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; }
        .navbar { background-color: #343a40; padding: 15px 20px; color: white; display: flex; justify-content: space-between; }
        .navbar a { color: white; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .navbar a:hover { text-decoration: underline; }
        .container { max-width: 1100px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        
        /* Filter Tabs */
        .tabs { margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .tabs a { text-decoration: none; padding: 10px 20px; color: #666; font-weight: bold; }
        .tabs a.active { color: #007BFF; border-bottom: 3px solid #007BFF; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 14px; font-weight: bold; color: white; text-align: center; display: inline-block;}
        .badge.Pending { background-color: #ffc107; color: black; }
        .badge.Approved { background-color: #28a745; }
        .badge.Rejected { background-color: #dc3545; }
        
        .btn-approve { background-color: #28a745; color: white; padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-reject { background-color: #dc3545; color: white; padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>CommunityConnect Admin</strong></div>
    <div>
        <a href="admin.php">Manage Services</a>
        <a href="manage_requests.php" style="text-decoration: underline;">Manage Requests</a>
        <a href="moderate_feedback.php">Moderate Feedback</a>
        <a href="logout.php" style="background: #dc3545; padding: 5px 10px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <h2>Manage Participation Requests</h2>
    <?php echo $message; ?>

    <div class="tabs">
        <a href="manage_requests.php?filter=Pending" class="<?php echo $filter=='Pending'?'active':''; ?>">Pending</a>
        <a href="manage_requests.php?filter=Approved" class="<?php echo $filter=='Approved'?'active':''; ?>">Approved</a>
        <a href="manage_requests.php?filter=Rejected" class="<?php echo $filter=='Rejected'?'active':''; ?>">Rejected</a>
        <a href="manage_requests.php?filter=All" class="<?php echo $filter=='All'?'active':''; ?>">All Requests</a>
    </div>

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
                    echo "<td><span class='badge " . $row['Status'] . "'>" . $row['Status'] . "</span></td>";
                    
                    // Action Buttons (Only show for pending requests)
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