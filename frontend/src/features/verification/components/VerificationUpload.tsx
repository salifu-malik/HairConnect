import { useState, useEffect } from 'react';
import { verificationService } from '../../../api/verificationService';
import { toast } from 'react-hot-toast';
import { AlertCircle, CheckCircle } from 'lucide-react';

export const VerificationUpload = () => {
  const [loading, setLoading] = useState(false);
  const [eligibility, setEligibility] = useState<{ eligible: boolean; message?: string } | null>(null);

  useEffect(() => {
    verificationService.checkEligibility()
      .then(setEligibility)
      .catch(() => toast.error('Failed to check verification eligibility'));
  }, []);

  const handleUpload = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!eligibility?.eligible) {
      toast.error(eligibility?.message || 'Not eligible for verification.');
      return;
    }

    const formData = new FormData(e.currentTarget);
    setLoading(true);
    try {
      await verificationService.submitDocuments(formData);
      toast.success('Documents submitted for verification.');
    } catch {
      toast.error('Failed to submit documents.');
    } finally {
      setLoading(false);
    }
  };

  if (!eligibility) return <div>Checking eligibility...</div>;

  if (!eligibility.eligible) {
    return (
      <div className="p-4 bg-yellow-50 text-yellow-700 rounded flex items-center gap-2">
        <AlertCircle size={20} />
        <p>{eligibility.message || 'Verification is available 2 days after account registration.'}</p>
      </div>
    );
  }

  return (
    <form onSubmit={handleUpload} className="space-y-4 p-6 bg-white rounded border border-gray-100">
      <h3 className="text-lg font-bold">Verification Documents</h3>
      <p className="text-sm text-gray-500">Upload your ID and Business Certificate to get verified.</p>
      <input type="file" name="idFront" accept="image/*" required className="block w-full border p-2 rounded" />
      <input type="file" name="idBack" accept="image/*" required className="block w-full border p-2 rounded" />
      <input type="file" name="holdingId" accept="image/*" required className="block w-full border p-2 rounded" />
      <input type="file" name="businessCertificate" accept="image/*" required className="block w-full border p-2 rounded" />
      <button disabled={loading} className="bg-blue-600 text-white px-4 py-2 rounded">
        {loading ? 'Submitting...' : 'Submit for Verification'}
      </button>
    </form>
  );
};
