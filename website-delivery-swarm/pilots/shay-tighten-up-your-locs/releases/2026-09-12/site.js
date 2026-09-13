export const SITE_KEY = "site-dffd4cb9c3aa47fd";
export function canonicalEndpoint(value, kind, origin) {
  if (!value) return "";
  try {
    const url = new URL(value, origin);
    const expected = kind === "request" ? "/api/booking-request/" + SITE_KEY : "/api/booking-availability/" + SITE_KEY;
    if (url.username || url.password || url.search || url.hash || ![expected, "/web" + expected].includes(url.pathname)) return "";
    if (url.origin !== origin && url.origin !== "https://famtasticdesigns.com") return "";
    if (url.protocol !== "https:" && !/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(url.origin)) return "";
    return url.href;
  } catch { return ""; }
}
export function settings(raw, origin) {
  return {
    request: raw.requestsEnabled === true ? canonicalEndpoint(raw.requestEndpoint, "request", origin) : "",
    availability: canonicalEndpoint(raw.availabilityEndpoint, "availability", origin),
    measurement: /^G-[A-Z0-9]{6,20}$/.test(raw.gaMeasurementId || "") ? raw.gaMeasurementId : "",
    zone: raw.siteTimeZone === "America/New_York" ? raw.siteTimeZone : ""
  };
}
export function isDurableReceipt(payload) {
  return payload?.ok === true && payload.status === "received" && typeof payload.reference === "string" && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(payload.reference);
}
export function safeAnalyticsParameters(origin) {
  return { page_location: new URL(origin).origin + "/", page_referrer: "", page_title: "Tighten Up Your Locs", send_page_view: false, allow_google_signals: false, allow_ad_personalization_signals: false };
}
export function publicWindows(payload, now = Date.now()) {
  if (payload?.ok !== true || payload.site_key !== SITE_KEY || !Array.isArray(payload.windows)) throw new Error("unavailable");
  if (payload.windows.some(item => !item || typeof item.label !== "string" || !Number.isFinite(Number(item.starts_at)) || !Number.isFinite(Number(item.ends_at)) || Number(item.ends_at) <= Number(item.starts_at))) throw new Error("invalid_windows");
  return payload.windows.filter(item => Number(item.ends_at) * 1000 > now).slice(0, 12);
}
export function initSite(win = window, doc = document) {
  const config = settings(win.LOCS_CONFIG || {}, win.location.origin);
  const form = doc.getElementById("contact-form"), button = doc.getElementById("send-request");
  const status = doc.getElementById("status"), info = doc.getElementById("request-availability");
  let sending = false, uncertain = false, consent = false, loaded = false;
  const analyticsStatus = doc.getElementById("analytics-status");
  const privacyKey = "locs.analytics-choice.v1";
  const remember = value => { try { win.localStorage.setItem(privacyKey, value); } catch {} };
  const event = name => {
    if (!consent || !loaded || !config.measurement) return;
    win.gtag("event", name, { send_to: config.measurement, ...safeAnalyticsParameters(win.location.origin) });
  };
  function allowAnalytics() {
    if (!config.measurement) { analyticsStatus.textContent = "Optional analytics are not configured. No tracking has started."; return; }
    consent = true; remember("allow"); win["ga-disable-" + config.measurement] = false;
    if (!loaded) {
      win.dataLayer = win.dataLayer || [];
      win.gtag = function () { win.dataLayer.push(arguments); };
      win.gtag("consent", "default", { analytics_storage: "granted", ad_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" });
      win.gtag("js", new Date());
      win.gtag("config", config.measurement, safeAnalyticsParameters(win.location.origin));
      const script = doc.createElement("script");
      script.async = true; script.id = "locs-analytics"; script.referrerPolicy = "no-referrer";
      script.src = "https://www.googletagmanager.com/gtag/js?id=" + config.measurement;
      script.onerror = () => { analyticsStatus.textContent = "Your choice is saved, but analytics could not load."; };
      doc.head.append(script); loaded = true;
      event("page_view");
    }
    analyticsStatus.textContent = "Optional analytics are allowed. Form contents are not included.";
  }
  function declineAnalytics() {
    consent = false; remember("deny");
    if (config.measurement) win["ga-disable-" + config.measurement] = true;
    doc.getElementById("locs-analytics")?.remove();
    // Remove only GA cookies for this site. Never touch authentication or other cookies.
    for (const cookie of doc.cookie.split(";")) {
      const name = cookie.split("=")[0].trim();
      if (!/^_ga(?:_|$)/.test(name)) continue;
      for (const domain of ["", "; domain=" + win.location.hostname, "; domain=." + win.location.hostname]) {
        doc.cookie = name + "=; Max-Age=0; path=/" + domain + "; SameSite=Lax";
      }
    }
    analyticsStatus.textContent = "Optional analytics are off. Previously sent measurements cannot be recalled.";
  }
  doc.getElementById("analytics-allow").addEventListener("click", allowAnalytics);
  doc.getElementById("analytics-decline").addEventListener("click", declineAnalytics);
  // Consent is the only local-storage value. No request details are persisted.
  try { if (win.localStorage.getItem(privacyKey) === "allow") allowAnalytics(); } catch {}
  if (config.request) {
    button.disabled = false;
    info.textContent = "Send a request for review. You will see a reference only after it is saved.";
  }
  doc.querySelectorAll("[data-service-request]").forEach(link => link.addEventListener("click", () => {
    form.elements.service_key.value = link.dataset.serviceRequest;
  }));
  form.addEventListener("submit", async e => {
    e.preventDefault();
    if (!config.request || sending || uncertain) return;
    if (!form.reportValidity()) return;
    sending = true; button.disabled = true; status.textContent = "Sending your request…";
    event("request_start");
    const data = Object.fromEntries(new FormData(form));
    data.source = "tighten-up-your-locs-site";
    try {
      const response = await win.fetch(config.request, {
        method: "POST", credentials: "omit", redirect: "error", cache: "no-store",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(data), signal: AbortSignal.timeout(20000)
      });
      const payload = await response.json();
      if (!response.ok) {
        if ([409, 422, 429].includes(response.status)) {
          status.textContent = response.status === 429 ? "Too many requests. Please wait before trying again." : response.status === 409 ? "Online requests are temporarily unavailable. Nothing was submitted." : "Please check the details and consent, then try again.";
          return;
        }
        throw new Error("uncertain");
      }
      if (!isDurableReceipt(payload)) throw new Error("uncertain");
      status.textContent = "Your request was saved. Reference: " + payload.reference + ". This is not an appointment confirmation.";
      event("request_saved");
      form.reset();
      uncertain = true; // Do not allow accidental repeat submission after an acknowledged save.
      button.textContent = "Request saved";
    } catch {
      uncertain = true;
      status.textContent = "We could not confirm whether your request was saved. Please do not submit it again yet. Your details remain here; email hello@tightenupyourlocs.com to check.";
      button.textContent = "Confirmation unavailable";
    } finally {
      sending = false; button.disabled = uncertain;
    }
  });
  if (config.availability && config.zone) {
    const target = doc.getElementById("availability-list"), note = doc.getElementById("availability-status");
    win.fetch(config.availability, { credentials: "omit", redirect: "error", cache: "no-store", headers: { Accept: "application/json" }, signal: AbortSignal.timeout(15000) })
      .then(async response => { if (!response.ok) throw new Error(); return response.json(); })
      .then(payload => {
        const windows = publicWindows(payload);
        if (!windows.length) { note.textContent = "No request windows are published right now. You can still suggest a day in your request."; return; }
        note.textContent = "Request windows, shown in Port St. Lucie time. These are not confirmed appointments.";
        const format = new Intl.DateTimeFormat("en-US", { weekday: "short", month: "short", day: "numeric", hour: "numeric", minute: "2-digit", timeZone: config.zone, timeZoneName: "short" });
        target.replaceChildren(...windows.map(item => {
          const choice = doc.createElement("button"); choice.type = "button";
          const label = String(item.label || "Request window").slice(0, 100), when = format.format(new Date(Number(item.starts_at) * 1000));
          choice.textContent = label + " · " + when; choice.disabled = !config.request;
          choice.addEventListener("click", () => {
            form.elements.requested_window.value = (label + " · " + when).slice(0, 160);
            doc.getElementById("request").scrollIntoView();
            form.elements.name.focus({ preventScroll: true });
          });
          return choice;
        }));
      }).catch(() => { note.textContent = "Published windows could not be loaded. You can suggest a day in your request."; });
  }
}
if (typeof window !== "undefined" && typeof document !== "undefined") initSite();
