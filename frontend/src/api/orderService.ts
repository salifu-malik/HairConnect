import api from './axios';

export const orderService = {
  createOrder: async (orderData: any) => {
    return api.post('/orders', orderData);
  },
  fetchOrders: async () => {
    return api.get('/orders');
  },
};
