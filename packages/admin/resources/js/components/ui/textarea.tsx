import * as React from "react"
import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "flex field-sizing-content min-h-24 w-full rounded-md border border-input/80 bg-card px-3 py-3 text-base leading-relaxed shadow-xs transition-[color,border-color,box-shadow] duration-150 ease-out-quart outline-none placeholder:text-muted-foreground hover:border-ring/45 focus-visible:border-ring/70 focus-visible:ring-4 focus-visible:ring-ring-glow disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/20 dark:aria-invalid:ring-destructive/40",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
