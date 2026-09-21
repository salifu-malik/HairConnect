import { Link } from 'react-router-dom';
import { useCartStore } from '../../../stores/cartStore';

export const ProductCard = ({ product }: { product: any }) => {
  const addToCart = useCartStore((state) => state.addToCart);

  return (
    <div className="bg-white p-4 rounded shadow-md border hover:shadow-lg transition">
      <img src={product.image || '/default-product.png'} alt={product.name} className="w-full h-40 object-cover rounded mb-2" />
      <h3 className="text-lg font-semibold">{product.name}</h3>
      <p className="text-gray-600">{product.price} GHS</p>
      <div className="flex space-x-2 mt-2">
        <Link to={`/marketplace/${product.id}`} className="flex-1 text-center bg-gray-200 p-2 rounded">Details</Link>
        <button onClick={() => addToCart(product)} className="flex-1 bg-blue-500 text-white p-2 rounded">Add to Cart</button>
      </div>
    </div>
  );
};
