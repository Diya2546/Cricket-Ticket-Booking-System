<?php
class MatchModel
{
    private $link;
    private $table_name = "matches";

    public function __construct($db_connection)
    {
        $this->link = $db_connection;
    }

    public function getAllMatches()
    {
        $query = "SELECT 
                    m.*,
                    t1.name AS team1_name,
                    t1.short_name AS team1_short,
                    t1.logo AS team1_logo,
                    t2.name AS team2_name,
                    t2.short_name AS team2_short,
                    t2.logo AS team2_logo,
                    v.name AS stadium_name,
                    v.city AS stadium_city,
                    v.state AS stadium_state,
                    v.country AS stadium_country,
                    v.address AS stadium_address,
                    v.capacity AS stadium_capacity,
                    MIN(vc.price) AS min_price,
                    MAX(vc.price) AS max_price,
                    COALESCE(SUM(vc.no_of_seats), 0) AS total_seats,
                    COALESCE(SUM(COALESCE(bs.booked_qty, 0)), 0) AS booked_seats,
                    COALESCE(SUM(vc.no_of_seats - COALESCE(bs.booked_qty, 0)), 0) AS available_seats
                  FROM " . $this->table_name . " m
                  JOIN teams t1 ON m.team1_id = t1.id
                  JOIN teams t2 ON m.team2_id = t2.id
                  JOIN venues v ON m.venue_id = v.id
                  LEFT JOIN venue_category vc ON m.venue_id = vc.venue_id
                  LEFT JOIN (
                      SELECT
                          b.match_id,
                          bi.category_id,
                          SUM(bi.quantity) AS booked_qty
                      FROM bookings b
                      INNER JOIN booking_items bi ON b.id = bi.booking_id
                      WHERE b.booking_status = 'confirmed'
                        AND b.payment_status = 'success'
                      GROUP BY b.match_id, bi.category_id
                  ) bs ON bs.match_id = m.id AND bs.category_id = vc.category_id
                  WHERE m.status IN ('upcoming', 'live', 'completed')
                  GROUP BY m.id
                  ORDER BY 
                      CASE 
                          WHEN m.status = 'live' THEN 1
                          WHEN m.status = 'upcoming' THEN 2
                          WHEN m.status = 'completed' THEN 3
                          ELSE 4
                      END,
                      m.match_date ASC,
                      m.match_time ASC";

        $result = mysqli_query($this->link, $query);

        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($this->link));
        }

        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function getLimitedMatches($limit = 3)
    {
        $limit = (int) $limit;

        $query = "SELECT 
                    m.*,
                    t1.name AS team1_name,
                    t1.short_name AS team1_short,
                    t1.logo AS team1_logo,
                    t2.name AS team2_name,
                    t2.short_name AS team2_short,
                    t2.logo AS team2_logo,
                    v.name AS stadium_name,
                    v.city AS stadium_city,
                    v.state AS stadium_state,
                    v.country AS stadium_country,
                    v.address AS stadium_address,
                    v.capacity AS stadium_capacity,
                    MIN(vc.price) AS min_price,
                    MAX(vc.price) AS max_price,
                    COALESCE(SUM(vc.no_of_seats), 0) AS total_seats,
                    COALESCE(SUM(COALESCE(bs.booked_qty, 0)), 0) AS booked_seats,
                    COALESCE(SUM(vc.no_of_seats - COALESCE(bs.booked_qty, 0)), 0) AS available_seats
                  FROM " . $this->table_name . " m
                  JOIN teams t1 ON m.team1_id = t1.id
                  JOIN teams t2 ON m.team2_id = t2.id
                  JOIN venues v ON m.venue_id = v.id
                  LEFT JOIN venue_category vc ON m.venue_id = vc.venue_id
                  LEFT JOIN (
                      SELECT
                          b.match_id,
                          bi.category_id,
                          SUM(bi.quantity) AS booked_qty
                      FROM bookings b
                      INNER JOIN booking_items bi ON b.id = bi.booking_id
                      WHERE b.booking_status = 'confirmed'
                        AND b.payment_status = 'success'
                      GROUP BY b.match_id, bi.category_id
                  ) bs ON bs.match_id = m.id AND bs.category_id = vc.category_id
                  WHERE m.status IN ('upcoming', 'live', 'completed')
                  GROUP BY m.id
                  ORDER BY 
                      CASE 
                          WHEN m.status = 'live' THEN 1
                          WHEN m.status = 'upcoming' THEN 2
                          WHEN m.status = 'completed' THEN 3
                          ELSE 4
                      END,
                      m.match_date ASC,
                      m.match_time ASC
                  LIMIT $limit";

        $result = mysqli_query($this->link, $query);

        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($this->link));
        }

        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function getMatchById($id)
    {
        $query = "SELECT 
                    m.*,
                    t1.name AS team1_name,
                    t1.short_name AS team1_short,
                    t1.logo AS team1_logo,
                    t2.name AS team2_name,
                    t2.short_name AS team2_short,
                    t2.logo AS team2_logo,
                    v.name AS stadium_name,
                    v.city AS stadium_city,
                    v.state AS stadium_state,
                    v.country AS stadium_country,
                    v.capacity AS stadium_capacity,
                    v.address AS stadium_address
                  FROM " . $this->table_name . " m
                  JOIN teams t1 ON m.team1_id = t1.id
                  JOIN teams t2 ON m.team2_id = t2.id
                  JOIN venues v ON m.venue_id = v.id
                  WHERE m.id = ?";

        $stmt = mysqli_prepare($this->link, $query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function getSeatCategories($match_id)
    {
        $query = "SELECT 
                    vc.*,
                    sc.name AS category_name,
                    sc.description AS category_description
                  FROM matches m
                  JOIN venue_category vc ON m.venue_id = vc.venue_id
                  JOIN seat_categories sc ON vc.category_id = sc.id
                  WHERE m.id = ?
                  ORDER BY vc.price DESC";

        $stmt = mysqli_prepare($this->link, $query);
        mysqli_stmt_bind_param($stmt, "i", $match_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}
?>