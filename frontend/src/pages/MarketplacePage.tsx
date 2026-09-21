import { useState, useEffect } from 'react';
import { productService } from '../api/productService';
import { ProductList } from '../features/marketplace/components/ProductList';
import { ProductFilter } from '../features/marketplace/components/ProductFilter';
import toast from 'react-hot-toast';

export const MarketplacePage = () => {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  useEffect(() => {
    const fetchProducts = async () => {
      try {
        const response = await productService.fetchProducts({ search });
        setProducts(response.data);
      } catch (error) {
        toast.error('Failed to fetch products');
      } finally {
        setLoading(false);
      }
    };
    fetchProducts();
  }, [search]);

  return (
    <div>
      <h2 className="text-2xl font-bold mb-4">Marketplace</h2>
      <ProductFilter search={search} setSearch={setSearch} />
      {loading ? <p>Loading products...</p> : <ProductList products={products} />}
    </div>
  );
};
