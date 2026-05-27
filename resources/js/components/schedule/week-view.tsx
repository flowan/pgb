import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Schedule, ScheduleException } from '@/types';

const dayNames = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

interface WeekViewProps {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    weekStart: Date;
    onDeleteSchedule?: (id: number) => void;
    onDeleteException?: (id: number) => void;
}

function formatTime(time: string): string {
    return time.substring(0, 5);
}

export function WeekView({
    schedules,
    exceptions,
    weekStart,
    onDeleteSchedule,
    onDeleteException,
}: WeekViewProps) {
    const days = Array.from({ length: 7 }, (_, i) => {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + i);
        return date;
    });

    return (
        <div className="grid grid-cols-7 gap-2">
            {days.map((date, dayIndex) => {
                // day_of_week: 0=Monday ... 6=Sunday
                const dayOfWeek = dayIndex;
                const dateStr = date.toISOString().split('T')[0];

                // Get exceptions for this day
                const dayExceptions = exceptions.filter(
                    (ex) => ex.date === dateStr,
                );

                // IDs of schedules that are cancelled or modified on this date
                const cancelledScheduleIds = new Set(
                    dayExceptions
                        .filter((ex) => ex.type === 'cancelled' || ex.type === 'modified')
                        .map((ex) => ex.schedule_id)
                        .filter((id): id is number => id !== null),
                );

                // Regular schedules for this day, excluding cancelled/modified ones
                const activeSchedules = schedules.filter(
                    (s) => s.day_of_week === dayOfWeek && !cancelledScheduleIds.has(s.id),
                );

                // Modifications (yellow)
                const modifications = dayExceptions.filter((ex) => ex.type === 'modified');

                // Additions (green)
                const additions = dayExceptions.filter((ex) => ex.type === 'added');

                // Cancellations (red)
                const cancellations = dayExceptions.filter((ex) => ex.type === 'cancelled');

                return (
                    <div key={dayIndex} className="min-h-[120px] rounded-lg border p-2">
                        <div className="mb-2 text-center text-xs font-medium">
                            {dayNames[dayIndex]}
                            <div className="text-muted-foreground">
                                {date.toLocaleDateString('nl-NL', {
                                    day: 'numeric',
                                    month: 'short',
                                })}
                            </div>
                        </div>

                        <div className="space-y-1">
                            {/* Regular schedules (blue) */}
                            {activeSchedules.map((schedule) => (
                                <div
                                    key={`s-${schedule.id}`}
                                    className="flex items-start justify-between rounded bg-blue-100 p-1 text-xs dark:bg-blue-900/30"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {schedule.caregiver?.name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {formatTime(schedule.start_time)} -{' '}
                                            {formatTime(schedule.end_time)}
                                        </div>
                                    </div>
                                    {onDeleteSchedule && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-5 w-5 p-0"
                                            onClick={() => onDeleteSchedule(schedule.id)}
                                        >
                                            <Trash2 className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                            ))}

                            {/* Modifications (yellow) */}
                            {modifications.map((ex) => (
                                <div
                                    key={`m-${ex.id}`}
                                    className="flex items-start justify-between rounded bg-yellow-100 p-1 text-xs dark:bg-yellow-900/30"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {ex.caregiver?.name}{' '}
                                            <span className="font-normal">(gewijzigd)</span>
                                        </div>
                                        <div className="text-muted-foreground">
                                            {formatTime(ex.start_time)} -{' '}
                                            {formatTime(ex.end_time)}
                                        </div>
                                    </div>
                                    {onDeleteException && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-5 w-5 p-0"
                                            onClick={() => onDeleteException(ex.id)}
                                        >
                                            <Trash2 className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                            ))}

                            {/* Additions (green) */}
                            {additions.map((ex) => (
                                <div
                                    key={`a-${ex.id}`}
                                    className="flex items-start justify-between rounded bg-green-100 p-1 text-xs dark:bg-green-900/30"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {ex.caregiver?.name}{' '}
                                            <span className="font-normal">(extra)</span>
                                        </div>
                                        <div className="text-muted-foreground">
                                            {formatTime(ex.start_time)} -{' '}
                                            {formatTime(ex.end_time)}
                                        </div>
                                    </div>
                                    {onDeleteException && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-5 w-5 p-0"
                                            onClick={() => onDeleteException(ex.id)}
                                        >
                                            <Trash2 className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                            ))}

                            {/* Cancellations (red, strikethrough) */}
                            {cancellations.map((ex) => (
                                <div
                                    key={`c-${ex.id}`}
                                    className="flex items-start justify-between rounded bg-red-100 p-1 text-xs line-through dark:bg-red-900/30"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {ex.caregiver?.name}{' '}
                                            <span className="font-normal">(geannuleerd)</span>
                                        </div>
                                    </div>
                                    {onDeleteException && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-5 w-5 p-0"
                                            onClick={() => onDeleteException(ex.id)}
                                        >
                                            <Trash2 className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
