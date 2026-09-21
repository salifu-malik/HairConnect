import { useState, useEffect } from 'react';
import { BarberManagementTable } from '../features/shop/components/BarberManagementTable';
import { ServiceManagementTable } from '../features/shop/components/ServiceManagementTable';
import { shopService } from '../api/shopService';
import { toast } from 'react-hot-toast';

export const ShopOwnerDashboardPage = () => {
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    shopService.fetchDashboardStats()
      .then(setStats)
      .catch(() => toast.error('Failed to load dashboard stats'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-950 p-6 transition-colors duration-300">
        <div className="flex items-center justify-center min-h-[300px]">
          <p className="text-gray-600 dark:text-gray-300">
            Loading...
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-950 p-6 transition-colors duration-300">

      {/* Page Header */}
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
          Shop Owner Dashboard
        </h2>

        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Manage your shop, barbers, services and business activities.
        </p>
      </div>

      {/* Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

        {/* Revenue */}
        <div className="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 transition-colors duration-300">
          <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
            Total Revenue
          </h3>

          <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            GHS {stats?.revenue || 0}
          </p>
        </div>

        {/* Bookings */}
        <div className="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 transition-colors duration-300">
          <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
            Total Bookings
          </h3>

          <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            {stats?.bookingsCount || 0}
          </p>
        </div>

        {/* Barbers */}
        <div className="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 transition-colors duration-300">
          <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
            Active Barbers
          </h3>

          <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            {stats?.barbersCount || 0}
          </p>
        </div>

      </div>

      {/* Management Sections */}
      <div className="space-y-6">

        {/* Barber Management */}
        <div className="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 transition-colors duration-300">

          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Barber Management
          </h3>

          <BarberManagementTable />

        </div>

        {/* Service Management */}
        <div className="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 transition-colors duration-300">

          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Service Management
          </h3>

          <ServiceManagementTable />

        </div>

      </div>

    </div>
  );
};
