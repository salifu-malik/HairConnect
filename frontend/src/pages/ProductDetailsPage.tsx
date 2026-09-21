import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { productService } from '../api/productService';
import { useCartStore } from '../stores/cartStore';
import { reviewService } from '../api/reviewService';
import { ReviewForm } from '../features/reviews/components/ReviewForm';
import { ReviewList } from '../features/reviews/components/ReviewList';
import toast from 'react-hot-toast';

export const ProductDetailsPage = () => {
  const { productId } = useParams<{ productId: string }>();
  const [product, setProduct] = useState<any>(null);
  const [reviews, setReviews] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const addToCart = useCartStore((state) => state.addToCart);

  const fetchProductData = async () => {
    try {
      const [productResponse, reviewsResponse] = await Promise.all([
        productService.fetchProductById(productId!),
        reviewService.fetchReviews('PRODUCT', productId!)
      ]);
      setProduct(productResponse.data);
      setReviews(reviewsResponse);
    } catch (error) {
      toast.error('Failed to fetch product details');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProductData();
  }, [productId]);

  if (loading) return <p>Loading product details...</p>;
  if (!product) return <p>Product not found.</p>;

  return (
    <div className="max-w-2xl mx-auto p-4 bg-white shadow-md rounded">
      <img src={product.image || '/default-product.png'} alt={product.name} className="w-full h-64 object-cover rounded mb-4" />
      <h2 className="text-3xl font-bold mb-2">{product.name}</h2>
      <p className="text-gray-700 mb-4">{product.description}</p>
      <p className="text-xl font-semibold mb-4">{product.price} GHS</p>
      <button onClick={() => addToCart(product)} className="w-full bg-blue-500 text-white p-2 rounded mb-6">Add to Cart</button>
      
      <ReviewList reviews={reviews} />
      <ReviewForm targetType="PRODUCT" targetId={productId!} onReviewSubmitted={fetchProductData} />
    </div>
  );
};
