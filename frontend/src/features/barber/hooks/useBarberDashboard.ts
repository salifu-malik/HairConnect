import { useEffect, useState } from 'react';
import { barberDashboardService } from '../../../api/barberDashboardService';
import { toast } from 'react-hot-toast';

export const useBarberDashboard = () => {
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const data = await barberDashboardService.fetchDashboardStats();
        setStats(data);
      } catch (error) {
        toast.error('Failed to load dashboard statistics');
      } finally {
        setLoading(false);
      }
    };
    fetchStats();
  }, []);

  return { stats, loading };
};
