import api from './axios';
import { API_ENDPOINTS } from './endpoints';

export const authService = {
  login: async (data: any) => {
    return api.post(API_ENDPOINTS.AUTH.LOGIN, data);
  },

  register: async (data: any) => {
    return api.post(API_ENDPOINTS.AUTH.REGISTER, data);
  },

  logout: async () => {
    return api.post(API_ENDPOINTS.AUTH.LOGOUT);
  },

  requestPasswordReset: async (data: { email: string }) => {
    return api.post(
        API_ENDPOINTS.AUTH.REQUEST_PASSWORD_RESET,
        data
    );
  },

  verifyPasswordResetCode: async (data: {
    email: string;
    code: string;
  }) => {
    return api.post(
        API_ENDPOINTS.AUTH.VERIFY_PASSWORD_RESET_CODE,
        data
    );
  },

  resetPassword: async (data: {
    reset_token: string;
    password: string;
    confirm_password: string;
  }) => {
    return api.post(
        API_ENDPOINTS.AUTH.RESET_PASSWORD,
        data
    );
  },
};