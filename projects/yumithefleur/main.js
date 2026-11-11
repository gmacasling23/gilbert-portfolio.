/* ══════════════════════════════════════════════════════════════
   main.js — Yumi the Fleur
══════════════════════════════════════════════════════════════ */

/* ── Modal helpers ───────────────────────────────────────────── */
function openModal(tab = "login") {

  closeMobileNav();

  switchTab(tab);

  document
    .getElementById("authModal")
    .classList.add("open");
}

function closeModal() {

  document
    .getElementById("authModal")
    .classList.remove("open");
}

function handleOverlay(e) {

  if (
    e.target ===
    document.getElementById("authModal")
  ) {
    closeModal();
  }
}

function switchTab(tab) {

  document
    .querySelectorAll(".m-tab")
    .forEach(t => t.classList.remove("active"));

  document
    .querySelectorAll(".fpanel")
    .forEach(p => p.classList.remove("active"));

  document
    .getElementById("mtab-" + tab)
    .classList.add("active");

  document
    .getElementById("fp-" + tab)
    .classList.add("active");
}

/* ── Navbar state ────────────────────────────────────────────── */
function updateNavbar(username) {

  const guestNav      = document.getElementById("guestNav");
  const userNav       = document.getElementById("userNav");
  const navUser       = document.getElementById("navUsername");
  const navAuthMobile = document.getElementById("navAuthMobile");

  if (username) {

    if (guestNav) {
      guestNav.style.display = "none";
    }

    if (userNav) {
      userNav.style.display = "flex";
    }

    if (navUser) {
      navUser.textContent = username;
    }

    if (navAuthMobile) {

      const span = document.createElement("span");
      span.style.cssText = "color:var(--deep-purple);font-weight:600;";
      span.textContent = username;

      const btn = document.createElement("button");
      btn.className = "btn-nav-login";
      btn.textContent = "Logout";
      btn.addEventListener("click", logout);

      navAuthMobile.replaceChildren(span, btn);
    }

  } else {

    if (guestNav) {
      guestNav.style.display = "";
    }

    if (userNav) {
      userNav.style.display = "none";
    }

    if (navUser) {
      navUser.textContent = "";
    }

    if (navAuthMobile) {

      navAuthMobile.innerHTML = `
        <button class="btn-nav-login"
                onclick="openModal('login')">
          Log in
        </button>

        <button class="btn-nav-signup"
                onclick="openModal('signup')">
          Sign up
        </button>
      `;
    }
  }
}


/* ── Dashboard State ───────────────────────────── */
function updateDashboard(username) {

  const guestDashboard  = document.getElementById("guestDashboard");
  const userDashboard   = document.getElementById("userDashboard");
  const welcomeText     = document.getElementById("welcomeText");

  if (username) {

    if (guestDashboard) guestDashboard.style.display = "none";
    if (userDashboard)  userDashboard.style.display  = "block";
    if (welcomeText)    welcomeText.textContent       = username;

    loadOrders();

  } else {

    if (guestDashboard) guestDashboard.style.display = "";
    if (userDashboard)  userDashboard.style.display  = "none";
  }
}


/* ── Mobile nav ──────────────────────────────────────────────── */
function closeMobileNav() {

  document
    .getElementById("mobileDrawer")
    ?.classList.remove("open");

  document
    .getElementById("hamburger")
    ?.classList.remove("open");
}

/* ══════════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════════ */
document.addEventListener(
  "DOMContentLoaded",
  function () {

    /* ── Restore login state ─────────────────────────────── */
    const saved =
      localStorage.getItem("username");

  if (saved) {

  updateNavbar(saved);

  updateDashboard(saved);

} else {

  updateDashboard(null);
}

    /* ── Hero stars ──────────────────────────────────────── */
    const heroStars =
      document.getElementById("heroStars");

    if (heroStars) {

      for (let i = 0; i < 28; i++) {

        const s = document.createElement("span");

        s.style.cssText = `
          position:absolute;
          width:${2 + Math.random() * 3}px;
          height:${2 + Math.random() * 3}px;
          border-radius:50%;
          background:#fff;
          top:${Math.random() * 100}%;
          left:${Math.random() * 100}%;
          opacity:${0.3 + Math.random() * 0.6};
          animation:twinkle ${2 + Math.random() * 3}s infinite alternate;
        `;

        heroStars.appendChild(s);
      }
    }

    /* ── Hamburger ───────────────────────────────────────── */
    const hamburger =
      document.getElementById("hamburger");

    const mobileDrawer =
      document.getElementById("mobileDrawer");

    if (hamburger && mobileDrawer) {

      hamburger.addEventListener(
        "click",
        function () {

          mobileDrawer.classList.toggle("open");

          hamburger.classList.toggle("open");
        }
      );
    }

    /* ══════════════════════════════════════════════════════
       LOGIN
    ══════════════════════════════════════════════════════ */
    const loginForm =
      document.getElementById("loginForm");

    if (loginForm) {

      loginForm.addEventListener(
        "submit",
        async function (e) {

          e.preventDefault();

          const err =
            document.getElementById("loginError");

          err.textContent = "";

          const fd = new FormData();

          fd.append(
            "username",
            loginForm.username.value.trim()
          );

          fd.append(
            "password",
            loginForm.password.value
          );

          try {

            const res = await fetch(
              "./login.php",
              {
                method: "POST",
                credentials: "same-origin",
                body: fd
              }
            );

            const data = await res.json();

            if (data.status === "success") {

              localStorage.setItem(
                "username",
                data.username
              );

              updateNavbar(data.username);

                updateDashboard(data.username);
                
                closeModal();

              loginForm.reset();

            } else {

              err.textContent =
                data.message ||
                "Login failed. Please try again.";
            }

          } catch (_) {

            err.textContent =
              "Connection error. Please try again.";
          }
        }
      );
    }

    /* ══════════════════════════════════════════════════════
       SIGNUP
    ══════════════════════════════════════════════════════ */
    const signupForm =
      document.getElementById("signupForm");

    if (signupForm) {

      signupForm.addEventListener(
        "submit",
        async function (e) {

          e.preventDefault();

          const err =
            document.getElementById("signupError");

          err.textContent = "";

          const password =
            signupForm.password.value;

          const confirm =
            signupForm.confirm.value;

          if (password !== confirm) {

            err.textContent =
              "Passwords do not match.";

            return;
          }

          const fd = new FormData();

          fd.append(
            "username",
            signupForm.username.value.trim()
          );

          fd.append(
            "email",
            signupForm.email.value.trim()
          );

          fd.append(
            "password",
            password
          );

          fd.append(
            "confirm",
            confirm
          );

          try {

            const res = await fetch(
              "./signup.php",
              {
                method: "POST",
                credentials: "same-origin",
                body: fd
              }
            );

            const data = await res.json();

            if (data.status === "success") {

              const username =
                signupForm.username.value.trim();

              localStorage.setItem(
                "username",
                username
              );

              updateNavbar(username);

              closeModal();

              signupForm.reset();

            } else {

              err.textContent =
                data.message ||
                "Signup failed. Please try again.";
            }

          } catch (_) {

            err.textContent =
              "Connection error. Please try again.";
          }
        }
      );
    }

  }
);


/* ══════════════════════════════════════════════════════════════
   LOAD ORDERS
══════════════════════════════════════════════════════════════ */

/* ══════════════════════════════════════════════════════════════
   LOAD ORDERS
══════════════════════════════════════════════════════════════ */

async function loadOrders(){

  const ordersText =
    document.getElementById("ordersText");

  const ordersBtn =
    document.getElementById("ordersBtn");

  // ── DASHBOARD STATS ───────────────────────
  const activeCount =
    document.getElementById("activeCount");

  const pendingCount =
    document.getElementById("pendingCount");

  const completedCount =
    document.getElementById("completedCount");

  try {

    const response =
      await fetch("./orders.php", {

        credentials: "same-origin"

      });

    const data =
      await response.json();

    console.log(data);

    // ─────────────────────────────────────────
    // SUCCESS
    // ─────────────────────────────────────────
    if(data.status === "success"){

      const orders =
        data.orders || [];

      // ───────────────────────────────────────
      // COUNT STATS
      // ───────────────────────────────────────
      let pending   = 0;
      let completed = 0;
      let active    = 0;

      orders.forEach(order => {

        const status =
          order.status.toLowerCase();

        if(status === "pending"){

          pending++;
          active++;

        }

        if(status === "completed"){

          completed++;

        }

      });

      // ───────────────────────────────────────
      // UPDATE DASHBOARD STATS
      // ───────────────────────────────────────
      if(activeCount){
        activeCount.textContent = active;
      }

      if(pendingCount){
        pendingCount.textContent = pending;
      }

      if(completedCount){
        completedCount.textContent = completed;
      }

      // ───────────────────────────────────────
      // SHOW ONLY LATEST 5 ORDERS
      // ───────────────────────────────────────
      const latestOrders =
        orders.slice(0, 5);

      // ───────────────────────────────────────
      // NO ORDERS
      // ───────────────────────────────────────
      if(latestOrders.length === 0){

        ordersText.innerHTML = `
          <div class="orders-empty">
            No orders yet.
          </div>
        `;

      } else {

        const esc = s =>
          String(s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");

        // ─────────────────────────────────────
        // TABLE START
        // ─────────────────────────────────────
        let html = `

          <div class="orders-table-wrap">

            <table class="dashboard-table">

              <thead>

                <tr>

                  <th>ID</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Date</th>

                </tr>

              </thead>

              <tbody>

        `;

        // ─────────────────────────────────────
        // TABLE ROWS
        // ─────────────────────────────────────
        latestOrders.forEach(order => {

          const safeStatus    = esc(order.status);
          const safeType      = esc(order.type);
          const safeDate      = esc(order.created_at);
          const statusClass   = order.status
            .toLowerCase()
            .replace(/[^a-z0-9-]/g, "");

          html += `

                <tr>

                  <td>#${Number(order.id)}</td>

                  <td>${safeType}</td>

                  <td>

                    <span class="table-status ${statusClass}">

                      ${safeStatus}

                    </span>

                  </td>

                  <td>${safeDate}</td>

                </tr>

          `;

        });

        // ─────────────────────────────────────
        // TABLE END
        // ─────────────────────────────────────
        html += `

              </tbody>

            </table>

          </div>

        `;

        ordersText.innerHTML = html;

      }

      // Hide sign in button
      if (ordersBtn) {

        ordersBtn.style.display = "none";

      }

    } else {

      ordersText.innerHTML = `

        <div class="orders-empty">

          ${data.message}

        </div>

      `;

    }

  } catch(err){

    console.error(err);

    ordersText.innerHTML = `

      <div class="orders-empty">

        Cannot load orders.

      </div>

    `;

  }

}

/* ══════════════════════════════════════════════════════════════
   LOGOUT
══════════════════════════════════════════════════════════════ */
async function logout() {

  try {

    await fetch(
      "./logout.php",
      {
        method: "POST",
        credentials: "same-origin"
      }
    );

  } catch (_) {}

  localStorage.removeItem("username");

  updateNavbar(null);

  updateDashboard(null);

  const page =
    window.location.pathname
      .split("/")
      .pop();

  if (
    ["contact.html", "chatbot.html"]
      .includes(page)
  ) {
    window.location.href = "index.html";
  }
}

/* ══════════════════════════════════════════════════════════════
   GLOBAL SELECTIONS
══════════════════════════════════════════════════════════════ */
let selectedStyle   = "";
let selectedGender  = "";
let selectedPose    = "";
let selectedPalette = [];

/* ══════════════════════════════════════════════════════════════
   STYLE SELECT
══════════════════════════════════════════════════════════════ */
function selectStyle(card, style) {

  document
    .querySelectorAll(".style-card")
    .forEach(c => c.classList.remove("selected"));

  card.classList.add("selected");

  selectedStyle = style;
}

/* ══════════════════════════════════════════════════════════════
   GENDER
══════════════════════════════════════════════════════════════ */
function toggleGender(btn) {

  document
    .querySelectorAll("#genderGroup .toggle-btn")
    .forEach(b => b.classList.remove("active"));

  btn.classList.add("active");

  selectedGender = btn.innerText.trim();
}

/* ══════════════════════════════════════════════════════════════
   POSE
══════════════════════════════════════════════════════════════ */
function togglePose(btn) {

  document
    .querySelectorAll("#poseGroup .toggle-btn")
    .forEach(b => b.classList.remove("active"));

  btn.classList.add("active");

  selectedPose = btn.innerText.trim();
}

/* ══════════════════════════════════════════════════════════════
   PALETTE
══════════════════════════════════════════════════════════════ */
function togglePalette(dot) {

  dot.classList.toggle("selected");

  selectedPalette = [];

  document
    .querySelectorAll(".palette-dot.selected")
    .forEach(d => {
      selectedPalette.push(d.title);
    });
}

/* ══════════════════════════════════════════════════════════════
   FILE PREVIEW
══════════════════════════════════════════════════════════════ */
function previewFiles(files) {

  const container =
    document.getElementById("uploadedFiles");

  container.innerHTML = "";

  [...files].forEach(file => {

    const div =
      document.createElement("div");

    div.style.padding      = "8px 12px";
    div.style.background   = "#fff";
    div.style.borderRadius = "12px";
    div.style.fontSize     = "14px";

    div.innerText = file.name;

    container.appendChild(div);
  });
}

/* ══════════════════════════════════════════════════════════════
   DRAG DROP
══════════════════════════════════════════════════════════════ */
function handleDrop(event) {

  event.preventDefault();

  const files =
    event.dataTransfer.files;

  previewFiles(files);

  const input =
    document.getElementById("f-refs");

  const dataTransfer =
    new DataTransfer();

  [...files].forEach(file => {
    dataTransfer.items.add(file);
  });

  input.files =
    dataTransfer.files;
}





/* ══════════════════════════════════════════════════════════════
   SUBMIT
══════════════════════════════════════════════════════════════ */
async function handleSubmit() {

  try {

    // ── VALIDATION ─────────────────────────────
    const name =
      document.getElementById("f-name").value.trim();

    const email =
      document.getElementById("f-email").value.trim();

    const charname =
      document.getElementById("f-charname").value.trim();

    const desc =
      document.getElementById("f-desc").value.trim();

    const files =
      document.getElementById("f-refs").files;

    if (!selectedStyle) {
      alert("Please select a commission style.");
      return;
    }

    if (!name || !email || !charname) {
      alert("Please fill in all required fields.");
      return;
    }

    if (desc.length < 20) {
      alert("Description must be at least 20 characters.");
      return;
    }

    if (files.length === 0) {
      alert("Please upload at least one reference image.");
      return;
    }

    // ── FORM DATA ─────────────────────────────
    const formData = new FormData();

    formData.append("customer_name", name);
    formData.append("email", email);
    formData.append("character_name", charname);
    formData.append("description", desc);

    formData.append(
      "background",
      document.getElementById("f-bg").value
    );

    formData.append("gender", selectedGender);
    formData.append("pose", selectedPose);

    formData.append(
      "palette",
      selectedPalette.join(", ")
    );

    formData.append("style", selectedStyle);

    // ── FILES ─────────────────────────────────
    for (let i = 0; i < files.length; i++) {

      formData.append(
        "references[]",
        files[i]
      );
    }

    // ── SUBMIT ────────────────────────────────
    const response = await fetch(
      "./submit_order.php",
      {
        method: "POST",
        body: formData
      }
    );

    // DEBUG
    const text = await response.text();

    console.log("SERVER RESPONSE:");
    console.log(text);

    // PARSE JSON
    let result;

    try {

      result = JSON.parse(text);

    } catch {

      alert("Server returned invalid JSON.\nCheck submit_order.php");

      return;
    }

    // ── SUCCESS ───────────────────────────────
    if (result.success) {

      // Show success notification
      document
        .getElementById("successOverlay")
        .classList.add("open");

      // Reset form fields
      document.getElementById("f-name").value    = "";
      document.getElementById("f-email").value   = "";
      document.getElementById("f-charname").value = "";
      document.getElementById("f-desc").value    = "";
      document.getElementById("f-bg").value      = "";
      document.getElementById("f-refs").value    = "";
      document.getElementById("uploadedFiles").innerHTML = "";
      document.getElementById("descCount").textContent  = "0 / 800";

      // Reset toggle selections
      document.querySelectorAll(".toggle-btn")
        .forEach(b => b.classList.remove("active"));

      document.querySelectorAll(".palette-dot")
        .forEach(d => d.classList.remove("selected"));

      document.querySelectorAll(".style-card")
        .forEach(c => c.classList.remove("selected"));

      selectedStyle   = "";
      selectedGender  = "";
      selectedPose    = "";
      selectedPalette = [];

    } else {

      alert(
        result.message ||
        "Submission failed."
      );
    }

  } catch (error) {

    console.error(error);

    alert(
      "Connection error.\nCheck console for details."
    );
  }
}


/* ══════════════════════════════════════════════════════════════
   DESCRIPTION CHARACTER COUNT
══════════════════════════════════════════════════════════════ */
function updateCharCount(textarea) {

  const count =
    document.getElementById("descCount");

  const error =
    document.getElementById("descError");

  const length =
    textarea.value.length;

  count.textContent =
    `${length} / 800`;

  if (length > 0 && length < 20) {

    error.style.display = "block";

  } else {

    error.style.display = "none";
  }
}


/* ══════════════════════════════════════════════════════════════
   SUCCESS OVERLAY
══════════════════════════════════════════════════════════════ */
function closeSuccessOverlay() {

  document
    .getElementById("successOverlay")
    .classList.remove("open");
}

function handleSuccessOverlay(e) {

  if (
    e.target ===
    document.getElementById("successOverlay")
  ) {
    closeSuccessOverlay();
  }
}


