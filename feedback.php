<?php
session_start();
include 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT name, email FROM users WHERE id = ?";
$stmt = mysqli_prepare($link, $user_query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = (int)$_POST['rating'];
    $message = trim($_POST['message']);
    $match_id = (int)$_POST['match_id'];

    // Validate input
    if ($rating >= 1 && $rating <= 5 && !empty($message) && $match_id > 0) {
        // Check if user has a booking for this match
        $booking_check = "SELECT b.id FROM bookings b 
                         WHERE b.user_id = ? AND b.match_id = ? 
                         AND b.booking_status = 'confirmed' 
                         LIMIT 1";
        $stmt = mysqli_prepare($link, $booking_check);
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $match_id);
        mysqli_stmt_execute($stmt);
        $booking_result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($booking_result) > 0) {
            $booking_id = mysqli_fetch_assoc($booking_result)['id'];

            // Check if feedback already exists
            $existing_check = "SELECT id FROM feedback WHERE user_id = ? AND match_id = ?";
            $stmt = mysqli_prepare($link, $existing_check);
            mysqli_stmt_bind_param($stmt, 'ii', $user_id, $match_id);
            mysqli_stmt_execute($stmt);
            $existing_result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($existing_result) === 0) {
                // Insert feedback
                $insert_query = "INSERT INTO feedback (user_id, match_id, booking_id, rating, message) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $insert_query);
                mysqli_stmt_bind_param($stmt, 'iiis', $user_id, $match_id, $booking_id, $rating, $message);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Thank you for your feedback!";
                }
                else {
                    $error_message = "Error submitting feedback. Please try again.";
                }
            }
            else {
                $error_message = "You have already submitted feedback for this match.";
            }
        }
        else {
            $error_message = "You can only submit feedback for matches you have booked.";
        }
    }
    else {
        $error_message = "Please fill all fields correctly.";
    }
}

// Get user's booked matches for feedback
$matches_query = "SELECT m.id, m.match_date, m.match_time, t1.name as team1_name, t2.name as team2_name, m.match_type
                  FROM matches m
                  JOIN bookings b ON m.id = b.match_id
                  JOIN teams t1 ON m.team1_id = t1.id
                  JOIN teams t2 ON m.team2_id = t2.id
                  WHERE b.user_id = ? AND b.booking_status = 'confirmed' AND m.status = 'completed'
                  AND m.id NOT IN (SELECT match_id FROM feedback WHERE user_id = ?)
                  ORDER BY m.match_date DESC";
$stmt = mysqli_prepare($link, $matches_query);
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
mysqli_stmt_execute($stmt);
$matches_result = mysqli_stmt_get_result($stmt);

// Get existing feedback
$feedback_query = "SELECT f.rating, f.message, f.created_at,
                  m.match_date, t1.name as team1_name, t2.name as team2_name, m.match_type
                  FROM feedback f
                  JOIN matches m ON f.match_id = m.id
                  JOIN teams t1 ON m.team1_id = t1.id
                  JOIN teams t2 ON m.team2_id = t2.id
                  WHERE f.user_id = ?
                  ORDER BY f.created_at DESC";
$stmt = mysqli_prepare($link, $feedback_query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$feedback_result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Cricket Ticket Booking</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/feedback.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .feedback-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .feedback-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .rating-selector {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .rating-selector label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 1.5rem;
        }
        
        .rating-selector input[type="radio"] {
            display: none;
        }
        
        .rating-selector .star {
            color: #ddd;
            transition: color 0.2s;
        }
        
        .rating-selector input[type="radio"]:checked ~ .star,
        .rating-selector label:hover .star {
            color: #ffc107;
        }
        
        .feedback-list {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .feedback-item {
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        
        .feedback-item:last-child {
            border-bottom: none;
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .match-info {
            font-weight: 600;
            color: #333;
        }
        
        .feedback-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .feedback-rating {
            color: #ffc107;
            margin: 0.5rem 0;
        }
        
        .feedback-message {
            color: #555;
            line-height: 1.5;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .no-matches {
            text-align: center;
            color: #666;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="feedback-container">
        <h1>Feedback</h1>
        <p>Share your experience about the matches you've attended.</p>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php
endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php
endif; ?>
        
        <?php if (mysqli_num_rows($matches_result) > 0): ?>
            <div class="feedback-form">
                <h2>Submit New Feedback</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="match_id">Select Match</label>
                        <select name="match_id" id="match_id" class="form-control" required>
                            <option value="">Choose a match you attended...</option>
                            <?php while ($match = mysqli_fetch_assoc($matches_result)): ?>
                                <option value="<?php echo $match['id']; ?>">
                                    <?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?>
                                    - <?php echo date('d M Y', strtotime($match['match_date'])); ?> (<?php echo htmlspecialchars($match['match_type']); ?>)
                                </option>
                            <?php
    endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="rating-selector">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" required>
                                    <span class="star">&#9733;</span>
                                </label>
                            <?php
    endfor; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Your Feedback</label>
                        <textarea name="message" id="message" class="form-control" placeholder="Share your experience..." required></textarea>
                    </div>
                    
                    <button type="submit" name="submit_feedback" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Feedback
                    </button>
                </form>
            </div>
        <?php
else: ?>
            <div class="no-matches">
                <i class="fas fa-comments" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem; display: block;"></i>
                <h3>No matches available for feedback</h3>
                <p>You can only submit feedback for completed matches that you have booked.</p>
                <a href="matches.php" class="btn btn-primary">Browse Matches</a>
            </div>
        <?php
endif; ?>
        
        <?php if (mysqli_num_rows($feedback_result) > 0): ?>
            <div class="feedback-list">
                <h2>Your Previous Feedback</h2>
                <?php while ($feedback = mysqli_fetch_assoc($feedback_result)): ?>
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <div class="match-info">
                                <?php echo htmlspecialchars($feedback['team1_name']); ?> vs <?php echo htmlspecialchars($feedback['team2_name']); ?>
                                - <?php echo date('d M Y', strtotime($feedback['match_date'])); ?> (<?php echo htmlspecialchars($feedback['match_type']); ?>)
                            </div>
                            <div class="feedback-date">
                                <?php echo date('d M Y, h:i A', strtotime($feedback['created_at'])); ?>
                            </div>
                        </div>
                        <div class="feedback-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star" style="color: <?php echo $i <= $feedback['rating'] ? '#ffc107' : '#ddd'; ?>;">&#9733;</span>
                            <?php
        endfor; ?>
                            (<?php echo (int)$feedback['rating']; ?>/5)
                        </div>
                        <div class="feedback-message">
                            <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                        </div>
                    </div>
                <?php
    endwhile; ?>
            </div>
        <?php
endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
