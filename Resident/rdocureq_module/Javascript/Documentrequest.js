// ============================================================
//  Barangay Tugtug E-System — Document Records Logic
//  File: Javascript/Documentrequest.js
// ============================================================

document.addEventListener("DOMContentLoaded", function () {
  const recordsContainer = document.querySelector(".document-records");
  const searchInput = document.querySelector(".search-input");
  const filterSelect = document.querySelector(".filter-select");
  const btnDisplay = document.querySelector(".btn-display");
  const btnPrint = document.querySelector(".btn-print");

  // ── Inject date range inputs into filter group ────────────
  const filterGroup = document.querySelector(".filter-group");
  const dateWrapper = document.createElement("div");
  dateWrapper.id = "date-filter-wrapper";
  dateWrapper.style.cssText = `
        display: none; align-items: center; gap: 0.5vw;
        position: relative; left: 6vh;
    `;
  dateWrapper.innerHTML = `
        <label style="color:#f3efe8;font-size:1.5vh;font-weight:600;white-space:nowrap;">From:</label>
        <input type="date" id="date-from" style="
            background:#f3efe8;border:none;border-radius:5px;
            padding:5px 8px;color:#273b07;font-size:1.4vh;font-weight:600;cursor:pointer;outline:none;">
        <label style="color:#f3efe8;font-size:1.5vh;font-weight:600;white-space:nowrap;">To:</label>
        <input type="date" id="date-to" style="
            background:#f3efe8;border:none;border-radius:5px;
            padding:5px 8px;color:#273b07;font-size:1.4vh;font-weight:600;cursor:pointer;outline:none;">
        <button id="btn-apply-date" style="
            background:#7d9e3b;color:white;border:none;border-radius:5px;
            padding:5px 12px;font-weight:bold;cursor:pointer;font-size:1.4vh;transition:0.3s;">
            Apply
        </button>
    `;
  filterGroup.insertBefore(dateWrapper, document.querySelector(".btn-display"));

  // ── Show/hide date inputs ─────────────────────────────────
  filterSelect.addEventListener("change", function () {
    if (filterSelect.value === "date") {
      dateWrapper.style.display = "flex";
    } else {
      dateWrapper.style.display = "none";
      fetchRecords();
    }
  });

  document.addEventListener("click", function (e) {
    if (e.target && e.target.id === "btn-apply-date") fetchRecords();
  });

  // ── Status colors (including Released) ───────────────────
  const statusColors = {
    Pending:    { bg: "#fff3cd", color: "#856404", border: "#ffc107" },
    Processing: { bg: "#cfe2ff", color: "#084298", border: "#0d6efd" },
    Ready:      { bg: "#d1e7dd", color: "#0a3622", border: "#198754" },
    Released:   { bg: "#e2d9f3", color: "#4a235a", border: "#8b5cf6" },
  };

  // ── Init ──────────────────────────────────────────────────
  fetchRecords();

  // ── Events ────────────────────────────────────────────────
  btnDisplay.addEventListener("click", () => {
    filterSelect.value = "";
    dateWrapper.style.display = "none";
    document.getElementById("date-from").value = "";
    document.getElementById("date-to").value = "";
    searchInput.value = "";
    fetchRecords();
  });
  searchInput.addEventListener("input", debounce(() => fetchRecords(), 400));

  // ── Dashboard Print All — fetches all records and prints a full report ──
  btnPrint.addEventListener("click", () => {
    const origText = btnPrint.textContent;
    btnPrint.textContent = "Loading…";
    btnPrint.disabled = true;

    fetch("php/GetDocuments.php")
      .then(res => res.json())
      .then(data => {
        btnPrint.textContent = origText;
        btnPrint.disabled = false;
        if (!data.success || !data.records) { showToast("❌ Could not load records for printing."); return; }
        printAllDocumentRecords(data.records, data.counts);
      })
      .catch(() => {
        btnPrint.textContent = origText;
        btnPrint.disabled = false;
        showToast("❌ Server error while loading records.");
      });
  });

  // ── Print ALL document request records ───────────────────
  function printAllDocumentRecords(records, counts) {
    function safe(v) { return v ? String(v).replace(/</g,"&lt;").replace(/>/g,"&gt;") : "—"; }
    function fmtD(d) {
      if (!d) return "—";
      return new Date(d).toLocaleDateString("en-PH", { year:"numeric", month:"short", day:"numeric" });
    }
    function fullName(r) {
      return [r.first_name, r.middle_initial ? r.middle_initial + "." : "", r.last_name]
        .filter(Boolean).join(" ").trim() || "—";
    }
    function stayStr(r) {
      const y = parseInt(r.length_stay_years)  || 0;
      const m = parseInt(r.length_stay_months) || 0;
      if (!y && !m) return "—";
      return (y ? y + " yr" + (y !== 1 ? "s" : "") : "") +
             (y && m ? " " : "") +
             (m ? m + " mo" + (m !== 1 ? "s" : "") : "");
    }
    function priceStr(r) {
      if (!r.price || r.price == 0) return "Free";
      return "₱" + parseFloat(r.price).toFixed(2);
    }
    function td2() { return "padding:5px 7px;border:1px solid #d0d0d0;font-size:10.5px;vertical-align:top;"; }

    const rows = records.map((r, i) => `
      <tr style="background:${i % 2 === 0 ? "#fafaf7" : "#f3efe8"};">
        <td style="${td2()}text-align:center;">${safe(r.request_ID)}</td>
        <td style="${td2()}">${safe(r.document_refnumber)}</td>
        <td style="${td2()}">${safe(fullName(r))}</td>
        <td style="${td2()}text-align:center;">${safe(r.sex)}</td>
        <td style="${td2()}text-align:center;">${safe(r.age)}</td>
        <td style="${td2()}text-align:center;">${safe(r.contact)}</td>
        <td style="${td2()}">${safe(r.document_type)}</td>
        <td style="${td2()}">${safe(r.document_purpose)}</td>
        <td style="${td2()}text-align:center;">${safe(r.quantity)}</td>
        <td style="${td2()}text-align:center;">${safe(priceStr(r))}</td>
        <td style="${td2()}text-align:center;">${fmtD(r.date)}</td>
        <td style="${td2()}text-align:center;">${fmtD(r.date_released)}</td>
        <td style="${td2()}text-align:center;">${safe(r.length_stay_years || 0) + "y " + safe(r.length_stay_months || 0) + "m"}</td>
        <td style="${td2()}">${safe(r.status)}</td>
      </tr>`).join("");

    const c = counts || {};
    const todayStr = new Date().toLocaleDateString("en-PH", { year:"numeric", month:"long", day:"numeric" });

    const win = window.open("", "_blank", "width=1200,height=820");
    win.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Document Requests — All Records</title>
<style>
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Times New Roman',Times,serif; font-size:11px; color:#000; background:#fff; padding:28px 36px; }
  @media print {
    body { padding:0; }
    @page { size: A3 landscape; margin: 1.2cm 1.5cm; }
    .no-print { display:none !important; }
  }
  .print-btn {
    display:block; margin:0 auto 18px; padding:9px 28px;
    background:#032f15; color:#fff; border:none; border-radius:6px;
    font-size:13px; font-weight:bold; cursor:pointer; letter-spacing:1px;
  }
  .print-btn:hover { background:#054d22; }

  /* Header */
  .header { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .header-logo { width:60px; height:auto; }
  .header-text { flex:1; text-align:center; }
  .header-text .sm   { font-size:9.5px; margin:1px 0; }
  .header-text .brgy { font-size:18px; font-weight:bold; color:#032f15; margin:2px 0; }
  .header-text .off  { font-size:11px; font-weight:bold; }

  /* Title */
  .report-title {
    text-align:center; font-size:13px; font-weight:bold; letter-spacing:2px;
    border-top:2px solid #000; border-bottom:2px solid #000;
    padding:4px 0; margin:6px 0 12px;
  }

  /* Summary bar — compact single row */
  .summary-bar {
    display:table; width:100%; border-collapse:collapse;
    border:1px solid #c8c8c8; border-radius:4px; margin-bottom:12px;
    background:#f9f9f4;
  }
  .sum-card {
    display:table-cell; text-align:center; padding:5px 10px;
    border-right:1px solid #ddd; vertical-align:middle;
  }
  .sum-card:last-child { border-right:none; }
  .sum-card .num { font-size:13px; font-weight:bold; color:#032f15; display:inline; }
  .sum-card .lbl { font-size:9.5px; text-transform:uppercase; color:#555; letter-spacing:0.4px; display:inline; margin-left:4px; }

  /* Table */
  table.records { width:100%; border-collapse:collapse; }
  table.records thead tr { background:#273b07; color:#f3efe8; }
  table.records thead th {
    padding:6px 7px; text-align:left; font-size:10px;
    font-weight:600; white-space:nowrap; border:1px solid #1a2d05;
  }

  /* Footer */
  .footer { margin-top:14px; padding-top:6px; border-top:1px solid #ccc; font-size:9px; color:#888; text-align:center; }
</style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">&#128424;&#65039; Print / Save as PDF</button>

<div class="header">
  <img class="header-logo" src="../photos/logo.png.png" alt="Logo" onerror="this.style.display='none'">
  <div class="header-text">
    <p class="sm">Republic of the Philippines &nbsp;|&nbsp; PROVINCE OF BATANGAS &nbsp;|&nbsp; Municipality of San Jose</p>
    <p class="brgy">Barangay Tugtug</p>
    <p class="off">OFFICE OF THE PUNONG BARANGAY</p>
  </div>
  <div style="min-width:70px;text-align:right;font-size:9px;color:#555;">
    Printed:<br><strong>${todayStr}</strong>
  </div>
</div>

<div class="report-title">DOCUMENT REQUESTS — ALL RECORDS</div>

<div class="summary-bar">
  <div class="sum-card"><span class="num">${c.Total || 0}</span> <span class="lbl">Total Records</span></div>
  <div class="sum-card"><span class="num">${c.Pending || 0}</span> <span class="lbl">Pending</span></div>
  <div class="sum-card"><span class="num">${c.Processing || 0}</span> <span class="lbl">Processing</span></div>
  <div class="sum-card"><span class="num">${c.Ready || 0}</span> <span class="lbl">Ready</span></div>
  <div class="sum-card"><span class="num">${c.Released || 0}</span> <span class="lbl">Released</span></div>
</div>

<table class="records">
  <thead>
    <tr>
      <th>Req. ID</th>
      <th>Ref No.</th>
      <th>Full Name</th>
      <th>Sex</th>
      <th>Age</th>
      <th>Contact</th>
      <th>Document Type</th>
      <th>Purpose</th>
      <th>Qty</th>
      <th>Price</th>
      <th>Date Requested</th>
      <th>Date Released</th>
      <th>Length of Stay</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>${rows}</tbody>
</table>

<div class="footer">
  Total Records: ${records.length} &nbsp;|&nbsp; Barangay Tugtug E-System &nbsp;|&nbsp; Printed on: ${todayStr}
</div>

</body></html>\`);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 600);
  }

  // Delegated click for dynamically rendered Update buttons
  recordsContainer.addEventListener("click", function (e) {
    const btn = e.target.closest(".btn-update-record");
    if (!btn) return;
    const rec = JSON.parse(btn.getAttribute("data-record").replace(/&apos;/g, "'"));
    openUpdateModal(rec);
  });

  // ── Fetch ─────────────────────────────────────────────────
  function fetchRecords() {
    const search = searchInput.value.trim();
    const filter = filterSelect.value;
    const dateFrom = document.getElementById("date-from")
      ? document.getElementById("date-from").value : "";
    const dateTo = document.getElementById("date-to")
      ? document.getElementById("date-to").value : "";

    let url = "php/GetDocuments.php?";
    if (search) url += "search=" + encodeURIComponent(search) + "&";
    if (filter && filter !== "date") url += "filter=" + encodeURIComponent(filter) + "&";
    if (filter === "date" && dateFrom) url += "date_from=" + encodeURIComponent(dateFrom) + "&";
    if (filter === "date" && dateTo) url += "date_to=" + encodeURIComponent(dateTo) + "&";

    recordsContainer.innerHTML = `
            <div style="display:flex;justify-content:center;align-items:center;
                height:30vh;color:#375309;font-family:'Segoe UI',sans-serif;font-size:2vh;gap:1vw;">
                <span style="width:2.5vh;height:2.5vh;border:3px solid #375309;
                    border-top-color:transparent;border-radius:50%;
                    display:inline-block;animation:spin 0.7s linear infinite;"></span>
                Loading records…
            </div>`;

    fetch(url)
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) { showError(data.message || "Failed to load records."); return; }
        updateCounts(data.counts);
        renderTable(data.records);
      })
      .catch((err) => { console.error(err); showError("Server error. Please try again."); });
  }

  // ── Summary cards ─────────────────────────────────────────
  function updateCounts(counts) {
    setCount(document.querySelector(".total"),      counts.Total      || 0);
    setCount(document.querySelector(".processing"), counts.Processing || 0);
    setCount(document.querySelector(".pending"),    counts.Pending    || 0);
    setCount(document.querySelector(".ready"),      counts.Ready      || 0);
  }
  function setCount(el, count) {
    if (!el) return;
    let numEl = el.querySelector(".count-number");
    if (!numEl) {
      numEl = document.createElement("p");
      numEl.className = "count-number";
      numEl.style.cssText = `position:absolute;bottom:1vh;left:1vw;
                font-size:4vh;font-weight:700;color:#273b07;margin:0;
                font-family:'Segoe UI',Tahoma,sans-serif;`;
      el.appendChild(numEl);
    }
    numEl.textContent = count;
  }

  // ── Render table ──────────────────────────────────────────
  function renderTable(records) {
    recordsContainer.style.cssText = `
            position: relative;
            top: 12vh;
            padding: 0 1.5%;
            padding-bottom: 3vh;
        `;

    if (!records || records.length === 0) {
      recordsContainer.innerHTML = `
                <div style="display:flex;justify-content:center;align-items:center;
                    height:30vh;color:#888;font-family:'Segoe UI',sans-serif;font-size:2vh;">
                    No records found.</div>`;
      return;
    }

    const table = document.createElement("table");
    table.style.cssText = `width:100%;border-collapse:collapse;
            font-family:'Segoe UI',Tahoma,sans-serif;font-size:1.6vh;`;

    table.innerHTML = `
            <thead>
                <tr style="background-color:#273b07;color:#f3efe8;position:sticky;top:0;z-index:1;">
                    <th style="${th()}">📋 Req. ID</th>
                    <th style="${th()}">🪪 Ref No.</th>
                    <th style="${th()}">👤 Full Name</th>
                    <th style="${th()}">⚧ Sex</th>
                    <th style="${th()}">📞 Contact</th>
                    <th style="${th()}">📄 Document Purpose</th>
                    <th style="${th()}">📅 Date Requested</th>
                    <th style="${th()}">📅 Document Type</th>
                    <th style="${th()}">🔢 Qty</th>
                    <th style="${th()}">🔖 Status</th>
                    <th style="${th()}">⚙ Action</th>
                </tr>
            </thead>
            <tbody id="records-tbody"></tbody>`;

    const tbody = table.querySelector("#records-tbody");

    records.forEach((rec, i) => {
      const tr = document.createElement("tr");
      const isReleased = rec.status === "Released";
      tr.style.cssText = `background-color:${i % 2 === 0 ? "#fafaf7" : "#f3efe8"};transition:background-color 0.2s;
        ${isReleased ? "opacity:0.82;" : ""}`;
      tr.onmouseover = () => (tr.style.backgroundColor = "#e8f0d8");
      tr.onmouseout  = () => (tr.style.backgroundColor = i % 2 === 0 ? "#fafaf7" : "#f3efe8");

      const fullName =
        `${rec.first_name || ""} ${rec.middle_initial ? rec.middle_initial + ". " : ""}${rec.last_name || ""}`.trim();
      const sc = statusColors[rec.status] || { bg: "#eee", color: "#333", border: "#aaa" };

      // Lock icon overlay on the Update button if Released
      const btnLabel = isReleased
        ? `🔒 View / Unlock`
        : `✏ Update`;
      const btnStyle = isReleased
        ? `background:#6c3483;`
        : `background:#375309;`;
      const btnHover = isReleased
        ? `this.style.background='#8b5cf6'`
        : `this.style.background='#7d9e3b'`;
      const btnOut = isReleased
        ? `this.style.background='#6c3483'`
        : `this.style.background='#375309'`;

      tr.innerHTML = `
                <td style="${td()}text-align:center;">${rec.request_ID}</td>
                <td style="${td()}text-align:center;">${rec.document_refnumber}</td>
                <td style="${td()}">${fullName}</td>
                <td style="${td()}text-align:center;">${rec.sex || "—"}</td>
                <td style="${td()}text-align:center;">${rec.contact || "—"}</td>
                <td style="${td()}">${rec.document_purpose || "—"}</td>
                <td style="${td()}text-align:center;">${fmtDate(rec.date)}</td>
                <td style="${td()}text-align:center;">${rec.document_type}</td>
                <td style="${td()}text-align:center;">${rec.quantity || 0}</td>
                <td style="${td()}text-align:center;">
                    <span style="background:${sc.bg};color:${sc.color};border:1px solid ${sc.border};
                        padding:0.4vh 0.8vw;border-radius:20px;font-size:1.4vh;font-weight:600;white-space:nowrap;">
                        ${rec.status}
                    </span>
                </td>
                <td style="${td()}text-align:center;">
                    <button class="btn-update-record"
                        data-record='${JSON.stringify(rec).replace(/'/g, "&apos;")}'
                        style="${btnStyle}color:#f3efe8;border:none;border-radius:5px;
                            padding:0.5vh 0.8vw;cursor:pointer;font-size:1.4vh;font-weight:600;
                            transition:background 0.2s;white-space:nowrap;"
                        onmouseover="${btnHover}"
                        onmouseout="${btnOut}">
                        ${btnLabel}
                    </button>
                </td>`;
      tbody.appendChild(tr);
    });

    recordsContainer.innerHTML = "";
    recordsContainer.appendChild(table);
  }

  function th() {
    return `padding:1.2vh 1vw;text-align:left;font-size:1.5vh;font-weight:600;white-space:nowrap;`;
  }
  function td() {
    return `padding:1vh 1vw;border-bottom:1px solid #ddd;`;
  }
  function fmtDate(d) {
    if (!d) return "—";
    return new Date(d).toLocaleDateString("en-PH", {
      year: "numeric", month: "short", day: "numeric",
    });
  }
  function showError(msg) {
    recordsContainer.innerHTML = `<div style="display:flex;justify-content:center;align-items:center;
            height:30vh;color:#cc0000;font-family:'Segoe UI',sans-serif;font-size:2vh;">${msg}</div>`;
  }
  function debounce(fn, delay) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  // ── CSS ───────────────────────────────────────────────────
  const style = document.createElement("style");
  style.textContent = `
        @keyframes spin { to { transform:rotate(360deg); } }

        .modal-overlay {
            position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;
            display:flex;align-items:center;justify-content:center;animation:fadeIn 0.2s ease;
        }
        .modal-box {
            background:#f3efe8;border-radius:15px;padding:3vh 2.5vw;width:38vw;min-width:320px;
            box-shadow:0 10px 40px rgba(0,0,0,0.3);font-family:'Segoe UI',Tahoma,sans-serif;
        }
        .modal-title { font-size:2.2vh;font-weight:700;color:#273b07;margin-bottom:2vh;font-family:'Crimson Text',serif; }
        .modal-label { font-size:1.6vh;color:#375309;font-weight:600;margin-bottom:0.5vh;display:block; }
        .modal-select { width:100%;padding:1vh 1vw;border-radius:8px;border:1.5px solid #7d9e3b;
            font-size:1.7vh;background:white;color:#273b07;margin-bottom:2.5vh;cursor:pointer;outline:none; }
        .modal-select:focus { border-color:#375309; }
        .modal-buttons { display:flex;gap:1vw;justify-content:flex-end; }
        .modal-btn-cancel { background:transparent;border:2px solid #375309;color:#375309;
            padding:0.8vh 1.5vw;border-radius:8px;font-size:1.6vh;font-weight:600;cursor:pointer;transition:0.2s; }
        .modal-btn-cancel:hover { background:#375309;color:white; }
        .modal-btn-save { background:#375309;border:none;color:#f3efe8;padding:0.8vh 1.5vw;
            border-radius:8px;font-size:1.6vh;font-weight:600;cursor:pointer;transition:0.2s; }
        .modal-btn-save:hover { background:#7d9e3b; }
        .modal-resident { font-size:1.5vh;color:#555;margin-bottom:2vh; }

        /* Released lock banner */
        .locked-banner {
            background:#f3e8ff;border:1.5px solid #8b5cf6;border-radius:10px;
            padding:1.2vh 1.2vw;margin-bottom:2vh;display:flex;align-items:flex-start;gap:0.8vw;
        }
        .locked-banner-icon { font-size:2.2vh;flex-shrink:0;margin-top:0.1vh; }
        .locked-banner-text { font-size:1.4vh;color:#4a235a;line-height:1.6; }
        .locked-banner-text strong { font-size:1.5vh; }

        /* Confirmation modal */
        .confirm-overlay {
            position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:10999;
            display:flex;align-items:center;justify-content:center;animation:fadeIn 0.15s ease;
        }
        .confirm-box {
            background:#fff;border-radius:15px;padding:3vh 2.5vw;width:32vw;min-width:280px;
            box-shadow:0 12px 50px rgba(0,0,0,0.4);font-family:'Segoe UI',Tahoma,sans-serif;
            text-align:center;
        }
        .confirm-icon { font-size:4.5vh;margin-bottom:1.5vh; }
        .confirm-title { font-size:2vh;font-weight:700;color:#273b07;margin-bottom:1vh; }
        .confirm-msg { font-size:1.5vh;color:#555;margin-bottom:0.8vh;line-height:1.6; }
        .confirm-note {
            background:#fff8e6;border:1.5px solid #ffc107;border-radius:8px;
            padding:1vh 1vw;font-size:1.35vh;color:#856404;margin:1.5vh 0 2vh;
            text-align:left;line-height:1.6;
        }
        .confirm-note strong { display:block;margin-bottom:0.3vh; }
        .confirm-buttons { display:flex;gap:1vw;justify-content:center; }
        .confirm-btn-no {
            background:transparent;border:2px solid #273b07;color:#273b07;
            padding:0.8vh 2vw;border-radius:8px;font-size:1.6vh;font-weight:600;cursor:pointer;transition:0.2s;
        }
        .confirm-btn-no:hover { background:#273b07;color:#f3efe8; transform: translateY(-10%);}
        .confirm-btn-yes {
            background:#273b07;border:none;color:#f3efe8;
            padding:0.8vh 2vw;border-radius:8px;font-size:1.6vh;font-weight:600;cursor:pointer;transition:0.2s;
        }
        .confirm-btn-yes:hover { background:#7d9e3b; transform: translateY(-10%);}

        /* Locked fields dim overlay */
        .status-locked-note {
            font-size:1.35vh;color:#6c3483;font-style:italic;margin-top:-1.5vh;margin-bottom:2vh;
            display:flex;align-items:center;gap:0.4vw;
        }

        .toast { position:fixed;bottom:4vh;right:2vw;background:#273b07;color:#f3efe8;
            padding:1.5vh 2vw;border-radius:10px;font-size:1.7vh;font-family:'Segoe UI',sans-serif;
            z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,0.3);animation:slideUp 0.3s ease; }
        @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
        @keyframes fadeIn  { from{opacity:0} to{opacity:1} }
    `;
  document.head.appendChild(style);

  // ── Modal ─────────────────────────────────────────────────
  function openUpdateModal(rec) {
    const existing = document.getElementById("update-modal");
    if (existing) existing.remove();

    const isReleased = rec.status === "Released";

    const fullName =
      `${rec.first_name || ""} ${rec.middle_initial ? rec.middle_initial + ". " : ""}${rec.last_name || ""}`.trim();

    function field(label, value) {
      return `
                <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:0.8vh 0;border-bottom:1px solid #dde8cc;">
                    <span style="font-size:1.4vh;color:#666;font-weight:600;min-width:40%;">${label}</span>
                    <span style="font-size:1.5vh;color:#273b07;text-align:right;">${value || "—"}</span>
                </div>`;
    }

    const priceDisplay =
      !rec.price || rec.price == 0
        ? '<span style="color:#0a3622;font-weight:700;">Free</span>'
        : `<span style="color:#0a3622;font-weight:700;">₱${parseFloat(rec.price).toFixed(2)}</span>`;

    // Status badge colors
    const sc = statusColors[rec.status] || { bg: "#eee", color: "#333", border: "#aaa" };
    const statusBadge = `<span style="
        margin-left:1vw;padding:0.3vh 0.8vw;border-radius:20px;font-size:1.4vh;
        background:${sc.bg};color:${sc.color};border:1px solid ${sc.border};">
        ${rec.status}
    </span>`;

    // Build status select options — always show all 4
    const statuses = ["Pending", "Processing", "Ready", "Released"];
    const statusOptions = statuses.map(s =>
      `<option value="${s}" ${rec.status === s ? "selected" : ""}>${s}</option>`
    ).join("");

    // Lock banner shown only when Released
    const lockBanner = isReleased ? `
        <div class="locked-banner">
            <span class="locked-banner-icon">🔒</span>
            <div class="locked-banner-text">
                <strong>This record is locked.</strong><br>
                This document has been marked as <strong>Released</strong> and is now locked for security purposes.
                You can still view all information. To make changes, unlock the record by selecting a different status and saving.
            </div>
        </div>` : "";

    // Select is always enabled — unlock button controls it visually
    const selectDisabled = "";
    const selectNote = "";

    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";
    overlay.id = "update-modal";
    overlay.innerHTML = `
            <div class="modal-box" style="width:38vw;min-width:320px;max-height:85vh;overflow-y:auto;">
                <div class="modal-title">Request Details #${rec.request_ID}</div>

                ${lockBanner}

                <div style="background:#e8f0d8;border-radius:10px;padding:1.5vh 1.5vw;margin-bottom:2vh;">
                    <div style="font-size:1.3vh;color:#375309;font-weight:700;margin-bottom:1vh;letter-spacing:0.05em;">
                        RESIDENT INFORMATION
                    </div>
                    ${field("Resident ID", rec.resident_ID)}
                    ${field("Full Name", fullName)}
                    ${field("Sex", rec.sex)}
                    ${field("Birthdate", rec.birthdate ? fmtDate(rec.birthdate) : "—")}
                </div>

                <div style="background:#f0ede4;border-radius:10px;padding:1.5vh 1.5vw;margin-bottom:2vh;">
                    <div style="font-size:1.3vh;color:#375309;font-weight:700;margin-bottom:1vh;letter-spacing:0.05em;">
                        REQUEST INFORMATION
                    </div>
                    ${field("Request ID", rec.request_ID)}
                    ${field("Reference Number", rec.document_refnumber)}
                    ${field("Document Type", rec.document_type || "—")}
                    ${field("Contact", rec.contact)}
                    ${field("Document Purpose", rec.document_purpose)}
                    ${field("Date Requested", fmtDate(rec.date))}
                    ${field("Date Released", rec.date_released ? fmtDate(rec.date_released) : "—")}
                    ${field("Age", rec.age)}
                    ${field("Length of Stay", (rec.length_stay_years || 0) + " yr(s) " + (rec.length_stay_months || 0) + " mo(s)")}
                    ${field("Quantity", rec.quantity)}
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.8vh 0;">
                        <span style="font-size:1.4vh;color:#666;font-weight:600;min-width:40%;">Price</span>
                        ${priceDisplay}
                    </div>
                </div>

                <div style="background:${isReleased ? "#f3e8ff" : "#fff8e6"};border-radius:10px;padding:1.5vh 1.5vw;margin-bottom:2vh;
                    border:${isReleased ? "1.5px solid #8b5cf6" : "none"};">
                    <div style="font-size:1.3vh;color:${isReleased ? "#6c3483" : "#856404"};font-weight:700;margin-bottom:1vh;letter-spacing:0.05em;
                        display:flex;align-items:center;justify-content:space-between;">
                        <span>${isReleased ? "🔒 RECORD STATUS (LOCKED)" : "UPDATE STATUS"}</span>
                        ${isReleased ? `
                        <button id="btn-unlock-record" style="
                            background:#6c3483;color:white;border:none;border-radius:6px;
                            padding:0.5vh 1vw;font-size:1.3vh;font-weight:700;cursor:pointer;
                            transition:background 0.2s;display:flex;align-items:center;gap:0.4vw;"
                            onmouseover="this.style.background='#8b5cf6'"
                            onmouseout="this.style.background='#6c3483'"
                            onclick="unlockRecord()">
                            🔓 Unlock to Edit
                        </button>` : ""}
                    </div>
                    <label class="modal-label">Current Status: ${statusBadge}</label>
                    <label class="modal-label" style="margin-top:1.5vh;">New Status</label>
                    <select class="modal-select" id="modal-status-select"
                        style="${isReleased ? "opacity:0.5;cursor:not-allowed;pointer-events:none;" : ""}">
                        ${statusOptions}
                    </select>
                    ${isReleased ? `
                    <div id="locked-hint" style="font-size:1.35vh;color:#6c3483;background:#ede7f6;padding:0.8vh 1vw;
                        border-radius:7px;margin-top:-1vh;margin-bottom:0.5vh;">
                        🔒 Click <strong>"Unlock to Edit"</strong> above to change the status.
                        If you set it to a non-Released status, the date released will be cleared automatically.
                    </div>` : ""}
                </div>

                <div class="modal-buttons">
                    <button class="modal-btn-cancel" onclick="document.getElementById('update-modal').remove()">Cancel</button>
                    <button class="modal-btn-save" id="modal-save-btn" onclick="saveStatus(${rec.request_ID})">
                        Save Changes
                    </button>
                </div>
            </div>`;

    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) overlay.remove();
    });
    document.body.appendChild(overlay);

    // Unlock button handler — clears date_released in DB immediately, then enables the select
    window.unlockRecord = function () {
      const select    = document.getElementById("modal-status-select");
      const hint      = document.getElementById("locked-hint");
      const unlockBtn = document.getElementById("btn-unlock-record");

      if (unlockBtn) { unlockBtn.disabled = true; unlockBtn.textContent = "Unlocking…"; }

      // POST to PHP: keep current status but signal to wipe date_released NOW
      fetch("php/GetDocuments.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          request_ID: rec.request_ID,
          status: rec.status,
          clear_date_released: true
        }),
      })
        .then(r => r.json())
        .then(() => {
          select.style.opacity       = "1";
          select.style.cursor        = "pointer";
          select.style.pointerEvents = "auto";
          select.value = "Pending";
          if (hint)      hint.style.display      = "none";
          if (unlockBtn) unlockBtn.style.display  = "none";
        })
        .catch(() => {
          if (unlockBtn) { unlockBtn.disabled = false; unlockBtn.textContent = "🔓 Unlock to Edit"; }
          showToast("❌ Failed to unlock. Please try again.");
        });
    };
  }

  // ── Save status (with confirmation if setting to Released) ─
  window.saveStatus = function (requestId) {
    const selectEl = document.getElementById("modal-status-select");
    const newStatus = selectEl.value;

    // If saving AS Released → show confirmation first
    if (newStatus === "Released") {
      showReleaseConfirmation(requestId);
      return;
    }

    // Otherwise just save directly
    doSave(requestId, newStatus);
  };

  function showReleaseConfirmation(requestId) {
    const existing = document.getElementById("confirm-modal");
    if (existing) existing.remove();

    // Pre-fill today's date in PH local time (YYYY-MM-DD)
    const todayLocal = new Date();
    const yyyy = todayLocal.getFullYear();
    const mm   = String(todayLocal.getMonth() + 1).padStart(2, "0");
    const dd   = String(todayLocal.getDate()).padStart(2, "0");
    const todayStr = `${yyyy}-${mm}-${dd}`;

    const confirmOverlay = document.createElement("div");
    confirmOverlay.className = "confirm-overlay";
    confirmOverlay.id = "confirm-modal";
    confirmOverlay.innerHTML = `
        <div class="confirm-box">
            <div class="confirm-title">Mark Document as Released?</div>
            <div class="confirm-msg">
                Are you sure this document has been <strong>released</strong> to the resident?
            </div>
            <div class="confirm-note">
                <strong>📌 Please Note:</strong>
                Once confirmed, this record will be <strong>locked</strong> to prevent accidental changes.<br><br>
                🔓 You can <b>unlock</b> this record at any time by opening it and selecting a different status.
            </div>
            <div style="margin:1.5vh 0 2vh;text-align:left;">
                <label style="font-size:1.45vh;font-weight:700;color:#273b07;display:block;margin-bottom:0.6vh;">
                    📅 Date Released
                </label>
                <input type="date" id="release-date-input"
                    value="${todayStr}"
                    max="${todayStr}"
                    style="width:100%;padding:0.9vh 0.8vw;border:1.5px solid #7d9e3b;border-radius:8px;
                        font-size:1.6vh;color:#273b07;background:#fff;outline:none;cursor:pointer;
                        box-sizing:border-box;">
                <span style="font-size:1.25vh;color:#888;display:block;margin-top:0.4vh;">
                    You may change this date if needed (cannot be a future date).
                </span>
            </div>
            <div class="confirm-buttons">
                <button class="confirm-btn-no" onclick="document.getElementById('confirm-modal').remove()">
                    No, Go Back
                </button>
                <button class="confirm-btn-yes" onclick="confirmRelease(${requestId})">
                    ✅ Yes, Mark as Released
                </button>
            </div>
        </div>`;
    document.body.appendChild(confirmOverlay);
  }

  window.confirmRelease = function (requestId) {
    const dateInput = document.getElementById("release-date-input");
    const chosenDate = dateInput ? dateInput.value : "";

    if (!chosenDate) {
      dateInput.style.border = "1.5px solid #cc0000";
      dateInput.placeholder = "Please select a date";
      return;
    }

    document.getElementById("confirm-modal").remove();
    doSave(requestId, "Released", chosenDate);
  };

  function doSave(requestId, newStatus, dateReleased) {
    const saveBtn = document.getElementById("modal-save-btn");
    if (saveBtn) { saveBtn.textContent = "Saving…"; saveBtn.disabled = true; }

    const payload = { request_ID: requestId, status: newStatus };
    if (dateReleased) payload.date_released = dateReleased;

    fetch("php/GetDocuments.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((res) => res.json())
      .then((data) => {
        const modal = document.getElementById("update-modal");
        if (modal) modal.remove();
        showToast(
          data.success
            ? (newStatus === "Released"
                ? "✅ Document marked as Released and locked."
                : newStatus === "Pending" || newStatus === "Processing" || newStatus === "Ready"
                  ? "🔓 Record unlocked. Status updated to " + newStatus + "."
                  : "✅ Status updated to " + newStatus)
            : "❌ " + (data.message || "Update failed.")
        );
        if (data.success) fetchRecords();
      })
      .catch(() => {
        const modal = document.getElementById("update-modal");
        if (modal) modal.remove();
        showToast("❌ Server error. Please try again.");
      });
  }

  function showToast(msg) {
    const ex = document.querySelector(".toast");
    if (ex) ex.remove();
    const t = document.createElement("div");
    t.className = "toast";
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }
});
