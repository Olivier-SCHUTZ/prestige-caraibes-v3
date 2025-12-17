/*
  Prestige Caraïbes — Header Global (v1)
  Vanilla JS :
  - Desktop mega panels (ARIA)
  - Mobile off-canvas (overlay, ESC, focus trap)
  - Mobile accordions
*/

(function () {
  "use strict";

  var root = document.querySelector("#pc-header[data-pc-hg]");
  if (!root) return;

  var cfg = window.PCHeaderGlobal || { bpDesktop: 1025 };

  // ------------------------------
  // Header search (suggest + redirect)
  // ------------------------------
  function pcDebounce(fn, wait) {
    var t = null;
    return function () {
      var ctx = this;
      var args = arguments;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  }

  function pcNorm(s) {
    if (!s) return "";
    s = String(s).trim().toLowerCase();
    try {
      s = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    } catch (e) {}
    return s;
  }

  function initHeaderSearch() {
    // Multi-instances (desktop + offcanvas)
    var boxes = qsa("[data-pc-hg-searchbox], .pc-hg__searchbox", root);
    if (!boxes.length) return;

    var restUrl = cfg && cfg.restUrl ? cfg.restUrl : null;
    var minChars = cfg && cfg.minChars ? parseInt(cfg.minChars, 10) : 2;
    var maxResults = cfg && cfg.maxResults ? parseInt(cfg.maxResults, 10) : 8;

    boxes.forEach(function (box, boxIndex) {
      var input = box.querySelector(".pc-hg__searchinput");
      var list = box.querySelector(".pc-hg__searchlist");
      if (!input || !list) return;

      // Assurer des IDs uniques (évite collisions desktop/offcanvas)
      if (!list.id) list.id = "pc-hg-search-list-" + boxIndex;
      input.setAttribute("aria-controls", list.id);
      input.setAttribute("aria-autocomplete", "list");

      var items = [];
      var open = false;
      var active = -1;

      function setOpen(state) {
        open = !!state;
        box.setAttribute("aria-expanded", open ? "true" : "false");
        input.setAttribute("aria-expanded", open ? "true" : "false");
        list.hidden = !open;

        if (!open) {
          active = -1;
          input.removeAttribute("aria-activedescendant");
        }
      }

      function setActive(idx) {
        var opts = list.querySelectorAll(".pc-hg__searchopt");
        if (!opts.length) return;

        idx = Math.max(0, Math.min(idx, opts.length - 1));
        active = idx;

        opts.forEach(function (el, i) {
          if (i === active) el.classList.add("is-active");
          else el.classList.remove("is-active");
        });

        input.setAttribute(
          "aria-activedescendant",
          "pc-hg-opt-" + boxIndex + "-" + active
        );
      }

      function render() {
        list.innerHTML = "";
        if (!items.length) {
          setOpen(false);
          return;
        }

        items.slice(0, maxResults).forEach(function (it, idx) {
          var opt = document.createElement("div");
          opt.className = "pc-hg__searchopt";
          opt.id = "pc-hg-opt-" + boxIndex + "-" + idx;
          opt.setAttribute("role", "option");
          opt.setAttribute("tabindex", "-1");
          opt.dataset.url = it.url;

          var t = document.createElement("div");
          t.className = "pc-hg__searchtitle";
          t.textContent = it.title;

          var meta = document.createElement("div");
          meta.className = "pc-hg__searchmeta";
          meta.textContent = it.type;

          opt.appendChild(t);
          opt.appendChild(meta);

          opt.addEventListener("mouseenter", function () {
            setActive(idx);
          });

          opt.addEventListener("mousedown", function (e) {
            // mousedown pour éviter le blur avant click
            e.preventDefault();
            window.location.href = it.url;
          });

          list.appendChild(opt);
        });

        setOpen(true);
        setActive(0);
      }

      function closeIfClickOutside(e) {
        if (!box.contains(e.target)) setOpen(false);
      }

      var fetchSuggest = pcDebounce(function () {
        var q = pcNorm(input.value);

        if (!restUrl || q.length < minChars) {
          items = [];
          setOpen(false);
          return;
        }

        fetch(restUrl + "?q=" + encodeURIComponent(q), {
          credentials: "same-origin",
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            items = Array.isArray(data) ? data : [];
            render();
          })
          .catch(function () {
            items = [];
            setOpen(false);
          });
      }, 220);

      input.addEventListener("input", fetchSuggest);

      input.addEventListener("focus", function () {
        if (items.length) setOpen(true);
      });

      input.addEventListener("keydown", function (e) {
        if (!open && (e.key === "ArrowDown" || e.key === "ArrowUp")) {
          if (items.length) {
            render();
            e.preventDefault();
          }
          return;
        }

        if (!open) return;

        if (e.key === "Escape") {
          setOpen(false);
          e.preventDefault();
        }

        if (e.key === "ArrowDown") {
          setActive(active + 1);
          e.preventDefault();
        }

        if (e.key === "ArrowUp") {
          setActive(active - 1);
          e.preventDefault();
        }

        if (e.key === "Enter") {
          var chosen = items[active];
          if (chosen && chosen.url) {
            window.location.href = chosen.url;
            e.preventDefault();
          }
        }
      });

      document.addEventListener("click", closeIfClickOutside);
    });
  }

  function isDesktop() {
    return window.innerWidth >= (cfg.bpDesktop || 1025);
  }

  function qs(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }
  function qsa(sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  }

  function setExpanded(btn, expanded) {
    btn.setAttribute("aria-expanded", expanded ? "true" : "false");
  }

  // ------------------------------
  // Desktop mega panels
  // ------------------------------
  var triggers = qsa("[data-pc-panel]", root);
  var panels = qsa("[data-pc-mega]", root);
  var openPanelId = null;
  var hoverTimer = null;

  function closeAllPanels() {
    triggers.forEach(function (t) {
      setExpanded(t, false);
    });
    panels.forEach(function (p) {
      p.classList.remove("is-open");
      p.setAttribute("aria-hidden", "true");
    });
    openPanelId = null;
  }

  function openPanel(panelId, focusPanel) {
    closeAllPanels();
    var btns = triggers.filter(function (t) {
      return t.getAttribute("data-pc-panel") === panelId;
    });
    var panel = document.getElementById(panelId);
    if (!btns.length || !panel) return;
    btns.forEach(function (b) {
      setExpanded(b, true);
    });
    panel.classList.add("is-open");
    panel.setAttribute("aria-hidden", "false");
    openPanelId = panelId;
    if (focusPanel) panel.focus({ preventScroll: true });
  }

  function togglePanel(panelId) {
    if (openPanelId === panelId) closeAllPanels();
    else openPanel(panelId, false);
  }

  triggers.forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      if (!isDesktop()) return;
      e.preventDefault();
      var panelId = btn.getAttribute("data-pc-panel");
      togglePanel(panelId);
    });

    btn.addEventListener("keydown", function (e) {
      if (!isDesktop()) return;
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        togglePanel(btn.getAttribute("data-pc-panel"));
      }
      if (e.key === "Escape") {
        closeAllPanels();
        btn.focus();
      }
    });

    btn.addEventListener("mouseenter", function () {
      if (!isDesktop()) return;
      clearTimeout(hoverTimer);
      var panelId = btn.getAttribute("data-pc-panel");
      hoverTimer = setTimeout(function () {
        openPanel(panelId, false);
      }, 60);
    });
  });

  panels.forEach(function (panel) {
    panel.addEventListener("mouseenter", function () {
      if (!isDesktop()) return;
      clearTimeout(hoverTimer);
    });
    panel.addEventListener("mouseleave", function () {
      if (!isDesktop()) return;
      clearTimeout(hoverTimer);
      hoverTimer = setTimeout(closeAllPanels, 120);
    });
  });

  document.addEventListener("click", function (e) {
    if (!isDesktop()) return;
    if (!openPanelId) return;
    if (!root.contains(e.target)) closeAllPanels();
  });

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    if (isDesktop()) closeAllPanels();
    if (document.body.classList.contains("pc-oc-open")) closeOffcanvas();
  });

  // ------------------------------
  // Off-canvas (mobile/tablette)
  // ------------------------------
  var oc = qs("#pc-offcanvas", root);
  var ocOpenBtn = qs("[data-pc-oc-open]", root);
  var ocCloseEls = qsa("[data-pc-oc-close]", root);
  var ocPanel = oc ? qs(".pc-offcanvas__panel", oc) : null;

  var lastFocus = null;

  function getFocusable(container) {
    if (!container) return [];
    return qsa(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      container
    ).filter(function (el) {
      return !!(
        el.offsetWidth ||
        el.offsetHeight ||
        el.getClientRects().length
      );
    });
  }

  function trapFocus(e) {
    if (!document.body.classList.contains("pc-oc-open")) return;
    if (e.key !== "Tab") return;
    var focusables = getFocusable(ocPanel);
    if (!focusables.length) return;
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  function openOffcanvas() {
    if (!oc || !ocPanel) return;
    lastFocus = document.activeElement;
    document.body.classList.add("pc-oc-open");
    oc.setAttribute("aria-hidden", "false");
    if (ocOpenBtn) ocOpenBtn.setAttribute("aria-expanded", "true");

    document.body.style.overflow = "hidden";

    var focusables = getFocusable(ocPanel);
    (focusables[0] || ocPanel).focus({ preventScroll: true });
    document.addEventListener("keydown", trapFocus);
  }

  function closeOffcanvas() {
    if (!oc || !ocPanel) return;
    document.body.classList.remove("pc-oc-open");
    oc.setAttribute("aria-hidden", "true");
    if (ocOpenBtn) ocOpenBtn.setAttribute("aria-expanded", "false");

    document.body.style.overflow = "";

    document.removeEventListener("keydown", trapFocus);
    if (lastFocus && typeof lastFocus.focus === "function") lastFocus.focus();
  }

  if (ocOpenBtn) {
    ocOpenBtn.addEventListener("click", function () {
      openOffcanvas();
    });
  }
  ocCloseEls.forEach(function (el) {
    el.addEventListener("click", function () {
      closeOffcanvas();
    });
  });

  // ------------------------------
  // Off-canvas accordions
  // ------------------------------
  qsa("[data-pc-oc-acc]", root).forEach(function (btn) {
    btn.addEventListener("click", function () {
      var expanded = btn.getAttribute("aria-expanded") === "true";
      var panelId = btn.getAttribute("aria-controls");
      var panel = panelId ? document.getElementById(panelId) : null;
      btn.setAttribute("aria-expanded", expanded ? "false" : "true");
      if (panel) panel.hidden = expanded;
    });
  });
  // Init
  initHeaderSearch();

  // Évite le flash : on active les transitions après le 1er paint
  requestAnimationFrame(function () {
    root.classList.add("pc-hg-ready");
  });
})();
