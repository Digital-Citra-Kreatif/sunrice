import { j as s, t as m, r as P } from "./admin-CFNdnF5j.js";
import { _ as C } from "./createLucideIcon-Ccbp7F45.js";
import { u as t } from "./ui-Dh5wfMwT.js";
import { F as A } from "./FieldError-Cv5-b9XX.js";
import { H as I } from "./FieldInput-BaD3VWRZ.js";
import { C as f } from "./check-OeKFYRnl.js";
function B(e, c) {
  return e === null || c === void 0 ? e : { ...e, choices: c };
}
const y = (e) => e.includes("::") ? e : `${e.replace(/\./g, "/")}.blade.php`;
function T(e, c) {
  const a = e.trim();
  return a !== "" && !F(e, c) ? a : c?.fallbacks.find((i) => i.exists && i.view !== a)?.view ?? null;
}
function F(e, c) {
  const a = e.trim();
  return a === "" ? !1 : c?.own?.view === a ? !c.own.exists : c?.choices !== void 0 && !c.choices.includes(a);
}
function S({ view: e }) {
  const c = y(e);
  return /* @__PURE__ */ s.jsx("code", { title: c, className: "cms:block cms:min-w-0 cms:truncate cms:font-mono cms:text-xs", children: c });
}
function O({ value: e = "", lookup: c = null }) {
  const a = e.trim(), i = T(e, c), d = /* @__PURE__ */ s.jsx("span", { className: "cms:shrink-0 cms:rounded cms:bg-emerald-500/15 cms:px-1.5 cms:text-[0.65rem] cms:font-medium cms:text-emerald-800 cms:dark:text-emerald-300", children: m("in use") }), x = [...a !== "" ? [a] : [], ...(c?.fallbacks ?? []).map((r) => r.view).filter((r) => r !== a)];
  return /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2 cms:text-xs", children: [
    /* @__PURE__ */ s.jsx("p", { className: "cms:text-muted-foreground", children: m("Tried in this order; the first file that exists is used.") }),
    /* @__PURE__ */ s.jsxs("ol", { className: "cms:flex cms:flex-col cms:gap-1", children: [
      x.map((r, u) => /* @__PURE__ */ s.jsxs("li", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-2 cms:text-muted-foreground", children: [
        /* @__PURE__ */ s.jsxs("span", { className: "cms:w-4 cms:shrink-0 cms:text-right cms:tabular-nums", children: [
          u + 1,
          "."
        ] }),
        /* @__PURE__ */ s.jsx(S, { view: r }),
        i === r && d
      ] }, r)),
      /* @__PURE__ */ s.jsxs("li", { className: "cms:flex cms:items-center cms:gap-2 cms:text-muted-foreground", children: [
        /* @__PURE__ */ s.jsxs("span", { className: "cms:w-4 cms:shrink-0 cms:text-right cms:tabular-nums", children: [
          x.length + 1,
          "."
        ] }),
        /* @__PURE__ */ s.jsx("span", { className: "cms:min-w-0 cms:truncate", children: m("the built-in template") }),
        i === null && d
      ] })
    ] })
  ] });
}
function D({ id: e, value: c, onChange: a, lookup: i = null, example: d, automatic: x, error: r, disabled: u = !1 }) {
  const [j, w] = C.useState(!1), [g, h] = C.useState(""), _ = () => {
    i?.choices === void 0 && P.router.reload({ only: ["templateChoices"] });
  }, N = i?.choices ?? [], l = c.trim(), v = T(c, i), k = F(c, i), o = g.trim().toLowerCase(), p = (n) => {
    a(n), w(!1), h("");
  };
  return /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ s.jsx(t.Label, { htmlFor: e, children: m("Template") }),
    /* @__PURE__ */ s.jsxs(t.Popover, { open: j, onOpenChange: (n) => {
      w(n), n || h(""), n && _();
    }, children: [
      /* @__PURE__ */ s.jsx(t.PopoverTrigger, { asChild: !0, children: /* @__PURE__ */ s.jsxs(
        t.Button,
        {
          id: e,
          type: "button",
          variant: "outline",
          role: "combobox",
          "aria-expanded": j,
          "aria-invalid": r !== void 0,
          disabled: u,
          className: "cms:w-full cms:min-w-0 cms:justify-between cms:font-normal",
          children: [
            /* @__PURE__ */ s.jsx("span", { className: `cms:min-w-0 cms:truncate ${l === "" ? "cms:text-muted-foreground" : "cms:font-mono cms:text-xs"}`, title: l || void 0, children: l === "" ? m("Automatic") : l }),
            /* @__PURE__ */ s.jsx(I, { className: "cms:opacity-50" })
          ]
        }
      ) }),
      /* @__PURE__ */ s.jsx(t.PopoverContent, { align: "start", className: "cms:w-(--radix-popover-trigger-width) cms:min-w-64 cms:p-0", children: /* @__PURE__ */ s.jsxs(t.Command, { children: [
        /* @__PURE__ */ s.jsx(t.CommandInput, { placeholder: m("Find or type a view name"), value: g, onValueChange: h }),
        /* @__PURE__ */ s.jsxs(t.CommandList, { children: [
          /* @__PURE__ */ s.jsx(t.CommandEmpty, { children: m("No such view.") }),
          /* @__PURE__ */ s.jsxs(t.CommandGroup, { children: [
            /* @__PURE__ */ s.jsxs(t.CommandItem, { value: "__automatic", keywords: [m("Automatic")], onSelect: () => p(""), children: [
              /* @__PURE__ */ s.jsx(f, { className: l === "" ? "" : "cms:opacity-0" }),
              " ",
              m("Automatic")
            ] }),
            N.map((n) => /* @__PURE__ */ s.jsxs(t.CommandItem, { value: n, onSelect: () => p(n), children: [
              /* @__PURE__ */ s.jsx(f, { className: l === n ? "" : "cms:opacity-0" }),
              " ",
              /* @__PURE__ */ s.jsx("span", { className: "cms:truncate cms:font-mono cms:text-xs", children: n })
            ] }, n)),
            o !== "" && !N.includes(o) && /* @__PURE__ */ s.jsxs(t.CommandItem, { value: `__typed ${o}`, keywords: [o], onSelect: () => p(o), children: [
              /* @__PURE__ */ s.jsx(f, { className: "cms:opacity-0" }),
              " ",
              /* @__PURE__ */ s.jsx("span", { className: "cms:truncate", children: m("Use :view", { view: o }) })
            ] })
          ] })
        ] })
      ] }) })
    ] }),
    k && /* @__PURE__ */ s.jsx("p", { className: "cms:text-xs cms:text-amber-700 cms:dark:text-amber-400", children: m("No such file yet: the page uses the next one until it exists.") }),
    /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-0.5 cms:text-xs cms:leading-relaxed cms:text-muted-foreground", children: [
      /* @__PURE__ */ s.jsx("p", { children: m("Files are under resources/views; a name like :example is :file.", { example: "\0", file: "" }).split(/(\u0000|\u0001)/).map((n, b) => n === "\0" ? /* @__PURE__ */ s.jsx("code", { className: "cms:wrap-anywhere", children: d }, b) : n === "" ? /* @__PURE__ */ s.jsx("code", { className: "cms:wrap-anywhere", children: y(d) }, b) : n) }),
      /* @__PURE__ */ s.jsx("p", { children: x })
    ] }),
    /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:min-w-0 cms:flex-wrap cms:items-center cms:gap-x-3 cms:gap-y-1 cms:text-xs cms:text-muted-foreground", children: [
      /* @__PURE__ */ s.jsxs("span", { className: "cms:flex cms:min-w-0 cms:max-w-full cms:items-baseline cms:gap-1", children: [
        /* @__PURE__ */ s.jsx("span", { className: "cms:shrink-0", children: m("Uses") }),
        v === null ? /* @__PURE__ */ s.jsx("span", { children: m("the built-in template") }) : /* @__PURE__ */ s.jsx(S, { view: v })
      ] }),
      /* @__PURE__ */ s.jsxs(t.Popover, { children: [
        /* @__PURE__ */ s.jsx(t.PopoverTrigger, { asChild: !0, children: /* @__PURE__ */ s.jsx("button", { type: "button", className: "cms:ml-auto cms:shrink-0 cms:rounded-sm cms:underline cms:underline-offset-4 cms:hover:text-foreground cms:focus-visible:outline-2 cms:focus-visible:outline-ring", children: m("How is it chosen?") }) }),
        /* @__PURE__ */ s.jsx(t.PopoverContent, { align: "end", className: "cms:w-max cms:min-w-72 cms:max-w-[min(32rem,calc(100vw-2rem))]", children: /* @__PURE__ */ s.jsx(O, { value: c, lookup: i }) })
      ] })
    ] }),
    /* @__PURE__ */ s.jsx(A, { message: r })
  ] });
}
function G({ note: e, redirectsUrl: c }) {
  return /* @__PURE__ */ s.jsx("div", { "aria-live": "polite", children: e !== null && /* @__PURE__ */ s.jsxs("p", { className: "cms:text-xs cms:text-muted-foreground", children: [
    e === "redirects" ? m("Once published, the old address redirects to the new one.") : m("The old address will stop working (404) unless a redirect is added."),
    e === "breaks" && c && /* @__PURE__ */ s.jsxs(s.Fragment, { children: [
      " ",
      /* @__PURE__ */ s.jsx("a", { href: c, target: "_blank", rel: "noopener", className: "cms:underline cms:underline-offset-2 cms:hover:text-foreground", children: m("Add a redirect") })
    ] })
  ] }) });
}
export {
  G as S,
  D as T,
  B as w
};
