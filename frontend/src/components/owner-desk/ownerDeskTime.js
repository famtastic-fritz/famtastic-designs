export function dayKey(timestamp, timezone) {
	if (!Number.isFinite(Number(timestamp)) || Number(timestamp) <= 0) return "";
	const parts = new Intl.DateTimeFormat("en-CA", {
		timeZone: timezone,
		year: "numeric",
		month: "2-digit",
		day: "2-digit",
	}).formatToParts(new Date(Number(timestamp) * 1000));
	const part = (type) => parts.find((item) => item.type === type)?.value;
	return `${part("year")}-${part("month")}-${part("day")}`;
}
function wallTime(timestamp, timezone) {
	const parts = new Intl.DateTimeFormat("en-CA", {
		timeZone: timezone,
		year: "numeric",
		month: "2-digit",
		day: "2-digit",
		hour: "2-digit",
		minute: "2-digit",
		hourCycle: "h23",
	}).formatToParts(new Date(timestamp));
	const part = (type) => parts.find((item) => item.type === type)?.value;
	return `${part("year")}-${part("month")}-${part("day")}T${part("hour")}:${part("minute")}`;
}
// Explicit IANA conversion: reject missing/repeated DST times instead of guessing.
export function wallTimeToUnix(value, timezone) {
	if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value))
		throw new Error("Enter a complete date and time.");
	const approximate = Date.parse(`${value}:00Z`);
	if (!Number.isFinite(approximate))
		throw new Error("Enter a valid date and time.");
	const matches = [];
	for (let minutes = -840; minutes <= 840; minutes += 15) {
		const timestamp = approximate + minutes * 60000;
		if (wallTime(timestamp, timezone) === value) matches.push(timestamp / 1000);
	}
	if (matches.length !== 1)
		throw new Error(
			matches.length
				? "This time repeats when clocks change. Choose an unambiguous time."
				: "This time does not exist in the selected timezone.",
		);
	return matches[0];
}
export function interval(start, end, timezone) {
	const starts_at = wallTimeToUnix(start, timezone),
		ends_at = wallTimeToUnix(end, timezone);
	if (ends_at <= starts_at) throw new Error("End must be after start.");
	return { starts_at, ends_at, timezone };
}
export function formatTime(value, timezone) {
	if (!Number.isFinite(Number(value)) || Number(value) <= 0)
		return "Time unavailable";
	return new Intl.DateTimeFormat([], {
		dateStyle: "medium",
		timeStyle: "short",
		timeZone: timezone,
	}).format(new Date(Number(value) * 1000));
}
export function proposalAvailable(proposal, now = Date.now() / 1000) {
	return Boolean(
		proposal &&
		proposal.proposal_available !== false &&
		proposal.status === "proposal_pending" &&
		Number(proposal.proposed_starts_at) > 0 &&
		Number(proposal.proposed_ends_at) > Number(proposal.proposed_starts_at) &&
		Number(proposal.expires_at) > now,
	);
}
