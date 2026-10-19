import { cn } from '@/lib/utils';

/** A field's validation message, under the field. */
export function InputError({ message, className }: { message?: string; className?: string }) {
    return message ? <p role="alert" className={cn('text-sm text-destructive', className)}>{message}</p> : null;
}
