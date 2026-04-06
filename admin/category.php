<?php
ob_start();
session_start();

if (!isset($_SESSION['admin_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

include '../connection.php';

$message = '';

// Fetch admin data for the header
$admin_id = (int)$_SESSION['admin_id'];
$admin_query = mysqli_query($link, "SELECT * FROM admins WHERE id = '$admin_id'");
$admin_data = mysqli_fetch_assoc($admin_query);
$admin_role = $admin_data['role'] ?? 'Administrator';
$admin_username = $admin_data['username'] ?? 'Admin';

function clean($link, $value)
{
    return mysqli_real_escape_string($link, trim($value));
}

/* ───────────── Add Category ───────────── */
if (isset($_POST['add_category'])) {
    $name = clean($link, $_POST['name'] ?? '');
    $description = clean($link, $_POST['description'] ?? '');

    if ($name === '') {
        $message = '<div class="alert alert-danger">❌ Category name is required.</div>';
    }
    else {
        $check = mysqli_query($link, "SELECT id FROM seat_categories WHERE LOWER(name) = LOWER('$name') LIMIT 1");

        if ($check && mysqli_num_rows($check) > 0) {
            $message = '<div class="alert alert-danger">❌ Category already exists.</div>';
        }
        else {
            $sql = "INSERT INTO seat_categories (name, description) VALUES ('$name', '$description')";
            if (mysqli_query($link, $sql)) {
                $_SESSION['message'] = '<div class="alert alert-success">✅ Category added successfully.</div>';
                ob_end_clean();
                header('Location: category.php');
                exit();
            }
            else {
                $message = '<div class="alert alert-danger">❌ Error: ' . mysqli_error($link) . '</div>';
            }
        }
    }
}

/* ───────────── Update Category ───────────── */
if (isset($_POST['update_category'])) {
    $id = intval($_POST['id'] ?? 0);
    $name = clean($link, $_POST['name'] ?? '');
    $description = clean($link, $_POST['description'] ?? '');

    if ($id <= 0 || $name === '') {
        $message = '<div class="alert alert-danger">❌ Valid category name is required.</div>';
    }
    else {
        $check = mysqli_query($link, "SELECT id FROM seat_categories WHERE LOWER(name) = LOWER('$name') AND id != $id LIMIT 1");

        if ($check && mysqli_num_rows($check) > 0) {
            $message = '<div class="alert alert-danger">❌ Another category with this name already exists.</div>';
        }
        else {
            $sql = "UPDATE seat_categories SET name='$name', description='$description' WHERE id=$id";
            if (mysqli_query($link, $sql)) {
                $_SESSION['message'] = '<div class="alert alert-success">✅ Category updated successfully.</div>';
                ob_end_clean();
                header('Location: category.php');
                exit();
            }
            else {
                $message = '<div class="alert alert-danger">❌ Error: ' . mysqli_error($link) . '</div>';
            }
        }
    }
}

/* ───────────── Delete Category ───────────── */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    if ($delete_id > 0) {
        $checkLink = mysqli_query($link, "SELECT id FROM venue_category WHERE category_id = $delete_id LIMIT 1");

        if ($checkLink && mysqli_num_rows($checkLink) > 0) {
            $_SESSION['message'] = '<div class="alert alert-danger">❌ Cannot delete. This category is already used in venue categories.</div>';
        }
        else {
            if (mysqli_query($link, "DELETE FROM seat_categories WHERE id = $delete_id")) {
                $_SESSION['message'] = '<div class="alert alert-success">✅ Category deleted successfully.</div>';
            }
            else {
                $_SESSION['message'] = '<div class="alert alert-danger">❌ Delete failed: ' . htmlspecialchars(mysqli_error($link)) . '</div>';
            }
        }

        ob_end_clean();
        header('Location: category.php');
        exit();
    }
}

/* ───────────── Fetch Categories (Unique by Name) ───────────── */
$categories = mysqli_query($link, "
    SELECT 
        MIN(sc.id) AS id,
        sc.name,
        MAX(sc.description) AS description,
        COUNT(vc.id) AS used_count
    FROM seat_categories sc
    LEFT JOIN venue_category vc ON vc.category_id = sc.id
    GROUP BY LOWER(sc.name), sc.name
    ORDER BY sc.name ASC
");

/* ───────────── Total Unique Categories ───────────── */
$countResult = mysqli_query($link, "SELECT COUNT(DISTINCT LOWER(name)) AS total FROM seat_categories");
$countRow = mysqli_fetch_assoc($countResult);
$totalCategories = $countRow['total'] ?? 0;

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Cricket Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/category.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="sidebar">
    <h2>Cricket Admin</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
    <a href="matches.php"><i class="fas fa-baseball-ball"></i><span>Manage Matches</span></a>
        <a href="bookings.php"><i class="fas fa-ticket-alt"></i> <span>Bookings</span></a>
    <a href="users.php"><i class="fas fa-users"></i><span>Manage Users</span></a>
    <a href="category.php" class="active"><i class="fas fa-list"></i><span>Manage Categories</span></a>
    <a href="venue_categories.php"><i class="fas fa-th-large"></i><span>Venue Categories</span></a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i><span>Reports & Analytics</span></a>
    <a href="feedback.php"><i class="fas fa-star"></i><span>Feedback</span></a>
    <div style="flex:1"></div>
</div>

<div class="main-content">

    <div class="top-header">
        <div class="header-left">
            <h2>Welcome, <?php echo htmlspecialchars($admin_username); ?>!</h2>
            <!-- <p><?php //echo htmlspecialchars($admin_role); ?></p> -->
        </div>
        <div class="header-right">

            <div class="admin-avatar" onclick="toggleProfileMenu()">
                <?php
$avatar_name = $_SESSION['admin_username'] ?? $admin_username ?? 'Admin';
echo strtoupper(substr($avatar_name, 0, 2));
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

    <div class="topbar-card">
        <div class="topbar-head"></div>
        <br>

        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search categories...">
        </div>

        <div class="filter-row">
            <div class="filter-chips">
                <button class="chip active" data-filter="all">All (<?php echo $totalCategories; ?>)</button>

                <?php
if ($categories && mysqli_num_rows($categories) > 0):
    mysqli_data_seek($categories, 0);
    while ($chip = mysqli_fetch_assoc($categories)):
?>
                    <button class="chip" data-filter="<?php echo strtolower(htmlspecialchars($chip['name'])); ?>">
                        <?php echo htmlspecialchars($chip['name']); ?>
                    </button>
                <?php
    endwhile;
endif;
?>
            </div>

            <div class="filter-action">
                <button class="add-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </div>

        <?php if (!empty($message))
    echo $message; ?>

        <br>
        <div class="section-title">All Categories</div>

        <div class="cards-grid" id="cardsGrid">
            <?php
if ($categories && mysqli_num_rows($categories) > 0):
    mysqli_data_seek($categories, 0);
    while ($row = mysqli_fetch_assoc($categories)):
?>
                <div class="category-card" data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>">
                    <div class="card-top">
                        <div>
                            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                        </div>
                        <div class="card-actions">
                            <button class="icon-btn edit-btn"
                                onclick="openEditModal('<?php echo (int)$row['id']; ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['description'])); ?>')">
                                <i class="fas fa-pen"></i>
                            </button>

                            <a href="category.php?delete_id=<?php echo (int)$row['id']; ?>"
                               class="icon-btn delete-btn"
                               onclick="return confirm('Delete this category?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <p class="label">Description</p>
                        <p class="description">
                            <?php echo htmlspecialchars($row['description'] ?: 'No description added yet.'); ?>
                        </p>

                        <div class="usage-row">
                            <span><i class="fas fa-layer-group"></i> Used in venue category</span>
                            <span class="usage-badge"><?php echo (int)$row['used_count']; ?> time(s)</span>
                        </div>
                    </div>
                </div>
            <?php
    endwhile;
else:
?>
                <p class="empty-text">No categories found.</p>
            <?php
endif; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add Category</h3>
            <button class="close-btn" onclick="closeAddModal()">&times;</button>
        </div>

        <form method="post" class="modal-form">
            <div class="form-group">
                <label>Category Name <span>*</span></label>
                <input type="text" name="name" required placeholder="e.g. VIP, Premium, General, Platinum">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Enter category description..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" name="add_category" class="btn-primary">Add Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-pen"></i> Edit Category</h3>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>

        <form method="post" class="modal-form">
            <input type="hidden" name="id" id="editId">

            <div class="form-group">
                <label>Category Name <span>*</span></label>
                <input type="text" name="name" id="editName" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editDescription"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="update_category" class="btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(id, name, description) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editDescription').value = description;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');

    if (e.target === addModal) addModal.style.display = 'none';
    if (e.target === editModal) editModal.style.display = 'none';
});

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

document.addEventListener("DOMContentLoaded", function () {
    const alertBox = document.querySelector('.alert');
    if (alertBox) {
        setTimeout(() => {
            alertBox.style.transition = "opacity 0.5s ease";
            alertBox.style.opacity = "0";
            setTimeout(() => alertBox.remove(), 500);
        }, 2000);
    }

    const searchInput = document.getElementById('searchInput');
    const cards = document.querySelectorAll('.category-card');
    const chips = document.querySelectorAll('.chip');

    function filterCards() {
        const search = searchInput.value.toLowerCase().trim();
        const activeChip = document.querySelector('.chip.active').dataset.filter;

        cards.forEach(card => {
            const name = card.dataset.name;
            const matchesSearch = name.includes(search);
            const matchesChip = activeChip === 'all' || name === activeChip;

            card.style.display = (matchesSearch && matchesChip) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterCards);
    }

    chips.forEach(chip => {
        chip.addEventListener('click', function() {
            chips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            filterCards();
        });
    });
});
</script>

</body>
</html>