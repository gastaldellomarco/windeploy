// frontend/src/components/layout/MainLayout.jsx
import React from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import {
  LayoutDashboard,
  Wand2,
  FileText,
  Package,
  Users,
  ClipboardList,
  LogOut,
} from 'lucide-react';
import { useAuthStore } from '../../store/authStore';

function normalizeRole(value) {
  return String(value ?? '').trim().toLowerCase();
}

function MainLayout() {
  const user = useAuthStore((state) => state.user);
  const role = useAuthStore((state) => state.role);
  const logout = useAuthStore((state) => state.logout);

  const currentRole = normalizeRole(role ?? user?.role ?? user?.ruolo);

  const canSeeTemplates = ['admin', 'tecnico'].includes(currentRole);
  const canSeeSoftware = ['admin'].includes(currentRole);
  const canSeeUsers = ['admin'].includes(currentRole);

  const handleLogout = () => {
    logout();
  };

  return (
    <div className="flex min-h-screen">
      <aside className="w-64 bg-slate-900 border-r border-slate-800 flex flex-col">
        <div className="px-4 py-4 border-b border-slate-800">
          <div className="text-lg font-semibold">WinDeploy</div>
          {user && (
            <div className="mt-1 text-xs text-slate-400">
              {user.name} · {currentRole || 'unknown'}
            </div>
          )}
        </div>

        <nav className="flex-1 px-2 py-4 space-y-1 text-sm">
          <NavLink to="/dashboard" className={({ isActive }) =>
            `flex items-center gap-2 rounded px-3 py-2 ${isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'}`
          }>
            <LayoutDashboard className="w-4 h-4" />
            <span>Dashboard</span>
          </NavLink>

          <NavLink to="/wizards" className={({ isActive }) =>
            `flex items-center gap-2 rounded px-3 py-2 ${isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'}`
          }>
            <Wand2 className="w-4 h-4" />
            <span>Wizards</span>
          </NavLink>

          {canSeeTemplates && (
            <NavLink to="/templates" className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'}`
            }>
              <FileText className="w-4 h-4" />
              <span>Templates</span>
            </NavLink>
          )}

          {canSeeSoftware && (
            <NavLink to="/software" className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'}`
            }>
              <Package className="w-4 h-4" />
              <span>Software library</span>
            </NavLink>
          )}

          <NavLink to="/reports" className={({ isActive }) =>
            `flex items-center gap-2 rounded px-3 py-2 ${isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'}`
          }>
            <ClipboardList className="w-4 h-4" />
            <span>Reports</span>
          </NavLink>

          {canSeeUsers && (
            <NavLink to="/users" className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'}`
            }>
              <Users className="w-4 h-4" />
              <span>Users</span>
            </NavLink>
          )}
        </nav>

        <button
          type="button"
          onClick={handleLogout}
          className="flex items-center gap-2 px-4 py-3 text-sm text-red-300 hover:bg-red-900/30 border-t border-slate-800"
        >
          <LogOut className="w-4 h-4" />
          <span>Logout</span>
        </button>
      </aside>

      <main className="flex-1 min-w-0">
        <div className="max-w-7xl mx-auto px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default MainLayout;
