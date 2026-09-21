import { useEffect, useState } from 'react';
import { financeService } from '../../../api/financeService';
import { DashboardChart } from '../../../components/charts/DashboardChart';
import { toast } from 'react-hot-toast';

export const FinanceAnalytics = () => {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    financeService.fetchAnalytics()
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
      title="Revenue Overview"
      color="#10b981"
    />
  );
};
