import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ClientCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        date_of_birth: '',
        notes: '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post('/clients');
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Cliënten', href: '/clients' },
                { title: 'Nieuwe cliënt', href: '/clients/create' },
            ]}
        >
            <Head title="Nieuwe cliënt" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/clients">
                            <ArrowLeft />
                            Terug
                        </Link>
                    </Button>
                </div>

                <h1 className="text-2xl font-semibold">Nieuwe cliënt</h1>

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
                        <Label htmlFor="date_of_birth">Geboortedatum</Label>
                        <Input
                            id="date_of_birth"
                            type="date"
                            value={data.date_of_birth}
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                            required
                        />
                        <InputError message={errors.date_of_birth} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notities</Label>
                        <textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Optionele notities"
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <div className="flex gap-4">
                        <Button type="submit" disabled={processing}>
                            Opslaan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/clients">Annuleren</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
