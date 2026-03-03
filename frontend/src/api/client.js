import axios from 'axios';
import { useAuthStore } from '../store/authStore';

// Creiamo una singola instance Axios per tutto il frontend
const client = axios.create({
  baseURL: '/api',
  withCredentials: true, // enable cookies for Sanctum; ensure CORS allows credentials
  timeout: 15000,
});

// Force Accept: application/json for all requests so Laravel treats them as API calls
client.interceptors.request.use(
  (config) => {
    config.headers = config.headers ?? {};
    config.headers.Accept = 'application/json';

    const { token } = useAuthStore.getState();
    if (token) {
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

  // During debugging avoid forcing a hard redirect which hides the original response.
  // Comment out the rough redirect so you can inspect the 401 response in DevTools.
  // if (window.location.pathname !== '/login') {
  //   window.location.href = '/login';
  // }
    }

    return Promise.reject(error);
  }
);

export default client;
