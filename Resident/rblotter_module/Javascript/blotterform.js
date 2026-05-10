function validateAndSubmit() {
  const form = document.getElementById("blotterForm");

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const btn = document.querySelector(".submit-btn");
  btn.textContent = "Submitting…";
  btn.disabled = true;

  // Create a FormData object directly from the form element
  const formData = new FormData(form);

  fetch("php/submit_blotter.php", {
    method: "POST",
    // No "Content-Type" header needed; the browser sets it automatically for FormData
    body: formData,
  })
    .then((res) => res.json())
    .then((result) => {
      if (result.success) {
        sessionStorage.setItem("blotter_ref", result.reference_number);
        window.location.href = "blotterthankyou.html";
      } else {
        alert("Error: " + (result.message || "Submission failed."));
        btn.textContent = "Submit Blotter Report";
        btn.disabled = false;
      }
    })
    .catch(() => {
      alert("Server error. Please try again.");
      btn.textContent = "Submit Blotter Report";
      btn.disabled = false;
    });
}
