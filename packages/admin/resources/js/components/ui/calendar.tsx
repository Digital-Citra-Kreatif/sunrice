/*
 * A month grid for picking one day, on date-fns. It reads as a grid: one
 * day is in the tab order, arrows move by a day or a week, PageUp/PageDown
 * by a month (a year with Shift), Home/End to the ends of the week, and
 * Enter or Space chooses (the days are buttons).
 */
import * as React from "react"
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react"
import { format, isSameDay, isSameMonth, startOfMonth, startOfToday, type Locale } from "date-fns"
import { enUS } from "date-fns/locale/en-US"
import { cn } from "@/lib/utils"
import { firstDayOfWeek, monthWeeks, moveFocus, tabbableDay, weekdays } from "@/lib/calendar-grid"
import { Button } from "@/components/ui/button"

export interface CalendarProps extends Omit<React.ComponentProps<"div">, "onSelect" | "defaultValue"> {
  selected?: Date | null
  onSelect?: (day: Date) => void
  /** The month shown first (the selected day's, else this month). */
  defaultMonth?: Date
  /** date-fns locale: names, and the first day of the week (en-US by default). */
  locale?: Locale
  /** Focus the day in the tab order (the selected day, else today) when shown. */
  autoFocus?: boolean
}

const dayKey = (day: Date) => format(day, "yyyy-MM-dd")

function Calendar({ selected = null, onSelect, defaultMonth, locale = enUS, autoFocus = false, className, ...props }: CalendarProps) {
  const today = startOfToday()
  const weekStartsOn = firstDayOfWeek(locale)
  const [month, setMonth] = React.useState(() => startOfMonth(defaultMonth ?? selected ?? today))
  const [focused, setFocused] = React.useState<Date | null>(null)
  const moveFocusTo = React.useRef(false)
  const root = React.useRef<HTMLDivElement>(null)
  const captionId = React.useId()
  const tabbable = tabbableDay(month, focused, selected, today)

  React.useEffect(() => {
    if (!moveFocusTo.current || !focused) return
    moveFocusTo.current = false
    root.current?.querySelector<HTMLButtonElement>(`[data-day="${dayKey(focused)}"]`)?.focus()
  }, [focused])

  React.useEffect(() => {
    if (autoFocus) root.current?.querySelector<HTMLButtonElement>('[data-day][tabindex="0"]')?.focus({ preventScroll: true })
    // Only when first shown.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const focusDay = (day: Date) => {
    setFocused(day)
    if (!isSameMonth(day, month)) setMonth(startOfMonth(day))
  }

  const showMonth = (next: Date) => {
    setFocused(null)
    setMonth(startOfMonth(next))
  }

  const handleKeyDown = (event: React.KeyboardEvent<HTMLTableElement>) => {
    const next = moveFocus(focused ?? tabbable, event, weekStartsOn)
    if (!next) return
    event.preventDefault()
    moveFocusTo.current = true
    focusDay(next)
  }

  return (
    <div
      ref={root}
      data-slot="calendar"
      className={cn(
        "group/calendar relative w-fit bg-background p-3 [--cell-size:--spacing(8)] [[data-slot=card-content]_&]:bg-transparent [[data-slot=popover-content]_&]:bg-transparent",
        className
      )}
      {...props}
    >
      <div className="absolute inset-x-3 top-3 flex items-center justify-between gap-1">
        <Button type="button" variant="ghost" size="icon" className="size-(--cell-size) p-0 select-none" aria-label="Go to the previous month" onClick={() => showMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1))}>
          <ChevronLeftIcon className="size-4 rtl:rotate-180" />
        </Button>
        <Button type="button" variant="ghost" size="icon" className="size-(--cell-size) p-0 select-none" aria-label="Go to the next month" onClick={() => showMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1))}>
          <ChevronRightIcon className="size-4 rtl:rotate-180" />
        </Button>
      </div>
      <div className="flex w-full flex-col gap-4">
        <div className="flex h-(--cell-size) w-full items-center justify-center px-(--cell-size)">
          <span id={captionId} role="status" aria-live="polite" className="text-sm font-medium select-none">
            {format(month, "LLLL yyyy", { locale })}
          </span>
        </div>
        <table role="grid" aria-labelledby={captionId} className="w-full border-collapse" onKeyDown={handleKeyDown}>
          <thead>
            <tr className="flex">
              {weekdays(weekStartsOn).map((day) => (
                <th key={day.getDay()} scope="col" aria-label={format(day, "cccc", { locale })} className="flex-1 rounded-md text-[0.8rem] font-normal text-muted-foreground select-none">
                  {format(day, "cccccc", { locale })}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {monthWeeks(month, weekStartsOn).map((week) => (
              <tr key={dayKey(week[0]!)} className="mt-2 flex w-full">
                {week.map((day) => {
                  const isSelected = selected !== null && isSameDay(day, selected)
                  const isToday = isSameDay(day, today)
                  const outside = !isSameMonth(day, month)
                  return (
                    <td key={dayKey(day)} role="gridcell" aria-selected={isSelected} className="relative aspect-square h-full w-full p-0 text-center select-none">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        data-day={dayKey(day)}
                        data-today={isToday && !isSelected}
                        data-outside={outside}
                        data-selected-single={isSelected}
                        tabIndex={isSameDay(day, tabbable) ? 0 : -1}
                        aria-label={format(day, "PPPP", { locale })}
                        aria-current={isToday ? "date" : undefined}
                        className="relative flex aspect-square size-auto w-full min-w-(--cell-size) flex-col gap-1 leading-none font-normal tabular-nums focus-visible:z-10 data-[outside=true]:text-muted-foreground/70 data-[today=true]:font-semibold data-[today=true]:after:absolute data-[today=true]:after:bottom-1 data-[today=true]:after:size-1 data-[today=true]:after:rounded-full data-[today=true]:after:bg-primary data-[selected-single=true]:bg-primary data-[selected-single=true]:font-medium data-[selected-single=true]:text-primary-foreground data-[selected-single=true]:hover:bg-primary/90 dark:hover:text-accent-foreground dark:data-[selected-single=true]:hover:text-primary-foreground"
                        onClick={() => {
                          focusDay(day)
                          onSelect?.(day)
                        }}
                      >
                        {format(day, "d", { locale })}
                      </Button>
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export { Calendar }
