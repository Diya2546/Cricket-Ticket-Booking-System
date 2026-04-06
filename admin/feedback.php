<?php
session_start();
include '../connection.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

/* -----------------------------
 Filters ------------------------------ */
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];
$params = [];
$types = '';

if ($rating_filter !== 'all') {
    $where_conditions[] = "f.rating = ?";
    $params[] = (int)$rating_filter;
    $types .= 'i';
}

if ($search !== '') {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR f.message LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($date_from !== '') {
    $where_conditions[] = "m.match_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to !== '') {
    $where_conditions[] = "m.match_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

/* -----------------------------
 Stats ------------------------------ */
$stats_query = "
    SELECT
        COUNT(*) AS total_feedback,
        COUNT(CASE WHEN DATE(f.created_at) = CURDATE() THEN 1 END) AS today_feedback,
        ROUND(AVG(f.rating), 1) AS avg_rating
    FROM feedback f
    JOIN matches m ON f.match_id = m.id
    $where_clause
";

$stats_stmt = mysqli_prepare($link, $stats_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stats_stmt, $types, ...$params);
}
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

/* -----------------------------
 Pagination ------------------------------ */
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

/* -----------------------------
 Feedback list ------------------------------ */
$list_query = "
    SELECT
        f.id,
        f.rating,
        f.message,
        f.created_at,
        u.name AS user_name,
        u.email AS user_email,
        t1.name AS team1_name,
        t2.name AS team2_name,
        m.match_date,
        m.match_type
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    JOIN matches m ON f.match_id = m.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    $where_clause
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
";

$list_stmt = mysqli_prepare($link, $list_query);
$all_params = array_merge($params, [$per_page, $offset]);
$all_types = $types . 'ii';
mysqli_stmt_bind_param($list_stmt, $all_types, ...$all_params);
mysqli_stmt_execute($list_stmt);
$list_result = mysqli_stmt_get_result($list_stmt);

/* -----------------------------
 Total rows for pagination ------------------------------ */
$count_query = "
    SELECT COUNT(*) AS total
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    JOIN matches m ON f.match_id = m.id
    $where_clause
";

$count_stmt = mysqli_prepare($link, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_count = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_count / $per_page);

$admin_name = $_SESSION['admin_username'] ?? 'Admin';
$avatar_text = strtoupper(substr($admin_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/feedback.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="sidebar">
    <h2>Cricket Admin</h2>

    <!-- <form class="search-form" method="get">
        <input type="text" name="search" placeholder="Search feedback..." value="<?php //echo htmlspecialchars($search); ?>">
        <i class="fas fa-search"></i>
    </form> -->

    <a href="dashboard.php">
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
    <a href="feedback.php" class="active">
        <i class="fas fa-star"></i> <span>Feedback</span>
    </a>
    <div style="flex:1"></div>
</div>

<div class="main-content">
    <div class="top-header">
        <div class="header-left">
            <h2>Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h2>
        </div>

        <div class="header-right">

            <div class="admin-avatar" onclick="toggleProfileMenu()">
                <?php echo $avatar_text; ?>
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
            <h1>Feedback Management</h1>
            <p>View customer feedback by category, rating, and message.</p>
        </div>
    </div>

    <div class="feedback-stats">
        <div class="stat-card">
            <div class="stat-icon blue-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total_feedback'] ?? 0); ?></div>
            <div class="stat-label">Total Feedback</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['today_feedback'] ?? 0); ?></div>
            <div class="stat-label">Today Feedback</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-value"><?php echo $stats['avg_rating'] !== null ? number_format($stats['avg_rating'], 1) : '0.0'; ?></div>
            <div class="stat-label">Average Rating</div>
        </div>
    </div>

    <div class="filter-section">
        <form method="get" class="filter-row">
            <div class="filter-group">
                <label>Rating</label>
                <select name="rating">
                    <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                    <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                    <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                    <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                    <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                    <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Match From Date</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>

            <div class="filter-group">
                <label>Match To Date</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>

            <a href="feedback.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>

            <a href="feedback_export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline">
                <i class="fas fa-download"></i> Export
            </a>
        </form>
    </div>

    <div class="feedback-table-container">
        <div class="table-header">
            <h3 class="table-title">Feedback List</h3>
        </div>

        <table class="feedback-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Match</th>
                    <th>Rating</th>
                    <th>Feedback Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($feedback = mysqli_fetch_assoc($list_result)): ?>
                    <tr>
                        <td>
                            <div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($feedback['user_name']); ?></div>
                                <div style="font-size: 0.82rem; color: var(--text-muted);"><?php echo htmlspecialchars($feedback['user_email']); ?></div>
                            </div>
                        </td>

                        <td>
                            <div>
                                <div style="font-weight: 600;">
                                    <?php echo htmlspecialchars($feedback['team1_name']); ?> vs <?php echo htmlspecialchars($feedback['team2_name']); ?>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-muted);">
                                    <?php echo date('d M Y', strtotime($feedback['match_date'])); ?> • <?php echo htmlspecialchars($feedback['match_type']); ?>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star <?php echo $i <= $feedback['rating'] ? '' : 'empty'; ?>"></i>
                                <?php
    endfor; ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                                <?php echo (int)$feedback['rating']; ?>/5
                            </div>
                        </td>

                        <td>
                            <div class="message-text">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                        </td>

                        <td>
                            <?php echo date('d M Y, h:i A', strtotime($feedback['created_at'])); ?>
                        </td>
                    </tr>
                <?php
endwhile; ?>

                <?php if (mysqli_num_rows($list_result) === 0): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:2rem; color: var(--text-muted);">
                            No feedback found.
                        </td>
                    </tr>
                <?php
endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrap">
                <?php if ($page > 1): ?>
                    <a class="btn btn-outline btn-sm" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php
    endif; ?>

                <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

                <?php if ($page < $total_pages): ?>
                    <a class="btn btn-outline btn-sm" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php
    endif; ?>
            </div>
        <?php
endif; ?>
    </div>
</div>

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

function openSettingsModal() {
    alert('Settings page coming soon!');
}
</script>

</body>
</html>