import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Scissors,
  ShoppingCart,
  Calendar,
  Sun,
  Moon,
} from 'lucide-react';

import { useThemeStore } from '../stores/themeStore';
import { LoginModal } from '../components/auth/LoginModal';
import { RegisterModal } from '../components/auth/RegisterModal';

type AuthModal = 'login' | 'register' | null;

export const HomePage = () => {
  const { theme, toggleTheme } = useThemeStore();

  const [authModal, setAuthModal] = useState<AuthModal>(null);

  return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex flex-col transition-colors duration-300">

        {/* Navigation */}
        <nav className="m-4 mt-6">
          <div className="container mx-auto bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm rounded-full py-3 px-6 flex justify-between items-center shadow-md border border-gray-200 dark:border-gray-800 transition-colors duration-300">

            {/* Logo */}
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-bold text-black dark:text-white">
                HairConnect
              </h1>
            </div>

            {/* Navigation Links */}
            <div className="hidden md:flex space-x-6 text-gray-700 dark:text-gray-300 font-medium">
              <Link
                  to="/"
                  className="hover:text-black dark:hover:text-white transition-colors"
              >
                Home
              </Link>

              <Link
                  to="/about"
                  className="hover:text-black dark:hover:text-white transition-colors"
              >
                About
              </Link>

              <Link
                  to="/marketplace"
                  className="hover:text-black dark:hover:text-white transition-colors"
              >
                Products
              </Link>

              <Link
                  to="/blog"
                  className="hover:text-black dark:hover:text-white transition-colors"
              >
                Blog
              </Link>

              <Link
                  to="/contact"
                  className="hover:text-black dark:hover:text-white transition-colors"
              >
                Contact
              </Link>
            </div>

            {/* Right Side */}
            <div className="flex items-center gap-4">

              {/* Theme Toggle */}
              <button
                  type="button"
                  onClick={toggleTheme}
                  className="p-2 rounded-full text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                  aria-label={
                    theme === 'light'
                        ? 'Switch to dark mode'
                        : 'Switch to light mode'
                  }
                  title={
                    theme === 'light'
                        ? 'Switch to dark mode'
                        : 'Switch to light mode'
                  }
              >
                {theme === 'light' ? (
                    <Moon size={20} />
                ) : (
                    <Sun size={20} />
                )}
              </button>

              {/* Login */}
              <button
                  type="button"
                  onClick={() => setAuthModal('login')}
                  className="text-gray-700 dark:text-gray-300 font-medium hover:text-black dark:hover:text-white transition-colors"
              >
                Login
              </button>

              {/* Register */}
              <button
                  type="button"
                  onClick={() => setAuthModal('register')}
                  className="bg-black dark:bg-white text-white dark:text-black px-5 py-2 rounded-full hover:bg-gray-800 dark:hover:bg-gray-200 transition-colors"
              >
                Register
              </button>

            </div>
          </div>
        </nav>

        {/* Main Content */}
        <main className="flex-grow container mx-auto p-6 text-center">

          {/* Hero Heading */}
          <h2 className="text-4xl font-bold text-gray-900 dark:text-white mb-6 transition-colors">
            Welcome to HairConnect
          </h2>

          <p className="text-lg text-gray-600 dark:text-gray-400 mb-10 transition-colors">
            Your all-in-one platform for grooming services and marketplace
            products in Ghana.
          </p>

          {/* Feature Cards */}
          <div className="grid md:grid-cols-3 gap-8">

            {/* Appointments */}
            <div className="bg-white dark:bg-gray-900 p-6 rounded-lg shadow-md dark:shadow-gray-950/30 border border-transparent dark:border-gray-800 transition-colors duration-300">

              <Scissors
                  className="w-12 h-12 text-black dark:text-white mx-auto mb-4"
              />

              <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                Book Appointments
              </h3>

              <p className="text-gray-600 dark:text-gray-400">
                Find top-rated barbers and book your spot instantly.
              </p>

            </div>

            {/* Marketplace */}
            <div className="bg-white dark:bg-gray-900 p-6 rounded-lg shadow-md dark:shadow-gray-950/30 border border-transparent dark:border-gray-800 transition-colors duration-300">

              <ShoppingCart
                  className="w-12 h-12 text-black dark:text-white mx-auto mb-4"
              />

              <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                Marketplace
              </h3>

              <p className="text-gray-600 dark:text-gray-400">
                Browse and order premium grooming products.
              </p>

            </div>

            {/* Queue */}
            <div className="bg-white dark:bg-gray-900 p-6 rounded-lg shadow-md dark:shadow-gray-950/30 border border-transparent dark:border-gray-800 transition-colors duration-300">

              <Calendar
                  className="w-12 h-12 text-black dark:text-white mx-auto mb-4"
              />

              <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">
                Manage Queue
              </h3>

              <p className="text-gray-600 dark:text-gray-400">
                Join the queue and track your waiting time.
              </p>

            </div>

          </div>
        </main>

        {/* Login Modal */}
        {authModal === 'login' && (
            <LoginModal
                onClose={() => setAuthModal(null)}
                onRegister={() => setAuthModal('register')}
            />
        )}

        {/* Register Modal */}
        {authModal === 'register' && (
            <RegisterModal
                onClose={() => setAuthModal(null)}
                onLogin={() => setAuthModal('login')}
            />
        )}

      </div>
  );
};