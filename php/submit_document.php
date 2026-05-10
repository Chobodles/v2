<?php
// ============================================================
//  Barangay Tugtug E-System — Submit Document Request
//  File: php/submit_document.php
// ============================================================
header("Content-Type: application/json");
// ✅ Temporarily show errors in response so we can debug
ini_set("display_errors", 1);
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
    echo json_encode(["success" => false, "message" => "Database connection error: " . $e->getMessage()]);
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
    $ref_num = "DOC-" . $year . "-" . $random;
    $check   = $pdo->prepare("SELECT COUNT(*) FROM document_reference_number WHERE document_refnumber = :ref");
    $check->execute([":ref" => $ref_num]);
    $exists  = $check->fetchColumn();
} while ($exists > 0);

try {
    // ── Find or insert resident ───────────────────────────────
    $res_check = $pdo->prepare(
        "SELECT resident_ID FROM resident_information
         WHERE first_name = :fn AND last_name = :ln LIMIT 1"
    );
    $res_check->execute([
        ":fn" => $data["first_name"] ?? "",
        ":ln" => $data["last_name"]  ?? "",
    ]);
    $resident = $res_check->fetch();

    if ($resident) {
        $resident_id = $resident["resident_ID"];
    } else {
        $middle = $data["middle_name"] ?? "";
        $mi     = $middle ? strtoupper(substr($middle, 0, 1)) : "";
        $gender = $data["gender"] ?? "";
        $sex    = $gender === "Male" ? "M" : ($gender === "Female" ? "F" : "O");

        $ins = $pdo->prepare(
            "INSERT INTO resident_information
                (first_name, last_name, middle_initial, sex, birthdate, birthplace)
             VALUES (:fn, :ln, :mi, :sex, :bd, :bp)"
        );
        $ins->execute([
            ":fn"  => $data["first_name"] ?? "",
            ":ln"  => $data["last_name"]  ?? "",
            ":mi"  => $mi,
            ":sex" => $sex,
            ":bd"  => $data["birthday"]   ?? null,
            ":bp"  => $data["birthplace"] ?? "",
        ]);
        $resident_id = $pdo->lastInsertId();
    }

    // ── certificate value is already the numeric document_ID ─
    $doc_id = intval($data["certificate"] ?? 0);
    if ($doc_id < 1 || $doc_id > 30) {
        echo json_encode(["success" => false, "message" => "Invalid certificate selected. Got: " . ($data["certificate"] ?? "none")]);
        exit();
    }

    // ── Insert document request ───────────────────────────────
    $doc_stmt = $pdo->prepare(
        "INSERT INTO document_request
            (document_refnumber, resident_ID, document_ID, contact, document_purpose,
             date, status, age, length_stay_years, length_stay_months, quantity)
         VALUES
            (:ref, :rid, :did, :contact, :purpose,
             CURDATE(), 'Pending', :age, :stay_y, :stay_m, :qty)"
    );
    $doc_stmt->execute([
        ":ref"     => $ref_num,
        ":rid"     => $resident_id,
        ":did"     => $doc_id,
        ":contact" => $data["contact"]     ?? "",
        ":purpose" => $data["purpose"]     ?? "",
        ":age"     => $data["age"]         ?? 0,
        ":stay_y"  => $data["stay_years"]  ?? 0,
        ":stay_m"  => $data["stay_months"] ?? 0,
        ":qty"     => $data["quantity"]    ?? 1,
    ]);
    $request_id = $pdo->lastInsertId();

    // ── Insert into reference number table (with request_ID) ──
    $ref_stmt = $pdo->prepare(
        "INSERT INTO document_reference_number (document_refnumber, request_ID)
         VALUES (:ref, :req_id)"
    );
    $ref_stmt->execute([":ref" => $ref_num, ":req_id" => $request_id]);

    echo json_encode([
        "success"          => true,
        "reference_number" => $ref_num,
        "request_id"       => $request_id,
    ]);

} catch (PDOException $e) {
    error_log("Insert error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Failed to save request: " . $e->getMessage(),
    ]);
}
exit();
?>