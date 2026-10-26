import { j as s, r as h, t as n, l as R } from "./admin-BDMoHrGt.js";
import { u as r } from "./ui-Dh5wfMwT.js";
import { D as _, F as $, E as F, b as w, G as K, A as U, h as W, B as q } from "./FieldInput-B5kPekdf.js";
import { S as z } from "./FieldSections-C4bGq3lh.js";
import { V as Q } from "./VersionConflict-B-nZ2Yu5.js";
import { L as X } from "./LanguageTabs-DyssaraR.js";
import { T as Y, w as Z, s as ee } from "./seo-DPQiJEh6.js";
import { h as se } from "./EntriesPicker-xK76PsPz.js";
import { E as ae } from "./external-link-CFxEu4M4.js";
import { L as te } from "./loader-circle-ByksABXO.js";
function ge(t) {
  return /* @__PURE__ */ s.jsx(re, { ...t }, `${t.editUrl}:${JSON.stringify(t.params ?? {})}:${t.locale}:${t.version}:${JSON.stringify([t.values, t.seo])}`);
}
function re({
  title: t,
  breadcrumbs: D,
  back: L,
  saveUrl: x,
  editUrl: A,
  params: d = {},
  viewUrl: g,
  empty: V,
  seoDescription: T,
  storageKey: k,
  menu: B,
  children: P,
  fields: c,
  locale: p,
  locales: j,
  translated: H = [],
  shared: f,
  values: m,
  seo: O,
  version: G,
  can: u,
  template: b,
  templates: C,
  templateChoices: I,
  templateHelp: J
}) {
  r.useBreadcrumbs(D);
  const v = C !== void 0, N = u.update && u.template === !0, a = h.useForm({
    locale: p,
    data: m,
    seo: O ?? {},
    version: G,
    ...v && N ? { template: b ?? "" } : {}
  }), l = a.errors, S = (e) => {
    const i = q(l, e);
    i.length > 0 && a.clearErrors(...i);
  }, y = j[0], o = u.update, E = () => {
    a.processing || (a.transform((e) => ({ ...e, ...d })), a.put(x, { preserveScroll: !0 }));
  }, M = () => {
    a.transform((e) => ({ ...e, ...d, version: null })), a.put(x, { preserveScroll: !0 });
  };
  return r.useUnsavedChanges(a.isDirty, o ? E : void 0), /* @__PURE__ */ s.jsxs("form", { noValidate: !0, onSubmit: (e) => {
    e.preventDefault(), E();
  }, className: "cms:flex cms:max-w-5xl cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ s.jsx(h.Head, { title: t }),
    /* @__PURE__ */ s.jsx(
      r.PageHeader,
      {
        title: t,
        back: L,
        menu: B,
        actions: /* @__PURE__ */ s.jsxs(s.Fragment, { children: [
          g && /* @__PURE__ */ s.jsx(r.Button, { type: "button", variant: "ghost", asChild: !0, children: /* @__PURE__ */ s.jsxs("a", { href: g, target: "_blank", rel: "noopener noreferrer", children: [
            /* @__PURE__ */ s.jsx(ae, {}),
            " ",
            n("View")
          ] }) }),
          o && /* @__PURE__ */ s.jsxs(r.Button, { type: "submit", disabled: a.processing, children: [
            a.processing && /* @__PURE__ */ s.jsx(te, { className: "cms:animate-spin" }),
            " ",
            n("Save")
          ] })
        ] }),
        children: a.isDirty && /* @__PURE__ */ s.jsx("span", { className: "cms:text-sm cms:text-muted-foreground", children: n("Unsaved changes") })
      }
    ),
    P,
    /* @__PURE__ */ s.jsx(X, { current: p, locales: j, translated: H, onSwitch: (e) => h.router.get(A, { ...d, locale: e }) }),
    /* @__PURE__ */ s.jsxs("fieldset", { disabled: !o, className: "cms:flex cms:flex-col cms:gap-6", children: [
      /* @__PURE__ */ s.jsx(Q, { message: l.version, processing: a.processing, onSaveMine: M }),
      c.length === 0 ? /* @__PURE__ */ s.jsx("p", { className: "cms:text-muted-foreground", children: V }) : /* @__PURE__ */ s.jsx(r.Card, { children: /* @__PURE__ */ s.jsxs(r.CardContent, { className: "cms:flex cms:flex-col cms:gap-6", children: [
        (f.length > 0 || se(c)) && /* @__PURE__ */ s.jsx("p", { role: "note", className: "cms:rounded-lg cms:border cms:border-dashed cms:px-3 cms:py-2 cms:text-sm cms:text-muted-foreground", children: y ? n("Shared by all languages: the greyed-out fields are edited in :language.", { language: R(y) }) : n("Shared by all languages: the greyed-out fields are edited in the main language.") }),
        /* @__PURE__ */ s.jsx("div", { className: K(), children: c.filter((e) => _(e, { ...m, ...a.data.data })).map((e) => f.includes(e.handle) ? /* @__PURE__ */ s.jsx("fieldset", { disabled: !0, className: `${z} ${F(e)}`, title: n("Shared by all languages"), children: /* @__PURE__ */ s.jsx($, { id: `data-${e.handle}`, field: e, content: !0, value: m[e.handle], errors: {}, onChange: () => {
        } }) }, e.handle) : /* @__PURE__ */ s.jsx("div", { className: F(e), children: /* @__PURE__ */ s.jsx(
          $,
          {
            id: `data-${e.handle}`,
            field: e,
            content: !0,
            value: a.data.data[e.handle],
            errors: w(l, `data.${e.handle}`),
            onChange: (i) => {
              a.setData("data", { ...a.data.data, [e.handle]: i }), S(`data.${e.handle}`);
            }
          }
        ) }, e.handle)) })
      ] }) })
    ] }),
    v && /* @__PURE__ */ s.jsx(r.Card, { children: /* @__PURE__ */ s.jsx(r.CardContent, { className: "cms:max-w-md", children: /* @__PURE__ */ s.jsx(
      Y,
      {
        id: "template",
        value: a.data.template ?? b ?? "",
        lookup: Z(C ?? null, I),
        onChange: (e) => {
          a.setData("template", e), a.clearErrors("template");
        },
        example: "cms.landing",
        automatic: J ?? n("Automatic uses the first file that exists."),
        error: l.template,
        disabled: !N
      }
    ) }) }),
    /* @__PURE__ */ s.jsx(r.CollapsibleCard, { title: n("SEO"), description: T, titleClassName: "cms:text-base", storageKey: k, hasErrors: W(l, "seo"), children: /* @__PURE__ */ s.jsx("fieldset", { disabled: !o, className: "cms:min-w-0", children: /* @__PURE__ */ s.jsx(U, { bare: !0, id: "seo", fields: ee(), value: a.data.seo, errors: w(l, "seo"), onChange: (e) => {
      a.setData("seo", e), S("seo");
    } }) }) })
  ] });
}
export {
  ge as P
};
