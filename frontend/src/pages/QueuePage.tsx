import { useState, useEffect } from 'react';
import { queueService } from '../api/queueService';
import { toast } from 'react-hot-toast';

export const QueuePage = () => {
  const [status, setStatus] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    queueService
        .fetchQueueStatus()
        .then(setStatus)
        .catch(() => toast.error('Failed to load queue status'))
        .finally(() => setLoading(false));
  }, []);

  const handleJoin = async (shopId: string) => {
    try {
      await queueService.joinQueue(shopId);

      toast.success('Joined queue');

      // Refresh status
      const updatedStatus =
          await queueService.fetchQueueStatus();

      setStatus(updatedStatus);
    } catch {
      toast.error('Failed to join queue');
    }
  };

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
        bg-white
        dark:bg-gray-900
        rounded-xl
        shadow
        dark:shadow-black/30
        border
        border-gray-100
        dark:border-gray-800
        text-gray-900
        dark:text-gray-100
        transition-colors
        duration-300
      "
      >
        {/* Header */}
        <h2
            className="
          text-2xl
          font-bold
          mb-4
          text-gray-900
          dark:text-white
        "
        >
          Queue Management
        </h2>

        {status?.inQueue ? (
            <div className="space-y-3">
              {/* Shop */}
              <div>
                <p
                    className="
                text-sm
                text-gray-500
                dark:text-gray-400
              "
                >
                  Current Queue
                </p>

                <p
                    className="
                text-lg
                font-semibold
                text-gray-900
                dark:text-white
              "
                >
                  {status.shopName}
                </p>
              </div>

              {/* Queue Position */}
              <div
                  className="
              flex
              items-center
              justify-between
              py-3
              border-y
              border-gray-200
              dark:border-gray-800
            "
              >
            <span
                className="
                text-gray-600
                dark:text-gray-400
              "
            >
              Position
            </span>

                <span
                    className="
                text-xl
                font-bold
                text-gray-900
                dark:text-white
              "
                >
              #{status.position}
            </span>
              </div>

              {/* Estimated Wait */}
              <div
                  className="
              flex
              items-center
              justify-between
            "
              >
            <span
                className="
                text-gray-600
                dark:text-gray-400
              "
            >
              Estimated wait
            </span>

                <span
                    className="
                font-semibold
                text-gray-900
                dark:text-white
              "
                >
              {status.estimatedWait} minutes
            </span>
              </div>

              {/* Leave Queue */}
              <button
                  type="button"
                  onClick={() =>
                      queueService
                          .leaveQueue()
                          .then(() => window.location.reload())
                  }
                  className="
              mt-4
              bg-red-600
              dark:bg-red-500
              text-white
              px-4
              py-2
              rounded-lg
              font-medium
              hover:bg-red-700
              dark:hover:bg-red-600
              transition-colors
            "
              >
                Leave Queue
              </button>
            </div>
        ) : (
            <div
                className="
            py-8
            text-center
          "
            >
              <p
                  className="
              text-gray-600
              dark:text-gray-300
              font-medium
            "
              >
                You are not currently in any queue.
              </p>

              <p
                  className="
              mt-1
              text-sm
              text-gray-500
              dark:text-gray-400
            "
              >
                Join a barber's queue when you are ready for your appointment.
              </p>
            </div>
        )}
      </div>
  );
};