/**
 * Gestion de la recherche et des suggestions (Desktop & Offcanvas)
 */
class PCHeaderSearch {
  constructor(root) {
    this.root = root;
    this.cfg = window.PCHeaderGlobal || {};
    this.restUrl = this.cfg.restUrl || null;
    this.minChars = parseInt(this.cfg.minChars || 2, 10);
    this.maxResults = parseInt(this.cfg.maxResults || 8, 10);

    // Multi-instances (desktop + offcanvas)
    this.boxes = Array.from(
      this.root.querySelectorAll("[data-pc-hg-searchbox], .pc-hg__searchbox"),
    );

    if (this.boxes.length > 0) {
      this.init();
    }
  }

  debounce(fn, wait) {
    let t = null;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  normalize(s) {
    if (!s) return "";
    s = String(s).trim().toLowerCase();
    try {
      s = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    } catch (e) {}
    return s;
  }

  init() {
    this.boxes.forEach((box, boxIndex) => {
      const input = box.querySelector(".pc-hg__searchinput");
      const list = box.querySelector(".pc-hg__searchlist");
      if (!input || !list) return;

      // Assurer des IDs uniques (évite collisions desktop/offcanvas)
      if (!list.id) list.id = "pc-hg-search-list-" + boxIndex;
      input.setAttribute("aria-controls", list.id);
      input.setAttribute("aria-autocomplete", "list");

      let items = [];
      let open = false;
      let active = -1;

      const setOpen = (state) => {
        open = !!state;
        box.setAttribute("aria-expanded", open ? "true" : "false");
        input.setAttribute("aria-expanded", open ? "true" : "false");
        list.hidden = !open;

        if (!open) {
          active = -1;
          input.removeAttribute("aria-activedescendant");
        }
      };

      const setActive = (idx) => {
        const opts = list.querySelectorAll(".pc-hg__searchopt");
        if (!opts.length) return;

        idx = Math.max(0, Math.min(idx, opts.length - 1));
        active = idx;

        opts.forEach((el, i) => {
          if (i === active) el.classList.add("is-active");
          else el.classList.remove("is-active");
        });

        input.setAttribute(
          "aria-activedescendant",
          "pc-hg-opt-" + boxIndex + "-" + active,
        );
      };

      const render = () => {
        list.innerHTML = "";
        if (!items.length) {
          setOpen(false);
          return;
        }

        items.slice(0, this.maxResults).forEach((it, idx) => {
          const opt = document.createElement("div");
          opt.className = "pc-hg__searchopt";
          opt.id = "pc-hg-opt-" + boxIndex + "-" + idx;
          opt.setAttribute("role", "option");
          opt.setAttribute("tabindex", "-1");
          opt.dataset.url = it.url;

          const t = document.createElement("div");
          t.className = "pc-hg__searchtitle";
          t.textContent = it.title;

          const meta = document.createElement("div");
          meta.className = "pc-hg__searchmeta";
          meta.textContent = it.type;

          opt.appendChild(t);
          opt.appendChild(meta);

          opt.addEventListener("mouseenter", () => setActive(idx));
          opt.addEventListener("mousedown", (e) => {
            e.preventDefault(); // évite le blur avant le clic
            window.location.href = it.url;
          });

          list.appendChild(opt);
        });

        setOpen(true);
        setActive(0);
      };

      const fetchSuggest = this.debounce(() => {
        const q = this.normalize(input.value);

        if (!this.restUrl || q.length < this.minChars) {
          items = [];
          setOpen(false);
          return;
        }

        fetch(this.restUrl + "?q=" + encodeURIComponent(q), {
          credentials: "same-origin",
        })
          .then((r) => r.json())
          .then((data) => {
            items = Array.isArray(data) ? data : [];
            render();
          })
          .catch(() => {
            items = [];
            setOpen(false);
          });
      }, 220);

      input.addEventListener("input", fetchSuggest);

      input.addEventListener("focus", () => {
        if (items.length) setOpen(true);
      });

      input.addEventListener("keydown", (e) => {
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
          const chosen = items[active];
          if (chosen && chosen.url) {
            window.location.href = chosen.url;
            e.preventDefault();
          }
        }
      });

      document.addEventListener("click", (e) => {
        if (!box.contains(e.target)) setOpen(false);
      });
    });
  }
}
