/**
 * Script du composant partagé Avis (pc-reviews)
 */
document.addEventListener("DOMContentLoaded", function () {
  function qs(s, ctx) {
    return (ctx || document).querySelector(s);
  }
  function qsa(s, ctx) {
    return (ctx || document).querySelectorAll(s);
  }

  // Toggle du formulaire "Laisser un avis"
  qsa(".pc-rev-toggle-form").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var f = qs("#pc-review-form");
      if (!f) return;

      // Affiche le formulaire s'il est caché
      if (
        f.style.display === "none" ||
        getComputedStyle(f).display === "none"
      ) {
        f.style.display = "block";
      }
      f.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  // Bouton "Voir plus" (AJAX)
  qsa(".pc-rev-more").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var wrap = btn.closest(".pc-reviews");
      if (!wrap) return;

      var pid = btn.getAttribute("data-post");
      var off = parseInt(btn.getAttribute("data-offset"), 10);
      var limit = parseInt(btn.getAttribute("data-limit"), 10);
      var nonce = btn.getAttribute("data-nonce");

      if (!pid || !limit || isNaN(off)) return;

      btn.disabled = true;
      btn.setAttribute("data-loading", "1");
      var txt = btn.textContent;
      btn.textContent = "Chargement…";

      var form = new FormData();
      form.append("action", "pc_reviews_more");
      form.append("post_id", pid);
      form.append("offset", off);
      form.append("limit", limit);
      form.append("_nonce", nonce);

      // On utilise la variable dynamique injectée par wp_localize_script
      fetch(pcReviewsData.ajax_url, {
        method: "POST",
        body: form,
        credentials: "same-origin",
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (json) {
          btn.disabled = false;
          btn.textContent = txt;
          if (!json || !json.success) return;

          var data = json.data || {};
          var list = qs(".pc-reviews-list", wrap);

          if (list && data.html) {
            var temp = document.createElement("div");
            temp.innerHTML = data.html;
            Array.from(temp.children).forEach(function (card) {
              list.appendChild(card);
            });
          }
          if (data.hasMore) {
            btn.setAttribute("data-offset", data.nextOffset);
          } else {
            btn.parentNode && btn.parentNode.removeChild(btn);
          }
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = txt;
        });
    });
  });
});
