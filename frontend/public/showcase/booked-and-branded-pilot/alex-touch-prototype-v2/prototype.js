(() => {
  "use strict";

  const STORAGE_KEY = "famtastic.alex-touch-prototype.v2";
  const defaults = {
    services: [
      { id: "signature", name: "Signature Cut", price: 40, duration: 45, enabled: true },
      { id: "cut-beard", name: "Cut + Beard", price: 55, duration: 60, enabled: true },
      { id: "kids", name: "Kids Cut", price: 30, duration: 35, enabled: true },
      { id: "lineup", name: "Lineup + Beard", price: 30, duration: 30, enabled: true }
    ],
    hours: [
      { day: "Monday", enabled: true, start: "10:00", end: "18:00" },
      { day: "Tuesday", enabled: true, start: "10:00", end: "18:00" },
      { day: "Wednesday", enabled: false, start: "10:00", end: "18:00" },
      { day: "Thursday", enabled: true, start: "10:00", end: "19:00" },
      { day: "Friday", enabled: true, start: "09:00", end: "19:00" },
      { day: "Saturday", enabled: true, start: "09:00", end: "17:00" },
      { day: "Sunday", enabled: false, start: "10:00", end: "15:00" }
    ],
    links: {
      instagram: "https://www.instagram.com/touchdabarber4150/",
      bookingLink: "",
      paymentLink: "",
      shopName: "Floresta Centre",
      address: "1542 SE Floresta Dr, Port St. Lucie, FL 34983",
      mapLink: "https://www.google.com/maps/search/?api=1&query=1542+SE+Floresta+Dr+Port+St+Lucie+FL+34983",
      buttonLabel: "Request chair time"
    },
    requests: [
      { id: "demo-1", name: "Marcus J.", contact: "•••-•••-1842", service: "Signature Cut", day: "Today", time: "3:30 PM", note: "Clean taper, keep length on top.", status: "pending" },
      { id: "demo-2", name: "Noah’s Mom", contact: "•••-•••-4110", service: "Kids Cut", day: "Saturday", time: "Morning", note: "First time in Alex’s chair.", status: "pending" },
      { id: "demo-3", name: "Dre P.", contact: "•••-•••-9088", service: "Cut + Beard", day: "Friday", time: "After 3 PM", note: "Shape beard and fade sides.", status: "confirmed" }
    ]
  };

  const clone = value => JSON.parse(JSON.stringify(value));
  const readState = () => {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
      return saved && Array.isArray(saved.services) && Array.isArray(saved.requests)
        ? { ...clone(defaults), ...saved, links: { ...clone(defaults.links), ...(saved.links || {}) } }
        : clone(defaults);
    } catch (_) {
      return clone(defaults);
    }
  };
  let state = readState();
  const writeState = () => localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  const escapeHtml = value => String(value ?? "").replace(/[&<>'"]/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
  const toast = message => {
    const node = document.querySelector("#toast");
    if (!node) return;
    node.textContent = message;
    node.classList.add("show");
    window.clearTimeout(toast.timer);
    toast.timer = window.setTimeout(() => node.classList.remove("show"), 2800);
  };

  const enabledServices = () => state.services.filter(service => service.enabled);
  const formatPrice = price => `$${Math.max(0, Number(price) || 0)}`;
  const safeHttpUrl = (value, fallback = "") => {
    try {
      const parsed = new URL(String(value || ""), location.origin);
      return ["http:", "https:"].includes(parsed.protocol) ? parsed.href : fallback;
    } catch (_) {
      return fallback;
    }
  };

  function renderPublicServices() {
    const list = document.querySelector("#service-grid");
    const select = document.querySelector("#booking-service");
    const services = enabledServices();
    if (list) {
      list.innerHTML = services.map((service, index) => `
        <article class="service-item">
          <span class="index">${String(index + 1).padStart(2, "0")}</span>
          <span><b>${escapeHtml(service.name)}</b><small>${escapeHtml(service.duration)} minutes · prototype detail</small></span>
          <strong>${formatPrice(service.price)}</strong>
        </article>`).join("") || "<p>No services are visible in this prototype.</p>";
    }
    if (select) {
      select.innerHTML = services.map(service => `<option value="${escapeHtml(service.id)}">${escapeHtml(service.name)} · ${formatPrice(service.price)}</option>`).join("");
    }
    document.querySelectorAll("#public-service-count").forEach(node => { node.textContent = String(services.length); });
    document.querySelectorAll("#public-request-count").forEach(node => { node.textContent = String(state.requests.filter(request => request.status === "pending").length); });
    const shopName = document.querySelector("#public-shop-name");
    const address = document.querySelector("#public-address");
    if (shopName) shopName.textContent = state.links.shopName || defaults.links.shopName;
    if (address) address.innerHTML = escapeHtml(state.links.address || defaults.links.address).replace(/, /g, "<br>");
    const directions = safeHttpUrl(state.links.mapLink, defaults.links.mapLink);
    document.querySelectorAll("#public-map-link, #public-map-link-secondary").forEach(node => { node.href = directions; });
    const mapFrame = document.querySelector("#public-map-frame");
    if (mapFrame && (state.links.address || defaults.links.address) !== defaults.links.address) {
      mapFrame.src = `https://www.google.com/maps?q=${encodeURIComponent(state.links.address)}&output=embed`;
    }
  }

  function setupBookingDialog() {
    const dialog = document.querySelector("#booking-dialog");
    const form = document.querySelector("#booking-form");
    if (!dialog || !form) return;
    document.querySelectorAll("[data-open-booking]").forEach(button => button.addEventListener("click", () => dialog.showModal()));
    document.querySelector("[data-close-booking]")?.addEventListener("click", () => dialog.close());
    dialog.addEventListener("click", event => {
      if (event.target === dialog) dialog.close();
    });
    const day = form.elements.day;
    if (day) day.min = new Date().toISOString().slice(0, 10);
    form.addEventListener("submit", event => {
      event.preventDefault();
      const data = new FormData(form);
      const digits = String(data.get("phone") || "").replace(/\D/g, "");
      const service = state.services.find(item => item.id === data.get("service"));
      state.requests.unshift({
        id: `demo-${Date.now()}`,
        name: String(data.get("name") || "Guest").trim().slice(0, 40),
        contact: `•••-•••-${digits.slice(-4).padStart(4, "•")}`,
        service: service?.name || "Service request",
        day: String(data.get("day") || "Preferred day"),
        time: String(data.get("time") || "Preferred time"),
        note: String(data.get("note") || "").trim().slice(0, 180),
        status: "pending"
      });
      writeState();
      renderPublicServices();
      form.reset();
      dialog.close();
      toast("Demo request saved on this device. Open Touch Control to see it.");
    });
  }

  function setupContactForm() {
    const form = document.querySelector("#contact-form");
    if (!form) return;
    form.addEventListener("submit", event => {
      event.preventDefault();
      const data = new FormData(form);
      const reply = String(data.get("reply") || "").trim();
      state.requests.unshift({
        id: `message-${Date.now()}`,
        name: String(data.get("name") || "Guest").trim().slice(0, 40),
        contact: reply.includes("@") ? reply.replace(/^(.{1,2}).*(@.*)$/, "$1•••$2") : `•••${reply.replace(/\D/g, "").slice(-4).padStart(4, "•")}`,
        service: "General question",
        day: "New message",
        time: "Reply requested",
        note: String(data.get("message") || "").trim().slice(0, 240),
        status: "pending"
      });
      writeState();
      renderPublicServices();
      form.reset();
      toast("Demo message saved. It is now visible in Touch Control on this device.");
    });
  }

  function requestMarkup(request, showActions = true) {
    return `<article class="request-row" data-request-id="${escapeHtml(request.id)}">
      <div><h3>${escapeHtml(request.name)} · ${escapeHtml(request.service)}</h3><p>${escapeHtml(request.day)} · ${escapeHtml(request.time)} · ${escapeHtml(request.contact)}</p>${request.note ? `<p>${escapeHtml(request.note)}</p>` : ""}</div>
      <span class="request-status ${escapeHtml(request.status)}">${escapeHtml(request.status)}</span>
      ${showActions ? `<div class="request-actions">
        <button type="button" data-request-action="confirmed">Confirm</button>
        <button type="button" data-request-action="completed">Complete</button>
        <button type="button" data-request-action="declined">Decline</button>
      </div>` : ""}
    </article>`;
  }

  function renderRequests() {
    const dashboard = document.querySelector("#dashboard-requests");
    const full = document.querySelector("#owner-request-list");
    if (dashboard) dashboard.innerHTML = state.requests.slice(0, 3).map(request => requestMarkup(request, false)).join("");
    if (full) full.innerHTML = state.requests.map(request => requestMarkup(request, true)).join("") || "<p>No prototype requests yet.</p>";
    const open = state.requests.filter(request => request.status === "pending").length;
    const confirmed = state.requests.filter(request => request.status === "confirmed").length;
    document.querySelector("#stat-open")?.replaceChildren(String(open));
    document.querySelector("#stat-confirmed")?.replaceChildren(String(confirmed));
    document.querySelector("#stat-services")?.replaceChildren(String(enabledServices().length));
  }

  function renderServiceSettings() {
    const form = document.querySelector("#services-form");
    if (!form) return;
    form.innerHTML = state.services.map(service => `<div class="setting-row" data-service-id="${escapeHtml(service.id)}">
      <input class="toggle" type="checkbox" aria-label="Show ${escapeHtml(service.name)}" data-field="enabled" ${service.enabled ? "checked" : ""}>
      <label>Service<input data-field="name" maxlength="44" value="${escapeHtml(service.name)}"></label>
      <label>Price<input data-field="price" type="number" min="0" max="500" value="${escapeHtml(service.price)}"></label>
      <label>Minutes<input data-field="duration" type="number" min="10" max="240" value="${escapeHtml(service.duration)}"></label>
    </div>`).join("");
  }

  function renderHours() {
    const form = document.querySelector("#hours-form");
    if (!form) return;
    form.innerHTML = state.hours.map((hours, index) => `<div class="setting-row" data-hours-index="${index}">
      <input class="toggle" type="checkbox" aria-label="Work ${escapeHtml(hours.day)}" data-field="enabled" ${hours.enabled ? "checked" : ""}>
      <b>${escapeHtml(hours.day)}</b>
      <label>Start<input data-field="start" type="time" value="${escapeHtml(hours.start)}"></label>
      <label>End<input data-field="end" type="time" value="${escapeHtml(hours.end)}"></label>
    </div>`).join("");
  }

  function renderLinks() {
    const form = document.querySelector("#links-form");
    if (!form) return;
    Object.entries(state.links).forEach(([key, value]) => {
      if (form.elements[key]) form.elements[key].value = value;
    });
  }

  function drawPrototypeQr() {
    const canvas = document.querySelector("#qr-preview");
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    const size = 18;
    const cell = canvas.width / size;
    const source = `${state.links.bookingLink}|${location.origin}${location.pathname.replace(/owner\/?$/, "")}`;
    let seed = [...source].reduce((sum, char) => (sum * 33 + char.charCodeAt(0)) >>> 0, 5381);
    const rand = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296; };
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "#090b0a";
    for (let y = 0; y < size; y += 1) {
      for (let x = 0; x < size; x += 1) {
        if (rand() > .51) ctx.fillRect(x * cell, y * cell, Math.ceil(cell), Math.ceil(cell));
      }
    }
    [[1,1],[12,1],[1,12]].forEach(([x,y]) => {
      ctx.fillStyle = "#fff"; ctx.fillRect(x*cell, y*cell, 5*cell, 5*cell);
      ctx.fillStyle = "#090b0a"; ctx.fillRect((x+.4)*cell, (y+.4)*cell, 4.2*cell, 4.2*cell);
      ctx.fillStyle = "#fff"; ctx.fillRect((x+1.3)*cell, (y+1.3)*cell, 2.4*cell, 2.4*cell);
      ctx.fillStyle = "#090b0a"; ctx.fillRect((x+2)*cell, (y+2)*cell, cell, cell);
    });
  }

  function activateTab(tabName) {
    document.body.dataset.ownerTab = tabName;
    document.querySelectorAll("[data-owner-tab]").forEach(button => button.classList.toggle("active", button.dataset.ownerTab === tabName));
    document.querySelectorAll("[data-owner-panel]").forEach(panel => panel.classList.toggle("active", panel.dataset.ownerPanel === tabName));
    const compactOwnerView = window.matchMedia("(max-width: 620px)").matches;
    window.scrollTo({ top: 0, behavior: compactOwnerView ? "auto" : "smooth" });
  }

  function setupOwner() {
    document.body.dataset.ownerTab = "dashboard";
    document.querySelectorAll("[data-owner-tab]").forEach(button => button.addEventListener("click", () => activateTab(button.dataset.ownerTab)));
    document.querySelectorAll("[data-jump-tab]").forEach(button => button.addEventListener("click", () => activateTab(button.dataset.jumpTab)));
    document.querySelector("#owner-request-list")?.addEventListener("click", event => {
      const action = event.target.closest("[data-request-action]");
      const row = event.target.closest("[data-request-id]");
      if (!action || !row) return;
      const request = state.requests.find(item => item.id === row.dataset.requestId);
      if (!request) return;
      request.status = action.dataset.requestAction;
      writeState();
      renderRequests();
      toast(`Prototype request marked ${request.status}. Nothing was sent.`);
    });
    document.querySelector("#save-services")?.addEventListener("click", () => {
      document.querySelectorAll("[data-service-id]").forEach(row => {
        const service = state.services.find(item => item.id === row.dataset.serviceId);
        if (!service) return;
        service.enabled = row.querySelector('[data-field="enabled"]').checked;
        service.name = row.querySelector('[data-field="name"]').value.trim().slice(0, 44) || service.name;
        service.price = Number(row.querySelector('[data-field="price"]').value) || 0;
        service.duration = Number(row.querySelector('[data-field="duration"]').value) || 30;
      });
      writeState();
      renderRequests();
      toast("Service changes saved on this device.");
    });
    document.querySelector("#save-hours")?.addEventListener("click", () => {
      document.querySelectorAll("[data-hours-index]").forEach(row => {
        const hours = state.hours[Number(row.dataset.hoursIndex)];
        hours.enabled = row.querySelector('[data-field="enabled"]').checked;
        hours.start = row.querySelector('[data-field="start"]').value;
        hours.end = row.querySelector('[data-field="end"]').value;
      });
      writeState();
      toast("Chair hours saved on this device.");
    });
    document.querySelector("#links-form")?.addEventListener("submit", event => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      Object.keys(state.links).forEach(key => { state.links[key] = String(data.get(key) || "").trim().slice(0, 300); });
      writeState();
      drawPrototypeQr();
      toast("Links saved. The QR remains a placement preview until launch.");
    });
    document.querySelector("#reset-demo")?.addEventListener("click", () => {
      state = clone(defaults);
      writeState();
      renderOwner();
      toast("Prototype sample data reset.");
    });
    document.querySelector("#copy-demo-link")?.addEventListener("click", async () => {
      const demoUrl = location.href.replace(/owner\/?$/, "");
      try {
        await navigator.clipboard.writeText(demoUrl);
        toast("Prototype URL copied.");
      } catch (_) {
        toast("Copy was blocked; use the browser address bar.");
      }
    });
  }

  function renderOwner() {
    renderRequests();
    renderServiceSettings();
    renderHours();
    renderLinks();
    drawPrototypeQr();
  }

  if (document.body.dataset.view === "public") {
    renderPublicServices();
    setupBookingDialog();
    setupContactForm();
  }
  if (document.body.dataset.view === "owner") {
    renderOwner();
    setupOwner();
  }
})();
