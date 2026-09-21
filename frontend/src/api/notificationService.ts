import api from './axios';

export const notificationService = {
  fetchNotifications: async () => {
    const { data } = await api.get('/notifications');
    return data;
  },
  markAsRead: async (id: string) => {
    await api.patch(`/notifications/${id}/read`);
  },
};
