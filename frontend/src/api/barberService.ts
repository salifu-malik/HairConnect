import api from './axios';

export const barberService = {
  fetchBarbers: async (params?: { search?: string; location?: string; rating?: number }) => {
    return api.get('/barbers', { params });
  },
  fetchBarberById: async (id: string) => {
    return api.get(`/barbers/${id}`);
  },
};
