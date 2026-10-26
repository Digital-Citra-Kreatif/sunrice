import { a as o, t as e, j as a, r as g } from "./admin-BDMoHrGt.js";
import { u as n } from "./ui-Dh5wfMwT.js";
import { P as p } from "./PageValuesForm-CnXxlpa6.js";
import { S as f } from "./settings-2-0VUe76YZ.js";
const i = "__shared";
function v({ taxonomy: l, collection: s, url: x, pages: r, settings: c, ...d }) {
  const m = o(`cms/taxonomies/${l.handle}/terms`), h = o(`cms/taxonomies/${l.handle}/index-page/edit`), u = s ? e("Index page: :collection", { collection: s.title }) : e("Index page");
  return /* @__PURE__ */ a.jsx(
    p,
    {
      ...d,
      title: `${l.title}: ${u}`,
      breadcrumbs: [{ label: l.title, href: m }, { label: u }],
      back: { href: m, label: e("Back to :title", { title: l.title }) },
      saveUrl: o(`cms/taxonomies/${l.handle}/index-page`),
      editUrl: h,
      params: s ? { collection: s.handle } : {},
      viewUrl: x,
      empty: e("The index page has no fields of its own: choose its fields (Index page fields) in the taxonomy's settings under Structure → Taxonomies."),
      seoDescription: e("How the index page appears in search results and shared links."),
      storageKey: "cms:taxonomy-index:seo",
      templateHelp: e("For all of :taxonomy's index pages, the shared one and each collection's, in every language; also under Structure → Taxonomies. Automatic uses the first file that exists.", { taxonomy: l.title }),
      menu: c ? [{ label: e("Taxonomy settings"), href: c, icon: /* @__PURE__ */ a.jsx(f, {}) }] : [],
      children: r.length > 1 && /* @__PURE__ */ a.jsxs(n.Select, { value: s?.handle ?? i, onValueChange: (t) => g.router.get(h, { ...t === i ? {} : { collection: t }, locale: d.locale }), children: [
        /* @__PURE__ */ a.jsx(n.SelectTrigger, { className: "cms:w-64", "aria-label": e("Page"), children: /* @__PURE__ */ a.jsx(n.SelectValue, {}) }),
        /* @__PURE__ */ a.jsx(n.SelectContent, { children: r.map((t) => /* @__PURE__ */ a.jsx(n.SelectItem, { value: t.collection ?? i, children: t.collection === null ? e("Shared index (all collections)") : e(":collection only", { collection: t.title }) }, t.collection ?? i)) })
      ] })
    }
  );
}
export {
  v as default
};
