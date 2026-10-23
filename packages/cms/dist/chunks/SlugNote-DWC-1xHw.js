import { j as s, t, r as P } from "./admin-CSdT0pxA.js";
import { _ as C } from "./createLucideIcon-Ccbp7F45.js";
import { u as n } from "./ui-Dh5wfMwT.js";
import { F as A } from "./FieldError--UeTqhS9.js";
import { H as I } from "./FieldInput-mTDdrad6.js";
import { C as f } from "./check-OeKFYRnl.js";
function B(e, c) {
  return e === null || c === void 0 ? e : { ...e, choices: c };
}
const y = (e) => e.includes("::") ? e : `${e.replace(/\./g, "/")}.blade.php`;
function T(e, c) {
  const i = e.trim();
  return i !== "" && !F(e, c) ? i : c?.fallbacks.find((r) => r.exists && r.view !== i)?.view ?? null;
}
function F(e, c) {
  const i = e.trim();
  return i === "" ? !1 : c?.own?.view === i ? !c.own.exists : c?.choices !== void 0 && !c.choices.includes(i);
}
function S({ view: e }) {
  const c = y(e);
  return /* @__PURE__ */ s.jsx("code", { title: c, className: "cms:block cms:min-w-0 cms:truncate cms:font-mono cms:text-xs", children: c });
}
function O({ value: e = "", lookup: c = null }) {
  const i = e.trim(), r = T(e, c), d = /* @__PURE__ */ s.jsx("span", { className: "cms:shrink-0 cms:rounded cms:bg-emerald-500/15 cms:px-1.5 cms:text-[0.65rem] cms:font-medium cms:text-emerald-800 cms:dark:text-emerald-300", children: t("in use") }), x = [...i !== "" ? [i] : [], ...(c?.fallbacks ?? []).map((a) => a.view).filter((a) => a !== i)];
  return /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-2 cms:text-xs", children: [
    /* @__PURE__ */ s.jsx("p", { className: "cms:text-muted-foreground", children: t("Tried in this order; the first file that exists is used.") }),
    /* @__PURE__ */ s.jsxs("ol", { className: "cms:flex cms:flex-col cms:gap-1", children: [
      x.map((a, u) => /* @__PURE__ */ s.jsxs("li", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-2 cms:text-muted-foreground", children: [
        /* @__PURE__ */ s.jsxs("span", { className: "cms:w-4 cms:shrink-0 cms:text-right cms:tabular-nums", children: [
          u + 1,
          "."
        ] }),
        /* @__PURE__ */ s.jsx(S, { view: a }),
        r === a && d
      ] }, a)),
      /* @__PURE__ */ s.jsxs("li", { className: "cms:flex cms:items-center cms:gap-2 cms:text-muted-foreground", children: [
        /* @__PURE__ */ s.jsxs("span", { className: "cms:w-4 cms:shrink-0 cms:text-right cms:tabular-nums", children: [
          x.length + 1,
          "."
        ] }),
        /* @__PURE__ */ s.jsx("span", { className: "cms:min-w-0 cms:truncate", children: t("the built-in template") }),
        r === null && d
      ] })
    ] })
  ] });
}
function D({ id: e, value: c, onChange: i, lookup: r = null, example: d, automatic: x, error: a, disabled: u = !1 }) {
  const [j, g] = C.useState(!1), [w, h] = C.useState(""), _ = () => {
    r?.choices === void 0 && P.router.reload({ only: ["templateChoices"] });
  }, N = r?.choices ?? [], l = c.trim(), v = T(c, r), k = F(c, r), o = w.trim().toLowerCase(), p = (m) => {
    i(m), g(!1), h("");
  };
  return /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:min-w-0 cms:flex-col cms:gap-2", children: [
    /* @__PURE__ */ s.jsx(n.Label, { htmlFor: e, children: t("Template") }),
    /* @__PURE__ */ s.jsxs(n.Popover, { open: j, onOpenChange: (m) => {
      g(m), m || h(""), m && _();
    }, children: [
      /* @__PURE__ */ s.jsx(n.PopoverTrigger, { asChild: !0, children: /* @__PURE__ */ s.jsxs(
        n.Button,
        {
          id: e,
          type: "button",
          variant: "outline",
          role: "combobox",
          "aria-expanded": j,
          "aria-invalid": a !== void 0,
          disabled: u,
          className: "cms:w-full cms:min-w-0 cms:justify-between cms:font-normal",
          children: [
            /* @__PURE__ */ s.jsx("span", { className: `cms:min-w-0 cms:truncate ${l === "" ? "cms:text-muted-foreground" : "cms:font-mono cms:text-xs"}`, title: l || void 0, children: l === "" ? t("Automatic") : l }),
            /* @__PURE__ */ s.jsx(I, { className: "cms:opacity-50" })
          ]
        }
      ) }),
      /* @__PURE__ */ s.jsx(n.PopoverContent, { align: "start", className: "cms:w-(--radix-popover-trigger-width) cms:min-w-64 cms:p-0", children: /* @__PURE__ */ s.jsxs(n.Command, { children: [
        /* @__PURE__ */ s.jsx(n.CommandInput, { placeholder: t("Find or type a view name"), value: w, onValueChange: h }),
        /* @__PURE__ */ s.jsxs(n.CommandList, { children: [
          /* @__PURE__ */ s.jsx(n.CommandEmpty, { children: t("No such view.") }),
          /* @__PURE__ */ s.jsxs(n.CommandGroup, { children: [
            /* @__PURE__ */ s.jsxs(n.CommandItem, { value: "__automatic", keywords: [t("Automatic")], onSelect: () => p(""), children: [
              /* @__PURE__ */ s.jsx(f, { className: l === "" ? "" : "cms:opacity-0" }),
              " ",
              t("Automatic")
            ] }),
            N.map((m) => /* @__PURE__ */ s.jsxs(n.CommandItem, { value: m, onSelect: () => p(m), children: [
              /* @__PURE__ */ s.jsx(f, { className: l === m ? "" : "cms:opacity-0" }),
              " ",
              /* @__PURE__ */ s.jsx("span", { className: "cms:truncate cms:font-mono cms:text-xs", children: m })
            ] }, m)),
            o !== "" && !N.includes(o) && /* @__PURE__ */ s.jsxs(n.CommandItem, { value: `__typed ${o}`, keywords: [o], onSelect: () => p(o), children: [
              /* @__PURE__ */ s.jsx(f, { className: "cms:opacity-0" }),
              " ",
              /* @__PURE__ */ s.jsx("span", { className: "cms:truncate", children: t("Use :view", { view: o }) })
            ] })
          ] })
        ] })
      ] }) })
    ] }),
    k && /* @__PURE__ */ s.jsx("p", { className: "cms:text-xs cms:text-amber-700 cms:dark:text-amber-400", children: t("No such file yet: the page uses the next one until it exists.") }),
    /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:flex-col cms:gap-0.5 cms:text-xs cms:leading-relaxed cms:text-muted-foreground", children: [
      /* @__PURE__ */ s.jsx("p", { children: t("Files are under resources/views; a name like :example is :file.", { example: "\0", file: "" }).split(/(\u0000|\u0001)/).map((m, b) => m === "\0" ? /* @__PURE__ */ s.jsx("code", { children: d }, b) : m === "" ? /* @__PURE__ */ s.jsx("code", { children: y(d) }, b) : m) }),
      /* @__PURE__ */ s.jsx("p", { children: x })
    ] }),
    /* @__PURE__ */ s.jsxs("div", { className: "cms:flex cms:min-w-0 cms:items-center cms:gap-2 cms:text-xs cms:text-muted-foreground", children: [
      /* @__PURE__ */ s.jsx("span", { className: "cms:shrink-0", children: t("Uses") }),
      v === null ? /* @__PURE__ */ s.jsx("span", { className: "cms:min-w-0 cms:truncate", children: t("the built-in template") }) : /* @__PURE__ */ s.jsx(S, { view: v }),
      /* @__PURE__ */ s.jsxs(n.Popover, { children: [
        /* @__PURE__ */ s.jsx(n.PopoverTrigger, { asChild: !0, children: /* @__PURE__ */ s.jsx("button", { type: "button", className: "cms:ml-auto cms:shrink-0 cms:rounded-sm cms:underline cms:underline-offset-4 cms:hover:text-foreground cms:focus-visible:outline-2 cms:focus-visible:outline-ring", children: t("How is it chosen?") }) }),
        /* @__PURE__ */ s.jsx(n.PopoverContent, { align: "end", className: "cms:w-80 cms:max-w-[calc(100vw-2rem)]", children: /* @__PURE__ */ s.jsx(O, { value: c, lookup: r }) })
      ] })
    ] }),
    /* @__PURE__ */ s.jsx(A, { message: a })
  ] });
}
function G({ note: e, redirectsUrl: c }) {
  return /* @__PURE__ */ s.jsx("div", { "aria-live": "polite", children: e !== null && /* @__PURE__ */ s.jsxs("p", { className: "cms:text-xs cms:text-muted-foreground", children: [
    e === "redirects" ? t("Once published, the old address redirects to the new one.") : t("The old address will stop working (404) unless a redirect is added."),
    e === "breaks" && c && /* @__PURE__ */ s.jsxs(s.Fragment, { children: [
      " ",
      /* @__PURE__ */ s.jsx("a", { href: c, target: "_blank", rel: "noopener", className: "cms:underline cms:underline-offset-2 cms:hover:text-foreground", children: t("Add a redirect") })
    ] })
  ] }) });
}
export {
  G as S,
  D as T,
  B as w
};
