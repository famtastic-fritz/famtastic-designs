export function ownerSitePresentation(site) {
	// Selected Ruby Signal source: website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12/styles.css
	// Presentation mapping grants no access.
	// Authoritative customer-site key verified against Drupal at release preflight.
	if (!["site-dffd4cb9c3aa47fd", "tighten-up-your-locs"].includes(site.site_key)) return site;
	return {
		...site,
		brand_id: "ruby-signal",
		theme: {
			"--od-bg": "#efe6d4",
			"--od-panel": "#efe6d4",
			"--od-ink": "#171311",
			"--od-line": "#aa9686",
			"--od-accent": "#8f1831",
			"--od-button-ink": "#ffffff",
		},
	};
}
