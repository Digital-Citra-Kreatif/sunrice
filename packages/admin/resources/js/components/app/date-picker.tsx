import * as React from 'react';
import { CalendarIcon, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { dateLabel, formatDateValue, parseDateValue } from '@/lib/date-value';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

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
export function DatePicker({ id, value, onChange, withTime = false, disabled = false, invalid = false, required = false, placeholder, className }: DatePickerProps) {
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
                    <span className="truncate">{label || (placeholder ?? t('Pick a date'))}</span>
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
                                <Label htmlFor={id ? `${id}-time` : undefined} className="text-xs text-muted-foreground">{t('Time')}</Label>
                                <Input
                                    id={id ? `${id}-time` : undefined}
                                    type="time"
                                    className="h-8 w-28 text-sm"
                                    value={time}
                                    disabled={!date}
                                    onChange={(event) => onChange(formatDateValue(date, event.target.value, true))}
                                />
                            </>
                        )}
                        {label && !required && (
                            <Button type="button" variant="ghost" size="sm" className="ml-auto text-muted-foreground" onClick={() => { onChange(null); setOpen(false); }}>
                                <X />
                                {t('Clear')}
                            </Button>
                        )}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
