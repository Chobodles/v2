// ============================================================
//  Barangay Tugtug E-System — Dashboard Logic
//  File: Javascript/Dashboard.js
// ============================================================

document.addEventListener("DOMContentLoaded", function () {
  // ── Latest 5 Document Requests ────────────────────────────
  function fetchLatestDocuments() {
    const container = document.querySelector(".document-latest-container");

    fetch("php/GetDocuments.php")
      .then((res) => res.json())
      .then((data) => {
        if (!data.success || !data.records) return;

        // Sort by date DESC, take top 5
        const sorted = [...data.records]
          .sort((a, b) => new Date(b.date) - new Date(a.date))
          .slice(0, 5);

        if (sorted.length === 0) {
          const label = container.querySelector(".dtitle");
          const empty = document.createElement("p");
          empty.style.cssText = `
            text-align:center;color:#888;font-family:'Segoe UI',sans-serif;
            font-size:1.6vh;margin-top:8vh;`;
          empty.textContent = "No recent requests.";
          container.appendChild(empty);
          return;
        }

        const list = document.createElement("div");
        list.style.cssText = `
          position:relative;
          top:8%;
          padding:0 5%;
        `;

        sorted.forEach((rec, i) => {
          const fullName = [
            rec.first_name,
            rec.middle_initial ? rec.middle_initial + "." : "",
            rec.last_name,
          ]
            .filter(Boolean)
            .join(" ");

          const dateStr = rec.date
            ? new Date(rec.date + "T00:00:00").toLocaleDateString("en-PH", {
                year: "numeric",
                month: "short",
                day: "numeric",
              })
            : "—";

          const statusColors = {
            Pending: { bg: "#fff3cd", color: "#856404" },
            Processing: { bg: "#cfe2ff", color: "#084298" },
            Ready: { bg: "#d1e7dd", color: "#0a3622" },
            Released: { bg: "#e2d9f3", color: "#4a235a" },
          };
          const sc = statusColors[rec.status] || { bg: "#eee", color: "#333" };

          const row = document.createElement("div");
          row.style.cssText = `
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:1.2vh 1vw;
            border-bottom:1px solid rgba(0,0,0,0.07);
            background:${i % 2 === 0 ? "#fcfaf7" : "#f3efe8"};
            border-radius:8px;
            margin-bottom:0.5vh;
            gap:0.5vw;
            transition:background 0.2s;
          `;
          row.onmouseover = () => (row.style.background = "#e8f0d8");
          row.onmouseout = () =>
            (row.style.background = i % 2 === 0 ? "#fcfaf7" : "#f3efe8");

          row.innerHTML = `
            <span style="
              font-family:'Segoe UI',sans-serif;font-size:1.35vh;
              color:#375309;font-weight:700;min-width:30%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${rec.document_refnumber || "—"}
            </span>
            <span style="
              font-family:'Segoe UI',sans-serif;font-size:1.35vh;
              color:#273b07;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:center;">
              ${fullName || "—"}
            </span>
            <span style="
              font-size:1.2vh;padding:3px 8px;border-radius:20px;
              background:${sc.bg};color:${sc.color};font-weight:600;
              white-space:nowrap;font-family:'Segoe UI',sans-serif;">
              ${rec.status}
            </span>
          `;

          list.appendChild(row);
        });

        container.appendChild(list);
      })
      .catch((err) => console.error("Dashboard doc fetch error:", err));
  }

  // ── Latest 5 Blotter Requests ─────────────────────────────
  function fetchLatestBlotters() {
    const container = document.querySelector(".blotter-latest-container");

    fetch("php/GetBlotter.php")
      .then((res) => res.json())
      .then((data) => {
        if (!data.success || !data.records) return;

        // Sort by submitted_at or petsa DESC, take top 5
        const sorted = [...data.records]
          .sort((a, b) => {
            const da = new Date(b.submitted_at || b.petsa || 0);
            const db = new Date(a.submitted_at || a.petsa || 0);
            return da - db;
          })
          .slice(0, 5);

        if (sorted.length === 0) {
          const empty = document.createElement("p");
          empty.style.cssText = `
            text-align:center;color:#888;font-family:'Segoe UI',sans-serif;
            font-size:1.6vh;margin-top:8vh;`;
          empty.textContent = "No recent blotter records.";
          container.appendChild(empty);
          return;
        }

        const list = document.createElement("div");
        list.style.cssText = `
          position:relative;
          top:8%;
          padding:0 5%;
        `;

        const statusColors = {
          Pending: { bg: "#fff3cd", color: "#856404" },
          Scheduled: { bg: "#cfe2ff", color: "#084298" },
          Resolved: { bg: "#d1e7dd", color: "#0a3622" },
          Escalated: { bg: "#e2d9f3", color: "#4a235a" },
        };

        sorted.forEach((rec, i) => {
          const sc = statusColors[rec.status] || { bg: "#eee", color: "#333" };

          const row = document.createElement("div");
          row.style.cssText = `
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:1.2vh 1vw;
            border-bottom:1px solid rgba(0,0,0,0.07);
            background:${i % 2 === 0 ? "#fcfaf7" : "#f3efe8"};
            border-radius:8px;
            margin-bottom:0.5vh;
            gap:0.5vw;
            transition:background 0.2s;
          `;
          row.onmouseover = () => (row.style.background = "#e8f0d8");
          row.onmouseout = () =>
            (row.style.background = i % 2 === 0 ? "#fcfaf7" : "#f3efe8");

          row.innerHTML = `
            <span style="
              font-family:'Segoe UI',sans-serif;font-size:1.35vh;
              color:#375309;font-weight:700;min-width:30%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${rec.reference_number || "—"}
            </span>
            <span style="
              font-family:'Segoe UI',sans-serif;font-size:1.35vh;
              color:#273b07;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:center;">
              ${rec.full_name || "—"}
            </span>
            <span style="
              font-size:1.2vh;padding:3px 8px;border-radius:20px;
              background:${sc.bg};color:${sc.color};font-weight:600;
              white-space:nowrap;font-family:'Segoe UI',sans-serif;">
              ${rec.status}
            </span>
          `;

          list.appendChild(row);
        });

        container.appendChild(list);
      })
      .catch((err) => console.error("Dashboard blotter fetch error:", err));
  }

  // ── Add column headers to the two latest containers ───────
  function addListHeader(container, label) {
    // The label element already exists in HTML (.dtitle / .btitle)
    // Just inject a column header row below it
    const header = document.createElement("div");
    header.style.cssText = `
      display:flex;
      justify-content:space-between;
      padding:0.8vh 1vw;
      margin: 0 5%;
      border-bottom:2px solid #375309;
      margin-top:4%;
    `;
    header.innerHTML = `
      <span style="font-family:'Segoe UI',sans-serif;font-size:1.3vh;font-weight:700;color:#375309;min-width:30%;">Ref No.</span>
      <span style="font-family:'Segoe UI',sans-serif;font-size:1.3vh;font-weight:700;color:#375309;flex:1;text-align:center;">Name</span>
      <span style="font-family:'Segoe UI',sans-serif;font-size:1.3vh;font-weight:700;color:#375309;">Status</span>
    `;
    container.appendChild(header);
  }

  // Add column headers to the panels
  addListHeader(
    document.querySelector(".document-latest-container"),
    "Document Requests",
  );
  addListHeader(
    document.querySelector(".blotter-latest-container"),
    "Blotter Requests",
  );

  // Fetch the latest records for both panels
  fetchLatestDocuments();
  fetchLatestBlotters();
});
