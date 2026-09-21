import { useEffect, useState } from 'react';
import { barberDashboardService } from '../../../api/barberDashboardService';
import { toast } from 'react-hot-toast';

export const useBarberAppointments = () => {
  const [appointments, setAppointments] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchAppointments = async () => {
    try {
      const data = await barberDashboardService.fetchAppointments();
      setAppointments(data);
    } catch (error) {
      toast.error('Failed to load appointments');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAppointments();
  }, []);

  const updateStatus = async (id: string, status: string) => {
    try {
      await barberDashboardService.updateAppointmentStatus(id, status);
      toast.success('Appointment updated');
      fetchAppointments();
    } catch (error) {
      toast.error('Failed to update appointment');
    }
  };

  return { appointments, loading, updateStatus };
};
