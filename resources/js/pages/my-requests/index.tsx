import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { SwapResponseDialog } from '@/components/schedule/swap-response-dialog';
import { OfferBidDialog } from '@/components/schedule/offer-bid-dialog';
import type {
    Caregiver,
    Client,
    OpenSwapOffer,
    OpenSwapRequest,
    Schedule,
    ScheduleException,
    ShiftSwapRequest,
    ShiftTakeoverOffer,
} from '@/types';

type ScheduleWithClient = Schedule & { client?: Client };
type ExceptionWithClient = ScheduleException & { client?: Client };

type TakeoverWithRelations = ShiftTakeoverOffer & {
    schedule?: ScheduleWithClient | null;
    schedule_exception?: ExceptionWithClient | null;
};

type SwapWithRelations = ShiftSwapRequest & {
    requester_schedule?: ScheduleWithClient | null;
    target_schedule?: ScheduleWithClient | null;
};

type OpenSwapWithRelations = OpenSwapRequest & {
    schedule?: ScheduleWithClient | null;
    schedule_exception?: ExceptionWithClient | null;
    offers?: (OpenSwapOffer & { offered_by?: Caregiver })[];
};

interface Props {
    myTakeoverOffers: TakeoverWithRelations[];
    myDirectSwapsOut: SwapWithRelations[];
    directSwapsIn: SwapWithRelations[];
    myOpenSwaps: OpenSwapWithRelations[];
    openSwapsToBidOn: OpenSwapWithRelations[];
}

type Tab = 'aanbiedingen' | 'ruilverzoeken' | 'aanmij' | 'biedingen';

function ClientLabel({
    schedule,
    exception,
}: {
    schedule?: ScheduleWithClient | null;
    exception?: ExceptionWithClient | null;
}) {
    const name = schedule?.client?.name ?? exception?.client?.name ?? 'Onbekende cliënt';
    return <span>{name}</span>;
}

function TimeLabel({
    schedule,
    exception,
}: {
    schedule?: ScheduleWithClient | null;
    exception?: ExceptionWithClient | null;
}) {
    const src = schedule ?? exception;
    if (!src) return null;
    return <span>{src.start_time.slice(0, 5)}–{src.end_time.slice(0, 5)}</span>;
}

export default function MyRequestsIndex({
    myTakeoverOffers,
    myDirectSwapsOut,
    directSwapsIn,
    myOpenSwaps,
    openSwapsToBidOn,
}: Props) {
    const [tab, setTab] = useState<Tab>('aanbiedingen');
    const [respondTo, setRespondTo] = useState<SwapWithRelations | null>(null);
    const [bidOn, setBidOn] = useState<OpenSwapWithRelations | null>(null);

    // Build a flat list of schedules belonging to me (from my outgoing swaps)
    // — best effort source for OfferBidDialog. Falls back to empty list.
    const mySchedules: Schedule[] = useMemo(() => {
        const map = new Map<number, Schedule>();
        for (const s of myDirectSwapsOut) {
            if (s.requester_schedule) map.set(s.requester_schedule.id, s.requester_schedule);
        }
        for (const o of myOpenSwaps) {
            if (o.schedule) map.set(o.schedule.id, o.schedule);
        }
        return Array.from(map.values());
    }, [myDirectSwapsOut, myOpenSwaps]);

    function withdrawTakeover(id: number) {
        router.delete(`/shift-takeover-offers/${id}`, { preserveScroll: true });
    }
    function withdrawDirectSwap(id: number) {
        router.delete(`/shift-swap-requests/${id}`, { preserveScroll: true });
    }
    function withdrawOpenSwap(id: number) {
        router.delete(`/open-swap-requests/${id}`, { preserveScroll: true });
    }

    const tabs: { id: Tab; label: string; count: number }[] = [
        { id: 'aanbiedingen', label: 'Mijn aanbiedingen', count: myTakeoverOffers.length + myOpenSwaps.length },
        { id: 'ruilverzoeken', label: 'Mijn ruilverzoeken', count: myDirectSwapsOut.length },
        { id: 'aanmij', label: 'Verzoeken aan mij', count: directSwapsIn.length },
        { id: 'biedingen', label: 'Aanbiedingen die ik kan doen', count: openSwapsToBidOn.length },
    ];

    return (
        <>
            <Head title="Mijn verzoeken" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Mijn verzoeken</h1>

                <div className="flex flex-wrap gap-1 border-b">
                    {tabs.map((t) => (
                        <button
                            key={t.id}
                            onClick={() => setTab(t.id)}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${
                                tab === t.id
                                    ? 'border-blue-600 font-medium text-blue-700'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label}
                            {t.count > 0 && (
                                <span className="ml-1.5 rounded-full bg-gray-200 px-1.5 text-xs text-gray-700">
                                    {t.count}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {tab === 'aanbiedingen' && (
                    <div className="space-y-4">
                        <section>
                            <h2 className="mb-2 text-sm font-medium uppercase text-muted-foreground">
                                Overname-aanbiedingen
                            </h2>
                            {myTakeoverOffers.length === 0 ? (
                                <EmptyState text="Je hebt geen overname-aanbiedingen geplaatst." />
                            ) : (
                                <div className="space-y-2">
                                    {myTakeoverOffers.map((o) => (
                                        <Card key={o.id} className="p-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="text-sm">
                                                    <div className="font-medium">
                                                        <ClientLabel schedule={o.schedule} exception={o.schedule_exception} />
                                                        {' • '}
                                                        {String(o.date)}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        <TimeLabel schedule={o.schedule} exception={o.schedule_exception} />
                                                        {' • Status: '}
                                                        {String(o.status)}
                                                    </div>
                                                </div>
                                                {o.status === 'open' && (
                                                    <Button variant="outline" size="sm" onClick={() => withdrawTakeover(o.id)}>
                                                        Intrekken
                                                    </Button>
                                                )}
                                            </div>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </section>
                        <section>
                            <h2 className="mb-2 text-sm font-medium uppercase text-muted-foreground">
                                Open ruilverzoeken
                            </h2>
                            {myOpenSwaps.length === 0 ? (
                                <EmptyState text="Je hebt geen open ruilverzoeken geplaatst." />
                            ) : (
                                <div className="space-y-2">
                                    {myOpenSwaps.map((o) => (
                                        <Card key={o.id} className="p-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="text-sm">
                                                    <div className="font-medium">
                                                        <ClientLabel schedule={o.schedule} exception={o.schedule_exception} />
                                                        {' • '}
                                                        {String(o.date)}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        <TimeLabel schedule={o.schedule} exception={o.schedule_exception} />
                                                        {' • Status: '}
                                                        {String(o.status)}
                                                        {' • '}
                                                        {(o.offers ?? []).length} bod(s)
                                                    </div>
                                                </div>
                                                {o.status === 'open' && (
                                                    <Button variant="outline" size="sm" onClick={() => withdrawOpenSwap(o.id)}>
                                                        Intrekken
                                                    </Button>
                                                )}
                                            </div>
                                            {(o.offers ?? []).length > 0 && (
                                                <div className="mt-3 space-y-1">
                                                    {(o.offers ?? []).map((bid) => (
                                                        <div
                                                            key={bid.id}
                                                            className="flex items-center justify-between rounded border bg-gray-50 p-2 text-xs dark:bg-gray-900/40"
                                                        >
                                                            <span>
                                                                {bid.offered_by?.name ?? 'Collega'} biedt shift op {String(bid.offered_date)} (status {bid.status})
                                                            </span>
                                                            {o.status === 'open' && bid.status === 'pending' && (
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `/open-swap-requests/${o.id}/offers/${bid.id}/accept`,
                                                                            {},
                                                                            { preserveScroll: true },
                                                                        )
                                                                    }
                                                                >
                                                                    Accepteer
                                                                </Button>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                )}

                {tab === 'ruilverzoeken' && (
                    <div className="space-y-2">
                        {myDirectSwapsOut.length === 0 ? (
                            <EmptyState text="Je hebt geen directe ruilverzoeken verstuurd." />
                        ) : (
                            myDirectSwapsOut.map((s) => (
                                <Card key={s.id} className="p-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="text-sm">
                                            <div className="font-medium">
                                                Met {s.target?.name ?? 'collega'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Jouw {String(s.requester_date)} ↔ Hun {String(s.target_date)} • Status: {String(s.status)}
                                                {s.decline_reason && (
                                                    <> • Reden: {s.decline_reason}</>
                                                )}
                                            </div>
                                        </div>
                                        {s.status === 'pending' && (
                                            <Button variant="outline" size="sm" onClick={() => withdrawDirectSwap(s.id)}>
                                                Intrekken
                                            </Button>
                                        )}
                                    </div>
                                </Card>
                            ))
                        )}
                    </div>
                )}

                {tab === 'aanmij' && (
                    <div className="space-y-2">
                        {directSwapsIn.length === 0 ? (
                            <EmptyState text="Geen openstaande verzoeken aan jou." />
                        ) : (
                            directSwapsIn.map((s) => (
                                <Card key={s.id} className="p-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="text-sm">
                                            <div className="font-medium">
                                                Van {s.requester?.name ?? 'collega'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Hun {String(s.requester_date)} ↔ Jouw {String(s.target_date)}
                                            </div>
                                        </div>
                                        <Button size="sm" onClick={() => setRespondTo(s)}>
                                            Reageren
                                        </Button>
                                    </div>
                                </Card>
                            ))
                        )}
                    </div>
                )}

                {tab === 'biedingen' && (
                    <div className="space-y-2">
                        {openSwapsToBidOn.length === 0 ? (
                            <EmptyState text="Geen open ruilverzoeken om op te bieden." />
                        ) : (
                            openSwapsToBidOn.map((o) => (
                                <Card key={o.id} className="p-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="text-sm">
                                            <div className="font-medium">
                                                {o.requester?.name ?? 'Collega'} •{' '}
                                                <ClientLabel schedule={o.schedule} exception={o.schedule_exception} />
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Shift op {String(o.date)}{' '}
                                                <TimeLabel schedule={o.schedule} exception={o.schedule_exception} />
                                            </div>
                                        </div>
                                        <Button size="sm" onClick={() => setBidOn(o)}>
                                            Aanbieden
                                        </Button>
                                    </div>
                                </Card>
                            ))
                        )}
                    </div>
                )}
            </div>

            {respondTo && (
                <SwapResponseDialog
                    open
                    onOpenChange={(o) => !o && setRespondTo(null)}
                    request={respondTo}
                />
            )}

            {bidOn && (
                <OfferBidDialog
                    open
                    onOpenChange={(o) => !o && setBidOn(null)}
                    request={bidOn}
                    mySchedules={mySchedules}
                />
            )}
        </>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}

MyRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Mijn verzoeken', href: '/my-requests' }],
};
