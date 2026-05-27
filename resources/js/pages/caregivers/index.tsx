import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Caregiver, Client } from '@/types';

const typeLabels: Record<Caregiver['type'], string> = {
    parent: 'Ouder',
    care_worker: 'Zorgmedewerker',
    day_care: 'Dagbesteding',
    zzp: 'ZZP\'er',
    other: 'Anders',
};

export default function CaregiversIndex({
    client,
    caregivers,
}: {
    client: Client;
    caregivers: Caregiver[];
}) {
    const [deleteTarget, setDeleteTarget] = useState<Caregiver | null>(null);

    function handleDelete() {
        if (!deleteTarget) return;
        router.delete(`/clients/${client.id}/caregivers/${deleteTarget.id}`, {
            onFinish: () => setDeleteTarget(null),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Cliënten', href: '/clients' },
                { title: client.name, href: `/clients/${client.id}` },
                { title: 'Zorgverleners', href: `/clients/${client.id}/caregivers` },
            ]}
        >
            <Head title={`Zorgverleners - ${client.name}`} />

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
                        Zorgverleners van {client.name}
                    </h1>
                    <Button asChild>
                        <Link href={`/clients/${client.id}/caregivers/create`}>
                            <Plus />
                            Nieuwe zorgverlener
                        </Link>
                    </Button>
                </div>

                {caregivers.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Nog geen zorgverleners. Voeg een zorgverlener toe.
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Naam</th>
                                    <th className="px-4 py-3 text-left font-medium">Type</th>
                                    <th className="px-4 py-3 text-left font-medium">Uurtarief</th>
                                    <th className="px-4 py-3 text-right font-medium">Acties</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {caregivers.map((caregiver) => (
                                    <tr key={caregiver.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3 font-medium">
                                            {caregiver.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="secondary">
                                                {typeLabels[caregiver.type]}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {caregiver.hourly_rate
                                                ? `€ ${Number(caregiver.hourly_rate).toFixed(2)}`
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link
                                                    href={`/clients/${client.id}/caregivers/${caregiver.id}/edit`}
                                                >
                                                    <Pencil />
                                                    Bewerken
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setDeleteTarget(caregiver)}
                                            >
                                                <Trash2 />
                                                Verwijderen
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Zorgverlener verwijderen</DialogTitle>
                        <DialogDescription>
                            Weet je zeker dat je {deleteTarget?.name} wilt verwijderen? Dit kan niet
                            ongedaan worden gemaakt.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>
                            Annuleren
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Verwijderen
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
