import { useBarberAppointments } from '../hooks/useBarberAppointments';

export const AppointmentsList = () => {
  const { appointments, loading, updateStatus } = useBarberAppointments();

  if (loading) {
    return (
        <div className="mt-6 text-gray-700 dark:text-gray-300">
          Loading...
        </div>
    );
  }

  return (
      <div
          className="
        bg-white dark:bg-gray-900
        p-6 rounded-lg
        shadow dark:shadow-black/20
        border border-gray-100 dark:border-gray-800
        mt-6
        transition-colors duration-300
      "
      >
        {/* Header */}
        <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
          Appointments
        </h3>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
            <tr className="border-b border-gray-200 dark:border-gray-800">
              <th className="text-left py-3 text-gray-700 dark:text-gray-300 font-semibold">
                Customer
              </th>

              <th className="text-left py-3 text-gray-700 dark:text-gray-300 font-semibold">
                Date/Time
              </th>

              <th className="text-left py-3 text-gray-700 dark:text-gray-300 font-semibold">
                Status
              </th>

              <th className="text-left py-3 text-gray-700 dark:text-gray-300 font-semibold">
                Actions
              </th>
            </tr>
            </thead>

            <tbody>
            {appointments.map((app: any) => (
                <tr
                    key={app.id}
                    className="
                  border-b border-gray-100 dark:border-gray-800
                  last:border-b-0
                  hover:bg-gray-50 dark:hover:bg-gray-800/50
                  transition-colors
                "
                >
                  {/* Customer */}
                  <td className="py-4 text-gray-900 dark:text-gray-100">
                    {app.customerName}
                  </td>

                  {/* Date/Time */}
                  <td className="py-4 text-gray-700 dark:text-gray-300">
                    {app.dateTime}
                  </td>

                  {/* Status */}
                  <td className="py-4">
                  <span
                      className={`
                      inline-flex
                      px-3 py-1
                      rounded-full
                      text-xs
                      font-semibold

                      ${
                          app.status === 'PENDING'
                              ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                              : app.status === 'ACCEPTED'
                                  ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                  : app.status === 'REJECTED'
                                      ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                                      : app.status === 'COMPLETED'
                                          ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'
                                          : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
                      }
                    `}
                  >
                    {app.status}
                  </span>
                  </td>

                  {/* Actions */}
                  <td className="py-4">
                    <div className="flex flex-wrap gap-2">

                      {/* Accept */}
                      {app.status === 'PENDING' && (
                          <button
                              type="button"
                              onClick={() =>
                                  updateStatus(app.id, 'ACCEPTED')
                              }
                              className="
                          bg-green-500
                          hover:bg-green-600
                          text-white
                          px-3 py-1.5
                          rounded-md
                          text-sm
                          font-medium
                          transition-colors
                        "
                          >
                            Accept
                          </button>
                      )}

                      {/* Reject */}
                      {app.status === 'PENDING' && (
                          <button
                              type="button"
                              onClick={() =>
                                  updateStatus(app.id, 'REJECTED')
                              }
                              className="
                          bg-red-500
                          hover:bg-red-600
                          text-white
                          px-3 py-1.5
                          rounded-md
                          text-sm
                          font-medium
                          transition-colors
                        "
                          >
                            Reject
                          </button>
                      )}

                      {/* Complete */}
                      {app.status === 'ACCEPTED' && (
                          <button
                              type="button"
                              onClick={() =>
                                  updateStatus(app.id, 'COMPLETED')
                              }
                              className="
                          bg-blue-500
                          hover:bg-blue-600
                          text-white
                          px-3 py-1.5
                          rounded-md
                          text-sm
                          font-medium
                          transition-colors
                        "
                          >
                            Complete
                          </button>
                      )}

                    </div>
                  </td>
                </tr>
            ))}
            </tbody>
          </table>
        </div>

        {/* Empty State */}
        {appointments.length === 0 && (
            <div className="py-8 text-center text-gray-500 dark:text-gray-400">
              No appointments found.
            </div>
        )}
      </div>
  );
};