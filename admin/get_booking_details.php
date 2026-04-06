<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

include '../connection.php';

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    
    // Query to get booking details
    $bookingQuery = "
        SELECT 
            b.*,
            u.name as user_name,
            u.email as user_email,
            u.phone as user_phone,
            m.match_date,
            m.match_time,
            t1.name as team1_name,
            t2.name as team2_name,
            s.name as stadium_name,
            s.location as stadium_location,
            m.tournament
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN matches m ON b.match_id = m.id
        JOIN teams t1 ON m.team1_id = t1.id
        JOIN teams t2 ON m.team2_id = t2.id
        JOIN stadiums s ON m.stadium_id = s.id
        WHERE b.id = $bookingId
    ";
    
    $bookingResult = mysqli_query($link, $bookingQuery);
    $booking = mysqli_fetch_assoc($bookingResult);
    
    if ($booking) {
        // Query to get ticket details
        $ticketsQuery = "
            SELECT 
                bd.*,
                st.type as seat_type,
                st.price as seat_price,
                z.name as zone_name
            FROM booking_details bd
            JOIN seat_types st ON bd.seat_type_id = st.id
            LEFT JOIN zones z ON bd.zone_id = z.id
            WHERE bd.booking_id = $bookingId
        ";
        
        $ticketsResult = mysqli_query($link, $ticketsQuery);
        $tickets = [];
        
        while ($ticket = mysqli_fetch_assoc($ticketsResult)) {
            $tickets[] = $ticket;
        }
        
        // Format dates
        $bookingDate = date('F j, Y', strtotime($booking['created_at']));
        $matchDate = date('F j, Y', strtotime($booking['match_date']));
        $matchTime = date('g:i A', strtotime($booking['match_time']));
        
        // Determine status badge
        if ($booking['booking_status'] == 'cancelled') {
            $statusBadge = '<span class="badge bg-danger">Cancelled</span>';
        } elseif ($booking['payment_status'] == 'pending') {
            $statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
        } else {
            $statusBadge = '<span class="badge bg-success">Confirmed</span>';
        }
        
        echo '
        <div class="booking-details">
            <div class="booking-detail-row">
                <div class="booking-detail-label">Booking Reference:</div>
                <div class="booking-detail-value">' . $booking['booking_reference'] . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Booking Date:</div>
                <div class="booking-detail-value">' . $bookingDate . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Status:</div>
                <div class="booking-detail-value">' . $statusBadge . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Customer Name:</div>
                <div class="booking-detail-value">' . $booking['user_name'] . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Customer Email:</div>
                <div class="booking-detail-value">' . $booking['user_email'] . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Customer Phone:</div>
                <div class="booking-detail-value">' . $booking['user_phone'] . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Match:</div>
                <div class="booking-detail-value">' . $booking['team1_name'] . ' vs ' . $booking['team2_name'] . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Tournament:</div>
                <div class="booking-detail-value">' . $booking['tournament'] . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Match Date & Time:</div>
                <div class="booking-detail-value">' . $matchDate . ' at ' . $matchTime . '</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Stadium:</div>
                <div class="booking-detail-value">' . $booking['stadium_name'] . ' (' . $booking['stadium_location'] . ')</div>
            </div>
            
            <div class="booking-detail-row">
                <div class="booking-detail-label">Total Amount:</div>
                <div class="booking-detail-value">₹' . number_format($booking['final_amount'], 2) . '</div>
            </div>
            
            <h6 class="mt-4 mb-3">Tickets:</h6>
            <table class="table table-sm ticket-table">
                <thead>
                    <tr>
                        <th>Seat Type</th>
                        <th>Zone</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($tickets as $ticket) {
            $subtotal = $ticket['quantity'] * $ticket['seat_price'];
            echo '
                    <tr>
                        <td>' . $ticket['seat_type'] . '</td>
                        <td>' . ($ticket['zone_name'] ? $ticket['zone_name'] : 'N/A') . '</td>
                        <td>' . $ticket['quantity'] . '</td>
                        <td>₹' . number_format($ticket['seat_price'], 2) . '</td>
                        <td>₹' . number_format($subtotal, 2) . '</td>
                    </tr>';
        }
        
        echo '
                </tbody>
            </table>
        </div>';
    } else {
        echo '<div class="alert alert-danger">Booking not found.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid request.</div>';
}
?>