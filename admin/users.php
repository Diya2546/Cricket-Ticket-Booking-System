<?php

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include '../connection.php';

// Get admin details
$admin_id = $_SESSION['admin_id'];
$adminQuery = "SELECT * FROM admins WHERE id = $admin_id";
$adminResult = mysqli_query($link, $adminQuery);
$adminData = mysqli_fetch_assoc($adminResult);

// Handle user delete action
$message = '';
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $userId = intval($_GET['id']);

    // First delete booking_items linked to this user's bookings
    $deleteItems = "DELETE bi FROM booking_items bi 
                    INNER JOIN bookings b ON bi.booking_id = b.id 
                    WHERE b.user_id = $userId";
    mysqli_query($link, $deleteItems);

    // Delete bookings of the user
    $deleteBookings = "DELETE FROM bookings WHERE user_id = $userId";
    mysqli_query($link, $deleteBookings);

    // Finally delete the user
    $deleteUser = "DELETE FROM users WHERE id = $userId";
    if (mysqli_query($link, $deleteUser)) {
        $message = '<div class="alert alert-success">✅ User and related bookings deleted successfully.</div>';
    }
    else {
        $message = '<div class="alert alert-danger">❌ Error deleting user.</div>';
    }
}

// Search and filter functionality
$search = '';
$whereClause = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($link, $_GET['search']);
    $whereClause = "WHERE (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}

// Get total number of users for pagination
$countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
$countResult = mysqli_query($link, $countQuery);
$totalUsers = mysqli_fetch_assoc($countResult)['total'];

// Pagination setup
$perPage = 10;
$totalPages = ceil($totalUsers / $perPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// Get users with booking counts and total spent
$usersQuery = "
    SELECT 
        u.*, 
        COUNT(DISTINCT b.id) as booking_count,
        COALESCE(SUM(b.total_amount), 0) as total_spent
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$usersResult = mysqli_query($link, $usersQuery);
$users = [];

if ($usersResult) {
    while ($user = mysqli_fetch_assoc($usersResult)) {
        $users[] = $user;
    }
}

// Get user statistics
$todayQuery = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
$todayResult = mysqli_query($link, $todayQuery);
$todayUsers = mysqli_fetch_assoc($todayResult)['count'] ?? 0;

$withBookingsQuery = "SELECT COUNT(DISTINCT user_id) as count FROM bookings";
$withBookingsResult = mysqli_query($link, $withBookingsQuery);
$usersWithBookings = mysqli_fetch_assoc($withBookingsResult)['count'] ?? 0;

$activeQuery = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
$activeResult = mysqli_query($link, $activeQuery);
$activeUsers = mysqli_fetch_assoc($activeResult)['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Cricket Admin</title>
    
    <!-- External CSS Files -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/users.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Cricket Admin</h2>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="matches.php"><i class="fas fa-baseball-ball"></i> <span>Manage Matches</span></a>
        <a href="bookings.php"><i class="fas fa-ticket-alt"></i> <span>Bookings</span></a>
        <a href="users.php" class="active"><i class="fas fa-users"></i> <span>Manage Users</span></a>
        <a href="category.php"><i class="fas fa-list"></i> <span>Manage Categories</span></a>
        <a href="venue_categories.php"><i class="fas fa-th-large"></i> <span>Venue Categories</span></a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span></a>
        <a href="feedback.php"><i class="fas fa-star"></i> <span>Feedback</span></a>
        <div style="flex:1"></div>
    </div>

    <div class="main-content">
        <!-- Top Header with Admin Profile -->
        <div class="top-header">
            <div class="header-left">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</h2>
                <!-- <p><?php //echo htmlspecialchars($adminData['role'] ?? 'Administrator'); ?></p> -->
            </div>
            <div class="header-right">
                <div class="admin-avatar" onclick="toggleProfileMenu()">
                    <?php
$name = $_SESSION['admin_username'] ?? 'Admin';
echo strtoupper(substr($name, 0, 2));
?>
                    <div class="profile-dropdown" id="profileMenu">
                        <a href="admin_profile.php">
                            <i class="fas fa-user-circle"></i> Profile
                        </a>
                        <hr>
                        <a href="../admin/logout.php" style="color: #ef4444;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Bar -->
        <!-- <div class="system-status">
            <span class="dot"></span>
            <span>System Live - All Services Operational</span>
        </div> -->

        <!-- Page Header -->
        <div class="page-header">
            <h1>Manage Users</h1>
        </div>

        <!-- Display any messages -->
        <?php if (!empty($message))
    echo $message; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Users</h3>
                    <p><?php echo $totalUsers; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-info">
                    <h3>New Today</h3>
                    <p><?php echo $todayUsers; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>Users With Bookings</h3>
                    <p><?php echo $usersWithBookings; ?></p>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by name, email or phone...">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php
endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-section">
            <div class="table-header">
                <h3>All Users (<?php echo $totalUsers; ?>)</h3>
                <span class="text-muted">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
            </div>

            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>USER</th>
                            <th>CONTACT</th>
                            <th>BOOKINGS</th>
                            <th>JOINED</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                            </div>
                                            <div class="user-details">
                                                <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                                                <span class="user-id">ID: <?php echo $user['id']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                            <span class="user-phone"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="booking-stats">
                                            <span class="booking-count"><?php echo $user['booking_count']; ?></span>
                                            <span class="booking-amount">₹<?php echo number_format($user['total_spent'], 2); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="join-date"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" onclick="viewUserDetails(<?php echo $user['id']; ?>)" 
                                               class="btn-sm" style="background:transparent; border:none; color:var(--text-light);">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <!-- <a href="users.php?action=delete&id=<?php //echo $user['id']; ?>" 
                                               class="btn-sm btn-danger"
                                               onclick="return confirm('Are you sure you want to delete this user and all their bookings?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a> -->
                                        </div>
                                    </td>
                                </tr>
                            <?php
    endforeach; ?>
                        <?php
else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>No users found.</p>
                                    <?php if (!empty($search)): ?>
                                        <p class="text-muted">Try adjusting your search criteria</p>
                                    <?php
    endif; ?>
                                </td>
                            </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php
    endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="page-link <?php echo $i == $currentPage ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php
    endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php
    endif; ?>
                </div>
            <?php
endif; ?>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none;">
                <div class="modal-body p-0" id="userDetailsContent">
                    <!-- Content will be loaded here via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewUserDetails(userId) {
            fetch('user_details.php?id=' + userId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('userDetailsContent').innerHTML = data;
                    var modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load user details');
                });
        }
        function toggleProfileMenu() {
            document.getElementById('profileMenu').classList.toggle('show');
        }

        window.addEventListener('click', function(e) {
            const menu = document.getElementById('profileMenu');
            const avatar = document.querySelector('.admin-avatar');
            if (avatar && !avatar.contains(e.target)) {
                menu.classList.remove('show');
            }
        });

        function openProfileModal() {
            window.location.href = 'admin_profile.php';
        }

        function openSettingsModal() {
            alert('Settings page coming soon!');
        }

        // Auto-hide alerts after 3 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 3000);
    </script>
</body>
</html>