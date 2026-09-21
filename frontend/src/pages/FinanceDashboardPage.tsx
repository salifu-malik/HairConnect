import { WithdrawalTable } from '../features/finance/components/WithdrawalTable';
import { FinanceAnalytics } from '../features/finance/components/FinanceAnalytics';
import { FinanceManagement } from '../features/finance/components/FinanceManagement';
import { UserApprovalTable } from '../features/finance/components/UserApprovalTable';

export const FinanceDashboardPage = () => {
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-6">Finance Dashboard</h2>
      <p>Welcome to the finance management portal.</p>
      
      <FinanceAnalytics />
      <WithdrawalTable />
      <FinanceManagement />
      <UserApprovalTable />
    </div>
  );
};
