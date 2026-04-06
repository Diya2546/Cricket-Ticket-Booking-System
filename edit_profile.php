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

// Fetch current user details
$stmt = $link->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = $user['email']; // Email is read-only, keep current

    $profile_image = $user['profile_image'];

    // Handle file upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        if (!is_dir('uploads/profiles/')) {
            mkdir('uploads/profiles/', 0777, true);
        }
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newName = 'user_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], 'uploads/profiles/' . $newName)) {
                $profile_image = $newName;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
        }
    }

    if (empty($name) || empty($phone)) {
        $error = "Name and Phone are required.";
    } elseif (!$error) { // Only update if no upload error
        $updateStmt = $link->prepare("UPDATE users SET name = ?, phone = ?, profile_image = ? WHERE id = ?");
        $updateStmt->bind_param("sssi", $name, $phone, $profile_image, $userId);
        if ($updateStmt->execute()) {
            $success = "Profile updated successfully!";
            $_SESSION['user_name'] = $name; // Update session if name changed
            // Refresh local user data
            $user['name'] = $name;
            $user['phone'] = $phone;
            $user['profile_image'] = $profile_image;
        } else {
            $error = "Error updating profile.";
        }
        $updateStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Cricket Ticket Hub</title>
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
    <div class="w-full max-w-lg">
        <div class="mb-8 text-center">
            <a href="profile.php" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 via-amber-400 to-emerald-400 text-slate-950 mb-4">
                <i class="fas fa-user-edit"></i>
            </a>
            <h1 class="text-4xl text-white">Edit Your Profile</h1>
            <p class="text-slate-400 mt-2 tracking-wide font-medium">Keep your contact information up to date</p>
        </div>

        <div class="glass rounded-[2rem] p-8 shadow-2xl sm:p-10">
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

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Profile Image</label>
                    <div class="flex items-center gap-4">
                        <?php if (!empty($user['profile_image']) && $user['profile_image'] !== 'default.jpg'): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="w-16 h-16 rounded-full object-cover border border-white/10" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random';">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" alt="Profile" class="w-16 h-16 rounded-full object-cover border border-white/10">
                        <?php endif; ?>
                        
                        <input type="file" name="profile_image" accept="image/*" class="w-full text-sm text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/20 transition-all cursor-pointer">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full rounded-2xl bg-white/5 border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400/50 transition-all font-medium" required>
                </div>
                <div>
                    <label for="email" class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Email Address (Read Only)</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full rounded-2xl bg-white/5 border border-white/10 px-4 py-3 text-slate-400 outline-none cursor-not-allowed" readonly>
                </div>
                <div>
                    <label for="phone" class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Phone Number</label>
                    <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full rounded-2xl bg-white/5 border border-white/10 px-4 py-3 text-white outline-none focus:border-emerald-400/50 transition-all font-medium" required>
                </div>
                
                <div class="pt-4 grid sm:grid-cols-2 gap-4">
                    <button type="submit" class="rounded-full bg-white py-4 text-sm font-bold uppercase tracking-widest text-slate-950 hover:bg-amber-100 transition-all">Save Changes</button>
                    <a href="profile.php" class="rounded-full border border-white/10 py-4 text-sm font-bold uppercase tracking-widest text-white text-center hover:bg-white/5 transition-all">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>