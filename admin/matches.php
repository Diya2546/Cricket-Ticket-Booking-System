<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

include '../connection.php';

// Get admin details
$admin_id = (int) $_SESSION['admin_id'];
$adminQuery = "SELECT * FROM admins WHERE id = $admin_id";
$adminResult = mysqli_query($link, $adminQuery);
$adminData = mysqli_fetch_assoc($adminResult);

$message = '';

// Retrieve and clear session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// ✅ Add New Team
if (isset($_POST['add_team'])) {
    if (ob_get_level()) {
        ob_clean();
    }

    $team_name = mysqli_real_escape_string($link, trim($_POST['team_name'] ?? ''));
    $team_short = mysqli_real_escape_string($link, trim($_POST['team_short'] ?? ''));

    if (empty($team_name) || empty($team_short)) {
        $response = ['success' => false, 'message' => 'Team name and short name are required'];
    } else {
        $check_team = "SELECT id FROM teams WHERE LOWER(name) = LOWER('$team_name') OR LOWER(short_name) = LOWER('$team_short') LIMIT 1";
        $check_result = mysqli_query($link, $check_team);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $response = ['success' => false, 'message' => 'Team with this name or short name already exists'];
        } else {
            $logo_path = null;
            if (isset($_FILES['team_logo']) && $_FILES['team_logo']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/avif', 'image/svg+xml'];
                $file_type = $_FILES['team_logo']['type'];

                if (in_array($file_type, $allowed_types)) {
                    $ext = pathinfo($_FILES['team_logo']['name'], PATHINFO_EXTENSION);
                    $filename = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $team_short)) . '.' . $ext;
                    $upload_dir = '../image/teams/';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $dest = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['team_logo']['tmp_name'], $dest)) {
                        $logo_path = 'image/teams/' . $filename;
                    }
                }
            }

            $logo_sql = $logo_path ? "'" . mysqli_real_escape_string($link, $logo_path) . "'" : "NULL";
            $insert_team = "INSERT INTO teams (name, short_name, logo) VALUES ('$team_name', '$team_short', $logo_sql)";
            
            if (mysqli_query($link, $insert_team)) {
                $response = [
                    'success' => true,
                    'message' => 'Team added successfully!',
                    'id' => mysqli_insert_id($link),
                    'name' => $team_name
                ];
            } else {
                $response = ['success' => false, 'message' => 'Error adding team: ' . mysqli_error($link)];
            }
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        $message = $response['success']
            ? '<div class="alert alert-success">✅ ' . $response['message'] . '</div>'
            : '<div class="alert alert-danger">❌ ' . $response['message'] . '</div>';
    }
}

// ✅ Add New Venue
if (isset($_POST['add_venue'])) {
    if (ob_get_level()) {
        ob_clean();
    }

    $venue_name = mysqli_real_escape_string($link, trim($_POST['venue_name'] ?? ''));
    $venue_city = mysqli_real_escape_string($link, trim($_POST['venue_city'] ?? ''));
    $venue_state = mysqli_real_escape_string($link, trim($_POST['venue_state'] ?? ''));
    $venue_country = mysqli_real_escape_string($link, trim($_POST['venue_country'] ?? ''));
    $venue_capacity = mysqli_real_escape_string($link, trim($_POST['venue_capacity'] ?? ''));
    $venue_address = mysqli_real_escape_string($link, trim($_POST['venue_address'] ?? ''));

    if (
        empty($venue_name) ||
        empty($venue_city) ||
        empty($venue_state) ||
        empty($venue_country) ||
        empty($venue_capacity) ||
        empty($venue_address)
    ) {
        $response = ['success' => false, 'message' => 'All venue details are required'];
    } else {
        $check_venue = "SELECT id FROM venues WHERE LOWER(name) = LOWER('$venue_name') AND LOWER(city) = LOWER('$venue_city') LIMIT 1";
        $check_result = mysqli_query($link, $check_venue);

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $response = ['success' => false, 'message' => 'Venue with this name and city already exists'];
        } else {
            $insert_venue = "INSERT INTO venues (name, city, state, country, capacity, address)
                             VALUES ('$venue_name', '$venue_city', '$venue_state', '$venue_country', '$venue_capacity', '$venue_address')";
            if (mysqli_query($link, $insert_venue)) {
                $response = [
                    'success' => true,
                    'message' => 'Venue added successfully!',
                    'id' => mysqli_insert_id($link),
                    'name' => $venue_name,
                    'city' => $venue_city
                ];
            } else {
                $response = ['success' => false, 'message' => 'Error adding venue: ' . mysqli_error($link)];
            }
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        $message = $response['success']
            ? '<div class="alert alert-success">✅ ' . $response['message'] . '</div>'
            : '<div class="alert alert-danger">❌ ' . $response['message'] . '</div>';
    }
}

// ✅ Add Match
if (isset($_POST['add_match'])) {
    $team1_id = mysqli_real_escape_string($link, $_POST['team1_id'] ?? '');
    $team2_id = mysqli_real_escape_string($link, $_POST['team2_id'] ?? '');
    $venue_id = mysqli_real_escape_string($link, $_POST['venue_id'] ?? '');
    $match_date = mysqli_real_escape_string($link, $_POST['match_date'] ?? '');
    $match_time = mysqli_real_escape_string($link, $_POST['match_time'] ?? '');
    $match_type = mysqli_real_escape_string($link, $_POST['match_type'] ?? '');
    $description = mysqli_real_escape_string($link, $_POST['description'] ?? '');
    $status = mysqli_real_escape_string($link, $_POST['status'] ?? '');

    if ($team1_id === $team2_id) {
        $_SESSION['message'] = '<div class="alert alert-danger">❌ Team 1 and Team 2 cannot be the same.</div>';
        header('Location: matches.php');
        exit;
    }

    $check_match = "SELECT id FROM matches
                    WHERE ((team1_id = '$team1_id' AND team2_id = '$team2_id') OR
                           (team1_id = '$team2_id' AND team2_id = '$team1_id'))
                    AND match_date = '$match_date'
                    AND match_time = '$match_time'
                    AND venue_id = '$venue_id'";
    $check_result = mysqli_query($link, $check_match);

    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $_SESSION['message'] = '<div class="alert alert-danger">❌ This match already exists!</div>';
    } else {
        $insert_sql = "INSERT INTO matches (team1_id, team2_id, venue_id, match_date, match_time, match_type, description, status)
                       VALUES ('$team1_id', '$team2_id', '$venue_id', '$match_date', '$match_time', '$match_type', '$description', '$status')";

        if (mysqli_query($link, $insert_sql)) {
            $_SESSION['message'] = '<div class="alert alert-success">✅ Match added successfully! It will now be visible to users.</div>';
        } else {
            $_SESSION['message'] = '<div class="alert alert-danger">❌ Error: ' . mysqli_error($link) . '</div>';
        }
    }

    header('Location: matches.php');
    exit;
}

// ✅ Edit Match
if (isset($_POST['edit_match'])) {
    $match_id = mysqli_real_escape_string($link, $_POST['match_id'] ?? '');
    $team1_id = mysqli_real_escape_string($link, $_POST['team1_id'] ?? '');
    $team2_id = mysqli_real_escape_string($link, $_POST['team2_id'] ?? '');
    $venue_id = mysqli_real_escape_string($link, $_POST['venue_id'] ?? '');
    $match_date = mysqli_real_escape_string($link, $_POST['match_date'] ?? '');
    $match_time = mysqli_real_escape_string($link, $_POST['match_time'] ?? '');
    $match_type = mysqli_real_escape_string($link, $_POST['match_type'] ?? '');
    $description = mysqli_real_escape_string($link, $_POST['description'] ?? '');
    $status = mysqli_real_escape_string($link, $_POST['status'] ?? '');

    if ($team1_id === $team2_id) {
        $message = '<div class="alert alert-danger">❌ Team 1 and Team 2 cannot be the same.</div>';
    } else {
        $update_sql = "UPDATE matches SET
                        team1_id='$team1_id',
                        team2_id='$team2_id',
                        venue_id='$venue_id',
                        match_date='$match_date',
                        match_time='$match_time',
                        match_type='$match_type',
                        description='$description',
                        status='$status'
                        WHERE id='$match_id'";

        if (mysqli_query($link, $update_sql)) {
            $message = '<div class="alert alert-success">✅ Match updated successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">❌ Error: ' . mysqli_error($link) . '</div>';
        }
    }
}

// ✅ Delete Match
if (isset($_POST['delete_match'])) {
    $match_id = mysqli_real_escape_string($link, $_POST['match_id'] ?? '');

    $delete_sql = "DELETE FROM matches WHERE id='$match_id'";

    if (mysqli_query($link, $delete_sql)) {
        $message = '<div class="alert alert-success">✅ Match deleted successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger"> Error: ' . mysqli_error($link) . '</div>';
    }
}

$search = '';
$whereClause = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($link, $_GET['search']);
    $whereClause = "WHERE (t1.name LIKE '%$search%' OR t2.name LIKE '%$search%' OR v.name LIKE '%$search%' OR v.city LIKE '%$search%' OR m.match_type LIKE '%$search%')";
}

$countQuery = "SELECT COUNT(*) as total FROM matches m
               JOIN teams t1 ON m.team1_id = t1.id
               JOIN teams t2 ON m.team2_id = t2.id
               JOIN venues v ON m.venue_id = v.id
               $whereClause";
$countResult = mysqli_query($link, $countQuery);
$totalMatches = mysqli_fetch_assoc($countResult)['total'] ?? 0;

$perPage = 10;
$totalPages = ceil($totalMatches / $perPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

$matches_query = "SELECT m.*,
                  t1.name as team1_name, t1.short_name as team1_short,
                  t2.name as team2_name, t2.short_name as team2_short,
                  v.name as venue_name, v.city as venue_city
                  FROM matches m
                  JOIN teams t1 ON m.team1_id = t1.id
                  JOIN teams t2 ON m.team2_id = t2.id
                  JOIN venues v ON m.venue_id = v.id
                  $whereClause
                  ORDER BY
                    CASE
                        WHEN m.status = 'live' THEN 1
                        WHEN m.status = 'upcoming' THEN 2
                        ELSE 3
                    END,
                    m.match_date ASC,
                    m.match_time ASC
                  LIMIT $perPage OFFSET $offset";
$matches_result = mysqli_query($link, $matches_query);

$teams_result = mysqli_query($link, "SELECT * FROM teams ORDER BY name ASC");
$venues_result = mysqli_query($link, "SELECT * FROM venues ORDER BY name ASC");

$todayQuery = "SELECT COUNT(*) as count FROM matches WHERE match_date = CURDATE()";
$todayResult = mysqli_query($link, $todayQuery);
$todayMatches = mysqli_fetch_assoc($todayResult)['count'] ?? 0;

$upcomingQuery = "SELECT COUNT(*) as count FROM matches WHERE status = 'upcoming' AND match_date >= CURDATE()";
$upcomingResult = mysqli_query($link, $upcomingQuery);
$upcomingMatches = mysqli_fetch_assoc($upcomingResult)['count'] ?? 0;

$liveQuery = "SELECT COUNT(*) as count FROM matches WHERE status = 'live'";
$liveResult = mysqli_query($link, $liveQuery);
$liveMatches = mysqli_fetch_assoc($liveResult)['count'] ?? 0;

$completedQuery = "SELECT COUNT(*) as count FROM matches WHERE status = 'completed'";
$completedResult = mysqli_query($link, $completedQuery);
$completedMatches = mysqli_fetch_assoc($completedResult)['count'] ?? 0;

$totalMatchesCount = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as count FROM matches"))['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Matches - CricTix Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/matches.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="sidebar">
        <h2>Cricket Admin</h2>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="matches.php" class="active"><i class="fas fa-baseball-ball"></i> <span>Matches Management</span></a>
        <a href="bookings.php"><i class="fas fa-ticket-alt"></i> <span>Bookings</span></a>
        <a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a>
        <a href="category.php"><i class="fas fa-list"></i> <span>Manage Categories</span></a>
        <a href="venue_categories.php"><i class="fas fa-th-large"></i> <span>Venue Categories</span></a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span></a>
        <a href="feedback.php"><i class="fas fa-star"></i> <span>Feedback</span></a>
        <div style="flex:1"></div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="header-left">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</h2>
                <!-- <p><?php //echo htmlspecialchars($adminData['role'] ?? 'Super Admin'); ?></p> -->
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
            <h1>Manage Matches</h1>
            <button class="btn-add" onclick="openModal('addMatchModal')">
                <i class="fas fa-plus-circle"></i> Add New Match
            </button>
        </div>

        <?php if ($message): ?>
            <?php echo $message; ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    setTimeout(function () {
                        const alertMessage = document.querySelector('.alert');
                        if (alertMessage) {
                            alertMessage.style.transition = 'opacity 1s';
                            alertMessage.style.opacity = '0';
                            setTimeout(function () {
                                if (alertMessage.parentNode) {
                                    alertMessage.remove();
                                }
                            }, 1000);
                        }
                    }, 3000);
                });
            </script>
            <?php
        endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-info">
                    <h3>Today's Matches</h3>
                    <p><?php echo $todayMatches; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3>Upcoming</h3>
                    <p><?php echo $upcomingMatches; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-broadcast-tower"></i></div>
                <div class="stat-info">
                    <h3>Live Now</h3>
                    <p><?php echo $liveMatches; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3>Completed</h3>
                    <p><?php echo $completedMatches; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-info">
                    <h3>Total Matches</h3>
                    <p><?php echo $totalMatchesCount; ?></p>
                </div>
            </div>
        </div>

        <div class="search-bar">
            <form method="GET" style="display: flex; width: 100%;">
                <input type="text" name="search" placeholder="Search by teams, venue, or match type..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if (!empty($search)): ?>
                    <a href="matches.php" class="btn btn-secondary search-clear-btn">Clear</a>
                    <?php
                endif; ?>
            </form>
        </div>

        <div class="matches-table-container">
            <table class="matches-table">
                <thead>
                    <tr>
                        <th>Match ID</th>
                        <th>Teams</th>
                        <th>Date & Time</th>
                        <th>Venue</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($matches_result && mysqli_num_rows($matches_result) > 0): ?>
                        <?php while ($match = mysqli_fetch_assoc($matches_result)):
                            $match_id_display = '#M-' . str_pad($match['id'], 3, '0', STR_PAD_LEFT);
                            $match_date = date('d M Y', strtotime($match['match_date']));
                            $match_time = date('h:i A', strtotime($match['match_time']));
                            $status_class = '';

                            switch ($match['status']) {
                                case 'upcoming':
                                    $status_class = 'status-upcoming';
                                    break;
                                case 'live':
                                    $status_class = 'status-live';
                                    break;
                                case 'completed':
                                    $status_class = 'status-completed';
                                    break;
                                case 'cancelled':
                                    $status_class = 'status-cancelled';
                                    break;
                            }
                            ?>
                            <tr>
                                <td><span class="match-id"><?php echo $match_id_display; ?></span></td>
                                <td>
                                    <div class="match-teams">
                                        <span class="team-names"><?php echo htmlspecialchars($match['team1_name']); ?> vs
                                            <?php echo htmlspecialchars($match['team2_name']); ?></span>
                                        <span class="match-type"><?php echo htmlspecialchars($match['match_type']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="datetime-info">
                                        <span class="match-date"><?php echo $match_date; ?></span>
                                        <span class="match-time"><?php echo $match_time; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="venue-info">
                                        <span class="venue-name"><?php echo htmlspecialchars($match['venue_name']); ?></span>
                                        <span class="venue-city"><?php echo htmlspecialchars($match['venue_city']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge match-type-badge">
                                        <?php echo htmlspecialchars($match['match_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($match['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-icons">
                                        <!-- <button class="action-icon view" onclick="viewMatch(<?php //echo $match['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button> -->
                                        <button class="action-icon edit" onclick="openEditModal(<?php echo $match['id']; ?>)"
                                            title="Edit Match">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-icon delete"
                                            onclick="openDeleteModal(<?php echo $match['id']; ?>)" title="Delete Match">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        endwhile; ?>
                        <?php
                    else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No matches found</p>
                            </td>
                        </tr>
                        <?php
                    endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="page-link">
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
                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php
                endif; ?>
            </div>
            <?php
        endif; ?>
    </div>

    <!-- Add Match Modal -->
    <div class="modal fade" id="addMatchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Match</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeModal('addMatchModal')"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <form method="POST" id="matchForm">
                        <h6 class="match-section-title"><i class="fas fa-info-circle"></i> Match Details</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Team 1</label>
                                <div class="input-with-plus">
                                    <select name="team1_id" id="team1Select" class="form-select" required>
                                        <option value="">Select Team</option>
                                        <?php
                                        mysqli_data_seek($teams_result, 0);
                                        while ($team = mysqli_fetch_assoc($teams_result)):
                                            ?>
                                            <option value="<?php echo $team['id']; ?>">
                                                <?php echo htmlspecialchars($team['name']); ?>
                                            </option>
                                            <?php
                                        endwhile; ?>
                                    </select>
                                    <button type="button" class="plus-icon-btn" onclick="openModal('addTeamModal')"
                                        title="Add New Team">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Team 2</label>
                                <div class="input-with-plus">
                                    <select name="team2_id" id="team2Select" class="form-select" required>
                                        <option value="">Select Team</option>
                                        <?php
                                        mysqli_data_seek($teams_result, 0);
                                        while ($team = mysqli_fetch_assoc($teams_result)):
                                            ?>
                                            <option value="<?php echo $team['id']; ?>">
                                                <?php echo htmlspecialchars($team['name']); ?>
                                            </option>
                                            <?php
                                        endwhile; ?>
                                    </select>
                                    <button type="button" class="plus-icon-btn" onclick="openModal('addTeamModal')"
                                        title="Add New Team">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Venue</label>
                                <div class="input-with-plus">
                                    <select name="venue_id" id="venueSelect" class="form-select" required>
                                        <option value="">Select Venue</option>
                                        <?php
                                        mysqli_data_seek($venues_result, 0);
                                        while ($venue = mysqli_fetch_assoc($venues_result)):
                                            ?>
                                            <option value="<?php echo $venue['id']; ?>">
                                                <?php echo htmlspecialchars($venue['name'] . ', ' . $venue['city']); ?>
                                            </option>
                                            <?php
                                        endwhile; ?>
                                    </select>
                                    <button type="button" class="plus-icon-btn" onclick="openModal('addVenueModal')"
                                        title="Add New Venue">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Match Date</label>
                                <input type="date" name="match_date" class="form-control" required
                                    min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Match Time</label>
                                <input type="time" name="match_time" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Match Type</label>
                                <select name="match_type" class="form-select">
                                    <option value="T20">T20</option>
                                    <option value="ODI">ODI</option>
                                    <option value="Test">Test</option>
                                    <option value="IPL" selected>IPL</option>
                                    <option value="World Cup">World Cup</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="upcoming" selected>Upcoming</option>
                                    <option value="live">Live</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" name="description" class="form-control"
                                    placeholder="Match description...">
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('addMatchModal')">Cancel</button>
                    <button type="submit" name="add_match" form="matchForm" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Match
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Team Modal -->
    <div class="modal fade" id="addTeamModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Team</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeModal('addTeamModal')"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="popup-form-grid">
                        <div class="mb-3">
                            <label class="form-label">Team Name</label>
                            <input type="text" class="form-control" id="newTeamName" placeholder="Team Name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Short Name</label>
                            <input type="text" class="form-control" id="newTeamShort"
                                placeholder="Short Name (e.g., IND)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Team Logo</label>
                            <input type="file" class="form-control" id="newTeamLogo" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTeamModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addNewTeam(event)">
                        <i class="fas fa-plus"></i> Add Team
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Venue Modal -->
    <div class="modal fade" id="addVenueModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Venue</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeModal('addVenueModal')"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Venue Name</label>
                            <input type="text" class="form-control" id="newVenueName" placeholder="Venue Name">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" id="newVenueCity" placeholder="City">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" id="newVenueState" placeholder="State">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" id="newVenueCountry" placeholder="Country">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="newVenueCapacity" placeholder="Capacity">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" id="newVenueAddress" rows="4"
                                placeholder="Full Address"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('addVenueModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addNewVenue(event)">
                        <i class="fas fa-plus"></i> Add Venue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Match Modal -->
    <div class="modal fade" id="editMatchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Match</h5>
                    <button type="button" class="btn-close" onclick="closeModal('editMatchModal')" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editMatchForm">
                        <input type="hidden" name="match_id" id="edit_match_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Team 1</label>
                                <select name="team1_id" id="edit_team1_id" class="form-select" required>
                                    <option value="">Select Team</option>
                                    <?php
                                    mysqli_data_seek($teams_result, 0);
                                    while ($team = mysqli_fetch_assoc($teams_result)):
                                        ?>
                                        <option value="<?php echo $team['id']; ?>">
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                        <?php
                                    endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Team 2</label>
                                <select name="team2_id" id="edit_team2_id" class="form-select" required>
                                    <option value="">Select Team</option>
                                    <?php
                                    mysqli_data_seek($teams_result, 0);
                                    while ($team = mysqli_fetch_assoc($teams_result)):
                                        ?>
                                        <option value="<?php echo $team['id']; ?>">
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                        <?php
                                    endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Venue</label>
                                <select name="venue_id" id="edit_venue_id" class="form-select" required>
                                    <option value="">Select Venue</option>
                                    <?php
                                    mysqli_data_seek($venues_result, 0);
                                    while ($venue = mysqli_fetch_assoc($venues_result)):
                                        ?>
                                        <option value="<?php echo $venue['id']; ?>">
                                            <?php echo htmlspecialchars($venue['name'] . ', ' . $venue['city']); ?>
                                        </option>
                                        <?php
                                    endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Match Date</label>
                                <input type="date" name="match_date" id="edit_match_date" class="form-control" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Match Time</label>
                                <input type="time" name="match_time" id="edit_match_time" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Match Type</label>
                                <select name="match_type" id="edit_match_type" class="form-select">
                                    <option value="T20">T20</option>
                                    <option value="ODI">ODI</option>
                                    <option value="Test">Test</option>
                                    <option value="IPL">IPL</option>
                                    <option value="World Cup">World Cup</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select">
                                    <option value="upcoming">Upcoming</option>
                                    <option value="live">Live</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" name="description" id="edit_description" class="form-control"
                                    placeholder="Match description...">
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('editMatchModal')">Cancel</button>
                    <button type="submit" name="edit_match" form="editMatchForm" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Match
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Match Modal -->
    <div class="modal fade" id="deleteMatchModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close" onclick="closeModal('deleteMatchModal')" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="deleteMatchForm">
                        <input type="hidden" name="match_id" id="delete_match_id">
                        <p>Are you sure you want to delete this match?</p>
                        <p><strong id="delete_match_details"></strong></p>
                        <p class="text-muted" id="delete_match_datetime"></p>
                        <p class="text-danger"><small>This action cannot be undone and will delete all associated seat
                                inventory.</small></p>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('deleteMatchModal')">Cancel</button>
                    <button type="submit" name="delete_match" form="deleteMatchForm" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Yes, Delete Match
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let matchesData = <?php
        $matches_array = [];
        if ($matches_result) {
            mysqli_data_seek($matches_result, 0);
            while ($match = mysqli_fetch_assoc($matches_result)) {
                $matches_array[$match['id']] = $match;
            }
            mysqli_data_seek($matches_result, 0);
        }
        echo json_encode($matches_array);
        ?>;

        function toggleProfileMenu() {
            document.getElementById('profileMenu').classList.toggle('show');
        }

        window.addEventListener('click', function (e) {
            const menu = document.getElementById('profileMenu');
            const avatar = document.querySelector('.admin-avatar');
            if (avatar && !avatar.contains(e.target) && menu) {
                menu.classList.remove('show');
            }
        });

        function openSettingsModal() {
            alert('Settings page coming soon!');
        }

        function viewMatch(matchId) {
            window.location.href = 'match_details.php?id=' + matchId;
        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.add('show');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('show');
            modal.style.display = 'none';

            const anyOpen = document.querySelector('.modal.show');
            document.body.style.overflow = anyOpen ? 'hidden' : 'auto';
        }

        function openEditModal(matchId) {
            const match = matchesData[matchId];
            if (match) {
                document.getElementById('edit_match_id').value = match.id;
                document.getElementById('edit_team1_id').value = match.team1_id;
                document.getElementById('edit_team2_id').value = match.team2_id;
                document.getElementById('edit_venue_id').value = match.venue_id;
                document.getElementById('edit_match_date').value = match.match_date;
                document.getElementById('edit_match_time').value = match.match_time;
                document.getElementById('edit_match_type').value = match.match_type;
                document.getElementById('edit_status').value = match.status;
                document.getElementById('edit_description').value = match.description || '';
                openModal('editMatchModal');
            }
        }

        function openDeleteModal(matchId) {
            const match = matchesData[matchId];
            if (match) {
                document.getElementById('delete_match_id').value = match.id;

                const matchDate = new Date(match.match_date + ' ' + match.match_time);
                const formattedDate = matchDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
                const formattedTime = matchDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                document.getElementById('delete_match_details').textContent =
                    match.team1_name + ' vs ' + match.team2_name;
                document.getElementById('delete_match_datetime').textContent =
                    'Date: ' + formattedDate + ' at ' + formattedTime;

                openModal('deleteMatchModal');
            }
        }

        window.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal') && event.target.classList.contains('show')) {
                closeModal(event.target.id);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                const openModalEl = document.querySelector('.modal.show');
                if (openModalEl) {
                    closeModal(openModalEl.id);
                }
            }
        });

        function addNewTeam(event) {
            if (window.teamAddingInProgress) return;

            const teamName = document.getElementById('newTeamName').value.trim();
            const teamShort = document.getElementById('newTeamShort').value.trim();

            if (!teamName || !teamShort) {
                alert('Please fill all team details');
                return;
            }

            window.teamAddingInProgress = true;
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            btn.disabled = true;

            const logoFile = document.getElementById('newTeamLogo') ? document.getElementById('newTeamLogo').files[0] : null;
            const formData = new FormData();
            formData.append('add_team', '1');
            formData.append('team_name', teamName);
            formData.append('team_short', teamShort);
            if (logoFile) {
                formData.append('team_logo', logoFile);
            }

            fetch('matches.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('newTeamName').value = '';
                        document.getElementById('newTeamShort').value = '';
                        closeModal('addTeamModal');
                        refreshTeamDropdown(data.id, data.name);
                        alert('Team added successfully!');
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding team');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    window.teamAddingInProgress = false;
                });
        }

        function addNewVenue(event) {
            if (window.venueAddingInProgress) return;

            const venueName = document.getElementById('newVenueName').value.trim();
            const venueCity = document.getElementById('newVenueCity').value.trim();
            const venueState = document.getElementById('newVenueState').value.trim();
            const venueCountry = document.getElementById('newVenueCountry').value.trim();
            const venueCapacity = document.getElementById('newVenueCapacity').value.trim();
            const venueAddress = document.getElementById('newVenueAddress').value.trim();

            if (!venueName || !venueCity || !venueState || !venueCountry || !venueCapacity || !venueAddress) {
                alert('Please fill all venue details');
                return;
            }

            window.venueAddingInProgress = true;
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            btn.disabled = true;

            fetch('matches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body:
                    `add_venue=1` +
                    `&venue_name=${encodeURIComponent(venueName)}` +
                    `&venue_city=${encodeURIComponent(venueCity)}` +
                    `&venue_state=${encodeURIComponent(venueState)}` +
                    `&venue_country=${encodeURIComponent(venueCountry)}` +
                    `&venue_capacity=${encodeURIComponent(venueCapacity)}` +
                    `&venue_address=${encodeURIComponent(venueAddress)}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('newVenueName').value = '';
                        document.getElementById('newVenueCity').value = '';
                        document.getElementById('newVenueState').value = '';
                        document.getElementById('newVenueCountry').value = '';
                        document.getElementById('newVenueCapacity').value = '';
                        document.getElementById('newVenueAddress').value = '';
                        closeModal('addVenueModal');
                        refreshVenueDropdown(data.id, data.name, data.city);
                        alert('Venue added successfully!');
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding venue');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    window.venueAddingInProgress = false;
                });
        }

        function refreshTeamDropdown(selectedId = null, selectedName = null) {
            fetch('matches.php')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newOptions = doc.querySelectorAll('#team1Select option');

                    const team1Select = document.getElementById('team1Select');
                    const team2Select = document.getElementById('team2Select');
                    const editTeam1Select = document.getElementById('edit_team1_id');
                    const editTeam2Select = document.getElementById('edit_team2_id');

                    [team1Select, team2Select, editTeam1Select, editTeam2Select].forEach(select => {
                        while (select.children.length > 1) {
                            select.removeChild(select.lastChild);
                        }
                    });

                    const addedTeams = new Set();
                    newOptions.forEach(option => {
                        if (option.value && !addedTeams.has(option.value)) {
                            addedTeams.add(option.value);
                            team1Select.appendChild(option.cloneNode(true));
                            team2Select.appendChild(option.cloneNode(true));
                            editTeam1Select.appendChild(option.cloneNode(true));
                            editTeam2Select.appendChild(option.cloneNode(true));
                        }
                    });

                    if (selectedId) {
                        team1Select.value = String(selectedId);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing teams:', error);
                });
        }

        function refreshVenueDropdown(selectedId = null) {
            fetch('matches.php')
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newOptions = doc.querySelectorAll('#venueSelect option');

                    const venueSelect = document.getElementById('venueSelect');
                    const editVenueSelect = document.getElementById('edit_venue_id');

                    [venueSelect, editVenueSelect].forEach(select => {
                        while (select.children.length > 1) {
                            select.removeChild(select.lastChild);
                        }
                    });

                    newOptions.forEach(option => {
                        if (option.value) {
                            venueSelect.appendChild(option.cloneNode(true));
                            editVenueSelect.appendChild(option.cloneNode(true));
                        }
                    });

                    if (selectedId) {
                        venueSelect.value = String(selectedId);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing venues:', error);
                });
        }
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
    </script>
</body>

</html>