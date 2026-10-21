import * as React from 'react';
import { CalendarIcon, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { dateLabel, formatDateValue, parseDateValue, parseTimeInput } from '@/lib/date-value';
import { cn } from '@/lib/utils';

export interface DatePickerProps {
    id?: string;
    /** "2026-10-19", or "2026-10-19T14:30" with `withTime`; null for none. */
    value: string | null;
    onChange: (value: string | null) => void;
    /** Also asks for a time of day. */
    withTime?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    /** No Clear button. */
    required?: boolean;
    placeholder?: string;
    className?: string;
}

/**
 * A calendar in a popover, with a time input when `withTime`. Reads and
 * writes the same strings a native date or datetime-local input would, so
 * it drops in where those were.
 */
export function DatePicker({ id, value, onChange, withTime = false, disabled = false, invalid = false, required = false, placeholder = 'Pick a date', className }: DatePickerProps) {
    const [open, setOpen] = React.useState(false);
    const { date, time } = parseDateValue(value);
    const label = dateLabel(value, withTime);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    aria-invalid={invalid}
                    data-empty={!label}
                    className={cn('h-10 w-full max-w-64 justify-start gap-2 px-3 font-normal data-[empty=true]:text-muted-foreground', className)}
                >
                    <CalendarIcon className="text-muted-foreground" />
                    <span className="truncate">{label || placeholder}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    autoFocus
                    selected={date}
                    defaultMonth={date ?? undefined}
                    onSelect={(picked) => {
                        onChange(formatDateValue(picked, time, withTime));
                        if (!withTime) setOpen(false);
                    }}
                />
                {(withTime || (label && !required)) && (
                    <div className="flex items-center gap-2 border-t px-3 py-2">
                        {withTime && (
                            <>
                                <Label htmlFor={id ? `${id}-time` : undefined} className="text-xs text-muted-foreground">Time (24h)</Label>
                                <TimeInput id={id ? `${id}-time` : undefined} value={time} disabled={!date}
                                    onChange={(next) => onChange(formatDateValue(date, next, true))} />
                            </>
                        )}
                        {label && !required && (
                            <Button type="button" variant="ghost" size="sm" className="ml-auto text-muted-foreground" onClick={() => { onChange(null); setOpen(false); }}>
                                <X />
                                Clear
                            </Button>
                        )}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}

/**
 * The time on the 24-hour clock, as the button shows it (a native time
 * input follows the browser's clock, 12-hour in some languages): typed as
 * "14:30", "0930" or "9", kept when it reads as a time.
 */
function TimeInput({ id, value, disabled, onChange }: { id?: string; value: string; disabled: boolean; onChange: (time: string) => void }) {
    const [text, setText] = React.useState(value);
    React.useEffect(() => setText(value), [value]);
    const valid = text.trim() === '' || parseTimeInput(text) !== null;

    return (
        <Input
            id={id}
            inputMode="numeric"
            placeholder="HH:MM"
            aria-invalid={!valid}
            className="h-8 w-20 text-center font-mono text-sm tabular-nums"
            value={text}
            disabled={disabled}
            onChange={(event) => {
                setText(event.target.value);
                const time = parseTimeInput(event.target.value);
                if (time !== null && event.target.value.trim().length >= 4) onChange(time);
            }}
            onBlur={() => {
                const time = parseTimeInput(text);
                if (time !== null) onChange(time);
                setText(time ?? value);
            }}
        />
    );
}
