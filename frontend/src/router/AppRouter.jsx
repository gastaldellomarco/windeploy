import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import PrivateRoute from './PrivateRoute';
import MainLayout from '../components/layout/MainLayout';

// Pagine
import LoginPage from '../pages/Login/LoginPage';
import DashboardPage from '../pages/Dashboard/DashboardPage';
import WizardsListPage from '../pages/Wizards/WizardsListPage';
import WizardBuilderPage from '../pages/Wizards/WizardBuilderPage';
import WizardMonitorPage from '../pages/Wizards/WizardMonitorPage';
import MonitorPage from '../pages/Monitor';
import TemplatesPage from '../pages/Templates/TemplatesPage';
import SoftwarePage from '../pages/Software/SoftwarePage';
import ReportsPage from '../pages/Reports/ReportsPage';
import UsersPage from '../pages/Users/UsersPage';
import NotFoundPage from '../pages/NotFound/NotFoundPage';

function AppRouter() {
  return (
    <Routes>
      {/* Pubblico */}
      <Route path="/login" element={<LoginPage />} />

      {/* Area protetta con layout */}
      <Route element={<PrivateRoute allowedRoles={['admin', 'tecnico', 'viewer']} />}>
        <Route element={<MainLayout />}>
          {/* Redirect root -> /dashboard */}
          <Route index element={<Navigate to="/dashboard" replace />} />

          {/* /dashboard → admin + tecnico + viewer */}
          <Route path="/dashboard" element={<DashboardPage />} />

          {/* /reports → tutti (admin + tecnico + viewer) */}
          <Route path="/reports" element={<ReportsPage />} />
          <Route path="/monitor" element={<MonitorPage />} />
        </Route>
      </Route>

      {/* /wizards, /wizards/new, /wizards/:id/monitor → tecnico + admin */}
      <Route element={<PrivateRoute allowedRoles={['admin', 'tecnico']} />}>
        <Route element={<MainLayout />}>
          <Route path="/wizards" element={<WizardsListPage />} />
          <Route path="/wizards/new" element={<WizardBuilderPage />} />
          <Route path="/wizards/:id/monitor" element={<WizardMonitorPage />} />

          {/* /templates → tecnico + admin */}
          <Route path="/templates" element={<TemplatesPage />} />
        </Route>
      </Route>

      {/* /software → solo admin */}
      <Route element={<PrivateRoute allowedRoles={['admin']} />}>
        <Route element={<MainLayout />}>
          <Route path="/software" element={<SoftwarePage />} />
        </Route>
      </Route>

      {/* /users → solo admin */}
      <Route element={<PrivateRoute allowedRoles={['admin']} />}>
        <Route element={<MainLayout />}>
          <Route path="/users" element={<UsersPage />} />
        </Route>
      </Route>

      {/* 404 */}
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}

export default AppRouter;
