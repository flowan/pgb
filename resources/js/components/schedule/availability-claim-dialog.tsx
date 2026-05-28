import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { AvailabilitySlot } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    slot: AvailabilitySlot;
    /** The date the user clicked on — used as anchor for once/until claims */
    date: string;
}

type Scope = 'once' | 'until' | 'forever';

export function AvailabilityClaimDialog({ open, onOpenChange, slot, date }: Props) {
    const isOneTime = slot.day_of_week === null;
    const [scope, setScope] = useState<Scope>('once');
    const [untilDate, setUntilDate] = useState('');

    function submit() {
        const data: Record<string, string> = {};
        if (!isOneTime) {
            data.scope = scope;
            data.date = date;
            if (scope === 'until') data.until_date = untilDate;
        }
        router.post(`/availability-slots/${slot.id}/claim`, data, {
            onSuccess: () => onOpenChange(false),
        });
    }

    // One-time slot: simple confirm
    if (isOneTime) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Beschikbare shift claimen</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Wil je deze shift op {slot.date} ({slot.start_time.slice(0, 5)}–{slot.end_time.slice(0, 5)}) claimen?
                    </p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => onOpenChange(false)}>Annuleren</Button>
                        <Button onClick={submit}>Claimen</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Beschikbare shift claimen</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Hoe wil je deze terugkerende shift ({slot.start_time.slice(0, 5)}–{slot.end_time.slice(0, 5)}) claimen?
                </p>
                <div className="space-y-2">
                    <ScopeOption
                        selected={scope === 'once'}
                        onSelect={() => setScope('once')}
                        title="Alleen deze datum"
                        description={`Eenmalig op ${date}. Andere weken blijven beschikbaar voor anderen.`}
                    />
                    <ScopeOption
                        selected={scope === 'until'}
                        onSelect={() => setScope('until')}
                        title="Tot een bepaalde datum"
                        description="Alle occurrences tussen de datum hierboven en de einddatum hieronder."
                    />
                    {scope === 'until' && (
                        <div className="ml-6 space-y-1">
                            <Label htmlFor="until-date" className="text-xs">Einddatum (laatste mogelijke datum)</Label>
                            <Input
                                id="until-date"
                                type="date"
                                value={untilDate}
                                min={date}
                                onChange={(e) => setUntilDate(e.target.value)}
                            />
                        </div>
                    )}
                    <ScopeOption
                        selected={scope === 'forever'}
                        onSelect={() => setScope('forever')}
                        title="Vanaf nu altijd"
                        description="Wordt een vaste shift in je weekplanning. Je kunt hem later teruggeven."
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Annuleren</Button>
                    <Button
                        onClick={submit}
                        disabled={scope === 'until' && !untilDate}
                    >
                        Claimen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ScopeOption({
    selected,
    onSelect,
    title,
    description,
}: {
    selected: boolean;
    onSelect: () => void;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={`w-full rounded-md border p-3 text-left text-sm ${
                selected ? 'border-purple-400 bg-purple-50' : 'border-gray-200 hover:border-gray-300'
            }`}
        >
            <div className="flex items-start gap-2">
                <span
                    className={`mt-0.5 h-4 w-4 shrink-0 rounded-full border-2 ${
                        selected ? 'border-purple-500 bg-purple-500' : 'border-gray-300'
                    }`}
                />
                <div>
                    <div className="font-medium">{title}</div>
                    <div className="text-xs text-muted-foreground">{description}</div>
                </div>
            </div>
        </button>
    );
}
