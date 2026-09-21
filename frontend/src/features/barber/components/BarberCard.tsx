import { Link } from 'react-router-dom';

export const BarberCard = ({ barber }: { barber: any }) => {
  return (
    <div className="bg-white p-4 rounded shadow-md border hover:shadow-lg transition">
      <img src={barber.profileImage || '/default-barber.png'} alt={barber.name} className="w-full h-40 object-cover rounded mb-2" />
      <h3 className="text-lg font-semibold">{barber.name}</h3>
      <p className="text-gray-600">{barber.shopName}</p>
      <p className="text-sm">Rating: {barber.rating} ⭐</p>
      <p className="text-sm">Location: {barber.location}</p>
      <Link to={`/barbers/${barber.id}/book`} className="mt-2 block text-center bg-blue-500 text-white p-2 rounded">
        Book Appointment
      </Link>
    </div>
  );
};
