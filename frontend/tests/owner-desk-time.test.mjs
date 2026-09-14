import test from "node:test";
import assert from "node:assert/strict";
import {
	wallTimeToUnix,
	dayKey,
	interval,
	proposalAvailable,
	formatTime,
} from "../src/components/owner-desk/ownerDeskTime.js";

test("wall times convert explicitly in selected zone independent of device zone", () => {
	assert.equal(
		wallTimeToUnix("2026-09-14T11:00", "America/New_York"),
		Date.parse("2026-09-14T15:00:00Z") / 1000,
	);
	assert.equal(
		wallTimeToUnix("2026-09-14T11:00", "America/Los_Angeles"),
		Date.parse("2026-09-14T18:00:00Z") / 1000,
	);
	assert.equal(
		dayKey(Date.parse("2026-09-14T02:00:00Z") / 1000, "America/New_York"),
		"2026-09-13",
	);
});
test("DST gaps, repeated times and invalid intervals are rejected", () => {
	assert.throws(
		() => wallTimeToUnix("2026-03-08T02:30", "America/New_York"),
		/does not exist/,
	);
	assert.throws(
		() => wallTimeToUnix("2026-11-01T01:30", "America/New_York"),
		/repeats/,
	);
	assert.throws(
		() => wallTimeToUnix("2026-02-30T12:00", "America/New_York"),
		/does not exist/,
	);
	assert.throws(
		() => interval("2026-09-14T12:00", "2026-09-14T11:00", "UTC"),
		/End must/,
	);
});
test("replayed, expired and zero-time proposals never show response controls or epoch date", () => {
	const valid = {
		status: "proposal_pending",
		proposal_available: true,
		proposed_starts_at: 2000,
		proposed_ends_at: 3000,
		expires_at: 1500,
	};
	assert.equal(proposalAvailable(valid, 1000), true);
	for (const override of [
		{ status: "confirmed" },
		{ proposed_starts_at: 0 },
		{ proposed_ends_at: 0 },
		{ expires_at: 999 },
		{ proposal_available: false },
	])
		assert.equal(proposalAvailable({ ...valid, ...override }, 1000), false);
	assert.equal(formatTime(0, "UTC"), "Time unavailable");
});
