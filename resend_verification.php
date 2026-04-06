<?php
// ============================================================
// resend_verification.php
// Resends the verification email to the user
// ============================================================

session_start();
require_once 'connection.php';
require_once 'services/EmailService.php';

$message = '';
$success = false;

// -------------------------------------------------------
// CASE A: User clicked "Resend" from the login error alert
// We use the session values saved during login attempt
// -------------------------------------------------------
if (isset($_SESSION['unverified_email'])) {
    $email = $_SESSION['unverified_email'];
    $name  = $_SESSION['unverified_name'];

    // Generate a fresh token
    $new_token  = bin2hex(random_bytes(32));
    $new_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $update = "UPDATE users 
               SET reset_token = ?, reset_expiry = ? 
               WHERE email = ? AND email_verified = 0";
    $stmt = mysqli_prepare($link, $update);
    mysqli_stmt_bind_param($stmt, 'sss', $new_token, $new_expiry, $email);

    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        $emailService = new EmailService();
        $sent = $emailService->sendVerificationEmail($email, $name, $new_token);

        if ($sent) {
            $success = true;
            $message = "Verification email resent to <strong>$email</strong>. Please check your inbox!<br><br><a href='https://mail.google.com/' target='_blank' style='display:inline-block; padding:10px 20px; background:linear-gradient(135deg, #1f7ae0 0%, #46b2ff 100%); color:#fff; text-decoration:none; font-weight:bold; border-radius:12px; margin-top:5px;'><i class='fas fa-envelope me-2'></i>Open Gmail App</a>";
        } else {
            $message = "Could not send email. Please check your SMTP settings.";
        }
    } else {
        $message = "Could not update verification token. Please try registering again.";
    }

    unset($_SESSION['unverified_email'], $_SESSION['unverified_name']);

// -------------------------------------------------------
// CASE B: User directly submits their email via the form below
// -------------------------------------------------------
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
    } else {
        $query = "SELECT id, name, email_verified FROM users WHERE email = ? LIMIT 1";
        $stmt  = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if (!$user) {
            $message = "No account found with this email.";
        } elseif ($user['email_verified'] == 1) {
            $success = true;
            $message = "This email is already verified! <a href='login.php' style='color:#4da3ff;'>Login here</a>.";
        } else {
            $new_token  = bin2hex(random_bytes(32));
            $new_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $update = "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?";
            $stmt2  = mysqli_prepare($link, $update);
            mysqli_stmt_bind_param($stmt2, 'ssi', $new_token, $new_expiry, $user['id']);
            mysqli_stmt_execute($stmt2);

            $emailService = new EmailService();
            $sent = $emailService->sendVerificationEmail($email, $user['name'] ?? 'User', $new_token);

            if ($sent) {
                $success = true;
                $message = "Verification email sent to <strong>$email</strong>. Please check your inbox!<br><br><a href='https://mail.google.com/' target='_blank' style='display:inline-block; padding:10px 20px; background:linear-gradient(135deg, #1f7ae0 0%, #46b2ff 100%); color:#fff; text-decoration:none; font-weight:bold; border-radius:12px; margin-top:5px;'><i class='fas fa-envelope me-2'></i>Open Gmail App</a>";
            } else {
                $message = "Could not send email. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - Cricket Ticket Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(7,18,42,0.75), rgba(7,18,42,0.75)),
                        url('image/backreg.jpg') no-repeat center center/cover;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 30px 15px;
        }
        .card-box {
            width: 440px; max-width: 100%;
            padding: 40px 34px;
            border-radius: 24px;
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(50px);
            border: 1px solid rgba(255,255,255,0.22);
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        }
        .brand { color: #4da3ff; text-align:center; font-size: 2.2rem; margin-bottom: 6px; }
        h1 { color: #fff; font-size: 1.7rem; font-weight: 800; text-align:center; margin-bottom: 8px; }
        .subtitle { color: rgba(255,255,255,0.7); text-align:center; font-size:0.93rem; margin-bottom: 28px; }
        .form-label { color: #fff; font-weight: 600; font-size: 0.94rem; display:block; margin-bottom: 8px; }
        .form-control {
            width: 100%; border: 1px solid rgba(255,255,255,0.2);
            border-radius: 14px; padding: 13px 15px; font-size:0.97rem;
            background: rgba(255,255,255,0.12); color: #fff;
            transition: all 0.3s;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.55); }
        .form-control:focus {
            border-color: #69b3ff; outline: none;
            background: rgba(255,255,255,0.18);
            box-shadow: 0 0 0 0.2rem rgba(77,163,255,0.22);
            color: #fff;
        }
        .btn-send {
            width: 100%; padding: 13px; margin-top: 14px;
            border: none; border-radius: 14px;
            background: linear-gradient(135deg, #1f7ae0, #46b2ff);
            color: #fff; font-weight: 700; font-size: 1rem;
            cursor: pointer; transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(31,122,224,0.35);
        }
        .btn-send:hover { transform: translateY(-2px); }
        .alert-box {
            border-radius: 14px; padding: 16px 18px;
            margin-bottom: 22px; font-size: 0.95rem;
        }
        .alert-success-custom {
            background: rgba(40,167,69,0.2);
            border: 1px solid rgba(40,167,69,0.4);
            color: #fff;
        }
        .alert-error-custom {
            background: rgba(255,89,89,0.18);
            border: 1px solid rgba(255,89,89,0.35);
            color: #fff;
        }
        .back-link { text-align:center; margin-top: 20px; }
        .back-link a { color: rgba(255,255,255,0.7); text-decoration:none; font-size:0.9rem; }
        .back-link a:hover { color: #fff; }
    </style>
</head>
<body>
<div class="card-box">
    <div class="brand">🏏</div>
    <h1>Resend Verification</h1>
    <p class="subtitle">Enter your registered email to receive a new verification link</p>

    <?php if (!empty($message)): ?>
        <div class="alert-box <?php echo $success ? 'alert-success-custom' : 'alert-error-custom'; ?>">
            <?php if ($success): ?>
                <i class="fas fa-check-circle me-2"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle me-2"></i>
            <?php endif; ?>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <div style="margin-bottom:18px;">
            <label class="form-label">
                <i class="fas fa-envelope me-2" style="color:#8fc5ff;"></i>Email Address
            </label>
            <input type="email" name="email" class="form-control"
                   placeholder="Enter your registered email" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <button type="submit" class="btn-send">
            <i class="fas fa-paper-plane me-2"></i>Send Verification Email
        </button>
    </form>
    <?php else: ?>
        <div style="text-align:center;margin-top:10px;">
            <a href="login.php" style="display:inline-block;padding:12px 30px;
               background:linear-gradient(135deg,#1f7ae0,#46b2ff);color:#fff;
               border-radius:12px;font-weight:700;text-decoration:none;">
                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
            </a>
        </div>
    <?php endif; ?>

    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
    </div>
</div>
</body>
</html>
