import { j as n, t as f, r as ts, a as ns } from "./admin-Bu-2lMxB.js";
import { c as U, _ as j } from "./createLucideIcon-Ccbp7F45.js";
import { u as p } from "./ui-Dh5wfMwT.js";
import { t as rs, a as q, E as Ae, o as cs, d as ge, b as as, g as ls, i as Ce, n as os, c as is, s as ms } from "./EntriesPicker-DMlWtQQo.js";
import { F as K } from "./FieldError-BrG6Smqr.js";
import { C as us } from "./check-OeKFYRnl.js";
import { X as ds } from "./EntryStatus-BUyLyZHj.js";
import { S as fs } from "./search-3zKi7F1_.js";
import { P as Te } from "./plus-COqahUZ2.js";
import { T as Ee } from "./trash-DHN6ewjn.js";
import { C as ps } from "./chevron-right-9qVV6b-Q.js";
const Oe = {
  name: "chevron-down",
  size: 24,
  node: [["path", { d: "m6 9 6 6 6-6", key: "qrunsl" }]]
};
Oe.node;
const hs = U(Oe);
const De = {
  name: "chevrons-down-up",
  size: 24,
  node: [
    ["path", { d: "m7 20 5-5 5 5", key: "13a0gw" }],
    ["path", { d: "m7 4 5 5 5-5", key: "1kwcof" }]
  ]
};
De.node;
const xs = U(De);
const qe = {
  name: "chevrons-up-down",
  size: 24,
  node: [
    ["path", { d: "m7 15 5 5 5-5", key: "1hf1tw" }],
    ["path", { d: "m7 9 5-5 5 5", key: "sgt6xg" }]
  ]
};
qe.node;
const ze = U(qe);
const Me = {
  name: "info",
  size: 24,
  node: [
    ["circle", { cx: "12", cy: "12", r: "10", key: "1mglay" }],
    ["path", { d: "M12 16v-4", key: "1dtifu" }],
    ["path", { d: "M12 8h.01", key: "e9boi3" }]
  ]
};
Me.node;
const gs = U(Me), bs = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
function I(e) {
  return e.replace(/[&<>"']/g, (s) => bs[s] ?? s);
}
function ys(e) {
  return /[\u0000- \u007f]/.test(e) ? !1 : /^(https?:\/\/[^/]|mailto:|tel:|\/(?![/\\])|#)/i.test(e);
}
const be = new RegExp("`([^`\\n]+)`|\\[([^\\]\\n]+)\\]\\(((?:[^()\\s]|\\([^()\\s]*\\))+)\\)|\\*\\*(?=\\S)(.+?)(?<=\\S)\\*\\*|\\*(?=[^\\s*])([^*\\n]+?)(?<=\\S)\\*");
function F(e, s = !1) {
  let t = "", c = e;
  for (let r = be.exec(c); r !== null; r = be.exec(c)) {
    t += I(c.slice(0, r.index));
    const [l, o, h, i, y, u] = r;
    o !== void 0 ? t += `<code>${I(o)}</code>` : h !== void 0 && i !== void 0 ? t += s || !ys(i) ? F(h, s) : `<a href="${I(i)}" target="_blank" rel="noopener noreferrer">${F(h, !0)}</a>` : y !== void 0 ? t += `<strong>${F(y, s)}</strong>` : u !== void 0 ? t += `<em>${F(u, s)}</em>` : t += I(l), c = c.slice(r.index + l.length);
  }
  return t + I(c);
}
const vs = ["text", "number"], js = ["group", "repeater", "flexible"], ws = ["placeholder", "prefix", "suffix"];
function V(e) {
  return typeof e == "string" && e.trim() !== "" ? e.trim() : null;
}
function Re(e) {
  return { hint: V(e.hint), tooltip: V(e.hint_tooltip) };
}
function He(e) {
  return vs.includes(e.type) ? { prefix: V(e.config?.prefix), suffix: V(e.config?.suffix) } : { prefix: null, suffix: null };
}
function ye({ field: e, id: s, label: t, maxWidth: c = "cms:max-w-[60%]" }) {
  const { hint: r, tooltip: l } = Re(e);
  return r === null && l === null ? null : /* @__PURE__ */ n.jsxs("span", { className: `cms:ml-auto cms:flex ${c} cms:min-w-0 cms:shrink-0 cms:items-center cms:gap-1 cms:text-xs cms:leading-none cms:text-muted-foreground`, children: [
    r !== null && /* @__PURE__ */ n.jsx("span", { id: `${s}-hint`, className: "cms:min-w-0 cms:truncate cms:py-0.5", title: r, children: r }),
    l !== null && /* @__PURE__ */ n.jsxs(p.Tooltip, { children: [
      /* @__PURE__ */ n.jsx(p.TooltipTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsx(
        "button",
        {
          type: "button",
          "aria-label": f("More about :field", { field: t }),
          className: "cms:-m-1 cms:inline-flex cms:shrink-0 cms:rounded-full cms:p-1 cms:text-muted-foreground cms:outline-none cms:hover:text-foreground cms:focus-visible:ring-2 cms:focus-visible:ring-ring",
          children: /* @__PURE__ */ n.jsx(gs, { className: "cms:size-3.5", "aria-hidden": !0 })
        }
      ) }),
      /* @__PURE__ */ n.jsx(p.TooltipContent, { className: "cms:max-w-xs cms:text-left cms:whitespace-pre-line", children: l })
    ] })
  ] });
}
function ks({ text: e, id: s, className: t = "" }) {
  return !e || e.trim() === "" ? null : /* @__PURE__ */ n.jsx(
    "p",
    {
      id: s,
      className: `cms:text-sm cms:text-muted-foreground cms:[overflow-wrap:anywhere] cms:[&_a]:font-medium cms:[&_a]:text-foreground cms:[&_a]:underline cms:[&_a]:underline-offset-2 cms:[&_a:hover]:text-primary cms:[&_code]:rounded cms:[&_code]:bg-muted cms:[&_code]:px-1 cms:[&_code]:font-mono cms:[&_code]:text-[0.8125rem] cms:[&_strong]:font-semibold cms:[&_strong]:text-foreground ${t}`.trim(),
      dangerouslySetInnerHTML: { __html: F(e) }
    }
  );
}
function ve({ prefix: e, suffix: s, id: t, className: c = "", children: r }) {
  if (e === null && s === null) return /* @__PURE__ */ n.jsx(n.Fragment, { children: r });
  const l = "cms:flex cms:shrink-0 cms:items-center cms:bg-muted/60 cms:px-3 cms:text-sm cms:whitespace-nowrap cms:text-muted-foreground cms:select-none";
  return /* @__PURE__ */ n.jsxs("div", { "data-slot": "input-group", className: `cms:flex cms:h-10 cms:w-full cms:min-w-0 cms:items-stretch cms:overflow-hidden cms:rounded-md cms:border cms:border-input/80 cms:bg-card cms:shadow-xs cms:transition-[border-color,box-shadow] cms:duration-150 cms:hover:border-ring/45 cms:focus-within:border-ring/70 cms:focus-within:ring-4 cms:focus-within:ring-ring-glow cms:has-[[aria-invalid=true]]:border-destructive cms:has-[[aria-invalid=true]]:ring-destructive/20 cms:has-disabled:cursor-not-allowed cms:has-disabled:opacity-50 cms:has-[[readonly]]:bg-muted/30 cms:dark:bg-input/20 ${c}`.trim(), children: [
    e !== null && /* @__PURE__ */ n.jsx("span", { id: `${t}-prefix`, className: `${l} cms:border-r cms:border-input/80`, children: e }),
    r,
    s !== null && /* @__PURE__ */ n.jsx("span", { id: `${t}-suffix`, className: `${l} cms:border-l cms:border-input/80`, children: s })
  ] });
}
const je = "cms:h-full cms:min-w-0 cms:flex-1 cms:rounded-none cms:border-0 cms:bg-transparent cms:shadow-none cms:hover:border-0 cms:focus-visible:ring-0 cms:aria-invalid:ring-0 cms:disabled:opacity-100 cms:dark:bg-transparent";
function Ns(e) {
  return e === null ? [] : Array.isArray(e) ? e : [e];
}
function Ie(e, s, t) {
  return s && t !== null && e.length >= t;
}
function $s(e, s, t, c) {
  return t ? e.includes(s) ? e.filter((r) => r !== s) : Ie(e, t, c) ? e.slice() : [...e, s] : [s];
}
function _s(e) {
  return e.slice(0, -1);
}
function Ss(e, s) {
  return s ? e.slice() : e[0] ?? null;
}
function As(e, s, t, c = () => !1) {
  const r = s === "ArrowDown" || s === "Home" ? 1 : s === "ArrowUp" || s === "End" ? -1 : 0;
  if (r === 0 || t === 0) return e;
  let l = s === "Home" ? 0 : s === "End" ? t - 1 : e + r;
  for (s === "ArrowUp" && e === -1 && (l = t - 1); l >= 0 && l < t && c(l); ) l += r;
  return l >= 0 && l < t ? l : e;
}
function Cs(e, s) {
  const t = new Set(e.map((c) => c.id));
  return [...e, ...s.filter((c) => !t.has(c.id))];
}
function Ts(e) {
  const s = [], t = {};
  for (const c of e)
    s.length = Math.min(s.length, c.depth), t[c.id] = s.slice(), s.push(c.label);
  return t;
}
function we(e, s) {
  return [...s ?? [], e].join(" › ");
}
const Es = j.createContext({});
function Os(e) {
  const s = e.termParents;
  return s && typeof s == "object" && !Array.isArray(s) ? s : {};
}
async function re(e, s) {
  const t = await fetch(`${ns("cms/terms/picker")}?${rs(e)}`, { headers: { Accept: "application/json" }, credentials: "same-origin", signal: s });
  if (!t.ok) throw new Error(String(t.status));
  return await t.json();
}
function Ds({ id: e, taxonomy: s, multiple: t, max: c, required: r, invalid: l, value: o, onChange: h }) {
  const i = j.useContext(Es), y = Os(ts.usePage().props), u = Ns(o), [g, m] = j.useState(!1), [x, k] = j.useState(""), [d, b] = j.useState(null), [N, _] = j.useState(!1), [w, C] = j.useState(!1), [z, We] = j.useState(0), [G, Xe] = j.useState({}), [S, W] = j.useState(-1), [X, Ye] = j.useState(null), M = j.useRef(null), Y = j.useRef(null), me = j.useRef(null), ue = j.useRef(null), J = x.trim(), R = j.useCallback((a, v) => {
    const $ = v ? Ts(a) : {};
    Xe((A) => {
      const B = { ...A };
      for (const D of a) B[D.id] = { label: D.label, parents: $[D.id] ?? D.parents ?? A[D.id]?.parents };
      return B;
    });
  }, []), T = g && s ? J : null;
  j.useEffect(() => {
    if (T === null || s === null || ue.current === `${s}
${T}`) return;
    const a = new AbortController(), v = window.setTimeout(() => {
      re({ taxonomy: s, q: T }, a.signal).then(($) => {
        ue.current = `${s}
${T}`, b({ ...$, q: T }), R($.items, T === ""), W(T !== "" && $.items.length > 0 ? 0 : -1), C(!1);
      }).catch(($) => {
        $ instanceof DOMException && $.name === "AbortError" || C(!0);
      });
    }, T === "" ? 0 : 250);
    return () => {
      window.clearTimeout(v), a.abort();
    };
  }, [s, T, z, R]);
  const de = (a) => i[String(a)] ?? G[a]?.label, Q = u.filter((a) => de(a) === void 0).join(",");
  j.useEffect(() => {
    !s || Q === "" || re({ taxonomy: s, ids: Q.split(",").map(Number) }).then((a) => R(a.items, !1)).catch(() => {
    });
  }, [s, Q, R]);
  const Z = () => {
    !s || !d?.more || N || d.q !== J || (_(!0), re({ taxonomy: s, q: d.q, offset: d.items.length }).then((a) => {
      const v = Cs(d.items, a.items);
      b({ items: v, more: a.more, q: d.q }), R(v, d.q === "");
    }).catch(() => C(!0)).finally(() => _(!1)));
  };
  if (j.useEffect(() => {
    S >= 0 && d && document.getElementById(`${e}-option-${d.items[S]?.id}`)?.scrollIntoView({ block: "nearest" });
  }, [S, e, d]), !s) return /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: f("Choose a taxonomy for this field in its blueprint.") });
  const fe = Ie(u, t, c), ee = t || !r, se = (a) => h(Ss(a, t)), pe = (a) => {
    se($s(u, a, t, c)), t || m(!1);
  }, O = d?.items ?? [], te = (a) => fe && !u.includes(O[a]?.id ?? -1), ne = (a) => de(a) ?? f("Term #:id", { id: a }), L = j.useCallback(() => {
    if (!M.current || !Y.current) return;
    const a = M.current.getBoundingClientRect(), v = Y.current.getBoundingClientRect(), $ = { width: a.width, alignOffset: a.left - v.left, sideOffset: 4 + a.bottom - v.bottom };
    Ye((A) => A && A.width === $.width && A.alignOffset === $.alignOffset && A.sideOffset === $.sideOffset ? A : $);
  }, []), Je = u.join(",");
  j.useLayoutEffect(() => {
    if (!g || (L(), typeof ResizeObserver > "u" || !M.current)) return;
    const a = new ResizeObserver(L);
    return a.observe(M.current), () => a.disconnect();
  }, [g, Je, L]);
  const Qe = (a) => {
    a && L(), a || k(""), m(a);
  }, Ze = (a) => {
    if (a.key === "ArrowDown" || a.key === "ArrowUp") {
      a.preventDefault();
      const v = As(S, a.key, O.length, te);
      a.key === "ArrowDown" && v === S && S === O.length - 1 && Z(), W(v);
    } else if (a.key === "Enter") {
      a.preventDefault();
      const v = O[S];
      v && !te(S) && pe(v.id);
    } else a.key === "Backspace" && x === "" && ee && u.length > 0 && (a.preventDefault(), se(_s(u)));
  }, es = (a) => {
    const v = a.currentTarget;
    v.scrollTop + v.clientHeight >= v.scrollHeight - 48 && Z();
  }, ss = t ? f("Choose terms…") : f("Choose a term…"), he = `${e}-listbox`, xe = d !== null && d.q !== J;
  let H;
  return w ? H = /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:items-start cms:gap-2 cms:p-3 cms:text-sm", children: [
    /* @__PURE__ */ n.jsx("p", { className: "cms:text-destructive", children: f("Terms could not be loaded.") }),
    /* @__PURE__ */ n.jsx(p.Button, { type: "button", variant: "outline", size: "sm", onClick: () => {
      C(!1), We((a) => a + 1);
    }, children: f("Try again") })
  ] }) : d === null ? H = /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-col cms:gap-2 cms:p-2", "aria-busy": "true", "aria-label": f("Loading terms"), children: [70, 55, 80, 45, 65].map((a) => /* @__PURE__ */ n.jsx(p.Skeleton, { className: "cms:h-5", style: { width: `${a}%` } }, a)) }) : O.length === 0 ? H = /* @__PURE__ */ n.jsx("p", { className: "cms:p-3 cms:text-sm cms:text-muted-foreground", children: d.q ? f("No terms match “:search”.", { search: d.q }) : f("Nothing to choose from yet.") }) : H = /* @__PURE__ */ n.jsxs("div", { className: `cms:max-h-72 cms:overflow-y-auto cms:overscroll-contain cms:p-1 ${xe ? "cms:opacity-60" : ""}`, onScroll: es, children: [
    /* @__PURE__ */ n.jsx("div", { role: "listbox", id: he, "aria-label": f("Terms"), "aria-multiselectable": t || void 0, "aria-busy": xe || void 0, children: O.map((a, v) => {
      const $ = u.includes(a.id), A = te(v), B = we(a.label, G[a.id]?.parents);
      return /* @__PURE__ */ n.jsxs(
        "div",
        {
          id: `${e}-option-${a.id}`,
          role: "option",
          "aria-selected": $,
          "aria-disabled": A || void 0,
          title: B,
          className: `cms:flex cms:cursor-default cms:items-center cms:gap-2 cms:rounded-sm cms:py-1.5 cms:pr-2 cms:text-sm cms:select-none ${v === S ? "cms:bg-accent cms:text-accent-foreground" : ""} ${A ? "cms:opacity-50" : ""}`,
          style: { paddingLeft: `${0.5 + Math.min(a.depth, 6) * 0.875}rem` },
          onMouseDown: (D) => D.preventDefault(),
          onMouseMove: () => {
            v !== S && W(v);
          },
          onClick: () => {
            A || pe(a.id);
          },
          children: [
            /* @__PURE__ */ n.jsx(us, { className: `cms:size-4 cms:shrink-0 ${$ ? "" : "cms:invisible"}`, "aria-hidden": "true" }),
            /* @__PURE__ */ n.jsx("span", { className: "cms:min-w-0 cms:truncate", children: a.label })
          ]
        },
        a.id
      );
    }) }),
    d.more && /* @__PURE__ */ n.jsx(p.Button, { type: "button", variant: "ghost", size: "sm", className: "cms:mt-1 cms:w-full", disabled: N, onMouseDown: (a) => a.preventDefault(), onClick: Z, children: N ? f("Loading…") : f("Load more") })
  ] }), /* @__PURE__ */ n.jsxs(p.Popover, { open: g, onOpenChange: Qe, children: [
    /* @__PURE__ */ n.jsxs(
      "div",
      {
        ref: M,
        "data-invalid": l || void 0,
        className: "cms:flex cms:w-full cms:max-w-xl cms:min-w-0 cms:[contain:inline-size] cms:flex-wrap cms:items-center cms:gap-1 cms:rounded-md cms:border cms:border-input/80 cms:bg-card cms:p-1 cms:shadow-xs cms:data-invalid:border-destructive cms:dark:bg-input/20",
        children: [
          u.map((a) => /* @__PURE__ */ n.jsxs(
            "span",
            {
              title: we(ne(a), G[a]?.parents ?? y[String(a)]),
              className: "cms:inline-flex cms:h-7 cms:max-w-full cms:min-w-0 cms:items-center cms:gap-0.5 cms:rounded-sm cms:bg-secondary cms:pl-2 cms:text-xs cms:font-medium cms:text-secondary-foreground",
              children: [
                /* @__PURE__ */ n.jsx("span", { className: `cms:min-w-0 cms:truncate ${ee ? "" : "cms:pr-2"}`, children: ne(a) }),
                ee && /* @__PURE__ */ n.jsx(
                  "button",
                  {
                    type: "button",
                    className: "cms:inline-flex cms:size-7 cms:shrink-0 cms:items-center cms:justify-center cms:rounded-sm cms:text-muted-foreground cms:hover:bg-muted cms:hover:text-foreground cms:focus-visible:ring-2 cms:focus-visible:ring-ring cms:focus-visible:outline-none",
                    "aria-label": f("Remove :term", { term: ne(a) }),
                    onClick: () => se(u.filter((v) => v !== a)),
                    children: /* @__PURE__ */ n.jsx(ds, { className: "cms:size-3", "aria-hidden": "true" })
                  }
                )
              ]
            },
            a
          )),
          /* @__PURE__ */ n.jsx(p.PopoverTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsxs(
            "button",
            {
              ref: Y,
              id: e,
              type: "button",
              "aria-haspopup": "listbox",
              "aria-invalid": l || void 0,
              className: "cms:flex cms:h-7 cms:min-w-24 cms:flex-1 cms:items-center cms:justify-between cms:gap-1 cms:rounded-sm cms:px-2 cms:text-left cms:text-sm cms:text-muted-foreground cms:hover:bg-muted cms:focus-visible:ring-2 cms:focus-visible:ring-ring cms:focus-visible:outline-none cms:disabled:pointer-events-none cms:disabled:opacity-50",
              children: [
                /* @__PURE__ */ n.jsx("span", { className: "cms:truncate", children: u.length === 0 ? ss : t ? f("Add…") : f("Change…") }),
                /* @__PURE__ */ n.jsx(ze, { className: "cms:size-4 cms:shrink-0 cms:opacity-60", "aria-hidden": "true" })
              ]
            }
          ) })
        ]
      }
    ),
    /* @__PURE__ */ n.jsxs(
      p.PopoverContent,
      {
        align: "start",
        alignOffset: X?.alignOffset,
        sideOffset: X?.sideOffset,
        className: "cms:max-w-[calc(100vw-2rem)] cms:min-w-60 cms:p-0",
        style: { width: X?.width },
        onOpenAutoFocus: (a) => {
          a.preventDefault(), me.current?.focus();
        },
        children: [
          /* @__PURE__ */ n.jsxs("div", { className: "cms:relative cms:border-b cms:border-border/60", children: [
            /* @__PURE__ */ n.jsx(fs, { className: "cms:pointer-events-none cms:absolute cms:top-1/2 cms:left-3 cms:size-4 cms:-translate-y-1/2 cms:text-muted-foreground", "aria-hidden": "true" }),
            /* @__PURE__ */ n.jsx(
              "input",
              {
                ref: me,
                type: "text",
                role: "combobox",
                "aria-expanded": "true",
                "aria-controls": he,
                "aria-autocomplete": "list",
                "aria-label": f("Search terms"),
                "aria-activedescendant": S >= 0 && O[S] ? `${e}-option-${O[S].id}` : void 0,
                className: "cms:h-10 cms:w-full cms:bg-transparent cms:pr-3 cms:pl-9 cms:text-sm cms:outline-none cms:placeholder:text-muted-foreground",
                placeholder: f("Search terms"),
                autoComplete: "off",
                value: x,
                onKeyDown: Ze,
                onChange: (a) => k(a.target.value)
              }
            )
          ] }),
          H,
          fe && c !== null && /* @__PURE__ */ n.jsx("p", { className: "cms:border-t cms:border-border/60 cms:px-3 cms:py-2 cms:text-xs cms:text-muted-foreground", children: f("Up to :max terms.", { max: c }) })
        ]
      }
    )
  ] });
}
const Pe = (e, s = {}) => e.replace(/:([A-Za-z_]+)/g, (t, c) => s[c] ?? t), gt = 5;
let qs = 0;
const le = () => `f${++qs}`;
function zs(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").replace(/^[0-9_]+/, "").slice(0, 64);
}
function Ms(e) {
  return e.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^[0-9_]+/, "").replace(/_{2,}/g, "_").slice(0, 64);
}
function Fe(e, s) {
  const t = new Set(s.map((l) => l.handle)), c = zs(e) || "field";
  let r = c;
  for (let l = 2; t.has(r); l++)
    r = `${c}_${l}`;
  return r;
}
function Rs(e) {
  return (e ?? []).map(({ fields: s, ...t }) => ({
    ...t,
    key: le(),
    fields: Rs(s),
    handleTouched: !0,
    savedHandle: t.handle
  }));
}
function Hs(e, s) {
  return e.map(({ key: t, handleTouched: c, savedHandle: r, fields: l, ...o }) => ({
    ...o,
    validation: (o.validation ?? []).map((h) => h.trim()).filter((h) => h !== ""),
    ...s(o.type) ? { fields: Hs(l, s) } : {}
  }));
}
function bt(e, s, t) {
  return { key: le(), type: e, label: s, handle: Fe(s, t), required: !1, config: {}, fields: [], handleTouched: !1 };
}
function yt(e) {
  return e.type !== "fieldset" && e.savedHandle !== void 0 && e.savedHandle !== "" && e.handle !== e.savedHandle ? e.savedHandle : null;
}
function Is(e) {
  return e.map((s) => ({ ...s, savedHandle: s.handle, fields: Is(s.fields) }));
}
function vt(e, s, t) {
  if (e.handleTouched) return { ...e, label: s };
  const c = t.filter((r) => r.key !== e.key);
  return { ...e, label: s, handle: Fe(s || e.type, c) };
}
function Ps(e, s) {
  const t = `${s}.`, c = {};
  for (const [r, l] of Object.entries(e))
    r.startsWith(t) && (c[r.slice(t.length)] = l);
  return c;
}
function oe(e, s) {
  const t = e[s];
  return { ...Ps(e, s), ...t !== void 0 ? { "": t } : {} };
}
function jt(e, s) {
  return Object.keys(e).filter((t) => t === s || t.startsWith(`${s}.`));
}
function wt(e, s) {
  return Object.keys(e).some((t) => t === s || t.startsWith(`${s}.`));
}
function kt(e, s) {
  return s.filter((t) => t.key !== e.key && t.handle !== "" && t.type !== "fieldset");
}
function Nt(e, s, t, c = Pe) {
  return e.type === "fieldset" ? null : t?.includes(e.handle) ? c('":handle" is used by the content itself; choose another handle.', { handle: e.handle }) : e.handle !== "" && s.some((r) => r.key !== e.key && r.type !== "fieldset" && r.handle === e.handle) ? c('There is already a field ":handle" here.', { handle: e.handle }) : null;
}
function $t(e, s) {
  return typeof e.translatable == "boolean" ? e.translatable : s;
}
function _t(e, s, t) {
  return { ...e, translatable: s === t ? null : s };
}
function Fs(e, s, t = []) {
  const c = /* @__PURE__ */ new Set([...s.map((o) => o.handle), ...t]), r = `${(Ms(e) || "field").slice(0, 56)}_copy`;
  let l = r;
  for (let o = 2; c.has(l); o++)
    l = `${r}_${o}`;
  return l;
}
function Le(e) {
  const { key: s, savedHandle: t, fields: c, ...r } = e;
  return { ...structuredClone(r), key: le(), fields: c.map(Le) };
}
function St(e, s, t = [], c = Pe) {
  const r = e[s];
  if (r === void 0) return null;
  const l = r.label || r.handle, o = {
    ...Le(r),
    label: c(":label (copy)", { label: l }),
    handle: r.type === "fieldset" ? r.handle : Fs(r.handle, e, t),
    handleTouched: !0
  };
  return { fields: [...e.slice(0, s + 1), o, ...e.slice(s + 1)], copy: o };
}
function Ls(e, s) {
  const t = e.condition;
  return !t || "rules" in t ? null : (t.operator === "filled" || t.operator === "equals" && (t.value === !0 || t.value === "true")) && s.some((r) => r.handle === t.field && r.type === "toggle") ? t.field : null;
}
function At(e) {
  const s = (o) => o.type === "number" || o.type === "text" && (o.instructions ?? "").length <= 60, t = (o) => o.type === "text" && ws.includes(o.handle), c = {}, r = [];
  for (const o of e) {
    const h = Ls(o, e);
    h === null ? r.push(o) : (c[h] ??= []).push(o);
  }
  const l = r.filter((o) => !t(o));
  return { options: l.filter((o) => !s(o)), limits: l.filter(s), appearance: r.filter(t), dependents: c };
}
const Bs = ["html", "css", "javascript", "json", "plain"];
function Vs(e) {
  const s = e.config?.language;
  return typeof s == "string" && Bs.includes(s) ? s : "plain";
}
const Ct = [
  { value: "equals", label: "is", needsValue: !0 },
  { value: "not_equals", label: "is not", needsValue: !0 },
  { value: "filled", label: "is filled in", needsValue: !1 },
  { value: "empty", label: "is empty", needsValue: !1 }
];
function ke(e) {
  return e === !0 ? "true" : e === !1 ? "false" : typeof e == "string" || typeof e == "number" ? String(e) : "";
}
function Us(e) {
  return e ? "rules" in e ? Array.isArray(e.rules) ? e.rules : [] : [e] : [];
}
function Ks(e) {
  return e && "rules" in e && e.match === "any" ? "any" : "all";
}
function Tt(e, s) {
  return e.length === 0 ? null : e.length === 1 ? e[0] : { match: s, rules: e };
}
function Ne(e, s) {
  const t = s[e.field], c = t != null && t !== "" && t !== !1 && !(Array.isArray(t) && t.length === 0), r = (Array.isArray(t) ? t : [t]).map(ke).includes(ke(e.value));
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
function Gs(e, s) {
  const t = Us(e.condition);
  return t.length === 0 ? !0 : Ks(e.condition) === "any" ? t.some((c) => Ne(c, s)) : t.every((c) => Ne(c, s));
}
function Et(e) {
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
function Ot(e) {
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
const Ws = [25, 33, 50, 66, 75, 100];
function Xs(e) {
  return Ws.find((s) => s === e.width) ?? 100;
}
function Ys(e = "cms:gap-x-4 cms:gap-y-6") {
  return `cms:@container cms:grid cms:grid-cols-12 cms:items-start ${e}`;
}
const Js = {
  25: "cms:@lg:col-span-3",
  33: "cms:@lg:col-span-4",
  50: "cms:@lg:col-span-6",
  66: "cms:@lg:col-span-8",
  75: "cms:@lg:col-span-9",
  100: ""
};
function $e(e) {
  return `cms:col-span-12 cms:min-w-0 ${Js[Xs(e)]}`.trim();
}
function Dt(e, s) {
  const { width: t, ...c } = e;
  return s === 100 ? c : { ...c, width: s };
}
const ce = "__none";
function ae(e) {
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
const Qs = ["repeater", "group", "flexible", "link", "media", "rich_text", "entries"];
function Be(e) {
  if (e.label) return e.label;
  const s = e.handle.replace(/_/g, " ");
  return s.charAt(0).toUpperCase() + s.slice(1);
}
function _e({ field: e, value: s, onChange: t, errors: c, id: r, content: l = !1, fill: o = !1, marker: h = null }) {
  const i = Be(e), y = Qs.includes(e.type) || ["select", "terms"].includes(e.type) && e.config?.multiple === !0, u = c[""] !== void 0, g = js.includes(e.type), m = e.instructions && e.instructions.trim() !== "" ? /* @__PURE__ */ n.jsx(ks, { id: `${r}-help`, text: e.instructions }) : null, { hint: x } = Re(e), { prefix: k, suffix: d } = He(e), b = [x && `${r}-hint`, k && `${r}-prefix`, d && `${r}-suffix`, m && `${r}-help`, u && `${r}-error`].filter(Boolean).join(" ") || void 0;
  return /* @__PURE__ */ n.jsxs(
    "div",
    {
      className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-2",
      ...y ? { role: "group", "aria-labelledby": `${r}-label`, ...u ? { "aria-invalid": !0 } : {}, ...u || m ? { "aria-describedby": [m && `${r}-help`, u && `${r}-error`].filter(Boolean).join(" ") } : {} } : {},
      children: [
        e.type === "toggle" ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-3", children: [
          /* @__PURE__ */ n.jsx(p.Switch, { id: r, checked: s === !0, "aria-describedby": b, onCheckedChange: (N) => t(N) }),
          /* @__PURE__ */ n.jsx(p.Label, { htmlFor: r, className: "cms:min-w-0", children: i }),
          h,
          /* @__PURE__ */ n.jsx(ye, { field: e, id: r, label: i })
        ] }) : /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:min-h-4 cms:min-w-0 cms:items-center cms:gap-2", children: [
          /* @__PURE__ */ n.jsxs(p.Label, { id: `${r}-label`, htmlFor: y ? void 0 : r, className: "cms:min-w-0", children: [
            i,
            e.required && /* @__PURE__ */ n.jsx("span", { className: "cms:text-destructive", children: " *" }),
            h
          ] }),
          /* @__PURE__ */ n.jsx(ye, { field: e, id: r, label: i })
        ] }),
        g && m,
        /* @__PURE__ */ n.jsx(et, { field: e, value: s, onChange: t, errors: c, id: r, content: l, fill: o, describedBy: b }),
        ["text", "textarea", "code"].includes(e.type) && E(e, "max") !== void 0 && /* @__PURE__ */ n.jsx(Zs, { length: typeof s == "string" ? [...s].length : 0, max: E(e, "max") ?? 0 }),
        !g && m,
        /* @__PURE__ */ n.jsx(K, { message: c[""], id: `${r}-error` })
      ]
    }
  );
}
function Zs({ length: e, max: s }) {
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
function et({ field: e, value: s, onChange: t, errors: c, id: r, content: l, fill: o = !1, describedBy: h }) {
  const i = c[""] !== void 0, { prefix: y, suffix: u } = He(e), g = y !== null || u !== null;
  switch (e.type) {
    case "toggle":
      return null;
    case "file":
      return /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: f("Visitors attach a file here when they send a form.") });
    case "textarea":
      return /* @__PURE__ */ n.jsx(
        p.Textarea,
        {
          id: r,
          "aria-invalid": i,
          "aria-describedby": h,
          value: typeof s == "string" ? s : "",
          rows: E(e, "rows") ?? 4,
          placeholder: P(e, "placeholder"),
          onChange: (m) => t(m.target.value)
        }
      );
    case "code":
      return /* @__PURE__ */ n.jsx(
        p.CodeEditor,
        {
          id: r,
          "aria-labelledby": `${r}-label`,
          "aria-describedby": h,
          invalid: i,
          language: Vs(e),
          value: typeof s == "string" ? s : "",
          placeholder: P(e, "placeholder"),
          onChange: (m) => t(m)
        }
      );
    case "rich_text":
      return /* @__PURE__ */ n.jsx(p.RichTextEditor, { id: r, labelledBy: `${r}-label`, invalid: i, value: typeof s == "string" ? s : "", onChange: t });
    case "number": {
      const m = ls(s);
      return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ n.jsx(ve, { prefix: y, suffix: u, id: r, className: o ? "" : "cms:max-w-64", children: /* @__PURE__ */ n.jsx(
          p.Input,
          {
            id: r,
            type: "number",
            className: g ? je : o ? "cms:w-full" : "cms:max-w-48",
            placeholder: P(e, "placeholder"),
            "aria-invalid": i,
            "aria-describedby": [h, m && `${r}-grouped`].filter(Boolean).join(" ") || void 0,
            value: typeof s == "number" ? String(s) : typeof s == "string" ? s : "",
            min: E(e, "min"),
            max: E(e, "max"),
            step: E(e, "step") ?? "any",
            onChange: (x) => t(x.target.value === "" ? null : Number(x.target.value))
          }
        ) }),
        m && /* @__PURE__ */ n.jsx("span", { id: `${r}-grouped`, className: "cms:text-sm cms:tabular-nums cms:text-muted-foreground", children: m })
      ] });
    }
    case "date": {
      const m = e.config?.time === !0;
      return /* @__PURE__ */ n.jsx(
        p.DatePicker,
        {
          id: r,
          withTime: m,
          invalid: i,
          required: e.required === !0,
          value: ge(as(s, m)),
          onChange: (x) => t(ge(x ?? ""))
        }
      );
    }
    case "select":
      return e.config?.multiple === !0 ? /* @__PURE__ */ n.jsx(
        tt,
        {
          options: ae(e),
          value: Array.isArray(s) ? s.map(String) : [],
          onChange: t,
          invalid: i,
          empty: typeof e.config?.empty == "string" ? e.config.empty : void 0,
          inTickOrder: e.config?.in_tick_order === !0
        }
      ) : /* @__PURE__ */ n.jsx(
        st,
        {
          options: ae(e),
          value: typeof s == "string" ? s : null,
          onChange: t,
          id: r,
          fill: o,
          empty: cs(ae(e), e.required === !0, typeof e.config?.default == "string" && e.config.default !== "", typeof s == "string" && s !== "")
        }
      );
    case "terms": {
      const m = e.config?.multiple === !0, x = Array.isArray(s) ? s.filter((k) => typeof k == "number") : typeof s == "number" ? s : null;
      return /* @__PURE__ */ n.jsx(
        Ds,
        {
          id: r,
          taxonomy: typeof e.taxonomy == "string" ? e.taxonomy : null,
          multiple: m,
          max: E(e, "max") ?? null,
          required: e.required === !0,
          invalid: i,
          value: m ? Array.isArray(x) ? x : [] : typeof x == "number" ? x : null,
          onChange: t
        }
      );
    }
    case "entries":
      return /* @__PURE__ */ n.jsx(
        Ae,
        {
          id: r,
          invalid: i,
          multiple: e.config?.multiple === !0,
          max: E(e, "max") ?? null,
          collections: e.collections ?? [],
          onChange: t,
          value: typeof s == "number" || Array.isArray(s) && s.every((m) => typeof m == "number") ? s : null
        }
      );
    case "link":
      return /* @__PURE__ */ n.jsx(
        nt,
        {
          label: Be(e),
          value: q(s),
          onChange: t,
          errors: c,
          id: r,
          collections: e.collections ?? [],
          placeholder: P(e, "placeholder"),
          describedBy: h
        }
      );
    case "media":
      return /* @__PURE__ */ n.jsx(
        p.MediaPickerInput,
        {
          id: r,
          invalid: i,
          disabled: !1,
          multiple: e.config?.multiple === !0,
          max: E(e, "max") ?? null,
          types: Array.isArray(e.config?.types) ? e.config.types : [],
          value: typeof s == "number" || Array.isArray(s) && s.every((m) => typeof m == "number") ? s : null,
          onChange: t
        }
      );
    case "group":
      return /* @__PURE__ */ n.jsx(ie, { fields: e.fields ?? [], value: q(s), onChange: t, errors: c, id: r, content: l });
    case "repeater":
      return /* @__PURE__ */ n.jsx(rt, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: c, id: r, content: l });
    case "flexible":
      return /* @__PURE__ */ n.jsx(ct, { field: e, value: Array.isArray(s) ? s : [], onChange: t, errors: c, id: r });
    default:
      return /* @__PURE__ */ n.jsx(ve, { prefix: y, suffix: u, id: r, children: /* @__PURE__ */ n.jsx(
        p.Input,
        {
          id: r,
          "aria-invalid": i,
          "aria-describedby": h,
          className: g ? je : void 0,
          value: typeof s == "string" ? s : "",
          placeholder: P(e, "placeholder"),
          onChange: (m) => t(m.target.value)
        }
      ) });
  }
}
function E(e, s) {
  const t = e.config?.[s];
  return typeof t == "number" ? t : void 0;
}
function P(e, s) {
  const t = e.config?.[s];
  return typeof t == "string" ? t : void 0;
}
function ie({ fields: e, value: s, onChange: t, errors: c, id: r, content: l, bare: o = !1 }) {
  const h = /* @__PURE__ */ n.jsx("div", { className: Ys(o ? void 0 : "cms:gap-4"), children: e.filter((i) => Gs(i, s)).map((i) => i.shared ? (
    // Greyed out; the form says once, at the top, where to edit shared fields.
    /* @__PURE__ */ n.jsx("fieldset", { disabled: !0, className: `cms:flex cms:flex-col cms:gap-1 cms:opacity-60 ${$e(i)}`, title: f("Shared by all languages: edit it in the main language."), children: /* @__PURE__ */ n.jsx(_e, { id: `${r}-${i.handle}`, field: i, value: s[i.handle], content: l, errors: {}, onChange: () => {
    } }) }, i.handle)
  ) : /* @__PURE__ */ n.jsx("div", { className: $e(i), children: /* @__PURE__ */ n.jsx(
    _e,
    {
      id: `${r}-${i.handle}`,
      field: i,
      value: s[i.handle],
      content: l,
      errors: oe(c, i.handle),
      onChange: (y) => t({ ...s, [i.handle]: y })
    }
  ) }, i.handle)) });
  return o ? h : /* @__PURE__ */ n.jsx("div", { className: "cms:rounded-lg cms:border cms:p-4", children: h });
}
function st({ options: e, value: s, onChange: t, id: c, empty: r, fill: l = !1 }) {
  return /* @__PURE__ */ n.jsxs(p.Select, { value: s ?? ce, onValueChange: (o) => t(o === ce ? null : o), children: [
    /* @__PURE__ */ n.jsx(p.SelectTrigger, { id: c, className: l ? "cms:w-full" : "cms:w-full cms:max-w-sm", children: /* @__PURE__ */ n.jsx(p.SelectValue, {}) }),
    /* @__PURE__ */ n.jsxs(p.SelectContent, { children: [
      r && /* @__PURE__ */ n.jsx(p.SelectItem, { value: ce, children: f("None") }),
      e.map((o) => /* @__PURE__ */ n.jsx(p.SelectItem, { value: o.value, children: o.label }, o.value))
    ] })
  ] });
}
function tt({ options: e, value: s, onChange: t, empty: c, inTickOrder: r = !1, invalid: l = !1 }) {
  return e.length === 0 ? /* @__PURE__ */ n.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: c ?? f("Nothing to choose from yet.") }) : /* @__PURE__ */ n.jsx("div", { className: "cms:flex cms:flex-col cms:gap-2", children: e.map((o) => /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
    /* @__PURE__ */ n.jsx(
      p.Checkbox,
      {
        checked: s.includes(o.value),
        "aria-invalid": l || void 0,
        onCheckedChange: (h) => t(h ? r ? [...s.filter((i) => i !== o.value), o.value] : e.map((i) => i.value).filter((i) => i === o.value || s.includes(i)) : s.filter((i) => i !== o.value))
      }
    ),
    o.label,
    r && s.includes(o.value) && /* @__PURE__ */ n.jsxs("span", { className: "cms:text-xs cms:text-muted-foreground", "aria-label": `number ${s.indexOf(o.value) + 1}`, children: [
      "#",
      s.indexOf(o.value) + 1
    ] })
  ] }, o.value)) });
}
function nt({ label: e, value: s, onChange: t, errors: c, id: r, collections: l, placeholder: o, describedBy: h }) {
  const i = s.type === "entry" ? "entry" : "url", y = (u) => {
    const g = { ...s, type: i, ...u }, m = g.type === "entry" ? typeof g.entry_id != "number" : g.url === "" || g.url === void 0 || g.url === null;
    t(m ? g.label || g.new_tab ? g : null : g);
  };
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsx("div", { role: "radiogroup", "aria-label": f(":field: link to", { field: e }), className: "cms:inline-flex cms:w-fit cms:rounded-md cms:border cms:p-0.5", children: ["url", "entry"].map((u) => /* @__PURE__ */ n.jsx(
      p.Button,
      {
        type: "button",
        size: "sm",
        role: "radio",
        "aria-checked": i === u,
        variant: i === u ? "secondary" : "ghost",
        onClick: () => y({ type: u, url: u === "url" ? s.url : void 0, entry_id: u === "entry" ? s.entry_id : void 0 }),
        children: u === "url" ? f("Address") : f("Entry")
      },
      u
    )) }),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:grid cms:gap-2 cms:md:grid-cols-[2fr_1fr_auto] cms:md:items-start", children: [
      i === "url" ? /* @__PURE__ */ n.jsx(
        p.Input,
        {
          id: r,
          "aria-label": f(":field: address", { field: e }),
          placeholder: o || f("https://… or /about"),
          "aria-describedby": h,
          value: typeof s.url == "string" ? s.url : "",
          onChange: (u) => y({ url: u.target.value }),
          "aria-invalid": c.url !== void 0
        }
      ) : /* @__PURE__ */ n.jsx(
        Ae,
        {
          id: r,
          invalid: c.entry_id !== void 0,
          multiple: !1,
          max: null,
          collections: l,
          value: typeof s.entry_id == "number" ? s.entry_id : null,
          onChange: (u) => y({ entry_id: typeof u == "number" ? u : void 0 })
        }
      ),
      /* @__PURE__ */ n.jsx(
        p.Input,
        {
          "aria-label": f(":field: link text", { field: e }),
          placeholder: i === "entry" ? f("Link text (the entry's title)") : f("Link text"),
          value: typeof s.label == "string" ? s.label : "",
          onChange: (u) => y({ label: u.target.value })
        }
      ),
      /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:h-9 cms:items-center cms:gap-2 cms:text-sm", children: [
        /* @__PURE__ */ n.jsx(p.Checkbox, { checked: s.new_tab === !0, onCheckedChange: (u) => y({ new_tab: u }) }),
        f("New tab")
      ] }),
      /* @__PURE__ */ n.jsx("div", { className: "cms:md:col-span-3", children: /* @__PURE__ */ n.jsx(K, { message: c.url ?? c.entry_id ?? c.label ?? c.type }) })
    ] })
  ] });
}
function Se(e, s) {
  return !s && (e.fields ?? []).every((t) => t.type === "text");
}
function Ve(e) {
  const s = j.useRef([]);
  return s.current = ms(s.current, e), {
    keys: s.current,
    remove: (t) => {
      s.current = s.current.filter((c, r) => r !== t);
    },
    move: (t, c) => {
      s.current = p.moveItem(s.current, t, c);
    }
  };
}
function Ue() {
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
function Ke({ keys: e, folding: s }) {
  if (e.length < 2) return null;
  const t = s.anyOpen(e);
  return /* @__PURE__ */ n.jsxs(p.Button, { type: "button", variant: "ghost", size: "sm", onClick: () => s.setAll(e, t), children: [
    t ? /* @__PURE__ */ n.jsx(xs, {}) : /* @__PURE__ */ n.jsx(ze, {}),
    " ",
    t ? f("Collapse all") : f("Expand all")
  ] });
}
function rt({ field: e, value: s, onChange: t, errors: c, id: r, content: l = !1 }) {
  const o = e.fields ?? [], h = s.map(q), i = Ve(h.length), y = (d, b) => t(h.map((N, _) => _ === d ? b : N)), u = (d) => {
    i.remove(d), t(h.filter((b, N) => N !== d));
  }, g = e.label ? e.label.toLowerCase().replace(/s$/, "") : f("row"), m = g.charAt(0).toUpperCase() + g.slice(1), x = h.map((d, b) => ({ row: d, index: b, key: i.keys[b] ?? String(b) })), k = Ue();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(
      p.SortableList,
      {
        items: x,
        getId: (d) => d.key,
        getLabel: (d) => `${g} ${d.index + 1}`,
        onReorder: (d, b, N) => {
          i.move(b, N), t(d.map((_) => _.row));
        },
        children: ({ row: d, index: b, key: N }, { handle: _ }) => Se(e, l) ? /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-start cms:gap-2", children: [
          /* @__PURE__ */ n.jsx(p.SortableHandle, { ..._, label: f("Move :item :number", { item: g, number: b + 1 }), className: "cms:mt-1" }),
          o.map((w) => /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-1 cms:flex-col cms:gap-1", children: [
            /* @__PURE__ */ n.jsx(
              p.Input,
              {
                "aria-label": w.label || w.handle,
                placeholder: w.label || w.handle,
                value: typeof d[w.handle] == "string" ? d[w.handle] : "",
                onChange: (C) => y(b, { ...d, [w.handle]: C.target.value })
              }
            ),
            /* @__PURE__ */ n.jsx(K, { message: c[`${b}.${w.handle}`] })
          ] }, w.handle)),
          /* @__PURE__ */ n.jsx(p.Button, { type: "button", variant: "ghost", size: "icon", "aria-label": f("Remove :item :number", { item: g, number: b + 1 }), onClick: () => u(b), children: /* @__PURE__ */ n.jsx(Ee, {}) })
        ] }) : (() => {
          const w = oe(c, String(b)), C = k.isCollapsed(N, Object.keys(w).length > 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              Ge,
              {
                label: `${m} ${b + 1}`,
                summary: Ce(o, d),
                handle: _,
                onRemove: () => u(b),
                collapsed: C,
                onToggle: () => k.toggle(N),
                controls: `${r}-${b}-fields`,
                hidden: l ? d._hidden === !0 : void 0,
                onHidden: (z) => y(b, { ...d, _hidden: z })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${b}-fields`, hidden: C, children: !C && /* @__PURE__ */ n.jsx(ie, { fields: o, value: d, onChange: (z) => y(b, z), errors: w, id: `${r}-${b}`, content: l }) })
          ] });
        })()
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(p.Button, { type: "button", variant: "outline", size: "sm", onClick: () => t([...h, l ? is() : {}]), children: [
        /* @__PURE__ */ n.jsx(Te, {}),
        " ",
        f("Add :item", { item: g })
      ] }),
      !Se(e, l) && /* @__PURE__ */ n.jsx(Ke, { keys: x.map((d) => d.key), folding: k })
    ] })
  ] });
}
function ct({ field: e, value: s, onChange: t, errors: c, id: r }) {
  const l = e.blocks ?? [], o = s.map(q), h = Ve(o.length), i = (m, x) => t(o.map((k, d) => d === m ? x : k)), y = (m) => {
    h.remove(m), t(o.filter((x, k) => k !== m));
  }, u = o.map((m, x) => ({ block: m, index: x, key: h.keys[x] ?? String(x) })), g = Ue();
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-3", children: [
    /* @__PURE__ */ n.jsx(
      p.SortableList,
      {
        items: u,
        getId: (m) => m.key,
        className: "cms:gap-3",
        getLabel: (m) => f(":type block", { type: l.find((x) => x.type === m.block.type)?.label ?? String(m.block.type) }),
        onReorder: (m, x, k) => {
          h.move(x, k), t(m.map((d) => d.block));
        },
        children: ({ block: m, index: x, key: k }, { handle: d }) => {
          const b = l.find((w) => w.type === m.type), N = oe(c, `${x}.values`), _ = g.isCollapsed(k, Object.keys(N).length > 0 || c[`${x}.type`] !== void 0);
          return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ n.jsx(
              Ge,
              {
                label: b?.label ?? String(m.type),
                summary: b ? Ce(b.fields, q(m.values)) : null,
                badge: !0,
                handle: d,
                onRemove: () => y(x),
                collapsed: _,
                onToggle: () => g.toggle(k),
                controls: `${r}-${x}-fields`,
                hidden: m.hidden === !0,
                onHidden: (w) => i(x, { ...m, hidden: w })
              }
            ),
            /* @__PURE__ */ n.jsx("div", { id: `${r}-${x}-fields`, hidden: _, children: _ ? null : b ? /* @__PURE__ */ n.jsx(
              ie,
              {
                fields: b.fields,
                value: q(m.values),
                onChange: (w) => i(x, { ...m, values: w }),
                errors: N,
                id: `${r}-${x}`,
                content: !0
              }
            ) : /* @__PURE__ */ n.jsx("p", { className: "cms:rounded-lg cms:border cms:p-4 cms:text-sm cms:text-muted-foreground", children: f("This kind of block no longer exists. Its values are kept.") }) }),
            /* @__PURE__ */ n.jsx(K, { message: c[`${x}.type`] })
          ] });
        }
      }
    ),
    /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
      /* @__PURE__ */ n.jsxs(p.DropdownMenu, { children: [
        /* @__PURE__ */ n.jsx(p.DropdownMenuTrigger, { asChild: !0, children: /* @__PURE__ */ n.jsxs(p.Button, { type: "button", variant: "outline", size: "sm", disabled: l.length === 0, children: [
          /* @__PURE__ */ n.jsx(Te, {}),
          " ",
          f("Add block")
        ] }) }),
        /* @__PURE__ */ n.jsx(p.DropdownMenuContent, { align: "start", children: l.map((m) => /* @__PURE__ */ n.jsx(p.DropdownMenuItem, { onSelect: () => t([...o, os(m.type)]), children: m.label }, m.type)) })
      ] }),
      /* @__PURE__ */ n.jsx(Ke, { keys: u.map((m) => m.key), folding: g })
    ] })
  ] });
}
function Ge({ label: e, summary: s, badge: t = !1, handle: c, onRemove: r, collapsed: l, onToggle: o, controls: h, hidden: i, onHidden: y }) {
  const u = l ? ps : hs, g = s ? `${e}: ${s}` : e;
  return /* @__PURE__ */ n.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
    /* @__PURE__ */ n.jsx(p.SortableHandle, { ...c, label: f("Move :title", { title: g }) }),
    /* @__PURE__ */ n.jsxs(
      "button",
      {
        type: "button",
        onClick: o,
        "aria-expanded": !l,
        "aria-controls": h,
        className: "cms:flex cms:min-w-0 cms:flex-1 cms:items-center cms:gap-2 cms:rounded-md cms:text-left cms:outline-none cms:focus-visible:ring-2 cms:focus-visible:ring-ring",
        children: [
          /* @__PURE__ */ n.jsx(u, { className: "cms:size-4 cms:shrink-0 cms:text-muted-foreground", "aria-hidden": !0 }),
          t ? /* @__PURE__ */ n.jsx(p.Badge, { variant: "secondary", children: e }) : /* @__PURE__ */ n.jsx("span", { className: "cms:shrink-0 cms:text-sm cms:font-medium", children: e }),
          s && /* @__PURE__ */ n.jsx("span", { className: "cms:truncate cms:text-sm cms:text-muted-foreground", children: s }),
          /* @__PURE__ */ n.jsx("span", { className: "cms:sr-only", children: l ? f("(collapsed)") : "" })
        ]
      }
    ),
    i !== void 0 && /* @__PURE__ */ n.jsxs("label", { className: "cms:flex cms:shrink-0 cms:items-center cms:gap-2 cms:text-sm cms:text-muted-foreground", title: f("Off: kept here, but left out of the page"), children: [
      /* @__PURE__ */ n.jsx(p.Switch, { checked: !i, onCheckedChange: (m) => y(!m), "aria-label": f(":title: visible on site", { title: g }) }),
      f("Visible on site")
    ] }),
    /* @__PURE__ */ n.jsx(
      p.Button,
      {
        type: "button",
        variant: "ghost",
        size: "icon",
        className: "cms:size-8 cms:shrink-0 cms:text-destructive",
        "aria-label": f("Remove :title", { title: g }),
        onClick: r,
        children: /* @__PURE__ */ n.jsx(Ee, {})
      }
    )
  ] });
}
export {
  ie as A,
  jt as B,
  hs as C,
  Gs as D,
  $e as E,
  _e as F,
  Ys as G,
  ze as H,
  ks as I,
  Be as J,
  ye as K,
  Re as L,
  gt as M,
  Ct as O,
  Ds as T,
  Nt as a,
  oe as b,
  Ms as c,
  St as d,
  Ps as e,
  Xs as f,
  yt as g,
  wt as h,
  $t as i,
  _t as j,
  Ws as k,
  kt as l,
  Us as m,
  bt as n,
  Ks as o,
  Et as p,
  Ot as q,
  vt as r,
  At as s,
  Tt as t,
  Rs as u,
  zs as v,
  Dt as w,
  Hs as x,
  Is as y,
  Es as z
};
