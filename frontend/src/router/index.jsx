// Path: frontend/src/router/index.jsx
import React, { Suspense } from "react";
import { Routes, Route, Navigate } from "react-router-dom";

import PrivateRoute from "./PrivateRoute.jsx";
import MainLayout from "../components/layout/MainLayout.jsx";

import RouteErrorBoundary from "../components/ErrorBoundary/RouteErrorBoundary.jsx";
import WizardErrorBoundary from "../components/ErrorBoundary/WizardErrorBoundary.jsx";

function PageLoader({ label }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
      {label || "Caricamento..."}
    </div>
  );
}

// English comment: Lazy-load pages so a crash in one page does not bring down the whole app.
const LoginPage = React.lazy(() => import("../pages/Login/LoginPage.jsx"));
const DashboardPage = React.lazy(() => import("../pages/Dashboard/DashboardPage.jsx"));
const WizardsListPage = React.lazy(() => import("../pages/Wizards/WizardsListPage.jsx"));
const WizardBuilderPage = React.lazy(() => import("../pages/Wizards/WizardBuilderPage.jsx"));
const MonitorPage = React.lazy(() => import("../pages/Monitor/index.jsx"));
const TemplatesPage = React.lazy(() => import("../pages/Templates/TemplatesPage.jsx"));
const SoftwarePage = React.lazy(() => import("../pages/Software/SoftwarePage.jsx"));
const ReportsPage = React.lazy(() => import("../pages/Reports/ReportsPage.jsx"));
const UsersPage = React.lazy(() => import("../pages/Users/UsersPage.jsx"));
const NotFoundPage = React.lazy(() => import("../pages/NotFound/NotFoundPage.jsx"));

function wrapRoute(element, { resetKeys, fallbackLabel } = {}) {
  return (
    <RouteErrorBoundary resetKeys={resetKeys}>
      <Suspense fallback={<PageLoader label={fallbackLabel} />}>{element}</Suspense>
    </RouteErrorBoundary>
  );
}

export default function AppRouter() {
  return (
    <Routes>
      {/* Public */}
      <Route
        path="/login"
        element={wrapRoute(<LoginPage />, {
          resetKeys: ["login"],
          fallbackLabel: "Caricamento login...",
        })}
      />

      {/* Protected */}
      <Route element={<PrivateRoute allowedRoles={["admin", "tecnico", "viewer"]} />}>
        <Route element={<MainLayout />}>
          {/* Redirect root -> /dashboard */}
          <Route index element={<Navigate to="/dashboard" replace />} />

          {/* Dashboard & Reports */}
          <Route
            path="/dashboard"
            element={wrapRoute(<DashboardPage />, {
              resetKeys: ["dashboard"],
              fallbackLabel: "Caricamento dashboard...",
            })}
          />

          <Route
            path="/reports"
            element={wrapRoute(<ReportsPage />, {
              resetKeys: ["reports"],
              fallbackLabel: "Caricamento report...",
            })}
          />

          {/* Monitor avanzato per UN wizard specifico */}
          <Route
            path="/monitor/:id"
            element={wrapRoute(<MonitorPage />, {
              resetKeys: ["monitor"],
              fallbackLabel: "Caricamento monitor...",
            })}
          />

          {/* Wizards → tecnico + admin */}
          <Route element={<PrivateRoute allowedRoles={["admin", "tecnico"]} />}>
            <Route
              path="/wizards"
              element={wrapRoute(<WizardsListPage />, {
                resetKeys: ["wizards"],
                fallbackLabel: "Caricamento lista wizard...",
              })}
            />

            <Route
              path="/wizards/new"
              element={
                <RouteErrorBoundary resetKeys={["wizard-new"]}>
                  <WizardErrorBoundary resetKeys={["wizard-new"]}>
                    <Suspense fallback={<PageLoader label="Caricamento Wizard Builder..." />}>
                      <WizardBuilderPage />
                    </Suspense>
                  </WizardErrorBoundary>
                </RouteErrorBoundary>
              }
            />

            {/* Templates → tecnico + admin */}
            <Route
              path="/templates"
              element={wrapRoute(<TemplatesPage />, {
                resetKeys: ["templates"],
                fallbackLabel: "Caricamento templates...",
              })}
            />
          </Route>

          {/* Admin only */}
          <Route element={<PrivateRoute allowedRoles={["admin"]} />}>
            <Route
              path="/software"
              element={wrapRoute(<SoftwarePage />, {
                resetKeys: ["software"],
                fallbackLabel: "Caricamento software library...",
              })}
            />

            <Route
              path="/users"
              element={wrapRoute(<UsersPage />, {
                resetKeys: ["users"],
                fallbackLabel: "Caricamento utenti...",
              })}
            />
          </Route>

          {/* 404 */}
          <Route
            path="*"
            element={wrapRoute(<NotFoundPage />, {
              resetKeys: ["notfound"],
              fallbackLabel: "Caricamento...",
            })}
          />
        </Route>
      </Route>
    </Routes>
  );
}
