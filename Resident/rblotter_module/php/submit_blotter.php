<?php
// Ensure you are pointing to where your actual connection file is
// Based on your image, it might be in a top-level 'include' folder
$servername = "localhost";
$database = "db-barangay-e-system"; // Verify if it is _ or -
$username = "root";
$password = "";

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ref_number =
        "BT-" . date("Y") . "-" . strtoupper(substr(md5(uniqid()), 0, 5));

    // Ensure every column here exists exactly like this in phpMyAdmin
    $sql = "INSERT INTO blotter (
                reference_number, first_name, middle_name, last_name, suffix,
                age, civil_status, address, occupation,
                petsa, oras, complaint_against, complaint_type, complaint_details,
                status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
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
            $_POST["incident_date"],
            $_POST["incident_time"],
            $_POST["complainant_name"],
            $_POST["complaint_type"],
            $_POST["complaint_details"],
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: ../blotterthankyou.html?ref=" . $ref_number);
            exit();
        } else {
            echo "Execution Error: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        // This will tell you exactly what is wrong with the SQL syntax or table
        die("Prepare Error: " . mysqli_error($conn));
    }
}
mysqli_close($conn);
?>
