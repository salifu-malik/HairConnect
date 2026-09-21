import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import { getDashboardPath } from '../utils/dashboardRedirect';

export const DashboardPage = () => {
  const { user } = useAuthStore();
  const navigate = useNavigate();


  useEffect(() => {
    console.log('========== DASHBOARD PAGE ==========');
    console.log('USER:', user);
    console.log('ROLES:', user?.roles);
    console.log('====================================');

    if (!user) return;

    const dashboardPath = getDashboardPath(user.roles);

    console.log('DashboardPage redirecting to:', dashboardPath);

    navigate(dashboardPath, {
      replace: true
    });
  }, [user, navigate]);
};