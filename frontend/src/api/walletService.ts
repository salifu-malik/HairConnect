import api from './axios';

export const walletService = {
  fetchWallet: async () => {
    const { data } = await api.get('/wallet');
    return data;
  },
  fetchTransactions: async () => {
    const { data } = await api.get('/wallet/transactions');
    return data;
  },
  requestWithdrawal: async (amount: number, method: string, details: string) => {
    const { data } = await api.post('/wallet/withdraw', { amount, method, details });
    return data;
  },
};
