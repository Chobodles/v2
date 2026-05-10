<?php
// ============================================================
//  Barangay Tugtug E-System — Track Request
//  File: php/track_request.php
//  GET ?ref=BRGY-2026-1234  or  ?ref=DOC-2026-1234
// ============================================================
header("Content-Type: application/json");
ini_set("display_errors", 0);
ini_set("log_errors", 1);

define("DB_HOST",    "localhost");
define("DB_NAME",    "db_barangay_e-system");
define("DB_USER",    "root");
define("DB_PASS",    "");
define("DB_CHARSET", "utf8mb4");

$dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // ✅ Must be true to avoid named param issues
    PDO::ATTR_EMULATE_PREPARES   => true,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit;
}

$ref = isset($_GET["ref"]) ? trim($_GET["ref"]) : "";

if (empty($ref)) {
    echo json_encode(["success" => false, "message" => "No reference number provided."]);
    exit;
}

// ── Determine type by prefix ──────────────────────────────────
if (str_starts_with($ref, "BRGY-")) {

    // ── Blotter lookup ────────────────────────────────────────
    try {
        $stmt = $pdo->prepare(
            "SELECT b.blotter_id, b.full_name, b.complaint_against,
                    b.petsa, b.status, b.complaint_details,
                    b.reference_number,
                    bd.schedule_date_1, bd.schedule_time_1,
                    bd.schedule_date_2, bd.schedule_time_2,
                    bd.schedule_date_3, bd.schedule_time_3
             FROM blotter_reference_number br
             JOIN blotter_details bd ON br.detail_id  = bd.detail_id
             JOIN blotter b          ON bd.blotter_id = b.blotter_id
             WHERE br.blotter_refnumber = :ref
             LIMIT 1"
        );
        $stmt->execute([":ref" => $ref]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(["success" => false, "message" => "Reference number not found."]);
            exit;
        }

        echo json_encode([
            "success" => true,
            "type"    => "blotter",
            "data"    => [
                "reference_number" => $row["reference_number"],
                "name"             => $row["full_name"],
                "complainant"      => $row["complaint_against"],
                "incident_date"    => $row["petsa"],
                "complaint"        => $row["complaint_details"],
                "status"           => $row["status"],
                "price"            => "Free",
                "schedule_date_1"  => $row["schedule_date_1"],
                "schedule_time_1"  => $row["schedule_time_1"],
                "schedule_date_2"  => $row["schedule_date_2"],
                "schedule_time_2"  => $row["schedule_time_2"],
                "schedule_date_3"  => $row["schedule_date_3"],
                "schedule_time_3"  => $row["schedule_time_3"],
            ]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(["success" => false, "message" => "Query error."]);
    }

} elseif (str_starts_with($ref, "DOC-")) {

    // ── Document lookup ───────────────────────────────────────
    // ✅ JOIN documents table to get document_type and price directly from DB
    try {
        $stmt = $pdo->prepare(
            "SELECT dr.request_ID,
                    ri.first_name,
                    ri.last_name,
                    dr.document_purpose,
                    dr.date,
                    dr.status,
                    dr.date_released,
                    dr.quantity,
                    dn.document_refnumber,
                    d.document_type,
                    d.price
             FROM document_reference_number dn
             JOIN document_request dr      ON dn.request_ID  = dr.request_ID
             JOIN resident_information ri  ON dr.resident_ID = ri.resident_ID
             LEFT JOIN documents d         ON dr.document_ID = d.document_ID
             WHERE dn.document_refnumber = :ref
             LIMIT 1"
        );
        $stmt->execute([":ref" => $ref]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(["success" => false, "message" => "Reference number not found."]);
            exit;
        }

        // ✅ Format price from DB: 0 = Free, otherwise show ₱ amount
        $rawPrice    = $row["price"] ?? 0;
        $priceDisplay = ($rawPrice == 0)
            ? "Free"
            : "₱" . number_format((float)$rawPrice, 2);

        // ✅ Fix zero date_released from MySQL
        $dateReleased = $row["date_released"];
        if ($dateReleased === "0000-00-00" || $dateReleased === "0000-00-00 00:00:00") {
            $dateReleased = null;
        }

        echo json_encode([
            "success" => true,
            "type"    => "document",
            "data"    => [
                "reference_number" => $row["document_refnumber"],
                "name"             => $row["first_name"] . " " . $row["last_name"],
                "document_type"    => $row["document_type"] ?? "—",
                "purpose"          => $row["document_purpose"],
                "date_requested"   => $row["date"],
                "date_released"    => $dateReleased,
                "quantity"         => $row["quantity"],
                "status"           => $row["status"],
                "price"            => $priceDisplay,
            ]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(["success" => false, "message" => "Query error."]);
    }

} else {
    echo json_encode(["success" => false, "message" => "Invalid reference number format. Use BRGY-YEAR-XXXX or DOC-YEAR-XXXX."]);
}
exit;
?>