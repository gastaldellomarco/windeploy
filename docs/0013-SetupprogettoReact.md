<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

## 1. Comandi npm per installare tutto

Da eseguire nella cartella `frontend/` (dove c’è già il progetto Vite React).

```bash
# (se non hai ancora creato il progetto Vite)
npm create vite@latest frontend -- --template react
cd frontend

# Dipendenze di routing / stato / API / grafici
npm install react-router-dom@6 axios zustand @tanstack/react-query react-hot-toast lucide-react recharts

# Tailwind CSS v3 + PostCSS + Autoprefixer
npm install -D tailwindcss@3 postcss autoprefixer

# Inizializza Tailwind v3 (genera tailwind.config.cjs e postcss.config.cjs)
npx tailwindcss init -p
```

Configura Tailwind per Vite seguendo la guida ufficiale (contenuti in `index.css` e path `./index.html, ./src/**/*.{js,jsx}`), tenendo presente che il resto della documentazione WinDeploy usa Tailwind più recente ma rimane compatibile come concetto.[^1_1]

***

## 2. Struttura cartelle completa (con commento)

```txt
frontend/
  src/
    api/
      client.js              // Axios instance con baseURL /api, interceptor token + gestione 401
      auth.js                // Funzioni API auth: login, me, logout
      wizards.js             // Funzioni API per wizards (lista, create, monitor stub)
      templates.js           // Funzioni API per template (stub)
      software.js            // Funzioni API per software library (stub)
      reports.js             // Funzioni API per reports (stub)
      users.js               // Funzioni API per gestione utenti (stub)

    components/
      layout/
        MainLayout.jsx       // Layout principale con sidebar/topbar e <Outlet />
      ui/
        Button.jsx           // Pulsante riutilizzabile con classi Tailwind
        Badge.jsx            // Badge di stato/ruolo
        Card.jsx             // Card container per box dashboard/pagine
        Table.jsx            // Tabella base riutilizzabile
        PageHeader.jsx       // Header pagina (titolo + azioni)
        LoadingSpinner.jsx   // Spinner di caricamento

    pages/
      Login/
        LoginPage.jsx        // Pagina di login (email/password, chiama API auth.login)
      Dashboard/
        DashboardPage.jsx    // Dashboard con grafici Recharts e metriche principali
      Wizards/
        WizardsListPage.jsx  // Lista wizards esistenti
        WizardBuilderPage.jsx// Pagina creazione nuovo wizard (/wizards/new)
        WizardMonitorPage.jsx// Monitor real-time / polling esecuzioni wizard
      Templates/
        TemplatesPage.jsx    // Gestione template
      Software/
        SoftwarePage.jsx     // Libreria software (solo admin)
      Reports/
        ReportsPage.jsx      // Lista report + dettaglio
      Users/
        UsersPage.jsx        // Gestione utenti (solo admin)
      NotFound/
        NotFoundPage.jsx     // 404 per route non trovate

    store/
      authStore.js           // Zustand: stato auth, token, user, login/logout con localStorage

    hooks/
      useAuth.js             // Hook per login/logout + user corrente (wrappa authStore + API)
      useWizards.js          // Hook React Query per wizards (lista, create, monitor stub)
      useTemplates.js        // Hook React Query per templates (stub)
      useSoftware.js         // Hook React Query per software library (stub)
      useReports.js          // Hook React Query per reports (stub)
      useUsers.js            // Hook React Query per utenti (stub)

    utils/
      formatDate.js          // Utility per formattare date
      generateCode.js        // Utility per generare codice wizard (es. WD-7A3F)
      roleLabels.js          // Mappa ruolo -> label/colore (admin, tecnico, viewer)[file:12]

    router/
      AppRouter.jsx          // Definizione di tutte le route + mapping pagine
      PrivateRoute.jsx       // Wrapper route protette con controllo ruolo

    App.jsx                  // Shell principale dell’app, include layout + AppRouter
    main.jsx                 // Entry-point React, QueryClientProvider, BrowserRouter, Toaster
    index.css                // Import Tailwind base/components/utilities
```


***

## 3. `main.jsx` e `App.jsx` con tutti i provider

### `src/main.jsx`

```jsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';
import App from './App.jsx';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 30 * 1000,
    },
  },
});

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <App />
      </BrowserRouter>
      <Toaster position="top-right" toastOptions={{ duration: 4000 }} />
    </QueryClientProvider>
  </React.StrictMode>
);
```


### `src/App.jsx`

```jsx
import React from 'react';
import AppRouter from './router/AppRouter';

function App() {
  return (
    <div className="min-h-screen bg-slate-950 text-slate-50">
      <AppRouter />
    </div>
  );
}

export default App;
```


***

## 4. Router completo con protezione per ruolo

### `src/router/PrivateRoute.jsx`

```jsx
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
```


### `src/components/layout/MainLayout.jsx`

Layout semplice con sidebar; puoi evolverlo dopo.

```jsx
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

function MainLayout() {
  const { user, logout } = useAuthStore();

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
              {user.name} · {user.role}
            </div>
          )}
        </div>

        <nav className="flex-1 px-2 py-4 space-y-1 text-sm">
          <NavLink
            to="/dashboard"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${
                isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'
              }`
            }
          >
            <LayoutDashboard className="w-4 h-4" />
            <span>Dashboard</span>
          </NavLink>

          <NavLink
            to="/wizards"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${
                isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'
              }`
            }
          >
            <Wand2 className="w-4 h-4" />
            <span>Wizards</span>
          </NavLink>

          <NavLink
            to="/templates"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${
                isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'
              }`
            }
          >
            <FileText className="w-4 h-4" />
            <span>Templates</span>
          </NavLink>

          <NavLink
            to="/software"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${
                isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'
              }`
            }
          >
            <Package className="w-4 h-4" />
            <span>Software library</span>
          </NavLink>

          <NavLink
            to="/reports"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${
                isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'
              }`
            }
          >
            <ClipboardList className="w-4 h-4" />
            <span>Reports</span>
          </NavLink>

          <NavLink
            to="/users"
            className={({ isActive }) =>
              `flex items-center gap-2 rounded px-3 py-2 ${
                isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/70'
              }`
            }
          >
            <Users className="w-4 h-4" />
            <span>Users</span>
          </NavLink>
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

      <main className="flex-1 bg-slate-950">
        <div className="max-w-6xl mx-auto px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default MainLayout;
```


### `src/router/AppRouter.jsx`

Tutte le route con protezione per ruolo come richiesto (admin / tecnico / viewer).[^1_2]

```jsx
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
```

> Nota: il controllo ruoli lato client è solo un “guardrail” UX; la vera autorizzazione deve restare nei middleware Laravel (Sanctum + ruolo) già previsti nel backend.[^1_3][^1_2]

***

## 5. `authStore.js` con Zustand (token in localStorage)

Questo store parte leggendo eventuale token/sessione dal `localStorage` e lo espone a tutto il frontend.[^1_3]

### `src/store/authStore.js`

```jsx
import { create } from 'zustand';

const TOKEN_KEY = 'windeploy_token';
const USER_KEY = 'windeploy_user';

const getInitialState = () => {
  const token = window.localStorage.getItem(TOKEN_KEY) || null;
  const userJson = window.localStorage.getItem(USER_KEY);
  let user = null;

  try {
    if (userJson) {
      user = JSON.parse(userJson);
    }
  } catch (error) {
    console.error('Failed to parse stored user', error);
  }

  return {
    token,
    user,
    isAuthenticated: Boolean(token && user),
  };
};

export const useAuthStore = create((set) => ({
  ...getInitialState(),

  login: ({ token, user }) => {
    // WARNING: token in localStorage è pratico ma vulnerabile a XSS.
    // Per produzione valuta in futuro un approccio cookie HttpOnly + CSRF.
    window.localStorage.setItem(TOKEN_KEY, token);
    window.localStorage.setItem(USER_KEY, JSON.stringify(user));

    set({
      token,
      user,
      isAuthenticated: true,
    });
  },

  logout: () => {
    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(USER_KEY);

    set({
      token: null,
      user: null,
      isAuthenticated: false,
    });
  },

  setUser: (user) => {
    window.localStorage.setItem(USER_KEY, JSON.stringify(user));
    set({ user });
  },
}));
```


***

## 6. Axios client con interceptor token + gestione 401

Con Vite il proxy è configurato per inoltrare `http://localhost:5173/api/...` verso il virtual host Laravel `http://windeploy.local.api/api/...`, quindi l’instance Axios può usare `baseURL: '/api'`.[^1_1]

### `src/api/client.js`

```jsx
import axios from 'axios';
import { useAuthStore } from '../store/authStore';

// Creiamo una singola instance Axios per tutto il frontend
const client = axios.create({
  baseURL: '/api',
  withCredentials: false, // usiamo Bearer token, non cookie
  timeout: 15000,
});

// Request interceptor: aggiunge Authorization Bearer <token> se presente
client.interceptors.request.use(
  (config) => {
    const { token } = useAuthStore.getState();

    if (token) {
      config.headers = config.headers || {};
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor: se 401 → logout e redirect a /login
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      const { logout } = useAuthStore.getState();
      logout();

      // Redirect “grezzo” fuori da React Router per evitare dipendenze circolari
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    return Promise.reject(error);
  }
);

export default client;
```


### Esempio moduli API che usano `client`

#### `src/api/auth.js`

Lato backend hai un `AuthController` Sanctum con `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me` che restituiscono token e dati utente (incluso ruolo).[^1_3]

```jsx
import client from './client';

export const loginApi = async ({ email, password }) => {
  const response = await client.post('/auth/login', { email, password });
  // Atteso: { token, token_expires_at, user: { id, name, email, role } }
  return response.data;
};

export const logoutApi = async () => {
  const response = await client.post('/auth/logout');
  return response.data;
};

export const meApi = async () => {
  const response = await client.get('/auth/me');
  return response.data;
};
```


#### `src/hooks/useAuth.js`

Hook comodo per usare login/logout con React Query + toast.

```jsx
import { useMutation, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { loginApi, logoutApi, meApi } from '../api/auth';
import { useAuthStore } from '../store/authStore';

export function useAuth() {
  const queryClient = useQueryClient();
  const { login, logout, user, isAuthenticated } = useAuthStore();

  const loginMutation = useMutation({
    mutationFn: loginApi,
    onSuccess: (data) => {
      login({ token: data.token, user: data.user });
      queryClient.invalidateQueries({ queryKey: ['me'] });
      toast.success('Login eseguito');
    },
    onError: () => {
      toast.error('Credenziali non valide');
    },
  });

  const logoutMutation = useMutation({
    mutationFn: logoutApi,
    onSettled: () => {
      logout();
      queryClient.clear();
      toast('Sessione terminata');
    },
  });

  const fetchMe = async () => {
    const profile = await meApi();
    useAuthStore.getState().setUser(profile);
    return profile;
  };

  return {
    user,
    isAuthenticated,
    login: (credentials) => loginMutation.mutate(credentials),
    loginStatus: loginMutation.status,
    logout: () => logoutMutation.mutate(),
    logoutStatus: logoutMutation.status,
    fetchMe,
  };
}
```


***

## 7. Stub pagine minime (pronte a compilare)

Per chiudere lo scaffold, ecco versioni ultra-minimali delle pagine chiave.

### `src/pages/Login/LoginPage.jsx`

```jsx
import React, { useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuthStore } from '../../store/authStore';
import { useAuth } from '../../hooks/useAuth';

function LoginPage() {
  const location = useLocation();
  const { isAuthenticated } = useAuthStore();
  const { login, loginStatus } = useAuth();
  const [form, setForm] = useState({ email: '', password: '' });

  const from = location.state?.from?.pathname || '/dashboard';

  if (isAuthenticated) {
    return <Navigate to={from} replace />;
  }

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    login(form);
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-950">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg">
        <h1 className="text-xl font-semibold mb-1 text-white">WinDeploy</h1>
        <p className="text-xs text-slate-400 mb-6">
          Accedi con il tuo account aziendale.
        </p>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs mb-1 text-slate-300" htmlFor="email">
              Email
            </label>
            <input
              id="email"
              name="email"
              type="email"
              autoComplete="email"
              required
              className="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-sky-500"
              value={form.email}
              onChange={handleChange}
            />
          </div>

          <div>
            <label className="block text-xs mb-1 text-slate-300" htmlFor="password">
              Password
            </label>
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              className="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-sky-500"
              value={form.password}
              onChange={handleChange}
            />
          </div>

          <button
            type="submit"
            disabled={loginStatus === 'pending'}
            className="w-full inline-flex items-center justify-center rounded bg-sky-600 hover:bg-sky-500 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
          >
            {loginStatus === 'pending' ? 'Accesso in corso...' : 'Accedi'}
          </button>
        </form>
      </div>
    </div>
  );
}

export default LoginPage;
```


### `src/pages/Dashboard/DashboardPage.jsx`

Esempio con Recharts per dare subito un grafico.

```jsx
import React from 'react';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { Card } from '../../components/ui/Card';

const dummyData = [
  { month: 'Gen', configured: 12 },
  { month: 'Feb', configured: 18 },
  { month: 'Mar', configured: 22 },
];

function DashboardPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-xl font-semibold">Dashboard</h1>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card title="PC configurati questo mese">
          <div className="text-3xl font-bold">18</div>
        </Card>
        <Card title="Wizard attivi">
          <div className="text-3xl font-bold">5</div>
        </Card>
        <Card title="Ultimo report">
          <div className="text-sm text-slate-300">Ieri, 16:42</div>
        </Card>
      </div>

      <Card title="Andamento configurazioni">
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={dummyData}>
              <XAxis dataKey="month" stroke="#64748b" />
              <YAxis stroke="#64748b" />
              <Tooltip />
              <Line type="monotone" dataKey="configured" stroke="#0ea5e9" strokeWidth={2} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </Card>
    </div>
  );
}

export default DashboardPage;
```


### `src/components/ui/Card.jsx`

```jsx
import React from 'react';

export function Card({ title, children }) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-900/70 p-4">
      {title && <div className="text-xs font-medium text-slate-400 mb-2">{title}</div>}
      {children}
    </div>
  );
}
```

Le altre pagine (`WizardsListPage`, `WizardBuilderPage`, `WizardMonitorPage`, `TemplatesPage`, `SoftwarePage`, `ReportsPage`, `UsersPage`, `NotFoundPage`) possono iniziare come semplici placeholder con un `h1` e una `div` vuota, da riempire mentre implementi i relativi endpoint Laravel già previsti (wizards, templates, softwarelibrary, reports, users con ruoli admin/tecnico/viewer).[^1_4][^1_5][^1_2]

***

## 8. Checklist operativa + note di sicurezza

- [ ] Verifica che `vite.config.js` usi il proxy verso `http://windeploy.local.api` per il path `/api` come da guida WinDeploy.[^1_1]
- [ ] Crea tutti i file indicati nella struttura, incollando il codice sopra (adattando nomi / testi dove serve).
- [ ] Allinea le risposte dell’endpoint `POST /api/auth/login` al formato atteso (`token`, `user` con `role`).[^1_3]
- [ ] Implementa sui controller Laravel i middleware `auth:sanctum` + `role:admin,tecnico,viewer` coerenti con le route frontend.[^1_2][^1_3]
- [ ] Testa:
    - login con admin/tecnico/viewer,
    - accesso alle route protette,
    - che un viewer non possa aprire /software o /users,
    - che un 401 dal backend faccia realmente logout e redirect a `/login`.

**Avvocato del diavolo / rischi:**

- Token in `localStorage` è comodo ma esposto a XSS: in produzione dovrai essere molto rigoroso su CSP, sanitizzazione e assenza di `dangerouslySetInnerHTML`. Un’alternativa più sicura nel lungo periodo sarebbe un flusso cookie HttpOnly + CSRF con Sanctum stateful.[^1_3]
- Il controllo dei ruoli sul router può essere bypassato da chi manipola il JS: la sicurezza reale rimane lato backend con `CheckRole` e policy Laravel.[^1_2][^1_3]
- Se cambi in futuro il formato della risposta `/auth/login` (es. aggiungendo refresh token o adattando la durata 8h prevista ora), ricorda di sincronizzare `authStore`, interceptor e hook `useAuth` per evitare sessioni zombie o bug di expirazione.[^1_3]
<span style="display:none">[^1_6]</span>

<div align="center">⁂</div>

[^1_1]: 0006-viteconfigjs.md

[^1_2]: 0001-projectdescription.txt

[^1_3]: 0010-autenticazione.md

[^1_4]: 0012-apiendpointwindows.md

[^1_5]: 0011-controllercrud.md

[^1_6]: 0007-cheatsheet.md

