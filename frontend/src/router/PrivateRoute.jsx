// Path: frontend/src/router/PrivateRoute.jsx
import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuthStore } from "../store/authStore.js";

export default function PrivateRoute({ allowedRoles }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  // Backend sends 'ruolo' (Italian), normalize to lowercase for comparison
  const normalizedRole = String(user?.ruolo || "").toLowerCase();

  if (Array.isArray(allowedRoles) && allowedRoles.length > 0) {
    const normalizedAllowed = allowedRoles.map((r) => String(r).toLowerCase());

    if (!normalizedRole || !normalizedAllowed.includes(normalizedRole)) {
      return (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
          <div className="text-sm font-semibold">Accesso negato</div>
          <div className="mt-1 text-sm">
            Il tuo ruolo ({normalizedRole || "n/d"}) non è autorizzato per questa pagina.
          </div>
        </div>
      );
    }
  }

  return <Outlet />;
}
