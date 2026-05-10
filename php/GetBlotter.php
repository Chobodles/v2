<?php
// ============================================================
//  Barangay Tugtug E-System — Get / Update Blotter Records
//  File: php/GetBlotter.php
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
//  GET — Fetch all blotter records (with details joined)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $search    = isset($_GET["search"])    ? trim($_GET["search"])    : "";
    $filter    = isset($_GET["filter"])    ? trim($_GET["filter"])    : "";
    $date_from = isset($_GET["date_from"]) ? trim($_GET["date_from"]) : "";
    $date_to   = isset($_GET["date_to"])   ? trim($_GET["date_to"])   : "";

    $sql = "
        SELECT
            b.blotter_id,
            b.reference_number,
            b.full_name,
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
            bd.detail_id,
            bd.schedule_date_1,
            bd.schedule_time_1,
            bd.schedule_details_1,
            bd.schedule_outcome_1,
            bd.schedule_date_2,
            bd.schedule_time_2,
            bd.schedule_details_2,
            bd.schedule_outcome_2,
            bd.schedule_date_3,
            bd.schedule_time_3,
            bd.schedule_details_3,
            bd.schedule_outcome_3,
            bd.resolution_notes,
            bd.resolved_at,
            bd.presiding_kagawad,
            bd.secretary_name
        FROM blotter b
        LEFT JOIN blotter_details bd ON b.blotter_id = bd.blotter_id
        WHERE 1=1";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND (
            b.full_name            LIKE :search1
            OR b.complaint_against LIKE :search2
            OR b.complaint_type    LIKE :search3
            OR b.reference_number  LIKE :search4
            OR b.status            LIKE :search5
        )";
        $like = "%" . $search . "%";
        $params[":search1"] = $like;
        $params[":search2"] = $like;
        $params[":search3"] = $like;
        $params[":search4"] = $like;
        $params[":search5"] = $like;
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
            foreach (["petsa", "submitted_at", "resolved_at",
                      "schedule_date_1", "schedule_date_2", "schedule_date_3"] as $col) {
                if (isset($row[$col]) && (
                    $row[$col] === "0000-00-00" ||
                    $row[$col] === "0000-00-00 00:00:00"
                )) {
                    $row[$col] = null;
                }
            }
        }
        unset($row);

        // Count per status
        $countStmt = $pdo->query(
            "SELECT status, COUNT(*) as count FROM blotter GROUP BY status"
        );
        $counts = [
            "Total"     => 0,
            "Pending"   => 0,
            "Scheduled" => 0,
            "Resolved"  => 0,
            "Escalated" => 0,
        ];
        while ($row = $countStmt->fetch()) {
            $counts[$row["status"]] = (int) $row["count"];
            $counts["Total"]       += (int) $row["count"];
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
//  POST — Update status, schedule, or unlock
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
    $action    = $data["action"] ?? "update_status";

    // ── ACTION: save_schedule ─────────────────────────────────
    if ($action === "save_schedule") {
        $n        = (int)   ($data["schedule_number"]  ?? 0);
        $date     = trim($data["schedule_date"]        ?? "");
        $time     = trim($data["schedule_time"]        ?? "");
        $details  = trim($data["schedule_details"]     ?? "");
        $outcome  = trim($data["schedule_outcome"]     ?? "");

        if ($n < 1 || $n > 3 || !$date || !$time || !$outcome) {
            echo json_encode(["success" => false, "message" => "Missing schedule fields."]);
            exit();
        }

        $allowedOutcomes = [
            "Appeared - Resolved",
            "Appeared - Rescheduled",
            "Did Not Appear - No Response",
            "Resolved",
            "Escalated",
            // Legacy DB enum values (kept for backward compatibility)
            "Appeared",
            "Did Not Appear",
            "Rescheduled",
            "No Response"
        ];
        if (!in_array($outcome, $allowedOutcomes)) {
            echo json_encode(["success" => false, "message" => "Invalid outcome."]);
            exit();
        }

        try {
            // Ensure blotter_details row exists
            $check = $pdo->prepare("SELECT detail_id FROM blotter_details WHERE blotter_id = :bid LIMIT 1");
            $check->execute([":bid" => $blotterId]);
            $detail = $check->fetch();

            if (!$detail) {
                // Get the reference number for this blotter to store in blotter_details
                $refLookup = $pdo->prepare("SELECT reference_number FROM blotter WHERE blotter_id = :bid LIMIT 1");
                $refLookup->execute([":bid" => $blotterId]);
                $refRow = $refLookup->fetch();
                $refNum = $refRow ? $refRow["reference_number"] : null;

                $ins = $pdo->prepare(
                    "INSERT INTO blotter_details (blotter_refnumber, blotter_id, created_at, updated_at)
                     VALUES (:ref, :bid, NOW(), NOW())"
                );
                $ins->execute([":ref" => $refNum, ":bid" => $blotterId]);
                $detailId = $pdo->lastInsertId();

                // Update blotter_reference_number using blotter_refnumber as the key
                if ($refNum) {
                    $updRef = $pdo->prepare(
                        "UPDATE blotter_reference_number SET detail_id = :did WHERE blotter_refnumber = :ref"
                    );
                    $updRef->execute([":did" => $detailId, ":ref" => $refNum]);
                }
            }

            $sql = "UPDATE blotter_details SET
                        schedule_date_{$n}    = :date,
                        schedule_time_{$n}    = :time,
                        schedule_details_{$n} = :details,
                        schedule_outcome_{$n} = :outcome,
                        updated_at            = NOW()
                    WHERE blotter_id = :bid";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":date"    => $date,
                ":time"    => $time,
                ":details" => $details,
                ":outcome" => $outcome,
                ":bid"     => $blotterId,
            ]);

            // Auto-update main blotter status to Scheduled when a schedule is saved
            $mainStatus = $pdo->prepare("SELECT status FROM blotter WHERE blotter_id = :bid");
            $mainStatus->execute([":bid" => $blotterId]);
            $current = $mainStatus->fetchColumn();

            if ($current === "Pending") {
                $updMain = $pdo->prepare("UPDATE blotter SET status = 'Scheduled' WHERE blotter_id = :bid");
                $updMain->execute([":bid" => $blotterId]);
            }

            echo json_encode(["success" => true, "message" => "Schedule saved."]);
        } catch (PDOException $e) {
            error_log("Schedule save error: " . $e->getMessage());
            echo json_encode(["success" => false, "message" => "Failed to save schedule."]);
        }
        exit();
    }

    // ── ACTION: update_status ─────────────────────────────────
    if ($action === "update_status") {
        $newStatus = trim($data["status"] ?? "");
        $allowedStatuses = ["Pending", "Scheduled", "Resolved", "Escalated"];

        if (!in_array($newStatus, $allowedStatuses)) {
            echo json_encode(["success" => false, "message" => "Invalid status."]);
            exit();
        }

        $resolvedAt = null;
        $resNotes   = trim($data["resolution_notes"]   ?? "");
        $kagawad    = trim($data["presiding_kagawad"]  ?? "");
        $secretary  = trim($data["secretary_name"]     ?? "");

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
            // Update main status
            $stmt = $pdo->prepare(
                "UPDATE blotter SET status = :status WHERE blotter_id = :bid"
            );
            $stmt->execute([":status" => $newStatus, ":bid" => $blotterId]);

            // Update blotter_details resolution fields
            if ($newStatus === "Resolved" || $newStatus === "Escalated") {
                // Ensure blotter_details row exists
                $check = $pdo->prepare("SELECT detail_id FROM blotter_details WHERE blotter_id = :bid LIMIT 1");
                $check->execute([":bid" => $blotterId]);
                if (!$check->fetch()) {
                    $refLookup = $pdo->prepare("SELECT reference_number FROM blotter WHERE blotter_id = :bid LIMIT 1");
                    $refLookup->execute([":bid" => $blotterId]);
                    $refRow = $refLookup->fetch();
                    $refNum = $refRow ? $refRow["reference_number"] : null;

                    $ins = $pdo->prepare(
                        "INSERT INTO blotter_details (blotter_refnumber, blotter_id, created_at, updated_at)
                         VALUES (:ref, :bid, NOW(), NOW())"
                    );
                    $ins->execute([":ref" => $refNum, ":bid" => $blotterId]);
                }

                $updDet = $pdo->prepare(
                    "UPDATE blotter_details SET
                        resolution_notes  = :notes,
                        resolved_at       = :rat,
                        presiding_kagawad = :kagawad,
                        secretary_name    = :sec,
                        updated_at        = NOW()
                    WHERE blotter_id = :bid"
                );
                $updDet->execute([
                    ":notes"   => $resNotes   ?: null,
                    ":rat"     => $resolvedAt,
                    ":kagawad" => $kagawad    ?: null,
                    ":sec"     => $secretary  ?: null,
                    ":bid"     => $blotterId,
                ]);
            }

            echo json_encode(["success" => true, "message" => "Status updated."]);
        } catch (PDOException $e) {
            error_log("Status update error: " . $e->getMessage());
            echo json_encode(["success" => false, "message" => "Failed to update status."]);
        }
        exit();
    }

    // ── ACTION: clear_locked (unlock) ─────────────────────────
    if (!empty($data["clear_locked"])) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE blotter_details SET
                    resolved_at = NULL, updated_at = NOW()
                 WHERE blotter_id = :bid"
            );
            $stmt->execute([":bid" => $blotterId]);

            echo json_encode(["success" => true, "message" => "Record unlocked."]);
        } catch (PDOException $e) {
            error_log("Unlock error: " . $e->getMessage());
            echo json_encode(["success" => false, "message" => "Failed to unlock."]);
        }
        exit();
    }

    echo json_encode(["success" => false, "message" => "Unknown action."]);
    exit();
}

http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed."]);
exit();
?>