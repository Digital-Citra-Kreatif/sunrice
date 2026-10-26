import { a as i, j as a, t } from "./admin-BDMoHrGt.js";
import { P as r } from "./PageValuesForm-CnXxlpa6.js";
function g({ collection: e, ...l }) {
  const s = i(`cms/collections/${e.handle}/entries`);
  return /* @__PURE__ */ a.jsx(
    r,
    {
      ...l,
      title: t(":title: listing page", { title: e.title }),
      breadcrumbs: [{ label: e.title, href: s }, { label: t("Listing page") }],
      back: { href: s, label: t("Back to :title", { title: e.title }) },
      saveUrl: i(`cms/collections/${e.handle}/listing`),
      editUrl: i(`cms/collections/${e.handle}/listing/edit`),
      viewUrl: e.url,
      empty: t("The listing page has no fields of its own: choose a listing blueprint for the collection under Structure → Collections."),
      seoDescription: t("How the listing page appears in search results and shared links."),
      storageKey: "cms:listing:seo",
      templateHelp: t("For :title's listing page, in every language; also under Structure → Collections. Automatic uses the first file that exists.", { title: e.title })
    }
  );
}
export {
  g as default
};
