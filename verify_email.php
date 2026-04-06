<?php
// ============================================================
// verify_email.php
// This page is opened when the user clicks the link in email
// URL example: verify_email.php?token=abc123xyz
// ============================================================

session_start();
require_once 'connection.php';

$message = '';
$success = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {

    $token = trim($_GET['token']);

    // Find the user with this verification token
    // Token must also not be expired (within 24 hours)
    $query = "SELECT id, name, email, email_verified 
              FROM users 
              WHERE reset_token = ? 
              AND reset_expiry > NOW() 
              LIMIT 1";

    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {

        if ($user['email_verified'] == 1) {
            // Already verified
            // Auto login and redirect
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: index.php");
            exit();
        } else {
            // Mark email as verified and clear the token
            $update = "UPDATE users 
                       SET email_verified = 1, reset_token = NULL, reset_expiry = NULL 
                       WHERE id = ?";
            $update_stmt = mysqli_prepare($link, $update);
            mysqli_stmt_bind_param($update_stmt, 'i', $user['id']);

            if (mysqli_stmt_execute($update_stmt)) {
                // Auto login and redirect
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['success_message'] = "🎉 Email verified successfully! You are now logged in.";
                header("Location: index.php");
                exit();
            } else {
                $message = "Something went wrong. Please try again.";
            }
        }

    } else {
        // Token not found or expired
        $message = "This verification link is invalid or has expired. Please register again.";
    }

} else {
    $message = "Invalid verification link.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Cricket Ticket Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(7,18,42,0.75), rgba(7,18,42,0.75)),
                        url('image/backreg.jpg') no-repeat center center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .verify-card {
            width: 420px;
            max-width: 100%;
            padding: 44px 34px;
            border-radius: 24px;
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(50px);
            border: 1px solid rgba(255,255,255,0.22);
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
            text-align: center;
        }
        .icon-circle {
            width: 90px; height: 90px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            font-size: 2.4rem;
        }
        .icon-circle.success {
            background: rgba(40,167,69,0.2);
            border: 2px solid rgba(40,167,69,0.5);
        }
        .icon-circle.error {
            background: rgba(255,89,89,0.2);
            border: 2px solid rgba(255,89,89,0.5);
        }
        h1 { color: #fff; font-size: 1.8rem; font-weight: 800; margin-bottom: 14px; }
        p { color: rgba(255,255,255,0.78); font-size: 1rem; line-height: 1.6; margin-bottom: 30px; }
        .btn-cricket {
            display: inline-block;
            padding: 13px 34px;
            border-radius: 14px;
            background: linear-gradient(135deg, #1f7ae0 0%, #46b2ff 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            border: none;
            box-shadow: 0 8px 20px rgba(31,122,224,0.35);
            transition: all 0.3s;
        }
        .btn-cricket:hover { transform: translateY(-2px); color: #fff; }
        .brand { color: #4da3ff; font-size: 0.9rem; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="verify-card">
        <p class="brand">🏏 Cricket Ticket Booking</p>

        <?php if ($success): ?>
            <div class="icon-circle success">✅</div>
            <h1>Verified!</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php" class="btn-cricket">
                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
            </a>
        <?php else: ?>
            <div class="icon-circle error">❌</div>
            <h1>Oops!</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="register.php" class="btn-cricket">
                <i class="fas fa-user-plus me-2"></i>Register Again
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
