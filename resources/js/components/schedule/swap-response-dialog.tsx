import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { ShiftSwapRequest } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: ShiftSwapRequest;
}

export function SwapResponseDialog({ open, onOpenChange, request }: Props) {
    const [declineReason, setDeclineReason] = useState('');
    const [showDecline, setShowDecline] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    function accept() {
        setSubmitting(true);
        router.post(`/shift-swap-requests/${request.id}/accept`, {}, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => onOpenChange(false),
        });
    }

    function decline() {
        setSubmitting(true);
        router.post(`/shift-swap-requests/${request.id}/decline`, {
            decline_reason: declineReason || undefined,
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
                    <DialogTitle>Ruilverzoek van {request.requester?.name ?? 'collega'}</DialogTitle>
                </DialogHeader>
                <div className="space-y-3 text-sm">
                    <div>
                        <span className="text-muted-foreground">Hun shift op</span> {String(request.requester_date)}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Voor jouw shift op</span> {String(request.target_date)}
                    </div>
                    {showDecline && (
                        <div className="space-y-1.5">
                            <Label htmlFor="decline_reason">Reden (optioneel)</Label>
                            <textarea
                                id="decline_reason"
                                value={declineReason}
                                onChange={(e) => setDeclineReason(e.target.value)}
                                rows={2}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                        </div>
                    )}
                </div>
                <DialogFooter>
                    {showDecline ? (
                        <>
                            <Button variant="outline" onClick={() => setShowDecline(false)} disabled={submitting}>
                                Terug
                            </Button>
                            <Button variant="destructive" onClick={decline} disabled={submitting}>
                                Afwijzen
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button variant="outline" onClick={() => setShowDecline(true)} disabled={submitting}>
                                Afwijzen
                            </Button>
                            <Button onClick={accept} disabled={submitting}>
                                Accepteren
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
