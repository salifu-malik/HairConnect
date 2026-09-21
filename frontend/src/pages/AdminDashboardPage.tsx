import { AdminUserTable } from '../features/admin/components/AdminUserTable';
import { AdminAnalytics } from '../features/admin/components/AdminAnalytics';
import { AdminSubscriptionManager } from '../features/admin/components/AdminSubscriptionManager';
import { AdminSettings } from '../features/admin/components/AdminSettings';
import { AdminAuditLogs } from '../features/admin/components/AdminAuditLogs';

export const AdminDashboardPage = () => {
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-6">Admin Dashboard</h2>
      <p>Welcome to the platform administration portal.</p>
      
      <AdminAnalytics />
      <AdminUserTable />
      <AdminSubscriptionManager />
      <AdminSettings />
      <AdminAuditLogs />
    </div>
  );
};
