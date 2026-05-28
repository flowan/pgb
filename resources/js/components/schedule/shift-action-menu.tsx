import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onTakeover: () => void;
    onDirectSwap: () => void;
    onOpenSwap: () => void;
}

export function ShiftActionMenu({ open, onOpenChange, onTakeover, onDirectSwap, onOpenSwap }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Wat wil je doen met deze shift?</DialogTitle>
                </DialogHeader>
                <div className="flex flex-col gap-2">
                    <Button variant="outline" className="justify-start" onClick={onTakeover}>
                        Aanbieden voor overname
                    </Button>
                    <Button variant="outline" className="justify-start" onClick={onDirectSwap}>
                        Direct ruilen met collega…
                    </Button>
                    <Button variant="outline" className="justify-start" onClick={onOpenSwap}>
                        Open ruilverzoek plaatsen
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
