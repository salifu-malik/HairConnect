
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { authService } from '../api/authService';
import { toast } from 'react-hot-toast';
import { Eye, EyeOff, Scissors } from 'lucide-react';

const registrationSchema = z.object({
  role: z.enum([
    'CUSTOMER',
    'BARBER',
    'SHOP_OWNER',
    'VENDOR'
  ]),
  firstname: z.string().min(2, 'First name is required'),
  lastname: z.string().min(2, 'Last name is required'),
  email: z.string().email('Invalid email'),
  phone: z.string().min(10, 'Phone is required'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
  confirmPassword: z.string().min(6, 'Password must be at least 6 characters'),
}).refine((data) => data.password === data.confirmPassword, {
  message: "Passwords don't match",
  path: ['confirmPassword'],
});

export const RegisterPage = () => {
  const navigate = useNavigate();

  const [role, setRole] = useState<
    'CUSTOMER' | 'BARBER' | 'SHOP_OWNER' | 'VENDOR'
  >('CUSTOMER');

  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(registrationSchema),
  });

  const onSubmit = async (data: any) => {
    try {
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      const { confirmPassword, ...registrationData } = data;

      await authService.register(registrationData);

      toast.success('Registration successful! Please login.');

      navigate('/login');
    } catch (error) {
      toast.error('Registration failed');
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
        py-10
        bg-gray-100
        dark:bg-gray-950
        transition-colors
        duration-300
      "
    >
      <div
        className="
          w-full
          max-w-md
          bg-white
          dark:bg-gray-900
          p-6
          rounded-xl
          shadow-md
          dark:shadow-black/30
          border
          border-gray-100
          dark:border-gray-800
          transition-colors
          duration-300
        "
      >
        {/* Brand */}
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

          <h2
            className="
              text-2xl
              font-bold
              text-gray-900
              dark:text-white
            "
          >
            Create Account
          </h2>

          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Join HairConnect today
          </p>
        </div>

        <form
          onSubmit={handleSubmit(onSubmit)}
          className="space-y-4"
        >
          {/* Role */}
          <div>
            <label
              className="
                block
                text-sm
                font-medium
                mb-1
                text-gray-700
                dark:text-gray-300
              "
            >
              Role
            </label>

            <select
              {...register('role')}
              value={role}
              onChange={(e) =>
                setRole(
                  e.target.value as
                    | 'CUSTOMER'
                    | 'BARBER'
                    | 'SHOP_OWNER'
                    | 'VENDOR'
                )
              }
              className="
                border
                border-gray-300
                dark:border-gray-700
                bg-white
                dark:bg-gray-800
                text-gray-900
                dark:text-white
                p-2.5
                rounded-lg
                w-full
                outline-none
                focus:ring-2
                focus:ring-gray-300
                dark:focus:ring-gray-600
                transition-colors
              "
            >
              <option value="CUSTOMER">Customer</option>
              <option value="BARBER">Barber</option>
              <option value="SHOP_OWNER">Shop Owner</option>
              <option value="VENDOR">Vendor</option>
            </select>
          </div>

          {/* First Name */}
          <div>
            <label
              className="
                block
                text-sm
                font-medium
                mb-1
                text-gray-700
                dark:text-gray-300
              "
            >
              First Name
            </label>

            <input
              {...register('firstname')}
              className="
                border
                border-gray-300
                dark:border-gray-700
                bg-white
                dark:bg-gray-800
                text-gray-900
                dark:text-white
                p-2.5
                rounded-lg
                w-full
                outline-none
                placeholder-gray-400
                dark:placeholder-gray-500
                focus:ring-2
                focus:ring-gray-300
                dark:focus:ring-gray-600
                transition-colors
              "
              placeholder="Enter your first name"
            />

            {errors.firstname && (
              <p className="text-red-500 dark:text-red-400 text-xs mt-1">
                {errors.firstname.message as string}
              </p>
            )}
          </div>

          {/* Last Name */}
          <div>
            <label
              className="
                block
                text-sm
                font-medium
                mb-1
                text-gray-700
                dark:text-gray-300
              "
            >
              Last Name
            </label>

            <input
              {...register('lastname')}
              className="
                border
                border-gray-300
                dark:border-gray-700
                bg-white
                dark:bg-gray-800
                text-gray-900
                dark:text-white
                p-2.5
                rounded-lg
                w-full
                outline-none
                placeholder-gray-400
                dark:placeholder-gray-500
                focus:ring-2
                focus:ring-gray-300
                dark:focus:ring-gray-600
                transition-colors
              "
              placeholder="Enter your last name"
            />

            {errors.lastname && (
              <p className="text-red-500 dark:text-red-400 text-xs mt-1">
                {errors.lastname.message as string}
              </p>
            )}
          </div>

          {/* Email */}
          <div>
            <label
              className="
                block
                text-sm
                font-medium
                mb-1
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
                border
                border-gray-300
                dark:border-gray-700
                bg-white
                dark:bg-gray-800
                text-gray-900
                dark:text-white
                p-2.5
                rounded-lg
                w-full
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
              <p className="text-red-500 dark:text-red-400 text-xs mt-1">
                {errors.email.message as string}
              </p>
            )}
          </div>

          {/* Phone */}
          <div>
            <label
              className="
                block
                text-sm
                font-medium
                mb-1
                text-gray-700
                dark:text-gray-300
              "
            >
              Phone
            </label>

            <input
              {...register('phone')}
              type="tel"
              autoComplete="tel"
              className="
                border
                border-gray-300
                dark:border-gray-700
                bg-white
                dark:bg-gray-800
                text-gray-900
                dark:text-white
                p-2.5
                rounded-lg
                w-full
                outline-none
                placeholder-gray-400
                dark:placeholder-gray-500
                focus:ring-2
                focus:ring-gray-300
                dark:focus:ring-gray-600
                transition-colors
              "
              placeholder="Enter your phone number"
            />

            {errors.phone && (
              <p className="text-red-500 dark:text-red-400 text-xs mt-1">
                {errors.phone.message as string}
              </p>
            )}
          </div>

          {/* Password */}
          <div>
            <label
              className="
                block
                text-sm
                font-medium
                mb-1
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
                autoComplete="new-password"
                className="
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
                  w-full
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
              <p className="text-red-500 dark:text-red-400 text-xs mt-1">
                {errors.password.message as string}
              </p>
            )}
          </div>

          {/* Confirm Password */}
          <div>
            <label
              className="
                block
                text-sm
                font-medium
                mb-1
                text-gray-700
                dark:text-gray-300
              "
            >
              Confirm Password
            </label>

            <div className="relative">
              <input
                type={showConfirmPassword ? 'text' : 'password'}
                {...register('confirmPassword')}
                autoComplete="new-password"
                className="
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
                  w-full
                  outline-none
                  placeholder-gray-400
                  dark:placeholder-gray-500
                  focus:ring-2
                  focus:ring-gray-300
                  dark:focus:ring-gray-600
                  transition-colors
                "
                placeholder="Confirm your password"
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
                onClick={() =>
                  setShowConfirmPassword(!showConfirmPassword)
                }
                aria-label={
                  showConfirmPassword
                    ? 'Hide confirm password'
                    : 'Show confirm password'
                }
              >
                {showConfirmPassword ? (
                  <EyeOff size={20} />
                ) : (
                  <Eye size={20} />
                )}
              </button>
            </div>

            {errors.confirmPassword && (
              <p className="text-red-500 dark:text-red-400 text-xs mt-1">
                {errors.confirmPassword.message as string}
              </p>
            )}
          </div>

          {/* Register Button */}
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
            Register
          </button>

          {/* Login Link */}
          <div
            className="
              text-center
              text-sm
              text-gray-600
              dark:text-gray-400
              pt-1
            "
          >
            Already have an account?{' '}

            <button
              type="button"
              onClick={() => navigate('/login')}
              className="
                font-semibold
                text-black
                dark:text-white
                hover:underline
                transition-colors
              "
            >
              Login
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

