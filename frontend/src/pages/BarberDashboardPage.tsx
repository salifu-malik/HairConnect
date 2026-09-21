
import { useBarberDashboard } from '../features/barber/hooks/useBarberDashboard';
import { AppointmentsList } from '../features/barber/components/AppointmentsList';

export const BarberDashboardPage = () => {
  const { stats, loading } = useBarberDashboard();

  if (loading) {
    return (
      <div className="p-6 text-gray-700 dark:text-gray-300">
        Loading...
      </div>
    );
  }

  return (
    <div className="p-6 text-gray-900 dark:text-gray-100 transition-colors duration-300">

      {/* Page Header */}
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
          Barber Dashboard
        </h2>

        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Overview of your bookings, earnings, and customers.
        </p>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">

        {/* Today's Bookings */}
        <div
          className="
            bg-white dark:bg-gray-900
            p-6 rounded-lg
            shadow dark:shadow-black/20
            border border-gray-100 dark:border-gray-800
            transition-colors duration-300
          "
        >
          <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
            Today's Bookings
          </h3>

          <p className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
            {stats?.bookingsCount || 0}
          </p>
        </div>

        {/* Earnings */}
        <div
          className="
            bg-white dark:bg-gray-900
            p-6 rounded-lg
            shadow dark:shadow-black/20
            border border-gray-100 dark:border-gray-800
            transition-colors duration-300
          "
        >
          <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
            Earnings
          </h3>

          <p className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
            GHS {stats?.earnings || 0}
          </p>
        </div>

        {/* Customers Served */}
        <div
          className="
            bg-white dark:bg-gray-900
            p-6 rounded-lg
            shadow dark:shadow-black/20
            border border-gray-100 dark:border-gray-800
            transition-colors duration-300
          "
        >
          <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300">
            Customers Served
          </h3>

          <p className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
            {stats?.customersCount || 0}
          </p>
        </div>

      </div>

      {/* Appointments */}
      <div className="mt-8">
        <AppointmentsList />
      </div>

    </div>
  );
};

