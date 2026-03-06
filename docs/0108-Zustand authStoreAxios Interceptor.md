<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# [GPT] Stack: React 18, Vite, Zustand, Axios, Tailwind CSS — WinDeploy Frontend Auth Store

════════════════════════════════════════════════════════════
CONTESTO
════════════════════════════════════════════════════════════
WinDeploy usa Sanctum per autenticare il frontend React.
Lo stato globale (token, user, ruolo) deve essere gestito
da Zustand con persist middleware su localStorage, in modo
che il token sopravviva al refresh e venga iniettato
automaticamente in ogni richiesta Axios.

════════════════════════════════════════════════════════════
RIFERIMENTI SPACE
════════════════════════════════════════════════════════════

- 0013-SetupprogettoReact.md → struttura cartelle frontend/src/store/,
setup Zustand, pattern useAuthStore già previsto,
dipendenze npm installate
- 0014-Login.md             → LoginPage.jsx corrente, come usa (o dovrebbe usare)
useAuthStore, gestione errori e redirect post-login
- 0015-dashboard.md         → MainLayout.jsx e Sidebar: dove condizionare
il rendering in base a isAuthenticated e ruolo
- 0101-auth e sicurezza.md  → struttura risposta /api/auth/login (campi: token,
user, role), endpoint /api/auth/logout e /api/auth/me

════════════════════════════════════════════════════════════
AUDIT PRELIMINARE — esegui PRIMA di scrivere codice
════════════════════════════════════════════════════════════

1. Verifica se esiste frontend/src/store/authStore.js (o .ts)
→ se esiste, mostrare il contenuto attuale e identificare
cosa manca rispetto alla specifica
→ se mancante, crearlo da zero
2. Verifica se esiste frontend/src/api/axios.js
→ se esiste, controllare se ha già l'interceptor Bearer token
→ se mancante, crearlo da zero
3. Verifica se LoginPage.jsx chiama già useAuthStore().login()
o gestisce il fetch inline (da refactorare)
4. Verifica se MainLayout.jsx o App.jsx leggono già isAuthenticated
per proteggere le route
5. Riporta l'esito in tabella:
| File | Stato | Azione necessaria |
prima di procedere con il codice.

════════════════════════════════════════════════════════════
COSA VOGLIO
════════════════════════════════════════════════════════════

1. Crea frontend/src/store/authStore.js con:
    - State: user (oggetto), token (stringa), isAuthenticated (bool),
role (stringa: 'admin' | 'technician' | 'viewer')
    - Azione login(email, password):
→ POST /api/auth/login via istanza axios importata da ../api/axios
→ In caso di successo: set({ user, token, role, isAuthenticated: true })
→ In caso di errore 401/422: rilancia l'errore (non fare catch silenzioso)
così LoginPage può mostrare il messaggio appropriato
    - Azione logout():
→ POST /api/auth/logout (fire-and-forget con try/catch silenzioso
perché il token potrebbe già essere scaduto lato server)
→ set({ user: null, token: null, role: null, isAuthenticated: false })
→ chiama anche window.location.href = '/login' per forzare redirect
pulito (evita stato React stale)
    - Azione setUser(user): aggiorna solo user nello state (usato dopo /api/auth/me)
    - Azione checkAuth(): chiama GET /api/auth/me, se risponde → setUser(),
se risponde 401 → chiama logout() — da invocare in App.jsx al mount
    - persist middleware:
→ name: 'windeploy-auth'
→ storage: localStorage
→ partialize: salva SOLO token e role (NON user completo per ridurre
rischio data leakage in localStorage)
→ onRehydrateStorage: dopo il rehydrate, se token è presente ma user
è null, invoca automaticamente checkAuth()
2. Crea (o aggiorna) frontend/src/api/axios.js con:
    - Istanza axios con baseURL da import.meta.env.VITE_API_URL
    - Request interceptor: legge token da useAuthStore.getState().token
e aggiunge header Authorization: Bearer {token} se presente
(usa getState() di Zustand, NON useAuthStore() hook —
gli interceptor non sono componenti React)
    - Response interceptor: se status 401, chiama
useAuthStore.getState().logout() per pulire stato e redirigere
    - Header default: Content-Type: application/json,
Accept: application/json
3. Aggiorna frontend/src/pages/LoginPage.jsx:
    - Importa useAuthStore e usa login() dallo store
    - Rimuovi qualsiasi fetch/axios inline relativo al login
    - Gestisci l'errore con messaggio localizzato:
401 → "Credenziali non valide"
422 → mostra i validation errors dal campo errors della response
altro → "Errore di rete, riprova"
    - Dopo login() con successo: navigate('/dashboard')
    - Mostra spinner sul bottone durante l'await (stato locale isLoading)
4. Aggiorna frontend/src/App.jsx (o router principale):
    - Aggiungi ProtectedRoute component inline che legge isAuthenticated
da useAuthStore: se false → <Navigate to="/login" replace />
    - Al mount di App (useEffect), invoca checkAuth() se token è presente
in store ma user è null (gestisce il caso refresh pagina)
    - Proteggi tutte le route eccetto /login con ProtectedRoute
5. Aggiorna frontend/src/layouts/MainLayout.jsx (o Sidebar):
    - Leggi user e role da useAuthStore
    - Mostra il nome utente nella navbar/sidebar
    - Condiziona visibilità voci di menu in base al role:
'admin' → tutte le voci
'technician' → nasconde sezione Users
'viewer' → nasconde Users e Software Library
    - Bottone logout chiama useAuthStore().logout()

════════════════════════════════════════════════════════════
VINCOLI TRASVERSALI
════════════════════════════════════════════════════════════

- Ogni file inizia con il percorso completo in commento:
// frontend/src/store/authStore.js
- Codice completo, nessuna abbreviazione o "// resto invariato"
- Commenti in italiano sulla logica non ovvia
- Nomi variabili, funzioni, componenti in inglese
- NON usare useAuthStore() hook fuori da componenti React
(negli interceptor axios usa sempre .getState())
- NON salvare la password in nessuno state o localStorage
- Compatibile con React 18 + Vite 5 + Zustand 4.x
- Se usi TypeScript (.ts/.tsx) genera anche i tipi User e AuthState,
altrimenti resta in .js coerente con il progetto esistente
- Segnala esplicitamente rischi sicurezza (es: token in localStorage
vs httpOnly cookie, XSS surface)

════════════════════════════════════════════════════════════
COMMIT FINALE
════════════════════════════════════════════════════════════
feat(auth): add Zustand authStore with persist and axios interceptor

- authStore: login/logout/setUser/checkAuth with localStorage persistence
- axios: Bearer token request interceptor + 401 auto-logout response interceptor
- LoginPage: refactor to use authStore.login(), add error handling and spinner
- App: add ProtectedRoute, invoke checkAuth on mount
- MainLayout: role-based menu visibility, logout button wired to store

Ho fatto l’audit sui materiali disponibili del progetto e c’è già evidenza documentale di `LoginPage.jsx` con login inline e di `MainLayout.jsx` con sidebar e logout, mentre non posso verificare il contenuto live del working tree per `authStore.js`, `axios.js` e `App.jsx` perché quei sorgenti non sono allegati nello Space.[^1_1][^1_2][^1_3]
Per restare coerente con la struttura già documentata di WinDeploy, sotto ti lascio il refactor completo in JavaScript per React 18 + Vite + Zustand 4, con store persistente, interceptor Axios, route protection e menu role-based, tenendo conto anche del fatto che nel progetto i payload auth risultano misti tra `name/nome` e `role/ruolo`.[^1_4][^1_2][^1_3][^1_1]

## Audit

| File | Stato | Azione necessaria |
| :-- | :-- | :-- |
| `frontend/src/store/authStore.js` | Non verificabile direttamente dai file allegati; lo scaffold però prevede esplicitamente `src/store/authStore.js` per token, user e stato auth.[^1_3] | Creare o sostituire con store Zustand + `persist`, salvando solo `token` e `role`.[^1_3] |
| `frontend/src/api/axios.js` | Non verificabile direttamente; nella documentazione corrente compare invece `src/api/client.js` come istanza Axios con interceptor Bearer/401.[^1_3] | Introdurre `src/api/axios.js` come nuovo client standard e aggiornare gli import che oggi puntano a `client.js`.[^1_3][^1_2][^1_1] |
| `frontend/src/pages/Login/LoginPage.jsx` | Esiste una versione documentata che fa `client.post('/auth/login')` inline e poi chiama `login({ token, user })` sullo store, quindi va refactorata per delegare il flusso allo store.[^1_2] | Sostituire il login inline con `useAuthStore().login(email, password)`, aggiungere mapping 401/422 e spinner locale.[^1_2][^1_4] |
| `frontend/src/App.jsx` | Nello scaffold attuale `App.jsx` è solo una shell che renderizza `AppRouter`, senza `checkAuth()` al mount.[^1_3] | Portare la protezione route in `App.jsx` con `ProtectedRoute` inline e bootstrap auth su refresh.[^1_3] |
| `frontend/src/components/layout/MainLayout.jsx` | Esiste una versione documentata che legge `user` e `logout` dallo store e mostra tutte le voci menu senza filtro per ruolo.[^1_1][^1_3] | Aggiornare con `role` esplicito, nome utente in sidebar e visibilità condizionale di `Users` e `Software Library`.[^1_1][^1_5] |

## File coinvolti

Per coerenza con la struttura già emersa nei materiali di progetto, toccherei questi file e non un path flat alternativo.[^1_2][^1_3][^1_1]

- `frontend/src/store/authStore.js`
- `frontend/src/api/axios.js`
- `frontend/src/pages/Login/LoginPage.jsx`
- `frontend/src/App.jsx`
- `frontend/src/components/layout/MainLayout.jsx`


## Codice

### 1) `authStore.js`

```js
// frontend/src/store/authStore.js
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import api from '../api/axios';

const initialState = {
  user: null,
  token: null,
  isAuthenticated: false,
  role: null,
};

function normalizeUser(rawUser) {
  if (!rawUser || typeof rawUser !== 'object') {
    return null;
  }

  return {
    ...rawUser,
    name: rawUser.name ?? rawUser.nome ?? '',
    email: rawUser.email ?? '',
    role: rawUser.role ?? rawUser.ruolo ?? null,
  };
}

export const useAuthStore = create(
  persist(
    (set, get) => ({
      ...initialState,

      async login(email, password) {
        try {
          const response = await api.post('/auth/login', {
            email,
            password,
          });

          const payload = response?.data ?? {};
          const user = normalizeUser(payload.user);
          const token = payload.token ?? null;
          const role = payload.role ?? user?.role ?? null;

          if (!token || !user) {
            throw new Error('Invalid login response shape.');
          }

          set({
            user,
            token,
            role,
            isAuthenticated: true,
          });

          return payload;
        } catch (error) {
          // Non intercettiamo in modo silenzioso: la LoginPage deve poter
          // distinguere 401, 422 e altri errori per mostrare il messaggio corretto.
          throw error;
        }
      },

      async logout() {
        try {
          // Fire-and-forget: il token potrebbe essere già scaduto o revocato lato server.
          // Usiamo un flag custom per evitare loop con il response interceptor su 401.
          await api.post(
            '/auth/logout',
            {},
            {
              _skipAuthLogout: true,
            }
          );
        } catch (_) {
          // Silenzioso per specifica.
        } finally {
          set({
            user: null,
            token: null,
            role: null,
            isAuthenticated: false,
          });

          window.location.href = '/login';
        }
      },

      setUser(user) {
        set({
          user: normalizeUser(user),
        });
      },

      async checkAuth() {
        try {
          const response = await api.get('/auth/me', {
            _skipAuthLogout: true,
          });

          const remoteUser = normalizeUser(response?.data);

          get().setUser(remoteUser);

          set((state) => ({
            role: remoteUser?.role ?? state.role ?? null,
            isAuthenticated: Boolean(state.token),
          }));

          return remoteUser;
        } catch (error) {
          if (error?.response?.status === 401) {
            await get().logout();
            return null;
          }

          throw error;
        }
      },
    }),
    {
      name: 'windeploy-auth',
      storage: createJSONStorage(() => window.localStorage),
      partialize: (state) => ({
        token: state.token,
        role: state.role,
      }),
      onRehydrateStorage: () => (state, error) => {
        if (error) {
          return;
        }

        // Dopo il rehydrate recuperiamo il profilo lato server se abbiamo
        // un token persistito ma non un oggetto user in memoria.
        if (state?.token && !state?.user) {
          setTimeout(() => {
            const { checkAuth } = useAuthStore.getState();
            checkAuth().catch(() => {
              // Nessun rumore in bootstrap.
            });
          }, 0);
        }
      },
    }
  )
);
```


### 2) `axios.js`

```js
// frontend/src/api/axios.js
import axios from 'axios';
import { useAuthStore } from '../store/authStore';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

api.interceptors.request.use(
  (config) => {
    const token = useAuthStore.getState().token;
    const nextConfig = { ...config };

    nextConfig.headers = nextConfig.headers ?? {};

    if (token) {
      nextConfig.headers.Authorization = `Bearer ${token}`;
    }

    return nextConfig;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status;
    const requestUrl = String(error?.config?.url ?? '');
    const skipAuthLogout = Boolean(error?.config?._skipAuthLogout);

    // Evitiamo loop quando il 401 arriva proprio dalla logout o da chiamate
    // dove abbiamo già deciso di gestire manualmente la sessione.
    if (status === 401 && !skipAuthLogout && !requestUrl.includes('/auth/logout')) {
      const { token, logout } = useAuthStore.getState();

      if (token) {
        await logout();
      } else if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    return Promise.reject(error);
  }
);

export default api;
```


### 3) `LoginPage.jsx`

```jsx
// frontend/src/pages/Login/LoginPage.jsx
import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Eye, EyeOff, Loader2, Lock, Mail } from 'lucide-react';
import { useAuthStore } from '../../store/authStore';

function InputField({
  id,
  name,
  label,
  type = 'text',
  placeholder,
  value,
  onChange,
  disabled = false,
  autoComplete,
  leftIcon: LeftIcon,
  rightSlot,
}) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">
        {label}
      </label>

      <div className="relative flex items-center rounded-xl border border-slate-200 bg-white shadow-sm transition focus-within:ring-2 focus-within:ring-sky-500/40">
        {LeftIcon ? (
          <div className="pointer-events-none absolute left-3 flex items-center">
            <LeftIcon className="h-5 w-5 text-slate-400" />
          </div>
        ) : null}

        <input
          id={id}
          name={name}
          type={type}
          className={[
            'w-full rounded-xl bg-transparent px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none',
            LeftIcon ? 'pl-11' : '',
            rightSlot ? 'pr-11' : '',
          ].join(' ')}
          placeholder={placeholder}
          value={value}
          onChange={onChange}
          disabled={disabled}
          autoComplete={autoComplete}
        />

        {rightSlot ? (
          <div className="absolute right-2 flex items-center">{rightSlot}</div>
        ) : null}
      </div>
    </div>
  );
}

function flattenValidationErrors(errorsObject) {
  if (!errorsObject || typeof errorsObject !== 'object') {
    return 'Dati non validi';
  }

  const messages = Object.values(errorsObject)
    .flat()
    .filter(Boolean);

  return messages.length > 0 ? messages.join(' ') : 'Dati non validi';
}

export default function LoginPage() {
  const navigate = useNavigate();

  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const login = useAuthStore((state) => state.login);

  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [form, setForm] = useState({
    email: '',
    password: '',
  });

  const canSubmit = useMemo(() => {
    return form.email.trim().length > 0 && form.password.trim().length > 0 && !isLoading;
  }, [form.email, form.password, isLoading]);

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/dashboard', { replace: true });
    }
  }, [isAuthenticated, navigate]);

  function handleChange(event) {
    const { name, value } = event.target;

    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));

    if (errorMessage) {
      setErrorMessage('');
    }
  }

  function mapLoginError(error) {
    const status = error?.response?.status;
    const responseData = error?.response?.data;

    if (status === 401) {
      return 'Credenziali non valide';
    }

    if (status === 422) {
      return flattenValidationErrors(responseData?.errors);
    }

    return 'Errore di rete, riprova';
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!canSubmit) {
      return;
    }

    setIsLoading(true);
    setErrorMessage('');

    try {
      await login(form.email.trim(), form.password);
      navigate('/dashboard', { replace: true });
    } catch (error) {
      setErrorMessage(mapLoginError(error));
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen w-full items-center justify-center bg-gradient-to-br from-[#1E3A5F] to-[#2E75B6] p-6">
      <div className="w-full max-w-md">
        <div className="rounded-2xl border border-white/40 bg-white px-6 py-7 shadow-2xl backdrop-blur sm:px-8 sm:py-8">
          <div>
            <div className="text-2xl font-semibold text-slate-900">⚙️ WinDeploy</div>
            <div className="mt-1 text-sm text-slate-600">IT Provisioning Platform</div>
          </div>

          <div className="mt-6">
            {errorMessage ? (
              <div
                className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                role="alert"
              >
                {errorMessage}
              </div>
            ) : null}

            <form onSubmit={handleSubmit} className="space-y-4">
              <InputField
                id="email"
                name="email"
                label="Email"
                type="email"
                placeholder="nome.cognome@azienda.it"
                value={form.email}
                onChange={handleChange}
                disabled={isLoading}
                autoComplete="email"
                leftIcon={Mail}
              />

              <InputField
                id="password"
                name="password"
                label="Password"
                type={showPassword ? 'text' : 'password'}
                placeholder="La tua password"
                value={form.password}
                onChange={handleChange}
                disabled={isLoading}
                autoComplete="current-password"
                leftIcon={Lock}
                rightSlot={
                  <button
                    type="button"
                    onClick={() => setShowPassword((prev) => !prev)}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label={showPassword ? 'Nascondi password' : 'Mostra password'}
                    disabled={isLoading}
                  >
                    {showPassword ? (
                      <EyeOff className="h-5 w-5" />
                    ) : (
                      <Eye className="h-5 w-5" />
                    )}
                  </button>
                }
              />

              <button
                type="submit"
                disabled={!canSubmit}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Accesso in corso...
                  </>
                ) : (
                  'Accedi'
                )}
              </button>
            </form>
          </div>
        </div>

        <div className="mt-4 text-center text-xs text-white/80">
          © {new Date().getFullYear()} WinDeploy
        </div>
      </div>
    </div>
  );
}
```


### 4) `App.jsx`

```jsx
// frontend/src/App.jsx
import React, { useEffect } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

import MainLayout from './components/layout/MainLayout';
import { useAuthStore } from './store/authStore';

import LoginPage from './pages/Login/LoginPage';
import DashboardPage from './pages/Dashboard/DashboardPage';
import WizardsListPage from './pages/Wizards/WizardsListPage';
import WizardBuilderPage from './pages/Wizards/WizardBuilderPage';
import WizardMonitorPage from './pages/Wizards/WizardMonitorPage';
import TemplatesPage from './pages/Templates/TemplatesPage';
import SoftwarePage from './pages/Software/SoftwarePage';
import ReportsPage from './pages/Reports/ReportsPage';
import UsersPage from './pages/Users/UsersPage';
import NotFoundPage from './pages/NotFound/NotFoundPage';

function ProtectedRoute({ children }) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return children;
}

function App() {
  const token = useAuthStore((state) => state.token);
  const user = useAuthStore((state) => state.user);
  const checkAuth = useAuthStore((state) => state.checkAuth);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  useEffect(() => {
    // Gestisce il caso refresh: token persistito ma profilo non ancora caricato.
    if (token && !user) {
      checkAuth().catch(() => {
        // Il cleanup della sessione viene già gestito nello store / interceptor.
      });
    }
  }, [token, user, checkAuth]);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-50">
      <Routes>
        <Route path="/login" element={<LoginPage />} />

        <Route
          path="/"
          element={
            <ProtectedRoute>
              <MainLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="dashboard" element={<DashboardPage />} />
          <Route path="wizards" element={<WizardsListPage />} />
          <Route path="wizards/new" element={<WizardBuilderPage />} />
          <Route path="wizards/:id/monitor" element={<WizardMonitorPage />} />
          <Route path="templates" element={<TemplatesPage />} />
          <Route path="software" element={<SoftwarePage />} />
          <Route path="reports" element={<ReportsPage />} />
          <Route path="users" element={<UsersPage />} />
        </Route>

        <Route
          path="*"
          element={
            isAuthenticated ? (
              <NotFoundPage />
            ) : (
              <Navigate to="/login" replace />
            )
          }
        />
      </Routes>
    </div>
  );
}

export default App;
```


### 5) `MainLayout.jsx`

```jsx
// frontend/src/components/layout/MainLayout.jsx
import React, { useMemo } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import {
  ClipboardList,
  FileText,
  LayoutDashboard,
  LogOut,
  Package,
  Users,
  Wand2,
} from 'lucide-react';
import { useAuthStore } from '../../store/authStore';

function MainLayout() {
  const user = useAuthStore((state) => state.user);
  const role = useAuthStore((state) => state.role);
  const logout = useAuthStore((state) => state.logout);

  const navigationItems = useMemo(() => {
    const items = [
      {
        to: '/dashboard',
        label: 'Dashboard',
        icon: LayoutDashboard,
        hidden: false,
      },
      {
        to: '/wizards',
        label: 'Wizards',
        icon: Wand2,
        hidden: false,
      },
      {
        to: '/templates',
        label: 'Templates',
        icon: FileText,
        hidden: false,
      },
      {
        to: '/software',
        label: 'Software library',
        icon: Package,
        hidden: role === 'viewer',
      },
      {
        to: '/reports',
        label: 'Reports',
        icon: ClipboardList,
        hidden: false,
      },
      {
        to: '/users',
        label: 'Users',
        icon: Users,
        hidden: role === 'technician' || role === 'viewer',
      },
    ];

    return items.filter((item) => !item.hidden);
  }, [role]);

  const displayName = user?.name || user?.nome || user?.email || 'Utente';
  const displayRole = role || user?.role || user?.ruolo || 'viewer';

  function handleLogout() {
    logout();
  }

  return (
    <div className="flex min-h-screen">
      <aside className="flex w-64 flex-col border-r border-slate-800 bg-slate-900">
        <div className="border-b border-slate-800 px-4 py-4">
          <div className="text-lg font-semibold">WinDeploy</div>

          <div className="mt-2 text-xs text-slate-400">
            <div>{displayName}</div>
            <div className="mt-1 uppercase tracking-wide text-slate-500">{displayRole}</div>
          </div>
        </div>

        <nav className="flex-1 space-y-1 px-2 py-4 text-sm">
          {navigationItems.map((item) => {
            const Icon = item.icon;

            return (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  `flex items-center gap-2 rounded px-3 py-2 ${
                    isActive
                      ? 'bg-slate-800 text-white'
                      : 'text-slate-300 hover:bg-slate-800/70'
                  }`
                }
              >
                <Icon className="h-4 w-4" />
                <span>{item.label}</span>
              </NavLink>
            );
          })}
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

export default MainLayout;
```


## Note critiche

C’è una piccola incoerenza documentale da gestire con attenzione: nei materiali del backend la risposta auth compare con campi annidati dentro `user` e naming misto `name/nome`, `role/ruolo`, quindi lo store sopra normalizza entrambe le forme per evitare rotture tra frontend e API.[^1_4][^1_1][^1_2]
C’è anche un trade-off architetturale importante: stai chiedendo `token` in `localStorage`, che è pratico per refresh e interceptor ma aumenta la superficie XSS rispetto a cookie `HttpOnly`; per WinDeploy questo è compatibile con l’MVP, ma lato sicurezza di lungo periodo conviene pianificare CSP rigorosa, evitare `dangerouslySetInnerHTML` e valutare in futuro Sanctum stateful via cookie per la web app.[^1_5][^1_2]
Ultimo punto da avvocato del diavolo: nascondere voci di menu in `MainLayout` migliora la UX, ma non è una misura di sicurezza; il progetto descrive ruoli distinti `admin`, `technician` e `viewer`, quindi i vincoli reali devono restare applicati anche lato backend e, se vuoi, anche nel routing client per evitare che un viewer navighi verso pagine di creazione tramite URL diretto.[^1_3][^1_5]

## Commit

```bash
git add frontend/src/store/authStore.js frontend/src/api/axios.js frontend/src/pages/Login/LoginPage.jsx frontend/src/App.jsx frontend/src/components/layout/MainLayout.jsx
git commit -m "feat(auth): add Zustand authStore with persist and axios interceptor

- authStore: login/logout/setUser/checkAuth with localStorage persistence
- axios: Bearer token request interceptor + 401 auto-logout response interceptor
- LoginPage: refactor to use authStore.login(), add error handling and spinner
- App: add ProtectedRoute, invoke checkAuth on mount
- MainLayout: role-based menu visibility, logout button wired to store"
```

Posso convertirti questo direttamente in patch `git diff` pronta da applicare.
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0015-dashboard.md

[^1_2]: 0014-Login.md

[^1_3]: 0013-SetupprogettoReact.md

[^1_4]: 0101-auth-e-sicurezza.md

[^1_5]: 0001-projectdescription.txt

[^1_6]: 0107-componenti React e UX.MD

[^1_7]: 0106-architetturacross-modulo e contratti dati.md

[^1_8]: 0105-schema DB.md

[^1_9]: 0104-frontend React e tooling Vite.md

[^1_10]: 0102-generazione bulk di codice CRUD.md

[^1_11]: 0103-configurazione auth e sicurezza.md

[^1_12]: 0100-File temporanei tmp_.php esposti nella root.md

[^1_13]: 0021-Installer-configurazione sistema.md

[^1_14]: 0020-Scansione-app-installate.md

[^1_15]: 0019-Struttura-agent.md

[^1_16]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_17]: 0017-motorpage.md

[^1_18]: 0016-wizards.md

[^1_19]: 0012-apiendpointwindows.md

[^1_20]: 0011-controllercrud.md

[^1_21]: 0010-autenticazione.md

[^1_22]: 0009-scaffoldprogetto.md

[^1_23]: 0003-setupiniziale.md


---

# vedo la pagina di login bianca

Non sembra un problema di autenticazione o API: se su `/login` vedi una pagina completamente bianca, il guasto è quasi certamente nel montaggio React, nel router, oppure in un crash runtime del componente `LoginPage`, perché la login di WinDeploy dovrebbe già renderizzare sfondo, card e form anche prima di fare la `POST /api/auth/login`.[^2_1][^2_2]

## Diagnosi rapida

Nel tuo scaffold WinDeploy il flusso atteso è `main.jsx` → `App.jsx` → `AppRouter` → `LoginPage.jsx`, con `BrowserRouter` montato in `main.jsx` e la route `/login` che punta alla pagina login.[^2_2]
Se uno di questi anelli è rotto, il risultato tipico è proprio schermo bianco senza UI visibile.[^2_3][^2_2]

Le cause più probabili, in ordine, sono queste:[^2_2][^2_3]

- `main.jsx` non monta davvero `<App />`.[^2_3][^2_2]
- `App.jsx` non renderizza il router corretto.[^2_2][^2_3]
- La route `/login` non punta al file giusto oppure l’import ha path/case errato.[^2_3][^2_2]
- `LoginPage.jsx` crasha a runtime per un import rotto, ad esempio `client` o `authStore`.[^2_1][^2_2]
- Tailwind ha `content` mancante: questo può svuotare o rompere molto lo styling, ma da solo di solito non basta a spiegare una root completamente vuota.[^2_1]


## File da controllare

Prima di scrivere patch, questi sono i file coinvolti nel percorso reale della login nel tuo progetto:[^2_1][^2_2]

- `frontend/src/main.jsx`[^2_2]
- `frontend/src/App.jsx`[^2_2]
- `frontend/src/router/AppRouter.jsx` oppure `frontend/src/router/index.jsx`, a seconda di quale importi davvero `App.jsx`[^2_3][^2_2]
- `frontend/src/pages/Login/LoginPage.jsx`[^2_1][^2_2]
- `frontend/src/store/authStore.js`[^2_1][^2_2]
- `frontend/src/api/client.js`[^2_2][^2_1]
- `frontend/tailwind.config.js` o `tailwind.config.cjs`[^2_1]


## Test deterministico

Fai questi test nell’ordine esatto, perché isolano il layer che si rompe.[^2_3]

- In `frontend/src/App.jsx`, sostituisci temporaneamente tutto con:

```jsx
import React from 'react';

export default function App() {
  return <div style={{ padding: 24, color: 'black', background: 'white' }}>APP MOUNTED</div>;
}
```

Se **non** vedi `APP MOUNTED`, il problema è prima del router: quasi certamente `main.jsx`, import/export rotti, oppure `root` non montata correttamente.[^2_3][^2_2]

- Se `APP MOUNTED` si vede, allora React monta correttamente e il problema è nel router o nella pagina login.[^2_3]
- A quel punto metti temporaneamente nel router reale:

```jsx
import React from 'react';

export default function AppRouter() {
  return <div style={{ padding: 24, color: 'black' }}>ROUTER OK</div>;
}
```

Se vedi `ROUTER OK`, il problema è nella definizione delle route oppure in `LoginPage.jsx`.[^2_3]

- Se `ROUTER OK` funziona, controlla che la route `/login` importi davvero `../pages/Login/LoginPage.jsx` e che il file esporti `export default function LoginPage()`.[^2_2][^2_1]


## Check immediati

Questa checklist è quella più veloce per il tuo caso:[^2_1][^2_2][^2_3]

- [ ] `main.jsx` monta davvero `<App />` dentro `BrowserRouter`.[^2_2]
- [ ] `App.jsx` importa il router corretto, senza doppioni tra `AppRouter.jsx` e `router/index.jsx`.[^2_2][^2_3]
- [ ] `LoginPage.jsx` esiste in `src/pages/Login/LoginPage.jsx` con `export default`.[^2_1][^2_2]
- [ ] `authStore.js` esporta `useAuthStore` e contiene `login`, `logout`, `isAuthenticated`.[^2_2]
- [ ] `client.js` esporta default Axios client.[^2_1][^2_2]
- [ ] `tailwind.config.*` ha `content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx}"]`.[^2_1]
- [ ] In DevTools Console non ci sono errori tipo `Failed to resolve import`, `useAuthStore is not a function`, `Cannot read properties of undefined`.[^2_3]


## Avvocato del diavolo

Il rischio più subdolo è avere due router diversi nel progetto, per esempio `src/router/AppRouter.jsx` e `src/router/index.jsx`, e che `App.jsx` ne importi uno mentre tu stai correggendo l’altro.[^2_3][^2_2]
Il secondo rischio è un import path corretto su Windows ma fragile altrove, per esempio mismatch di maiuscole/minuscole tra `layout/MainLayout.jsx`, `pages/Login/LoginPage.jsx` e gli import nel router.[^2_2][^2_3]

Incollami esattamente questi 4 file, così ti dico il punto rotto senza tentativi: `frontend/src/main.jsx`, `frontend/src/App.jsx`, il file router che `App.jsx` importa davvero, e `frontend/src/pages/Login/LoginPage.jsx`.[^2_1][^2_2]
<span style="display:none">[^2_4][^2_5][^2_6]</span>

<div align="center">⁂</div>

[^2_1]: 0014-Login.md

[^2_2]: 0013-SetupprogettoReact.md

[^2_3]: 0107-componenti-React-e-UX.MD

[^2_4]: image.jpg

[^2_5]: 0015-dashboard.md

[^2_6]: 0104-frontend-React-e-tooling-Vite.md

