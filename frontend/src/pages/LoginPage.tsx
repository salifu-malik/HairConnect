import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { authService } from '../api/authService';
import { useAuthStore } from '../stores/authStore';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { Eye, EyeOff, Scissors } from 'lucide-react';
import { getDashboardPath } from '../utils/dashboardRedirect';

const loginSchema = z.object({
  email: z.string().email('Invalid email address'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
});

type LoginInputs = z.infer<typeof loginSchema>;

export const LoginPage = () => {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginInputs>({
    resolver: zodResolver(loginSchema),
  });

  const [showPassword, setShowPassword] = useState(false);

  const setAuth = useAuthStore((state) => state.setAuth);

  const navigate = useNavigate();

  const onSubmit = async (data: LoginInputs) => {
    try {
      const response = await authService.login(data);

      const {
        token,
        refresh_token,
        user,
      } = response.data.data;

      console.log('LOGIN USER:', user);
      console.log('LOGIN ROLES:', user.roles);

      setAuth(
          token,
          refresh_token,
          user
      );

      toast.success('Login successful!');

      const dashboardPath = getDashboardPath(user.roles);

      console.log('========== DASHBOARD REDIRECT ==========');
      console.log('USER:', user);
      console.log('ROLES:', user.roles);
      console.log('CALCULATED PATH:', dashboardPath);
      console.log('=========================================');

      navigate(dashboardPath, {
        replace: true,
      });

    } catch (error: any) {
      console.error('LOGIN ERROR:', error);
      console.error('SERVER RESPONSE:', error?.response?.data);

      toast.error(
          error?.response?.data?.message ||
          'Login request failed.'
      );
    }
  };

  return (
      <div
          className="
        min-h-screen
        flex
        justify-center
        items-center
        px-4
        bg-gray-100
        dark:bg-gray-950
        transition-colors
        duration-300
      "
      >
        <form
            onSubmit={handleSubmit(onSubmit)}
            className="
          bg-white
          dark:bg-gray-900
          p-8
          rounded-xl
          shadow-md
          dark:shadow-black/30
          border
          border-gray-100
          dark:border-gray-800
          w-full
          max-w-md
          transition-colors
          duration-300
        "
        >
          {/* Logo / Brand */}
          <div className="flex flex-col items-center mb-6">
            <div
                className="
              w-14
              h-14
              rounded-full
              bg-black
              dark:bg-white
              text-white
              dark:text-black
              flex
              items-center
              justify-center
              mb-3
            "
            >
              <Scissors size={26} />
            </div>

            <h1
                className="
              text-2xl
              font-bold
              text-gray-900
              dark:text-white
            "
            >
              HairConnect
            </h1>

            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Sign in to your account
            </p>
          </div>

          {/* Email */}
          <div className="mb-4">
            <label
                className="
              block
              mb-1
              text-sm
              font-medium
              text-gray-700
              dark:text-gray-300
            "
            >
              Email
            </label>

            <input
                {...register('email')}
                type="email"
                autoComplete="email"
                className="
              w-full
              border
              border-gray-300
              dark:border-gray-700
              bg-white
              dark:bg-gray-800
              text-gray-900
              dark:text-white
              p-2.5
              rounded-lg
              outline-none
              placeholder-gray-400
              dark:placeholder-gray-500
              focus:ring-2
              focus:ring-gray-300
              dark:focus:ring-gray-600
              transition-colors
            "
                placeholder="Enter your email"
            />

            {errors.email && (
                <p className="text-red-500 dark:text-red-400 text-sm mt-1">
                  {errors.email.message}
                </p>
            )}
          </div>

          {/* Password */}
          <div className="mb-4">
            <label
                className="
              block
              mb-1
              text-sm
              font-medium
              text-gray-700
              dark:text-gray-300
            "
            >
              Password
            </label>

            <div className="relative">
              <input
                  type={showPassword ? 'text' : 'password'}
                  {...register('password')}
                  autoComplete="current-password"
                  className="
                w-full
                border
                border-gray-300
                dark:border-gray-700
                bg-white
                dark:bg-gray-800
                text-gray-900
                dark:text-white
                p-2.5
                pr-10
                rounded-lg
                outline-none
                placeholder-gray-400
                dark:placeholder-gray-500
                focus:ring-2
                focus:ring-gray-300
                dark:focus:ring-gray-600
                transition-colors
              "
                  placeholder="Enter your password"
              />

              <button
                  type="button"
                  className="
                absolute
                right-2
                top-1/2
                -translate-y-1/2
                p-1
                text-gray-400
                dark:text-gray-500
                hover:text-gray-700
                dark:hover:text-gray-200
                transition-colors
              "
                  onClick={() => setShowPassword(!showPassword)}
                  aria-label={
                    showPassword
                        ? 'Hide password'
                        : 'Show password'
                  }
              >
                {showPassword ? (
                    <EyeOff size={20} />
                ) : (
                    <Eye size={20} />
                )}
              </button>
            </div>

            {errors.password && (
                <p className="text-red-500 dark:text-red-400 text-sm mt-1">
                  {errors.password.message}
                </p>
            )}
          </div>

          {/* Login Button */}
          <button
              type="submit"
              className="
            w-full
            bg-black
            dark:bg-white
            text-white
            dark:text-black
            p-2.5
            rounded-lg
            font-medium
            hover:bg-gray-800
            dark:hover:bg-gray-200
            transition-colors
          "
          >
            Login
          </button>

          {/* Forgot Password */}
          <div className="mt-4 text-center">
            <button
                type="button"
                onClick={() => navigate('/forgot-password')}
                className="
              text-sm
              text-gray-600
              dark:text-gray-400
              hover:text-black
              dark:hover:text-white
              hover:underline
              transition-colors
            "
            >
              Forgot password?
            </button>
          </div>

          {/* Register */}
          <div
              className="
            mt-3
            text-center
            text-sm
            text-gray-600
            dark:text-gray-400
          "
          >
            New here?{' '}

            <button
                type="button"
                onClick={() => navigate('/register')}
                className="
              font-semibold
              text-black
              dark:text-white
              hover:underline
              transition-colors
            "
            >
              Create account
            </button>
          </div>
        </form>
      </div>
  );
};