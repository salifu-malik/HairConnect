import api from './axios';

export const subscriptionService = {
  fetchPlans: async (role?: 'BARBER' | 'VENDOR') => {
    const url = role ? `/subscriptions/plans?role=${role}` : '/subscriptions/plans';
    const { data } = await api.get(url);
    return data;
  },
  subscribe: async (planId: string) => {
    const { data } = await api.post('/subscriptions/subscribe', { planId });
    return data;
  },
  cancelSubscription: async () => {
    await api.post('/subscriptions/cancel');
  },
};
