import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { authService } from '../api/authService';

export const ForgotPasswordPage = () => {
    const navigate = useNavigate();

    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!email) {
            toast.error('Please enter your email address.');
            return;
        }

        try {
            setLoading(true);

            await authService.requestPasswordReset({
                email,
            });

            toast.success(
                'If the email exists, a verification code has been sent.'
            );

            navigate('/verify-reset-code', {
                state: { email },
            });
        } catch (error: any) {
            toast.error(
                error?.response?.data?.message ||
                'Unable to process your request.'
            );
        } finally {
            setLoading(false);
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
                onSubmit={handleSubmit}
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
                {/* Heading */}
                <h1
                    className="
            text-2xl
            font-bold
            mb-2
            text-gray-900
            dark:text-white
          "
                >
                    Forgot Password?
                </h1>

                <p
                    className="
            text-sm
            text-gray-600
            dark:text-gray-400
            mb-6
          "
                >
                    Enter your email address and we will send you a
                    verification code.
                </p>

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
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
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
                </div>

                {/* Submit */}
                <button
                    type="submit"
                    disabled={loading}
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
            disabled:opacity-50
            disabled:cursor-not-allowed
            transition-colors
          "
                >
                    {loading
                        ? 'Sending...'
                        : 'Send Verification Code'}
                </button>

                {/* Back to Login */}
                <button
                    type="button"
                    onClick={() => navigate('/login')}
                    className="
            w-full
            mt-3
            text-sm
            text-gray-600
            dark:text-gray-400
            hover:text-black
            dark:hover:text-white
            hover:underline
            transition-colors
          "
                >
                    Back to Login
                </button>
            </form>
        </div>
    );
};