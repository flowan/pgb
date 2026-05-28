import { useEffect, useState } from 'react';

interface NotificationItem {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

export function useNotifications() {
    const [unreadCount, setUnreadCount] = useState(0);
    const [items, setItems] = useState<NotificationItem[]>([]);

    async function refresh() {
        try {
            const res = await fetch('/notifications', { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            setUnreadCount(data.unread_count);
            setItems(data.notifications);
        } catch {
            // ignore network errors – will retry on next poll
        }
    }

    useEffect(() => {
        refresh();
        const id = setInterval(refresh, 60_000);
        return () => clearInterval(id);
    }, []);

    return { unreadCount, items, refresh };
}
