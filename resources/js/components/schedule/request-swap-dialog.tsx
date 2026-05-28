import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import type { Schedule } from '@/types';

interface MyShift {
    kind: 'schedule' | 'exception';
    id: number;
    day_of_week: number | null;
    date: string | null;
    start_time: string;
    end_time: string;
    client_name?: string;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The colleague shift you want to take */
    targetKind: 'schedule' | 'exception';
    targetId: number;
    targetDate: string;
    targetCaregiverId: number;
    targetCaregiverName: string;
    targetStartTime: string;
    targetEndTime: string;
    mySchedules: Schedule[];
}

export function RequestSwapDialog({
    open,
    onOpenChange,
    targetKind,
    targetId,
    targetDate,
    targetCaregiverId,
    targetCaregiverName,
    targetStartTime,
    targetEndTime,
    mySchedules,
}: Props) {
    const [myScheduleId, setMyScheduleId] = useState<number | null>(null);
    const [myDate, setMyDate] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        if (!myScheduleId || !myDate) return;
        setSubmitting(true);
        const body: Record<string, unknown> = {
            requester_schedule_id: myScheduleId,
            requester_date: myDate,
            target_caregiver_id: targetCaregiverId,
            target_date: targetDate,
        };
        if (targetKind === 'schedule') body.target_schedule_id = targetId;
        else body.target_schedule_exception_id = targetId;

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
                    <DialogTitle>Vraag ruil aan {targetCaregiverName}</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    <div className="rounded-md border bg-gray-50 p-3 text-sm">
                        <div className="text-xs uppercase text-muted-foreground">Hun shift</div>
                        <div className="font-medium">{targetCaregiverName}</div>
                        <div className="text-muted-foreground">
                            {targetDate} · {targetStartTime.slice(0, 5)}–{targetEndTime.slice(0, 5)}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Bied één van jouw shifts aan</Label>
                        <select
                            value={myScheduleId ?? ''}
                            onChange={(e) => setMyScheduleId(e.target.value ? Number(e.target.value) : null)}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        >
                            <option value="">— Kies een eigen shift —</option>
                            {mySchedules.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {dayLabel(s.day_of_week)} {s.start_time.slice(0, 5)}–{s.end_time.slice(0, 5)}
                                    {s.caregiver?.name ? ` · ${s.caregiver.name}` : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="my_date">Datum van jouw shift</Label>
                        <Input
                            id="my_date"
                            type="date"
                            value={myDate}
                            onChange={(e) => setMyDate(e.target.value)}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuleren
                    </Button>
                    <Button onClick={submit} disabled={submitting || !myScheduleId || !myDate}>
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
