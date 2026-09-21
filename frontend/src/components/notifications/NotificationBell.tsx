import { useState, useEffect } from 'react';
import { Bell } from 'lucide-react';
import { notificationService } from '../../api/notificationService';

export const NotificationBell = () => {
  const [count, setCount] = useState(0);

  useEffect(() => {
    notificationService.fetchNotifications()
      .then((data) => setCount(data.filter((n: any) => !n.read).length));
  }, []);

  return (
    <div className="relative">
      <Bell className="cursor-pointer" />
      {count > 0 && (
        <span className="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
          {count}
        </span>
      )}
    </div>
  );
};
