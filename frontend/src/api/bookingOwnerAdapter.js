import {
	commandBookingAppointment,
	createBookingAvailability,
	getBookingAppointments,
	getBookingAvailability,
	getBookingRequests,
	updateBookingAvailability,
	updateBookingRequest,
} from "./customer.js";
function receipt(result, field) {
	if (result?.ok !== true || !result[field])
		throw new Error("The saved result could not be verified.");
	return result;
}
export const bookingOwnerAdapter = {
	async load(site) {
		const [requests, appointments, availability] = await Promise.all([
			getBookingRequests(site),
			getBookingAppointments(site),
			getBookingAvailability(site),
		]);
		if (
			[requests, appointments, availability].some(
				(result) => result?.ok !== true || result?.site_key !== site,
			)
		)
			throw new Error("Site scope mismatch");
		return {
			requests: requests.requests || [],
			appointments: appointments.appointments || [],
			windows: availability.windows || [],
			appointments_has_more: appointments.has_more === true,
		};
	},
	async command(site, payload) {
		return receipt(
			await commandBookingAppointment(site, payload),
			"appointment",
		);
	},
	async createOpening(site, payload) {
		return receipt(await createBookingAvailability(site, payload), "window");
	},
	async updateOpening(site, { window_id, status }) {
		return receipt(
			await updateBookingAvailability(site, window_id, { status }),
			"window",
		);
	},
	async closeRequest(site, { request_id, status }) {
		const result = await updateBookingRequest(site, request_id, status);
		if (result?.ok !== true || result.status !== status)
			throw new Error("The saved status could not be verified.");
		return result;
	},
};
export function ownerSitePresentation(site) {
	// Selected Ruby Signal source: website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12/styles.css
	// Presentation mapping grants no access.
	if (site.site_key !== "tighten-up-your-locs") return site;
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
