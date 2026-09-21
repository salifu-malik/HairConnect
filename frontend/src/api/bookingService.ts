import api from './axios';

export const bookingService = {
  fetchServices: async (barberId: string) => {
    return api.get(`/barbers/${barberId}/services`);
  },
  createBooking: async (bookingData: { barberId: string; serviceId: string; date: string; time: string }) => {
    return api.post('/bookings', bookingData);
  },
};
