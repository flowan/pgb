import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { WeekView } from '@/components/schedule/week-view';
import { MonthView } from '@/components/schedule/month-view';
import { ShiftActionMenu } from '@/components/schedule/shift-action-menu';
import { TakeoverOfferDialog } from '@/components/schedule/takeover-offer-dialog';
import { DirectSwapDialog } from '@/components/schedule/direct-swap-dialog';
import { OpenSwapDialog } from '@/components/schedule/open-swap-dialog';
import type { AvailabilitySlot, Caregiver, Client, Schedule, ScheduleException } from '@/types';

interface ClientWithSchedule extends Client {
    schedules: Schedule[];
    schedule_exceptions: ScheduleException[];
    availability_slots: AvailabilitySlot[];
    caregivers: Caregiver[];
}

type DialogState =
    | { action: 'menu'; kind: 'schedule' | 'exception'; id: number; date: string }
    | { action: 'takeover'; kind: 'schedule' | 'exception'; id: number; date: string }
    | { action: 'directswap'; kind: 'schedule' | 'exception'; id: number; date: string }
    | { action: 'openswap'; kind: 'schedule' | 'exception'; id: number; date: string };

function collectColleagues(
    clients: ClientWithSchedule[],
    myIds: number[],
): (Caregiver & { schedules?: Schedule[] })[] {
    const seen = new Set<number>();
    const colleagues: (Caregiver & { schedules?: Schedule[] })[] = [];
    for (const client of clients) {
        for (const cg of client.caregivers ?? []) {
            if (myIds.includes(cg.id)) continue;
            if (seen.has(cg.id)) continue;
            seen.add(cg.id);
            const cgSchedules = (client.schedules ?? []).filter((s) => s.caregiver_id === cg.id);
            colleagues.push({ ...cg, schedules: cgSchedules });
        }
    }
    return colleagues;
}

function getMonday(date: Date): Date {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff);
    d.setHours(0, 0, 0, 0);
    return d;
}

export default function CaregiverScheduleIndex({
    clients,
    myCaregiverIds,
}: {
    clients: ClientWithSchedule[];
    myCaregiverIds: number[];
}) {
    const [weekStart, setWeekStart] = useState(() => getMonday(new Date()));
    const [view, setView] = useState<'week' | 'month'>('week');
    const [showAvailability, setShowAvailability] = useState(true);
    const [dialog, setDialog] = useState<DialogState | null>(null);

    function claimSlot(slotId: number) {
        router.post(`/availability-slots/${slotId}/claim`);
    }

    function prev() {
        setWeekStart((p) => {
            const d = new Date(p);
            if (view === 'week') {
                d.setDate(d.getDate() - 7);
            } else {
                d.setMonth(d.getMonth() - 1);
            }
            return d;
        });
    }

    function next() {
        setWeekStart((p) => {
            const d = new Date(p);
            if (view === 'week') {
                d.setDate(d.getDate() + 7);
            } else {
                d.setMonth(d.getMonth() + 1);
            }
            return d;
        });
    }

    function jumpToWeek(date: Date) {
        setWeekStart(getMonday(date));
        setView('week');
    }

    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);

    // Pass the raw data through — we now want to see the real caregiver name
    // so colleagues' shifts are distinguishable from your own.
    const allSchedules: Schedule[] = clients.flatMap((c) => c.schedules ?? []);
    const allExceptions: ScheduleException[] = clients.flatMap((c) => c.schedule_exceptions ?? []);
    const allAvailabilitySlots: AvailabilitySlot[] = clients.flatMap((client) =>
        (client.availability_slots ?? []).map((slot) => ({
            ...slot,
            notes: client.name + (slot.notes ? ` — ${slot.notes}` : ''),
        })),
    );

    const colleagues = collectColleagues(clients, myCaregiverIds);

    function handleShiftClick(kind: 'schedule' | 'exception', id: number, date: string, isMine: boolean) {
        if (!isMine) return;
        setDialog({ action: 'menu', kind, id, date });
    }

    return (
        <>
            <Head title="Mijn rooster" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Mijn rooster</h1>

                {/* Navigation + view toggle */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setWeekStart(getMonday(new Date()))}
                        >
                            Vandaag
                        </Button>
                        <div className="flex items-center gap-1">
                            <Button variant="ghost" size="sm" onClick={prev}>
                                <ChevronLeft />
                            </Button>
                            <Button variant="ghost" size="sm" onClick={next}>
                                <ChevronRight />
                            </Button>
                        </div>
                        <span className="text-lg font-medium">
                            {view === 'week'
                                ? `${weekStart.toLocaleDateString('nl-NL', { day: 'numeric', month: 'long' })} – ${weekEnd.toLocaleDateString('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' })}`
                                : weekStart.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' })}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setShowAvailability((v) => !v)}
                            className={`flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs ${showAvailability ? 'border-purple-300 bg-purple-50 text-purple-900' : 'border-gray-200 bg-white text-gray-400'}`}
                        >
                            <span className={`h-2.5 w-2.5 rounded-full border border-dashed ${showAvailability ? 'border-purple-400 bg-purple-300' : 'border-gray-300'}`} />
                            Beschikbaarheid
                        </button>
                        <div className="flex items-center gap-1 rounded-lg bg-muted p-0.5">
                            <button
                                className={`rounded-md px-3 py-1 text-sm ${view === 'week' ? 'bg-white font-medium shadow-sm dark:bg-gray-800' : 'hover:bg-white/50'}`}
                                onClick={() => setView('week')}
                            >
                                Week
                            </button>
                            <button
                                className={`rounded-md px-3 py-1 text-sm ${view === 'month' ? 'bg-white font-medium shadow-sm dark:bg-gray-800' : 'hover:bg-white/50'}`}
                                onClick={() => setView('month')}
                            >
                                Maand
                            </button>
                        </div>
                    </div>
                </div>

                {clients.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Je bent nog niet gekoppeld aan een cliënt.
                    </div>
                ) : (
                    view === 'week' ? (
                        <WeekView
                            schedules={allSchedules}
                            exceptions={allExceptions}
                            availabilitySlots={showAvailability ? allAvailabilitySlots : []}
                            weekStart={weekStart}
                            onClaimAvailability={claimSlot}
                            myCaregiverIds={myCaregiverIds}
                            onShiftClick={handleShiftClick}
                        />
                    ) : (
                        <MonthView
                            schedules={allSchedules}
                            exceptions={allExceptions}
                            availabilitySlots={showAvailability ? allAvailabilitySlots : []}
                            monthStart={weekStart}
                            onOpenWeek={jumpToWeek}
                            onClaimAvailability={claimSlot}
                            myCaregiverIds={myCaregiverIds}
                            onShiftClick={handleShiftClick}
                        />
                    )
                )}
            </div>

            {dialog?.action === 'menu' && (
                <ShiftActionMenu
                    open
                    onOpenChange={(o) => !o && setDialog(null)}
                    onTakeover={() => setDialog({ ...dialog, action: 'takeover' })}
                    onDirectSwap={() => setDialog({ ...dialog, action: 'directswap' })}
                    onOpenSwap={() => setDialog({ ...dialog, action: 'openswap' })}
                />
            )}

            {dialog?.action === 'takeover' && (
                <TakeoverOfferDialog
                    open
                    onOpenChange={(o) => !o && setDialog(null)}
                    kind={dialog.kind}
                    id={dialog.id}
                    date={dialog.date}
                />
            )}

            {dialog?.action === 'directswap' && (
                <DirectSwapDialog
                    open
                    onOpenChange={(o) => !o && setDialog(null)}
                    kind={dialog.kind}
                    id={dialog.id}
                    date={dialog.date}
                    colleagues={colleagues}
                />
            )}

            {dialog?.action === 'openswap' && (
                <OpenSwapDialog
                    open
                    onOpenChange={(o) => !o && setDialog(null)}
                    kind={dialog.kind}
                    id={dialog.id}
                    date={dialog.date}
                />
            )}
        </>
    );
}

CaregiverScheduleIndex.layout = {
    breadcrumbs: [{ title: 'Mijn rooster', href: '/my-schedule' }],
};
