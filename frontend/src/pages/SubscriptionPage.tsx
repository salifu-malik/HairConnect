import { useEffect, useState } from 'react';
import { subscriptionService } from '../api/subscriptionService';
import { useAuthStore } from '../stores/authStore';
import toast from 'react-hot-toast';

export const SubscriptionPage = () => {
  const { user } = useAuthStore();
  const [plans, setPlans] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (user?.role === 'BARBER' || user?.role === 'VENDOR') {
      subscriptionService.fetchPlans(user.role)
        .then(setPlans)
        .catch(() => toast.error('Failed to load plans'))
        .finally(() => setLoading(false));
    }
  }, [user]);

  if (loading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-6">Subscription Plans</h2>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {plans.map((plan) => (
          <div key={plan.id} className="bg-white p-6 rounded shadow border">
            <h3 className="text-xl font-bold">{plan.name}</h3>
            <p className="text-3xl font-bold my-4">GHS {plan.price}</p>
            <ul className="mb-6 space-y-2">
              {plan.features.map((feature: string) => (
                <li key={feature}>✓ {feature}</li>
              ))}
            </ul>
            <button
              onClick={() => {
                subscriptionService.subscribe(plan.id)
                  .then(() => toast.success('Subscribed successfully'))
                  .catch(() => toast.error('Subscription failed'));
              }}
              className="w-full bg-blue-600 text-white py-2 rounded"
            >
              Subscribe
            </button>
          </div>
        ))}
      </div>
    </div>
  );
};
