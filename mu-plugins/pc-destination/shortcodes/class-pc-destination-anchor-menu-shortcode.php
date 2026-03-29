<?php

/**
 * Composant Shortcode : Menu d'ancres de navigation Destinations [pc_destination_anchor_menu]
 * Design : Pilule Flottante Premium (Glassmorphism)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Assure-toi que la classe de base existe, sinon retire "extends PC_Destination_Shortcode_Base"
class PC_Destination_Anchor_Menu_Shortcode // extends PC_Destination_Shortcode_Base
{
    public function __construct()
    {
        add_shortcode('pc_destination_anchor_menu', [$this, 'render']);
    }

    public function render(array $atts = []): void
    {
        // 1. Possibilité de masquer manuellement via Elementor (ex: [pc_destination_anchor_menu hide="faq,logements"])
        $parsed_atts = shortcode_atts(['hide' => ''], $atts);
        $hidden_keys = array_filter(array_map('trim', explode(',', strtolower($parsed_atts['hide']))));

        // Les liens spécifiques à la Destination
        $all_links = [
            'description'  => 'Description',
            'logements'    => 'Logements',
            'informations' => 'Informations',
            'experiences'  => 'Expériences',
            'faq'          => 'FAQ'
        ];

        // 2. Filtrage PHP des ancres
        $links = [];
        foreach ($all_links as $id => $label) {
            if (!in_array($id, $hidden_keys)) {
                $links[$id] = $label;
            }
        }

        // Si tout est masqué, on ne sort aucun HTML
        if (empty($links)) return;

        ob_start(); ?>

        <div class="dest-anchor-wrapper">
            <div class="dest-anchor-menu" id="destination-anchor-menu" role="navigation" aria-label="Navigation de la page" data-header-offset="68">

                <button type="button" class="dest-anchor-arrow prev" aria-label="Précédent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>

                <div class="dest-anchor-scroller">
                    <nav class="dest-anchor-nav">
                        <?php foreach ($links as $id => $label): ?>
                            <a href="#<?php echo esc_attr($id); ?>" class="dest-anchor-link"><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <button type="button" class="dest-anchor-arrow next" aria-label="Suivant">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18l6-6-6-6" />
                    </svg>
                </button>

            </div>
        </div>

        <style>
            /* Design Premium "Glassmorphism" connecté à pc-base.css */
            .dest-anchor-wrapper {
                position: -webkit-sticky;
                position: sticky;
                top: 20px;
                z-index: 99;
                display: flex;
                justify-content: center;
                margin: 0 0 2rem 0;
                pointer-events: none;
                font-family: var(--pc-font-body, system-ui, sans-serif);
                /* Raccordé à pc-base.css */
            }

            .dest-anchor-menu {
                pointer-events: auto;
                position: relative;
                display: flex;
                align-items: center;
                max-width: 95%;
                background: rgba(255, 255, 255, 0.90);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 999px;
                /* Retour de l'arrondi total en forme de pilule ! */
                padding: 0.4rem;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            }

            .dest-anchor-scroller {
                overflow-x: auto;
                scroll-behavior: smooth;
                -ms-overflow-style: none;
                scrollbar-width: none;
                border-radius: 999px;
            }

            .dest-anchor-scroller::-webkit-scrollbar {
                display: none;
            }

            .dest-anchor-nav {
                display: flex;
                align-items: center;
                white-space: nowrap;
                padding: 0 0.5rem;
            }

            .dest-anchor-link {
                display: inline-block;
                padding: 0.6rem 1.2rem;
                margin: 0 0.2rem;
                color: var(--pc-color-text, #3a3a3a);
                /* Raccordé à pc-base.css */
                font-weight: 500;
                font-size: 0.95rem;
                text-decoration: none;
                border-radius: 999px;
                transition: all 0.3s ease;
            }

            .dest-anchor-link:hover {
                color: var(--pc-color-primary, #007a92);
                /* Raccordé à pc-base.css */
                /* Utilisation de color-mix natif pour créer l'effet hover transparent basé sur ta couleur primaire */
                background: color-mix(in srgb, var(--pc-color-primary) 8%, transparent);
            }

            .dest-anchor-link.is-active {
                background: var(--pc-color-primary, #007a92);
                /* Raccordé à pc-base.css */
                color: var(--pc-color-btn-text, #ffffff);
                box-shadow: 0 4px 12px color-mix(in srgb, var(--pc-color-primary) 30%, transparent);
                font-weight: 600;
            }

            .dest-anchor-arrow {
                background: #ffffff;
                border: none;
                color: var(--pc-color-muted, #6f6f6f);
                /* Raccordé à pc-base.css */
                width: 36px;
                height: 36px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
                position: absolute;
                z-index: 2;
                box-shadow: var(--pc-shadow-soft, 0 2px 8px rgba(0, 0, 0, 0.1));
            }

            .dest-anchor-arrow:disabled {
                opacity: 0;
                pointer-events: none;
                transform: scale(0.8);
            }

            .dest-anchor-arrow:hover {
                color: var(--pc-color-primary-hover, #005f73);
                /* Raccordé à pc-base.css */
                transform: scale(1.05);
            }

            .dest-anchor-arrow svg {
                width: 18px;
                height: 18px;
            }

            .dest-anchor-arrow.prev {
                left: 8px;
            }

            .dest-anchor-arrow.next {
                right: 8px;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const menu = document.getElementById('destination-anchor-menu');
                if (!menu) return;

                const scroller = menu.querySelector('.dest-anchor-scroller');
                const nav = menu.querySelector('.dest-anchor-nav');
                const prevBtn = menu.querySelector('.dest-anchor-arrow.prev');
                const nextBtn = menu.querySelector('.dest-anchor-arrow.next');
                let links = Array.from(nav.querySelectorAll('.dest-anchor-link'));

                if (!scroller || !nav || links.length === 0) return;

                // 🪄 Nettoyage des ancres fantômes 
                // On attend que le DOM soit chargé pour vérifier si les IDs existent bien
                links.forEach(link => {
                    const targetId = link.getAttribute('href').slice(1);
                    if (!document.getElementById(targetId)) {
                        link.remove();
                    }
                });

                // Mise à jour de la liste après nettoyage
                links = Array.from(nav.querySelectorAll('.dest-anchor-link'));
                if (links.length === 0) {
                    menu.closest('.dest-anchor-wrapper').style.display = 'none';
                    return;
                }

                // --- 1) Scroll offset
                function getScrollOffset() {
                    const adminBar = document.getElementById('wpadminbar');
                    const headerCustom = parseInt(menu.getAttribute('data-header-offset') || '68', 10);
                    const menuHeight = menu.offsetHeight;
                    return (adminBar ? adminBar.offsetHeight : 0) + headerCustom + menuHeight + 16;
                }

                // --- 2) Scroll vers section au clic
                nav.addEventListener('click', function(e) {
                    const link = e.target.closest('.dest-anchor-link');
                    if (!link) return;

                    const targetId = link.getAttribute('href').slice(1);
                    const targetEl = document.getElementById(targetId);
                    if (targetEl) {
                        e.preventDefault();
                        const y = targetEl.getBoundingClientRect().top + window.pageYOffset - getScrollOffset();
                        window.scrollTo({
                            top: y,
                            behavior: 'smooth'
                        });
                    }
                });

                // --- 3) Surbrillance lien actif + auto-centrage
                let observer;

                function createObserver() {
                    if (observer) observer.disconnect();

                    observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            const id = entry.target.getAttribute('id');
                            const link = nav.querySelector(`a[href="#${id}"]`);
                            if (entry.isIntersecting && link) {
                                links.forEach(l => l.classList.remove('is-active'));
                                link.classList.add('is-active');

                                const scrollPos = link.offsetLeft - (scroller.clientWidth / 2) + (link.clientWidth / 2);
                                scroller.scrollTo({
                                    left: scrollPos,
                                    behavior: 'smooth'
                                });
                            }
                        });
                    }, {
                        root: null,
                        rootMargin: `-${getScrollOffset()}px 0px -65% 0px`,
                        threshold: [0, 0.01, 0.1]
                    });

                    links.forEach(link => {
                        const id = link.getAttribute('href').slice(1);
                        const el = document.getElementById(id);
                        if (el) observer.observe(el);
                    });
                }

                // --- 4) Flèches de défilement horizontal
                function updateArrowState() {
                    const maxScroll = scroller.scrollWidth - scroller.clientWidth;
                    if (prevBtn) prevBtn.disabled = scroller.scrollLeft < 1;
                    if (nextBtn) nextBtn.disabled = scroller.scrollLeft >= (maxScroll - 1);
                }

                if (prevBtn) prevBtn.addEventListener('click', () => {
                    scroller.scrollBy({
                        left: -scroller.clientWidth * 0.8,
                        behavior: 'smooth'
                    });
                });
                if (nextBtn) nextBtn.addEventListener('click', () => {
                    scroller.scrollBy({
                        left: scroller.clientWidth * 0.8,
                        behavior: 'smooth'
                    });
                });

                scroller.addEventListener('scroll', updateArrowState, {
                    passive: true
                });

                // Init + resize
                createObserver();
                updateArrowState();
                window.addEventListener('resize', () => {
                    updateArrowState();
                    createObserver();
                }, {
                    passive: true
                });
            });
        </script>

<?php echo ob_get_clean();
    }
}
// N'oublie pas d'initialiser la classe si ton système ne le fait pas automatiquement !
new PC_Destination_Anchor_Menu_Shortcode();
