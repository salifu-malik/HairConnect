import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { authService } from '../api/authService';
import { toast } from 'react-hot-toast';

export const VerifyResetCodePage = () => {
    const navigate = useNavigate();
    const location = useLocation();

    const email = location.state?.email as string | undefined;

    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(false);

    const [cooldown, setCooldown] = useState(0);

    useEffect(() => {
        if (!email) {
            navigate('/forgot-password', { replace: true });
        }
    }, [email, navigate]);

    useEffect(() => {
        if (cooldown <= 0) return;

        const timer = setInterval(() => {
            setCooldown((previous) => previous - 1);
        }, 1000);

        return () => clearInterval(timer);
    }, [cooldown]);

    const handleVerify = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!email) {
            toast.error('Email address is missing.');
            navigate('/forgot-password');
            return;
        }

        if (!/^\d{6}$/.test(code)) {
            toast.error('Please enter the 6-digit verification code.');
            return;
        }

        try {
            setLoading(true);

            const response = await authService.verifyPasswordResetCode({
                email,
                code,
            });

            const resetToken = response.data?.data?.reset_token;

            if (!resetToken) {
                toast.error('Invalid response from server.');
                return;
            }

            toast.success('Code verified successfully.');

            navigate('/reset-password', {
                replace: true,
                state: {
                    email,
                    resetToken,
                },
            });
        } catch (error: any) {
            const message =
                error?.response?.data?.message ||
                'Invalid or expired verification code.';

            toast.error(message);
        } finally {
            setLoading(false);
        }
    };

    const handleResend = async () => {
        if (!email || cooldown > 0) return;

        try {
            setLoading(true);

            await authService.requestPasswordReset({
                email,
            });

            toast.success('A new verification code has been sent.');

            // 5-minute cooldown
            setCooldown(300);

            // Clear the old code
            setCode('');
        } catch (error: any) {
            const message =
                error?.response?.data?.message ||
                'Unable to resend verification code.';

            toast.error(message);
        } finally {
            setLoading(false);
        }
    };

    const formatCooldown = () => {
        const minutes = Math.floor(cooldown / 60);
        const seconds = cooldown % 60;

        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    };

    if (!email) {
        return null;
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
            <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
                <div className="mb-8 text-center">
                    <h1 className="text-2xl font-bold text-gray-900">
                        Verify your email
                    </h1>

                    <p className="mt-2 text-sm text-gray-600">
                        Enter the 6-digit verification code sent to
                    </p>

                    <p className="mt-1 font-medium text-gray-900">
                        {email}
                    </p>
                </div>

                <form onSubmit={handleVerify} className="space-y-5">
                    <div>
                        <label
                            htmlFor="code"
                            className="mb-2 block text-sm font-medium text-gray-700"
                        >
                            Verification Code
                        </label>

                        <input
                            id="code"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={code}
                            onChange={(e) => {
                                const value = e.target.value
                                    .replace(/\D/g, '')
                                    .slice(0, 6);

                                setCode(value);
                            }}
                            placeholder="Enter 6-digit code"
                            className="w-full rounded-lg border border-gray-300 px-4 py-3 text-center text-lg tracking-[0.4em] outline-none transition focus:border-black focus:ring-1 focus:ring-black"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={loading || code.length !== 6}
                        className="w-full rounded-lg bg-black px-4 py-3 font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {loading ? 'Verifying...' : 'Verify Code'}
                    </button>
                </form>

                <div className="mt-6 text-center">
                    <p className="text-sm text-gray-600">
                        Didn't receive the code?
                    </p>

                    <button
                        type="button"
                        onClick={handleResend}
                        disabled={loading || cooldown > 0}
                        className="mt-2 text-sm font-semibold text-black hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
                    >
                        {cooldown > 0
                            ? `Resend code in ${formatCooldown()}`
                            : 'Resend verification code'}
                    </button>
                </div>

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