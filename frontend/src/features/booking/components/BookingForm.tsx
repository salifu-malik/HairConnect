import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { bookingService } from '../../../api/bookingService';
import toast from 'react-hot-toast';

const bookingSchema = z.object({
  serviceId: z.string().min(1, 'Please select a service'),
  date: z.string().min(1, 'Please select a date'),
  time: z.string().min(1, 'Please select a time'),
});

type BookingInputs = z.infer<typeof bookingSchema>;

export const BookingForm = ({ barberId, services }: { barberId: string; services: any[] }) => {
  const { register, handleSubmit, formState: { errors } } = useForm<BookingInputs>({
    resolver: zodResolver(bookingSchema),
  });

  const onSubmit = async (data: BookingInputs) => {
    try {
      await bookingService.createBooking({ ...data, barberId });
      toast.success('Booking successful!');
    } catch (error) {
      toast.error('Failed to create booking.');
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <label className="block">Service</label>
        <select {...register('serviceId')} className="w-full border p-2 rounded">
          <option value="">Select a service</option>
          {services.map((service) => (
            <option key={service.id} value={service.id}>{service.name} - {service.price} GHS</option>
          ))}
        </select>
        {errors.serviceId && <p className="text-red-500 text-sm">{errors.serviceId.message}</p>}
      </div>
      <div>
        <label className="block">Date</label>
        <input type="date" {...register('date')} className="w-full border p-2 rounded" />
        {errors.date && <p className="text-red-500 text-sm">{errors.date.message}</p>}
      </div>
      <div>
        <label className="block">Time</label>
        <input type="time" {...register('time')} className="w-full border p-2 rounded" />
        {errors.time && <p className="text-red-500 text-sm">{errors.time.message}</p>}
      </div>
      <button type="submit" className="bg-blue-500 text-white p-2 rounded w-full">Book Now</button>
    </form>
  );
};
