<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files directly from the PHPMailer/src folder you downloaded
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

class EmailService
{
    private $config;

    public function __construct()
    {
        // Load SMTP settings from config/smtp_config.php
        $this->config = require __DIR__ . '/../config/smtp_config.php';
    }

    // -------------------------------------------------------
    // Private helper: sets up the mailer with SMTP settings
    // -------------------------------------------------------
    private function getMailer()
    {
        $mail = new PHPMailer(true); // true = exceptions enabled

        $mail->isSMTP();                                         // Use SMTP protocol
        $mail->Host       = $this->config["Host"];              // smtp.gmail.com
        $mail->SMTPAuth   = true;                               // Enable authentication
        $mail->Username   = $this->config["User"];             // Your Gmail
        $mail->Password   = $this->config["Password"];         // 16-digit App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;    // TLS encryption
        $mail->Port       = $this->config["Port"];             // Port 587

        $mail->setFrom(
            $this->config["FromEmail"],
            $this->config["FromName"]
        );

        $mail->isHTML(true); // Send HTML emails

        return $mail;
    }

    // -------------------------------------------------------
    // 1. Send Email Verification Link (on Registration)
    // -------------------------------------------------------
    public function sendVerificationEmail($toEmail, $userName, $verifyToken)
    {
        $mail = $this->getMailer();

        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $verifyLink = "http://" . $domain . "/cricket-ticket-booking/verify_email.php?token=" . urlencode($verifyToken);

        $mail->addAddress($toEmail, $userName);
        $mail->Subject = "Verify Your Email - Cricket Ticket Booking";

        $mail->Body = "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:auto;
                     background:#0d1b2e;padding:36px 32px;border-radius:16px;
                     border:1px solid rgba(77,163,255,0.25);color:#fff;'>

            <div style='text-align:center;margin-bottom:24px;'>
                <span style='font-size:2.4rem;'>🏏</span>
                <h2 style='color:#4da3ff;margin:10px 0 4px;'>Cricket Ticket Booking</h2>
                <p style='color:rgba(255,255,255,0.65);font-size:0.9rem;margin:0;'>Email Verification</p>
            </div>

            <p style='color:#fff;'>Hello <strong>$userName</strong>,</p>
            <p style='color:rgba(255,255,255,0.8);line-height:1.7;'>
                Thank you for registering! Please click the button below to verify your 
                email address. This link will expire in <strong style='color:#ffd36b;'>24 hours</strong>.
            </p>

            <div style='text-align:center;margin:30px 0;'>
                <a href='$verifyLink'
                   style='display:inline-block;padding:14px 36px;
                          background:linear-gradient(135deg,#1f7ae0,#46b2ff);
                          color:#fff;font-weight:700;text-decoration:none;
                          border-radius:12px;font-size:1rem;
                          box-shadow:0 8px 20px rgba(31,122,224,0.4);'>
                    ✅ Verify My Email
                </a>
            </div>

            <p style='color:rgba(255,255,255,0.6);font-size:0.85rem;'>
                If the button doesn't work, copy and paste this link in your browser:<br>
                <a href='$verifyLink' style='color:#4da3ff;word-break:break-all;'>$verifyLink</a>
            </p>

            <hr style='border:none;border-top:1px solid rgba(255,255,255,0.12);margin:24px 0;'>
            <p style='color:rgba(255,255,255,0.5);font-size:0.82rem;text-align:center;margin:0;'>
                If you did not create this account, please ignore this email.<br>
                &copy; Cricket Ticket Booking
            </p>
        </div>";

        $mail->AltBody = "Hello $userName, please verify your email by opening this link: $verifyLink";

        return $mail->send();
    }

    // -------------------------------------------------------
    // 2. Send Login Alert Email
    // -------------------------------------------------------
    public function sendLoginEmail($toEmail, $userName)
    {
        $mail = $this->getMailer();

        $loginTime = date('d M Y, h:i A');

        $mail->addAddress($toEmail, $userName);
        $mail->Subject = "Login Alert - Cricket Ticket Booking";

        $mail->Body = "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:auto;
                     background:#0d1b2e;padding:36px 32px;border-radius:16px;
                     border:1px solid rgba(77,163,255,0.25);color:#fff;'>

            <div style='text-align:center;margin-bottom:24px;'>
                <span style='font-size:2.4rem;'>🏏</span>
                <h2 style='color:#4da3ff;margin:10px 0 4px;'>Cricket Ticket Booking</h2>
                <p style='color:rgba(255,255,255,0.65);font-size:0.9rem;margin:0;'>Login Notification</p>
            </div>

            <p>Hello <strong>$userName</strong>,</p>
            <p style='color:rgba(255,255,255,0.8);line-height:1.7;'>
                Your account was just logged in successfully.
            </p>

            <div style='background:rgba(77,163,255,0.12);border:1px solid rgba(77,163,255,0.3);
                        border-radius:10px;padding:16px 20px;margin:20px 0;'>
                <p style='margin:0;color:rgba(255,255,255,0.7);font-size:0.9rem;'>
                    🕐 <strong style='color:#fff;'>Login Time:</strong> $loginTime
                </p>
            </div>

            <p style='color:rgba(255,255,255,0.8);'>
                If this was <strong style='color:#ff8a8a;'>not you</strong>, please 
                change your password immediately.
            </p>

            <hr style='border:none;border-top:1px solid rgba(255,255,255,0.12);margin:24px 0;'>
            <p style='color:rgba(255,255,255,0.5);font-size:0.82rem;text-align:center;margin:0;'>
                &copy; Cricket Ticket Booking
            </p>
        </div>";

        $mail->AltBody = "Hello $userName, your account was logged in at $loginTime. If this was not you, please change your password.";

        return $mail->send();
    }

    // -------------------------------------------------------
    // 3. Send OTP Email (Forgot Password)
    // -------------------------------------------------------
    public function sendOtpEmail($toEmail, $userName, $otp)
    {
        $mail = $this->getMailer();

        $expiry = '10 minutes';

        $mail->addAddress($toEmail, $userName);
        $mail->Subject = "Password Reset OTP - Cricket Ticket Booking";

        // Split OTP digits for styled display
        $otpDigits = '';
        foreach (str_split($otp) as $digit) {
            $otpDigits .= "
                <span style='display:inline-block;width:42px;height:50px;line-height:50px;
                             text-align:center;font-size:1.6rem;font-weight:800;color:#fff;
                             background:rgba(77,163,255,0.18);border:2px solid rgba(77,163,255,0.45);
                             border-radius:12px;margin:0 4px;letter-spacing:0;'>
                    $digit
                </span>";
        }

        $mail->Body = "
        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:auto;
                     background:#0d1b2e;padding:36px 32px;border-radius:16px;
                     border:1px solid rgba(77,163,255,0.25);color:#fff;'>

            <div style='text-align:center;margin-bottom:24px;'>
                <span style='font-size:2.4rem;'>🏏</span>
                <h2 style='color:#4da3ff;margin:10px 0 4px;'>Cricket Ticket Booking</h2>
                <p style='color:rgba(255,255,255,0.65);font-size:0.9rem;margin:0;'>Password Reset OTP</p>
            </div>

            <p style='color:#fff;'>Hello <strong>$userName</strong>,</p>
            <p style='color:rgba(255,255,255,0.8);line-height:1.7;'>
                We received a request to reset your password. Use the OTP below to proceed.
                This OTP will expire in <strong style='color:#ffd36b;'>$expiry</strong>.
            </p>

            <div style='text-align:center;margin:28px 0;'>
                <p style='color:rgba(255,255,255,0.65);font-size:0.85rem;margin-bottom:14px;'>
                    Your One-Time Password (OTP):
                </p>
                <div style='letter-spacing:4px;'>$otpDigits</div>
            </div>

            <div style='background:rgba(255,193,7,0.1);border:1px solid rgba(255,193,7,0.3);
                        border-radius:10px;padding:14px 18px;margin:20px 0;'>
                <p style='margin:0;color:rgba(255,255,255,0.8);font-size:0.88rem;'>
                    ⚠️ <strong>Never share this OTP</strong> with anyone. Our team will never ask for it.
                    If you did not request a password reset, please ignore this email.
                </p>
            </div>

            <hr style='border:none;border-top:1px solid rgba(255,255,255,0.12);margin:24px 0;'>
            <p style='color:rgba(255,255,255,0.5);font-size:0.82rem;text-align:center;margin:0;'>
                &copy; Cricket Ticket Booking
            </p>
        </div>";

        $mail->AltBody = "Hello $userName, your password reset OTP is: $otp. It expires in $expiry. Do not share it with anyone.";

        return $mail->send();
    }
}
