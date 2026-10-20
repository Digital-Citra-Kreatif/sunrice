/*
 * A filterable list of items with a search box: shadcn's `command`, on a
 * small in-house list instead of cmdk. The input is a combobox that keeps
 * focus while Up/Down/Home/End move the active option
 * (aria-activedescendant) and Enter chooses it. Items match with the command
 * palette's ranking (commandScore) on their `value` (their text when none is
 * given) and `keywords`; `shouldFilter={false}` shows them all, as given.
 */
import * as React from "react"
import { SearchIcon } from "lucide-react"
import { cn } from "@/lib/utils"
import { displayOrder, isNavigationKey, itemScore, nextActive } from "@/lib/command-list"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"

interface Entry {
  value: string
  group: string | null
  score: number
  visible: boolean
  disabled: boolean
  select: () => void
}

/* The items rendered in one list, so groups and the empty state know what shows. */
class Registry {
  entries = new Map<string, Entry>()
  version = 0
  private listeners = new Set<() => void>()

  subscribe = (listener: () => void) => {
    this.listeners.add(listener)
    return () => { this.listeners.delete(listener) }
  }

  snapshot = () => this.version

  set(id: string, entry: Entry | null) {
    if (entry) this.entries.set(id, entry)
    else this.entries.delete(id)
    this.version++
    this.listeners.forEach((listener) => listener())
  }
}

interface CommandContextValue {
  registry: Registry
  search: string
  setSearch: (search: string) => void
  shouldFilter: boolean
  active: string | undefined
  setActive: (value: string) => void
  activeId: string | undefined
  listId: string
  ready: boolean
}

const CommandContext = React.createContext<CommandContextValue | null>(null)
const GroupContext = React.createContext<string | null>(null)

function useCommand(): CommandContextValue {
  const context = React.useContext(CommandContext)
  if (!context) throw new Error("Command parts must be inside <Command>.")
  return context
}

function useRegistry(registry: Registry): number {
  return React.useSyncExternalStore(registry.subscribe, registry.snapshot, registry.snapshot)
}

function Command({
  className,
  shouldFilter = true,
  loop = true,
  label,
  onKeyDown,
  children,
  ...props
}: React.ComponentProps<"div"> & {
  /** False when the items are already the matches (a server search). */
  shouldFilter?: boolean
  /** Down from the last item goes back to the first (default true). */
  loop?: boolean
  /** The list's accessible name. */
  label?: string
}) {
  const [registry] = React.useState(() => new Registry())
  const version = useRegistry(registry)
  const [search, setSearch] = React.useState("")
  const [active, setActive] = React.useState<string>()
  const [ready, setReady] = React.useState(false)
  const root = React.useRef<HTMLDivElement>(null)
  const lastSearch = React.useRef(search)
  const listId = React.useId()

  /* The selectable values in the order they show. */
  const ordered = React.useCallback((): string[] => {
    const elements = Array.from(root.current?.querySelectorAll<HTMLElement>('[data-slot="command-item"]') ?? [])
      .filter((element) => !element.hidden && element.getAttribute("aria-disabled") !== "true")
    const order = displayOrder(elements.map((element) => ({
      score: Number(element.dataset.score ?? 1),
      group: element.dataset.group ?? null,
    })))
    return order.map((index) => elements[index]!.dataset.value ?? "")
  }, [])

  React.useLayoutEffect(() => setReady(true), [])

  /* A new search, or the active item gone: the first match is active. */
  React.useLayoutEffect(() => {
    const values = ordered()
    if (lastSearch.current !== search || active === undefined || !values.includes(active)) {
      lastSearch.current = search
      setActive(values[0])
    }
  }, [version, search, active, ordered])

  const activeId = active === undefined
    ? undefined
    : [...registry.entries].find(([, entry]) => entry.visible && entry.value === active)?.[0]

  React.useEffect(() => {
    if (activeId) document.getElementById(activeId)?.scrollIntoView?.({ block: "nearest" })
  }, [activeId])

  const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    onKeyDown?.(event)
    if (event.defaultPrevented || event.nativeEvent.isComposing) return
    if (isNavigationKey(event.key)) {
      event.preventDefault()
      const next = nextActive(ordered(), active, event.key, loop)
      if (next !== undefined) setActive(next)
    } else if (event.key === "Enter" && activeId) {
      event.preventDefault()
      registry.entries.get(activeId)?.select()
    }
  }

  const context = React.useMemo<CommandContextValue>(
    () => ({ registry, search, setSearch, shouldFilter, active, setActive, activeId, listId, ready }),
    [registry, search, shouldFilter, active, activeId, listId, ready]
  )

  return (
    <CommandContext.Provider value={context}>
      <div
        ref={root}
        data-slot="command"
        aria-label={label}
        className={cn(
          "flex h-full w-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground",
          className
        )}
        onKeyDown={handleKeyDown}
        {...props}
      >
        {children}
      </div>
    </CommandContext.Provider>
  )
}

function CommandDialog({
  title = "Command Palette",
  description = "Search for a command to run...",
  children,
  className,
  showCloseButton = true,
  shouldFilter = true,
  ...props
}: React.ComponentProps<typeof Dialog> & {
  title?: string
  description?: string
  className?: string
  showCloseButton?: boolean
  shouldFilter?: boolean
}) {
  return (
    <Dialog {...props}>
      <DialogContent
        className={cn("overflow-hidden p-0", className)}
        showCloseButton={showCloseButton}
      >
        <DialogHeader className="sr-only">
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        <Command shouldFilter={shouldFilter} className="**:data-[slot=command-group]:px-2 **:data-[slot=command-group-heading]:font-medium **:data-[slot=command-input]:h-12 **:data-[slot=command-input-wrapper]:h-12 **:data-[slot=command-input-wrapper]:[&_svg]:size-5 **:data-[slot=command-item]:px-2 **:data-[slot=command-item]:py-3 **:data-[slot=command-item]:[&_svg]:size-5">
          {children}
        </Command>
      </DialogContent>
    </Dialog>
  )
}

function CommandInput({
  className,
  value,
  onValueChange,
  onChange,
  ...props
}: Omit<React.ComponentProps<"input">, "value"> & {
  value?: string
  onValueChange?: (search: string) => void
}) {
  const { search, setSearch, listId, activeId } = useCommand()

  React.useLayoutEffect(() => {
    if (value !== undefined) setSearch(value)
  }, [value, setSearch])

  return (
    <div
      data-slot="command-input-wrapper"
      className="flex h-9 items-center gap-2 border-b px-3"
    >
      <SearchIcon className="size-4 shrink-0 opacity-50" />
      <input
        data-slot="command-input"
        type="text"
        role="combobox"
        aria-expanded={true}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={activeId}
        autoComplete="off"
        autoCorrect="off"
        spellCheck={false}
        className={cn(
          "flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50",
          className
        )}
        value={value ?? search}
        onChange={(event) => {
          onChange?.(event)
          if (value === undefined) setSearch(event.target.value)
          onValueChange?.(event.target.value)
        }}
        {...props}
      />
    </div>
  )
}

function CommandList({
  className,
  children,
  ...props
}: React.ComponentProps<"div">) {
  const { listId } = useCommand()

  return (
    <div
      id={listId}
      role="listbox"
      data-slot="command-list"
      className={cn(
        "max-h-[300px] scroll-py-1 overflow-x-hidden overflow-y-auto",
        className
      )}
      {...props}
    >
      <div className="flex flex-col">{children}</div>
    </div>
  )
}

function CommandEmpty({ className, ...props }: React.ComponentProps<"div">) {
  const { registry, ready } = useCommand()
  useRegistry(registry)
  const empty = ready && ![...registry.entries.values()].some((entry) => entry.visible)
  if (!empty) return null

  return (
    <div
      role="presentation"
      data-slot="command-empty"
      className={cn("py-6 text-center text-sm", className)}
      {...props}
    />
  )
}

function CommandLoading({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      role="status"
      aria-busy={true}
      data-slot="command-loading"
      className={cn("order-first", className)}
      {...props}
    />
  )
}

function CommandGroup({
  className,
  heading,
  children,
  ...props
}: Omit<React.ComponentProps<"div">, "children"> & {
  heading?: React.ReactNode
  children?: React.ReactNode
}) {
  const { registry, search, shouldFilter } = useCommand()
  useRegistry(registry)
  const id = React.useId()
  const headingId = `${id}-heading`
  const entries = [...registry.entries.values()].filter((entry) => entry.group === id)
  const visible = entries.filter((entry) => entry.visible)
  const best = Math.max(0, ...visible.map((entry) => entry.score))

  return (
    <div
      data-slot="command-group"
      role="presentation"
      hidden={entries.length > 0 && visible.length === 0}
      style={shouldFilter && search ? { order: -best } : undefined}
      className={cn("overflow-hidden p-1 text-foreground", className)}
      {...props}
    >
      {heading && (
        <div
          id={headingId}
          aria-hidden={true}
          data-slot="command-group-heading"
          className="px-2 py-1.5 text-xs font-medium text-muted-foreground"
        >
          {heading}
        </div>
      )}
      <div role="group" aria-labelledby={heading ? headingId : undefined} className="flex flex-col">
        <GroupContext.Provider value={id}>{children}</GroupContext.Provider>
      </div>
    </div>
  )
}

function CommandSeparator({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const { search, shouldFilter } = useCommand()
  if (shouldFilter && search) return null

  return (
    <div
      role="separator"
      data-slot="command-separator"
      className={cn("-mx-1 h-px bg-border", className)}
      {...props}
    />
  )
}

function CommandItem({
  className,
  value,
  keywords,
  disabled = false,
  onSelect,
  onClick,
  onPointerMove,
  children,
  ...props
}: Omit<React.ComponentProps<"div">, "onSelect"> & {
  /** What the item matches on and is known by (its text when not given). */
  value?: string
  /** Other words it matches on. */
  keywords?: string[]
  disabled?: boolean
  /** Chosen with a click or Enter; gets the item's value. */
  onSelect?: (value: string) => void
}) {
  const { registry, search, shouldFilter, active, setActive } = useCommand()
  const group = React.useContext(GroupContext)
  const id = React.useId()
  const ref = React.useRef<HTMLDivElement>(null)
  const [text, setText] = React.useState(typeof children === "string" ? children : "")
  const itemValue = (value ?? text).trim()
  const score = shouldFilter ? itemScore(itemValue, keywords, search) : 1
  const visible = score > 0
  const selectRef = React.useRef(onSelect)
  selectRef.current = onSelect

  React.useLayoutEffect(() => {
    if (value === undefined) setText(ref.current?.textContent ?? "")
  })

  React.useLayoutEffect(() => {
    registry.set(id, { value: itemValue, group, score, visible, disabled, select: () => selectRef.current?.(itemValue) })
  }, [registry, id, itemValue, group, score, visible, disabled])

  React.useLayoutEffect(() => () => registry.set(id, null), [registry, id])

  const selected = visible && !disabled && active === itemValue

  return (
    <div
      ref={ref}
      id={id}
      role="option"
      aria-selected={selected}
      aria-disabled={disabled}
      hidden={!visible}
      data-slot="command-item"
      data-value={itemValue}
      data-group={group ?? undefined}
      data-score={score}
      data-selected={selected}
      data-disabled={disabled}
      style={shouldFilter && search ? { order: -score } : undefined}
      className={cn(
        "relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground",
        className
      )}
      onPointerMove={(event) => {
        onPointerMove?.(event)
        if (!disabled && active !== itemValue) setActive(itemValue)
      }}
      onClick={(event) => {
        onClick?.(event)
        if (!disabled) onSelect?.(itemValue)
      }}
      {...props}
    >
      {children}
    </div>
  )
}

function CommandShortcut({
  className,
  ...props
}: React.ComponentProps<"span">) {
  return (
    <span
      data-slot="command-shortcut"
      className={cn(
        "ml-auto text-xs tracking-widest text-muted-foreground",
        className
      )}
      {...props}
    />
  )
}

export {
  Command,
  CommandDialog,
  CommandInput,
  CommandList,
  CommandEmpty,
  CommandLoading,
  CommandGroup,
  CommandItem,
  CommandShortcut,
  CommandSeparator,
}
