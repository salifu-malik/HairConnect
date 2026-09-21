import api from './axios';

export const queueService = {
  fetchQueueStatus: async () => {
    const { data } = await api.get('/queue/status');
    return data;
  },
  joinQueue: async (shopId: string) => {
    const { data } = await api.post('/queue/join', { shopId });
    return data;
  },
  leaveQueue: async () => {
    await api.post('/queue/leave');
  },
};
