<?php
include '../connection.php';
header('Content-Type: application/json');

$range = $_GET['range'] ?? '6months';

function getDateCondition($range) {
    switch ($range) {
        case '7days':
            return "DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        case '1month':
            return "DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        case '3months':
            return "DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        case '6months':
            return "DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        case '1year':
            return "DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        default:
            return "DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    }
}

$fromDate = getDateCondition($range);

// Revenue trend from bookings table for consistency
$sqlRevenueTrend = "
    SELECT 
        DATE_FORMAT(booking_time, '%b') AS label,
        SUM(total_amount * 1.20) AS total
    FROM bookings
    WHERE booking_status != 'cancelled'
      AND DATE(booking_time) >= $fromDate
    GROUP BY YEAR(booking_time), MONTH(booking_time)
    ORDER BY YEAR(booking_time), MONTH(booking_time)
";
$resRevenueTrend = mysqli_query($link, $sqlRevenueTrend);
$revenueLabels = [];
$revenueValues = [];
while ($row = mysqli_fetch_assoc($resRevenueTrend)) {
    $revenueLabels[] = $row['label'];
    $revenueValues[] = (float) $row['total'];
}

// Booking trend = count of bookings, not tickets
$sqlBookingTrend = "
    SELECT 
        DATE_FORMAT(booking_time, '%b') AS label,
        COUNT(*) AS total
    FROM bookings
    WHERE booking_status != 'cancelled'
      AND DATE(booking_time) >= $fromDate
    GROUP BY YEAR(booking_time), MONTH(booking_time)
    ORDER BY YEAR(booking_time), MONTH(booking_time)
";
$resBookingTrend = mysqli_query($link, $sqlBookingTrend);
$bookingLabels = [];
$bookingValues = [];
while ($row = mysqli_fetch_assoc($resBookingTrend)) {
    $bookingLabels[] = $row['label'];
    $bookingValues[] = (int) $row['total'];
}

// Booking status
$sqlStatus = "
    SELECT booking_status, COUNT(*) AS total
    FROM bookings
    WHERE DATE(booking_time) >= $fromDate
    GROUP BY booking_status
";
$resStatus = mysqli_query($link, $sqlStatus);
$statusLabels = [];
$statusValues = [];
while ($row = mysqli_fetch_assoc($resStatus)) {
    $statusLabels[] = ucfirst($row['booking_status']);
    $statusValues[] = (int) $row['total'];
}

// Unique user trend
$sqlUniqueUsersTrend = "
    SELECT 
        DATE_FORMAT(booking_time, '%b') AS label,
        COUNT(DISTINCT user_id) AS total
    FROM bookings
    WHERE booking_status != 'cancelled'
      AND DATE(booking_time) >= $fromDate
    GROUP BY YEAR(booking_time), MONTH(booking_time)
    ORDER BY YEAR(booking_time), MONTH(booking_time)
";
$resUniqueUsersTrend = mysqli_query($link, $sqlUniqueUsersTrend);
$userLabels = [];
$userValues = [];
while ($row = mysqli_fetch_assoc($resUniqueUsersTrend)) {
    $userLabels[] = $row['label'];
    $userValues[] = (int) $row['total'];
}

echo json_encode([
    'revenue' => [
        'labels' => $revenueLabels,
        'values' => $revenueValues
    ],
    'bookings' => [
        'labels' => $bookingLabels,
        'values' => $bookingValues
    ],
    'booking_status' => [
        'labels' => $statusLabels,
        'values' => $statusValues
    ],
    'unique_users' => [
        'labels' => $userLabels,
        'values' => $userValues
    ]
]);