import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ArrowRightLeft, Hand } from 'lucide-react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    caregiverName: string;
    date: string;
    startTime: string;
    endTime: string;
    onRequestSwap: () => void;
    onRequestTakeover: () => void;
}

export function ColleagueShiftMenu({
    open,
    onOpenChange,
    caregiverName,
    date,
    startTime,
    endTime,
    onRequestSwap,
    onRequestTakeover,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Shift van {caregiverName}</DialogTitle>
                </DialogHeader>
                <div className="text-sm text-muted-foreground">
                    {date} · {startTime.slice(0, 5)}–{endTime.slice(0, 5)}
                </div>
                <div className="mt-2 space-y-2">
                    <button
                        onClick={onRequestSwap}
                        className="w-full rounded-md border p-3 text-left text-sm hover:bg-gray-50"
                    >
                        <div className="flex items-start gap-2">
                            <ArrowRightLeft className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                            <div>
                                <div className="font-medium">Vraag te ruilen</div>
                                <div className="text-xs text-muted-foreground">
                                    Bied één van jouw shifts in ruil voor deze shift.
                                </div>
                            </div>
                        </div>
                    </button>
                    <button
                        onClick={onRequestTakeover}
                        className="w-full rounded-md border p-3 text-left text-sm hover:bg-gray-50"
                    >
                        <div className="flex items-start gap-2">
                            <Hand className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                            <div>
                                <div className="font-medium">Vraag over te nemen</div>
                                <div className="text-xs text-muted-foreground">
                                    Neem deze shift over zonder iets in ruil te geven.
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
