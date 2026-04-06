<?php
session_start();
require_once 'connection.php';

// Guard: must have gone through OTP verification
if (empty($_SESSION['otp_email']) || empty($_SESSION['otp_verified'])) {
    header('Location: forgot_password.php');
    exit();
}

$email = $_SESSION['otp_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one letter and one number.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);

        $stmt = mysqli_prepare($link, "UPDATE users SET password = ? WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $hashed, $email);

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);

            // Clean up all OTPs for this user
            $del = mysqli_prepare($link, "DELETE FROM otp_verifications WHERE email = ?");
            mysqli_stmt_bind_param($del, 's', $email);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            // Clear session flags
            unset($_SESSION['otp_email'], $_SESSION['otp_verified']);

            $_SESSION['success_message'] = '✅ Password reset successfully! Please login with your new password.';
            header('Location: login.php');
            exit();
        } else {
            mysqli_stmt_close($stmt);
            $error = 'Failed to update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Cricket Ticket Booking</title>
    <meta name="description" content="Set a new password for your Cricket Ticket Booking account.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(rgba(7,18,42,0.75), rgba(7,18,42,0.75)),
                url('image/backreg.jpg') no-repeat center center / cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .card-box {
            width: 440px;
            max-width: 100%;
            padding: 38px 32px 32px;
            border-radius: 24px;
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(50px);
            -webkit-backdrop-filter: blur(50px);
            border: 1px solid rgba(255,255,255,0.22);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-box::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(145deg, rgba(255,255,255,0.16), rgba(255,255,255,0.04));
            pointer-events: none;
        }
        .card-box > * { position: relative; z-index: 1; }

        .icon-wrap { text-align: center; margin-bottom: 18px; }
        .main-icon {
            font-size: 3rem;
            color: #4da3ff;
            text-shadow: 0 6px 20px rgba(77,163,255,0.4);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%,100% { transform: scale(1); }
            50%      { transform: scale(1.08); }
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
            margin-bottom: 12px;
        }

        h1.page-title {
            font-size: 1.9rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 6px;
        }
        .page-subtitle {
            color: rgba(255,255,255,0.72);
            text-align: center;
            font-size: 0.93rem;
            margin-bottom: 26px;
            line-height: 1.5;
        }

        .form-label {
            font-weight: 600;
            color: #fff;
            font-size: 0.95rem;
            margin-bottom: 8px;
            display: block;
        }
        .form-label i { color: #8fc5ff; }

        .input-group {
            position: relative;
            margin-bottom: 18px;
        }
        .form-control {
            width: 100%;
            padding: 13px 48px 13px 16px;
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
        .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.55);
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.2s;
            padding: 4px;
        }
        .toggle-pw:hover { color: #fff; }

        /* Strength meter */
        .strength-bar {
            height: 4px;
            border-radius: 4px;
            margin-top: 8px;
            background: rgba(255,255,255,0.12);
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 4px;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .strength-label {
            font-size: 0.78rem;
            margin-top: 4px;
            color: rgba(255,255,255,0.6);
        }

        /* Requirement list */
        .req-list {
            list-style: none;
            padding: 0;
            margin: 10px 0 6px;
            font-size: 0.82rem;
        }
        .req-list li {
            color: rgba(255,255,255,0.55);
            margin-bottom: 3px;
            transition: color 0.2s;
        }
        .req-list li.met { color: #5cd65c; }
        .req-list li i { margin-right: 5px; }

        .btn-reset {
            width: 100%;
            margin-top: 8px;
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
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(31,122,224,0.45);
        }
        .btn-reset:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .alert {
            border-radius: 14px;
            margin-bottom: 18px;
            border: 1px solid rgba(255,255,255,0.12);
            font-size: 0.92rem;
        }
        .alert-danger { background: rgba(255,89,89,0.18); color: #fff; }
        .btn-close { filter: invert(1); }

        .match-msg {
            font-size: 0.8rem;
            margin-top: 5px;
            min-height: 16px;
        }

        .bottom-links {
            margin-top: 22px;
            text-align: center;
            color: rgba(255,255,255,0.65);
            font-size: 0.9rem;
        }
        .bottom-links a {
            color: #8fc5ff;
            font-weight: 600;
            text-decoration: none;
        }
        .bottom-links a:hover { color: #fff; }

        @media (max-width: 480px) {
            .card-box { padding: 28px 18px 24px; }
            h1.page-title { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <div class="card-box">
        <div class="icon-wrap">
            <i class="fas fa-shield-halved main-icon"></i>
        </div>

        <div class="text-center">
            <span class="step-badge"><i class="fas fa-circle-dot me-1"></i> Step 3 of 3</span>
        </div>

        <h1 class="page-title">Reset Password</h1>
        <p class="page-subtitle">Create a strong new password for your account.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="resetForm">
            <!-- New Password -->
            <div>
                <label for="new_password" class="form-label">
                    <i class="fas fa-lock me-2"></i>New Password
                </label>
                <div class="input-group">
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-control"
                        placeholder="Enter new password"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="toggle-pw" onclick="togglePw('new_password', 'eyeIcon1')" id="eyeIcon1Btn" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="eyeIcon1"></i>
                    </button>
                </div>

                <!-- Strength bar -->
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel">Password strength</div>

                <!-- Requirements -->
                <ul class="req-list">
                    <li id="req-len"><i class="fas fa-circle-xmark"></i> At least 8 characters</li>
                    <li id="req-let"><i class="fas fa-circle-xmark"></i> Contains a letter</li>
                    <li id="req-num"><i class="fas fa-circle-xmark"></i> Contains a number</li>
                </ul>
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-lock-open me-2"></i>Confirm Password
                </label>
                <div class="input-group">
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Re-enter new password"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', 'eyeIcon2')" id="eyeIcon2Btn" aria-label="Toggle confirm password visibility">
                        <i class="fas fa-eye" id="eyeIcon2"></i>
                    </button>
                </div>
                <div class="match-msg" id="matchMsg"></div>
            </div>

            <button type="submit" name="reset_password" class="btn-reset" id="resetBtn">
                <i class="fas fa-check-circle me-2"></i>Reset Password
            </button>
        </form>

        <div class="bottom-links">
            <p class="mt-3">
                <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ── Toggle password visibility ───────────────────────────────
    function togglePw(fieldId, iconId) {
        const field = document.getElementById(fieldId);
        const icon  = document.getElementById(iconId);
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // ── Password strength ────────────────────────────────────────
    const pwInput     = document.getElementById('new_password');
    const fill        = document.getElementById('strengthFill');
    const label       = document.getElementById('strengthLabel');
    const reqLen      = document.getElementById('req-len');
    const reqLet      = document.getElementById('req-let');
    const reqNum      = document.getElementById('req-num');

    const levels = [
        { pct: '20%', color: '#ff4d4d', text: 'Very Weak' },
        { pct: '40%', color: '#ff944d', text: 'Weak'      },
        { pct: '60%', color: '#ffd36b', text: 'Fair'      },
        { pct: '80%', color: '#4da3ff', text: 'Good'      },
        { pct: '100%',color: '#5cd65c', text: 'Strong'    },
    ];

    function updateReq(el, met) {
        const icon = el.querySelector('i');
        if (met) {
            el.classList.add('met');
            icon.classList.replace('fa-circle-xmark', 'fa-circle-check');
        } else {
            el.classList.remove('met');
            icon.classList.replace('fa-circle-check', 'fa-circle-xmark');
        }
    }

    pwInput.addEventListener('input', function () {
        const v = this.value;
        const hasLen = v.length >= 8;
        const hasLet = /[A-Za-z]/.test(v);
        const hasNum = /[0-9]/.test(v);
        const hasSym = /[^A-Za-z0-9]/.test(v);
        const hasUp  = /[A-Z]/.test(v);

        updateReq(reqLen, hasLen);
        updateReq(reqLet, hasLet);
        updateReq(reqNum, hasNum);

        let score = 0;
        if (hasLen) score++;
        if (hasLet) score++;
        if (hasNum) score++;
        if (hasSym) score++;
        if (hasUp)  score++;

        if (!v.length) { fill.style.width = '0%'; label.textContent = 'Password strength'; return; }
        const lvl = levels[Math.max(0, score - 1)];
        fill.style.width      = lvl.pct;
        fill.style.background = lvl.color;
        label.textContent     = lvl.text;
        label.style.color     = lvl.color;
    });

    // ── Confirm match check ─────────────────────────────────────
    const confirmInput = document.getElementById('confirm_password');
    const matchMsg     = document.getElementById('matchMsg');

    function checkMatch() {
        if (!confirmInput.value) { matchMsg.textContent = ''; return; }
        if (pwInput.value === confirmInput.value) {
            matchMsg.style.color = '#5cd65c';
            matchMsg.textContent = '✅ Passwords match!';
        } else {
            matchMsg.style.color = '#ff8a8a';
            matchMsg.textContent = '❌ Passwords do not match';
        }
    }
    pwInput.addEventListener('input', checkMatch);
    confirmInput.addEventListener('input', checkMatch);

    // ── Submit spinner ─────────────────────────────────────────
    document.getElementById('resetForm').addEventListener('submit', function (e) {
        const btn = document.getElementById('resetBtn');
        setTimeout(function() {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        }, 50);
    });

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            try { new bootstrap.Alert(a).close(); } catch(e) {}
        });
    }, 5000);
    </script>
</body>
</html>
