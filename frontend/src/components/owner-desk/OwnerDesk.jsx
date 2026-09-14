import { useEffect, useRef, useState } from "react";
import { dayKey, formatTime, interval } from "./ownerDeskTime.js";
import "./owner-desk.css";

const statuses = {
	confirmed: "Confirmed",
	proposal_pending: "Waiting for client",
	cancelled: "Cancelled",
	completed: "Completed",
	declined: "Declined",
	closed: "Closed",
	new: "New",
	reviewing: "Reviewing",
	responded: "Responded",
};
const messageFor = (error) =>
	error?.code === "appointment_slot_conflict"
		? "That time overlaps another appointment."
		: error?.code === "appointment_revision_conflict"
			? "This appointment changed. Refresh before continuing."
			: error?.message?.includes("time") || error?.message?.includes("End must")
				? error.message
				: "The result could not be verified. Retry the same action or refresh to check its status.";
function Card({ title, children }) {
	return (
		<section className="od-card">
			{title && <h3>{title}</h3>}
			{children}
		</section>
	);
}
function TimeForm({ title, submitLabel, disabled, timezone, onSubmit }) {
	return (
		<form
			className="od-form"
			onSubmit={async (event) => {
				event.preventDefault();
				const form = event.currentTarget;
				const data = new FormData(form);
				const saved = await onSubmit(() =>
					interval(data.get("start"), data.get("end"), timezone),
				);
				if (saved && form.isConnected) form.reset();
			}}
		>
			<strong>{title}</strong>
			<small>All times in {timezone}</small>
			<label>
				Start
				<input
					name="start"
					type="datetime-local"
					required
					disabled={disabled}
				/>
			</label>
			<label>
				End
				<input name="end" type="datetime-local" required disabled={disabled} />
			</label>
			<button disabled={disabled} type="submit">
				{submitLabel}
			</button>
		</form>
	);
}
// Host adapter supplies authenticated persistence. No provider or business identity baked in.
function Desk({ site, adapter }) {
	const [tab, setTab] = useState("calendar");
	const [timezone, setTimezone] = useState(
		site.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC",
	);
	const [day, setDay] = useState(() => dayKey(Date.now() / 1000, timezone));
	const [data, setData] = useState(null);
	const [error, setError] = useState(""),
		[notice, setNotice] = useState("");
	const [busy, setBusy] = useState(false),
		[refresh, setRefresh] = useState(0);
	const locked = useRef(false),
		alive = useRef(true),
		keys = useRef(new Map());
	useEffect(() => {
		alive.current = true;
		return () => {
			alive.current = false;
		};
	}, []);
	useEffect(() => {
		let current = true;
		setData(null);
		setError("");
		adapter
			.load(site.site_key)
			.then((result) => {
				if (current) setData(result);
			})
			.catch(() => {
				if (current)
					setError("The Owner Desk could not be loaded. Refresh to try again.");
			});
		return () => {
			current = false;
		};
	}, [adapter, site.site_key, refresh]);
	async function save(operation, payloadFactory, success) {
		if (locked.current) return false;
		locked.current = true;
		setBusy(true);
		setError("");
		setNotice("");
		try {
			const payload = payloadFactory(),
				signature = JSON.stringify([operation, payload]);
			if (!keys.current.has(signature))
				keys.current.set(signature, `owner:${crypto.randomUUID()}`);
			const result = await adapter[operation](site.site_key, {
				...payload,
				idempotency_key: keys.current.get(signature),
			});
			if (result?.ok !== true) throw new Error("unverified");
			keys.current.delete(signature);
			if (alive.current) {
				setNotice(success);
				setRefresh((value) => value + 1);
			}
			return true;
		} catch (cause) {
			if (alive.current) setError(messageFor(cause));
			return false;
		} finally {
			locked.current = false;
			if (alive.current) setBusy(false);
		}
	}
	const appointments = data?.appointments || [],
		requests = data?.requests || [],
		windows = data?.windows || [];
	const disabled = busy || !data;
	const selectedAppointments = appointments.filter(
		(item) =>
			dayKey(item.starts_at || item.proposed_starts_at, timezone) === day ||
			dayKey(item.proposed_starts_at, timezone) === day,
	);
	return (
		<div
			className="owner-desk"
			data-brand={site.brand_id || "default"}
			style={site.theme || {}}
		>
			<header className="od-card">
				<p className="od-eyebrow">{site.business_name || "Your business"}</p>
				<h2>Owner Desk</h2>
				<p>Your calendar, client requests and available openings.</p>
				<label>
					Calendar timezone
					<select
						value={timezone}
						disabled={busy}
						onChange={(event) => setTimezone(event.target.value)}
					>
						{[
							...new Set([
								timezone,
								"America/New_York",
								"America/Chicago",
								"America/Denver",
								"America/Los_Angeles",
								"Pacific/Honolulu",
								"UTC",
							]),
						].map((zone) => (
							<option key={zone}>{zone}</option>
						))}
					</select>
				</label>
				{!site.timezone && (
					<small>
						Starts with this device’s timezone. Choose the timezone for your
						business before entering times.
					</small>
				)}
				<nav aria-label="Owner Desk sections" className="od-tabs">
					{[
						["calendar", "Calendar"],
						["requests", "Requests"],
						["openings", "Openings"],
					].map(([id, label]) => (
						<button
							key={id}
							aria-pressed={tab === id}
							onClick={() => setTab(id)}
						>
							{label}
						</button>
					))}
				</nav>
				{notice && <p role="status">{notice}</p>}
				{error && <p role="alert">{error}</p>}
				<button
					disabled={busy}
					onClick={() => setRefresh((value) => value + 1)}
				>
					Refresh
				</button>
			</header>
			{!data ? (
				<p role="status">
					{error ? "Information unavailable." : "Loading your business…"}
				</p>
			) : (
				<>
					{data.appointments_has_more && (
						<p role="status">
							Only part of your appointment history is loaded. Some dates may
							have additional appointments.
						</p>
					)}
					{tab === "calendar" && (
						<>
							<Card title="Calendar">
								<div className="od-row">
									<label>
										Day
										<input
											type="date"
											value={day}
											onChange={(event) => setDay(event.target.value)}
										/>
									</label>
									<button
										onClick={() => setDay(dayKey(Date.now() / 1000, timezone))}
									>
										Today
									</button>
								</div>
								<p>
									{day} · {timezone}
								</p>
								{selectedAppointments.length === 0 && (
                <p>{data.appointments_has_more ? 'No appointments in the loaded results for this day.' : 'No appointments on this day.'}</p>
								)}
							</Card>
							{selectedAppointments.map((item) => (
								<Card
									key={item.id}
									title={item.customer?.name || "Client appointment"}
								>
									<p>
										<strong>{statuses[item.status] || "Appointment"}</strong>
									</p>
									{Number(item.starts_at) > 0 && (
										<p>
											Appointment: {formatTime(item.starts_at, timezone)} —{" "}
											{formatTime(item.ends_at, timezone)}
										</p>
									)}
									{Number(item.proposed_starts_at) > 0 && (
										<p>
											Proposed: {formatTime(item.proposed_starts_at, timezone)}{" "}
											— {formatTime(item.proposed_ends_at, timezone)}
										</p>
									)}
									<p>{item.customer?.email}</p>
									{["confirmed", "proposal_pending"].includes(item.status) && (
										<>
											<div className="od-row">
												{item.status === "confirmed" && (
													<button
														disabled={disabled}
														onClick={() =>
															save(
																"command",
																() => ({
																	action: "complete",
																	appointment_id: item.id,
																	expected_revision: item.revision,
																}),
																"Completion saved.",
															)
														}
													>
														Mark complete
													</button>
												)}
												<button
													disabled={disabled}
													onClick={() =>
														save(
															"command",
															() => ({
																action: "cancel",
																appointment_id: item.id,
																expected_revision: item.revision,
															}),
															"Cancellation saved. Notification queued.",
														)
													}
												>
													Cancel appointment
												</button>
											</div>
											<details>
												<summary>Propose a new time</summary>
												<TimeForm
													title="New proposed time"
													timezone={timezone}
													disabled={disabled}
													submitLabel="Save proposed time"
													onSubmit={(times) =>
														save(
															"command",
															() => ({
																action: "reschedule",
																appointment_id: item.id,
																expected_revision: item.revision,
																...times(),
															}),
															"Proposed time saved. Notification queued.",
														)
													}
												/>
											</details>
										</>
									)}
								</Card>
							))}
						</>
					)}
					{tab === "requests" && (
						<>
							<Card title="Client requests">
								{!requests.length && <p>No requests have been received.</p>}
							</Card>
							{requests.map((item) => {
								const existing = appointments.find(
									(appointment) =>
										Number(appointment.request_id) === Number(item.id),
								);
								return (
									<Card
										key={item.id}
										title={item.customer_name || "Website visitor"}
									>
										<p>
											{item.service_key} · {item.requested_window}
										</p>
										<p className="od-message">
											{item.message || "No message supplied."}
										</p>
										<p>
											{item.email}
											<br />
											{item.phone}
										</p>
										<p>{statuses[item.status] || "Request received"}</p>
										{existing ? (
											<p>
												Appointment record:{" "}
												{statuses[existing.status] || "Saved"}.
											</p>
										) : (
											!["closed", "declined"].includes(item.status) && (
												<>
													<details>
														<summary>Confirm or propose a time</summary>
														<TimeForm
															title="Confirm this request"
															timezone={timezone}
															disabled={disabled}
															submitLabel="Confirm appointment"
															onSubmit={(times) =>
																save(
																	"command",
																	() => ({
																		action: "confirm",
																		request_id: item.id,
																		...times(),
																	}),
																	"Appointment confirmed and saved. Notification queued.",
																)
															}
														/>
														<TimeForm
															title="Propose another time"
															timezone={timezone}
															disabled={disabled}
															submitLabel="Save proposed time"
															onSubmit={(times) =>
																save(
																	"command",
																	() => ({
																		action: "propose",
																		request_id: item.id,
																		...times(),
																	}),
																	"Proposed time saved. Notification queued.",
																)
															}
														/>
													</details>
													<button
														disabled={disabled}
														onClick={() =>
															save(
																"closeRequest",
																() => ({
																	request_id: item.id,
																	status: "closed",
																}),
																"Request closed. History retained.",
															)
														}
													>
														Close request
													</button>
												</>
											)
										)}
									</Card>
								);
							})}
						</>
					)}
					{tab === "openings" && (
						<>
							<Card title="Publish an opening">
								<p>
									An opening invites requests. It does not reserve an
									appointment.
								</p>
								<form
									className="od-form"
									onSubmit={async (event) => {
										event.preventDefault();
										const form = event.currentTarget;
										const values = new FormData(form);
										const done = await save(
											"createOpening",
											() => ({
												label: values.get("label"),
												...interval(
													values.get("start"),
													values.get("end"),
													timezone,
												),
												status: values.get("published") ? "published" : "draft",
												service_keys: [],
											}),
											"Opening saved.",
										);
										if (done && form.isConnected) form.reset();
									}}
								>
									<label>
										Client-facing label
										<input
											name="label"
											required
											maxLength={100}
											disabled={disabled}
										/>
									</label>
									<small>Times in {timezone}</small>
									<label>
										Start
										<input
											name="start"
											type="datetime-local"
											required
											disabled={disabled}
										/>
									</label>
									<label>
										End
										<input
											name="end"
											type="datetime-local"
											required
											disabled={disabled}
										/>
									</label>
									<label className="od-check">
										<input
											name="published"
											type="checkbox"
											disabled={disabled}
										/>{" "}
										Publish on my website now
									</label>
									<button disabled={disabled}>Save opening</button>
								</form>
							</Card>
							{windows.map((item) => (
								<Card key={item.id} title={item.label}>
									<p>
										{formatTime(item.starts_at, timezone)} —{" "}
										{formatTime(item.ends_at, timezone)}
									</p>
									<p>{item.status}</p>
									<button
										disabled={disabled}
										onClick={() =>
											save(
												"updateOpening",
												() => ({
													window_id: item.id,
													status:
														item.status === "published"
															? "hidden"
															: "published",
												}),
												"Opening visibility saved.",
											)
										}
									>
										{item.status === "published"
											? "Hide opening"
											: "Publish opening"}
									</button>
								</Card>
							))}
						</>
					)}
				</>
			)}
		</div>
	);
}

export default function OwnerDesk({ site, adapter }) {
	return <Desk key={site.site_key} site={site} adapter={adapter} />;
}
