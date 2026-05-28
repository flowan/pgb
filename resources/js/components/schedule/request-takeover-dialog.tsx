import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    targetKind: 'schedule' | 'exception';
    targetId: number;
    targetDate: string;
    targetCaregiverId: number;
    targetCaregiverName: string;
    targetStartTime: string;
    targetEndTime: string;
}

export function RequestTakeoverDialog({
    open,
    onOpenChange,
    targetKind,
    targetId,
    targetDate,
    targetCaregiverId,
    targetCaregiverName,
    targetStartTime,
    targetEndTime,
}: Props) {
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        setSubmitting(true);
        const body: Record<string, unknown> = {
            target_caregiver_id: targetCaregiverId,
            target_date: targetDate,
            message: message || undefined,
        };
        if (targetKind === 'schedule') body.target_schedule_id = targetId;
        else body.target_schedule_exception_id = targetId;

        router.post('/shift-takeover-requests', body, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Vraag om deze shift over te nemen</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    <div className="rounded-md border bg-gray-50 p-3 text-sm">
                        <div className="text-xs uppercase text-muted-foreground">Shift</div>
                        <div className="font-medium">{targetCaregiverName}</div>
                        <div className="text-muted-foreground">
                            {targetDate} · {targetStartTime.slice(0, 5)}–{targetEndTime.slice(0, 5)}
                        </div>
                    </div>

                    <p className="text-sm text-muted-foreground">
                        {targetCaregiverName} krijgt een verzoek en kan accepteren of afwijzen. Je biedt
                        geen eigen shift in ruil — als ze akkoord gaan, neem jij hun shift over.
                    </p>

                    <div className="space-y-1.5">
                        <Label htmlFor="message">Bericht (optioneel)</Label>
                        <textarea
                            id="message"
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            placeholder="Bijv. korte uitleg waarom"
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            rows={3}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuleren
                    </Button>
                    <Button onClick={submit} disabled={submitting}>
                        Verzoek versturen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
