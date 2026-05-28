import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { AvailabilitySlot, Schedule, ScheduleException, ShiftTakeoverOffer } from '@/types';

const dayNamesShort = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
const HOUR_HEIGHT = 60;
const START_HOUR = 7;
const END_HOUR = 20;
const hours = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i);

function toLocalDateStr(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

interface WeekViewProps {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    availabilitySlots?: AvailabilitySlot[];
    takeoverOffers?: ShiftTakeoverOffer[];
    weekStart: Date;
    onDeleteSchedule?: (id: number) => void;
    onDeleteException?: (id: number) => void;
    onDeleteAvailability?: (id: number) => void;
    onClaimAvailability?: (slot: AvailabilitySlot, date: string) => void;
    onUnreportSick?: (exceptionId: number) => void;
    onClaimTakeover?: (offerId: number) => void;
    myCaregiverIds?: number[];
    onShiftClick?: (kind: 'schedule' | 'exception', id: number, date: string, isMine: boolean) => void;
}

function timeToMinutes(time: string): number {
    const [h, m] = time.substring(0, 5).split(':').map(Number);
    return h * 60 + m;
}

function formatTime(time: string): string {
    return time.substring(0, 5);
}

interface EventBlock {
    id: string;
    label: string;
    sublabel?: string;
    startTime: string;
    endTime: string;
    variant: 'regular' | 'added' | 'modified' | 'cancelled' | 'available' | 'other' | 'offered';
    onDelete?: () => void;
    onAction?: () => void;
    actionLabel?: string;
}

interface PositionedEvent extends EventBlock {
    columnIndex: number;
    columnsInGroup: number;
}

function positionEvents(events: EventBlock[]): PositionedEvent[] {
    const sorted = [...events].sort(
        (a, b) => timeToMinutes(a.startTime) - timeToMinutes(b.startTime),
    );

    // Group events that transitively overlap with each other
    const groups: EventBlock[][] = [];
    for (const event of sorted) {
        const startMin = timeToMinutes(event.startTime);
        const endMin = timeToMinutes(event.endTime);
        const overlapping = groups.find((group) =>
            group.some((e) => {
                const eStart = timeToMinutes(e.startTime);
                const eEnd = timeToMinutes(e.endTime);
                return startMin < eEnd && endMin > eStart;
            }),
        );
        if (overlapping) {
            overlapping.push(event);
        } else {
            groups.push([event]);
        }
    }

    // Within each group, assign columns greedily
    const positioned: PositionedEvent[] = [];
    for (const group of groups) {
        const columns: EventBlock[][] = [];
        const eventColumn = new Map<string, number>();
        for (const event of group) {
            const startMin = timeToMinutes(event.startTime);
            let col = columns.findIndex((colEvents) => {
                const last = colEvents[colEvents.length - 1];
                return startMin >= timeToMinutes(last.endTime);
            });
            if (col === -1) {
                col = columns.length;
                columns.push([]);
            }
            columns[col].push(event);
            eventColumn.set(event.id, col);
        }
        for (const event of group) {
            positioned.push({
                ...event,
                columnIndex: eventColumn.get(event.id)!,
                columnsInGroup: columns.length,
            });
        }
    }

    return positioned;
}

function EventCard({ event }: { event: PositionedEvent }) {
    const startMin = timeToMinutes(event.startTime);
    const endMin = timeToMinutes(event.endTime);
    const top = (startMin - START_HOUR * 60) * (HOUR_HEIGHT / 60);
    const height = Math.max((endMin - startMin) * (HOUR_HEIGHT / 60), 24);

    const widthPercent = 100 / event.columnsInGroup;
    const leftPercent = event.columnIndex * widthPercent;

    const styles = {
        regular: 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800 dark:text-blue-200',
        added: 'bg-green-50 border-green-300 text-green-900 dark:bg-green-950/40 dark:border-green-800 dark:text-green-200',
        modified: 'bg-amber-50 border-amber-300 text-amber-900 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-200',
        cancelled: 'bg-red-50 border-red-200 text-red-400 line-through dark:bg-red-950/30 dark:border-red-800 dark:text-red-400',
        available: 'bg-purple-50 border-purple-300 border-dashed text-purple-900 dark:bg-purple-950/40 dark:border-purple-700 dark:text-purple-200',
        other: 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-900/40 dark:border-gray-700 dark:text-gray-400',
        offered: 'bg-orange-50 border-orange-300 border-dashed text-orange-900 dark:bg-orange-950/40 dark:border-orange-700 dark:text-orange-200',
    };

    const timeStyles = {
        regular: 'text-blue-600 dark:text-blue-400',
        added: 'text-green-600 dark:text-green-400',
        modified: 'text-amber-600 dark:text-amber-400',
        cancelled: 'text-red-400 dark:text-red-500',
        available: 'text-purple-600 dark:text-purple-400',
        other: 'text-gray-400 dark:text-gray-500',
        offered: 'text-orange-600 dark:text-orange-400',
    };

    return (
        <div
            className={`group absolute overflow-hidden rounded-md border px-2 py-1 text-xs ${styles[event.variant]}`}
            style={{
                top: `${top}px`,
                height: `${height}px`,
                left: `calc(${leftPercent}% + 2px)`,
                width: `calc(${widthPercent}% - 4px)`,
            }}
        >
            <div className="flex items-start justify-between">
                <div className="min-w-0 flex-1">
                    <div className="truncate font-medium">{event.label}</div>
                    <div className={`text-[11px] ${timeStyles[event.variant]}`}>
                        {formatTime(event.startTime)} – {formatTime(event.endTime)}
                    </div>
                    {event.sublabel && height > 50 && (
                        <div className="mt-0.5 truncate text-[11px] opacity-70">
                            {event.sublabel}
                        </div>
                    )}
                </div>
                {event.onDelete && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-4 w-4 shrink-0 p-0 opacity-0 group-hover:opacity-100"
                        onClick={(e) => {
                            e.stopPropagation();
                            event.onDelete!();
                        }}
                    >
                        <Trash2 className="h-3 w-3" />
                    </Button>
                )}
                {event.onAction && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-5 shrink-0 px-1.5 text-[10px] font-medium text-purple-700 opacity-0 group-hover:opacity-100"
                        onClick={(e) => {
                            e.stopPropagation();
                            event.onAction!();
                        }}
                    >
                        {event.actionLabel ?? 'Claimen'}
                    </Button>
                )}
            </div>
        </div>
    );
}

function isToday(date: Date): boolean {
    const now = new Date();
    return (
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate()
    );
}

function NowIndicator() {
    const now = new Date();
    const minutes = now.getHours() * 60 + now.getMinutes();
    const top = (minutes - START_HOUR * 60) * (HOUR_HEIGHT / 60);

    if (top < 0 || top > hours.length * HOUR_HEIGHT) return null;

    return (
        <div className="pointer-events-none absolute left-0 right-0 z-10" style={{ top: `${top}px` }}>
            <div className="relative h-0.5 bg-red-500">
                <div className="absolute -left-[5px] -top-[4px] h-2.5 w-2.5 rounded-full bg-red-500" />
            </div>
        </div>
    );
}

export function WeekView({
    schedules,
    exceptions,
    availabilitySlots = [],
    takeoverOffers = [],
    weekStart,
    onDeleteSchedule,
    onDeleteException,
    onDeleteAvailability,
    onClaimAvailability,
    onUnreportSick,
    onClaimTakeover,
    myCaregiverIds = [],
    onShiftClick,
}: WeekViewProps) {
    const days = Array.from({ length: 7 }, (_, i) => {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + i);
        return date;
    });

    function getEventsForDay(dayIndex: number, date: Date): EventBlock[] {
        const dateStr = toLocalDateStr(date);
        const dayExceptions = exceptions.filter((ex) => ex.date === dateStr);
        const dayOffers = (takeoverOffers ?? []).filter((o) => o.date === dateStr);

        const cancelledOrModifiedIds = new Set(
            dayExceptions
                .filter((ex) => ex.type === 'cancelled' || ex.type === 'modified')
                .map((ex) => ex.schedule_id)
                .filter((id): id is number => id !== null),
        );

        const events: EventBlock[] = [];

        schedules
            .filter((s) => s.day_of_week === dayIndex && !cancelledOrModifiedIds.has(s.id))
            .forEach((s) => {
                const isMine = myCaregiverIds.includes(s.caregiver_id);
                const offer = dayOffers.find((o) => o.schedule_id === s.id);
                if (offer) {
                    const offerByMe = myCaregiverIds.includes(offer.offered_by_caregiver_id);
                    events.push({
                        id: `s-${s.id}`,
                        label: s.caregiver?.name ?? 'Onbekend',
                        sublabel: offerByMe ? 'Aangeboden voor overname' : 'Wordt aangeboden',
                        startTime: s.start_time,
                        endTime: s.end_time,
                        variant: 'offered',
                        onAction: !offerByMe && onClaimTakeover
                            ? () => onClaimTakeover(offer.id)
                            : (onShiftClick ? () => onShiftClick('schedule', s.id, dateStr, isMine) : undefined),
                        actionLabel: !offerByMe && onClaimTakeover ? 'Claimen' : (isMine ? 'Acties' : 'Info'),
                    });
                    return;
                }
                events.push({
                    id: `s-${s.id}`,
                    label: s.caregiver?.name ?? 'Onbekend',
                    startTime: s.start_time,
                    endTime: s.end_time,
                    variant: isMine ? 'regular' : 'other',
                    onDelete: isMine && onDeleteSchedule ? () => onDeleteSchedule(s.id) : undefined,
                    onAction: onShiftClick ? () => onShiftClick('schedule', s.id, dateStr, isMine) : undefined,
                    actionLabel: isMine ? 'Acties' : 'Info',
                });
            });

        dayExceptions.forEach((ex) => {
            const isMine = myCaregiverIds.includes(ex.caregiver_id);
            if (ex.type === 'cancelled') {
                const canUnreportSick = isMine && ex.due_to_sickness && !!onUnreportSick;
                events.push({
                    id: `c-${ex.id}`,
                    label: ex.caregiver?.name ?? 'Onbekend',
                    sublabel: ex.due_to_sickness ? 'Ziek gemeld' : 'Geannuleerd',
                    startTime: ex.start_time,
                    endTime: ex.end_time,
                    variant: 'cancelled',
                    onDelete: isMine && onDeleteException ? () => onDeleteException(ex.id) : undefined,
                    onAction: canUnreportSick ? () => onUnreportSick!(ex.id) : undefined,
                    actionLabel: canUnreportSick ? 'Beter' : undefined,
                });
            } else if (ex.type === 'modified') {
                events.push({
                    id: `m-${ex.id}`,
                    label: ex.caregiver?.name ?? 'Onbekend',
                    sublabel: 'Gewijzigd',
                    startTime: ex.start_time,
                    endTime: ex.end_time,
                    variant: isMine ? 'modified' : 'other',
                    onDelete: isMine && onDeleteException ? () => onDeleteException(ex.id) : undefined,
                    onAction: onShiftClick ? () => onShiftClick('exception', ex.id, dateStr, isMine) : undefined,
                    actionLabel: isMine ? 'Acties' : 'Info',
                });
            } else if (ex.type === 'added') {
                events.push({
                    id: `a-${ex.id}`,
                    label: ex.caregiver?.name ?? 'Onbekend',
                    sublabel: 'Extra afspraak',
                    startTime: ex.start_time,
                    endTime: ex.end_time,
                    variant: isMine ? 'added' : 'other',
                    onDelete: isMine && onDeleteException ? () => onDeleteException(ex.id) : undefined,
                    onAction: onShiftClick ? () => onShiftClick('exception', ex.id, dateStr, isMine) : undefined,
                    actionLabel: isMine ? 'Acties' : 'Info',
                });
            }
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
                    sublabel: slot.notes ?? undefined,
                    startTime: slot.start_time,
                    endTime: slot.end_time,
                    variant: 'available',
                    onDelete: onDeleteAvailability ? () => onDeleteAvailability(slot.id) : undefined,
                    onAction: onClaimAvailability ? () => onClaimAvailability(slot, dateStr) : undefined,
                    actionLabel: 'Claimen',
                });
            });

        return events;
    }

    return (
        <div className="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-950">
            {/* Time grid (headers sticky inside for alignment with content) */}
            <div className="max-h-[660px] overflow-y-auto">
                <div className="grid grid-cols-[64px_repeat(7,1fr)]">
                    {/* Day headers */}
                    <div className="sticky top-0 z-10 bg-white dark:bg-gray-950" />
                    {days.map((date, i) => {
                        const today = isToday(date);
                        return (
                            <div
                                key={`h-${i}`}
                                className={`sticky top-0 z-10 border-b border-l p-2 text-center ${today ? 'bg-blue-50/50 dark:bg-blue-950/20' : 'bg-white dark:bg-gray-950'}`}
                            >
                                <div className={`text-xs uppercase ${today ? 'font-medium text-blue-600 dark:text-blue-400' : 'text-muted-foreground'}`}>
                                    {dayNamesShort[i]}
                                </div>
                                <div
                                    className={`mx-auto mt-0.5 text-sm font-medium ${
                                        today
                                            ? 'flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white'
                                            : i >= 5
                                              ? 'text-muted-foreground'
                                              : ''
                                    }`}
                                >
                                    {date.getDate()}
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className="relative grid grid-cols-[64px_repeat(7,1fr)]">
                    {/* Time labels */}
                    <div>
                        {hours.map((hour) => (
                            <div
                                key={hour}
                                className="flex items-start justify-end border-b pr-2 text-xs text-muted-foreground"
                                style={{ height: `${HOUR_HEIGHT}px` }}
                            >
                                {String(hour).padStart(2, '0')}:00
                            </div>
                        ))}
                    </div>

                    {/* Day columns */}
                    {days.map((date, dayIndex) => {
                        const today = isToday(date);
                        const events = getEventsForDay(dayIndex, date);

                        return (
                            <div
                                key={dayIndex}
                                className={`relative border-l ${today ? 'bg-blue-50/30 dark:bg-blue-950/10' : ''}`}
                            >
                                {/* Hour grid lines */}
                                {hours.map((hour) => (
                                    <div
                                        key={hour}
                                        className="border-b"
                                        style={{ height: `${HOUR_HEIGHT}px` }}
                                    />
                                ))}

                                {/* Events */}
                                {positionEvents(events).map((event) => (
                                    <EventCard key={event.id} event={event} />
                                ))}

                                {/* Now indicator */}
                                {today && <NowIndicator />}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Legend */}
            <div className="flex gap-4 border-t px-4 py-2 text-xs text-muted-foreground">
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-blue-200 bg-blue-50" />
                    Vast ingepland
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-green-300 bg-green-50" />
                    Extra afspraak
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-amber-300 bg-amber-50" />
                    Gewijzigd
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-red-200 bg-red-50" />
                    Geannuleerd
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-dashed border-purple-300 bg-purple-50" />
                    Beschikbaar
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-gray-200 bg-gray-50" />
                    Collega
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-dashed border-orange-300 bg-orange-50" />
                    Aangeboden
                </div>
            </div>
        </div>
    );
}
