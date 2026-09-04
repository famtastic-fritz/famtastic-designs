import { Routes, Route, Navigate } from 'react-router';
import Layout from './components/Layout.jsx';
import HomePage from './pages/HomePage.jsx';
import ContentPage from './pages/ContentPage.jsx';
import NodeView from './components/NodeView.jsx';
import LoginPage from './pages/LoginPage.jsx';
import AdminDashboardPage from './pages/AdminDashboardPage.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import { UserProvider } from './auth/UserContext.jsx';
import ProofHub from './pages/ProofHub.jsx';
import PaymentReturnPage from './pages/PaymentReturnPage.jsx';
import PaymentCancelPage from './pages/PaymentCancelPage.jsx';
import IntakePage from './pages/IntakePage.jsx';
import ProofStatusPage from './pages/ProofStatusPage.jsx';
import ServicesHubPage from './pages/ServicesHubPage.jsx';
import ServicePage from './pages/ServicePage.jsx';
import PackagesHubPage from './pages/PackagesHubPage.jsx';
import PackagePage from './pages/PackagePage.jsx';
import WorkHubPage from './pages/WorkHubPage.jsx';
import CaseStudyPage from './pages/CaseStudyPage.jsx';
import BlogHubPage from './pages/BlogHubPage.jsx';
import BlogPostPage from './pages/BlogPostPage.jsx';
import FAQHubPage from './pages/FAQHubPage.jsx';
import ContactPage from './pages/ContactPage.jsx';
import StartPage from './pages/StartPage.jsx';
import AliasPage from './pages/AliasPage.jsx';
import SEO from './components/SEO.jsx';
import GoogleAnalytics from './components/GoogleAnalytics.jsx';
import ClientPortalPage from './pages/ClientPortalPage.jsx';
import CustomerPortalDashboard from './pages/CustomerPortalDashboard.jsx';
import VerifyEmailPage from './pages/VerifyEmailPage.jsx';
import ResetPasswordPage from './pages/ResetPasswordPage.jsx';
import PurchasePage from './pages/PurchasePage.jsx';
import FiftyFiveCentWebsitePage from './pages/FiftyFiveCentWebsitePage.jsx';
import ScrollToTop from './components/ScrollToTop.jsx';
import ProofSharePage from './pages/ProofSharePage.jsx';
import PublicPreviewRoomPage from './pages/PublicPreviewRoomPage.jsx';
import IntakeHubPage from './pages/IntakeHubPage.jsx';
import SpecializedIntakePage from './pages/SpecializedIntakePage.jsx';
import DeepDivePage from './pages/DeepDivePage.jsx';
import PrivacyPolicyPage from './pages/PrivacyPolicyPage.jsx';
import TermsOfServicePage from './pages/TermsOfServicePage.jsx';

export default function App() {
  return (
    <UserProvider>
      <SEO />
      <GoogleAnalytics />
      <ScrollToTop />
      <Routes>
        {/* Public, token-scoped prospect pipeline (no marketing chrome, no auth). */}
        {/* /p/:token enters via ProofHub: active proof campaign → proof hub;
            converted/paid/no-campaign prospects fall through to the existing flow. */}
        <Route path="/p/:token" element={<ProofHub />} />
        <Route path="/p/:token/return" element={<PaymentReturnPage />} />
        <Route path="/p/:token/cancel" element={<PaymentCancelPage />} />
        <Route path="/p/:token/intake" element={<IntakePage />} />
        <Route path="/p/:token/status" element={<ProofStatusPage />} />
        <Route path="/portal/:token" element={<ClientPortalPage />} />
        <Route path="/portal" element={<CustomerPortalDashboard />} />
        <Route path="/proofs/share/:requestId/:signature" element={<ProofSharePage />} />
        <Route path="/proofs/preview/:previewDelivery/:signature" element={<PublicPreviewRoomPage />} />
        <Route path="/deep-dive/:invitation" element={<DeepDivePage />} />

        <Route element={<Layout />}>
          <Route index element={<HomePage />} />

          {/* Marketing hubs + single-item pages. */}
          <Route path="/services" element={<ServicesHubPage />} />
          <Route path="/services/:slug" element={<ServicePage />} />
          <Route path="/packages" element={<PackagesHubPage />} />
          <Route path="/packages/:slug" element={<PackagePage />} />
          <Route path="/work" element={<WorkHubPage />} />
          <Route path="/work/:slug" element={<CaseStudyPage />} />
          <Route path="/blog" element={<BlogHubPage />} />
          <Route path="/blog/:slug" element={<BlogPostPage />} />
          <Route path="/faq" element={<FAQHubPage />} />
          <Route path="/contact" element={<ContactPage />} />
          <Route path="/start" element={<StartPage />} />
          <Route path="/buy" element={<PurchasePage />} />
          <Route path="/purchase" element={<PurchasePage />} />
          <Route path="/pricing" element={<Navigate to="/packages" replace />} />
          <Route path="/intake" element={<IntakeHubPage />} />
          <Route path="/intake/:serviceSlug" element={<SpecializedIntakePage />} />
          <Route path="/55-cents-a-day-website" element={<FiftyFiveCentWebsitePage />} />
          <Route path="/privacy-policy" element={<PrivacyPolicyPage />} />
          <Route path="/privacy" element={<Navigate to="/privacy-policy" replace />} />
          <Route path="/terms-of-service" element={<TermsOfServicePage />} />
          <Route path="/terms" element={<Navigate to="/terms-of-service" replace />} />

          {/* Legacy /content/* URLs → clean routes. */}
          <Route path="/content/page" element={<Navigate to="/" replace />} />
          <Route path="/content/article" element={<Navigate to="/blog" replace />} />
          <Route path="/content/:type" element={<ContentPage />} />
          <Route path="/node/:uuid" element={<NodeView />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/verify-email" element={<VerifyEmailPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
          <Route
            path="/admin"
            element={
              <ProtectedRoute>
                <AdminDashboardPage />
              </ProtectedRoute>
            }
          />
          {/* Catch-all: resolves page aliases (/about, /contact) or redirects home. */}
          <Route path="*" element={<AliasPage />} />
        </Route>
      </Routes>
    </UserProvider>
  );
}
