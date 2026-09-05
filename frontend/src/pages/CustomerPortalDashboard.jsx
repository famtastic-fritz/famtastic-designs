import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import {
  createCustomerReferral,
  createCustomerThread,
  createWebsiteRequest,
  customerLogout,
  customerSession,
  decideWebsiteRequestProof,
  getCustomerCatalog,
  getCustomerThread,
  getCustomerWorkspace,
  replyCustomerThread,
  updateCustomerPreferences,
  updateCustomerProfile,
  updateWebsiteRequest,
  updateWebsiteRequestProofShare,
  uploadWebsiteRequestAsset,
} from '../api/customer.js';
import { collectUtmParams } from '../api/pipeline.js';
import '../portal.css';

import { LABELS } from '../components/portal/PortalShared.jsx';
import PortalNav from '../components/portal/PortalNav.jsx';
import PortalHeader from '../components/portal/PortalHeader.jsx';
import PortalHomeView from '../components/portal/PortalHomeView.jsx';
import PortalProjectsView from '../components/portal/PortalProjectsView.jsx';
import PortalServicesView from '../components/portal/PortalServicesView.jsx';
import PortalFilesView from '../components/portal/PortalFilesView.jsx';
import PortalAnalyticsView from '../components/portal/PortalAnalyticsView.jsx';
import PortalMessagesView from '../components/portal/PortalMessagesView.jsx';
import PortalShayAssistant from '../components/portal/PortalShayAssistant.jsx';
import PortalSupportView from '../components/portal/PortalSupportView.jsx';
import PortalFAQView from '../components/portal/PortalFAQView.jsx';
import PortalGrowthView from '../components/portal/PortalGrowthView.jsx';
import PortalReferralsView from '../components/portal/PortalReferralsView.jsx';
import PortalBillingView from '../components/portal/PortalBillingView.jsx';
import PortalAccountView from '../components/portal/PortalAccountView.jsx';
import PortalSettingsView from '../components/portal/PortalSettingsView.jsx';

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
  const [menu, setMenu] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const [activeThread, setActiveThread] = useState(null);
  const [faqSearch, setFaqSearch] = useState('');
  const [busy, setBusy] = useState(false);
  const [editingRequest, setEditingRequest] = useState(continuingWebsiteLead ? {} : null);
  const [activeRequestId, setActiveRequestId] = useState(null);
  const [targetRequest, setTargetRequest] = useState('');
  const proofIntentHandled = useRef(false);

  useEffect(() => {
    Promise.all([customerSession(), getCustomerWorkspace(), getCustomerCatalog()])
      .then(([s, w, c]) => {
        setSession(s);
        setWorkspace(w);
        setCatalog(c);
        setState('ready');
      })
      .catch(() => navigate('/login', { replace: true }));
  }, [navigate]);

  useEffect(() => {
    if (!workspace || !session || proofIntentHandled.current) return;
    proofIntentHandled.current = true;
    const params = new URLSearchParams(window.location.search);
    const requestedSection = params.get('tab') || params.get('section');
    if (params.get('order') && params.get('grant') === 'applied') {
      setNotice('Your sponsored order is complete — everything is activated in your workspace. Welcome aboard!');
    } else if (params.get('order') && params.get('completed') === '1') {
      setNotice('Payment received — thank you! Your services are live in this workspace and your receipt is on its way.');
    } else if (params.get('order')) {
      setNotice('Purchase complete. Your services are active in this workspace.');
    }

    const requestId = params.get('request') || '';
    const startWebsite = params.get('start') === 'website';
    const requestedProof = requestId
      ? workspace.website_requests?.find((request) => request.public_id === requestId)
      : null;
    const requestedProofReady =
      requestedProof && [3, 6].includes(requestedProof.proofs?.variants?.length);
    const readyProof = workspace.website_requests?.find(
      (request) =>
        ['customer_ready', 'notified'].includes(request.proof_review_status) &&
        [3, 6].includes(request.proofs?.variants?.length)
    );

    if (requestedSection && Object.hasOwn(LABELS, requestedSection)) {
      setSection(requestedSection);
    }
    if (startWebsite) {
      setSection('projects');
      setEditingRequest((current) => current || {});
    }
    if (requestId || readyProof) setSection('projects');
    if (requestId) {
      setTargetRequest(requestId);
      if (requestedProofReady) {
        const count = requestedProof.proofs?.variants?.length || 0;
        setNotice(
          `Your ${count} website concepts are ready below. Compare each direction and choose when you are ready.`
        );
      } else if (requestedProof) {
        setError(
          'This website request belongs to your account, but its concepts are not available for customer review yet. FAMtastic will email you when the complete set is approved.'
        );
      } else {
        setError(
          `This proof link is not connected to the account signed in as ${session.customer.email}. Sign out, then sign in with the email address that received the proof-ready message.`
        );
      }
    } else if (readyProof) {
      setTargetRequest(readyProof.public_id);
      setNotice(`Your ${readyProof.proofs.variants.length} website concepts are ready below.`);
    }
  }, [workspace, session]);

  useEffect(() => {
    if (section !== 'projects' || !targetRequest) return;
    const target = document.getElementById(`website-request-${targetRequest}`);
    if (!target) return;
    window.requestAnimationFrame(() => {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      target.focus({ preventScroll: true });
    });
  }, [section, targetRequest]);

  const org = workspace?.organization;
  const project = workspace?.projects?.[0];
  const order = workspace?.orders?.[0];
  const openThreadsCount = (workspace?.threads || []).filter(
    (thread) => thread.status === 'open'
  ).length;

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

  const go = (id) => {
    setSection(id);
    setNotice('');
    setMenu(false);
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

  const openThread = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    act(async () => {
      await createCustomerThread({
        ...Object.fromEntries(new FormData(form)),
        organization: org.public_id,
      });
      await refresh();
      form.reset();
    }, 'Your request was sent.');
  };

  const viewThread = (id) =>
    act(async () => {
      const threadData = await getCustomerThread(id);
      setActiveThread(threadData);
    });

  const replyThread = (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    act(async () => {
      await replyCustomerThread(activeThread.thread.public_id, new FormData(form).get('body'));
      const updated = await getCustomerThread(activeThread.thread.public_id);
      setActiveThread(updated);
      form.reset();
    }, 'Reply sent.');
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
    }, payload.action === 'revision' ? 'Changes requested. Fritz has your notes.' : 'Selection saved. Your chosen direction is highlighted below.');
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

  return (
    <div className={`portal-app ${menu ? 'menu-open' : ''}`}>
      <PortalNav
        section={section}
        go={go}
        menu={menu}
        setMenu={setMenu}
        org={org}
        customer={session.customer}
        openThreadsCount={openThreadsCount}
        onSignOut={() =>
          act(async () => {
            await customerLogout();
            navigate('/login');
          })
        }
      />

      <main className="portal-main">
        <PortalHeader section={section} customer={session.customer} org={org} />

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
            busy={busy}
            onSaveWebsiteRequest={saveWebsiteRequest}
            onUploadAsset={uploadReference}
            onDecideProof={decideProof}
            onShareProof={shareProof}
            navigate={navigate}
          />
        )}

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
            org={org}
            activeThread={activeThread}
            setActiveThread={setActiveThread}
            viewThread={viewThread}
            onReplyThread={replyThread}
            onOpenThread={openThread}
            busy={busy}
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
