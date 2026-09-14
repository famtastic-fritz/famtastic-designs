import { useEffect, useRef, useState } from "react";
import { useLocation, useParams, useSearchParams } from "react-router";
import { getBookingProposal, respondBookingProposal } from "../api/customer.js";
import {
	formatTime,
	proposalAvailable,
} from "../components/owner-desk/ownerDeskTime.js";

function Proposal({ appointment, token }) {
	const [state, setState] = useState({ loading: true });
	const locked = useRef(false);
	const alive = useRef(true);
	useEffect(() => {
		alive.current = true;
		getBookingProposal(appointment, token)
			.then((result) => {
				if (alive.current)
					setState(
						result.ok === true && result.appointment
							? { proposal: result.appointment }
							: { unavailable: true },
					);
			})
			.catch(() => {
				if (alive.current) setState({ unavailable: true });
			});
		return () => {
			alive.current = false;
		};
	}, [appointment, token]);
	async function decide(decision) {
		if (locked.current || !proposalAvailable(state.proposal)) return;
		locked.current = true;
		setState((old) => ({ ...old, busy: true, error: "" }));
		try {
			const result = await respondBookingProposal(appointment, token, decision);
			if (
				result.ok !== true ||
				!result.appointment ||
				(decision === "accept" && result.appointment.status !== "confirmed")
			)
				throw new Error("unverified");
			if (alive.current)
				setState({ completed: decision, appointment: result.appointment });
		} catch {
			if (alive.current)
				setState((old) => ({
					...old,
					busy: false,
					error:
						"That response could not be verified. The time may no longer be available. Refresh to check its current status.",
				}));
		} finally {
			locked.current = false;
		}
	}
	if (state.loading)
		return (
			<main className="purchase-shell">
				<p>Loading the proposed time…</p>
			</main>
		);
	if (state.completed)
		return (
			<main className="purchase-shell">
				<span>Tighten Up Your Locs · Response saved</span>
				<h1>
					{state.completed === "accept"
						? "Your appointment is confirmed."
						: "The proposed time was declined."}
				</h1>
				{state.completed === "accept" ? (
					<p>
						{formatTime(
							state.appointment.starts_at,
							state.appointment.timezone,
						)}
					</p>
				) : (
					<p>Contact Shay to choose another time.</p>
				)}
			</main>
		);
	if (state.unavailable || !proposalAvailable(state.proposal))
		return (
			<main className="purchase-shell">
				<h1>This appointment link is unavailable.</h1>
				<p>
					It may have expired, been answered or been replaced. Contact Shay for
					the current time.
				</p>
			</main>
		);
	const proposal = state.proposal;
	return (
		<main className="purchase-shell">
			<span>Tighten Up Your Locs</span>
			<h1>Shay proposed a time.</h1>
			<p>
				<strong>
					{formatTime(proposal.proposed_starts_at, proposal.timezone)}
				</strong>
				<br />
				until {formatTime(proposal.proposed_ends_at, proposal.timezone)}
				<br />
				Timezone: {proposal.timezone}
			</p>
			<div style={{ display: "grid", gap: 12, maxWidth: 420 }}>
				<button
					className="btn"
					style={{ minHeight: 44, background: "#8f1831", color: "#fff" }}
					disabled={state.busy}
					onClick={() => decide("accept")}
				>
					Accept this time
				</button>
				<button
					className="btn"
					style={{ minHeight: 44 }}
					disabled={state.busy}
					onClick={() => decide("decline")}
				>
					I need another time
				</button>
			</div>
			{state.error && <p role="alert">{state.error}</p>}
		</main>
	);
}
export default function AppointmentProposalPage() {
	const { appointment } = useParams();
	const [search] = useSearchParams();
	const location = useLocation();
	const token =
		new URLSearchParams(location.hash.slice(1)).get("token") ||
		search.get("token") ||
		"";
	return (
		<Proposal
			key={appointment + ":" + token}
			appointment={appointment}
			token={token}
		/>
	);
}
