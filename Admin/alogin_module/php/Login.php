<?php
// ============================================================
//  Barangay Tugtug E-System — Login Backend
//  File: php/login.php
//  Called by login.js via fetch("php/login.php")
// ============================================================
 
// ── 0. Output & error config ─────────────────────────────────
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
 
// Hide PHP errors from the browser in production;
// log them to server error log instead
ini_set("display_errors", 0);
ini_set("log_errors",     1);
error_reporting(E_ALL);
 
// ── 1. Only accept POST requests ─────────────────────────────
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit;
}
 
// ── 2. Parse JSON body ───────────────────────────────────────
$body = file_get_contents("php://input");
$data = json_decode($body, true);
 
if (!isset($data["email"], $data["password"])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing email or password."]);
    exit;
}
 
$email    = trim($data["email"]);
$password = $data["password"];
 
// ── 3. Basic server-side validation ──────────────────────────
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email format."]);
    exit;
}
if (strlen($password) < 1) {
    echo json_encode(["success" => false, "message" => "Password cannot be empty."]);
    exit;
}
 
// ── 4. Database connection ────────────────────────────────────
//  ⚠️  CHANGE these values to match YOUR actual database.
define("DB_HOST",   "localhost");
define("DB_NAME",   "db_barangay_e-system");
define("DB_USER",   "root");               // your DB username
define("DB_PASS",   "");                   // your DB password
define("DB_CHARSET","utf8mb4");
 
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
 
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Never expose real DB errors to the client
    error_log("DB Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error. Please contact the administrator."]);
    exit;
}
 
// ── 5. Look up user by email ──────────────────────────────────
//
//  Expected table structure (adjust column names to match yours):
//
//  CREATE TABLE users (
//      id            INT          AUTO_INCREMENT PRIMARY KEY,
//      email         VARCHAR(255) UNIQUE NOT NULL,
//      password_hash VARCHAR(255) NOT NULL,           -- store bcrypt hash, NOT plaintext
//      role          VARCHAR(50)  NOT NULL DEFAULT 'staff',
//      is_active     TINYINT(1)   NOT NULL DEFAULT 1,
//      created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
//  );
//
try {
    $stmt = $pdo->prepare(
        "SELECT id, email, password_hash, role, is_active
         FROM   users
         WHERE  email = :email
         LIMIT  1"
    );
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch();
 
} catch (PDOException $e) {
    error_log("Query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error. Please try again."]);
    exit;
}
 
// ── 6. Verify credentials ────────────────────────────────────
// Generic message so attackers cannot tell which field was wrong
$genericError = ["success" => false, "message" => "Invalid email or password."];
 
if (!$user) {
    // No account found
    echo json_encode($genericError);
    exit;
}
 
if (!$user["is_active"]) {
    echo json_encode(["success" => false, "message" => "Your account is inactive. Please contact the Barangay administrator."]);
    exit;
}
 
// password_verify() safely compares against the bcrypt hash
if (!password_verify($password, $user["password_hash"])) {
    echo json_encode($genericError);
    exit;
}
 
// ── 7. Start session & store user data ───────────────────────
session_start();
session_regenerate_id(true);          // prevent session fixation
 
$_SESSION["user_id"]    = $user["id"];
$_SESSION["user_email"] = $user["email"];
$_SESSION["user_role"]  = $user["role"];
$_SESSION["logged_in"]  = true;
 
// ── 8. Return success ────────────────────────────────────────
echo json_encode([
    "success" => true,
    "message" => "Login successful.",
    "role"    => $user["role"]       // optional: JS can redirect by role later
]);
exit;
?>
