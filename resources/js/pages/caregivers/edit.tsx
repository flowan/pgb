import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Caregiver, Client } from '@/types';

const typeOptions = [
    { value: 'parent', label: 'Ouder' },
    { value: 'care_worker', label: 'Zorgmedewerker' },
    { value: 'day_care', label: 'Dagbesteding' },
    { value: 'zzp', label: 'ZZP\'er' },
    { value: 'other', label: 'Anders' },
];

export default function CaregiverEdit({
    client,
    caregiver,
}: {
    client: Client;
    caregiver: Caregiver;
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: caregiver.name,
        type: caregiver.type,
        hourly_rate: caregiver.hourly_rate ?? '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put(`/clients/${client.id}/caregivers/${caregiver.id}`);
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Cliënten', href: '/clients' },
                { title: client.name, href: `/clients/${client.id}` },
                { title: 'Zorgverleners', href: `/clients/${client.id}/caregivers` },
                {
                    title: caregiver.name,
                    href: `/clients/${client.id}/caregivers/${caregiver.id}/edit`,
                },
            ]}
        >
            <Head title={`${caregiver.name} bewerken - ${client.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/clients/${client.id}/caregivers`}>
                            <ArrowLeft />
                            Terug
                        </Link>
                    </Button>
                </div>

                <h1 className="text-2xl font-semibold">{caregiver.name} bewerken</h1>

                <form onSubmit={handleSubmit} className="max-w-lg space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Naam</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder="Volledige naam"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="type">Type</Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => setData('type', value)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Selecteer type" />
                            </SelectTrigger>
                            <SelectContent>
                                {typeOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="hourly_rate">Uurtarief</Label>
                        <Input
                            id="hourly_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.hourly_rate}
                            onChange={(e) => setData('hourly_rate', e.target.value)}
                            placeholder="Optioneel"
                        />
                        <InputError message={errors.hourly_rate} />
                    </div>

                    <div className="flex gap-4">
                        <Button type="submit" disabled={processing}>
                            Opslaan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/clients/${client.id}/caregivers`}>Annuleren</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
