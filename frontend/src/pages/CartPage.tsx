import { Link } from 'react-router-dom';
import { useCartStore } from '../stores/cartStore';

export const CartPage = () => {
  const {
    items,
    removeFromCart,
    updateQuantity,
  } = useCartStore();

  const total = items.reduce(
      (sum, item) => sum + item.price * item.quantity,
      0
  );

  return (
      <div
          className="
        max-w-4xl
        mx-auto
        p-4
        bg-white
        dark:bg-gray-900
        shadow-md
        dark:shadow-black/30
        rounded-xl
        border
        border-gray-100
        dark:border-gray-800
        text-gray-900
        dark:text-gray-100
        transition-colors
        duration-300
      "
      >
        {/* Heading */}
        <h2
            className="
          text-2xl
          font-bold
          mb-4
          text-gray-900
          dark:text-white
        "
        >
          Shopping Cart
        </h2>

        {/* Empty Cart */}
        {items.length === 0 ? (
            <div
                className="
            py-10
            text-center
            text-gray-500
            dark:text-gray-400
          "
            >
              Your cart is empty.
            </div>
        ) : (
            <>
              {/* Cart Items */}
              <div className="space-y-4 mb-4">
                {items.map((item) => (
                    <div
                        key={item.id}
                        className="
                  flex
                  justify-between
                  items-center
                  gap-4
                  border-b
                  border-gray-200
                  dark:border-gray-800
                  pb-4
                  transition-colors
                "
                    >
                      {/* Product Information */}
                      <div className="min-w-0">
                        <h3
                            className="
                      font-semibold
                      text-gray-900
                      dark:text-white
                    "
                        >
                          {item.name}
                        </h3>

                        <p
                            className="
                      mt-1
                      text-gray-600
                      dark:text-gray-400
                    "
                        >
                          {item.price} GHS
                        </p>
                      </div>

                      {/* Quantity + Remove */}
                      <div className="flex items-center gap-2 flex-shrink-0">
                        <input
                            type="number"
                            value={item.quantity}
                            onChange={(e) =>
                                updateQuantity(
                                    item.id,
                                    parseInt(e.target.value)
                                )
                            }
                            className="
                      w-16
                      border
                      border-gray-300
                      dark:border-gray-700
                      bg-white
                      dark:bg-gray-800
                      text-gray-900
                      dark:text-white
                      p-1.5
                      rounded-lg
                      outline-none
                      focus:ring-2
                      focus:ring-gray-300
                      dark:focus:ring-gray-600
                      transition-colors
                    "
                            min="1"
                        />

                        <button
                            type="button"
                            onClick={() => removeFromCart(item.id)}
                            className="
                      text-red-500
                      dark:text-red-400
                      hover:text-red-700
                      dark:hover:text-red-300
                      text-sm
                      font-medium
                      transition-colors
                    "
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                ))}
              </div>

              {/* Cart Summary */}
              <div
                  className="
              text-right
              pt-2
            "
              >
                <p
                    className="
                text-xl
                font-bold
                text-gray-900
                dark:text-white
              "
                >
                  Total: {total} GHS
                </p>

                <Link
                    to="/checkout"
                    className="
                inline-block
                mt-4
                bg-black
                dark:bg-white
                text-white
                dark:text-black
                px-5
                py-2.5
                rounded-lg
                font-medium
                hover:bg-gray-800
                dark:hover:bg-gray-200
                transition-colors
              "
                >
                  Proceed to Checkout
                </Link>
              </div>
            </>
        )}
      </div>
  );
};