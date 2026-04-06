<?php
require_once __DIR__ . '/../connection.php';

if (!class_exists('Booking')) {
    class Booking {
        private $link;

        public function __construct($db_link = null) {
            global $link;

            if ($db_link) {
                $this->link = $db_link;
            } elseif (isset($link)) {
                $this->link = $link;
            } else {
                throw new Exception("Database connection not found.");
            }
        }

        public function createBooking($user_id, $match_id, $category_id, $selected_seats, $payment_method = 'upi') {
            if (empty($selected_seats) || !is_array($selected_seats)) {
                throw new Exception("No seats selected.");
            }

            $user_id = (int)$user_id;
            $match_id = (int)$match_id;
            $category_id = (int)$category_id;
            $quantity = count($selected_seats);

            mysqli_begin_transaction($this->link);

            try {
                $query = "
                    SELECT 
                        m.id AS match_id,
                        m.venue_id,
                        vc.id AS venue_category_id,
                        vc.no_of_seats,
                        vc.price,
                        sc.name AS category_name
                    FROM matches m
                    INNER JOIN venue_category vc ON vc.venue_id = m.venue_id
                    INNER JOIN seat_categories sc ON sc.id = vc.category_id
                    WHERE m.id = $match_id AND vc.category_id = $category_id
                    LIMIT 1
                ";

                $result = mysqli_query($this->link, $query);
                if (!$result || mysqli_num_rows($result) === 0) {
                    throw new Exception("Selected category not found for this venue.");
                }

                $categoryData = mysqli_fetch_assoc($result);

                if ((int)$categoryData['no_of_seats'] < $quantity) {
                    throw new Exception("Not enough seats available in this category.");
                }

                $alreadyBookedSeats = [];
                $seatCheckQuery = "
                    SELECT bi.seats_no
                    FROM booking_items bi
                    INNER JOIN bookings b ON b.id = bi.booking_id
                    WHERE b.match_id = $match_id
                      AND bi.category_id = $category_id
                      AND b.booking_status = 'confirmed'
                      AND b.payment_status = 'success'
                ";

                $seatCheckResult = mysqli_query($this->link, $seatCheckQuery);
                if ($seatCheckResult) {
                    while ($row = mysqli_fetch_assoc($seatCheckResult)) {
                        if (!empty($row['seats_no'])) {
                            $dbSeats = explode(',', $row['seats_no']);
                            foreach ($dbSeats as $seat) {
                                $seat = trim($seat);
                                if ($seat !== '') {
                                    $alreadyBookedSeats[] = $seat;
                                }
                            }
                        }
                    }
                }

                $alreadyBookedSeats = array_unique($alreadyBookedSeats);
                $conflictSeats = array_intersect($selected_seats, $alreadyBookedSeats);

                if (!empty($conflictSeats)) {
                    throw new Exception("These seats are already booked in this category: " . implode(', ', $conflictSeats));
                }

                $unit_price = (float)$categoryData['price'];
                $total_amount = $unit_price * $quantity;
                $booking_code = 'CT' . date('YmdHis') . rand(100, 999);

                $deadlineQuery = "
                    SELECT CONCAT(match_date, ' ', match_time) AS match_datetime
                    FROM matches
                    WHERE id = $match_id
                    LIMIT 1
                ";
                $deadlineResult = mysqli_query($this->link, $deadlineQuery);
                $deadlineRow = mysqli_fetch_assoc($deadlineResult);

                $cancellation_deadline = null;
                if ($deadlineRow && !empty($deadlineRow['match_datetime'])) {
                    $matchDateTime = new DateTime($deadlineRow['match_datetime']);
                    $matchDateTime->modify('-24 hours');
                    $cancellation_deadline = $matchDateTime->format('Y-m-d H:i:s');
                }

                $insertBooking = "
                    INSERT INTO bookings (
                        booking_id, user_id, match_id, total_tickets, total_amount,
                        booking_status, payment_status, cancellation_deadline
                    ) VALUES (
                        '$booking_code', $user_id, $match_id, $quantity, $total_amount,
                        'confirmed', 'success', " . ($cancellation_deadline ? "'$cancellation_deadline'" : "NULL") . "
                    )
                ";

                if (!mysqli_query($this->link, $insertBooking)) {
                    throw new Exception("Booking insert failed: " . mysqli_error($this->link));
                }

                $booking_db_id = mysqli_insert_id($this->link);

                $seat_string = mysqli_real_escape_string($this->link, implode(',', $selected_seats));

                $insertItem = "
                    INSERT INTO booking_items (
                        booking_id, category_id, quantity, seats_no, unit_price, total_price
                    ) VALUES (
                        $booking_db_id, $category_id, $quantity, '$seat_string', $unit_price, $total_amount
                    )
                ";

                if (!mysqli_query($this->link, $insertItem)) {
                    throw new Exception("Booking items insert failed: " . mysqli_error($this->link));
                }

                $updateSeats = "
                    UPDATE venue_category
                    SET no_of_seats = no_of_seats - $quantity
                    WHERE venue_id = {$categoryData['venue_id']}
                      AND category_id = $category_id
                      AND no_of_seats >= $quantity
                ";

                if (!mysqli_query($this->link, $updateSeats) || mysqli_affected_rows($this->link) === 0) {
                    throw new Exception("Seat update failed or insufficient seats.");
                }

                $payment_method = mysqli_real_escape_string($this->link, $payment_method);

                $insertPayment = "
                    INSERT INTO payments (
                        booking_id, payment_method, amount, payment_status
                    ) VALUES (
                        $booking_db_id, '$payment_method', $total_amount, 'success'
                    )
                ";

                if (!mysqli_query($this->link, $insertPayment)) {
                    throw new Exception("Payment record insert failed: " . mysqli_error($this->link));
                }

                mysqli_commit($this->link);
                return $booking_code;

            } catch (Exception $e) {
                mysqli_rollback($this->link);
                throw $e;
            }
        }

        public function getBookingByCode($booking_code) {
            $booking_code = mysqli_real_escape_string($this->link, $booking_code);

            $query = "
                SELECT 
                    b.*,
                    m.match_date,
                    m.match_time,
                    m.match_type,
                    t1.name AS team1_name,
                    t2.name AS team2_name,
                    v.name AS venue_name,
                    v.city AS venue_city
                FROM bookings b
                INNER JOIN matches m ON b.match_id = m.id
                INNER JOIN teams t1 ON m.team1_id = t1.id
                INNER JOIN teams t2 ON m.team2_id = t2.id
                INNER JOIN venues v ON m.venue_id = v.id
                WHERE b.booking_id = '$booking_code'
                LIMIT 1
            ";

            $result = mysqli_query($this->link, $query);
            return $result ? mysqli_fetch_assoc($result) : null;
        }

        public function getBookingItems($booking_db_id) {
            $booking_db_id = (int)$booking_db_id;

            $query = "
                SELECT 
                    bi.*,
                    sc.name AS category_name
                FROM booking_items bi
                INNER JOIN seat_categories sc ON bi.category_id = sc.id
                WHERE bi.booking_id = $booking_db_id
            ";

            $result = mysqli_query($this->link, $query);

            $items = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $items[] = $row;
                }
            }

            return $items;
        }
    }
}
?>