<?php
session_start();
require_once 'connection.php';
require_once 'services/EmailService.php';

// if (isset($_SESSION['user_id'])) {
//     header('Location: index.php');
//     exit();
// }

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($link, $check_query);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $check_result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Email already exists. Please login or use a different email.';
        } else {
            // -------------------------------------------------------
            // STEP 1: Generate a unique verification token
            // bin2hex(random_bytes(32)) gives a 64-character random token
            // -------------------------------------------------------
            $verify_token  = bin2hex(random_bytes(32));
            $token_expiry  = date('Y-m-d H:i:s', strtotime('+24 hours')); // expires in 24 hours

            // -------------------------------------------------------
            // STEP 2: Save the user with email_verified = 0 and the token
            // -------------------------------------------------------
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users 
                             (name, email, phone, password, email_verified, reset_token, reset_expiry) 
                             VALUES (?, ?, ?, ?, 0, ?, ?)";
            $stmt = mysqli_prepare($link, $insert_query);
            mysqli_stmt_bind_param($stmt, 'ssssss',
                $name, $email, $phone, $hashed_password,
                $verify_token, $token_expiry
            );

            if (mysqli_stmt_execute($stmt)) {
                // -------------------------------------------------------
                // STEP 3: Send the verification email (wrapped in try-catch)
                // Even if email fails, the account is created successfully
                // -------------------------------------------------------
                try {
                    $emailService = new EmailService();
                    $emailSent = $emailService->sendVerificationEmail($email, $name, $verify_token);

                    if ($emailSent) {
                        $_SESSION['success_message'] = "✅ Registration successful! A verification email has been sent to <strong>$email</strong>. Please check your inbox (or Spam folder) and click the verification link before logging in.<br><br><a href='https://mail.google.com/' target='_blank' style='display:inline-block; padding:10px 20px; background:linear-gradient(135deg, #1f7ae0 0%, #46b2ff 100%); color:#fff; text-decoration:none; font-weight:bold; border-radius:12px; margin-top:10px;'><i class='fas fa-envelope me-2'></i>Open Gmail App</a>";
                    } else {
                        $_SESSION['success_message'] = "✅ Registration successful! However, the verification email could not be sent. <a href='resend_verification.php' style='color:#4da3ff;'>Click here to resend it</a>.";
                    }
                } catch (Exception $e) {
                    // SMTP error — account is still created, just email failed
                    // Log the error for debugging
                    error_log("Email send failed for $email: " . $e->getMessage());
                    $_SESSION['success_message'] = "✅ Account created! But the verification email failed to send (SMTP Error). <a href='resend_verification.php' style='color:#4da3ff;'>Click here to resend it</a>.";
                    $_SESSION['smtp_error'] = $e->getMessage(); // Store for debugging
                }

                header('Location: login.php');
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Register - Cricket Ticket Booking</title>
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

    .register-container {
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

    .register-container::before {
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

    .register-container > * {
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

    .password-strength {
        margin-top: 7px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .strength-weak {
        color: #ff8a8a;
    }

    .strength-medium {
        color: #ffd36b;
    }

    .strength-strong {
        color: #7dffb0;
    }

    @media (max-width: 768px) {
        body {
            padding: 20px 12px;
        }

        .register-container {
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
    <div class="register-container">
        <i class="fas fa-cricket-ball cricket-icon"></i>
        
        <h1 class="form-title">Join Cricket Ticket</h1>
        <p class="form-subtitle">Create your account and start booking tickets</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name" class="form-label">
                    <i class="fas fa-user me-2"></i>Full Name
                </label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                       placeholder="Enter your full name" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i>Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">
                    <i class="fas fa-phone me-2"></i>Phone Number
                </label>
                <input type="tel" class="form-control" id="phone" name="phone" 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                       placeholder="Enter your phone number">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-2"></i>Password
                </label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Create a password (min 6 chars)" required minlength="6">
                <div id="passwordStrength" class="password-strength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-lock me-2"></i>Confirm Password
                </label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       placeholder="Confirm your password" required>
            </div>

            <button type="submit" name="submit" class="btn btn-cricket">
                <i class="fas fa-user-plus me-2"></i>Create Account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>

        <div class="back-link">
            <a href="index.php">
                <i class="fas fa-arrow-left me-1"></i>Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                feedback = 'Weak password';
                strengthDiv.className = 'password-strength strength-weak';
            } else if (strength <= 4) {
                feedback = 'Medium password';
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                feedback = 'Strong password';
                strengthDiv.className = 'password-strength strength-strong';
            }
            
            strengthDiv.textContent = feedback;
        });

        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>
