// ============================================================
//  Barangay Tugtug E-System — Login Logic
//  File: login.js
//  Attach this ONLY via <script src="js/login.js"></script>
//  placed right before </body> in your Login.html
// ============================================================
 
document.addEventListener("DOMContentLoaded", function () {
 
    const submitBtn  = document.getElementById("submit-button");
    const emailInput = document.getElementById("Email");
    const passInput  = document.getElementById("Password");
 
    // ── 1. Replace the inline onclick so PHP handles auth ──
    submitBtn.removeAttribute("onclick");   // removes window.location.href='Dashboard.html'
 
    // ── 2. Create a small feedback label (injected once) ────
    const feedback = document.createElement("p");
    feedback.id = "login-feedback";
    feedback.style.cssText = `
        position  : absolute;
        bottom    : 12%;
        left      : 7%;
        right     : 7%;
        color     : #ff6b6b;
        font-size : 13px;
        font-family: system-ui, sans-serif;
        text-align: center;
        margin    : 0;
        display   : none;
    `;
    submitBtn.parentElement.appendChild(feedback);
 
    // ── 3. Helper: show message ──────────────────────────────
    function showFeedback(msg, color = "#ff6b6b") {
        feedback.textContent   = msg;
        feedback.style.color   = color;
        feedback.style.display = "block";
    }
 
    // ── 4. Helper: simple email format check ────────────────
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
 
    // ── 5. Main login handler ────────────────────────────────
    async function handleLogin() {
 
        const email    = emailInput.value.trim();
        const password = passInput.value;
 
        // — Client-side validation —
        if (!email || !password) {
            showFeedback("Please fill in both fields.");
            return;
        }
        if (!isValidEmail(email)) {
            showFeedback("Please enter a valid email address.");
            return;
        }
 
        // — Disable button while request is in-flight —
        submitBtn.disabled     = true;
        submitBtn.textContent  = "Logging in…";
        feedback.style.display = "none";
 
        try {
            // ── 6. Send credentials to PHP ───────────────────
            const response = await fetch("php/Login.php", {
                method  : "POST",
                headers : { "Content-Type": "application/json" },
                body    : JSON.stringify({ email, password })
            });
 
            const result = await response.json();
 
            if (result.success) {
                // ✅ Login OK — fade out then redirect
                showFeedback("Login successful! Redirecting…", "#6bffb8");
                document.body.classList.add("fade-out");
 
                setTimeout(() => {
                    window.location.href = "Dashboard.html";
                }, 500);
 
            } else {
                // ❌ Wrong credentials or inactive account
                showFeedback(result.message || "Invalid email or password.");
                submitBtn.disabled    = false;
                submitBtn.textContent = "Log-in";
            }
 
        } catch (error) {
            // ❌ Network / server error
            console.error("Login error:", error);
            showFeedback("Server error. Please try again later.");
            submitBtn.disabled    = false;
            submitBtn.textContent = "Log-in";
        }
    }
 
    // ── 7. Trigger login on button click OR Enter key ───────
    submitBtn.addEventListener("click", handleLogin);
 
    [emailInput, passInput].forEach(input => {
        input.addEventListener("keydown", function (e) {
            if (e.key === "Enter") handleLogin();
        });
    });
 
});