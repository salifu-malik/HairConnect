import { create } from 'zustand';
import { User } from '../types/auth';

interface AuthState {
  token: string | null;
  refreshToken: string | null;
  user: User | null;

  setAuth: (
      token: string,
      refreshToken: string,
      user: User
  ) => void;

  logout: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  token: localStorage.getItem('token'),

  refreshToken: localStorage.getItem('refresh_token'),

  user: (() => {
    try {
      const user = localStorage.getItem('user');
      return user ? JSON.parse(user) : null;
    } catch {
      return null;
    }
  })(),

  setAuth: (token, refreshToken, user) => {
    localStorage.setItem('token', token);
    localStorage.setItem('refresh_token', refreshToken);
    localStorage.setItem('user', JSON.stringify(user));

    set({
      token,
      refreshToken,
      user
    });
  },

  logout: () => {
    localStorage.removeItem('token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');

    set({
      token: null,
      refreshToken: null,
      user: null
    });
  }
}));
