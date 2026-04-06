<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require('../connection.php');

if(isset($_GET['id']) && isset($_GET['type']) && $_GET['type'] == 'user') {
    $id = intval($_GET['id']);
    
    // First check if user has any bookings
    $bookingCheck = "SELECT COUNT(*) as booking_count FROM bookings WHERE user_id = $id";
    $result = mysqli_query($link, $bookingCheck);
    
    if ($result) {
        $data = mysqli_fetch_assoc($result);
        $booking_count = $data['booking_count'] ?? 0;
        
        if ($booking_count > 0) {
            $_SESSION['delete_message'] = 'has_bookings';
            header('Location: users.php');
            exit();
        } else {
            // Delete user
            $deleteQuery = "DELETE FROM users WHERE id = $id";
            if(mysqli_query($link, $deleteQuery)) {
                $_SESSION['delete_message'] = 'success';
                header('Location: users.php');
                exit();
            } else {
                $_SESSION['delete_message'] = 'error';
                header('Location: users.php');
                exit();
            }
        }
    } else {
        $_SESSION['delete_message'] = 'error';
        header('Location: users.php');
        exit();
    }
} else {
    header('Location: users.php');
    exit();
}
?>