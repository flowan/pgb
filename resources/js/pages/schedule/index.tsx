import { Head, useForm, router, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { WeekView } from '@/components/schedule/week-view';
import type { Caregiver, Client, Schedule, ScheduleException } from '@/types';

const dayOptions = [
    { value: '0', label: 'Maandag' },
    { value: '1', label: 'Dinsdag' },
    { value: '2', label: 'Woensdag' },
    { value: '3', label: 'Donderdag' },
    { value: '4', label: 'Vrijdag' },
    { value: '5', label: 'Zaterdag' },
    { value: '6', label: 'Zondag' },
];

function getMonday(date: Date): Date {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff);
    d.setHours(0, 0, 0, 0);
    return d;
}

export default function ScheduleIndex({
    schedules,
    exceptions,
    caregivers,
    client,
}: {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    caregivers: Caregiver[];
    client: Client;
}) {
    const [weekStart, setWeekStart] = useState(() => getMonday(new Date()));
    const [showScheduleForm, setShowScheduleForm] = useState(false);
    const [showExceptionForm, setShowExceptionForm] = useState(false);

    const scheduleForm = useForm({
        caregiver_id: '',
        day_of_week: '',
        start_time: '',
        end_time: '',
        notes: '',
    });

    const exceptionForm = useForm({
        schedule_id: '',
        caregiver_id: '',
        date: '',
        start_time: '',
        end_time: '',
        type: '',
        notes: '',
    });

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

    function submitSchedule(e: React.FormEvent) {
        e.preventDefault();
        scheduleForm.post(`/clients/${client.id}/schedules`, {
            onSuccess: () => {
                scheduleForm.reset();
                setShowScheduleForm(false);
            },
        });
    }

    function submitException(e: React.FormEvent) {
        e.preventDefault();
        const data = {
            ...exceptionForm.data,
            schedule_id: exceptionForm.data.schedule_id || null,
        };
        router.post(`/clients/${client.id}/schedule-exceptions`, data, {
            onSuccess: () => {
                exceptionForm.reset();
                setShowExceptionForm(false);
            },
        });
    }

    function deleteSchedule(id: number) {
        router.delete(`/clients/${client.id}/schedules/${id}`);
    }

    function deleteException(id: number) {
        router.delete(`/clients/${client.id}/schedule-exceptions/${id}`);
    }

    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Cliënten', href: '/clients' },
                { title: client.name, href: `/clients/${client.id}` },
                { title: 'Planning', href: `/clients/${client.id}/schedule` },
            ]}
        >
            <Head title={`Planning - ${client.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/clients/${client.id}`}>
                            <ArrowLeft />
                            Terug naar cliënt
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">
                        Planning van {client.name}
                    </h1>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setShowScheduleForm(!showScheduleForm)}
                        >
                            <Plus />
                            Vast moment toevoegen
                        </Button>
                        <Button
                            onClick={() => setShowExceptionForm(!showExceptionForm)}
                        >
                            <Plus />
                            Afwijking/afspraak
                        </Button>
                    </div>
                </div>

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

                {showScheduleForm && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Vast moment toevoegen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitSchedule} className="flex flex-col gap-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="sch-caregiver">Zorgverlener</Label>
                                        <Select
                                            value={scheduleForm.data.caregiver_id}
                                            onValueChange={(val) =>
                                                scheduleForm.setData('caregiver_id', val)
                                            }
                                        >
                                            <SelectTrigger id="sch-caregiver">
                                                <SelectValue placeholder="Selecteer zorgverlener" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {caregivers.map((cg) => (
                                                    <SelectItem key={cg.id} value={String(cg.id)}>
                                                        {cg.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {scheduleForm.errors.caregiver_id && (
                                            <p className="text-sm text-red-500">
                                                {scheduleForm.errors.caregiver_id}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sch-day">Dag</Label>
                                        <Select
                                            value={scheduleForm.data.day_of_week}
                                            onValueChange={(val) =>
                                                scheduleForm.setData('day_of_week', val)
                                            }
                                        >
                                            <SelectTrigger id="sch-day">
                                                <SelectValue placeholder="Selecteer dag" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {dayOptions.map((opt) => (
                                                    <SelectItem key={opt.value} value={opt.value}>
                                                        {opt.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {scheduleForm.errors.day_of_week && (
                                            <p className="text-sm text-red-500">
                                                {scheduleForm.errors.day_of_week}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sch-start">Starttijd</Label>
                                        <Input
                                            id="sch-start"
                                            type="time"
                                            value={scheduleForm.data.start_time}
                                            onChange={(e) =>
                                                scheduleForm.setData('start_time', e.target.value)
                                            }
                                        />
                                        {scheduleForm.errors.start_time && (
                                            <p className="text-sm text-red-500">
                                                {scheduleForm.errors.start_time}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sch-end">Eindtijd</Label>
                                        <Input
                                            id="sch-end"
                                            type="time"
                                            value={scheduleForm.data.end_time}
                                            onChange={(e) =>
                                                scheduleForm.setData('end_time', e.target.value)
                                            }
                                        />
                                        {scheduleForm.errors.end_time && (
                                            <p className="text-sm text-red-500">
                                                {scheduleForm.errors.end_time}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="sch-notes">Notities</Label>
                                    <Input
                                        id="sch-notes"
                                        value={scheduleForm.data.notes}
                                        onChange={(e) =>
                                            scheduleForm.setData('notes', e.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={scheduleForm.processing}>
                                        Opslaan
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setShowScheduleForm(false)}
                                    >
                                        Annuleren
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {showExceptionForm && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Afwijking/afspraak toevoegen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitException} className="flex flex-col gap-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="ex-type">Type</Label>
                                        <Select
                                            value={exceptionForm.data.type}
                                            onValueChange={(val) =>
                                                exceptionForm.setData('type', val)
                                            }
                                        >
                                            <SelectTrigger id="ex-type">
                                                <SelectValue placeholder="Selecteer type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="cancelled">Geannuleerd</SelectItem>
                                                <SelectItem value="modified">Gewijzigd</SelectItem>
                                                <SelectItem value="added">Extra</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {exceptionForm.errors.type && (
                                            <p className="text-sm text-red-500">
                                                {exceptionForm.errors.type}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="ex-schedule">Vast moment (optioneel)</Label>
                                        <Select
                                            value={exceptionForm.data.schedule_id}
                                            onValueChange={(val) =>
                                                exceptionForm.setData('schedule_id', val)
                                            }
                                        >
                                            <SelectTrigger id="ex-schedule">
                                                <SelectValue placeholder="Geen (los)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {schedules.map((s) => (
                                                    <SelectItem key={s.id} value={String(s.id)}>
                                                        {s.caregiver?.name} -{' '}
                                                        {dayOptions[s.day_of_week]?.label}{' '}
                                                        {s.start_time.substring(0, 5)}-
                                                        {s.end_time.substring(0, 5)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="ex-caregiver">Zorgverlener</Label>
                                        <Select
                                            value={exceptionForm.data.caregiver_id}
                                            onValueChange={(val) =>
                                                exceptionForm.setData('caregiver_id', val)
                                            }
                                        >
                                            <SelectTrigger id="ex-caregiver">
                                                <SelectValue placeholder="Selecteer zorgverlener" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {caregivers.map((cg) => (
                                                    <SelectItem key={cg.id} value={String(cg.id)}>
                                                        {cg.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {exceptionForm.errors.caregiver_id && (
                                            <p className="text-sm text-red-500">
                                                {exceptionForm.errors.caregiver_id}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="ex-date">Datum</Label>
                                        <Input
                                            id="ex-date"
                                            type="date"
                                            value={exceptionForm.data.date}
                                            onChange={(e) =>
                                                exceptionForm.setData('date', e.target.value)
                                            }
                                        />
                                        {exceptionForm.errors.date && (
                                            <p className="text-sm text-red-500">
                                                {exceptionForm.errors.date}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="ex-start">Starttijd</Label>
                                        <Input
                                            id="ex-start"
                                            type="time"
                                            value={exceptionForm.data.start_time}
                                            onChange={(e) =>
                                                exceptionForm.setData('start_time', e.target.value)
                                            }
                                        />
                                        {exceptionForm.errors.start_time && (
                                            <p className="text-sm text-red-500">
                                                {exceptionForm.errors.start_time}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="ex-end">Eindtijd</Label>
                                        <Input
                                            id="ex-end"
                                            type="time"
                                            value={exceptionForm.data.end_time}
                                            onChange={(e) =>
                                                exceptionForm.setData('end_time', e.target.value)
                                            }
                                        />
                                        {exceptionForm.errors.end_time && (
                                            <p className="text-sm text-red-500">
                                                {exceptionForm.errors.end_time}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="ex-notes">Notities</Label>
                                    <Input
                                        id="ex-notes"
                                        value={exceptionForm.data.notes}
                                        onChange={(e) =>
                                            exceptionForm.setData('notes', e.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={exceptionForm.processing}>
                                        Opslaan
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setShowExceptionForm(false)}
                                    >
                                        Annuleren
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <WeekView
                    schedules={schedules}
                    exceptions={exceptions}
                    weekStart={weekStart}
                    onDeleteSchedule={deleteSchedule}
                    onDeleteException={deleteException}
                />
            </div>
        </AppLayout>
    );
}
