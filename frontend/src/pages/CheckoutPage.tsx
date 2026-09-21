import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { useCartStore } from '../stores/cartStore';
import { orderService } from '../api/orderService';
import toast from 'react-hot-toast';

export const CheckoutPage = () => {
  const { register, handleSubmit } = useForm();
  const { items, clearCart } = useCartStore();
  const navigate = useNavigate();

  const onSubmit = async (data: any) => {
    try {
      await orderService.createOrder({ ...data, items });
      clearCart();
      toast.success('Order placed successfully!');
      navigate('/dashboard');
    } catch (error) {
      toast.error('Failed to place order.');
    }
  };

  return (
    <div className="max-w-2xl mx-auto p-4 bg-white shadow-md rounded">
      <h2 className="text-2xl font-bold mb-4">Checkout</h2>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <input {...register('address')} placeholder="Shipping Address" className="w-full border p-2 rounded" required />
        <input {...register('phone')} placeholder="Phone Number" className="w-full border p-2 rounded" required />
        <button type="submit" className="w-full bg-blue-500 text-white p-2 rounded">Place Order</button>
      </form>
    </div>
  );
};
