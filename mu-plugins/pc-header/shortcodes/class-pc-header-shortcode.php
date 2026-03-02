<?php

/**
 * Gestion du shortcode principal du Header
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_Shortcode
{
    /**
     * Enregistre le shortcode
     */
    public function register()
    {
        add_shortcode('pc_header_global', [$this, 'render']);
    }

    /**
     * Rendu HTML du shortcode
     */
    public function render($atts = []): string
    {
        // Éviter les fatals dans l'éditeur Elementor (preview/iframe/admin render)
        if (class_exists('\Elementor\Plugin')) {
            try {
                if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                    return '<div style="padding:10px;border:1px dashed #ccc;font-size:13px;">PC Header Global — rendu désactivé dans l’éditeur Elementor.</div>';
                }
            } catch (\Throwable $e) {
                // Si Elementor change son API, on reste safe et on continue le rendu normal en front.
            }
        }

        // Chargement des CSS/JS
        PC_Header_Asset_Manager::enqueue_assets();

        // Récupération de la configuration
        $cfg = PC_Header_Config::get();

        // Récupération et construction de l'arbre des menus
        $items = PC_Header_Menu_Helper::get_items($cfg['menu_name']);
        $tree  = PC_Header_Menu_Helper::build_tree($items);

        $services_items = PC_Header_Menu_Helper::get_items($cfg['menu_services_name']);
        $services_tree  = PC_Header_Menu_Helper::build_tree($services_items);

        if (!$tree) {
            return '<div id="pc-header" class="pc-hg"><div class="pc-container"><p style="margin:0;padding:12px">Menu introuvable : ' . esc_html($cfg['menu_name']) . '</p></div></div>';
        }

        $search_url = esc_url(home_url($cfg['search_url']));

        ob_start();
?>
        <div id="pc-header" class="pc-hg pc-hg-smart" data-pc-hg>
            <div class="pc-hg__bar" aria-hidden="false">
                <div class="pc-hg__container pc-hg__bar-inner">

                    <div class="pc-hg__social" aria-label="Réseaux sociaux">
                        <?php foreach ($cfg['social'] as $s): ?>
                            <a class="pc-hg__social-link" href="<?php echo esc_url($s['href']); ?>" target="_blank" rel="noopener">
                                <span class="sr-only"><?php echo esc_html($s['label']); ?></span>
                                <span class="pc-hg__social-ico" aria-hidden="true"><?php echo PC_Header_SVG_Helper::get($s['key']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <nav class="pc-hg__services-desktop" aria-label="Services complémentaires">
                        <ul class="pc-nav__list">
                            <?php echo PC_Header_Render_Helper::render_navigation($services_tree); ?>
                        </ul>
                    </nav>

                    <div class="pc-hg__topsearch">
                        <label class="sr-only" for="pc-hg-search">Recherche</label>
                        <div class="pc-hg__searchbox" role="combobox" aria-haspopup="listbox" aria-expanded="false">
                            <input
                                id="pc-hg-search"
                                class="pc-hg__searchinput"
                                type="search"
                                inputmode="search"
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                placeholder="<?php echo esc_attr($cfg['search_placeholder']); ?>"
                                aria-autocomplete="list"
                                aria-controls="pc-hg-search-list"
                                aria-expanded="false" />
                            <div id="pc-hg-search-list" class="pc-hg__searchlist" role="listbox" hidden></div>
                        </div>
                    </div>

                    <a class="pc-hg__tel" href="<?php echo esc_url($cfg['tel_href']); ?>">
                        <span class="pc-hg__icon" aria-hidden="true"><?php echo PC_Header_SVG_Helper::get('phone'); ?></span>
                        <span class="pc-hg__tel-text"><?php echo esc_html($cfg['tel_label']); ?></span>
                    </a>

                </div>
            </div>

            <div class="pc-hg__main">
                <div class="pc-container pc-hg__main-inner">
                    <?php echo PC_Header_Render_Helper::render_logo($cfg); ?>

                    <nav class="pc-nav" aria-label="Navigation principale">
                        <ul class="pc-nav__list" role="menubar">
                            <?php echo PC_Header_Render_Helper::render_navigation($tree); ?>
                        </ul>
                    </nav>

                    <div class="pc-hg__actions">
                        <a class="pc-btn pc-btn--primary pc-hg__search" href="<?php echo $search_url; ?>">Rechercher</a>
                        <button class="pc-hg__burger" type="button" aria-label="Ouvrir le menu" aria-controls="pc-offcanvas" aria-expanded="false" data-pc-oc-open>
                            <span class="pc-hg__burger-ico" aria-hidden="true"><?php echo PC_Header_SVG_Helper::get('menu'); ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="pc-hg__panels" aria-hidden="false">
                <?php echo PC_Header_Render_Helper::render_mega_panels($tree, $cfg); ?>
                <?php echo PC_Header_Render_Helper::render_mega_panels($services_tree, $cfg); ?>
            </div>

            <?php echo PC_Header_Render_Helper::render_offcanvas($tree, $cfg, $services_tree); ?>
        </div>
<?php
        return ob_get_clean();
    }
}
