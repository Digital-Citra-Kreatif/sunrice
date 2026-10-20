import { cn } from "@/lib/utils"

function Skeleton({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="skeleton"
      className={cn("sunrice-shimmer rounded-md bg-accent", className)}
      {...props}
    />
  )
}

export { Skeleton }
