
import { useState } from 'react';
import { Outlet, Link, useNavigate } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import {
  LayoutDashboard,
  Scissors,
  ShoppingCart,
  LogOut,
  Wallet,
  Calendar,
  CreditCard,
  Menu,
  X,
  MessageSquare,
  Sun,
  Moon,
  User,
} from 'lucide-react';
import { Role } from '../types/auth';
import { NotificationBell } from '../components/notifications/NotificationBell';
import { getDashboardPath } from '../utils/dashboardRedirect';
import { useThemeStore } from '../stores/themeStore';

export const DashboardLayout = () => {
  const { logout, user } = useAuthStore();
  const { theme, toggleTheme } = useThemeStore();

  const navigate = useNavigate();

  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const navItems: {
    name: string;
    path: string;
    icon: any;
    allowedRoles: Role[];
  }[] = [
    {
      name: 'Dashboard',
      path: getDashboardPath(user?.roles ?? []),
      icon: LayoutDashboard,
      allowedRoles: [
        'CUSTOMER',
        'BARBER',
        'SHOP_OWNER',
        'VENDOR',
        'DELIVERY_PERSONNEL',
        'FINANCE_MANAGER',
        'ADMIN',
      ],
    },

    {
      name: 'Barbers',
      path: '/barbers',
      icon: Scissors,
      allowedRoles: ['CUSTOMER'],
    },

    {
      name: 'Bookings',
      path: '/bookings',
      icon: Calendar,
      allowedRoles: ['CUSTOMER', 'BARBER', 'SHOP_OWNER'],
    },

    {
      name: 'Marketplace',
      path: '/marketplace',
      icon: ShoppingCart,
      allowedRoles: ['CUSTOMER', 'VENDOR'],
    },

    {
      name: 'Cart',
      path: '/cart',
      icon: ShoppingCart,
      allowedRoles: ['CUSTOMER'],
    },

    {
      name: 'Subscriptions',
      path: '/subscriptions',
      icon: CreditCard,
      allowedRoles: ['BARBER', 'VENDOR'],
    },

    {
      name: 'Wallet',
      path: '/wallet',
      icon: Wallet,
      allowedRoles: [
        'CUSTOMER',
        'BARBER',
        'SHOP_OWNER',
        'VENDOR',
        'FINANCE_MANAGER',
      ],
    },

    {
      name: 'Profile',
      path: '/profile',
      icon: User,
      allowedRoles: [
        'CUSTOMER',
        'BARBER',
        'SHOP_OWNER',
        'VENDOR',
        'DELIVERY_PERSONNEL',
        'FINANCE_MANAGER',
        'ADMIN',
      ],
    },

    {
      name: 'Queue',
      path: '/queue',
      icon: Calendar,
      allowedRoles: ['CUSTOMER'],
    },

    {
      name: 'Messages',
      path: '/messages',
      icon: MessageSquare,
      allowedRoles: ['CUSTOMER', 'BARBER', 'VENDOR'],
    },
  ];

  const filteredNavItems = navItems.filter(
    (item) =>
      user &&
      user.roles?.some((role) =>
        item.allowedRoles.includes(role as Role)
      )
  );

  return (
    <div className="flex h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 transition-colors duration-300">

      {/* Mobile Sidebar Overlay */}
      {isSidebarOpen && (
        <div
          className="fixed inset-0 bg-gray-600/75 dark:bg-black/70 z-20 md:hidden"
          onClick={() => setIsSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`
fixed inset-y-0 left-0 z-30 w-64
bg-white dark:bg-gray-900
shadow-lg dark:shadow-black/30
transform transition-all duration-300
md:relative md:transform-none
rounded-r-3xl
border-r border-gray-100 dark:border-gray-800
${
  isSidebarOpen
      ? 'translate-x-0'
      : '-translate-x-full'
}
`}
      >

        {/* Sidebar Header */}
        <div
          className="
            p-6 text-xl font-bold
            border-b border-gray-100 dark:border-gray-800
            flex justify-between items-center
            text-gray-900 dark:text-white
          "
        >
          <span>HairConnect</span>

          <button
            type="button"
            className="
              md:hidden p-2 rounded-full
              text-gray-600 dark:text-gray-300
              hover:bg-gray-100 dark:hover:bg-gray-800
              transition-colors
            "
            onClick={() => setIsSidebarOpen(false)}
            aria-label="Close sidebar"
          >
            <X size={20} />
          </button>
        </div>

        {/* Navigation */}
        <nav className="p-4 space-y-2">

          {filteredNavItems.map((item) => (
            <Link
              key={item.name}
              to={item.path}
              className="
                flex items-center space-x-3 p-3 rounded-full
                text-gray-700 dark:text-gray-300
                hover:bg-gray-100 dark:hover:bg-gray-800
                hover:text-gray-900 dark:hover:text-white
                transition-colors
              "
              onClick={() => setIsSidebarOpen(false)}
            >
              <item.icon size={20} />

              <span className="font-medium">
                {item.name}
              </span>
            </Link>
          ))}

          {/* Logout */}
          <button
            type="button"
            onClick={handleLogout}
            className="
              flex items-center space-x-3 p-3 rounded-full
              hover:bg-red-50 dark:hover:bg-red-950/40
              transition-colors
              w-full
              text-red-600 dark:text-red-400
              font-medium
            "
          >
            <LogOut size={20} />

            <span>Logout</span>
          </button>
        </nav>
      </aside>

      {/* Main Content */}
      <main className="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-950 transition-colors duration-300">

        {/* Header */}
        <header
          className="
            bg-white dark:bg-gray-900
            shadow-sm dark:shadow-black/20
            border-b border-gray-100 dark:border-gray-800
            p-4
            flex justify-between items-center
            transition-colors duration-300
          "
        >

          {/* Left Side */}
          <div className="flex items-center space-x-4">

            {/* Mobile Menu */}
            <button
              type="button"
              className="
                md:hidden p-2 rounded-full
                text-gray-700 dark:text-gray-300
                hover:bg-gray-100 dark:hover:bg-gray-800
                transition-colors
              "
              onClick={() => setIsSidebarOpen(!isSidebarOpen)}
              aria-label="Toggle sidebar"
            >
              <Menu size={20} />
            </button>

            <h1 className="text-lg font-semibold text-gray-900 dark:text-white">
              Welcome,{' '}
              {user?.firstName
                ? `${user.firstName} ${user.lastName}`
                : 'User'}
            </h1>
          </div>

          {/* Right Side */}
          <div className="flex items-center gap-3">

            {/* Theme Toggle */}
            <button
              type="button"
              onClick={toggleTheme}
              className="
                p-2 rounded-full
                text-gray-700 dark:text-gray-300
                hover:bg-gray-100 dark:hover:bg-gray-800
                transition-colors
              "
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

            {/* Notifications */}
            <Link
              to="/notifications"
              className="
                p-2 rounded-full
                hover:bg-gray-100 dark:hover:bg-gray-800
                transition-colors
              "
            >
              <NotificationBell />
            </Link>
          </div>
        </header>

        {/* Page Content */}
        <div className="p-6">
          <Outlet />
        </div>

      </main>
    </div>
  );
};

