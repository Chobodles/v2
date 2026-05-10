<?php
/* ============================================================
   Barangay Tugtug E-System — Unified Login
   File: Login.php
   ============================================================ */
session_start();

// --- 1. Database Configuration ---
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "db-barangay-system";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 2. Handle Authentication Logic ---
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login_action"])) {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // Use prepared statement to find the user
        $stmt = $conn->prepare(
            "SELECT id, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1",
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            // Verify the entered password against the stored Bcrypt hash
            if (password_verify($password, $user["password_hash"])) {
                if (!$user["is_active"]) {
                    $error_message =
                        "Your account is inactive. Contact the administrator.";
                } else {
                    // SUCCESS - Initialize Session
                    session_regenerate_id(true);
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_role"] = $user["role"];
                    $_SESSION["logged_in"] = true;

                    header("Location: ../adashboard_module/Dashboard.php");
                    exit();
                }
            } else {
                // Password does not match hash
                $error_message = "Invalid email or password.";
            }
        } else {
            // Email not found
            $error_message = "Invalid email or password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Log-in | Barangay Tugtug E-System</title>
    <link rel="stylesheet" href="cssfile/Login.css"/>
    <link rel="icon" href="photos/logo.png.png"/>
    <style>
        #login-feedback {
            position: absolute;
            bottom: 12%;
            left: 7%;
            right: 7%;
            color: #ff6b6b;
            font-size: 13px;
            font-family: system-ui, sans-serif;
            text-align: center;
            margin: 0;
        }
    </style>
</head>
<body>

    <button class="btn-back" onclick="window.location.href='choice.html'">
        <img class="backbutton" src="photos/backbutton.png" alt="Back">
        <label class="back">Back</label>
    </button>

    <header class="Login-Container">
        <img id="Profile" src="photos/ProfilePicture.png" alt="Profile">
        <h2 id="E-System">Barangay E-System</h2>

        <form id="loginForm" method="POST" action="Login.php">
            <input type="hidden" name="login_action" value="1">

            <input id="Email" name="email" type="email" placeholder="Enter Email"
                   value="<?php echo isset($_POST["email"])
                       ? htmlspecialchars($_POST["email"])
                       : ""; ?>" required>

            <input id="Password" name="password" type="password" placeholder="Enter Password" required>

            <button id="submit-button" type="submit">Log-in</button>
        </form>

        <?php if (!empty($error_message)): ?>
            <p id="login-feedback"><?php echo $error_message; ?></p>
        <?php endif; ?>
    </header>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.getElementById("loginForm");
            const submitBtn = document.getElementById("submit-button");

            form.addEventListener("submit", function() {
                submitBtn.disabled = true;
                submitBtn.textContent = "Logging in...";
            });
        });
    </script>

</body>
</html>
<?php $conn->close(); ?>
