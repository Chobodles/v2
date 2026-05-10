


<?php
session_start();

// 1. Session Security Check
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

    // 3. Fetch Latest 5 Documents (Joining with resident_information for names)
    $docStmt = $pdo->query("SELECT dr.document_refnumber, ri.first_name, ri.last_name, dr.status
                            FROM document_request dr
                            INNER JOIN resident_information ri ON dr.resident_ID = ri.resident_ID
                            ORDER BY dr.date DESC LIMIT 5");
    $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Latest 5 Blotters (Concatenating name parts)
    $blotterStmt = $pdo->query("SELECT reference_number,
                                CONCAT(first_name, ' ', last_name) AS full_name,
                                status
                                FROM blotter
                                ORDER BY submitted_at DESC LIMIT 5");
    $blotters = $blotterStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

// 5. Helper Function for Status Badge Colors
function getStatusStyle($status)
{
    $status = strtolower($status);
    $map = [
        "pending" => ["bg" => "#fff3cd", "text" => "#856404"],
        "processing" => ["bg" => "#cfe2ff", "text" => "#084298"],
        "ready" => ["bg" => "#d1e7dd", "text" => "#0a3622"],
        "released" => ["bg" => "#e2d9f3", "text" => "#4a235a"],
        "scheduled" => ["bg" => "#cfe2ff", "text" => "#084298"],
        "resolved" => ["bg" => "#d1e7dd", "text" => "#0a3622"],
        "escalated" => ["bg" => "#f8d7da", "text" => "#842029"],
    ];
    return $map[$status] ?? ["bg" => "#eee", "text" => "#333"];
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Home | Barangay Tugtug E-System</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="photos/logo.png.png" />
    <link rel="stylesheet" href="cssfile/Dashboard.css" />
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&display=swap" rel="stylesheet" />
</head>
<body>
    <nav class="sidebar-container">
        <div class="Logo-container">
            <img class="Logo" src="photos/logo.png.png" alt="Barangay Logo">
            <h2 class="E-System">Barangay E-System</h2>
            <div class="home-container" onclick="window.location.href='Dashboard.php'">
                <img class="picture-home" src="photos/home.png"><h3 class="Home-word">Home</h3>
            </div>
            <div class="document-container" onclick="window.location.href='Documentrequest.php'">
                <img class="picture-document" src="photos/Document.png"><h3 class="Document-word">Documents</h3>
            </div>
            <div class="blotter-container" onclick="window.location.href='Blotter.php'">
                <img class="picture-blotter" src="photos/Blotter.png"><h3 class="Blotter-word">Blotter</h3>
            </div>
            <div class="analytics-container" onclick="window.location.href='Analytics.php'">
                <img class="picture-analytics" src="photos/Analytics.png"><h3 class="Analytics-word">Analytics</h3>
            </div>
            <div class="profile-section">
                <img class="picture-profile" src="photos/ProfilePicture.png">
                <hr class="sidebar-divider" />
                <img class="picture-logout" src="photos/Logout.png" onclick="window.location.href='../php/logout.php'">
            </div>
        </div>
    </nav>

    <nav class="upbar-container">
        <h1 class="home-title">Home</h1>
    </nav>

    <main class="main-content">
        <section class="document-latest-container">
            <label class="dtitle">Recent Document Requests</label>
            <div class="php-table-container">
                <div class="table-header">
                    <span class="h-ref">Ref No.</span>
                    <span class="h-name">Resident Name</span>
                    <span class="h-status">Status</span>
                </div>
                <?php foreach ($documents as $doc):
                    $style = getStatusStyle($doc["status"]); ?>
                    <div class="table-row">
                        <span class="r-ref"><?= htmlspecialchars(
                            $doc["document_refnumber"],
                        ) ?></span>
                        <span class="r-name"><?= htmlspecialchars(
                            $doc["first_name"] . " " . $doc["last_name"],
                        ) ?></span>
                        <span class="r-status">
                            <small style="background:<?= $style[
                                "bg"
                            ] ?>; color:<?= $style["text"] ?>;">
                                <?= htmlspecialchars($doc["status"]) ?>
                            </small>
                        </span>
                    </div>
                <?php
                endforeach; ?>
            </div>
        </section>

        <section class="blotter-latest-container">
            <label class="btitle">Recent Blotter Reports</label>
            <div class="php-table-container">
                <div class="table-header">
                    <span class="h-ref">Ref No.</span>
                    <span class="h-name">Complainant Name</span>
                    <span class="h-status">Status</span>
                </div>
                <?php foreach ($blotters as $blotter):
                    $style = getStatusStyle($blotter["status"]); ?>
                    <div class="table-row">
                        <span class="r-ref"><?= htmlspecialchars(
                            $blotter["reference_number"],
                        ) ?></span>
                        <span class="r-name"><?= htmlspecialchars(
                            $blotter["full_name"],
                        ) ?></span>
                        <span class="r-status">
                            <small style="background:<?= $style[
                                "bg"
                            ] ?>; color:<?= $style["text"] ?>;">
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
