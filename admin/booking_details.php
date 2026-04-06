<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

include '../connection.php';

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    
    // Query to get booking details with correct table structure
    $query = "
        SELECT 
            b.*,
            u.name as user_name,
            u.email as user_email,
            u.phone as user_phone,
            m.match_date,
            m.match_time,
            m.match_type,
            t1.name as team1_name,
            t2.name as team2_name,
            v.name as venue_name,
            v.city as venue_city,
            v.address as venue_address
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN matches m ON b.match_id = m.id
        JOIN teams t1 ON m.team1_id = t1.id
        JOIN teams t2 ON m.team2_id = t2.id
        JOIN venues v ON m.venue_id = v.id
        WHERE b.id = $bookingId
    ";
    
    $result = mysqli_query($link, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $booking = mysqli_fetch_assoc($result);
        
        // Get all ticket items for this booking
        $itemsQuery = "
            SELECT bi.*, sc.name as category_name, sc.color_code
            FROM booking_items bi
            JOIN seat_categories sc ON bi.category_id = sc.id
            WHERE bi.booking_id = $bookingId
        ";
        $itemsResult = mysqli_query($link, $itemsQuery);
        $items = [];
        $totalTickets = 0;
        
        while ($item = mysqli_fetch_assoc($itemsResult)) {
            $items[] = $item;
            $totalTickets += $item['quantity'];
        }
        
        // Format dates
        $bookingDate = date('F j, Y, g:i A', strtotime($booking['booking_time']));
        $matchDate = date('F j, Y', strtotime($booking['match_date']));
        $matchTime = date('g:i A', strtotime($booking['match_time']));
        
        // Determine status
        $statusClass = '';
        $statusText = '';
        
        if ($booking['booking_status'] == 'cancelled') {
            $statusClass = 'status-cancelled-large';
            $statusText = 'Cancelled';
        } elseif ($booking['payment_status'] == 'success' && $booking['booking_status'] == 'confirmed') {
            $statusClass = 'status-confirmed-large';
            $statusText = 'Confirmed';
        } elseif ($booking['payment_status'] == 'pending') {
            $statusClass = 'status-pending-large';
            $statusText = 'Pending';
        } else {
            $statusClass = 'status-failed-large';
            $statusText = 'Failed';
        }
        
        echo '<div class="booking-details-modal">';
        echo '<div class="modal-header">';
        echo '<h2><i class="fas fa-ticket-alt"></i> Booking Details</h2>';
        echo '<button type="button" class="close-btn" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>';
        echo '</div>';
        
        echo '<div class="modal-body">';
        
        // Status Banner
        echo '<div style="text-align: right; margin-bottom: 1.5rem;">';
        echo '<span class="booking-status-large ' . $statusClass . '">' . $statusText . '</span>';
        echo '</div>';
        
        // Booking and Customer Info in two columns
        echo '<div class="row">';
        
        // Booking Info
        echo '<div class="col-md-6">';
        echo '<div class="info-card">';
        echo '<h3><i class="fas fa-receipt"></i> Booking Information</h3>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Reference:</span>';
        echo '<span class="info-value"><strong>' . htmlspecialchars($booking['booking_id']) . '</strong></span>';
        echo '</div>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Booking Date:</span>';
        echo '<span class="info-value">' . $bookingDate . '</span>';
        echo '</div>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Total Tickets:</span>';
        echo '<span class="info-value"><strong>' . $totalTickets . '</strong></span>';
        echo '</div>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Payment Status:</span>';
        echo '<span class="info-value"><span class="status-badge ' . ($booking['payment_status'] == 'success' ? 'status-confirmed' : 'status-pending') . '">' . ucfirst($booking['payment_status']) . '</span></span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Customer Info
        echo '<div class="col-md-6">';
        echo '<div class="info-card">';
        echo '<h3><i class="fas fa-user"></i> Customer Information</h3>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Name:</span>';
        echo '<span class="info-value"><strong>' . htmlspecialchars($booking['user_name']) . '</strong></span>';
        echo '</div>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Email:</span>';
        echo '<span class="info-value">' . htmlspecialchars($booking['user_email']) . '</span>';
        echo '</div>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Phone:</span>';
        echo '<span class="info-value">' . htmlspecialchars($booking['user_phone']) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Match Info
        echo '<div class="info-card">';
        echo '<h3><i class="fas fa-baseball-ball"></i> Match Information</h3>';
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<div class="info-row">';
        echo '<span class="info-label">Match:</span>';
        echo '<span class="info-value"><strong>' . htmlspecialchars($booking['team1_name'] . ' vs ' . $booking['team2_name']) . '</strong></span>';
        echo '</div>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Type:</span>';
        echo '<span class="info-value">' . htmlspecialchars($booking['match_type']) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<div class="info-row">';
        echo '<span class="info-label">Date:</span>';
        echo '<span class="info-value">' . $matchDate . '</span>';
        echo '</div>';
        echo '<div class="info-row">';
        echo '<span class="info-label">Time:</span>';
        echo '<span class="info-value">' . $matchTime . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-12">';
        echo '<div class="info-row">';
        echo '<span class="info-label">Venue:</span>';
        echo '<span class="info-value">' . htmlspecialchars($booking['venue_name']) . ', ' . htmlspecialchars($booking['venue_city']) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Tickets Table
        if (!empty($items)) {
            echo '<div class="info-card">';
            echo '<h3><i class="fas fa-chair"></i> Ticket Details</h3>';
            echo '<table class="tickets-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Category</th>';
            echo '<th>Quantity</th>';
            echo '<th>Unit Price</th>';
            echo '<th>Total</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            $subtotal = 0;
            foreach ($items as $item) {
                $categoryClass = strtolower($item['category_name']);
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemTotal;
                
                echo '<tr>';
                echo '<td><span class="ticket-type ' . $categoryClass . '">' . htmlspecialchars($item['category_name']) . '</span></td>';
                echo '<td>' . $item['quantity'] . '</td>';
                echo '<td>₹' . number_format($item['unit_price'], 2) . '</td>';
                echo '<td><strong>₹' . number_format($itemTotal, 2) . '</strong></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            // Price Summary
            echo '<div class="price-summary">';
            echo '<div class="summary-row">';
            echo '<span>Subtotal:</span>';
            echo '<span>₹' . number_format($subtotal, 2) . '</span>';
            echo '</div>';
            
            // Calculate tax (assuming 18% GST)
            $taxRate = 18;
            $taxAmount = $subtotal * $taxRate / 100;
            echo '<div class="summary-row">';
            echo '<span>GST (' . $taxRate . '%):</span>';
            echo '<span>₹' . number_format($taxAmount, 2) . '</span>';
            echo '</div>';
            
            echo '<div class="summary-row total">';
            echo '<span>Total Amount:</span>';
            echo '<span class="amount">₹' . number_format($booking['total_amount'], 2) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        // Action Buttons
        echo '<div class="action-buttons">';
        if ($booking['payment_status'] == 'pending' && $booking['booking_status'] != 'cancelled') {
            echo '<a href="bookings.php?action=confirm&id=' . $booking['id'] . '" class="btn btn-primary" onclick="return confirm(\'Confirm this booking?\')">';
            echo '<i class="fas fa-check"></i> Confirm Booking';
            echo '</a>';
        }
        if ($booking['booking_status'] != 'cancelled') {
            echo '<a href="bookings.php?action=cancel&id=' . $booking['id'] . '" class="btn btn-danger" onclick="return confirm(\'Cancel this booking?\')">';
            echo '<i class="fas fa-ban"></i> Cancel Booking';
            echo '</a>';
        }
        echo '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">';
        echo '<i class="fas fa-times"></i> Close';
        echo '</button>';
        echo '</div>';
        
        echo '</div>'; // modal-body
        echo '</div>'; // booking-details-modal
    } else {
        echo '<div class="alert alert-danger">Booking not found.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid request.</div>';
}
?>