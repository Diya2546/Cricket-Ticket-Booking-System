<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

include '../connection.php';

$admin_id = (int) $_SESSION['admin_id'];

/* =========================
   GET ADMIN DETAILS
========================= */
$adminQuery = "SELECT * FROM admins WHERE id = ?";
$stmt = mysqli_prepare($link, $adminQuery);
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
$adminResult = mysqli_stmt_get_result($stmt);
$adminData = mysqli_fetch_assoc($adminResult);

/* =========================
   AUTO UPDATE MATCH STATUS
========================= */
$statusUpdateQuery = "
    UPDATE matches
    SET status = CASE
        WHEN status = 'cancelled' THEN 'cancelled'
        WHEN match_date > CURDATE() THEN 'upcoming'
        WHEN match_date = CURDATE() AND match_time > CURTIME() THEN 'upcoming'
        WHEN match_date = CURDATE() AND match_time <= CURTIME() THEN 'live'
        WHEN match_date < CURDATE() THEN 'completed'
        ELSE status
    END
";
mysqli_query($link, $statusUpdateQuery);

/* =========================
   DYNAMIC STATS
========================= */

// Total Users
$userQuery = "SELECT COUNT(*) AS total FROM users WHERE status = 'active'";
$userResult = mysqli_query($link, $userQuery);
$totalUsers = (int) (mysqli_fetch_assoc($userResult)['total'] ?? 0);

// Total Bookings
$bookingQuery = "SELECT COUNT(*) AS total FROM bookings WHERE booking_status IN ('confirmed','pending')";
$bookingResult = mysqli_query($link, $bookingQuery);
$totalBookings = (int) (mysqli_fetch_assoc($bookingResult)['total'] ?? 0);

// Upcoming Matches
$matchQuery = "SELECT COUNT(*) AS total FROM matches WHERE status = 'upcoming'";
$matchResult = mysqli_query($link, $matchQuery);
$upcomingMatches = (int) (mysqli_fetch_assoc($matchResult)['total'] ?? 0);

// Total Revenue (all confirmed + paid bookings)
$totalRevenueQuery = "
    SELECT COALESCE(SUM(
        b.total_amount
        + ROUND(b.total_amount * 0.02)
        + ROUND(b.total_amount * 0.18)
    ), 0) AS total
    FROM bookings b
    WHERE b.booking_status = 'confirmed'
      AND b.payment_status = 'success'
";
$totalRevenueResult = mysqli_query($link, $totalRevenueQuery);
$totalRevenue = (float) (mysqli_fetch_assoc($totalRevenueResult)['total'] ?? 0);

// Completed Match Revenue
$completedRevenueQuery = "
    SELECT COALESCE(SUM(
        b.total_amount
        + ROUND(b.total_amount * 0.02)
        + ROUND(b.total_amount * 0.18)
    ), 0) AS total
    FROM bookings b
    INNER JOIN matches m ON b.match_id = m.id
    WHERE b.booking_status = 'confirmed'
      AND b.payment_status = 'success'
      AND m.status = 'completed'
";
$completedRevenueResult = mysqli_query($link, $completedRevenueQuery);
$completedRevenue = (float) (mysqli_fetch_assoc($completedRevenueResult)['total'] ?? 0);

// Upcoming Match Revenue
$upcomingRevenueQuery = "
    SELECT COALESCE(SUM(
        b.total_amount
        + ROUND(b.total_amount * 0.02)
        + ROUND(b.total_amount * 0.18)
    ), 0) AS total
    FROM bookings b
    INNER JOIN matches m ON b.match_id = m.id
    WHERE b.booking_status = 'confirmed'
      AND b.payment_status = 'success'
      AND m.status = 'upcoming'
";
$upcomingRevenueResult = mysqli_query($link, $upcomingRevenueQuery);
$upcomingRevenue = (float) (mysqli_fetch_assoc($upcomingRevenueResult)['total'] ?? 0);

// Live Match Revenue
// $liveRevenueQuery = "
//     SELECT COALESCE(SUM(
//         b.total_amount
//         + ROUND(b.total_amount * 0.02)
//         + ROUND(b.total_amount * 0.18)
//     ), 0) AS total
//     FROM bookings b
//     INNER JOIN matches m ON b.match_id = m.id
//     WHERE b.booking_status = 'confirmed'
//       AND b.payment_status = 'success'
//       AND m.status = 'live'
// ";
// $liveRevenueResult = mysqli_query($link, $liveRevenueQuery);
// $liveRevenue = (float) (mysqli_fetch_assoc($liveRevenueResult)['total'] ?? 0);

/* =========================
   RECENT BOOKINGS
========================= */
$recentOrderColumn = 'b.booking_time';

$recentQuery = "
    SELECT 
        b.id,
        b.booking_id,
        b.total_amount,
        b.booking_status,
        b.payment_status,
        u.name AS user_name,
        t1.name AS team1,
        t2.name AS team2,
        m.match_date,
        m.match_time,
        bi.category_id,
        bi.quantity,
        sc.name AS category_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN matches m ON b.match_id = m.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    LEFT JOIN booking_items bi ON b.id = bi.booking_id
    LEFT JOIN seat_categories sc ON bi.category_id = sc.id
    ORDER BY {$recentOrderColumn} DESC
    LIMIT 20
";
$recentResult = mysqli_query($link, $recentQuery);

$recentBookings = [];
while ($row = mysqli_fetch_assoc($recentResult)) {
    $uniqueBookingKey = $row['booking_id'];

    if (!isset($recentBookings[$uniqueBookingKey])) {
        $qty = !empty($row['quantity']) ? (int) $row['quantity'] : 1;
        $categoryName = !empty($row['category_name']) ? $row['category_name'] : 'N/A';
        $row['category_display'] = $categoryName . ' (' . $qty . ')';
        $recentBookings[$uniqueBookingKey] = $row;
    }
}
$recentBookings = array_slice($recentBookings, 0, 5);

/* =========================
   REVENUE CHART - LAST 7 DAYS
========================= */
$chartQuery = "
    SELECT 
        DATE(booking_time) AS chart_date,
        COALESCE(SUM(
            total_amount
            + ROUND(total_amount * 0.02)
            + ROUND(total_amount * 0.18)
        ), 0) AS daily_revenue
    FROM bookings
    WHERE booking_status = 'confirmed'
      AND payment_status = 'success'
      AND DATE(booking_time) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
    GROUP BY DATE(booking_time)
    ORDER BY chart_date ASC
";
$chartResult = mysqli_query($link, $chartQuery);

$chartData = [];
while ($row = mysqli_fetch_assoc($chartResult)) {
    $chartData[$row['chart_date']] = (float) $row['daily_revenue'];
}

$chartDates = [];
$chartRevenue = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartDates[] = date('M d', strtotime($date));
    $chartRevenue[] = isset($chartData[$date]) ? $chartData[$date] : 0;
}

/* =========================
   PROFILE UPDATE
========================= */
$updateMessage = '';

if (isset($_POST['update_profile'])) {
    $new_email = mysqli_real_escape_string($link, trim($_POST['email']));
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (!empty($new_password) && !password_verify($current_password, $adminData['password'])) {
        $updateMessage = '<div class="alert alert-danger">Current password incorrect.</div>';
    } else {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = mysqli_prepare($link, "UPDATE admins SET email = ?, password = ? WHERE id = ?");
            mysqli_stmt_bind_param($updateStmt, 'ssi', $new_email, $hashed_password, $admin_id);
        } else {
            $updateStmt = mysqli_prepare($link, "UPDATE admins SET email = ? WHERE id = ?");
            mysqli_stmt_bind_param($updateStmt, 'si', $new_email, $admin_id);
        }

        if (mysqli_stmt_execute($updateStmt)) {
            $updateMessage = '<div class="alert alert-success">Profile updated.</div>';

            $stmt = mysqli_prepare($link, $adminQuery);
            mysqli_stmt_bind_param($stmt, 'i', $admin_id);
            mysqli_stmt_execute($stmt);
            $adminResult = mysqli_stmt_get_result($stmt);
            $adminData = mysqli_fetch_assoc($adminResult);

            $_SESSION['admin_email'] = $adminData['email'] ?? '';
        } else {
            $updateMessage = '<div class="alert alert-danger">Error: ' . mysqli_error($link) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket · Admin Dashboard</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>

<body>
    <div class="sidebar">
        <h2>Cricket Admin</h2>

        <a href="dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="matches.php">
            <i class="fas fa-baseball-ball"></i> <span>Matches Management</span>
        </a>
        <a href="bookings.php">
            <i class="fas fa-ticket-alt"></i> <span>Bookings</span>
        </a>
        <a href="users.php">
            <i class="fas fa-users"></i> <span>Users</span>
        </a>
        <a href="category.php">
            <i class="fas fa-list"></i> <span>Manage Categories</span>
        </a>
        <a href="venue_categories.php">
            <i class="fas fa-th-large"></i> <span>Venue Categories</span>
        </a>
        <a href="reports.php">
            <i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span>
        </a>
        <a href="feedback.php">
            <i class="fas fa-star"></i> <span>Feedback</span>
        </a>

        <div style="flex:1"></div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="header-left">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</h2>
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

        <div class="header-bar">
            <h1>Dashboard Overview</h1>
        </div>

        <?php if ($updateMessage): ?>
            <?php echo $updateMessage; ?>
        <?php endif; ?>

        <div class="metrics-grid">
            <div class="metric-card">
                <span class="icon"><i class="fas fa-user"></i></span>
                <span class="label">Total Users</span>
                <span class="value value-small"><?php echo number_format($totalUsers); ?></span>
            </div>

            <div class="metric-card">
                <span class="icon"><i class="fas fa-ticket-alt"></i></span>
                <span class="label">Total Bookings</span>
                <span class="value value-small"><?php echo number_format($totalBookings); ?></span>
            </div>

            <div class="metric-card">
                <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                <span class="label">Upcoming Matches</span>
                <span class="value value-small"><?php echo number_format($upcomingMatches); ?></span>
            </div>

            <div class="metric-card">
                <span class="icon"><i class="fas fa-rupee-sign"></i></span>
                <span class="label">Total Revenue</span>
                <span class="value value-small">₹<?php echo number_format($totalRevenue, 2); ?></span>
            </div>

            <div class="metric-card">
                <span class="icon"><i class="fas fa-flag-checkered"></i></span>
                <span class="label">Completed Revenue</span>
                <span class="value value-small">₹<?php echo number_format($completedRevenue, 2); ?></span>
            </div>

            <div class="metric-card">
                <span class="icon"><i class="fas fa-hourglass-half"></i></span>
                <span class="label">Upcoming Revenue</span>
                <span class="value value-small">₹<?php echo number_format($upcomingRevenue, 2); ?></span>
            </div>

            <!-- <div class="metric-card">
                <span class="icon"><i class="fas fa-broadcast-tower"></i></span>
                <span class="label">Live Revenue</span>
                <span class="value value-small">₹<?php //echo number_format($liveRevenue, 2); ?></span>
            </div> -->
        </div>

        <div class="recent-bookings">
            <div class="recent-bookings-header">
                <h3>
                    <i class="fas fa-clock"></i>
                    Recent Bookings
                </h3>
                <button class="view-all-btn" onclick="window.location.href='bookings.php'">
                    View All
                </button>
            </div>

            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User</th>
                        <th>Match</th>
                        <th>Date</th>
                        <th>Category & Seats</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentBookings)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:2rem;">
                                No recent bookings found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentBookings as $b):
                            $statusClass = $b['booking_status'] == 'confirmed' ? 'status-success' :
                                ($b['booking_status'] == 'pending' ? 'status-pending' : 'status-failed');

                            $matchLabel = $b['team1'] . ' vs ' . $b['team2'];
                            $matchDate = date('d M Y', strtotime($b['match_date']));

                            $ticketSubtotal = (float) $b['total_amount'];
                            $convenienceFee = round($ticketSubtotal * 0.02);
                            $gstAmount = round($ticketSubtotal * 0.18);
                            $grandTotal = $ticketSubtotal + $convenienceFee + $gstAmount;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($b['booking_id'], 0, 12)); ?></td>
                                <td><?php echo htmlspecialchars($b['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($matchLabel); ?></td>
                                <td><?php echo htmlspecialchars($matchDate); ?></td>
                                <td><?php echo htmlspecialchars($b['category_display']); ?></td>
                                <td>₹<?php echo number_format($grandTotal, 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($b['booking_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="chart-container">
            <div class="chart-title">
                <i class="fas fa-chart-line"></i>
                Revenue Last 7 Days
            </div>
            <div class="chart-wrapper" style="position:relative; height:280px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        function toggleProfileMenu() {
            document.getElementById('profileMenu').classList.toggle('show');
        }

        window.addEventListener('click', function (e) {
            const menu = document.getElementById('profileMenu');
            const avatar = document.querySelector('.admin-avatar');
            if (avatar && !avatar.contains(e.target)) {
                menu.classList.remove('show');
            }
        });

        const chartLabels = <?php echo json_encode($chartDates); ?>;
        const chartValues = <?php echo json_encode($chartRevenue); ?>;

        const canvas = document.getElementById('revenueChart');
        if (canvas) {
            const ctx = canvas.getContext('2d');

            const areaGradient = ctx.createLinearGradient(0, 0, 0, 280);
            areaGradient.addColorStop(0, 'rgba(56, 182, 255, 0.35)');
            areaGradient.addColorStop(1, 'rgba(56, 182, 255, 0.02)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: chartValues,
                        borderColor: '#38b6ff',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#38b6ff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#38b6ff',
                        pointHoverBorderWidth: 3,
                        fill: true,
                        backgroundColor: areaGradient,
                        tension: 0.45
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleColor: '#94a3b8',
                            bodyColor: '#fff',
                            borderColor: 'rgba(56, 182, 255, 0.4)',
                            borderWidth: 1,
                            padding: 14,
                            cornerRadius: 12,
                            displayColors: false,
                            callbacks: {
                                title: function (items) {
                                    return items[0].label;
                                },
                                label: function (item) {
                                    return '₹ ' + Number(item.raw).toLocaleString('en-IN');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#64748b',
                                font: { size: 12 }
                            },
                            border: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255,255,255,0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: { size: 11 },
                                callback: function (v) {
                                    return '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v);
                                }
                            },
                            border: { display: false }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>