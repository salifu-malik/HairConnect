import api from './axios';

export const profileService = {
  fetchProfile: async () => {
    const { data } = await api.get('/profile');

    return data.data;
  },

  updateProfile: async (profileData: any) => {
    const { data } = await api.put('/profile', profileData);

    return data;
  },

  changePassword: async (passwordData: any) => {
    await api.post('/profile/change-password', passwordData);
  },
};