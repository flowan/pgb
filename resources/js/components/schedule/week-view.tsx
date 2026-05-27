import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { AvailabilitySlot, Schedule, ScheduleException } from '@/types';

const dayNamesShort = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
const HOUR_HEIGHT = 60;
const START_HOUR = 7;
const END_HOUR = 20;
const hours = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i);

interface WeekViewProps {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    availabilitySlots?: AvailabilitySlot[];
    weekStart: Date;
    onDeleteSchedule?: (id: number) => void;
    onDeleteException?: (id: number) => void;
    onDeleteAvailability?: (id: number) => void;
    onClaimAvailability?: (id: number) => void;
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
    variant: 'regular' | 'added' | 'modified' | 'cancelled' | 'available';
    onDelete?: () => void;
    onAction?: () => void;
    actionLabel?: string;
}

function EventCard({ event }: { event: EventBlock }) {
    const startMin = timeToMinutes(event.startTime);
    const endMin = timeToMinutes(event.endTime);
    const top = (startMin - START_HOUR * 60) * (HOUR_HEIGHT / 60);
    const height = Math.max((endMin - startMin) * (HOUR_HEIGHT / 60), 24);

    const styles = {
        regular: 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800 dark:text-blue-200',
        added: 'bg-green-50 border-green-300 text-green-900 dark:bg-green-950/40 dark:border-green-800 dark:text-green-200',
        modified: 'bg-amber-50 border-amber-300 text-amber-900 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-200',
        cancelled: 'bg-red-50 border-red-200 text-red-400 line-through dark:bg-red-950/30 dark:border-red-800 dark:text-red-400',
        available: 'bg-purple-50 border-purple-300 border-dashed text-purple-900 dark:bg-purple-950/40 dark:border-purple-700 dark:text-purple-200',
    };

    const timeStyles = {
        regular: 'text-blue-600 dark:text-blue-400',
        added: 'text-green-600 dark:text-green-400',
        modified: 'text-amber-600 dark:text-amber-400',
        cancelled: 'text-red-400 dark:text-red-500',
        available: 'text-purple-600 dark:text-purple-400',
    };

    return (
        <div
            className={`group absolute left-1 right-1 overflow-hidden rounded-md border px-2 py-1 text-xs ${styles[event.variant]}`}
            style={{ top: `${top}px`, height: `${height}px` }}
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
    weekStart,
    onDeleteSchedule,
    onDeleteException,
    onDeleteAvailability,
    onClaimAvailability,
}: WeekViewProps) {
    const days = Array.from({ length: 7 }, (_, i) => {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + i);
        return date;
    });

    function getEventsForDay(dayIndex: number, date: Date): EventBlock[] {
        const dateStr = date.toISOString().split('T')[0];
        const dayExceptions = exceptions.filter((ex) => ex.date === dateStr);

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
                events.push({
                    id: `s-${s.id}`,
                    label: s.caregiver?.name ?? 'Onbekend',
                    startTime: s.start_time,
                    endTime: s.end_time,
                    variant: 'regular',
                    onDelete: onDeleteSchedule ? () => onDeleteSchedule(s.id) : undefined,
                });
            });

        dayExceptions.forEach((ex) => {
            if (ex.type === 'cancelled') {
                events.push({
                    id: `c-${ex.id}`,
                    label: ex.caregiver?.name ?? 'Onbekend',
                    sublabel: 'Geannuleerd',
                    startTime: ex.start_time,
                    endTime: ex.end_time,
                    variant: 'cancelled',
                    onDelete: onDeleteException ? () => onDeleteException(ex.id) : undefined,
                });
            } else if (ex.type === 'modified') {
                events.push({
                    id: `m-${ex.id}`,
                    label: ex.caregiver?.name ?? 'Onbekend',
                    sublabel: 'Gewijzigd',
                    startTime: ex.start_time,
                    endTime: ex.end_time,
                    variant: 'modified',
                    onDelete: onDeleteException ? () => onDeleteException(ex.id) : undefined,
                });
            } else if (ex.type === 'added') {
                events.push({
                    id: `a-${ex.id}`,
                    label: ex.caregiver?.name ?? 'Onbekend',
                    sublabel: 'Extra afspraak',
                    startTime: ex.start_time,
                    endTime: ex.end_time,
                    variant: 'added',
                    onDelete: onDeleteException ? () => onDeleteException(ex.id) : undefined,
                });
            }
        });

        availabilitySlots
            .filter((slot) => {
                if (slot.status !== 'open') return false;
                if (slot.day_of_week !== null) return slot.day_of_week === dayIndex;
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
                    onAction: onClaimAvailability ? () => onClaimAvailability(slot.id) : undefined,
                    actionLabel: 'Claimen',
                });
            });

        return events;
    }

    return (
        <div className="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-950">
            {/* Day headers */}
            <div className="grid grid-cols-[64px_repeat(7,1fr)] border-b">
                <div />
                {days.map((date, i) => {
                    const today = isToday(date);
                    return (
                        <div
                            key={i}
                            className={`border-l p-2 text-center ${today ? 'bg-blue-50/50 dark:bg-blue-950/20' : ''}`}
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

            {/* Time grid */}
            <div className="max-h-[600px] overflow-y-auto">
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
                                {events.map((event) => (
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
            </div>
        </div>
    );
}
