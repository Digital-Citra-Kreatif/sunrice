import { j as n, t as u, a as Ie } from "./admin-m-MSHeg_.js";
import { c as se, _ as j } from "./createLucideIcon-Ccbp7F45.js";
import { u as d } from "./ui-Dh5wfMwT.js";
import { t as Pe, a as z, E as be, o as Ve, d as de, b as Ue, g as Ke, i as ve, n as We, c as Ge, s as Xe } from "./EntriesPicker-Bma-RF4K.js";
import { F as H } from "./FieldError-4x4zIf-o.js";
import { C as Qe } from "./check-OeKFYRnl.js";
import { X as Ze } from "./EntryStatus-DcLJvSTt.js";
import { S as Je } from "./search-3zKi7F1_.js";
import { P as je } from "./plus-COqahUZ2.js";
import { T as ke } from "./trash-DHN6ewjn.js";
import { C as Ye } from "./chevron-right-9qVV6b-Q.js";
const we = {
  name: "chevron-down",
  size: 24,
  node: [["path", { d: "m6 9 6 6 6-6", key: "qrunsl" }]]
};
we.node;
const es = se(we);
const Ne = {
  name: "chevrons-down-up",
  size: 24,
  node: [
    ["path", { d: "m7 20 5-5 5 5", key: "13a0gw" }],
    ["path", { d: "m7 4 5 5 5-5", key: "1kwcof" }]
  ]
};
Ne.node;
const ss = se(Ne);
const $e = {
  name: "chevrons-up-down",
  size: 24,
  node: [
    ["path", { d: "m7 15 5 5 5-5", key: "1hf1tw" }],
    ["path", { d: "m7 9 5-5 5 5", key: "sgt6xg" }]
  ]
};
$e.node;
const Ce = se($e);
function ts(e) {
  return e === null ? [] : Array.isArray(e) ? e : [e];
}
function Ae(e, s, t) {
  return s && t !== null && e.length >= t;
}
function ns(e, s, t, c) {
  return t ? e.includes(s) ? e.filter((r) => r !== s) : Ae(e, t, c) ? e.slice() : [...e, s] : [s];
}
function rs(e) {
  return e.slice(0, -1);
}
function cs(e, s) {
  return s ? e.slice() : e[0] ?? null;
}
function as(e, s, t, c = () => !1) {
  const r = s === "ArrowDown" || s === "Home" ? 1 : s === "ArrowUp" || s === "End" ? -1 : 0;
  if (r === 0 || t === 0) return e;
  let l = s === "Home" ? 0 : s === "End" ? t - 1 : e + r;
  for (s === "ArrowUp" && e === -1 && (l = t - 1); l >= 0 && l < t && c(l); ) l += r;
  return l >= 0 && l < t ? l : e;
}
function ls(e, s) {
  const t = new Set(e.map((c) => c.id));
  return [...e, ...s.filter((c) => !t.has(c.id))];
}
function os(e) {
  const s = [], t = {};
  for (const c of e)
    s.length = Math.min(s.length, c.depth), t[c.id] = s.slice(), s.push(c.label);
  return t;
}
function fe(e, s) {
  return [...s ?? [], e].join(" › ");
}
const is = j.createContext({});
async function J(e, s) {
  const t = await fetch(`${Ie("cms/terms/picker")}?${Pe(e)}`, { headers: { Accept: "application/json" }, credentials: "same-origin", signal: s });
  if (!t.ok) throw new Error(String(t.status));
  return await t.json();
}
function ms({ id: e, taxonomy: s, multiple: t, max: c, required: r, invalid: l, value: i, onChange: m }) {
  const o = j.useContext(is), f = ts(i), [w, k] = j.useState(!1), [p, b] = j.useState(""), [g, x] = j.useState(null), [h, N] = j.useState(!1), [A, v] = j.useState(!1), [D, E] = j.useState(0), [F, Re] = j.useState({}), [C, B] = j.useState(-1), [I, Me] = j.useState(null), P = j.useRef(null), V = j.useRef(null), ce = j.useRef(null), ae = j.useRef(null), U = p.trim(), R = j.useCallback((a, y) => {
    const $ = y ? os(a) : {};
    Re((q) => {
      const L = { ...q };
      for (const O of a) L[O.id] = { label: O.label, parents: $[O.id] ?? q[O.id]?.parents };
      return L;
    });
  }, []), S = w && s ? U : null;
  j.useEffect(() => {
    if (S === null || s === null || ae.current === `${s}
${S}`) return;
    const a = new AbortController(), y = window.setTimeout(() => {
      J({ taxonomy: s, q: S }, a.signal).then(($) => {
        ae.current = `${s}
${S}`, x({ ...$, q: S }), R($.items, S === ""), B(S !== "" && $.items.length > 0 ? 0 : -1), v(!1);
      }).catch(($) => {
        $ instanceof DOMException && $.name === "AbortError" || v(!0);
      });
    }, S === "" ? 0 : 250);
    return () => {
      window.clearTimeout(y), a.abort();
    };
  }, [s, S, D, R]);
  const le = (a) => o[String(a)] ?? F[a]?.label, K = f.filter((a) => le(a) === void 0).join(",");
  j.useEffect(() => {
    !s || K === "" || J({ taxonomy: s, ids: K.split(",").map(Number) }).then((a) => R(a.items, !1)).catch(() => {
    });
  }, [s, K, R]);
  const W = () => {
    !s || !g?.more || h || g.q !== U || (N(!0), J({ taxonomy: s, q: g.q, offset: g.items.length }).then((a) => {
      const y = ls(g.items, a.items);
      x({ items: y, more: a.more, q: g.q }), R(y, g.q === "");
    }).catch(() => v(!0)).finally(() => N(!1)));
  };
  if (j.useEffect(() => {
    C >= 0 && g && document.getElementById(`${e}-option-${g.items[C]?.id}`)?.scrollIntoView({ block: "nearest" });
  }, [C, e, g]), !s) return /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: u("Choose a taxonomy for this field in its blueprint.") });
  const oe = Ae(f, t, c), G = t || !r, X = (a) => m(cs(a, t)), ie = (a) => {
    X(ns(f, a, t, c)), t || k(!1);
  }, T = g?.items ?? [], Q = (a) => oe && !f.includes(T[a]?.id ?? -1), Z = (a) => le(a) ?? u("Term #:id", { id: a }), Le = (a) => {
    if (a && P.current && V.current) {
      const y = P.current.getBoundingClientRect(), $ = V.current.getBoundingClientRect();
      Me({ width: y.width, alignOffset: y.left - $.left, sideOffset: 4 + y.bottom - $.bottom });
    }
    a || b(""), k(a);
  }, He = (a) => {
    if (a.key === "ArrowDown" || a.key === "ArrowUp") {
      a.preventDefault();
      const y = as(C, a.key, T.length, Q);
      a.key === "ArrowDown" && y === C && C === T.length - 1 && W(), B(y);
    } else if (a.key === "Enter") {
      a.preventDefault();
      const y = T[C];
      y && !Q(C) && ie(y.id);
    } else a.key === "Backspace" && p === "" && G && f.length > 0 && (a.preventDefault(), X(rs(f)));
  }, Fe = (a) => {
    const y = a.currentTarget;
    y.scrollTop + y.clientHeight >= y.scrollHeight - 48 && W();
  }, Be = t ? u("Choose terms…") : u("Choose a term…"), me = `${e}-listbox`, ue = g !== null && g.q !== U;
  let M;
  return A ? M = /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:items-start cms:gap-2 cms:p-3 cms:text-sm", children: [
    /* @__PURE__ */ n.jsx("p", { className: "cms:text-destructive", children: u("Terms could not be loaded.") }),
    /* @__PURE__ */ n.jsx(d.Button, { type: "button", variant: "outline", size: "sm", onClick: () => {
      v(!1), E((a) => a + 1);
    }, children: u("Try again") })
  ] }) : g === null ? M = /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-col cms:gap-2 cms:p-2", "aria-busy": "true", "aria-label": u("Loading terms"), children: [70, 55, 80, 45, 65].map((a) => /* @__PURE__ */ n.jsx(d.Skeleton, { className: "cms:h-5", style: { width: `${a}%` } }, a)) }) : T.length === 0 ? M = /* @__PURE__ */ n.jsx("p", { className: "cms:p-3 cms:text-sm cms:text-muted-foreground", children: g.q ? u("No terms match “:search”.", { search: g.q }) : u("Nothing to choose from yet.") }) : M = /* @__PURE__ */ n.jsxs("div", { className: `cms:max-h-72 cms:overflow-y-auto cms:overscroll-contain cms:p-1 ${ue ? "cms:opacity-60" : ""}`, onScroll: Fe, children: [
    /* @__PURE__ */ n.jsx("div", { role: "listbox", id: me, "aria-label": u("Terms"), "aria-multiselectable": t || void 0, "aria-busy": ue || void 0, children: T.map((a, y) => {
      const $ = f.includes(a.id), q = Q(y), L = fe(a.label, F[a.id]?.parents);
      return /* @__PURE__ */ n.jsxs(
        "div",
        {
          id: `${e}-option-${a.id}`,
          role: "option",
          "aria-selected": $,
          "aria-disabled": q || void 0,
          title: L,
          className: `cms:flex cms:cursor-default cms:items-center cms:gap-2 cms:rounded-sm cms:py-1.5 cms:pr-2 cms:text-sm cms:select-none ${y === C ? "cms:bg-accent cms:text-accent-foreground" : ""} ${q ? "cms:opacity-50" : ""}`,
          style: { paddingLeft: `${0.5 + Math.min(a.depth, 6) * 0.875}rem` },
          onMouseDown: (O) => O.preventDefault(),
          onMouseMove: () => {
            y !== C && B(y);
          },
          onClick: () => {
            q || ie(a.id);
          },
          children: [
            /* @__PURE__ */ n.jsx(Qe, { className: `cms:size-4 cms:shrink-0 ${$ ? "" : "cms:invisible"}`, "aria-hidden": "true" }),
            /* @__PURE__ */ n.jsx("span", { className: "cms:min-w-0 cms:truncate", children: a.label })
          ]
        },
        a.id
      );
    }) }),
    g.more && /* @__PURE__ */ n.jsx(d.Button, { type: "button", variant: "ghost", size: "sm", className: "cms:mt-1 cms:w-full", disabled: h, onMouseDown: (a) => a.preventDefault(), onClick: W, children: h ? u("Loading…") : u("Load more") })
  ] }), /* @__PURE__ */ n.jsxs(d.Popover, { open: w, onOpenChange: Le, children: [
    /* @__PURE__ */ n.jsxs(
      "div",
      {
        ref: P,
        "data-invalid": l || void 0,
        className: "cms:flex cms:w-full cms:max-w-xl cms:min-w-0 cms:[contain:inline-size] cms:flex-wrap cms:items-center cms:gap-1 cms:rounded-md cms:border cms:border-input/80 cms:bg-card cms:p-1 cms:shadow-xs cms:data-invalid:border-destructive cms:dark:bg-input/20",
        children: [
          f.map((a) => /* @__PURE__ */ n.jsxs(
            "span",
            {
              title: fe(Z(a), F[a]?.parents),
              className: "cms:inline-flex cms:h-7 cms:max-w-full cms:min-w-0 cms:items-center cms:gap-0.5 cms:rounded-sm cms:bg-secondary cms:pl-2 cms:text-xs cms:font-medium cms:text-secondary-foreground",
              children: [
                /* @__PURE__ */ n.jsx("span", { className: `cms:min-w-0 cms:truncate ${G ? "" : "cms:pr-2"}`, children: Z(a) }),
                G && /* @__PURE__ */ n.jsx(
                  "button",
                  {
                    type: "button",
                    className: "cms:inline-flex cms:size-7 cms:shrink-0 cms:items-center cms:justify-center cms:rounded-sm cms:text-muted-foreground cms:hover:bg-muted cms:hover:text-foreground cms:focus-visible:ring-2 cms:focus-visible:ring-ring cms:focus-visible:outline-none",
                    "aria-label": u("Remove :term", { term: Z(a) }),
                    onClick: () => X(f.filter((y) => y !== a)),
                    children: /* @__PURE__ */ n.jsx(Ze, { className: "cms:size-3", "aria-hidden": "true" })
                  }
                )
              ]
            },
            a
          )),
          /* @__PURE__ */ n.jsx(d.PopoverTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsxs(
            "button",
            {
              ref: V,
              id: e,
              type: "button",
              "aria-haspopup": "listbox",
              "aria-invalid": l || void 0,
              className: "cms:flex cms:h-7 cms:min-w-24 cms:flex-1 cms:items-center cms:justify-between cms:gap-1 cms:rounded-sm cms:px-2 cms:text-left cms:text-sm cms:text-muted-foreground cms:hover:bg-muted cms:focus-visible:ring-2 cms:focus-visible:ring-ring cms:focus-visible:outline-none cms:disabled:pointer-events-none cms:disabled:opacity-50",
              children: [
                /* @__PURE__ */ n.jsx("span", { className: "cms:truncate", children: f.length === 0 ? Be : t ? u("Add…") : u("Change…") }),
                /* @__PURE__ */ n.jsx(Ce, { className: "cms:size-4 cms:shrink-0 cms:opacity-60", "aria-hidden": "true" })
              ]
            }
          ) })
        ]
      }
    ),
    /* @__PURE__ */ n.jsxs(
      d.PopoverContent,
      {
        align: "start",
        alignOffset: I?.alignOffset,
        sideOffset: I?.sideOffset,
        className: "cms:max-w-[calc(100vw-2rem)] cms:min-w-60 cms:p-0",
        style: { width: I?.width },
        onOpenAutoFocus: (a) => {
          a.preventDefault(), ce.current?.focus();
        },
        children: [
          /* @__PURE__ */ n.jsxs("div", { className: "cms:relative cms:border-b cms:border-border/60", children: [
            /* @__PURE__ */ n.jsx(Je, { className: "cms:pointer-events-none cms:absolute cms:top-1/2 cms:left-3 cms:size-4 cms:-translate-y-1/2 cms:text-muted-foreground", "aria-hidden": "true" }),
            /* @__PURE__ */ n.jsx(
              "input",
              {
                ref: ce,
                type: "text",
                role: "combobox",
                "aria-expanded": "true",
                "aria-controls": me,
                "aria-autocomplete": "list",
                "aria-label": u("Search terms"),
                "aria-activedescendant": C >= 0 && T[C] ? `${e}-option-${T[C].id}` : void 0,
                className: "cms:h-10 cms:w-full cms:bg-transparent cms:pr-3 cms:pl-9 cms:text-sm cms:outline-none cms:placeholder:text-muted-foreground",
                placeholder: u("Search terms"),
                autoComplete: "off",
                value: p,
                onKeyDown: He,
                onChange: (a) => b(a.target.value)
              }
            )
          ] }),
          M,
          oe && c !== null && /* @__PURE__ */ n.jsx("p", { className: "cms:border-t cms:border-border/60 cms:px-3 cms:py-2 cms:text-xs cms:text-muted-foreground", children: u("Up to :max terms.", { max: c }) })
        ]
      }
    )
  ] });
}
const Se = (e, s = {}) => e.replace(/:([A-Za-z_]+)/g, (t, c) => s[c] ?? t), Is = 5;
let us = 0;
const te = () => `f${++us}`;
function ds(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").replace(/^[0-9_]+/, "").slice(0, 64);
}
function fs(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^[0-9_]+/, "").replace(/_{2,}/g, "_").slice(0, 64);
}
function _e(e, s) {
  const t = new Set(s.map((l) => l.handle)), c = ds(e) || "field";
  let r = c;
  for (let l = 2; t.has(r); l++)
    r = `${c}_${l}`;
  return r;
}
function ps(e) {
  return (e ?? []).map(({ fields: s, ...t }) => ({
    ...t,
    key: te(),
    fields: ps(s),
    handleTouched: !0,
    savedHandle: t.handle
  }));
}
function hs(e, s) {
  return e.map(({ key: t, handleTouched: c, savedHandle: r, fields: l, ...i }) => ({
    ...i,
    validation: (i.validation ?? []).map((m) => m.trim()).filter((m) => m !== ""),
    ...s(i.type) ? { fields: hs(l, s) } : {}
  }));
}
function Ps(e, s, t) {
  return { key: te(), type: e, label: s, handle: _e(s, t), required: !1, config: {}, fields: [], handleTouched: !1 };
}
function Vs(e) {
  return e.type !== "fieldset" && e.savedHandle !== void 0 && e.savedHandle !== "" && e.handle !== e.savedHandle ? e.savedHandle : null;
}
function xs(e) {
  return e.map((s) => ({ ...s, savedHandle: s.handle, fields: xs(s.fields) }));
}
function Us(e, s, t) {
  if (e.handleTouched) return { ...e, label: s };
  const c = t.filter((r) => r.key !== e.key);
  return { ...e, label: s, handle: _e(s || e.type, c) };
}
function gs(e, s) {
  const t = `${s}.`, c = {};
  for (const [r, l] of Object.entries(e))
    r.startsWith(t) && (c[r.slice(t.length)] = l);
  return c;
}
function ne(e, s) {
  const t = e[s];
  return { ...gs(e, s), ...t !== void 0 ? { "": t } : {} };
}
function Ks(e, s) {
  return Object.keys(e).filter((t) => t === s || t.startsWith(`${s}.`));
}
function Ws(e, s) {
  return Object.keys(e).some((t) => t === s || t.startsWith(`${s}.`));
}
function Gs(e, s) {
  return s.filter((t) => t.key !== e.key && t.handle !== "" && t.type !== "fieldset");
}
function Xs(e, s, t, c = Se) {
  return e.type === "fieldset" ? null : t?.includes(e.handle) ? c('":handle" is used by the content itself; choose another handle.', { handle: e.handle }) : e.handle !== "" && s.some((r) => r.key !== e.key && r.type !== "fieldset" && r.handle === e.handle) ? c('There is already a field ":handle" here.', { handle: e.handle }) : null;
}
function Qs(e, s) {
  return typeof e.translatable == "boolean" ? e.translatable : s;
}
function Zs(e, s, t) {
  return { ...e, translatable: s === t ? null : s };
}
function ys(e, s, t = []) {
  const c = /* @__PURE__ */ new Set([...s.map((i) => i.handle), ...t]), r = `${(fs(e) || "field").slice(0, 56)}_copy`;
  let l = r;
  for (let i = 2; c.has(l); i++)
    l = `${r}_${i}`;
  return l;
}
function Te(e) {
  const { key: s, savedHandle: t, fields: c, ...r } = e;
  return { ...structuredClone(r), key: te(), fields: c.map(Te) };
}
function Js(e, s, t = [], c = Se) {
  const r = e[s];
  if (r === void 0) return null;
  const l = r.label || r.handle, i = {
    ...Te(r),
    label: c(":label (copy)", { label: l }),
    handle: r.type === "fieldset" ? r.handle : ys(r.handle, e, t),
    handleTouched: !0
  };
  return { fields: [...e.slice(0, s + 1), i, ...e.slice(s + 1)], copy: i };
}
function bs(e, s) {
  const t = e.condition;
  return !t || "rules" in t ? null : (t.operator === "filled" || t.operator === "equals" && (t.value === !0 || t.value === "true")) && s.some((r) => r.handle === t.field && r.type === "toggle") ? t.field : null;
}
function Ys(e) {
  const s = (r) => r.type === "number" || r.type === "text" && (r.instructions ?? "").length <= 60, t = {}, c = [];
  for (const r of e) {
    const l = bs(r, e);
    l === null ? c.push(r) : (t[l] ??= []).push(r);
  }
  return { options: c.filter((r) => !s(r)), limits: c.filter(s), dependents: t };
}
const et = [
  { value: "equals", label: "is", needsValue: !0 },
  { value: "not_equals", label: "is not", needsValue: !0 },
  { value: "filled", label: "is filled in", needsValue: !1 },
  { value: "empty", label: "is empty", needsValue: !1 }
];
function pe(e) {
  return e === !0 ? "true" : e === !1 ? "false" : typeof e == "string" || typeof e == "number" ? String(e) : "";
}
function vs(e) {
  return e ? "rules" in e ? Array.isArray(e.rules) ? e.rules : [] : [e] : [];
}
function js(e) {
  return e && "rules" in e && e.match === "any" ? "any" : "all";
}
function st(e, s) {
  return e.length === 0 ? null : e.length === 1 ? e[0] : { match: s, rules: e };
}
function he(e, s) {
  const t = s[e.field], c = t != null && t !== "" && t !== !1 && !(Array.isArray(t) && t.length === 0), r = (Array.isArray(t) ? t : [t]).map(pe).includes(pe(e.value));
  switch (e.operator) {
    case "equals":
      return r;
    case "not_equals":
      return !r;
    case "filled":
      return c;
    default:
      return !c;
  }
}
function ks(e, s) {
  const t = vs(e.condition);
  return t.length === 0 ? !0 : js(e.condition) === "any" ? t.some((c) => he(c, s)) : t.every((c) => he(c, s));
}
function tt(e) {
  const s = e?.config ?? {};
  switch (e?.type) {
    case "select": {
      const t = (Array.isArray(s.options) ? s.options : []).flatMap((c) => {
        if (typeof c == "string" && c !== "") return [{ value: c, label: c }];
        if (c && typeof c == "object" && typeof c.value == "string" && c.value !== "") {
          const { value: r, label: l } = c;
          return [{ value: r, label: typeof l == "string" && l !== "" ? l : r }];
        }
        return [];
      });
      return t.length > 0 ? { kind: "options", options: t } : { kind: "text" };
    }
    case "toggle":
      return { kind: "toggle" };
    case "terms":
      return { kind: "terms", taxonomy: typeof s.taxonomy == "string" && s.taxonomy !== "" ? s.taxonomy : null };
    case "entries":
      return { kind: "entries", collections: Array.isArray(s.collections) ? s.collections.filter((t) => typeof t == "string") : [] };
    case "number":
      return { kind: "number" };
    default:
      return { kind: "text" };
  }
}
function nt(e) {
  switch (e.kind) {
    case "toggle":
      return !0;
    case "options":
      return e.options[0]?.value ?? "";
    case "terms":
    case "entries":
    case "number":
      return null;
    default:
      return "";
  }
}
const Y = "__none";
function ee(e) {
  const s = e.config?.options;
  return Array.isArray(s) ? s.flatMap((t) => {
    if (typeof t == "string") return [{ value: t, label: t }];
    if (t && typeof t == "object" && typeof t.value == "string") {
      const { value: c, label: r } = t;
      return [{ value: c, label: r || c }];
    }
    return [];
  }) : [];
}
const ws = ["repeater", "group", "flexible", "link", "media", "rich_text", "entries"];
function De(e) {
  if (e.label) return e.label;
  const s = e.handle.replace(/_/g, " ");
  return s.charAt(0).toUpperCase() + s.slice(1);
}
function xe({ field: e, value: s, onChange: t, errors: c, id: r, content: l = !1, fill: i = !1, marker: m = null }) {
  const o = De(e), f = ws.includes(e.type) || ["select", "terms"].includes(e.type) && e.config?.multiple === !0, w = c[""] !== void 0;
  return /* @__PURE__ */ n.jsxs(
    "div",
    {
      className: "cms:flex cms:flex-col cms:gap-2",
      ...f ? { role: "group", "aria-labelledby": `${r}-label`, ...w ? { "aria-invalid": !0, "aria-describedby": `${r}-error` } : {} } : {},
      children: [
        e.type === "toggle" ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-center cms:gap-3", children: [
          /* @__PURE__ */ n.jsx(d.Switch, { id: r, checked: s === !0, onCheckedChange: (k) => t(k) }),
          /* @__PURE__ */ n.jsx(d.Label, { htmlFor: r, children: o }),
          m
        ] }) : /* @__PURE__ */ n.jsxs(d.Label, { id: `${r}-label`, htmlFor: f ? void 0 : r, children: [
          o,
          e.required && /* @__PURE__ */ n.jsx("span", { className: "cms:text-destructive", children: " *" }),
          m
        ] }),
        /* @__PURE__ */ n.jsx($s, { field: e, value: s, onChange: t, errors: c, id: r, content: l, fill: i }),
        ["text", "textarea"].includes(e.type) && _(e, "max") !== void 0 && /* @__PURE__ */ n.jsx(Ns, { length: typeof s == "string" ? [...s].length : 0, max: _(e, "max") ?? 0 }),
        e.instructions && /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: e.instructions }),
        /* @__PURE__ */ n.jsx(H, { message: c[""], id: `${r}-error` })
      ]
    }
  );
}
function Ns({ length: e, max: s }) {
  const t = e > s;
  return /* @__PURE__ */ n.jsxs("p", { className: `cms:-mt-1 cms:self-end cms:text-xs cms:tabular-nums ${t ? "cms:font-medium cms:text-destructive" : "cms:text-muted-foreground"}`, children: [
    e,
    " / ",
    s,
    t && /* @__PURE__ */ n.jsxs("span", { children: [
      " · ",
      e - s,
      " too many"
    ] })
  ] });
}
function $s({ field: e, value: s, onChange: t, errors: c, id: r, content: l, fill: i = !1 }) {
  const m = c[""] !== void 0;
  switch (e.type) {
    case "toggle":
      return null;
    case "file":
      return /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: u("Visitors attach a file here when they send a form.") });
    case "textarea":
      return /* @__PURE__ */ n.jsx(
        d.Textarea,
        {
          id: r,
          "aria-invalid": m,
          value: typeof s == "string" ? s : "",
          rows: _(e, "rows") ?? 4,
          onChange: (o) => t(o.target.value)
        }
      );
    case "rich_text":
      return /* @__PURE__ */ n.jsx(d.RichTextEditor, { id: r, labelledBy: `${r}-label`, invalid: m, value: typeof s == "string" ? s : "", onChange: t });
    case "number": {
      const o = Ke(s);
      return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ n.jsx(
          d.Input,
          {
            id: r,
            type: "number",
            className: i ? "cms:w-full" : "cms:max-w-48",
            placeholder: ge(e, "placeholder"),
            "aria-invalid": m,
            "aria-describedby": o ? `${r}-grouped` : void 0,
            value: typeof s == "number" ? String(s) : typeof s == "string" ? s : "",
            min: _(e, "min"),
            max: _(e, "max"),
            step: _(e, "step") ?? "any",
            onChange: (f) => t(f.target.value === "" ? null : Number(f.target.value))
          }
        ),
        o && /* @__PURE__ */ n.jsx("span", { id: `${r}-grouped`, className: "cms:text-sm cms:tabular-nums cms:text-muted-foreground", children: o })
      ] });
    }
    case "date": {
      const o = e.config?.time === !0;
      return /* @__PURE__ */ n.jsx(
        d.DatePicker,
        {
          id: r,
          withTime: o,
          invalid: m,
          required: e.required === !0,
          value: de(Ue(s, o)),
          onChange: (f) => t(de(f ?? ""))
        }
      );
    }
    case "select":
      return e.config?.multiple === !0 ? /* @__PURE__ */ n.jsx(
        As,
        {
          options: ee(e),
          value: Array.isArray(s) ? s.map(String) : [],
          onChange: t,
          invalid: m,
          empty: typeof e.config?.empty == "string" ? e.config.empty : void 0,
          inTickOrder: e.config?.in_tick_order === !0
        }
      ) : /* @__PURE__ */ n.jsx(
        Cs,
        {
          options: ee(e),
          value: typeof s == "string" ? s : null,
          onChange: t,
          id: r,
          fill: i,
          empty: Ve(ee(e), e.required === !0, typeof e.config?.default == "string" && e.config.default !== "", typeof s == "string" && s !== "")
        }
      );
    case "terms": {
      const o = e.config?.multiple === !0, f = Array.isArray(s) ? s.filter((w) => typeof w == "number") : typeof s == "number" ? s : null;
      return /* @__PURE__ */ n.jsx(
        ms,
        {
          id: r,
          taxonomy: typeof e.taxonomy == "string" ? e.taxonomy : null,
          multiple: o,
          max: _(e, "max") ?? null,
          required: e.required === !0,
          invalid: m,
          value: o ? Array.isArray(f) ? f : [] : typeof f == "number" ? f : null,
          onChange: t
        }
      );
    }
    case "entries":
      return /* @__PURE__ */ n.jsx(
        be,
        {
          id: r,
          invalid: m,
          multiple: e.config?.multiple === !0,
          max: _(e, "max") ?? null,
          collections: e.collections ?? [],
          onChange: t,
          value: typeof s == "number" || Array.isArray(s) && s.every((o) => typeof o == "number") ? s : null
        }
      );
    case "link":
      return /* @__PURE__ */ n.jsx(Ss, { label: De(e), value: z(s), onChange: t, errors: c, id: r, collections: e.collections ?? [] });
    case "media":
      return /* @__PURE__ */ n.jsx(
        d.MediaPickerInput,
        {
          id: r,
          invalid: m,
          disabled: !1,
          multiple: e.config?.multiple === !0,
          max: _(e, "max") ?? null,
          types: Array.isArray(e.config?.types) ? e.config.types : [],
          value: typeof s == "number" || Array.isArray(s) && s.every((o) => typeof o == "number") ? s : null,
          onChange: t
        }
      );
    case "group":
      return /* @__PURE__ */ n.jsx(re, { fields: e.fields ?? [], value: z(s), onChange: t, errors: c, id: r, content: l });
    case "repeater":
      return /* @__PURE__ */ n.jsx(_s, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: c, id: r, content: l });
    case "flexible":
      return /* @__PURE__ */ n.jsx(Ts, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: c, id: r });
    default:
      return /* @__PURE__ */ n.jsx(
        d.Input,
        {
          id: r,
          "aria-invalid": m,
          value: typeof s == "string" ? s : "",
          placeholder: ge(e, "placeholder"),
          onChange: (o) => t(o.target.value)
        }
      );
  }
}
function _(e, s) {
  const t = e.config?.[s];
  return typeof t == "number" ? t : void 0;
}
function ge(e, s) {
  const t = e.config?.[s];
  return typeof t == "string" ? t : void 0;
}
function re({ fields: e, value: s, onChange: t, errors: c, id: r, content: l, bare: i = !1 }) {
  return /* @__PURE__ */ n.jsx("div", { className: i ? "cms:flex cms:flex-col cms:gap-6" : "cms:flex cms:flex-col cms:gap-4 cms:rounded-lg cms:border cms:p-4", children: e.filter((m) => ks(m, s)).map((m) => m.shared ? (
    // Greyed out; the form says once, at the top, where to edit shared fields.
    /* @__PURE__ */ n.jsx("fieldset", { disabled: !0, className: "cms:flex cms:flex-col cms:gap-1 cms:opacity-60", title: u("Shared by all languages: edit it in the main language."), children: /* @__PURE__ */ n.jsx(xe, { id: `${r}-${m.handle}`, field: m, value: s[m.handle], content: l, errors: {}, onChange: () => {
    } }) }, m.handle)
  ) : /* @__PURE__ */ n.jsx(
    xe,
    {
      id: `${r}-${m.handle}`,
      field: m,
      value: s[m.handle],
      content: l,
      errors: ne(c, m.handle),
      onChange: (o) => t({ ...s, [m.handle]: o })
    },
    m.handle
  )) });
}
function Cs({ options: e, value: s, onChange: t, id: c, empty: r, fill: l = !1 }) {
  return /* @__PURE__ */ n.jsxs(d.Select, { value: s ?? Y, onValueChange: (i) => t(i === Y ? null : i), children: [
    /* @__PURE__ */ n.jsx(d.SelectTrigger, { id: c, className: l ? "cms:w-full" : "cms:w-full cms:max-w-sm", children: /* @__PURE__ */ n.jsx(d.SelectValue, {}) }),
    /* @__PURE__ */ n.jsxs(d.SelectContent, { children: [
      r && /* @__PURE__ */ n.jsx(d.SelectItem, { value: Y, children: u("None") }),
      e.map((i) => /* @__PURE__ */ n.jsx(d.SelectItem, { value: i.value, children: i.label }, i.value))
    ] })
  ] });
}
function As({ options: e, value: s, onChange: t, empty: c, inTickOrder: r = !1, invalid: l = !1 }) {
  return e.length === 0 ? /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: c ?? u("Nothing to choose from yet.") }) : /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-col cms:gap-2", children: e.map((i) => /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
    /* @__PURE__ */ n.jsx(
      d.Checkbox,
      {
        checked: s.includes(i.value),
        "aria-invalid": l || void 0,
        onCheckedChange: (m) => t(m ? r ? [...s.filter((o) => o !== i.value), i.value] : e.map((o) => o.value).filter((o) => o === i.value || s.includes(o)) : s.filter((o) => o !== i.value))
      }
    ),
    i.label,
    r && s.includes(i.value) && /* @__PURE__ */ n.jsxs("span", { className: "cms:text-xs cms:text-muted-foreground", "aria-label": `number ${s.indexOf(i.value) + 1}`, children: [
      "#",
      s.indexOf(i.value) + 1
    ] })
  ] }, i.value)) });
}
function Ss({ label: e, value: s, onChange: t, errors: c, id: r, collections: l }) {
  const i = s.type === "entry" ? "entry" : "url", m = (o) => {
    const f = { ...s, type: i, ...o }, w = f.type === "entry" ? typeof f.entry_id != "number" : f.url === "" || f.url === void 0 || f.url === null;
    t(w ? f.label || f.new_tab ? f : null : f);
  };
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsx("div", { role: "radiogroup", "aria-label": u(":field: link to", { field: e }), className: "cms:inline-flex cms:w-fit cms:rounded-md cms:border cms:p-0.5", children: ["url", "entry"].map((o) => /* @__PURE__ */ n.jsx(
      d.Button,
      {
        type: "button",
        size: "sm",
        role: "radio",
        "aria-checked": i === o,
        variant: i === o ? "secondary" : "ghost",
        onClick: () => m({ type: o, url: o === "url" ? s.url : void 0, entry_id: o === "entry" ? s.entry_id : void 0 }),
        children: o === "url" ? u("Address") : u("Entry")
      },
      o
    )) }),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:grid cms:gap-2 cms:md:grid-cols-[2fr_1fr_auto] cms:md:items-start", children: [
      i === "url" ? /* @__PURE__ */ n.jsx(
        d.Input,
        {
          id: r,
          "aria-label": u(":field: address", { field: e }),
          placeholder: u("https://… or /about"),
          value: typeof s.url == "string" ? s.url : "",
          onChange: (o) => m({ url: o.target.value }),
          "aria-invalid": c.url !== void 0
        }
      ) : /* @__PURE__ */ n.jsx(
        be,
        {
          id: r,
          invalid: c.entry_id !== void 0,
          multiple: !1,
          max: null,
          collections: l,
          value: typeof s.entry_id == "number" ? s.entry_id : null,
          onChange: (o) => m({ entry_id: typeof o == "number" ? o : void 0 })
        }
      ),
      /* @__PURE__ */ n.jsx(
        d.Input,
        {
          "aria-label": u(":field: link text", { field: e }),
          placeholder: i === "entry" ? u("Link text (the entry's title)") : u("Link text"),
          value: typeof s.label == "string" ? s.label : "",
          onChange: (o) => m({ label: o.target.value })
        }
      ),
      /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:h-9 cms:items-center cms:gap-2 cms:text-sm", children: [
        /* @__PURE__ */ n.jsx(d.Checkbox, { checked: s.new_tab === !0, onCheckedChange: (o) => m({ new_tab: o }) }),
        u("New tab")
      ] }),
      /* @__PURE__ */ n.jsx("div", { className: "cms:md:col-span-3", children: /* @__PURE__ */ n.jsx(H, { message: c.url ?? c.entry_id ?? c.label ?? c.type }) })
    ] })
  ] });
}
function ye(e, s) {
  return !s && (e.fields ?? []).every((t) => t.type === "text");
}
function qe(e) {
  const s = j.useRef([]);
  return s.current = Xe(s.current, e), {
    keys: s.current,
    remove: (t) => {
      s.current = s.current.filter((c, r) => r !== t);
    },
    move: (t, c) => {
      s.current = d.moveItem(s.current, t, c);
    }
  };
}
function Oe() {
  const [e, s] = j.useState(() => /* @__PURE__ */ new Set());
  return {
    isCollapsed: (t, c) => !c && e.has(t),
    toggle: (t) => s((c) => {
      const r = new Set(c);
      return r.delete(t) || r.add(t), r;
    }),
    setAll: (t, c) => s(c ? new Set(t) : /* @__PURE__ */ new Set()),
    anyOpen: (t) => t.some((c) => !e.has(c))
  };
}
function ze({ keys: e, folding: s }) {
  if (e.length < 2) return null;
  const t = s.anyOpen(e);
  return /* @__PURE__ */ n.jsxs(d.Button, { type: "button", variant: "ghost", size: "sm", onClick: () => s.setAll(e, t), children: [
    t ? /* @__PURE__ */ n.jsx(ss, {}) : /* @__PURE__ */ n.jsx(Ce, {}),
    " ",
    t ? u("Collapse all") : u("Expand all")
  ] });
}
function _s({ field: e, value: s, onChange: t, errors: c, id: r, content: l = !1 }) {
  const i = e.fields ?? [], m = s.map(z), o = qe(m.length), f = (x, h) => t(m.map((N, A) => A === x ? h : N)), w = (x) => {
    o.remove(x), t(m.filter((h, N) => N !== x));
  }, k = e.label ? e.label.toLowerCase().replace(/s$/, "") : u("row"), p = k.charAt(0).toUpperCase() + k.slice(1), b = m.map((x, h) => ({ row: x, index: h, key: o.keys[h] ?? String(h) })), g = Oe();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(
      d.SortableList,
      {
        items: b,
        getId: (x) => x.key,
        getLabel: (x) => `${k} ${x.index + 1}`,
        onReorder: (x, h, N) => {
          o.move(h, N), t(x.map((A) => A.row));
        },
        children: ({ row: x, index: h, key: N }, { handle: A }) => ye(e, l) ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-start cms:gap-2", children: [
          /* @__PURE__ */ n.jsx(d.SortableHandle, { ...A, label: u("Move :item :number", { item: k, number: h + 1 }), className: "cms:mt-1" }),
          i.map((v) => /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-1 cms:flex-col cms:gap-1", children: [
            /* @__PURE__ */ n.jsx(
              d.Input,
              {
                "aria-label": v.label || v.handle,
                placeholder: v.label || v.handle,
                value: typeof x[v.handle] == "string" ? x[v.handle] : "",
                onChange: (D) => f(h, { ...x, [v.handle]: D.target.value })
              }
            ),
            /* @__PURE__ */ n.jsx(H, { message: c[`${h}.${v.handle}`] })
          ] }, v.handle)),
          /* @__PURE__ */ n.jsx(d.Button, { type: "button", variant: "ghost", size: "icon", "aria-label": u("Remove :item :number", { item: k, number: h + 1 }), onClick: () => w(h), children: /* @__PURE__ */ n.jsx(ke, {}) })
        ] }) : (() => {
          const v = ne(c, String(h)), D = g.isCollapsed(N, Object.keys(v).length > 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              Ee,
              {
                label: `${p} ${h + 1}`,
                summary: ve(i, x),
                handle: A,
                onRemove: () => w(h),
                collapsed: D,
                onToggle: () => g.toggle(N),
                controls: `${r}-${h}-fields`,
                hidden: l ? x._hidden === !0 : void 0,
                onHidden: (E) => f(h, { ...x, _hidden: E })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${h}-fields`, hidden: D, children: !D && /* @__PURE__ */ n.jsx(re, { fields: i, value: x, onChange: (E) => f(h, E), errors: v, id: `${r}-${h}`, content: l }) })
          ] });
        })()
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(d.Button, { type: "button", variant: "outline", size: "sm", onClick: () => t([...m, l ? Ge() : {}]), children: [
        /* @__PURE__ */ n.jsx(je, {}),
        " ",
        u("Add :item", { item: k })
      ] }),
      !ye(e, l) && /* @__PURE__ */ n.jsx(ze, { keys: b.map((x) => x.key), folding: g })
    ] })
  ] });
}
function Ts({ field: e, value: s, onChange: t, errors: c, id: r }) {
  const l = e.blocks ?? [], i = s.map(z), m = qe(i.length), o = (p, b) => t(i.map((g, x) => x === p ? b : g)), f = (p) => {
    m.remove(p), t(i.filter((b, g) => g !== p));
  }, w = i.map((p, b) => ({ block: p, index: b, key: m.keys[b] ?? String(b) })), k = Oe();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-3", children: [
    /* @__PURE__ */ n.jsx(
      d.SortableList,
      {
        items: w,
        getId: (p) => p.key,
        className: "cms:gap-3",
        getLabel: (p) => u(":type block", { type: l.find((b) => b.type === p.block.type)?.label ?? String(p.block.type) }),
        onReorder: (p, b, g) => {
          m.move(b, g), t(p.map((x) => x.block));
        },
        children: ({ block: p, index: b, key: g }, { handle: x }) => {
          const h = l.find((v) => v.type === p.type), N = ne(c, `${b}.values`), A = k.isCollapsed(g, Object.keys(N).length > 0 || c[`${b}.type`] !== void 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              Ee,
              {
                label: h?.label ?? String(p.type),
                summary: h ? ve(h.fields, z(p.values)) : null,
                badge: !0,
                handle: x,
                onRemove: () => f(b),
                collapsed: A,
                onToggle: () => k.toggle(g),
                controls: `${r}-${b}-fields`,
                hidden: p.hidden === !0,
                onHidden: (v) => o(b, { ...p, hidden: v })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${b}-fields`, hidden: A, children: A ? null : h ? /* @__PURE__ */ n.jsx(
              re,
              {
                fields: h.fields,
                value: z(p.values),
                onChange: (v) => o(b, { ...p, values: v }),
                errors: N,
                id: `${r}-${b}`,
                content: !0
              }
            ) : /* @__PURE__ */ n.jsx("p", { className: "cms:rounded-lg cms:border cms:p-4 cms:text-sm cms:text-muted-foreground", children: u("This kind of block no longer exists. Its values are kept.") }) }),
            /* @__PURE__ */ n.jsx(H, { message: c[`${b}.type`] })
          ] });
        }
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(d.DropdownMenu, { children: [
        /* @__PURE__ */ n.jsx(d.DropdownMenuTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsxs(d.Button, { type: "button", variant: "outline", size: "sm", disabled: l.length === 0, children: [
          /* @__PURE__ */ n.jsx(je, {}),
          " ",
          u("Add block")
        ] }) }),
        /* @__PURE__ */ n.jsx(d.DropdownMenuContent, { align: "start", children: l.map((p) => /* @__PURE__ */ n.jsx(d.DropdownMenuItem, { onSelect: () => t([...i, We(p.type)]), children: p.label }, p.type)) })
      ] }),
      /* @__PURE__ */ n.jsx(ze, { keys: w.map((p) => p.key), folding: k })
    ] })
  ] });
}
function Ee({ label: e, summary: s, badge: t = !1, handle: c, onRemove: r, collapsed: l, onToggle: i, controls: m, hidden: o, onHidden: f }) {
  const w = l ? Ye : es, k = s ? `${e}: ${s}` : e;
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(d.SortableHandle, { ...c, label: u("Move :title", { title: k }) }),
    /* @__PURE__ */ n.jsxs(
      "button",
      {
        type: "button",
        onClick: i,
        "aria-expanded": !l,
        "aria-controls": m,
        className: "cms:flex cms:min-w-0 cms:flex-1 cms:items-center cms:gap-2 cms:rounded-md cms:text-left cms:outline-none cms:focus-visible:ring-2 cms:focus-visible:ring-ring",
        children: [
          /* @__PURE__ */ n.jsx(w, { className: "cms:size-4 cms:shrink-0 cms:text-muted-foreground", "aria-hidden": !0 }),
          t ? /* @__PURE__ */ n.jsx(d.Badge, { variant: "secondary", children: e }) : /* @__PURE__ */ n.jsx("span", { className: "cms:shrink-0 cms:text-sm cms:font-medium", children: e }),
          s && /* @__PURE__ */ n.jsx("span", { className: "cms:truncate cms:text-sm cms:text-muted-foreground", children: s }),
          /* @__PURE__ */ n.jsx("span", { className: "cms:sr-only", children: l ? u("(collapsed)") : "" })
        ]
      }
    ),
    o !== void 0 && /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:shrink-0 cms:items-center cms:gap-2 cms:text-sm cms:text-muted-foreground", title: u("Off: kept here, but left out of the page"), children: [
      /* @__PURE__ */ n.jsx(d.Switch, { checked: !o, onCheckedChange: (p) => f(!p), "aria-label": u(":title: visible on site", { title: k }) }),
      u("Visible on site")
    ] }),
    /* @__PURE__ */ n.jsx(
      d.Button,
      {
        type: "button",
        variant: "ghost",
        size: "icon",
        className: "cms:size-8 cms:shrink-0 cms:text-destructive",
        "aria-label": u("Remove :title", { title: k }),
        onClick: r,
        children: /* @__PURE__ */ n.jsx(ke, {})
      }
    )
  ] });
}
export {
  Ce as A,
  De as B,
  es as C,
  xe as F,
  Is as M,
  et as O,
  ms as T,
  Xs as a,
  ne as b,
  fs as c,
  Js as d,
  gs as e,
  Vs as f,
  Zs as g,
  Ws as h,
  Qs as i,
  Gs as j,
  vs as k,
  js as l,
  tt as m,
  Ps as n,
  nt as o,
  st as p,
  ps as q,
  Us as r,
  Ys as s,
  ds as t,
  hs as u,
  xs as v,
  is as w,
  re as x,
  Ks as y,
  ks as z
};
