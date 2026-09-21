import { useEffect, useState } from 'react';
import { notificationService } from '../api/notificationService';
import toast from 'react-hot-toast';

export const NotificationsPage = () => {
    const [notifications, setNotifications] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        notificationService
            .fetchNotifications()
            .then(setNotifications)
            .catch(() => toast.error('Failed to load notifications'))
            .finally(() => setLoading(false));
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
            <h2
                className="
          text-2xl
          font-bold
          mb-6
          text-gray-900
          dark:text-white
        "
            >
                Notifications
            </h2>

            {/* Notifications */}
            {notifications.length > 0 ? (
                <div className="space-y-4">
                    {notifications.map((n) => (
                        <div
                            key={n.id}
                            className={`
                p-4
                rounded-xl
                border
                shadow-sm
                transition-colors
                duration-300
                ${
                                n.read
                                    ? `
                      bg-gray-100
                      dark:bg-gray-800
                      border-gray-200
                      dark:border-gray-700
                    `
                                    : `
                      bg-white
                      dark:bg-gray-900
                      border-gray-200
                      dark:border-gray-700
                    `
                            }
              `}
                        >
                            <p
                                className={`
                  ${
                                    n.read
                                        ? 'text-gray-600 dark:text-gray-400'
                                        : 'text-gray-900 dark:text-white font-medium'
                                }
                `}
                            >
                                {n.message}
                            </p>
                        </div>
                    ))}
                </div>
            ) : (
                /* Empty State */
                <div
                    className="
            bg-white
            dark:bg-gray-900
            border
            border-gray-200
            dark:border-gray-800
            rounded-xl
            p-10
            text-center
            transition-colors
            duration-300
          "
                >
                    <p
                        className="
              text-gray-600
              dark:text-gray-300
              font-medium
            "
                    >
                        No notifications yet.
                    </p>

                    <p
                        className="
              mt-1
              text-sm
              text-gray-500
              dark:text-gray-400
            "
                    >
                        You will see new notifications here.
                    </p>
                </div>
            )}
        </div>
    );
};