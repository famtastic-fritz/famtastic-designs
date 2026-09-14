import { preview } from "vite";
import { chromium } from "playwright";
import assert from "node:assert/strict";
import { mkdtemp } from "node:fs/promises";
import { tmpdir } from "node:os";
import { resolve } from "node:path";

const server = await preview({
	root: resolve(import.meta.dirname, ".."),
	preview: { host: "127.0.0.1", port: 0 },
});
const url = server.resolvedUrls.local[0];
const shots = await mkdtemp(resolve(tmpdir(), "booking-owner-qa-"));
const browser = await chromium.launch();
const checks = [];
try {
	for (const width of [390, 768, 1280]) {
		const context = await browser.newContext({
			viewport: { width, height: 900 },
			timezoneId: "America/Los_Angeles",
		});
		const page = await context.newPage();
		const errors = [];
		page.on("pageerror", (error) => errors.push(error.message));
		let appointment = null,
			windows = [],
			failFirst = true;
		const commands = [];
		await page.route("https://**", (route) => route.abort());
		await page.route("**/web/session/token", (route) =>
			route.fulfill({ body: "local-csrf-fixture" }),
		);
		await page.route("**/web/api/customer/**", async (route) => {
			const path = new URL(route.request().url()).pathname;
			const method = route.request().method();
			const site = path.includes("/other-business/")
				? "other-business"
				: "tighten-up-your-locs";
			let payload = {};
			if (path.endsWith("/session"))
				payload = {
					customer: {
						public_id: "owner-fixture",
						display_name: "Shay",
						email: "owner@example.test",
						verified: true,
					},
				};
			else if (path.endsWith("/workspace"))
				payload = {
					organization: {
						public_id: "org-fixture",
						name: "Tighten Up Your Locs",
					},
					booking_sites: [
						{
							site_key: "tighten-up-your-locs",
							business_name: "Tighten Up Your Locs",
						},
						{ site_key: "other-business", business_name: "Other Business" },
					],
					website_requests: [{ public_id: 'ready-proof-fixture', proof_review_status: 'customer_ready', customer_archived: false, proofs: { variants: [{ id: 'a' }, { id: 'b' }, { id: 'c' }] } }],
					threads: [],
				};
			else if (path.endsWith("/catalog")) payload = { products: [] };
			else if (path.endsWith("/booking-requests"))
				payload = {
					ok: true,
					site_key: site,
					requests:
						site === "other-business"
							? []
							: [
									{
										id: 1,
										customer_name: "Local Visitor Fixture",
										email: "visitor@example.test",
										service_key: "loc-care",
										requested_window: "Monday",
										message: "Fixture only",
										status: appointment ? "responded" : "new",
									},
								],
				};
			else if (path.endsWith("/appointments")) {
				if (method === "POST") {
					assert.equal(
						route.request().headers()["x-csrf-token"],
						"local-csrf-fixture",
					);
					const command = route.request().postDataJSON();
					commands.push(command);
					if (failFirst) {
						failFirst = false;
						return route.fulfill({
							status: 503,
							contentType: "application/json",
							body: JSON.stringify({ error: "fixture_ambiguous_failure" }),
						});
					}
					appointment = {
						id: 8,
						request_id: 1,
						starts_at: command.starts_at,
						ends_at: command.ends_at,
						status: "confirmed",
						revision: 1,
						timezone: command.timezone,
						customer: {
							name: "Local Visitor Fixture",
							email: "visitor@example.test",
						},
					};
					payload = { ok: true, appointment };
				} else
					payload = {
						ok: true,
						site_key: site,
						appointments:
							site === "other-business" ? [] : appointment ? [appointment] : [],
					};
			} else if (path.endsWith("/availability")) {
				if (method === "POST") {
					const window = { id: 2, ...route.request().postDataJSON() };
					windows.push(window);
					payload = { ok: true, window };
				} else
					payload = {
						ok: true,
						site_key: site,
						windows: site === "other-business" ? [] : windows,
					};
			}
			if (site === "other-business")
				await new Promise((done) => setTimeout(done, 250));
			return route.fulfill({
				contentType: "application/json",
				body: JSON.stringify(payload),
			});
		});

		await page.goto(url + "portal/?section=booking");
		await page.locator(".owner-desk").waitFor();
		assert.equal(await page.getByText('Your 3 website concepts are ready below.', { exact: true }).count(), 0, 'explicit booking entry must not be replaced by a ready proof');
		await page.getByLabel("Calendar timezone").selectOption("America/New_York");
		assert.equal(
			await page.locator(".owner-desk").getAttribute("data-brand"),
			"ruby-signal",
		);
		await page.getByRole("button", { name: "Requests", exact: true }).click();
		await page
			.getByRole("heading", { name: "Local Visitor Fixture" })
			.waitFor();
		await page.getByText("Confirm or propose a time").click();
		const form = page
			.locator("form")
			.filter({ hasText: "Confirm this request" });
		await form.getByLabel("Start").fill("2026-09-14T11:00");
		await form.getByLabel("End").fill("2026-09-14T12:00");
		await form.evaluate((element) => {
			element.requestSubmit();
			element.requestSubmit();
		});
		await page
			.getByRole("alert")
			.filter({ hasText: "could not be verified" })
			.waitFor();
		assert.equal(commands.length, 1, "synchronous duplicate blocked");
		await form.getByRole("button", { name: "Confirm appointment" }).click();
		await page
			.getByText("Appointment confirmed and saved. Notification queued.")
			.waitFor();
		assert.equal(commands.length, 2);
		assert.equal(
			commands[0].idempotency_key,
			commands[1].idempotency_key,
			"ambiguous retry retains key",
		);
		assert.equal(
			commands[1].starts_at,
			Date.parse("2026-09-14T15:00:00Z") / 1000,
		);
		await page.getByRole("button", { name: "Calendar", exact: true }).click();
		await page.getByLabel("Day", { exact: true }).fill("2026-09-14");
		await page.getByText("Confirmed", { exact: true }).waitFor();
		await page.getByLabel("Day", { exact: true }).fill("2026-09-15");
		await page.getByText("No appointments on this day.").waitFor();
		await page.getByLabel("Day", { exact: true }).fill("2026-09-14");
		assert.equal(
			await page.evaluate(
				() => document.documentElement.scrollWidth <= innerWidth,
			),
			true,
		);
		await page.evaluate(() => window.scrollTo(0, 0));
		await page.screenshot({ path: resolve(shots, "owner-desk-viewport-" + width + ".png") });
		await page.locator('.od-tabs').scrollIntoViewIfNeeded();
		await page.screenshot({ path: resolve(shots, "owner-desk-tabs-" + width + ".png") });
		assert.equal(await page.locator('.od-tabs button').evaluateAll(buttons => buttons.every(button => getComputedStyle(button).whiteSpace === 'nowrap' && button.scrollWidth <= button.clientWidth)), true);
		await page.evaluate(() => window.scrollTo(0, 0));
		await page.screenshot({
			path: resolve(shots, "owner-desk-" + width + ".png"),
			fullPage: true,
		});
		await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
		await page.screenshot({ path: resolve(shots, "owner-desk-bottom-" + width + ".png") });
		await page.getByRole("button", { name: "Openings", exact: true }).click();
		const opening = page
			.locator("form")
			.filter({ hasText: "Client-facing label" });
		await opening.getByLabel("Client-facing label").fill("Fixture opening");
		await opening.getByLabel("Start").fill("2026-09-16T11:00");
		await opening.getByLabel("End").fill("2026-09-16T12:00");
		await opening.getByRole("button", { name: "Save opening" }).click();
		await page.getByText("Opening saved.", { exact: true }).waitFor();
		assert.equal(windows.length, 1);
		assert.equal(
			await opening.getByLabel("Client-facing label").inputValue(),
			"",
		);
		await page
			.getByLabel("Website", { exact: true })
			.selectOption("other-business");
		assert.equal(
			await page.getByText("Fixture opening", { exact: true }).count(),
			0,
		);
		assert.equal(
			await page.getByText("Opening saved.", { exact: true }).count(),
			0,
		);
		await page.getByText("No appointments on this day.").waitFor();
		assert.equal(
			await page.locator(".owner-desk").getAttribute("data-brand"),
			"default",
		);
		assert.deepEqual(errors, []);
		checks.push({
			width,
			states: [
				"explicit timezone",
				"duplicate submit blocked",
				"stable ambiguous retry",
				"day filtering",
				"opening reset",
				"site isolation",
				"no overflow",
			],
		});
		await context.close();
	}
	const page = await browser.newPage();
	let replay = true,
		reads = 0;
	await page.route("https://**", (route) => route.abort());
	await page.route("**/web/api/booking-appointment/*", (route) => {
		assert.equal(new URL(route.request().url()).search, "");
		assert.equal(
			route.request().headers()["x-appointment-token"],
			"fixture-secret",
		);
		reads++;
		return route.fulfill({
			contentType: "application/json",
			body: JSON.stringify({
				ok: true,
				appointment: replay
					? {
							status: "confirmed",
							proposal_available: false,
							proposed_starts_at: 0,
						}
					: {
							status: "proposal_pending",
							proposal_available: true,
							proposed_starts_at: 2000000000,
							proposed_ends_at: 2000003600,
							timezone: "America/New_York",
							expires_at: 2000000000,
						},
			}),
		});
	});
	await page.goto(url + "appointment/fixture-id#token=fixture-secret");
	await page
		.getByRole("heading", { name: "This appointment link is unavailable." })
		.waitFor();
	assert.equal(await page.getByText(/1970/).count(), 0);
	assert.equal(
		await page.getByRole("button", { name: "Accept this time" }).count(),
		0,
	);
	replay = false;
	await page.reload();
	await page.getByRole("button", { name: "Accept this time" }).waitFor();
	assert.equal(reads, 2);
	console.log(
		JSON.stringify(
			{
				classification:
					"LOCAL FIXTURE API ONLY; no DB, provider or customer actions",
				checks,
				proposal: [
					"fragment token sent in header",
					"replay unavailable without epoch date",
				],
				screenshots: shots,
			},
			null,
			2,
		),
	);
} finally {
	await browser.close();
	await new Promise((done) => server.httpServer.close(done));
}
