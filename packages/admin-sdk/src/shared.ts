/**
 * The modules a plugin bundle shares with the panel instead of bundling
 * its own copy (two copies of React break hooks; two of Inertia don't see
 * each other's pages). The panel puts them on `window.Sunrice.modules`;
 * the Vite plugin points imports of these names there.
 *
 * The export lists are checked against the real modules by the panel's
 * tests (tests/Frontend/shared-modules.test.ts).
 */

/** Bumped when the runtime changes incompatibly. Plugins built for another major version refuse to load. */
export const SDK_VERSION = 3;

export const SHARED_MODULES = {
    react: [
        'Activity', 'Children', 'Component', 'Fragment', 'Profiler', 'PureComponent', 'StrictMode', 'Suspense', 'ViewTransition',
        'act', 'addTransitionType', 'cache', 'cacheSignal', 'captureOwnerStack', 'cloneElement', 'createContext', 'createElement',
        'createRef', 'forwardRef', 'isValidElement', 'lazy', 'memo', 'startTransition', 'unstable_useCacheRefresh', 'use',
        'useActionState', 'useCallback', 'useContext', 'useDebugValue', 'useDeferredValue', 'useEffect', 'useEffectEvent', 'useId',
        'useImperativeHandle', 'useInsertionEffect', 'useLayoutEffect', 'useMemo', 'useOptimistic', 'useReducer', 'useRef',
        'useState', 'useSyncExternalStore', 'useTransition', 'version',
    ],
    'react/jsx-runtime': ['Fragment', 'jsx', 'jsxs'],
    'react-dom': [
        'createPortal', 'flushSync', 'preconnect', 'prefetchDNS', 'preinit', 'preinitModule', 'preload', 'preloadModule',
        'requestFormReset', 'unstable_batchedUpdates', 'useFormState', 'useFormStatus', 'version',
    ],
    'react-dom/client': ['createRoot', 'hydrateRoot', 'version'],
    '@inertiajs/react': [
        'App', 'Deferred', 'Form', 'Head', 'InfiniteScroll', 'Link', 'WhenMounted', 'WhenVisible', 'config', 'createInertiaApp',
        'http', 'progress', 'resetLayoutProps', 'router', 'setLayoutProps', 'useForm', 'useFormContext', 'useHttp', 'usePage',
        'usePoll', 'usePrefetch', 'useRemember',
    ],
    '@imajiner/admin-sdk/ui': [
        'Badge', 'Button', 'Card', 'CardContent', 'CardDescription', 'CardFooter', 'CardHeader', 'CardTitle', 'Checkbox', 'CodeEditor', 'CollapsibleCard',
        'Command', 'CommandEmpty', 'CommandGroup', 'CommandInput', 'CommandItem', 'CommandList', 'CommandSeparator', 'DatePicker', 'DeveloperHelp',
        'Dialog', 'DialogContent', 'DialogDescription', 'DialogFooter', 'DialogHeader', 'DialogTitle', 'DialogTrigger',
        'DropdownMenu', 'DropdownMenuCheckboxItem', 'DropdownMenuContent', 'DropdownMenuItem', 'DropdownMenuLabel', 'DropdownMenuSeparator', 'DropdownMenuTrigger',
        'FormSections', 'Input', 'KeptEditsNotice', 'Label', 'LanguageTabs', 'MediaPickerInput', 'moveItem', 'PageHeader', 'Popover', 'PopoverAnchor', 'PopoverContent', 'PopoverTrigger', 'registerCommandSource', 'RichTextEditor',
        'Select', 'SelectContent', 'SelectItem', 'SelectTrigger', 'SelectValue', 'Separator', 'SettingsLayout', 'SettingsNav', 'Skeleton', 'SortableHandle', 'SortableList', 'Switch',
        'Table', 'TableBody', 'TableCell', 'TableHead', 'TableHeader', 'TableRow', 'Tabs', 'TabsContent', 'TabsList', 'TabsTrigger',
        'Textarea', 'Tooltip', 'TooltipContent', 'TooltipTrigger', 'toast', 'TrashBanner', 'TrashHint', 'TrashTitle', 'useBreadcrumbs', 'useEditLock', 'useTablePreferences', 'useUnsavedChanges', 'ViewingNotice',
    ],
} as const satisfies Record<string, readonly string[]>;

export type SharedModuleName = keyof typeof SHARED_MODULES;
