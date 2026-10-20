import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon } from 'lucide-react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';

const Toaster = ({ ...props }: ToasterProps) => {
    const { appearance } = useAppearance();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            icons={{
                success: <CircleCheckIcon className="size-4" />,
                info: <InfoIcon className="size-4" />,
                warning: <TriangleAlertIcon className="size-4" />,
                error: <OctagonXIcon className="size-4" />,
                loading: <Loader2Icon className="size-4 animate-spin" />,
            }}
            offset={16}
            mobileOffset={16}
            toastOptions={{ classNames: { toast: 'rounded-xl! border-border/60! shadow-pop! backdrop-blur-md!' } }}
            style={
                {
                    '--normal-bg': 'var(--surface-glass)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--border-radius': 'calc(var(--radius) + 4px)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
};

export { Toaster };
