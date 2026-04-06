<?php
session_start();
require_once 'connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Handle fast profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fast_upload_image'])) {
    if ($_FILES['fast_upload_image']['error'] == 0) {
        if (!is_dir('uploads/profiles/')) {
            mkdir('uploads/profiles/', 0777, true);
        }
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['fast_upload_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newName = 'user_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['fast_upload_image']['tmp_name'], 'uploads/profiles/' . $newName)) {
                $updateStmt = $link->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newName, $userId);
                $updateStmt->execute();
                $updateStmt->close();
                header("Location: profile.php");
                exit();
            }
        }
    }
}

// Fetch user details
$stmt = $link->prepare("SELECT name, email, phone, profile_image, email_verified, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch booking count
$stmt2 = $link->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$ticketCount = (int)($stmt2->get_result()->fetch_assoc()['total'] ?? 0);
$stmt2->close();

$joinedDate = isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Profile - Cricket Ticket Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top right, rgba(55, 211, 159, 0.05), transparent 30%), 
                        linear-gradient(180deg, #050b14 0%, #0a1320 100%);
            color: #f8fafc;
            min-height: 100vh;
        }
        .main-card {
            background: linear-gradient(180deg, #101a2b 0%, #0d1523 100%);
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            border-radius: 1.5rem;
        }
        .gradient-ring {
            background: linear-gradient(135deg, #f59e0b, #34d399); /* yellow to green */
            padding: 3px;
            border-radius: 50%;
        }
        .avatar-inner {
            background: #101a2b;
            border-radius: 50%;
            height: 100%;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gradient-button {
            background: linear-gradient(90deg, #f97316 0%, #f59e0b 40%, #34d399 100%);
            color: #0f172a;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .gradient-button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .action-card {
            background: #111b2d;
            border: 1px solid rgba(255,255,255,0.03);
            border-radius: 1rem;
            transition: all 0.2s ease;
        }
        .action-card:hover {
            background: #152238;
            border-color: rgba(255,255,255,0.08);
        }
        .tickets-card {
            background: #111b2d;
            border: 1px solid rgba(255,255,255,0.03);
            border-radius: 1rem;
        }
    </style>
</head>
<body class="flex flex-col items-center justify-center p-4 sm:p-8">

<header class="w-full max-w-4xl flex justify-between items-center mb-8">
    <a href="index.php" class="flex items-center gap-3 text-white hover:text-amber-400 transition">
        <i class="fas fa-arrow-left"></i>
        <span class="font-semibold tracking-wide">Back to Home</span>
    </a>
</header>

<main class="w-full max-w-4xl main-card p-8 sm:p-12 relative overflow-hidden">
    <!-- Subtle top right glow inside card -->
    <div class="absolute -top-32 -right-32 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- ── Top Section: Avatar & Info ───────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-8 border-b border-white/5 pb-10 mb-10">
        <!-- Avatar with Gradient Ring -->
        <div class="shrink-0 relative group">
            <div class="gradient-ring w-32 h-32 relative shadow-[0_0_30px_rgba(245,158,11,0.15)] rounded-full cursor-pointer transition-transform hover:scale-105" onclick="document.getElementById('fastUploadInput').click()">
                <div class="avatar-inner overflow-hidden rounded-full w-full h-full relative">
                    <?php if (!empty($user['profile_image']) && $user['profile_image'] !== 'default.jpg'): ?>
                        <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random&size=128" alt="Profile" class="w-full h-full object-cover">
                    <?php endif; ?>

                    <!-- Upload Overlay visible on hover -->
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center transition-opacity duration-300">
                        <i class="fas fa-camera text-2xl text-white mb-1"></i>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-white">Change</span>
                    </div>
                </div>
            </div>

            <!-- Hidden File Input Form -->
            <form id="fastUploadForm" method="POST" enctype="multipart/form-data" class="hidden">
                <input type="file" id="fastUploadInput" name="fast_upload_image" accept="image/*" onchange="document.getElementById('fastUploadForm').submit()">
            </form>
        </div>

        <div class="flex flex-col text-center sm:text-left pt-2">
            <h1 class="text-4xl sm:text-5xl font-bold text-white mb-4 tracking-tight"><?php echo htmlspecialchars($user['name']); ?></h1>
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 text-[15px] text-slate-300 font-medium">
                <div class="flex items-center gap-2">
                    <i class="fas fa-envelope text-emerald-400 text-sm"></i>
                    <?php echo htmlspecialchars($user['email']); ?>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-phone-alt text-amber-500 text-sm"></i>
                    <?php echo htmlspecialchars($user['phone'] ?: 'No phone provided'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Bottom Section: Two Columns ───────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 relative z-10">
        
        <!-- Left Column: Tickets Card -->
        <div class="tickets-card p-8 flex flex-col justify-between shadow-lg">
            <div>
                <div class="flex justify-between items-start mb-6">
                    <div class="w-12 h-12 rounded-xl bg-amber-600/20 flex items-center justify-center text-amber-500">
                        <i class="fas fa-ticket-alt text-xl"></i>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-[10px] font-bold uppercase tracking-widest text-slate-400">History</span>
                </div>
                <h3 class="text-2xl font-bold text-white mb-4">My Match Tickets</h3>
                
                <div class="flex items-baseline gap-3 mb-6">
                    <span class="text-5xl font-light text-amber-300 leading-none"><?php echo $ticketCount; ?></span>
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Bookings Made</span>
                </div>

                <p class="text-sm text-slate-400 leading-relaxed mb-8 pr-4">
                    View your active and past match bookings, download e-tickets, and submit feedback.
                </p>
            </div>
            
            <a href="MyBooking.php" class="gradient-button w-full block text-center py-4 rounded-xl shadow-[0_10px_20px_rgba(52,211,153,0.15)] uppercase tracking-widest text-sm">
                View Bookings <i class="fas fa-arrow-right ml-2 opacity-80"></i>
            </a>
        </div>

        <!-- Right Column: Actions -->
        <div class="flex flex-col gap-4">
            
            <a href="edit_profile.php" class="action-card p-5 pr-6 flex items-center gap-5 group cursor-pointer shadow-lg">
                <div class="w-12 h-12 rounded-xl bg-emerald-900/40 flex items-center justify-center text-emerald-400 shrink-0 transition-transform group-hover:scale-110">
                    <i class="fas fa-user-edit text-lg"></i>
                </div>
                <div class="flex-grow">
                    <h4 class="text-lg font-bold text-white mb-1">Edit Profile</h4>
                    <p class="text-xs text-slate-500 font-medium">Update your personal details</p>
                </div>
                <i class="fas fa-chevron-right text-slate-600 group-hover:text-emerald-400 transition-colors text-sm"></i>
            </a>

            <a href="change_password.php" class="action-card p-5 pr-6 flex items-center gap-5 group cursor-pointer shadow-lg">
                <div class="w-12 h-12 rounded-xl bg-amber-900/40 flex items-center justify-center text-amber-400 shrink-0 transition-transform group-hover:scale-110">
                    <i class="fas fa-lock text-lg"></i>
                </div>
                <div class="flex-grow">
                    <h4 class="text-lg font-bold text-white mb-1">Change Password</h4>
                    <p class="text-xs text-slate-500 font-medium">Secure your account</p>
                </div>
                <i class="fas fa-chevron-right text-slate-600 group-hover:text-amber-400 transition-colors text-sm"></i>
            </a>

            <a href="logout.php" class="action-card p-5 pr-6 flex items-center gap-5 group cursor-pointer shadow-lg border-red-500/10 hover:border-red-500/20">
                <div class="w-12 h-12 rounded-xl bg-red-900/30 flex items-center justify-center text-red-400 shrink-0 transition-transform group-hover:scale-110">
                    <i class="fas fa-sign-out-alt text-lg"></i>
                </div>
                <div class="flex-grow">
                    <h4 class="text-lg font-bold text-white mb-1">Logout</h4>
                    <p class="text-xs text-slate-500 font-medium">Sign out of your account</p>
                </div>
                <i class="fas fa-chevron-right text-slate-600 group-hover:text-red-400 transition-colors text-sm"></i>
            </a>

        </div>
    </div>
</main>

</body>
</html>