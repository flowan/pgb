import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import type { OpenSwapRequest, Schedule } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: OpenSwapRequest;
    mySchedules: Schedule[];
}

export function OfferBidDialog({ open, onOpenChange, request, mySchedules }: Props) {
    const [offeredScheduleId, setOfferedScheduleId] = useState<number | null>(null);
    const [offeredDate, setOfferedDate] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        if (!offeredScheduleId || !offeredDate) return;
        setSubmitting(true);
        router.post(`/open-swap-requests/${request.id}/offers`, {
            offered_schedule_id: offeredScheduleId,
            offered_date: offeredDate,
        }, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bod doen op ruilverzoek</DialogTitle>
                </DialogHeader>
                <div className="space-y-3 text-sm">
                    <p className="text-muted-foreground">
                        Verzoek van {request.requester?.name ?? 'collega'} voor shift op {String(request.date)}.
                    </p>
                    <div className="space-y-1.5">
                        <Label>Jouw shift om aan te bieden</Label>
                        <select
                            value={offeredScheduleId ?? ''}
                            onChange={(e) => setOfferedScheduleId(e.target.value ? Number(e.target.value) : null)}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">— Kies een eigen shift —</option>
                            {mySchedules.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {dayLabel(s.day_of_week)} {s.start_time.slice(0, 5)}–{s.end_time.slice(0, 5)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="offered_date">Datum van jouw shift</Label>
                        <Input
                            id="offered_date"
                            type="date"
                            value={offeredDate}
                            onChange={(e) => setOfferedDate(e.target.value)}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuleren
                    </Button>
                    <Button onClick={submit} disabled={submitting || !offeredScheduleId || !offeredDate}>
                        Bod versturen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function dayLabel(d: number): string {
    return ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'][d] ?? '?';
}
