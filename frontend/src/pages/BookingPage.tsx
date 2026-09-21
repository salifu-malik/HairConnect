import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { bookingService } from '../api/bookingService';
import { BookingForm } from '../features/booking/components/BookingForm';
import toast from 'react-hot-toast';

export const BookingPage = () => {
  const { barberId } = useParams<{ barberId: string }>();
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchServices = async () => {
      try {
        const response = await bookingService.fetchServices(barberId!);
        setServices(response.data);
      } catch (error) {
        toast.error('Failed to fetch services');
      } finally {
        setLoading(false);
      }
    };

    fetchServices();
  }, [barberId]);

  if (loading) {
    return (
        <div
            className="
          min-h-[300px]
          flex
          items-center
          justify-center
          text-gray-600
          dark:text-gray-400
          transition-colors
          duration-300
        "
        >
          Loading services...
        </div>
    );
  }

  return (
      <div
          className="
        max-w-md
        mx-auto
        text-gray-900
        dark:text-gray-100
        transition-colors
        duration-300
      "
      >
        <h2
            className="
          text-2xl
          font-bold
          mb-4
          text-gray-900
          dark:text-white
        "
        >
          Book Appointment
        </h2>

        <BookingForm
            barberId={barberId!}
            services={services}
        />
      </div>
  );
};