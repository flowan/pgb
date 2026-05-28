import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { useNotifications } from '@/hooks/use-notifications';

export function NotificationBell() {
    const { unreadCount, items, refresh } = useNotifications();
    const [open, setOpen] = useState(false);

    function markAllRead() {
        router.post('/notifications/read-all', {}, {
            preserveScroll: true,
            onSuccess: () => refresh(),
        });
    }

    return (
        <div className="relative">
            <button
                onClick={() => setOpen((o) => !o)}
                className="relative rounded-md p-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                aria-label="Meldingen"
            >
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                    <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-medium text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-2 w-80 rounded-xl border bg-white shadow-xl dark:bg-gray-900">
                    <div className="flex items-center justify-between border-b p-3">
                        <div className="text-sm font-medium">Meldingen</div>
                        {unreadCount > 0 && (
                            <button
                                onClick={markAllRead}
                                className="text-xs text-blue-600 hover:underline"
                            >
                                Alle als gelezen
                            </button>
                        )}
                    </div>
                    <div className="max-h-96 overflow-y-auto">
                        {items.length === 0 ? (
                            <div className="p-6 text-center text-sm text-gray-500">Geen meldingen</div>
                        ) : (
                            items.map((item) => (
                                <div
                                    key={item.id}
                                    className={`border-b p-3 text-sm ${item.read_at ? 'text-gray-500' : 'font-medium'}`}
                                >
                                    {renderText(item)}
                                    <div className="mt-1 text-xs text-gray-400">
                                        {new Date(item.created_at).toLocaleString('nl-NL')}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function renderText(item: { type: string; data: Record<string, unknown> }): string {
    switch (item.type) {
        case 'App\\Notifications\\ShiftTakeoverOffered':
            return `${item.data.offered_by ?? 'Een collega'} biedt een shift aan op ${item.data.date}`;
        case 'App\\Notifications\\ShiftTakenOver':
            return 'Jouw shift is overgenomen';
        case 'App\\Notifications\\ShiftSwapRequested':
            return 'Iemand wil met jou ruilen';
        case 'App\\Notifications\\ShiftSwapResponded':
            return 'Reactie op jouw ruilverzoek';
        case 'App\\Notifications\\OpenSwapRequested':
            return 'Open ruilverzoek geplaatst';
        case 'App\\Notifications\\OpenSwapOfferReceived':
            return 'Nieuw ruilaanbod ontvangen';
        case 'App\\Notifications\\OpenSwapAccepted':
            return 'Jouw aanbod is geaccepteerd';
        case 'App\\Notifications\\OpenSwapDeclined':
            return 'Jouw aanbod is afgewezen';
        case 'App\\Notifications\\ShiftRequestAutoCancelled':
            return 'Een verzoek is automatisch geannuleerd';
        default:
            return 'Nieuwe melding';
    }
}
