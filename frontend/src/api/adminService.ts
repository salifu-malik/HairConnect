import api from './axios';

export const adminService = {
  fetchStats: async () => {
    const { data } = await api.get('/admin/stats');
    return data;
  },
  fetchAnalytics: async () => {
    const { data } = await api.get('/admin/analytics');
    return data;
  },
  fetchUsers: async () => {
    const { data } = await api.get('/admin/users');
    return data;
  },
  suspendUser: async (id: string) => {
    await api.post(`/admin/users/${id}/suspend`);
  },
  fetchApprovals: async () => {
    const { data } = await api.get('/admin/approvals');
    return data;
  },
  approveUser: async (id: string) => {
    await api.post(`/admin/approvals/${id}/approve`);
  },
  getSettings: async () => {
    const { data } = await api.get('/admin/settings');
    return data;
  },
  updateSettings: async (settings: any) => {
    await api.post('/admin/settings', settings);
  },
  fetchAuditLogs: async () => {
    const { data } = await api.get('/admin/audit-logs');
    return data;
  },
};
