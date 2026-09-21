import api from './axios';

export const reviewService = {
  fetchReviews: async (targetType: 'BARBER' | 'PRODUCT', targetId: string) => {
    const { data } = await api.get(`/reviews/${targetType}/${targetId}`);
    return data;
  },
  submitReview: async (targetType: 'BARBER' | 'PRODUCT', targetId: string, rating: number, comment: string) => {
    const { data } = await api.post('/reviews', { targetType, targetId, rating, comment });
    return data;
  },
};
