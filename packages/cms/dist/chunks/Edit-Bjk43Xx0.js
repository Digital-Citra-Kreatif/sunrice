import { j as e, a as u, t as l, r as p, l as m } from "./admin-GWMPPoIx.js";
import { u as r } from "./ui-Dh5wfMwT.js";
import { l as D, F as E } from "./languages-CVtOxb13.js";
import { V as K } from "./VersionConflict-DjcLfVMF.js";
import { B as V } from "./FieldInput-CxUFdGRl.js";
import { L as $ } from "./loader-circle-ByksABXO.js";
import { S as B } from "./settings-2-0VUe76YZ.js";
import { L as C } from "./layout-template-RwJXNfEY.js";
function J(t) {
  return /* @__PURE__ */ e.jsx(O, { ...t }, `${t.locale}:${t.version}:${JSON.stringify(t.values)}`);
}
function O({ set: t, fields: f, locale: c, locales: b, shared: S, values: x, version: v, can: j, settings: y = null, blueprint: k = null }) {
  const g = u(`cms/globals/${t.handle}/values`);
  r.useBreadcrumbs([{ label: l("Globals"), href: u("cms/globals") }, { label: t.title }]);
  const a = p.useForm({ locale: c, data: x, version: v }), h = a.errors, w = (s) => {
    const n = V(h, s);
    n.length > 0 && a.clearErrors(...n);
  }, i = b[0], o = r.useEditLock({
    type: "cms.global",
    id: t.id,
    locale: c,
    enabled: j.update,
    noun: l("global set"),
    leaveTo: u("cms/globals"),
    getUnsaved: () => a.isDirty ? { data: a.data.data } : null
  }), d = j.update && !o.readOnly, N = () => a.processing || a.put(g, { preserveScroll: !0 }), L = () => {
    a.transform((s) => ({ ...s, version: null })), a.put(g, { preserveScroll: !0 });
  }, T = async (s) => {
    const n = await o.loadKept(s);
    n.data && typeof n.data == "object" && a.setData("data", n.data);
  };
  return r.useUnsavedChanges(a.isDirty && !o.takenOver, d ? N : void 0), /* @__PURE__ */ e.jsxs("form", { noValidate: !0, onSubmit: (s) => {
    s.preventDefault(), N();
  }, className: "cms:flex cms:max-w-5xl cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(p.Head, { title: t.title }),
    o.dialogs,
    /* @__PURE__ */ e.jsx(
      r.PageHeader,
      {
        title: t.title,
        menu: [
          ...y ? [{ label: l("Settings"), href: y, icon: /* @__PURE__ */ e.jsx(B, {}) }] : [],
          ...k ? [{ label: l("Edit blueprint"), href: k, icon: /* @__PURE__ */ e.jsx(C, {}) }] : []
        ],
        back: { href: u("cms/globals"), label: l("Back to globals") },
        actions: /* @__PURE__ */ e.jsx(e.Fragment, { children: d && /* @__PURE__ */ e.jsxs(r.Button, { type: "submit", disabled: a.processing, children: [
          a.processing && /* @__PURE__ */ e.jsx($, { className: "cms:animate-spin" }),
          " ",
          l("Save")
        ] }) }),
        children: a.isDirty && /* @__PURE__ */ e.jsx("span", { className: "cms:text-sm cms:text-muted-foreground", children: l("Unsaved changes") })
      }
    ),
    /* @__PURE__ */ e.jsx(r.LanguageTabs, { value: c, languages: D(b, null, m), onValueChange: (s) => p.router.get(g, { locale: s }) }),
    f.length === 0 ? /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: l("This global set has no fields: choose a blueprint for it in its Settings.") }) : /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-4", children: [
      /* @__PURE__ */ e.jsx(r.ViewingNotice, { by: o.viewingBy, onTakeOver: o.requestTakeOver }),
      i && c !== i && /* @__PURE__ */ e.jsx("p", { className: "cms:rounded-lg cms:border cms:bg-muted/40 cms:p-3 cms:text-sm cms:text-muted-foreground", role: "note", children: v === null ? l("Not saved in :language yet: these are the :main values, which the site shows meanwhile. Translate them, then save.", { language: m(c), main: m(i) }) : l("A field left empty shows the :main value on the site.", { main: m(i) }) }),
      /* @__PURE__ */ e.jsx(K, { message: h.version, processing: a.processing, onSaveMine: L }),
      /* @__PURE__ */ e.jsx(r.KeptEditsNotice, { kept: o.kept, disabled: !d, onLoad: (s) => {
        T(s);
      }, onDiscard: (s) => {
        o.discardKept(s);
      } }),
      /* @__PURE__ */ e.jsx(
        E,
        {
          disabled: !d,
          fields: f,
          values: a.data.data,
          errors: h,
          storageKey: `cms:global:${t.handle}`,
          shared: S,
          sharedValues: x,
          mainLanguage: i ? m(i) : null,
          onChange: (s, n) => {
            a.setData("data", { ...a.data.data, [s]: n }), w(`data.${s}`);
          }
        }
      )
    ] })
  ] });
}
export {
  J as default
};
