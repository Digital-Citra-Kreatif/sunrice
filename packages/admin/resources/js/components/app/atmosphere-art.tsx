import { cn } from '@/lib/utils';

/** Decoration for the sign-in pages: two orbits and three orbs in the panel's colours (admin.css). */
export function AtmosphereArt({ className }: { className?: string }) {
    return (
        <div aria-hidden="true" className={cn('pointer-events-none relative', className)}>
            <div className="sunrice-orbit top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
            <div className="sunrice-orbit top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 opacity-50" style={{ transform: 'rotate(35deg) scaleY(0.65)' }} />
            <div className="sunrice-orb top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
            <div className="sunrice-orb top-[18%] left-[24%] size-8!" />
            <div className="sunrice-orb right-[16%] bottom-[18%] size-12!" />
        </div>
    );
}
