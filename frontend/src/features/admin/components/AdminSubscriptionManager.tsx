import { useEffect, useState } from 'react';
import { subscriptionService } from '../../../api/subscriptionService';
import { toast } from 'react-hot-toast';

export const AdminSubscriptionManager = () => {
  const [plans, setPlans] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchPlans = async () => {
    try {
      const data = await subscriptionService.fetchPlans();
      setPlans(data);
    } catch (error) {
      toast.error('Failed to load plans');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPlans();
  }, []);

  if (loading) return <div>Loading plans...</div>;

  return (
    <div className="bg-white p-6 rounded shadow mt-6">
      <h3 className="text-lg font-semibold mb-4">Subscription Plans</h3>
      <table className="w-full">
        <thead>
          <tr>
            <th className="text-left">Name</th>
            <th className="text-left">Price</th>
            <th className="text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          {plans.map((plan: any) => (
            <tr key={plan.id}>
              <td>{plan.name}</td>
              <td>{plan.price}</td>
              <td>
                <button className="text-blue-500 mr-2">Edit</button>
                <button className="text-red-500">Delete</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
