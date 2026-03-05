import axios from 'axios';
import { useAuthStore } from '../store/authStore';

// ── NOTA ARCHITETTURALE ────────────────────────────────────────────────────
// baseURL è '/api' (relativo, senza host).
// In DEV: Vite intercetta /api/* e lo proxy verso http://windeploy.local.api
// In PROD: Nginx serve React e fa reverse proxy /api → Laravel backend
// NON usare mai 'http://windeploy.local.api/api' qui —
// bypassa il proxy e causa CORS in sviluppo.
// ──────────────────────────────────────────────────────────────────────────

const client = axios.create({
  baseURL: '/api',

  // withCredentials: true → invia cookie di sessione Sanctum
  // Necessario per il flusso SPA con CSRF token
  withCredentials: true,

  // Timeout aumentato a 30s per operazioni lunghe (es. avvio wizard)
  timeout: 30000,
});

// ── INTERCEPTOR REQUEST ───────────────────────────────────────────────────
// Aggiunge automaticamente a ogni chiamata:
// 1. Accept: application/json → Laravel risponde sempre JSON, mai HTML
// 2. Authorization: Bearer <token> → letto da Zustand authStore
client.interceptors.request.use(
  (config) => {
    config.headers = config.headers ?? {};

    // Forza risposta JSON — evita che Laravel restituisca redirect HTML
    config.headers['Accept'] = 'application/json';

    // Legge il token dallo store Zustand (non da React hook — siamo fuori da component tree)
    const { token } = useAuthStore.getState();
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// ── INTERCEPTOR RESPONSE ──────────────────────────────────────────────────
// Gestione centralizzata degli errori HTTP:
// - 401 Unauthorized → logout automatico + redirect a /login
// - 422 Unprocessable → errori di validazione Laravel (gestiti nei singoli hook)
// - 500 Server Error → toast errore generico
client.interceptors.response.use(
  (response) => response, // Risposta OK: pass-through senza modifiche

  (error) => {
    const status = error?.response?.status;

    if (status === 401) {
      // Token scaduto o non valido → invalida la sessione locale
      const { logout } = useAuthStore.getState();
      logout();

      // Redirect grezzo (fuori da React Router) per evitare dipendenze circolari
      // Guard: non fare redirect se siamo già su /login (evita loop)
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    if (status === 500) {
      // Errore interno Laravel — mostra toast generico
      // Importa dinamicamente react-hot-toast per non creare dipendenza circolare
      import('react-hot-toast').then(({ default: toast }) => {
        toast.error('Errore del server. Riprova o contatta il supporto.');
      });
    }

    // Per 422 (validazione) i singoli hook gestiscono error.response.data.errors
    return Promise.reject(error);
  }
);

export default client;
