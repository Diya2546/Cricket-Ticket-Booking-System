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

if (isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    
    // Get user details
    $query = "SELECT * FROM users WHERE id = $userId";
    $result = mysqli_query($link, $query);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        // Get user's bookings with match details
        $bookingsQuery = "
            SELECT b.*, 
                   m.match_date, m.match_time, m.match_type,
                   t1.name as team1_name, t2.name as team2_name,
                   v.name as venue_name
            FROM bookings b
            JOIN matches m ON b.match_id = m.id
            JOIN teams t1 ON m.team1_id = t1.id
            JOIN teams t2 ON m.team2_id = t2.id
            JOIN venues v ON m.venue_id = v.id
            WHERE b.user_id = $userId
            ORDER BY b.booking_time DESC
        ";
        $bookingsResult = mysqli_query($link, $bookingsQuery);
?>
<style>
.modal-header .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
.user-details-modal .content-container { padding: 5px; }
.empty-state { text-align: center; padding: 30px; color: #94a3b8; }
.empty-state i { font-size: 36px; margin-bottom: 12px; opacity: 0.6; }
</style>
<link rel="stylesheet" href="../css/user-details.css">

<div class="user-details-modal" style="background:var(--bg-dark); color:var(--text-light); border-radius:12px;">
    <div class="modal-header border-0 pb-0" style="padding: 20px;">
        <h5 class="modal-title" style="color: #fff; font-weight:800; font-size:22px;"><i class="fas fa-user-circle"></i> User Profile</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body" style="padding: 20px;">
        <div class="content-container">
            <!-- Profile Card -->
            <div class="card-section">
                <div class="profile-header">
                    <div class="user-avatar-large">
                        <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                    </div>
                    <div class="user-info-section">
                        <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="user-id">User ID: <?php echo $user['id']; ?></div>
                        <span class="join-badge">
                            <i class="fas fa-calendar-alt"></i> 
                            Joined: <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- User Information -->
            <div class="card-section">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>User Information</h3>
                </div>
                <table class="info-table">
                    <tr>
                        <th>Full Name</th>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone Number</th>
                        <td><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Booking History -->
            <div class="card-section">
                <div class="card-header">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>Booking History</h3>
                </div>
                <?php if (mysqli_num_rows($bookingsResult) > 0): ?>
                    <div class="table-responsive">
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Match</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = mysqli_fetch_assoc($bookingsResult)): 
                                    $statusClass = $booking['booking_status'] == 'confirmed' ? 'status-confirmed' : 
                                                  ($booking['booking_status'] == 'pending' ? 'status-pending' : 'status-cancelled');
                                    $ticket_subtotal = (float)$booking['total_amount'];
                                    $convenience_fee = round($ticket_subtotal * 0.02);
                                    $gst_amount      = round($ticket_subtotal * 0.18);
                                    $grand_total     = $ticket_subtotal + $convenience_fee + $gst_amount;  
                                ?>
                                    <tr>
                                        <td><span class="booking-id"><?php echo htmlspecialchars($booking['booking_id']); ?></span></td>
                                        <td><?php echo htmlspecialchars($booking['team1_name'] . ' vs ' . $booking['team2_name']); ?><br>
                                            <small style="color: var(--text-muted);"><?php echo $booking['match_type']; ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($booking['match_date'])); ?><br>
                                            <small style="color: var(--text-muted);"><?php echo date('h:i A', strtotime($booking['match_time'])); ?></small>
                                        </td>
                                        <td><span class="booking-amount">₹<?php echo number_format($grand_total, 2); ?></span></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>" style="padding: 4px 12px;">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No bookings found for this user.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
    } else {
        echo '<div style="padding: 40px; color: #ef4444; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i>
                <p>User not found.</p>
                <a href="users.php" style="color: var(--accent); text-decoration: none;">Back to Users</a>
              </div>';
    }
}
?>