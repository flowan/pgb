import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Caregiver, Schedule, ScheduleException } from '@/types';

interface ClientWithCaregivers {
    id: number;
    name: string;
    caregivers?: Caregiver[];
    schedules?: Schedule[];
    schedule_exceptions?: ScheduleException[];
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The own shift we want to swap from */
    kind: 'schedule' | 'exception';
    id: number;
    date: string;
    /** Used to enumerate target caregivers + their upcoming shift occurrences */
    clients: ClientWithCaregivers[];
    myCaregiverIds: number[];
}

interface ColleagueOccurrence {
    kind: 'schedule' | 'exception';
    id: number;
    date: string;
    startTime: string;
    endTime: string;
    caregiverId: number;
    caregiverName: string;
    clientName: string;
    dayLabel: string;
}

const DAY_LABELS = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];

function ownDayOfWeek(d: Date): number {
    const js = d.getDay();
    return js === 0 ? 6 : js - 1;
}

function buildColleagueOccurrences(
    clients: ClientWithCaregivers[],
    myIds: number[],
    weeks: number,
): ColleagueOccurrence[] {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(today);
    end.setDate(end.getDate() + weeks * 7);

    const out: ColleagueOccurrence[] = [];

    for (const client of clients) {
        const skip = new Set<string>();
        for (const ex of client.schedule_exceptions ?? []) {
            if ((ex.type === 'cancelled' || ex.type === 'modified') && ex.schedule_id) {
                skip.add(`${ex.schedule_id}:${ex.date}`);
            }
        }

        const caregiverNameById = new Map<number, string>();
        for (const cg of client.caregivers ?? []) caregiverNameById.set(cg.id, cg.name);

        for (const s of client.schedules ?? []) {
            if (myIds.includes(s.caregiver_id)) continue;
            const cursor = new Date(today);
            while (cursor <= end) {
                if (ownDayOfWeek(cursor) === s.day_of_week) {
                    const dateStr = `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`;
                    if (!skip.has(`${s.id}:${dateStr}`)) {
                        out.push({
                            kind: 'schedule',
                            id: s.id,
                            date: dateStr,
                            startTime: s.start_time,
                            endTime: s.end_time,
                            caregiverId: s.caregiver_id,
                            caregiverName: s.caregiver?.name ?? caregiverNameById.get(s.caregiver_id) ?? 'Onbekend',
                            clientName: client.name,
                            dayLabel: DAY_LABELS[s.day_of_week] ?? '?',
                        });
                    }
                }
                cursor.setDate(cursor.getDate() + 1);
            }
        }

        for (const ex of client.schedule_exceptions ?? []) {
            if (myIds.includes(ex.caregiver_id)) continue;
            if (ex.type !== 'added' && ex.type !== 'modified') continue;
            const d = new Date(ex.date);
            d.setHours(0, 0, 0, 0);
            if (d < today || d > end) continue;
            out.push({
                kind: 'exception',
                id: ex.id,
                date: ex.date,
                startTime: ex.start_time,
                endTime: ex.end_time,
                caregiverId: ex.caregiver_id,
                caregiverName: ex.caregiver?.name ?? caregiverNameById.get(ex.caregiver_id) ?? 'Onbekend',
                clientName: client.name,
                dayLabel: DAY_LABELS[ownDayOfWeek(d)] ?? '?',
            });
        }
    }

    return out.sort((a, b) =>
        a.date === b.date ? a.startTime.localeCompare(b.startTime) : a.date.localeCompare(b.date),
    );
}

function formatOccurrence(o: ColleagueOccurrence): string {
    const d = new Date(o.date);
    const dateLabel = d.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' });
    return `${o.dayLabel} ${dateLabel} · ${o.startTime.slice(0, 5)}–${o.endTime.slice(0, 5)} · ${o.caregiverName} (${o.clientName})`;
}

export function DirectSwapDialog({ open, onOpenChange, kind, id, date, clients, myCaregiverIds }: Props) {
    const occurrences = useMemo(
        () => buildColleagueOccurrences(clients, myCaregiverIds, 8),
        [clients, myCaregiverIds],
    );
    const [selection, setSelection] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        if (!selection) return;
        const occ = occurrences.find(
            (o) => `${o.kind}|${o.id}|${o.date}` === selection,
        );
        if (!occ) return;

        setSubmitting(true);
        const body: Record<string, unknown> = {
            requester_date: date,
            target_caregiver_id: occ.caregiverId,
            target_date: occ.date,
        };
        if (kind === 'schedule') body.requester_schedule_id = id;
        else body.requester_schedule_exception_id = id;
        if (occ.kind === 'schedule') body.target_schedule_id = occ.id;
        else body.target_schedule_exception_id = occ.id;

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
                    <DialogTitle>Direct ruilen met collega</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                        Jouw shift: {date}
                    </p>
                    <div className="space-y-1.5">
                        <Label>Kies de shift van een collega om mee te ruilen</Label>
                        {occurrences.length === 0 ? (
                            <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                                Geen aankomende collega-shifts om mee te ruilen.
                            </div>
                        ) : (
                            <select
                                value={selection}
                                onChange={(e) => setSelection(e.target.value)}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            >
                                <option value="">— Kies een collega-shift —</option>
                                {occurrences.map((o) => (
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
