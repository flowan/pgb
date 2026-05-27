interface ProgressBarProps {
    allocated: number;
    spent: number;
    label?: string;
}

export function ProgressBar({ allocated, spent, label }: ProgressBarProps) {
    const percentage = allocated > 0 ? (spent / allocated) * 100 : 0;
    const remaining = allocated - spent;

    let barColor = 'bg-green-500';
    if (percentage >= 100) {
        barColor = 'bg-red-500';
    } else if (percentage >= 80) {
        barColor = 'bg-yellow-500';
    }

    return (
        <div className="space-y-1">
            {label && (
                <div className="flex items-center justify-between text-sm">
                    <span className="font-medium">{label}</span>
                    <span className="text-muted-foreground">
                        {formatCurrency(spent)} / {formatCurrency(allocated)}
                    </span>
                </div>
            )}
            {!label && (
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{formatCurrency(spent)} / {formatCurrency(allocated)}</span>
                    <span>Rest: {formatCurrency(remaining)}</span>
                </div>
            )}
            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={`h-full rounded-full transition-all ${barColor}`}
                    style={{ width: `${Math.min(percentage, 100)}%` }}
                />
            </div>
            {label && (
                <div className="text-xs text-muted-foreground">
                    Rest: {formatCurrency(remaining)}
                </div>
            )}
        </div>
    );
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(amount);
}
