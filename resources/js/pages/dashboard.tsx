import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ProgressBar } from '@/components/budget/progress-bar';
import type { Client, Schedule } from '@/types';

function getTodayDayOfWeek(): number {
    const day = new Date().getDay();
    // Convert from JS (0=Sun) to our format (0=Mon)
    return day === 0 ? 6 : day - 1;
}

function formatTime(time: string): string {
    return time.substring(0, 5);
}

export default function Dashboard({ clients }: { clients: Client[] }) {
    const todayDow = getTodayDayOfWeek();

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}
        >
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Dashboard</h1>

                {clients.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        <p className="mb-4">
                            Welkom! Voeg je eerste client toe om te beginnen.
                        </p>
                        <Button asChild>
                            <Link href="/clients/create">
                                <Plus />
                                Client toevoegen
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2">
                        {clients.map((client) => {
                            const todaySchedules = (
                                (client as Client & { schedules?: Schedule[] }).schedules ?? []
                            ).filter((s) => s.day_of_week === todayDow);

                            return (
                                <Card key={client.id}>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            <Link
                                                href={`/clients/${client.id}`}
                                                className="hover:underline"
                                            >
                                                {client.name}
                                            </Link>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {/* Budget overview */}
                                        {client.budget_categories &&
                                            client.budget_categories.length > 0 && (
                                                <div className="space-y-2">
                                                    <h3 className="text-sm font-medium">Budget</h3>
                                                    {client.budget_categories.map((cat) => (
                                                        <ProgressBar
                                                            key={cat.id}
                                                            label={cat.name}
                                                            allocated={Number(cat.allocated_amount)}
                                                            spent={Number(cat.spent_amount)}
                                                        />
                                                    ))}
                                                </div>
                                            )}

                                        {/* Today's schedule */}
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Planning vandaag
                                            </h3>
                                            {todaySchedules.length > 0 ? (
                                                <ul className="space-y-1 text-sm">
                                                    {todaySchedules.map((s) => (
                                                        <li
                                                            key={s.id}
                                                            className="flex items-center justify-between rounded bg-muted/50 px-3 py-1.5"
                                                        >
                                                            <span>
                                                                {s.caregiver?.name}
                                                            </span>
                                                            <span className="text-muted-foreground">
                                                                {formatTime(s.start_time)} -{' '}
                                                                {formatTime(s.end_time)}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    Geen afspraken vandaag
                                                </p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
