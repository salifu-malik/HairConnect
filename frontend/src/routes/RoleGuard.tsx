// import { Navigate, Outlet } from 'react-router-dom';
// import { useAuthStore } from '../stores/authStore';
//
// interface RoleGuardProps {
//   allowedRoles: string[];
// }
//
// export const RoleGuard = ({ allowedRoles }: RoleGuardProps) => {
//   const { token, user } = useAuthStore();
//
//   // Not authenticated
//   if (!token || !user) {
//     return <Navigate to="/login" replace />;
//   }
//
//   // User can have multiple roles
//   const hasAllowedRole = user.roles?.some((role) =>
//       allowedRoles.includes(role)
//   );
//
//   // Authenticated but not authorized
//   if (!hasAllowedRole) {
//     return <Navigate to="/unauthorized" replace />;
//   }
//
//   return <Outlet />;
// };

import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

interface RoleGuardProps {
  allowedRoles: string[];
}

export const RoleGuard = ({
                            allowedRoles
                          }: RoleGuardProps) => {

  const user = useAuthStore((state) => state.user);

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  const hasAllowedRole = user.roles.some(
      role => allowedRoles.includes(role)
  );

  if (!hasAllowedRole) {
    return <Navigate to="/unauthorized" replace />;
  }

  return <Outlet />;
};