import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, Pencil, Trash2, Users, Wallet } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Client } from '@/types';

export default function ClientShow({ client }: { client: Client }) {
    const [confirmDelete, setConfirmDelete] = useState(false);

    function handleDelete() {
        router.delete(`/clients/${client.id}`, {
            onFinish: () => setConfirmDelete(false),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Cliënten', href: '/clients' },
                { title: client.name, href: `/clients/${client.id}` },
            ]}
        >
            <Head title={client.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/clients">
                            <ArrowLeft />
                            Terug
                        </Link>
                    </Button>
                </div>

                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{client.name}</h1>
                        <p className="text-muted-foreground">
                            Geboortedatum: {new Date(client.date_of_birth).toLocaleDateString('nl-NL')}
                        </p>
                        {client.notes && (
                            <p className="mt-2 text-sm text-muted-foreground">{client.notes}</p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/clients/${client.id}/edit`}>
                                <Pencil />
                                Bewerken
                            </Link>
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => setConfirmDelete(true)}
                        >
                            <Trash2 />
                            Verwijderen
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Users className="size-4" />
                                Zorgverleners
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {client.caregivers?.length ?? 0}
                            </p>
                            <Button variant="link" size="sm" className="mt-2 px-0" asChild>
                                <Link href={`/clients/${client.id}/caregivers`}>
                                    Beheren
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Wallet className="size-4" />
                                Budget
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {client.budget_categories?.length ?? 0} categorieën
                            </p>
                            <Button variant="link" size="sm" className="mt-2 px-0" asChild>
                                <Link href={`/clients/${client.id}/budget`}>
                                    Beheren
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Calendar className="size-4" />
                                Planning
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Weekplanning bekijken
                            </p>
                            <Button variant="link" size="sm" className="mt-2 px-0" asChild>
                                <Link href={`/clients/${client.id}/schedule`}>
                                    Bekijken
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cliënt verwijderen</DialogTitle>
                        <DialogDescription>
                            Weet je zeker dat je {client.name} wilt verwijderen? Dit kan niet ongedaan
                            worden gemaakt.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDelete(false)}>
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
