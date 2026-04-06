<?php
session_start();
require_once 'connection.php';

// Dynamically determine the network IP for the QR code so mobiles can access it
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
  $host = getHostByName(getHostName());
  if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
    $host .= ':' . $_SERVER['SERVER_PORT'];
  }
}
$dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$dir = rtrim($dir, '/');
$base_url_for_qr = $protocol . $host . $dir . '/';

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

$user_id = (int) $_SESSION['user_id'];
$success_message = '';
$error_message = '';

/* =========================
   MARK NOTIFICATIONS AS READ
========================= */
if (isset($_GET['mark_notifications_read']) && $_GET['mark_notifications_read'] == '1') {
  mysqli_query($link, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
  header("Location: MyBooking.php");
  exit();
}

/* =========================
   HANDLE CANCELLATION REQUEST
========================= */
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
  $booking_id = (int) $_POST['booking_id'];

  $verify_query = "
        SELECT 
            b.id,
            b.booking_id,
            b.total_amount,
            b.payment_status,
            m.venue_id,
            m.match_date,
            m.match_time,
            m.status as match_status,
            t1.name AS team1_name,
            t2.name AS team2_name
        FROM bookings b
        JOIN matches m ON b.match_id = m.id
        JOIN teams t1 ON m.team1_id = t1.id
        JOIN teams t2 ON m.team2_id = t2.id
        WHERE b.id = $booking_id
          AND b.user_id = $user_id
          AND b.booking_status != 'cancelled'
        LIMIT 1
    ";
  $verify_result = mysqli_query($link, $verify_query);

  if ($verify_result && mysqli_num_rows($verify_result) > 0) {
    $booking_data = mysqli_fetch_assoc($verify_result);

    $match_datetime = strtotime($booking_data['match_date'] . ' ' . $booking_data['match_time']);
    $current_datetime = time();
    $match_status = strtolower($booking_data['match_status']);

    $match_date_only = $booking_data['match_date'];
    $current_date_only = date('Y-m-d');

    if ($match_date_only <= $current_date_only || $match_datetime <= $current_datetime || in_array($match_status, ['live', 'completed'])) {
      $error_message = "You cannot cancel tickets on or after the match day, or if the match is live/completed.";
    } else {
      $venue_id = (int) $booking_data['venue_id'];

      mysqli_begin_transaction($link);

      try {
        $update_query = "UPDATE bookings SET booking_status = 'cancelled' WHERE id = $booking_id";

        if (!mysqli_query($link, $update_query)) {
          throw new Exception("Error cancelling booking: " . mysqli_error($link));
        }

        $items_query = "SELECT category_id, quantity FROM booking_items WHERE booking_id = $booking_id";
        $items_result = mysqli_query($link, $items_query);

        if ($items_result) {
          while ($item = mysqli_fetch_assoc($items_result)) {
            $cat_id = (int) $item['category_id'];
            $qty = (int) $item['quantity'];

            $restore_seats_query = "
                            UPDATE venue_category
                            SET no_of_seats = no_of_seats + $qty
                            WHERE venue_id = $venue_id AND category_id = $cat_id
                        ";
            mysqli_query($link, $restore_seats_query);
          }
        }

        $notif_title = mysqli_real_escape_string($link, 'Booking Cancelled');
        $notif_message = mysqli_real_escape_string(
          $link,
          'Your refund will be processed in a few days and the seats you booked have been successfully cancelled.'
        );

        $notification_query = "
                    INSERT INTO notifications (user_id, type, title, message, is_read)
                    VALUES ($user_id, 'cancellation', '$notif_title', '$notif_message', 0)
                ";

        if (!mysqli_query($link, $notification_query)) {
          throw new Exception("Error adding notification: " . mysqli_error($link));
        }

        mysqli_commit($link);

        $success_message = "Booking cancelled successfully. Refund is in process and usually original payment method me 5-7 working days me credit ho jayega.";
      } catch (Exception $e) {
        mysqli_rollback($link);
        $error_message = $e->getMessage();
      }
    }
  } else {
    $error_message = "Invalid booking, or it has already been cancelled, or you don't have permission to cancel this booking.";
  }
}

/* =========================
   FETCH USER INFO
========================= */
$ticketCount = 0;
$userProfileImage = 'default.jpg';

$userIdForHeader = (int) $_SESSION['user_id'];

$ticketStmt = $link->prepare("SELECT COUNT(*) AS total FROM bookings WHERE user_id = ?");
$ticketStmt->bind_param("i", $userIdForHeader);
$ticketStmt->execute();
$ticketResult = $ticketStmt->get_result();
$ticketRow = $ticketResult->fetch_assoc();
$ticketCount = (int) ($ticketRow['total'] ?? 0);
$ticketStmt->close();

$imgStmt = $link->prepare("SELECT profile_image FROM users WHERE id = ?");
$imgStmt->bind_param("i", $userIdForHeader);
$imgStmt->execute();
$imgResult = $imgStmt->get_result();
$imgRow = $imgResult->fetch_assoc();
$userProfileImage = $imgRow['profile_image'] ?? 'default.jpg';
$imgStmt->close();

$usr_q = "SELECT name, email FROM users WHERE id = $user_id LIMIT 1";
$usr_r = mysqli_query($link, $usr_q);
$user = $usr_r ? mysqli_fetch_assoc($usr_r) : ['name' => 'Guest', 'email' => ''];

/* =========================
   FETCH NOTIFICATIONS
========================= */
$notifications = [];
$unreadNotifications = 0;

$notifCountQuery = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $user_id AND is_read = 0";
$notifCountResult = mysqli_query($link, $notifCountQuery);
if ($notifCountResult) {
  $notifCountRow = mysqli_fetch_assoc($notifCountResult);
  $unreadNotifications = (int) ($notifCountRow['total'] ?? 0);
}

$notifQuery = "
    SELECT id, title, message, is_read, created_at, type
    FROM notifications
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT 8
";
$notifResult = mysqli_query($link, $notifQuery);
if ($notifResult) {
  while ($notif = mysqli_fetch_assoc($notifResult)) {
    $notifications[] = $notif;
  }
}

/* =========================
   FETCH USER BOOKINGS
========================= */
$query = "
    SELECT 
        b.*,
        m.match_date,
        m.match_time,
        m.match_type,
        m.status as match_status,
        t1.name as team1_name,
        t2.name as team2_name,
        v.name as venue_name,
        v.city as venue_city
    FROM bookings b
    JOIN matches m ON b.match_id = m.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    JOIN venues v ON m.venue_id = v.id
    WHERE b.user_id = $user_id
    ORDER BY b.booking_time DESC
";

$result = mysqli_query($link, $query);
$bookings = [];
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
  }
}

/* =========================
   FETCH BOOKING ITEMS
========================= */
$items_query = "
    SELECT bi.booking_id, c.name as category_name, bi.seats_no
    FROM booking_items bi
    JOIN seat_categories c ON bi.category_id = c.id
";
$items_result = mysqli_query($link, $items_query);
$booking_items = [];
if ($items_result) {
  while ($item = mysqli_fetch_assoc($items_result)) {
    $b_id = $item['booking_id'];
    if (!isset($booking_items[$b_id])) {
      $booking_items[$b_id] = [];
    }
    $booking_items[$b_id][] = $item;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Bookings – Cricket Ticket Booking</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <!-- Qrious for instant Client-side QR Generation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
  <style>
    *,
    *::before,
    *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --bg-deep: #040814;
      --bg-mid: #070e1f;
      --gold: #f6c84c;
      --gold-dim: #fde68a;
      --green: #3fa34d;
      --green-glow: rgba(74, 222, 128, 0.22);
      --glass-bg: rgba(255, 255, 255, 0.04);
      --glass-bd: rgba(255, 255, 255, 0.09);
      --text-muted: #cbd5e1;
      --text-dim: #94a3b8;
      --danger: #f87171;
    }

    body {
      font-family: 'Inter', Arial, sans-serif;
      color: #fff;
      min-height: 100vh;
      background:
        radial-gradient(circle at top left, rgba(59, 130, 246, 0.10), transparent 25%),
        radial-gradient(circle at top right, rgba(168, 85, 247, 0.10), transparent 25%),
        radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.07), transparent 28%),
        linear-gradient(135deg, #040814 0%, #091120 50%, #050b18 100%);
    }

    .display-font {
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 2px;
    }

    .glass {
      background: linear-gradient(180deg, rgba(16, 31, 57, 0.8), rgba(8, 18, 35, 0.94));
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(16px);
    }

    header.sticky {
      top: 0 !important;
    }

    .page-wrap {
      max-width: 1100px;
      margin: 40px auto;
      padding: 0 20px;
    }

    .page-header {
      margin-bottom: 40px;
    }

    .page-header h1 {
      font-size: 36px;
      font-weight: 900;
      color: #fff;
      margin-bottom: 10px;
    }

    .page-header p {
      color: var(--text-muted);
      font-size: 16px;
    }

    .alert {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alert-success {
      background: rgba(63, 163, 77, 0.15);
      border: 1px solid rgba(74, 222, 128, 0.25);
      color: #4ade80;
    }

    .alert-error {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(248, 113, 113, 0.25);
      color: #f87171;
    }

    .bookings-list {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .glass-card {
      background: var(--glass-bg);
      backdrop-filter: blur(14px);
      border: 1px solid var(--glass-bd);
      border-radius: 20px;
      box-shadow: 0 14px 44px rgba(0, 0, 0, 0.2);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease, border-color 0.3s;
    }

    .glass-card:hover {
      border-color: rgba(255, 255, 255, 0.15);
      transform: translateY(-2px);
    }

    .card-top {
      padding: 24px 30px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      flex-wrap: wrap;
      gap: 20px;
    }

    .match-info {
      flex: 1;
    }

    .match-teams {
      font-size: 24px;
      font-weight: 900;
      letter-spacing: .5px;
      margin-bottom: 8px;
      text-transform: uppercase;
    }

    .match-tournament {
      color: var(--gold);
      font-size: 14px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 16px;
      display: inline-block;
      background: rgba(246, 200, 76, 0.15);
      padding: 4px 12px;
      border-radius: 20px;
    }

    .details-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-top: 16px;
    }

    .detail-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      color: var(--text-muted);
    }

    .detail-item svg {
      width: 18px;
      height: 18px;
      stroke: var(--text-dim);
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
      flex-shrink: 0;
    }

    .booking-meta {
      text-align: right;
      min-width: 200px;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }

    .status-confirmed {
      background: rgba(63, 163, 77, 0.15);
      border: 1px solid rgba(74, 222, 128, 0.25);
      color: #4ade80;
    }

    .status-cancelled {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(248, 113, 113, 0.25);
      color: #f87171;
    }

    .status-pending {
      background: rgba(246, 200, 76, 0.15);
      border: 1px solid rgba(246, 200, 76, 0.25);
      color: var(--gold);
    }

    .booking-amount {
      font-size: 26px;
      font-weight: 800;
      color: #fff;
      margin-bottom: 4px;
    }

    .booking-date {
      font-size: 12px;
      color: var(--text-dim);
    }

    .refund-note {
      margin-top: 12px;
      padding: 12px 14px;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(246, 200, 76, 0.12), rgba(34, 197, 94, 0.08));
      border: 1px solid rgba(246, 200, 76, 0.22);
      color: #fde68a;
      font-size: 12px;
      font-weight: 600;
      line-height: 1.6;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }

    .refund-label {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      margin-bottom: 6px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #facc15;
    }

    .refund-label i {
      font-size: 11px;
    }

    .btn-disabled {
      background: rgba(255, 255, 255, 0.06);
      color: #94a3b8;
      border: 1px solid rgba(255, 255, 255, 0.10);
      cursor: not-allowed;
      pointer-events: none;
    }

    .cancelled-text {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      color: #fca5a5;
      font-size: 13px;
      font-weight: 700;
      border-radius: 12px;
      background: rgba(239, 68, 68, 0.08);
      border: 1px solid rgba(248, 113, 113, 0.15);
    }

    .card-actions {
      padding: 16px 30px;
      background: rgba(0, 0, 0, 0.2);
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      border: none;
      cursor: pointer;
      transition: all .2s;
    }

    .btn svg {
      width: 16px;
      height: 16px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .btn-view {
      background: var(--gold);
      color: #000;
    }

    .btn-view:hover {
      background: var(--gold-dim);
      transform: translateY(-1px);
    }

    .btn-cancel {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
      border: 1px solid rgba(248, 113, 113, 0.3);
    }

    .btn-cancel:hover {
      background: rgba(239, 68, 68, 0.25);
    }

    .empty-state {
      text-align: center;
      padding: 80px 20px;
      background: var(--glass-bg);
      border: 1px dashed var(--glass-bd);
      border-radius: 20px;
    }

    .empty-state svg {
      width: 64px;
      height: 64px;
      stroke: var(--text-dim);
      margin-bottom: 20px;
      opacity: 0.5;
    }

    .empty-state h3 {
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 10px;
    }

    .empty-state p {
      color: var(--text-muted);
      margin-bottom: 24px;
    }

    .btn-primary {
      background: linear-gradient(135deg, #3fa34d, #4ade80);
      color: #fff;
      padding: 14px 28px;
      font-size: 16px;
    }

    .btn-primary:hover {
      box-shadow: 0 8px 20px rgba(74, 222, 128, 0.3);
      transform: translateY(-2px);
    }

    /* Notification Bell */
    .nav-action-wrap {
      position: relative;
    }

    .notification-btn {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      border-radius: 14px;
      border: 1px solid rgba(255, 255, 255, 0.10);
      background: rgba(255, 255, 255, 0.05);
      color: #fff;
      transition: 0.25s ease;
      cursor: pointer;
    }

    .notification-btn:hover {
      background: rgba(255, 255, 255, 0.10);
      transform: translateY(-1px);
    }

    .notification-btn i {
      font-size: 18px;
    }

    .notification-badge-dot {
      position: absolute;
      top: 6px;
      right: 6px;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 999px;
      background: #ef4444;
      color: white;
      font-size: 10px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
    }

    .notification-dropdown {
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      width: 360px;
      background: linear-gradient(180deg, rgba(16, 31, 57, 0.96), rgba(8, 18, 35, 0.98));
      border: 1px solid rgba(255, 255, 255, 0.10);
      border-radius: 18px;
      backdrop-filter: blur(20px);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
      overflow: hidden;
      opacity: 0;
      visibility: hidden;
      transform: translateY(8px);
      transition: all 0.22s ease;
      z-index: 80;
    }

    .notification-dropdown.show {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .notification-header {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .notification-header h4 {
      font-size: 15px;
      font-weight: 800;
      color: #fff;
    }

    .notification-header a {
      font-size: 12px;
      color: #93c5fd;
      text-decoration: none;
      font-weight: 700;
    }

    .notification-list {
      max-height: 360px;
      overflow-y: auto;
    }

    .notification-item {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      transition: background 0.2s ease;
    }

    .notification-item:hover {
      background: rgba(255, 255, 255, 0.04);
    }

    .notification-item.unread {
      background: rgba(59, 130, 246, 0.08);
    }

    .notification-item:last-child {
      border-bottom: none;
    }

    .notification-title {
      font-size: 14px;
      font-weight: 800;
      color: #fff;
      margin-bottom: 6px;
    }

    .notification-message {
      font-size: 13px;
      color: #cbd5e1;
      line-height: 1.5;
      margin-bottom: 8px;
    }

    .notification-time {
      font-size: 11px;
      color: #94a3b8;
    }

    .notification-empty {
      padding: 24px 18px;
      text-align: center;
      color: #94a3b8;
      font-size: 14px;
    }

    @media(max-width: 768px) {
      .card-top {
        flex-direction: column;
        gap: 24px;
      }

      .booking-meta {
        text-align: left;
      }

      .nav-links {
        display: none;
      }

      .notification-dropdown {
        width: 300px;
        right: -60px;
      }
    }

    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(30, 30, 30, 0.9);
      backdrop-filter: blur(8px);
      z-index: 9999;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal-content {
      width: 100%;
      max-width: 760px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: #fff;
    }

    .modal-header h2 {
      font-size: 24px;
      font-weight: 800;
    }

    .btn-close-modal {
      background: none;
      border: none;
      color: #fff;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .ticket-wrapper {
      position: relative;
      display: flex;
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    }

    .ticket-left {
      flex: 1;
      padding: 34px;
      color: #1a1a1a;
      position: relative;
    }

    .ticket-right {
      width: 250px;
      padding: 30px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border-left: 2px dashed #e2e8f0;
      position: relative;
    }

    .ticket-right::before,
    .ticket-right::after {
      content: '';
      width: 34px;
      height: 34px;
      background: rgba(30, 30, 30, 0.98);
      border-radius: 50%;
      position: absolute;
      left: -18px;
    }

    .ticket-right::before {
      top: -17px;
    }

    .ticket-right::after {
      bottom: -17px;
    }

    .t-brand {
      color: #16a34a;
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 24px;
    }

    .t-id {
      position: absolute;
      top: 34px;
      right: 30px;
      font-size: 14px;
      color: #94a3b8;
      font-family: monospace;
    }

    .t-title {
      font-size: 28px;
      font-weight: 900;
      color: #0f172a;
      margin-bottom: 8px;
      line-height: 1.2;
    }

    .t-date {
      font-size: 16px;
      font-weight: 500;
      color: #64748b;
      margin-bottom: 24px;
    }

    .t-venue {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 17px;
      font-weight: 600;
      color: #334155;
      margin-bottom: 30px;
    }

    .t-divider {
      height: 1px;
      background: #f1f5f9;
      margin-bottom: 20px;
    }

    .t-row {
      display: flex;
      gap: 40px;
    }

    .t-col {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .t-col span:first-child {
      font-size: 12px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .t-col span:last-child {
      font-size: 18px;
      font-weight: 800;
      color: #0f172a;
    }

    .t-col.paid span:last-child {
      color: #16a34a;
    }

    .t-qr-title {
      font-size: 14px;
      font-weight: 800;
      color: #334155;
      margin-bottom: 12px;
    }

    .t-qr-img {
      width: 140px;
      height: 140px;
      margin-bottom: 15px;
      border: 1px solid #e2e8f0;
      padding: 10px;
      border-radius: 8px;
      display: block;
    }

    .t-user {
      font-size: 13px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .download-wrapper {
      text-align: center;
      margin-top: 10px;
    }

    .btn-download {
      background: #f59e0b;
      color: #fff;
      border: none;
      padding: 14px 34px;
      border-radius: 8px;
      font-size: 17px;
      font-weight: 800;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: background 0.2s;
      box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);
    }

    .btn-download:hover {
      background: #d97706;
    }

    @media(max-width: 768px) {
      .ticket-wrapper {
        flex-direction: column;
      }

      .ticket-right {
        width: 100%;
        border-left: none;
        border-top: 2px dashed #e2e8f0;
        padding-top: 40px;
      }

      .ticket-right::before {
        top: -17px;
        left: 50%;
        transform: translateX(-50%);
      }

      .ticket-right::after {
        display: none;
      }

      .t-row {
        flex-wrap: wrap;
        gap: 20px;
      }

      .t-id {
        position: relative;
        top: 0;
        right: 0;
        margin-bottom: 10px;
        display: block;
      }
    }
  </style>

  <script>
    function confirmCancel(bookingId) {
      return confirm("Are you sure you want to cancel booking " + bookingId + "?\nRefund 5-7 working days me process ho jayega.");
    }

    function openTicketModal(btn) {
      const d = btn.dataset;
      document.getElementById('m-id').innerText = "ID: " + d.id;
      document.getElementById('m-title').innerText = d.title;
      document.getElementById('m-date').innerText = d.datetime;
      document.getElementById('m-venue').innerText = d.venue;
      document.getElementById('m-cat').innerText = d.category;
      document.getElementById('m-seats').innerText = d.seats || 'All general';
      document.getElementById('m-total').innerText = d.total;
      document.getElementById('m-user').innerText = d.user;

      // Generate QR Code with a full network URL so it opens the ticket on a scanned phone
      const baseUrlForQR = '<?php echo $base_url_for_qr; ?>';
      const ticketUrl = baseUrlForQR + 'ticket-view.php?booking_id=' + d.id;

      new QRious({
        element: document.getElementById('m-qr'),
        value: ticketUrl,
        size: 256,
        background: 'white',
        foreground: '#0f172a'
      });

      document.getElementById('ticketModal').classList.add('active');
    }

    function closeTicketModal() {
      document.getElementById('ticketModal').classList.remove('active');
    }

    function downloadTicketPDF() {
      const element = document.getElementById('ticket-element');
      const filename = document.getElementById('m-id').innerText.replace('ID: ', '') + '.pdf';
      const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: filename,
        image: { type: 'jpeg', quality: 1 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
      };

      html2pdf().set(opt).from(element).save();
    }
  </script>
</head>

<body>

  <header class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/75 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
      <a href="index.php" class="flex items-center gap-3">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 via-amber-400 to-emerald-400 text-slate-950">
          <i class="fas fa-ticket-alt"></i>
        </div>
        <div>
          <p class="display-font text-3xl leading-none text-white">
            CRICKET TICKET BOOKING
          </p>
          <p class="text-xs uppercase tracking-[0.32em] text-slate-300">Book the Game. Feel the Stadium.</p>
        </div>
      </a>

      <nav class="hidden items-center gap-7 text-sm font-medium text-slate-200 lg:flex">
        <a href="index.php#top" class="hover:text-white">Home</a>
        <a href="index.php#matches" class="hover:text-white">Matches</a>
        <a href="index.php#feedback" class="hover:text-white">Feedback</a>
      </nav>

      <div class="hidden items-center gap-3 lg:flex">
        <?php if (isset($_SESSION['user_id'])): ?>

          <div class="nav-action-wrap">
            <button id="notificationBtn" class="notification-btn" type="button" title="Notifications">
              <i class="fas fa-bell"></i>
              <?php if ($unreadNotifications > 0): ?>
                <span
                  class="notification-badge-dot"><?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?></span>
              <?php endif; ?>
            </button>

            <div id="notificationDropdown" class="notification-dropdown">
              <div class="notification-header">
                <h4>Notifications</h4>
                <?php if ($unreadNotifications > 0): ?>
                  <a href="MyBooking.php?mark_notifications_read=1">Mark all read</a>
                <?php endif; ?>
              </div>

              <div class="notification-list">
                <?php if (empty($notifications)): ?>
                  <div class="notification-empty">
                    No notifications yet.
                  </div>
                <?php else: ?>
                  <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                      <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                      <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                      <div class="notification-time"><?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="relative">
            <button id="profileDropdownBtn"
              class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white transition-all hover:bg-white/10 overflow-hidden ring-offset-2 ring-offset-slate-950 focus:ring-2 ring-amber-400/50">
              <?php if (!empty($userProfileImage) && $userProfileImage !== 'default.jpg'): ?>
                <img src="uploads/profiles/<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile"
                  class="w-full h-full object-cover">
              <?php else: ?>
                <i class="fas fa-user-circle text-2xl"></i>
              <?php endif; ?>
            </button>

            <div id="profileDropdown"
              class="absolute right-0 mt-3 w-56 glass origin-top-right rounded-2xl p-2 opacity-0 invisible transition-all duration-200 z-50 translate-y-2">
              <div class="px-3 py-2 border-b border-white/10 mb-2">
                <p class="text-sm font-bold text-white truncate">
                  <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                </p>
                <p class="text-[10px] uppercase tracking-widest text-slate-400 mt-0.5"><?php echo $ticketCount; ?> Tickets
                  Booked</p>
              </div>
              <a href="profile.php"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                <i class="fas fa-user-circle text-xs w-4"></i> Profile
              </a>
              <a href="MyBooking.php"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                <i class="fas fa-ticket-alt text-xs w-4"></i> My Bookings
              </a>
              <div class="my-1 border-t border-white/5"></div>
              <a href="logout.php"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                <i class="fas fa-sign-out-alt text-xs w-4"></i> Logout
              </a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php"
            class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">Login</a>
          <a href="register.php"
            class="rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-5 py-2 text-sm font-bold text-slate-950">Create
            Account</a>
        <?php endif; ?>
      </div>

      <button id="menuToggle"
        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white lg:hidden"
        type="button">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <div id="mobileMenu" class="hidden border-t border-white/10 bg-slate-950/95 px-4 py-4 lg:hidden">
      <div class="flex flex-col gap-3 text-sm font-medium text-slate-200">
        <a href="index.php#top" class="rounded-xl px-3 py-2 hover:bg-white/5">Home</a>
        <a href="index.php#matches" class="rounded-xl px-3 py-2 hover:bg-white/5">Matches</a>
        <a href="index.php#feedback" class="rounded-xl px-3 py-2 hover:bg-white/5">Feedback</a>
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="MyBooking.php" class="rounded-xl px-3 py-2 hover:bg-white/5">My Tickets</a>
          <a href="profile.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Profile</a>
          <a href="MyBooking.php?mark_notifications_read=1"
            class="rounded-xl px-3 py-2 hover:bg-white/5">Notifications<?php echo $unreadNotifications > 0 ? ' (' . $unreadNotifications . ')' : ''; ?></a>
          <a href="logout.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Logout</a>
        <?php else: ?>
          <a href="login.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Login</a>
          <a href="register.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <script>
    const profileBtn = document.getElementById('profileDropdownBtn');
    const profileMenu = document.getElementById('profileDropdown');
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (profileBtn && profileMenu) {
      profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();

        if (notificationDropdown) {
          notificationDropdown.classList.remove('show');
        }

        const isOpen = !profileMenu.classList.contains('invisible');

        if (!isOpen) {
          profileMenu.classList.remove('invisible', 'opacity-0', 'translate-y-2');
          profileMenu.classList.add('opacity-100', 'translate-y-0');
        } else {
          profileMenu.classList.add('invisible', 'opacity-0', 'translate-y-2');
          profileMenu.classList.remove('opacity-100', 'translate-y-0');
        }
      });
    }

    if (notificationBtn && notificationDropdown) {
      notificationBtn.addEventListener('click', (e) => {
        e.stopPropagation();

        if (profileMenu) {
          profileMenu.classList.add('invisible', 'opacity-0', 'translate-y-2');
          profileMenu.classList.remove('opacity-100', 'translate-y-0');
        }

        notificationDropdown.classList.toggle('show');
      });
    }

    document.addEventListener('click', () => {
      if (profileMenu) {
        profileMenu.classList.add('invisible', 'opacity-0', 'translate-y-2');
        profileMenu.classList.remove('opacity-100', 'translate-y-0');
      }

      if (notificationDropdown) {
        notificationDropdown.classList.remove('show');
      }
    });

    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');

    if (menuToggle && mobileMenu) {
      menuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
      });
    }
  </script>

  <div class="page-wrap">

    <div class="page-header">
      <h1>My Bookings</h1>
      <p>View and manage all your upcoming and past cricket ticket bookings.</p>
    </div>

    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success">
        <svg width="20" height="20" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
        <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
      <div class="alert alert-error">
        <svg width="20" height="20" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2.5" />
          <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
          <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
        </svg>
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24">
          <path d="M22 12A10 10 0 1112 2a10 10 0 0110 10zM15 9l-6 6M9 9l6 6" fill="none" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <h3>No tickets found</h3>
        <p>You haven't booked any matches yet. Ready to experience the thrill live?</p>
        <a href="index.php" class="btn btn-primary">Discover Matches</a>
      </div>
    <?php else: ?>
      <div class="bookings-list">
        <?php foreach ($bookings as $booking): ?>
          <?php
          $status = strtolower($booking['booking_status']);
          $status_class = 'status-pending';
          $status_icon = '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
          if ($status === 'confirmed') {
            $status_class = 'status-confirmed';
            $status_icon = '<polyline points="20 6 9 17 4 12"/>';
          } elseif ($status === 'cancelled') {
            $status_class = 'status-cancelled';
            $status_icon = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
          }

          $curr_items = isset($booking_items[$booking['id']]) ? $booking_items[$booking['id']] : [];
          $cat_names = [];
          $all_seats = [];
          foreach ($curr_items as $ci) {
            $cat_names[] = $ci['category_name'];
            if (!empty($ci['seats_no'])) {
              $all_seats[] = $ci['seats_no'];
            }
          }
          $cat_str = !empty($cat_names) ? implode(', ', array_unique($cat_names)) : 'General';
          $seats_str = !empty($all_seats) ? implode(', ', $all_seats) : '';

          $ticket_subtotal = (float) $booking['total_amount'];
          $convenience_fee = round($ticket_subtotal * 0.02);
          $gst_amount = round($ticket_subtotal * 0.18);
          $grand_total = $ticket_subtotal + $convenience_fee + $gst_amount;
          ?>
          <div class="glass-card">
            <div class="card-top">
              <div class="match-info">
                <div class="match-tournament"><?php echo htmlspecialchars($booking['match_type']); ?></div>
                <div class="match-teams">
                  <?php echo htmlspecialchars($booking['team1_name']); ?> vs
                  <?php echo htmlspecialchars($booking['team2_name']); ?>
                </div>

                <div class="details-grid">
                  <div class="detail-item">
                    <svg viewBox="0 0 24 24">
                      <rect x="3" y="4" width="18" height="18" rx="2" />
                      <line x1="16" y1="2" x2="16" y2="6" />
                      <line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    <span><?php echo date('D, d M Y', strtotime($booking['match_date'])); ?></span>
                  </div>
                  <div class="detail-item">
                    <svg viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                    <span><?php echo date('h:i A', strtotime($booking['match_time'])); ?> IST</span>
                  </div>
                  <div class="detail-item" style="grid-column: 1 / -1;">
                    <svg viewBox="0 0 24 24">
                      <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z" />
                      <circle cx="12" cy="10" r="3" />
                    </svg>
                    <span><?php echo htmlspecialchars($booking['venue_name']); ?>,
                      <?php echo htmlspecialchars($booking['venue_city']); ?></span>
                  </div>
                </div>
              </div>

              <div class="booking-meta">
                <div class="status-badge <?php echo $status_class; ?>">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round"><?php echo $status_icon; ?></svg>
                  <?php echo ucfirst($status); ?>
                </div>
                <div class="booking-amount">₹<?php echo number_format($grand_total); ?></div>
                <div class="booking-date">ID: <?php echo htmlspecialchars($booking['booking_id']); ?></div>
                <div class="booking-date" style="margin-top:4px;">Booked on
                  <?php echo date('d M Y', strtotime($booking['booking_time'])); ?>
                </div>

                <?php if ($status === 'cancelled'): ?>
                  <div class="refund-note">
                    <div class="refund-label">
                      <i class="fas fa-rotate-left"></i>
                      Refund In Process
                    </div>
                    5-7 working days me amount original payment method me credit ho jayega.
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="card-actions">
              <?php if ($status === 'confirmed' || $status === 'pending'): ?>
                <button type="button" class="btn btn-view" onclick="openTicketModal(this)"
                  data-id="<?php echo htmlspecialchars($booking['booking_id']); ?>"
                  data-title="<?php echo htmlspecialchars($booking['team1_name'] . ' vs ' . $booking['team2_name']); ?>"
                  data-datetime="<?php echo date('m/d/Y', strtotime($booking['match_date'])) . ' | ' . date('H:i', strtotime($booking['match_time'])); ?>"
                  data-venue="<?php echo htmlspecialchars($booking['venue_name'] . ', ' . $booking['venue_city']); ?>"
                  data-category="<?php echo htmlspecialchars($cat_str); ?>"
                  data-seats="<?php echo htmlspecialchars($seats_str); ?>"
                  data-total="₹<?php echo number_format($grand_total); ?>"
                  data-user="<?php echo htmlspecialchars(strtoupper($user['name'])); ?>">
                  <svg viewBox="0 0 24 24">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  View
                </button>

                <?php
                $match_datetime_stamp = strtotime($booking['match_date'] . ' ' . $booking['match_time']);
                $current_timestamp = time();
                $match_status_flag = strtolower($booking['match_status']);
                $match_date_str = $booking['match_date'];
                $current_date_str = date('Y-m-d');
                $is_cancellable = ($match_date_str > $current_date_str) && ($match_datetime_stamp > $current_timestamp) && !in_array($match_status_flag, ['live', 'completed']);
                ?>
                <?php if ($is_cancellable): ?>
                  <form method="POST" action="MyBooking.php"
                    onsubmit="return confirmCancel('<?php echo htmlspecialchars($booking['booking_id']); ?>')"
                    style="margin:0;">
                    <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">
                    <button type="submit" name="cancel_booking" class="btn btn-cancel">
                      <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                      </svg>
                      Cancel Booking
                    </button>
                  </form>
                <?php else: ?>
                  <button type="button" class="btn btn-disabled">
                    <svg viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="10" />
                      <line x1="15" y1="9" x2="9" y2="15" />
                      <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                    Cancellation Closed
                  </button>
                <?php endif; ?>
              <?php elseif ($status === 'cancelled'): ?>
                <a href="booking-confirmation.php?booking_id=<?php echo htmlspecialchars($booking['booking_id']); ?>"
                  class="btn btn-view" style="background: rgba(255,255,255,0.1); color: #fff;">
                  <svg viewBox="0 0 24 24">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  View Details
                </a>
                <button type="button" class="btn btn-disabled">
                  <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                  </svg>
                  Cancelled
                </button>
                <div class="cancelled-text">
                  <i class="fas fa-ticket-alt"></i>
                  Tickets Cancelled
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <div id="ticketModal" class="modal-overlay">
    <div class="modal-content">

      <div class="modal-header">
        <h2>My Tickets</h2>
        <button class="btn-close-modal" onclick="closeTicketModal()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
          Close
        </button>
      </div>

      <div class="ticket-wrapper" id="ticket-element">
        <div class="ticket-left">
          <div class="t-brand">Cricket Booking</div>
          <div class="t-id" id="m-id">ID: BK640291</div>

          <div class="t-title" id="m-title">England vs New Zealand</div>
          <div class="t-date" id="m-date">11/25/2023 | 19:30</div>

          <div class="t-venue">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <span id="m-venue">Eden Gardens, Kolkata</span>
          </div>

          <div class="t-divider"></div>

          <div class="t-row">
            <div class="t-col">
              <span>Category</span>
              <span id="m-cat">premium</span>
            </div>
            <div class="t-col">
              <span>Seats</span>
              <span id="m-seats">P3, P4</span>
            </div>
            <div class="t-col paid">
              <span>Total Paid</span>
              <span id="m-total">₹4000</span>
            </div>
          </div>
        </div>

        <div class="ticket-right">
          <div class="t-qr-title">Scan at Entry</div>
          <canvas id="m-qr" class="t-qr-img"></canvas>
          <div class="t-user" id="m-user">DEMO USER</div>
        </div>
      </div>

      <div class="download-wrapper">
        <button class="btn-download" onclick="downloadTicketPDF()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" y1="15" x2="12" y2="3" />
          </svg>
          Download PDF
        </button>
      </div>

    </div>
  </div>

</body>

</html>