import api from './axios';

export const deliveryService = {
  fetchAssignments: async () => {
    const { data } = await api.get('/delivery/assignments');
    return data;
  },
  updateAssignmentStatus: async (id: string, status: string) => {
    await api.patch(`/delivery/assignments/${id}`, { status });
  },
  fetchStats: async () => {
    const { data } = await api.get('/delivery/stats');
    return data;
  },
};
