// frontend/src/router/PrivateRoute.jsx
import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import useAuthStore from '../store/authStore.js';

function normalizeRole(value) {
  return String(value ?? '').trim().toLowerCase();
}

export default function PrivateRoute({ allowedRoles = [] }) {
  const location = useLocation();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const user = useAuthStore((state) => state.user);
  const storedRole = useAuthStore((state) => state.role);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (allowedRoles.length > 0) {
    const userRole = normalizeRole(
      storedRole ?? user?.role ?? user?.ruolo ?? user?.normalizedRole
    );

    const normalizedAllowedRoles = allowedRoles.map((role) => normalizeRole(role));

    if (!userRole || !normalizedAllowedRoles.includes(userRole)) {
      return <Navigate to="/dashboard" replace state={{ denied: true }} />;
    }
  }

  return <Outlet />;
}
