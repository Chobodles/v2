<?php
// ============================================================
//  Barangay Tugtug E-System — Submit Blotter Report
//  File: php/submit_blotter.php
// ============================================================
header("Content-Type: application/json");
ini_set("display_errors", 0);
ini_set("log_errors", 1);
error_reporting(E_ALL);

define("DB_HOST",    "localhost");
define("DB_NAME",    "db_barangay_e-system");
define("DB_USER",    "root");
define("DB_PASS",    "");
define("DB_CHARSET", "utf8mb4");

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

$body = file_get_contents("php://input");
$data = json_decode($body, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON data received."]);
    exit();
}

// ── Generate unique reference number ─────────────────────────
$year    = date("Y");
$ref_num = "";
do {
    $random  = str_pad(mt_rand(1000, 9999), 4, "0", STR_PAD_LEFT);
    $ref_num = "BRGY-" . $year . "-" . $random;
    $check   = $pdo->prepare(
        "SELECT COUNT(*) FROM blotter_reference_number WHERE blotter_refnumber = :ref"
    );
    $check->execute([":ref" => $ref_num]);
    $exists = $check->fetchColumn();
} while ($exists > 0);

try {
    // ── Insert into blotter ───────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO blotter
            (reference_number, full_name, age, civil_status, address, occupation,
             petsa, oras, complaint_against, complaint_type, complaint_details,
             submitted_at, status)
         VALUES
            (:ref, :name, :age, :civil, :address, :occupation,
             :petsa, :oras, :against, :type, :details,
             NOW(), 'Pending')"
    );
    $stmt->execute([
        ":ref"        => $ref_num,
        ":name"       => trim($data["full_name"]         ?? ""),
        ":age"        => intval($data["age"]             ?? 0),
        ":civil"      => trim($data["civil_status"]      ?? ""),
        ":address"    => trim($data["address"]           ?? ""),
        ":occupation" => trim($data["occupation"]        ?? ""),
        ":petsa"      => $data["petsa"]                  ?? null,
        ":oras"       => $data["oras"]                   ?? null,
        ":against"    => trim($data["complaint_against"] ?? ""),
        ":type"       => trim($data["complaint_type"]    ?? ""),
        ":details"    => trim($data["complaint_details"] ?? ""),
    ]);
    $blotterId = $pdo->lastInsertId();

    // ── Insert empty blotter_details row ─────────────────────
    // blotter_details has blotter_refnumber and blotter_id columns
    $detStmt = $pdo->prepare(
        "INSERT INTO blotter_details (blotter_refnumber, blotter_id, created_at, updated_at)
         VALUES (:ref, :bid, NOW(), NOW())"
    );
    $detStmt->execute([":ref" => $ref_num, ":bid" => $blotterId]);
    $detailId = $pdo->lastInsertId();

    // ── Insert into blotter_reference_number ─────────────────
    // Table only has (blotter_refnumber, detail_id)
    $refStmt = $pdo->prepare(
        "INSERT INTO blotter_reference_number (blotter_refnumber, detail_id)
         VALUES (:ref, :did)"
    );
    $refStmt->execute([
        ":ref" => $ref_num,
        ":did" => $detailId,
    ]);

    echo json_encode([
        "success"          => true,
        "reference_number" => $ref_num,
        "blotter_id"       => $blotterId,
    ]);

} catch (PDOException $e) {
    error_log("Insert error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Failed to save blotter: " . $e->getMessage(),
    ]);
}
exit();
?>