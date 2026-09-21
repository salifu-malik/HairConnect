import api from './axios';

export const barberDashboardService = {
  fetchDashboardStats: async () => {
    const { data } = await api.get('/barber/dashboard/stats');
    return data;
  },
  fetchAppointments: async () => {
    const { data } = await api.get('/barber/appointments');
    return data;
  },
  updateAppointmentStatus: async (id: string, status: string) => {
    const { data } = await api.put(`/barber/appointments/${id}`, { status });
    return data;
  },
  fetchServices: async () => {
    const { data } = await api.get('/barber/services');
    return data;
  },
  createService: async (serviceData: any) => {
    const { data } = await api.post('/barber/services', serviceData);
    return data;
  },
  fetchWallet: async () => {
    const { data } = await api.get('/barber/wallet');
    return data;
  },
};
