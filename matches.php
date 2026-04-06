<?php
session_start();
require_once 'connection.php';

/* =========================
   MARK NOTIFICATIONS AS READ
========================= */
if (isset($_GET['mark_notifications_read']) && $_GET['mark_notifications_read'] == '1' && isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    mysqli_query($link, "UPDATE notifications SET is_read = 1 WHERE user_id = $uid");
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit();
}

$notifications = [];
$unreadNotifications = 0;
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $notifCountQuery = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $uid AND is_read = 0";
    $notifCountResult = mysqli_query($link, $notifCountQuery);
    if ($notifCountResult) {
      $notifCountRow = mysqli_fetch_assoc($notifCountResult);
      $unreadNotifications = (int) ($notifCountRow['total'] ?? 0);
    }
    
    $notifQuery = "SELECT id, title, message, is_read, created_at, type FROM notifications WHERE user_id = $uid ORDER BY created_at DESC LIMIT 8";
    $notifResult = mysqli_query($link, $notifQuery);
    if ($notifResult) {
      while ($notif = mysqli_fetch_assoc($notifResult)) {
        $notifications[] = $notif;
      }
    }
}


// Get user data for header
$ticketCount = 0;
$userProfileImage = 'default.jpg';
if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];

    $ticketStmt = $link->prepare("SELECT COUNT(*) AS total FROM bookings WHERE user_id = ?");
    $ticketStmt->bind_param("i", $userId);
    $ticketStmt->execute();
    $ticketRow = $ticketStmt->get_result()->fetch_assoc();
    $ticketCount = (int) ($ticketRow['total'] ?? 0);
    $ticketStmt->close();

    $imgStmt = $link->prepare("SELECT profile_image FROM users WHERE id = ?");
    $imgStmt->bind_param("i", $userId);
    $imgStmt->execute();
    $imgRow = $imgStmt->get_result()->fetch_assoc();
    $userProfileImage = $imgRow['profile_image'] ?? 'default.jpg';
    $imgStmt->close();
}

require_once 'models/Match.php';

function teamCode(?string $shortName, string $teamName): string
{
    if (!empty($shortName)) {
        return strtoupper($shortName);
    }

    $parts = preg_split('/\s+/', trim($teamName)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= strtoupper(substr($part, 0, 1));
        }
    }

    return $letters !== '' ? substr($letters, 0, 3) : strtoupper(substr($teamName, 0, 3));
}

function displayTeamName(string $teamName): string
{
    $teamName = trim($teamName);
    return strlen($teamName) <= 4 ? strtoupper($teamName) : ucwords($teamName);
}

function statusMeta(string $status): array
{
    switch ($status) {
        case 'live':
            return ['label' => 'Live Now', 'class' => 'border-red-400/40 bg-red-500/10 text-red-200'];
        case 'completed':
            return ['label' => 'Completed', 'class' => 'border-slate-400/40 bg-slate-500/10 text-slate-200'];
        case 'cancelled':
            return ['label' => 'Cancelled', 'class' => 'border-amber-300/40 bg-amber-500/10 text-amber-100'];
        default:
            return ['label' => 'Upcoming', 'class' => 'border-emerald-300/40 bg-emerald-500/10 text-emerald-100'];
    }
}

function availabilityLabel(int $availableSeats): string
{
    if ($availableSeats <= 0) {
        return 'Sold out';
    }
    if ($availableSeats < 25) {
        return 'Selling fast';
    }
    if ($availableSeats < 75) {
        return 'Limited seats';
    }
    return 'Open for booking';
}

$matchModel = new MatchModel($link);

try {
    $matches = $matchModel->getAllMatches();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$buckets = ['live' => [], 'upcoming' => [], 'completed' => [], 'cancelled' => []];
foreach ($matches as $match) {
    $key = isset($buckets[$match['status']]) ? $match['status'] : 'upcoming';
    $buckets[$key][] = $match;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Matches - Cricket Ticket Booking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --line: rgba(255,255,255,0.1); }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(244, 201, 93, 0.12), transparent 28%),
                radial-gradient(circle at right top, rgba(55, 211, 159, 0.12), transparent 32%),
                linear-gradient(180deg, #07111f 0%, #081728 45%, #050d18 100%);
            color: #f8fafc;
        }

        h1, h2, h3, .display-font {
            font-family: 'Bebas Neue', sans-serif;
            letter-spacing: 0.04em;
        }

        .glass {
            background: linear-gradient(180deg, rgba(16, 31, 57, 0.8), rgba(8, 18, 35, 0.94));
            border: 1px solid var(--line);
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.26);
            backdrop-filter: blur(16px);
        }

        .team-mark {
            width: 3.8rem;
            height: 3.8rem;
            border-radius: 1.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-weight: 800;
            letter-spacing: 0.08em;
            overflow: hidden;
        }

        .team-mark:has(.team-logo) {
            background: none;
            border: none;
        }

        .team-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.24), rgba(244, 201, 93, 0.18));
            border-color: rgba(255, 214, 102, 0.4);
            color: #fff7ed;
        }

        .card-rise {
            transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .card-rise:hover {
            transform: translateY(-6px);
            border-color: rgba(255, 255, 255, 0.18);
            box-shadow: 0 26px 50px rgba(2, 6, 23, 0.34);
        }

        .track { background: rgba(148, 163, 184, 0.16); }
        .fill { background: linear-gradient(90deg, #37d39f, #f4c95d, #ff6b35); }

        .top-filter-wrap {
            backdrop-filter: blur(14px);
            background: rgba(15, 23, 42, 0.85);
        }

        .top-pill {
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.12);
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
            transition: all 0.25s ease;
        }

        .top-pill:hover {
            background: rgba(255,255,255,0.18);
            color: #ffffff;
            border-color: rgba(255,255,255,0.35);
        }

        .top-pill.active {
            background: linear-gradient(135deg, rgba(255,107,53,0.4), rgba(244,201,93,0.35));
            border-color: rgba(255,214,102,0.6);
            color: #ffffff;
        }

        .top-select-wrap {
            position: relative;
        }

        .top-select {
            min-width: 150px;
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.15);
            color: #e2e8f0;
            font-size: 13px;
            outline: none;
        }

        .top-select:focus {
            border-color: rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.18);
        }

        .top-select option {
            background: #1e293b;
            color: #e2e8f0;
        }

        .filter-btn-main {
            padding: 10px 22px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            background: linear-gradient(to right, #ff6b35, #f4c95d, #37d39f);
            color: #04121f;
            transition: 0.25s;
        }

        .filter-btn-main:hover {
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .top-select {
                min-width: 100%;
                width: 100%;
            }
        }
    
    /* Notification Bell */
    .nav-action-wrap { position: relative; }
    .notification-btn {
      position: relative; display: flex; align-items: center; justify-content: center;
      width: 44px; height: 44px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.10);
      background: rgba(255, 255, 255, 0.05); color: #fff; transition: 0.25s ease; cursor: pointer;
    }
    .notification-btn:hover { background: rgba(255, 255, 255, 0.10); transform: translateY(-1px); }
    .notification-btn i { font-size: 18px; }
    .notification-badge-dot {
      position: absolute; top: 6px; right: 6px; min-width: 18px; height: 18px; padding: 0 5px;
      border-radius: 999px; background: #ef4444; color: white; font-size: 10px; font-weight: 800;
      display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
    }
    .notification-dropdown {
      position: absolute; top: calc(100% + 12px); right: 0; width: 360px;
      background: linear-gradient(180deg, rgba(16, 31, 57, 0.96), rgba(8, 18, 35, 0.98));
      border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 18px; backdrop-filter: blur(20px);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35); overflow: hidden; opacity: 0; visibility: hidden;
      transform: translateY(8px); transition: all 0.22s ease; z-index: 80;
    }
    .notification-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
    .notification-header {
      padding: 16px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      display: flex; justify-content: space-between; align-items: center;
    }
    .notification-header h4 { font-size: 15px; font-weight: 700; color: #fff; margin: 0; font-family: 'Inter', sans-serif; }
    .notification-header a { font-size: 12px; font-weight: 600; color: #3b82f6; text-decoration: none; transition: color 0.2s; }
    .notification-header a:hover { color: #60a5fa; }
    .notification-list { max-height: 400px; overflow-y: auto; }
    .notification-list::-webkit-scrollbar { width: 6px; }
    .notification-list::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 10px; }
    .notification-item { padding: 16px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); transition: background 0.2s; }
    .notification-item:last-child { border-bottom: none; }
    .notification-item:hover { background: rgba(255, 255, 255, 0.03); }
    .notification-item.unread { background: rgba(59, 130, 246, 0.06); }
    .notification-title { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 6px; }
    .notification-message { font-size: 13px; color: #cbd5e1; line-height: 1.5; margin-bottom: 8px; }
    .notification-time { font-size: 11px; font-weight: 600; color: #64748b; }
    .notification-empty { padding: 40px 20px; text-align: center; color: #94a3b8; font-size: 14px; font-weight: 500; }
    @media(max-width: 768px) {
      .notification-dropdown { position: fixed; top: 70px; right: 16px; left: 16px; width: auto; }
    }

</style>
</head>
<body>

<header class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/75 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="index.php" class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 via-amber-400 to-emerald-400 text-slate-950">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div>
                <p class="display-font text-3xl leading-none text-white">CRICKET TICKET BOOKING</p>
                <p class="text-xs uppercase tracking-[0.32em] text-slate-300">Book the Game. Feel the Stadium.</p>
            </div>
        </a>

        <nav class="hidden items-center gap-7 text-sm font-medium text-slate-200 lg:flex">
            <a href="index.php#top" class="hover:text-white">Home</a>
            <a href="index.php#matches" class="hover:text-white">Matches</a>
            <a href="index.php#feedback" class="hover:text-white">Feedback</a>
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="nav-action-wrap">
            <button id="notificationBtn" class="notification-btn" type="button" title="Notifications">
              <i class="fas fa-bell"></i>
              <?php if (isset($unreadNotifications) && $unreadNotifications > 0): ?>
                <span class="notification-badge-dot"><?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?></span>
              <?php endif; ?>
            </button>
            <div id="notificationDropdown" class="notification-dropdown">
              <div class="notification-header">
                <h4>Notifications</h4>
                <?php if (isset($unreadNotifications) && $unreadNotifications > 0): ?>
                  <a href="?mark_notifications_read=1">Mark all read</a>
                <?php endif; ?>
              </div>
              <div class="notification-list">
                <?php if (empty($notifications)): ?>
                  <div class="notification-empty">No notifications yet.</div>
                <?php else: ?>
                  <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                      <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                      <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                      <div class="notification-time"><?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="relative">
                    <button id="profileDropdownBtn" class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white transition-all hover:bg-white/10 overflow-hidden ring-offset-2 ring-offset-slate-950 focus:ring-2 ring-amber-400/50">
                        <?php if (!empty($userProfileImage) && $userProfileImage !== 'default.jpg'): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-2xl"></i>
                        <?php endif; ?>
                    </button>

                    <div id="profileDropdown" class="absolute right-0 mt-3 w-56 glass origin-top-right rounded-2xl p-2 opacity-0 invisible transition-all duration-200 z-50 translate-y-2">
                        <div class="px-3 py-2 border-b border-white/10 mb-2">
                            <p class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                            <p class="text-[10px] uppercase tracking-widest text-slate-400 mt-0.5"><?php echo $ticketCount; ?> Tickets Booked</p>
                        </div>
                        <a href="profile.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                            <i class="fas fa-user-circle text-xs w-4"></i> Profile
                        </a>
                        <a href="MyBooking.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                            <i class="fas fa-ticket-alt text-xs w-4"></i> My Bookings
                        </a>
                        <div class="my-1 border-t border-white/5"></div>
                        <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                            <i class="fas fa-sign-out-alt text-xs w-4"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/5">Login</a>
                <a href="register.php" class="rounded-full bg-gradient-to-r from-orange-500 via-amber-400 to-emerald-400 px-5 py-2 text-sm font-bold text-slate-950">Create Account</a>
            <?php endif; ?>
        </div>

        <button id="menuToggle" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white lg:hidden" type="button">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div id="mobileMenu" class="hidden border-t border-white/10 bg-slate-950/95 px-4 py-4 lg:hidden">
        <div class="flex flex-col gap-3 text-sm font-medium text-slate-200">
            <a href="index.php#top" class="rounded-xl px-3 py-2 hover:bg-white/5">Home</a>
            <a href="index.php#matches" class="rounded-xl px-3 py-2 hover:bg-white/5">Matches</a>
            <a href="index.php#feedback" class="rounded-xl px-3 py-2 hover:bg-white/5">Feedback</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="MyBooking.php" class="rounded-xl px-3 py-2 hover:bg-white/5">My Tickets</a>
                <a href="profile.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Profile</a>
                <a href="logout.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Logout</a>
            <?php else: ?>
                <a href="login.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Login</a>
                <a href="register.php" class="rounded-xl px-3 py-2 hover:bg-white/5">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<br>
<!-- added the h1 and paragraph -->
<h1 class="text-4xl font-bold text-white mb-4" style=" margin-bottom: 10px; margin-top: 10px; color: #fff;">ALL Matches</h1>
<p class="text-slate-300 mb-6" style=" margin-bottom: 20px; margin-top: 10px; font-size: 18px; color: #fff;">Find and book tickets for your favorite cricket matches</p>
   
<!-- SEARCH BAR -->
    <div class="mb-6">
        <div class="relative">
            <input id="searchMatches" type="text" placeholder="Search matches, teams, venues..."
                class="w-full rounded-2xl border border-white/20 bg-slate-800/60 px-6 py-4 text-white placeholder-slate-300 focus:border-white/30 focus:outline-none focus:ring-2 focus:ring-white/15">
            <i class="fas fa-search absolute right-6 top-1/2 -translate-y-1/2 text-slate-400"></i>
        </div>
    </div>

    <!-- TOP FILTER BAR -->
    <div class="mb-10">
        <div class="flex  px-4 py-4 ">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="filter-btn top-pill active" data-filter="all">All Matches</button>
                <button type="button" class="filter-btn top-pill" data-filter="live">Live</button>
                <button type="button" class="filter-btn top-pill" data-filter="upcoming">Upcoming</button>
                <button type="button" class="filter-btn top-pill" data-filter="completed">Completed</button>
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <div class="top-select-wrap">
                    <select id="matchTypeFilter" class="top-select">
                        <option value="">Filter By</option>
                        <option value="IPL">IPL</option>
                        <option value="T20">T20</option>
                        <option value="ODI">ODI</option>
                        <option value="Test">Test</option>
                    </select>
                </div>

                <div class="top-select-wrap">
                    <select id="priceFilter" class="top-select">
                        <option value="">All Prices</option>
                        <option value="0-1000">Under ₹1000</option>
                        <option value="1000-5000">₹1000 - ₹5000</option>
                        <option value="5000-10000">₹5000 - ₹10000</option>
                        <option value="10000-999999">Above ₹10000</option>
                    </select>
                </div>

                <button id="applyFilters" class="filter-btn-main">
                    FILTER →
                </button>
            </div>
        </div>
    </div>

    <div id="matchesGrid" class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <?php if (empty($matches)): ?>
            <div class="glass col-span-full rounded-[2rem] p-10 text-center">
                <i class="fas fa-calendar-times text-5xl text-slate-500"></i>
                <h3 class="mt-4 text-3xl font-bold text-white">No matches available</h3>
                <p class="mt-2 text-slate-300">Add fixtures in your database and they will appear here automatically.</p>
            </div>
        <?php else: ?>
            <?php foreach ($matches as $match): ?>
                <?php
                $meta = statusMeta($match['status']);
                $matchTime = strtotime($match['match_date'] . ' ' . $match['match_time']);
                $availableSeats = (int) $match['available_seats'];
                $totalSeats = max((int) $match['total_seats'], 1);
                $bookedSeats = (int) $match['booked_seats'];
                $occupancy = $totalSeats > 0 ? min(100, max(0, (int) round(($bookedSeats / $totalSeats) * 100))) : 0;

                $searchText = strtolower(
                    ($match['team1_name'] ?? '') . ' ' .
                    ($match['team2_name'] ?? '') . ' ' .
                    ($match['stadium_name'] ?? '') . ' ' .
                    ($match['stadium_city'] ?? '') . ' ' .
                    ($match['match_type'] ?? '') . ' ' .
                    ($match['description'] ?? '')
                );
                ?>
                <article
                    class="glass card-rise rounded-[2rem] border border-white/8 p-6"
                    data-status="<?php echo htmlspecialchars($match['status']); ?>"
                    data-match-type="<?php echo htmlspecialchars($match['match_type']); ?>"
                    data-min-price="<?php echo (float) ($match['min_price'] ?? 0); ?>"
                    data-search="<?php echo htmlspecialchars($searchText); ?>"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] <?php echo $meta['class']; ?>">
                                <?php echo htmlspecialchars($meta['label']); ?>
                            </span>
                            <p class="mt-3 text-sm font-semibold uppercase tracking-[0.24em] text-amber-200">
                                <?php echo htmlspecialchars($match['match_type']); ?>
                            </p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-right">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">From</p>
                            <p class="mt-1 text-xl font-black text-emerald-300">
                                &#8377;<?php echo number_format((float) ($match['min_price'] ?? 0), 2); ?>
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between gap-4">
                        <div class="text-center">
                            <div class="team-mark"><?php if (!empty($match['team1_logo'])): ?><img src="<?php echo htmlspecialchars($match['team1_logo']); ?>" alt="<?php echo htmlspecialchars($match['team1_name']); ?>" class="team-logo"><?php else: echo htmlspecialchars(teamCode($match['team1_short'], $match['team1_name'])); endif; ?></div>
                            <p class="mt-3 text-lg font-bold text-white"><?php echo htmlspecialchars(displayTeamName($match['team1_name'])); ?></p>
                        </div>
                        <div class="text-center">
                            <p class="display-font text-4xl text-white">VS</p>
                        </div>
                        <div class="text-center">
                            <div class="team-mark"><?php if (!empty($match['team2_logo'])): ?><img src="<?php echo htmlspecialchars($match['team2_logo']); ?>" alt="<?php echo htmlspecialchars($match['team2_name']); ?>" class="team-logo"><?php else: echo htmlspecialchars(teamCode($match['team2_short'], $match['team2_name'])); endif; ?></div>
                            <p class="mt-3 text-lg font-bold text-white"><?php echo htmlspecialchars(displayTeamName($match['team2_name'])); ?></p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3 text-sm text-slate-300">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-calendar-alt w-5 text-amber-200"></i>
                            <span><?php echo date('d M Y', $matchTime); ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-clock w-5 text-emerald-300"></i>
                            <span><?php echo date('h:i A', $matchTime); ?> IST</span>
                        </div>
                        <div class="flex items-start gap-4">
                            <i class="fas fa-map-marker-alt w-5 text-orange-300 mt-1.5"></i>
                            <div class="flex flex-col">
                                <span class="text-lg font-bold text-white tracking-wide">
                                    <?php echo htmlspecialchars(ucwords($match['stadium_name'])); ?>
                                </span>
                                <span class="text-[0.8rem] leading-relaxed text-slate-400 mt-1.5">
                                    <?php 
                                        $matchLocParts = [];
                                        if (!empty($match['stadium_address'])) $matchLocParts[] = ucwords($match['stadium_address']);
                                        if (!empty($match['stadium_city'])) $matchLocParts[] = ucwords($match['stadium_city']);
                                        if (!empty($match['stadium_state'])) $matchLocParts[] = ucwords($match['stadium_state']);
                                        if (!empty($match['stadium_country'])) $matchLocParts[] = ucwords($match['stadium_country']);
                                        echo htmlspecialchars(implode(', ', $matchLocParts));
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-semibold text-white"><?php //echo htmlspecialchars(availabilityLabel($availableSeats)); ?></span>
                            <span class="text-slate-300"><?php //echo $availableSeats; ?> / <?php //echo $totalSeats; ?> seats left</span>
                        </div>
                        <div class="track mt-3 h-2 rounded-full">
                            <div class="fill h-2 rounded-full" style="width: <?php //echo $occupancy; ?>%;"></div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
                            <span><?php //echo $bookedSeats; ?> booked</span>
                            <span><?php //echo $occupancy; ?>% occupied</span>
                        </div>
                    </div> -->

                    <!-- <p class="mt-5 min-h-[3.5rem] text-sm leading-7 text-slate-300">
                        <?php //echo htmlspecialchars($match['description'] ?: 'Catch the atmosphere live from the stands and lock in the best seats before they are gone.'); ?>
                    </p> -->

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Range</p>
                            <p class="mt-1 text-sm font-semibold text-white">
                                &#8377;<?php echo number_format((float) ($match['min_price'] ?? 0), 0); ?>
                                -
                                &#8377;<?php echo number_format((float) ($match['max_price'] ?? $match['min_price'] ?? 0), 0); ?>
                            </p>
                        </div>

                        <a href="booking.php?match_id=<?php echo $match['id']; ?>" class="rounded-full bg-white px-5 py-3 text-sm font-bold uppercase tracking-[0.18em] text-slate-950 hover:bg-amber-200">
                            Book Now
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="noResultsMessage" class="hidden mt-8">
        <div class="glass rounded-[2rem] p-10 text-center">
            <i class="fas fa-search text-5xl text-slate-500"></i>
            <h3 class="mt-4 text-3xl font-bold text-white">No matches found</h3>
            <p class="mt-2 text-slate-300">Try changing your search or filters.</p>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const matchCards = document.querySelectorAll('article[data-status]');
    const matchTypeFilter = document.getElementById('matchTypeFilter');
    const priceFilter = document.getElementById('priceFilter');
    const applyFiltersBtn = document.getElementById('applyFilters');
    const searchInput = document.getElementById('searchMatches');
    const noResultsMessage = document.getElementById('noResultsMessage');

    const matchData = [];
    matchCards.forEach(card => {
        const status = card.dataset.status;
        const matchType = card.dataset.matchType || '';
        const minPrice = parseFloat(card.dataset.minPrice) || 0;
        const search = (card.dataset.search || '').toLowerCase();
        matchData.push({ card, status, matchType, minPrice, search });
    });

    function applyAllFilters() {
        const statusFilter = document.querySelector('.filter-btn.active')?.dataset.filter || 'all';
        const selectedMatchType = matchTypeFilter?.value || '';
        const selectedPriceRange = priceFilter?.value || '';
        const searchTerm = (searchInput?.value || '').trim().toLowerCase();

        let visibleCount = 0;

        matchData.forEach(({ card, status, matchType, minPrice, search }) => {
            let showCard = true;

            if (statusFilter !== 'all' && status !== statusFilter) {
                showCard = false;
            }

            if (showCard && selectedMatchType && matchType !== selectedMatchType) {
                showCard = false;
            }

            if (showCard && selectedPriceRange) {
                const [min, max] = selectedPriceRange.split('-').map(p => parseFloat(p));
                if (minPrice < min || minPrice > max) {
                    showCard = false;
                }
            }

            if (showCard && searchTerm && !search.includes(searchTerm)) {
                showCard = false;
            }

            card.style.display = showCard ? 'block' : 'none';

            if (showCard) {
                visibleCount++;
            }
        });

        if (noResultsMessage) {
            noResultsMessage.classList.toggle('hidden', visibleCount !== 0);
        }
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyAllFilters();
        });
    });

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', applyAllFilters);
    }

    if (matchTypeFilter) {
        matchTypeFilter.addEventListener('change', applyAllFilters);
    }

    if (priceFilter) {
        priceFilter.addEventListener('change', applyAllFilters);
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyAllFilters);
    }

    const profileDropdownBtn = document.getElementById('profileDropdownBtn');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileDropdownBtn && profileDropdown) {
        profileDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('opacity-0');
            profileDropdown.classList.toggle('invisible');
            profileDropdown.classList.toggle('translate-y-2');
        });
    }

    document.addEventListener('click', function(e) {
        if (
            profileDropdown &&
            profileDropdownBtn &&
            !profileDropdown.contains(e.target) &&
            !profileDropdownBtn.contains(e.target)
        ) {
            profileDropdown.classList.add('opacity-0', 'invisible', 'translate-y-2');
        }
    });

    const mobileMenuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');

    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
});
</script>

</body>
</html>