import { j as n, t as p, a as Ne } from "./admin-DGaQfNFD.js";
import { c as H, _ as S } from "./createLucideIcon-Ccbp7F45.js";
import { u as m } from "./ui-Dh5wfMwT.js";
import { t as _e, a as T, E as te, o as $e, d as K, b as Se, g as Ae, i as ne, n as Ce, c as Te, s as Ee } from "./EntriesPicker-DPkif6qZ.js";
import { F as z } from "./FieldError-CNbEtKtu.js";
import { X as Ie } from "./EntryStatus-DQwSQKUx.js";
import { S as Fe } from "./search-3zKi7F1_.js";
import { P as re } from "./plus-COqahUZ2.js";
import { T as ce } from "./trash-DHN6ewjn.js";
import { C as Oe } from "./chevron-right-9qVV6b-Q.js";
const ae = {
  name: "chevron-down",
  size: 24,
  node: [["path", { d: "m6 9 6 6 6-6", key: "qrunsl" }]]
};
ae.node;
const He = H(ae);
const le = {
  name: "chevrons-down-up",
  size: 24,
  node: [
    ["path", { d: "m7 20 5-5 5 5", key: "13a0gw" }],
    ["path", { d: "m7 4 5 5 5-5", key: "1kwcof" }]
  ]
};
le.node;
const ze = H(le);
const oe = {
  name: "chevrons-up-down",
  size: 24,
  node: [
    ["path", { d: "m7 15 5 5 5-5", key: "1hf1tw" }],
    ["path", { d: "m7 9 5-5 5 5", key: "sgt6xg" }]
  ]
};
oe.node;
const De = H(oe);
const ie = {
  name: "info",
  size: 24,
  node: [
    ["circle", { cx: "12", cy: "12", r: "10", key: "1mglay" }],
    ["path", { d: "M12 16v-4", key: "1dtifu" }],
    ["path", { d: "M12 8h.01", key: "e9boi3" }]
  ]
};
ie.node;
const Re = H(ie), Me = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
function E(e) {
  return e.replace(/[&<>"']/g, (s) => Me[s] ?? s);
}
function qe(e) {
  return /[\u0000- \u007f]/.test(e) ? !1 : /^(https?:\/\/[^/]|mailto:|tel:|\/(?![/\\])|#)/i.test(e);
}
const U = new RegExp("`([^`\\n]+)`|\\[([^\\]\\n]+)\\]\\(([^()\\s]+)\\)|\\*\\*(?=\\S)(.+?)(?<=\\S)\\*\\*|\\*(?=[^\\s*])([^*\\n]+?)(?<=\\S)\\*");
function I(e, s = !1) {
  let t = "", c = e;
  for (let r = U.exec(c); r !== null; r = U.exec(c)) {
    t += E(c.slice(0, r.index));
    const [l, a, f, o, h, d] = r;
    a !== void 0 ? t += `<code>${E(a)}</code>` : f !== void 0 && o !== void 0 ? t += s || !qe(o) ? I(f, s) : `<a href="${E(o)}" target="_blank" rel="noopener noreferrer">${I(f, !0)}</a>` : h !== void 0 ? t += `<strong>${I(h, s)}</strong>` : d !== void 0 ? t += `<em>${I(d, s)}</em>` : t += E(l), c = c.slice(r.index + l.length);
  }
  return t + E(c);
}
const Pe = ["text", "number"], Be = ["group", "repeater", "flexible"], Le = ["placeholder", "prefix", "suffix"];
function O(e) {
  return typeof e == "string" && e.trim() !== "" ? e.trim() : null;
}
function me(e) {
  return { hint: O(e.hint), tooltip: O(e.hint_tooltip) };
}
function ue(e) {
  return Pe.includes(e.type) ? { prefix: O(e.config?.prefix), suffix: O(e.config?.suffix) } : { prefix: null, suffix: null };
}
function W({ field: e, id: s, label: t, maxWidth: c = "cms:max-w-[60%]" }) {
  const { hint: r, tooltip: l } = me(e);
  return r === null && l === null ? null : /* @__PURE__ */ n.jsxs("span", { className: `cms:ml-auto cms:flex ${c} cms:min-w-0 cms:shrink-0 cms:items-center cms:gap-1 cms:text-xs cms:leading-none cms:text-muted-foreground`, children: [
    r !== null && /* @__PURE__ */ n.jsx("span", { id: `${s}-hint`, className: "cms:min-w-0 cms:truncate cms:py-0.5", title: r, children: r }),
    l !== null && /* @__PURE__ */ n.jsxs(m.Tooltip, { children: [
      /* @__PURE__ */ n.jsx(m.TooltipTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsx(
        "button",
        {
          type: "button",
          "aria-label": p("More about :field", { field: t }),
          className: "cms:-m-1 cms:inline-flex cms:shrink-0 cms:rounded-full cms:p-1 cms:text-muted-foreground cms:outline-none cms:hover:text-foreground cms:focus-visible:ring-2 cms:focus-visible:ring-ring",
          children: /* @__PURE__ */ n.jsx(Re, { className: "cms:size-3.5", "aria-hidden": !0 })
        }
      ) }),
      /* @__PURE__ */ n.jsx(m.TooltipContent, { className: "cms:max-w-xs cms:text-left cms:whitespace-pre-line", children: l })
    ] })
  ] });
}
function Ve({ text: e, id: s, className: t = "" }) {
  return !e || e.trim() === "" ? null : /* @__PURE__ */ n.jsx(
    "p",
    {
      id: s,
      className: `cms:text-sm cms:text-muted-foreground cms:[overflow-wrap:anywhere] cms:[&_a]:font-medium cms:[&_a]:text-foreground cms:[&_a]:underline cms:[&_a]:underline-offset-2 cms:[&_a:hover]:text-primary cms:[&_code]:rounded cms:[&_code]:bg-muted cms:[&_code]:px-1 cms:[&_code]:font-mono cms:[&_code]:text-[0.8125rem] cms:[&_strong]:font-semibold cms:[&_strong]:text-foreground ${t}`.trim(),
      dangerouslySetInnerHTML: { __html: I(e) }
    }
  );
}
function G({ prefix: e, suffix: s, id: t, className: c = "", children: r }) {
  if (e === null && s === null) return /* @__PURE__ */ n.jsx(n.Fragment, { children: r });
  const l = "cms:flex cms:shrink-0 cms:items-center cms:bg-muted/60 cms:px-3 cms:text-sm cms:whitespace-nowrap cms:text-muted-foreground cms:select-none";
  return /* @__PURE__ */ n.jsxs("div", { "data-slot": "input-group", className: `cms:flex cms:h-10 cms:w-full cms:min-w-0 cms:items-stretch cms:overflow-hidden cms:rounded-md cms:border cms:border-input/80 cms:bg-card cms:shadow-xs cms:transition-[border-color,box-shadow] cms:duration-150 cms:hover:border-ring/45 cms:focus-within:border-ring/70 cms:focus-within:ring-4 cms:focus-within:ring-ring-glow cms:has-[[aria-invalid=true]]:border-destructive cms:has-[[aria-invalid=true]]:ring-destructive/20 cms:has-disabled:cursor-not-allowed cms:has-disabled:opacity-50 cms:has-[[readonly]]:bg-muted/30 cms:dark:bg-input/20 ${c}`.trim(), children: [
    e !== null && /* @__PURE__ */ n.jsx("span", { id: `${t}-prefix`, className: `${l} cms:border-r cms:border-input/80`, children: e }),
    r,
    s !== null && /* @__PURE__ */ n.jsx("span", { id: `${t}-suffix`, className: `${l} cms:border-l cms:border-input/80`, children: s })
  ] });
}
const X = "cms:h-full cms:min-w-0 cms:flex-1 cms:rounded-none cms:border-0 cms:bg-transparent cms:shadow-none cms:hover:border-0 cms:focus-visible:ring-0 cms:aria-invalid:ring-0 cms:disabled:opacity-100 cms:dark:bg-transparent", Ke = S.createContext({}), R = "__none";
async function Y(e) {
  const s = await fetch(`${Ne("cms/terms/picker")}?${_e(e)}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
  if (!s.ok) throw new Error(String(s.status));
  return await s.json();
}
function Ue({ id: e, taxonomy: s, multiple: t, max: c, required: r, invalid: l, value: a, onChange: f }) {
  const o = S.useContext(Ke), h = a === null ? [] : Array.isArray(a) ? a : [a], [d, y] = S.useState(null), [i, x] = S.useState(!1), [v, b] = S.useState(""), [g, k] = S.useState({}), [N, j] = S.useState(!1);
  S.useEffect(() => {
    if (!s) return;
    const u = v.trim(), _ = window.setTimeout(() => {
      Y({ taxonomy: s, q: u }).then((w) => {
        y(w.items), w.more && x(!0), j(!1);
      }).catch(() => j(!0));
    }, u === "" ? 0 : 250);
    return () => window.clearTimeout(_);
  }, [s, v]);
  const $ = (u) => o[String(u)] ?? g[u] ?? d?.find((_) => _.id === u)?.label, C = d === null ? "" : h.filter((u) => $(u) === void 0).join(",");
  if (S.useEffect(() => {
    !s || C === "" || Y({ taxonomy: s, ids: C.split(",").map(Number) }).then((u) => k((_) => ({ ..._, ...Object.fromEntries(u.items.map((w) => [w.id, w.label])) }))).catch(() => j(!0));
  }, [s, C]), !s) return /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: p("Choose a taxonomy for this field in its blueprint.") });
  if (d === null) return N ? /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-destructive", children: p("Terms could not be loaded. Try again.") }) : /* @__PURE__ */ n.jsx(m.Skeleton, { className: "cms:h-9 cms:w-full cms:max-w-sm" });
  const D = (u) => `${"— ".repeat(u.depth)}${u.label}`, je = t && c !== null && h.length >= c, V = (u) => f(t ? u : u[0] ?? null), ve = i && h.length > 0 && /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-wrap cms:gap-1.5", children: h.map((u) => /* @__PURE__ */ n.jsxs(m.Badge, { variant: "secondary", className: "cms:gap-1 cms:pr-1", children: [
    $(u) ?? p("Term #:id", { id: u }),
    !(r && !t) && /* @__PURE__ */ n.jsx(
      "button",
      {
        type: "button",
        className: "cms:rounded-sm cms:p-0.5 cms:hover:bg-muted",
        "aria-label": p("Remove :title", { title: $(u) ?? u }),
        onClick: () => V(h.filter((_) => _ !== u)),
        children: /* @__PURE__ */ n.jsx(Ie, { className: "cms:size-3" })
      }
    )
  ] }, u)) }), we = i && /* @__PURE__ */ n.jsxs("div", { className: "cms:relative cms:max-w-sm", children: [
    /* @__PURE__ */ n.jsx(Fe, { className: "cms:absolute cms:top-1/2 cms:left-3 cms:size-4 cms:-translate-y-1/2 cms:text-muted-foreground" }),
    /* @__PURE__ */ n.jsx(
      m.Input,
      {
        id: e,
        className: "cms:pl-9",
        placeholder: p("Search terms"),
        autoComplete: "off",
        "aria-invalid": l,
        value: v,
        onChange: (u) => b(u.target.value)
      }
    )
  ] });
  if (!t && !i) {
    const u = h[0], _ = u === void 0 || d.some((w) => w.id === u);
    return /* @__PURE__ */ n.jsxs(m.Select, { value: u === void 0 ? R : String(u), onValueChange: (w) => f(w === R ? null : Number(w)), children: [
      /* @__PURE__ */ n.jsx(m.SelectTrigger, { id: e, className: "cms:w-full cms:max-w-sm", "aria-invalid": l, children: /* @__PURE__ */ n.jsx(m.SelectValue, { children: u === void 0 ? p("None") : $(u) ?? p("Term #:id", { id: u }) }) }),
      /* @__PURE__ */ n.jsxs(m.SelectContent, { children: [
        !r && /* @__PURE__ */ n.jsx(m.SelectItem, { value: R, children: p("None") }),
        !_ && u !== void 0 && /* @__PURE__ */ n.jsx(m.SelectItem, { value: String(u), children: $(u) ?? p("Term #:id", { id: u }) }),
        d.map((w) => /* @__PURE__ */ n.jsx(m.SelectItem, { value: String(w.id), children: D(w) }, w.id))
      ] })
    ] });
  }
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    ve,
    we,
    d.length === 0 ? /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: v.trim() ? p("No terms match.") : p("Nothing to choose from yet.") }) : t ? /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:max-h-72 cms:flex-col cms:gap-2 cms:overflow-auto", children: d.map((u) => {
      const _ = h.includes(u.id);
      return /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
        /* @__PURE__ */ n.jsx(
          m.Checkbox,
          {
            checked: _,
            disabled: !_ && je,
            onCheckedChange: (w) => V(w ? [...h, u.id] : h.filter((ke) => ke !== u.id))
          }
        ),
        D(u)
      ] }, u.id);
    }) }) : /* @__PURE__ */ n.jsx("div", { role: "listbox", "aria-label": p("Terms"), className: "cms:flex cms:max-h-72 cms:flex-col cms:overflow-auto", children: d.map((u) => /* @__PURE__ */ n.jsx(
      m.Button,
      {
        type: "button",
        variant: h[0] === u.id ? "secondary" : "ghost",
        size: "sm",
        className: "cms:justify-start",
        role: "option",
        "aria-selected": h[0] === u.id,
        onClick: () => f(u.id),
        children: D(u)
      },
      u.id
    )) })
  ] });
}
const de = (e, s = {}) => e.replace(/:([A-Za-z_]+)/g, (t, c) => s[c] ?? t), Ss = 5;
let We = 0;
const P = () => `f${++We}`;
function Ge(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").replace(/^[0-9_]+/, "").slice(0, 64);
}
function Xe(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^[0-9_]+/, "").replace(/_{2,}/g, "_").slice(0, 64);
}
function fe(e, s) {
  const t = new Set(s.map((l) => l.handle)), c = Ge(e) || "field";
  let r = c;
  for (let l = 2; t.has(r); l++)
    r = `${c}_${l}`;
  return r;
}
function Ye(e) {
  return (e ?? []).map(({ fields: s, ...t }) => ({
    ...t,
    key: P(),
    fields: Ye(s),
    handleTouched: !0,
    savedHandle: t.handle
  }));
}
function Je(e, s) {
  return e.map(({ key: t, handleTouched: c, savedHandle: r, fields: l, ...a }) => ({
    ...a,
    validation: (a.validation ?? []).map((f) => f.trim()).filter((f) => f !== ""),
    ...s(a.type) ? { fields: Je(l, s) } : {}
  }));
}
function As(e, s, t) {
  return { key: P(), type: e, label: s, handle: fe(s, t), required: !1, config: {}, fields: [], handleTouched: !1 };
}
function Cs(e) {
  return e.type !== "fieldset" && e.savedHandle !== void 0 && e.savedHandle !== "" && e.handle !== e.savedHandle ? e.savedHandle : null;
}
function Qe(e) {
  return e.map((s) => ({ ...s, savedHandle: s.handle, fields: Qe(s.fields) }));
}
function Ts(e, s, t) {
  if (e.handleTouched) return { ...e, label: s };
  const c = t.filter((r) => r.key !== e.key);
  return { ...e, label: s, handle: fe(s || e.type, c) };
}
function Ze(e, s) {
  const t = `${s}.`, c = {};
  for (const [r, l] of Object.entries(e))
    r.startsWith(t) && (c[r.slice(t.length)] = l);
  return c;
}
function B(e, s) {
  const t = e[s];
  return { ...Ze(e, s), ...t !== void 0 ? { "": t } : {} };
}
function Es(e, s) {
  return Object.keys(e).filter((t) => t === s || t.startsWith(`${s}.`));
}
function Is(e, s) {
  return Object.keys(e).some((t) => t === s || t.startsWith(`${s}.`));
}
function Fs(e, s) {
  return s.filter((t) => t.key !== e.key && t.handle !== "" && t.type !== "fieldset");
}
function Os(e, s, t, c = de) {
  return e.type === "fieldset" ? null : t?.includes(e.handle) ? c('":handle" is used by the content itself; choose another handle.', { handle: e.handle }) : e.handle !== "" && s.some((r) => r.key !== e.key && r.type !== "fieldset" && r.handle === e.handle) ? c('There is already a field ":handle" here.', { handle: e.handle }) : null;
}
function Hs(e, s) {
  return typeof e.translatable == "boolean" ? e.translatable : s;
}
function zs(e, s, t) {
  return { ...e, translatable: s === t ? null : s };
}
function es(e, s, t = []) {
  const c = /* @__PURE__ */ new Set([...s.map((a) => a.handle), ...t]), r = `${(Xe(e) || "field").slice(0, 56)}_copy`;
  let l = r;
  for (let a = 2; c.has(l); a++)
    l = `${r}_${a}`;
  return l;
}
function pe(e) {
  const { key: s, savedHandle: t, fields: c, ...r } = e;
  return { ...structuredClone(r), key: P(), fields: c.map(pe) };
}
function Ds(e, s, t = [], c = de) {
  const r = e[s];
  if (r === void 0) return null;
  const l = r.label || r.handle, a = {
    ...pe(r),
    label: c(":label (copy)", { label: l }),
    handle: r.type === "fieldset" ? r.handle : es(r.handle, e, t),
    handleTouched: !0
  };
  return { fields: [...e.slice(0, s + 1), a, ...e.slice(s + 1)], copy: a };
}
function ss(e, s) {
  const t = e.condition;
  return !t || "rules" in t ? null : (t.operator === "filled" || t.operator === "equals" && (t.value === !0 || t.value === "true")) && s.some((r) => r.handle === t.field && r.type === "toggle") ? t.field : null;
}
function Rs(e) {
  const s = (a) => a.type === "number" || a.type === "text" && (a.instructions ?? "").length <= 60, t = (a) => a.type === "text" && Le.includes(a.handle), c = {}, r = [];
  for (const a of e) {
    const f = ss(a, e);
    f === null ? r.push(a) : (c[f] ??= []).push(a);
  }
  const l = r.filter((a) => !t(a));
  return { options: l.filter((a) => !s(a)), limits: l.filter(s), appearance: r.filter(t), dependents: c };
}
const Ms = [
  { value: "equals", label: "is", needsValue: !0 },
  { value: "not_equals", label: "is not", needsValue: !0 },
  { value: "filled", label: "is filled in", needsValue: !1 },
  { value: "empty", label: "is empty", needsValue: !1 }
];
function J(e) {
  return e === !0 ? "true" : e === !1 ? "false" : typeof e == "string" || typeof e == "number" ? String(e) : "";
}
function ts(e) {
  return e ? "rules" in e ? Array.isArray(e.rules) ? e.rules : [] : [e] : [];
}
function ns(e) {
  return e && "rules" in e && e.match === "any" ? "any" : "all";
}
function qs(e, s) {
  return e.length === 0 ? null : e.length === 1 ? e[0] : { match: s, rules: e };
}
function Q(e, s) {
  const t = s[e.field], c = t != null && t !== "" && t !== !1 && !(Array.isArray(t) && t.length === 0), r = (Array.isArray(t) ? t : [t]).map(J).includes(J(e.value));
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
function rs(e, s) {
  const t = ts(e.condition);
  return t.length === 0 ? !0 : ns(e.condition) === "any" ? t.some((c) => Q(c, s)) : t.every((c) => Q(c, s));
}
function Ps(e) {
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
function Bs(e) {
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
const cs = [25, 33, 50, 66, 75, 100];
function as(e) {
  return cs.find((s) => s === e.width) ?? 100;
}
function ls(e = "cms:gap-x-4 cms:gap-y-6") {
  return `cms:@container cms:grid cms:grid-cols-12 cms:items-start ${e}`;
}
const os = {
  25: "cms:@lg:col-span-3",
  33: "cms:@lg:col-span-4",
  50: "cms:@lg:col-span-6",
  66: "cms:@lg:col-span-8",
  75: "cms:@lg:col-span-9",
  100: ""
};
function Z(e) {
  return `cms:col-span-12 cms:min-w-0 ${os[as(e)]}`.trim();
}
function Ls(e, s) {
  const { width: t, ...c } = e;
  return s === 100 ? c : { ...c, width: s };
}
const M = "__none";
function q(e) {
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
const is = ["repeater", "group", "flexible", "link", "media", "rich_text", "entries"];
function he(e) {
  if (e.label) return e.label;
  const s = e.handle.replace(/_/g, " ");
  return s.charAt(0).toUpperCase() + s.slice(1);
}
function ee({ field: e, value: s, onChange: t, errors: c, id: r, content: l = !1, fill: a = !1, marker: f = null }) {
  const o = he(e), h = is.includes(e.type) || ["select", "terms"].includes(e.type) && e.config?.multiple === !0, d = c[""] !== void 0, y = Be.includes(e.type), i = e.instructions && e.instructions.trim() !== "" ? /* @__PURE__ */ n.jsx(Ve, { id: `${r}-help`, text: e.instructions }) : null, { hint: x } = me(e), { prefix: v, suffix: b } = ue(e), g = [x && `${r}-hint`, v && `${r}-prefix`, b && `${r}-suffix`, i && `${r}-help`, d && `${r}-error`].filter(Boolean).join(" ") || void 0;
  return /* @__PURE__ */ n.jsxs(
    "div",
    {
      className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-2",
      ...h ? { role: "group", "aria-labelledby": `${r}-label`, ...d ? { "aria-invalid": !0 } : {}, ...d || i ? { "aria-describedby": [i && `${r}-help`, d && `${r}-error`].filter(Boolean).join(" ") } : {} } : {},
      children: [
        e.type === "toggle" ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-3", children: [
          /* @__PURE__ */ n.jsx(m.Switch, { id: r, checked: s === !0, "aria-describedby": g, onCheckedChange: (k) => t(k) }),
          /* @__PURE__ */ n.jsx(m.Label, { htmlFor: r, className: "cms:min-w-0", children: o }),
          f,
          /* @__PURE__ */ n.jsx(W, { field: e, id: r, label: o })
        ] }) : /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:min-h-4 cms:min-w-0 cms:items-center cms:gap-2", children: [
          /* @__PURE__ */ n.jsxs(m.Label, { id: `${r}-label`, htmlFor: h ? void 0 : r, className: "cms:min-w-0", children: [
            o,
            e.required && /* @__PURE__ */ n.jsx("span", { className: "cms:text-destructive", children: " *" }),
            f
          ] }),
          /* @__PURE__ */ n.jsx(W, { field: e, id: r, label: o })
        ] }),
        y && i,
        /* @__PURE__ */ n.jsx(us, { field: e, value: s, onChange: t, errors: c, id: r, content: l, fill: a, describedBy: g }),
        ["text", "textarea"].includes(e.type) && A(e, "max") !== void 0 && /* @__PURE__ */ n.jsx(ms, { length: typeof s == "string" ? [...s].length : 0, max: A(e, "max") ?? 0 }),
        !y && i,
        /* @__PURE__ */ n.jsx(z, { message: c[""], id: `${r}-error` })
      ]
    }
  );
}
function ms({ length: e, max: s }) {
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
function us({ field: e, value: s, onChange: t, errors: c, id: r, content: l, fill: a = !1, describedBy: f }) {
  const o = c[""] !== void 0, { prefix: h, suffix: d } = ue(e), y = h !== null || d !== null;
  switch (e.type) {
    case "toggle":
      return null;
    case "file":
      return /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: p("Visitors attach a file here when they send a form.") });
    case "textarea":
      return /* @__PURE__ */ n.jsx(
        m.Textarea,
        {
          id: r,
          "aria-invalid": o,
          "aria-describedby": f,
          value: typeof s == "string" ? s : "",
          rows: A(e, "rows") ?? 4,
          placeholder: F(e, "placeholder"),
          onChange: (i) => t(i.target.value)
        }
      );
    case "rich_text":
      return /* @__PURE__ */ n.jsx(m.RichTextEditor, { id: r, labelledBy: `${r}-label`, invalid: o, value: typeof s == "string" ? s : "", onChange: t });
    case "number": {
      const i = Ae(s);
      return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ n.jsx(G, { prefix: h, suffix: d, id: r, className: a ? "" : "cms:max-w-64", children: /* @__PURE__ */ n.jsx(
          m.Input,
          {
            id: r,
            type: "number",
            className: y ? X : a ? "cms:w-full" : "cms:max-w-48",
            placeholder: F(e, "placeholder"),
            "aria-invalid": o,
            "aria-describedby": [f, i && `${r}-grouped`].filter(Boolean).join(" ") || void 0,
            value: typeof s == "number" ? String(s) : typeof s == "string" ? s : "",
            min: A(e, "min"),
            max: A(e, "max"),
            step: A(e, "step") ?? "any",
            onChange: (x) => t(x.target.value === "" ? null : Number(x.target.value))
          }
        ) }),
        i && /* @__PURE__ */ n.jsx("span", { id: `${r}-grouped`, className: "cms:text-sm cms:tabular-nums cms:text-muted-foreground", children: i })
      ] });
    }
    case "date": {
      const i = e.config?.time === !0;
      return /* @__PURE__ */ n.jsx(
        m.DatePicker,
        {
          id: r,
          withTime: i,
          invalid: o,
          required: e.required === !0,
          value: K(Se(s, i)),
          onChange: (x) => t(K(x ?? ""))
        }
      );
    }
    case "select":
      return e.config?.multiple === !0 ? /* @__PURE__ */ n.jsx(
        fs,
        {
          options: q(e),
          value: Array.isArray(s) ? s.map(String) : [],
          onChange: t,
          invalid: o,
          empty: typeof e.config?.empty == "string" ? e.config.empty : void 0,
          inTickOrder: e.config?.in_tick_order === !0
        }
      ) : /* @__PURE__ */ n.jsx(
        ds,
        {
          options: q(e),
          value: typeof s == "string" ? s : null,
          onChange: t,
          id: r,
          fill: a,
          empty: $e(q(e), e.required === !0, typeof e.config?.default == "string" && e.config.default !== "", typeof s == "string" && s !== "")
        }
      );
    case "terms": {
      const i = e.config?.multiple === !0, x = Array.isArray(s) ? s.filter((v) => typeof v == "number") : typeof s == "number" ? s : null;
      return /* @__PURE__ */ n.jsx(
        Ue,
        {
          id: r,
          taxonomy: typeof e.taxonomy == "string" ? e.taxonomy : null,
          multiple: i,
          max: A(e, "max") ?? null,
          required: e.required === !0,
          invalid: o,
          value: i ? Array.isArray(x) ? x : [] : typeof x == "number" ? x : null,
          onChange: t
        }
      );
    }
    case "entries":
      return /* @__PURE__ */ n.jsx(
        te,
        {
          id: r,
          invalid: o,
          multiple: e.config?.multiple === !0,
          max: A(e, "max") ?? null,
          collections: e.collections ?? [],
          onChange: t,
          value: typeof s == "number" || Array.isArray(s) && s.every((i) => typeof i == "number") ? s : null
        }
      );
    case "link":
      return /* @__PURE__ */ n.jsx(
        ps,
        {
          label: he(e),
          value: T(s),
          onChange: t,
          errors: c,
          id: r,
          collections: e.collections ?? [],
          placeholder: F(e, "placeholder"),
          describedBy: f
        }
      );
    case "media":
      return /* @__PURE__ */ n.jsx(
        m.MediaPickerInput,
        {
          id: r,
          invalid: o,
          disabled: !1,
          multiple: e.config?.multiple === !0,
          max: A(e, "max") ?? null,
          types: Array.isArray(e.config?.types) ? e.config.types : [],
          value: typeof s == "number" || Array.isArray(s) && s.every((i) => typeof i == "number") ? s : null,
          onChange: t
        }
      );
    case "group":
      return /* @__PURE__ */ n.jsx(L, { fields: e.fields ?? [], value: T(s), onChange: t, errors: c, id: r, content: l });
    case "repeater":
      return /* @__PURE__ */ n.jsx(hs, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: c, id: r, content: l });
    case "flexible":
      return /* @__PURE__ */ n.jsx(xs, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: c, id: r });
    default:
      return /* @__PURE__ */ n.jsx(G, { prefix: h, suffix: d, id: r, children: /* @__PURE__ */ n.jsx(
        m.Input,
        {
          id: r,
          "aria-invalid": o,
          "aria-describedby": f,
          className: y ? X : void 0,
          value: typeof s == "string" ? s : "",
          placeholder: F(e, "placeholder"),
          onChange: (i) => t(i.target.value)
        }
      ) });
  }
}
function A(e, s) {
  const t = e.config?.[s];
  return typeof t == "number" ? t : void 0;
}
function F(e, s) {
  const t = e.config?.[s];
  return typeof t == "string" ? t : void 0;
}
function L({ fields: e, value: s, onChange: t, errors: c, id: r, content: l, bare: a = !1 }) {
  const f = /* @__PURE__ */ n.jsx("div", { className: ls(a ? void 0 : "cms:gap-4"), children: e.filter((o) => rs(o, s)).map((o) => o.shared ? (
    // Greyed out; the form says once, at the top, where to edit shared fields.
    /* @__PURE__ */ n.jsx("fieldset", { disabled: !0, className: `cms:flex cms:flex-col cms:gap-1 cms:opacity-60 ${Z(o)}`, title: p("Shared by all languages: edit it in the main language."), children: /* @__PURE__ */ n.jsx(ee, { id: `${r}-${o.handle}`, field: o, value: s[o.handle], content: l, errors: {}, onChange: () => {
    } }) }, o.handle)
  ) : /* @__PURE__ */ n.jsx("div", { className: Z(o), children: /* @__PURE__ */ n.jsx(
    ee,
    {
      id: `${r}-${o.handle}`,
      field: o,
      value: s[o.handle],
      content: l,
      errors: B(c, o.handle),
      onChange: (h) => t({ ...s, [o.handle]: h })
    }
  ) }, o.handle)) });
  return a ? f : /* @__PURE__ */ n.jsx("div", { className: "cms:rounded-lg cms:border cms:p-4", children: f });
}
function ds({ options: e, value: s, onChange: t, id: c, empty: r, fill: l = !1 }) {
  return /* @__PURE__ */ n.jsxs(m.Select, { value: s ?? M, onValueChange: (a) => t(a === M ? null : a), children: [
    /* @__PURE__ */ n.jsx(m.SelectTrigger, { id: c, className: l ? "cms:w-full" : "cms:w-full cms:max-w-sm", children: /* @__PURE__ */ n.jsx(m.SelectValue, {}) }),
    /* @__PURE__ */ n.jsxs(m.SelectContent, { children: [
      r && /* @__PURE__ */ n.jsx(m.SelectItem, { value: M, children: p("None") }),
      e.map((a) => /* @__PURE__ */ n.jsx(m.SelectItem, { value: a.value, children: a.label }, a.value))
    ] })
  ] });
}
function fs({ options: e, value: s, onChange: t, empty: c, inTickOrder: r = !1, invalid: l = !1 }) {
  return e.length === 0 ? /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: c ?? p("Nothing to choose from yet.") }) : /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-col cms:gap-2", children: e.map((a) => /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
    /* @__PURE__ */ n.jsx(
      m.Checkbox,
      {
        checked: s.includes(a.value),
        "aria-invalid": l || void 0,
        onCheckedChange: (f) => t(f ? r ? [...s.filter((o) => o !== a.value), a.value] : e.map((o) => o.value).filter((o) => o === a.value || s.includes(o)) : s.filter((o) => o !== a.value))
      }
    ),
    a.label,
    r && s.includes(a.value) && /* @__PURE__ */ n.jsxs("span", { className: "cms:text-xs cms:text-muted-foreground", "aria-label": `number ${s.indexOf(a.value) + 1}`, children: [
      "#",
      s.indexOf(a.value) + 1
    ] })
  ] }, a.value)) });
}
function ps({ label: e, value: s, onChange: t, errors: c, id: r, collections: l, placeholder: a, describedBy: f }) {
  const o = s.type === "entry" ? "entry" : "url", h = (d) => {
    const y = { ...s, type: o, ...d }, i = y.type === "entry" ? typeof y.entry_id != "number" : y.url === "" || y.url === void 0 || y.url === null;
    t(i ? y.label || y.new_tab ? y : null : y);
  };
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsx("div", { role: "radiogroup", "aria-label": p(":field: link to", { field: e }), className: "cms:inline-flex cms:w-fit cms:rounded-md cms:border cms:p-0.5", children: ["url", "entry"].map((d) => /* @__PURE__ */ n.jsx(
      m.Button,
      {
        type: "button",
        size: "sm",
        role: "radio",
        "aria-checked": o === d,
        variant: o === d ? "secondary" : "ghost",
        onClick: () => h({ type: d, url: d === "url" ? s.url : void 0, entry_id: d === "entry" ? s.entry_id : void 0 }),
        children: d === "url" ? p("Address") : p("Entry")
      },
      d
    )) }),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:grid cms:gap-2 cms:md:grid-cols-[2fr_1fr_auto] cms:md:items-start", children: [
      o === "url" ? /* @__PURE__ */ n.jsx(
        m.Input,
        {
          id: r,
          "aria-label": p(":field: address", { field: e }),
          placeholder: a || p("https://… or /about"),
          "aria-describedby": f,
          value: typeof s.url == "string" ? s.url : "",
          onChange: (d) => h({ url: d.target.value }),
          "aria-invalid": c.url !== void 0
        }
      ) : /* @__PURE__ */ n.jsx(
        te,
        {
          id: r,
          invalid: c.entry_id !== void 0,
          multiple: !1,
          max: null,
          collections: l,
          value: typeof s.entry_id == "number" ? s.entry_id : null,
          onChange: (d) => h({ entry_id: typeof d == "number" ? d : void 0 })
        }
      ),
      /* @__PURE__ */ n.jsx(
        m.Input,
        {
          "aria-label": p(":field: link text", { field: e }),
          placeholder: o === "entry" ? p("Link text (the entry's title)") : p("Link text"),
          value: typeof s.label == "string" ? s.label : "",
          onChange: (d) => h({ label: d.target.value })
        }
      ),
      /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:h-9 cms:items-center cms:gap-2 cms:text-sm", children: [
        /* @__PURE__ */ n.jsx(m.Checkbox, { checked: s.new_tab === !0, onCheckedChange: (d) => h({ new_tab: d }) }),
        p("New tab")
      ] }),
      /* @__PURE__ */ n.jsx("div", { className: "cms:md:col-span-3", children: /* @__PURE__ */ n.jsx(z, { message: c.url ?? c.entry_id ?? c.label ?? c.type }) })
    ] })
  ] });
}
function se(e, s) {
  return !s && (e.fields ?? []).every((t) => t.type === "text");
}
function xe(e) {
  const s = S.useRef([]);
  return s.current = Ee(s.current, e), {
    keys: s.current,
    remove: (t) => {
      s.current = s.current.filter((c, r) => r !== t);
    },
    move: (t, c) => {
      s.current = m.moveItem(s.current, t, c);
    }
  };
}
function ge() {
  const [e, s] = S.useState(() => /* @__PURE__ */ new Set());
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
function ye({ keys: e, folding: s }) {
  if (e.length < 2) return null;
  const t = s.anyOpen(e);
  return /* @__PURE__ */ n.jsxs(m.Button, { type: "button", variant: "ghost", size: "sm", onClick: () => s.setAll(e, t), children: [
    t ? /* @__PURE__ */ n.jsx(ze, {}) : /* @__PURE__ */ n.jsx(De, {}),
    " ",
    t ? p("Collapse all") : p("Expand all")
  ] });
}
function hs({ field: e, value: s, onChange: t, errors: c, id: r, content: l = !1 }) {
  const a = e.fields ?? [], f = s.map(T), o = xe(f.length), h = (b, g) => t(f.map((k, N) => N === b ? g : k)), d = (b) => {
    o.remove(b), t(f.filter((g, k) => k !== b));
  }, y = e.label ? e.label.toLowerCase().replace(/s$/, "") : p("row"), i = y.charAt(0).toUpperCase() + y.slice(1), x = f.map((b, g) => ({ row: b, index: g, key: o.keys[g] ?? String(g) })), v = ge();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(
      m.SortableList,
      {
        items: x,
        getId: (b) => b.key,
        getLabel: (b) => `${y} ${b.index + 1}`,
        onReorder: (b, g, k) => {
          o.move(g, k), t(b.map((N) => N.row));
        },
        children: ({ row: b, index: g, key: k }, { handle: N }) => se(e, l) ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-start cms:gap-2", children: [
          /* @__PURE__ */ n.jsx(m.SortableHandle, { ...N, label: p("Move :item :number", { item: y, number: g + 1 }), className: "cms:mt-1" }),
          a.map((j) => /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-1 cms:flex-col cms:gap-1", children: [
            /* @__PURE__ */ n.jsx(
              m.Input,
              {
                "aria-label": j.label || j.handle,
                placeholder: j.label || j.handle,
                value: typeof b[j.handle] == "string" ? b[j.handle] : "",
                onChange: ($) => h(g, { ...b, [j.handle]: $.target.value })
              }
            ),
            /* @__PURE__ */ n.jsx(z, { message: c[`${g}.${j.handle}`] })
          ] }, j.handle)),
          /* @__PURE__ */ n.jsx(m.Button, { type: "button", variant: "ghost", size: "icon", "aria-label": p("Remove :item :number", { item: y, number: g + 1 }), onClick: () => d(g), children: /* @__PURE__ */ n.jsx(ce, {}) })
        ] }) : (() => {
          const j = B(c, String(g)), $ = v.isCollapsed(k, Object.keys(j).length > 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              be,
              {
                label: `${i} ${g + 1}`,
                summary: ne(a, b),
                handle: N,
                onRemove: () => d(g),
                collapsed: $,
                onToggle: () => v.toggle(k),
                controls: `${r}-${g}-fields`,
                hidden: l ? b._hidden === !0 : void 0,
                onHidden: (C) => h(g, { ...b, _hidden: C })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${g}-fields`, hidden: $, children: !$ && /* @__PURE__ */ n.jsx(L, { fields: a, value: b, onChange: (C) => h(g, C), errors: j, id: `${r}-${g}`, content: l }) })
          ] });
        })()
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(m.Button, { type: "button", variant: "outline", size: "sm", onClick: () => t([...f, l ? Te() : {}]), children: [
        /* @__PURE__ */ n.jsx(re, {}),
        " ",
        p("Add :item", { item: y })
      ] }),
      !se(e, l) && /* @__PURE__ */ n.jsx(ye, { keys: x.map((b) => b.key), folding: v })
    ] })
  ] });
}
function xs({ field: e, value: s, onChange: t, errors: c, id: r }) {
  const l = e.blocks ?? [], a = s.map(T), f = xe(a.length), o = (i, x) => t(a.map((v, b) => b === i ? x : v)), h = (i) => {
    f.remove(i), t(a.filter((x, v) => v !== i));
  }, d = a.map((i, x) => ({ block: i, index: x, key: f.keys[x] ?? String(x) })), y = ge();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-3", children: [
    /* @__PURE__ */ n.jsx(
      m.SortableList,
      {
        items: d,
        getId: (i) => i.key,
        className: "cms:gap-3",
        getLabel: (i) => p(":type block", { type: l.find((x) => x.type === i.block.type)?.label ?? String(i.block.type) }),
        onReorder: (i, x, v) => {
          f.move(x, v), t(i.map((b) => b.block));
        },
        children: ({ block: i, index: x, key: v }, { handle: b }) => {
          const g = l.find((j) => j.type === i.type), k = B(c, `${x}.values`), N = y.isCollapsed(v, Object.keys(k).length > 0 || c[`${x}.type`] !== void 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              be,
              {
                label: g?.label ?? String(i.type),
                summary: g ? ne(g.fields, T(i.values)) : null,
                badge: !0,
                handle: b,
                onRemove: () => h(x),
                collapsed: N,
                onToggle: () => y.toggle(v),
                controls: `${r}-${x}-fields`,
                hidden: i.hidden === !0,
                onHidden: (j) => o(x, { ...i, hidden: j })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${x}-fields`, hidden: N, children: N ? null : g ? /* @__PURE__ */ n.jsx(
              L,
              {
                fields: g.fields,
                value: T(i.values),
                onChange: (j) => o(x, { ...i, values: j }),
                errors: k,
                id: `${r}-${x}`,
                content: !0
              }
            ) : /* @__PURE__ */ n.jsx("p", { className: "cms:rounded-lg cms:border cms:p-4 cms:text-sm cms:text-muted-foreground", children: p("This kind of block no longer exists. Its values are kept.") }) }),
            /* @__PURE__ */ n.jsx(z, { message: c[`${x}.type`] })
          ] });
        }
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(m.DropdownMenu, { children: [
        /* @__PURE__ */ n.jsx(m.DropdownMenuTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsxs(m.Button, { type: "button", variant: "outline", size: "sm", disabled: l.length === 0, children: [
          /* @__PURE__ */ n.jsx(re, {}),
          " ",
          p("Add block")
        ] }) }),
        /* @__PURE__ */ n.jsx(m.DropdownMenuContent, { align: "start", children: l.map((i) => /* @__PURE__ */ n.jsx(m.DropdownMenuItem, { onSelect: () => t([...a, Ce(i.type)]), children: i.label }, i.type)) })
      ] }),
      /* @__PURE__ */ n.jsx(ye, { keys: d.map((i) => i.key), folding: y })
    ] })
  ] });
}
function be({ label: e, summary: s, badge: t = !1, handle: c, onRemove: r, collapsed: l, onToggle: a, controls: f, hidden: o, onHidden: h }) {
  const d = l ? Oe : He, y = s ? `${e}: ${s}` : e;
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(m.SortableHandle, { ...c, label: p("Move :title", { title: y }) }),
    /* @__PURE__ */ n.jsxs(
      "button",
      {
        type: "button",
        onClick: a,
        "aria-expanded": !l,
        "aria-controls": f,
        className: "cms:flex cms:min-w-0 cms:flex-1 cms:items-center cms:gap-2 cms:rounded-md cms:text-left cms:outline-none cms:focus-visible:ring-2 cms:focus-visible:ring-ring",
        children: [
          /* @__PURE__ */ n.jsx(d, { className: "cms:size-4 cms:shrink-0 cms:text-muted-foreground", "aria-hidden": !0 }),
          t ? /* @__PURE__ */ n.jsx(m.Badge, { variant: "secondary", children: e }) : /* @__PURE__ */ n.jsx("span", { className: "cms:shrink-0 cms:text-sm cms:font-medium", children: e }),
          s && /* @__PURE__ */ n.jsx("span", { className: "cms:truncate cms:text-sm cms:text-muted-foreground", children: s }),
          /* @__PURE__ */ n.jsx("span", { className: "cms:sr-only", children: l ? p("(collapsed)") : "" })
        ]
      }
    ),
    o !== void 0 && /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:shrink-0 cms:items-center cms:gap-2 cms:text-sm cms:text-muted-foreground", title: p("Off: kept here, but left out of the page"), children: [
      /* @__PURE__ */ n.jsx(m.Switch, { checked: !o, onCheckedChange: (i) => h(!i), "aria-label": p(":title: visible on site", { title: y }) }),
      p("Visible on site")
    ] }),
    /* @__PURE__ */ n.jsx(
      m.Button,
      {
        type: "button",
        variant: "ghost",
        size: "icon",
        className: "cms:size-8 cms:shrink-0 cms:text-destructive",
        "aria-label": p("Remove :title", { title: y }),
        onClick: r,
        children: /* @__PURE__ */ n.jsx(ce, {})
      }
    )
  ] });
}
export {
  L as A,
  Es as B,
  He as C,
  rs as D,
  Z as E,
  ee as F,
  ls as G,
  De as H,
  Ve as I,
  he as J,
  W as K,
  me as L,
  Ss as M,
  Ms as O,
  Ue as T,
  Os as a,
  B as b,
  Xe as c,
  Ds as d,
  Ze as e,
  as as f,
  Cs as g,
  Is as h,
  Hs as i,
  zs as j,
  cs as k,
  Fs as l,
  ts as m,
  As as n,
  ns as o,
  Ps as p,
  Bs as q,
  Ts as r,
  Rs as s,
  qs as t,
  Ye as u,
  Ge as v,
  Ls as w,
  Je as x,
  Qe as y,
  Ke as z
};
