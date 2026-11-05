const h = globalThis.Sunrice;
if (!h) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const b = h.modules["react/jsx-runtime"], p = globalThis.Sunrice;
if (!p) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const C = p.modules["@inertiajs/react"], d = 3;
function c() {
  const e = globalThis.Sunrice;
  if (!e)
    throw new Error("[sunrice] This script runs outside the Sunrice admin panel (window.Sunrice is missing).");
  return e;
}
function S(e) {
  const n = c();
  if (n.version !== d)
    throw new Error(`[sunrice] This plugin was built for admin runtime ${d}, but the panel runs ${n.version}. Rebuild it with a matching @imajiner/admin-sdk.`);
  for (const [t, i] of Object.entries(e.pages ?? {}))
    n.registerPage(t, i);
  for (const [t, i] of Object.entries(e.lazyPages ?? {}))
    n.registerPageLoader(t, i);
}
function a(e, n = {}) {
  return c().t(e, n);
}
function x(e = "") {
  return c().adminUrl(e);
}
function $(e) {
  return { draft: a("Draft"), scheduled: a("Scheduled"), published: a("Live") }[e];
}
function P(e, n) {
  if (!e) return "—";
  const t = new Date(e);
  return Number.isNaN(t.getTime()) ? "—" : t.toLocaleString(document.documentElement.lang || void 0, { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h23" });
}
function j(e, n = /* @__PURE__ */ new Date()) {
  const t = e ? new Date(e) : null;
  if (t && !Number.isNaN(t.getTime()) && t > n) return t;
  const i = new Date(n);
  return i.setDate(i.getDate() + 1), i.setMinutes(0, 0, 0), i;
}
function F(e) {
  const n = (t) => String(t).padStart(2, "0");
  return `${e.getFullYear()}-${n(e.getMonth() + 1)}-${n(e.getDate())}T${n(e.getHours())}:${n(e.getMinutes())}`;
}
function L(e, n, t, i = Date.now()) {
  const r = n === "" ? null : new Date(n);
  if (r === null || Number.isNaN(r.getTime())) return { action: "now", at: null };
  const s = (w) => Math.floor(w / 6e4), l = t ? new Date(t).getTime() : null;
  if (l !== null && s(l) === s(r.getTime()) && (e !== "draft" || r.getTime() <= i)) return { action: "now", at: null };
  const m = r.toISOString();
  return r.getTime() > i ? { action: "schedule", at: m } : { action: e === "draft" ? "backdate" : "redate", at: m };
}
let u = {};
function T(e) {
  u = e ?? {};
}
function M(e, n) {
  if (n === void 0 && u[e]) return u[e];
  try {
    return new Intl.DisplayNames(n ? [n] : void 0, { type: "language" }).of(e.replace("_", "-")) ?? e;
  } catch {
    return e;
  }
}
function g(e) {
  return e.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
}
function O(e, n, t, i) {
  const r = g(n), s = e === r || e.startsWith(`${r}-`) && /^\d+$/.test(e.slice(r.length + 1));
  return i && r !== "" && s ? g(t) : e;
}
function U(e, n, t) {
  return !e || n.trim() === "" || n.trim() === e ? null : t ? "redirects" : "breaks";
}
function z(e) {
  return e.trashed ? "trashed" : e.status === "draft" ? e.everPublished ? "offline" : "draft" : e.status === "scheduled" ? e.hasUnpublishedChanges ? "scheduled-changes" : "scheduled" : e.hasUnpublishedChanges ? "live-changes" : "live";
}
function E(e) {
  return e === "live-changes" || e === "scheduled-changes";
}
function k(e, n) {
  const t = E(e);
  return {
    viewLive: n.liveUrl && (e === "live" || e === "live-changes"),
    compare: t,
    preview: n.pages && e !== "trashed" && e !== "live",
    discard: t && n.canUpdate
  };
}
function y(e) {
  return e.status !== "draft" && e.changes === !0;
}
const o = /* @__PURE__ */ new Map();
function v(e, n) {
  o.has(e) && console.warn(`[sunrice] The field input "${e}" was registered twice; the second registration wins.`), o.set(e, n);
}
function R(e) {
  return o.get(e);
}
function N() {
  globalThis.SunriceCms = { version: 1, registerFieldInput: v };
}
N();
function D(e) {
  return function(t) {
    const i = C.usePage().props.cms;
    return T(i?.locales), /* @__PURE__ */ b.jsx(e, { ...t });
  };
}
const f = () => (__sunricePreload(["./ui-BzV6lfEf.js","./triangle-alert-CDOdGjCe.js","./Confirm-D3wIXPDE.js","./FieldInput-DDzijfI9.js","./EntriesPicker-UGlyV514.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./FieldError-BZMS2hqi.js","./plus-Cn7AaagX.js","./chevron-right-1BqdTUoX.js","./link-2-HefnCw8K.js","./tags-RK9FkZvx.js","./copy-D11fpdhs.js","./loader-circle-CjVcQmAV.js"]), import("./Edit-BnjVPc2D.js")), I = {
  "Cms/Blueprints/Index": () => (__sunricePreload(["./FieldError-BZMS2hqi.js","./StructureList-Dw4lM_Ad.js","./ui-BzV6lfEf.js","./Confirm-D3wIXPDE.js","./plus-Cn7AaagX.js"]), import("./Index-BIneXOM1.js")),
  "Cms/Blueprints/Edit": f,
  "Cms/Fieldsets/Index": () => (__sunricePreload(["./FieldError-BZMS2hqi.js","./StructureList-Dw4lM_Ad.js","./ui-BzV6lfEf.js","./Confirm-D3wIXPDE.js","./plus-Cn7AaagX.js"]), import("./Index-DUIzP_YR.js")),
  // A fieldset is edited like a blueprint.
  "Cms/Fieldsets/Edit": f,
  "Cms/Entries/Index": () => (__sunricePreload(["./ui-BzV6lfEf.js","./Confirm-D3wIXPDE.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./ellipsis-BA6pVhi_.js","./tree-BdgFD7PS.js","./download-O5RUS1Kt.js","./plus-Cn7AaagX.js","./rotate-ccw-BxpZ9siY.js","./chevron-left-DDGNQO3o.js","./chevron-right-1BqdTUoX.js"]), import("./Index-DvN1D_Cq.js")),
  "Cms/Entries/Edit": () => (__sunricePreload(["./ui-BzV6lfEf.js","./Confirm-D3wIXPDE.js","./seo-CztxhUcP.js","./FieldError-BZMS2hqi.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./EntriesPicker-UGlyV514.js","./undo-2-B1yF9-es.js","./languages-CKEZEi3X.js","./FieldInput-DDzijfI9.js","./plus-Cn7AaagX.js","./chevron-right-1BqdTUoX.js","./SlugNote-C4WSu9b3.js","./rotate-ccw-BxpZ9siY.js","./link-2-HefnCw8K.js","./copy-D11fpdhs.js","./layout-template-Btp-VVsS.js","./loader-circle-CjVcQmAV.js","./external-link-Mqd1ZxLN.js"]), import("./Edit-DEvOUh8o.js")),
  "Cms/Listing/Edit": () => (__sunricePreload(["./PageValuesForm-DgKO-1qO.js","./ui-BzV6lfEf.js","./FieldInput-DDzijfI9.js","./EntriesPicker-UGlyV514.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./FieldError-BZMS2hqi.js","./plus-Cn7AaagX.js","./Confirm-D3wIXPDE.js","./chevron-right-1BqdTUoX.js","./languages-CKEZEi3X.js","./VersionConflict-BumgveFa.js","./seo-CztxhUcP.js","./UnsavedNote-DxgON8Kz.js","./external-link-Mqd1ZxLN.js","./loader-circle-CjVcQmAV.js","./settings-2-B2-hLaF4.js","./layout-template-Btp-VVsS.js"]), import("./Edit-kVFerNx_.js")),
  "Cms/Terms/Index": () => (__sunricePreload(["./ui-BzV6lfEf.js","./Confirm-D3wIXPDE.js","./tree-BdgFD7PS.js","./settings-2-B2-hLaF4.js","./layout-template-Btp-VVsS.js","./plus-Cn7AaagX.js","./search-Dj4j-7U0.js","./external-link-Mqd1ZxLN.js","./rotate-ccw-BxpZ9siY.js","./ellipsis-BA6pVhi_.js","./pencil-BROVvpkc.js"]), import("./Index-FRRTDvaN.js")),
  "Cms/TaxonomyIndex/Edit": () => (__sunricePreload(["./ui-BzV6lfEf.js","./PageValuesForm-DgKO-1qO.js","./FieldInput-DDzijfI9.js","./EntriesPicker-UGlyV514.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./FieldError-BZMS2hqi.js","./plus-Cn7AaagX.js","./Confirm-D3wIXPDE.js","./chevron-right-1BqdTUoX.js","./languages-CKEZEi3X.js","./VersionConflict-BumgveFa.js","./seo-CztxhUcP.js","./UnsavedNote-DxgON8Kz.js","./external-link-Mqd1ZxLN.js","./loader-circle-CjVcQmAV.js","./settings-2-B2-hLaF4.js","./layout-template-Btp-VVsS.js"]), import("./Edit-DLAEmUnL.js")),
  "Cms/Translations/Edit": () => (__sunricePreload(["./ui-BzV6lfEf.js","./FieldError-BZMS2hqi.js","./UnsavedNote-DxgON8Kz.js","./loader-circle-CjVcQmAV.js","./external-link-Mqd1ZxLN.js"]), import("./Edit-BU39aMPS.js")),
  "Cms/Terms/Edit": () => (__sunricePreload(["./ui-BzV6lfEf.js","./FieldError-BZMS2hqi.js","./FieldInput-DDzijfI9.js","./EntriesPicker-UGlyV514.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./plus-Cn7AaagX.js","./Confirm-D3wIXPDE.js","./chevron-right-1BqdTUoX.js","./languages-CKEZEi3X.js","./SlugNote-C4WSu9b3.js","./seo-CztxhUcP.js","./VersionConflict-BumgveFa.js","./UnsavedNote-DxgON8Kz.js","./external-link-Mqd1ZxLN.js","./loader-circle-CjVcQmAV.js","./layout-template-Btp-VVsS.js"]), import("./Edit-CgeCtF81.js")),
  "Cms/Globals/Edit": () => (__sunricePreload(["./ui-BzV6lfEf.js","./languages-CKEZEi3X.js","./FieldInput-DDzijfI9.js","./EntriesPicker-UGlyV514.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./FieldError-BZMS2hqi.js","./plus-Cn7AaagX.js","./Confirm-D3wIXPDE.js","./chevron-right-1BqdTUoX.js","./VersionConflict-BumgveFa.js","./UnsavedNote-DxgON8Kz.js","./loader-circle-CjVcQmAV.js","./settings-2-B2-hLaF4.js","./layout-template-Btp-VVsS.js"]), import("./Edit-Dguq8PRC.js")),
  "Cms/Menus/Edit": () => (__sunricePreload(["./ui-BzV6lfEf.js","./EntriesPicker-UGlyV514.js","./TermsPicker-Btx24xdC.js","./check-Cvm5ZpBE.js","./search-Dj4j-7U0.js","./FieldError-BZMS2hqi.js","./VersionConflict-BumgveFa.js","./Confirm-D3wIXPDE.js","./tree-BdgFD7PS.js","./UnsavedNote-DxgON8Kz.js","./plus-Cn7AaagX.js","./tags-RK9FkZvx.js","./link-2-HefnCw8K.js","./loader-circle-CjVcQmAV.js","./settings-2-B2-hLaF4.js","./pencil-BROVvpkc.js"]), import("./Edit-BjGYilIG.js")),
  "Cms/Comments/Index": () => (__sunricePreload(["./ui-BzV6lfEf.js","./Confirm-D3wIXPDE.js","./FieldError-BZMS2hqi.js","./search-Dj4j-7U0.js","./check-Cvm5ZpBE.js","./undo-2-B1yF9-es.js","./rotate-ccw-BxpZ9siY.js","./external-link-Mqd1ZxLN.js","./pencil-BROVvpkc.js","./chevron-left-DDGNQO3o.js","./chevron-right-1BqdTUoX.js"]), import("./Index-D6brCbl3.js")),
  "Cms/Seo/Edit": () => (__sunricePreload(["./ui-BzV6lfEf.js","./FieldError-BZMS2hqi.js","./triangle-alert-CDOdGjCe.js"]), import("./Edit-DfbSDNSo.js")),
  "Cms/Submissions/Index": () => (__sunricePreload(["./ui-BzV6lfEf.js","./Confirm-D3wIXPDE.js","./plus-Cn7AaagX.js","./download-O5RUS1Kt.js","./layout-template-Btp-VVsS.js","./chevron-left-DDGNQO3o.js","./chevron-right-1BqdTUoX.js"]), import("./Index-BUqT78Yz.js"))
};
S({
  lazyPages: Object.fromEntries(Object.entries(I).map(([e, n]) => [
    e,
    async () => D((await n()).default)
  ]))
});
export {
  x as a,
  F as b,
  k as c,
  L as d,
  z as e,
  P as f,
  O as g,
  U as h,
  j as i,
  b as j,
  E as k,
  M as l,
  R as m,
  y as n,
  C as r,
  $ as s,
  a as t
};

function __sunricePreload(files) {
    if (typeof document === 'undefined') return;
    for (const file of files) {
        const href = new URL(file, import.meta.url).href;
        if (document.querySelector('link[rel="modulepreload"][href="' + CSS.escape(href) + '"]')) continue;
        const link = document.createElement('link');
        link.rel = 'modulepreload';
        link.href = href;
        document.head.appendChild(link);
    }
}
