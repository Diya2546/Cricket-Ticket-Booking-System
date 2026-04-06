<?php
session_start();
include '../connection.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$rating_filter   = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from       = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to         = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];
$params = [];
$types = '';

if ($category_filter > 0) {
    $where_conditions[] = "f.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if ($rating_filter !== 'all') {
    $where_conditions[] = "f.rating = ?";
    $params[] = (int)$rating_filter;
    $types .= 'i';
}

if ($search !== '') {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR f.message LIKE ? OR fc.category_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if ($date_from !== '') {
    $where_conditions[] = "DATE(f.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to !== '') {
    $where_conditions[] = "DATE(f.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$export_query = "
    SELECT
        u.name AS user_name,
        u.email AS user_email,
        fc.category_name,
        f.rating,
        f.message,
        t1.name AS team1_name,
        t2.name AS team2_name,
        m.match_date,
        m.match_type,
        f.created_at
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    JOIN feedback_categories fc ON f.category_id = fc.id
    JOIN matches m ON f.match_id = m.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    $where_clause
    ORDER BY f.created_at DESC
";

$stmt = mysqli_prepare($link, $export_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="feedback_export_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'User Name',
    'User Email',
    'Category',
    'Rating',
    'Feedback Message',
    'Match',
    'Match Date',
    'Match Type',
    'Submitted At'
]);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['user_name'],
        $row['user_email'],
        $row['category_name'],
        $row['rating'],
        $row['message'],
        $row['team1_name'] . ' vs ' . $row['team2_name'],
        date('Y-m-d', strtotime($row['match_date'])),
        $row['match_type'],
        date('Y-m-d H:i:s', strtotime($row['created_at']))
    ]);
}

fclose($output);
exit();
?>