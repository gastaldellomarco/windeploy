// frontend/src/api/axios.js
import axios from 'axios';
import { useAuthStore } from '../store/authStore';

const api = axios.create({
  // Use VITE_API_URL when provided, otherwise default to '/api' so Vite dev proxy handles requests
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
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
