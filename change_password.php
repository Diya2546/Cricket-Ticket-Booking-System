<?php
session_start();
require_once 'connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        // Verify current password
        $stmt = $link->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($currentPassword, $user['password'])) {
            // Update to new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $link->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userId);
            if ($updateStmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Could not update password. Please try again.";
            }
            $updateStmt->close();
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Cricket Ticket Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --line: rgba(255,255,255,0.1); }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(180deg, #07111f 0%, #081728 45%, #050d18 100%);
            color: #f8fafc;
            min-height: 100vh;
        }
        h1, .display-font { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.04em; }
        .glass {
            background: linear-gradient(180deg, rgba(16, 31, 57, 0.8), rgba(8, 18, 35, 0.94));
            border: 1px solid var(--line);
            backdrop-filter: blur(16px);
        }
    </style>
</head>
<body class="flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <a href="profile.php" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 via-amber-400 to-emerald-400 text-slate-950 mb-4">
                <i class="fas fa-ticket-alt"></i>
            </a>
            <h1 class="text-4xl text-white">Security Settings</h1>
            <p class="text-slate-400 mt-2 tracking-wide font-medium">Update your account password</p>
        </div>

        <div class="glass rounded-[2rem] p-8 shadow-2xl">
            <?php if ($success): ?>
                <div class="mb-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="current_password" class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="w-full rounded-2xl bg-white/5 border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400/50 transition-all" required>
                </div>
                <div>
                    <label for="new_password" class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">New Password</label>
                    <input type="password" name="new_password" id="new_password" class="w-full rounded-2xl bg-white/5 border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400/50 transition-all" required minlength="6">
                </div>
                <div>
                    <label for="confirm_password" class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="w-full rounded-2xl bg-white/5 border border-white/10 px-4 py-3 text-white outline-none focus:border-emerald-400/50 transition-all" required>
                </div>
                
                <button type="submit" class="w-full rounded-full bg-white py-4 text-sm font-bold uppercase tracking-widest text-slate-950 hover:bg-amber-100 transition-all">Update Password</button>
            </form>

            <div class="mt-8 text-center pt-6 border-t border-white/5">
                <a href="profile.php" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-arrow-left me-2"></i>Back to Profile
                </a>
            </div>
        </div>
    </div>
</body>
</html>
