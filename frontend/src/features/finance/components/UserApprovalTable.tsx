import { adminService } from '../../../api/adminService';
import { useEffect, useState } from 'react';
import { toast } from 'react-hot-toast';

export const UserApprovalTable = () => {
  const [approvals, setApprovals] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchApprovals = async () => {
    try {
      const data = await adminService.fetchApprovals();
      setApprovals(data);
    } catch (error) {
      toast.error('Failed to load approvals');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApprovals();
  }, []);

  const handleApprove = async (id: string) => {
    try {
      await adminService.approveUser(id);
      toast.success('Approved');
      fetchApprovals();
    } catch (error) {
      toast.error('Failed to approve');
    }
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div className="bg-white p-6 rounded shadow mt-6">
      <h3 className="text-lg font-semibold mb-4">Pending Approvals</h3>
      <table className="w-full">
        <thead>
          <tr>
            <th className="text-left">Name</th>
            <th className="text-left">Type</th>
            <th className="text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          {approvals.map((app: any) => (
            <tr key={app.id}>
              <td>{app.name}</td>
              <td>{app.type}</td>
              <td>
                <button onClick={() => handleApprove(app.id)} className="bg-green-500 text-white px-2 py-1 rounded">Approve</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
