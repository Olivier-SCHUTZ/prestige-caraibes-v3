/**
 * Orchestrateur Principal du Header
 */
document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("#pc-header[data-pc-hg]");
  if (!root) return;

  // 1. Navigation Desktop (Méga panneaux)
  if (typeof PCHeaderNavigation !== "undefined") {
    new PCHeaderNavigation(root);
  }

  // 2. Recherche et Suggestions
  if (typeof PCHeaderSearch !== "undefined") {
    new PCHeaderSearch(root);
  }

  // 3. Menu Mobile (Offcanvas)
  if (typeof PCHeaderOffcanvas !== "undefined") {
    new PCHeaderOffcanvas(root);
  }

  // 4. Smart Header (Scroll)
  if (typeof PCHeaderSmart !== "undefined") {
    new PCHeaderSmart();
  }

  // Évite le flash
  requestAnimationFrame(() => {
    root.classList.add("pc-hg-ready");
  });
});
