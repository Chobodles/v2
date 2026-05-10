<?php
// ============================================================
//  Barangay Tugtug E-System — Track Request
//  File: php/track_request.php
//  GET ?ref=BRGY-2026-1234  or  ?ref=DOC-2026-1234
//
//  Blotter lookup: queries blotter table directly by
//  reference_number (UNIQUE key). No blotter_reference_number
//  or blotter_details tables — those are not in the SQL schema.
//
//  Document lookup: queries document_request joined to
//  resident_information and documents via existing FK columns.
//  document_reference_number is also not in the SQL schema,
//  so we look up by document_request.document_refnumber instead.
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

// ── BRGY prefix → blotter ─────────────────────────────────────
if (str_starts_with($ref, "BRGY-")) {

    try {
        // reference_number is a UNIQUE key on the blotter table —
        // query it directly, no join to blotter_reference_number needed.
        $stmt = $pdo->prepare(
            "SELECT
                blotter_id,
                reference_number,
                first_name,
                middle_name,
                last_name,
                suffix,
                complaint_against,
                petsa,
                status,
                complaint_details
             FROM blotter
             WHERE reference_number = :ref
             LIMIT 1"
        );
        $stmt->execute([":ref" => $ref]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(["success" => false, "message" => "Reference number not found."]);
            exit;
        }

        // Build display name from separate columns
        $fullName = trim(implode(" ", array_filter([
            $row["first_name"],
            $row["middle_name"] ?: null,
            $row["last_name"],
            $row["suffix"]      ?: null,
        ])));

        echo json_encode([
            "success" => true,
            "type"    => "blotter",
            "data"    => [
                "reference_number" => $row["reference_number"],
                "name"             => $fullName,
                "complainant"      => $row["complaint_against"],
                "incident_date"    => $row["petsa"],
                "complaint"        => $row["complaint_details"],
                "status"           => $row["status"],
                "price"            => "Free",
                // No schedule data — blotter_details not in schema
                "schedule_date_1"  => null,
                "schedule_time_1"  => null,
                "schedule_date_2"  => null,
                "schedule_time_2"  => null,
                "schedule_date_3"  => null,
                "schedule_time_3"  => null,
            ]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(["success" => false, "message" => "Query error."]);
    }

// ── DOC prefix → document request ────────────────────────────
} elseif (str_starts_with($ref, "DOC-")) {

    try {
        // document_refnumber is a UNIQUE key on document_request —
        // query it directly, no join to document_reference_number needed.
        $stmt = $pdo->prepare(
            "SELECT
                dr.request_ID,
                ri.first_name,
                ri.last_name,
                dr.document_purpose,
                dr.date,
                dr.status,
                dr.date_released,
                dr.quantity,
                dr.document_refnumber,
                d.document_type,
                d.price
             FROM document_request dr
             JOIN resident_information ri ON dr.resident_ID = ri.resident_ID
             LEFT JOIN documents d        ON dr.document_ID = d.document_ID
             WHERE dr.document_refnumber = :ref
             LIMIT 1"
        );
        $stmt->execute([":ref" => $ref]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(["success" => false, "message" => "Reference number not found."]);
            exit;
        }

        $rawPrice     = $row["price"] ?? 0;
        $priceDisplay = ($rawPrice == 0)
            ? "Free"
            : "₱" . number_format((float)$rawPrice, 2);

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
