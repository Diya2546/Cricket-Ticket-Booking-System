<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

function sendLoginMail($email, $name) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'yourgmail@gmail.com';
        $mail->Password = 'your_16_digit_app_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('yourgmail@gmail.com', 'Cricket Ticket Booking');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Login Alert - Cricket Ticket Booking';

        $mail->Body = "
            <h3>Hello {$name},</h3>
            <p>Your account logged in successfully.</p>
            <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p>If this was not you, please change your password immediately.</p>
            <br>
            <p>Thanks,<br>Cricket Ticket Booking</p>
        ";

        $mail->AltBody = "Hello {$name}, your account logged in successfully at " . date('Y-m-d H:i:s') . ". If this was not you, please change your password.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>