import { useEffect, useState } from 'react';
import { vendorService } from '../api/vendorService';
import { toast } from 'react-hot-toast';
import { DashboardChart } from '../components/charts/DashboardChart';

export const VendorDashboardPage = () => {
  const [stats, setStats] = useState<any>(null);
  const [analytics, setAnalytics] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      vendorService.fetchDashboardStats(),
      vendorService.fetchAnalytics()
    ])
      .then(([statsData, analyticsData]) => {
        setStats(statsData);
        setAnalytics(analyticsData);
      })
      .catch(() => toast.error('Failed to load dashboard data'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-6">Vendor Dashboard</h2>
      
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold">Total Revenue</h3>
          <p className="text-3xl font-bold">GHS {stats?.revenue || 0}</p>
        </div>
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold">Total Orders</h3>
          <p className="text-3xl font-bold">{stats?.ordersCount || 0}</p>
        </div>
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold">Active Products</h3>
          <p className="text-3xl font-bold">{stats?.productsCount || 0}</p>
        </div>
      </div>
      
      <DashboardChart
        data={analytics}
        xKey="label"
        yKey="value"
        title="Sales Performance"
        color="#f59e0b"
      />

      <div className="space-y-6 mt-6">
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold mb-4">Product Management</h3>
          <div className="text-gray-500">Product management features coming soon.</div>
        </div>
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold mb-4">Order Management</h3>
          <div className="text-gray-500">Order management features coming soon.</div>
        </div>
      </div>
    </div>
  );
};
