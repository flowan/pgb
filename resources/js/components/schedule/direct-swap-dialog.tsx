import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import type { Caregiver, Schedule } from '@/types';

interface ColleagueWithSchedules extends Caregiver {
    schedules?: Schedule[];
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: 'schedule' | 'exception';
    id: number;
    date: string;
    colleagues: ColleagueWithSchedules[];
}

export function DirectSwapDialog({ open, onOpenChange, kind, id, date, colleagues }: Props) {
    const [targetCaregiverId, setTargetCaregiverId] = useState<number | null>(null);
    const [targetScheduleId, setTargetScheduleId] = useState<number | null>(null);
    const [targetDate, setTargetDate] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);

    const selectedColleague = colleagues.find((c) => c.id === targetCaregiverId);

    function submit() {
        if (!targetCaregiverId || !targetScheduleId || !targetDate) return;
        setSubmitting(true);
        const body: Record<string, unknown> = {
            requester_date: date,
            target_caregiver_id: targetCaregiverId,
            target_schedule_id: targetScheduleId,
            target_date: targetDate,
        };
        if (kind === 'schedule') body.requester_schedule_id = id;
        else body.requester_schedule_exception_id = id;

        router.post('/shift-swap-requests', body, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Direct ruilen met collega</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        Jouw shift: {date}
                    </p>
                    <div className="space-y-1.5">
                        <Label>Collega</Label>
                        <select
                            value={targetCaregiverId ?? ''}
                            onChange={(e) => {
                                setTargetCaregiverId(e.target.value ? Number(e.target.value) : null);
                                setTargetScheduleId(null);
                            }}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">— Kies een collega —</option>
                            {colleagues.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    {selectedColleague && (
                        <div className="space-y-1.5">
                            <Label>Hun shift</Label>
                            <select
                                value={targetScheduleId ?? ''}
                                onChange={(e) => setTargetScheduleId(e.target.value ? Number(e.target.value) : null)}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            >
                                <option value="">— Kies hun shift —</option>
                                {(selectedColleague.schedules ?? []).map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {dayLabel(s.day_of_week)} {s.start_time.slice(0, 5)}–{s.end_time.slice(0, 5)}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div className="space-y-1.5">
                        <Label htmlFor="target_date">Datum van hun shift</Label>
                        <Input
                            id="target_date"
                            type="date"
                            value={targetDate}
                            onChange={(e) => setTargetDate(e.target.value)}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuleren
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={submitting || !targetCaregiverId || !targetScheduleId || !targetDate}
                    >
                        Verzoek versturen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function dayLabel(d: number): string {
    return ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'][d] ?? '?';
}
