export type Role =
  | 'CUSTOMER'
  | 'BARBER'
  | 'SHOP_OWNER'
  | 'VENDOR'
  | 'DELIVERY_PERSONNEL'
  | 'FINANCE_MANAGER'
  | 'ADMIN';

// export interface User {
//   id: string;
//   name: string;
//   email: string;
//   role: Role;
// }

export interface User {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  status: string;
  roles: string[];
  createdAt: string;
}
