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
import { AvailabilityClaimDialog } from '@/components/schedule/availability-claim-dialog';
import { RequestSwapDialog } from '@/components/schedule/request-swap-dialog';
import { RequestTakeoverDialog } from '@/components/schedule/request-takeover-dialog';
import { ColleagueShiftMenu } from '@/components/schedule/colleague-shift-menu';
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
    const [perspective, setPerspective] = useState<'mine' | 'per-client'>('mine');
    const [selectedClientId, setSelectedClientId] = useState<number | null>(
        clients[0]?.id ?? null,
    );
    const [showAvailability, setShowAvailability] = useState(true);
    const [showColleagues, setShowColleagues] = useState(true);
    const [dialog, setDialog] = useState<DialogState | null>(null);
    const [claimDialog, setClaimDialog] = useState<{ slot: AvailabilitySlot; date: string } | null>(null);
    type ColleagueShiftRef = {
        kind: 'schedule' | 'exception';
        id: number;
        date: string;
        caregiverId: number;
        caregiverName: string;
        startTime: string;
        endTime: string;
    };
    const [colleagueMenu, setColleagueMenu] = useState<ColleagueShiftRef | null>(null);
    const [requestSwap, setRequestSwap] = useState<ColleagueShiftRef | null>(null);
    const [requestTakeover, setRequestTakeover] = useState<ColleagueShiftRef | null>(null);

    function claimSlot(slot: AvailabilitySlot, date: string) {
        setClaimDialog({ slot, date });
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

    // Build view data based on perspective
    let allSchedules: Schedule[];
    let allExceptions: ScheduleException[];
    let allAvailabilitySlots: AvailabilitySlot[];

    if (perspective === 'mine') {
        // Personal agenda: only my own shifts, but with the CLIENT name as label
        // (so I see WHERE I need to be, not my own name twice)
        allSchedules = clients.flatMap((client) =>
            (client.schedules ?? [])
                .filter((s) => myCaregiverIds.includes(s.caregiver_id))
                .map((s) => ({
                    ...s,
                    caregiver: { ...(s.caregiver as Caregiver), name: client.name },
                })),
        );
        allExceptions = clients.flatMap((client) =>
            (client.schedule_exceptions ?? [])
                .filter((ex) => myCaregiverIds.includes(ex.caregiver_id))
                .map((ex) => ({
                    ...ex,
                    caregiver: { ...(ex.caregiver as Caregiver), name: client.name },
                })),
        );
        allAvailabilitySlots = clients.flatMap((client) =>
            (client.availability_slots ?? []).map((slot) => ({
                ...slot,
                notes: client.name + (slot.notes ? ` — ${slot.notes}` : ''),
            })),
        );
    } else {
        // Per-client: only the selected client, with real caregiver names
        const client = clients.find((c) => c.id === selectedClientId);
        const schedules = client?.schedules ?? [];
        const exceptions = client?.schedule_exceptions ?? [];
        const slots = client?.availability_slots ?? [];
        allSchedules = schedules.filter(
            (s) => showColleagues || myCaregiverIds.includes(s.caregiver_id),
        );
        allExceptions = exceptions.filter(
            (ex) => showColleagues || myCaregiverIds.includes(ex.caregiver_id),
        );
        allAvailabilitySlots = slots;
    }

    const colleagues = collectColleagues(clients, myCaregiverIds);

    function handleShiftClick(kind: 'schedule' | 'exception', id: number, date: string, isMine: boolean) {
        if (isMine) {
            setDialog({ action: 'menu', kind, id, date });
            return;
        }
        // Colleague shift — open menu with options (ruil / overname)
        for (const client of clients) {
            if (kind === 'schedule') {
                const s = (client.schedules ?? []).find((x) => x.id === id);
                if (s) {
                    setColleagueMenu({
                        kind, id, date,
                        caregiverId: s.caregiver_id,
                        caregiverName: s.caregiver?.name ?? 'Onbekend',
                        startTime: s.start_time,
                        endTime: s.end_time,
                    });
                    return;
                }
            } else {
                const ex = (client.schedule_exceptions ?? []).find((x) => x.id === id);
                if (ex) {
                    setColleagueMenu({
                        kind, id, date,
                        caregiverId: ex.caregiver_id,
                        caregiverName: ex.caregiver?.name ?? 'Onbekend',
                        startTime: ex.start_time,
                        endTime: ex.end_time,
                    });
                    return;
                }
            }
        }
    }

    // All my own recurring schedules across clients — used as options when proposing a swap
    const myOwnSchedules: Schedule[] = clients.flatMap((c) =>
        (c.schedules ?? [])
            .filter((s) => myCaregiverIds.includes(s.caregiver_id))
            .map((s) => ({
                ...s,
                caregiver: { ...(s.caregiver as Caregiver), name: c.name },
            })),
    );

    return (
        <>
            <Head title="Mijn rooster" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Mijn rooster</h1>
                    <div className="flex items-center gap-1 rounded-lg bg-muted p-0.5">
                        <button
                            className={`rounded-md px-3 py-1 text-sm ${perspective === 'mine' ? 'bg-white font-medium shadow-sm dark:bg-gray-800' : 'hover:bg-white/50'}`}
                            onClick={() => setPerspective('mine')}
                        >
                            Mijn agenda
                        </button>
                        <button
                            className={`rounded-md px-3 py-1 text-sm ${perspective === 'per-client' ? 'bg-white font-medium shadow-sm dark:bg-gray-800' : 'hover:bg-white/50'}`}
                            onClick={() => setPerspective('per-client')}
                        >
                            Per cliënt
                        </button>
                    </div>
                </div>

                {perspective === 'per-client' && clients.length > 0 && (
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">Cliënt:</span>
                        <div className="flex flex-wrap gap-1">
                            {clients.map((c) => (
                                <button
                                    key={c.id}
                                    onClick={() => setSelectedClientId(c.id)}
                                    className={`rounded-md border px-3 py-1 text-sm ${selectedClientId === c.id ? 'border-blue-400 bg-blue-50 text-blue-900' : 'border-gray-200 hover:border-gray-300'}`}
                                >
                                    {c.name}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

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
                        {perspective === 'per-client' && (
                            <button
                                onClick={() => setShowColleagues((v) => !v)}
                                className={`flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs ${showColleagues ? 'border-gray-300 bg-gray-50 text-gray-700' : 'border-gray-200 bg-white text-gray-400'}`}
                            >
                                <span className={`h-2.5 w-2.5 rounded-full ${showColleagues ? 'bg-gray-400' : 'border border-gray-300'}`} />
                                Collega's
                            </button>
                        )}
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

            {claimDialog && (
                <AvailabilityClaimDialog
                    open
                    onOpenChange={(o) => !o && setClaimDialog(null)}
                    slot={claimDialog.slot}
                    date={claimDialog.date}
                />
            )}

            {colleagueMenu && (
                <ColleagueShiftMenu
                    open
                    onOpenChange={(o) => !o && setColleagueMenu(null)}
                    caregiverName={colleagueMenu.caregiverName}
                    date={colleagueMenu.date}
                    startTime={colleagueMenu.startTime}
                    endTime={colleagueMenu.endTime}
                    onRequestSwap={() => {
                        setRequestSwap(colleagueMenu);
                        setColleagueMenu(null);
                    }}
                    onRequestTakeover={() => {
                        setRequestTakeover(colleagueMenu);
                        setColleagueMenu(null);
                    }}
                />
            )}

            {requestSwap && (
                <RequestSwapDialog
                    open
                    onOpenChange={(o) => !o && setRequestSwap(null)}
                    targetKind={requestSwap.kind}
                    targetId={requestSwap.id}
                    targetDate={requestSwap.date}
                    targetCaregiverId={requestSwap.caregiverId}
                    targetCaregiverName={requestSwap.caregiverName}
                    targetStartTime={requestSwap.startTime}
                    targetEndTime={requestSwap.endTime}
                    mySchedules={myOwnSchedules}
                />
            )}

            {requestTakeover && (
                <RequestTakeoverDialog
                    open
                    onOpenChange={(o) => !o && setRequestTakeover(null)}
                    targetKind={requestTakeover.kind}
                    targetId={requestTakeover.id}
                    targetDate={requestTakeover.date}
                    targetCaregiverId={requestTakeover.caregiverId}
                    targetCaregiverName={requestTakeover.caregiverName}
                    targetStartTime={requestTakeover.startTime}
                    targetEndTime={requestTakeover.endTime}
                />
            )}
        </>
    );
}

CaregiverScheduleIndex.layout = {
    breadcrumbs: [{ title: 'Mijn rooster', href: '/my-schedule' }],
};
