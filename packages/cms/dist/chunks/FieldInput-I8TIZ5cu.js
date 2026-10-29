import { a as Q, t as d, j as n } from "./admin-shE_CgCh.js";
import { c as H, _ as $ } from "./createLucideIcon-Ccbp7F45.js";
import { u as m } from "./ui-Dh5wfMwT.js";
import { E as Z } from "./EntriesPicker-Bnd7yW9Z.js";
import { F as L } from "./FieldError-Ca1tQD8Y.js";
import { X as xe, c as A, T as ge, o as ye, d as q, e as be, g as je, i as ee, n as ve, f as we, l as O, h as P, j as ke, L as Ne, k as $e, t as _e, s as Se, b as Ae, m as Ce, p as Te, q as Ie } from "./TermsPicker-BrOe_tiZ.js";
import { S as Ee } from "./search-3zKi7F1_.js";
import { L as Le } from "./loader-circle-ByksABXO.js";
import { P as se } from "./plus-COqahUZ2.js";
import { T as te } from "./Confirm-DX1zITqe.js";
import { C as Oe } from "./chevron-right-9qVV6b-Q.js";
const ne = {
  name: "chevron-down",
  size: 24,
  node: [["path", { d: "m6 9 6 6 6-6", key: "qrunsl" }]]
};
ne.node;
const De = H(ne);
const re = {
  name: "chevrons-down-up",
  size: 24,
  node: [
    ["path", { d: "m7 20 5-5 5 5", key: "13a0gw" }],
    ["path", { d: "m7 4 5 5 5-5", key: "1kwcof" }]
  ]
};
re.node;
const Re = H(re);
const ae = {
  name: "info",
  size: 24,
  node: [
    ["circle", { cx: "12", cy: "12", r: "10", key: "1mglay" }],
    ["path", { d: "M12 16v-4", key: "1dtifu" }],
    ["path", { d: "M12 8h.01", key: "e9boi3" }]
  ]
};
ae.node;
const He = H(ae), Fe = 200;
async function ze(e, s, t) {
  const a = new URLSearchParams({ q: s.trim(), source: e }), r = await fetch(`${Q("rich-text-links")}?${a.toString()}`, { signal: t, headers: { Accept: "application/json" }, credentials: "same-origin" });
  return r.ok ? (await r.json()).groups?.[0]?.items ?? [] : [];
}
function Me({ id: e, source: s, value: t, invalid: a, onChange: r, label: o }) {
  const [c, f] = $.useState(null), [i, w] = $.useState(""), [y, j] = $.useState(!1), [l, u] = $.useState(null), [v, g] = $.useState(0), p = $.useId();
  $.useEffect(() => {
    if (t === null) {
      f(null);
      return;
    }
    if (c?.id === t) return;
    const x = new AbortController();
    return fetch(Q(`rich-text-links/${s}:${t}`), { signal: x.signal, headers: { Accept: "application/json" }, credentials: "same-origin" }).then(async (h) => {
      const N = h.ok ? (await h.json()).item : null;
      f(N ?? { id: t, title: d("Not found"), hint: null });
    }).catch(() => {
    }), () => x.abort();
  }, [t, s]), $.useEffect(() => {
    if (!y) return;
    const x = new AbortController();
    u(null);
    const h = setTimeout(() => {
      ze(s, i, x.signal).then((N) => {
        u(N), g(0);
      }).catch(() => {
      });
    }, Fe);
    return () => {
      clearTimeout(h), x.abort();
    };
  }, [y, i, s]);
  const k = (x) => {
    f(x), r(x.id), j(!1), w("");
  };
  return t !== null ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:h-9 cms:min-w-0 cms:items-center cms:gap-1 cms:rounded-md cms:border cms:bg-muted/40 cms:pr-1 cms:pl-3 cms:text-sm", children: [
    /* @__PURE__ */ n.jsx("span", { className: "cms:truncate", children: c?.title ?? d("Loading…") }),
    c?.hint && /* @__PURE__ */ n.jsx("span", { className: "cms:truncate cms:text-xs cms:text-muted-foreground", children: c.hint }),
    /* @__PURE__ */ n.jsx(m.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:ml-auto cms:size-7", "aria-label": d("Remove"), onClick: () => r(null), children: /* @__PURE__ */ n.jsx(xe, {}) })
  ] }) : (
    // Over the inputs below it while its list is open.
    /* @__PURE__ */ n.jsxs("div", { className: y ? "cms:relative cms:z-30" : "cms:relative", children: [
      /* @__PURE__ */ n.jsx(Ee, { className: "cms:pointer-events-none cms:absolute cms:top-1/2 cms:left-2.5 cms:size-4 cms:-translate-y-1/2 cms:text-muted-foreground", "aria-hidden": !0 }),
      /* @__PURE__ */ n.jsx(
        m.Input,
        {
          id: e,
          value: i,
          autoComplete: "off",
          className: "cms:pl-8",
          placeholder: d("Search…"),
          "aria-label": o,
          "aria-invalid": a || void 0,
          role: "combobox",
          "aria-expanded": y && (l?.length ?? 0) > 0,
          "aria-controls": p,
          "aria-activedescendant": y && l?.length ? `${p}-${v}` : void 0,
          onFocus: () => j(!0),
          onBlur: () => j(!1),
          onChange: (x) => {
            w(x.target.value), j(!0);
          },
          onKeyDown: (x) => {
            const h = l?.length ?? 0;
            x.key === "ArrowDown" && h > 0 ? (x.preventDefault(), g((N) => (N + 1) % h)) : x.key === "ArrowUp" && h > 0 ? (x.preventDefault(), g((N) => (N - 1 + h) % h)) : x.key === "Enter" && l?.[v] ? (x.preventDefault(), k(l[v])) : x.key === "Escape" && j(!1);
          }
        }
      ),
      y && /* @__PURE__ */ n.jsxs(
        "div",
        {
          id: p,
          role: "listbox",
          "aria-label": o,
          className: "cms:absolute cms:z-30 cms:mt-1 cms:max-h-64 cms:w-full cms:overflow-y-auto cms:rounded-md cms:border cms:bg-card cms:p-1 cms:shadow-md",
          children: [
            l === null && /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2 cms:px-2 cms:py-1.5 cms:text-sm cms:text-muted-foreground", children: [
              /* @__PURE__ */ n.jsx(Le, { className: "cms:size-4 cms:animate-spin", "aria-hidden": !0 }),
              " ",
              d("Searching…")
            ] }),
            l?.length === 0 && /* @__PURE__ */ n.jsx("div", { className: "cms:px-2 cms:py-1.5 cms:text-sm cms:text-muted-foreground", children: d("Nothing found.") }),
            l?.map((x, h) => /* @__PURE__ */ n.jsxs(
              "div",
              {
                id: `${p}-${h}`,
                role: "option",
                "aria-selected": h === v,
                className: `cms:flex cms:cursor-pointer cms:items-baseline cms:gap-2 cms:rounded-sm cms:px-2 cms:py-1.5 cms:text-sm ${h === v ? "cms:bg-accent cms:text-accent-foreground" : ""}`,
                onMouseDown: (N) => N.preventDefault(),
                onMouseEnter: () => g(h),
                onClick: () => k(x),
                children: [
                  /* @__PURE__ */ n.jsx("span", { className: "cms:truncate", children: x.title }),
                  x.hint && /* @__PURE__ */ n.jsx("span", { className: "cms:shrink-0 cms:text-xs cms:text-muted-foreground", children: x.hint })
                ]
              },
              x.id
            ))
          ]
        }
      )
    ] })
  );
}
const qe = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
function C(e) {
  return e.replace(/[&<>"']/g, (s) => qe[s] ?? s);
}
function Pe(e) {
  return /[\u0000- \u007f]/.test(e) ? !1 : /^(https?:\/\/[^/]|mailto:|tel:|\/(?![/\\])|#)/i.test(e);
}
const B = new RegExp("`([^`\\n]+)`|\\[([^\\]\\n]+)\\]\\(((?:[^()\\s]|\\([^()\\s]*\\))+)\\)|\\*\\*(?=\\S)(.+?)(?<=\\S)\\*\\*|\\*(?=[^\\s*])([^*\\n]+?)(?<=\\S)\\*");
function I(e, s = !1) {
  let t = "", a = e;
  for (let r = B.exec(a); r !== null; r = B.exec(a)) {
    t += C(a.slice(0, r.index));
    const [o, c, f, i, w, y] = r;
    c !== void 0 ? t += `<code>${C(c)}</code>` : f !== void 0 && i !== void 0 ? t += s || !Pe(i) ? I(f, s) : `<a href="${C(i)}" target="_blank" rel="noopener noreferrer">${I(f, !0)}</a>` : w !== void 0 ? t += `<strong>${I(w, s)}</strong>` : y !== void 0 ? t += `<em>${I(y, s)}</em>` : t += C(o), a = a.slice(r.index + o.length);
  }
  return t + C(a);
}
const Be = ["text", "number"], Ke = ["group", "repeater", "flexible"], Ve = ["placeholder", "prefix", "suffix"];
function E(e) {
  return typeof e == "string" && e.trim() !== "" ? e.trim() : null;
}
function ce(e) {
  return { hint: E(e.hint), tooltip: E(e.hint_tooltip) };
}
function le(e) {
  return Be.includes(e.type) ? { prefix: E(e.config?.prefix), suffix: E(e.config?.suffix) } : { prefix: null, suffix: null };
}
function K({ field: e, id: s, label: t, maxWidth: a = "cms:max-w-[60%]" }) {
  const { hint: r, tooltip: o } = ce(e);
  return r === null && o === null ? null : /* @__PURE__ */ n.jsxs("span", { className: `cms:ml-auto cms:flex ${a} cms:min-w-0 cms:shrink-0 cms:items-center cms:gap-1 cms:text-xs cms:leading-none cms:text-muted-foreground`, children: [
    r !== null && /* @__PURE__ */ n.jsx("span", { id: `${s}-hint`, className: "cms:min-w-0 cms:truncate cms:py-0.5", title: r, children: r }),
    o !== null && /* @__PURE__ */ n.jsxs(m.Tooltip, { children: [
      /* @__PURE__ */ n.jsx(m.TooltipTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsx(
        "button",
        {
          type: "button",
          "aria-label": d("More about :field", { field: t }),
          className: "cms:-m-1 cms:inline-flex cms:shrink-0 cms:rounded-full cms:p-1 cms:text-muted-foreground cms:outline-none cms:hover:text-foreground cms:focus-visible:ring-2 cms:focus-visible:ring-ring",
          children: /* @__PURE__ */ n.jsx(He, { className: "cms:size-3.5", "aria-hidden": !0 })
        }
      ) }),
      /* @__PURE__ */ n.jsx(m.TooltipContent, { className: "cms:max-w-xs cms:text-left cms:whitespace-pre-line", children: o })
    ] })
  ] });
}
function Ue({ text: e, id: s, className: t = "" }) {
  return !e || e.trim() === "" ? null : /* @__PURE__ */ n.jsx(
    "p",
    {
      id: s,
      className: `cms:text-sm cms:text-muted-foreground cms:[overflow-wrap:anywhere] cms:[&_a]:font-medium cms:[&_a]:text-foreground cms:[&_a]:underline cms:[&_a]:underline-offset-2 cms:[&_a:hover]:text-primary cms:[&_code]:rounded cms:[&_code]:bg-muted cms:[&_code]:px-1 cms:[&_code]:font-mono cms:[&_code]:text-[0.8125rem] cms:[&_strong]:font-semibold cms:[&_strong]:text-foreground ${t}`.trim(),
      dangerouslySetInnerHTML: { __html: I(e) }
    }
  );
}
const Ge = $.createContext(null);
function We({ id: e, className: s = "" }) {
  const t = $.useContext(Ge);
  return /* @__PURE__ */ n.jsx("p", { id: e, className: `cms:text-sm cms:text-muted-foreground ${s}`.trim(), children: t ? d("Not translatable: edit it in :language.", { language: t }) : d("Not translatable: edit it in the main language.") });
}
function V({ prefix: e, suffix: s, id: t, className: a = "", children: r }) {
  if (e === null && s === null) return /* @__PURE__ */ n.jsx(n.Fragment, { children: r });
  const o = "cms:flex cms:shrink-0 cms:items-center cms:bg-muted/60 cms:px-3 cms:text-sm cms:whitespace-nowrap cms:text-muted-foreground cms:select-none";
  return /* @__PURE__ */ n.jsxs("div", { "data-slot": "input-group", className: `cms:flex cms:h-10 cms:w-full cms:min-w-0 cms:items-stretch cms:overflow-hidden cms:rounded-md cms:border cms:border-input/80 cms:bg-card cms:shadow-xs cms:transition-[border-color,box-shadow] cms:duration-150 cms:hover:border-ring/45 cms:focus-within:border-ring/70 cms:focus-within:ring-4 cms:focus-within:ring-ring-glow cms:has-[[aria-invalid=true]]:border-destructive cms:has-[[aria-invalid=true]]:ring-destructive/20 cms:has-disabled:cursor-not-allowed cms:has-disabled:opacity-50 cms:has-[[readonly]]:bg-muted/30 cms:dark:bg-input/20 ${a}`.trim(), children: [
    e !== null && /* @__PURE__ */ n.jsx("span", { id: `${t}-prefix`, className: `${o} cms:border-r cms:border-input/80`, children: e }),
    r,
    s !== null && /* @__PURE__ */ n.jsx("span", { id: `${t}-suffix`, className: `${o} cms:border-l cms:border-input/80`, children: s })
  ] });
}
const U = "cms:h-full cms:min-w-0 cms:flex-1 cms:rounded-none cms:border-0 cms:bg-transparent cms:shadow-none cms:hover:border-0 cms:focus-visible:ring-0 cms:aria-invalid:ring-0 cms:disabled:opacity-100 cms:dark:bg-transparent", oe = (e, s = {}) => e.replace(/:([A-Za-z_]+)/g, (t, a) => s[a] ?? t), Os = 5;
let Xe = 0;
const F = () => `f${++Xe}`;
function Ye(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").replace(/^[0-9_]+/, "").slice(0, 64);
}
function Je(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^[0-9_]+/, "").replace(/_{2,}/g, "_").slice(0, 64);
}
function ie(e, s) {
  const t = new Set(s.map((o) => o.handle)), a = Ye(e) || "field";
  let r = a;
  for (let o = 2; t.has(r); o++)
    r = `${a}_${o}`;
  return r;
}
function Qe(e) {
  return (e ?? []).map(({ fields: s, ...t }) => ({
    ...t,
    key: F(),
    fields: Qe(s),
    handleTouched: !0,
    savedHandle: t.handle
  }));
}
function Ze(e, s) {
  return e.map(({ key: t, handleTouched: a, savedHandle: r, fields: o, ...c }) => ({
    ...c,
    validation: (c.validation ?? []).map((f) => f.trim()).filter((f) => f !== ""),
    ...s(c.type) ? { fields: Ze(o, s) } : {}
  }));
}
function Ds(e, s, t) {
  return { key: F(), type: e, label: s, handle: ie(s, t), required: !1, config: {}, fields: [], handleTouched: !1 };
}
function Rs(e) {
  return e.type !== "fieldset" && e.savedHandle !== void 0 && e.savedHandle !== "" && e.handle !== e.savedHandle ? e.savedHandle : null;
}
function es(e) {
  return e.map((s) => ({ ...s, savedHandle: s.handle, fields: es(s.fields) }));
}
function Hs(e, s, t) {
  if (e.handleTouched) return { ...e, label: s };
  const a = t.filter((r) => r.key !== e.key);
  return { ...e, label: s, handle: ie(s || e.type, a) };
}
function ss(e, s) {
  const t = `${s}.`, a = {};
  for (const [r, o] of Object.entries(e))
    r.startsWith(t) && (a[r.slice(t.length)] = o);
  return a;
}
function z(e, s) {
  const t = e[s];
  return { ...ss(e, s), ...t !== void 0 ? { "": t } : {} };
}
function Fs(e, s) {
  return Object.keys(e).filter((t) => t === s || t.startsWith(`${s}.`));
}
function zs(e, s) {
  return Object.keys(e).some((t) => t === s || t.startsWith(`${s}.`));
}
function Ms(e, s) {
  return s.filter((t) => t.key !== e.key && t.handle !== "" && t.type !== "fieldset");
}
function qs(e, s, t, a = oe) {
  return e.type === "fieldset" ? null : t?.includes(e.handle) ? a('":handle" is used by the content itself; choose another handle.', { handle: e.handle }) : e.handle !== "" && s.some((r) => r.key !== e.key && r.type !== "fieldset" && r.handle === e.handle) ? a('There is already a field ":handle" here.', { handle: e.handle }) : null;
}
function Ps(e, s) {
  return typeof e.translatable == "boolean" ? e.translatable : s;
}
function Bs(e, s, t) {
  return { ...e, translatable: s === t ? null : s };
}
function ts(e, s, t = []) {
  const a = /* @__PURE__ */ new Set([...s.map((c) => c.handle), ...t]), r = `${(Je(e) || "field").slice(0, 56)}_copy`;
  let o = r;
  for (let c = 2; a.has(o); c++)
    o = `${r}_${c}`;
  return o;
}
function me(e) {
  const { key: s, savedHandle: t, fields: a, ...r } = e;
  return { ...structuredClone(r), key: F(), fields: a.map(me) };
}
function Ks(e, s, t = [], a = oe) {
  const r = e[s];
  if (r === void 0) return null;
  const o = r.label || r.handle, c = {
    ...me(r),
    label: a(":label (copy)", { label: o }),
    handle: r.type === "fieldset" ? r.handle : ts(r.handle, e, t),
    handleTouched: !0
  };
  return { fields: [...e.slice(0, s + 1), c, ...e.slice(s + 1)], copy: c };
}
function ns(e, s) {
  const t = e.condition;
  return !t || "rules" in t ? null : (t.operator === "filled" || t.operator === "equals" && (t.value === !0 || t.value === "true")) && s.some((r) => r.handle === t.field && r.type === "toggle") ? t.field : null;
}
function Vs(e) {
  const s = (c) => c.type === "number" || c.type === "text" && (c.instructions ?? "").length <= 60, t = (c) => c.type === "text" && Ve.includes(c.handle), a = {}, r = [];
  for (const c of e) {
    const f = ns(c, e);
    f === null ? r.push(c) : (a[f] ??= []).push(c);
  }
  const o = r.filter((c) => !t(c));
  return { options: o.filter((c) => !s(c)), limits: o.filter(s), appearance: r.filter(t), dependents: a };
}
const rs = ["html", "css", "javascript", "json", "plain"];
function as(e) {
  const s = e.config?.language;
  return typeof s == "string" && rs.includes(s) ? s : "plain";
}
const Us = [
  { value: "equals", label: "is", needsValue: !0 },
  { value: "not_equals", label: "is not", needsValue: !0 },
  { value: "filled", label: "is filled in", needsValue: !1 },
  { value: "empty", label: "is empty", needsValue: !1 }
];
function G(e) {
  return e === !0 ? "true" : e === !1 ? "false" : typeof e == "string" || typeof e == "number" ? String(e) : "";
}
function cs(e) {
  return e ? "rules" in e ? Array.isArray(e.rules) ? e.rules : [] : [e] : [];
}
function ls(e) {
  return e && "rules" in e && e.match === "any" ? "any" : "all";
}
function Gs(e, s) {
  return e.length === 0 ? null : e.length === 1 ? e[0] : { match: s, rules: e };
}
function W(e, s) {
  const t = s[e.field], a = t != null && t !== "" && t !== !1 && !(Array.isArray(t) && t.length === 0), r = (Array.isArray(t) ? t : [t]).map(G).includes(G(e.value));
  switch (e.operator) {
    case "equals":
      return r;
    case "not_equals":
      return !r;
    case "filled":
      return a;
    default:
      return !a;
  }
}
function os(e, s) {
  const t = cs(e.condition);
  return t.length === 0 ? !0 : ls(e.condition) === "any" ? t.some((a) => W(a, s)) : t.every((a) => W(a, s));
}
function Ws(e) {
  const s = e?.config ?? {};
  switch (e?.type) {
    case "select": {
      const t = (Array.isArray(s.options) ? s.options : []).flatMap((a) => {
        if (typeof a == "string" && a !== "") return [{ value: a, label: a }];
        if (a && typeof a == "object" && typeof a.value == "string" && a.value !== "") {
          const { value: r, label: o } = a;
          return [{ value: r, label: typeof o == "string" && o !== "" ? o : r }];
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
function Xs(e) {
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
const is = [25, 33, 50, 66, 75, 100];
function ms(e) {
  return is.find((s) => s === e.width) ?? 100;
}
function us(e = "cms:gap-x-4 cms:gap-y-6") {
  return `cms:@container cms:grid cms:grid-cols-12 cms:items-start ${e}`;
}
const ds = {
  25: "cms:@lg:col-span-3",
  33: "cms:@lg:col-span-4",
  50: "cms:@lg:col-span-6",
  66: "cms:@lg:col-span-8",
  75: "cms:@lg:col-span-9",
  100: ""
};
function X(e) {
  return `cms:col-span-12 cms:min-w-0 ${ds[ms(e)]}`.trim();
}
function Ys(e, s) {
  const { width: t, ...a } = e;
  return s === 100 ? a : { ...a, width: s };
}
const D = "__none";
function R(e) {
  const s = e.config?.options;
  return Array.isArray(s) ? s.flatMap((t) => {
    if (typeof t == "string") return [{ value: t, label: t }];
    if (t && typeof t == "object" && typeof t.value == "string") {
      const { value: a, label: r } = t;
      return [{ value: a, label: r || a }];
    }
    return [];
  }) : [];
}
const fs = ["repeater", "group", "flexible", "link", "media", "rich_text", "entries"];
function ue(e) {
  if (e.label) return e.label;
  const s = e.handle.replace(/_/g, " ");
  return s.charAt(0).toUpperCase() + s.slice(1);
}
function Y({ field: e, value: s, onChange: t, errors: a, id: r, content: o = !1, fill: c = !1, marker: f = null, shared: i = e.shared === !0 }) {
  const w = ue(e), y = fs.includes(e.type) || ["select", "terms"].includes(e.type) && e.config?.multiple === !0, j = a[""] !== void 0, l = Ke.includes(e.type), u = e.instructions && e.instructions.trim() !== "" ? /* @__PURE__ */ n.jsx(Ue, { id: `${r}-help`, text: e.instructions }) : null, v = i ? /* @__PURE__ */ n.jsx(We, { id: `${r}-shared` }) : null, { hint: g } = ce(e), { prefix: p, suffix: k } = le(e), x = [g && `${r}-hint`, p && `${r}-prefix`, k && `${r}-suffix`, u && `${r}-help`, v && `${r}-shared`, j && `${r}-error`].filter(Boolean).join(" ") || void 0;
  return /* @__PURE__ */ n.jsxs(
    "div",
    {
      className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-2",
      ...y ? { role: "group", "aria-labelledby": `${r}-label`, ...j ? { "aria-invalid": !0 } : {}, ...j || u || v ? { "aria-describedby": [u && `${r}-help`, v && `${r}-shared`, j && `${r}-error`].filter(Boolean).join(" ") } : {} } : {},
      children: [
        e.type === "toggle" ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-3", children: [
          /* @__PURE__ */ n.jsx(m.Switch, { id: r, checked: s === !0, "aria-describedby": x, onCheckedChange: (h) => t(h) }),
          /* @__PURE__ */ n.jsx(m.Label, { htmlFor: r, className: "cms:min-w-0", children: w }),
          f,
          /* @__PURE__ */ n.jsx(K, { field: e, id: r, label: w })
        ] }) : /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:min-h-4 cms:min-w-0 cms:items-center cms:gap-2", children: [
          /* @__PURE__ */ n.jsxs(m.Label, { id: `${r}-label`, htmlFor: y ? void 0 : r, className: "cms:min-w-0", children: [
            w,
            e.required && /* @__PURE__ */ n.jsx("span", { className: "cms:text-destructive", children: " *" }),
            f
          ] }),
          /* @__PURE__ */ n.jsx(K, { field: e, id: r, label: w })
        ] }),
        l && u,
        l && v,
        /* @__PURE__ */ n.jsx(hs, { field: e, value: s, onChange: t, errors: a, id: r, content: o, fill: c, describedBy: x }),
        ["text", "textarea", "code"].includes(e.type) && _(e, "max") !== void 0 && /* @__PURE__ */ n.jsx(ps, { length: typeof s == "string" ? [...s].length : 0, max: _(e, "max") ?? 0 }),
        !l && u,
        !l && v,
        /* @__PURE__ */ n.jsx(L, { message: a[""], id: `${r}-error` })
      ]
    }
  );
}
function ps({ length: e, max: s }) {
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
function hs({ field: e, value: s, onChange: t, errors: a, id: r, content: o, fill: c = !1, describedBy: f }) {
  const i = a[""] !== void 0, { prefix: w, suffix: y } = le(e), j = w !== null || y !== null;
  switch (e.type) {
    case "toggle":
      return null;
    case "file":
      return /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: d("Visitors attach a file here when they send a form.") });
    case "textarea":
      return /* @__PURE__ */ n.jsx(
        m.Textarea,
        {
          id: r,
          "aria-invalid": i,
          "aria-describedby": f,
          value: typeof s == "string" ? s : "",
          rows: _(e, "rows") ?? 4,
          placeholder: T(e, "placeholder"),
          onChange: (l) => t(l.target.value)
        }
      );
    case "code":
      return /* @__PURE__ */ n.jsx(
        m.CodeEditor,
        {
          id: r,
          "aria-labelledby": `${r}-label`,
          "aria-describedby": f,
          invalid: i,
          language: as(e),
          value: typeof s == "string" ? s : "",
          placeholder: T(e, "placeholder"),
          onChange: (l) => t(l)
        }
      );
    case "rich_text":
      return /* @__PURE__ */ n.jsx(m.RichTextEditor, { id: r, labelledBy: `${r}-label`, invalid: i, imageStyles: e.imageStyles, value: typeof s == "string" ? s : "", onChange: t });
    case "number": {
      const l = je(s);
      return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ n.jsx(V, { prefix: w, suffix: y, id: r, className: c ? "" : "cms:max-w-64", children: /* @__PURE__ */ n.jsx(
          m.Input,
          {
            id: r,
            type: "number",
            className: j ? U : c ? "cms:w-full" : "cms:max-w-48",
            placeholder: T(e, "placeholder"),
            "aria-invalid": i,
            "aria-describedby": [f, l && `${r}-grouped`].filter(Boolean).join(" ") || void 0,
            value: typeof s == "number" ? String(s) : typeof s == "string" ? s : "",
            min: _(e, "min"),
            max: _(e, "max"),
            step: _(e, "step") ?? "any",
            onChange: (u) => t(u.target.value === "" ? null : Number(u.target.value))
          }
        ) }),
        l && /* @__PURE__ */ n.jsx("span", { id: `${r}-grouped`, className: "cms:text-sm cms:tabular-nums cms:text-muted-foreground", children: l })
      ] });
    }
    case "date": {
      const l = e.config?.time === !0;
      return /* @__PURE__ */ n.jsx(
        m.DatePicker,
        {
          id: r,
          withTime: l,
          invalid: i,
          required: e.required === !0,
          value: q(be(s, l)),
          onChange: (u) => t(q(u ?? ""))
        }
      );
    }
    case "select":
      return e.config?.multiple === !0 ? /* @__PURE__ */ n.jsx(
        gs,
        {
          options: R(e),
          value: Array.isArray(s) ? s.map(String) : [],
          onChange: t,
          invalid: i,
          empty: typeof e.config?.empty == "string" ? e.config.empty : void 0,
          inTickOrder: e.config?.in_tick_order === !0
        }
      ) : /* @__PURE__ */ n.jsx(
        xs,
        {
          options: R(e),
          value: typeof s == "string" ? s : null,
          onChange: t,
          id: r,
          fill: c,
          empty: ye(R(e), e.required === !0, typeof e.config?.default == "string" && e.config.default !== "", typeof s == "string" && s !== "")
        }
      );
    case "terms": {
      const l = e.config?.multiple === !0, u = Array.isArray(s) ? s.filter((v) => typeof v == "number") : typeof s == "number" ? s : null;
      return /* @__PURE__ */ n.jsx(
        ge,
        {
          id: r,
          taxonomy: typeof e.taxonomy == "string" ? e.taxonomy : null,
          multiple: l,
          max: _(e, "max") ?? null,
          required: e.required === !0,
          invalid: i,
          value: l ? Array.isArray(u) ? u : [] : typeof u == "number" ? u : null,
          onChange: t
        }
      );
    }
    case "entries":
      return /* @__PURE__ */ n.jsx(
        Z,
        {
          id: r,
          invalid: i,
          multiple: e.config?.multiple === !0,
          max: _(e, "max") ?? null,
          collections: e.collections ?? [],
          onChange: t,
          value: typeof s == "number" || Array.isArray(s) && s.every((l) => typeof l == "number") ? s : null
        }
      );
    case "link":
      return /* @__PURE__ */ n.jsx(
        js,
        {
          label: ue(e),
          value: A(s),
          onChange: t,
          errors: a,
          id: r,
          collections: e.collections ?? [],
          placeholder: T(e, "placeholder"),
          describedBy: f
        }
      );
    case "media":
      return /* @__PURE__ */ n.jsx(
        m.MediaPickerInput,
        {
          id: r,
          invalid: i,
          disabled: !1,
          multiple: e.config?.multiple === !0,
          max: _(e, "max") ?? null,
          types: Array.isArray(e.config?.types) ? e.config.types : [],
          value: typeof s == "number" || Array.isArray(s) && s.every((l) => typeof l == "number") ? s : null,
          onChange: t
        }
      );
    case "group":
      return /* @__PURE__ */ n.jsx(M, { fields: e.fields ?? [], value: A(s), onChange: t, errors: a, id: r, content: o });
    case "repeater":
      return /* @__PURE__ */ n.jsx(vs, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: a, id: r, content: o });
    case "flexible":
      return /* @__PURE__ */ n.jsx(ws, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: a, id: r });
    default:
      return /* @__PURE__ */ n.jsx(V, { prefix: w, suffix: y, id: r, children: /* @__PURE__ */ n.jsx(
        m.Input,
        {
          id: r,
          "aria-invalid": i,
          "aria-describedby": f,
          className: j ? U : void 0,
          value: typeof s == "string" ? s : "",
          placeholder: T(e, "placeholder"),
          onChange: (l) => t(l.target.value)
        }
      ) });
  }
}
function _(e, s) {
  const t = e.config?.[s];
  return typeof t == "number" ? t : void 0;
}
function T(e, s) {
  const t = e.config?.[s];
  return typeof t == "string" ? t : void 0;
}
function M({ fields: e, value: s, onChange: t, errors: a, id: r, content: o, bare: c = !1 }) {
  const f = /* @__PURE__ */ n.jsx("div", { className: us(c ? void 0 : "cms:gap-4"), children: e.filter((i) => os(i, s)).map((i) => i.shared ? (
    // Greyed out, with where to edit it under it.
    /* @__PURE__ */ n.jsx("fieldset", { disabled: !0, className: `cms:flex cms:flex-col cms:gap-1 cms:opacity-60 ${X(i)}`, children: /* @__PURE__ */ n.jsx(Y, { id: `${r}-${i.handle}`, field: i, value: s[i.handle], content: o, errors: {}, onChange: () => {
    }, shared: !0 }) }, i.handle)
  ) : /* @__PURE__ */ n.jsx("div", { className: X(i), children: /* @__PURE__ */ n.jsx(
    Y,
    {
      id: `${r}-${i.handle}`,
      field: i,
      value: s[i.handle],
      content: o,
      errors: z(a, i.handle),
      onChange: (w) => t({ ...s, [i.handle]: w })
    }
  ) }, i.handle)) });
  return c ? f : /* @__PURE__ */ n.jsx("div", { className: "cms:rounded-lg cms:border cms:p-4", children: f });
}
function xs({ options: e, value: s, onChange: t, id: a, empty: r, fill: o = !1 }) {
  return /* @__PURE__ */ n.jsxs(m.Select, { value: s ?? D, onValueChange: (c) => t(c === D ? null : c), children: [
    /* @__PURE__ */ n.jsx(m.SelectTrigger, { id: a, className: o ? "cms:w-full" : "cms:w-full cms:max-w-sm", children: /* @__PURE__ */ n.jsx(m.SelectValue, {}) }),
    /* @__PURE__ */ n.jsxs(m.SelectContent, { children: [
      r && /* @__PURE__ */ n.jsx(m.SelectItem, { value: D, children: d("None") }),
      e.map((c) => /* @__PURE__ */ n.jsx(m.SelectItem, { value: c.value, children: c.label }, c.value))
    ] })
  ] });
}
function gs({ options: e, value: s, onChange: t, empty: a, inTickOrder: r = !1, invalid: o = !1 }) {
  return e.length === 0 ? /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: a ?? d("Nothing to choose from yet.") }) : /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-col cms:gap-2", children: e.map((c) => /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
    /* @__PURE__ */ n.jsx(
      m.Checkbox,
      {
        checked: s.includes(c.value),
        "aria-invalid": o || void 0,
        onCheckedChange: (f) => t(f ? r ? [...s.filter((i) => i !== c.value), c.value] : e.map((i) => i.value).filter((i) => i === c.value || s.includes(i)) : s.filter((i) => i !== c.value))
      }
    ),
    c.label,
    r && s.includes(c.value) && /* @__PURE__ */ n.jsxs("span", { className: "cms:text-xs cms:text-muted-foreground", "aria-label": `number ${s.indexOf(c.value) + 1}`, children: [
      "#",
      s.indexOf(c.value) + 1
    ] })
  ] }, c.value)) });
}
function ys(e) {
  return { url: d("Link"), entry: d("Entry"), term: d("Term"), listing: d("Listing") }[e];
}
function bs(e) {
  return e === "nofollow" ? d("nofollow: search engines don't follow the link or count it as an endorsement.") : e === "sponsored" ? d("sponsored: a paid link, an advert or a sponsorship.") : d("ugc: a link in what visitors wrote, such as comments.");
}
function js({ label: e, value: s, onChange: t, errors: a, id: r, collections: o, placeholder: c, describedBy: f }) {
  const [i, w] = $.useState(O(s.type) ?? "url"), y = O(s.type) ?? i, j = $.useRef({}), l = $.useRef(Object.keys(s).length === 0), [u, v] = $.useState(!1), g = typeof s.new_tab == "boolean" ? s.new_tab : l.current && !u && P(y), p = (b) => t(Ie({ ...s, type: y, new_tab: g, ...b })), k = (b) => {
    if (b === y) return;
    const S = Te(s, y, b, j.current);
    j.current = S.remembered, w(b), t(S.value === null || !l.current || u ? S.value : { ...S.value, new_tab: P(b) });
  }, x = ke(s.rel), h = Ce[y], N = a[h] ?? a.url ?? a.label ?? a.type ?? a.rel;
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsxs("div", { className: "cms:grid cms:gap-2 cms:md:grid-cols-[10rem_1fr] cms:md:items-start", children: [
      /* @__PURE__ */ n.jsxs(m.Select, { value: y, onValueChange: (b) => k(O(b) ?? "url"), children: [
        /* @__PURE__ */ n.jsx(m.SelectTrigger, { "aria-label": d(":field: link to", { field: e }), className: "cms:w-full", children: /* @__PURE__ */ n.jsx(m.SelectValue, {}) }),
        /* @__PURE__ */ n.jsx(m.SelectContent, { children: Ne.map((b) => /* @__PURE__ */ n.jsx(m.SelectItem, { value: b, children: ys(b) }, b)) })
      ] }),
      y === "url" && /* @__PURE__ */ n.jsx(
        m.Input,
        {
          id: r,
          "aria-label": d(":field: address", { field: e }),
          placeholder: c || d("https://… or /about"),
          "aria-describedby": f,
          value: typeof s.url == "string" ? s.url : "",
          onChange: (b) => p({ url: b.target.value }),
          "aria-invalid": a.url !== void 0
        }
      ),
      y === "entry" && /* @__PURE__ */ n.jsx(
        Z,
        {
          id: r,
          invalid: a.entry_id !== void 0,
          multiple: !1,
          max: null,
          collections: o,
          value: typeof s.entry_id == "number" ? s.entry_id : null,
          onChange: (b) => p({ entry_id: typeof b == "number" ? b : void 0 })
        }
      ),
      (y === "term" || y === "listing") && /* @__PURE__ */ n.jsx(
        Me,
        {
          id: r,
          source: y,
          label: d(":field: search", { field: e }),
          invalid: a[h] !== void 0,
          value: typeof s[h] == "number" ? s[h] : null,
          onChange: (b) => p({ [h]: b ?? void 0 })
        }
      )
    ] }),
    /* @__PURE__ */ n.jsx(
      m.Input,
      {
        "aria-label": d(":field: link text", { field: e }),
        placeholder: y === "url" ? d("Link text") : d("Link text (its title)"),
        value: typeof s.label == "string" ? s.label : "",
        onChange: (b) => p({ label: b.target.value })
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { role: "group", "aria-label": d(":field: link attributes", { field: e }), className: "cms:flex cms:flex-wrap cms:items-center cms:gap-x-4 cms:gap-y-2", children: [
      /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
        /* @__PURE__ */ n.jsx(m.Checkbox, { checked: g, onCheckedChange: (b) => {
          v(!0), p({ new_tab: b === !0 });
        } }),
        d("Open in a new tab")
      ] }),
      /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-1", children: $e.map((b) => /* @__PURE__ */ n.jsx(
        m.Button,
        {
          type: "button",
          size: "sm",
          variant: x.includes(b) ? "secondary" : "outline",
          className: "cms:h-7 cms:px-2 cms:text-xs",
          title: bs(b),
          "aria-pressed": x.includes(b),
          onClick: () => {
            const S = _e(s.rel, b);
            p({ rel: S.length === 0 ? void 0 : S });
          },
          children: b
        },
        b
      )) }),
      /* @__PURE__ */ n.jsx("p", { className: "cms:text-xs cms:text-muted-foreground", children: d("Links that open in a new tab always do so safely (noopener noreferrer).") })
    ] }),
    /* @__PURE__ */ n.jsx(L, { message: N })
  ] });
}
function J(e, s) {
  return !s && (e.fields ?? []).every((t) => t.type === "text");
}
function de(e) {
  const s = $.useRef([]);
  return s.current = Se(s.current, e), {
    keys: s.current,
    remove: (t) => {
      s.current = s.current.filter((a, r) => r !== t);
    },
    move: (t, a) => {
      s.current = m.moveItem(s.current, t, a);
    }
  };
}
function fe() {
  const [e, s] = $.useState(() => /* @__PURE__ */ new Set());
  return {
    isCollapsed: (t, a) => !a && e.has(t),
    toggle: (t) => s((a) => {
      const r = new Set(a);
      return r.delete(t) || r.add(t), r;
    }),
    setAll: (t, a) => s(a ? new Set(t) : /* @__PURE__ */ new Set()),
    anyOpen: (t) => t.some((a) => !e.has(a))
  };
}
function pe({ keys: e, folding: s }) {
  if (e.length < 2) return null;
  const t = s.anyOpen(e);
  return /* @__PURE__ */ n.jsxs(m.Button, { type: "button", variant: "ghost", size: "sm", onClick: () => s.setAll(e, t), children: [
    t ? /* @__PURE__ */ n.jsx(Re, {}) : /* @__PURE__ */ n.jsx(Ae, {}),
    " ",
    t ? d("Collapse all") : d("Expand all")
  ] });
}
function vs({ field: e, value: s, onChange: t, errors: a, id: r, content: o = !1 }) {
  const c = e.fields ?? [], f = s.map(A), i = de(f.length), w = (g, p) => t(f.map((k, x) => x === g ? p : k)), y = (g) => {
    i.remove(g), t(f.filter((p, k) => k !== g));
  }, j = e.label ? e.label.toLowerCase().replace(/s$/, "") : d("row"), l = j.charAt(0).toUpperCase() + j.slice(1), u = f.map((g, p) => ({ row: g, index: p, key: i.keys[p] ?? String(p) })), v = fe();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(
      m.SortableList,
      {
        items: u,
        getId: (g) => g.key,
        getLabel: (g) => `${j} ${g.index + 1}`,
        onReorder: (g, p, k) => {
          i.move(p, k), t(g.map((x) => x.row));
        },
        children: ({ row: g, index: p, key: k }, { handle: x }) => J(e, o) ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-start cms:gap-2", children: [
          /* @__PURE__ */ n.jsx(m.SortableHandle, { ...x, label: d("Move :item :number", { item: j, number: p + 1 }), className: "cms:mt-1" }),
          c.map((h) => /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-1 cms:flex-col cms:gap-1", children: [
            /* @__PURE__ */ n.jsx(
              m.Input,
              {
                "aria-label": h.label || h.handle,
                placeholder: h.label || h.handle,
                value: typeof g[h.handle] == "string" ? g[h.handle] : "",
                onChange: (N) => w(p, { ...g, [h.handle]: N.target.value })
              }
            ),
            /* @__PURE__ */ n.jsx(L, { message: a[`${p}.${h.handle}`] })
          ] }, h.handle)),
          /* @__PURE__ */ n.jsx(m.Button, { type: "button", variant: "ghost", size: "icon", "aria-label": d("Remove :item :number", { item: j, number: p + 1 }), onClick: () => y(p), children: /* @__PURE__ */ n.jsx(te, {}) })
        ] }) : (() => {
          const h = z(a, String(p)), N = v.isCollapsed(k, Object.keys(h).length > 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              he,
              {
                label: `${l} ${p + 1}`,
                summary: ee(c, g),
                handle: x,
                onRemove: () => y(p),
                collapsed: N,
                onToggle: () => v.toggle(k),
                controls: `${r}-${p}-fields`,
                hidden: o ? g._hidden === !0 : void 0,
                onHidden: (b) => w(p, { ...g, _hidden: b })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${p}-fields`, hidden: N, children: !N && /* @__PURE__ */ n.jsx(M, { fields: c, value: g, onChange: (b) => w(p, b), errors: h, id: `${r}-${p}`, content: o }) })
          ] });
        })()
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(m.Button, { type: "button", variant: "outline", size: "sm", onClick: () => t([...f, o ? we(e.defaults) : {}]), children: [
        /* @__PURE__ */ n.jsx(se, {}),
        " ",
        d("Add :item", { item: j })
      ] }),
      !J(e, o) && /* @__PURE__ */ n.jsx(pe, { keys: u.map((g) => g.key), folding: v })
    ] })
  ] });
}
function ws({ field: e, value: s, onChange: t, errors: a, id: r }) {
  const o = e.blocks ?? [], c = s.map(A), f = de(c.length), i = (l, u) => t(c.map((v, g) => g === l ? u : v)), w = (l) => {
    f.remove(l), t(c.filter((u, v) => v !== l));
  }, y = c.map((l, u) => ({ block: l, index: u, key: f.keys[u] ?? String(u) })), j = fe();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-3", children: [
    /* @__PURE__ */ n.jsx(
      m.SortableList,
      {
        items: y,
        getId: (l) => l.key,
        className: "cms:gap-3",
        getLabel: (l) => d(":type block", { type: o.find((u) => u.type === l.block.type)?.label ?? String(l.block.type) }),
        onReorder: (l, u, v) => {
          f.move(u, v), t(l.map((g) => g.block));
        },
        children: ({ block: l, index: u, key: v }, { handle: g }) => {
          const p = o.find((h) => h.type === l.type), k = z(a, `${u}.values`), x = j.isCollapsed(v, Object.keys(k).length > 0 || a[`${u}.type`] !== void 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              he,
              {
                label: p?.label ?? String(l.type),
                summary: p ? ee(p.fields, A(l.values)) : null,
                badge: !0,
                handle: g,
                onRemove: () => w(u),
                collapsed: x,
                onToggle: () => j.toggle(v),
                controls: `${r}-${u}-fields`,
                hidden: l.hidden === !0,
                onHidden: (h) => i(u, { ...l, hidden: h })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${u}-fields`, hidden: x, children: x ? null : p ? /* @__PURE__ */ n.jsx(
              M,
              {
                fields: p.fields,
                value: A(l.values),
                onChange: (h) => i(u, { ...l, values: h }),
                errors: k,
                id: `${r}-${u}`,
                content: !0
              }
            ) : /* @__PURE__ */ n.jsx("p", { className: "cms:rounded-lg cms:border cms:p-4 cms:text-sm cms:text-muted-foreground", children: d("This kind of block no longer exists. Its values are kept.") }) }),
            /* @__PURE__ */ n.jsx(L, { message: a[`${u}.type`] })
          ] });
        }
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(m.DropdownMenu, { children: [
        /* @__PURE__ */ n.jsx(m.DropdownMenuTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsxs(m.Button, { type: "button", variant: "outline", size: "sm", disabled: o.length === 0, children: [
          /* @__PURE__ */ n.jsx(se, {}),
          " ",
          d("Add block")
        ] }) }),
        /* @__PURE__ */ n.jsx(m.DropdownMenuContent, { align: "start", children: o.map((l) => /* @__PURE__ */ n.jsx(m.DropdownMenuItem, { onSelect: () => t([...c, ve(l.type, l.defaults)]), children: l.label }, l.type)) })
      ] }),
      /* @__PURE__ */ n.jsx(pe, { keys: y.map((l) => l.key), folding: j })
    ] })
  ] });
}
function he({ label: e, summary: s, badge: t = !1, handle: a, onRemove: r, collapsed: o, onToggle: c, controls: f, hidden: i, onHidden: w }) {
  const y = o ? Oe : De, j = s ? `${e}: ${s}` : e;
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(m.SortableHandle, { ...a, label: d("Move :title", { title: j }) }),
    /* @__PURE__ */ n.jsxs(
      "button",
      {
        type: "button",
        onClick: c,
        "aria-expanded": !o,
        "aria-controls": f,
        className: "cms:flex cms:min-w-0 cms:flex-1 cms:items-center cms:gap-2 cms:rounded-md cms:text-left cms:outline-none cms:focus-visible:ring-2 cms:focus-visible:ring-ring",
        children: [
          /* @__PURE__ */ n.jsx(y, { className: "cms:size-4 cms:shrink-0 cms:text-muted-foreground", "aria-hidden": !0 }),
          t ? /* @__PURE__ */ n.jsx(m.Badge, { variant: "secondary", children: e }) : /* @__PURE__ */ n.jsx("span", { className: "cms:shrink-0 cms:text-sm cms:font-medium", children: e }),
          s && /* @__PURE__ */ n.jsx("span", { className: "cms:truncate cms:text-sm cms:text-muted-foreground", children: s }),
          /* @__PURE__ */ n.jsx("span", { className: "cms:sr-only", children: o ? d("(collapsed)") : "" })
        ]
      }
    ),
    i !== void 0 && /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:shrink-0 cms:items-center cms:gap-2 cms:text-sm cms:text-muted-foreground", title: d("Off: kept here, but left out of the page"), children: [
      /* @__PURE__ */ n.jsx(m.Switch, { checked: !i, onCheckedChange: (l) => w(!l), "aria-label": d(":title: visible on site", { title: j }) }),
      d("Visible on site")
    ] }),
    /* @__PURE__ */ n.jsx(
      m.Button,
      {
        type: "button",
        variant: "ghost",
        size: "icon",
        className: "cms:size-8 cms:shrink-0 cms:text-destructive",
        "aria-label": d("Remove :title", { title: j }),
        onClick: r,
        children: /* @__PURE__ */ n.jsx(te, {})
      }
    )
  ] });
}
export {
  Fs as A,
  Ge as B,
  De as C,
  os as D,
  X as E,
  Y as F,
  us as G,
  Ue as H,
  ue as I,
  K as J,
  ce as K,
  Os as M,
  Us as O,
  We as S,
  qs as a,
  z as b,
  Je as c,
  Ks as d,
  ss as e,
  ms as f,
  Rs as g,
  zs as h,
  Ps as i,
  Bs as j,
  is as k,
  Ms as l,
  cs as m,
  Ds as n,
  ls as o,
  Ws as p,
  Xs as q,
  Hs as r,
  Vs as s,
  Gs as t,
  Qe as u,
  Ye as v,
  Ys as w,
  Ze as x,
  es as y,
  M as z
};
