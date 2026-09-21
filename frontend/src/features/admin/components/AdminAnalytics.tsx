import { useEffect, useState } from 'react';
import { adminService } from '../../../api/adminService';
import { DashboardChart } from '../../../components/charts/DashboardChart';
import { toast } from 'react-hot-toast';

export const AdminAnalytics = () => {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    adminService.fetchAnalytics()
      .then(setData)
      .catch(() => toast.error('Failed to load analytics'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div>Loading analytics...</div>;

  return (
    <DashboardChart
      data={data}
      xKey="label"
      yKey="value"
      title="Platform Statistics"
      color="#3b82f6"
    />
  );
};
