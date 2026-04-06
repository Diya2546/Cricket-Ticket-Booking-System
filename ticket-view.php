<?php
require_once 'connection.php';

if (!isset($_GET['booking_id'])) {
    die("Invalid Ticket ID");
}

$booking_id = trim($_GET['booking_id']);
$booking_id_safe = mysqli_real_escape_string($link, $booking_id);

$query = "
    SELECT 
        b.*,
        m.match_date,
        m.match_time,
        m.match_type,
        t1.name as team1_name,
        t2.name as team2_name,
        v.name as venue_name,
        v.city as venue_city,
        u.name as user_name
    FROM bookings b
    JOIN matches m ON b.match_id = m.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    JOIN venues v ON m.venue_id = v.id
    JOIN users u ON b.user_id = u.id
    WHERE b.booking_id = '$booking_id_safe'
";
$result = mysqli_query($link, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    die("Ticket not found.");
}

$booking = mysqli_fetch_assoc($result);
$bk_internal_id = (int)$booking['id'];

// Get seat items
$items_query = "
    SELECT c.name as category_name, bi.seats_no
    FROM booking_items bi
    JOIN seat_categories c ON bi.category_id = c.id
    WHERE bi.booking_id = $bk_internal_id
";
$items_result = mysqli_query($link, $items_query);

$cat_names = [];
$all_seats = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $cat_names[] = $item['category_name'];
    if (!empty($item['seats_no'])) {
        $all_seats[] = $item['seats_no'];
    }
}
$cat_str = !empty($cat_names) ? implode(', ', array_unique($cat_names)) : 'General';
$seats_str = !empty($all_seats) ? implode(', ', $all_seats) : 'All General';

$ticket_subtotal = (float) $booking['total_amount'];
$convenience_fee = round($ticket_subtotal * 0.02);
$gst_amount = round($ticket_subtotal * 0.18);
$grand_total = $ticket_subtotal + $convenience_fee + $gst_amount;
$status = strtolower($booking['booking_status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Digital Ticket - <?php echo htmlspecialchars($booking['booking_id']); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #040814 0%, #091120 50%, #050b18 100%);
      color: #fff;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .ticket-container {
      width: 100%;
      max-width: 420px;
      padding: 20px;
    }
    .ticket-card {
      background: #ffffff;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 24px 48px rgba(0,0,0,0.5);
      color: #1a1a1a;
      position: relative;
    }
    .ticket-header {
      background: #16a34a; /* success green */
      color: #fff;
      padding: 24px 20px;
      text-align: center;
      border-bottom: 3px dashed rgba(255,255,255,0.4);
      position: relative;
    }
    .ticket-header.status-cancelled {
      background: #dc2626; /* red */
    }
    .ticket-header.status-pending {
      background: #d97706; /* amber */
    }
    .ticket-header::before, .ticket-header::after {
      content: '';
      position: absolute;
      bottom: -14px;
      width: 28px;
      height: 28px;
      background: #091120;
      border-radius: 50%;
    }
    .ticket-header::before { left: -14px; }
    .ticket-header::after { right: -14px; }
    
    .ticket-header h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 34px;
      letter-spacing: 1.5px;
      margin: 0;
    }
    .ticket-header p {
      font-size: 14px;
      font-weight: 500;
      opacity: 0.9;
      margin-top: 6px;
      letter-spacing: 1px;
    }
    .ticket-body {
      padding: 30px 24px;
    }
    .teams {
      font-size: 26px;
      font-weight: 900;
      color: #0f172a;
      text-align: center;
      margin-bottom: 8px;
      line-height: 1.2;
    }
    .datetime {
      font-size: 15px;
      font-weight: 600;
      color: #64748b;
      text-align: center;
      margin-bottom: 24px;
    }
    .venue {
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      gap: 8px;
      font-size: 15px;
      color: #334155;
      font-weight: 700;
      margin-bottom: 30px;
    }
    .venue svg {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
    }
    .details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 26px;
      background: #f8fafc;
      padding: 16px;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
    }
    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .detail-item span:first-child {
      font-size: 11px;
      font-weight: 800;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .detail-item span:last-child {
      font-size: 16px;
      font-weight: 800;
      color: #0f172a;
    }
    .status-badge {
      text-align: center;
      padding: 12px 0;
      font-weight: 900;
      border-radius: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-size: 15px;
    }
    .status-confirmed { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .status-cancelled { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
    .status-pending { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
    .footer {
      text-align: center;
      padding: 16px 20px;
      font-size: 12px;
      color: #94a3b8;
      font-weight: 600;
      background: #f1f5f9;
      border-top: 1px solid #e2e8f0;
    }
  </style>
</head>
<body>
  <div class="ticket-container">
    <div class="ticket-card">
      <div class="ticket-header status-<?php echo $status; ?>">
        <h1>CRICKET TICKET</h1>
        <p>ID: <?php echo htmlspecialchars($booking['booking_id']); ?></p>
      </div>
      <div class="ticket-body">
        <div class="teams"><?php echo htmlspecialchars($booking['team1_name'] . ' vs ' . $booking['team2_name']); ?></div>
        <div class="datetime"><?php echo date('d M Y', strtotime($booking['match_date'])); ?> &nbsp;|&nbsp; <?php echo date('h:i A', strtotime($booking['match_time'])); ?></div>
        <div class="venue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          <?php echo htmlspecialchars($booking['venue_name'] . ', ' . $booking['venue_city']); ?>
        </div>
        
        <div class="details-grid">
          <div class="detail-item">
            <span>Category</span>
            <span><?php echo htmlspecialchars($cat_str); ?></span>
          </div>
          <div class="detail-item">
            <span>Seats</span>
            <span><?php echo htmlspecialchars($seats_str); ?></span>
          </div>
          <div class="detail-item">
            <span>Booked By</span>
            <span><?php echo htmlspecialchars($booking['user_name']); ?></span>
          </div>
          <div class="detail-item">
            <span>Total Paid</span>
            <span style="color: #16a34a;">₹<?php echo number_format($grand_total); ?></span>
          </div>
        </div>
        
        <div class="status-badge status-<?php echo $status; ?>">
          <?php 
          if ($status === 'confirmed') echo '✓ VALID TICKET'; 
          else if ($status === 'cancelled') echo '✗ CANCELLED';
          else echo strtoupper($status);
          ?>
        </div>
      </div>
      <div class="footer">
        Powered by CTB Check-in System
      </div>
    </div>
  </div>
</body>
</html>
