import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ArrowRightLeft, HandHelping, Megaphone } from 'lucide-react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    clientName: string;
    date: string;
    startTime: string;
    endTime: string;
    onTakeover: () => void;
    onDirectSwap: () => void;
    onOpenSwap: () => void;
}

export function ShiftActionMenu({
    open,
    onOpenChange,
    clientName,
    date,
    startTime,
    endTime,
    onTakeover,
    onDirectSwap,
    onOpenSwap,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Jouw shift bij {clientName}</DialogTitle>
                </DialogHeader>
                <div className="text-sm text-muted-foreground">
                    {date} · {startTime.slice(0, 5)}–{endTime.slice(0, 5)}
                </div>
                <div className="mt-2 space-y-2">
                    <button
                        onClick={onTakeover}
                        className="w-full rounded-md border p-3 text-left text-sm hover:bg-gray-50"
                    >
                        <div className="flex items-start gap-2">
                            <HandHelping className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                            <div>
                                <div className="font-medium">Aanbieden voor overname</div>
                                <div className="text-xs text-muted-foreground">
                                    Open je shift zodat een collega hem kan claimen.
                                </div>
                            </div>
                        </div>
                    </button>
                    <button
                        onClick={onDirectSwap}
                        className="w-full rounded-md border p-3 text-left text-sm hover:bg-gray-50"
                    >
                        <div className="flex items-start gap-2">
                            <ArrowRightLeft className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                            <div>
                                <div className="font-medium">Direct ruilen met collega</div>
                                <div className="text-xs text-muted-foreground">
                                    Stuur een ruilverzoek naar een specifieke collega.
                                </div>
                            </div>
                        </div>
                    </button>
                    <button
                        onClick={onOpenSwap}
                        className="w-full rounded-md border p-3 text-left text-sm hover:bg-gray-50"
                    >
                        <div className="flex items-start gap-2">
                            <Megaphone className="mt-0.5 h-4 w-4 shrink-0 text-purple-600" />
                            <div>
                                <div className="font-medium">Open ruilverzoek plaatsen</div>
                                <div className="text-xs text-muted-foreground">
                                    Vraag collega's om een ruilaanbod te doen. Jij kiest welke.
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
