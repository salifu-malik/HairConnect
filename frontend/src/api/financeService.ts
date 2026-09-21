import api from './axios';

export const financeService = {
  fetchRevenueOverview: async () => {
    const { data } = await api.get('/finance/revenue');
    return data;
  },
  fetchAnalytics: async () => {
    const { data } = await api.get('/finance/analytics');
    return data;
  },
  fetchWithdrawals: async () => {
    const { data } = await api.get('/finance/withdrawals');
    return data;
  },
  processWithdrawal: async (id: string, action: 'approve' | 'reject') => {
    await api.post(`/finance/withdrawals/${id}/${action}`);
  },
  fetchTransactions: async () => {
    const { data } = await api.get('/finance/transactions');
    return data;
  },
  processRefund: async (id: string) => {
    await api.post(`/finance/refunds/${id}`);
  },
};
