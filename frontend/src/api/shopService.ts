import api from './axios';

export const shopService = {
  fetchDashboardStats: async () => {
    const { data } = await api.get('/shop/stats');
    return data;
  },

  fetchBarbers: async () => {
    const { data } = await api.get('/shop/barbers');
    return data;
  },

  addBarber: async (barber: any) => {
    const { data } = await api.post('/shop/barbers', barber);
    return data;
  },

  removeBarber: async (id: string) => {
    const { data } = await api.delete(`/shop/barbers/${id}`);
    return data;
  },

  fetchServices: async () => {
    const { data } = await api.get('/shop/services');
    return data;
  },

  addService: async (service: any) => {
    const { data } = await api.post('/shop/services', service);
    return data;
  },

  updateService: async (id: string, service: any) => {
    const { data } = await api.put(`/shop/services/${id}`, service);
    return data;
  },

  deleteService: async (id: string) => {
    const { data } = await api.delete(`/shop/services/${id}`);
    return data;
  },

  /**
   * Get barbers belonging to the authenticated shop owner's shop.
   */
  fetchMyBarbers: async () => {
    const { data } = await api.get('/shop-owner/barbers');
    return data;
  },

  /**
   * Approve or reject a barber application.
   */
  updateBarberApproval: async (
    barberId: number,
    approvalStatus: 'approved' | 'rejected'
  ) => {
    const { data } = await api.patch(
      `/shop-owner/barbers/${barberId}/approval`,
{
  approval_status: approvalStatus,
}
);

return data;
},
};
