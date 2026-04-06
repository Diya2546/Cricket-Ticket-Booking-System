<?php
session_start();
include '../connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$period = isset($_GET['period']) ? $_GET['period'] : '30';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Fetch admin data for header
$admin_id = (int) $_SESSION['admin_id'];
$adminQuery = mysqli_query($link, "SELECT * FROM admins WHERE id = '$admin_id'");
$adminData = mysqli_fetch_assoc($adminQuery);
$adminRole = $adminData['role'] ?? 'Super Admin';
$adminUsername = $adminData['username'] ?? 'Admin';

// Set date range based on period
switch ($period) {
    case '7':
        $date_from = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30':
        $date_from = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90':
        $date_from = date('Y-m-d', strtotime('-90 days'));
        break;
    case '180':
        $date_from = date('Y-m-d', strtotime('-180 days'));
        break;
    case '365':
        $date_from = date('Y-m-d', strtotime('-365 days'));
        break;
}

// Make date_to inclusive till end of day
$date_from_datetime = $date_from . ' 00:00:00';
$date_to_datetime   = $date_to . ' 23:59:59';

// Previous period calculation
$current_range_days = max(1, (int) round((strtotime($date_to) - strtotime($date_from)) / 86400) + 1);
$previous_date_to = date('Y-m-d', strtotime($date_from . ' -1 day'));
$previous_date_from = date('Y-m-d', strtotime($previous_date_to . ' -' . ($current_range_days - 1) . ' days'));

$previous_date_from_datetime = $previous_date_from . ' 00:00:00';
$previous_date_to_datetime   = $previous_date_to . ' 23:59:59';

// Fetch summary statistics
$summaryQuery = "
    SELECT 
        COALESCE(SUM(total_amount * 1.20), 0) as total_revenue,
        COUNT(*) as total_bookings,
        COUNT(DISTINCT user_id) as unique_customers,
        COALESCE(AVG(total_amount * 1.20), 0) as avg_order_value
    FROM bookings 
    WHERE booking_time BETWEEN '$date_from_datetime' AND '$date_to_datetime'
    AND booking_status != 'cancelled'
";

$summaryResult = mysqli_query($link, $summaryQuery);
$summary = mysqli_fetch_assoc($summaryResult);

// Calculate percentage changes compared to previous period
$previousQuery = "
    SELECT 
        COALESCE(SUM(total_amount * 1.20), 0) as prev_revenue,
        COUNT(*) as prev_bookings,
        COUNT(DISTINCT user_id) as prev_customers,
        COALESCE(AVG(total_amount * 1.20), 0) as prev_avg_order
    FROM bookings 
    WHERE booking_time BETWEEN '$previous_date_from_datetime' AND '$previous_date_to_datetime'
    AND booking_status != 'cancelled'
";

$previousResult = mysqli_query($link, $previousQuery);
$previous = mysqli_fetch_assoc($previousResult);

// Calculate percentages
$revenue_change = ($previous['prev_revenue'] > 0)
    ? round((($summary['total_revenue'] - $previous['prev_revenue']) / $previous['prev_revenue']) * 100, 1)
    : 0;

$bookings_change = ($previous['prev_bookings'] > 0)
    ? round((($summary['total_bookings'] - $previous['prev_bookings']) / $previous['prev_bookings']) * 100, 1)
    : 0;

$customers_change = ($previous['prev_customers'] > 0)
    ? round((($summary['unique_customers'] - $previous['prev_customers']) / $previous['prev_customers']) * 100, 1)
    : 0;

$avg_change = ($previous['prev_avg_order'] > 0)
    ? round((($summary['avg_order_value'] - $previous['prev_avg_order']) / $previous['prev_avg_order']) * 100, 1)
    : 0;

// Fetch revenue trend data (last 5 days)
$revenueTrendQuery = "
    SELECT 
        DATE(booking_time) as day,
        DATE_FORMAT(booking_time, '%d %b') as day_label,
        COALESCE(SUM(total_amount * 1.20), 0) as revenue
    FROM bookings 
    WHERE booking_time >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
    AND booking_status != 'cancelled'
    GROUP BY DATE(booking_time), DATE_FORMAT(booking_time, '%d %b')
    ORDER BY day ASC
";

$revenueTrendResult = mysqli_query($link, $revenueTrendQuery);
$revenueLabels = [];
$revenueData   = [];
$rawDays       = [];

while ($row = mysqli_fetch_assoc($revenueTrendResult)) {
    $rawDays[]       = $row['day'];
    $revenueLabels[] = $row['day_label'];
    $revenueData[]   = (float) $row['revenue'];
}

// Fill missing days with 0 so we always show 5 bars
$fullLabels = [];
$fullData   = [];
for ($i = 4; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $idx = array_search($d, $rawDays);
    $fullLabels[] = date('d M', strtotime($d));
    $fullData[]   = ($idx !== false) ? $revenueData[$idx] : 0;
}
$revenueLabels = $fullLabels;
$revenueData   = $fullData;

// Fetch booking volume by match type
$bookingVolumeQuery = "
    SELECT 
        m.match_type,
        COUNT(b.id) as booking_count
    FROM bookings b
    JOIN matches m ON b.match_id = m.id
    WHERE b.booking_time BETWEEN '$date_from_datetime' AND '$date_to_datetime'
    AND b.booking_status != 'cancelled'
    GROUP BY m.match_type
    ORDER BY booking_count DESC
";

$bookingVolumeResult = mysqli_query($link, $bookingVolumeQuery);
$volumeLabels = [];
$volumeData = [];

while ($row = mysqli_fetch_assoc($bookingVolumeResult)) {
    $volumeLabels[] = $row['match_type'];
    $volumeData[] = (int) $row['booking_count'];
}

// Fetch top matches using BOOKING COUNT, not seat count
$topMatchesQuery = "
    SELECT 
        CONCAT(UCASE(t1.name), ' vs ', UCASE(t2.name)) as match_name,
        m.match_date,
        v.name as venue_name,
        COUNT(b.id) as total_bookings,
        COUNT(DISTINCT b.user_id) as unique_users,
        SUM(b.total_amount * 1.20) as total_revenue
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    JOIN venues v ON m.venue_id = v.id
    JOIN bookings b ON m.id = b.match_id
    WHERE b.booking_time BETWEEN '$date_from_datetime' AND '$date_to_datetime'
    AND b.booking_status != 'cancelled'
    GROUP BY m.id, t1.name, t2.name, m.match_date, v.name
    ORDER BY total_bookings DESC, total_revenue DESC
    LIMIT 5
";

$topMatchesResult = mysqli_query($link, $topMatchesQuery);

// Fetch revenue by category
$categoryRevenueQuery = "
    SELECT 
        sc.name as category_name,
        COALESCE(MAX(vc.color_code), '#2a5298') as color_code,
        SUM(bi.quantity) as tickets_sold,
        SUM(bi.total_price * 1.20) as revenue
    FROM booking_items bi
    JOIN seat_categories sc ON bi.category_id = sc.id
    JOIN bookings b ON bi.booking_id = b.id
    JOIN matches m ON b.match_id = m.id
    LEFT JOIN (
        SELECT category_id, venue_id, MAX(color_code) as color_code 
        FROM venue_category 
        GROUP BY category_id, venue_id
    ) vc ON vc.category_id = sc.id AND vc.venue_id = m.venue_id
    WHERE b.booking_time BETWEEN '$date_from_datetime' AND '$date_to_datetime'
    AND b.booking_status != 'cancelled'
    GROUP BY sc.name
    ORDER BY revenue DESC
";

$categoryRevenueResult = mysqli_query($link, $categoryRevenueQuery);

// Fetch booking status distribution
$statusQuery = "
    SELECT 
        booking_status,
        COUNT(*) as count
    FROM bookings 
    WHERE booking_time BETWEEN '$date_from_datetime' AND '$date_to_datetime'
    GROUP BY booking_status
";
$statusResult = mysqli_query($link, $statusQuery);
$statusLabels = [];
$statusData = [];

while ($status = mysqli_fetch_assoc($statusResult)) {
    $statusLabels[] = ucfirst($status['booking_status']);
    $statusData[] = (int) $status['count'];
}

// Prepare category chart arrays before export / HTML
mysqli_data_seek($categoryRevenueResult, 0);
$categoryLabels = [];
$categoryData = [];
$categoryColors = [];
$categoryTickets = [];
$totalCategoryRevenue = 0;

while ($cat = mysqli_fetch_assoc($categoryRevenueResult)) {
    $categoryLabels[] = $cat['category_name'];
    $revenue = (float) $cat['revenue'];
    $categoryData[] = $revenue;
    $categoryColors[] = $cat['color_code'] ?: '#2a5298';
    $categoryTickets[] = (int) $cat['tickets_sold'];
    $totalCategoryRevenue += $revenue;
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reports_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['Report Type', 'Value', 'Period']);
    fputcsv($output, ['Total Revenue', $summary['total_revenue'], "$date_from to $date_to"]);
    fputcsv($output, ['Total Bookings', $summary['total_bookings'], "$date_from to $date_to"]);
    fputcsv($output, ['Unique Customers', $summary['unique_customers'], "$date_from to $date_to"]);
    fputcsv($output, ['Avg Order Value', $summary['avg_order_value'], "$date_from to $date_to"]);

    fputcsv($output, []);
    fputcsv($output, ['Match', 'Venue', 'Date', 'Bookings', 'Unique Users', 'Revenue']);

    mysqli_data_seek($topMatchesResult, 0);
    while ($row = mysqli_fetch_assoc($topMatchesResult)) {
        fputcsv($output, [
            $row['match_name'],
            $row['venue_name'],
            date('d M Y', strtotime($row['match_date'])),
            $row['total_bookings'],
            $row['unique_users'],
            $row['total_revenue']
        ]);
    }

    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analysis - Cricket Ticket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/reports.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Cricket Admin</h2>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="matches.php"><i class="fas fa-baseball-ball"></i> <span>Matches Management</span></a>
        <a href="bookings.php"><i class="fas fa-ticket-alt"></i> <span>Bookings</span></a>
        <a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a>
        <a href="category.php"><i class="fas fa-list"></i> <span>Manage Categories</span></a>
        <a href="venue_categories.php"><i class="fas fa-th-large"></i> <span>Venue Categories</span></a>
        <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span></a>
        <a href="feedback.php"><i class="fas fa-star"></i> <span>Feedback</span></a>
        <div style="flex:1"></div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-left">
                <h2>Welcome, <?php echo htmlspecialchars($adminUsername); ?>!</h2>
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

        <div class="page-header">
            <div>
                <h1>Report & Analysis</h1>
                <p>Monitor bookings, customers and revenue for cricket ticket sales</p>
            </div>
        </div>

        <br><br>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(25,118,210,0.15); color: #42a5f5;">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <p>₹<?php echo number_format($summary['total_revenue']); ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.15); color: #10b981;">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Bookings</h3>
                    <p><?php echo number_format($summary['total_bookings']); ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245,124,0,0.15); color: #fb923c;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Customers</h3>
                    <p><?php echo number_format($summary['unique_customers']); ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(194,24,91,0.15); color: #f472b6;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h3>Avg Order Value</h3>
                    <p>₹<?php echo number_format($summary['avg_order_value']); ?></p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="?period=<?php echo urlencode($period); ?>&export=csv" class="btn btn-outline">
                <i class="fas fa-download"></i>
                Export Report
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i>
                Print Report
            </button>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <span class="chart-title">Revenue Trend (Last 5 Days)</span>
                    <i class="fas fa-info-circle" style="color: #6c757d;"></i>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <span class="chart-title">Booking Volume by Match Type</span>
                    <i class="fas fa-info-circle" style="color: #6c757d;"></i>
                </div>
                <div class="chart-container">
                    <canvas id="volumeChart"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card" id="category-chart-card">
                <div class="chart-header">
                    <span class="chart-title">Revenue by Seat Category</span>
                    <i class="fas fa-info-circle" style="color: #6c757d;"></i>
                </div>
                <!-- Flex row: chart left, legend right -->
                <div style="display:flex; align-items:center; justify-content:center; gap:32px; padding: 16px 8px 8px;">
                    <!-- Donut chart -->
                    <div style="position:relative; width:200px; height:200px; flex-shrink:0;">
                        <canvas id="categoryChart"></canvas>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; pointer-events:none;">
                            <div id="cat-center-label" style="font-size:0.65rem; color:#64748b; letter-spacing:0.08em; text-transform:uppercase;">In Total</div>
                            <div id="cat-center-value" style="font-size:1.15rem; font-weight:700; color:#fff; margin-top:2px;">
                                ₹<?php echo number_format($totalCategoryRevenue); ?>
                            </div>
                        </div>
                    </div>
                    <!-- Legend list -->
                    <ul id="category-legend" style="list-style:none; margin:0; padding:0;">
                        <?php
                            foreach ($categoryLabels as $i => $label):
                                $rev   = $categoryData[$i] ?? 0;
                                $color = $categoryColors[$i] ?? '#2a5298';
                                // Improve neon yellow
                                if ($color === '#eeff00' || $color === '#deed07') $color = '#f5c518';
                                $pct   = $totalCategoryRevenue > 0 ? round(($rev / $totalCategoryRevenue) * 100, 1) : 0;
                        ?>
                        <li style="display:flex; align-items:center; padding:10px 0;">
                            <span style="width:11px; height:11px; border-radius:50%; background:<?php echo htmlspecialchars($color); ?>; flex-shrink:0;"></span>
                            <span style="font-size:0.875rem; color:#cbd5e1; margin-left:10px;"><?php echo htmlspecialchars($label); ?></span>
                            <span style="font-size:0.875rem; font-weight:600; color:#f1f5f9; margin-left:12px;">₹<?php echo number_format($rev); ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($categoryLabels)): ?>
                        <li style="text-align:center; color:#64748b; padding:20px 0;">No data for selected period</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <span class="chart-title">Booking Status Distribution</span>
                    <i class="fas fa-info-circle" style="color: #6c757d;"></i>
                </div>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Selling Matches Table -->
        <div class="table-card">
            <div class="table-header">
                <span class="table-title">Top Selling Matches</span>
                <a href="matches.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>Venue</th>
                        <th>Date</th>
                        <th>Bookings</th>
                        <th>Users</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($topMatchesResult, 0); ?>
                    <?php while ($match = mysqli_fetch_assoc($topMatchesResult)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($match['match_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($match['venue_name']); ?></td>
                            <td><?php echo date('d M Y', strtotime($match['match_date'])); ?></td>
                            <td><strong><?php echo number_format($match['total_bookings']); ?></strong></td>
                            <td><?php echo number_format($match['unique_users']); ?></td>
                            <td>₹<?php echo number_format($match['total_revenue']); ?></td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if (mysqli_num_rows($topMatchesResult) == 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: #6c757d;">
                                No booking data available for this period
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Revenue Trend Chart — 5-day teal area
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revAreaGrad = revenueCtx.createLinearGradient(0, 0, 0, 300);
        revAreaGrad.addColorStop(0, 'rgba(32, 178, 150, 0.22)');
        revAreaGrad.addColorStop(1, 'rgba(32, 178, 150, 0.0)');

        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenueLabels); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($revenueData); ?>,
                    borderColor: '#2ba99a',
                    backgroundColor: revAreaGrad,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#2ba99a',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2,
                    tension: 0.42,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#94a3b8',
                        bodyColor: '#f1f5f9',
                        borderColor: 'rgba(56, 182, 255, 0.4)',
                        borderWidth: 1,
                        padding: 14,
                        cornerRadius: 12,
                        displayColors: false,
                        callbacks: {
                            label: ctx => '₹ ' + Number(ctx.parsed.y).toLocaleString('en-IN')
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 11 },
                            callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0) + 'K' : v)
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 12 } },
                        border: { display: false }
                    }
                }
            }
        });

        // Booking Volume Chart
        const volumeCtx = document.getElementById('volumeChart').getContext('2d');
        new Chart(volumeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($volumeLabels); ?>,
                datasets: [{
                    label: 'Number of Bookings',
                    data: <?php echo json_encode($volumeData); ?>,
                    backgroundColor: ['#2a5298', '#ffc107', '#28a745', '#dc3545', '#17a2b8'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Category Revenue Chart — donut with center total
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryLabels = <?php echo json_encode($categoryLabels); ?>;
        const categoryValues = <?php echo json_encode($categoryData); ?>;
        const categoryColorsArr = <?php echo json_encode($categoryColors); ?>;
        const categoryTicketsData = <?php echo json_encode($categoryTickets); ?>;
        const totalRevenue = <?php echo (float)$totalCategoryRevenue; ?>;

        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryValues,
                    backgroundColor: categoryColorsArr.map(c =>
                        (c === '#eeff00' || c === '#deed07') ? '#f5c518' : c
                    ),
                    borderWidth: 0,
                    hoverOffset: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                animation: { animateRotate: true, duration: 1200, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#94a3b8',
                        bodyColor: '#f1f5f9',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        padding: 14,
                        cornerRadius: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const tickets = categoryTicketsData[context.dataIndex];
                                const pct = totalRevenue > 0 ? ((value / totalRevenue) * 100).toFixed(1) : 0;
                                return [
                                    ` ₹${value.toLocaleString('en-IN')}  (${pct}%)`,
                                    ` ${tickets} ticket${tickets !== 1 ? 's' : ''} sold`
                                ];
                            }
                        }
                    }
                },
                onHover: (event, elements) => {
                    const centerVal = document.getElementById('cat-center-value');
                    const centerLabel = document.getElementById('cat-center-label');
                    if (elements.length > 0) {
                        const idx = elements[0].index;
                        const val = categoryChart.data.datasets[0].data[idx];
                        const lbl = categoryChart.data.labels[idx];
                        centerVal.textContent = '₹' + Number(val).toLocaleString('en-IN');
                        centerLabel.textContent = lbl;
                    } else {
                        centerVal.textContent = '₹<?php echo number_format($totalCategoryRevenue); ?>';
                        centerLabel.textContent = 'In Total';
                    }
                }
            }
        });

        // Booking Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusData); ?>,
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    animateRotate: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });

        // Initialize status chart rotation
        setTimeout(() => {
            let start = Date.now();
            let dur = 1500;
            function rot() {
                let prog = Math.min((Date.now() - start) / dur, 1);
                let e = 1 - Math.pow(1 - prog, 3);
                statusChart.options.rotation = e * 360;
                statusChart.update('none');
                if (prog < 1) requestAnimationFrame(rot);
            }
            rot();
        }, 700);
    </script>

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
    </script>
</body>
</html>