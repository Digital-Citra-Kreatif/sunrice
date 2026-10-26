import { j as e, r as x, t as r, l as R } from "./admin-7c56ii_N.js";
import { u as l } from "./ui-Dh5wfMwT.js";
import { D as K, F as w, E as D, b as L, G as U, A as W, h as q, B as z } from "./FieldInput-LlfKtaf1.js";
import { S as Q } from "./FieldSections-CngIpqMM.js";
import { V as X } from "./VersionConflict-0jel093h.js";
import { L as Y } from "./LanguageTabs-CgR4VxT-.js";
import { s as Z, T as ee, w as se } from "./seo-DLMYf7wv.js";
import { h as ae } from "./EntriesPicker-jSYb1-ZS.js";
import { E as te } from "./external-link-CFxEu4M4.js";
import { L as re } from "./loader-circle-ByksABXO.js";
function pe(t) {
  return /* @__PURE__ */ e.jsx(le, { ...t }, `${t.editUrl}:${JSON.stringify(t.params ?? {})}:${t.locale}:${t.version}:${JSON.stringify([t.values, t.seo])}`);
}
function le({
  title: t,
  breadcrumbs: A,
  back: V,
  saveUrl: g,
  editUrl: P,
  params: c = {},
  viewUrl: p,
  empty: T,
  seoDescription: k,
  storageKey: f,
  menu: B,
  children: H,
  fields: m,
  locale: j,
  locales: b,
  translated: O = [],
  shared: v,
  values: d,
  seo: _,
  version: G,
  can: h,
  template: C,
  templates: N,
  templateChoices: I,
  templateHelp: J
}) {
  l.useBreadcrumbs(A);
  const u = N !== void 0, S = h.update && h.template === !0, a = x.useForm({
    locale: j,
    data: d,
    seo: _ ?? {},
    version: G,
    ...u && S ? { template: C ?? "" } : {}
  }), n = a.errors, y = (s) => {
    const i = z(n, s);
    i.length > 0 && a.clearErrors(...i);
  }, E = b[0], o = h.update, $ = () => {
    a.processing || (a.transform((s) => ({ ...s, ...c })), a.put(g, { preserveScroll: !0 }));
  }, M = () => {
    a.transform((s) => ({ ...s, ...c, version: null })), a.put(g, { preserveScroll: !0 });
  };
  l.useUnsavedChanges(a.isDirty, o ? $ : void 0);
  const F = /* @__PURE__ */ e.jsxs(e.Fragment, { children: [
    H,
    /* @__PURE__ */ e.jsx(Y, { current: j, locales: b, translated: O, onSwitch: (s) => x.router.get(P, { ...c, locale: s }) }),
    /* @__PURE__ */ e.jsxs("fieldset", { disabled: !o, className: "cms:flex cms:flex-col cms:gap-6", children: [
      /* @__PURE__ */ e.jsx(X, { message: n.version, processing: a.processing, onSaveMine: M }),
      m.length === 0 ? /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: T }) : /* @__PURE__ */ e.jsx(l.Card, { children: /* @__PURE__ */ e.jsxs(l.CardContent, { className: "cms:flex cms:flex-col cms:gap-6", children: [
        (v.length > 0 || ae(m)) && /* @__PURE__ */ e.jsx("p", { role: "note", className: "cms:rounded-lg cms:border cms:border-dashed cms:px-3 cms:py-2 cms:text-sm cms:text-muted-foreground", children: E ? r("Shared by all languages: the greyed-out fields are edited in :language.", { language: R(E) }) : r("Shared by all languages: the greyed-out fields are edited in the main language.") }),
        /* @__PURE__ */ e.jsx("div", { className: U(), children: m.filter((s) => K(s, { ...d, ...a.data.data })).map((s) => v.includes(s.handle) ? /* @__PURE__ */ e.jsx("fieldset", { disabled: !0, className: `${Q} ${D(s)}`, title: r("Shared by all languages"), children: /* @__PURE__ */ e.jsx(w, { id: `data-${s.handle}`, field: s, content: !0, value: d[s.handle], errors: {}, onChange: () => {
        } }) }, s.handle) : /* @__PURE__ */ e.jsx("div", { className: D(s), children: /* @__PURE__ */ e.jsx(
          w,
          {
            id: `data-${s.handle}`,
            field: s,
            content: !0,
            value: a.data.data[s.handle],
            errors: L(n, `data.${s.handle}`),
            onChange: (i) => {
              a.setData("data", { ...a.data.data, [s.handle]: i }), y(`data.${s.handle}`);
            }
          }
        ) }, s.handle)) })
      ] }) })
    ] }),
    /* @__PURE__ */ e.jsx(l.CollapsibleCard, { title: r("SEO"), description: k, titleClassName: "cms:text-base", storageKey: f, hasErrors: q(n, "seo"), children: /* @__PURE__ */ e.jsx("fieldset", { disabled: !o, className: "cms:min-w-0", children: /* @__PURE__ */ e.jsx(W, { bare: !0, id: "seo", fields: Z(), value: a.data.seo, errors: L(n, "seo"), onChange: (s) => {
      a.setData("seo", s), y("seo");
    } }) }) })
  ] });
  return /* @__PURE__ */ e.jsxs("form", { noValidate: !0, onSubmit: (s) => {
    s.preventDefault(), $();
  }, className: `cms:flex cms:flex-col cms:gap-6 ${u ? "" : "cms:max-w-5xl"}`, children: [
    /* @__PURE__ */ e.jsx(x.Head, { title: t }),
    /* @__PURE__ */ e.jsx(
      l.PageHeader,
      {
        title: t,
        back: V,
        menu: B,
        actions: /* @__PURE__ */ e.jsxs(e.Fragment, { children: [
          p && /* @__PURE__ */ e.jsx(l.Button, { type: "button", variant: "ghost", asChild: !0, children: /* @__PURE__ */ e.jsxs("a", { href: p, target: "_blank", rel: "noopener noreferrer", children: [
            /* @__PURE__ */ e.jsx(te, {}),
            " ",
            r("View")
          ] }) }),
          o && /* @__PURE__ */ e.jsxs(l.Button, { type: "submit", disabled: a.processing, children: [
            a.processing && /* @__PURE__ */ e.jsx(re, { className: "cms:animate-spin" }),
            " ",
            r("Save")
          ] })
        ] }),
        children: a.isDirty && /* @__PURE__ */ e.jsx("span", { className: "cms:text-sm cms:text-muted-foreground", children: r("Unsaved changes") })
      }
    ),
    u ? (
      // Like the entry and term editors: the page's texts, and its settings beside them.
      /* @__PURE__ */ e.jsxs("div", { className: "cms:grid cms:items-start cms:gap-6 cms:lg:grid-cols-[minmax(0,1fr)_20rem]", children: [
        /* @__PURE__ */ e.jsx("div", { className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-6", children: F }),
        /* @__PURE__ */ e.jsx("aside", { className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-6", children: /* @__PURE__ */ e.jsx(
          l.CollapsibleCard,
          {
            title: r("Page settings"),
            titleClassName: "cms:text-base",
            storageKey: `${f}:page-settings`,
            hasErrors: n.template !== void 0,
            contentClassName: "cms:flex cms:flex-col cms:gap-2",
            children: /* @__PURE__ */ e.jsx(
              ee,
              {
                id: "template",
                value: a.data.template ?? C ?? "",
                lookup: se(N ?? null, I),
                onChange: (s) => {
                  a.setData("template", s), a.clearErrors("template");
                },
                example: "cms.landing",
                automatic: J ?? r("Automatic uses the first file that exists."),
                error: n.template,
                disabled: !S
              }
            )
          }
        ) })
      ] })
    ) : F
  ] });
}
export {
  pe as P
};
