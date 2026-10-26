import { a as o, t as e, j as t, r as f } from "./admin-7c56ii_N.js";
import { u as n } from "./ui-Dh5wfMwT.js";
import { P as g } from "./PageValuesForm-DlpcCYHm.js";
import { S as b } from "./settings-2-0VUe76YZ.js";
const r = "__shared";
function I({ taxonomy: l, collection: s, url: u, pages: d, settings: i, ...m }) {
  const h = i ?? o(`cms/taxonomies/${l.handle}/terms`), x = o(`cms/taxonomies/${l.handle}/index-page/edit`), c = s ? e("Index page: :collection", { collection: s.title }) : e("Index page"), p = i ? [{ label: e("Taxonomies"), href: o("cms/taxonomies") }, { label: l.title, href: i }, { label: c }] : [{ label: l.title, href: h }, { label: c }];
  return /* @__PURE__ */ t.jsx(
    g,
    {
      ...m,
      title: `${l.title}: ${c}`,
      breadcrumbs: p,
      back: { href: h, label: e("Back to :title", { title: l.title }) },
      saveUrl: o(`cms/taxonomies/${l.handle}/index-page`),
      editUrl: x,
      params: s ? { collection: s.handle } : {},
      viewUrl: u,
      empty: e("The index page has no fields of its own: choose its fields (Index page fields) in the taxonomy's settings under Structure → Taxonomies."),
      seoDescription: e("How the index page appears in search results and shared links."),
      storageKey: "cms:taxonomy-index:seo",
      templateHelp: e("For all of :taxonomy's index pages, the shared one and each collection's, in every language; also under Structure → Taxonomies. Automatic uses the first file that exists.", { taxonomy: l.title }),
      menu: i ? [{ label: e("Taxonomy settings"), href: i, icon: /* @__PURE__ */ t.jsx(b, {}) }] : [],
      children: d.length > 1 && /* @__PURE__ */ t.jsxs(n.Select, { value: s?.handle ?? r, onValueChange: (a) => f.router.get(x, { ...a === r ? {} : { collection: a }, locale: m.locale }), children: [
        /* @__PURE__ */ t.jsx(n.SelectTrigger, { className: "cms:w-64", "aria-label": e("Page"), children: /* @__PURE__ */ t.jsx(n.SelectValue, {}) }),
        /* @__PURE__ */ t.jsx(n.SelectContent, { children: d.map((a) => /* @__PURE__ */ t.jsx(n.SelectItem, { value: a.collection ?? r, children: a.collection === null ? e("Shared index (all collections)") : e(":collection only", { collection: a.title }) }, a.collection ?? r)) })
      ] })
    }
  );
}
export {
  I as default
};
