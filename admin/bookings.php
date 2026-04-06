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

// Handle status update actions
$message = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'confirm') {
        $updateQuery = "UPDATE bookings SET payment_status = 'success', booking_status = 'confirmed' WHERE id = $bookingId";
        if (mysqli_query($link, $updateQuery)) {
            $message = '<div class="alert alert-success">✅ Booking confirmed successfully.</div>';
        }
        else {
            $message = '<div class="alert alert-danger">❌ Error confirming booking: ' . mysqli_error($link) . '</div>';
        }
    }
    elseif ($action === 'cancel') {
        $updateQuery = "UPDATE bookings SET booking_status = 'cancelled' WHERE id = $bookingId";
        if (mysqli_query($link, $updateQuery)) {
            $message = '<div class="alert alert-success">✅ Booking cancelled successfully.</div>';
        }
        else {
            $message = '<div class="alert alert-danger">❌ Error cancelling booking: ' . mysqli_error($link) . '</div>';
        }
    }

    // Redirect to avoid resubmission on refresh
    header("Location: bookings.php?message=" . urlencode($message));
    exit();
}

// Check for message in URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Search and filter functionality
$search = '';
$statusFilter = '';
$whereConditions = [];

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search = mysqli_real_escape_string($link, $_GET['search']);
    $whereConditions[] = "(u.email LIKE '%$search%' OR u.phone LIKE '%$search%' OR b.booking_id LIKE '%$search%' OR u.name LIKE '%$search%')";
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $statusFilter = mysqli_real_escape_string($link, $_GET['status']);

    if ($statusFilter === 'pending') {
        $whereConditions[] = "b.payment_status = 'pending' AND b.booking_status != 'cancelled'";
    }
    elseif ($statusFilter === 'confirmed') {
        $whereConditions[] = "b.payment_status = 'success' AND b.booking_status = 'confirmed'";
    }
    elseif ($statusFilter === 'cancelled') {
        $whereConditions[] = "b.booking_status = 'cancelled'";
    }
}

// Build WHERE clause
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Get total number of bookings for pagination
$countQuery = "SELECT COUNT(DISTINCT b.id) as total
              FROM bookings b
              JOIN users u ON b.user_id = u.id
              $whereClause";
$countResult = mysqli_query($link, $countQuery);
$totalBookings = 0;
if ($countResult) {
    $totalBookings = mysqli_fetch_assoc($countResult)['total'] ?? 0;
}

// Pagination setup
$perPage = 10;
$totalPages = max(1, ceil($totalBookings / $perPage));
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// Get bookings with user and match information
$bookingsQuery = "
    SELECT 
        b.*,
        u.name as user_name,
        u.email as user_email,
        u.phone as user_phone,
        m.match_date,
        t1.name as team1_name,
        t2.name as team2_name,
        v.name as venue_name,
        m.match_type,
        COUNT(bi.id) as ticket_count
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN matches m ON b.match_id = m.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    JOIN venues v ON m.venue_id = v.id
    LEFT JOIN booking_items bi ON b.id = bi.booking_id
    $whereClause
    GROUP BY b.id
    ORDER BY b.booking_time DESC
    LIMIT $perPage OFFSET $offset
";

$bookingsResult = mysqli_query($link, $bookingsQuery);
$bookings = [];

if ($bookingsResult) {
    while ($booking = mysqli_fetch_assoc($bookingsResult)) {
        $bookings[] = $booking;
    }
}

// Helper: append condition safely
function appendCondition($baseWhere, $condition)
{
    if (!empty($baseWhere))
        return $baseWhere . " AND " . $condition;
    return "WHERE " . $condition;
}

// Booking statistics
$pendingQuery = "
    SELECT COUNT(*) as count
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    " . appendCondition($statsBaseWhere ?? '', "b.payment_status = 'pending' AND b.booking_status != 'cancelled'") . "
";
$pendingResult = mysqli_query($link, $pendingQuery);
$pendingBookings = ($pendingResult && mysqli_fetch_assoc($pendingResult)) ? (mysqli_fetch_assoc(mysqli_query($link, $pendingQuery))['count'] ?? 0) : 0;

// Confirmed
$confirmedQuery = "
    SELECT COUNT(*) as count
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    " . appendCondition($statsBaseWhere ?? '', "b.payment_status = 'success' AND b.booking_status = 'confirmed'") . "
";
$confirmedResult = mysqli_query($link, $confirmedQuery);
$confirmedRow = $confirmedResult ? mysqli_fetch_assoc($confirmedResult) : null;
$confirmedBookings = $confirmedRow['count'] ?? 0;

// Cancelled
$cancelledQuery = "
    SELECT COUNT(*) as count
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    " . appendCondition($statsBaseWhere ?? '', "b.booking_status = 'cancelled'") . "
";
$cancelledResult = mysqli_query($link, $cancelledQuery);
$cancelledRow = $cancelledResult ? mysqli_fetch_assoc($cancelledResult) : null;
$cancelledBookings = $cancelledRow['count'] ?? 0;

// Revenue
$revenueQuery = "
    SELECT COALESCE(SUM(b.total_amount), 0) as revenue
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    " . appendCondition($statsBaseWhere ?? '', "b.payment_status = 'success' AND b.booking_status = 'confirmed'") . "
";
$revenueResult = mysqli_query($link, $revenueQuery);
$revenueRow = $revenueResult ? mysqli_fetch_assoc($revenueResult) : null;
$totalRevenueRaw = $revenueRow['revenue'] ?? 0;
// Incorporate 18% GST and 2% Convenience Fee to reflect true collected revenue
$totalRevenue = $totalRevenueRaw + round($totalRevenueRaw * 0.02) + round($totalRevenueRaw * 0.18);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - CricTix Admin</title>
    
    <!-- External CSS Files -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/bookings.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Cricket Admin</h2>
            <!-- <form class="search-form" method="get">
                <input type="text" name="search" placeholder="Search matches..." value="<?php //echo htmlspecialchars($search); ?>">
                <i class="fas fa-search"></i>
            </form> -->
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="matches.php"><i class="fas fa-baseball-ball"></i> <span>Matches Management</span></a>
        <a href="bookings.php" class="active"><i class="fas fa-ticket-alt"></i> <span>Bookings</span></a>
        <a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a>
        <a href="category.php"><i class="fas fa-list"></i> <span>Manage Categories</span></a>
        <a href="venue_categories.php"><i class="fas fa-th-large"></i> <span>Venue Categories</span></a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span></a>
        <a href="feedback.php"><i class="fas fa-star"></i> <span>Feedback</span></a>
        <div style="flex:1"></div>
    </div>

    <div class="main-content">
        <!-- Top Header -->
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

        <!-- Page Header -->
        <div class="page-header">
            <h1>Manage Bookings</h1>
            <!-- <a href="matches.php" class="btn-add">
                <i class="fas fa-plus-circle"></i> Add New Match
            </a> -->
        </div>

        <?php if (!empty($message))
    echo $message; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Bookings</h3>
                    <p><?php echo $totalBookings; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Confirmed</h3>
                    <p><?php echo $confirmedBookings; ?></p>
                </div>
            </div>

            <!-- <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>Pending</h3>
                    <p><?php //echo $pendingBookings; ?></p>
                </div>
            </div> -->

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-info">
                    <h3>Cancelled</h3>
                    <p><?php echo $cancelledBookings; ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Revenue</h3>
                    <p>₹<?php echo number_format($totalRevenue, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search by name, email, phone or booking ID...">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="bookings.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php
endif; ?>
            </form>
        </div>

        <!-- Bookings Table -->
        <div class="table-section">
            <div class="table-header">
                <h3>All Bookings</h3>
                <span class="text-muted"><?php echo count($bookings); ?> of <?php echo $totalBookings; ?> bookings</span>
            </div>

            <div class="table-responsive">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Booking Ref</th>
                            <th>Customer</th>
                            <th>Match</th>
                            <th>Date</th>
                            <th>Tickets</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bookings) > 0): ?>
                            <?php foreach ($bookings as $booking):
        $statusClass = '';
        $statusText = '';

        if ($booking['booking_status'] == 'cancelled') {
            $statusClass = 'status-cancelled';
            $statusText = 'Cancelled';
        }
        elseif ($booking['payment_status'] == 'success' && $booking['booking_status'] == 'confirmed') {
            $statusClass = 'status-confirmed';
            $statusText = 'Confirmed';
        }
        elseif ($booking['payment_status'] == 'pending') {
            $statusClass = 'status-pending';
            $statusText = 'Pending';
        }
        else {
            $statusClass = 'status-failed';
            $statusText = 'Failed';
        }
        
        $ticket_subtotal = (float)$booking['total_amount'];
        $convenience_fee = round($ticket_subtotal * 0.02);
        $gst_amount      = round($ticket_subtotal * 0.18);
        $grand_total     = $ticket_subtotal + $convenience_fee + $gst_amount;
?>
                                <tr onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">
                                    <td><span class="booking-ref"><?php echo htmlspecialchars($booking['booking_id']); ?></span></td>
                                    <td>
                                        <div class="customer-info">
                                            <span class="customer-name"><?php echo htmlspecialchars($booking['user_name']); ?></span>
                                            <span class="customer-email"><?php echo htmlspecialchars($booking['user_email']); ?></span>
                                            <span class="customer-phone"><?php echo htmlspecialchars($booking['user_phone']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="match-info">
                                            <span class="match-teams"><?php echo htmlspecialchars($booking['team1_name'] . ' vs ' . $booking['team2_name']); ?></span>
                                            <span class="match-venue"><?php echo htmlspecialchars($booking['venue_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="match-date"><?php echo date('d M Y', strtotime($booking['match_date'])); ?></span>
                                    </td>
                                    <td>
                                        <span class="ticket-count"><?php echo $booking['ticket_count']; ?> tickets</span>
                                    </td>
                                    <td><span class="amount">₹<?php echo number_format($grand_total, 2); ?></span></td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php
    endforeach; ?>
                        <?php
else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No bookings found</p>
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
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link" style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>" 
                       class="page-link <?php echo $i == $currentPage ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>" class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link" style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: var(--card-bg); color: var(--text-light);">
                <div class="modal-body" id="bookingDetailsContent" style="padding: 0;">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        function viewBookingDetails(bookingId) {
            // Load booking details via AJAX
            fetch('booking_details.php?id=' + bookingId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('bookingDetailsContent').innerHTML = data;
                    var modal = new bootstrap.Modal(document.getElementById('bookingDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load booking details');
                });
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