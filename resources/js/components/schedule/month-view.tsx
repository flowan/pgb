import { useState, useRef, useEffect } from 'react';
import type { AvailabilitySlot, Schedule, ScheduleException, ShiftTakeoverOffer } from '@/types';

interface MonthViewProps {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    availabilitySlots?: AvailabilitySlot[];
    takeoverOffers?: ShiftTakeoverOffer[];
    monthStart: Date;
    onOpenWeek?: (date: Date) => void;
    onClaimAvailability?: (slot: AvailabilitySlot, date: string) => void;
    onUnreportSick?: (exceptionId: number) => void;
    onClaimTakeover?: (offerId: number) => void;
    myCaregiverIds?: number[];
    onShiftClick?: (kind: 'schedule' | 'exception', id: number, date: string, isMine: boolean) => void;
}

interface DayEvent {
    id: string;
    label: string;
    startTime: string;
    endTime: string;
    variant: 'regular' | 'added' | 'modified' | 'cancelled' | 'available' | 'other' | 'offered';
    sublabel?: string;
    onClaim?: () => void;
    onAction?: () => void;
    actionLabel?: string;
}

const dayNamesShort = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];

const chipStyles: Record<DayEvent['variant'], string> = {
    regular: 'bg-blue-100 text-blue-900',
    added: 'bg-green-100 text-green-900',
    modified: 'bg-amber-100 text-amber-900',
    cancelled: 'bg-red-100 text-red-700 line-through',
    available: 'bg-purple-100 text-purple-900 border border-dashed border-purple-300',
    other: 'bg-gray-100 text-gray-500',
    offered: 'bg-orange-100 text-orange-900 border border-dashed border-orange-300',
};

const detailStyles: Record<DayEvent['variant'], string> = {
    regular: 'bg-blue-50 border-blue-500',
    added: 'bg-green-50 border-green-500',
    modified: 'bg-amber-50 border-amber-500',
    cancelled: 'bg-red-50 border-red-500',
    available: 'bg-purple-50 border-dashed border-purple-400',
    other: 'bg-gray-50 border-gray-300',
    offered: 'bg-orange-50 border-dashed border-orange-400',
};

function formatTime(time: string): string {
    return time.substring(0, 5);
}

function toLocalDateStr(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function formatTimeShort(time: string): string {
    // 09:00:00 → 09
    return time.substring(0, 2);
}

function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function getGridStart(monthStart: Date): Date {
    // First day of the month
    const first = new Date(monthStart.getFullYear(), monthStart.getMonth(), 1);
    // Monday = 0 in our model; JS getDay returns 0 for Sunday
    const jsDay = first.getDay();
    const offset = jsDay === 0 ? 6 : jsDay - 1;
    const start = new Date(first);
    start.setDate(start.getDate() - offset);
    start.setHours(0, 0, 0, 0);
    return start;
}

function getNumberOfWeeks(monthStart: Date): number {
    const first = new Date(monthStart.getFullYear(), monthStart.getMonth(), 1);
    const last = new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 0);
    const gridStart = getGridStart(monthStart);
    const totalDays = Math.ceil((last.getTime() - gridStart.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    return Math.ceil(totalDays / 7);
}

export function MonthView({
    schedules,
    exceptions,
    availabilitySlots = [],
    takeoverOffers = [],
    monthStart,
    onOpenWeek,
    onClaimAvailability,
    onUnreportSick,
    onClaimTakeover,
    myCaregiverIds = [],
    onShiftClick,
}: MonthViewProps) {
    const [openDay, setOpenDay] = useState<string | null>(null);
    const popoverRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
                setOpenDay(null);
            }
        }
        if (openDay) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [openDay]);

    const gridStart = getGridStart(monthStart);
    const weeksCount = getNumberOfWeeks(monthStart);
    const days = Array.from({ length: weeksCount * 7 }, (_, i) => {
        const d = new Date(gridStart);
        d.setDate(d.getDate() + i);
        return d;
    });

    const today = new Date();

    function getEventsForDay(date: Date, dayIndex: number): DayEvent[] {
        const dateStr = toLocalDateStr(date);
        const dayExceptions = exceptions.filter((ex) => ex.date === dateStr);
        const dayOffers = takeoverOffers.filter((o) => o.date === dateStr);

        const cancelledOrModifiedIds = new Set(
            dayExceptions
                .filter((ex) => ex.type === 'cancelled' || ex.type === 'modified')
                .map((ex) => ex.schedule_id)
                .filter((id): id is number => id !== null),
        );

        const events: DayEvent[] = [];

        schedules
            .filter((s) => s.day_of_week === dayIndex && !cancelledOrModifiedIds.has(s.id))
            .forEach((s) => {
                const isMine = myCaregiverIds.includes(s.caregiver_id);
                const offer = dayOffers.find((o) => o.schedule_id === s.id);
                if (offer) {
                    const offerByMe = myCaregiverIds.includes(offer.offered_by_caregiver_id);
                    const reason = offer.from_sickness ? ' (ziek)' : '';
                    events.push({
                        id: `s-${s.id}`,
                        label: s.caregiver?.name ?? 'Onbekend',
                        sublabel: (offerByMe ? 'Aangeboden voor overname' : 'Wordt aangeboden') + reason,
                        startTime: s.start_time,
                        endTime: s.end_time,
                        variant: 'offered',
                        onClaim: !offerByMe && onClaimTakeover ? () => onClaimTakeover(offer.id) : undefined,
                        onAction: onShiftClick ? () => onShiftClick('schedule', s.id, dateStr, isMine) : undefined,
                        actionLabel: isMine ? 'Acties' : 'Info',
                    });
                    return;
                }
                events.push({
                    id: `s-${s.id}`,
                    label: s.caregiver?.name ?? 'Onbekend',
                    startTime: s.start_time,
                    endTime: s.end_time,
                    variant: isMine ? 'regular' : 'other',
                    onAction: onShiftClick ? () => onShiftClick('schedule', s.id, dateStr, isMine) : undefined,
                    actionLabel: isMine ? 'Acties' : 'Info',
                });
            });

        dayExceptions.forEach((ex) => {
            const isMine = myCaregiverIds.includes(ex.caregiver_id);
            const baseVariant: DayEvent['variant'] =
                ex.type === 'cancelled' ? 'cancelled' : ex.type === 'modified' ? 'modified' : 'added';
            const variant: DayEvent['variant'] =
                ex.type === 'cancelled' ? 'cancelled' : isMine ? baseVariant : 'other';
            const sublabel =
                ex.type === 'cancelled'
                    ? (ex.due_to_sickness ? 'Ziek gemeld' : 'Geannuleerd')
                    : ex.type === 'modified' ? 'Gewijzigd' : 'Extra';
            const canUnreportSick = ex.type === 'cancelled' && ex.due_to_sickness && isMine && !!onUnreportSick;
            const onAction = canUnreportSick
                ? () => onUnreportSick!(ex.id)
                : ex.type !== 'cancelled' && onShiftClick
                    ? () => onShiftClick('exception', ex.id, dateStr, isMine)
                    : undefined;
            const actionLabel = canUnreportSick ? 'Beter' : (isMine ? 'Acties' : 'Info');
            events.push({
                id: `e-${ex.id}`,
                label: ex.caregiver?.name ?? 'Onbekend',
                startTime: ex.start_time,
                endTime: ex.end_time,
                variant,
                sublabel,
                onAction,
                actionLabel,
            });
        });

        availabilitySlots
            .filter((slot) => {
                if (slot.status !== 'open') return false;
                if (slot.day_of_week !== null) {
                    if (slot.day_of_week !== dayIndex) return false;
                    if ((slot.claimed_dates ?? []).includes(dateStr)) return false;
                    return true;
                }
                return slot.date === dateStr;
            })
            .forEach((slot) => {
                events.push({
                    id: `av-${slot.id}`,
                    label: 'Beschikbaar',
                    startTime: slot.start_time,
                    endTime: slot.end_time,
                    variant: 'available',
                    sublabel: slot.notes ?? undefined,
                    onClaim: onClaimAvailability ? () => onClaimAvailability(slot, dateStr) : undefined,
                });
            });

        return events.sort((a, b) => a.startTime.localeCompare(b.startTime));
    }

    return (
        <div className="relative rounded-xl border bg-white shadow-sm dark:bg-gray-950">
            {/* Day headers */}
            <div className="grid grid-cols-7 border-b">
                {dayNamesShort.map((name) => (
                    <div key={name} className="p-2 text-center text-xs font-medium uppercase text-muted-foreground">
                        {name}
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-7">
                {days.map((date, i) => {
                    // day index in our 0-6 Monday-Sunday format
                    const dayIndex = i % 7;
                    const inMonth = date.getMonth() === monthStart.getMonth();
                    const isCurrent = isSameDay(date, today);
                    const events = getEventsForDay(date, dayIndex);
                    const visible = events.slice(0, 2);
                    const overflow = events.length - visible.length;
                    const dayKey = toLocalDateStr(date);
                    const isOpen = openDay === dayKey;
                    const weekIndex = Math.floor(i / 7);
                    const isLastTwoRows = weekIndex >= weeksCount - 2;
                    const isLastTwoCols = dayIndex >= 5;

                    return (
                        <div
                            key={i}
                            className={`relative min-h-[110px] border-l border-t p-1.5 ${!inMonth ? 'text-muted-foreground' : ''} ${events.length > 0 ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40' : ''} ${isOpen ? 'z-20 ring-2 ring-blue-300' : ''}`}
                            onClick={() => {
                                if (events.length > 0) {
                                    setOpenDay(isOpen ? null : dayKey);
                                }
                            }}
                        >
                            <div className="mb-1">
                                {isCurrent ? (
                                    <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-medium text-white">
                                        {date.getDate()}
                                    </span>
                                ) : (
                                    <span className={`text-sm ${inMonth ? '' : 'text-gray-300 dark:text-gray-600'}`}>
                                        {date.getDate()}
                                    </span>
                                )}
                            </div>

                            <div className="space-y-0.5 text-xs">
                                {visible.map((event) => (
                                    <div
                                        key={event.id}
                                        className={`truncate rounded px-1.5 py-0.5 ${chipStyles[event.variant]}`}
                                    >
                                        {formatTimeShort(event.startTime)}–{formatTimeShort(event.endTime)} {event.label}
                                    </div>
                                ))}
                                {overflow > 0 && (
                                    <div className="px-1.5 py-0.5 font-medium text-blue-600">
                                        + {overflow} meer
                                    </div>
                                )}
                            </div>

                            {isOpen && (
                                <div
                                    ref={popoverRef}
                                    className={`absolute z-30 w-64 rounded-xl border bg-white p-3 shadow-xl dark:bg-gray-900 ${isLastTwoRows ? 'bottom-full mb-1' : 'top-full mt-1'} ${isLastTwoCols ? 'right-0' : 'left-0'}`}
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <div className="mb-2 flex items-center justify-between">
                                        <div className="text-sm font-medium">
                                            {date.toLocaleDateString('nl-NL', {
                                                weekday: 'long',
                                                day: 'numeric',
                                                month: 'long',
                                            })}
                                        </div>
                                        <button
                                            className="text-gray-400 hover:text-gray-600"
                                            onClick={() => setOpenDay(null)}
                                        >
                                            ×
                                        </button>
                                    </div>

                                    <div className="space-y-1.5">
                                        {events.map((event) => (
                                            <div
                                                key={event.id}
                                                className={`rounded border-l-2 px-2 py-1 ${detailStyles[event.variant]}`}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="truncate text-xs font-medium">
                                                            {event.label}
                                                        </div>
                                                        <div className="text-[11px] text-muted-foreground">
                                                            {formatTime(event.startTime)} – {formatTime(event.endTime)}
                                                            {event.sublabel && <> · {event.sublabel}</>}
                                                        </div>
                                                    </div>
                                                    {event.onClaim && (
                                                        <button
                                                            className="shrink-0 rounded bg-purple-600 px-2 py-0.5 text-[10px] font-medium text-white hover:bg-purple-700"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                event.onClaim!();
                                                            }}
                                                        >
                                                            Claimen
                                                        </button>
                                                    )}
                                                    {event.onAction && (
                                                        <button
                                                            className="shrink-0 rounded bg-gray-200 px-2 py-0.5 text-[10px] font-medium text-gray-700 hover:bg-gray-300"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setOpenDay(null);
                                                                event.onAction!();
                                                            }}
                                                        >
                                                            {event.actionLabel ?? 'Acties'}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {onOpenWeek && (
                                        <button
                                            className="mt-3 w-full text-xs text-blue-600 hover:underline"
                                            onClick={() => {
                                                onOpenWeek(date);
                                                setOpenDay(null);
                                            }}
                                        >
                                            Open weekweergave →
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {/* Legend */}
            <div className="flex flex-wrap gap-4 border-t px-4 py-2 text-xs text-muted-foreground">
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-blue-100" />
                    Vast ingepland
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-green-100" />
                    Extra
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-amber-100" />
                    Gewijzigd
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-red-100" />
                    Geannuleerd
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-dashed border-purple-300 bg-purple-100" />
                    Beschikbaar
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded bg-gray-100" />
                    Collega
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-dashed border-orange-300 bg-orange-100" />
                    Aangeboden
                </div>
            </div>
        </div>
    );
}
