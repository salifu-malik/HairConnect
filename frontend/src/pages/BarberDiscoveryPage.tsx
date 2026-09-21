import { useState, useEffect } from 'react';
import { barberService } from '../api/barberService';
import { BarberCard } from '../features/barber/components/BarberCard';
import toast from 'react-hot-toast';

export const BarberDiscoveryPage = () => {
  const [barbers, setBarbers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  useEffect(() => {
    const fetchBarbers = async () => {
      try {
        const response = await barberService.fetchBarbers({ search });
        setBarbers(response.data);
      } catch (error) {
        toast.error('Failed to fetch barbers');
      } finally {
        setLoading(false);
      }
    };

    fetchBarbers();
  }, [search]);

  return (
      <div
          className="
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
            Discover Barbers
          </h2>

          <p
              className="
            mt-1
            text-gray-500
            dark:text-gray-400
          "
          >
            Find a barber or shop that matches your needs.
          </p>
        </div>

        {/* Search */}
        <div className="mb-6">
          <input
              type="text"
              placeholder="Search by shop or barber name..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="
            w-full
            p-3
            border
            border-gray-300
            dark:border-gray-700
            rounded-lg
            bg-white
            dark:bg-gray-900
            text-gray-900
            dark:text-white
            placeholder-gray-400
            dark:placeholder-gray-500
            outline-none
            focus:ring-2
            focus:ring-gray-300
            dark:focus:ring-gray-600
            transition-colors
            duration-300
          "
          />
        </div>

        {/* Loading */}
        {loading ? (
            <div
                className="
            flex
            items-center
            justify-center
            py-12
            text-gray-500
            dark:text-gray-400
          "
            >
              Loading...
            </div>
        ) : (
            <>
              {barbers.length > 0 ? (
                  <div
                      className="
                grid
                grid-cols-1
                md:grid-cols-3
                lg:grid-cols-4
                gap-4
              "
                  >
                    {barbers.map((barber: any) => (
                        <BarberCard
                            key={barber.id}
                            barber={barber}
                        />
                    ))}
                  </div>
              ) : (
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
                      No barbers found.
                    </p>

                    <p
                        className="
                  mt-1
                  text-sm
                  text-gray-500
                  dark:text-gray-400
                "
                    >
                      Try searching with a different barber or shop name.
                    </p>
                  </div>
              )}
            </>
        )}
      </div>
  );
};