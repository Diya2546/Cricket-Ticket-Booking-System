<?php
session_start();
require_once 'connection.php';

// Must have come from forgot_password.php
if (empty($_SESSION['otp_email'])) {
    header('Location: forgot_password.php');
    exit();
}

$email   = $_SESSION['otp_email'];
$error   = '';
$success = '';

// ── Resend OTP ──────────────────────────────────────────────────────────────
if (isset($_POST['resend_otp'])) {
    require_once 'services/EmailService.php';

    $stmt = mysqli_prepare($link, "SELECT id, name FROM users WHERE email = ? AND status='active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($user) {
        // Delete old OTPs
        $del = mysqli_prepare($link, "DELETE FROM otp_verifications WHERE email = ?");
        mysqli_stmt_bind_param($del, 's', $email);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $ins = mysqli_prepare($link,
            "INSERT INTO otp_verifications (user_id, email, otp_code, expires_at) VALUES (?,?,?,?)"
        );
        mysqli_stmt_bind_param($ins, 'isss', $user['id'], $email, $otp, $expires);

        if (mysqli_stmt_execute($ins)) {
            mysqli_stmt_close($ins);
            try {
                $svc = new EmailService();
                $svc->sendOtpEmail($email, $user['name'], $otp);
                $success = 'A new OTP has been sent to your email.';
            } catch (Exception $e) {
                $error = 'Failed to resend OTP. Please try again.';
            }
        } else {
            mysqli_stmt_close($ins);
            $error = 'Database error. Please try again.';
        }
    }
}

// ── Verify OTP ──────────────────────────────────────────────────────────────
if (isset($_POST['verify_otp'])) {
    // Combine 6 individual digit inputs
    $digits = [];
    for ($i = 1; $i <= 6; $i++) {
        $digits[] = isset($_POST["d$i"]) ? trim($_POST["d$i"]) : '';
    }
    $otp_entered = implode('', $digits);

    if (strlen($otp_entered) !== 6 || !ctype_digit($otp_entered)) {
        $error = 'Please enter a valid 6-digit OTP.';
    } else {
        $now  = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($link,
            "SELECT id FROM otp_verifications
             WHERE email = ? AND otp_code = ? AND expires_at > ? AND is_used = 0
             ORDER BY id DESC LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $email, $otp_entered, $now);
        mysqli_stmt_execute($stmt);
        $otp_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$otp_row) {
            $error = 'Invalid or expired OTP. Please try again or resend.';
        } else {
            // Mark OTP as used
            $upd = mysqli_prepare($link, "UPDATE otp_verifications SET is_used = 1 WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'i', $otp_row['id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // Allow password reset
            $_SESSION['otp_verified'] = true;
            header('Location: reset_password.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Cricket Ticket Booking</title>
    <meta name="description" content="Enter the 6-digit OTP sent to your email to reset your password.">

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
            width: 460px;
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
        .icon-wrap .main-icon {
            font-size: 3rem;
            color: #4da3ff;
            text-shadow: 0 6px 20px rgba(77,163,255,0.4);
            animation: bounce 2.5s infinite;
        }
        @keyframes bounce {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-8px); }
        }

        h1.page-title {
            font-size: 1.85rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 6px;
        }
        .page-subtitle {
            color: rgba(255,255,255,0.72);
            font-size: 0.93rem;
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.55;
        }
        .email-highlight {
            display: inline-block;
            color: #7ec8ff;
            font-weight: 700;
            background: rgba(77,163,255,0.12);
            border-radius: 8px;
            padding: 2px 10px;
            margin-bottom: 20px;
            font-size: 0.92rem;
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

        /* ─── OTP Input Boxes ─── */
        .otp-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0 10px;
        }
        .otp-digit {
            width: 52px;
            height: 58px;
            border: 2px solid rgba(255,255,255,0.22);
            border-radius: 14px;
            background: rgba(255,255,255,0.10);
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
            transition: all 0.25s ease;
            caret-color: #4da3ff;
        }
        .otp-digit:focus {
            outline: none;
            border-color: #4da3ff;
            background: rgba(77,163,255,0.14);
            box-shadow: 0 0 0 3px rgba(77,163,255,0.22);
            transform: scale(1.07);
        }
        .otp-digit.filled {
            border-color: rgba(77,163,255,0.7);
            background: rgba(77,163,255,0.12);
        }
        .otp-digit.error-box {
            border-color: rgba(255,89,89,0.8);
            background: rgba(255,89,89,0.12);
        }

        .btn-verify {
            width: 100%;
            margin-top: 12px;
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
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(31,122,224,0.45);
        }
        .btn-verify:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            border-radius: 14px;
            margin-bottom: 18px;
            border: 1px solid rgba(255,255,255,0.12);
            font-size: 0.92rem;
        }
        .alert-danger  { background: rgba(255,89,89,0.18);  color: #fff; }
        .alert-success { background: rgba(40,167,69,0.18);  color: #fff; }
        .btn-close { filter: invert(1); }

        /* ─── Timer & Resend ─── */
        .timer-wrap {
            text-align: center;
            margin-top: 14px;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }
        #countdown {
            font-weight: 700;
            color: #ffd36b;
        }
        .resend-form { display: inline; }
        .btn-resend {
            background: none;
            border: none;
            color: #8fc5ff;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: underline;
            padding: 0;
        }
        .btn-resend:hover { color: #fff; }
        .btn-resend:disabled { color: rgba(255,255,255,0.4); cursor: default; text-decoration: none; }

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
            .card-box { padding: 28px 16px 24px; }
            .otp-digit { width: 42px; height: 50px; font-size: 1.4rem; border-radius: 10px; }
            .otp-group { gap: 7px; }
        }
    </style>
</head>
<body>
    <div class="card-box">
        <div class="icon-wrap">
            <i class="fas fa-envelope-open-text main-icon"></i>
        </div>

        <div class="text-center">
            <span class="step-badge"><i class="fas fa-circle-dot me-1"></i> Step 2 of 3</span>
        </div>

        <h1 class="page-title">Verify OTP</h1>
        <p class="page-subtitle">We sent a 6-digit code to</p>
        <div class="text-center">
            <span class="email-highlight">
                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($email); ?>
            </span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="otpForm">
            <div class="otp-group" id="otpGroup">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        class="otp-digit"
                        id="d<?php echo $i; ?>"
                        name="d<?php echo $i; ?>"
                        autocomplete="off"
                        required
                    >
                <?php endfor; ?>
            </div>

            <button type="submit" name="verify_otp" class="btn-verify" id="verifyBtn">
                <i class="fas fa-check-circle me-2"></i>Verify OTP
            </button>
        </form>

        <!-- Resend OTP -->
        <div class="timer-wrap">
            <span id="timerMsg">OTP expires in <span id="countdown">10:00</span></span>
            <span id="resendMsg" style="display:none;">
                Didn't receive the code?
                <form method="POST" class="resend-form">
                    <button type="submit" name="resend_otp" class="btn-resend" id="resendBtn">
                        Resend OTP
                    </button>
                </form>
            </span>
        </div>

        <div class="bottom-links">
            <p class="mt-3">
                <a href="forgot_password.php"><i class="fas fa-arrow-left me-1"></i>Use different email</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ── OTP digit auto-focus & navigation ──────────────────────────
    const digits = document.querySelectorAll('.otp-digit');
    digits[0].focus();

    digits.forEach((input, idx) => {
        input.addEventListener('input', function () {
            // Allow only digits
            this.value = this.value.replace(/\D/g, '').slice(-1);
            if (this.value) {
                this.classList.add('filled');
                if (idx < 5) digits[idx + 1].focus();
            } else {
                this.classList.remove('filled');
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) {
                digits[idx - 1].focus();
                digits[idx - 1].value = '';
                digits[idx - 1].classList.remove('filled');
            }
            // Arrow keys
            if (e.key === 'ArrowRight' && idx < 5) digits[idx + 1].focus();
            if (e.key === 'ArrowLeft'  && idx > 0) digits[idx - 1].focus();
        });

        // Handle paste (paste full OTP)
        input.addEventListener('paste', function (e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData)
                .getData('text').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach((ch, i) => {
                if (digits[i]) {
                    digits[i].value = ch;
                    digits[i].classList.add('filled');
                }
            });
            if (pasted.length < 6) digits[pasted.length].focus();
            else digits[5].focus();
        });
    });

    // ── Submit: show spinner ──────────────────────────────────────
    document.getElementById('otpForm').addEventListener('submit', function (e) {
        const btn = document.getElementById('verifyBtn');
        // IMPORTANT: Delay disabling so browser includes button value in POST
        setTimeout(function() {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
        }, 50);
    });

    // ── Countdown timer (10 min) ──────────────────────────────────
    let total = 10 * 60;
    const countdownEl = document.getElementById('countdown');
    const timerMsg    = document.getElementById('timerMsg');
    const resendMsg   = document.getElementById('resendMsg');

    const timer = setInterval(() => {
        total--;
        const m = Math.floor(total / 60).toString().padStart(2, '0');
        const s = (total % 60).toString().padStart(2, '0');
        countdownEl.textContent = `${m}:${s}`;
        if (total <= 60) countdownEl.style.color = '#ff8a8a';
        if (total <= 0) {
            clearInterval(timer);
            timerMsg.style.display = 'none';
            resendMsg.style.display = 'inline';
        }
    }, 1000);

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            try { new bootstrap.Alert(a).close(); } catch(e) {}
        });
    }, 6000);
    </script>
</body>
</html>
