import { useState, useEffect } from 'react';
import { adminService } from '../../../api/adminService';
import { toast } from 'react-hot-toast';
import { Settings, Save } from 'lucide-react';

export const AdminSettings = () => {
  const [settings, setSettings] = useState({
    commissionRate: 0,
    platformName: '',
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    adminService.getSettings()
      .then(setSettings)
      .catch(() => toast.error('Failed to load settings'))
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await adminService.updateSettings(settings);
      toast.success('Settings updated successfully');
    } catch {
      toast.error('Failed to update settings');
    }
  };

  if (loading) return <div>Loading settings...</div>;

  return (
    <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100 mt-6">
      <h3 className="text-xl font-semibold mb-6 flex items-center gap-2">
        <Settings className="text-black" />
        Platform Settings
      </h3>
      <form onSubmit={handleSave} className="space-y-4">
        <div>
          <label className="block text-sm font-medium mb-1">Platform Name</label>
          <input 
            value={settings.platformName} 
            onChange={(e) => setSettings({ ...settings, platformName: e.target.value })}
            className="border p-2 rounded w-full"
          />
        </div>
        <div>
          <label className="block text-sm font-medium mb-1">Commission Rate (%)</label>
          <input 
            type="number"
            value={settings.commissionRate} 
            onChange={(e) => setSettings({ ...settings, commissionRate: Number(e.target.value) })}
            className="border p-2 rounded w-full"
          />
        </div>
        <button type="submit" className="flex items-center gap-2 bg-black text-white px-6 py-2 rounded hover:bg-gray-800">
          <Save size={18} />
          Save Settings
        </button>
      </form>
    </div>
  );
};
