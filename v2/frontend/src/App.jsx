import { Routes, Route } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import HomePage from './pages/HomePage.jsx';
import ContentPage from './pages/ContentPage.jsx';
import NodeView from './components/NodeView.jsx';
import LoginPage from './pages/LoginPage.jsx';
import AdminDashboardPage from './pages/AdminDashboardPage.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import { UserProvider } from './auth/UserContext.jsx';
import ProspectLandingPage from './pages/ProspectLandingPage.jsx';
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
import AliasPage from './pages/AliasPage.jsx';

export default function App() {
  return (
    <UserProvider>
      <Routes>
        {/* Public, token-scoped prospect pipeline (no marketing chrome, no auth). */}
        <Route path="/p/:token" element={<ProspectLandingPage />} />
        <Route path="/p/:token/return" element={<PaymentReturnPage />} />
        <Route path="/p/:token/cancel" element={<PaymentCancelPage />} />
        <Route path="/p/:token/intake" element={<IntakePage />} />
        <Route path="/p/:token/status" element={<ProofStatusPage />} />

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

          <Route path="/content/:type" element={<ContentPage />} />
          <Route path="/node/:uuid" element={<NodeView />} />
          <Route path="/login" element={<LoginPage />} />
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
