<?php

/**
 * Helper statique pour la gestion des icônes SVG du Header
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Header_SVG_Helper
{
    /**
     * Retourne le code HTML d'une icône SVG selon sa clé
     *
     * @param string $key Clé de l'icône (ex: 'facebook', 'menu', 'close')
     * @return string Code SVG ou chaîne vide si non trouvé
     */
    public static function get(string $key): string
    {
        // On force les dimensions et le comportement directement dans le HTML
        $attr = ' width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false"';

        switch ($key) {
            case 'facebook':
                return '<svg' . $attr . ' viewBox="0 0 24 24"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7H8v-2.9h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4H15.8c-1.2 0-1.6.7-1.6 1.5v1.8H17l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg>';
            case 'instagram':
                return '<svg' . $attr . ' viewBox="0 0 24 24"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3z"/><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/><path d="M17.5 6.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>';
            case 'youtube':
                return '<svg' . $attr . ' viewBox="0 0 24 24"><path d="M21.6 7.2a3 3 0 0 0-2.1-2.1C17.8 4.6 12 4.6 12 4.6s-5.8 0-7.5.5A3 3 0 0 0 2.4 7.2 31 31 0 0 0 2 12a31 31 0 0 0 .4 4.8 3 3 0 0 0 2.1 2.1c1.7.5 7.5.5 7.5.5s5.8 0 7.5-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 22 12a31 31 0 0 0-.4-4.8zM10 15.2V8.8L15.5 12 10 15.2z"/></svg>';
            case 'whatsapp':
                return '<svg' . $attr . ' viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 20 12a8 8 0 0 1-8 8z"/><path d="M16.8 14.5c-.2-.1-1.3-.6-1.5-.7s-.4-.1-.6.1-.7.7-.8.8-.3.2-.5.1a6.6 6.6 0 0 1-2-1.2 7.5 7.5 0 0 1-1.4-1.8c-.1-.2 0-.4.1-.5l.4-.4.3-.4c.1-.1.1-.3 0-.5s-.6-1.4-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3s-1 1-1 2.4 1 2.8 1.2 3 .1.2 0 0a10.6 10.6 0 0 0 4.1 3.6c.6.3 1 .5 1.4.6.6.2 1.1.2 1.5.1.5-.1 1.3-.5 1.5-1 .2-.5.2-.9.1-1s-.2-.2-.4-.3z"/></svg>';
            case 'phone':
                return '<svg width="16" height="16" aria-hidden="true" viewBox="0 0 512 512"><path fill="currentColor" d="M497.39 361.8l-112-48a24 24 0 0 0-28 6.9l-49.6 60.6A370.66 370.66 0 0 1 130.6 204.11l60.6-49.6a23.94 23.94 0 0 0 6.9-28l-48-112A24.16 24.16 0 0 0 122.6.61l-104 24A24 24 0 0 0 0 48c0 256.5 207.9 464 464 464a24 24 0 0 0 23.4-18.6l24-104a24.29 24.29 0 0 0-14.01-27.6z"/></svg>';
            case 'chev-down':
                return '<svg width="14" height="14" aria-hidden="true" viewBox="0 0 320 512"><path fill="currentColor" d="M31.3 192h257.3c17.8 0 26.7 21.5 14.1 34.1L174.1 354.8c-7.8 7.8-20.5 7.8-28.3 0L17.2 226.1C4.6 213.5 13.5 192 31.3 192z"/></svg>';
            case 'menu':
                return '<svg width="26" height="26" aria-hidden="true" viewBox="0 0 1000 1000"><path fill="currentColor" d="M104 333H896C929 333 958 304 958 271S929 208 896 208H104C71 208 42 237 42 271S71 333 104 333ZM104 583H896C929 583 958 554 958 521S929 458 896 458H104C71 458 42 487 42 521S71 583 104 583ZM104 833H896C929 833 958 804 958 771S929 708 896 708H104C71 708 42 737 42 771S71 833 104 833Z"/></svg>';
            case 'close':
                return '<svg width="20" height="20" aria-hidden="true" viewBox="0 0 1000 1000"><path fill="currentColor" d="M742 167L500 408 258 167C246 154 233 150 217 150 196 150 179 158 167 167 154 179 150 196 150 212 150 229 154 242 171 254L408 500 167 742C138 771 138 800 167 829 196 858 225 858 254 829L496 587 738 829C750 842 767 846 783 846 800 846 817 842 829 829 842 817 846 804 846 783 846 767 842 750 829 737L588 500 833 258C863 229 863 200 833 171 804 137 775 137 742 167Z"/></svg>';
        }
        return '';
    }
}
