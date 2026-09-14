// Independent same-origin Locs backend. Deploy only after database migration and cutover acceptance.
window.LOCS_CONFIG = Object.freeze({
  requestsEnabled: true,
  requestEndpoint: "/api/booking-request/site-dffd4cb9c3aa47fd",
  availabilityEndpoint: "/api/booking-availability/site-dffd4cb9c3aa47fd",
  gaMeasurementId: "G-V8M437DWV0",
  siteTimeZone: "America/New_York"
});
