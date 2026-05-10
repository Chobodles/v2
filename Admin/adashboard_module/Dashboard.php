<?php
session_start();

// 1. Session Check
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: ../login_module/Login.php");
    exit();
}

// 2. Database Connection
$host = "localhost";
$dbname = "db-barangay-system";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Fetch Latest 5 Documents (Joining with resident_information)
    $docStmt = $pdo->query("SELECT dr.document_refnumber, ri.first_name, ri.last_name, dr.status
                            FROM document_request dr
                            INNER JOIN resident_information ri ON dr.resident_ID = ri.resident_ID
                            ORDER BY dr.date DESC LIMIT 5");
    $documents = $docStmt->fetchAll();

    // 2. Fetch Latest 5 Blotters (Combining name columns into 'full_name')
    $blotterStmt = $pdo->query("SELECT reference_number,
                                CONCAT(first_name, ' ', last_name) AS full_name,
                                status
                                FROM blotter
                                ORDER BY submitted_at DESC LIMIT 5");
    $blotters = $blotterStmt->fetchAll();
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper for Status Tags
function getStatusClass($status)
{
    $status = strtolower($status);
    $map = [
        "pending" => ["bg" => "#fff3cd", "text" => "#856404"],
        "processing" => ["bg" => "#cfe2ff", "text" => "#084298"],
        "ready" => ["bg" => "#d1e7dd", "text" => "#0a3622"],
        "released" => ["bg" => "#e2d9f3", "text" => "#4a235a"],
        "scheduled" => ["bg" => "#cfe2ff", "text" => "#084298"],
        "resolved" => ["bg" => "#d1e7dd", "text" => "#0a3622"],
    ];
    return $map[$status] ?? ["bg" => "#eee", "text" => "#333"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home | Barangay Tugtug E-System</title>
    <link rel="stylesheet" href="cssfile/Dashboard.css">
</head>
<body>
    <main class="main-content">
        <section class="document-latest-container">
            <label class="dtitle">Document Requests</label>
            <div class="php-table-container">
                <div class="table-header">
                    <span class="h-ref">Ref No.</span>
                    <span class="h-name">Name</span>
                    <span class="h-status">Status</span>
                </div>
                <?php foreach ($documents as $doc):
                    $colors = getStatusClass($doc["status"]); ?>
                    <div class="table-row">
                        <span class="r-ref"><?= htmlspecialchars(
                            $doc["document_refnumber"],
                        ) ?></span>
                        <span class="r-name"><?= htmlspecialchars(
                            $doc["first_name"] . " " . $doc["last_name"],
                        ) ?></span>
                        <span class="r-status">
                            <small style="background:<?= $colors[
                                "bg"
                            ] ?>; color:<?= $colors["text"] ?>;">
                                <?= htmlspecialchars($doc["status"]) ?>
                            </small>
                        </span>
                    </div>
                <?php
                endforeach; ?>
            </div>
        </section>

        <section class="blotter-latest-container">
            <label class="btitle">Blotter Requests</label>
            <div class="php-table-container">
                <div class="table-header">
                    <span class="h-ref">Ref No.</span>
                    <span class="h-name">Name</span>
                    <span class="h-status">Status</span>
                </div>
                <?php foreach ($blotters as $blotter):
                    $colors = getStatusClass($blotter["status"]); ?>
                    <div class="table-row">
                        <span class="r-ref"><?= htmlspecialchars(
                            $blotter["reference_number"],
                        ) ?></span>
                        <span class="r-name"><?= htmlspecialchars(
                            $blotter["full_name"],
                        ) ?></span>
                        <span class="r-status">
                            <small style="background:<?= $colors[
                                "bg"
                            ] ?>; color:<?= $colors["text"] ?>;">
                                <?= htmlspecialchars($blotter["status"]) ?>
                            </small>
                        </span>
                    </div>
                <?php
                endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
