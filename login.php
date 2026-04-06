<?php
session_start();
require_once 'connection.php';
require_once 'services/EmailService.php';

// If already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit();
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {

        // =========================
        // CHECK ADMIN LOGIN FIRST
        // =========================
        $admin_query = "SELECT id, username, email, password, role FROM admins WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($link, $admin_query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $admin_result = mysqli_stmt_get_result($stmt);

            if ($admin = mysqli_fetch_assoc($admin_result)) {
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_role'] = $admin['role'];

                    // Send login alert email to admin
                    try {
                        $emailService = new EmailService();
                        $emailService->sendLoginEmail($admin['email'], $admin['username']);
                    } catch (Exception $e) {
                        // Ignore email error
                    }

                    // Update last login
                    $update_admin = "UPDATE admins SET last_login = NOW() WHERE id = ?";
                    $update_stmt = mysqli_prepare($link, $update_admin);
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, 'i', $admin['id']);
                        mysqli_stmt_execute($update_stmt);
                    }

                    header('Location: admin/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password';
                }
            } else {

                // =========================
                // CHECK USER LOGIN
                // =========================
                $user_query = "SELECT id, name, email, password, status, email_verified 
                               FROM users WHERE email = ? LIMIT 1";
                $stmt2 = mysqli_prepare($link, $user_query);

                if ($stmt2) {
                    mysqli_stmt_bind_param($stmt2, 's', $email);
                    mysqli_stmt_execute($stmt2);
                    $user_result = mysqli_stmt_get_result($stmt2);

                    if ($user = mysqli_fetch_assoc($user_result)) {
                        if ($user['status'] !== 'active') {
                            $error = 'Your account is inactive. Please contact support.';
                        } elseif (!password_verify($password, $user['password'])) {
                            $error = 'Invalid email or password';
                        } elseif ($user['email_verified'] == 0) {
                            // -------------------------------------------------------
                            // EMAIL NOT VERIFIED — block login, offer resend option
                            // -------------------------------------------------------
                            $error = 'email_not_verified';
                            $_SESSION['unverified_email'] = $user['email'];
                            $_SESSION['unverified_name']  = $user['name'];
                        } else {
                            // ✅ All checks passed — Login successful
                            $_SESSION['user_id']    = $user['id'];
                            $_SESSION['user_name']  = $user['name'];
                            $_SESSION['user_email'] = $user['email'];

                            // Send login alert email to user
                            try {
                                $emailService = new EmailService();
                                $emailService->sendLoginEmail($user['email'], $user['name']);
                            } catch (Exception $e) {
                                // Ignore email error
                            }

                            header('Location: index.php');
                            exit();
                        }
                    } else {
                        $error = 'Invalid email or password';
                    }
                } else {
                    $error = 'Database error. Please try again.';
                }
            }
        } else {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cricket Ticket Booking</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(rgba(7, 18, 42, 0.72), rgba(7, 18, 42, 0.72)),
                url('image/backreg.jpg') no-repeat center center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            overflow-x: hidden;
        }

        .login-container {
            width: 430px;
            max-width: 100%;
            padding: 34px 30px 28px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(50px);
            -webkit-backdrop-filter: blur(50px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                145deg,
                rgba(255,255,255,0.18),
                rgba(255,255,255,0.05)
            );
            pointer-events: none;
        }

        .login-container > * {
            position: relative;
            z-index: 1;
        }

        .cricket-icon {
            font-size: 3rem;
            color: #4da3ff;
            margin-bottom: 16px;
            animation: bounce 2s infinite;
            text-align: center;
            display: block;
            text-shadow: 0 6px 18px rgba(77, 163, 255, 0.35);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-8px);
            }
            60% {
                transform: translateY(-4px);
            }
        }

        .form-title {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .form-subtitle {
            color: rgba(255, 255, 255, 0.78);
            margin-bottom: 26px;
            text-align: center;
            font-size: 0.97rem;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        .form-label i {
            color: #8fc5ff;
        }

        .form-control {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.20);
            border-radius: 14px;
            padding: 13px 15px;
            font-size: 0.98rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.62);
        }

        .form-control:focus {
            border-color: #69b3ff;
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 0 0 0.22rem rgba(77, 163, 255, 0.22);
            outline: none;
            color: #fff;
        }

        .btn-cricket {
            width: 100%;
            margin-top: 8px;
            padding: 13px 20px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #1f7ae0 0%, #46b2ff 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(31, 122, 224, 0.35);
        }

        .btn-cricket:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(31, 122, 224, 0.42);
            color: #fff;
        }

        .btn-cricket:focus {
            color: #fff;
            box-shadow: 0 0 0 0.22rem rgba(77, 163, 255, 0.25);
        }

        .alert {
            border-radius: 14px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.14);
            backdrop-filter: blur(10px);
        }

        .alert-danger {
            background: rgba(255, 89, 89, 0.18);
            color: #fff;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.18);
            color: #fff;
        }

        .btn-close {
            filter: invert(1);
        }

        .login-link,
        .back-link {
            text-align: center;
        }

        .login-link {
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.8);
        }

        .login-link a,
        .back-link a {
            color: #8fc5ff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-link a:hover,
        .back-link a:hover {
            color: #fff;
        }

        .back-link {
            margin-top: 14px;
        }

        .back-link a {
            color: rgba(255, 255, 255, 0.78);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            body {
                padding: 20px 12px;
            }

            .login-container {
                width: 100%;
                padding: 28px 20px 24px;
                border-radius: 20px;
            }

            .form-title {
                font-size: 1.65rem;
            }

            .form-subtitle {
                font-size: 0.92rem;
            }

            .cricket-icon {
                font-size: 2.4rem;
            }

            .form-control {
                padding: 12px 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <i class="fas fa-ticket-alt cricket-icon"></i>

        <h1 class="form-title">Welcome Back</h1>
        <p class="form-subtitle">Login with your email and password</p>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; // HTML allowed for bold email ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <?php if ($error === 'email_not_verified'): ?>
                <!-- Special block: Email not verified -->
                <div class="alert alert-warning alert-dismissible fade show" role="alert"
                     style="background:rgba(255,193,7,0.18);color:#fff;border-color:rgba(255,193,7,0.3);">
                    <i class="fas fa-envelope me-2"></i>
                    <strong>Email Not Verified!</strong><br>
                    Please verify your email before logging in.
                    <a href="resend_verification.php" class="d-block mt-2"
                       style="color:#ffd36b;font-weight:700;text-decoration:underline;">
                        🔁 Resend Verification Email
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                </div>
            <?php else: ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>


        <form method="POST">
            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i>Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-2"></i>Password
                </label>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="Enter your password" required>
            </div>

            <button type="submit" name="submit" class="btn btn-cricket">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>

            <div style="text-align:center;margin-top:14px;">
                <a href="forgot_password.php"
                   style="color:#8fc5ff;font-size:0.9rem;text-decoration:none;font-weight:600;transition:color 0.25s;"
                   onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#8fc5ff'">
                    <i class="fas fa-key me-1"></i>Forgot Password?
                </a>
            </div>
        </form>

        <div class="login-link">
            Don’t have an account? <a href="register.php">Sign up here</a>
        </div>

        <div class="back-link">
            <a href="index.php">
                <i class="fas fa-arrow-left me-1"></i>Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert:not(.alert-success)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>