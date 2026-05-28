import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { WeekView } from '@/components/schedule/week-view';
import type { AvailabilitySlot, Caregiver, Client, Schedule, ScheduleException } from '@/types';

interface ClientWithSchedule extends Client {
    schedules: Schedule[];
    schedule_exceptions: ScheduleException[];
    availability_slots: AvailabilitySlot[];
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
}: {
    clients: ClientWithSchedule[];
}) {
    const [weekStart, setWeekStart] = useState(() => getMonday(new Date()));

    function claimSlot(slotId: number) {
        router.post(`/availability-slots/${slotId}/claim`);
    }

    function prevWeek() {
        setWeekStart((prev) => {
            const d = new Date(prev);
            d.setDate(d.getDate() - 7);
            return d;
        });
    }

    function nextWeek() {
        setWeekStart((prev) => {
            const d = new Date(prev);
            d.setDate(d.getDate() + 7);
            return d;
        });
    }

    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);

    // Merge all clients' data into single arrays, replacing the caregiver name
    // with the client name so the user sees which client each appointment is for.
    const allSchedules: Schedule[] = clients.flatMap((client) =>
        (client.schedules ?? []).map((s) => ({
            ...s,
            caregiver: { ...(s.caregiver as Caregiver), name: client.name },
        })),
    );

    const allExceptions: ScheduleException[] = clients.flatMap((client) =>
        (client.schedule_exceptions ?? []).map((ex) => ({
            ...ex,
            caregiver: { ...(ex.caregiver as Caregiver), name: client.name },
        })),
    );

    const allAvailabilitySlots: AvailabilitySlot[] = clients.flatMap((client) =>
        (client.availability_slots ?? []).map((slot) => ({
            ...slot,
            notes: client.name + (slot.notes ? ` — ${slot.notes}` : ''),
        })),
    );

    return (
        <>
            <Head title="Mijn rooster" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Mijn rooster</h1>

                {/* Week navigation */}
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setWeekStart(getMonday(new Date()))}
                    >
                        Vandaag
                    </Button>
                    <div className="flex items-center gap-1">
                        <Button variant="ghost" size="sm" onClick={prevWeek}>
                            <ChevronLeft />
                        </Button>
                        <Button variant="ghost" size="sm" onClick={nextWeek}>
                            <ChevronRight />
                        </Button>
                    </div>
                    <span className="text-lg font-medium">
                        {weekStart.toLocaleDateString('nl-NL', {
                            day: 'numeric',
                            month: 'long',
                        })}{' '}
                        –{' '}
                        {weekEnd.toLocaleDateString('nl-NL', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </span>
                </div>

                {clients.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Je bent nog niet gekoppeld aan een cliënt.
                    </div>
                ) : (
                    <WeekView
                        schedules={allSchedules}
                        exceptions={allExceptions}
                        availabilitySlots={allAvailabilitySlots}
                        weekStart={weekStart}
                        onClaimAvailability={claimSlot}
                    />
                )}
            </div>
        </>
    );
}

CaregiverScheduleIndex.layout = {
    breadcrumbs: [{ title: 'Mijn rooster', href: '/my-schedule' }],
};
