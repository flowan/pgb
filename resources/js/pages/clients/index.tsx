import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Client } from '@/types';

export default function ClientsIndex({ clients }: { clients: Client[] }) {
    return (
        <>
            <Head title="Cliënten" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Cliënten</h1>
                    <Button asChild>
                        <Link href="/clients/create">
                            <Plus />
                            Nieuwe cliënt
                        </Link>
                    </Button>
                </div>

                {clients.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Nog geen cliënten. Voeg je eerste cliënt toe.
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Naam</th>
                                    <th className="px-4 py-3 text-left font-medium">Geboortedatum</th>
                                    <th className="px-4 py-3 text-left font-medium">Zorgverleners</th>
                                    <th className="px-4 py-3 text-right font-medium">Acties</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {clients.map((client) => (
                                    <tr key={client.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/clients/${client.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {client.name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {new Date(client.date_of_birth).toLocaleDateString('nl-NL')}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {client.caregivers_count ?? 0}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/clients/${client.id}`}>
                                                    Bekijken
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/clients/${client.id}/edit`}>
                                                    Bewerken
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

ClientsIndex.layout = {
    breadcrumbs: [
        { title: 'Cliënten', href: '/clients' },
    ],
};
