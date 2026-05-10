<?php
// ============================================================
//  Barangay Tugtug E-System — Get / Update Blotter Records
//  File: php/GetBlotter.php
//
//  Only queries/updates the `blotter` table.
//  blotter_details and blotter_reference_number are NOT in the
//  SQL schema and are not referenced here.
//
//  CONCAT_WS builds a full_name alias so existing JS
//  (rec.full_name) continues to work without changes.
//
//  Valid status enum values from SQL:
//    Pending | Scheduled | Ongoing | Resolved | Escalated | Dismissed
// ============================================================

header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
ini_set("display_errors", 0);
ini_set("log_errors", 1);
error_reporting(E_ALL);

define("DB_HOST",    "localhost");
define("DB_NAME",    "db_barangay_e-system");
define("DB_USER",    "root");
define("DB_PASS",    "");
define("DB_CHARSET", "utf8mb4");

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("DB Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit();
}

// ============================================================
//  GET — Fetch blotter records from the blotter table only
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $search    = isset($_GET["search"])    ? trim($_GET["search"])    : "";
    $filter    = isset($_GET["filter"])    ? trim($_GET["filter"])    : "";
    $date_from = isset($_GET["date_from"]) ? trim($_GET["date_from"]) : "";
    $date_to   = isset($_GET["date_to"])   ? trim($_GET["date_to"])   : "";

    // CONCAT_WS builds full_name from separate columns.
    // All schedule/resolution columns are gone (no blotter_details).
    // JS Blotter.js uses rec.full_name — still works via the alias.
    $sql = "
        SELECT
            b.blotter_id,
            b.reference_number,
            b.first_name,
            b.middle_name,
            b.last_name,
            b.suffix,
            TRIM(CONCAT_WS(' ',
                b.first_name,
                NULLIF(b.middle_name, ''),
                b.last_name,
                NULLIF(b.suffix, '')
            )) AS full_name,
            b.age,
            b.civil_status,
            b.address,
            b.occupation,
            b.petsa,
            b.oras,
            b.complaint_against,
            b.complaint_type,
            b.complaint_details,
            b.submitted_at,
            b.status,
            b.resolved_at
        FROM blotter b
        WHERE 1=1";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND (
            b.first_name       LIKE :search1
            OR b.last_name     LIKE :search2
            OR b.complaint_against LIKE :search3
            OR b.complaint_type    LIKE :search4
            OR b.reference_number  LIKE :search5
            OR b.status            LIKE :search6
        )";
        $like = "%" . $search . "%";
        $params[":search1"] = $like;
        $params[":search2"] = $like;
        $params[":search3"] = $like;
        $params[":search4"] = $like;
        $params[":search5"] = $like;
        $params[":search6"] = $like;
    }

    if (!empty($filter) && $filter !== "Total" && $filter !== "date") {
        $sql .= " AND b.status = :filter";
        $params[":filter"] = $filter;
    }

    if (!empty($date_from)) {
        $sql .= " AND b.petsa >= :date_from";
        $params[":date_from"] = $date_from;
    }
    if (!empty($date_to)) {
        $sql .= " AND b.petsa <= :date_to";
        $params[":date_to"] = $date_to;
    }

    $sql .= " ORDER BY b.blotter_id ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        // Fix zero-dates from MySQL
        foreach ($records as &$row) {
            foreach (["petsa", "submitted_at", "resolved_at"] as $col) {
                if (isset($row[$col]) && (
                    $row[$col] === "0000-00-00" ||
                    $row[$col] === "0000-00-00 00:00:00"
                )) {
                    $row[$col] = null;
                }
            }
        }
        unset($row);

        // Counts — all enum values from the SQL schema
        $countStmt = $pdo->query(
            "SELECT status, COUNT(*) as count FROM blotter GROUP BY status"
        );
        $counts = [
            "Total"     => 0,
            "Pending"   => 0,
            "Scheduled" => 0,
            "Ongoing"   => 0,
            "Resolved"  => 0,
            "Escalated" => 0,
            "Dismissed" => 0,
        ];
        while ($row = $countStmt->fetch()) {
            if (array_key_exists($row["status"], $counts)) {
                $counts[$row["status"]] = (int) $row["count"];
            }
            $counts["Total"] += (int) $row["count"];
        }

        echo json_encode([
            "success" => true,
            "records" => $records,
            "counts"  => $counts,
        ]);
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Failed to fetch records."]);
    }
    exit();
}

// ============================================================
//  POST — Update blotter status
//  Only updates the blotter table (no blotter_details).
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $body = file_get_contents("php://input");
    $data = json_decode($body, true);

    if (!isset($data["blotter_id"])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Missing blotter_id."]);
        exit();
    }

    $blotterId = (int) $data["blotter_id"];
    $newStatus = trim($data["status"] ?? "");

    // Must match the blotter status enum exactly
    $allowedStatuses = ["Pending", "Scheduled", "Ongoing", "Resolved", "Escalated", "Dismissed"];
    if (!in_array($newStatus, $allowedStatuses)) {
        echo json_encode(["success" => false, "message" => "Invalid status."]);
        exit();
    }

    // resolved_at — set when Resolved/Escalated, clear otherwise
    $resolvedAt = null;
    if ($newStatus === "Resolved" || $newStatus === "Escalated") {
        if (!empty($data["resolved_at"])) {
            $d = DateTime::createFromFormat("Y-m-d", $data["resolved_at"]);
            $resolvedAt = ($d && $d->format("Y-m-d") === $data["resolved_at"])
                ? $data["resolved_at"]
                : date("Y-m-d");
        } else {
            $resolvedAt = date("Y-m-d");
        }
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE blotter
             SET status = :status, resolved_at = :resolved_at
             WHERE blotter_id = :bid"
        );
        $stmt->execute([
            ":status"      => $newStatus,
            ":resolved_at" => $resolvedAt,
            ":bid"         => $blotterId,
        ]);

        echo json_encode(["success" => true, "message" => "Status updated."]);
    } catch (PDOException $e) {
        error_log("Status update error: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Failed to update status."]);
    }
    exit();
}

http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed."]);
exit();
?>
