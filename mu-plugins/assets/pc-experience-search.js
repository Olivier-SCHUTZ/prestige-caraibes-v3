// Adaptation au SSR et simplification du rendu
(function(w, d, $) {
    'use strict';

    $(function() {
        const $wrapper = $('.pc-exp-search-wrapper');
        if (!$wrapper.length) return;

        const $resultsContainer = $('#pc-exp-results-container');
        const $mapContainer = $('#pc-map-container');
        let map = null,
            markersLayer = null;

        // --- Initialisation de la carte Leaflet ---
        if ($mapContainer.length && typeof w.L !== 'undefined') {
            map = L.map($mapContainer[0]).setView([16.265, -61.551], 9);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            markersLayer = new L.LayerGroup().addTo(map);

            // Hydratation initiale de la carte avec les données du SSR
            if (typeof pc_exp_initial_data !== 'undefined' && pc_exp_initial_data.map_data) {
                updateMapMarkers(pc_exp_initial_data.map_data);
            }
        }

        function updateMapMarkers(items) {
            if (!map || !markersLayer) return;
            markersLayer.clearLayers();
            if (!items || !items.length) return;

            const bounds = [];
            items.forEach(function(item) {
                if (!item.lat || !item.lng) return;
                const latLng = [parseFloat(item.lat), parseFloat(item.lng)];
                const popupContent = `<strong>${item.title}</strong><br>${item.price ? `À partir de ${item.price}€` : ''}<br><a href="${item.link}" target="_blank" rel="noopener">Voir l'expérience</a>`;
                L.marker(latLng).addTo(markersLayer).bindPopup(popupContent);
                bounds.push(latLng);
            });

            if (bounds.length) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

        function collectFormData() {
            return {
                action: 'pc_filter_experiences',
                security: pc_exp_ajax.nonce,
                category: $wrapper.find('#filter-exp-category').val() || '',
                ville: $wrapper.find('#filter-exp-ville').val() || '',
                keyword: $wrapper.find('#filter-exp-keyword').val() || '',
                participants: $wrapper.find('#filter-exp-participants').val() || '1',
                prix_min: $wrapper.find('#filter-exp-prix-min').val() || '',
                prix_max: $wrapper.find('#filter-exp-prix-max').val() || '',
            };
        }

        function performSearch(page = 1) {
            const data = { ...collectFormData(), page: page };

            $resultsContainer.addClass('is-loading').html(''); // On vide en attendant
            $('.pc-pagination').remove();

            $.ajax({
                url: pc_exp_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success && response.data) {
                        // On injecte directement le HTML reçu
                        $resultsContainer.html(response.data.vignettes_html);
                        $resultsContainer.after(response.data.pagination_html);
                        updateMapMarkers(response.data.map_data || []);
                    } else {
                        $resultsContainer.html('<div class="pc-no-results"><h3>Une erreur est survenue.</h3><p>Veuillez réessayer.</p></div>');
                    }
                },
                error: function() {
                    $resultsContainer.html('<div class="pc-no-results"><h3>Une erreur de communication est survenue.</h3></div>');
                },
                complete: function() {
                    $resultsContainer.removeClass('is-loading');
                }
            });
        }
        
        function debounce(fn, delay = 350) {
            let timeoutId;
            return function(...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn.apply(this, args), delay);
            };
        }
        const debouncedSearch = debounce(() => performSearch(1));

        $wrapper.find('#pc-exp-filters-form').on('submit', function(e) {
            e.preventDefault();
            performSearch(1);
        });
        
        $wrapper.on('change', '#filter-exp-category, #filter-exp-ville', () => performSearch(1));
        $wrapper.on('keyup', '#filter-exp-keyword', debouncedSearch);
        $wrapper.on('change', '#filter-exp-prix-min, #filter-exp-prix-max, #filter-exp-participants', debouncedSearch);

        $wrapper.on('click', '.pc-exp-adv-toggle', function() {
            const $panel = $wrapper.find('#pc-exp-advanced');
            const isHidden = $panel.is('[hidden]');
            $panel.prop('hidden', !isHidden);
            $(this).attr('aria-expanded', isHidden);
        });
        
        $wrapper.on('click', '.num-stepper', function() {
            const targetId = $(this).data('target');
            const step = parseInt($(this).data('step'), 10);
            const $input = $wrapper.find(`#filter-exp-${targetId}`);
            let currentValue = parseInt($input.val(), 10) || 1;
            let newValue = Math.max(1, currentValue + step);
            $input.val(newValue).trigger('change');
        });
        
         $(d).on('click', '.pc-pagination a', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'), 10) || 1;
            performSearch(page);
            $('html, body').animate({
                scrollTop: $wrapper.offset().top - 30 
            }, 500);
        });
    });

})(window, document, jQuery);