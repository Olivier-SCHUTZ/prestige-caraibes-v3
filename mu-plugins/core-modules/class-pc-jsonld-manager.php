<?php
// Fichier : mu-plugins/core-modules/class-pc-jsonld-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class PC_JsonLD_Manager
{

    // === PROPRIÉTÉS ===
    private static $instance = null;
    private $faq_printed = false;

    // === INITIALISATION (Singleton) ===
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    public function init_hooks()
    {
        // --- 1. Globaux (Organization, WebSite) ---
        add_action('wp_head', [$this, 'output_global_schemas'], 48);

        // --- 2. Pages, FAQ & ItemList (Accueil/Catégories) ---
        add_action('wp_head', [$this, 'output_webpage_and_faq_schema'], 11);

        // --- 3. CPT : Destination (TouristDestination & FAQ) ---
        add_action('wp_head', [$this, 'output_cpt_schemas'], 12);

        // --- 4. CPT : Logements (VacationRental) ---
        add_action('wp_footer', [$this, 'output_vacation_rental_schema'], 99);

        // --- 5. CPT : Expériences (Product) ---
        add_action('wp_footer', [$this, 'output_product_schema'], 98);

        // --- 6. Articles & Archives ---
        add_action('wp_footer', [$this, 'output_article_schema'], 96);
        add_action('wp_head', [$this, 'output_blog_archive_schema'], 48);

        // --- 7. Pages de Recherche ---
        add_action('wp_footer', [$this, 'output_search_results_schema'], 1);

        // --- 8. Fil d'Ariane (Breadcrumb) ---
        add_action('wp_footer', [$this, 'output_breadcrumb_schema'], 100);

        // --- 9. Recommandations (Destinations & Expériences) ---
        add_action('wp_footer', [$this, 'output_recommendation_lists'], 52);

        // --- 10. Gardien Anti-Doublons FAQ ---
        add_action('template_redirect', [$this, 'setup_faq_guardian'], 9999);
    }

    // ==========================================
    // HELPERS INTERNES (Nettoyage & Formatage)
    // ==========================================
    private function clean_text($html, $len = 1200)
    {
        $t = wp_strip_all_tags((string)$html, true);
        $t = preg_replace('/\s+/', ' ', $t);
        if ($len && mb_strlen($t) > $len) $t = rtrim(mb_substr($t, 0, $len - 1)) . '…';
        return $t;
    }

    private function clean_array_recursive($a)
    {
        if (!is_array($a)) return $a;
        foreach ($a as $k => $v) {
            if (is_array($v)) $a[$k] = $this->clean_array_recursive($v);
        }
        return array_filter($a, function ($x) {
            return $x !== null && $x !== '' && $x !== [];
        });
    }

    private function print_jsonld(array $data, string $class = '')
    {
        if (empty($data)) return;
        $json = wp_json_encode($this->clean_array_recursive($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $class_attr = $class ? " class='" . esc_attr($class) . "'" : '';
        echo "\n<script type='application/ld+json'{$class_attr}>{$json}</script>\n";
    }

    private function get_field_or_meta($key, $post_id, $default = '', $raw = false)
    {
        if (function_exists('get_field')) {
            $v = get_field($key, $post_id);
            if ($raw) return $v;
            if ($v !== null && $v !== false && $v !== '') return is_string($v) ? trim($v) : $v;
        }
        if (function_exists('pcseo_get_meta')) {
            $suffix = preg_replace('~^(pc_|exp_|dest_|post_)~', '', $key);
            $v = pcseo_get_meta($post_id, $suffix);
            if ($raw) return $v;
            if ($v !== '' && $v !== null) return is_string($v) ? trim($v) : $v;
        }
        $v = get_post_meta($post_id, $key, true);
        if ($raw) return $v;
        return ($v !== '' && $v !== null) ? (is_string($v) ? trim($v) : $v) : $default;
    }

    private function get_global_option($key)
    {
        return function_exists('get_field') ? (get_field($key, 'option') ?: '') : get_option($key, '');
    }

    private function is_elementor_edit_mode()
    {
        return (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode());
    }

    // ==========================================
    // 1. SCHÉMAS GLOBAUX (Organization & WebSite)
    // ==========================================
    public function output_global_schemas()
    {
        if (is_admin() || $this->is_elementor_edit_mode()) return;
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (is_feed() || strpos($uri, '/wp-sitemap') !== false || strpos($uri, '/robots.txt') !== false) return;

        $site_url  = trailingslashit($this->get_global_option('pc_org_url') ?: home_url('/'));
        $site_name = $this->get_global_option('pc_org_name') ?: get_bloginfo('name');
        $legal     = $this->get_global_option('pc_org_legal_name') ?: '';
        $logo      = $this->get_global_option('pc_org_logo') ?: '';

        if (!$logo && function_exists('get_custom_logo')) {
            $cid = get_theme_mod('custom_logo');
            if ($cid) $logo = wp_get_attachment_image_url($cid, 'full');
        }
        if (!$logo && function_exists('get_site_icon_url')) {
            $logo = get_site_icon_url();
        }

        $addr = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $this->get_global_option('pc_org_address_street') ?: '',
            'addressLocality' => $this->get_global_option('pc_org_address_locality') ?: '',
            'addressRegion'   => $this->get_global_option('pc_org_address_region') ?: '',
            'postalCode'      => $this->get_global_option('pc_org_address_postal') ?: '',
            'addressCountry'  => $this->get_global_option('pc_org_address_country') ?: '',
        ];

        $sameas_raw = $this->get_global_option('pc_org_sameas') ?: '';
        $sameas = [];
        foreach (preg_split('/\R+/', (string)$sameas_raw) as $l) {
            $l = trim($l);
            if ($l !== '') $sameas[] = $l;
        }

        $lang   = get_bloginfo('language') ?: 'fr-FR';
        $org_id = $site_url . '#organization';
        $web_id = $site_url . '#website';

        // Organization
        $org = [
            '@context'   => 'https://schema.org',
            '@type'      => 'Organization',
            '@id'        => $org_id,
            'url'        => $site_url,
            'name'       => $site_name,
            'inLanguage' => $lang,
        ];
        if ($legal) $org['legalName'] = $legal;
        if ($logo)  $org['logo']      = $logo;
        if ($phone = $this->get_global_option('pc_org_phone')) $org['telephone'] = $phone;
        if ($email = $this->get_global_option('pc_org_email')) $org['email']     = $email;
        if ($vat   = $this->get_global_option('pc_org_vat_id')) $org['vatID']     = $vat;
        if (!empty($addr) && count(array_filter($addr)) > 1) $org['address'] = $addr;
        if (!empty($sameas)) $org['sameAs'] = $sameas;

        $this->print_jsonld($org, 'pc-seo-organization-schema');

        // WebSite
        $search_target = $this->get_global_option('pc_site_search_target') ?: home_url('/?s={search_term_string}');
        if (stripos($search_target, '{search_term_string}') === false) {
            $search_target = home_url('/?s={search_term_string}');
        }

        $web = [
            '@context'      => 'https://schema.org',
            '@type'         => 'WebSite',
            '@id'           => $web_id,
            'url'           => $site_url,
            'name'          => $site_name,
            'inLanguage'    => $lang,
            'publisher'     => ['@id' => $org_id],
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => $search_target,
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $this->print_jsonld($web, 'pc-seo-website-schema');
    }

    // ==========================================
    // 2. WEBPAGE & FAQ (Pages statiques)
    // ==========================================
    public function output_webpage_and_faq_schema()
    {
        if (!is_singular('page')) return;
        $page_id = get_queried_object_id();
        if (!$page_id) return;

        $kind = $this->get_field_or_meta('pc_schema_kind', $page_id, 'generic');

        $url = get_permalink($page_id);
        $types = ['WebPage'];
        if (in_array($kind, ['accueil', 'category'])) $types[] = 'CollectionPage';

        $webpage = [
            '@context' => 'https://schema.org',
            '@type'    => array_unique($types),
            '@id'      => trailingslashit($url) . '#webpage',
            'url'      => $url,
            'name'     => get_the_title($page_id),
            'isPartOf' => ['@id' => rtrim(home_url('/'), '/') . '/#website'],
        ];

        if ($desc_acf = $this->get_field_or_meta('pc_meta_description', $page_id)) {
            $webpage['description'] = $desc_acf;
        }

        // ItemList pour Accueil/Catégories
        if (in_array($kind, ['accueil', 'category'])) {
            $category_slug_to_query = '';
            $is_paginated = false;

            if ($kind === 'accueil' && is_front_page()) {
                $category_slug_to_query = 'accueil';
            } else {
                $page_slug = get_post_field('post_name', $page_id);
                $slug_to_category_map = [
                    'location-appartement-en-guadeloupe'   => 'appartements',
                    'promotion-villa-en-guadeloupe'        => 'promotions',
                    'location-grande-villa-en-guadeloupe'  => 'grandes-villas',
                    'location-villa-en-guadeloupe'         => 'villas-traditions',
                    'location-villa-de-luxe-en-guadeloupe' => 'villas-prestige',
                ];
                if (isset($slug_to_category_map[$page_slug])) {
                    $category_slug_to_query = $slug_to_category_map[$page_slug];
                    $is_paginated = true;
                }
            }

            if (!empty($category_slug_to_query)) {
                $current_page = max(1, get_query_var('paged'), get_query_var('page'));
                if (!empty($_GET)) {
                    foreach ($_GET as $key => $value) {
                        if (strpos($key, 'e-page-') === 0 && is_numeric($value)) {
                            $current_page = (int) $value;
                            break;
                        }
                    }
                }

                $query_args = [
                    'post_type' => ['logement', 'villa', 'appartement'],
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'tax_query' => [['taxonomy' => 'categorie_logement', 'field' => 'slug', 'terms' => $category_slug_to_query]],
                ];

                if ($is_paginated) {
                    $query_args['posts_per_page'] = 6;
                    $query_args['paged'] = $current_page;
                } else {
                    $query_args['posts_per_page'] = -1;
                }

                $loop_query = new WP_Query($query_args);
                $item_ids = $loop_query->posts;

                if (!empty($item_ids)) {
                    $items = [];
                    $pos = $is_paginated ? (($current_page - 1) * 6) + 1 : 1;
                    foreach ($item_ids as $pid) {
                        $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'item' => ['@id' => get_permalink($pid), 'name' => get_the_title($pid)]];
                    }
                    if (!empty($items)) {
                        $webpage['mainEntity'] = ['@type' => 'ItemList', 'itemListElement' => $items, 'numberOfItems' => count($items)];
                    }
                }
            }
        }

        $this->print_jsonld($webpage, 'pc-seo-page-schema');

        // Génération FAQ générique
        if (empty($GLOBALS['pc_faq_schema_printed'])) {
            $faq_rows = $this->get_field_or_meta('pc_faq_items', $page_id, []);
            if (is_array($faq_rows) && !empty($faq_rows)) {
                $main = [];
                foreach ($faq_rows as $row) {
                    $q = isset($row['question']) ? trim(wp_strip_all_tags($row['question'])) : '';
                    $a = isset($row['answer']) ? trim(wp_strip_all_tags($row['answer'])) : '';
                    if ($q !== '' && $a !== '') {
                        $main[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
                    }
                }
                if (!empty($main)) {
                    $this->print_jsonld(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main], 'pc-seo-faq-schema');
                    $GLOBALS['pc_faq_schema_printed'] = true;
                }
            }
        }
    }

    // ==========================================
    // 3. DESTINATIONS (TouristDestination & FAQ)
    // ==========================================
    public function output_cpt_schemas()
    {
        if ($this->is_elementor_edit_mode()) return;
        if (!is_singular(['destination', 'experience', 'villa', 'appartement', 'logement'])) return;

        $p = get_queried_object();
        if (!$p) return;

        // Destination
        if ($p->post_type === 'destination') {
            $name      = $this->get_field_or_meta('dest_h1', $p->ID) ?: get_the_title($p->ID);
            $intro_raw = $this->get_field_or_meta('dest_intro', $p->ID) ?: get_the_excerpt($p->ID);
            $desc      = trim(wp_strip_all_tags($intro_raw));

            $img_data  = $this->get_field_or_meta('dest_hero_desktop', $p->ID, '', true);
            $img_url   = '';
            if (is_array($img_data) && !empty($img_data['url'])) {
                $img_url = $img_data['url'];
            } elseif (is_numeric($img_data)) {
                $img_url = wp_get_attachment_image_url($img_data, 'full');
            } elseif (is_string($img_data)) {
                $img_url = $img_data;
            }

            $schema = [
                '@context' => 'https://schema.org',
                '@type'    => 'TouristDestination',
                'name'     => $name,
                'url'      => get_permalink($p->ID),
            ];
            if ($desc) $schema['description'] = $desc;
            if ($img_url) $schema['image'] = esc_url($img_url);

            $this->print_jsonld($schema, 'pc-seo-destination-schema');
        }

        // FAQ Partagée (Tous CPT)
        if (empty($GLOBALS['pc_faq_schema_printed'])) {
            $field_map = ['destination' => 'dest_faq', 'experience' => 'exp_faq', 'villa' => 'log_faq', 'appartement' => 'log_faq', 'logement' => 'log_faq'];

            if (array_key_exists($p->post_type, $field_map)) {
                $rows = $this->get_field_or_meta($field_map[$p->post_type], $p->ID, [], true);
                if (is_array($rows) && !empty($rows)) {
                    $q_keys = ['question', 'exp_question', 'dest_question', 'log_question'];
                    $a_keys = ['answer', 'reponse', 'exp_reponse', 'dest_reponse', 'log_reponse'];
                    $main = [];

                    foreach ($rows as $row) {
                        $q = '';
                        $a = '';
                        if (is_array($row)) {
                            foreach ($q_keys as $k) {
                                if (!empty($row[$k])) {
                                    $q = $row[$k];
                                    break;
                                }
                            }
                            foreach ($a_keys as $k) {
                                if (!empty($row[$k])) {
                                    $a = $row[$k];
                                    break;
                                }
                            }
                        }
                        $q = trim(wp_strip_all_tags($q));
                        $a = trim(wp_strip_all_tags($a));
                        if ($q !== '' && $a !== '') {
                            $main[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
                        }
                    }

                    if (!empty($main)) {
                        $this->print_jsonld(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main], 'pc-seo-cpt-faq-schema');
                        $GLOBALS['pc_faq_schema_printed'] = true;
                    }
                }
            }
        }
    }

    // ==========================================
    // 4. LOGEMENTS (VacationRental)
    // ==========================================
    public function output_vacation_rental_schema()
    {
        if ($this->is_elementor_edit_mode() || !is_singular(['villa', 'appartement', 'logement'])) return;

        static $done = false;
        if ($done) return;
        $done = true;

        $p = get_queried_object();
        $id = $p->ID;

        $name = get_the_title($p);
        $desc = $this->clean_text($this->get_field_or_meta('seo_long_html', $id));
        if (empty($desc)) {
            $desc = $this->clean_text(get_post_field('post_excerpt', $id) ?: get_post_field('post_content', $id));
        }

        // Images
        $imgs = [];
        $raw_urls = $this->get_field_or_meta('seo_gallery_urls', $id, '', true);
        if (is_string($raw_urls) && trim($raw_urls) !== '') {
            foreach (preg_split('/\R+/', trim($raw_urls)) as $u) {
                if (trim($u)) $imgs[] = esc_url(trim($u));
            }
        }
        if (empty($imgs) && has_post_thumbnail($id)) {
            if ($thumb = wp_get_attachment_image_url(get_post_thumbnail_id($id), 'full')) $imgs[] = $thumb;
        }
        $imgs = array_slice($imgs, 0, 12);

        // Address
        $address = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $this->get_field_or_meta('adresse_rue', $id) ?: null,
            'addressLocality' => $this->get_field_or_meta('ville', $id) ?: null,
            'addressRegion'   => 'Guadeloupe',
            'postalCode'      => $this->get_field_or_meta('code_postal', $id) ?: null,
            'addressCountry'  => 'GP',
        ];

        // Geo
        $geo = [];
        $lat = $lng = null;
        $gc = $this->get_field_or_meta('geo_coords', $id);
        if (is_string($gc) && strpos($gc, ',') !== false) {
            [$lat, $lng] = array_map('trim', explode(',', $gc, 2));
        }
        if (!$lat || !$lng) {
            $lat = $this->get_field_or_meta('latitude', $id);
            $lng = $this->get_field_or_meta('longitude', $id);
        }
        if ($lat && $lng) {
            $geo = ['@type' => 'GeoCoordinates', 'latitude' => (float)$lat, 'longitude' => (float)$lng];
        }

        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'VacationRental',
            'name'        => $name,
            'identifier'  => (string) $id,
            'url'         => get_permalink($id),
            'description' => $desc,
            'additionalType' => ($p->post_type === 'villa') ? 'House' : (($p->post_type === 'appartement') ? 'Apartment' : 'Residence'),
        ];

        if (!empty($imgs)) $data['image'] = $imgs;
        if (count(array_filter($address)) > 3) $data['address'] = $address;
        if (!empty($geo)) $data['geo'] = $geo;

        // Accommodation
        $acc = ['@type' => 'Accommodation', 'additionalType' => 'EntirePlace'];
        $beds = (int)$this->get_field_or_meta('nombre_de_chambres', $id);
        $baths = (float)$this->get_field_or_meta('nombre_sdb', $id);
        $cap = (int)$this->get_field_or_meta('capacite', $id);

        if ($beds > 0)  $acc['numberOfBedrooms'] = $beds;
        if ($baths > 0) $acc['numberOfBathroomsTotal'] = $baths;
        if ($cap > 0)   $acc['occupancy'] = ['@type' => 'QuantitativeValue', 'value' => $cap];

        $features = [];
        $amenKeys = (array)$this->get_field_or_meta('google_vr_amenities', $id, [], true);
        foreach ($amenKeys as $k) {
            if (is_string($k) && trim($k)) $features[] = ['@type' => 'LocationFeatureSpecification', 'name' => trim($k), 'value' => true];
        }
        if (!empty($features)) $acc['amenityFeature'] = $features;

        if (count($acc) > 2) $data['containsPlace'] = $acc;

        // Avis
        $reviews = $this->get_reviews_for_post($id);
        if ($reviews) {
            $data['aggregateRating'] = $reviews['aggregate'];
            if (!empty($reviews['items'])) $data['review'] = $reviews['items'];
        }

        $this->print_jsonld($data, 'pc-seo-vr-schema');
    }

    // ==========================================
    // 5. EXPÉRIENCES (Product)
    // ==========================================
    public function output_product_schema()
    {
        if (!is_singular('experience') || is_admin() || $this->is_elementor_edit_mode()) return;

        static $done = false;
        if ($done) return;
        $done = true;

        $p = get_queried_object();
        $id = $p->ID;

        $name = $this->get_field_or_meta('exp_h1_custom', $id) ?: get_the_title($id);
        $desc = $this->get_field_or_meta('exp_meta_description', $id) ?: $this->clean_text(get_the_excerpt($id));

        $img = $this->get_field_or_meta('exp_hero_desktop', $id, '', true) ?: get_the_post_thumbnail_url($id, 'full');
        if (is_array($img) && isset($img['url'])) $img = $img['url'];
        elseif (is_numeric($img)) $img = wp_get_attachment_image_url($img, 'full');

        $tarifs = $this->get_field_or_meta('exp_types_de_tarifs', $id, [], true);
        $price = 0;
        $is_devis = true;

        if (is_array($tarifs) && !empty($tarifs)) {
            $first = $tarifs[0];
            if (($first['exp_type'] ?? '') !== 'sur-devis' && !empty($first['exp_tarifs_lignes'])) {
                foreach ($first['exp_tarifs_lignes'] as $ligne) {
                    if ((float)($ligne['tarif_valeur'] ?? 0) > 0) {
                        $price = (float)$ligne['tarif_valeur'];
                        $is_devis = false;
                        break;
                    }
                }
            }
        }

        $offer = [
            '@type' => 'Offer',
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/' . $this->get_field_or_meta('exp_availability', $id, 'InStock')
        ];

        if ($is_devis || $price <= 0) {
            $offer['price'] = '0';
            $offer['priceSpecification'] = ['@type' => 'PriceSpecification', 'price' => '0', 'priceCurrency' => 'EUR', 'valueAddedTaxIncluded' => 'true', 'priceType' => 'Tarif sur devis'];
        } else {
            $offer['price'] = $price;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
            'name'     => $name,
            'sku'      => (string) $id,
            'url'      => get_permalink($id),
            'brand'    => ['@type' => 'Brand', 'name' => 'Prestige Caraïbes'],
            'offers'   => $offer,
        ];
        if ($desc) $data['description'] = $desc;
        if ($img)  $data['image'] = esc_url($img);

        $reviews = $this->get_reviews_for_post($id);
        if ($reviews) {
            $data['aggregateRating'] = $reviews['aggregate'];
            $data['review'] = $reviews['items']; // Ici pas de filtre sur contentReferenceTime contrairement au VR
        }

        $this->print_jsonld($data, 'pc-seo-product-schema');
    }

    // ==========================================
    // 6. ARTICLES (BlogPosting) & ARCHIVES
    // ==========================================
    public function output_article_schema()
    {
        if (is_admin() || !is_singular('post') || $this->is_elementor_edit_mode()) return;

        static $done = false;
        if ($done) return;
        $done = true;

        $id = get_queried_object_id();
        $url = get_permalink($id);
        $lang = get_bloginfo('language') ?: 'fr-FR';

        $desc = get_post_field('post_excerpt', $id) ?: get_post_field('post_content', $id);
        $desc = $this->clean_text($desc, 300);

        $img = get_the_post_thumbnail_url($id, 'full');
        if (!$img) $img = get_site_icon_url(512);
        if (!$img && ($logo_id = get_theme_mod('custom_logo'))) $img = wp_get_attachment_image_url($logo_id, 'full');

        $author = get_the_author_meta('display_name', get_post_field('post_author', $id)) ?: 'Rédaction';
        $cats = get_the_category($id);
        $section = (!empty($cats) && is_array($cats)) ? wp_strip_all_tags($cats[0]->name) : '';

        $site = rtrim(home_url('/'), '/') . '/';
        $data = [
            '@context'         => 'https://schema.org',
            '@type'            => ['Article', 'BlogPosting'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'isPartOf'         => ['@id' => $site . '#website'],
            'publisher'        => ['@id' => $site . '#organization'],
            'inLanguage'       => $lang,
            'url'              => $url,
            'headline'         => get_the_title($id),
            'datePublished'    => get_post_time('c', true, $id),
            'dateModified'     => get_post_modified_time('c', true, $id),
            'author'           => ['@type' => 'Person', 'name' => $author],
        ];
        if ($desc) $data['description'] = $desc;
        if ($img)  $data['image'] = $img;
        if ($section) $data['articleSection'] = $section;

        $this->print_jsonld($data, 'pc-seo-article-schema');
    }

    public function output_blog_archive_schema()
    {
        if (is_admin() || is_feed() || is_404()) return;
        if (!(is_home() || (is_archive() && (is_category() || is_tag() || is_post_type_archive('post'))))) return;

        static $done = false;
        if ($done) return;
        $done = true;

        if (is_home()) {
            $name = get_the_title((int) get_option('page_for_posts')) ?: 'Magazine';
            $desc = get_bloginfo('description') ?: '';
        } else {
            $name = wp_strip_all_tags(get_the_archive_title());
            $desc = wp_strip_all_tags(term_description(get_queried_object()) ?: '');
        }
        $desc = $this->clean_text($desc, 300);

        $items = [];
        global $wp_query;
        if ($wp_query instanceof WP_Query && !empty($wp_query->posts)) {
            $paged = max(1, (int) get_query_var('paged'));
            $perpage = (int) get_query_var('posts_per_page', 10);
            $offset = ($paged - 1) * $perpage;
            $pos = 1;
            foreach (array_slice($wp_query->posts, 0, 10) as $post_obj) {
                $items[] = ['@type' => 'ListItem', 'position' => $offset + $pos++, 'item' => ['@id' => get_permalink($post_obj->ID), 'name' => get_the_title($post_obj->ID)]];
            }
        }

        $data = [
            '@context'   => 'https://schema.org',
            '@type'      => 'CollectionPage',
            'name'       => $name,
            'url'        => home_url(add_query_arg(null, null)),
            'inLanguage' => get_bloginfo('language') ?: 'fr-FR',
            'isPartOf'   => ['@id' => rtrim(home_url('/'), '/') . '/#website'],
        ];
        if ($desc) $data['description'] = $desc;
        if (!empty($items)) $data['mainEntity'] = ['@type' => 'ItemList', 'itemListElement' => $items];

        $this->print_jsonld($data, 'pc-seo-blog-archive-schema');
    }

    // ==========================================
    // 7. PAGES DE RECHERCHE
    // ==========================================
    public function output_search_results_schema()
    {
        if (!is_singular('page')) return;
        $page_id = (int) get_queried_object_id();

        if ($this->get_field_or_meta('pc_schema_kind', $page_id) !== 'search') return;
        $search_type = $this->get_field_or_meta('pc_search_type', $page_id);
        if (empty($search_type)) return;

        $items = [];
        $pos = 1;

        if ($search_type === 'logement' && function_exists('pc_get_filtered_logements')) {
            $filters = ['page' => 1, 'ville' => sanitize_text_field($_GET['ville'] ?? ''), 'date_arrivee' => sanitize_text_field($_GET['date_arrivee'] ?? ''), 'date_depart' => sanitize_text_field($_GET['date_depart'] ?? ''), 'invites' => intval($_GET['invites'] ?? 1), 'theme' => sanitize_text_field($_GET['theme'] ?? '')];
            $results = pc_get_filtered_logements($filters);
            if (!empty($results['vignettes'])) {
                foreach ($results['vignettes'] as $v) {
                    $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'item' => ['@type' => 'VacationRental', 'name' => $v['title'], 'url' => $v['link'], 'image' => $v['thumb'], 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $v['city'], 'addressRegion' => 'Guadeloupe', 'addressCountry' => 'GP']]];
                }
            }
        } elseif ($search_type === 'experience' && function_exists('pc_get_filtered_experiences')) {
            $filters = ['page' => 1, 'category' => sanitize_text_field($_GET['categorie'] ?? ''), 'ville' => sanitize_text_field($_GET['ville'] ?? ''), 'keyword' => sanitize_text_field($_GET['keyword'] ?? '')];
            $results = pc_get_filtered_experiences($filters);
            if (!empty($results['vignettes'])) {
                foreach ($results['vignettes'] as $v) {
                    $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'item' => ['@type' => 'Trip', 'name' => $v['title'], 'url' => $v['link'], 'image' => $v['thumb']]];
                }
            }
        }

        if (empty($items)) return;

        $data = [
            '@context'         => 'https://schema.org',
            '@type'            => 'SearchResultsPage',
            'name'             => get_the_title($page_id),
            'url'              => get_permalink($page_id),
            'mainEntityOfPage' => get_permalink($page_id),
            'mainEntity'       => ['@type' => 'ItemList', 'itemListElement' => $items],
        ];

        $this->print_jsonld($data, 'pc-seo-search-results-schema');
    }

    // ==========================================
    // 8. BREADCRUMBLIST (Fil d'Ariane)
    // ==========================================
    public function output_breadcrumb_schema()
    {
        if (is_admin() || $this->is_elementor_edit_mode() || is_404() || get_query_var('pcseo_is_410') || is_front_page()) return;

        $items = [];
        $pos = 1;
        $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_bloginfo('name') ?: 'Accueil', 'item' => home_url('/')];

        if (is_singular()) {
            $id = get_queried_object_id();
            $pt = get_post_type($id);

            if ($pt === 'post' && ($blog_id = (int) get_option('page_for_posts'))) {
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($blog_id), 'item' => get_permalink($blog_id)];
            }

            $override = apply_filters('pcseo_breadcrumb_archive_map', ['villa' => '/location-villa/', 'appartement' => '/location-appartement/', 'destination' => '/destinations/']);
            if (isset($override[$pt])) {
                $path = $override[$pt];
                $url = home_url(user_trailingslashit(ltrim($path, '/')));
                $page_obj = get_page_by_path(trim($path, '/'));
                $title = $page_obj ? get_the_title($page_obj) : (['villa' => 'location-villa', 'appartement' => 'location-appartement', 'destination' => 'destinations', 'experience' => 'Expériences'][$pt] ?? ucfirst($pt));
                if ($url !== get_permalink($id)) $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $title, 'item' => $url];
            } else {
                $pto = get_post_type_object($pt);
                if ($pto && !empty($pto->has_archive) && ($archive_url = get_post_type_archive_link($pt)) && $archive_url !== get_permalink($id)) {
                    $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $pto->labels->name ?: ucfirst($pt), 'item' => $archive_url];
                }
            }

            if (is_post_type_hierarchical($pt)) {
                foreach (array_reverse(get_post_ancestors($id)) as $pid) {
                    $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($pid), 'item' => get_permalink($pid)];
                }
            }
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($id), 'item' => get_permalink($id)];
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                foreach (array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')) as $tid) {
                    $t = get_term($tid, $term->taxonomy);
                    if ($t && !is_wp_error($t)) $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $t->name, 'item' => get_term_link($t)];
                }
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $term->name, 'item' => get_term_link($term)];
            }
        } elseif (is_post_type_archive()) {
            $pt = get_query_var('post_type') ?: get_post_type();
            $pto = $pt ? get_post_type_object($pt) : null;
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => ($pto && $pto->labels->name) ? $pto->labels->name : 'Archive', 'item' => home_url(add_query_arg(null, null))];
        } elseif (is_home() && !is_front_page() && ($blog_id = (int) get_option('page_for_posts'))) {
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($blog_id), 'item' => get_permalink($blog_id)];
        } else {
            return;
        }

        $items = apply_filters('pcseo_breadcrumb_items', $items);
        $items = array_values(array_filter($items, fn($li) => !empty($li['name']) && !empty($li['item'])));

        if (count($items) > 1) {
            $this->print_jsonld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items], 'pc-seo-breadcrumb');
        }
    }

    // ==========================================
    // 9. LISTES DE RECOMMANDATIONS
    // ==========================================
    public function output_recommendation_lists()
    {
        if (is_admin() || is_feed() || is_404() || !is_singular(['destination', 'experience'])) return;

        $id = get_queried_object_id();
        $pt = get_post_type($id);

        $generate = function ($ids, $class) {
            if (empty($ids)) return;
            $items = [];
            $pos = 1;
            foreach ($ids as $pid) $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'item' => ['@id' => get_permalink($pid), 'name' => get_the_title($pid)]];
            if ($items) $this->print_jsonld(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items], $class);
        };

        if ($pt === 'destination') {
            $generate((array) $this->get_field_or_meta('dest_logements_recommandes', $id, [], true), 'pc-seo-destination-lodging-list');
            $generate((array) $this->get_field_or_meta('dest_exp_featured', $id, [], true), 'pc-seo-destination-experience-list');
        } elseif ($pt === 'experience') {
            $generate((array) $this->get_field_or_meta('exp_logements_recommandes', $id, [], true), 'pc-seo-experience-lodging-list');
        }
    }

    // ==========================================
    // 10. GARDIEN ANTI-DOUBLONS (FAQ)
    // ==========================================
    public function setup_faq_guardian()
    {
        if (is_admin() || wp_doing_ajax()) return;

        ob_start(function ($html) {
            if (!preg_match_all('~<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>~is', $html, $matches)) return $html;

            $valid_found = false;
            $remove = [];

            foreach ($matches[0] as $tag) {
                if (strpos($tag, '"@type":"FAQPage"') !== false) {
                    if (preg_match('/class=["\'][^"\']*pc-seo-[^"\']*-schema[^"\']*["\']/i', $tag)) {
                        if ($valid_found) $remove[] = $tag;
                        else $valid_found = true;
                    } else {
                        $remove[] = $tag;
                    }
                }
            }
            return !empty($remove) ? str_replace($remove, '', $html) : $html;
        });
    }

    // ==========================================
    // MÉTHODE UTILITAIRE POUR LES AVIS
    // ==========================================
    private function get_reviews_for_post($post_id)
    {
        $args = ['post_type' => 'pc_review', 'post_status' => 'publish', 'posts_per_page' => 5, 'meta_query' => [['key' => 'pc_post_id', 'value' => $post_id], ['key' => 'pc_source', 'value' => 'internal']]];
        $posts = get_posts($args);
        if (!$posts) return false;

        $sum = 0;
        $cnt = 0;
        $reviews = [];
        foreach ($posts as $rp) {
            $rating = (float)get_post_meta($rp->ID, 'pc_rating', true);
            if ($rating > 0) {
                $sum += $rating;
                $cnt++;
                $review = [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => get_post_meta($rp->ID, 'pc_reviewer_name', true) ?: 'Client vérifié'],
                    'datePublished' => get_the_date('Y-m-d', $rp->ID),
                    'reviewBody' => wp_strip_all_tags(get_post_meta($rp->ID, 'pc_body', true)),
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $rating, 'bestRating' => '5']
                ];
                $ref_time = get_post_meta($rp->ID, 'pc_stayed_date', true);
                if ($ref_time) $review['contentReferenceTime'] = date('c', strtotime($ref_time . '-01'));
                $reviews[] = $review;
            }
        }
        return ($cnt > 0) ? ['aggregate' => ['@type' => 'AggregateRating', 'ratingValue' => round($sum / $cnt, 1), 'reviewCount' => $cnt], 'items' => $reviews] : false;
    }
}
