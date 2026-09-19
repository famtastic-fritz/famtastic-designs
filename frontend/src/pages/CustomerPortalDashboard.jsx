import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import {
  createCustomerReferral,
  createWebsiteRequest,
  customerLogout,
  customerSession,
  decideWebsiteRequestProof,
  getCustomerCatalog,
  getCustomerWorkspace,
  updateCustomerPreferences,
  updateCustomerProfile,
  updateWebsiteRequest,
  updateWebsiteRequestArchive,
  updateWebsiteRequestProofShare,
  uploadWebsiteRequestAsset,
} from '../api/customer.js';
import { collectUtmParams } from '../api/pipeline.js';
import '../portal.css';

import { LABELS } from '../components/portal/PortalShared.jsx';
import PortalNav from '../components/portal/PortalNav.jsx';
import PortalBookingRequestsView from '../components/portal/PortalBookingRequestsView.jsx';
import PortalHeader from '../components/portal/PortalHeader.jsx';
import PortalHomeView from '../components/portal/PortalHomeView.jsx';
import PortalProjectsView from '../components/portal/PortalProjectsView.jsx';
import PortalServicesView from '../components/portal/PortalServicesView.jsx';
import PortalFilesView from '../components/portal/PortalFilesView.jsx';
import PortalAnalyticsView from '../components/portal/PortalAnalyticsView.jsx';
import PortalMessagesView from '../components/portal/PortalMessagesView.jsx';
import usePortalInbox from '../components/portal/usePortalInbox.js';
import PortalShayAssistant from '../components/portal/PortalShayAssistant.jsx';
import PortalSupportView from '../components/portal/PortalSupportView.jsx';
import PortalFAQView from '../components/portal/PortalFAQView.jsx';
import PortalGrowthView from '../components/portal/PortalGrowthView.jsx';
import PortalReferralsView from '../components/portal/PortalReferralsView.jsx';
import PortalBillingView from '../components/portal/PortalBillingView.jsx';
import PortalAccountView from '../components/portal/PortalAccountView.jsx';
import PortalSettingsView from '../components/portal/PortalSettingsView.jsx';
import { getStaffCommandCenterLink, loadCustomerPortal } from './customerPortalLoader.js';
import { portalReturn } from './portalReturn.js';

export default function CustomerPortalDashboard() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const continuingWebsiteLead = searchParams.get('start') === 'website';
  const rawTab = searchParams.get('tab') || searchParams.get('section');
  const requestedTab = rawTab === 'products' ? 'projects' : rawTab;
  const initialSection =
    requestedTab && Object.hasOwn(LABELS, requestedTab)
      ? requestedTab
      : continuingWebsiteLead
      ? 'projects'
      : 'home';

  const [section, setSection] = useState(initialSection);
  const [session, setSession] = useState(null);
  const [workspace, setWorkspace] = useState(null);
  const [catalog, setCatalog] = useState(null);
  const [state, setState] = useState('loading');
  const [loadAttempt, setLoadAttempt] = useState(0);
  const [menu, setMenu] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const [faqSearch, setFaqSearch] = useState('');
  const [busy, setBusy] = useState(false);
  const [editingRequest, setEditingRequest] = useState(continuingWebsiteLead ? {} : null);
  const [activeRequestId, setActiveRequestId] = useState(null);
  const [targetRequest, setTargetRequest] = useState('');
  const proofIntentHandled = useRef(false);
  const messageIntentHandled = useRef('');
  const messages = usePortalInbox({ enabled: state === 'ready', isViewing: section === 'messages', organization: workspace?.organization?.public_id });

  useEffect(() => {
    let cancelled = false;
    // Capture before the asynchronous load: a second late 401 must not replace
    // the original project return with the login page (StrictMode/retry race).
    const destination = portalReturn(window.location.pathname + window.location.search);
    setState('loading');
    setError('');
    loadCustomerPortal({
      getSession: customerSession,
      getWorkspace: getCustomerWorkspace,
      getCatalog: getCustomerCatalog,
    })
      .then(({ session: nextSession, workspace: nextWorkspace, catalog: nextCatalog }) => {
        if (cancelled) return;
        setSession(nextSession);
        setWorkspace(nextWorkspace);
        setCatalog(nextCatalog);
        if (!nextWorkspace && nextSession.can_manage_messages === true) {
          setSection(['messages', 'billing'].includes(requestedTab) ? requestedTab : 'messages');
        }
        setState('ready');
      })
      .catch((exception) => {
        if (cancelled) return;
        if ([401, 403].includes(exception?.status)) {
          navigate('/login?redirect=' + encodeURIComponent(destination), { replace: true });
          return;
        }
        setError('Your command center could not connect. Your saved work is unchanged. Check your connection and try again.');
        setState('error');
      });
    return () => { cancelled = true; };
  }, [navigate, loadAttempt]);

  useEffect(() => {
    const thread = searchParams.get('thread');
    if (state !== 'ready' || !thread || messageIntentHandled.current === thread) return;
    messageIntentHandled.current = thread;
    setSection('messages');
    messages.loadThread(thread);
  }, [state, searchParams, messages.loadThread]);

  useEffect(() => {
    if (!workspace || !session || proofIntentHandled.current) return;
    proofIntentHandled.current = true;
    const params = new URLSearchParams(window.location.search);
    const requestedSection = params.get('tab') || params.get('section');
    const returnedOrderReference = params.get('order');
    if (returnedOrderReference) {
      const returnedOrder = (workspace.orders || []).find((candidate) =>
        [candidate.id, candidate.uuid, candidate.order_number]
          .filter((value) => value !== undefined && value !== null && value !== '')
          .some((value) => String(value) === returnedOrderReference)
      );
      if (!returnedOrder) {
        setError('This return link does not match an order in your account. No payment or activation is being claimed. Open Billing or contact FAMtastic Support for help.');
      }
      else if (returnedOrder.payment_status === 'paid') {
        setNotice(
          params.get('grant') === 'applied'
            ? 'Your sponsored order is confirmed. Follow its fulfillment status in Projects.'
            : 'Payment is confirmed on your account. Follow fulfillment in Projects and open Billing for the recorded order.'
        );
      }
      else {
        setSection('billing');
        setNotice('Your order is recorded, but payment is not confirmed yet. Billing shows the current saved status.');
      }
    }

    const requestId = params.get('request') || '';
    const startWebsite = params.get('start') === 'website';
    const requestedProof = requestId
      ? workspace.website_requests?.find((request) => request.public_id === requestId)
      : null;
    const requestedProofReady =
      requestedProof && !requestedProof.customer_archived && [3, 6].includes(requestedProof.proofs?.variants?.length);
    const readyProof = workspace.website_requests?.find(
      (request) =>
        !request.customer_archived &&
        ['customer_ready', 'notified'].includes(request.proof_review_status) &&
        [3, 6].includes(request.proofs?.variants?.length)
    );

    const hasRequestedSection = requestedSection && Object.hasOwn(LABELS, requestedSection);
    const showDefaultReadyProof = readyProof && !hasRequestedSection && !startWebsite;
    if (hasRequestedSection) {
      setSection(requestedSection);
    }
    if (startWebsite) {
      setSection('projects');
      setEditingRequest((current) => current || {});
    }
    if (requestId || showDefaultReadyProof) setSection('projects');
    if (requestId) {
      setTargetRequest(requestId);
      if (requestedProofReady) {
        const count = requestedProof.proofs?.variants?.length || 0;
        setNotice(
          `Your ${count} website concepts are ready below. Compare each direction and choose when you are ready.`
        );
      } else if (requestedProof?.customer_archived) {
        setError('This project is in Archive. Open Archive below and restore it to review the concepts again.');
      } else if (requestedProof) {
        setError(
          'This website request belongs to your account, but its concepts are not available for customer review yet. FAMtastic will email you when the complete set is approved.'
        );
      } else {
        setError(
          `This proof link is not connected to the account signed in as ${session?.customer?.email || 'this account'}. Sign out, then sign in with the email address that received the proof-ready message.`
        );
      }
    } else if (showDefaultReadyProof) {
      setTargetRequest(readyProof.public_id);
      setNotice(`Your ${readyProof.proofs.variants.length} website concepts are ready below.`);
    }
  }, [workspace, session]);

  useEffect(() => {
    if (section !== 'projects' || !targetRequest) return;
    const target = document.getElementById(`concepts-${targetRequest}`)
      || document.getElementById(`website-request-${targetRequest}`);
    if (!target) return;
    window.requestAnimationFrame(() => {
      target.scrollIntoView({ behavior: 'instant', block: 'start' });
      target.focus({ preventScroll: true });
    });
  }, [section, targetRequest]);

  const org = workspace?.organization;
  const project = workspace?.projects?.[0];
  const order = workspace?.orders?.[0];
  const unreadMessagesCount = messages.inbox?.unread_count || 0;
  const needsReplyCount = messages.inbox?.needs_reply_count || 0;
  const staffOnly = !workspace && session?.can_manage_messages === true;
  const staffCommandCenter = getStaffCommandCenterLink(session);

  const nextAction = useMemo(() => {
    if (!order) return 'Tell us what your business needs next';
    if (order.payment_status !== 'paid') return 'Complete your purchase';
    if (!project) return 'Complete your project brief';
    if (project.approval_status !== 'approved') return 'Review and approve your project';
    return 'See your next growth opportunity';
  }, [order, project]);

  const filteredFaqs = useMemo(() => {
    const query = faqSearch.toLowerCase();
    return (workspace?.faqs || []).filter((item) =>
      `${item.question} ${item.answer} ${item.category}`.toLowerCase().includes(query)
    );
  }, [workspace, faqSearch]);

  if (state === 'loading') {
    return (
      <div className="portal-state">
        <i />Opening your customer command center…
      </div>
    );
  }

  if (state === 'error') {
    return (
      <div className="portal-state portal-state--error" role="alert">
        <strong>We could not open your command center.</strong>
        <p>{error}</p>
        <button type="button" onClick={() => setLoadAttempt((attempt) => attempt + 1)}>
          Try again
        </button>
      </div>
    );
  }

  const go = (id) => {
    if (staffOnly && !['messages', 'billing'].includes(id)) return;
    setSection(id);
    setNotice('');
    setMenu(false);
    navigate(`/portal?tab=${encodeURIComponent(id)}`, { replace: true });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const refresh = async () => setWorkspace(await getCustomerWorkspace(org.public_id));

  const act = async (work, success) => {
    setError('');
    setBusy(true);
    try {
      const value = await work();
      if (success) setNotice(success);
      return { ok: true, value };
    } catch (exception) {
      setError(exception.message);
      return { ok: false, value: null };
    } finally {
      setBusy(false);
    }
  };

  const saveProfile = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    act(async () => {
      const updated = await updateCustomerProfile(Object.fromEntries(new FormData(form)));
      setSession(updated);
    }, 'Profile updated.');
  };

  const saveSettings = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    data.topics = new FormData(form).getAll('topics');
    act(async () => {
      const result = await updateCustomerPreferences(data);
      setWorkspace((current) => ({ ...current, preferences: result.preferences }));
      setSession((current) => ({
        ...current,
        customer: {
          ...current.customer,
          marketing_status: result.preferences.deals_promotions ? 'subscribed' : 'unsubscribed',
        },
      }));
    }, 'Communication preferences saved.');
  };

  const refer = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    act(async () => {
      await createCustomerReferral({
        ...Object.fromEntries(new FormData(form)),
        organization: org.public_id,
      });
      await refresh();
      form.reset();
    }, 'Referral recorded. Thank you for sharing FAMtastic.');
  };

  const saveWebsiteRequest = (event, explicitRequestId = null) => {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    data.organization = org.public_id;
    data.recommendation_requested = formData.has('recommendation_requested');
    data.utm = collectUtmParams();
    data.action = event.nativeEvent?.submitter?.value || data.action || 'save';

    const targetId = explicitRequestId || data.request_id || editingRequest?.public_id || activeRequestId;

    act(async () => {
      let result;
      if (targetId) {
        const existingReq = (workspace?.website_requests || []).find((r) => r.public_id === targetId);
        if (!data.project_name && existingReq?.project_name) {
          data.project_name = existingReq.project_name;
        }
        if (!data.project_type && existingReq?.project_type) {
          data.project_type = existingReq.project_type;
        }
        if (!data.business_name && existingReq?.business_name) {
          data.business_name = existingReq.business_name;
        }
        result = await updateWebsiteRequest(targetId, data);
      } else {
        if (!data.project_name) {
          data.project_name = `${org?.name || 'My'} Business Website`;
        }
        result = await createWebsiteRequest(data);
      }
      await refresh();
      if (editingRequest) {
        setEditingRequest(result.website_request);
        window.setTimeout(
          () =>
            document
              .getElementById('website-request-editor')
              ?.scrollIntoView({ behavior: 'smooth', block: 'start' }),
          80
        );
      }
      return result;
    }, data.action === 'submit' ? 'Website request submitted. Your receipt, owner alert, and proof routine are queued.' : 'Website request & domain saved.');
  };

  const uploadReference = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    act(async () => {
      const result = await uploadWebsiteRequestAsset(editingRequest.public_id, new FormData(form));
      await refresh();
      setEditingRequest((current) => ({
        ...current,
        assets: result.duplicate ? current.assets : [...(current.assets || []), result.asset],
      }));
      form.reset();
    }, 'Reference file saved securely with this website request.');
  };

  const decideProof = async (requestId, payload) => {
    const result = await act(async () => {
      const decision = await decideWebsiteRequestProof(requestId, payload);
      await refresh();
      return decision;
    }, payload.action === 'revision'
      ? 'Changes requested. FAMtastic has your notes.'
      : 'Selection saved. Your staging build is the next recorded step; checkout stays closed until that review is ready.');
    return result.ok;
  };

  const shareProof = async (requestId, action) => {
    const message =
      action === 'disable'
        ? 'Unlisted sharing is off. The previous link no longer works.'
        : action === 'rotate'
        ? 'A new unlisted link is ready. The previous link no longer works.'
        : 'Unlisted sharing is on. Copy the link below when you are ready.';
    const result = await act(async () => {
      await updateWebsiteRequestProofShare(requestId, action);
      await refresh();
    }, message);
    return result.ok;
  };

  const archiveWebsiteRequest = async (requestId, action) => {
    const result = await act(async () => {
      await updateWebsiteRequestArchive(requestId, action);
      if (requestId === activeRequestId) setActiveRequestId(null);
      if (requestId === targetRequest) setTargetRequest('');
      await refresh();
    }, action === 'archive'
      ? 'Project moved to Archive. Nothing was deleted or cancelled.'
      : 'Project restored to your active list.');
    return result.ok;
  };

  return (
    <div className={`portal-app ${menu ? 'menu-open' : ''} ${section === 'messages' ? 'is-messaging' : ''}`}>
      <PortalNav
        hasBookingSites={Boolean(workspace?.booking_sites?.length)}
        section={section}
        go={go}
        menu={menu}
        setMenu={setMenu}
        org={org}
        customer={session.customer || session.staff}
        unreadMessagesCount={unreadMessagesCount}
        needsReplyCount={needsReplyCount}
        staffOnly={staffOnly}
        onSignOut={() =>
          act(async () => {
            await customerLogout();
            navigate('/login');
          })
        }
      />

      <main className="portal-main">
        <PortalHeader section={section} customer={session.customer || session.staff} org={org} isStaff={staffOnly || (section === 'messages' && messages.inbox?.is_staff)} />

        {staffCommandCenter && (
          <a className="portal-staff-command-center" href={staffCommandCenter.href}>
            <span aria-hidden="true">↗</span>
            {staffCommandCenter.label}
          </a>
        )}

        {section !== 'messages' && messages.error && (
          <div className="portal-inbox-feedback" role="status">
            Messages need attention. <button type="button" onClick={() => go('messages')}>Open inbox</button>
          </div>
        )}

        {notice && (
          <div className="portal-notice" role="status">
            {notice}
            <button aria-label="Dismiss" onClick={() => setNotice('')}>
              ×
            </button>
          </div>
        )}
        {error && (
          <div className="portal-notice portal-notice--error" role="alert">
            {error}
            <button aria-label="Dismiss" onClick={() => setError('')}>
              ×
            </button>
          </div>
        )}

        {section === 'home' && (
          <PortalHomeView
            workspace={workspace}
            org={org}
            order={order}
            project={project}
            nextAction={nextAction}
            go={go}
            catalog={catalog}
            inbox={messages.inbox}
          />
        )}


        {section === 'projects' && (
          <PortalProjectsView
            workspace={workspace}
            editingRequest={editingRequest}
            setEditingRequest={setEditingRequest}
            activeRequestId={activeRequestId}
            setActiveRequestId={setActiveRequestId}
            targetRequest={targetRequest}
            setTargetRequest={setTargetRequest}
            busy={busy}
            onSaveWebsiteRequest={saveWebsiteRequest}
            onUploadAsset={uploadReference}
            onDecideProof={decideProof}
            onShareProof={shareProof}
            onArchiveRequest={archiveWebsiteRequest}
            navigate={navigate}
          />
        )}

        {section === 'booking' && <PortalBookingRequestsView key={org.public_id} sites={workspace.booking_sites || []} />}

        {section === 'services' && (
          <PortalServicesView
            workspace={workspace}
            catalog={catalog}
            go={go}
          />
        )}

        {section === 'files' && (
          <PortalFilesView
            workspace={workspace}
            busy={busy}
            onUploadAsset={uploadReference}
            activeRequestId={activeRequestId}
            go={go}
          />
        )}

        {section === 'results' && (
          <PortalAnalyticsView
            workspace={workspace}
            go={go}
          />
        )}

        {section === 'messages' && (
          <PortalMessagesView
            workspace={workspace}
            messages={messages}
          />
        )}

        {section === 'shay' && (
          <PortalShayAssistant
            workspace={workspace}
            go={go}
          />
        )}

        {section === 'support' && (
          <PortalSupportView
            workspace={workspace}
            go={go}
          />
        )}

        {section === 'faq' && (
          <PortalFAQView
            filteredFaqs={filteredFaqs}
            faqSearch={faqSearch}
            setFaqSearch={setFaqSearch}
            go={go}
          />
        )}

        {section === 'grow' && (
          <PortalGrowthView
            workspace={workspace}
            go={go}
            organization={org}
          />
        )}

        {section === 'referrals' && (
          <PortalReferralsView
            workspace={workspace}
            onRefer={refer}
            busy={busy}
          />
        )}

        {section === 'billing' && (
          <PortalBillingView
            workspace={workspace}
            inbox={messages.inbox}
            go={go}
          />
        )}

        {section === 'account' && (
          <PortalAccountView
            session={session}
            workspace={workspace}
            onSaveProfile={saveProfile}
            busy={busy}
          />
        )}

        {section === 'settings' && (
          <PortalSettingsView
            workspace={workspace}
            onSaveSettings={saveSettings}
            busy={busy}
          />
        )}
      </main>
    </div>
  );
}
