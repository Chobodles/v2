// ============================================================
//  Barangay Tugtug E-System — Blotter Form Submission JS
//  File: Javascript/blotterform.js
//  Used by: blotterdemo.html
// ============================================================

function validateAndSubmit() {
    const form = document.getElementById("blotterForm");

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const btn = document.querySelector(".submit-btn");
    btn.textContent = "Submitting…";
    btn.disabled    = true;

    const data = {
        full_name:         form.querySelector('[name="name"]').value.trim(),
        age:               form.querySelector('[name="age"]').value,
        civil_status:      form.querySelector('[name="status"]').value.trim(),
        address:           form.querySelector('[name="address"]').value.trim(),
        occupation:        form.querySelector('[name="occupation"]').value.trim(),
        petsa:             form.querySelector('[name="incident_date"]').value,
        oras:              form.querySelector('[name="incident_time"]').value,
        complaint_against: form.querySelector('[name="complainant_name"]').value.trim(),
        complaint_type:    form.querySelector('[name="complaint_type"]')
                               ? form.querySelector('[name="complaint_type"]').value.trim()
                               : "",
        complaint_details: form.querySelector('[name="complaint_details"]').value.trim(),
    };

    fetch("php/submit_blotter.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(data),
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            // Store reference number for the thank-you page
            sessionStorage.setItem("blotter_ref", result.reference_number);
            window.location.href = "blotterthankyou.html";
        } else {
            alert("Error: " + (result.message || "Submission failed."));
            btn.textContent = "Submit Blotter Report";
            btn.disabled    = false;
        }
    })
    .catch(() => {
        alert("Server error. Please try again.");
        btn.textContent = "Submit Blotter Report";
        btn.disabled    = false;
    });
}
