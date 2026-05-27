import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { WeekView } from '@/components/schedule/week-view';
import type { Client, Schedule, ScheduleException } from '@/types';

interface ClientWithSchedule extends Client {
    schedules: Schedule[];
    schedule_exceptions: ScheduleException[];
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

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Mijn rooster', href: '/my-schedule' }]}
        >
            <Head title="Mijn rooster" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Mijn rooster</h1>

                {/* Week navigation */}
                <div className="flex items-center justify-center gap-4">
                    <Button variant="outline" size="sm" onClick={prevWeek}>
                        <ChevronLeft />
                    </Button>
                    <span className="text-sm font-medium">
                        {weekStart.toLocaleDateString('nl-NL', {
                            day: 'numeric',
                            month: 'long',
                        })}{' '}
                        -{' '}
                        {weekEnd.toLocaleDateString('nl-NL', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </span>
                    <Button variant="outline" size="sm" onClick={nextWeek}>
                        <ChevronRight />
                    </Button>
                </div>

                {clients.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Je bent nog niet gekoppeld aan een client.
                    </div>
                ) : (
                    clients.map((client) => (
                        <Card key={client.id}>
                            <CardHeader>
                                <CardTitle className="text-base">{client.name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <WeekView
                                    schedules={client.schedules ?? []}
                                    exceptions={client.schedule_exceptions ?? []}
                                    weekStart={weekStart}
                                />
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
