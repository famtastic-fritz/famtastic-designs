import { Routes, Route, Navigate } from 'react-router-dom';
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
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </UserProvider>
  );
}
