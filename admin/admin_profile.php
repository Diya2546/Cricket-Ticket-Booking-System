<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

include '../connection.php';

$admin_id = $_SESSION['admin_id'];
$message = '';

// Fetch admin details
$adminQuery = "SELECT * FROM admins WHERE id = $admin_id";
$adminResult = mysqli_query($link, $adminQuery);
$adminData = mysqli_fetch_assoc($adminResult);

$adminUsername = $adminData['username'];
$adminRole = $adminData['role'] ?? 'Super Admin';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $username = mysqli_real_escape_string($link, $_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $updateQuery = "UPDATE admins SET username = '$username'";
    $canUpdate = true;

    // Verify and update password if provided
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Passwords do not match.</div>';
            $canUpdate = false;
        }
        elseif (strlen($new_password) < 6) {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Password must be at least 6 characters.</div>';
            $canUpdate = false;
        }
        else {
            // Verify current password
            if (!password_verify($current_password, $adminData['password'])) {
                $message = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Current password is incorrect.</div>';
                $canUpdate = false;
            }
            else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $updateQuery .= ", password = '$hashed_password'";
            }
        }
    }

    if ($canUpdate) {
        $updateQuery .= " WHERE id = $admin_id";
        if (mysqli_query($link, $updateQuery)) {
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile updated successfully!</div>';
            // Refresh admin data
            $adminResult = mysqli_query($link, $adminQuery);
            $adminData = mysqli_fetch_assoc($adminResult);
            // Update session
            $_SESSION['admin_username'] = $adminData['username'];
            $adminUsername = $adminData['username'];
        }
        else {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Error updating profile: ' . mysqli_error($link) . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Cricket Ticket Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_profile.css?v=<?php echo time(); ?>">
</head>
<body>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Cric Tix</h2>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="matches.php"><i class="fas fa-baseball-ball"></i> <span>Matches Management</span></a>
        <a href="bookings.php"><i class="fas fa-ticket-alt"></i> <span>Bookings</span></a>
        <a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a>
        <a href="category.php"><i class="fas fa-list"></i> <span>Manage Categories</span></a>
        <a href="venue_categories.php"><i class="fas fa-th-large"></i> <span>Venue Categories</span></a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span></a>
         <a href="feedback.php">
        <i class="fas fa-star"></i> <span>Feedback</span>
    </a>
        <div style="flex:1"></div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-left">
                <h2>Admin Profile</h2>
            </div>
            <div class="header-right">
                <div class="admin-avatar" onclick="toggleProfileMenu()">
                    <?php echo strtoupper(substr($adminUsername, 0, 2)); ?>
                    <div class="profile-dropdown" id="profileMenu">
                        <a href="admin_profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                        <hr>
                        <a href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-container">
            <?php if (!empty($message))
    echo $message; ?>

            <div class="profile-card">
                <div class="profile-banner">
                    <div class="avatar-large">
                        <?php echo strtoupper(substr($adminUsername, 0, 2)); ?>
                    </div>
                    <div class="user-meta">
                        <h2><?php echo htmlspecialchars($adminUsername); ?></h2>
                        <!-- <span class="role"><?php //echo htmlspecialchars($adminRole); ?></span> -->
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($adminUsername); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address (Cannot be changed)</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($adminData['email']); ?>" disabled>
                        </div>

                        <div class="section-divider">Change Password</div>

                        <div class="form-group full-width">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="Required only if changing password">
                        </div>

                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters">
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn-update">
                        <i class="fas fa-save"></i> Save Profile Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleProfileMenu() {
            document.getElementById('profileMenu').classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.matches('.admin-avatar')) {
                var dropdowns = document.getElementsByClassName("profile-dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Auto-hide alert messages after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 3000);
            });
        });
    </script>
</body>
</html>
