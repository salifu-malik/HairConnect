import { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { profileService } from '../api/profileService';
import { toast } from 'react-hot-toast';
import {
  User,
  Mail,
  Phone,
  Lock,
  Save,
  Camera,
  Eye,
  EyeOff,
  ShieldCheck,
} from 'lucide-react';
import { useAuthStore } from '../stores/authStore';
import { VerificationUpload } from '../features/verification/components/VerificationUpload';

const profileSchema = z.object({
  firstname: z.string().min(2, 'First name is required'),
  lastname: z.string().min(2, 'Last name is required'),
  email: z.string().email('Invalid email'),
  phone: z.string().min(10, 'Phone is required'),
});

export const ProfilePage = () => {
  const user = useAuthStore((state) => state.user);
  const logout = useAuthStore((state) => state.logout);

  const [loading, setLoading] = useState(true);
  const [showPassword, setShowPassword] = useState(false);

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(profileSchema),
  });

  useEffect(() => {
    profileService
        .fetchProfile()
        .then((data) => {
          setValue('firstname', data.first_name);
          setValue('lastname', data.last_name);
          setValue('email', data.email);
          setValue('phone', data.phone);
        })
        .catch(() => toast.error('Failed to load profile'))
        .finally(() => setLoading(false));
  }, [setValue]);

  const handleUpdate = async (data: any) => {
    try {
      await profileService.updateProfile(data);
      toast.success('Profile updated successfully');
    } catch {
      toast.error('Failed to update profile');
    }
  };

  const handlePasswordUpdate = async (e: React.FormEvent) => {
    e.preventDefault();

    const form = e.currentTarget as HTMLFormElement;

    const formData = new FormData(form);

    const passwordData = Object.fromEntries(formData.entries());

    try {
      await profileService.changePassword(passwordData);

      toast.success(
          'Password changed successfully. Please log in again.'
      );

      form.reset();

      // Clear the frontend authentication state
      logout();

      // Redirect to the login page
      window.location.href = '/login';
    } catch (error: any) {
      const message =
          error?.response?.data?.message ||
          'Failed to update password';

      toast.error(message);
    }
  };

  if (loading) {
    return (
        <div className="p-6 text-gray-700 dark:text-gray-300">
          Loading...
        </div>
    );
  }

  return (
      <div
          className="
        p-6
        max-w-4xl
        mx-auto
        text-gray-900
        dark:text-gray-100
        transition-colors
        duration-300
      "
      >
        {/* Page Header */}
        <h2
            className="
          text-3xl
          font-bold
          mb-8
          flex
          items-center
          gap-2
          text-gray-900
          dark:text-white
        "
        >
          <User className="text-black dark:text-white" />

          Profile Settings

          {user?.status === 'VERIFIED' && (
              <span
                  className="
              text-green-600
              dark:text-green-400
              flex
              items-center
              gap-1
              text-sm
              bg-green-50
              dark:bg-green-900/30
              px-2
              py-1
              rounded
            "
              >
            <ShieldCheck size={16} />
            Verified
          </span>
          )}
        </h2>

        {/* Profile Information Card */}
        <div
            className="
          bg-white
          dark:bg-gray-900
          p-6
          rounded-lg
          shadow-sm
          dark:shadow-black/20
          border
          border-gray-100
          dark:border-gray-800
          transition-colors
          duration-300
        "
        >
          {/* Profile Photo */}
          <div className="flex flex-col md:flex-row items-center gap-6 mb-8">
            <div className="relative">
              <div
                  className="
                w-24
                h-24
                bg-gray-200
                dark:bg-gray-800
                rounded-full
                flex
                items-center
                justify-center
              "
              >
                <User
                    size={48}
                    className="text-gray-400 dark:text-gray-500"
                />
              </div>

              <button
                  type="button"
                  className="
                absolute
                bottom-0
                right-0
                p-2
                bg-black
                dark:bg-white
                text-white
                dark:text-black
                rounded-full
                hover:bg-gray-800
                dark:hover:bg-gray-200
                transition-colors
              "
              >
                <Camera size={16} />
              </button>
            </div>

            <div>
              <h3 className="text-xl font-semibold text-gray-900 dark:text-white">
                Profile Photo
              </h3>

              <p className="text-sm text-gray-500 dark:text-gray-400">
                Update your photo to personalize your profile
              </p>
            </div>
          </div>

          {/* Profile Form */}
          <form
              onSubmit={handleSubmit(handleUpdate)}
              className="space-y-6"
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

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

                <div className="relative">
                  <User
                      className="
                    absolute
                    left-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                  "
                      size={18}
                  />

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
                    p-2
                    pl-10
                    rounded
                    w-full
                    outline-none
                    focus:ring-2
                    focus:ring-gray-300
                    dark:focus:ring-gray-600
                    transition-colors
                  "
                  />
                </div>

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

                <div className="relative">
                  <User
                      className="
                    absolute
                    left-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                  "
                      size={18}
                  />

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
                    p-2
                    pl-10
                    rounded
                    w-full
                    outline-none
                    focus:ring-2
                    focus:ring-gray-300
                    dark:focus:ring-gray-600
                    transition-colors
                  "
                  />
                </div>

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

                <div className="relative">
                  <Mail
                      className="
                    absolute
                    left-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                  "
                      size={18}
                  />

                  <input
                      {...register('email')}
                      disabled
                      className="
                    border
                    border-gray-300
                    dark:border-gray-700
                    p-2
                    pl-10
                    rounded
                    w-full
                    bg-gray-50
                    dark:bg-gray-800
                    text-gray-500
                    dark:text-gray-400
                    cursor-not-allowed
                  "
                  />
                </div>
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

                <div className="relative">
                  <Phone
                      className="
                    absolute
                    left-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                  "
                      size={18}
                  />

                  <input
                      {...register('phone')}
                      className="
                    border
                    border-gray-300
                    dark:border-gray-700
                    bg-white
                    dark:bg-gray-800
                    text-gray-900
                    dark:text-white
                    p-2
                    pl-10
                    rounded
                    w-full
                    outline-none
                    focus:ring-2
                    focus:ring-gray-300
                    dark:focus:ring-gray-600
                    transition-colors
                  "
                  />
                </div>

                {errors.phone && (
                    <p className="text-red-500 dark:text-red-400 text-xs mt-1">
                      {errors.phone.message as string}
                    </p>
                )}
              </div>
            </div>

            {/* Save Profile */}
            <button
                type="submit"
                className="
              flex
              items-center
              gap-2
              bg-black
              dark:bg-white
              text-white
              dark:text-black
              px-6
              py-2
              rounded
              hover:bg-gray-800
              dark:hover:bg-gray-200
              transition-colors
            "
            >
              <Save size={18} />
              Save Changes
            </button>
          </form>
        </div>

        {/* Security Card */}
        <div
            className="
          bg-white
          dark:bg-gray-900
          p-6
          rounded-lg
          shadow-sm
          dark:shadow-black/20
          border
          border-gray-100
          dark:border-gray-800
          mt-6
          transition-colors
          duration-300
        "
        >
          {/* Security Header */}
          <h3
              className="
            text-xl
            font-semibold
            mb-6
            flex
            items-center
            gap-2
            text-gray-900
            dark:text-white
          "
          >
            <ShieldCheck className="text-black dark:text-white" />
            Security
          </h3>

          {/* Password Change */}
          <div className="mb-8">
            <h4 className="font-medium mb-4 text-gray-900 dark:text-white">
              Change Password
            </h4>

            <form
                onSubmit={handlePasswordUpdate}
                className="space-y-4"
            >
              {/* Current Password */}
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
                  Current Password
                </label>

                <div className="relative">
                  <Lock
                      className="
                    absolute
                    left-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                  "
                      size={18}
                  />

                  <input
                      type={showPassword ? 'text' : 'password'}
                      name="currentPassword"
                      className="
                    border
                    border-gray-300
                    dark:border-gray-700
                    bg-white
                    dark:bg-gray-800
                    text-gray-900
                    dark:text-white
                    p-2
                    pl-10
                    pr-10
                    rounded
                    w-full
                    outline-none
                    focus:ring-2
                    focus:ring-gray-300
                    dark:focus:ring-gray-600
                    transition-colors
                  "
                      required
                  />

                  <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="
                    absolute
                    right-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                    hover:text-gray-700
                    dark:hover:text-gray-200
                    transition-colors
                  "
                      aria-label={
                        showPassword
                            ? 'Hide password'
                            : 'Show password'
                      }
                  >
                    {showPassword ? (
                        <EyeOff size={18} />
                    ) : (
                        <Eye size={18} />
                    )}
                  </button>
                </div>
              </div>

              {/* New Password */}
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
                  New Password
                </label>

                <div className="relative">
                  <Lock
                      className="
                    absolute
                    left-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                  "
                      size={18}
                  />

                  <input
                      type={showPassword ? 'text' : 'password'}
                      name="newPassword"
                      className="
                    border
                    border-gray-300
                    dark:border-gray-700
                    bg-white
                    dark:bg-gray-800
                    text-gray-900
                    dark:text-white
                    p-2
                    pl-10
                    pr-10
                    rounded
                    w-full
                    outline-none
                    focus:ring-2
                    focus:ring-gray-300
                    dark:focus:ring-gray-600
                    transition-colors
                  "
                      required
                  />

                  <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="
                    absolute
                    right-3
                    top-2.5
                    text-gray-400
                    dark:text-gray-500
                    hover:text-gray-700
                    dark:hover:text-gray-200
                    transition-colors
                  "
                      aria-label={
                        showPassword
                            ? 'Hide password'
                            : 'Show password'
                      }
                  >
                    {showPassword ? (
                        <EyeOff size={18} />
                    ) : (
                        <Eye size={18} />
                    )}
                  </button>
                </div>
              </div>

              {/* Update Password */}
              <button
                  type="submit"
                  className="
                flex
                items-center
                gap-2
                bg-black
                dark:bg-white
                text-white
                dark:text-black
                px-6
                py-2
                rounded
                hover:bg-gray-800
                dark:hover:bg-gray-200
                transition-colors
              "
              >
                <Save size={18} />
                Update Password
              </button>
            </form>
          </div>

          {/* Verification */}
          {(user?.roles.includes('BARBER') ||
                  user?.roles.includes('SHOP_OWNER') ||
                  user?.roles.includes('VENDOR')) &&
              user?.status !== 'VERIFIED' && (
                  <div>
                    <h4 className="font-medium mb-4 text-gray-900 dark:text-white">
                      Account Verification
                    </h4>

                    <VerificationUpload />
                  </div>
              )}
        </div>
      </div>
  );
};