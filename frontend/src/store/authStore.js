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

// Default export for consumers that import the store as default
export default useAuthStore;
