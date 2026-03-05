// Path: frontend/src/components/layout/MainLayout.jsx
import React from "react";
import { NavLink, Outlet } from "react-router-dom";
import {
  LayoutDashboard,
  Wand2,
  FileText,
  Package,
  Users,
  ClipboardList,
  LogOut,
} from "lucide-react";
import { useAuthStore } from "../../store/authStore.js";

function buildNavClass({ isActive }) {
  return [
    "flex items-center gap-2 rounded px-3 py-2 transition-colors",
    isActive
      ? "bg-slate-800 text-white"
      : "text-slate-300 hover:bg-slate-800/70 hover:text-white",
  ].join(" ");
}

export default function MainLayout() {
  const { user, logout } = useAuthStore();

  function handleLogout() {
    logout();
  }

  return (
    <div className="flex min-h-screen bg-slate-950 text-slate-50">
      <aside className="w-64 border-r border-slate-800 bg-slate-900 flex flex-col">
        <div className="px-4 py-4 border-b border-slate-800">
          <div className="text-lg font-semibold">WinDeploy</div>
          {user ? (
            <div className="mt-1 text-xs text-slate-400">
              {user.name} · {user.role}
            </div>
          ) : null}
        </div>

        <nav className="flex-1 px-2 py-4 space-y-1 text-sm">
          <NavLink to="/dashboard" end className={buildNavClass}>
            <LayoutDashboard className="h-4 w-4" />
            <span>Dashboard</span>
          </NavLink>

          <NavLink to="/wizards" className={buildNavClass}>
            <Wand2 className="h-4 w-4" />
            <span>Wizards</span>
          </NavLink>

          <NavLink to="/templates" className={buildNavClass}>
            <FileText className="h-4 w-4" />
            <span>Templates</span>
          </NavLink>

          <NavLink to="/software" className={buildNavClass}>
            <Package className="h-4 w-4" />
            <span>Software library</span>
          </NavLink>

          <NavLink to="/reports" className={buildNavClass}>
            <ClipboardList className="h-4 w-4" />
            <span>Reports</span>
          </NavLink>

          <NavLink to="/users" className={buildNavClass}>
            <Users className="h-4 w-4" />
            <span>Users</span>
          </NavLink>
        </nav>

        <button
          type="button"
          onClick={handleLogout}
          className="flex items-center gap-2 border-t border-slate-800 px-4 py-3 text-sm text-red-300 hover:bg-red-900/30"
        >
          <LogOut className="h-4 w-4" />
          <span>Logout</span>
        </button>
      </aside>

      <main className="flex-1 bg-slate-950">
        <div className="mx-auto max-w-6xl px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
