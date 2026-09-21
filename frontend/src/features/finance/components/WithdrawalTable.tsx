import { financeService } from '../../../api/financeService';
import { useEffect, useState } from 'react';
import { toast } from 'react-hot-toast';

export const WithdrawalTable = () => {
  const [withdrawals, setWithdrawals] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchWithdrawals = async () => {
    try {
      const data = await financeService.fetchWithdrawals();
      setWithdrawals(data);
    } catch (error) {
      toast.error('Failed to load withdrawals');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchWithdrawals();
  }, []);

  const handleAction = async (id: string, action: 'approve' | 'reject') => {
    try {
      await financeService.processWithdrawal(id, action);
      toast.success(`Withdrawal ${action}ed`);
      fetchWithdrawals();
    } catch (error) {
      toast.error(`Failed to ${action} withdrawal`);
    }
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div className="bg-white p-6 rounded shadow mt-6">
      <h3 className="text-lg font-semibold mb-4">Pending Withdrawals</h3>
      <table className="w-full">
        <thead>
          <tr>
            <th className="text-left">User</th>
            <th className="text-left">Amount</th>
            <th className="text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          {withdrawals.map((w: any) => (
            <tr key={w.id}>
              <td>{w.userName}</td>
              <td>{w.amount}</td>
              <td>
                <button onClick={() => handleAction(w.id, 'approve')} className="bg-green-500 text-white px-2 py-1 rounded mr-2">Approve</button>
                <button onClick={() => handleAction(w.id, 'reject')} className="bg-red-500 text-white px-2 py-1 rounded">Reject</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
