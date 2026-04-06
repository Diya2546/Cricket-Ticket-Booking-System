<?php
session_start();
require_once 'connection.php';

/* =========================
   MARK NOTIFICATIONS AS READ
========================= */
if (isset($_GET['mark_notifications_read']) && $_GET['mark_notifications_read'] == '1' && isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    mysqli_query($link, "UPDATE notifications SET is_read = 1 WHERE user_id = $uid");
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit();
}

$notifications = [];
$unreadNotifications = 0;
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $notifCountQuery = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $uid AND is_read = 0";
    $notifCountResult = mysqli_query($link, $notifCountQuery);
    if ($notifCountResult) {
      $notifCountRow = mysqli_fetch_assoc($notifCountResult);
      $unreadNotifications = (int) ($notifCountRow['total'] ?? 0);
    }
    
    $notifQuery = "SELECT id, title, message, is_read, created_at, type FROM notifications WHERE user_id = $uid ORDER BY created_at DESC LIMIT 8";
    $notifResult = mysqli_query($link, $notifQuery);
    if ($notifResult) {
      while ($notif = mysqli_fetch_assoc($notifResult)) {
        $notifications[] = $notif;
      }
    }
}

require_once 'models/Booking.php';

// Validate booking_id param (booking.php sends ?booking_id=...)
if (!isset($_GET['booking_id'])) {
  header('Location: index.php');
  exit();
}

$booking_code = trim($_GET['booking_id']);

// Fetch booking via model helper
$bookingModel = new Booking($link);
$booking = $bookingModel->getBookingByCode($booking_code);

if (!$booking) {
  header('Location: index.php');
  exit();
}

// Get the user ID from the booking itself (so it works even if not logged in on mobile)
$user_id = (int) $booking['user_id'];

$ticketCount = 0;
$userProfileImage = 'default.jpg';
$ticketStmt = $link->prepare("SELECT COUNT(*) AS total FROM bookings WHERE user_id = ?");
$ticketStmt->bind_param("i", $user_id);
$ticketStmt->execute();
$ticketRow = $ticketStmt->get_result()->fetch_assoc();
$ticketCount = (int) ($ticketRow['total'] ?? 0);
$ticketStmt->close();

$imgStmt = $link->prepare("SELECT profile_image FROM users WHERE id = ?");
$imgStmt->bind_param("i", $user_id);
$imgStmt->execute();
$imgRow = $imgStmt->get_result()->fetch_assoc();
$userProfileImage = $imgRow['profile_image'] ?? 'default.jpg';
$imgStmt->close();

// Fetch line items
$items = $bookingModel->getBookingItems((int) $booking['id']);

// Fetch payment info
$pay_q = "SELECT * FROM payments WHERE booking_id = {$booking['id']} ORDER BY id DESC LIMIT 1";
$pay_r = mysqli_query($link, $pay_q);
$payment = $pay_r ? mysqli_fetch_assoc($pay_r) : null;

// Fetch user info
$usr_q = "SELECT name, email FROM users WHERE id = $user_id LIMIT 1";
$usr_r = mysqli_query($link, $usr_q);
$user = $usr_r ? mysqli_fetch_assoc($usr_r) : ['name' => 'Guest', 'email' => ''];

// Compute fee breakdown (same formula used in booking.php JS)
$ticket_subtotal = (float) $booking['total_amount'];
$convenience_fee = round($ticket_subtotal * 0.02);
$gst_amount = round($ticket_subtotal * 0.18);
$grand_total = $ticket_subtotal + $convenience_fee + $gst_amount;

// Cancellation deadline display
$deadline_display = '';
if (!empty($booking['cancellation_deadline'])) {
  $deadline_display = date('d M Y, h:i A', strtotime($booking['cancellation_deadline']));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking Confirmed – Cricket Ticket Booking</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .display-font {
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 0.04em;
    }

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

    /* ─── STEPS ─── */
    .steps-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 22px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
    }

    .step {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255, 255, 255, 0.42);
      font-size: 16px;
      font-weight: 600;
    }

    .step-number {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      background: rgba(255, 255, 255, 0.09);
      color: #fff;
    }

    .step.done {
      color: var(--text-muted);
    }

    .step.done .step-number {
      background: rgba(63, 163, 77, 0.25);
      color: #4ade80;
    }

    .step.active {
      color: #fff;
    }

    .step.active .step-number {
      background: var(--gold);
      color: #111;
    }

    .step-arrow {
      color: rgba(255, 255, 255, 0.28);
      font-size: 20px;
    }

    /* ─── WRAP ─── */
    .page-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 20px 60px;
    }

    /* ─── SUCCESS BANNER ─── */
    .success-banner {
      background: linear-gradient(135deg, rgba(63, 163, 77, 0.18), rgba(34, 197, 94, 0.10));
      border: 1px solid rgba(74, 222, 128, 0.28);
      border-radius: 24px;
      padding: 32px 36px;
      display: flex;
      align-items: center;
      gap: 24px;
      margin-bottom: 36px;
      flex-wrap: wrap;
    }

    .success-icon {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3fa34d, #78e06d);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 0 32px rgba(74, 222, 128, 0.35);
      animation: pulse-green 2.4s infinite;
    }

    @keyframes pulse-green {

      0%,
      100% {
        box-shadow: 0 0 32px rgba(74, 222, 128, 0.35);
      }

      50% {
        box-shadow: 0 0 52px rgba(74, 222, 128, 0.55);
      }
    }

    .success-icon svg {
      width: 36px;
      height: 36px;
      stroke: #fff;
      fill: none;
      stroke-width: 3;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .success-text h1 {
      font-size: 30px;
      font-weight: 900;
      margin-bottom: 6px;
    }

    .success-text p {
      color: var(--text-muted);
      font-size: 16px;
    }

    .success-text .booking-ref {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.07);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 10px;
      padding: 6px 14px;
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 1px;
      margin-top: 10px;
      color: var(--gold);
    }

    /* ─── GRID ─── */
    .main-grid {
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 26px;
      align-items: start;
    }

    @media(max-width:900px) {
      .main-grid {
        grid-template-columns: 1fr;
      }
    }

    /* ─── GLASS CARD ─── */
    .glass-card {
      background: var(--glass-bg);
      backdrop-filter: blur(14px);
      border: 1px solid var(--glass-bd);
      border-radius: 24px;
      box-shadow: 0 14px 44px rgba(0, 0, 0, 0.38);
      overflow: hidden;
    }

    .card-header {
      padding: 20px 28px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.07);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .card-header h2 {
      font-size: 19px;
      font-weight: 800;
    }

    .card-header .header-icon {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.07);
      flex-shrink: 0;
    }

    .card-header .header-icon svg {
      width: 20px;
      height: 20px;
      stroke: #fff;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .card-body {
      padding: 26px 28px;
    }

    /* ─── MATCH DETAIL ─── */
    .match-teams {
      font-size: 26px;
      font-weight: 900;
      letter-spacing: .5px;
      margin-bottom: 6px;
    }

    .match-type-badge {
      display: inline-block;
      background: rgba(246, 200, 76, 0.15);
      border: 1px solid rgba(246, 200, 76, 0.30);
      color: var(--gold);
      font-size: 12px;
      font-weight: 700;
      border-radius: 20px;
      padding: 3px 12px;
      margin-bottom: 20px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .detail-rows {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .detail-row {
      display: flex;
      align-items: center;
      gap: 14px;
      font-size: 15px;
    }

    .detail-row .d-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .detail-row .d-icon svg {
      width: 18px;
      height: 18px;
      stroke: var(--text-muted);
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .detail-row .d-label {
      color: var(--text-dim);
      font-size: 13px;
      margin-bottom: 1px;
    }

    .detail-row .d-value {
      color: #fff;
      font-weight: 600;
    }

    /* ─── SEAT ITEMS ─── */
    .seat-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      background: rgba(63, 163, 77, 0.08);
      border: 1px solid rgba(74, 222, 128, 0.16);
      border-radius: 14px;
      padding: 14px 18px;
      margin-bottom: 12px;
    }

    .seat-item:last-child {
      margin-bottom: 0;
    }

    .seat-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.10);
      border-radius: 8px;
      padding: 4px 12px;
      font-size: 13px;
      font-weight: 700;
      color: #e2e8f0;
      margin-right: 4px;
      margin-bottom: 4px;
      display: inline-block;
    }

    .seats-list {
      flex: 1;
    }

    .seat-cat-name {
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 6px;
    }

    .seat-numbers {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
    }

    .seat-qty-price {
      text-align: right;
      flex-shrink: 0;
    }

    .seat-qty {
      color: var(--text-muted);
      font-size: 13px;
      margin-bottom: 4px;
    }

    .seat-amount {
      font-size: 18px;
      font-weight: 800;
      color: #4ade80;
    }

    /* ─── PRICE BREAKDOWN ─── */
    .price-table {
      width: 100%;
      border-collapse: collapse;
    }

    .price-table td {
      padding: 10px 0;
      font-size: 15px;
    }

    .price-table td:last-child {
      text-align: right;
      font-weight: 600;
    }

    .price-table .muted {
      color: var(--text-muted);
    }

    .price-divider {
      border: none;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      margin: 10px 0;
    }

    .total-row td {
      font-size: 20px;
      font-weight: 900;
      padding-top: 16px;
    }

    .total-row td:last-child {
      color: var(--gold);
    }

    /* ─── PAYMENT BADGE ─── */
    .payment-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 12px;
      padding: 8px 16px;
      font-size: 14px;
      font-weight: 700;
    }

    .payment-badge.success {
      background: rgba(63, 163, 77, 0.15);
      border: 1px solid rgba(74, 222, 128, 0.25);
      color: #4ade80;
    }

    .payment-badge.pending {
      background: rgba(246, 200, 76, 0.12);
      border: 1px solid rgba(246, 200, 76, 0.28);
      color: var(--gold);
    }

    .badge-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }

    .dot-green {
      background: #4ade80;
    }

    .dot-gold {
      background: var(--gold);
    }

    /* ─── SIDEBAR STICKY ─── */
    .sidebar {
      display: flex;
      flex-direction: column;
      gap: 22px;
      position: sticky;
      top: 20px;
    }

    /* ─── USER CARD ─── */
    .user-row {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .user-avatar {
      width: 50px;
      height: 50px;
      border-radius: 14px;
      background: linear-gradient(135deg, #3b82f6, #7c3aed);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      font-weight: 800;
      color: #fff;
      flex-shrink: 0;
    }

    .user-name {
      font-size: 17px;
      font-weight: 700;
    }

    .user-email {
      color: var(--text-muted);
      font-size: 13px;
      margin-top: 2px;
    }

    /* ─── INFO BOX ─── */
    .info-box {
      background: rgba(246, 200, 76, 0.09);
      border: 1px solid rgba(246, 200, 76, 0.22);
      border-radius: 18px;
      padding: 20px 22px;
    }

    .info-box h4 {
      font-size: 15px;
      font-weight: 800;
      color: var(--gold);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .info-box ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 9px;
    }

    .info-box ul li {
      font-size: 13px;
      color: #fde68a;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      line-height: 1.5;
    }

    .info-box ul li::before {
      content: "•";
      color: var(--gold);
      font-size: 16px;
      flex-shrink: 0;
      margin-top: -1px;
    }

    /* ─── ACTION BUTTONS ─── */
    .action-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn {
      flex: 1;
      min-width: 130px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px 20px;
      border-radius: 14px;
      font-size: 15px;
      font-weight: 700;
      text-decoration: none;
      border: none;
      cursor: pointer;
      transition: transform .22s, box-shadow .22s;
    }

    .btn svg {
      width: 18px;
      height: 18px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2.2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .btn:hover {
      transform: translateY(-2px);
    }

    .btn-print {
      background: linear-gradient(90deg, #2f8f54, #7ad46f);
      color: #fff;
      box-shadow: 0 8px 24px rgba(74, 222, 128, 0.20);
    }

    .btn-print:hover {
      box-shadow: 0 14px 32px rgba(74, 222, 128, 0.30);
    }

    .btn-bookings {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.14);
      color: #fff;
    }

    .btn-bookings:hover {
      background: rgba(255, 255, 255, 0.12);
    }

    .btn-home {
      background: rgba(246, 200, 76, 0.12);
      border: 1px solid rgba(246, 200, 76, 0.25);
      color: var(--gold);
    }

    .btn-home:hover {
      background: rgba(246, 200, 76, 0.20);
    }

    /* ─── PRINT ─── */
    @media print {
      body {
        background: #fff !important;
        color: #000 !important;
      }

      .top-bar,
      .steps-wrap,
      .action-row,
      .sidebar .glass-card:not(.print-show) {
        display: none !important;
      }

      .main-grid {
        grid-template-columns: 1fr !important;
      }

      .glass-card {
        border: 1px solid #ccc !important;
        background: #fff !important;
        box-shadow: none !important;
        color: #000 !important;
      }
    }

    /* ─── CONFETTI CANVAS ─── */
    #confettiCanvas {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 9999;
    }

    @media(max-width:600px) {
      .success-banner {
        padding: 22px 20px;
      }

      .success-text h1 {
        font-size: 22px;
      }

      .card-body {
        padding: 20px 18px;
      }

      .match-teams {
        font-size: 20px;
      }

      .btn {
        font-size: 14px;
        padding: 12px 14px;
      }
    }
  
    /* Notification Bell */
    .nav-action-wrap { position: relative; }
    .notification-btn {
      position: relative; display: flex; align-items: center; justify-content: center;
      width: 44px; height: 44px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.10);
      background: rgba(255, 255, 255, 0.05); color: #fff; transition: 0.25s ease; cursor: pointer;
    }
    .notification-btn:hover { background: rgba(255, 255, 255, 0.10); transform: translateY(-1px); }
    .notification-btn i { font-size: 18px; }
    .notification-badge-dot {
      position: absolute; top: 6px; right: 6px; min-width: 18px; height: 18px; padding: 0 5px;
      border-radius: 999px; background: #ef4444; color: white; font-size: 10px; font-weight: 800;
      display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
    }
    .notification-dropdown {
      position: absolute; top: calc(100% + 12px); right: 0; width: 360px;
      background: linear-gradient(180deg, rgba(16, 31, 57, 0.96), rgba(8, 18, 35, 0.98));
      border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 18px; backdrop-filter: blur(20px);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35); overflow: hidden; opacity: 0; visibility: hidden;
      transform: translateY(8px); transition: all 0.22s ease; z-index: 80;
    }
    .notification-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
    .notification-header {
      padding: 16px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      display: flex; justify-content: space-between; align-items: center;
    }
    .notification-header h4 { font-size: 15px; font-weight: 700; color: #fff; margin: 0; font-family: 'Inter', sans-serif; }
    .notification-header a { font-size: 12px; font-weight: 600; color: #3b82f6; text-decoration: none; transition: color 0.2s; }
    .notification-header a:hover { color: #60a5fa; }
    .notification-list { max-height: 400px; overflow-y: auto; }
    .notification-list::-webkit-scrollbar { width: 6px; }
    .notification-list::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 10px; }
    .notification-item { padding: 16px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); transition: background 0.2s; }
    .notification-item:last-child { border-bottom: none; }
    .notification-item:hover { background: rgba(255, 255, 255, 0.03); }
    .notification-item.unread { background: rgba(59, 130, 246, 0.06); }
    .notification-title { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 6px; }
    .notification-message { font-size: 13px; color: #cbd5e1; line-height: 1.5; margin-bottom: 8px; }
    .notification-time { font-size: 11px; font-weight: 600; color: #64748b; }
    .notification-empty { padding: 40px 20px; text-align: center; color: #94a3b8; font-size: 14px; font-weight: 500; }
    @media(max-width: 768px) {
      .notification-dropdown { position: fixed; top: 70px; right: 16px; left: 16px; width: auto; }
    }

</style>
</head>

<body>

  <canvas id="confettiCanvas"></canvas>

  <!-- TOP BAR -->
  <header class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/75 backdrop-blur-xl"
    style="font-family: 'Plus Jakarta Sans', sans-serif;">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8"
      style="padding-top: 1rem; padding-bottom: 1rem;">
      <a href="index.php" class="flex items-center gap-3" style="text-decoration: none;">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 via-amber-400 to-emerald-400 text-slate-950">
          <i class="fas fa-ticket-alt"></i>
        </div>
        <div>
          <p class="display-font text-3xl leading-none text-white m-0">Cricket Ticket Booking</p>
          <p class="text-xs uppercase tracking-[0.32em] text-slate-300">Book the Game. Feel the Stadium.</p>
        </div>
      </a>

      <nav class="hidden items-center gap-7 text-sm font-medium text-slate-200 lg:flex" style="margin: 0;">
        <a href="index.php" class="hover:text-white" style="text-decoration: none;">Home</a>
        <a href="matches.php" class="hover:text-white" style="text-decoration: none;">Matches</a>
        <a href="feedback.php" class="hover:text-white" style="text-decoration: none;">Feedback</a>
      </nav>

      <div class="hidden items-center gap-3 lg:flex">
        <?php if (isset($_SESSION['user_id'])): ?>
          <div class="relative" style="position: relative;">
            <button id="profileDropdownBtn"
              class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white transition-all hover:bg-white/10 overflow-hidden ring-offset-2 ring-offset-slate-950 focus:ring-2 ring-amber-400/50"
              style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
              <?php if (!empty($userProfileImage) && $userProfileImage !== 'default.jpg'): ?>
                <img src="uploads/profiles/<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile"
                  class="w-full h-full object-cover">
              <?php else: ?>
                <i class="fas fa-user-circle text-2xl"></i>
              <?php endif; ?>
            </button>

            <div id="profileDropdown"
              class="absolute right-0 mt-3 w-56 origin-top-right rounded-2xl p-2 opacity-0 invisible transition-all duration-200 z-50 translate-y-2"
              style="background: rgba(16, 31, 57, 0.95); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 24px 60px rgba(0,0,0,0.5);">
              <div class="px-3 py-2 border-b border-white/10 mb-2">
                <p class="text-sm font-bold text-white truncate">
                  <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                </p>
                <p class="text-[10px] uppercase tracking-widest text-slate-400 mt-0.5"><?php echo $ticketCount; ?> Tickets
                  Booked</p>
              </div>
              <a href="profile.php"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors"
                style="text-decoration: none;">
                <i class="fas fa-user-circle text-xs w-4"></i> Profile
              </a>
              <a href="MyBooking.php"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors"
                style="text-decoration: none;">
                <i class="fas fa-ticket-alt text-xs w-4"></i> My Bookings
              </a>
              <div class="my-1 border-t border-white/5"></div>
              <a href="logout.php"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-red-400 hover:bg-red-500/10 transition-colors"
                style="text-decoration: none;">
                <i class="fas fa-sign-out-alt text-xs w-4"></i> Logout
              </a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php"
            class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5"
            style="text-decoration: none; padding: 0.5rem 1rem;">Login</a>
          <a href="register.php"
            class="rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-5 py-2 text-sm font-bold text-slate-950"
            style="text-decoration: none; padding: 0.5rem 1.25rem;">Create Account</a>
        <?php endif; ?>
      </div>

      <button id="menuToggle"
        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white lg:hidden"
        type="button" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <div id="mobileMenu" class="hidden border-t border-white/10 bg-slate-950/95 px-4 py-4 lg:hidden">
      <div class="flex flex-col gap-3 text-sm font-medium text-slate-200">
        <a href="index.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">Home</a>
        <a href="matches.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">Matches</a>
        <a href="feedback.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">Feedback</a>
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="MyBooking.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">My
            Tickets</a>
          <a href="profile.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">Profile</a>
          <a href="logout.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">Logout</a>
        <?php else: ?>
          <a href="login.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">Login</a>
          <a href="register.php" class="rounded-xl px-3 py-2 hover:bg-white/5" style="text-decoration: none;">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- STEPS -->
  <div class="steps-wrap">
    <div class="step done">
      <div class="step-number">✓</div>
      <span>Select Seats</span>
    </div>
    <div class="step-arrow">→</div>
    <div class="step done">
      <div class="step-number">✓</div>
      <span>Payment</span>
    </div>
    <div class="step-arrow">→</div>
    <div class="step active">
      <div class="step-number" style="background: rgba(63,163,77,0.25); color: #4ade80;">✓</div>
      <span style="color: #fff;">Confirmation</span>
    </div>
  </div>

  <!-- PAGE BODY -->
  <div class="page-wrap">

    <!-- SUCCESS BANNER -->
    <div class="success-banner">
      <div class="success-icon">
        <svg viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12" />
        </svg>
      </div>
      <div class="success-text">
        <h1>Booking Confirmed! 🎉</h1>
        <p>Your tickets have been successfully booked. Check your details below.</p>
        <div class="booking-ref">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" />
            <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
          </svg>
          Booking ID: <?php echo htmlspecialchars($booking['booking_id']); ?>
        </div>
      </div>
    </div>

    <div class="main-grid">

      <!-- LEFT COLUMN -->
      <div style="display:flex;flex-direction:column;gap:22px;">

        <!-- MATCH DETAILS -->
        <div class="glass-card">
          <div class="card-header">
            <div class="header-icon">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 8v4l3 3" />
              </svg>
            </div>
            <h2>Match Details</h2>
          </div>
          <div class="card-body">
            <div class="match-teams">
              <?php echo strtoupper(htmlspecialchars($booking['team1_name'])); ?>
              <span style="color:var(--text-muted);font-weight:600;font-size:22px;margin:0 12px;">vs</span>
              <?php echo strtoupper(htmlspecialchars($booking['team2_name'])); ?>
            </div>
            <div class="match-type-badge"><?php echo htmlspecialchars($booking['match_type']); ?></div>

            <div class="detail-rows">
              <div class="detail-row">
                <div class="d-icon">
                  <svg viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                </div>
                <div>
                  <div class="d-label">Match Date</div>
                  <div class="d-value"><?php echo date('d M Y', strtotime($booking['match_date'])); ?></div>
                </div>
              </div>
              <div class="detail-row">
                <div class="d-icon">
                  <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                </div>
                <div>
                  <div class="d-label">Match Time</div>
                  <div class="d-value"><?php echo date('h:i A', strtotime($booking['match_time'])); ?> IST</div>
                </div>
              </div>
              <div class="detail-row">
                <div class="d-icon">
                  <svg viewBox="0 0 24 24">
                    <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z" />
                    <circle cx="12" cy="10" r="3" />
                  </svg>
                </div>
                <div>
                  <div class="d-label">Venue</div>
                  <div class="d-value"><?php echo htmlspecialchars($booking['venue_name']); ?>,
                    <?php echo htmlspecialchars($booking['venue_city']); ?>
                  </div>
                </div>
              </div>
              <?php if ($deadline_display): ?>
                <div class="detail-row">
                  <div class="d-icon">
                    <svg viewBox="0 0 24 24">
                      <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                      <line x1="12" y1="9" x2="12" y2="13" />
                      <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                  </div>
                  <div>
                    <div class="d-label">Cancellation Deadline</div>
                    <div class="d-value" style="color:#f87171;"><?php echo $deadline_display; ?></div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- BOOKED SEATS -->
        <div class="glass-card">
          <div class="card-header">
            <div class="header-icon">
              <svg viewBox="0 0 24 24">
                <path d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z" />
                <path d="M1 13h22M1 11h22" />
              </svg>
            </div>
            <h2>Booked Seats</h2>
          </div>
          <div class="card-body">
            <?php if (!empty($items)): ?>
              <?php foreach ($items as $item): ?>
                <div class="seat-item">
                  <div class="seats-list">
                    <div class="seat-cat-name"><?php echo htmlspecialchars($item['category_name']); ?> Category</div>
                    <?php if (!empty($item['seats_no'])): ?>
                      <div class="seat-numbers">
                        <?php foreach (explode(',', $item['seats_no']) as $sn): ?>
                          <span class="seat-chip"><?php echo htmlspecialchars(trim($sn)); ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="seat-qty-price">
                    <div class="seat-qty"><?php echo (int) $item['quantity']; ?> ×
                      ₹<?php echo number_format((float) $item['unit_price']); ?></div>
                    <div class="seat-amount">₹<?php echo number_format((float) $item['total_price']); ?></div>
                  </div>
                </div>
                <table class="price-table">
                  <tr class="total-row">
                    <td>Total Paid</td>
                    <td>₹<?php echo number_format($grand_total, 2); ?></td>
                  </tr>
                </table>
              <?php endforeach; ?>
            <?php else: ?>
              <p style="color:var(--text-muted);font-size:15px;">No seat details found.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- PRICE BREAKDOWN -->
        <!-- <div class="glass-card">
        <div class="card-header"> -->
        <!-- <div class="header-icon">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
          </div> -->
        <!-- <h2>Price Breakdown</h2>
        </div>
        <div class="card-body">
          <table class="price-table">
            <tr>
              <td class="muted">Ticket Price</td>
              <td>₹<?php //echo number_format($ticket_subtotal, 2); ?></td>
            </tr>
            <tr>
              <td class="muted">Convenience Fee (2%)</td>
              <td>₹<?php //echo number_format($convenience_fee, 2); ?></td>
            </tr>
            <tr>
              <td class="muted">GST (18%)</td>
              <td>₹<?php //echo number_format($gst_amount, 2); ?></td>
            </tr>
          </table>
          <hr class="price-divider">
          <table class="price-table">
            <tr class="total-row">
              <td>Total Paid</td>
              <td>₹<?php //echo number_format($grand_total, 2); ?></td>
            </tr>
          </table>
        </div>
      </div> -->

        <!-- ACTION BUTTONS -->
        <div class="action-row">
          <a href="MyBooking.php" class="btn btn-bookings">
            <svg viewBox="0 0 24 24">
              <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
            </svg>
            My Bookings
          </a>
          <a href="index.php" class="btn btn-home">
            <svg viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
              <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
            Home
          </a>
        </div>

      </div><!-- /left -->

      <!-- RIGHT SIDEBAR -->
      <div class="sidebar">

        <!-- BOOKING ID CARD -->
        <div class="glass-card">
          <div class="card-header">
            <div class="header-icon">
              <svg viewBox="0 0 24 24">
                <rect x="5" y="2" width="14" height="20" rx="2" />
                <line x1="9" y1="7" x2="15" y2="7" />
                <line x1="9" y1="11" x2="15" y2="11" />
                <line x1="9" y1="15" x2="13" y2="15" />
              </svg>
            </div>
            <h2>Booking Info</h2>
          </div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:16px;">
            <div>
              <div
                style="color:var(--text-dim);font-size:12px;margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px;">
                Booking ID</div>
              <div style="font-size:17px;font-weight:800;color:var(--gold);">
                <?php echo htmlspecialchars($booking['booking_id']); ?>
              </div>
            </div>
            <div>
              <div
                style="color:var(--text-dim);font-size:12px;margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px;">
                Booked On</div>
              <div style="font-size:14px;font-weight:600;">
                <?php echo date('d M Y, h:i A', strtotime($booking['booking_time'])); ?>
              </div>
            </div>
            <div>
              <div
                style="color:var(--text-dim);font-size:12px;margin-bottom:6px;text-transform:uppercase;letter-spacing:.8px;">
                Status</div>
              <?php
              $bs = $booking['booking_status'];
              $ps = $booking['payment_status'];
              $bclass = ($bs === 'confirmed') ? 'success' : 'pending';
              $bdot = ($bs === 'confirmed') ? 'dot-green' : 'dot-gold';
              $pclass = ($ps === 'success') ? 'success' : 'pending';
              $pdot = ($ps === 'success') ? 'dot-green' : 'dot-gold';
              ?>
              <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <span class="payment-badge <?php echo $bclass; ?>">
                  <span class="badge-dot <?php echo $bdot; ?>"></span>
                  Booking: <?php echo ucfirst($bs); ?>
                </span>
                <span class="payment-badge <?php echo $pclass; ?>">
                  <span class="badge-dot <?php echo $pdot; ?>"></span>
                  Payment: <?php echo ucfirst($ps); ?>
                </span>
              </div>
            </div>
            <?php if ($payment): ?>
              <div>
                <div
                  style="color:var(--text-dim);font-size:12px;margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px;">
                  Payment Method</div>
                <div style="font-size:14px;font-weight:600;text-transform:capitalize;">
                  <?php echo htmlspecialchars($payment['payment_method']); ?>
                </div>
              </div>
            <?php endif; ?>
            <div>
              <div
                style="color:var(--text-dim);font-size:12px;margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px;">
                Total Tickets</div>
              <div style="font-size:22px;font-weight:900;">
                <?php echo (int) $booking['total_tickets']; ?>
                <span style="font-size:14px;font-weight:500;color:var(--text-muted);"> tickets</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ATTENDEE -->
        <!-- <div class="glass-card">
        <div class="card-header">
          <div class="header-icon">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <h2>Attendee</h2>
        </div>
        <div class="card-body">
          <div class="user-row">
            <div class="user-avatar"><?php //echo strtoupper(substr($user['name'], 0, 1)); ?></div>
            <div>
              <div class="user-name"><?php //echo htmlspecialchars($user['name']); ?></div>
              <div class="user-email"><?php //echo htmlspecialchars($user['email']); ?></div>
            </div>
          </div>
        </div>
      </div> -->

        <!-- IMPORTANT INFO -->
        <div class="info-box">
          <h4>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
            Important Information
          </h4>
          <ul>
            <li>This booking has been cancelled.</li>
            <li>Your refund will be processed within 5–7 working days.</li>
            <li>The amount will be credited to your original payment method.</li>
            <li>Please keep this booking ID for reference.</li>
            <li>For any queries, contact support.</li>
          </ul>
        </div>

      </div><!-- /sidebar -->

    </div><!-- /main-grid -->
  </div><!-- /page-wrap -->

  <!-- CONFETTI ANIMATION -->
  <script>
    (function () {
      const canvas = document.getElementById('confettiCanvas');
      const ctx = canvas.getContext('2d');
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
      window.addEventListener('resize', () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; });

      const colors = ['#f6c84c', '#4ade80', '#60a5fa', '#f472b6', '#a78bfa', '#fb923c'];
      const pieces = Array.from({ length: 120 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height - canvas.height,
        r: Math.random() * 5 + 3,
        d: Math.random() * 80 + 20,
        color: colors[Math.floor(Math.random() * colors.length)],
        tilt: Math.floor(Math.random() * 10) - 10,
        tiltAngle: 0,
        tiltIncrement: (Math.random() * 0.07 + 0.05) * (Math.random() > 0.5 ? 1 : -1)
      }));

      let angle = 0;
      let frame = 0;
      const maxFrames = 260;

      function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        angle += 0.01;
        pieces.forEach((p, i) => {
          p.tiltAngle += p.tiltIncrement;
          p.y += (Math.cos(angle + p.d) + 2.5) * 1.1;
          p.x += Math.sin(angle) * 1.2;
          p.tilt = Math.sin(p.tiltAngle) * 12;

          ctx.beginPath();
          ctx.lineWidth = p.r / 2;
          ctx.strokeStyle = p.color;
          ctx.moveTo(p.x + p.tilt + p.r / 2, p.y);
          ctx.lineTo(p.x + p.tilt, p.y + p.tilt + p.r);
          ctx.stroke();

          if (p.y > canvas.height) {
            p.x = Math.random() * canvas.width;
            p.y = -12;
          }
        });
        frame++;
        if (frame < maxFrames) {
          requestAnimationFrame(draw);
        } else {
          ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
      }
      draw();
    })();

    document.addEventListener('DOMContentLoaded', function () {
      const menuToggle = document.getElementById('menuToggle');
      const mobileMenu = document.getElementById('mobileMenu');
      const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationBtn) {
            notificationBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (profileDropdownBtn) {
                   const profileMenu = document.getElementById('profileDropdown');
                   if (profileMenu) {
                       profileMenu.classList.remove('opacity-100', 'translate-y-0');
                       profileMenu.classList.add('invisible', 'opacity-0', 'translate-y-2');
                   }
                }
                if (notificationDropdown) notificationDropdown.classList.toggle('show');
            });
        }
        document.addEventListener('click', (e) => {
            if (notificationDropdown && !notificationDropdown.contains(e.target) && notificationBtn && !notificationBtn.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
        
        const profileDropdownBtn = document.getElementById('profileDropdownBtn');
      const profileDropdown = document.getElementById('profileDropdown');

      if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', function () {
          mobileMenu.classList.toggle('hidden');
        });
      }

      if (profileDropdownBtn && profileDropdown) {
        profileDropdownBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          profileDropdown.classList.toggle('opacity-0');
          profileDropdown.classList.toggle('invisible');
          profileDropdown.classList.toggle('translate-y-2');
          profileDropdown.classList.toggle('translate-y-0');
        });

        document.addEventListener('click', function (e) {
          if (!profileDropdown.contains(e.target) && !profileDropdownBtn.contains(e.target)) {
            profileDropdown.classList.add('opacity-0', 'invisible', 'translate-y-2');
            profileDropdown.classList.remove('translate-y-0');
          }
        });
      }
    });
  </script>

</body>

</html>