import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Calendar,
  Clock,
  ShoppingBag,
  Plus,
} from 'lucide-react';
import { bookingService } from '../api/bookingService';
import { queueService } from '../api/queueService';
import { orderService } from '../api/orderService';
import { toast } from 'react-hot-toast';

export const CustomerDashboardPage = () => {
  const [stats, setStats] = useState({
    upcomingAppointments: 0,
    queuePosition: 'N/A',
    pendingOrders: 0,
  });

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [appointments, queue, orders] = await Promise.all([
          bookingService.getUpcomingAppointments(),
          queueService.getActiveQueuePosition(),
          orderService.getRecentOrders(),
        ]);

        setStats({
          upcomingAppointments: appointments.length || 0,
          queuePosition: queue.position || 'Not in queue',
          pendingOrders:
              orders.filter(
                  (o: any) => o.status === 'PENDING'
              ).length || 0,
        });
      } catch {
        toast.error('Failed to load dashboard data');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) {
    return (
        <div
            className="
          p-6
          text-gray-600
          dark:text-gray-400
          transition-colors
          duration-300
        "
        >
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
          <h2
              className="
            text-2xl
            font-bold
            text-gray-900
            dark:text-white
          "
          >
            Customer Dashboard
          </h2>

          <p
              className="
            mt-1
            text-gray-500
            dark:text-gray-400
          "
          >
            Manage your appointments, queue position and orders.
          </p>
        </div>

        {/* Statistics */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

          {/* Upcoming Appointments */}
          <div
              className="
            bg-white
            dark:bg-gray-900
            p-6
            rounded-xl
            shadow-sm
            dark:shadow-black/20
            border
            border-gray-100
            dark:border-gray-800
            flex
            items-center
            gap-4
            transition-colors
            duration-300
          "
          >
            <div
                className="
              p-3
              bg-gray-100
              dark:bg-gray-800
              rounded-full
              text-black
              dark:text-white
            "
            >
              <Calendar size={24} />
            </div>

            <div>
              <h3
                  className="
                text-sm
                text-gray-500
                dark:text-gray-400
              "
              >
                Upcoming Appointments
              </h3>

              <p
                  className="
                text-2xl
                font-bold
                text-gray-900
                dark:text-white
              "
              >
                {stats.upcomingAppointments}
              </p>
            </div>
          </div>

          {/* Queue Position */}
          <div
              className="
            bg-white
            dark:bg-gray-900
            p-6
            rounded-xl
            shadow-sm
            dark:shadow-black/20
            border
            border-gray-100
            dark:border-gray-800
            flex
            items-center
            gap-4
            transition-colors
            duration-300
          "
          >
            <div
                className="
              p-3
              bg-green-100
              dark:bg-green-900/30
              rounded-full
              text-green-600
              dark:text-green-400
            "
            >
              <Clock size={24} />
            </div>

            <div>
              <h3
                  className="
                text-sm
                text-gray-500
                dark:text-gray-400
              "
              >
                Queue Position
              </h3>

              <p
                  className="
                text-2xl
                font-bold
                text-gray-900
                dark:text-white
              "
              >
                {stats.queuePosition}
              </p>
            </div>
          </div>

          {/* Pending Orders */}
          <div
              className="
            bg-white
            dark:bg-gray-900
            p-6
            rounded-xl
            shadow-sm
            dark:shadow-black/20
            border
            border-gray-100
            dark:border-gray-800
            flex
            items-center
            gap-4
            transition-colors
            duration-300
          "
          >
            <div
                className="
              p-3
              bg-orange-100
              dark:bg-orange-900/30
              rounded-full
              text-orange-600
              dark:text-orange-400
            "
            >
              <ShoppingBag size={24} />
            </div>

            <div>
              <h3
                  className="
                text-sm
                text-gray-500
                dark:text-gray-400
              "
              >
                Pending Orders
              </h3>

              <p
                  className="
                text-2xl
                font-bold
                text-gray-900
                dark:text-white
              "
              >
                {stats.pendingOrders}
              </p>
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="flex flex-wrap gap-4">

          {/* Book Now */}
          <Link
              to="/barbers"
              className="
            bg-black
            dark:bg-white
            text-white
            dark:text-black
            px-6
            py-2.5
            rounded-lg
            flex
            items-center
            gap-2
            font-medium
            hover:bg-gray-800
            dark:hover:bg-gray-200
            transition-colors
          "
          >
            <Plus size={18} />
            Book Now
          </Link>

          {/* Marketplace */}
          <Link
              to="/marketplace"
              className="
            bg-white
            dark:bg-gray-900
            text-black
            dark:text-white
            border
            border-gray-300
            dark:border-gray-700
            px-6
            py-2.5
            rounded-lg
            font-medium
            hover:bg-gray-100
            dark:hover:bg-gray-800
            transition-colors
          "
          >
            Browse Marketplace
          </Link>
        </div>
      </div>
  );
};