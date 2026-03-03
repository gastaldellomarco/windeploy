import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';

// allowedRoles: array di ruoli ammessi, es. ['admin', 'tecnico']
function PrivateRoute({ allowedRoles }) {
  const location = useLocation();
  const { isAuthenticated, user } = useAuthStore();

  if (!isAuthenticated) {
    return (
      <Navigate
        to="/login"
        replace
        state={{ from: location }}
      />
    );
  }

  if (allowedRoles && allowedRoles.length > 0) {
    const userRole = user?.role;
    if (!userRole || !allowedRoles.includes(userRole)) {
      // Utente loggato ma ruolo non sufficiente
      return <Navigate to="/dashboard" replace />;
    }
  }

  return <Outlet />;
}

export default PrivateRoute;
