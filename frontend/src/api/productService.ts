import api from './axios';

export const productService = {
  fetchProducts: async (params?: { search?: string; category?: string; sort?: string }) => {
    return api.get('/products', { params });
  },
  fetchProductById: async (id: string) => {
    return api.get(`/products/${id}`);
  },
};
