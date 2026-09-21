import { useEffect, useState } from 'react';
import { financeService } from '../../../api/financeService';
import { toast } from 'react-hot-toast';

export const FinanceManagement = () => {
  const [transactions, setTransactions] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = async () => {
    try {
      const data = await financeService.fetchTransactions();
      setTransactions(data);
    } catch (error) {
      toast.error('Failed to load transactions');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleRefund = async (id: string) => {
    try {
      await financeService.processRefund(id);
      toast.success('Refund processed');
      fetchData();
    } catch (error) {
      toast.error('Failed to process refund');
    }
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div className="bg-white p-6 rounded shadow mt-6">
      <h3 className="text-lg font-semibold mb-4">Transactions & Refunds</h3>
      <table className="w-full">
        <thead>
          <tr>
            <th className="text-left">ID</th>
            <th className="text-left">Amount</th>
            <th className="text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          {transactions.map((t: any) => (
            <tr key={t.id}>
              <td>{t.id}</td>
              <td>{t.amount}</td>
              <td>
                <button onClick={() => handleRefund(t.id)} className="bg-orange-500 text-white px-2 py-1 rounded">Refund</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
