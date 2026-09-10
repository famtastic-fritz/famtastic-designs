(() => {
  "use strict";
  const API = "/web/api/booking-request/noise-cuts";
  const dialog = document.querySelector("#booking-dialog");
  const form = document.querySelector("#booking-form");
  const status = document.querySelector("#form-status");
  document.querySelectorAll("[data-open-booking]").forEach(button => button.addEventListener("click", () => dialog?.showModal()));
  document.querySelector("[data-close-booking]")?.addEventListener("click", () => dialog?.close());
  dialog?.addEventListener("click", event => { if (event.target === dialog) dialog.close(); });
  const day = form?.elements.day;
  if (day) day.min = new Date().toISOString().slice(0, 10);
  form?.addEventListener("submit", async event => {
    event.preventDefault();
    const button = form.querySelector("button[type=submit]");
    const data = new FormData(form);
    const payload = {
      name: String(data.get("name") || "").trim(),
      email: String(data.get("email") || "").trim(),
      phone: String(data.get("phone") || "").trim(),
      service_key: String(data.get("service_key") || "general"),
      requested_window: `${String(data.get("day") || "")} · ${String(data.get("time") || "")}`,
      message: String(data.get("message") || "").trim(),
      consent: data.get("consent") === "on",
      website: String(data.get("website") || ""),
      source: "noise-cuts-proof-v1"
    };
    button.disabled = true;
    status.textContent = "Enviando…";
    try {
      const response = await fetch(API, {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(payload)});
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result.ok) throw new Error(result.error || "request_failed");
      form.reset();
      status.textContent = "Solicitud recibida. Noise Cuts debe revisarla antes de confirmar la cita.";
      window.localStorage.setItem("famtastic.noise-cuts.last-reference", String(result.reference || "received"));
    } catch (error) {
      status.textContent = error.message === "booking_requests_not_enabled"
        ? "La agenda todavía no está abierta. Guarda este concepto y vuelve cuando Noise Cuts la active."
        : "No pudimos guardar la solicitud. Inténtalo de nuevo o contacta a Noise Cuts directamente.";
    } finally {
      button.disabled = false;
    }
  });
})();
