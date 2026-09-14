import { useState } from "react";
import OwnerDesk from "../owner-desk/OwnerDesk.jsx";
import {
	bookingOwnerAdapter,
	ownerSitePresentation,
} from "../../api/bookingOwnerAdapter.js";
import { Empty, Panel } from "./PortalShared.jsx";

export default function PortalBookingRequestsView({ sites = [] }) {
	const [selected, setSelected] = useState("");
	const site = sites.find((item) => item.site_key === selected) || sites[0];
	if (!site)
		return (
			<Panel title="Owner Desk">
				<Empty>
					No owner-operated website is linked to this workspace yet.
				</Empty>
			</Panel>
		);
	return (
		<div style={{ minWidth: 0, overflowX: "clip" }}>
			{sites.length > 1 && (
				<label>
					Website
          <select
            aria-label="Website"
						style={{ minHeight: 44, width: "100%", marginBottom: 16 }}
						value={site.site_key}
						onChange={(event) => setSelected(event.target.value)}
					>
						{sites.map((item) => (
							<option key={item.site_key} value={item.site_key}>
								{item.business_name || item.site_key}
							</option>
						))}
					</select>
				</label>
			)}
			<OwnerDesk
				key={site.site_key}
				site={ownerSitePresentation(site)}
				adapter={bookingOwnerAdapter}
			/>
		</div>
	);
}
