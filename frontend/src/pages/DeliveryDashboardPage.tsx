import { useEffect, useState } from 'react';
import { deliveryService } from '../api/deliveryService';
import { toast } from 'react-hot-toast';

export const DeliveryDashboardPage = () => {
  const [stats, setStats] = useState<any>(null);
  const [assignments, setAssignments] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      deliveryService.fetchStats(),
      deliveryService.fetchAssignments()
    ])
      .then(([statsData, assignmentsData]) => {
        setStats(statsData);
        setAssignments(assignmentsData);
      })
      .catch(() => toast.error('Failed to load delivery data'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-6">Delivery Dashboard</h2>
      
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold">Pending Deliveries</h3>
          <p className="text-3xl font-bold">{stats?.pendingCount || 0}</p>
        </div>
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold">Completed Today</h3>
          <p className="text-3xl font-bold">{stats?.completedToday || 0}</p>
        </div>
        <div className="bg-white p-6 rounded shadow">
          <h3 className="text-lg font-semibold">Total Earnings</h3>
          <p className="text-3xl font-bold">GHS {stats?.earnings || 0}</p>
        </div>
      </div>

      <div className="bg-white p-6 rounded shadow">
        <h3 className="text-lg font-semibold mb-4">Current Assignments</h3>
        {assignments.length === 0 ? (
          <div className="text-gray-500">No active assignments.</div>
        ) : (
          <table className="w-full text-left">
            <thead>
              <tr className="border-b">
                <th className="py-2">Order ID</th>
                <th className="py-2">Destination</th>
                <th className="py-2">Status</th>
                <th className="py-2">Action</th>
              </tr>
            </thead>
            <tbody>
              {assignments.map((assignment) => (
                <tr key={assignment.id} className="border-b">
                  <td className="py-2">{assignment.orderId}</td>
                  <td className="py-2">{assignment.destination}</td>
                  <td className="py-2">{assignment.status}</td>
                  <td className="py-2">
                    <button className="text-blue-600 hover:underline">Update</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
};
