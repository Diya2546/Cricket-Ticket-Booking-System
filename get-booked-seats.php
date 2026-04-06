<?php
require_once 'connection.php';

header('Content-Type: application/json');

if (!isset($_GET['match_id']) || !is_numeric($_GET['match_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid match id',
        'seats' => []
    ]);
    exit();
}

if (!isset($_GET['category_id']) || !is_numeric($_GET['category_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid category id',
        'seats' => []
    ]);
    exit();
}

$match_id = (int)$_GET['match_id'];
$category_id = (int)$_GET['category_id'];

$sql = "
    SELECT bi.seats_no
    FROM booking_items bi
    INNER JOIN bookings b ON b.id = bi.booking_id
    WHERE b.match_id = $match_id
      AND bi.category_id = $category_id
      AND b.booking_status = 'confirmed'
      AND b.payment_status = 'success'
";

$result = mysqli_query($link, $sql);

$bookedSeats = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
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

echo json_encode([
    'success' => true,
    'seats' => $bookedSeats
]);
exit();
?>