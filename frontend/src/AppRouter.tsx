

import { BrowserRouter, Routes, Route } from 'react-router-dom';

import { ProtectedRoute } from './routes/ProtectedRoute';
import { RoleGuard } from './routes/RoleGuard';

import { HomePage } from './pages/HomePage';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { ForgotPasswordPage } from './pages/ForgotPasswordPage';
import { VerifyResetCodePage } from './pages/VerifyResetCodePage';
import { ResetPasswordPage } from './pages/ResetPasswordPage';

import { DashboardLayout } from './layouts/DashboardLayout';

import { DashboardPage } from './pages/DashboardPage';
import { BarberDashboardPage } from './pages/BarberDashboardPage';
import { AdminDashboardPage } from './pages/AdminDashboardPage';
import { FinanceDashboardPage } from './pages/FinanceDashboardPage';
import { ShopOwnerDashboardPage } from './pages/ShopOwnerDashboardPage';
import { VendorDashboardPage } from './pages/VendorDashboardPage';
import { DeliveryDashboardPage } from './pages/DeliveryDashboardPage';
import { CustomerDashboardPage } from './pages/CustomerDashboardPage';

import { BarberDiscoveryPage } from './pages/BarberDiscoveryPage';
import { BookingPage } from './pages/BookingPage';
import { MarketplacePage } from './pages/MarketplacePage';
import { ProductDetailsPage } from './pages/ProductDetailsPage';
import { CartPage } from './pages/CartPage';
import { CheckoutPage } from './pages/CheckoutPage';
import { SubscriptionPage } from './pages/SubscriptionPage';
import { WalletPage } from './pages/WalletPage';
import { NotificationsPage } from './pages/NotificationsPage';
import { ChatPage } from './pages/ChatPage';
import { ProfilePage } from './pages/ProfilePage';
import { QueuePage } from './pages/QueuePage';

export const AppRouter = () => {
  return (
      <BrowserRouter>
        <Routes>

          {/* Public routes */}
          <Route path="/" element={<HomePage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/verify-reset-code" element={<VerifyResetCodePage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />

          {/* Authentication required */}
          <Route element={<ProtectedRoute />}>

            <Route element={<DashboardLayout />}>

              {/* General dashboard */}
              <Route
                  path="/dashboard"
                  element={<DashboardPage />}
              />

              {/* Barber */}
              <Route element={<RoleGuard allowedRoles={['BARBER']} />}>
                <Route
                    path="/dashboard/barber"
                    element={<BarberDashboardPage />}
                />
              </Route>

              {/* Admin */}
              <Route element={<RoleGuard allowedRoles={['ADMIN']} />}>
                <Route
                    path="/dashboard/admin"
                    element={<AdminDashboardPage />}
                />
              </Route>

              {/* Finance */}
              <Route element={<RoleGuard allowedRoles={['FINANCE_MANAGER']} />}>
                <Route
                    path="/dashboard/finance"
                    element={<FinanceDashboardPage />}
                />
              </Route>

              {/* Shop owner */}
              <Route element={<RoleGuard allowedRoles={['SHOP_OWNER']} />}>
                <Route
                    path="/dashboard/shop-owner"
                    element={<ShopOwnerDashboardPage />}
                />
              </Route>

              {/* Vendor */}
              <Route element={<RoleGuard allowedRoles={['VENDOR']} />}>
                <Route
                    path="/dashboard/vendor"
                    element={<VendorDashboardPage />}
                />
              </Route>

              {/* Delivery */}
              <Route element={<RoleGuard allowedRoles={['DELIVERY_PERSONNEL']} />}>
                <Route
                    path="/dashboard/delivery"
                    element={<DeliveryDashboardPage />}
                />
              </Route>

              {/* Customer */}
              <Route element={<RoleGuard allowedRoles={['CUSTOMER']} />}>
                <Route
                    path="/dashboard/customer"
                    element={<CustomerDashboardPage />}
                />
              </Route>

              {/* Shared authenticated pages */}
              <Route
                  path="/barbers"
                  element={<BarberDiscoveryPage />}
              />

              <Route
                  path="/barbers/:barberId/book"
                  element={<BookingPage />}
              />

              <Route
                  path="/marketplace"
                  element={<MarketplacePage />}
              />

              <Route
                  path="/marketplace/:productId"
                  element={<ProductDetailsPage />}
              />

              <Route
                  path="/cart"
                  element={<CartPage />}
              />

              <Route
                  path="/checkout"
                  element={<CheckoutPage />}
              />

              <Route
                  path="/subscriptions"
                  element={<SubscriptionPage />}
              />

              <Route
                  path="/wallet"
                  element={<WalletPage />}
              />

              <Route
                  path="/notifications"
                  element={<NotificationsPage />}
              />

              <Route
                  path="/messages"
                  element={<ChatPage />}
              />

              <Route
                  path="/profile"
                  element={<ProfilePage />}
              />

              <Route
                  path="/queue"
                  element={<QueuePage />}
              />

            </Route>

          </Route>

          {/* Unauthorized */}
          <Route
              path="/unauthorized"
              element={
                <div className="flex min-h-screen items-center justify-center">
                  <div className="text-center">
                    <h1 className="text-3xl font-bold">
                      Access Denied
                    </h1>

                    <p className="mt-2 text-gray-600">
                      You do not have permission to access this page.
                    </p>
                  </div>
                </div>
              }
          />

        </Routes>
      </BrowserRouter>
  );
};
