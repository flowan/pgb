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
import { SickReportDialog } from '@/components/schedule/sick-report-dialog';
import { Thermometer } from 'lucide-react';
import type { AvailabilitySlot, Caregiver, Client, Schedule, ScheduleException } from '@/types';

interface ClientWithSchedule extends Client {
    schedules: Schedule[];
    schedule_exceptions: ScheduleException[];
    availability_slots: AvailabilitySlot[];
    caregivers: Caregiver[];
}

type DialogState =
    | { action: 'menu'; kind: 'schedule' | 'exception'; id: number; date: string; clientId: number }
    | { action: 'takeover'; kind: 'schedule' | 'exception'; id: number; date: string; clientId: number }
    | { action: 'directswap'; kind: 'schedule' | 'exception'; id: number; date: string; clientId: number }
    | { action: 'openswap'; kind: 'schedule' | 'exception'; id: number; date: string; clientId: number };

export interface ShiftOccurrence {
    kind: 'schedule' | 'exception';
    id: number;
    date: string; // YYYY-MM-DD
    startTime: string;
    endTime: string;
    clientName: string;
    dayLabel: string;
}

const DAY_LABELS = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];

function toDateStr(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function ownDayOfWeek(d: Date): number {
    const js = d.getDay();
    return js === 0 ? 6 : js - 1;
}

function collectMyOccurrences(
    clients: ClientWithSchedule[],
    myIds: number[],
    weeks: number,
): ShiftOccurrence[] {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(today);
    end.setDate(end.getDate() + weeks * 7);

    const occurrences: ShiftOccurrence[] = [];

    for (const client of clients) {
        // Cancelled/modified date+schedule_id pairs — skip those occurrences of the base schedule
        const skip = new Set<string>();
        for (const ex of client.schedule_exceptions ?? []) {
            if ((ex.type === 'cancelled' || ex.type === 'modified') && ex.schedule_id) {
                skip.add(`${ex.schedule_id}:${ex.date}`);
            }
        }

        // Expand recurring schedules into upcoming dates
        const mySchedules = (client.schedules ?? []).filter((s) =>
            myIds.includes(s.caregiver_id),
        );
        for (const s of mySchedules) {
            const cursor = new Date(today);
            while (cursor <= end) {
                if (ownDayOfWeek(cursor) === s.day_of_week) {
                    const dateStr = toDateStr(cursor);
                    if (!skip.has(`${s.id}:${dateStr}`)) {
                        occurrences.push({
                            kind: 'schedule',
                            id: s.id,
                            date: dateStr,
                            startTime: s.start_time,
                            endTime: s.end_time,
                            clientName: client.name,
                            dayLabel: DAY_LABELS[s.day_of_week] ?? '?',
                        });
                    }
                }
                cursor.setDate(cursor.getDate() + 1);
            }
        }

        // One-off added exceptions for me (e.g., from availability claims)
        for (const ex of client.schedule_exceptions ?? []) {
            if (!myIds.includes(ex.caregiver_id)) continue;
            if (ex.type !== 'added' && ex.type !== 'modified') continue;
            const d = new Date(ex.date);
            d.setHours(0, 0, 0, 0);
            if (d < today || d > end) continue;
            occurrences.push({
                kind: 'exception',
                id: ex.id,
                date: ex.date,
                startTime: ex.start_time,
                endTime: ex.end_time,
                clientName: client.name,
                dayLabel: DAY_LABELS[ownDayOfWeek(d)] ?? '?',
            });
        }
    }

    return occurrences.sort((a, b) =>
        a.date === b.date ? a.startTime.localeCompare(b.startTime) : a.date.localeCompare(b.date),
    );
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
        clientId: number;
    };
    const [colleagueMenu, setColleagueMenu] = useState<ColleagueShiftRef | null>(null);
    const [requestSwap, setRequestSwap] = useState<ColleagueShiftRef | null>(null);
    const [requestTakeover, setRequestTakeover] = useState<ColleagueShiftRef | null>(null);
    const [sickDialog, setSickDialog] = useState(false);

    function reportSickSingle(kind: 'schedule' | 'exception', id: number, date: string) {
        router.post('/sick-reports', { scope: 'single', kind, id, date }, { preserveScroll: true });
    }

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


    function handleShiftClick(kind: 'schedule' | 'exception', id: number, date: string, isMine: boolean) {
        // Find the source client for this shift
        let clientId: number | null = null;
        for (const c of clients) {
            if (kind === 'schedule' && (c.schedules ?? []).some((x) => x.id === id)) {
                clientId = c.id;
                break;
            }
            if (kind === 'exception' && (c.schedule_exceptions ?? []).some((x) => x.id === id)) {
                clientId = c.id;
                break;
            }
        }
        if (clientId === null) return;

        if (isMine) {
            setDialog({ action: 'menu', kind, id, date, clientId });
            return;
        }
        // Colleague shift — open menu with options (ruil / overname)
        for (const client of clients) {
            if (client.id !== clientId) continue;
            if (kind === 'schedule') {
                const s = (client.schedules ?? []).find((x) => x.id === id);
                if (s) {
                    setColleagueMenu({
                        kind, id, date, clientId,
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
                        kind, id, date, clientId,
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

    // All upcoming occurrences of my own shifts across clients (next 8 weeks).
    // Used as picker options when proposing a swap — combines recurring schedules
    // expanded to dates with one-time added exceptions, minus cancelled/modified dates.
    const myOwnOccurrences = collectMyOccurrences(clients, myCaregiverIds, 8);

    return (
        <>
            <Head title="Mijn rooster" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Mijn rooster</h1>
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSickDialog(true)}
                        >
                            <Thermometer className="h-4 w-4" />
                            Ziek melden
                        </Button>
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

            {dialog?.action === 'menu' && (() => {
                const client = clients.find((c) => c.id === dialog.clientId);
                const shift = dialog.kind === 'schedule'
                    ? client?.schedules?.find((x) => x.id === dialog.id)
                    : client?.schedule_exceptions?.find((x) => x.id === dialog.id);
                if (!client || !shift) return null;
                return (
                    <ShiftActionMenu
                        open
                        onOpenChange={(o) => !o && setDialog(null)}
                        clientName={client.name}
                        date={dialog.date}
                        startTime={shift.start_time}
                        endTime={shift.end_time}
                        onTakeover={() => setDialog({ ...dialog, action: 'takeover' })}
                        onDirectSwap={() => setDialog({ ...dialog, action: 'directswap' })}
                        onOpenSwap={() => setDialog({ ...dialog, action: 'openswap' })}
                        onReportSick={() => {
                            reportSickSingle(dialog.kind, dialog.id, dialog.date);
                            setDialog(null);
                        }}
                    />
                );
            })()}

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
                    clients={clients.filter((c) => c.id === dialog.clientId)}
                    myCaregiverIds={myCaregiverIds}
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
                    myOccurrences={collectMyOccurrences(
                        clients.filter((c) => c.id === requestSwap.clientId),
                        myCaregiverIds,
                        8,
                    )}
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

            <SickReportDialog open={sickDialog} onOpenChange={setSickDialog} />
        </>
    );
}

CaregiverScheduleIndex.layout = {
    breadcrumbs: [{ title: 'Mijn rooster', href: '/my-schedule' }],
};
