function ms(s, t) {
  for (var l = 0; l < t.length; l++) {
    const c = t[l];
    if (typeof c != "string" && !Array.isArray(c)) {
      for (const r in c)
        if (r !== "default" && !(r in s)) {
          const n = Object.getOwnPropertyDescriptor(c, r);
          n && Object.defineProperty(s, r, n.get ? n : {
            enumerable: !0,
            get: () => c[r]
          });
        }
    }
  }
  return Object.freeze(Object.defineProperty(s, Symbol.toStringTag, { value: "Module" }));
}
const de = 1;
function be() {
  const s = globalThis.Sunrice;
  if (!s)
    throw new Error("[sunrice] This script runs outside the Sunrice admin panel (window.Sunrice is missing).");
  return s;
}
function us(s) {
  const t = be();
  if (t.version !== de)
    throw new Error(`[sunrice] This plugin was built for admin runtime ${de}, but the panel runs ${t.version}. Rebuild it with a matching @sunrice/admin-sdk.`);
  for (const [l, c] of Object.entries(s.pages ?? {}))
    t.registerPage(l, c);
}
function S(s = "") {
  return be().adminUrl(s);
}
const ve = globalThis.Sunrice;
if (!ve) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const e = ve.modules["react/jsx-runtime"], Ne = globalThis.Sunrice;
if (!Ne) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const w = Ne.modules.react, Ct = /* @__PURE__ */ ms({
  __proto__: null
}, [w]), ye = globalThis.Sunrice;
if (!ye) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const j = ye.modules["@inertiajs/react"];
const hs = (s) => s?.replace(/([a-z0-9])([A-Z])/g, "$1-$2").toLowerCase();
function xs(s, t, l = []) {
  if (t == null)
    throw new Error("[lucide]: iconNode is required when icon name is used");
  return {
    name: hs(s),
    size: 24,
    node: t,
    ...l.length > 0 ? { aliases: l } : {}
  };
}
const ps = (s) => {
  let t = "", l = !1;
  for (const c of s) {
    if (c === "-" || c === "_" || c <= " ") {
      l = t.length > 0;
      continue;
    }
    t.length === 0 ? t += c.toLowerCase() : t += l ? c.toUpperCase() : c, l = !1;
  }
  return t;
};
const gs = (s) => {
  const t = ps(s);
  return t.charAt(0).toUpperCase() + t.slice(1);
};
const ce = (...s) => s.filter((t, l, c) => !!t && t.trim() !== "" && c.indexOf(t) === l).join(" ").trim();
const R = {
  xmlns: "http://www.w3.org/2000/svg",
  width: 24,
  height: 24,
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": 2,
  "stroke-linecap": "round",
  "stroke-linejoin": "round"
};
function ae(s) {
  return s != null;
}
function js(s, t = {}) {
  const l = t.attributeNames ?? {}, c = (u) => l[u] ?? u, r = s.size ?? s.width ?? R.width, n = s.size ?? s.height ?? R.height, i = s.aliases?.filter((u) => typeof u == "string" && u.trim() !== "").map((u) => `lucide-${u}`) ?? [], m = [...s.name ? [`lucide-${s.name}`] : [], ...i], d = t.className?.split(" ").filter(Boolean) ?? [], x = t.includeDefaultClasses === !1 ? ce(...d) : ce("lucide", ...m, ...d), o = t.absoluteStrokeWidth ? Number(t.strokeWidth ?? R["stroke-width"]) * Number(s.size ?? s.width ?? R.width) / Number(t.size ?? t.width ?? R.width) : t.strokeWidth ?? R["stroke-width"];
  return [
    "svg",
    {
      ...Object.entries(R).reduce((u, [g, b]) => (u[c(g)] = b, u), {}),
      ..."color" in t && t.color && {
        [c("stroke")]: t.color
      },
      ..."size" in t && ae(t.size) && {
        [c("width")]: t.size,
        [c("height")]: t.size
      },
      ..."width" in t && ae(t.width) && {
        [c("width")]: t.width
      },
      ..."height" in t && ae(t.height) && {
        [c("height")]: t.height
      },
      [c("stroke-width")]: o,
      ...x && {
        [c("class")]: x
      },
      [c("viewBox")]: `0 0 ${r} ${n}`,
      ...t.hasA11yProp === !1 ? {
        [c("aria-hidden")]: "true"
      } : {},
      ..."attributes" in t && t.attributes
    },
    s.node.map((u) => {
      const [g, b, h] = u, v = t.nonScalingStroke ? { [c("vector-effect")]: "non-scaling-stroke", ...b } : b;
      return h ? [g, v, h] : [g, v];
    })
  ];
}
function fs(s, t = {}) {
  return js(s, {
    ...t,
    attributeNames: {
      ...t.attributeNames,
      class: "className",
      "stroke-width": "strokeWidth",
      "stroke-linecap": "strokeLinecap",
      "stroke-linejoin": "strokeLinejoin",
      "vector-effect": "vectorEffect"
    }
  });
}
const bs = (s) => {
  for (const t in s)
    if (t.startsWith("aria-") || t === "role" || t === "title")
      return !0;
  return !1;
}, vs = w.createContext({}), Ns = () => w.useContext(vs), ys = w.forwardRef(
  ({
    color: s,
    size: t,
    width: l,
    height: c,
    strokeWidth: r,
    absoluteStrokeWidth: n,
    nonScalingStroke: i,
    className: m = "",
    children: d,
    iconNode: x = [],
    icon: o = {
      node: x,
      aliases: [],
      size: 24
    },
    ...p
  }, u) => {
    const {
      size: g = 24,
      strokeWidth: b = 2,
      absoluteStrokeWidth: h = !1,
      nonScalingStroke: v = !1,
      color: T = "currentColor",
      className: N = ""
    } = Ns() ?? {}, k = !!d || bs(p), [f, C, $ = []] = fs(o, {
      color: s ?? T,
      width: l ?? t ?? g,
      height: c ?? t ?? g,
      strokeWidth: r ?? b,
      absoluteStrokeWidth: n ?? h,
      nonScalingStroke: i ?? v,
      className: ce(N, m),
      hasA11yProp: k,
      attributes: p
    });
    return w.createElement(
      f,
      {
        ref: u,
        ...C
      },
      [
        ...$.map(([B, z]) => w.createElement(B, z)),
        ...Array.isArray(d) ? d : [d]
      ]
    );
  }
);
function _(s, t = [], l = []) {
  const c = typeof s == "string" ? xs(s, t, l) : s, r = w.forwardRef(
    ({ className: n, ...i }, m) => w.createElement(ys, {
      ref: m,
      icon: c,
      className: n,
      ...i
    })
  );
  return c.name && (r.displayName = gs(c.name)), r;
}
const Ce = {
  name: "arrow-down",
  size: 24,
  node: [
    ["path", { d: "M12 5v14", key: "s699le" }],
    ["path", { d: "m19 12-7 7-7-7", key: "1idqje" }]
  ]
};
Ce.node;
const W = _(Ce);
const ke = {
  name: "arrow-left",
  size: 24,
  node: [
    ["path", { d: "m12 19-7-7 7-7", key: "1l729n" }],
    ["path", { d: "M19 12H5", key: "x3x0zl" }]
  ]
};
ke.node;
const Q = _(ke);
const we = {
  name: "arrow-up",
  size: 24,
  node: [
    ["path", { d: "m5 12 7-7 7 7", key: "hav0vg" }],
    ["path", { d: "M12 19V5", key: "x0mq9r" }]
  ]
};
we.node;
const G = _(we);
const Se = {
  name: "calendar-clock",
  size: 24,
  node: [
    ["path", { d: "M16 14v2.2l1.6 1", key: "fo4ql5" }],
    ["path", { d: "M16 2v3", key: "otl347" }],
    ["path", { d: "M21 7.338V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2h2.338", key: "7hb8p4" }],
    ["path", { d: "M3 9h5.859", key: "numkqi" }],
    ["path", { d: "M8 2v3", key: "1ioesn" }],
    ["circle", { cx: "16", cy: "16", r: "6", key: "qoo3c4" }]
  ]
};
Se.node;
const Cs = _(Se);
const $e = {
  name: "chevron-down",
  size: 24,
  node: [["path", { d: "m6 9 6 6 6-6", key: "qrunsl" }]]
};
$e.node;
const De = _($e);
const _e = {
  name: "chevron-left",
  size: 24,
  node: [["path", { d: "m15 18-6-6 6-6", key: "1wnfg3" }]]
};
_e.node;
const Te = _(_e);
const Be = {
  name: "chevron-right",
  size: 24,
  node: [["path", { d: "m9 18 6-6-6-6", key: "mthhwq" }]]
};
Be.node;
const re = _(Be);
const ze = {
  name: "copy",
  size: 24,
  node: [
    ["rect", { width: "14", height: "14", x: "8", y: "8", rx: "2", ry: "2", key: "17jyea" }],
    ["path", { d: "M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2", key: "zix9uf" }]
  ]
};
ze.node;
const ks = _(ze);
const Le = {
  name: "download",
  size: 24,
  node: [
    ["path", { d: "M12 15V3", key: "m9g1x1" }],
    ["path", { d: "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4", key: "ih7n3h" }],
    ["path", { d: "m7 10 5 5 5-5", key: "brsn70" }]
  ]
};
Le.node;
const Ie = _(Le);
const Me = {
  name: "external-link",
  size: 24,
  node: [
    ["path", { d: "M15 3h6v6", key: "1q9fwt" }],
    ["path", { d: "M10 14 21 3", key: "gplh6r" }],
    ["path", { d: "M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6", key: "a6xqqp" }]
  ]
};
Me.node;
const ws = _(Me);
const Ae = {
  name: "eye",
  size: 24,
  node: [
    [
      "path",
      {
        d: "M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0",
        key: "1nclc0"
      }
    ],
    ["circle", { cx: "12", cy: "12", r: "3", key: "1v7zrd" }]
  ]
};
Ae.node;
const Ss = _(Ae);
const Ee = {
  name: "link-2",
  size: 24,
  node: [
    ["path", { d: "M9 17H7A5 5 0 0 1 7 7h2", key: "8i5ue5" }],
    ["path", { d: "M15 7h2a5 5 0 1 1 0 10h-2", key: "1b9ql8" }],
    ["line", { x1: "8", x2: "16", y1: "12", y2: "12", key: "1jonct" }]
  ]
};
Ee.node;
const $s = _(Ee);
const Fe = {
  name: "list-indent-decrease",
  size: 24,
  node: [
    ["path", { d: "M21 5H11", key: "us1j55" }],
    ["path", { d: "M21 12H11", key: "wd7e0v" }],
    ["path", { d: "M21 19H11", key: "saa85w" }],
    ["path", { d: "m7 8-4 4 4 4", key: "o5hrat" }]
  ],
  aliases: ["outdent", "indent-decrease"]
};
Fe.node;
const Ds = _(Fe);
const He = {
  name: "list-indent-increase",
  size: 24,
  node: [
    ["path", { d: "M21 5H11", key: "us1j55" }],
    ["path", { d: "M21 12H11", key: "wd7e0v" }],
    ["path", { d: "M21 19H11", key: "saa85w" }],
    ["path", { d: "m3 8 4 4-4 4", key: "1a3j6y" }]
  ],
  aliases: ["indent", "indent-increase"]
};
He.node;
const _s = _(He);
const Re = {
  name: "loader-circle",
  size: 24,
  node: [["path", { d: "M21 12a9 9 0 1 1-6.219-8.56", key: "13zald" }]],
  aliases: ["loader-2"]
};
Re.node;
const ee = _(Re);
const Oe = {
  name: "pencil",
  size: 24,
  node: [
    [
      "path",
      {
        d: "M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z",
        key: "1a8usu"
      }
    ],
    ["path", { d: "m15 5 4 4", key: "1mk7zo" }]
  ]
};
Oe.node;
const Ts = _(Oe);
const Pe = {
  name: "plus",
  size: 24,
  node: [
    ["path", { d: "M5 12h14", key: "1ays0h" }],
    ["path", { d: "M12 5v14", key: "s699le" }]
  ]
};
Pe.node;
const F = _(Pe);
const Ue = {
  name: "rotate-ccw",
  size: 24,
  node: [
    ["path", { d: "M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8", key: "1357e3" }],
    ["path", { d: "M3 3v5h5", key: "1xhq8a" }]
  ]
};
Ue.node;
const Bs = _(Ue);
const Ve = {
  name: "search",
  size: 24,
  node: [
    ["path", { d: "m21 21-4.34-4.34", key: "14j7rj" }],
    ["circle", { cx: "11", cy: "11", r: "8", key: "4ej97u" }]
  ]
};
Ve.node;
const qe = _(Ve);
const We = {
  name: "trash",
  size: 24,
  node: [
    ["path", { d: "M10 11v6", key: "nco0om" }],
    ["path", { d: "M14 11v6", key: "outv1u" }],
    ["path", { d: "M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6", key: "miytrc" }],
    ["path", { d: "M3 6h18", key: "d0wm0j" }],
    ["path", { d: "M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2", key: "e791ji" }]
  ],
  aliases: ["trash-2"]
};
We.node;
const I = _(We);
const Ge = {
  name: "undo-2",
  size: 24,
  node: [
    ["path", { d: "M9 14 4 9l5-5", key: "102s5s" }],
    ["path", { d: "M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11", key: "f3b9sd" }]
  ]
};
Ge.node;
const zs = _(Ge);
const Ke = {
  name: "x",
  size: 24,
  node: [
    ["path", { d: "M18 6 6 18", key: "1bl5f8" }],
    ["path", { d: "m6 6 12 12", key: "d8bk6v" }]
  ]
};
Ke.node;
const Ls = _(Ke), Je = globalThis.Sunrice;
if (!Je) throw new Error("[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).");
const a = Je.modules["@sunrice/admin-sdk/ui"];
function K() {
  const [s, t] = w.useState(null), l = /* @__PURE__ */ e.jsx(a.Dialog, { open: s !== null, onOpenChange: (c) => !c && t(null), children: /* @__PURE__ */ e.jsxs(a.DialogContent, { children: [
    /* @__PURE__ */ e.jsxs(a.DialogHeader, { children: [
      /* @__PURE__ */ e.jsx(a.DialogTitle, { children: "Are you sure?" }),
      /* @__PURE__ */ e.jsx(a.DialogDescription, { children: s?.message })
    ] }),
    /* @__PURE__ */ e.jsxs(a.DialogFooter, { children: [
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "outline", onClick: () => t(null), children: "Cancel" }),
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "destructive", onClick: () => {
        s?.onConfirm(), t(null);
      }, children: "Delete" })
    ] })
  ] }) });
  return [(c, r) => t({ message: c, onConfirm: r }), l];
}
const Is = 5;
let Ms = 0;
const Ze = () => `f${++Ms}`;
function Xe(s) {
  return s.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "").replace(/^[0-9_]+/, "").slice(0, 64);
}
function Ye(s, t) {
  const l = new Set(t.map((n) => n.handle)), c = Xe(s) || "field";
  let r = c;
  for (let n = 2; l.has(r); n++)
    r = `${c}_${n}`;
  return r;
}
function Qe(s) {
  return (s ?? []).map(({ fields: t, ...l }) => ({
    ...l,
    key: Ze(),
    fields: Qe(t),
    handleTouched: !0
  }));
}
function es(s, t) {
  return s.map(({ key: l, handleTouched: c, fields: r, ...n }) => ({
    ...n,
    validation: (n.validation ?? []).map((i) => i.trim()).filter((i) => i !== ""),
    ...t(n.type) ? { fields: es(r, t) } : {}
  }));
}
function As(s, t, l) {
  return { key: Ze(), type: s, label: t, handle: Ye(t, l), required: !1, config: {}, fields: [], handleTouched: !1 };
}
function U(s, t, l) {
  const c = t + l;
  if (t < 0 || t >= s.length || c < 0 || c >= s.length) return s;
  const r = s.slice(), [n] = r.splice(t, 1);
  return r.splice(c, 0, n), r;
}
function Es(s, t, l) {
  if (s.handleTouched) return { ...s, label: t };
  const c = l.filter((r) => r.key !== s.key);
  return { ...s, label: t, handle: Ye(t || s.type, c) };
}
function se(s, t) {
  const l = `${t}.`, c = {};
  for (const [r, n] of Object.entries(s))
    r.startsWith(l) && (c[r.slice(l.length)] = n);
  return c;
}
function P(s, t) {
  const l = s[t];
  return { ...se(s, t), ...l !== void 0 ? { "": l } : {} };
}
function me(s, t) {
  return Object.keys(s).some((l) => l === t || l.startsWith(`${t}.`));
}
function Z(s) {
  return s && typeof s == "object" && !Array.isArray(s) ? s : {};
}
function Fs(s, t) {
  if (typeof s != "string" || s === "") return "";
  const l = /^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/.exec(s);
  return l ? t ? `${l[1]}T${l[2] ?? "00:00"}` : l[1] ?? "" : "";
}
function Hs(s) {
  return s === "" ? null : s;
}
function Rs() {
  return { _hidden: !1 };
}
function Os(s) {
  return { type: s, hidden: !1, values: {} };
}
function Ps(s) {
  const t = s.replace(/[\u0000- \u007f]+/g, ""), l = /^([a-zA-Z][a-zA-Z0-9+.-]*):/.exec(t);
  return l ? ["http", "https", "mailto", "tel"].includes(l[1].toLowerCase()) : !t.includes("\\");
}
function ss({ ids: s, search: t, collections: l }) {
  const c = new URLSearchParams();
  for (const r of s ?? []) c.append("ids[]", String(r));
  t && c.set("search", t);
  for (const r of l) c.append("collections[]", r);
  return c.toString();
}
function V(s) {
  return { draft: "Draft", scheduled: "Scheduled", published: "Published" }[s];
}
function X(s, t) {
  if (!s) return "—";
  const l = new Date(s);
  return Number.isNaN(l.getTime()) ? "—" : l.toLocaleString(t, { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
}
function Us(s) {
  const t = (l) => String(l).padStart(2, "0");
  return `${s.getFullYear()}-${t(s.getMonth() + 1)}-${t(s.getDate())}T${t(s.getHours())}:${t(s.getMinutes())}`;
}
function L(s, t) {
  try {
    return new Intl.DisplayNames(t ? [t] : void 0, { type: "language" }).of(s.replace("_", "-")) ?? s;
  } catch {
    return s;
  }
}
async function ue(s) {
  const t = await fetch(`${S("cms/entry-picker")}?${ss(s)}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
  if (!t.ok) throw new Error(String(t.status));
  return (await t.json()).items;
}
function ie({ id: s, value: t, multiple: l, max: c, collections: r, invalid: n, onChange: i }) {
  const m = t === null ? [] : Array.isArray(t) ? t : [t], [d, x] = w.useState({}), [o, p] = w.useState(""), [u, g] = w.useState(null), [b, h] = w.useState(!1), v = l && c !== null && m.length >= c, T = m.filter((f) => !d[f]).join(",");
  w.useEffect(() => {
    T !== "" && ue({ ids: T.split(",").map(Number), collections: r }).then((f) => x((C) => ({ ...C, ...Object.fromEntries(f.map(($) => [$.id, $])) }))).catch(() => h(!0));
  }, [T]), w.useEffect(() => {
    if (o.trim() === "") {
      g(null);
      return;
    }
    const f = window.setTimeout(() => {
      ue({ search: o.trim(), collections: r }).then((C) => {
        g(C), h(!1);
      }).catch(() => h(!0));
    }, 250);
    return () => window.clearTimeout(f);
  }, [o]);
  const N = (f) => {
    x((C) => ({ ...C, [f.id]: f })), i(l ? [...m, f.id] : f.id), p("");
  }, k = (f) => i(l ? f : f[0] ?? null);
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    m.length > 0 && /* @__PURE__ */ e.jsx("ul", { className: "cms:flex cms:flex-col cms:gap-1", children: m.map((f, C) => {
      const $ = d[f];
      return /* @__PURE__ */ e.jsxs("li", { className: "cms:flex cms:items-center cms:gap-2 cms:rounded-md cms:border cms:px-3 cms:py-1.5 cms:text-sm", children: [
        /* @__PURE__ */ e.jsx("span", { className: "cms:min-w-0 cms:flex-1 cms:truncate", children: $ ? $.title : `Entry #${f}` }),
        $ && /* @__PURE__ */ e.jsx("span", { className: "cms:text-xs cms:text-muted-foreground", children: $.collection }),
        $ && /* @__PURE__ */ e.jsx(a.Badge, { variant: $.status === "published" ? "default" : "secondary", children: V($.status) }),
        l && /* @__PURE__ */ e.jsxs(e.Fragment, { children: [
          /* @__PURE__ */ e.jsx(
            a.Button,
            {
              type: "button",
              variant: "ghost",
              size: "icon",
              className: "cms:size-7",
              "aria-label": `Move ${$?.title ?? f} up`,
              disabled: C === 0,
              onClick: () => k(U(m, C, -1)),
              children: /* @__PURE__ */ e.jsx(G, {})
            }
          ),
          /* @__PURE__ */ e.jsx(
            a.Button,
            {
              type: "button",
              variant: "ghost",
              size: "icon",
              className: "cms:size-7",
              "aria-label": `Move ${$?.title ?? f} down`,
              disabled: C === m.length - 1,
              onClick: () => k(U(m, C, 1)),
              children: /* @__PURE__ */ e.jsx(W, {})
            }
          )
        ] }),
        /* @__PURE__ */ e.jsx(
          a.Button,
          {
            type: "button",
            variant: "ghost",
            size: "icon",
            className: "cms:size-7",
            "aria-label": `Remove ${$?.title ?? f}`,
            onClick: () => k(m.filter((B) => B !== f)),
            children: /* @__PURE__ */ e.jsx(Ls, {})
          }
        )
      ] }, f);
    }) }),
    !v && (l || m.length === 0) && /* @__PURE__ */ e.jsxs("div", { className: "cms:relative cms:max-w-md", children: [
      /* @__PURE__ */ e.jsx(qe, { className: "cms:absolute cms:top-1/2 cms:left-3 cms:size-4 cms:-translate-y-1/2 cms:text-muted-foreground" }),
      /* @__PURE__ */ e.jsx(
        a.Input,
        {
          id: s,
          className: "cms:pl-9",
          placeholder: "Search entries by title",
          "aria-invalid": n,
          value: o,
          autoComplete: "off",
          onChange: (f) => p(f.target.value)
        }
      ),
      u !== null && /* @__PURE__ */ e.jsxs("ul", { role: "listbox", "aria-label": "Matching entries", className: "cms:absolute cms:z-10 cms:mt-1 cms:max-h-64 cms:w-full cms:overflow-auto cms:rounded-md cms:border cms:bg-card cms:p-1 cms:shadow-md", children: [
        u.filter((f) => !m.includes(f.id)).length === 0 && /* @__PURE__ */ e.jsx("li", { className: "cms:p-2 cms:text-sm cms:text-muted-foreground", children: "No entries match." }),
        u.filter((f) => !m.includes(f.id)).map((f) => /* @__PURE__ */ e.jsx("li", { role: "option", "aria-selected": !1, children: /* @__PURE__ */ e.jsxs(
          "button",
          {
            type: "button",
            className: "cms:flex cms:w-full cms:items-center cms:gap-2 cms:rounded cms:px-2 cms:py-1.5 cms:text-left cms:text-sm cms:hover:bg-accent",
            onClick: () => N(f),
            children: [
              /* @__PURE__ */ e.jsx("span", { className: "cms:flex-1 cms:truncate", children: f.title }),
              /* @__PURE__ */ e.jsx("span", { className: "cms:text-xs cms:text-muted-foreground", children: f.collection })
            ]
          }
        ) }, f.id))
      ] })
    ] }),
    b && /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-destructive", children: "Entries could not be loaded. Try again." })
  ] });
}
const Vs = w.lazy(() => import("./RichTextEditor-Dlyx1pxC.js").then((s) => ({ default: s.RichTextEditor }))), le = "__none";
function D({ message: s }) {
  return s ? /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-destructive", children: s }) : null;
}
function he(s) {
  const t = s.config?.options;
  return Array.isArray(t) ? t.flatMap((l) => {
    if (typeof l == "string") return [{ value: l, label: l }];
    if (l && typeof l == "object" && typeof l.value == "string") {
      const { value: c, label: r } = l;
      return [{ value: c, label: r || c }];
    }
    return [];
  }) : [];
}
const qs = ["repeater", "group", "flexible", "link", "media", "rich_text", "entries"];
function Ws(s) {
  if (s.label) return s.label;
  const t = s.handle.replace(/_/g, " ");
  return t.charAt(0).toUpperCase() + t.slice(1);
}
function q({ field: s, value: t, onChange: l, errors: c, id: r, content: n = !1 }) {
  const i = Ws(s), m = qs.includes(s.type) || ["select", "terms"].includes(s.type) && s.config?.multiple === !0;
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", ...m ? { role: "group", "aria-labelledby": `${r}-label` } : {}, children: [
    s.type === "toggle" ? /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-3", children: [
      /* @__PURE__ */ e.jsx(a.Switch, { id: r, checked: t === !0, onCheckedChange: (d) => l(d) }),
      /* @__PURE__ */ e.jsx(a.Label, { htmlFor: r, children: i })
    ] }) : /* @__PURE__ */ e.jsxs(a.Label, { id: `${r}-label`, htmlFor: m ? void 0 : r, children: [
      i,
      s.required && /* @__PURE__ */ e.jsx("span", { className: "cms:text-destructive", children: " *" })
    ] }),
    /* @__PURE__ */ e.jsx(Gs, { field: s, value: t, onChange: l, errors: c, id: r, content: n }),
    s.instructions && /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: s.instructions }),
    /* @__PURE__ */ e.jsx(D, { message: c[""] })
  ] });
}
function Gs({ field: s, value: t, onChange: l, errors: c, id: r, content: n }) {
  const i = c[""] !== void 0;
  switch (s.type) {
    case "toggle":
      return null;
    case "textarea":
      return /* @__PURE__ */ e.jsx(
        a.Textarea,
        {
          id: r,
          "aria-invalid": i,
          value: typeof t == "string" ? t : "",
          rows: O(s, "rows") ?? 4,
          onChange: (m) => l(m.target.value)
        }
      );
    case "rich_text":
      return /* @__PURE__ */ e.jsx(w.Suspense, { fallback: /* @__PURE__ */ e.jsx(a.Skeleton, { className: "cms:h-48 cms:w-full" }), children: /* @__PURE__ */ e.jsx(Vs, { id: r, labelledBy: `${r}-label`, invalid: i, value: typeof t == "string" ? t : "", onChange: l }) });
    case "number":
      return /* @__PURE__ */ e.jsx(
        a.Input,
        {
          id: r,
          type: "number",
          className: "cms:max-w-48",
          "aria-invalid": i,
          value: typeof t == "number" ? String(t) : typeof t == "string" ? t : "",
          min: O(s, "min"),
          max: O(s, "max"),
          step: O(s, "step") ?? "any",
          onChange: (m) => l(m.target.value === "" ? null : Number(m.target.value))
        }
      );
    case "date": {
      const m = s.config?.time === !0;
      return /* @__PURE__ */ e.jsx(
        a.Input,
        {
          id: r,
          type: m ? "datetime-local" : "date",
          className: "cms:max-w-64",
          "aria-invalid": i,
          value: Fs(t, m),
          onChange: (d) => l(Hs(d.target.value))
        }
      );
    }
    case "select":
      return s.config?.multiple === !0 ? /* @__PURE__ */ e.jsx(pe, { options: he(s), value: Array.isArray(t) ? t.map(String) : [], onChange: l }) : /* @__PURE__ */ e.jsx(xe, { options: he(s), value: typeof t == "string" ? t : null, onChange: l, id: r, required: s.required === !0 });
    case "terms": {
      const m = s.terms ?? [];
      if (s.config?.multiple === !0) {
        const d = Array.isArray(t) ? t.map(String) : [];
        return /* @__PURE__ */ e.jsx(pe, { options: m, value: d, onChange: (x) => l(x.map(Number)) });
      }
      return /* @__PURE__ */ e.jsx(
        xe,
        {
          options: m,
          id: r,
          required: s.required === !0,
          value: t == null ? null : String(t),
          onChange: (d) => l(d === null ? null : Number(d))
        }
      );
    }
    case "entries":
      return /* @__PURE__ */ e.jsx(
        ie,
        {
          id: r,
          invalid: i,
          multiple: s.config?.multiple === !0,
          max: O(s, "max") ?? null,
          collections: s.collections ?? [],
          onChange: l,
          value: typeof t == "number" || Array.isArray(t) && t.every((m) => typeof m == "number") ? t : null
        }
      );
    case "link":
      return /* @__PURE__ */ e.jsx(Js, { value: Z(t), onChange: l, errors: c, id: r });
    case "media":
      return /* @__PURE__ */ e.jsx(
        a.MediaPickerInput,
        {
          id: r,
          invalid: i,
          disabled: !1,
          multiple: s.config?.multiple === !0,
          max: O(s, "max") ?? null,
          types: Array.isArray(s.config?.types) ? s.config.types : [],
          value: typeof t == "number" || Array.isArray(t) && t.every((m) => typeof m == "number") ? t : null,
          onChange: l
        }
      );
    case "group":
      return /* @__PURE__ */ e.jsx(te, { fields: s.fields ?? [], value: Z(t), onChange: l, errors: c, id: r, content: n });
    case "repeater":
      return /* @__PURE__ */ e.jsx(Xs, { field: s, value: Array.isArray(t) ? t : [], onChange: l, errors: c, id: r, content: n });
    case "flexible":
      return /* @__PURE__ */ e.jsx(Ys, { field: s, value: Array.isArray(t) ? t : [], onChange: l, errors: c, id: r });
    default:
      return /* @__PURE__ */ e.jsx(
        a.Input,
        {
          id: r,
          "aria-invalid": i,
          value: typeof t == "string" ? t : "",
          placeholder: Ks(s, "placeholder"),
          maxLength: O(s, "max"),
          onChange: (m) => l(m.target.value)
        }
      );
  }
}
function O(s, t) {
  const l = s.config?.[t];
  return typeof l == "number" ? l : void 0;
}
function Ks(s, t) {
  const l = s.config?.[t];
  return typeof l == "string" ? l : void 0;
}
function te({ fields: s, value: t, onChange: l, errors: c, id: r, content: n }) {
  return /* @__PURE__ */ e.jsx("div", { className: "cms:flex cms:flex-col cms:gap-4 cms:rounded-lg cms:border cms:p-4", children: s.map((i) => /* @__PURE__ */ e.jsx(
    q,
    {
      id: `${r}-${i.handle}`,
      field: i,
      value: t[i.handle],
      content: n,
      errors: P(c, i.handle),
      onChange: (m) => l({ ...t, [i.handle]: m })
    },
    i.handle
  )) });
}
function xe({ options: s, value: t, onChange: l, id: c, required: r }) {
  return /* @__PURE__ */ e.jsxs(a.Select, { value: t ?? le, onValueChange: (n) => l(n === le ? null : n), children: [
    /* @__PURE__ */ e.jsx(a.SelectTrigger, { id: c, className: "cms:w-full cms:max-w-sm", children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
    /* @__PURE__ */ e.jsxs(a.SelectContent, { children: [
      !r && /* @__PURE__ */ e.jsx(a.SelectItem, { value: le, children: "None" }),
      s.map((n) => /* @__PURE__ */ e.jsx(a.SelectItem, { value: n.value, children: n.label }, n.value))
    ] })
  ] });
}
function pe({ options: s, value: t, onChange: l }) {
  return s.length === 0 ? /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: "Nothing to choose from yet." }) : /* @__PURE__ */ e.jsx("div", { className: "cms:flex cms:flex-col cms:gap-2", children: s.map((c) => /* @__PURE__ */ e.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
    /* @__PURE__ */ e.jsx(
      a.Checkbox,
      {
        checked: t.includes(c.value),
        onCheckedChange: (r) => l(r ? s.map((n) => n.value).filter((n) => n === c.value || t.includes(n)) : t.filter((n) => n !== c.value))
      }
    ),
    c.label
  ] }, c.value)) });
}
function Js({ value: s, onChange: t, errors: l, id: c }) {
  const r = (n, i) => {
    const m = { ...s, [n]: i };
    t(m.url === "" || m.url === void 0 ? null : m);
  };
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:grid cms:gap-2 cms:md:grid-cols-[2fr_1fr_auto] cms:md:items-center", children: [
    /* @__PURE__ */ e.jsx(
      a.Input,
      {
        id: c,
        "aria-label": "Address",
        placeholder: "https://… or /about",
        value: typeof s.url == "string" ? s.url : "",
        onChange: (n) => r("url", n.target.value),
        "aria-invalid": l.url !== void 0
      }
    ),
    /* @__PURE__ */ e.jsx(
      a.Input,
      {
        "aria-label": "Link text",
        placeholder: "Link text",
        value: typeof s.label == "string" ? s.label : "",
        onChange: (n) => r("label", n.target.value)
      }
    ),
    /* @__PURE__ */ e.jsxs("label", { className: "cms:flex cms:items-center cms:gap-2 cms:text-sm", children: [
      /* @__PURE__ */ e.jsx(a.Checkbox, { checked: s.new_tab === !0, onCheckedChange: (n) => r("new_tab", n) }),
      "New tab"
    ] }),
    /* @__PURE__ */ e.jsx("div", { className: "cms:md:col-span-3", children: /* @__PURE__ */ e.jsx(D, { message: l.url ?? l.label }) })
  ] });
}
function Zs(s, t) {
  return !t && (s.fields ?? []).every((l) => l.type === "text");
}
function Xs({ field: s, value: t, onChange: l, errors: c, id: r, content: n = !1 }) {
  const i = s.fields ?? [], m = t.map(Z), d = (p, u) => l(m.map((g, b) => b === p ? u : g)), x = (s.label || "row").toLowerCase().replace(/s$/, ""), o = x.charAt(0).toUpperCase() + x.slice(1);
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    m.map((p, u) => Zs(s, n) ? /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-start cms:gap-2", children: [
      i.map((g) => /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-1 cms:flex-col cms:gap-1", children: [
        /* @__PURE__ */ e.jsx(
          a.Input,
          {
            "aria-label": g.label || g.handle,
            placeholder: g.label || g.handle,
            value: typeof p[g.handle] == "string" ? p[g.handle] : "",
            onChange: (b) => d(u, { ...p, [g.handle]: b.target.value })
          }
        ),
        /* @__PURE__ */ e.jsx(D, { message: c[`${u}.${g.handle}`] })
      ] }, g.handle)),
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", "aria-label": `Remove ${x} ${u + 1}`, onClick: () => l(m.filter((g, b) => b !== u)), children: /* @__PURE__ */ e.jsx(I, {}) })
    ] }, u) : /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
      /* @__PURE__ */ e.jsx(
        ts,
        {
          label: `${o} ${u + 1}`,
          index: u,
          count: m.length,
          onMove: (g) => l(U(m, u, g)),
          onRemove: () => l(m.filter((g, b) => b !== u)),
          hidden: n ? p._hidden === !0 : void 0,
          onHidden: (g) => d(u, { ...p, _hidden: g })
        }
      ),
      /* @__PURE__ */ e.jsx(te, { fields: i, value: p, onChange: (g) => d(u, g), errors: P(c, String(u)), id: `${r}-${u}`, content: n })
    ] }, typeof p._id == "string" ? p._id : u)),
    /* @__PURE__ */ e.jsx("div", { children: /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "outline", size: "sm", onClick: () => l([...m, n ? Rs() : {}]), children: [
      /* @__PURE__ */ e.jsx(F, {}),
      " Add ",
      x
    ] }) })
  ] });
}
function Ys({ field: s, value: t, onChange: l, errors: c, id: r }) {
  const n = s.blocks ?? [], i = t.map(Z), m = (d, x) => l(i.map((o, p) => p === d ? x : o));
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-3", children: [
    i.map((d, x) => {
      const o = n.find((p) => p.type === d.type);
      return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(
          ts,
          {
            label: o?.label ?? String(d.type),
            badge: !0,
            index: x,
            count: i.length,
            onMove: (p) => l(U(i, x, p)),
            onRemove: () => l(i.filter((p, u) => u !== x)),
            hidden: d.hidden === !0,
            onHidden: (p) => m(x, { ...d, hidden: p })
          }
        ),
        o ? /* @__PURE__ */ e.jsx(
          te,
          {
            fields: o.fields,
            value: Z(d.values),
            onChange: (p) => m(x, { ...d, values: p }),
            errors: P(c, `${x}.values`),
            id: `${r}-${x}`,
            content: !0
          }
        ) : /* @__PURE__ */ e.jsx("p", { className: "cms:rounded-lg cms:border cms:p-4 cms:text-sm cms:text-muted-foreground", children: "This kind of block no longer exists. Its values are kept." }),
        /* @__PURE__ */ e.jsx(D, { message: c[`${x}.type`] })
      ] }, typeof d.id == "string" ? d.id : x);
    }),
    /* @__PURE__ */ e.jsx("div", { children: /* @__PURE__ */ e.jsxs(a.DropdownMenu, { children: [
      /* @__PURE__ */ e.jsx(a.DropdownMenuTrigger, { asChild: !0, children: /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "outline", size: "sm", disabled: n.length === 0, children: [
        /* @__PURE__ */ e.jsx(F, {}),
        " Add block"
      ] }) }),
      /* @__PURE__ */ e.jsx(a.DropdownMenuContent, { align: "start", children: n.map((d) => /* @__PURE__ */ e.jsx(a.DropdownMenuItem, { onSelect: () => l([...i, Os(d.type)]), children: d.label }, d.type)) })
    ] }) })
  ] });
}
function ts({ label: s, badge: t = !1, index: l, count: c, onMove: r, onRemove: n, hidden: i, onHidden: m }) {
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
    t ? /* @__PURE__ */ e.jsx(a.Badge, { variant: "secondary", children: s }) : /* @__PURE__ */ e.jsx("span", { className: "cms:text-sm cms:font-medium", children: s }),
    i !== void 0 && /* @__PURE__ */ e.jsxs("label", { className: "cms:ml-auto cms:flex cms:items-center cms:gap-2 cms:text-sm cms:text-muted-foreground", children: [
      /* @__PURE__ */ e.jsx(a.Switch, { checked: !i, onCheckedChange: (d) => m(!d), "aria-label": `Show ${s}` }),
      "Show"
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: i === void 0 ? "cms:ml-auto cms:flex" : "cms:flex", children: [
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": `Move ${s} up`, disabled: l === 0, onClick: () => r(-1), children: /* @__PURE__ */ e.jsx(G, {}) }),
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": `Move ${s} down`, disabled: l === c - 1, onClick: () => r(1), children: /* @__PURE__ */ e.jsx(W, {}) }),
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8 cms:text-destructive", "aria-label": `Remove ${s}`, onClick: n, children: /* @__PURE__ */ e.jsx(I, {}) })
    ] })
  ] });
}
function as({ fields: s, onChange: t, types: l, errors: c, depth: r = 1, idPrefix: n = "field" }) {
  const [i, m] = w.useState(() => /* @__PURE__ */ new Set()), d = (p) => m((u) => {
    const g = new Set(u);
    return g.has(p) ? g.delete(p) : g.add(p), g;
  }), x = (p, u) => t(s.map((g, b) => b === p ? u : g)), o = (p) => {
    const u = As(p.type, p.label, s);
    t([...s, u]), m((g) => new Set(g).add(u.key));
  };
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
    s.length === 0 && /* @__PURE__ */ e.jsx("p", { className: "cms:rounded-lg cms:border cms:border-dashed cms:p-4 cms:text-sm cms:text-muted-foreground", children: "No fields yet." }),
    s.map((p, u) => {
      const g = l.find((v) => v.type === p.type), b = i.has(p.key) || me(c, String(u)), h = `${n}-${p.key}`;
      return /* @__PURE__ */ e.jsxs("div", { className: `cms:rounded-lg cms:border cms:bg-card ${me(c, String(u)) ? "cms:border-destructive" : ""}`, children: [
        /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2 cms:p-2", children: [
          /* @__PURE__ */ e.jsx(
            a.Button,
            {
              type: "button",
              variant: "ghost",
              size: "icon",
              className: "cms:size-8",
              "aria-expanded": b,
              "aria-controls": h,
              "aria-label": `${b ? "Close" : "Open"} ${p.label || p.handle}`,
              onClick: () => d(p.key),
              children: b ? /* @__PURE__ */ e.jsx(De, {}) : /* @__PURE__ */ e.jsx(re, {})
            }
          ),
          /* @__PURE__ */ e.jsxs("button", { type: "button", className: "cms:flex cms:min-w-0 cms:flex-1 cms:items-center cms:gap-2 cms:text-left", onClick: () => d(p.key), children: [
            /* @__PURE__ */ e.jsx("span", { className: "cms:truncate cms:font-medium", children: p.label || p.handle }),
            /* @__PURE__ */ e.jsx("code", { className: "cms:truncate cms:text-xs cms:text-muted-foreground", children: p.handle })
          ] }),
          p.required && /* @__PURE__ */ e.jsx(a.Badge, { variant: "outline", children: "Required" }),
          /* @__PURE__ */ e.jsx(a.Badge, { variant: "secondary", children: g?.label ?? p.type }),
          /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": "Move up", disabled: u === 0, onClick: () => t(U(s, u, -1)), children: /* @__PURE__ */ e.jsx(G, {}) }),
          /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": "Move down", disabled: u === s.length - 1, onClick: () => t(U(s, u, 1)), children: /* @__PURE__ */ e.jsx(W, {}) }),
          /* @__PURE__ */ e.jsx(
            a.Button,
            {
              type: "button",
              variant: "ghost",
              size: "icon",
              className: "cms:size-8 cms:text-destructive",
              "aria-label": `Remove ${p.label || p.handle}`,
              onClick: () => t(s.filter((v, T) => T !== u)),
              children: /* @__PURE__ */ e.jsx(I, {})
            }
          )
        ] }),
        b && /* @__PURE__ */ e.jsx(
          Qs,
          {
            id: h,
            field: p,
            type: g,
            siblings: s,
            onChange: (v) => x(u, v),
            types: l,
            errors: se(c, String(u)),
            depth: r
          }
        )
      ] }, p.key);
    }),
    /* @__PURE__ */ e.jsx("div", { children: /* @__PURE__ */ e.jsxs(a.DropdownMenu, { children: [
      /* @__PURE__ */ e.jsx(a.DropdownMenuTrigger, { asChild: !0, children: /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "outline", size: "sm", children: [
        /* @__PURE__ */ e.jsx(F, {}),
        " Add field"
      ] }) }),
      /* @__PURE__ */ e.jsx(a.DropdownMenuContent, { align: "start", children: l.filter((p) => !p.hasChildren || r < Is).map((p) => /* @__PURE__ */ e.jsx(a.DropdownMenuItem, { onSelect: () => o(p), children: p.label }, p.type)) })
    ] }) })
  ] });
}
const E = { default: "__default", yes: "yes", no: "no" };
function Qs({ id: s, field: t, type: l, siblings: c, onChange: r, types: n, errors: i, depth: m }) {
  const d = t.config ?? {};
  return /* @__PURE__ */ e.jsxs("div", { id: s, className: "cms:flex cms:flex-col cms:gap-4 cms:border-t cms:p-4", children: [
    !l && /* @__PURE__ */ e.jsxs("p", { className: "cms:text-sm cms:text-destructive", children: [
      'The field type "',
      t.type,
      `" isn't registered. Its data is kept; register the type or remove the field.`
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:grid cms:gap-4 cms:md:grid-cols-2", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${s}-label`, children: "Label" }),
        /* @__PURE__ */ e.jsx(a.Input, { id: `${s}-label`, value: t.label ?? "", onChange: (x) => r(Es(t, x.target.value, c)) }),
        /* @__PURE__ */ e.jsx(D, { message: i.label })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${s}-handle`, children: "Handle" }),
        /* @__PURE__ */ e.jsx(
          a.Input,
          {
            id: `${s}-handle`,
            className: "cms:font-mono",
            value: t.handle,
            onChange: (x) => r({ ...t, handle: x.target.value, handleTouched: !0 })
          }
        ),
        /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: "How templates read it. Renaming it hides values saved under the old one." }),
        /* @__PURE__ */ e.jsx(D, { message: i.handle })
      ] })
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
      /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${s}-instructions`, children: "Instructions" }),
      /* @__PURE__ */ e.jsx(
        a.Input,
        {
          id: `${s}-instructions`,
          value: t.instructions ?? "",
          placeholder: "Shown under the field to editors",
          onChange: (x) => r({ ...t, instructions: x.target.value || null })
        }
      ),
      /* @__PURE__ */ e.jsx(D, { message: i.instructions })
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-6", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx(a.Switch, { id: `${s}-required`, checked: t.required === !0, onCheckedChange: (x) => r({ ...t, required: x }) }),
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${s}-required`, children: "Required" })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${s}-translatable`, children: "Per language" }),
        /* @__PURE__ */ e.jsxs(
          a.Select,
          {
            value: t.translatable === !0 ? E.yes : t.translatable === !1 ? E.no : E.default,
            onValueChange: (x) => r({ ...t, translatable: x === E.yes ? !0 : x === E.no ? !1 : null }),
            children: [
              /* @__PURE__ */ e.jsx(a.SelectTrigger, { id: `${s}-translatable`, className: "cms:w-48", children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
              /* @__PURE__ */ e.jsxs(a.SelectContent, { children: [
                /* @__PURE__ */ e.jsx(a.SelectItem, { value: E.default, children: "As the type decides" }),
                /* @__PURE__ */ e.jsx(a.SelectItem, { value: E.yes, children: "Yes, translated" }),
                /* @__PURE__ */ e.jsx(a.SelectItem, { value: E.no, children: "No, shared" })
              ] })
            ]
          }
        )
      ] })
    ] }),
    (l?.settings.length ?? 0) > 0 && /* @__PURE__ */ e.jsx("div", { className: "cms:grid cms:gap-4 cms:md:grid-cols-2", children: l?.settings.map((x) => /* @__PURE__ */ e.jsx(
      q,
      {
        id: `${s}-config-${x.handle}`,
        field: x,
        value: d[x.handle],
        errors: P(i, `config.${x.handle}`),
        onChange: (o) => r({ ...t, config: { ...d, [x.handle]: o } })
      },
      x.handle
    )) }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
      /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${s}-validation`, children: "Extra rules" }),
      /* @__PURE__ */ e.jsx(
        a.Textarea,
        {
          id: `${s}-validation`,
          rows: 2,
          className: "cms:font-mono",
          placeholder: `min:3
regex:/^[A-Z]/`,
          value: (t.validation ?? []).join(`
`),
          onChange: (x) => r({ ...t, validation: x.target.value.split(`
`) })
        }
      ),
      /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: "Laravel rules, one per line." }),
      /* @__PURE__ */ e.jsx(D, { message: i.validation ?? Object.entries(i).find(([x]) => x.startsWith("validation."))?.[1] })
    ] }),
    l?.hasChildren && /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
      /* @__PURE__ */ e.jsx(a.Label, { children: "Fields inside" }),
      /* @__PURE__ */ e.jsx(
        as,
        {
          fields: t.fields,
          onChange: (x) => r({ ...t, fields: x }),
          types: n,
          errors: se(i, "fields"),
          depth: m + 1,
          idPrefix: s
        }
      ),
      /* @__PURE__ */ e.jsx(D, { message: i.fields })
    ] }),
    /* @__PURE__ */ e.jsx(D, { message: i.type })
  ] });
}
function et({ kind: s = "blueprint", blueprint: t, fieldTypes: l, usedBy: c = [] }) {
  const r = s === "fieldset" ? "Fieldset" : "Blueprint", n = S(s === "fieldset" ? "cms/fieldsets" : "cms/blueprints"), i = t ? t.title : `New ${r.toLowerCase()}`;
  a.useBreadcrumbs([{ label: `${r}s`, href: n }, { label: i }]);
  const [m, d] = K(), x = j.useForm({ title: t?.title ?? "", handle: t?.handle ?? "" }), [o, p] = w.useState(t !== null), [u, g] = w.useState(() => Qe(t?.fields)), b = (B) => l.find((z) => z.type === B)?.hasChildren === !0, h = x.errors, [v, T] = w.useState(() => JSON.stringify(u)), N = x.isDirty || JSON.stringify(u) !== v, k = () => {
    x.setDefaults(), T(JSON.stringify(u));
  }, f = () => {
    x.processing || (x.transform((B) => ({
      ...t ? { title: B.title } : B,
      fields: es(u, b)
    })), t ? x.put(`${n}/${t.id}`, { preserveScroll: !0, onSuccess: k }) : x.post(n, { onSuccess: k }));
  }, C = (B) => {
    B.preventDefault(), f();
  };
  a.useUnsavedChanges(N, f);
  const $ = () => t && m(
    `Delete the ${r.toLowerCase()} "${t.title}"? Content keeps its values.`,
    () => j.router.delete(`${n}/${t.id}`, { preserveScroll: !0 })
  );
  return /* @__PURE__ */ e.jsxs("form", { onSubmit: C, className: "cms:flex cms:max-w-4xl cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: i }),
    d,
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-4", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "icon", className: "cms:size-8 cms:shrink-0", asChild: !0, children: /* @__PURE__ */ e.jsx(j.Link, { href: n, "aria-label": `Back to ${r.toLowerCase()}s`, children: /* @__PURE__ */ e.jsx(Q, {}) }) }),
        /* @__PURE__ */ e.jsx("h1", { className: "cms:truncate cms:text-2xl cms:font-medium cms:tracking-tight", children: i })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
        t && /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "outline", size: "icon", className: "cms:text-destructive", "aria-label": `Delete ${i}`, onClick: $, children: /* @__PURE__ */ e.jsx(I, {}) }),
        /* @__PURE__ */ e.jsxs(a.Button, { type: "submit", disabled: x.processing, children: [
          x.processing && /* @__PURE__ */ e.jsx(ee, { className: "cms:animate-spin" }),
          t ? "Save" : `Create ${r.toLowerCase()}`
        ] })
      ] })
    ] }),
    /* @__PURE__ */ e.jsx(D, { message: h.blueprint ?? h.fieldset }),
    /* @__PURE__ */ e.jsx(a.Card, { children: /* @__PURE__ */ e.jsxs(a.CardContent, { className: "cms:grid cms:gap-4 cms:md:grid-cols-2", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "title", children: "Title" }),
        /* @__PURE__ */ e.jsx(a.Input, { id: "title", value: x.data.title, required: !0, onChange: (B) => {
          x.setData((z) => ({ ...z, title: B.target.value, handle: o ? z.handle : Xe(B.target.value) }));
        } }),
        /* @__PURE__ */ e.jsx(D, { message: h.title })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "handle", children: "Handle" }),
        /* @__PURE__ */ e.jsx(
          a.Input,
          {
            id: "handle",
            className: "cms:font-mono",
            value: x.data.handle,
            disabled: t !== null,
            required: !0,
            onChange: (B) => {
              p(!0), x.setData("handle", B.target.value);
            }
          }
        ),
        /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: t ? "Set when it was created." : "Lowercase letters, digits and underscores. It can't change later." }),
        /* @__PURE__ */ e.jsx(D, { message: h.handle })
      ] })
    ] }) }),
    c.length > 0 && /* @__PURE__ */ e.jsxs("p", { className: "cms:text-sm cms:text-muted-foreground", children: [
      "Used by ",
      c.join(", "),
      ". Changes show there at once."
    ] }),
    /* @__PURE__ */ e.jsxs("section", { className: "cms:flex cms:flex-col cms:gap-3", "aria-labelledby": "fields-heading", children: [
      /* @__PURE__ */ e.jsx("h2", { id: "fields-heading", className: "cms:text-lg cms:font-medium", children: "Fields" }),
      /* @__PURE__ */ e.jsx(as, { fields: u, onChange: g, types: l, errors: se(h, "fields") }),
      /* @__PURE__ */ e.jsx(D, { message: h.fields })
    ] })
  ] });
}
function ls({ noun: s, base: t, rows: l, intro: c }) {
  const r = `${s}s`;
  a.useBreadcrumbs([{ label: r }]);
  const [n, i] = K();
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: r }),
    i,
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-4", children: [
      /* @__PURE__ */ e.jsxs("div", { children: [
        /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: r }),
        /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: c })
      ] }),
      /* @__PURE__ */ e.jsx(a.Button, { asChild: !0, children: /* @__PURE__ */ e.jsxs(j.Link, { href: `${t}/create`, children: [
        /* @__PURE__ */ e.jsx(F, {}),
        " New ",
        s.toLowerCase()
      ] }) })
    ] }),
    /* @__PURE__ */ e.jsx(a.Card, { className: "cms:py-0", children: /* @__PURE__ */ e.jsxs(a.Table, { children: [
      /* @__PURE__ */ e.jsx(a.TableHeader, { children: /* @__PURE__ */ e.jsxs(a.TableRow, { children: [
        /* @__PURE__ */ e.jsx(a.TableHead, { children: "Title" }),
        /* @__PURE__ */ e.jsx(a.TableHead, { children: "Handle" }),
        /* @__PURE__ */ e.jsx(a.TableHead, { children: "Fields" }),
        l.some((m) => m.usedBy) && /* @__PURE__ */ e.jsx(a.TableHead, { children: "Used by" }),
        /* @__PURE__ */ e.jsx(a.TableHead, { className: "cms:w-12", children: /* @__PURE__ */ e.jsx("span", { className: "cms:sr-only", children: "Actions" }) })
      ] }) }),
      /* @__PURE__ */ e.jsxs(a.TableBody, { children: [
        l.length === 0 && /* @__PURE__ */ e.jsx(a.TableRow, { children: /* @__PURE__ */ e.jsxs(a.TableCell, { colSpan: 5, className: "cms:py-8 cms:text-center cms:text-muted-foreground", children: [
          "No ",
          r.toLowerCase(),
          " yet."
        ] }) }),
        l.map((m) => /* @__PURE__ */ e.jsxs(a.TableRow, { children: [
          /* @__PURE__ */ e.jsx(a.TableCell, { children: /* @__PURE__ */ e.jsx(j.Link, { href: `${t}/${m.id}/edit`, className: "cms:font-medium cms:hover:underline", children: m.title }) }),
          /* @__PURE__ */ e.jsx(a.TableCell, { children: /* @__PURE__ */ e.jsx("code", { className: "cms:text-xs", children: m.handle }) }),
          /* @__PURE__ */ e.jsx(a.TableCell, { children: m.fields }),
          m.usedBy && /* @__PURE__ */ e.jsx(a.TableCell, { className: "cms:text-muted-foreground", children: m.usedBy.join(", ") || "—" }),
          /* @__PURE__ */ e.jsx(a.TableCell, { children: /* @__PURE__ */ e.jsx(
            a.Button,
            {
              variant: "ghost",
              size: "icon",
              className: "cms:size-8 cms:text-destructive",
              "aria-label": `Delete ${m.title}`,
              onClick: () => n(
                `Delete the ${s.toLowerCase()} "${m.title}"? Content keeps its values.`,
                () => j.router.delete(`${t}/${m.id}`, { preserveScroll: !0 })
              ),
              children: /* @__PURE__ */ e.jsx(I, {})
            }
          ) })
        ] }, m.id))
      ] })
    ] }) })
  ] });
}
function st({ blueprints: s }) {
  const t = j.usePage().props.errors;
  return /* @__PURE__ */ e.jsxs(e.Fragment, { children: [
    /* @__PURE__ */ e.jsx(D, { message: t.blueprint }),
    /* @__PURE__ */ e.jsx(
      ls,
      {
        noun: "Blueprint",
        base: S("cms/blueprints"),
        rows: s,
        intro: "The fields editors fill in, for each kind of content."
      }
    )
  ] });
}
const tt = [
  { handle: "title", type: "text", label: "SEO title", instructions: "For search results and tabs. Empty: the title.", config: { max: 255 } },
  { handle: "description", type: "textarea", label: "Description", instructions: "A sentence or two for search results and shared links.", config: { max: 500 } },
  { handle: "image", type: "media", label: "Share image", instructions: "Shown when the page is shared.", config: { types: ["image"] } },
  { handle: "noindex", type: "toggle", label: "Hide from search engines" }
];
function at(s) {
  const { entry: t } = s;
  return /* @__PURE__ */ e.jsx(lt, { ...s }, t ? `${t.id}:${t.locale}:${t.version}:${t.status}:${t.trashed}:${t.publishedAt}` : "new");
}
function lt({ collection: s, fields: t, locales: l = [], shared: c = [], sharedValues: r = {}, entry: n, values: i, revisions: m, undoRestore: d = null, settings: x, can: o }) {
  const p = S(`cms/collections/${s.handle}/entries`), u = n ? S(`cms/entries/${n.id}`) : p, g = n ? i.title : "New entry";
  a.useBreadcrumbs([{ label: s.title, href: p }, { label: g }]);
  const [b, h] = K(), [v, T] = w.useState(!1), N = j.useForm({ ...i, seo: i.seo ?? {}, locale: n?.locale ?? null, version: n?.version ?? null, publish: !1, published_at: null }), k = n ? { locale: n.locale } : {}, f = l.find((y) => y.main)?.code, C = N.errors, $ = n !== null && n.status !== "draft" && !n.hasUnpublishedChanges && !N.isDirty, B = n === null ? o.create : o.update && !n.trashed, z = (y = !1, A = null) => {
    N.transform((H) => ({ ...H, publish: y, published_at: A })), n ? N.put(u, { preserveScroll: !0, onSuccess: () => T(!1) }) : N.post(u);
  }, os = () => {
    N.transform((y) => ({ ...y, version: null, publish: !1, published_at: null })), N.put(u, { preserveScroll: !0 });
  }, M = a.useEditLock({
    type: "cms.entry",
    id: n?.id,
    locale: n?.locale,
    enabled: n !== null && B,
    noun: "entry",
    leaveTo: p,
    getUnsaved: () => N.isDirty ? { title: N.data.title, slug: N.data.slug, data: N.data.data, seo: N.data.seo } : null
  }), J = B && !M.readOnly, ds = async (y) => {
    const A = await M.loadKept(y);
    N.setData((H) => ({ ...H, title: A.title ?? H.title, slug: A.slug ?? H.slug, data: A.data ?? H.data, seo: A.seo ?? H.seo }));
  };
  a.useUnsavedChanges(N.isDirty && !M.takenOver, J ? () => N.processing || z() : void 0);
  const oe = (y = null) => {
    o.update ? z(!0, y) : n && j.router.post(`${u}/publish`, { ...k, published_at: y }, { preserveScroll: !0, onSuccess: () => T(!1) });
  };
  return /* @__PURE__ */ e.jsxs("form", { onSubmit: (y) => {
    y.preventDefault(), z();
  }, className: "cms:flex cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: g }),
    h,
    M.dialogs,
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-4", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "icon", className: "cms:size-8 cms:shrink-0", asChild: !0, children: /* @__PURE__ */ e.jsx(j.Link, { href: p, "aria-label": `Back to ${s.title}`, children: /* @__PURE__ */ e.jsx(Q, {}) }) }),
        /* @__PURE__ */ e.jsx("h1", { className: "cms:truncate cms:text-2xl cms:font-medium cms:tracking-tight", children: g }),
        n && /* @__PURE__ */ e.jsx(a.Badge, { variant: n.status === "published" ? "default" : "secondary", children: n.trashed ? "In the trash" : V(n.status) }),
        N.isDirty && /* @__PURE__ */ e.jsx("span", { className: "cms:text-sm cms:text-muted-foreground", children: "Unsaved changes" })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
        n && !n.trashed && /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", asChild: !0, children: /* @__PURE__ */ e.jsxs(
          "a",
          {
            href: n.previewUrl,
            target: "_blank",
            rel: "noopener noreferrer",
            title: N.isDirty ? "Shows the saved draft: save first to see your changes." : "Shows the saved draft.",
            children: [
              /* @__PURE__ */ e.jsx(Ss, {}),
              " Preview"
            ]
          }
        ) }),
        n?.url && /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", asChild: !0, children: /* @__PURE__ */ e.jsxs("a", { href: n.url, target: "_blank", rel: "noopener noreferrer", children: [
          /* @__PURE__ */ e.jsx(ws, {}),
          " View"
        ] }) }),
        n?.trashed && o.delete && /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "outline", onClick: () => j.router.post(`${u}/restore`, {}, { preserveScroll: !0 }), children: [
          /* @__PURE__ */ e.jsx(Bs, {}),
          " Restore"
        ] }),
        n?.trashed && o.delete && /* @__PURE__ */ e.jsxs(
          a.Button,
          {
            type: "button",
            variant: "outline",
            className: "cms:text-destructive",
            onClick: () => b(`Delete "${i.title}" for good, in every language, with its revisions? This can't be undone.`, () => j.router.delete(`${u}/force`)),
            children: [
              /* @__PURE__ */ e.jsx(I, {}),
              " Delete for good"
            ]
          }
        ),
        n && !n.trashed && o.create && /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "ghost", title: "Copy every language as a new draft", onClick: () => j.router.post(`${u}/duplicate`), children: [
          /* @__PURE__ */ e.jsx(ks, {}),
          " Duplicate"
        ] }),
        n && !n.trashed && o.delete && !M.readOnly && /* @__PURE__ */ e.jsx(
          a.Button,
          {
            type: "button",
            variant: "outline",
            size: "icon",
            className: "cms:text-destructive",
            "aria-label": "Move to the trash",
            onClick: () => b(`Move "${i.title}" to the trash? It comes off the site; you can restore it.`, () => j.router.delete(u)),
            children: /* @__PURE__ */ e.jsx(I, {})
          }
        ),
        J && /* @__PURE__ */ e.jsxs(a.Button, { type: "submit", variant: n && o.publish ? "outline" : "default", disabled: N.processing, children: [
          N.processing && /* @__PURE__ */ e.jsx(ee, { className: "cms:animate-spin" }),
          n ? "Save draft" : "Create draft"
        ] }),
        n && !n.trashed && o.publish && !M.readOnly && /* @__PURE__ */ e.jsxs("div", { className: "cms:flex", children: [
          /* @__PURE__ */ e.jsx(a.Button, { type: "button", className: "cms:rounded-r-none", disabled: N.processing || $, onClick: () => oe(), children: n.status === "draft" ? "Publish" : $ ? "Published" : "Publish changes" }),
          /* @__PURE__ */ e.jsxs(a.DropdownMenu, { children: [
            /* @__PURE__ */ e.jsx(a.DropdownMenuTrigger, { asChild: !0, children: /* @__PURE__ */ e.jsx(a.Button, { type: "button", size: "icon", className: "cms:rounded-l-none cms:border-l cms:border-primary-foreground/20", "aria-label": "More publishing options", children: /* @__PURE__ */ e.jsx(De, {}) }) }),
            /* @__PURE__ */ e.jsxs(a.DropdownMenuContent, { align: "end", children: [
              /* @__PURE__ */ e.jsxs(a.DropdownMenuItem, { onSelect: () => T(!0), children: [
                /* @__PURE__ */ e.jsx(Cs, {}),
                " Publish on a date…"
              ] }),
              n.status !== "draft" && /* @__PURE__ */ e.jsxs(e.Fragment, { children: [
                /* @__PURE__ */ e.jsx(a.DropdownMenuSeparator, {}),
                /* @__PURE__ */ e.jsx(a.DropdownMenuItem, { onSelect: () => j.router.post(`${u}/unpublish`, k, { preserveScroll: !0 }), children: "Unpublish" })
              ] })
            ] })
          ] })
        ] })
      ] })
    ] }),
    n && l.length > 1 && /* @__PURE__ */ e.jsx(
      nt,
      {
        entryUrl: u,
        current: n.locale,
        locales: l,
        canAdd: o.create && !n.trashed,
        canRemove: o.delete && !n.isMain && !n.trashed,
        onRemove: () => b(
          `Remove the ${L(n.locale)} translation of "${i.title}"? Its revisions go with it.`,
          () => j.router.delete(`${u}/translations/${n.locale}`)
        )
      }
    ),
    /* @__PURE__ */ e.jsx(a.ViewingNotice, { by: M.viewingBy }),
    /* @__PURE__ */ e.jsx(a.KeptEditsNotice, { kept: M.kept, disabled: !J, onLoad: (y) => {
      ds(y);
    }, onDiscard: (y) => {
      M.discardKept(y);
    } }),
    C.version && /* @__PURE__ */ e.jsxs("div", { role: "alert", className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-3 cms:rounded-lg cms:border cms:border-destructive cms:p-3 cms:text-sm cms:text-destructive", children: [
      /* @__PURE__ */ e.jsx("span", { children: C.version }),
      /* @__PURE__ */ e.jsxs("span", { className: "cms:flex cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "outline", size: "sm", onClick: () => j.router.reload(), children: "Load theirs" }),
        /* @__PURE__ */ e.jsx(
          a.Button,
          {
            type: "button",
            variant: "destructive",
            size: "sm",
            disabled: N.processing,
            onClick: () => b("Save your version over theirs? Their changes are replaced (their earlier published versions stay in the revisions).", os),
            children: "Save mine anyway"
          }
        )
      ] })
    ] }),
    (C.entry || C.published_at) && /* @__PURE__ */ e.jsx("p", { role: "alert", className: "cms:text-sm cms:text-destructive", children: C.entry ?? C.published_at }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:grid cms:gap-6 cms:lg:grid-cols-[minmax(0,1fr)_18rem]", children: [
      /* @__PURE__ */ e.jsxs("fieldset", { disabled: !J, className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-6", children: [
        /* @__PURE__ */ e.jsx(a.Card, { children: /* @__PURE__ */ e.jsxs(a.CardContent, { className: "cms:grid cms:gap-4 cms:md:grid-cols-[2fr_1fr]", children: [
          /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "title", children: "Title" }),
            /* @__PURE__ */ e.jsx(
              a.Input,
              {
                id: "title",
                value: N.data.title,
                "aria-invalid": C.title !== void 0,
                onChange: (y) => N.setData("title", y.target.value)
              }
            ),
            /* @__PURE__ */ e.jsx(D, { message: C.title })
          ] }),
          /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
            /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "slug", children: "Slug" }),
            /* @__PURE__ */ e.jsx(
              a.Input,
              {
                id: "slug",
                className: "cms:font-mono",
                value: N.data.slug,
                placeholder: "Made from the title",
                "aria-invalid": C.slug !== void 0,
                onChange: (y) => N.setData("slug", y.target.value)
              }
            ),
            /* @__PURE__ */ e.jsx(D, { message: C.slug })
          ] })
        ] }) }),
        t.length > 0 && /* @__PURE__ */ e.jsx(a.Card, { children: /* @__PURE__ */ e.jsx(a.CardContent, { className: "cms:flex cms:flex-col cms:gap-6", children: t.map((y) => c.includes(y.handle) ? /* @__PURE__ */ e.jsxs("fieldset", { disabled: !0, className: "cms:flex cms:flex-col cms:gap-1", children: [
          /* @__PURE__ */ e.jsx(q, { id: `data-${y.handle}`, field: y, content: !0, value: r[y.handle], errors: {}, onChange: () => {
          } }),
          /* @__PURE__ */ e.jsxs("p", { className: "cms:text-sm cms:text-muted-foreground", children: [
            "Shared by all languages: edit it in ",
            f ? L(f) : "the main language",
            "."
          ] })
        ] }, y.handle) : /* @__PURE__ */ e.jsx(
          q,
          {
            id: `data-${y.handle}`,
            field: y,
            content: !0,
            value: N.data.data[y.handle],
            errors: P(C, `data.${y.handle}`),
            onChange: (A) => N.setData("data", { ...N.data.data, [y.handle]: A })
          },
          y.handle
        )) }) }),
        /* @__PURE__ */ e.jsxs(a.Card, { children: [
          /* @__PURE__ */ e.jsx(a.CardHeader, { children: /* @__PURE__ */ e.jsx(a.CardTitle, { className: "cms:text-base", children: "Search and sharing" }) }),
          /* @__PURE__ */ e.jsx(a.CardContent, { children: /* @__PURE__ */ e.jsx(
            te,
            {
              id: "seo",
              fields: tt,
              value: N.data.seo,
              errors: P(C, "seo"),
              onChange: (y) => N.setData("seo", y)
            }
          ) })
        ] })
      ] }),
      n && /* @__PURE__ */ e.jsxs("aside", { className: "cms:flex cms:flex-col cms:gap-4", children: [
        /* @__PURE__ */ e.jsxs(a.Card, { children: [
          /* @__PURE__ */ e.jsx(a.CardHeader, { children: /* @__PURE__ */ e.jsx(a.CardTitle, { className: "cms:text-base", children: "Status" }) }),
          /* @__PURE__ */ e.jsxs(a.CardContent, { className: "cms:flex cms:flex-col cms:gap-1 cms:text-sm", children: [
            /* @__PURE__ */ e.jsxs("p", { children: [
              V(n.status),
              n.status !== "draft" && n.publishedAt ? ` · ${X(n.publishedAt)}` : ""
            ] }),
            n.status !== "draft" && n.hasUnpublishedChanges && /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "The draft has changes the site doesn't show yet." })
          ] })
        ] }),
        x && /* @__PURE__ */ e.jsx(rt, { entryUrl: u, collection: s, settings: x, disabled: !J }),
        /* @__PURE__ */ e.jsxs(a.Card, { children: [
          /* @__PURE__ */ e.jsx(a.CardHeader, { children: /* @__PURE__ */ e.jsx(a.CardTitle, { className: "cms:text-base", children: "Revisions" }) }),
          /* @__PURE__ */ e.jsxs(a.CardContent, { className: "cms:flex cms:flex-col cms:gap-2 cms:text-sm", children: [
            d !== null && o.update && /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "outline", size: "sm", onClick: () => j.router.post(S(`cms/entry-translations/${d}/undo-restore`), {}, { preserveScroll: !0 }), children: [
              /* @__PURE__ */ e.jsx(zs, {}),
              " Undo the restore"
            ] }),
            m.length === 0 && /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "Each publish keeps one." }),
            m.map((y) => /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:justify-between cms:gap-2", children: [
              /* @__PURE__ */ e.jsxs("div", { className: "cms:min-w-0", children: [
                /* @__PURE__ */ e.jsx("div", { className: "cms:truncate", children: y.title }),
                /* @__PURE__ */ e.jsx("div", { className: "cms:text-xs cms:text-muted-foreground", children: X(y.createdAt) })
              ] }),
              o.update && !n.trashed && /* @__PURE__ */ e.jsx(
                a.Button,
                {
                  type: "button",
                  variant: "ghost",
                  size: "sm",
                  onClick: () => j.router.post(S(`cms/revisions/${y.id}/restore`), {}, { preserveScroll: !0 }),
                  children: "Restore"
                }
              )
            ] }, y.id))
          ] })
        ] })
      ] })
    ] }),
    /* @__PURE__ */ e.jsx(
      ct,
      {
        open: v,
        onClose: () => T(!1),
        processing: N.processing,
        current: n?.publishedAt ?? null,
        onSchedule: (y) => oe(y)
      }
    )
  ] });
}
function nt({ entryUrl: s, current: t, locales: l, canAdd: c, canRemove: r, onRemove: n }) {
  return /* @__PURE__ */ e.jsxs("nav", { "aria-label": "Languages", className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
    l.map((i) => {
      const m = `${L(i.code)}${i.main ? " (main)" : ""}`;
      return i.status === null ? c ? /* @__PURE__ */ e.jsxs(
        a.Button,
        {
          type: "button",
          variant: "outline",
          size: "sm",
          className: "cms:border-dashed",
          onClick: () => j.router.post(`${s}/translations`, { locale: i.code }),
          children: [
            /* @__PURE__ */ e.jsx(F, {}),
            " ",
            m
          ]
        },
        i.code
      ) : null : /* @__PURE__ */ e.jsxs(
        a.Button,
        {
          type: "button",
          size: "sm",
          variant: i.code === t ? "secondary" : "ghost",
          "aria-current": i.code === t ? "page" : void 0,
          onClick: () => i.code !== t && j.router.get(`${s}/edit`, { locale: i.code }),
          children: [
            m,
            /* @__PURE__ */ e.jsx(a.Badge, { variant: i.status === "published" ? "default" : "outline", className: "cms:ml-1", children: V(i.status) })
          ]
        },
        i.code
      );
    }),
    r && /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "sm", className: "cms:ml-auto cms:text-destructive", onClick: n, children: "Remove this language" })
  ] });
}
function ct({ open: s, onClose: t, onSchedule: l, current: c, processing: r }) {
  const [n, i] = w.useState("");
  return w.useEffect(() => {
    s && i(Us(c ? new Date(c) : new Date(Date.now() + 864e5)));
  }, [s, c]), /* @__PURE__ */ e.jsx(a.Dialog, { open: s, onOpenChange: (m) => !m && t(), children: /* @__PURE__ */ e.jsxs(a.DialogContent, { children: [
    /* @__PURE__ */ e.jsxs(a.DialogHeader, { children: [
      /* @__PURE__ */ e.jsx(a.DialogTitle, { children: "Publish on a date" }),
      /* @__PURE__ */ e.jsx(a.DialogDescription, { children: "The entry goes live then; a date in the past publishes it now with that date." })
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
      /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "published-at", children: "Date and time" }),
      /* @__PURE__ */ e.jsx(a.Input, { id: "published-at", type: "datetime-local", value: n, onChange: (m) => i(m.target.value) }),
      /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: "In your time zone." })
    ] }),
    /* @__PURE__ */ e.jsxs(a.DialogFooter, { children: [
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "outline", onClick: t, children: "Cancel" }),
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", disabled: n === "" || r, onClick: () => l(new Date(n).toISOString()), children: "Publish" })
    ] })
  ] }) });
}
function rt({ entryUrl: s, collection: t, settings: l, disabled: c }) {
  const r = j.useForm({ parent_id: l.parentId, template: l.template ?? "" }), n = r.errors;
  return /* @__PURE__ */ e.jsxs(a.Card, { children: [
    /* @__PURE__ */ e.jsx(a.CardHeader, { children: /* @__PURE__ */ e.jsx(a.CardTitle, { className: "cms:text-base", children: "Page settings" }) }),
    /* @__PURE__ */ e.jsx(a.CardContent, { children: /* @__PURE__ */ e.jsxs("fieldset", { disabled: c, className: "cms:flex cms:flex-col cms:gap-4 cms:text-sm", children: [
      t.hierarchical && /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "parent", children: "Parent" }),
        /* @__PURE__ */ e.jsx(
          ie,
          {
            id: "parent",
            multiple: !1,
            max: 1,
            collections: [t.handle],
            invalid: n.parent_id !== void 0,
            value: r.data.parent_id,
            onChange: (i) => r.setData("parent_id", typeof i == "number" ? i : null)
          }
        ),
        /* @__PURE__ */ e.jsx(D, { message: n.parent_id })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "template", children: "Template" }),
        /* @__PURE__ */ e.jsx(
          a.Input,
          {
            id: "template",
            className: "cms:font-mono",
            placeholder: "The collection's",
            value: r.data.template,
            onChange: (i) => r.setData("template", i.target.value),
            "aria-invalid": n.template !== void 0
          }
        ),
        /* @__PURE__ */ e.jsx(D, { message: n.template })
      ] }),
      r.isDirty && /* @__PURE__ */ e.jsx(
        a.Button,
        {
          type: "button",
          size: "sm",
          disabled: r.processing,
          onClick: () => r.put(`${s}/settings`, { preserveScroll: !0, onSuccess: () => r.setDefaults() }),
          children: "Save settings"
        }
      )
    ] }) })
  ] });
}
function it({ collection: s, collections: t, entries: l, query: c, can: r }) {
  a.useBreadcrumbs([{ label: "Entries", href: S("cms/entries") }, ...s ? [{ label: s.title }] : []]);
  const [n, i] = w.useState(c.search), m = s ? S(`cms/collections/${s.handle}/entries`) : "", [d, x] = w.useState([]), [o, p] = K();
  w.useEffect(() => x([]), [l]);
  const u = c.trashed ? r?.delete ? [{ action: "restore", label: "Restore" }, { action: "delete", label: "Delete for good", danger: !0 }] : [] : [
    ...r?.publish ? [{ action: "publish", label: "Publish" }, { action: "unpublish", label: "Unpublish" }] : [],
    ...r?.delete ? [{ action: "trash", label: "Move to the trash", danger: !0 }] : []
  ], g = (h, v, T = !1) => {
    const N = () => j.router.post(`${m}/bulk`, { action: h, ids: d }, { preserveScroll: !0 });
    T ? o(`${v}: ${d.length} ${d.length === 1 ? "entry" : "entries"}?${h === "delete" ? " This can't be undone." : ""}`, N) : N();
  }, b = (h) => {
    const v = { ...c, ...h };
    j.router.get(
      m,
      { ...v.search ? { search: v.search } : {}, ...v.trashed ? { trashed: 1 } : {} },
      { preserveState: !0, preserveScroll: !0, replace: !0 }
    );
  };
  return w.useEffect(() => {
    if (n === c.search) return;
    const h = window.setTimeout(() => b({ search: n }), 300);
    return () => window.clearTimeout(h);
  }, [n]), !s || !l ? /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-4", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: "Entries" }),
    /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: "Entries" }),
    /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "There are no collections yet. Create one under Structure → Collections, then come back to write its entries." })
  ] }) : /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: s.title }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-4", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: s.title }),
        t.length > 1 && /* @__PURE__ */ e.jsxs(a.Select, { value: s.handle, onValueChange: (h) => j.router.get(S(`cms/collections/${h}/entries`)), children: [
          /* @__PURE__ */ e.jsx(a.SelectTrigger, { className: "cms:w-48", "aria-label": "Collection", children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
          /* @__PURE__ */ e.jsx(a.SelectContent, { children: t.map((h) => /* @__PURE__ */ e.jsx(a.SelectItem, { value: h.handle, children: h.title }, h.handle)) })
        ] })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
        l.total > 0 && !c.trashed && /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", asChild: !0, children: /* @__PURE__ */ e.jsxs("a", { href: `${m}/export`, children: [
          /* @__PURE__ */ e.jsx(Ie, {}),
          " Export CSV"
        ] }) }),
        r?.create && !c.trashed && /* @__PURE__ */ e.jsx(a.Button, { asChild: !0, children: /* @__PURE__ */ e.jsxs(j.Link, { href: `${m}/create`, children: [
          /* @__PURE__ */ e.jsx(F, {}),
          " New entry"
        ] }) })
      ] })
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-3", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:relative cms:w-full cms:max-w-sm", children: [
        /* @__PURE__ */ e.jsx(qe, { className: "cms:absolute cms:top-1/2 cms:left-3 cms:size-4 cms:-translate-y-1/2 cms:text-muted-foreground" }),
        /* @__PURE__ */ e.jsx(
          a.Input,
          {
            className: "cms:pl-9",
            placeholder: "Search titles",
            "aria-label": "Search titles",
            value: n,
            onChange: (h) => i(h.target.value)
          }
        )
      ] }),
      /* @__PURE__ */ e.jsx(a.Button, { variant: c.trashed ? "secondary" : "ghost", size: "sm", onClick: () => b({ trashed: !c.trashed }), children: c.trashed ? "Back to entries" : "Trash" }),
      d.length > 0 && u.length > 0 && /* @__PURE__ */ e.jsxs("div", { role: "toolbar", "aria-label": "Selected entries", className: "cms:ml-auto cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
        /* @__PURE__ */ e.jsxs("span", { className: "cms:text-sm cms:text-muted-foreground", children: [
          d.length,
          " selected"
        ] }),
        u.map((h) => /* @__PURE__ */ e.jsx(a.Button, { size: "sm", variant: h.danger ? "destructive" : "outline", onClick: () => g(h.action, h.label, h.danger), children: h.label }, h.action))
      ] })
    ] }),
    p,
    /* @__PURE__ */ e.jsx(a.Card, { className: "cms:py-0", children: /* @__PURE__ */ e.jsxs(a.Table, { children: [
      /* @__PURE__ */ e.jsx(a.TableHeader, { children: /* @__PURE__ */ e.jsxs(a.TableRow, { children: [
        u.length > 0 && /* @__PURE__ */ e.jsx(a.TableHead, { className: "cms:w-10", children: /* @__PURE__ */ e.jsx(
          a.Checkbox,
          {
            "aria-label": "Select all on this page",
            checked: l.data.length > 0 && d.length === l.data.length,
            onCheckedChange: (h) => x(h ? l.data.map((v) => v.id) : [])
          }
        ) }),
        /* @__PURE__ */ e.jsx(a.TableHead, { children: "Title" }),
        /* @__PURE__ */ e.jsx(a.TableHead, { children: "Status" }),
        /* @__PURE__ */ e.jsx(a.TableHead, { children: s.dated ? "Date" : "Updated" })
      ] }) }),
      /* @__PURE__ */ e.jsxs(a.TableBody, { children: [
        l.data.length === 0 && /* @__PURE__ */ e.jsx(a.TableRow, { children: /* @__PURE__ */ e.jsx(a.TableCell, { colSpan: u.length > 0 ? 4 : 3, className: "cms:py-8 cms:text-center cms:text-muted-foreground", children: c.trashed ? "The trash is empty." : c.search ? "Nothing matches." : "No entries yet." }) }),
        l.data.map((h) => /* @__PURE__ */ e.jsxs(a.TableRow, { "data-state": d.includes(h.id) ? "selected" : void 0, children: [
          u.length > 0 && /* @__PURE__ */ e.jsx(a.TableCell, { children: /* @__PURE__ */ e.jsx(
            a.Checkbox,
            {
              "aria-label": `Select ${h.title}`,
              checked: d.includes(h.id),
              onCheckedChange: (v) => x(v ? [...d, h.id] : d.filter((T) => T !== h.id))
            }
          ) }),
          /* @__PURE__ */ e.jsxs(a.TableCell, { children: [
            /* @__PURE__ */ e.jsx(j.Link, { href: S(`cms/entries/${h.id}/edit`), className: "cms:font-medium cms:hover:underline", children: h.title }),
            /* @__PURE__ */ e.jsxs("div", { className: "cms:text-xs cms:text-muted-foreground", children: [
              "/",
              h.slug,
              h.parent ? ` · under ${h.parent}` : ""
            ] })
          ] }),
          /* @__PURE__ */ e.jsx(a.TableCell, { children: /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-1", children: [
            /* @__PURE__ */ e.jsx(a.Badge, { variant: h.status === "published" ? "default" : "secondary", children: V(h.status) }),
            h.locales.length > 1 && h.locales.filter((v) => !v.main).map((v) => /* @__PURE__ */ e.jsx(
              a.Badge,
              {
                variant: v.status === "published" ? "default" : "outline",
                className: v.status === null ? "cms:opacity-50" : "",
                title: `${v.code}: ${v.status === null ? "not translated" : V(v.status)}`,
                children: v.code.toUpperCase()
              },
              v.code
            ))
          ] }) }),
          /* @__PURE__ */ e.jsx(a.TableCell, { className: "cms:text-muted-foreground", children: /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:justify-between cms:gap-2", children: [
            X(s.dated ? h.publishedAt : h.updatedAt),
            s.ordered && r?.update && !c.trashed && !c.search && /* @__PURE__ */ e.jsxs("span", { className: "cms:flex", children: [
              /* @__PURE__ */ e.jsx(a.Button, { variant: "ghost", size: "icon", "aria-label": `Move ${h.title} up`, onClick: () => j.router.post(S(`cms/entries/${h.id}/move`), { direction: "up" }, { preserveScroll: !0 }), children: /* @__PURE__ */ e.jsx(G, {}) }),
              /* @__PURE__ */ e.jsx(a.Button, { variant: "ghost", size: "icon", "aria-label": `Move ${h.title} down`, onClick: () => j.router.post(S(`cms/entries/${h.id}/move`), { direction: "down" }, { preserveScroll: !0 }), children: /* @__PURE__ */ e.jsx(W, {}) })
            ] })
          ] }) })
        ] }, h.id))
      ] })
    ] }) }),
    l.total > 0 && /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:justify-between cms:text-sm cms:text-muted-foreground", children: [
      /* @__PURE__ */ e.jsxs("span", { children: [
        l.from,
        "–",
        l.to,
        " of ",
        l.total
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(
          a.Button,
          {
            variant: "outline",
            size: "icon",
            "aria-label": "Previous page",
            disabled: !l.prev_page_url,
            onClick: () => l.prev_page_url && j.router.get(l.prev_page_url, {}, { preserveScroll: !0 }),
            children: /* @__PURE__ */ e.jsx(Te, {})
          }
        ),
        /* @__PURE__ */ e.jsx(
          a.Button,
          {
            variant: "outline",
            size: "icon",
            "aria-label": "Next page",
            disabled: !l.next_page_url,
            onClick: () => l.next_page_url && j.router.get(l.next_page_url, {}, { preserveScroll: !0 }),
            children: /* @__PURE__ */ e.jsx(re, {})
          }
        )
      ] })
    ] })
  ] });
}
function ot({ fieldsets: s }) {
  const t = j.usePage().props.errors;
  return /* @__PURE__ */ e.jsxs(e.Fragment, { children: [
    /* @__PURE__ */ e.jsx(D, { message: t.fieldset }),
    /* @__PURE__ */ e.jsx(
      ls,
      {
        noun: "Fieldset",
        base: S("cms/fieldsets"),
        rows: s,
        intro: "Fields shared between blueprints, and the kinds of block of flexible fields."
      }
    )
  ] });
}
function dt(s) {
  return /* @__PURE__ */ e.jsx(mt, { ...s }, `${s.locale}:${JSON.stringify(s.values)}`);
}
function mt({ set: s, fields: t, locale: l, locales: c, shared: r, values: n, can: i }) {
  const m = S(`cms/globals/${s.handle}`);
  a.useBreadcrumbs([{ label: "Globals", href: S("cms/globals") }, { label: s.title }]);
  const d = j.useForm({ locale: l, data: n }), x = d.errors, o = c[0], p = a.useEditLock({
    type: "cms.global",
    id: s.id,
    locale: l,
    enabled: i.update,
    noun: "global set",
    leaveTo: S("cms/globals"),
    getUnsaved: () => d.isDirty ? { data: d.data.data } : null
  }), u = i.update && !p.readOnly, g = () => d.processing || d.put(m, { preserveScroll: !0 }), b = async (h) => {
    const v = await p.loadKept(h);
    v.data && typeof v.data == "object" && d.setData("data", v.data);
  };
  return a.useUnsavedChanges(d.isDirty && !p.takenOver, u ? g : void 0), /* @__PURE__ */ e.jsxs("form", { onSubmit: (h) => {
    h.preventDefault(), g();
  }, className: "cms:flex cms:max-w-3xl cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: s.title }),
    p.dialogs,
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-4", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "icon", className: "cms:size-8 cms:shrink-0", asChild: !0, children: /* @__PURE__ */ e.jsx(j.Link, { href: S("cms/globals"), "aria-label": "Back to globals", children: /* @__PURE__ */ e.jsx(Q, {}) }) }),
        /* @__PURE__ */ e.jsx("h1", { className: "cms:truncate cms:text-2xl cms:font-medium cms:tracking-tight", children: s.title }),
        d.isDirty && /* @__PURE__ */ e.jsx("span", { className: "cms:text-sm cms:text-muted-foreground", children: "Unsaved changes" })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-2", children: [
        c.length > 1 && /* @__PURE__ */ e.jsxs(a.Select, { value: l, onValueChange: (h) => j.router.get(m, { locale: h }), children: [
          /* @__PURE__ */ e.jsx(a.SelectTrigger, { className: "cms:w-48", "aria-label": "Language", children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
          /* @__PURE__ */ e.jsx(a.SelectContent, { children: c.map((h, v) => /* @__PURE__ */ e.jsxs(a.SelectItem, { value: h, children: [
            L(h),
            v === 0 ? " (main)" : ""
          ] }, h)) })
        ] }),
        u && /* @__PURE__ */ e.jsxs(a.Button, { type: "submit", disabled: d.processing, children: [
          d.processing && /* @__PURE__ */ e.jsx(ee, { className: "cms:animate-spin" }),
          " Save"
        ] })
      ] })
    ] }),
    t.length === 0 ? /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "This global set has no fields: choose a blueprint for it under Structure → Global sets." }) : /* @__PURE__ */ e.jsxs("fieldset", { disabled: !u, className: "cms:flex cms:flex-col cms:gap-4", children: [
      /* @__PURE__ */ e.jsx(a.ViewingNotice, { by: p.viewingBy }),
      /* @__PURE__ */ e.jsx(a.KeptEditsNotice, { kept: p.kept, disabled: !u, onLoad: (h) => {
        b(h);
      }, onDiscard: (h) => {
        p.discardKept(h);
      } }),
      /* @__PURE__ */ e.jsx(a.Card, { children: /* @__PURE__ */ e.jsx(a.CardContent, { className: "cms:flex cms:flex-col cms:gap-6", children: t.map((h) => r.includes(h.handle) ? /* @__PURE__ */ e.jsxs("fieldset", { disabled: !0, className: "cms:flex cms:flex-col cms:gap-1", children: [
        /* @__PURE__ */ e.jsx(q, { id: `data-${h.handle}`, field: h, content: !0, value: n[h.handle], errors: {}, onChange: () => {
        } }),
        /* @__PURE__ */ e.jsxs("p", { className: "cms:text-sm cms:text-muted-foreground", children: [
          "Shared by all languages: edit it in ",
          o ? L(o) : "the main language",
          "."
        ] })
      ] }, h.handle) : /* @__PURE__ */ e.jsx(
        q,
        {
          id: `data-${h.handle}`,
          field: h,
          content: !0,
          value: d.data.data[h.handle],
          errors: P(x, `data.${h.handle}`),
          onChange: (v) => d.setData("data", { ...d.data.data, [h.handle]: v })
        },
        h.handle
      )) }) })
    ] })
  ] });
}
function ut({ sets: s }) {
  return a.useBreadcrumbs([{ label: "Globals" }]), /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: "Globals" }),
    /* @__PURE__ */ e.jsxs("div", { children: [
      /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: "Globals" }),
      /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: "Values the whole site uses, like contact details and footer text." })
    ] }),
    s.length === 0 && /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "There are no global sets yet. Create one under Structure → Global sets." }),
    /* @__PURE__ */ e.jsx("div", { className: "cms:grid cms:gap-4 cms:sm:grid-cols-2 cms:lg:grid-cols-3", children: s.map((t) => /* @__PURE__ */ e.jsx(j.Link, { href: S(`cms/globals/${t.handle}`), className: "cms:rounded-xl cms:focus-visible:ring-2 cms:focus-visible:ring-ring cms:focus-visible:outline-none", children: /* @__PURE__ */ e.jsxs(a.Card, { className: "cms:h-full cms:transition-colors cms:hover:bg-accent", children: [
      /* @__PURE__ */ e.jsxs(a.CardHeader, { children: [
        /* @__PURE__ */ e.jsx(a.CardTitle, { className: "cms:text-base", children: t.title }),
        /* @__PURE__ */ e.jsx(a.CardDescription, { children: t.blueprint ? /* @__PURE__ */ e.jsx("code", { className: "cms:text-xs", children: t.handle }) : "No blueprint yet: choose one under Structure → Global sets." })
      ] }),
      /* @__PURE__ */ e.jsx(a.CardContent, {})
    ] }) }, t.handle)) })
  ] });
}
let ns = 0;
function cs(s, t = 0) {
  return s.flatMap(({ children: l, ...c }) => [{ ...c, key: `m${++ns}`, depth: t }, ...cs(l ?? [], t + 1)]);
}
function ht(s) {
  const t = [], l = [{ depth: -1, children: t }];
  for (const { key: c, depth: r, ...n } of s) {
    for (; l.length > 1 && l[l.length - 1].depth >= r; ) l.pop();
    const i = { ...n, children: [] };
    l[l.length - 1].children.push(i), l.push({ depth: r, children: i.children });
  }
  return t;
}
function Y(s, t) {
  const l = s[t]?.depth ?? 0;
  let c = t + 1;
  for (; c < s.length && s[c].depth > l; ) c++;
  return c;
}
function rs(s, t, l) {
  const c = Y(s, t);
  return s.map((r, n) => n >= t && n < c ? { ...r, depth: r.depth + l } : r);
}
function xt(s, t, l) {
  const c = s[t], r = s[t - 1];
  return !c || !r || c.depth > r.depth || Math.max(...s.slice(t, Y(s, t)).map((i) => i.depth)) + 1 > l - 1 ? s : rs(s, t, 1);
}
function pt(s, t) {
  return (s[t]?.depth ?? 0) === 0 ? s : rs(s, t, -1);
}
function ge(s, t, l) {
  const c = s[t];
  if (!c) return s;
  const r = Y(s, t), n = s.slice(t, r);
  if (l === -1) {
    let d = t - 1;
    for (; d >= 0 && s[d].depth > c.depth; ) d--;
    return d < 0 || s[d].depth !== c.depth ? s : [...s.slice(0, d), ...n, ...s.slice(d, t), ...s.slice(r)];
  }
  const i = s[r];
  if (!i || i.depth !== c.depth) return s;
  const m = Y(s, r);
  return [...s.slice(0, t), ...s.slice(r, m), ...n, ...s.slice(m)];
}
function gt(s, t) {
  return [...s.slice(0, t), ...s.slice(Y(s, t))];
}
function je(s) {
  return { key: `m${++ns}`, depth: 0, entry_id: null, entry: null, url: null, labels: {}, new_tab: !1, ...s };
}
function jt({ menu: s, items: t, locales: l, limits: c, can: r }) {
  const n = S(`cms/menus/${s.handle}`);
  a.useBreadcrumbs([{ label: "Menus", href: S("cms/menus") }, { label: s.title }]);
  const [i, m] = w.useState(() => cs(t)), [d, x] = w.useState(null), o = j.useForm({ items: t }), p = o.errors, [u, g] = w.useState(!1), b = l[0] ?? "en", h = (k) => {
    m(k), g(!0);
  }, v = (k, f) => h(i.map((C, $) => $ === k ? { ...C, ...f } : C)), T = () => {
    o.processing || (o.transform(() => ({ items: ht(i).map(is) })), o.put(n, { preserveScroll: !0, onSuccess: () => g(!1) }));
  }, N = (k) => {
    k.preventDefault(), T();
  };
  return a.useUnsavedChanges(u, r.update ? T : void 0), /* @__PURE__ */ e.jsxs("form", { onSubmit: N, className: "cms:flex cms:max-w-4xl cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: s.title }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-4", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", size: "icon", className: "cms:size-8 cms:shrink-0", asChild: !0, children: /* @__PURE__ */ e.jsx(j.Link, { href: S("cms/menus"), "aria-label": "Back to menus", children: /* @__PURE__ */ e.jsx(Q, {}) }) }),
        /* @__PURE__ */ e.jsx("h1", { className: "cms:truncate cms:text-2xl cms:font-medium cms:tracking-tight", children: s.title }),
        u && /* @__PURE__ */ e.jsx("span", { className: "cms:text-sm cms:text-muted-foreground", children: "Unsaved changes" })
      ] }),
      r.update && /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:gap-2", children: [
        /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "outline", disabled: i.length >= c.items, onClick: () => x("entry"), children: [
          /* @__PURE__ */ e.jsx(F, {}),
          " Entry"
        ] }),
        /* @__PURE__ */ e.jsxs(a.Button, { type: "button", variant: "outline", disabled: i.length >= c.items, onClick: () => x("url"), children: [
          /* @__PURE__ */ e.jsx($s, {}),
          " Link"
        ] }),
        /* @__PURE__ */ e.jsxs(a.Button, { type: "submit", disabled: o.processing, children: [
          o.processing && /* @__PURE__ */ e.jsx(ee, { className: "cms:animate-spin" }),
          " Save"
        ] })
      ] })
    ] }),
    /* @__PURE__ */ e.jsx(D, { message: p.items }),
    Object.keys(p).some((k) => k.startsWith("items.")) && /* @__PURE__ */ e.jsxs("p", { role: "alert", className: "cms:text-sm cms:text-destructive", children: [
      "Some links need fixing: ",
      Object.values(p).filter(Boolean)[0]
    ] }),
    /* @__PURE__ */ e.jsx(a.Card, { className: "cms:py-0", children: /* @__PURE__ */ e.jsxs("ul", { className: "cms:divide-y", children: [
      i.length === 0 && /* @__PURE__ */ e.jsx("li", { className: "cms:p-6 cms:text-center cms:text-muted-foreground", children: "No links yet. Add an entry or a link." }),
      i.map((k, f) => /* @__PURE__ */ e.jsxs("li", { className: "cms:flex cms:flex-col cms:gap-2 cms:p-3", style: { paddingLeft: `${0.75 + k.depth * 2}rem` }, children: [
        /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-2", children: [
          /* @__PURE__ */ e.jsx(
            a.Input,
            {
              "aria-label": `Label (${L(b)})`,
              className: "cms:max-w-xs",
              disabled: !r.update,
              placeholder: k.entry?.title ?? "Label",
              value: k.labels[b] ?? "",
              onChange: (C) => v(f, { labels: { ...k.labels, [b]: C.target.value } })
            }
          ),
          /* @__PURE__ */ e.jsx("span", { className: "cms:min-w-0 cms:flex-1 cms:truncate cms:text-sm cms:text-muted-foreground", children: k.entry_id !== null ? /* @__PURE__ */ e.jsxs(e.Fragment, { children: [
            k.entry?.collection,
            ": ",
            k.entry?.title ?? `Entry #${k.entry_id}`,
            " ",
            k.entry && !k.entry.live && /* @__PURE__ */ e.jsx(a.Badge, { variant: "outline", children: "Not on the site" })
          ] }) : k.url }),
          r.update && /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center", children: [
            /* @__PURE__ */ e.jsxs("label", { className: "cms:mr-2 cms:flex cms:items-center cms:gap-1.5 cms:text-sm", children: [
              /* @__PURE__ */ e.jsx(a.Checkbox, { checked: k.new_tab, onCheckedChange: (C) => v(f, { new_tab: C }) }),
              " New tab"
            ] }),
            /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": "Out a level", disabled: k.depth === 0, onClick: () => h(pt(i, f)), children: /* @__PURE__ */ e.jsx(Ds, {}) }),
            /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": "In a level", onClick: () => h(xt(i, f, c.depth)), children: /* @__PURE__ */ e.jsx(_s, {}) }),
            /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": "Move up", onClick: () => h(ge(i, f, -1)), children: /* @__PURE__ */ e.jsx(G, {}) }),
            /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": "Move down", onClick: () => h(ge(i, f, 1)), children: /* @__PURE__ */ e.jsx(W, {}) }),
            /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", size: "icon", className: "cms:size-8 cms:text-destructive", "aria-label": "Remove, with what's under it", onClick: () => h(gt(i, f)), children: /* @__PURE__ */ e.jsx(I, {}) })
          ] })
        ] }),
        l.length > 1 && /* @__PURE__ */ e.jsx("div", { className: "cms:flex cms:flex-wrap cms:gap-2", children: l.slice(1).map((C) => /* @__PURE__ */ e.jsx(
          a.Input,
          {
            "aria-label": `Label (${L(C)})`,
            className: "cms:max-w-xs",
            disabled: !r.update,
            placeholder: `${L(C)}: as ${L(b)}`,
            value: k.labels[C] ?? "",
            onChange: ($) => v(f, { labels: { ...k.labels, [C]: $.target.value } })
          },
          C
        )) })
      ] }, k.key))
    ] }) }),
    /* @__PURE__ */ e.jsx(ft, { kind: d, onClose: () => x(null), onAdd: (k) => {
      h([...i, k]), x(null);
    }, main: b })
  ] });
}
function is({ entry: s, children: t, ...l }) {
  return { ...l, children: t.map(is) };
}
function ft({ kind: s, onClose: t, onAdd: l, main: c }) {
  const [r, n] = w.useState(null), [i, m] = w.useState(""), [d, x] = w.useState("");
  w.useEffect(() => {
    n(null), m(""), x("");
  }, [s]);
  const o = i.trim() === "" || Ps(i.trim()), p = async () => {
    if (s === "entry" && r !== null) {
      const u = await fetch(`${S("cms/entry-picker")}?${ss({ ids: [r], collections: [] })}`, { headers: { Accept: "application/json" }, credentials: "same-origin" }), g = u.ok ? (await u.json()).items[0] : void 0;
      l(je({ entry_id: r, entry: g ? { title: g.title, collection: g.collection, live: g.status === "published" } : null, labels: d ? { [c]: d } : {} }));
    } else s === "url" && i.trim() !== "" && o && d.trim() !== "" && l(je({ url: i.trim(), labels: { [c]: d.trim() } }));
  };
  return /* @__PURE__ */ e.jsx(a.Dialog, { open: s !== null, onOpenChange: (u) => !u && t(), children: /* @__PURE__ */ e.jsxs(a.DialogContent, { children: [
    /* @__PURE__ */ e.jsxs(a.DialogHeader, { children: [
      /* @__PURE__ */ e.jsx(a.DialogTitle, { children: s === "entry" ? "Link to an entry" : "Link to an address" }),
      /* @__PURE__ */ e.jsx(a.DialogDescription, { children: s === "entry" ? "It follows the entry: its title (unless you give a label) and its address in each language. It shows only while the entry is on the site." : "A web address, a path on this site (/contact), mailto: or tel:." })
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-3", children: [
      s === "entry" ? /* @__PURE__ */ e.jsx(
        ie,
        {
          id: "menu-entry",
          value: r,
          multiple: !1,
          max: null,
          collections: [],
          invalid: !1,
          onChange: (u) => n(typeof u == "number" ? u : null)
        }
      ) : /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(a.Label, { htmlFor: "menu-url", children: "Address" }),
        /* @__PURE__ */ e.jsx(
          a.Input,
          {
            id: "menu-url",
            value: i,
            placeholder: "https://… or /contact",
            "aria-invalid": !o,
            onChange: (u) => m(u.target.value)
          }
        ),
        !o && /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-destructive", children: "This address could run code; use https://, a path, mailto: or tel:." })
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2", children: [
        /* @__PURE__ */ e.jsxs(a.Label, { htmlFor: "menu-label", children: [
          "Label",
          s === "entry" ? " (optional)" : ""
        ] }),
        /* @__PURE__ */ e.jsx(a.Input, { id: "menu-label", value: d, onChange: (u) => x(u.target.value) })
      ] })
    ] }),
    /* @__PURE__ */ e.jsxs(a.DialogFooter, { children: [
      /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "outline", onClick: t, children: "Cancel" }),
      /* @__PURE__ */ e.jsx(
        a.Button,
        {
          type: "button",
          onClick: () => {
            p();
          },
          disabled: s === "entry" ? r === null : i.trim() === "" || !o || d.trim() === "",
          children: "Add"
        }
      )
    ] })
  ] }) });
}
function bt({ menus: s }) {
  return a.useBreadcrumbs([{ label: "Menus" }]), /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: "Menus" }),
    /* @__PURE__ */ e.jsxs("div", { children: [
      /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: "Menus" }),
      /* @__PURE__ */ e.jsx("p", { className: "cms:text-sm cms:text-muted-foreground", children: "Navigation for the site: the main menu, the footer…" })
    ] }),
    s.length === 0 && /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "There are no menus yet. Create one under Structure → Menus." }),
    /* @__PURE__ */ e.jsx("div", { className: "cms:grid cms:gap-4 cms:sm:grid-cols-2 cms:lg:grid-cols-3", children: s.map((t) => /* @__PURE__ */ e.jsx(j.Link, { href: S(`cms/menus/${t.handle}`), className: "cms:rounded-xl cms:focus-visible:ring-2 cms:focus-visible:ring-ring cms:focus-visible:outline-none", children: /* @__PURE__ */ e.jsxs(a.Card, { className: "cms:h-full cms:transition-colors cms:hover:bg-accent", children: [
      /* @__PURE__ */ e.jsxs(a.CardHeader, { children: [
        /* @__PURE__ */ e.jsx(a.CardTitle, { className: "cms:text-base", children: t.title }),
        /* @__PURE__ */ e.jsx(a.CardDescription, { children: /* @__PURE__ */ e.jsx("code", { className: "cms:text-xs", children: t.handle }) })
      ] }),
      /* @__PURE__ */ e.jsx(a.CardContent, {})
    ] }) }, t.handle)) })
  ] });
}
const vt = 3;
function Nt({ form: s, forms: t, submissions: l, can: c }) {
  a.useBreadcrumbs([{ label: "Submissions", href: S("cms/submissions") }, ...s ? [{ label: s.title }] : []]);
  const [r, n] = w.useState(null), [i, m] = K();
  if (!s || !l)
    return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-4", children: [
      /* @__PURE__ */ e.jsx(j.Head, { title: "Submissions" }),
      /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: "Submissions" }),
      /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "There are no forms yet. Create one under Structure → Forms and put it on a page; what visitors send shows up here." })
    ] });
  const d = (l.data[0]?.rows ?? []).slice(0, vt), x = (o) => i(
    "Delete this submission for good? It can't be restored.",
    () => j.router.delete(S(`cms/submissions/${o.id}`), { preserveScroll: !0, onSuccess: () => n(null) })
  );
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: `${s.title} submissions` }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:justify-between cms:gap-4", children: [
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-3", children: [
        /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: s.title }),
        t.length > 1 && /* @__PURE__ */ e.jsxs(a.Select, { value: s.handle, onValueChange: (o) => j.router.get(S(`cms/forms/${o}/submissions`)), children: [
          /* @__PURE__ */ e.jsx(a.SelectTrigger, { className: "cms:w-56", "aria-label": "Form", children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
          /* @__PURE__ */ e.jsx(a.SelectContent, { children: t.map((o) => /* @__PURE__ */ e.jsxs(a.SelectItem, { value: o.handle, children: [
            o.title,
            " (",
            o.count,
            ")"
          ] }, o.handle)) })
        ] })
      ] }),
      l.total > 0 && /* @__PURE__ */ e.jsx(a.Button, { variant: "outline", asChild: !0, children: /* @__PURE__ */ e.jsxs("a", { href: S(`cms/forms/${s.handle}/submissions/export`), children: [
        /* @__PURE__ */ e.jsx(Ie, {}),
        " Export CSV"
      ] }) })
    ] }),
    /* @__PURE__ */ e.jsx(a.Card, { className: "cms:py-0", children: /* @__PURE__ */ e.jsxs(a.Table, { children: [
      /* @__PURE__ */ e.jsx(a.TableHeader, { children: /* @__PURE__ */ e.jsxs(a.TableRow, { children: [
        /* @__PURE__ */ e.jsx(a.TableHead, { children: "Sent" }),
        d.map((o) => /* @__PURE__ */ e.jsx(a.TableHead, { children: o.label }, o.handle))
      ] }) }),
      /* @__PURE__ */ e.jsxs(a.TableBody, { children: [
        l.data.length === 0 && /* @__PURE__ */ e.jsx(a.TableRow, { children: /* @__PURE__ */ e.jsx(a.TableCell, { colSpan: d.length + 1, className: "cms:py-8 cms:text-center cms:text-muted-foreground", children: "Nothing sent yet." }) }),
        l.data.map((o) => /* @__PURE__ */ e.jsxs(a.TableRow, { children: [
          /* @__PURE__ */ e.jsx(a.TableCell, { className: "cms:whitespace-nowrap", children: /* @__PURE__ */ e.jsx("button", { type: "button", className: "cms:font-medium cms:hover:underline", onClick: () => n(o), children: X(o.createdAt) }) }),
          d.map((p) => /* @__PURE__ */ e.jsx(a.TableCell, { className: "cms:max-w-64 cms:truncate", children: o.rows.find((u) => u.handle === p.handle)?.value }, p.handle))
        ] }, o.id))
      ] })
    ] }) }),
    l.total > 0 && /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:justify-between cms:text-sm cms:text-muted-foreground", children: [
      /* @__PURE__ */ e.jsxs("span", { children: [
        l.from,
        "–",
        l.to,
        " of ",
        l.total
      ] }),
      /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:gap-2", children: [
        /* @__PURE__ */ e.jsx(
          a.Button,
          {
            variant: "outline",
            size: "icon",
            "aria-label": "Previous page",
            disabled: !l.prev_page_url,
            onClick: () => l.prev_page_url && j.router.get(l.prev_page_url, {}, { preserveScroll: !0 }),
            children: /* @__PURE__ */ e.jsx(Te, {})
          }
        ),
        /* @__PURE__ */ e.jsx(
          a.Button,
          {
            variant: "outline",
            size: "icon",
            "aria-label": "Next page",
            disabled: !l.next_page_url,
            onClick: () => l.next_page_url && j.router.get(l.next_page_url, {}, { preserveScroll: !0 }),
            children: /* @__PURE__ */ e.jsx(re, {})
          }
        )
      ] })
    ] }),
    /* @__PURE__ */ e.jsx(a.Dialog, { open: r !== null, onOpenChange: (o) => !o && n(null), children: /* @__PURE__ */ e.jsxs(a.DialogContent, { className: "cms:max-h-[85vh] cms:overflow-y-auto", children: [
      /* @__PURE__ */ e.jsxs(a.DialogHeader, { children: [
        /* @__PURE__ */ e.jsx(a.DialogTitle, { children: s.title }),
        /* @__PURE__ */ e.jsxs(a.DialogDescription, { children: [
          "Sent ",
          X(r?.createdAt ?? null),
          " · ",
          r?.locale.toUpperCase()
        ] })
      ] }),
      /* @__PURE__ */ e.jsx("dl", { className: "cms:flex cms:flex-col cms:gap-3 cms:text-sm", children: r?.rows.map((o) => /* @__PURE__ */ e.jsxs("div", { children: [
        /* @__PURE__ */ e.jsx("dt", { className: "cms:font-medium", children: o.label }),
        /* @__PURE__ */ e.jsx("dd", { className: "cms:whitespace-pre-wrap cms:break-words cms:text-muted-foreground", children: o.value || "—" })
      ] }, o.handle)) }),
      c.delete && r && /* @__PURE__ */ e.jsx(a.DialogFooter, { children: /* @__PURE__ */ e.jsxs(a.Button, { variant: "destructive", onClick: () => x(r), children: [
        /* @__PURE__ */ e.jsx(I, {}),
        " Delete"
      ] }) })
    ] }) }),
    m
  ] });
}
const ne = "__top";
function yt({ taxonomy: s, taxonomies: t, terms: l, locales: c, locale: r }) {
  a.useBreadcrumbs([{ label: "Terms", href: S("cms/terms") }, ...s ? [{ label: s.title }] : []]);
  const [n, i] = K(), [m, d] = w.useState(null);
  if (!s)
    return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-4", children: [
      /* @__PURE__ */ e.jsx(j.Head, { title: "Terms" }),
      /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: "Terms" }),
      /* @__PURE__ */ e.jsx("p", { className: "cms:text-muted-foreground", children: "There are no taxonomies yet. Create one under Structure → Taxonomies (categories, tags…), then add its terms here." })
    ] });
  const x = S(`cms/taxonomies/${s.handle}/terms`);
  return /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:max-w-4xl cms:flex-col cms:gap-6", children: [
    /* @__PURE__ */ e.jsx(j.Head, { title: s.title }),
    i,
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-wrap cms:items-center cms:gap-3", children: [
      /* @__PURE__ */ e.jsx("h1", { className: "cms:text-2xl cms:font-medium cms:tracking-tight", children: s.title }),
      t.length > 1 && /* @__PURE__ */ e.jsxs(a.Select, { value: s.handle, onValueChange: (o) => j.router.get(S(`cms/taxonomies/${o}/terms`)), children: [
        /* @__PURE__ */ e.jsx(a.SelectTrigger, { className: "cms:w-48", "aria-label": "Taxonomy", children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
        /* @__PURE__ */ e.jsx(a.SelectContent, { children: t.map((o) => /* @__PURE__ */ e.jsx(a.SelectItem, { value: o.handle, children: o.title }, o.handle)) })
      ] }),
      c.length > 1 && /* @__PURE__ */ e.jsxs(a.Select, { value: r, onValueChange: (o) => j.router.get(x, { locale: o }), children: [
        /* @__PURE__ */ e.jsx(a.SelectTrigger, { className: "cms:ml-auto cms:w-48", "aria-label": "Language", children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
        /* @__PURE__ */ e.jsx(a.SelectContent, { children: c.map((o) => /* @__PURE__ */ e.jsx(a.SelectItem, { value: o, children: L(o) }, o)) })
      ] })
    ] }),
    /* @__PURE__ */ e.jsx(a.Card, { children: /* @__PURE__ */ e.jsx(a.CardContent, { children: /* @__PURE__ */ e.jsx(
      fe,
      {
        action: x,
        method: "post",
        locale: r,
        terms: l,
        hierarchical: s.hierarchical,
        submitLabel: "Add term"
      },
      `new-${l.length}`
    ) }) }),
    /* @__PURE__ */ e.jsx(a.Card, { className: "cms:py-0", children: /* @__PURE__ */ e.jsxs("ul", { className: "cms:divide-y", children: [
      l.length === 0 && /* @__PURE__ */ e.jsx("li", { className: "cms:p-6 cms:text-center cms:text-muted-foreground", children: "No terms yet." }),
      l.map((o) => {
        const p = l.filter((g) => g.parentId === o.parentId), u = p.findIndex((g) => g.id === o.id);
        return /* @__PURE__ */ e.jsx("li", { className: "cms:p-3", style: { paddingLeft: `${0.75 + o.depth * 1.5}rem` }, children: m === o.id ? /* @__PURE__ */ e.jsx(
          fe,
          {
            action: S(`cms/terms/${o.id}`),
            method: "put",
            locale: r,
            terms: l,
            hierarchical: s.hierarchical,
            term: o,
            submitLabel: "Save",
            onDone: () => d(null)
          }
        ) : /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:items-center cms:gap-3", children: [
          /* @__PURE__ */ e.jsxs("div", { className: "cms:min-w-0 cms:flex-1", children: [
            o.title !== null ? /* @__PURE__ */ e.jsx("span", { className: "cms:font-medium", children: o.title }) : /* @__PURE__ */ e.jsxs("span", { className: "cms:text-muted-foreground cms:italic", children: [
              o.fallback,
              " · not translated"
            ] }),
            o.slug && /* @__PURE__ */ e.jsxs("span", { className: "cms:ml-2 cms:text-xs cms:text-muted-foreground", children: [
              "/",
              o.slug
            ] })
          ] }),
          /* @__PURE__ */ e.jsx(
            a.Button,
            {
              variant: "ghost",
              size: "icon",
              className: "cms:size-8",
              "aria-label": `Move ${o.fallback} up`,
              disabled: u === 0,
              onClick: () => j.router.post(S(`cms/terms/${o.id}/move`), { direction: "up" }, { preserveScroll: !0 }),
              children: /* @__PURE__ */ e.jsx(G, {})
            }
          ),
          /* @__PURE__ */ e.jsx(
            a.Button,
            {
              variant: "ghost",
              size: "icon",
              className: "cms:size-8",
              "aria-label": `Move ${o.fallback} down`,
              disabled: u === p.length - 1,
              onClick: () => j.router.post(S(`cms/terms/${o.id}/move`), { direction: "down" }, { preserveScroll: !0 }),
              children: /* @__PURE__ */ e.jsx(W, {})
            }
          ),
          /* @__PURE__ */ e.jsx(a.Button, { variant: "ghost", size: "icon", className: "cms:size-8", "aria-label": `Edit ${o.fallback}`, onClick: () => d(o.id), children: /* @__PURE__ */ e.jsx(Ts, {}) }),
          /* @__PURE__ */ e.jsx(
            a.Button,
            {
              variant: "ghost",
              size: "icon",
              className: "cms:size-8 cms:text-destructive",
              "aria-label": `Delete ${o.fallback}`,
              onClick: () => n(
                `Delete "${o.fallback}" in every language? Terms under it move up a level.`,
                () => j.router.delete(S(`cms/terms/${o.id}`), { preserveScroll: !0 })
              ),
              children: /* @__PURE__ */ e.jsx(I, {})
            }
          )
        ] }) }, o.id);
      })
    ] }) })
  ] });
}
function fe({ action: s, method: t, locale: l, terms: c, hierarchical: r, term: n, submitLabel: i, onDone: m }) {
  const d = j.useForm({ title: n?.title ?? "", slug: n?.slug ?? "", parent_id: n?.parentId ?? null, locale: l }), x = d.errors, o = /* @__PURE__ */ new Set();
  if (n) {
    o.add(n.id);
    const g = c.findIndex((b) => b.id === n.id);
    for (let b = g + 1; b < c.length && (c[b]?.depth ?? 0) > n.depth; b++) o.add(c[b].id);
  }
  const p = (g) => {
    g.preventDefault(), d.submit(t, s, { preserveScroll: !0, onSuccess: () => {
      t === "post" && d.reset("title", "slug"), m?.();
    } });
  }, u = n ? `term-${n.id}` : "new-term";
  return /* @__PURE__ */ e.jsxs("form", { onSubmit: p, className: "cms:grid cms:items-start cms:gap-3 cms:md:grid-cols-[2fr_1.5fr_1.5fr_auto]", children: [
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-1", children: [
      /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${u}-title`, className: n ? "cms:sr-only" : "", children: "Title" }),
      /* @__PURE__ */ e.jsx(
        a.Input,
        {
          id: `${u}-title`,
          value: d.data.title,
          placeholder: n?.fallback,
          required: !0,
          onChange: (g) => d.setData("title", g.target.value)
        }
      ),
      /* @__PURE__ */ e.jsx(D, { message: x.title })
    ] }),
    /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-1", children: [
      /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${u}-slug`, className: n ? "cms:sr-only" : "", children: "Slug" }),
      /* @__PURE__ */ e.jsx(
        a.Input,
        {
          id: `${u}-slug`,
          className: "cms:font-mono",
          value: d.data.slug,
          placeholder: "Made from the title",
          onChange: (g) => d.setData("slug", g.target.value)
        }
      ),
      /* @__PURE__ */ e.jsx(D, { message: x.slug })
    ] }),
    r ? /* @__PURE__ */ e.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-1", children: [
      /* @__PURE__ */ e.jsx(a.Label, { htmlFor: `${u}-parent`, className: n ? "cms:sr-only" : "", children: "Under" }),
      /* @__PURE__ */ e.jsxs(
        a.Select,
        {
          value: d.data.parent_id === null ? ne : String(d.data.parent_id),
          onValueChange: (g) => d.setData("parent_id", g === ne ? null : Number(g)),
          children: [
            /* @__PURE__ */ e.jsx(a.SelectTrigger, { id: `${u}-parent`, children: /* @__PURE__ */ e.jsx(a.SelectValue, {}) }),
            /* @__PURE__ */ e.jsxs(a.SelectContent, { children: [
              /* @__PURE__ */ e.jsx(a.SelectItem, { value: ne, children: "Top level" }),
              c.filter((g) => !o.has(g.id)).map((g) => /* @__PURE__ */ e.jsxs(a.SelectItem, { value: String(g.id), children: [
                "— ".repeat(g.depth),
                g.title ?? g.fallback
              ] }, g.id))
            ] })
          ]
        }
      ),
      /* @__PURE__ */ e.jsx(D, { message: x.parent_id })
    ] }) : /* @__PURE__ */ e.jsx("div", {}),
    /* @__PURE__ */ e.jsxs("div", { className: `cms:flex cms:gap-2 ${n ? "" : "cms:md:mt-6"}`, children: [
      /* @__PURE__ */ e.jsxs(a.Button, { type: "submit", disabled: d.processing, children: [
        !n && /* @__PURE__ */ e.jsx(F, {}),
        " ",
        i
      ] }),
      m && /* @__PURE__ */ e.jsx(a.Button, { type: "button", variant: "ghost", onClick: m, children: "Cancel" })
    ] })
  ] });
}
us({
  pages: {
    "Cms/Blueprints/Index": st,
    "Cms/Blueprints/Form": et,
    "Cms/Fieldsets/Index": ot,
    "Cms/Entries/Index": it,
    "Cms/Entries/Editor": at,
    "Cms/Terms/Index": yt,
    "Cms/Globals/Index": ut,
    "Cms/Globals/Edit": dt,
    "Cms/Menus/Index": bt,
    "Cms/Menus/Edit": jt,
    "Cms/Submissions/Index": Nt
  }
});
export {
  $s as L,
  w as R,
  zs as U,
  Ct as _,
  S as a,
  _ as c,
  Ps as i,
  e as j,
  a as u
};
