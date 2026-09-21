export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/auth/login',
    REGISTER: '/auth/register',
    REFRESH: '/auth/refresh',
    LOGOUT: '/auth/logout',

    REQUEST_PASSWORD_RESET: '/auth/forgot-password',
    VERIFY_PASSWORD_RESET_CODE: '/auth/verify-reset-code',
    RESET_PASSWORD: '/auth/reset-password',
  },

  PROFILE: {
    GET: '/profile',
    UPDATE: '/profile',
    CHANGE_PASSWORD: '/profile/change-password',
  },
};