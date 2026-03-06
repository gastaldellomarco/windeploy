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
        const response = await api.post('/auth/login', {
          email,
          password,
        });

        const payload = response?.data ?? {};
        // Support two common shapes: { user: { ... }, token } or { ...userFields..., token }
        const user = normalizeUser(payload.user ?? payload);
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
        } catch {
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
        const normalizedUser = normalizeUser(user);

        set((state) => ({
          user: normalizedUser,
          role: normalizedUser?.role ?? state.role ?? null,
          isAuthenticated: Boolean(state.token && normalizedUser),
        }));
      },

      async checkAuth() {
        try {
          const response = await api.get('/auth/me', {
            _skipAuthLogout: true,
          });

          const remoteUser = normalizeUser(response?.data?.user ?? response?.data);

          set((state) => ({
            user: remoteUser,
            role: remoteUser?.role ?? state.role ?? null,
            isAuthenticated: Boolean(state.token && remoteUser),
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

// Compatibilità: molti moduli importano lo store come default.
export default useAuthStore;
