import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { authService } from '../api/authService';
import { toast } from 'react-hot-toast';
import { Eye, EyeOff } from 'lucide-react';

export const ResetPasswordPage = () => {
    const navigate = useNavigate();
    const location = useLocation();

    const email = location.state?.email as string | undefined;
    const resetToken = location.state?.resetToken as string | undefined;

    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!email || !resetToken) {
            navigate('/forgot-password', { replace: true });
        }
    }, [email, resetToken, navigate]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!email || !resetToken) {
            toast.error('Password reset session is invalid or expired.');
            navigate('/forgot-password');
            return;
        }

        if (password.length < 8) {
            toast.error('Password must be at least 8 characters.');
            return;
        }

        if (password !== confirmPassword) {
            toast.error('Passwords do not match.');
            return;
        }

        try {
            setLoading(true);

            await authService.resetPassword({
                reset_token: resetToken,
                password,
                confirm_password: confirmPassword,
            });

            toast.success('Password reset successfully.');

            navigate('/login', {
                replace: true,
                state: {
                    message: 'Your password has been reset. Please log in.',
                },
            });
        } catch (error: any) {
            const message =
                error?.response?.data?.message ||
                'Unable to reset password. Please try again.';

            toast.error(message);
        } finally {
            setLoading(false);
        }
    };

    if (!email || !resetToken) {
        return null;
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
            <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
                <div className="mb-8 text-center">
                    <h1 className="text-2xl font-bold text-gray-900">
                        Create new password
                    </h1>

                    <p className="mt-2 text-sm text-gray-600">
                        Create a new password for
                    </p>

                    <p className="mt-1 font-medium text-gray-900">
                        {email}
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    {/* New Password */}
                    <div>
                        <label
                            htmlFor="password"
                            className="mb-2 block text-sm font-medium text-gray-700"
                        >
                            New Password
                        </label>

                        <div className="relative">
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="Enter new password"
                                autoComplete="new-password"
                                className="w-full rounded-lg border border-gray-300 px-4 py-3 pr-12 outline-none transition focus:border-black focus:ring-1 focus:ring-black"
                            />

                            <button
                                type="button"
                                onClick={() => setShowPassword((previous) => !previous)}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-black"
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

                        <p className="mt-2 text-xs text-gray-500">
                            Password must be at least 8 characters.
                        </p>
                    </div>

                    {/* Confirm Password */}
                    <div>
                        <label
                            htmlFor="confirmPassword"
                            className="mb-2 block text-sm font-medium text-gray-700"
                        >
                            Confirm Password
                        </label>

                        <div className="relative">
                            <input
                                id="confirmPassword"
                                type={
                                    showConfirmPassword ? 'text' : 'password'
                                }
                                value={confirmPassword}
                                onChange={(e) =>
                                    setConfirmPassword(e.target.value)
                                }
                                placeholder="Confirm new password"
                                autoComplete="new-password"
                                className="w-full rounded-lg border border-gray-300 px-4 py-3 pr-12 outline-none transition focus:border-black focus:ring-1 focus:ring-black"
                            />

                            <button
                                type="button"
                                onClick={() =>
                                    setShowConfirmPassword(
                                        (previous) => !previous
                                    )
                                }
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-black"
                                aria-label={
                                    showConfirmPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                }
                            >
                                {showConfirmPassword ? (
                                    <EyeOff size={20} />
                                ) : (
                                    <Eye size={20} />
                                )}
                            </button>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={
                            loading ||
                            password.length < 8 ||
                            confirmPassword.length < 8
                        }
                        className="w-full rounded-lg bg-black px-4 py-3 font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {loading ? 'Resetting Password...' : 'Reset Password'}
                    </button>
                </form>

                <div className="mt-6 text-center">
                    <button
                        type="button"
                        onClick={() => navigate('/login')}
                        className="text-sm text-gray-600 hover:text-black hover:underline"
                    >
                        Back to Login
                    </button>
                </div>
            </div>
        </div>
    );
};