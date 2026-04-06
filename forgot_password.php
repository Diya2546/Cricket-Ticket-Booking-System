<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'connection.php';
require_once 'services/EmailService.php';

// ── Auto-create otp_verifications table if it doesn't exist ──
mysqli_query($link, "
    CREATE TABLE IF NOT EXISTS `otp_verifications` (
        `id`         int(11)      NOT NULL AUTO_INCREMENT,
        `user_id`    int(11)      NOT NULL,
        `email`      varchar(100) NOT NULL,
        `otp_code`   varchar(6)   NOT NULL,
        `expires_at` datetime     NOT NULL,
        `is_used`    tinyint(1)   NOT NULL DEFAULT 0,
        `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `email` (`email`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// If already logged in, redirect
// if (isset($_SESSION['user_id'])) {
//     header('Location: index.php');
//     exit();
// }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if email exists in users table
        $stmt = mysqli_prepare($link, "SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = 'No active account found with that email address.';
        } else {
            // Delete any old OTPs for this email
            $del = mysqli_prepare($link, "DELETE FROM otp_verifications WHERE email = ?");
            mysqli_stmt_bind_param($del, 's', $email);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            // Generate 6-digit OTP
            $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Save OTP in database
            $ins = mysqli_prepare($link,
                "INSERT INTO otp_verifications (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($ins, 'isss', $user['id'], $email, $otp, $expires);

            if (mysqli_stmt_execute($ins)) {
                mysqli_stmt_close($ins);

                // Send OTP email
                try {
                    $emailService = new EmailService();
                    $emailService->sendOtpEmail($email, $user['name'], $otp);
                    // Store email in session for the next step
                    $_SESSION['otp_email'] = $email;
                    header('Location: verify_otp.php');
                    exit();
                } catch (Exception $e) {
                    // OTP was saved but email failed — show detailed error
                    $error = '⚠️ OTP saved but email sending failed: ' . $e->getMessage();
                }
            } else {
                mysqli_stmt_close($ins);
                $error = '❌ Database insert error: ' . mysqli_error($link);
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
    <title>Forgot Password - Cricket Ticket Booking</title>
    <meta name="description" content="Reset your Cricket Ticket Booking account password using OTP verification.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(rgba(7, 18, 42, 0.75), rgba(7, 18, 42, 0.75)),
                url('image/backreg.jpg') no-repeat center center / cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .card-box {
            width: 430px;
            max-width: 100%;
            padding: 38px 32px 32px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.13);
            backdrop-filter: blur(50px);
            -webkit-backdrop-filter: blur(50px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0);    }
        }

        .card-box::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(145deg, rgba(255,255,255,0.16), rgba(255,255,255,0.04));
            pointer-events: none;
        }

        .card-box > * { position: relative; z-index: 1; }

        .icon-wrap {
            text-align: center;
            margin-bottom: 18px;
        }
        .icon-wrap .main-icon {
            font-size: 3rem;
            color: #4da3ff;
            animation: pulse 2s infinite;
            text-shadow: 0 6px 20px rgba(77,163,255,0.4);
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.08); }
        }

        h1.page-title {
            font-size: 1.9rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        .page-subtitle {
            color: rgba(255,255,255,0.72);
            text-align: center;
            font-size: 0.95rem;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        .step-badge {
            display: inline-block;
            background: rgba(77,163,255,0.18);
            border: 1px solid rgba(77,163,255,0.35);
            color: #7ec8ff;
            border-radius: 30px;
            padding: 4px 14px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .form-label {
            font-weight: 600;
            color: #fff;
            font-size: 0.95rem;
            margin-bottom: 8px;
            display: block;
        }
        .form-label i { color: #8fc5ff; }

        .form-control {
            width: 100%;
            padding: 13px 16px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 14px;
            background: rgba(255,255,255,0.11);
            color: #fff;
            font-size: 0.97rem;
            transition: all 0.3s ease;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.55); }
        .form-control:focus {
            outline: none;
            border-color: #69b3ff;
            background: rgba(255,255,255,0.18);
            box-shadow: 0 0 0 0.22rem rgba(77,163,255,0.22);
            color: #fff;
        }

        .btn-send {
            width: 100%;
            margin-top: 10px;
            padding: 13px 20px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #1f7ae0 0%, #46b2ff 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 28px rgba(31,122,224,0.38);
            position: relative;
            overflow: hidden;
        }
        .btn-send::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0);
            transition: background 0.2s;
        }
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(31,122,224,0.45);
        }
        .btn-send:hover::after { background: rgba(255,255,255,0.06); }

        .alert {
            border-radius: 14px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            font-size: 0.93rem;
        }
        .alert-danger  { background: rgba(255,89,89,0.18);  color: #fff; }
        .alert-success { background: rgba(40,167,69,0.18);  color: #fff; }
        .btn-close { filter: invert(1); }

        .bottom-links {
            margin-top: 22px;
            text-align: center;
            color: rgba(255,255,255,0.72);
            font-size: 0.92rem;
        }
        .bottom-links a {
            color: #8fc5ff;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.25s;
        }
        .bottom-links a:hover { color: #fff; }

        .info-box {
            background: rgba(77,163,255,0.1);
            border: 1px solid rgba(77,163,255,0.28);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 22px;
            color: rgba(255,255,255,0.8);
            font-size: 0.88rem;
            line-height: 1.6;
        }
        .info-box i { color: #4da3ff; margin-right: 6px; }

        @media (max-width: 480px) {
            .card-box { padding: 28px 18px 24px; }
            h1.page-title { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <div class="card-box">
        <div class="icon-wrap">
            <i class="fas fa-key main-icon"></i>
        </div>

        <div class="text-center">
            <span class="step-badge"><i class="fas fa-circle-dot me-1"></i> Step 1 of 3</span>
        </div>

        <h1 class="page-title">Forgot Password?</h1>
        <p class="page-subtitle">Enter your registered email and we'll send a 6-digit OTP to reset your password.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <i class="fas fa-shield-halved"></i>
            A <strong>6-digit OTP</strong> will be sent to your email. It expires in <strong>10 minutes</strong>.
        </div>

        <form method="POST" id="forgotForm">
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i>Email Address
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="Enter your registered email"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <button type="submit" name="send_otp" class="btn-send" id="sendBtn">
                <i class="fas fa-paper-plane me-2"></i>Send OTP
            </button>
        </form>

        <div class="bottom-links">
            <p class="mt-3 mb-1">
                Remember your password? <a href="login.php">Login here</a>
            </p>
            <p>
                <a href="index.php"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('sendBtn');
            // IMPORTANT: Delay disabling so browser includes button value in POST
            setTimeout(function() {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending OTP...';
            }, 50);
        });

        // Auto-dismiss alerts after 8s
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => {
                try { new bootstrap.Alert(a).close(); } catch(e) {}
            });
        }, 8000);
    </script>
</body>
</html>
