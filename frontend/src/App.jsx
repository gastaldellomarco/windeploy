// frontend/src/App.jsx
import React, { useEffect } from 'react';
import AppRouter from './router';
import { useAuthStore } from './store/authStore';

function App() {
  const token = useAuthStore((state) => state.token);
  const user = useAuthStore((state) => state.user);
  const checkAuth = useAuthStore((state) => state.checkAuth);

  useEffect(() => {
    if (token && !user) {
      checkAuth().catch(() => {
        // Session cleanup already handled by store/interceptors.
      });
    }
  }, [token, user, checkAuth]);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-50">
      <AppRouter />
    </div>
  );
}

export default App;
