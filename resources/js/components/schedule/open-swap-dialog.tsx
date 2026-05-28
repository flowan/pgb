import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: 'schedule' | 'exception';
    id: number;
    date: string;
}

export function OpenSwapDialog({ open, onOpenChange, kind, id, date }: Props) {
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        setSubmitting(true);
        const body: Record<string, unknown> = { date, notes: notes || undefined };
        if (kind === 'schedule') body.schedule_id = id;
        else body.schedule_exception_id = id;

        router.post('/open-swap-requests', body, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Open ruilverzoek plaatsen</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        Je plaatst een open verzoek voor jouw shift op {date}. Collega's kunnen vervolgens een
                        eigen shift aanbieden om mee te ruilen.
                    </p>
                    <div className="space-y-1.5">
                        <Label htmlFor="notes">Notitie (optioneel)</Label>
                        <textarea
                            id="notes"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={3}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuleren
                    </Button>
                    <Button onClick={submit} disabled={submitting}>
                        Plaatsen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
