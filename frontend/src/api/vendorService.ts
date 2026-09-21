import api from './axios';

export const vendorService = {
  fetchDashboardStats: async () => {
    const { data } = await api.get('/vendor/stats');
    return data;
  },
  fetchProducts: async () => {
    const { data } = await api.get('/vendor/products');
    return data;
  },
  addProduct: async (product: any) => {
    await api.post('/vendor/products', product);
  },
  updateProduct: async (id: string, product: any) => {
    await api.put(`/vendor/products/${id}`, product);
  },
  deleteProduct: async (id: string) => {
    await api.delete(`/vendor/products/${id}`);
  },
  fetchOrders: async () => {
    const { data } = await api.get('/vendor/orders');
    return data;
  },
  updateOrderStatus: async (id: string, status: string) => {
    await api.patch(`/vendor/orders/${id}`, { status });
  },
  fetchAnalytics: async () => {
    const { data } = await api.get('/vendor/analytics');
    return data;
  },
};
