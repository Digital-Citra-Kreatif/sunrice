import { j as s } from "./admin-lZSvxQGo.js";
import { u as o } from "./ui-BzV6lfEf.js";
import { D as A, B as S, F as b, E as x, b as y, G as C, H as w, S as E, z as H, I as p, h as D, J as G, K as I } from "./FieldInput-V6aURv0P.js";
import { C as $ } from "./TermsPicker-ByRlyGhB.js";
import { F as L } from "./FieldError-CF4lpTrm.js";
const O = (m) => {
  const { hint: l, tooltip: d } = I(m);
  return l !== null || d !== null;
}, T = "cms:flex cms:flex-col cms:gap-1 cms:opacity-60";
function B({ fields: m, values: l, onChange: d, errors: t, storageKey: c, shared: r = [], sharedValues: i = {}, mainLanguage: N = null, disabled: g = !1, changed: j = [] }) {
  const u = [], v = { ...i, ...l };
  for (const a of m.filter((n) => A(n, v))) {
    const n = u[u.length - 1];
    a.type === "group" ? u.push(a) : Array.isArray(n) ? n.push(a) : u.push([a]);
  }
  return u.length === 0 ? null : /* @__PURE__ */ s.jsx(S.Provider, { value: N, children: /* @__PURE__ */ s.jsx(o.CollapsibleCardGroup, { className: "cms:gap-6", children: /* @__PURE__ */ s.jsx("div", { className: C("cms:gap-6"), children: u.map((a) => {
    if (Array.isArray(a))
      return /* @__PURE__ */ s.jsx(o.Card, { className: "cms:col-span-12 cms:min-w-0", children: /* @__PURE__ */ s.jsx(o.CardContent, { children: /* @__PURE__ */ s.jsx("fieldset", { disabled: g, className: "cms:min-w-0", children: /* @__PURE__ */ s.jsx("div", { className: C(), children: a.map((e) => r.includes(e.handle) ? /* @__PURE__ */ s.jsx("fieldset", { disabled: !0, className: `${T} ${x(e)}`, children: /* @__PURE__ */ s.jsx(b, { id: `data-${e.handle}`, field: e, content: !0, value: i[e.handle], errors: {}, onChange: () => {
      }, shared: !0 }) }, e.handle) : /* @__PURE__ */ s.jsx("div", { className: x(e), children: /* @__PURE__ */ s.jsx(
        b,
        {
          id: `data-${e.handle}`,
          field: e,
          content: !0,
          value: l[e.handle],
          errors: y(t, `data.${e.handle}`),
          onChange: (F) => d(e.handle, F),
          marker: j.includes(e.handle) ? /* @__PURE__ */ s.jsx($, {}) : null
        }
      ) }, e.handle)) }) }) }) }, `fields-${a[0]?.handle ?? ""}`);
    const n = r.includes(a.handle), h = (n ? i : l)[a.handle], f = y(t, `data.${a.handle}`);
    return /* @__PURE__ */ s.jsx(
      o.CollapsibleCard,
      {
        className: x(a),
        summary: o.cardSummary([h]) || void 0,
        title: j.includes(a.handle) ? /* @__PURE__ */ s.jsxs(s.Fragment, { children: [
          p(a),
          /* @__PURE__ */ s.jsx($, {})
        ] }) : p(a),
        titleClassName: "cms:text-base",
        headerAction: O(a) ? /* @__PURE__ */ s.jsx(G, { field: a, id: `data-${a.handle}`, label: p(a), maxWidth: "cms:max-w-40 cms:sm:max-w-64" }) : void 0,
        storageKey: `${c}:${a.handle}`,
        hasErrors: !n && D(t, `data.${a.handle}`),
        children: /* @__PURE__ */ s.jsxs("fieldset", { disabled: n || g, role: "group", "aria-label": p(a), className: `cms:flex cms:flex-col cms:gap-2 ${n ? "cms:opacity-60" : ""}`, children: [
          /* @__PURE__ */ s.jsx(w, { text: a.instructions, className: "cms:-mt-1 cms:mb-2" }),
          n && /* @__PURE__ */ s.jsx(E, { className: "cms:-mt-1 cms:mb-2" }),
          /* @__PURE__ */ s.jsx(
            H,
            {
              bare: !0,
              fields: a.fields ?? [],
              id: `data-${a.handle}`,
              content: !0,
              errors: n ? {} : f,
              value: h && typeof h == "object" && !Array.isArray(h) ? h : {},
              onChange: (e) => d(a.handle, e)
            }
          ),
          /* @__PURE__ */ s.jsx(L, { message: n ? void 0 : f[""] })
        ] })
      },
      a.handle
    );
  }) }) }) });
}
function J(m, l, d) {
  return m.map((t, c) => ({
    code: t,
    name: d(t),
    main: c === 0,
    missing: l !== null && !l.includes(t)
  }));
}
function P(m, l, d, t, c) {
  return m.filter((r) => r.status !== null || l).map((r) => {
    const i = { code: r.code, name: r.name ?? c(r.code), main: r.main };
    return r.status === null ? { ...i, missing: !0 } : r.outdated ? { ...i, status: { tone: "warning", label: d("Outdated: the main language changed since") }, description: t(r.status) } : r.status === "published" ? { ...i, status: { tone: "success", label: t(r.status) } } : { ...i, description: t(r.status) };
  });
}
export {
  B as F,
  T as S,
  P as e,
  J as l
};
