<?php

/**
 * Composant Shortcode : Menu d'ancres de navigation Expériences [pc_experience_anchor_menu]
 * Design : Pilule Flottante Premium (Glassmorphism) calqué sur le modèle Housing V3
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Experience_Anchor_Menu_Shortcode extends PC_Experience_Shortcode_Base
{
    public function __construct()
    {
        add_shortcode('pc_experience_anchor_menu', [$this, 'render']);
    }

    public function render(array $atts = []): void
    {
        // 1. Possibilité de masquer manuellement via Elementor (ex: [pc_experience_anchor_menu hide="avis,photos"])
        $parsed_atts = shortcode_atts(['hide' => ''], $atts);
        $hidden_keys = array_filter(array_map('trim', explode(',', strtolower($parsed_atts['hide']))));

        $all_links = [
            'description'  => 'Description',
            'photos'       => 'Photos',
            'emplacement'  => 'Emplacement',
            'tarifs-devis' => 'Tarifs & Devis',
            'a-savoir'     => 'À Savoir',
            'avis'         => 'Avis'
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

        <div class="pc-anchor-wrapper">
            <div class="pc-anchor-pill" id="experience-anchor-menu" role="navigation" aria-label="Navigation de la page">

                <button type="button" class="pc-anchor-arrow prev" aria-label="Précédent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>

                <div class="pc-anchor-scroller">
                    <nav class="pc-anchor-nav">
                        <?php foreach ($links as $id => $label): ?>
                            <a href="#<?php echo esc_attr($id); ?>" class="pc-anchor-link"><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <button type="button" class="pc-anchor-arrow next" aria-label="Suivant">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18l6-6-6-6" />
                    </svg>
                </button>

            </div>
        </div>

        <style>
            /* On réutilise strictement la structure CSS du modèle Housing pour garder une cohérence UI parfaite */
            .pc-anchor-wrapper {
                position: -webkit-sticky;
                position: sticky;
                top: 20px;
                z-index: 99;
                display: flex;
                justify-content: center;
                margin: 0 0 2rem 0;
                pointer-events: none;
                font-family: var(--pc-font-family-body, system-ui, sans-serif);
            }

            .pc-anchor-pill {
                pointer-events: auto;
                position: relative;
                display: flex;
                align-items: center;
                max-width: 95%;
                background: rgba(255, 255, 255, 0.90);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 999px;
                padding: 0.4rem;
                box-shadow: 0 10px 40px rgba(14, 43, 92, 0.12), 0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            }

            .pc-anchor-scroller {
                overflow-x: auto;
                scroll-behavior: smooth;
                -ms-overflow-style: none;
                scrollbar-width: none;
                border-radius: 999px;
            }

            .pc-anchor-scroller::-webkit-scrollbar {
                display: none;
            }

            .pc-anchor-nav {
                display: flex;
                align-items: center;
                white-space: nowrap;
                padding: 0 0.5rem;
            }

            .pc-anchor-link {
                display: inline-block;
                padding: 0.6rem 1.2rem;
                margin: 0 0.2rem;
                color: #475569;
                font-weight: 500;
                font-size: 0.95rem;
                text-decoration: none;
                border-radius: 999px;
                transition: all 0.3s ease;
            }

            .pc-anchor-link:hover {
                color: var(--pc-primary, #0e2b5c);
                background: rgba(14, 43, 92, 0.05);
            }

            .pc-anchor-link.is-active {
                background: var(--pc-primary, #0e2b5c);
                color: #ffffff;
                box-shadow: 0 4px 12px rgba(14, 43, 92, 0.2);
                font-weight: 600;
            }

            .pc-anchor-arrow {
                background: #ffffff;
                border: none;
                color: #64748b;
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
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .pc-anchor-arrow:disabled {
                opacity: 0;
                pointer-events: none;
                transform: scale(0.8);
            }

            .pc-anchor-arrow:hover {
                color: var(--pc-primary, #0e2b5c);
                transform: scale(1.05);
            }

            .pc-anchor-arrow svg {
                width: 18px;
                height: 18px;
            }

            .pc-anchor-arrow.prev {
                left: 8px;
            }

            .pc-anchor-arrow.next {
                right: 8px;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const menu = document.getElementById('experience-anchor-menu');
                if (!menu) return;

                const scroller = menu.querySelector('.pc-anchor-scroller');
                const nav = menu.querySelector('.pc-anchor-nav');
                const prevBtn = menu.querySelector('.pc-anchor-arrow.prev');
                const nextBtn = menu.querySelector('.pc-anchor-arrow.next');

                // On utilise 'let' car la liste va être filtrée
                let links = Array.from(nav.querySelectorAll('.pc-anchor-link'));

                if (!scroller || !nav || links.length === 0) return;

                // 🪄 MAGIE V3 : Auto-nettoyage des ancres fantômes !
                // Si la section n'existe pas sur la page Elementor, on supprime son bouton du menu dynamiquement.
                links.forEach(link => {
                    const targetId = link.getAttribute('href').slice(1);
                    if (!document.getElementById(targetId)) {
                        link.remove(); // Retire l'élément HTML du menu
                    }
                });

                // On met à jour la liste des liens avec ceux qui ont survécu au nettoyage
                links = Array.from(nav.querySelectorAll('.pc-anchor-link'));

                // Si le menu s'est entièrement vidé, on cache carrément la pilule
                if (links.length === 0) {
                    menu.style.display = 'none';
                    return;
                }

                // --- 1. Calcul de l'offset (Inspiré de ton script actuel + Housing) ---
                function getScrollOffset() {
                    const adminBar = document.getElementById('wpadminbar');
                    const menuHeight = menu.offsetHeight;
                    const headerHeight = parseInt(getComputedStyle(document.querySelector('.pc-anchor-wrapper')).top) || 68;
                    return (adminBar ? adminBar.offsetHeight : 0) + headerHeight + menuHeight + 16;
                }

                nav.addEventListener('click', function(e) {
                    const link = e.target.closest('.pc-anchor-link');
                    if (!link) return;

                    const targetId = link.getAttribute('href').slice(1);
                    const targetElement = document.getElementById(targetId);

                    if (targetElement) {
                        e.preventDefault();
                        const y = targetElement.getBoundingClientRect().top + window.pageYOffset - getScrollOffset();
                        window.scrollTo({
                            top: y,
                            behavior: 'smooth'
                        });
                    }
                });

                // --- 2. Mise en surbrillance au scroll ---
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const id = entry.target.getAttribute('id');
                            const activeLink = nav.querySelector(`a[href="#${id}"]`);

                            if (activeLink) {
                                links.forEach(l => l.classList.remove('is-active'));
                                activeLink.classList.add('is-active');

                                // Scroll horizontal calculé mathématiquement (ne bloque plus le scroll vertical !)
                                const scrollPos = activeLink.offsetLeft - (scroller.clientWidth / 2) + (activeLink.clientWidth / 2);
                                scroller.scrollTo({
                                    left: scrollPos,
                                    behavior: 'smooth'
                                });
                            }
                        }
                    });
                }, {
                    rootMargin: `-${getScrollOffset()}px 0px -65% 0px`
                });

                links.forEach(link => {
                    const targetId = link.getAttribute('href').slice(1);
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) observer.observe(targetElement);
                });

                // --- 3. Gestion des flèches ---
                function updateArrowState() {
                    const scrollLeft = scroller.scrollLeft;
                    const maxScroll = scroller.scrollWidth - scroller.clientWidth;
                    if (prevBtn) prevBtn.disabled = scrollLeft <= 2;
                    if (nextBtn) nextBtn.disabled = scrollLeft >= (maxScroll - 2);
                }

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        const scrollAmount = scroller.clientWidth * 0.8;
                        scroller.scrollBy({
                            left: -scrollAmount,
                            behavior: 'smooth'
                        });
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        const scrollAmount = scroller.clientWidth * 0.8;
                        scroller.scrollBy({
                            left: scrollAmount,
                            behavior: 'smooth'
                        });
                    });
                }

                scroller.addEventListener('scroll', updateArrowState, {
                    passive: true
                });
                window.addEventListener('resize', updateArrowState, {
                    passive: true
                });
                setTimeout(updateArrowState, 150);
            });
        </script>

<?php echo ob_get_clean();
    }
}
