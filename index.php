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


function fetchSingleValue(mysqli $link, string $query): int
{
    $result = mysqli_query($link, $query);
    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

function teamCode(?string $shortName, string $teamName): string
{
    if (!empty($shortName)) {
        return strtoupper($shortName);
    }

    $parts = preg_split('/\s+/', trim($teamName)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= strtoupper(substr($part, 0, 1));
        }
    }

    return $letters !== '' ? substr($letters, 0, 3) : strtoupper(substr($teamName, 0, 3));
}

function displayTeamName(string $teamName): string
{
    $teamName = trim($teamName);
    return strlen($teamName) <= 4 ? strtoupper($teamName) : ucwords($teamName);
}

function statusMeta(string $status): array
{
    switch ($status) {
        case 'live':
            return ['label' => 'Live Now', 'class' => 'border-red-400/40 bg-red-500/10 text-red-200'];
        case 'completed':
            return ['label' => 'Completed', 'class' => 'border-slate-400/40 bg-slate-500/10 text-slate-200'];
        case 'cancelled':
            return ['label' => 'Cancelled', 'class' => 'border-amber-300/40 bg-amber-500/10 text-amber-100'];
        default:
            return ['label' => 'Upcoming', 'class' => 'border-emerald-300/40 bg-emerald-500/10 text-emerald-100'];
    }
}

function availabilityLabel(int $availableSeats): string
{
    if ($availableSeats <= 0) {
        return 'Sold out';
    }
    if ($availableSeats < 25) {
        return 'Selling fast';
    }
    if ($availableSeats < 75) {
        return 'Limited seats';
    }
    return 'Open for booking';
}

$totalMatches = fetchSingleValue($link, "SELECT COUNT(*) AS total FROM matches");
$totalUsers = fetchSingleValue($link, "SELECT COUNT(*) AS total FROM users");
$totalBookings = fetchSingleValue($link, "SELECT COUNT(*) AS total FROM bookings");
$totalVenues = fetchSingleValue($link, "SELECT COUNT(*) AS total FROM venues");
$liveMatchesCount = fetchSingleValue($link, "SELECT COUNT(*) AS total FROM matches WHERE status = 'live'");

$ticketCount = 0;
$userProfileImage = 'default.jpg';
if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];

    $ticketStmt = $link->prepare("SELECT COUNT(*) AS total FROM bookings WHERE user_id = ?");
    $ticketStmt->bind_param("i", $userId);
    $ticketStmt->execute();
    $ticketRow = $ticketStmt->get_result()->fetch_assoc();
    $ticketCount = (int) ($ticketRow['total'] ?? 0);
    $ticketStmt->close();

    $imgStmt = $link->prepare("SELECT profile_image FROM users WHERE id = ?");
    $imgStmt->bind_param("i", $userId);
    $imgStmt->execute();
    $imgRow = $imgStmt->get_result()->fetch_assoc();
    $userProfileImage = $imgRow['profile_image'] ?? 'default.jpg';
    $imgStmt->close();
}

$matches = [];
$matchSql = "
    SELECT
        m.id,
        m.match_date,
        m.match_time,
        m.match_type,
        m.description,
        m.status,
        t1.name AS team1_name,
        t1.short_name AS team1_short,
        t1.logo AS team1_logo,
        t2.name AS team2_name,
        t2.short_name AS team2_short,
        t2.logo AS team2_logo,
        v.name AS venue_name,
        v.city AS venue_city,
        v.state AS venue_state,
        v.country AS venue_country,
        v.address AS venue_address,
        COALESCE(MIN(vc.price), 0) AS starting_price,
        COALESCE(MAX(vc.price), 0) AS highest_price,
        COALESCE(SUM(vc.no_of_seats), 0) AS total_seats,
        COALESCE(SUM(COALESCE(bs.booked_qty, 0)), 0) AS booked_seats,
        COALESCE(SUM(vc.no_of_seats - COALESCE(bs.booked_qty, 0)), 0) AS available_seats
    FROM matches m
    INNER JOIN teams t1 ON m.team1_id = t1.id
    INNER JOIN teams t2 ON m.team2_id = t2.id
    INNER JOIN venues v ON m.venue_id = v.id
    LEFT JOIN venue_category vc ON m.venue_id = vc.venue_id
    LEFT JOIN (
        SELECT
            b.match_id,
            bi.category_id,
            SUM(bi.quantity) AS booked_qty
        FROM bookings b
        INNER JOIN booking_items bi ON b.id = bi.booking_id
        WHERE b.booking_status = 'confirmed'
          AND b.payment_status = 'success'
        GROUP BY b.match_id, bi.category_id
    ) bs ON bs.match_id = m.id AND bs.category_id = vc.category_id
    GROUP BY
        m.id, m.match_date, m.match_time, m.match_type, m.description, m.status,
        t1.name, t1.short_name, t1.logo, t2.name, t2.short_name, t2.logo,
        v.name, v.city, v.state, v.country, v.address
    ORDER BY
        CASE
            WHEN m.status = 'live' THEN 1
            WHEN m.status = 'upcoming' THEN 2
            WHEN m.status = 'completed' THEN 3
            ELSE 4
        END,
        m.match_date ASC,
        m.match_time ASC
";
$matchResult = mysqli_query($link, $matchSql);
if ($matchResult) {
    while ($row = mysqli_fetch_assoc($matchResult)) {
        $matches[] = $row;
    }
}

$buckets = ['live' => [], 'upcoming' => [], 'completed' => [], 'cancelled' => []];
foreach ($matches as $match) {
    $key = isset($buckets[$match['status']]) ? $match['status'] : 'upcoming';
    $buckets[$key][] = $match;
}
$featuredMatch = $buckets['live'][0] ?? $buckets['upcoming'][0] ?? ($matches[0] ?? null);

$seatCategories = [];
$seatSql = "
    SELECT
        sc.id,
        sc.name,
        sc.description,
        COALESCE(MIN(vc.color_code), '#8b5cf6') AS color_code,
        COALESCE(MIN(vc.price), 0) AS price,
        COUNT(DISTINCT m.id) AS match_count
    FROM seat_categories sc
    LEFT JOIN venue_category vc ON sc.id = vc.category_id
    LEFT JOIN matches m ON m.venue_id = vc.venue_id
    GROUP BY sc.id, sc.name, sc.description
    ORDER BY sc.id ASC
";
$seatResult = mysqli_query($link, $seatSql);
if ($seatResult) {
    while ($row = mysqli_fetch_assoc($seatResult)) {
        $seatCategories[] = $row;
    }
}

$feedbackSuccess = '';
$feedbackError = '';
if (isset($_POST['submit_feedback'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['feedback_error'] = "Please login to submit feedback.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $userId = (int) $_SESSION['user_id'];
    $matchId = !empty($_POST['match_id']) ? (int) $_POST['match_id'] : 0;
    $bookingId = !empty($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
    $rating = !empty($_POST['rating']) ? (int) $_POST['rating'] : 0;
    $message = trim($_POST['message'] ?? '');

    if ($matchId <= 0 || $bookingId <= 0 || $rating < 1 || $rating > 5 || $message === '') {
        $_SESSION['feedback_error'] = "Please fill all feedback fields correctly.";
    } else {
        $feedbackStmt = $link->prepare("INSERT INTO feedback (user_id, match_id, booking_id, rating, message) VALUES (?, ?, ?, ?, ?)");
        $feedbackStmt->bind_param("iiiis", $userId, $matchId, $bookingId, $rating, $message);
        if ($feedbackStmt->execute()) {
            $_SESSION['feedback_success'] = "Thank you for your feedback!";
        } else {
            $_SESSION['feedback_error'] = "Error: Could not save feedback.";
        }
        $feedbackStmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_SESSION['feedback_success'])) {
    $feedbackSuccess = $_SESSION['feedback_success'];
    unset($_SESSION['feedback_success']);
}
if (isset($_SESSION['feedback_error'])) {
    $feedbackError = $_SESSION['feedback_error'];
    unset($_SESSION['feedback_error']);
}

$feedbacks = [];
$feedbackSql = "
    SELECT
        f.rating,
        f.message,
        f.created_at,
        u.name AS user_name,
        t1.name AS team1_name,
        t2.name AS team2_name
    FROM feedback f
    INNER JOIN users u ON f.user_id = u.id
    INNER JOIN matches m ON f.match_id = m.id
    INNER JOIN teams t1 ON m.team1_id = t1.id
    INNER JOIN teams t2 ON m.team2_id = t2.id
    ORDER BY f.created_at DESC
    LIMIT 4
";
$feedbackResult = mysqli_query($link, $feedbackSql);
if ($feedbackResult) {
    while ($row = mysqli_fetch_assoc($feedbackResult)) {
        $feedbacks[] = $row;
    }
}

$userBookings = [];
if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $bookingSql = "
        SELECT
            b.id AS booking_id,
            b.match_id,
            b.booking_id AS booking_code,
            t1.name AS team1_name,
            t2.name AS team2_name
        FROM bookings b
        INNER JOIN matches m ON b.match_id = m.id
        INNER JOIN teams t1 ON m.team1_id = t1.id
        INNER JOIN teams t2 ON m.team2_id = t2.id
        WHERE b.user_id = ?
        ORDER BY b.booking_time DESC
    ";
    $bookingStmt = $link->prepare($bookingSql);
    $bookingStmt->bind_param("i", $userId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    while ($row = $bookingResult->fetch_assoc()) {
        $userBookings[] = $row;
    }
    $bookingStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket Ticket Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --line: rgba(255,255,255,0.1); }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(244, 201, 93, 0.12), transparent 28%),
                radial-gradient(circle at right top, rgba(55, 211, 159, 0.12), transparent 32%),
                linear-gradient(180deg, #07111f 0%, #081728 45%, #050d18 100%);
            color: #f8fafc;
        }

        h1, h2, h3, .display-font {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 0.04em;
        }

        .glass {
            background: linear-gradient(180deg, rgba(16, 31, 57, 0.8), rgba(8, 18, 35, 0.94));
            border: 1px solid var(--line);
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.26);
            backdrop-filter: blur(16px);
        }

        .hero {
            background:
                linear-gradient(125deg, rgba(7, 17, 31, 0.94), rgba(14, 28, 52, 0.78)),
                url('image/back.jpeg') center/cover no-repeat;
        }

        .team-mark {
            width: 3.8rem;
            height: 3.8rem;
            border-radius: 1.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-weight: 800;
            letter-spacing: 0.08em;
            overflow: hidden;
        }

        .team-mark:has(.team-logo) {
            background: none;
            border: none;
        }

        .team-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.24), rgba(244, 201, 93, 0.18));
            border-color: rgba(255, 214, 102, 0.4);
            color: #fff7ed;
        }

        .card-rise {
            transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .card-rise:hover {
            transform: translateY(-6px);
            border-color: rgba(255, 255, 255, 0.18);
            box-shadow: 0 26px 50px rgba(2, 6, 23, 0.34);
        }

        .track { background: rgba(148, 163, 184, 0.16); }
        .fill { background: linear-gradient(90deg, #37d39f, #f4c95d, #ff6b35); }

        .seat-card {
            position: relative;
            overflow: hidden;
            border-radius: 1.75rem;
            border: 1px solid rgba(255,255,255,0.08);
            background: linear-gradient(180deg, rgba(14, 28, 52, 0.88), rgba(8, 18, 35, 0.96));
            box-shadow:
                0 20px 45px rgba(2, 6, 23, 0.28),
                inset 0 1px 0 rgba(255,255,255,0.04);
            transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
            display: block;
        }

        .seat-card:hover {
            transform: translateY(-8px);
            box-shadow:
                0 28px 55px rgba(2, 6, 23, 0.38),
                0 0 0 1px rgba(255,255,255,0.04) inset;
            border-color: rgba(255,255,255,0.14);
        }

        .seat-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(135deg, rgba(255,255,255,0.05), var(--seat-color), rgba(255,255,255,0.03));
            -webkit-mask:
                linear-gradient(#fff 0 0) content-box,
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
                    mask-composite: exclude;
            pointer-events: none;
            opacity: 0.95;
        }

        .seat-card::after {
            content: "";
            position: absolute;
            top: -30%;
            right: -20%;
            width: 150px;
            height: 100px;
            border-radius: 999px;
            background: radial-gradient(circle, var(--seat-color) 0%, transparent 70%);
            opacity: 0.14;
            filter: blur(20px);
            pointer-events: none;
        }

        .display-font {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 2px;
            font-size: 34px;
        }

        .tagline {
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            letter-spacing: 3px;
        }

        nav a {
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }

        .venue-info-box {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
        }

        .venue-info-box p {
            margin: 4px 0;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.5;
        }

        .venue-info-box strong {
            color: #fff;
            font-weight: 600;
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

    /* === SEAT COUNT MODAL === */
    .seat-modal-overlay {
        position: fixed; inset: 0; z-index: 9999;
        background: rgba(4, 8, 20, 0.85);
        backdrop-filter: blur(10px);
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        opacity: 0; pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .seat-modal-overlay.open {
        opacity: 1; pointer-events: all;
    }
    .seat-modal-box {
        background: linear-gradient(160deg, #0d1f3c 0%, #070e1f 100%);
        border: 1px solid rgba(255,255,255,0.10);
        border-radius: 28px;
        box-shadow: 0 40px 80px rgba(0,0,0,0.5);
        max-width: 420px; width: 100%;
        transform: translateY(30px) scale(0.96);
        transition: transform 0.3s ease;
        overflow: hidden;
    }
    .seat-modal-overlay.open .seat-modal-box {
        transform: translateY(0) scale(1);
    }
    .seat-modal-top {
        position: relative;
        background: linear-gradient(135deg, rgba(255,107,53,0.18), rgba(16,185,129,0.14));
        padding: 32px 28px 24px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .seat-modal-top h3 {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 28px; letter-spacing: 2px;
        color: #fff; margin: 0 0 6px;
    }
    .seat-modal-top p {
        font-size: 13px; color: #94a3b8; margin: 0;
    }
    .seat-modal-close {
        position: absolute; top: 16px; right: 18px;
        background: rgba(255,255,255,0.07); border: none;
        color: #94a3b8; width: 32px; height: 32px;
        border-radius: 50%; font-size: 16px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.2s;
    }
    .seat-modal-close:hover { background: rgba(255,255,255,0.14); color: #fff; }
    .seat-modal-body { padding: 28px; }
    .seat-count-label {
        text-align: center; font-size: 13px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 2px; color: #64748b;
        margin-bottom: 18px;
    }
    .seat-numbers {
        display: flex; flex-wrap: wrap; justify-content: center;
        gap: 10px; margin-bottom: 22px;
    }
    .seat-num-btn {
        width: 50px; height: 50px;
        border-radius: 14px;
        border: 1.5px solid rgba(255,255,255,0.12);
        background: rgba(255,255,255,0.04);
        color: #cbd5e1; font-size: 17px; font-weight: 800;
        cursor: pointer;
        transition: all 0.18s ease;
        display: flex; align-items: center; justify-content: center;
    }
    .seat-num-btn:hover {
        border-color: rgba(244,201,93,0.5);
        background: rgba(244,201,93,0.1);
        color: #f4c95d;
        transform: translateY(-2px);
    }
    .seat-num-btn.selected {
        background: linear-gradient(135deg, #f97316, #f4c95d);
        border-color: #f4c95d;
        color: #0f172a;
        box-shadow: 0 6px 20px rgba(249,115,22,0.35);
        transform: translateY(-2px) scale(1.08);
    }
    .seat-modal-hint {
        text-align: center; font-size: 12px; color: #475569;
        margin-bottom: 24px; line-height: 1.6;
        padding: 10px 14px;
        background: rgba(255,255,255,0.03);
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .seat-modal-btn {
        display: block; width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #f97316, #f4c95d, #10b981);
        border: none; border-radius: 14px;
        font-size: 15px; font-weight: 800;
        text-transform: uppercase; letter-spacing: 1.5px;
        color: #0f172a; cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 8px 24px rgba(249,115,22,0.25);
    }
    .seat-modal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(249,115,22,0.4);
    }
    .seat-modal-btn:disabled {
        opacity: 0.45; cursor: not-allowed; transform: none;
    }
    @media (max-width: 480px) {
        .seat-num-btn { width: 44px; height: 44px; font-size: 15px; border-radius: 12px; }
    }
</style>
</head>
<body>
<header class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/75 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="index.php" class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 via-amber-400 to-emerald-400 text-slate-950">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div>
                <p class="display-font text-3xl leading-none text-white">CRICKET TICKET BOOKING</p>
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
              <?php if (isset($unreadNotifications) && $unreadNotifications > 0): ?>
                <span class="notification-badge-dot"><?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?></span>
              <?php endif; ?>
            </button>
            <div id="notificationDropdown" class="notification-dropdown">
              <div class="notification-header">
                <h4>Notifications</h4>
                <?php if (isset($unreadNotifications) && $unreadNotifications > 0): ?>
                  <a href="?mark_notifications_read=1">Mark all read</a>
                <?php endif; ?>
              </div>
              <div class="notification-list">
                <?php if (empty($notifications)): ?>
                  <div class="notification-empty">No notifications yet.</div>
                <?php else: ?>
                  <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                      <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                      <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                      <div class="notification-time"><?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="relative">
                    <button id="profileDropdownBtn" class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white transition-all hover:bg-white/10 overflow-hidden ring-offset-2 ring-offset-slate-950 focus:ring-2 ring-amber-400/50">
                        <?php if (!empty($userProfileImage) && $userProfileImage !== 'default.jpg'): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-2xl"></i>
                        <?php endif; ?>
                    </button>

                    <div id="profileDropdown" class="absolute right-0 mt-3 w-56 glass origin-top-right rounded-2xl p-2 opacity-0 invisible transition-all duration-200 z-50 translate-y-2">
                        <div class="px-3 py-2 border-b border-white/10 mb-2">
                            <p class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                            <p class="text-[10px] uppercase tracking-widest text-slate-400 mt-0.5"><?php echo $ticketCount; ?> Tickets Booked</p>
                        </div>
                        <a href="profile.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                            <i class="fas fa-user-circle text-xs w-4"></i> Profile
                        </a>
                        <a href="MyBooking.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                            <i class="fas fa-ticket-alt text-xs w-4"></i> My Bookings
                        </a>
                        <div class="my-1 border-t border-white/5"></div>
                        <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                            <i class="fas fa-sign-out-alt text-xs w-4"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">Login</a>
                <a href="register.php" class="rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-5 py-2 text-sm font-bold text-slate-950">Create Account</a>
            <?php endif; ?>
        </div>

        <button id="menuToggle" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white lg:hidden" type="button">
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
                <a href="logout.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Logout</a>
            <?php else: ?>
                <a href="login.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Login</a>
                <a href="register.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="top">
    <section class="hero">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.12fr_0.88fr] lg:px-8 lg:py-24">
            <div>
                <h1 class="mt-6 text-6xl leading-[0.92] text-white sm:text-7xl lg:text-8xl">Book The Big Game <span class="block text-amber-200">Before The Crowd Arrives</span></h1>
                <p class="mt-6 max-w-2xl text-base leading-8 text-slate-200 sm:text-lg">Track live fixtures, compare seat zones, and jump into upcoming matches from one home page built around real match data from your database.</p>
                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="#matches" class="rounded-full bg-white px-6 py-3 text-sm font-bold uppercase tracking-[0.24em] text-slate-950 hover:bg-amber-200">Browse Matches</a>
                    <a href="matches.php" class="rounded-full border border-white/15 px-6 py-3 text-sm font-bold uppercase tracking-[0.24em] text-white hover:bg-white/5">View All Matches</a>
                </div>

                <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="glass rounded-3xl p-5">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-300">Matches</p>
                        <p class="mt-3 text-4xl font-black text-white"><?php echo $totalMatches; ?></p>
                    </div>
                    <div class="glass rounded-3xl p-5">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-300">Live Now</p>
                        <p class="mt-3 text-4xl font-black text-red-300"><?php echo $liveMatchesCount; ?></p>
                    </div>
                    <div class="glass rounded-3xl p-5">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-300">Fans</p>
                        <p class="mt-3 text-4xl font-black text-emerald-300"><?php echo $totalUsers; ?></p>
                    </div>
                    <div class="glass rounded-3xl p-5">
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-300">Bookings</p>
                        <p class="mt-3 text-4xl font-black text-amber-200"><?php echo $totalBookings; ?></p>
                    </div>
                </div>
            </div>

            <div class="glass rounded-[2rem] p-6 sm:p-8">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-300">Spotlight Match</p>
                        <p class="mt-2 text-sm text-slate-400">Updated from your latest fixture data</p>
                    </div>
                    <div id="homeClock" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs uppercase tracking-[0.28em] text-slate-200">IST</div>
                </div>

                <?php if ($featuredMatch): ?>
                    <?php
                    $featuredMeta = statusMeta($featuredMatch['status']);
                    $featuredTime = strtotime($featuredMatch['match_date'] . ' ' . $featuredMatch['match_time']);
                    $featuredAvailable = (int) $featuredMatch['available_seats'];
                    $featuredTotal = max((int) $featuredMatch['total_seats'], 1);
                    $featuredBooked = (int) $featuredMatch['booked_seats'];
                    $featuredOccupancy = $featuredTotal > 0 ? min(100, max(0, (int) round(($featuredBooked / $featuredTotal) * 100))) : 0;
                    ?>
                    <div class="mt-8 rounded-[1.75rem] border border-white/10 bg-slate-950/40 p-6">
                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] <?php echo $featuredMeta['class']; ?>"><?php echo htmlspecialchars($featuredMeta['label']); ?></span>
                            <span class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-200"><?php echo htmlspecialchars($featuredMatch['match_type']); ?></span>
                        </div>
                        <div class="mt-6 flex items-center justify-between gap-4">
                            <div class="text-center">
                                <div class="team-mark mx-auto"><?php if (!empty($featuredMatch['team1_logo'])): ?><img src="<?php echo htmlspecialchars($featuredMatch['team1_logo']); ?>" alt="<?php echo htmlspecialchars($featuredMatch['team1_name']); ?>" class="team-logo"><?php else: echo htmlspecialchars(teamCode($featuredMatch['team1_short'], $featuredMatch['team1_name'])); endif; ?></div>
                                <p class="mt-3 text-lg font-bold text-white"><?php echo htmlspecialchars(displayTeamName($featuredMatch['team1_name'])); ?></p>
                            </div>
                            <div class="text-center">
                                <p class="display-font text-5xl text-white">VS</p>
                                <p class="mt-2 text-xs uppercase tracking-[0.28em] text-slate-400">Prime Fixture</p>
                            </div>
                            <div class="text-center">
                                <div class="team-mark mx-auto"><?php if (!empty($featuredMatch['team2_logo'])): ?><img src="<?php echo htmlspecialchars($featuredMatch['team2_logo']); ?>" alt="<?php echo htmlspecialchars($featuredMatch['team2_name']); ?>" class="team-logo"><?php else: echo htmlspecialchars(teamCode($featuredMatch['team2_short'], $featuredMatch['team2_name'])); endif; ?></div>
                                <p class="mt-3 text-lg font-bold text-white"><?php echo htmlspecialchars(displayTeamName($featuredMatch['team2_name'])); ?></p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                                <p class="text-[0.65rem] font-bold uppercase tracking-[0.25em] text-slate-400">Date & Time</p>
                                <p class="mt-3 text-lg font-bold text-white"><?php echo date('d M Y', $featuredTime); ?></p>
                                <p class="text-sm font-medium text-slate-300 mt-1"><?php echo date('h:i A', $featuredTime); ?> IST</p>
                            </div>

                            <div class="flex flex-col justify-center">
                                <p class="text-[0.65rem] font-bold uppercase tracking-[0.25em] text-slate-400 mb-2">Venue</p>
                                <p class="text-xl sm:text-2xl font-black text-white leading-tight">
                                    <?php echo htmlspecialchars(ucwords($featuredMatch['venue_name'])); ?>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 px-1">
                            <p class="text-[0.85rem] leading-relaxed text-slate-400">
                                <i class="fas fa-map-marker-alt text-orange-400/80 mr-2 text-[0.75rem]"></i>
                                <?php 
                                    $featLocParts = [];
                                    if (!empty($featuredMatch['venue_address'])) $featLocParts[] = ucwords($featuredMatch['venue_address']);
                                    if (!empty($featuredMatch['venue_city'])) $featLocParts[] = ucwords($featuredMatch['venue_city']);
                                    if (!empty($featuredMatch['venue_state'])) $featLocParts[] = ucwords($featuredMatch['venue_state']);
                                    if (!empty($featuredMatch['venue_country'])) $featLocParts[] = ucwords($featuredMatch['venue_country']);
                                    echo htmlspecialchars(implode(', ', $featLocParts));
                                ?>
                            </p>
                        </div>

                        <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-semibold text-white"><?php echo htmlspecialchars(availabilityLabel($featuredAvailable)); ?></span>
                                <span class="text-slate-300">&#8377;<?php echo number_format((float) $featuredMatch['starting_price'], 2); ?> onwards</span>
                            </div>
                            <div class="track mt-3 h-2 rounded-full">
                                <div class="fill h-2 rounded-full" style="width: <?php echo $featuredOccupancy; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="booking.php?match_id=<?php echo $featuredMatch['id']; ?>" class="open-seat-modal rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-5 py-3 text-sm font-bold uppercase tracking-[0.2em] text-slate-950">Book This Match</a>
                            <a href="#feedback" class="rounded-full border border-white/10 px-5 py-3 text-sm font-bold uppercase tracking-[0.2em] text-white hover:bg-white/5">Fan Reviews</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-8 rounded-[1.75rem] border border-dashed border-white/12 bg-slate-950/40 p-8 text-center">
                        <i class="fas fa-calendar-times text-5xl text-slate-500"></i>
                        <p class="mt-4 text-2xl font-bold text-white">No matches available</p>
                        <p class="mt-2 text-sm text-slate-300">Once matches are added, the spotlight card will update automatically.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="glass rounded-[2rem] p-6">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Matchday Reach</p>
                <p class="mt-3 text-4xl font-black text-white"><?php echo $totalVenues; ?></p>
                <p class="mt-2 text-sm text-slate-300">Venues listed on the platform.</p>
            </div>
            <div class="glass rounded-[2rem] p-6">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Upcoming Queue</p>
                <p class="mt-3 text-4xl font-black text-white"><?php echo count($buckets['upcoming']); ?></p>
                <p class="mt-2 text-sm text-slate-300">Matches ready to book right now.</p>
            </div>
            <div class="glass rounded-[2rem] p-6">
                <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Home Page Mode</p>
                <p class="mt-3 text-3xl font-black text-amber-200">Database Driven</p>
                <p class="mt-2 text-sm text-slate-300">Cards and sections update from live project data.</p>
            </div>
        </div>
    </section>

    <section id="matches" class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-amber-200">Featured Match Centre</p>
                <h2 class="mt-4 text-5xl text-white sm:text-6xl">Live, Upcoming And Ready To Book</h2>
                <p class="mt-4 max-w-2xl text-base leading-8 text-slate-300">Filter the home page by match status and let visitors discover fixtures immediately without leaving the landing page.</p>
            </div>
            <a href="matches.php" class="inline-flex items-center gap-2 rounded-full border border-white/10 px-5 py-3 text-sm font-bold uppercase tracking-[0.22em] text-white hover:bg-white/5">View All Matches <i class="fas fa-arrow-right text-xs"></i></a>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <button type="button" class="filter-btn active rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200" data-filter="all">All Matches</button>
            <button type="button" class="filter-btn rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200" data-filter="live">Live</button>
            <button type="button" class="filter-btn rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200" data-filter="upcoming">Upcoming</button>
            <button type="button" class="filter-btn rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-200" data-filter="completed">Completed</button>
        </div>

        <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <?php if (empty($matches)): ?>
                <div class="glass col-span-full rounded-[2rem] p-10 text-center">
                    <i class="fas fa-calendar-times text-5xl text-slate-500"></i>
                    <h3 class="mt-4 text-3xl font-bold text-white">No matches available</h3>
                    <p class="mt-2 text-slate-300">Add fixtures in your database and they will appear here automatically.</p>
                </div>
            <?php else: ?>
                <?php foreach ($matches as $match): ?>
                    <?php
                    $meta = statusMeta($match['status']);
                    $matchTime = strtotime($match['match_date'] . ' ' . $match['match_time']);
                    $availableSeats = (int) $match['available_seats'];
                    $totalSeats = max((int) $match['total_seats'], 1);
                    $bookedSeats = (int) $match['booked_seats'];
                    $occupancy = $totalSeats > 0 ? min(100, max(0, (int) round(($bookedSeats / $totalSeats) * 100))) : 0;
                    ?>
                    <article class="glass card-rise rounded-[2rem] border border-white/8 p-6" data-status="<?php echo htmlspecialchars($match['status']); ?>">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] <?php echo $meta['class']; ?>"><?php echo htmlspecialchars($meta['label']); ?></span>
                                <p class="mt-3 text-sm font-semibold uppercase tracking-[0.24em] text-amber-200"><?php echo htmlspecialchars($match['match_type']); ?></p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-right">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">From</p>
                                <p class="mt-1 text-xl font-black text-emerald-300">&#8377;<?php echo number_format((float) $match['starting_price'], 2); ?></p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-between gap-4">
                            <div class="text-center">
                                <div class="team-mark"><?php if (!empty($match['team1_logo'])): ?><img src="<?php echo htmlspecialchars($match['team1_logo']); ?>" alt="<?php echo htmlspecialchars($match['team1_name']); ?>" class="team-logo"><?php else: echo htmlspecialchars(teamCode($match['team1_short'], $match['team1_name'])); endif; ?></div>
                                <p class="mt-3 text-lg font-bold text-white"><?php echo htmlspecialchars(displayTeamName($match['team1_name'])); ?></p>
                            </div>
                            <div class="text-center">
                                <p class="display-font text-4xl text-white">VS</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.24em] text-slate-400">Stadium Clash</p>
                            </div>
                            <div class="text-center">
                                <div class="team-mark"><?php if (!empty($match['team2_logo'])): ?><img src="<?php echo htmlspecialchars($match['team2_logo']); ?>" alt="<?php echo htmlspecialchars($match['team2_name']); ?>" class="team-logo"><?php else: echo htmlspecialchars(teamCode($match['team2_short'], $match['team2_name'])); endif; ?></div>
                                <p class="mt-3 text-lg font-bold text-white"><?php echo htmlspecialchars(displayTeamName($match['team2_name'])); ?></p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3 text-sm text-slate-300">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-calendar-alt w-5 text-amber-200"></i>
                                <span><?php echo date('d M Y', $matchTime); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-clock w-5 text-emerald-300"></i>
                                <span><?php echo date('h:i A', $matchTime); ?> IST</span>
                            </div>
                            <div class="flex items-start gap-4">
                                <i class="fas fa-map-marker-alt w-5 text-orange-300 mt-1.5"></i>
                                <div class="flex flex-col">
                                    <span class="text-lg font-bold text-white tracking-wide">
                                        <?php echo htmlspecialchars(ucwords($match['venue_name'])); ?>
                                    </span>
                                    <span class="text-[0.8rem] leading-relaxed text-slate-400 mt-1.5">
                                        <?php 
                                            $locParts = [];
                                            if (!empty($match['venue_address'])) $locParts[] = ucwords($match['venue_address']);
                                            if (!empty($match['venue_city'])) $locParts[] = ucwords($match['venue_city']);
                                            if (!empty($match['venue_state'])) $locParts[] = ucwords($match['venue_state']);
                                            if (!empty($match['venue_country'])) $locParts[] = ucwords($match['venue_country']);
                                            echo htmlspecialchars(implode(', ', $locParts));
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Range</p>
                                <p class="mt-1 text-sm font-semibold text-white">&#8377;<?php echo number_format((float) $match['starting_price'], 0); ?> - &#8377;<?php echo number_format((float) $match['highest_price'], 0); ?></p>
                            </div>

                            <a href="booking.php?match_id=<?php echo $match['id']; ?>" class="rounded-full bg-white px-5 py-3 text-sm font-bold uppercase tracking-[0.18em] text-slate-950 hover:bg-amber-200">
                                Book Now
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section id="seat-zones" class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-300">Seat Zones</p>
                <h2 class="mt-4 text-5xl text-white sm:text-6xl">Pricing Bands That Update From Inventory</h2>
                <p class="mt-4 max-w-xl text-base leading-8 text-slate-300">These cards are pulled from your seat category and venue category tables, so the homepage stays connected to your real booking setup.</p>

                <div class="mt-8 flex items-center gap-3">
                    <button id="btnScrollLeft" class="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white transition-all hover:-translate-x-1 active:scale-95" aria-label="Scroll left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button id="btnScrollRight" class="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white transition-all hover:translate-x-1 active:scale-95" aria-label="Scroll right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <span class="ml-3 text-[0.65rem] font-bold uppercase tracking-[0.25em] text-amber-200/80 animate-pulse flex items-center gap-2">Swipe to explore <i class="fas fa-arrow-right-long"></i></span>
                </div>
            </div>

            <div id="seatScrollContainer" class="flex gap-4 overflow-x-auto pb-12 pt-4 snap-x snap-mandatory scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:'none'] [scrollbar-width:'none']" style="-webkit-overflow-scrolling: touch;">
                <?php if (empty($seatCategories)): ?>
                    <div class="glass rounded-[2rem] p-8 text-center w-full">
                        <p class="text-white">No seat categories found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($seatCategories as $category): ?>
                        <a href="matches.php?category=<?php echo (int) $category['id']; ?>"
                           class="seat-card p-4 lg:p-5 flex flex-col justify-between flex-shrink-0 w-[260px] sm:w-[280px] snap-start"
                           style="--seat-color: <?php echo htmlspecialchars($category['color_code'] ?: '#8b5cf6'); ?>;"
                        >
                            <div>
                                <div class="relative z-10 flex items-center gap-2.5 mb-2">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: var(--seat-color); box-shadow: 0 0 10px var(--seat-color);"></span>
                                    <h3 class="text-xl font-bold uppercase tracking-[0.08em] text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </h3>
                                </div>

                                <p class="relative z-10 text-[0.88rem] leading-snug text-slate-300 mb-4 min-h-[2.6rem]">
                                    <?php echo htmlspecialchars($category['description'] ?: 'Premium seats with good view'); ?>
                                </p>

                                <div class="relative z-10 rounded-2xl border border-white/5 bg-slate-950/60 p-4 mb-4 shadow-inner">
                                    <p class="text-[0.6rem] font-bold uppercase tracking-[0.25em] text-slate-400 mb-1">Price</p>
                                    <?php if ((float) $category['price'] > 0): ?>
                                        <p class="text-[1.8rem] font-black text-[#fde68a] drop-shadow-sm mb-1" style="letter-spacing: -0.01em;">₹<?php echo number_format((float) $category['price'], 0); ?></p>
                                        <p class="text-[0.7rem] font-medium text-slate-400">Starting price for this category</p>
                                    <?php else: ?>
                                        <p class="text-base font-semibold text-slate-300 mt-1 mb-1">To be announced</p>
                                    <?php endif; ?>
                                </div>

                                <p class="mt-5 text-[0.7rem] font-medium text-slate-400">
                                    <?php echo (int) $category['match_count']; ?> <?php echo ((int) $category['match_count'] === 1) ? 'match' : 'matches'; ?> available
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="feedback" class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="relative">

            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold tracking-wide text-white display-font sm:text-5xl">Feedback</h2>
                <div class="mt-6 flex flex-col items-center justify-center">
                    <button id="openFeedbackModal" class="rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-8 py-3 text-sm font-bold uppercase tracking-[0.2em] text-slate-950 focus:outline-none transition-transform hover:scale-105">
                        Give Feedback
                    </button>

                    <?php if (!empty($feedbackSuccess)): ?>
                        <div id="feedbackAlertMsg" class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200 transition-opacity duration-300"><?php echo htmlspecialchars($feedbackSuccess); ?></div>
                        <script>
                            setTimeout(function() {
                                const alertMsg = document.getElementById('feedbackAlertMsg');
                                if(alertMsg) {
                                    alertMsg.style.opacity = '0';
                                    setTimeout(() => alertMsg.remove(), 300);
                                }
                            }, 2000);
                        </script>
                    <?php endif; ?>
                    <?php if (!empty($feedbackError) && !isset($_POST['submit_feedback'])): ?>
                        <div class="mt-6 rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php echo htmlspecialchars($feedbackError); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-6 border-b border-white/10 pb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-[0.28em] text-amber-200">Recent Feedbacks</h3>
                <?php if (!empty($feedbacks)): ?>
                <div class="flex items-center gap-2">
                    <button id="btnFeedbackLeft" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white transition-all hover:-translate-x-1 active:scale-95" aria-label="Scroll left">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </button>
                    <button id="btnFeedbackRight" class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white transition-all hover:translate-x-1 active:scale-95" aria-label="Scroll right">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="w-full min-w-0 relative">
                <?php if (empty($feedbacks)): ?>
                    <div class="glass rounded-[2rem] p-8 text-center w-full">
                        <i class="fas fa-comments text-4xl text-slate-500"></i>
                        <p class="mt-4 text-slate-300 font-medium">No feedback available yet.</p>
                    </div>
                <?php else: ?>
                    <div id="feedbackScrollContainer" class="flex gap-4 overflow-x-auto pb-4 pt-2 snap-x snap-mandatory scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:'none'] [scrollbar-width:'none']" style="-webkit-overflow-scrolling: touch;">
                        <?php foreach ($feedbacks as $feedback): ?>
                            <?php
                            $nameStr = trim($feedback['user_name']);
                            $initial = !empty($nameStr) ? strtoupper(substr($nameStr, 0, 1)) : 'U';
                            $emptyStars = 5 - (int)$feedback['rating'];
                            ?>
                            <div class="flex-shrink-0 w-[280px] sm:w-[320px] snap-start h-auto flex">
                                <article class="glass card-rise rounded-[2rem] p-6 flex flex-col justify-start w-full">
                                    <div class="flex items-center gap-4 mb-4">
                                        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-gradient-to-br from-orange-500 to-amber-500 flex items-center justify-center text-slate-950 font-bold text-lg shadow-sm">
                                            <?php echo htmlspecialchars($initial); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-base font-bold text-white truncate"><?php echo htmlspecialchars($nameStr); ?></p>
                                            <div class="flex items-center gap-0.5 mt-1">
                                                <?php for ($i = 0; $i < (int)$feedback['rating']; $i++): ?>
                                                    <i class="fas fa-star text-amber-400 text-xs"></i>
                                                <?php endfor; ?>
                                                <?php for ($i = 0; $i < $emptyStars; $i++): ?>
                                                    <i class="fas fa-star text-slate-600 text-xs"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-sm leading-7 text-slate-200 italic">"<?php echo htmlspecialchars($feedback['message']); ?>"</p>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="feedbackModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4 opacity-0 pointer-events-none transition-opacity duration-300">
                <div class="modal-content glass w-full max-w-lg rounded-[2rem] p-6 sm:p-8 relative transform scale-95 transition-transform duration-300">
                    <button id="closeFeedbackModal" class="absolute top-6 right-6 text-slate-400 hover:text-white transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10"><i class="fas fa-times text-lg"></i></button>
                    <h3 class="text-2xl font-bold text-white mb-6">Share Your Matchday Review</h3>

                    <?php if (!empty($feedbackError) && isset($_POST['submit_feedback'])): ?>
                        <div class="mb-6 rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php echo htmlspecialchars($feedbackError); ?></div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (empty($userBookings)): ?>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center">
                                <p class="text-sm text-slate-300">You need at least one booking before submitting feedback.</p>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="space-y-5 text-left">
                                <div>
                                    <label for="bookingSelect" class="mb-2 block text-sm font-semibold text-slate-200">Select Booking</label>
                                    <select name="booking_id" id="bookingSelect" class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-white outline-none focus:border-amber-300/50" required>
                                        <option value="">Choose Booking</option>
                                        <?php foreach ($userBookings as $booking): ?>
                                            <option value="<?php echo $booking['booking_id']; ?>" data-match="<?php echo $booking['match_id']; ?>"><?php echo htmlspecialchars($booking['booking_code']); ?> - <?php echo htmlspecialchars($booking['team1_name']); ?> vs <?php echo htmlspecialchars($booking['team2_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="match_id" id="matchIdField">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-200">Rating</label>
                                    <div class="flex items-center gap-2 star-rating-group bg-slate-950/60 border border-white/10 rounded-2xl px-4 py-3">
                                        <input type="hidden" name="rating" id="ratingInput" required>
                                        <button type="button" class="star-btn text-slate-600 hover:text-amber-400 text-2xl transition-colors focus:outline-none" data-val="1"><i class="fas fa-star drop-shadow-sm"></i></button>
                                        <button type="button" class="star-btn text-slate-600 hover:text-amber-400 text-2xl transition-colors focus:outline-none" data-val="2"><i class="fas fa-star drop-shadow-sm"></i></button>
                                        <button type="button" class="star-btn text-slate-600 hover:text-amber-400 text-2xl transition-colors focus:outline-none" data-val="3"><i class="fas fa-star drop-shadow-sm"></i></button>
                                        <button type="button" class="star-btn text-slate-600 hover:text-amber-400 text-2xl transition-colors focus:outline-none" data-val="4"><i class="fas fa-star drop-shadow-sm"></i></button>
                                        <button type="button" class="star-btn text-slate-600 hover:text-amber-400 text-2xl transition-colors focus:outline-none" data-val="5"><i class="fas fa-star drop-shadow-sm"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label for="message" class="mb-2 block text-sm font-semibold text-slate-200">Message</label>
                                    <textarea name="message" id="message" rows="4" class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-white outline-none focus:border-amber-300/50 resize-y min-h-[100px]" placeholder="Write your experience..." required></textarea>
                                </div>
                                <button type="submit" name="submit_feedback" class="w-full rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-6 py-3.5 text-sm font-bold uppercase tracking-[0.2em] text-slate-950 transition-colors mt-2">Submit Feedback</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center">
                            <p class="text-sm leading-7 text-slate-300">Sign in to submit feedback after you book a match.</p>
                            <div class="mt-5 flex items-center justify-center gap-3">
                                <a href="login.php" class="rounded-full bg-white px-5 py-2.5 text-sm font-bold uppercase tracking-[0.18em] text-slate-950 hover:bg-amber-200">Login</a>
                                <a href="register.php" class="rounded-full border border-white/10 px-5 py-2.5 text-sm font-bold uppercase tracking-[0.18em] text-white hover:bg-white/5">Register</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>
</main>

<footer class="border-t border-white/10 bg-slate-950/70 pb-8 pt-14">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <p class="display-font text-4xl text-white">Cricket Ticket Booking</p>
                <p class="mt-4 max-w-sm text-sm leading-7 text-slate-300">Dynamic cricket ticket booking with live match visibility, seat pricing, and fan reviews in one homepage.</p>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-slate-400">Quick Links</p>
                <div class="mt-4 flex flex-col gap-3 text-sm text-slate-300">
                    <a href="#top" class="hover:text-white">Home</a>
                    <a href="#matches" class="hover:text-white">Matches</a>
                    <a href="#seat-zones" class="hover:text-white">Seat Zones</a>
                    <a href="#feedback" class="hover:text-white">Feedback</a>
                </div>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-slate-400">Account</p>
                <div class="mt-4 flex flex-col gap-3 text-sm text-slate-300">
                    <a href="matches.php" class="hover:text-white">View All Matches</a>
                    <a href="MyBooking.php" class="hover:text-white">My Bookings</a>
                    <a href="profile.php" class="hover:text-white">Profile</a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="hover:text-white">Login</a>
                    <?php else: ?>
                        <a href="logout.php" class="hover:text-white">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.28em] text-slate-400">Platform Snapshot</p>
                <div class="mt-4 space-y-3 text-sm text-slate-300">
                    <p><?php echo $totalMatches; ?> matches tracked</p>
                    <p><?php echo $liveMatchesCount; ?> matches live right now</p>
                    <p><?php echo $totalBookings; ?> total bookings</p>
                    <p><?php echo $totalUsers; ?> registered fans</p>
                </div>
            </div>
        </div>

        <div class="mt-10 flex flex-col gap-4 border-t border-white/10 pt-6 text-sm text-slate-400 md:flex-row md:items-center md:justify-between">
            <p>&copy; <?php echo date('Y'); ?> Cricket Ticket Hub. All rights reserved.</p>
            <a href="#top" class="inline-flex items-center gap-2 text-white hover:text-amber-200">Back to top <i class="fas fa-arrow-up text-xs"></i></a>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const bookingSelect = document.getElementById('bookingSelect');
    const matchIdField = document.getElementById('matchIdField');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const matchCards = document.querySelectorAll('[data-status]');
    const homeClock = document.getElementById('homeClock');

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

    if (bookingSelect && matchIdField) {
        bookingSelect.addEventListener('change', function () {
            const selected = bookingSelect.options[bookingSelect.selectedIndex];
            matchIdField.value = selected ? (selected.getAttribute('data-match') || '') : '';
        });
    }

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const filter = button.getAttribute('data-filter');
            filterButtons.forEach(function (item) {
                item.classList.remove('active');
            });
            button.classList.add('active');

            if (filter === 'all') {
                // Default: show only first 3 cards
                let shown = 0;
                matchCards.forEach(function (card) {
                    if (shown < 3) {
                        card.style.display = '';
                        shown++;
                    } else {
                        card.style.display = 'none';
                    }
                });
            } else {
                // Filter: show ALL matching cards
                matchCards.forEach(function (card) {
                    const status = card.getAttribute('data-status');
                    card.style.display = filter === status ? '' : 'none';
                });
            }
        });
    });

    // On page load: show only first 3 cards by default
    let initialShown = 0;
    matchCards.forEach(function (card) {
        if (initialShown < 3) {
            card.style.display = '';
            initialShown++;
        } else {
            card.style.display = 'none';
        }
    });


    function updateClock() {
        if (!homeClock) return;

        const formatter = new Intl.DateTimeFormat('en-IN', {
            timeZone: 'Asia/Kolkata',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });

        homeClock.textContent = formatter.format(new Date()) + ' IST';
    }

    updateClock();
    setInterval(updateClock, 1000);

    const seatScrollContainer = document.getElementById('seatScrollContainer');
    const btnScrollLeft = document.getElementById('btnScrollLeft');
    const btnScrollRight = document.getElementById('btnScrollRight');

    if (seatScrollContainer && btnScrollLeft && btnScrollRight) {
        btnScrollLeft.addEventListener('click', () => {
            seatScrollContainer.scrollBy({ left: -300, behavior: 'smooth' });
        });

        btnScrollRight.addEventListener('click', () => {
            seatScrollContainer.scrollBy({ left: 300, behavior: 'smooth' });
        });
    }

    const feedbackModal = document.getElementById('feedbackModal');
    const openFeedbackModalBtn = document.getElementById('openFeedbackModal');
    const closeFeedbackModalBtn = document.getElementById('closeFeedbackModal');

    function openFeedbackModal() {
        if (feedbackModal) {
            feedbackModal.classList.remove('opacity-0', 'pointer-events-none');
            const content = feedbackModal.querySelector('.modal-content');
            if (content) {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }
        }
    }

    function closeFeedbackModal() {
        if (feedbackModal) {
            feedbackModal.classList.add('opacity-0', 'pointer-events-none');
            const content = feedbackModal.querySelector('.modal-content');
            if (content) {
                content.classList.remove('scale-100');
                content.classList.add('scale-95');
            }
        }
    }

    if (openFeedbackModalBtn) openFeedbackModalBtn.addEventListener('click', openFeedbackModal);
    if (closeFeedbackModalBtn) closeFeedbackModalBtn.addEventListener('click', closeFeedbackModal);

    if (feedbackModal) {
        feedbackModal.addEventListener('click', (e) => {
            if (e.target === feedbackModal) closeFeedbackModal();
        });
    }

    <?php if (!empty($feedbackError) && isset($_POST['submit_feedback'])): ?>
    openFeedbackModal();
    <?php endif; ?>

    const starBtns = document.querySelectorAll('.star-btn');
    const ratingInput = document.getElementById('ratingInput');

    if (starBtns && ratingInput) {
        starBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const val = parseInt(btn.getAttribute('data-val'));
                ratingInput.value = val;

                starBtns.forEach(b => {
                    const bVal = parseInt(b.getAttribute('data-val'));
                    if (bVal <= val) {
                        b.classList.remove('text-slate-600');
                        b.classList.add('text-amber-400');
                    } else {
                        b.classList.remove('text-amber-400');
                        b.classList.add('text-slate-600');
                    }
                });
            });

            btn.addEventListener('mouseenter', () => {
                if (ratingInput.value !== '') return;
                const val = parseInt(btn.getAttribute('data-val'));
                starBtns.forEach(b => {
                    if (parseInt(b.getAttribute('data-val')) <= val) {
                        b.classList.add('opacity-75', 'text-amber-400');
                        b.classList.remove('text-slate-600');
                    }
                });
            });

            btn.addEventListener('mouseleave', () => {
                if (ratingInput.value !== '') return;
                starBtns.forEach(b => {
                    b.classList.remove('opacity-75', 'text-amber-400');
                    b.classList.add('text-slate-600');
                });
            });
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const feedbackScrollContainer = document.getElementById('feedbackScrollContainer');
    const btnFeedbackLeft = document.getElementById('btnFeedbackLeft');
    const btnFeedbackRight = document.getElementById('btnFeedbackRight');

    if (feedbackScrollContainer && btnFeedbackLeft && btnFeedbackRight) {
        btnFeedbackLeft.addEventListener('click', () => {
            feedbackScrollContainer.scrollBy({ left: -320, behavior: 'smooth' });
        });

        btnFeedbackRight.addEventListener('click', () => {
            feedbackScrollContainer.scrollBy({ left: 320, behavior: 'smooth' });
        });
    }
});
</script>
<!-- How Many Seats Modal -->
<div id="seatCountModal" class="seat-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="seatModalTitle">
    <div class="seat-modal-box">
        <div class="seat-modal-top">
            <button class="seat-modal-close" id="closeSeatModal" aria-label="Close"><i class="fas fa-times"></i></button>
            <div style="font-size:42px; margin-bottom:10px;">🏟️</div>
            <h3 id="seatModalTitle">How Many Seats?</h3>
            <p>Select the number of tickets you want to book</p>
        </div>
        <div class="seat-modal-body">
            <div class="seat-count-label">Choose quantity (1 – 10)</div>
            <div class="seat-numbers">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <button type="button" class="seat-num-btn" data-val="<?php echo $i; ?>"><?php echo $i; ?></button>
                <?php endfor; ?>
            </div>
            <div class="seat-modal-hint">
                ℹ️ Minimum <strong style="color:#f4c95d">1</strong> and maximum <strong style="color:#f4c95d">10</strong> tickets per booking.
            </div>
            <button type="button" id="seatModalProceed" class="seat-modal-btn" disabled>
                <i class="fas fa-chair" style="margin-right:8px"></i> Select Seats
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('seatCountModal');
    const closeBtn = document.getElementById('closeSeatModal');
    const proceedBtn = document.getElementById('seatModalProceed');
    const numBtns = document.querySelectorAll('.seat-num-btn');
    const openBtns = document.querySelectorAll('.open-seat-modal');
    let selectedQty = 0;
    let selectedMatchId = null;

    function openModal(matchId) {
        selectedMatchId = matchId;
        selectedQty = 0;
        numBtns.forEach(b => b.classList.remove('selected'));
        proceedBtn.disabled = true;
        proceedBtn.textContent = 'Select Seats';
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-match-id'));
        });
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    numBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            numBtns.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            selectedQty = parseInt(btn.getAttribute('data-val'));
            proceedBtn.disabled = false;
            proceedBtn.innerHTML = '<i class="fas fa-chair" style="margin-right:8px"></i> Select Seats (' + selectedQty + ')';
        });
    });

    proceedBtn.addEventListener('click', function () {
        if (selectedQty > 0 && selectedMatchId) {
            window.location.href = 'booking.php?match_id=' + selectedMatchId + '&qty=' + selectedQty;
        }
    });
});
</script>

</body>
</html>