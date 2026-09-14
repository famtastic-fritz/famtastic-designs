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
    measurement: ["https://tightenupyourlocs.com", "https://www.tightenupyourlocs.com"].includes(origin) && /^G-[A-Z0-9]{6,20}$/.test(raw.gaMeasurementId || "") ? raw.gaMeasurementId : "",
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
export function initAnalytics(win, doc, measurement) {
  const noop = () => {};
  if (!measurement || doc.getElementById("locs-analytics")) return noop;
  // Migrate away from cookie-based measurement without overriding prior refusals.
  for (const cookie of doc.cookie.split(";")) {
    const name = cookie.split("=")[0].trim();
    if (!/^_ga(?:_|$)/.test(name)) continue;
    const domains = new Set(["", win.location.hostname, "." + win.location.hostname]);
    if (/^(www\.)?tightenupyourlocs\.com$/.test(win.location.hostname)) domains.add(".tightenupyourlocs.com");
    for (const domain of domains) doc.cookie = name + "=; Max-Age=0; path=/" + (domain ? "; domain=" + domain : "") + "; SameSite=Lax";
  }
  let refused = win.navigator?.globalPrivacyControl === true || win.navigator?.doNotTrack === "1";
  try { refused ||= win.localStorage.getItem("locs.analytics-choice.v1") === "deny"; } catch {}
  win["ga-disable-" + measurement] = refused;
  if (refused) return noop;
  win.dataLayer = win.dataLayer || [];
  win.gtag = function () { win.dataLayer.push(arguments); };
  // Set BEFORE loading Google: no analytics/ad cookies, no inferred visitor grant.
  win.gtag("consent", "default", { analytics_storage: "denied", ad_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" });
  win.gtag("set", "ads_data_redaction", true);
  win.gtag("set", "url_passthrough", false);
  win.gtag("js", new Date());
  win.gtag("config", measurement, safeAnalyticsParameters(win.location.origin));
  const script = doc.createElement("script");
  script.async = true; script.id = "locs-analytics"; script.referrerPolicy = "no-referrer";
  script.src = "https://www.googletagmanager.com/gtag/js?id=" + measurement;
  doc.head.append(script);
  const event = name => {
    if (!["page_view", "request_start", "request_saved"].includes(name)) return;
    win.gtag("event", name, { send_to: measurement, ...safeAnalyticsParameters(win.location.origin) });
  };
  event("page_view");
  return event;
}
export function initSite(win = window, doc = document) {
  const config = settings(win.LOCS_CONFIG || {}, win.location.origin);
  const form = doc.getElementById("contact-form"), button = doc.getElementById("send-request");
  const status = doc.getElementById("status"), info = doc.getElementById("request-availability");
  let sending = false, uncertain = false;
  const event = initAnalytics(win, doc, config.measurement);
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
