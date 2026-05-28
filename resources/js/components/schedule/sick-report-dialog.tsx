import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function SickReportDialog({ open, onOpenChange }: Props) {
    const today = new Date().toISOString().split('T')[0];
    const [startDate, setStartDate] = useState(today);
    const [endDate, setEndDate] = useState(today);
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        setSubmitting(true);
        router.post('/sick-reports', {
            scope: 'range',
            start_date: startDate,
            end_date: endDate,
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
                    <DialogTitle>Ziek melden</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Al jouw shifts in dit bereik worden geannuleerd en automatisch aangeboden
                    voor overname.
                </p>
                <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="sick-start">Van</Label>
                        <Input
                            id="sick-start"
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="sick-end">Tot en met</Label>
                        <Input
                            id="sick-end"
                            type="date"
                            value={endDate}
                            min={startDate}
                            onChange={(e) => setEndDate(e.target.value)}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuleren
                    </Button>
                    <Button onClick={submit} disabled={submitting || !startDate || !endDate}>
                        Ziek melden
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
