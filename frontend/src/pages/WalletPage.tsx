import { useEffect, useState } from 'react';
import { walletService } from '../api/walletService';
import toast from 'react-hot-toast';

export const WalletPage = () => {
  const [wallet, setWallet] = useState<any>(null);
  const [transactions, setTransactions] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      walletService.fetchWallet(),
      walletService.fetchTransactions(),
    ])
        .then(([walletData, transactionData]) => {
          setWallet(walletData);
          setTransactions(transactionData);
        })
        .catch(() => toast.error('Failed to load wallet data'))
        .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
        <div className="p-6 text-gray-700 dark:text-gray-300">
          Loading...
        </div>
    );
  }

  return (
      <div
          className="
        p-6
        text-gray-900
        dark:text-gray-100
        transition-colors
        duration-300
      "
      >
        {/* Page Header */}
        <div className="mb-6">
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
            Wallet
          </h2>

          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage your wallet balance and view your transaction history.
          </p>
        </div>

        {/* Balance Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

          {/* Available Balance */}
          <div
              className="
            bg-white
            dark:bg-gray-900
            p-6
            rounded-lg
            shadow
            dark:shadow-black/20
            border
            border-gray-100
            dark:border-gray-800
            transition-colors
            duration-300
          "
          >
            <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
              Available Balance
            </h3>

            <p className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
              GHS {wallet?.balance || 0}
            </p>
          </div>

          {/* Pending Balance */}
          <div
              className="
            bg-white
            dark:bg-gray-900
            p-6
            rounded-lg
            shadow
            dark:shadow-black/20
            border
            border-gray-100
            dark:border-gray-800
            transition-colors
            duration-300
          "
          >
            <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
              Pending Balance
            </h3>

            <p className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
              GHS {wallet?.pending || 0}
            </p>
          </div>
        </div>

        {/* Transaction History */}
        <div
            className="
          bg-white
          dark:bg-gray-900
          p-6
          rounded-lg
          shadow
          dark:shadow-black/20
          border
          border-gray-100
          dark:border-gray-800
          transition-colors
          duration-300
        "
        >
          <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
            Transaction History
          </h3>

          {/* Responsive Table */}
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
              <tr className="border-b border-gray-200 dark:border-gray-800">
                <th className="py-3 text-gray-700 dark:text-gray-300 font-semibold">
                  Date
                </th>

                <th className="py-3 text-gray-700 dark:text-gray-300 font-semibold">
                  Description
                </th>

                <th className="py-3 text-gray-700 dark:text-gray-300 font-semibold">
                  Amount
                </th>
              </tr>
              </thead>

              <tbody>
              {transactions.map((tx) => (
                  <tr
                      key={tx.id}
                      className="
                    border-b
                    border-gray-100
                    dark:border-gray-800
                    hover:bg-gray-50
                    dark:hover:bg-gray-800/50
                    transition-colors
                  "
                  >
                    <td className="py-3 text-gray-700 dark:text-gray-300">
                      {tx.date}
                    </td>

                    <td className="py-3 text-gray-900 dark:text-gray-100">
                      {tx.description}
                    </td>

                    <td className="py-3 font-medium text-gray-900 dark:text-white">
                      {tx.amount}
                    </td>
                  </tr>
              ))}
              </tbody>
            </table>
          </div>

          {/* Empty State */}
          {transactions.length === 0 && (
              <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                No transactions found.
              </div>
          )}
        </div>
      </div>
  );
};