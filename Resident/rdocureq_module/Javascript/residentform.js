function validateAndSubmit() {
    const form = document.getElementById("personal-info-form");

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const btn = document.querySelector(".btn-submit");
    btn.textContent = "Submitting…";
    btn.disabled    = true;

    // ✅ Get the select element so we can read both the value (numeric ID)
    //    and the visible label text for display on the success page
    const certSelect     = form.querySelector('[name="certificate"]');
    const certValue      = certSelect.value;                            // e.g. "1"
    const certLabel      = certSelect.options[certSelect.selectedIndex].text; // e.g. "Poultry"

    const data = {
        first_name:   form.querySelector('[name="first_name"]').value.trim(),
        last_name:    form.querySelector('[name="last_name"]').value.trim(),
        middle_name:  form.querySelector('[name="middle_name"]').value.trim(),
        birthday:     form.querySelector('[name="birthday"]').value,
        age:          form.querySelector('[name="age"]').value,
        gender:       form.querySelector('[name="gender"]').value,
        civil_status: form.querySelector('[name="civil_status"]').value,
        contact:      form.querySelector('[name="contact"]').value.trim(),
        birthplace:   form.querySelector('[name="birthplace"]').value.trim(),
        stay_years:   form.querySelector('[name="stay_years"]').value,
        stay_months:  form.querySelector('[name="stay_months"]').value || 0,
        // ✅ Send numeric ID so PHP can use it directly as document_ID
        certificate:  certValue,
        quantity:     form.querySelector('[name="quantity"]').value,
        purpose:      form.querySelector('[name="purpose"]').value.trim(),
    };

    fetch("php/submit_document.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            // ✅ Store ref number AND readable certificate label for success page
            sessionStorage.setItem("document_ref",   result.reference_number);
            sessionStorage.setItem("document_type",  certLabel);
            sessionStorage.setItem("document_qty",   data.quantity);
            sessionStorage.setItem("document_name",  data.first_name + " " + data.last_name);
            window.location.href = "DocumentSuccess.html";
        } else {
            alert("Error: " + (result.message || "Submission failed."));
            btn.textContent = "Submit";
            btn.disabled    = false;
        }
    })
    .catch(() => {
        alert("Server error. Please try again.");
        btn.textContent = "Submit";
        btn.disabled    = false;
    });
}