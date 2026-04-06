<?php
session_start();
require_once 'connection.php';
require_once 'models/Booking.php';

$ticketCount = 0;
$userProfileImage = 'default.jpg';
if (isset($_SESSION['user_id'])) {
  $headerUserId = (int) $_SESSION['user_id'];

  $ticketStmt = $link->prepare("SELECT COUNT(*) AS total FROM bookings WHERE user_id = ?");
  $ticketStmt->bind_param("i", $headerUserId);
  $ticketStmt->execute();
  $ticketRow = $ticketStmt->get_result()->fetch_assoc();
  $ticketCount = (int) ($ticketRow['total'] ?? 0);
  $ticketStmt->close();

  $imgStmt = $link->prepare("SELECT profile_image FROM users WHERE id = ?");
  $imgStmt->bind_param("i", $headerUserId);
  $imgStmt->execute();
  $imgRow = $imgStmt->get_result()->fetch_assoc();
  $userProfileImage = $imgRow['profile_image'] ?? 'default.jpg';
  $imgStmt->close();
}

if (!isset($_GET['match_id']) || !is_numeric($_GET['match_id'])) {
  header('Location: index.php');
  exit();
}

$match_id = (int) $_GET['match_id'];
$preselected_qty = isset($_GET['qty']) ? max(1, min(10, (int) $_GET['qty'])) : 0;

// Fetch match details
$matchQuery = "
    SELECT 
        m.*,
        t1.name AS team1_name,
        t1.short_name AS team1_short,
        t1.logo AS team1_logo,
        t2.name AS team2_name,
        t2.short_name AS team2_short,
        t2.logo AS team2_logo,
        v.name AS venue_name,
        v.city AS venue_city,
        v.capacity AS venue_capacity
    FROM matches m
    INNER JOIN teams t1 ON m.team1_id = t1.id
    INNER JOIN teams t2 ON m.team2_id = t2.id
    INNER JOIN venues v ON m.venue_id = v.id
    WHERE m.id = $match_id
    LIMIT 1
";

$matchResult = mysqli_query($link, $matchQuery);
$match = $matchResult ? mysqli_fetch_assoc($matchResult) : null;

if (!$match) {
  header('Location: index.php');
  exit();
}

// Fetch venue categories
$categoryQuery = "
    SELECT 
        vc.id AS venue_category_id,
        vc.category_id,
        vc.no_of_seats,
        vc.color_code,
        vc.price,
        vc.amenities,
        sc.name AS category_name,
        sc.description
    FROM venue_category vc
    INNER JOIN seat_categories sc ON vc.category_id = sc.id
    WHERE vc.venue_id = {$match['venue_id']}
    ORDER BY vc.price ASC
";

$categoryResult = mysqli_query($link, $categoryQuery);
$categories = [];
while ($row = mysqli_fetch_assoc($categoryResult)) {
  $categories[] = $row;
}

if (empty($categories)) {
  die("No seat categories found for this match venue.");
}

$defaultCategory = $categories[0];
$defaultCategoryId = (int) $defaultCategory['category_id'];

// Fetch booked seats ONLY for default category
$bookedSeats = [];
$bookedSeatQuery = "
    SELECT bi.seats_no
    FROM booking_items bi
    INNER JOIN bookings b ON b.id = bi.booking_id
    WHERE b.match_id = $match_id
      AND bi.category_id = $defaultCategoryId
      AND b.booking_status = 'confirmed'
      AND b.payment_status = 'success'
";

$bookedSeatResult = mysqli_query($link, $bookedSeatQuery);
if ($bookedSeatResult) {
  while ($row = mysqli_fetch_assoc($bookedSeatResult)) {
    if (!empty($row['seats_no'])) {
      $seats = explode(',', $row['seats_no']);
      foreach ($seats as $seat) {
        $seat = trim($seat);
        if ($seat !== '') {
          $bookedSeats[] = $seat;
        }
      }
    }
  }
}
$bookedSeats = array_values(array_unique($bookedSeats));

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
  try {
    $selected_category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'upi';
    $selected_seats_json = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : '[]';
    $selected_seats = json_decode($selected_seats_json, true);

    if (!$selected_category_id) {
      throw new Exception("Please select a category.");
    }

    if (!is_array($selected_seats) || count($selected_seats) === 0) {
      throw new Exception("Please select at least one seat.");
    }

    if (count($selected_seats) > 10) {
      throw new Exception("You cannot book more than 10 seats at a time.");
    }

    // Re-check seats only for selected category
    $alreadyBookedSeats = [];
    $recheckQuery = "
            SELECT bi.seats_no
            FROM booking_items bi
            INNER JOIN bookings b ON b.id = bi.booking_id
            WHERE b.match_id = $match_id
              AND bi.category_id = $selected_category_id
              AND b.booking_status = 'confirmed'
              AND b.payment_status = 'success'
        ";

    $recheckResult = mysqli_query($link, $recheckQuery);
    if ($recheckResult) {
      while ($row = mysqli_fetch_assoc($recheckResult)) {
        if (!empty($row['seats_no'])) {
          $dbSeats = explode(',', $row['seats_no']);
          foreach ($dbSeats as $seat) {
            $seat = trim($seat);
            if ($seat !== '') {
              $alreadyBookedSeats[] = $seat;
            }
          }
        }
      }
    }

    $alreadyBookedSeats = array_values(array_unique($alreadyBookedSeats));
    $conflictSeats = array_intersect($selected_seats, $alreadyBookedSeats);

    if (!empty($conflictSeats)) {
      throw new Exception("These seats are already booked in this category: " . implode(', ', $conflictSeats));
    }

    $bookingModel = new Booking($link);
    $booking_code = $bookingModel->createBooking(
      $_SESSION['user_id'],
      $match_id,
      $selected_category_id,
      $selected_seats,
      $payment_method
    );

    header("Location: booking-confirmation.php?booking_id=" . urlencode($booking_code));
    exit();

  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

$stadium_bg = 'image/stadium.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Tickets - Cricket Booking</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    .display-font {
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 0.04em;
    }

    body {
      font-family: Arial, sans-serif;
      color: #fff;
      min-height: 100vh;
      background:
        radial-gradient(circle at top left, rgba(59, 130, 246, 0.10), transparent 22%),
        radial-gradient(circle at top right, rgba(168, 85, 247, 0.10), transparent 22%),
        radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.08), transparent 25%),
        linear-gradient(135deg, #040814 0%, #091120 45%, #050b18 100%);
    }

    .glass {
      background: rgba(255, 255, 255, 0.04);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
    }

    .top-border {
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .main-wrap {
      max-width: 1450px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .match-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      padding: 28px 0 22px;
      flex-wrap: wrap;
    }

    .back-btn {
      color: #fff;
      text-decoration: none;
      font-size: 18px;
      opacity: 0.9;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .back-btn:hover {
      opacity: 1;
    }

    .match-info {
      display: flex;
      align-items: flex-start;
      gap: 18px;
      flex-wrap: wrap;
    }

    .teams-wrap h1 {
      font-size: 45px;
      font-weight: 800;
      letter-spacing: 1px;
      display: flex;
      align-items: center;
      gap: 18px;
      flex-wrap: wrap;
    }

    .vs-text {
      color: #cbd5e1;
      font-size: 40px;
      font-weight: 700;
    }

    .flag-box {
      min-width: 78px;
      height: 54px;
      border-radius: 14px;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 15px;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
      padding: 0 16px;
      text-transform: uppercase;
    }

    .flag-box:has(.flag-logo) {
      background: none;
      border: none;
      box-shadow: none;
      padding: 0;
    }

    .flag-logo {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .match-subtext {
      color: #cbd5e1;
      margin-top: 10px;
      font-size: 18px;
    }

    .steps {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    .step {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255, 255, 255, 0.55);
      font-size: 18px;
      font-weight: 600;
    }

    .step-number {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
    }

    .step.active {
      color: #fff;
    }

    .step.active .step-number {
      background: #f6c84c;
      color: #111827;
    }

    .arrow {
      color: rgba(255, 255, 255, 0.3);
      font-size: 22px;
    }

    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 34px;
      padding: 34px 0 40px;
      align-items: start;
    }

    .category-list {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 26px;
    }

    .category-card {
      min-width: 150px;
      border-radius: 18px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      padding: 16px 24px;
      text-align: left;
      color: #fff;
      background: rgba(255, 255, 255, 0.03);
      cursor: pointer;
      transition: 0.3s ease;
      position: relative;
    }

    .category-card .cat-name {
      font-size: 20px;
      font-weight: 700;
    }

    .category-card .cat-price {
      font-size: 18px;
      margin-top: 4px;
      opacity: 0.95;
    }

    .category-card .cat-seat {
      font-size: 14px;
      color: #cbd5e1;
      margin-top: 6px;
    }

    .category-card.active-tab {
      border-color: rgba(250, 204, 21, 0.75);
      box-shadow: 0 0 25px rgba(250, 204, 21, 0.22);
      background: linear-gradient(180deg, rgba(250, 204, 21, 0.18), rgba(255, 255, 255, 0.03));
    }

    .category-card.active-tab::after {
      content: "";
      position: absolute;
      left: 50%;
      bottom: -10px;
      transform: translateX(-50%);
      width: 0;
      height: 0;
      border-left: 10px solid transparent;
      border-right: 10px solid transparent;
      border-top: 10px solid #f6c84c;
    }

    .stadium-box {
      position: relative;
      border-radius: 34px;
      min-height: 760px;
      overflow: hidden;
      padding: 24px 24px 28px;
      background: radial-gradient(circle at center, rgba(255, 255, 255, 0.02), transparent 42%),
        linear-gradient(180deg, rgba(8, 14, 28, 0.96), rgba(6, 11, 24, 0.98));
    }

    .stadium-stage {
      position: relative;
      width: 100%;
      min-height: 760px;
    }

    .stadium-image-wrap {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      pointer-events: none;
      z-index: 1;
    }

    .stadium-image {
      width: 100%;
      max-width: 980px;
      height: auto;
      object-fit: contain;
      opacity: 0.95;
      filter: drop-shadow(0 30px 50px rgba(0, 0, 0, 0.4));
      user-select: none;
      pointer-events: none;
    }

    .overlay-label {
      position: absolute;
      z-index: 4;
      font-weight: 800;
      letter-spacing: 2px;
      text-shadow: 0 2px 12px rgba(0, 0, 0, 0.55);
      color: rgba(255, 255, 255, 0.92);
      pointer-events: none;
    }

    .gate1 {
      top: 150px;
      left: 28px;
      font-size: 14px;
    }

    .gate2 {
      top: 150px;
      right: 28px;
      font-size: 14px;
    }

    .gate3 {
      bottom: 150px;
      left: 20px;
      font-size: 14px;
    }

    .gate4 {
      bottom: 150px;
      right: 20px;
      font-size: 14px;
    }

    .premium-top {
      position: absolute;
      top: 118px;
      left: 50%;
      transform: translateX(-50%);
      width: 210px;
      height: 70px;
      z-index: 5;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 900;
      color: #2b2100;
      background: linear-gradient(180deg, rgba(250, 204, 21, 0.95), rgba(214, 158, 12, 0.85));
      clip-path: polygon(8% 0%, 92% 0%, 100% 40%, 90% 100%, 10% 100%, 0% 40%);
      box-shadow: 0 10px 22px rgba(250, 204, 21, 0.22);
      pointer-events: none;
    }

    .left-side-label {
      left: 62px;
      top: 355px;
      transform: rotate(-90deg);
      font-size: 13px;
    }

    .right-side-label {
      right: 52px;
      top: 350px;
      transform: rotate(90deg);
      font-size: 13px;
      text-align: center;
      line-height: 1.1;
    }

    .bottom-side-label {
      left: 50%;
      bottom: 128px;
      transform: translateX(-50%);
      font-size: 15px;
    }

    .field-text {
      position: absolute;
      top: 190px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 4;
      font-size: 18px;
      font-weight: 900;
      letter-spacing: 2px;
      color: rgba(255, 255, 255, 0.88);
      text-shadow: 0 2px 12px rgba(0, 0, 0, 0.55);
      pointer-events: none;
    }

    .seat-overlay {
      position: absolute;
      left: 50%;
      top: 285px;
      transform: translateX(-50%);
      width: 570px;
      max-width: 92%;
      z-index: 6;
      background: linear-gradient(180deg, rgba(9, 18, 38, 0.88), rgba(2, 8, 24, 0.95));
      border: 1px solid rgba(255, 255, 255, 0.07);
      border-radius: 18px 18px 20px 20px;
      padding: 22px 20px 18px;
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .seat-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 12px;
      flex-wrap: nowrap;
    }

    .row-label {
      width: 28px;
      text-align: center;
      font-size: 28px;
      font-weight: 800;
      color: rgba(255, 255, 255, 0.95);
      margin-right: 8px;
    }

    .seat {
      width: 54px;
      height: 42px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      transition: 0.25s ease;
      border: 1px solid rgba(255, 255, 255, 0.08);
      user-select: none;
    }

    .seat.available {
      background: linear-gradient(180deg, #dfe4ea, #b6bec9);
      color: #1f2937;
    }

    .seat.available:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 255, 255, 0.08);
    }

    .seat.selected {
      background: linear-gradient(180deg, #78e06d, #2fa14a);
      color: #fff;
      box-shadow: 0 0 0 1px rgba(74, 222, 128, 0.35), 0 0 20px rgba(74, 222, 128, 0.20);
    }

    .seat.booked {
      background: linear-gradient(180deg, #4e5c72, #2d384a);
      color: rgba(255, 255, 255, 0.78);
      opacity: 0.9;
      cursor: not-allowed;
    }

    .legend {
      display: flex;
      gap: 28px;
      flex-wrap: wrap;
      align-items: center;
      margin-top: 22px;
      justify-content: center;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #d1d5db;
      font-size: 18px;
    }

    .legend-box {
      width: 26px;
      height: 22px;
      border-radius: 6px;
      display: inline-block;
    }

    .summary-card {
      border-radius: 28px;
      padding: 24px;
      position: sticky;
      top: 20px;
    }

    .summary-card h2 {
      font-size: 24px;
      margin-bottom: 18px;
      font-weight: 800;
    }

    .stadium-preview {
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 22px;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.03);
      margin-bottom: 22px;
    }

    .preview-top {
      height: 170px;
      background: linear-gradient(rgba(4, 10, 20, 0.16), rgba(4, 10, 20, 0.40)),
        url('<?php echo $stadium_bg; ?>') center center / cover no-repeat;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #cbd5e1;
      font-size: 18px;
      font-weight: 600;
      position: relative;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
    }

    .preview-bottom {
      padding: 20px;
    }

    .category-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .category-price {
      font-size: 20px;
      font-weight: 800;
      margin-bottom: 4px;
    }

    .small-text {
      color: #cbd5e1;
      font-size: 18px;
    }

    .selected-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .selected-header h3 {
      font-size: 20px;
      font-weight: 700;
    }

    .clear-btn {
      background: none;
      border: none;
      color: #cbd5e1;
      cursor: pointer;
      font-size: 16px;
    }

    .clear-btn:hover {
      color: #fff;
    }

    .selected-seats-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .summary-seat {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      min-width: 72px;
      max-width: 72px;
      background: rgba(1, 212, 79, 0.77);
      border: 1px solid rgba(74, 222, 128, 0.18);
      padding: 10px 8px;
      border-radius: 12px;
      text-align: center;
    }

    .summary-seat-name {
      font-size: 15px;
      font-weight: 800;
      color: #fff;
      line-height: 1.1;
    }

    .summary-seat-row {
      color: #fff;
      font-size: 12px;
      margin-top: 3px;
      line-height: 1.1;
    }

    .price-area {
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      margin-top: 18px;
      padding-top: 18px;
    }

    .price-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;
      font-size: 18px;
      font-weight: 700;
    }

    .price-line .label {
      color: #fff;
    }

    .total-line {
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      margin-top: 10px;
      padding-top: 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .total-line .total-label {
      font-size: 20px;
      font-weight: 700;
    }

    .total-line .total-value {
      font-size: 25px;
      font-weight: 800;
    }

    .checkout-btn {
      width: 100%;
      margin-top: 22px;
      padding: 18px 20px;
      border: none;
      border-radius: 18px;
      cursor: pointer;
      font-size: 20px;
      font-weight: 750;
      color: #fff;
      background: linear-gradient(90deg, #2f8f54);
      box-shadow: 0 12px 28px rgba(74, 222, 128, 0.20);
      transition: 0.3s ease;
    }

    .checkout-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 34px rgba(74, 222, 128, 0.28);
    }

    .empty-seat-text {
      color: #9ca3af;
      font-size: 17px;
    }

    .error-box {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.35);
      color: #fecaca;
      padding: 14px 16px;
      border-radius: 14px;
      margin-bottom: 20px;
    }

    .login-box {
      background: rgba(250, 204, 21, 0.12);
      border: 1px solid rgba(250, 204, 21, 0.30);
      color: #fde68a;
      padding: 14px 16px;
      border-radius: 14px;
      margin-top: 18px;
    }

    .payment-select {
      width: 100%;
      background: rgba(255, 255, 255, 0.06);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: 14px;
      padding: 14px;
      margin-top: 18px;
      outline: none;
    }

    .payment-select option {
      color: #111;
    }

    @media (max-width: 1200px) {
      .content-grid {
        grid-template-columns: 1fr;
      }

      .summary-card {
        position: static;
      }
    }

    @media (max-width: 900px) {
      .teams-wrap h1 {
        font-size: 30px;
      }

      .vs-text {
        font-size: 28px;
      }

      .stadium-box {
        min-height: 700px;
        padding: 18px 14px 22px;
      }

      .stadium-stage {
        min-height: 640px;
      }

      .seat-overlay {
        width: 520px;
        top: 265px;
      }

      .seat {
        width: 46px;
        height: 38px;
        font-size: 13px;
      }

      .row-label {
        font-size: 22px;
        width: 24px;
      }

      .premium-top {
        top: 102px;
        width: 180px;
        height: 62px;
        font-size: 18px;
      }

      .field-text {
        top: 172px;
        font-size: 15px;
      }
    }

    @media (max-width: 600px) {
      .category-card {
        min-width: 100%;
      }

      .teams-wrap h1 {
        font-size: 20px;
        gap: 10px;
      }

      .flag-box {
        min-width: 58px;
        height: 42px;
        font-size: 12px;
        padding: 0 12px;
      }

      .match-subtext {
        font-size: 15px;
      }

      .stadium-stage {
        min-height: 590px;
      }

      .seat-overlay {
        top: 245px;
        width: 92%;
        padding: 16px 10px 14px;
      }

      .seat {
        width: 38px;
        height: 34px;
        font-size: 11px;
      }

      .seat-row {
        gap: 6px;
      }

      .row-label {
        width: 18px;
        font-size: 16px;
        margin-right: 4px;
      }

      .total-line .total-value {
        font-size: 34px;
      }

      .checkout-btn {
        font-size: 18px;
      }

      .summary-seat {
        min-width: 120px;
        max-width: 140px;
        padding: 8px 10px;
      }

      .summary-seat-name {
        font-size: 16px;
      }

      .summary-seat-row {
        font-size: 11px;
      }
    }

    /* ===== WHITE POPUP THEME ===== */
    .sq-overlay {
      position: fixed;
      inset: 0;
      z-index: 9000;
      background: rgba(15, 23, 42, 0.68);
      backdrop-filter: blur(6px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      transition: opacity 0.3s ease;
    }

    .sq-overlay.sq-hidden {
      opacity: 0;
      pointer-events: none;
    }

    .sq-modal {
      width: 100%;
      max-width: 560px;
      background: linear-gradient(180deg, #fafafa 0%, #f3f4f6 100%);
      border-radius: 24px;
      padding: 34px 24px 24px;
      box-shadow: 0 30px 90px rgba(0, 0, 0, 0.35);
      text-align: center;
      transform: scale(1);
      transition: transform 0.28s ease;
    }

    .sq-overlay.sq-hidden .sq-modal {
      transform: scale(0.95);
    }

    .sq-title {
      font-size: 20px;
      font-weight: 700;
      color: #374151;
      margin-bottom: 10px;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .sq-vehicle-wrap {
      position: relative;
      height: 180px;
      margin: 10px 0 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .sq-vehicle-img {
      position: absolute;
      inset: 0;
      margin: auto;
      width: 240px;
      height: 170px;
      object-fit: contain;
      mix-blend-mode: multiply;
      opacity: 0;
      transform: translateY(8px) scale(0.96);
      transition: opacity 0.32s ease, transform 0.32s ease;
      pointer-events: none;
    }

    .sq-vehicle-img.sq-active {
      opacity: 1;
      transform: translateY(0) scale(1);
    }

    .sq-numbers {
      display: flex;
      justify-content: center;
      gap: 6px;
      margin-bottom: 18px;
    }

    .sq-num {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      border: none;
      background: transparent;
      font-size: 17px;
      font-weight: 600;
      color: #374151;
      cursor: pointer;
      transition: all 0.18s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .sq-num:hover {
      background: rgba(244, 63, 94, 0.10);
      color: #e11d48;
    }

    .sq-num.sq-picked {
      background: #f43f5e;
      color: #fff;
      box-shadow: 0 6px 18px rgba(244, 63, 94, 0.35);
      transform: scale(1.08);
    }

    .sq-hint {
      background: #e5e7eb;
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 12px;
      color: #6b7280;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 22px;
      text-align: left;
      line-height: 1.5;
    }

    .sq-hint svg {
      flex-shrink: 0;
      margin-top: 1px;
    }

    .sq-continue {
      display: block;
      width: 100%;
      padding: 16px;
      background: #f43f5e;
      border: none;
      border-radius: 14px;
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      cursor: pointer;
      transition: all 0.2s ease;
      letter-spacing: 0.3px;
    }

    .sq-continue:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }

    .sq-continue:not(:disabled):hover {
      background: #e11d48;
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(244, 63, 94, 0.30);
    }

    body.sq-open {
      overflow: hidden;
    }

    @media (max-width: 640px) {
      .sq-modal {
        max-width: 96%;
        padding: 26px 14px 18px;
        border-radius: 22px;
      }

      .sq-title {
        font-size: 18px;
      }

      .sq-vehicle-img {
        width: 180px;
        height: 140px;
      }

      .sq-numbers {
        gap: 4px;
        flex-wrap: wrap;
      }

      .sq-num {
        width: 38px;
        height: 38px;
        font-size: 15px;
      }
    }
  </style>
</head>

<body>

  <header class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/75 backdrop-blur-xl"
    style="font-family: 'Plus Jakarta Sans', sans-serif;">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
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

      <nav class="hidden items-center gap-7 text-sm font-medium text-slate-200 lg:flex">
        <a href="index.php" class="hover:text-white" style="text-decoration: none;">Home</a>
        <a href="matches.php" class="hover:text-white" style="text-decoration: none;">Matches</a>
        <a href="feedback.php" class="hover:text-white" style="text-decoration: none;">Feedback</a>
      </nav>

      <div class="hidden items-center gap-3 lg:flex">
        <?php if (isset($_SESSION['user_id'])): ?>
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
            style="text-decoration: none;">Login</a>
          <a href="register.php"
            class="rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-5 py-2 text-sm font-bold text-slate-950"
            style="text-decoration: none;">Create Account</a>
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

  <div class="top-border">
    <div class="main-wrap">
      <div class="match-header">
        <div class="match-info">
          <div class="teams-wrap">
            <h1>
              <div class="flag-box"><?php if (!empty($match['team1_logo'])): ?><img
                    src="<?php echo htmlspecialchars($match['team1_logo']); ?>"
                    alt="<?php echo htmlspecialchars($match['team1_name']); ?>" class="flag-logo"><?php else:
                echo htmlspecialchars($match['team1_short'] ?: substr($match['team1_name'], 0, 3));
              endif; ?>
              </div>
              <?php echo strtoupper(htmlspecialchars($match['team1_short'] ?: $match['team1_name'])); ?>
              <span class="vs-text">vs</span>
              <?php echo strtoupper(htmlspecialchars($match['team2_short'] ?: $match['team2_name'])); ?>
              <div class="flag-box"><?php if (!empty($match['team2_logo'])): ?><img
                    src="<?php echo htmlspecialchars($match['team2_logo']); ?>"
                    alt="<?php echo htmlspecialchars($match['team2_name']); ?>" class="flag-logo"><?php else:
                echo htmlspecialchars($match['team2_short'] ?: substr($match['team2_name'], 0, 3));
              endif; ?>
              </div>
            </h1>
            <div class="match-subtext">
              <?php echo htmlspecialchars($match['match_type']); ?> •
              <?php echo date('d M Y', strtotime($match['match_date'])); ?> •
              <?php echo date('h:i A', strtotime($match['match_time'])); ?> •
              <?php echo htmlspecialchars($match['venue_name']); ?>,
              <?php echo htmlspecialchars($match['venue_city']); ?>
            </div>
          </div>
        </div>

        <div class="steps">
          <div class="step active">
            <div class="step-number">1</div>
            <span>Select Seats</span>
          </div>
          <div class="arrow">→</div>
          <div class="step active">
            <div class="step-number">2</div>
            <span>Payment</span>
          </div>
          <div class="arrow">→</div>
          <div class="step">
            <div class="step-number">3</div>
            <span>Confirmation</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="main-wrap">
    <?php if ($error): ?>
      <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($preselected_qty > 0): ?>
      <div id="seatQtyBanner"
        style="background: rgba(244,201,93,0.12); border: 1px solid rgba(244,201,93,0.30); color:#fde68a; padding:14px 18px; border-radius:14px; margin-bottom:20px; display:flex; align-items:center; gap:12px; font-size:15px; font-weight:600;">
        <span style="font-size:20px;">🎟️</span>
        You are selecting <strong style="color:#f4c95d;"><?php echo $preselected_qty; ?>
          seat<?php echo $preselected_qty > 1 ? 's' : ''; ?></strong>. Please click exactly
        <?php echo $preselected_qty; ?> seat<?php echo $preselected_qty > 1 ? 's' : ''; ?> on the map below.
      </div>
    <?php else: ?>
      <div id="seatQtyBanner"
        style="background: rgba(244,201,93,0.12); border: 1px solid rgba(244,201,93,0.30); color:#fde68a; padding:14px 18px; border-radius:14px; margin-bottom:20px; display:none; align-items:center; gap:12px; font-size:15px; font-weight:600;">
        🎟️
      </div>
    <?php endif; ?>

    <div class="content-grid">
      <div>
        <div class="category-list" id="categoryCards">
          <?php foreach ($categories as $index => $cat): ?>
            <button type="button" class="category-card <?php echo $index === 0 ? 'active-tab' : ''; ?>"
              data-category-id="<?php echo (int) $cat['category_id']; ?>"
              data-category="<?php echo htmlspecialchars($cat['category_name']); ?>"
              data-price="<?php echo (float) $cat['price']; ?>">
              <div class="cat-name"><?php echo htmlspecialchars($cat['category_name']); ?></div>
              <div class="cat-price">₹<?php echo number_format($cat['price']); ?></div>
              <div class="cat-seat"><?php echo (int) $cat['no_of_seats']; ?> seats available</div>
            </button>
          <?php endforeach; ?>
        </div>

        <div class="stadium-box glass">
          <div class="stadium-stage">
            <div class="stadium-image-wrap">
              <img src="<?php echo $stadium_bg; ?>" alt="Stadium" class="stadium-image">
            </div>

            <div class="overlay-label gate1">GATE 1</div>
            <div class="overlay-label gate2">GATE 2</div>
            <div class="overlay-label gate3">GATE 3</div>
            <div class="overlay-label gate4">GATE 4</div>

            <div class="premium-top" id="selectedCategoryLabel">
              <?php echo strtoupper(htmlspecialchars($defaultCategory['category_name'])); ?>
            </div>

            <div class="overlay-label left-side-label">GENERAL</div>
            <div class="overlay-label right-side-label">VIP | EAST</div>
            <div class="overlay-label bottom-side-label">CORPORATE BOX</div>
            <div class="field-text" id="fieldText">STAGE / PLAYING FIELD</div>

            <div class="seat-overlay" id="seatOverlay">
              <?php
              $rows = ['A', 'B', 'C', 'D', 'E', 'F'];
              $seats_per_row = 8;
              foreach ($rows as $row):
                ?>
                <div class="seat-row">
                  <div class="row-label"><?php echo $row; ?></div>
                  <?php for ($i = 1; $i <= $seats_per_row; $i++): ?>
                    <?php
                    $seat_id = $row . $i;
                    $isBooked = in_array($seat_id, $bookedSeats, true);
                    ?>
                    <div class="seat <?php echo $isBooked ? 'booked' : 'available'; ?>" data-seat="<?php echo $seat_id; ?>"
                      data-row="<?php echo $row; ?>" onclick="selectSeat(this, '<?php echo $seat_id; ?>')">
                      <?php echo $seat_id; ?>
                    </div>
                  <?php endfor; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="legend">
          <div class="legend-item">
            <span class="legend-box" style="background:#d7dce5;"></span>
            Available
          </div>
          <div class="legend-item">
            <span class="legend-box" style="background:#3fa34d;"></span>
            Selected
          </div>
          <div class="legend-item">
            <span class="legend-box" style="background:#475569;"></span>
            Booked
          </div>
        </div>
      </div>

      <div>
        <div class="summary-card glass">
          <h2>Booking Summary</h2>

          <div class="stadium-preview">
            <div class="preview-top"><span>Venue Preview</span></div>
            <div class="preview-bottom">
              <div class="category-title">
                <span id="summaryCategoryTitle" style="color:#fde68a;">
                  <?php echo htmlspecialchars($defaultCategory['category_name']); ?>
                </span> Category
              </div>
              <div class="category-price" id="summaryCategoryPrice">
                ₹<?php echo number_format($defaultCategory['price']); ?></div>
              <div class="small-text">per seat</div>
            </div>
          </div>

          <div class="selected-header">
            <h3>Selected Seats (<span id="selectedCount">0</span>)</h3>
            <button type="button" class="clear-btn" onclick="clearAllSeats()">Clear All</button>
          </div>

          <div id="selectedSeatsContainer">
            <div class="empty-seat-text">No seats selected</div>
          </div>

          <div class="price-area">
            <div class="price-line">
              <span class="label">Ticket Price</span>
              <span id="ticketPriceText">₹0</span>
            </div>
            <div class="price-line">
              <span class="label">Convenience Fee(2%)</span>
              <span id="convenienceFee">₹0</span>
            </div>
            <div class="price-line">
              <span class="label">GST(18%)</span>
              <span id="gstAmount">₹0</span>
            </div>
          </div>

          <div class="total-line">
            <div class="total-label">Total</div>
            <div class="total-value" id="grandTotal">₹0</div>
          </div>

          <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="login-box">
              Please <a href="login.php" class="underline">login</a> to continue booking.
            </div>
          <?php else: ?>
            <form method="POST" onsubmit="return handleBookingSubmit(event);">
              <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="[]">
              <input type="hidden" name="category_id" id="selectedCategoryInput"
                value="<?php echo $defaultCategoryId; ?>">

              <select name="payment_method" class="payment-select" required>
                <option value="">Select payment method</option>
                <option value="upi">UPI</option>
                <option value="card">Card</option>
                <option value="netbanking">Net Banking</option>
                <option value="wallet">Wallet</option>
              </select>

              <button type="submit" class="checkout-btn">Proceed</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    let selectedSeats = [];
    let selectedCategoryId = <?php echo (int) $defaultCategoryId; ?>;
    let bookedSeats = <?php echo json_encode($bookedSeats); ?>;
    let seatPrice = <?php echo (float) $defaultCategory['price']; ?>;
    const matchId = <?php echo (int) $match_id; ?>;
    const maxQty = <?php echo (int) $preselected_qty; ?>;

    let modalSelectedQty = 0;
    window.setMaxQty = function (qty) {
      modalSelectedQty = qty;
      const banner = document.getElementById('seatQtyBanner');
      if (banner) {
        banner.innerHTML = '🎟️ Please select exactly <strong style="color:#f4c95d;">' + qty + '</strong> seat' + (qty > 1 ? 's' : '') + ' on the map below.';
        banner.style.display = 'flex';
      }
    };

    const categoryCards = document.querySelectorAll(".category-card");
    const selectedCategoryLabel = document.getElementById("selectedCategoryLabel");
    const summaryCategoryTitle = document.getElementById("summaryCategoryTitle");
    const selectedSeatsInput = document.getElementById("selectedSeatsInput");

    categoryCards.forEach(card => {
      card.addEventListener("click", function () {
        categoryCards.forEach(c => c.classList.remove("active-tab"));
        this.classList.add("active-tab");

        selectedCategoryId = parseInt(this.dataset.categoryId, 10);
        seatPrice = parseFloat(this.dataset.price || 0);

        document.getElementById("selectedCategoryInput").value = selectedCategoryId;
        document.getElementById("summaryCategoryPrice").textContent = "₹" + seatPrice.toLocaleString("en-IN");
        summaryCategoryTitle.textContent = this.dataset.category;
        selectedCategoryLabel.textContent = this.dataset.category.toUpperCase();

        selectedSeats = [];
        bookedSeats = [];
        selectedSeatsInput.value = "[]";

        applyBookedSeats();
        updateSummary();
        fetchBookedSeats();
      });
    });

    function applyBookedSeats() {
      document.querySelectorAll(".seat").forEach(seatEl => {
        const seatId = seatEl.dataset.seat;

        seatEl.classList.remove("booked", "selected", "available");

        if (bookedSeats.includes(seatId)) {
          seatEl.classList.add("booked");
        } else if (selectedSeats.includes(seatId)) {
          seatEl.classList.add("selected");
        } else {
          seatEl.classList.add("available");
        }
      });
    }

    function fetchBookedSeats() {
      fetch(`get-booked-seats.php?match_id=${matchId}&category_id=${selectedCategoryId}&t=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            bookedSeats = data.seats || [];
            selectedSeats = selectedSeats.filter(seat => !bookedSeats.includes(seat));
            selectedSeatsInput.value = JSON.stringify(selectedSeats);
            applyBookedSeats();
            updateSummary();
          }
        })
        .catch(error => {
          console.error("Seat fetch error:", error);
        });
    }

    function selectSeat(element, seatId) {
      if (bookedSeats.includes(seatId)) {
        alert("This seat is already booked.");
        return;
      }

      const index = selectedSeats.indexOf(seatId);
      const effectiveMax = modalSelectedQty > 0 ? modalSelectedQty : (maxQty > 0 ? maxQty : 10);

      if (index === -1) {
        if (selectedSeats.length >= effectiveMax) {
          if (modalSelectedQty > 0 || maxQty > 0) {
            alert(`You selected ${effectiveMax} seat(s). Please deselect one seat first.`);
          } else {
            alert("You cannot book more than 10 seats.");
          }
          return;
        }
        selectedSeats.push(seatId);
      } else {
        selectedSeats.splice(index, 1);
      }

      selectedSeatsInput.value = JSON.stringify(selectedSeats);
      applyBookedSeats();
      updateSummary();
    }

    function clearAllSeats() {
      selectedSeats = [];
      selectedSeatsInput.value = JSON.stringify(selectedSeats);
      applyBookedSeats();
      updateSummary();
    }

    function updateSummary() {
      const selectedSeatsContainer = document.getElementById("selectedSeatsContainer");
      const selectedCount = document.getElementById("selectedCount");
      const ticketPriceText = document.getElementById("ticketPriceText");
      const convenienceFee = document.getElementById("convenienceFee");
      const gstAmount = document.getElementById("gstAmount");
      const grandTotal = document.getElementById("grandTotal");

      selectedCount.textContent = selectedSeats.length;

      if (selectedSeats.length === 0) {
        selectedSeatsContainer.innerHTML = `<div class="empty-seat-text">No seats selected</div>`;
        ticketPriceText.textContent = "₹0";
        convenienceFee.textContent = "₹0";
        gstAmount.textContent = "₹0";
        grandTotal.textContent = "₹0";
        selectedSeatsInput.value = "[]";
        return;
      }

      let html = "";
      let subtotal = 0;

      selectedSeats.forEach(seat => {
        subtotal += seatPrice;
        html += `
        <div class="summary-seat">
          <div class="summary-seat-name">${seat}</div>
          <div class="summary-seat-row">Seat</div>
        </div>
      `;
      });

      selectedSeatsContainer.innerHTML = `<div class="selected-seats-grid">${html}</div>`;

      const fee = Math.round(subtotal * 0.02);
      const gst = Math.round(subtotal * 0.18);
      const total = subtotal + fee + gst;

      ticketPriceText.textContent = `₹${subtotal.toLocaleString("en-IN")}`;
      convenienceFee.textContent = `₹${fee.toLocaleString("en-IN")}`;
      gstAmount.textContent = `₹${gst.toLocaleString("en-IN")}`;
      grandTotal.textContent = `₹${total.toLocaleString("en-IN")}`;

      selectedSeatsInput.value = JSON.stringify(selectedSeats);
    }

    async function validateBooking() {
      if (selectedSeats.length === 0) {
        alert("Please select at least one seat.");
        return false;
      }

      const effectiveMax = modalSelectedQty > 0 ? modalSelectedQty : (maxQty > 0 ? maxQty : 10);
      if (selectedSeats.length !== effectiveMax) {
        alert(`Please select exactly ${effectiveMax} seat(s) before proceeding.`);
        return false;
      }

      const paymentMethod = document.querySelector('select[name="payment_method"]');
      if (!paymentMethod.value) {
        alert("Please select a payment method.");
        return false;
      }

      try {
        const response = await fetch(`get-booked-seats.php?match_id=${matchId}&category_id=${selectedCategoryId}&t=${Date.now()}`);
        const data = await response.json();

        if (data.success) {
          const latestBookedSeats = data.seats || [];
          const conflictSeats = selectedSeats.filter(seat => latestBookedSeats.includes(seat));

          if (conflictSeats.length > 0) {
            bookedSeats = latestBookedSeats;
            selectedSeats = selectedSeats.filter(seat => !latestBookedSeats.includes(seat));
            selectedSeatsInput.value = JSON.stringify(selectedSeats);
            applyBookedSeats();
            updateSummary();
            alert("Some selected seats were just booked: " + conflictSeats.join(", "));
            return false;
          }
        }
      } catch (error) {
        console.error(error);
        alert("Unable to verify latest seat status. Please try again.");
        return false;
      }

      return true;
    }

    async function handleBookingSubmit(event) {
      event.preventDefault();
      const isValid = await validateBooking();
      if (isValid) {
        event.target.submit();
      }
      return false;
    }

    applyBookedSeats();
    updateSummary();
    fetchBookedSeats();
    setInterval(fetchBookedSeats, 5000);

    document.addEventListener('DOMContentLoaded', function () {
      const menuToggle = document.getElementById('menuToggle');
      const mobileMenu = document.getElementById('mobileMenu');
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

  <!-- ===== WHITE CENTER MODAL ===== -->
  <div class="sq-overlay" id="sqOverlay">
    <div class="sq-modal" id="sqModal">
      <h3 class="sq-title">How many seats?</h3>

      <div class="sq-vehicle-wrap">
        <img class="sq-vehicle-img sq-active" id="sqImg1" src="image/vehicle_scooter.png" alt="1-2 seats">
        <img class="sq-vehicle-img" id="sqImg2" src="image/vehicle_car.png" alt="3-5 seats">
        <img class="sq-vehicle-img" id="sqImg3" src="image/vehicle_van.png" alt="6-8 seats">
        <img class="sq-vehicle-img" id="sqImg4" src="image/vehicle_bus.png" alt="9-10 seats">
      </div>

      <div class="sq-numbers">
        <?php for ($i = 1; $i <= 10; $i++): ?>
          <button type="button" class="sq-num" data-val="<?php echo $i; ?>"><?php echo $i; ?></button>
        <?php endfor; ?>
      </div>

      <div class="sq-hint">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="16" x2="12" y2="12" />
          <line x1="12" y1="8" x2="12.01" y2="8" />
        </svg>
        <span>1 is the minimum and 10 is the maximum quantity of tickets you can purchase for this event.</span>
      </div>

      <button type="button" class="sq-continue" id="sqContinue" disabled>Continue</button>
    </div>
  </div>

  <script>
    (function () {
      const overlay = document.getElementById('sqOverlay');
      const continueBtn = document.getElementById('sqContinue');
      const numBtns = document.querySelectorAll('.sq-num');
      const imgs = [
        document.getElementById('sqImg1'),
        document.getElementById('sqImg2'),
        document.getElementById('sqImg3'),
        document.getElementById('sqImg4')
      ];

      let chosenQty = <?php echo $preselected_qty > 0 ? (int) $preselected_qty : 0; ?>;
      document.body.classList.add('sq-open');

      function getImgIndex(qty) {
        if (qty <= 2) return 0;
        if (qty <= 5) return 1;
        if (qty <= 8) return 2;
        return 3;
      }

      function switchImage(qty) {
        const idx = getImgIndex(qty);
        imgs.forEach(function (img, i) {
          img.classList.toggle('sq-active', i === idx);
        });
      }

      function closeModal() {
        overlay.classList.add('sq-hidden');
        document.body.classList.remove('sq-open');
        if (typeof window.setMaxQty === 'function') {
          window.setMaxQty(chosenQty);
        }
      }

      numBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          numBtns.forEach(b => b.classList.remove('sq-picked'));
          btn.classList.add('sq-picked');
          chosenQty = parseInt(btn.getAttribute('data-val'), 10);
          continueBtn.disabled = false;
          switchImage(chosenQty);
        });
      });

      continueBtn.addEventListener('click', function () {
        if (chosenQty > 0) closeModal();
      });

      if (chosenQty > 0) {
        numBtns.forEach(b => {
          if (parseInt(b.getAttribute('data-val'), 10) === chosenQty) {
            b.classList.add('sq-picked');
          }
        });
        continueBtn.disabled = false;
        switchImage(chosenQty);
        closeModal();
      }
    })();
  </script>

</body>

</html>