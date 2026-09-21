export const getDashboardPath = (roles: string[]): string => {
    if (roles.includes('ADMIN')) {
        return '/dashboard/admin';
    }

    if (roles.includes('BARBER')) {
        return '/dashboard/barber';
    }

    if (roles.includes('FINANCE_MANAGER')) {
        return '/dashboard/finance';
    }

    if (roles.includes('SHOP_OWNER')) {
        return '/dashboard/shop-owner';
    }

    if (roles.includes('VENDOR')) {
        return '/dashboard/vendor';
    }

    if (roles.includes('DELIVERY_PERSONNEL')) {
        return '/dashboard/delivery';
    }

    if (roles.includes('CUSTOMER')) {
        return '/dashboard/customer';
    }

    return '/dashboard';
};