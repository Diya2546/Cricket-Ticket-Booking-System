<?php
ob_start();
session_start();

if (!isset($_SESSION['admin_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

include '../connection.php';

// Get admin details safely
$admin_id = $_SESSION['admin_id'];
$adminQuery = "SELECT * FROM admins WHERE id = ?";
$stmt = mysqli_prepare($link, $adminQuery);
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
$adminResult = mysqli_stmt_get_result($stmt);
$adminData = mysqli_fetch_assoc($adminResult);

$message = '';

function sendJson($data) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

/* ───────────────── AJAX: Get Venues ───────────────── */
if (isset($_GET['ajax_venues'])) {
    $venues = [];
    $q = mysqli_query($link, "SELECT id, name, city FROM venues ORDER BY name ASC");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $venues[] = $row;
        }
    }
    sendJson(['success' => true, 'venues' => $venues]);
}

/* ───────────────── AJAX: Get Categories ───────────────── */
if (isset($_GET['ajax_categories'])) {
    $categories = [];
    $q = mysqli_query($link, "SELECT id, name FROM seat_categories ORDER BY name ASC");
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $categories[] = $row;
        }
    }
    sendJson(['success' => true, 'categories' => $categories]);
}

/* ───────────────── AJAX: Get Single Venue Category ───────────────── */
if (isset($_GET['ajax_get']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "
        SELECT vc.*, sc.name AS category_name, sc.description AS category_description
        FROM venue_category vc
        INNER JOIN seat_categories sc ON vc.category_id = sc.id
        WHERE vc.id = $id
    ";
    $res = mysqli_query($link, $sql);

    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        sendJson(['success' => true, 'data' => $row]);
    } else {
        sendJson(['success' => false, 'message' => 'Record not found.']);
    }
}

/* ───────────────── AJAX: Update Venue Category ───────────────── */
if (isset($_POST['ajax_update'])) {
    $id          = intval($_POST['id'] ?? 0);
    $venue_id    = intval($_POST['venue_id'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price'] ?? 0);
    $total_seats = intval($_POST['total_seats'] ?? 0);
    $color_code  = mysqli_real_escape_string($link, trim($_POST['color_code'] ?? '#10b981'));
    $amenities   = mysqli_real_escape_string($link, trim($_POST['amenities'] ?? ''));

    if ($id <= 0 || $venue_id <= 0 || $category_id <= 0) {
        sendJson(['success' => false, 'message' => 'Venue and category are required.']);
    }

    $check = mysqli_query($link, "
        SELECT id FROM venue_category
        WHERE venue_id = $venue_id AND category_id = $category_id AND id != $id
    ");

    if ($check && mysqli_num_rows($check) > 0) {
        sendJson(['success' => false, 'message' => 'This category already exists for the selected venue.']);
    }

    // current row fetch
    $oldRes = mysqli_query($link, "SELECT total_seats, no_of_seats FROM venue_category WHERE id = $id");
    $oldRow = ($oldRes && mysqli_num_rows($oldRes) > 0) ? mysqli_fetch_assoc($oldRes) : null;

    if (!$oldRow) {
        sendJson(['success' => false, 'message' => 'Record not found.']);
    }

    $old_total_seats = (int)$oldRow['total_seats'];
    $old_available   = (int)$oldRow['no_of_seats'];
    $booked_seats    = max(0, $old_total_seats - $old_available);

    if ($total_seats < $booked_seats) {
        sendJson(['success' => false, 'message' => 'Total seats cannot be less than already booked seats (' . $booked_seats . ').']);
    }

    $new_available = $total_seats - $booked_seats;

    $sql = "
        UPDATE venue_category
        SET venue_id = $venue_id,
            category_id = $category_id,
            total_seats = $total_seats,
            no_of_seats = $new_available,
            color_code = '$color_code',
            price = '$price',
            amenities = '$amenities'
        WHERE id = $id
    ";

    if (mysqli_query($link, $sql)) {
        sendJson(['success' => true, 'message' => 'Venue category updated successfully.']);
    } else {
        sendJson(['success' => false, 'message' => mysqli_error($link)]);
    }
}

/* ───────────────── Add Venue Category ───────────────── */
if (isset($_POST['add_category'])) {
    $venue_id    = intval($_POST['venue_id'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $price       = floatval($_POST['price'] ?? 0);
    $total_seats = intval($_POST['total_seats'] ?? 0);
    $color_code  = mysqli_real_escape_string($link, trim($_POST['color_code'] ?? '#10b981'));
    $amenities   = mysqli_real_escape_string($link, trim($_POST['amenities'] ?? ''));

    if ($venue_id <= 0 || $category_id <= 0) {
        $message = '<div class="alert alert-danger">❌ Venue and category are required.</div>';
    } else {
        $check = mysqli_query($link, "
            SELECT id FROM venue_category
            WHERE venue_id = $venue_id AND category_id = $category_id
        ");

        if ($check && mysqli_num_rows($check) > 0) {
            $message = '<div class="alert alert-danger">❌ This category already exists for the selected venue.</div>';
        } else {
            $sql = "
                INSERT INTO venue_category (venue_id, category_id, total_seats, no_of_seats, color_code, price, amenities)
                VALUES ($venue_id, $category_id, $total_seats, $total_seats, '$color_code', '$price', '$amenities')
            ";

            if (mysqli_query($link, $sql)) {
                $_SESSION['message'] = '<div class="alert alert-success">✅ Venue category added successfully.</div>';
                ob_end_clean();
                header('Location: venue_categories.php');
                exit();
            } else {
                $message = '<div class="alert alert-danger">❌ Error: ' . mysqli_error($link) . '</div>';
            }
        }
    }
}

/* ───────────────── Delete Venue Category ───────────────── */
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);

    if ($del_id > 0) {
        $sql = "DELETE FROM venue_category WHERE id = $del_id";

        if (mysqli_query($link, $sql)) {
            $_SESSION['message'] = '<div class="alert alert-success">✅ Record deleted successfully.</div>';
        } else {
            $_SESSION['message'] = '<div class="alert alert-danger">❌ Delete failed: ' . htmlspecialchars(mysqli_error($link)) . '</div>';
        }

        ob_end_clean();
        header('Location: venue_categories.php');
        exit();
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

/* ───────────────── Pagination Logic ───────────────── */
$items_per_page = 8; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$totalCountRes = mysqli_query($link, "SELECT COUNT(*) AS total FROM venue_category");
$totalRows = mysqli_fetch_assoc($totalCountRes)['total'] ?? 0;
$totalPages = ceil($totalRows / $items_per_page);
if ($current_page > $totalPages && $totalPages > 0) $current_page = $totalPages;

$offset = ($current_page - 1) * $items_per_page;

/* ───────────────── Main List Query ───────────────── */
/*
    total_seats = fixed total seats
    no_of_seats = available seats
    display = available / total
*/
$listQuery = "
    SELECT 
        vc.id, 
        vc.venue_id, 
        vc.category_id, 
        vc.total_seats,
        vc.no_of_seats, 
        vc.color_code, 
        vc.price, 
        vc.amenities,
        v.name AS venue_name, 
        v.city AS venue_city,
        sc.name AS category_name, 
        sc.description AS category_description,
        COUNT(DISTINCT m.id) AS total_matches
    FROM venue_category vc
    INNER JOIN venues v ON vc.venue_id = v.id
    INNER JOIN seat_categories sc ON vc.category_id = sc.id
    LEFT JOIN matches m ON m.venue_id = vc.venue_id
    GROUP BY 
        vc.id, 
        vc.venue_id, 
        vc.category_id, 
        vc.total_seats,
        vc.no_of_seats, 
        vc.color_code, 
        vc.price, 
        vc.amenities,
        v.name,
        v.city,
        sc.name,
        sc.description
    ORDER BY sc.name ASC, v.name ASC
    LIMIT $items_per_page OFFSET $offset
";

$catResult = mysqli_query($link, $listQuery);

/* ───────────────── Stats ───────────────── */
$stat1 = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) AS total FROM venue_category"));
$totalVenueCategories = $stat1['total'] ?? 0;

$stat2 = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE payment_status = 'success'
"));
$confirmedBookings = $stat2['total'] ?? 0;

$stat3 = mysqli_fetch_assoc(mysqli_query($link, "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE payment_status = 'pending'
"));
$pendingBookings = $stat3['total'] ?? 0;

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Categories - Cricket Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/venue-categories.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="sidebar">
    <h2>Cricket Admin</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
    <a href="matches.php"><i class="fas fa-baseball-ball"></i> <span>Manage Matches</span></a>
    <a href="bookings.php"><i class="fas fa-ticket-alt"></i> <span>Bookings</span></a>
    <a href="users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
    <a href="category.php"><i class="fas fa-list"></i> <span>Manage Categories</span></a>
    <a href="venue_categories.php" class="active"><i class="fas fa-th-large"></i> <span>Venue Categories</span></a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span></a>
    <a href="feedback.php"><i class="fas fa-star"></i> <span>Feedback</span></a>
    <div style="flex:1"></div>
</div>

<div class="main-content">
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
                    <a href="logout.php" style="color: #ef4444;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);">
                <i class="fas fa-th-large" style="color:#10b981;"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $totalVenueCategories; ?></h3>
                <p>Total Venue Categories</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.15);">
                <i class="fas fa-check-circle" style="color:#3b82f6;"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $confirmedBookings; ?></h3>
                <p>Confirmed</p>
            </div>
        </div>
        <!-- <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.15);">
                <i class="fas fa-clock" style="color:#f59e0b;"></i>
            </div>
            <div class="stat-content">
                <h3><?php //echo $pendingBookings; ?></h3>
                <p>Pending Payment</p>
            </div>
        </div> -->
    </div>

    <div class="action-bar">
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Add New Category
        </button>
        <div class="action-right">
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-download"></i> Export Categories
            </button>
            <input type="text" class="search-input" id="searchInput" placeholder="Search categories...">
            <select class="sort-select" id="sortSelect">
                <option value="name">Sort by Category</option>
                <option value="price">Sort by Price</option>
                <option value="availability">Sort by Availability</option>
            </select>
        </div>
    </div>

    <?php if (!empty($message)) echo $message; ?>

    <div class="page-header">
        <h2><i class="fas fa-th-large"></i> Venue Categories</h2>
        <p>Manage venue categories, pricing, and availability for different match sections.</p>
    </div>

    <div id="toastMsg" class="toast-notify" style="display:none;"></div>

    <div class="categories-grid" id="categoriesGrid">
        <?php if ($catResult && mysqli_num_rows($catResult) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($catResult)): ?>
                <?php
                    $total_seats = (int)$row['total_seats'];
                    $available   = (int)$row['no_of_seats'];

                    if ($available < 0) {
                        $available = 0;
                    }

                    if ($available > $total_seats) {
                        $available = $total_seats;
                    }

                    $tickets_sold = $total_seats - $available;
                    $occupancy = $total_seats > 0 ? round(($tickets_sold / $total_seats) * 100, 1) : 0;
                    $amenities = !empty($row['amenities']) ? explode(',', $row['amenities']) : [];
                ?>

                <div class="category-card-modern"
                     style="border-left:4px solid <?php echo htmlspecialchars($row['color_code']); ?>;"
                     data-name="<?php echo htmlspecialchars(strtolower($row['category_name'])); ?>"
                     data-price="<?php echo (float)$row['price']; ?>"
                     data-avail="<?php echo $available; ?>">

                    <div class="card-header-modern">
                        <div>
                            <h3><?php echo htmlspecialchars($row['category_name']); ?></h3>
                            <div class="venue-pill">
                                <i class="fas fa-map-marker-alt" style="color:<?php echo htmlspecialchars($row['color_code']); ?>;"></i>
                                <span>
                                    Stadium: <strong><?php echo htmlspecialchars($row['venue_name'] . ' (' . $row['venue_city'] . ')'); ?></strong>
                                </span>
                            </div>
                            <p class="category-desc">
                                <?php echo htmlspecialchars($row['category_description'] ?: 'No description provided.'); ?>
                            </p>
                        </div>

                        <div class="card-actions">
                            <button class="btn-icon" onclick="openEditModal(<?php echo (int)$row['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete_id=<?php echo (int)$row['id']; ?>" class="btn-icon btn-danger-icon" onclick="return confirm('Delete this record?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>

                    <div class="card-body-modern">
                        <div class="price-section">
                            <span class="price-label">PRICE</span>
                            <span class="price-value" style="color:<?php echo htmlspecialchars($row['color_code']); ?>;">
                                Rs.<?php echo number_format($row['price'], 0); ?>
                            </span>
                        </div>

                        <div class="availability-section">
                            <div class="availability-stats">
                                <span>Availability: <?php echo $available; ?> / <?php echo $total_seats; ?></span>
                                <span class="occupancy-badge"><?php echo $occupancy; ?>% Occupied</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar" style="width:<?php echo $occupancy; ?>%; background:<?php echo htmlspecialchars($row['color_code']); ?>;"></div>
                            </div>
                        </div>

                        <?php if (!empty($amenities)): ?>
                            <div class="amenities-section">
                                <p class="amenities-title">AMENITIES:</p>
                                <ul class="amenities-list">
                                    <?php foreach ($amenities as $amenity): ?>
                                        <?php $amenity = trim($amenity); ?>
                                        <?php if ($amenity !== ''): ?>
                                            <li>
                                                <i class="fas fa-check-circle" style="color:<?php echo htmlspecialchars($row['color_code']); ?>;"></i>
                                                <?php echo htmlspecialchars($amenity); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="matches-info">
                            <i class="fas fa-map-marker-alt" style="color:<?php echo htmlspecialchars($row['color_code']); ?>;"></i>
                            <span><?php echo (int)$row['total_matches']; ?> Matches using this category</span>
                            <i class="fas fa-chevron-right matches-chevron" style="color:<?php echo htmlspecialchars($row['color_code']); ?>;"></i>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-th-large"></i>
                <h3>No Records Found</h3>
                <p>Get started by adding your first venue category.</p>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus-circle"></i> Add Record
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
        <a href="?page=<?php echo max(1, $current_page - 1); ?>" class="page-link <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
            <i class="fas fa-chevron-left"></i>
        </a>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $current_page === $i ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <a href="?page=<?php echo min($totalPages, $current_page + 1); ?>" class="page-link <?php echo $current_page >= $totalPages ? 'disabled' : ''; ?>">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add Venue Category</h3>
            <button class="close-btn" onclick="closeAddModal()">&times;</button>
        </div>

        <form method="post" class="modal-form">
            <div class="form-group">
                <label>Venue <span class="req">*</span></label>
                <select id="addVenue" name="venue_id" class="form-control" required></select>
            </div>

            <div class="form-group">
                <label>Category <span class="req">*</span></label>
                <select id="addCategory" name="category_id" class="form-control" required></select>
            </div>

            <div class="form-row">
                <div class="form-group half">
                    <label>Price (Rs.)</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" value="0">
                </div>
                <div class="form-group half">
                    <label>Total Seats</label>
                    <input type="number" name="total_seats" class="form-control" min="0" value="0">
                </div>
            </div>

            <div class="form-group">
                <label>Color Code</label>
                <div class="color-input-group">
                    <input type="color" id="addColorPicker" class="color-input" value="#10b981"
                           oninput="document.getElementById('addColorText').value=this.value">
                    <input type="text" id="addColorText" name="color_code" class="form-control" value="#10b981"
                           oninput="syncColorPicker('addColorPicker',this.value)">
                </div>
            </div>

            <div class="form-group">
                <label>Amenities</label>
                <input type="text" name="amenities" class="form-control" placeholder="e.g. AC, Food, Best View">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" name="add_category" class="btn btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Venue Category</h3>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>

        <div id="editLoader" style="text-align:center;padding:2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#10b981;"></i>
            <p style="margin-top:1rem;">Loading...</p>
        </div>

        <form id="editForm" class="modal-form" style="display:none;" onsubmit="submitEditForm(event)">
            <input type="hidden" name="id" id="editId">

            <div class="form-group">
                <label>Venue <span class="req">*</span></label>
                <select id="editVenue" name="venue_id" class="form-control" required></select>
            </div>

            <div class="form-group">
                <label>Category <span class="req">*</span></label>
                <select id="editCategory" name="category_id" class="form-control" required></select>
            </div>

            <div class="form-row">
                <div class="form-group half">
                    <label>Price</label>
                    <input type="number" id="editPrice" name="price" class="form-control" step="0.01" min="0">
                </div>
                <div class="form-group half">
                    <label>Total Seats</label>
                    <input type="number" id="editTotalSeats" name="total_seats" class="form-control" min="0">
                </div>
            </div>

            <div class="form-group">
                <label>Color Code</label>
                <div class="color-input-group">
                    <input type="color" id="editColorPicker" class="color-input" value="#10b981"
                           oninput="document.getElementById('editColorText').value=this.value">
                    <input type="text" id="editColorText" name="color_code" class="form-control" value="#10b981"
                           oninput="syncColorPicker('editColorPicker',this.value)">
                </div>
            </div>

            <div class="form-group">
                <label>Amenities</label>
                <input type="text" id="editAmenities" name="amenities" class="form-control">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" id="updateBtn" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function syncColorPicker(pickerId, hex) {
    if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        document.getElementById(pickerId).value = hex;
    }
}

function toggleProfileMenu() {
    document.getElementById('profileMenu').classList.toggle('show');
}

window.addEventListener('click', function(e) {
    const menu = document.getElementById('profileMenu');
    const avatar = document.querySelector('.admin-avatar');
    if (avatar && !avatar.contains(e.target)) {
        if (menu && menu.classList.contains('show')) {
            menu.classList.remove('show');
        }
    }
});

function openProfileModal() {
    alert('Profile settings coming soon!');
}

function openSettingsModal() {
    alert('System settings coming soon!');
}

const SELF_URL = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';
let venueCache = null;
let categoryCache = null;

function showToast(msg, type='success') {
    const t = document.getElementById('toastMsg');
    t.textContent = msg;
    t.className = 'toast-notify toast-' + type;
    t.style.display = 'block';
    t.style.opacity = '1';
    setTimeout(() => { t.style.opacity = '0'; }, 2500);
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}

function loadVenues(selectId, selectedId = '') {
    const sel = document.getElementById(selectId);
    if (!sel) return;

    if (venueCache) {
        populateSelect(sel, venueCache, selectedId, 'Select Venue');
        return;
    }

    fetch(SELF_URL + '?ajax_venues=1')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                venueCache = res.venues;
                populateSelect(sel, venueCache, selectedId, 'Select Venue');
            }
        });
}

function loadCategories(selectId, selectedId = '') {
    const sel = document.getElementById(selectId);
    if (!sel) return;

    if (categoryCache) {
        populateSelect(sel, categoryCache, selectedId, 'Select Category');
        return;
    }

    fetch(SELF_URL + '?ajax_categories=1')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                categoryCache = res.categories;
                populateSelect(sel, categoryCache, selectedId, 'Select Category');
            }
        });
}

function populateSelect(sel, items, selectedId, placeholder) {
    sel.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.text = item.city ? `${item.name} (${item.city})` : item.name;
        if (String(item.id) === String(selectedId)) {
            opt.selected = true;
        }
        sel.appendChild(opt);
    });
}

function openAddModal() {
    document.getElementById('addCategoryModal').style.display = 'flex';
    loadVenues('addVenue');
    loadCategories('addCategory');
}

function closeAddModal() {
    document.getElementById('addCategoryModal').style.display = 'none';
}

function openEditModal(id) {
    document.getElementById('editCategoryModal').style.display = 'flex';
    document.getElementById('editLoader').style.display = 'block';
    document.getElementById('editForm').style.display = 'none';

    fetch(SELF_URL + '?ajax_get=1&id=' + id)
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);

            const d = res.data;
            document.getElementById('editId').value = d.id;
            document.getElementById('editPrice').value = d.price;
            document.getElementById('editTotalSeats').value = d.total_seats;
            document.getElementById('editAmenities').value = d.amenities || '';
            document.getElementById('editColorPicker').value = d.color_code || '#10b981';
            document.getElementById('editColorText').value = d.color_code || '#10b981';

            loadVenues('editVenue', d.venue_id);
            loadCategories('editCategory', d.category_id);

            document.getElementById('editLoader').style.display = 'none';
            document.getElementById('editForm').style.display = 'block';
        })
        .catch(err => {
            showToast(err.message, 'error');
            closeEditModal();
        });
}

function closeEditModal() {
    document.getElementById('editCategoryModal').style.display = 'none';
}

function submitEditForm(e) {
    e.preventDefault();

    const btn = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.innerHTML = 'Updating...';

    const formData = new FormData(document.getElementById('editForm'));
    formData.append('ajax_update', '1');

    fetch(SELF_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = 'Update';

        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Update';
        showToast('Update failed', 'error');
    });
}

document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.category-card-modern').forEach(card => {
        const name = card.dataset.name || '';
        const desc = card.querySelector('.category-desc')?.textContent.toLowerCase() || '';
        card.style.display = (name.includes(q) || desc.includes(q)) ? '' : 'none';
    });
});

document.getElementById('sortSelect').addEventListener('change', function() {
    const by = this.value;
    const grid = document.getElementById('categoriesGrid');
    const cards = Array.from(grid.querySelectorAll('.category-card-modern'));

    cards.sort((a, b) => {
        if (by === 'name') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
        if (by === 'price') return parseFloat(b.dataset.price || 0) - parseFloat(a.dataset.price || 0);
        if (by === 'availability') return parseFloat(b.dataset.avail || 0) - parseFloat(a.dataset.avail || 0);
        return 0;
    });

    cards.forEach(card => grid.appendChild(card));
});

['addCategoryModal', 'editCategoryModal'].forEach(id => {
    const modal = document.getElementById(id);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    }
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const alertBox = document.querySelector('.alert');
    if (alertBox) {
        setTimeout(() => {
            alertBox.style.transition = "opacity 0.5s ease";
            alertBox.style.opacity = "0";

            setTimeout(() => {
                alertBox.remove();
            }, 500);
        }, 3000);
    }
});
</script>
</body>
</html>