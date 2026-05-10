<?php
header("Content-Type: application/json");

// 1. Check if data was actually posted
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method.",
    ]);
    exit();
}

// 2. Generate Reference Number
$ref_number = "BT-" . date("Y") . "-" . strtoupper(substr(md5(uniqid()), 0, 5));

$servername = "localhost";
$database = "db-barangay-e-system";
$username = "root";
$password = "";
$conn = mysqli_connect($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Prepare the SQL (Directly using $_POST values)
$sql = "INSERT INTO blotter (
            reference_number, first_name, middle_name, last_name, suffix,
            age, civil_status, address, occupation,
            petsa, oras, complaint_against, complaint_type, complaint_details,
            status, submitted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    // Map $_POST keys to the query.
    // Ensure the keys match the 'name' attributes in your HTML form!
    mysqli_stmt_bind_param(
        $stmt,
        "sssssissssssss",
        $ref_number,
        $_POST["first_name"],
        $_POST["middle_name"],
        $_POST["last_name"],
        $_POST["suffix"],
        $_POST["age"],
        $_POST["civil_status"],
        $_POST["address"],
        $_POST["occupation"],
        $_POST["incident_date"], // Matching your JS names
        $_POST["incident_time"],
        $_POST["complainant_name"],
        $_POST["complaint_type"],
        $_POST["complaint_details"],
    );

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "success" => true,
            "reference_number" => $ref_number,
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => mysqli_error($conn),
        ]);
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>
