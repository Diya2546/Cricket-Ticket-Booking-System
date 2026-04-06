<?php
// Start output buffer FIRST — connection.php has HTML comment text that
// would poison JSON responses if not buffered.
ob_start();

session_start();
if (!isset($_SESSION['admin_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

include '../connection.php';

$admin_id = $_SESSION['admin_id'];
$message  = '';

// ─── Helper: send clean JSON ──────────────────────────────────────────────────
function sendJson($data) {
    ob_clean();                              // discard any stray output (e.g. HTML comment from connection.php)
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

// ─── AJAX: Get single category for edit modal ─────────────────────────────────
if (isset($_GET['ajax_get']) && isset($_GET['id'])) {
    $id  = intval($_GET['id']);
    $res = mysqli_query($link, "SELECT * FROM seat_categories WHERE id = $id");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        sendJson(['success' => true, 'data' => $row]);
    } else {
        sendJson(['success' => false, 'message' => 'Category not found.']);
    }
}

// ─── AJAX: Get all venues for Venue Selection dropdown ────────────────────────
if (isset($_GET['ajax_venues'])) {
    $vres   = mysqli_query($link, "SELECT id, name, city FROM venues ORDER BY name ASC");
    $venues = [];
    if ($vres) {
        while ($v = mysqli_fetch_assoc($vres)) {
            $venues[] = $v;
        }
    }
    sendJson(['success' => true, 'venues' => $venues]);
}

// ─── AJAX: Update category (includes venue_id) ──────────────────────────────
if (isset($_POST['ajax_update'])) {
    $id          = intval($_POST['id']);
    $venue_id    = intval($_POST['venue_id'] ?? 0);
    $name        = mysqli_real_escape_string($link, trim($_POST['name']        ?? ''));
    $description = mysqli_real_escape_string($link, trim($_POST['description'] ?? ''));
    $color_code  = mysqli_real_escape_string($link, trim($_POST['color_code']  ?? '#10b981'));
    $price       = floatval($_POST['price'] ?? 0);
    $amenities   = mysqli_real_escape_string($link, trim($_POST['amenities']   ?? ''));

    if ($id && !empty($name)) {
        // Set venue_id (NULL if 0 / "All Venues" selected)
        $venue_val = ($venue_id > 0) ? $venue_id : 'NULL';
        $q = "UPDATE seat_categories
              SET venue_id=$venue_val, name='$name', description='$description',
                  color_code='$color_code', price='$price', amenities='$amenities'
              WHERE id = $id";
        if (mysqli_query($link, $q)) {
            sendJson(['success' => true, 'message' => 'Category updated successfully.']);
        } else {
            sendJson(['success' => false, 'message' => mysqli_error($link)]);
        }
    } else {
        sendJson(['success' => false, 'message' => 'Category name is required.']);
    }
}

// ─── Handle add category (regular form POST) ──────────────────────────────────
if (isset($_POST['add_category'])) {
    $venue_id    = intval($_POST['venue_id'] ?? 0);
    $name        = mysqli_real_escape_string($link, trim($_POST['name']        ?? ''));
    $description = mysqli_real_escape_string($link, trim($_POST['description'] ?? ''));
    $color_code  = mysqli_real_escape_string($link, trim($_POST['color_code']  ?? '#10b981'));
    $price       = floatval($_POST['price'] ?? 0);
    $amenities   = mysqli_real_escape_string($link, trim($_POST['amenities']   ?? ''));

    if (!empty($name)) {
        $venue_val = ($venue_id > 0) ? $venue_id : 'NULL';
        $q = "INSERT INTO seat_categories (venue_id, name, description, color_code, price, amenities)
              VALUES ($venue_val, '$name', '$description', '$color_code', '$price', '$amenities')";
        if (mysqli_query($link, $q)) {
            $_SESSION['message'] = '<div class="alert alert-success">✅ Category added successfully.</div>';
            ob_end_clean();
            header('Location: seat_categories.php');
            exit();
        } else {
            $message = '<div class="alert alert-danger">❌ Error: ' . mysqli_error($link) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">❌ Name is required.</div>';
    }
}

// ─── Handle delete ────────────────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    if ($del_id) {
        // Check if category is linked in match_seat_inventory
        $cr = mysqli_query($link, "SELECT COUNT(*) AS cnt FROM match_seat_inventory WHERE category_id = $del_id");
        $linked = $cr ? (int)mysqli_fetch_assoc($cr)['cnt'] : 0;

        if ($linked > 0) {
            // Cannot delete — linked to match inventory
            $_SESSION['message'] = '<div class="alert alert-danger">❌ Cannot delete: This category is linked to ' . $linked . ' match(es). Remove it from those matches first.</div>';
        } else {
            // Safe to delete — try
            if (mysqli_query($link, "DELETE FROM seat_categories WHERE id = $del_id")) {
                $_SESSION['message'] = '<div class="alert alert-success">✅ Category deleted successfully.</div>';
            } else {
                // Likely a FK constraint from another table
                $err = mysqli_error($link);
                $_SESSION['message'] = '<div class="alert alert-danger">❌ Delete failed: ' . htmlspecialchars($err) . '</div>';
            }
        }
        ob_end_clean();
        header('Location: seat_categories.php');
        exit();
    }
}

// ─── Session message ──────────────────────────────────────────────────────────
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// ─── Fetch categories with stats ──────────────────────────────────────────────
// Use sc.price directly for the base price. Venue comes from sc.venue_id -> venues.
$catQuery = "
    SELECT sc.*,
           v.name  AS venue_name,
           v.city  AS venue_city,
           COUNT(DISTINCT msi.match_id)       AS total_matches,
           COALESCE(SUM(msi.booked_seats), 0) AS tickets_sold,
           COALESCE(SUM(msi.total_seats),  0) AS total_seats
    FROM seat_categories sc
    LEFT JOIN venues v  ON sc.venue_id = v.id
    LEFT JOIN match_seat_inventory msi ON sc.id = msi.category_id
    GROUP BY sc.id
    ORDER BY sc.name ASC";
$catResult = mysqli_query($link, $catQuery);


// ─── Stats ────────────────────────────────────────────────────────────────────
$statsRow = mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COUNT(*) AS total_categories,
            COALESCE(SUM(booked_seats),0) AS total_tickets_sold,
            COUNT(DISTINCT match_id) AS active_matches
     FROM match_seat_inventory")) ?: [];

// count distinct seat_categories directly for total
$catCount = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(DISTINCT name) AS c FROM seat_categories"))['c'] ?? 0;

$bookingStats = mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COALESCE(SUM(CASE WHEN payment_status='success' THEN 1 ELSE 0 END),0) AS confirmed,
            COALESCE(SUM(CASE WHEN payment_status='pending' THEN 1 ELSE 0 END),0) AS pending_payment
     FROM bookings")) ?: ['confirmed' => 0, 'pending_payment' => 0];

// Flush the buffer so the HTML page starts cleanly
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seat Categories - Cricket Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/seat-categories.css?v=<?php echo time(); ?>">
    <style>
    /* ── Venue Pill Badge ─────────────────────────────────────────────────── */
    .venue-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.14);
        border-radius: 999px;
        padding: 4px 14px 4px 8px;
        font-size: 0.82rem;
        font-weight: 500;
        color: #d1d5db;
        margin-bottom: 0.5rem;
        margin-top: 0.25rem;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .venue-pill i {
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .venue-pill strong {
        font-weight: 600;
        color: #f3f4f6;
    }

    /* ── Price: PRICE label stacked above Rs.X,XXX ────────────────────────── */
    .price-section {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 2px !important;
        margin-bottom: 1.2rem;
    }
    .price-label {
        font-size: 0.78rem !important;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 500;
        color: #9ca3af !important;
        line-height: 1;
    }
    .price-value {
        font-size: 2.2rem !important;
        font-weight: 800 !important;
        line-height: 1.1 !important;
        letter-spacing: -0.5px;
    }

    /* ── Matches info row ─────────────────────────────────────────────────── */
    .matches-info {
        display: flex !important;
        align-items: center;
        gap: 0.7rem;
        color: #9ca3af;
        font-size: 0.87rem;
        margin-bottom: 1rem;
        padding: 0.7rem 1rem;
        background: rgba(255,255,255,0.04);
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.06);
    }
    .matches-info span { flex: 1; }
    .matches-chevron   { font-size: 0.72rem; opacity: 0.85; }
    </style>
</head>
<body>

<!-- ═══ Sidebar ═══════════════════════════════════════════════════════════════ -->
<div class="sidebar">
    <h2>Cricket Admin</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
    <a href="matches.php"><i class="fas fa-baseball-ball"></i> <span>Manage Matches</span></a>
    <a href="bookings.php"><i class="fas fa-ticket-alt"></i> <span>View Bookings</span></a>
    <a href="users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
    <a href="seat_categories.php" class="active"><i class="fas fa-th-large"></i> <span>Seat Categories</span></a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports &amp; Analytics</span></a>
    <a href="feedback.php"><i class="fas fa-star"></i> <span>Feedback</span></a>
    <div style="flex:1"></div>
</div>

<!-- ═══ Main Content ══════════════════════════════════════════════════════════ -->
<div class="main-content">

    <!-- Top Header -->
    <div class="top-header">
        <div class="header-left">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</h2>
            <p>Seat Categories &bull; Manage categories, pricing, and availability</p>
        </div>
        <div class="header-right">
            <div class="admin-avatar" onclick="toggleProfileMenu()">
                <?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'AD', 0, 2)); ?>
                <div class="profile-dropdown" id="profileMenu">
                    <a href="admin_profile.php">
                        <i class="fas fa-user-circle"></i> Profile
                    </a>
                    <a href="#" onclick="alert('Settings coming soon!');return false;"><i class="fas fa-cog"></i> Settings</a>
                    <hr>
                    <a href="logout.php" style="color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);">
                <i class="fas fa-th-large" style="color:#10b981;"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $catCount; ?></h3>
                <p>Total Categories</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.15);">
                <i class="fas fa-check-circle" style="color:#3b82f6;"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($bookingStats['confirmed']); ?></h3>
                <p>Confirmed</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.15);">
                <i class="fas fa-clock" style="color:#f59e0b;"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($bookingStats['pending_payment']); ?></h3>
                <p>Pending Payment</p>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Add New Category
        </button>
        <div class="action-right">
            <button class="btn btn-secondary" id="exportBtn">
                <i class="fas fa-download"></i> Export Categories
            </button>
            <input type="text" class="search-input" id="searchInput" placeholder="Search categories...">
            <select class="sort-select" id="sortSelect">
                <option value="name">Sort by Name</option>
                <option value="price">Sort by Price</option>
                <option value="availability">Sort by Availability</option>
            </select>
        </div>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($message)) echo $message; ?>

    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="fas fa-th-large"></i> Seat Categories</h2>
        <p>Manage seat categories, pricing, and availability for different match sections.</p>
    </div>

    <!-- Toast notification -->
    <div id="toastMsg" class="toast-notify" style="display:none;"></div>

    <!-- Categories Grid -->
    <div class="categories-grid" id="categoriesGrid">
        <?php
        if ($catResult && mysqli_num_rows($catResult) > 0):
            while ($row = mysqli_fetch_assoc($catResult)):
                $available    = $row['total_seats'] - $row['tickets_sold'];
                $totalSeats   = max((int)$row['total_seats'], 1);
                $occupancy    = round(($row['tickets_sold'] / $totalSeats) * 100, 1);
                $amenities    = !empty($row['amenities']) ? explode(',', $row['amenities']) : [];
                // Use the category's own base price (sc.price) — NOT match inventory price
                $displayPrice = (float)$row['price'];
                // Venue comes from sc.venue_id join
                $venueText    = !empty($row['venue_name'])
                                ? $row['venue_name'] . (!empty($row['venue_city']) ? ' ('.$row['venue_city'].')' : '')
                                : null;
                $color        = htmlspecialchars($row['color_code']);
        ?>
        <div class="category-card-modern"
             style="border-left:4px solid <?php echo $color; ?>;"
             data-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>"
             data-price="<?php echo $displayPrice; ?>"
             data-avail="<?php echo $available; ?>">

            <div class="card-header-modern">
                <div>
                    <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                    <?php if ($venueText): ?>
                    <!-- Pill badge flanking stadium name -->
                    <div class="venue-pill" style="--cat-color:<?php echo $color; ?>;">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Stadium: <strong><?php echo htmlspecialchars($venueText); ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <p class="category-desc"><?php echo htmlspecialchars($row['description'] ?: 'No description provided.'); ?></p>
                </div>
                <div class="card-actions">
                    <button class="btn-icon" title="Edit"
                            onclick="openEditModal(<?php echo (int)$row['id']; ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="?delete_id=<?php echo (int)$row['id']; ?>"
                       class="btn-icon btn-danger-icon" title="Delete"
                       onclick="return confirm('Delete this category?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>

            <div class="card-body-modern">
                <!-- Price stacked: PRICE label on top, Rs.X,XXX below -->
                <div class="price-section">
                    <span class="price-label">PRICE</span>
                    <span class="price-value" style="color:<?php echo $color; ?>;">
                        Rs.<?php echo number_format($displayPrice, 0); ?>
                    </span>
                </div>

                <div class="availability-section">
                    <div class="availability-stats">
                        <span>Availability: <?php echo number_format($available); ?> / <?php echo number_format($row['total_seats'] ?: 100); ?></span>
                        <span class="occupancy-badge"><?php echo $occupancy; ?>% Occupied</span>
                    </div>
                    <!-- <div class="progress-bar-container">
                        <div class="progress-bar-bg">
                            <div class="progress-bar" style="width:<?php //echo $occupancy; ?>%; background:<?php //echo $color; ?>;"></div>
                        </div>
                    </div> -->
                </div>

                <?php if (!empty($amenities)): ?>
                <div class="amenities-section">
                    <p class="amenities-title">AMENITIES:</p>
                    <ul class="amenities-list">
                        <?php foreach ($amenities as $amenity): ?>
                        <li>
                            <i class="fas fa-check-circle" style="color:<?php echo $color; ?>;"></i>
                            <?php echo htmlspecialchars(trim($amenity)); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="matches-info">
                    <i class="fas fa-map-marker-alt" style="color:<?php echo $color; ?>;"></i>
                    <span><?php echo (int)$row['total_matches']; ?> Matches using this category</span>
                    <i class="fas fa-chevron-right matches-chevron" style="color:<?php echo $color; ?>;"></i>
                </div>

                <!-- <div class="card-footer-modern">
                    <a href="category_matches.php?id=<?php //echo (int)$row['id']; ?>"
                       class="btn-view"
                       style="color:<?php //echo $color; ?>; border-color:<?php //echo $color; ?>;">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div> -->
            </div>
        </div>
        <?php
            endwhile;
        else:
        ?>
        <div class="empty-state">
            <i class="fas fa-th-large"></i>
            <h3>No Categories Found</h3>
            <p>Get started by adding your first seat category.</p>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus-circle"></i> Add Category
            </button>
        </div>
        <?php endif; ?>
    </div>
</div><!-- /main-content -->


<!-- ═══════════════════════════════════════════════════════
     ADD CATEGORY MODAL
     ═══════════════════════════════════════════════════════ -->
<div class="modal" id="addCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Category</h3>
            <button class="close-btn" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="post" class="modal-form" id="addForm">
            <!-- Venue Selection -->
            <div class="form-group" id="addVenueSelectGroup">
                <label>Venue Selection</label>
                <select id="addVenue" name="venue_id" class="form-control">
                    <option value="0">All Venues</option>
                </select>
            </div>

            <div class="form-group">
                <label>Category Name <span class="req">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="e.g., VIP, Premium, General">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Describe this seat category..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group half">
                    <label>Price (Rs.)</label>
                    <input type="number" name="price" class="form-control" value="100" step="0.01" min="0">
                </div>
                <div class="form-group half">
                    <label>Color Code</label>
                    <div class="color-input-group">
                        <input type="color" id="addColorPicker" class="color-input" value="#10b981"
                               oninput="document.getElementById('addColorText').value=this.value">
                        <input type="text" id="addColorText" name="color_code" class="form-control" value="#10b981"
                               oninput="syncColorPicker('addColorPicker',this.value)">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Amenities <small>(comma separated)</small></label>
                <input type="text" name="amenities" class="form-control"
                       placeholder="e.g., Standard Seats, Basic Facilities, Food">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" name="add_category" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════
     EDIT CATEGORY MODAL  — fully dynamic via AJAX
     ═══════════════════════════════════════════════════════ -->
<div class="modal" id="editCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Category</h3>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>

        <!-- Loading spinner -->
        <div id="editLoader" style="text-align:center;padding:2.5rem 1.5rem;">
            <i class="fas fa-spinner fa-spin" style="font-size:2.2rem;color:var(--accent);"></i>
            <p style="margin-top:1rem;color:var(--text-muted);">Loading category data…</p>
        </div>

        <!-- Edit form (revealed after data is loaded) -->
        <form id="editForm" class="modal-form" style="display:none;" onsubmit="submitEditForm(event)">
            <input type="hidden" id="editId" name="id">

            <!-- Venue Selection -->
            <div class="form-group" id="venueSelectGroup">
                <label>Venue Selection</label>
                <select id="editVenue" name="venue_id" class="form-control">
                    <option value="0">All Venues</option>
                </select>
            </div>

            <div class="form-group">
                <label>Category Name <span class="req">*</span></label>
                <input type="text" id="editName" name="name" class="form-control" required placeholder="Category name">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea id="editDescription" name="description" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group half">
                    <label>Price (Rs)</label>
                    <input type="number" id="editPrice" name="price" class="form-control" step="0.01" min="0">
                </div>
                <div class="form-group half">
                    <label>Color Code</label>
                    <div class="color-input-group">
                        <input type="color" id="editColorPicker" class="color-input" value="#10b981"
                               oninput="document.getElementById('editColorText').value=this.value">
                        <input type="text" id="editColorText" name="color_code" class="form-control" value="#10b981"
                               oninput="syncColorPicker('editColorPicker',this.value)">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Amenities <small>(comma separated)</small></label>
                <input type="text" id="editAmenities" name="amenities" class="form-control"
                       placeholder="e.g., Standard Seats, Basic Facilities, Food">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" id="updateBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Helpers ──────────────────────────────────────────── */
function syncColorPicker(pickerId, hex) {
    if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        document.getElementById(pickerId).value = hex;
    }
}

/* ── Profile dropdown ─────────────────────────────────── */
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

/* ── Toast ────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
    const t = document.getElementById('toastMsg');
    t.textContent = msg;
    t.className   = 'toast-notify toast-' + type;
    t.style.display  = 'block';
    t.style.opacity  = '1';
    setTimeout(() => { t.style.opacity = '0'; }, 3200);
    setTimeout(() => { t.style.display = 'none'; t.style.opacity = '1'; }, 3800);
}

/* ── Auto-hide PHP flash alerts ───────────────────────── */
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity .5s';
        a.style.opacity    = '0';
        setTimeout(() => a.remove(), 500);
    });
}, 3500);

/* ── Add Modal ────────────────────────────────────────── */
function openAddModal() {
    document.getElementById('addCategoryModal').style.display = 'flex';
    // Load venues dynamically into the add modal
    loadVenues('addVenue', '0');
}
function closeAddModal() {
    document.getElementById('addCategoryModal').style.display = 'none';
}

/* ── Edit Modal — AJAX-driven ─────────────────────────── */
// Build the absolute base URL once so fetch always hits the right file.
const SELF_URL = (function() {
    const a = document.createElement('a');
    a.href  = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';
    return a.href;
})();

// Venue list — fetched once and cached
let venueCache = null;

function loadVenues(selectId, selectedId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;

    if (venueCache) {
        populateVenueSelect(sel, venueCache, selectedId);
        return;
    }
    fetch(SELF_URL + '?ajax_venues=1')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                venueCache = res.venues;
                populateVenueSelect(sel, res.venues, selectedId);
            }
        })
        .catch(() => {/* venues are optional, ignore errors */});
}

function populateVenueSelect(sel, venues, selectedId) {
    // Reset to just the 'All Venues' placeholder, then add each venue
    sel.innerHTML = '<option value="0">All Venues</option>';
    const sid = String(selectedId || '').trim();
    venues.forEach(v => {
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.text  = v.name + (v.city ? ' (' + v.city + ')' : '');
        if (sid && sid !== '0' && sid === String(v.id)) opt.selected = true;
        sel.appendChild(opt);
    });
    // If nothing was selected, ensure placeholder is selected
    if (!sid || sid === '0') sel.selectedIndex = 0;
}

function openEditModal(id) {
    // Show modal with spinner, hide form
    const modal  = document.getElementById('editCategoryModal');
    const loader = document.getElementById('editLoader');
    const form   = document.getElementById('editForm');

    loader.style.display = 'block';
    form.style.display   = 'none';
    modal.style.display  = 'flex';

    // Fetch category data
    fetch(SELF_URL + '?ajax_get=1&id=' + id)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(res => {
            if (!res.success) throw new Error(res.message || 'Load failed.');

            const d = res.data;

            // Populate all fields
            document.getElementById('editId').value          = d.id          || '';
            document.getElementById('editName').value        = d.name        || '';
            document.getElementById('editDescription').value = d.description || '';
            document.getElementById('editPrice').value       = d.price       || '';
            document.getElementById('editAmenities').value   = d.amenities   || '';

            const color = (d.color_code && d.color_code.trim()) ? d.color_code.trim() : '#10b981';
            document.getElementById('editColorPicker').value = color;
            document.getElementById('editColorText').value   = color;

            // Load venue dropdown
            loadVenues('editVenue', d.venue_id || '');

            // Show form
            loader.style.display = 'none';
            form.style.display   = 'block';
        })
        .catch(err => {
            showToast('❌ ' + err.message, 'error');
            closeEditModal();
        });
}

function closeEditModal() {
    document.getElementById('editCategoryModal').style.display = 'none';
}

/* ── Submit edit via AJAX ─────────────────────────────── */
function submitEditForm(e) {
    e.preventDefault();

    const btn  = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';

    const data = new FormData(document.getElementById('editForm'));
    data.append('ajax_update', '1');

    fetch(SELF_URL, { method: 'POST', body: data })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(res => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Update Category';
            if (res.success) {
                showToast('✅ ' + res.message, 'success');
                closeEditModal();
                setTimeout(() => location.reload(), 900);
            } else {
                showToast('❌ ' + res.message, 'error');
            }
        })
        .catch(err => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Update Category';
            showToast('❌ ' + err.message, 'error');
        });
}

/* ── Close modals on backdrop click ──────────────────── */
['addCategoryModal','editCategoryModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});

/* ── Search ───────────────────────────────────────────── */
document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.category-card-modern').forEach(card => {
        const name = card.dataset.name || '';
        const desc = card.querySelector('.category-desc')?.textContent.toLowerCase() || '';
        card.style.display = (name.includes(q) || desc.includes(q)) ? '' : 'none';
    });
});

/* ── Sort ─────────────────────────────────────────────── */
document.getElementById('sortSelect').addEventListener('change', function() {
    const by   = this.value;
    const grid = document.getElementById('categoriesGrid');
    const cards = Array.from(grid.querySelectorAll('.category-card-modern'));
    cards.sort((a, b) => {
        if (by === 'name')         return (a.dataset.name  || '').localeCompare(b.dataset.name || '');
        if (by === 'price')        return parseFloat(b.dataset.price || 0) - parseFloat(a.dataset.price || 0);
        if (by === 'availability') return parseFloat(b.dataset.avail || 0) - parseFloat(a.dataset.avail || 0);
        return 0;
    });
    cards.forEach(c => grid.appendChild(c));
});

/* ── Export CSV ───────────────────────────────────────── */
document.getElementById('exportBtn').addEventListener('click', function() {
    const rows = [['Name','Description','Price','Amenities','Total Matches','Availability']];
    document.querySelectorAll('.category-card-modern').forEach(card => {
        const name    = card.querySelector('h3')?.textContent.trim() || '';
        const desc    = card.querySelector('.category-desc')?.textContent.trim() || '';
        const price   = card.dataset.price || '';
        const amen    = Array.from(card.querySelectorAll('.amenities-list li')).map(l => l.textContent.trim()).join('; ');
        const matches = card.querySelector('.matches-info span')?.textContent.trim() || '';
        const avail   = card.dataset.avail || '';
        rows.push([name, desc, price, amen, matches, avail]);
    });
    const csv = rows.map(r => r.map(v => '"' + v.replace(/"/g,'""') + '"').join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'seat_categories.csv';
    a.click();
});
</script>
</body>
</html>