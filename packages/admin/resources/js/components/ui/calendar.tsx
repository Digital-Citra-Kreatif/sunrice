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
}

const dayKey = (day: Date) => format(day, "yyyy-MM-dd")

function Calendar({ selected = null, onSelect, defaultMonth, locale = enUS, className, ...props }: CalendarProps) {
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
                        className="flex aspect-square size-auto w-full min-w-(--cell-size) flex-col gap-1 leading-none font-normal focus-visible:relative focus-visible:z-10 data-[outside=true]:text-muted-foreground data-[today=true]:bg-accent data-[today=true]:text-accent-foreground data-[selected-single=true]:bg-primary data-[selected-single=true]:text-primary-foreground dark:hover:text-accent-foreground"
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
