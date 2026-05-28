import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { ShiftOccurrence } from '@/pages/caregiver-schedule/index';

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
    myOccurrences: ShiftOccurrence[];
}

function formatOccurrence(o: ShiftOccurrence): string {
    const d = new Date(o.date);
    const dateLabel = d.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' });
    return `${o.dayLabel} ${dateLabel} · ${o.startTime.slice(0, 5)}–${o.endTime.slice(0, 5)} · ${o.clientName}`;
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
    myOccurrences,
}: Props) {
    // Encode selection as "kind:id:date" so each occurrence is a unique value
    const [selection, setSelection] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        if (!selection) return;
        const [kind, idStr, date] = selection.split('|');
        const id = Number(idStr);

        const body: Record<string, unknown> = {
            requester_date: date,
            target_caregiver_id: targetCaregiverId,
            target_date: targetDate,
        };
        if (kind === 'schedule') body.requester_schedule_id = id;
        else body.requester_schedule_exception_id = id;
        if (targetKind === 'schedule') body.target_schedule_id = targetId;
        else body.target_schedule_exception_id = targetId;

        setSubmitting(true);
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
                        {myOccurrences.length === 0 ? (
                            <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                                Je hebt geen aankomende eigen shifts om aan te bieden.
                            </div>
                        ) : (
                            <select
                                value={selection}
                                onChange={(e) => setSelection(e.target.value)}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            >
                                <option value="">— Kies een eigen shift —</option>
                                {myOccurrences.map((o) => (
                                    <option key={`${o.kind}|${o.id}|${o.date}`} value={`${o.kind}|${o.id}|${o.date}`}>
                                        {formatOccurrence(o)}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuleren
                    </Button>
                    <Button onClick={submit} disabled={submitting || !selection}>
                        Verzoek versturen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
