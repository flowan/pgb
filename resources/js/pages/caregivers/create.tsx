import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
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
import type { Client } from '@/types';

const typeOptions = [
    { value: 'parent', label: 'Ouder' },
    { value: 'care_worker', label: 'Zorgmedewerker' },
    { value: 'day_care', label: 'Dagbesteding' },
    { value: 'zzp', label: 'ZZP\'er' },
    { value: 'other', label: 'Anders' },
];

export default function CaregiverCreate({ client }: { client: Client }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: '',
        hourly_rate: '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(`/clients/${client.id}/caregivers`);
    }

    return (
        <>
            <Head title={`Nieuwe zorgverlener - ${client.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/clients/${client.id}/caregivers`}>
                            <ArrowLeft />
                            Terug
                        </Link>
                    </Button>
                </div>

                <h1 className="text-2xl font-semibold">Nieuwe zorgverlener</h1>

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
        </>
    );
}

CaregiverCreate.layout = (props: { client: Client }) => ({
    breadcrumbs: [
        { title: 'Cliënten', href: '/clients' },
        { title: props.client.name, href: `/clients/${props.client.id}` },
        { title: 'Zorgverleners', href: `/clients/${props.client.id}/caregivers` },
        { title: 'Nieuwe zorgverlener', href: `/clients/${props.client.id}/caregivers/create` },
    ],
});
