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
