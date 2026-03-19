<?php
/**
 * WP Datenschutz-relevante Dienste Scanner (Single-Site + Multisite)
 *
 * Scannt alle Sites (Multisite) oder die aktuelle Installation (Single-Site)
 * und erkennt datenschutzrelevante Dienste (Plugins, Embeds, externe Ressourcen).
 *
 * Nutzung:
 *   wp eval-file scan-privacy-services.php
 *   ODER: im Browser als Must-Use-Plugin (nur für Admins)
 *
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Versuche WordPress zu laden, wenn direkt aufgerufen
    $wp_load_candidates = [
        __DIR__ . '/wp-load.php',
        __DIR__ . '/../wp-load.php',
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
    ];
    $loaded = false;
    foreach ( $wp_load_candidates as $candidate ) {
        if ( file_exists( $candidate ) ) {
            require_once $candidate;
            $loaded = true;
            break;
        }
    }
    if ( ! $loaded ) {
        die( "WordPress konnte nicht geladen werden. Bitte per WP-CLI ausfuehren:\n  wp eval-file scan-privacy-services.php\n" );
    }
}

// Sicherheitscheck: Nur Admins/Super-Admins duerfen scannen
if ( ! defined( 'WP_CLI' ) ) {
    if ( ! is_user_logged_in() ) {
        die( "Zugriff verweigert. Nur eingeloggte Admins koennen diesen Scan ausfuehren.\n" );
    }
    if ( is_multisite() && ! is_super_admin() ) {
        die( "Zugriff verweigert. Nur Super-Admins koennen diesen Scan ausfuehren.\n" );
    }
    if ( ! is_multisite() && ! current_user_can( 'manage_options' ) ) {
        die( "Zugriff verweigert. Nur Administratoren koennen diesen Scan ausfuehren.\n" );
    }
}

$is_multisite = is_multisite();

/**
 * ============================================================
 * KONFIGURATION: Bekannte datenschutz-relevante Dienste
 * ============================================================
 */
class PrivacyServiceRegistry {

    /**
     * Mapping: Plugin-Slug (Ordnername) => Dienst-Beschreibung
     */
    public static function get_plugin_map(): array {
        return [
            // Analytics & Tracking
            'google-analytics-for-wordpress'       => ['name' => 'Google Analytics (MonsterInsights)', 'category' => 'Analytics/Tracking'],
            'google-site-kit'                      => ['name' => 'Google Site Kit', 'category' => 'Analytics/Tracking'],
            'ga-google-analytics'                  => ['name' => 'GA Google Analytics', 'category' => 'Analytics/Tracking'],
            'analytify'                            => ['name' => 'Google Analytics (Analytify)', 'category' => 'Analytics/Tracking'],
            'wp-statistics'                        => ['name' => 'WP Statistics', 'category' => 'Analytics/Tracking'],
            'matomo'                               => ['name' => 'Matomo Analytics', 'category' => 'Analytics/Tracking'],
            'wp-piwik'                             => ['name' => 'WP-Matomo (Piwik)', 'category' => 'Analytics/Tracking'],
            'duracelltomi-google-tag-manager'      => ['name' => 'Google Tag Manager', 'category' => 'Analytics/Tracking'],
            'google-analytics-dashboard-for-wp'    => ['name' => 'ExactMetrics (Google Analytics)', 'category' => 'Analytics/Tracking'],
            'facebook-for-woocommerce'             => ['name' => 'Facebook Pixel / CAPI', 'category' => 'Analytics/Tracking'],
            'pixelyoursite'                        => ['name' => 'PixelYourSite (Facebook/Google Pixel)', 'category' => 'Analytics/Tracking'],
            'hotjar'                               => ['name' => 'Hotjar', 'category' => 'Analytics/Tracking'],

            // Kontaktformulare (Datenerfassung)
            'contact-form-7'                       => ['name' => 'Contact Form 7', 'category' => 'Kontaktformular'],
            'wpforms-lite'                         => ['name' => 'WPForms', 'category' => 'Kontaktformular'],
            'gravityforms'                         => ['name' => 'Gravity Forms', 'category' => 'Kontaktformular'],
            'formidable'                           => ['name' => 'Formidable Forms', 'category' => 'Kontaktformular'],
            'ninja-forms'                          => ['name' => 'Ninja Forms', 'category' => 'Kontaktformular'],
            'caldera-forms'                        => ['name' => 'Caldera Forms', 'category' => 'Kontaktformular'],
            'happyforms'                           => ['name' => 'HappyForms', 'category' => 'Kontaktformular'],
            'everest-forms'                        => ['name' => 'Everest Forms', 'category' => 'Kontaktformular'],
            'fluentform'                           => ['name' => 'Fluent Forms', 'category' => 'Kontaktformular'],

            // Newsletter / E-Mail-Marketing
            'mailchimp-for-wp'                     => ['name' => 'Mailchimp for WordPress', 'category' => 'Newsletter/E-Mail'],
            'newsletter'                           => ['name' => 'Newsletter Plugin', 'category' => 'Newsletter/E-Mail'],
            'mailpoet'                             => ['name' => 'MailPoet', 'category' => 'Newsletter/E-Mail'],
            'klicktipp-api-integration'            => ['name' => 'KlickTipp', 'category' => 'Newsletter/E-Mail'],
            'sendinblue'                           => ['name' => 'Brevo (Sendinblue)', 'category' => 'Newsletter/E-Mail'],
            'the-starter-templates'                => ['name' => 'Starter Templates', 'category' => 'Templates'],

            // reCAPTCHA / Anti-Spam
            'google-captcha'                       => ['name' => 'Google reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
            'advanced-google-recaptcha'            => ['name' => 'Advanced Google reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
            'invisible-recaptcha'                  => ['name' => 'Invisible reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
            'akismet'                              => ['name' => 'Akismet (Automattic-Server)', 'category' => 'Anti-Spam/Captcha'],
            'antispam-bee'                         => ['name' => 'Antispam Bee', 'category' => 'Anti-Spam/Captcha'],
            'hcaptcha-for-forms-and-more'          => ['name' => 'hCaptcha', 'category' => 'Anti-Spam/Captcha'],

            // Social Media
            'jetpack'                              => ['name' => 'Jetpack (WordPress.com-Verbindung)', 'category' => 'Social/Extern'],
            'instagram-feed'                       => ['name' => 'Smash Balloon Instagram Feed', 'category' => 'Social/Extern'],
            'custom-facebook-feed'                 => ['name' => 'Smash Balloon Facebook Feed', 'category' => 'Social/Extern'],
            'feeds-for-youtube'                    => ['name' => 'Smash Balloon YouTube Feed', 'category' => 'Social/Extern'],
            'custom-twitter-feeds'                 => ['name' => 'Smash Balloon Twitter Feed', 'category' => 'Social/Extern'],
            'social-warfare'                       => ['name' => 'Social Warfare', 'category' => 'Social/Extern'],
            'shareaholic'                          => ['name' => 'Shareaholic', 'category' => 'Social/Extern'],
            'add-to-any'                           => ['name' => 'AddToAny Share Buttons', 'category' => 'Social/Extern'],
            'super-socializer'                     => ['name' => 'Super Socializer (Social Login)', 'category' => 'Social/Extern'],

            // Maps / Standort
            'jetstash-google-maps'                 => ['name' => 'Google Maps Plugin', 'category' => 'Karten/Standort'],
            'jetstash-google-maps-widget'          => ['name' => 'Google Maps Widget', 'category' => 'Karten/Standort'],
            'jetstash-maps-builder'                => ['name' => 'Maps Builder', 'category' => 'Karten/Standort'],
            'jetstash-maps-marker'                 => ['name' => 'Maps Marker', 'category' => 'Karten/Standort'],
            'jetstash-openstreetmap'               => ['name' => 'OpenStreetMap Plugin', 'category' => 'Karten/Standort'],
            'jetstash-leaflet-maps'                => ['name' => 'Leaflet Maps Marker', 'category' => 'Karten/Standort'],
            'jetstash-mappress'                    => ['name' => 'MapPress Easy Google Maps', 'category' => 'Karten/Standort'],
            'jetstash-open-street-map'             => ['name' => 'OSM - OpenStreetMap', 'category' => 'Karten/Standort'],
            'jetstash-maps-for-wp'                 => ['name' => 'Maps for WP', 'category' => 'Karten/Standort'],
            'jetstash-googlemaps'                  => ['name' => 'Google Maps Plugin', 'category' => 'Karten/Standort'],
            'jetstash-maps-pro'                    => ['name' => 'Maps Pro', 'category' => 'Karten/Standort'],
            'jetstash-map-multi-marker'            => ['name' => 'Map Multi Marker', 'category' => 'Karten/Standort'],
            'jetstash-maps-widget-pro'             => ['name' => 'Maps Widget Pro', 'category' => 'Karten/Standort'],
            'jetstash-map-widget'                  => ['name' => 'Map Widget', 'category' => 'Karten/Standort'],
            'jetstash-wp-google-maps'              => ['name' => 'WP Google Maps', 'category' => 'Karten/Standort'],
            'jetstash-jetstash-maps'               => ['name' => 'Jetstash Maps', 'category' => 'Karten/Standort'],
            'jetstash-jetstash-maps-pro'           => ['name' => 'Jetstash Maps Pro', 'category' => 'Karten/Standort'],
            'jetstash-jetstash-maps-widget'        => ['name' => 'Jetstash Maps Widget', 'category' => 'Karten/Standort'],
            'wp-google-maps'                       => ['name' => 'WP Google Maps', 'category' => 'Karten/Standort'],
            'jetstash-maps-block'                  => ['name' => 'Maps Block', 'category' => 'Karten/Standort'],

            // Fonts / CDN
            'jetstash-custom-fonts'                => ['name' => 'Custom Fonts (extern)', 'category' => 'Fonts/CDN'],
            'jetstash-use-any-font'                => ['name' => 'Use Any Font', 'category' => 'Fonts/CDN'],
            'jetstash-jetstash-google-fonts'       => ['name' => 'Google Fonts Plugin', 'category' => 'Fonts/CDN'],
            'jetstash-jetstash-easy-google-fonts'  => ['name' => 'Easy Google Fonts', 'category' => 'Fonts/CDN'],

            // E-Commerce / Zahlung
            'woocommerce'                          => ['name' => 'WooCommerce', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-payments'                 => ['name' => 'WooCommerce Payments (Stripe)', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-paypal-payments'          => ['name' => 'WooCommerce PayPal', 'category' => 'E-Commerce/Zahlung'],
            'woo-stripe-payment'                   => ['name' => 'Stripe Payment Gateway', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-germanized'               => ['name' => 'WooCommerce Germanized', 'category' => 'E-Commerce/Zahlung'],

            // Kommentare
            'jetstash-disqus'                      => ['name' => 'Disqus Comment System', 'category' => 'Kommentare'],
            'disqus-comment-system'                => ['name' => 'Disqus Comment System', 'category' => 'Kommentare'],

            // Chat / Support
            'jetstash-tidio'                       => ['name' => 'Tidio Live Chat', 'category' => 'Chat/Support'],
            'jetstash-zendesk-chat'                => ['name' => 'Zendesk Chat', 'category' => 'Chat/Support'],
            'jetstash-hubspot'                     => ['name' => 'HubSpot', 'category' => 'Chat/Support'],
            'jetstash-tawk-to'                     => ['name' => 'Tawk.to Live Chat', 'category' => 'Chat/Support'],
            'jetstash-crisp'                       => ['name' => 'Crisp Live Chat', 'category' => 'Chat/Support'],
            'jetstash-userlike'                    => ['name' => 'Userlike', 'category' => 'Chat/Support'],
            'jetstash-livechat'                    => ['name' => 'LiveChat', 'category' => 'Chat/Support'],

            // Datenschutz-Tools selbst
            'jetstash-complianz'                   => ['name' => 'Complianz (Cookie Consent)', 'category' => 'Datenschutz-Tool'],
            'jetstash-cookiebot'                   => ['name' => 'Cookiebot', 'category' => 'Datenschutz-Tool'],
            'jetstash-cookie-notice'               => ['name' => 'Cookie Notice', 'category' => 'Datenschutz-Tool'],
            'jetstash-real-cookie-banner'          => ['name' => 'Real Cookie Banner', 'category' => 'Datenschutz-Tool'],
            'jetstash-borlabs-cookie'              => ['name' => 'Borlabs Cookie', 'category' => 'Datenschutz-Tool'],
            'jetstash-gdpr-cookie-compliance'      => ['name' => 'GDPR Cookie Compliance', 'category' => 'Datenschutz-Tool'],
            'jetstash-cookie-law-info'             => ['name' => 'CookieYes (Cookie Law Info)', 'category' => 'Datenschutz-Tool'],
            'jetstash-iubenda'                     => ['name' => 'iubenda', 'category' => 'Datenschutz-Tool'],
            'jetstash-wp-gdpr-compliance'          => ['name' => 'WP GDPR Compliance', 'category' => 'Datenschutz-Tool'],
            'complianz-gdpr'                       => ['name' => 'Complianz GDPR/CCPA', 'category' => 'Datenschutz-Tool'],
            'cookie-notice'                        => ['name' => 'Cookie Notice & Compliance', 'category' => 'Datenschutz-Tool'],
            'real-cookie-banner'                   => ['name' => 'Real Cookie Banner', 'category' => 'Datenschutz-Tool'],
            'borlabs-cookie'                       => ['name' => 'Borlabs Cookie', 'category' => 'Datenschutz-Tool'],

            // Video
            'jetstash-jetstash-youtube'            => ['name' => 'YouTube Embed Plugin', 'category' => 'Video/Embed'],
            'jetstash-jetstash-vimeo'              => ['name' => 'Vimeo Embed Plugin', 'category' => 'Video/Embed'],
            'jetstash-jetstash-video-embed'        => ['name' => 'Video Embed Plugin', 'category' => 'Video/Embed'],

            // Sicherheit / Login
            'jetstash-wordfence'                   => ['name' => 'Wordfence Security', 'category' => 'Sicherheit'],
            'wordfence'                            => ['name' => 'Wordfence Security', 'category' => 'Sicherheit'],
            'jetstash-sucuri'                      => ['name' => 'Sucuri Security', 'category' => 'Sicherheit'],
            'jetstash-loginizer'                   => ['name' => 'Loginizer', 'category' => 'Sicherheit'],
            'jetstash-limit-login-attempts'        => ['name' => 'Limit Login Attempts', 'category' => 'Sicherheit'],
            'jetstash-ithemes-security'            => ['name' => 'iThemes Security (SolidWP)', 'category' => 'Sicherheit'],
            'jetstash-all-in-one-wp-security'      => ['name' => 'All In One WP Security', 'category' => 'Sicherheit'],

            // Elementor mit externen Diensten
            'elementor'                            => ['name' => 'Elementor (Google Fonts, externe Bibliotheken)', 'category' => 'Page Builder'],
            'jetstash-elementor-pro'               => ['name' => 'Elementor Pro', 'category' => 'Page Builder'],

            // Backup (Datenexport)
            'jetstash-updraftplus'                 => ['name' => 'UpdraftPlus (Cloud-Backup)', 'category' => 'Backup/Cloud'],
            'updraftplus'                          => ['name' => 'UpdraftPlus (Cloud-Backup)', 'category' => 'Backup/Cloud'],
            'jetstash-backwpup'                    => ['name' => 'BackWPup', 'category' => 'Backup/Cloud'],
            'jetstash-duplicator'                  => ['name' => 'Duplicator', 'category' => 'Backup/Cloud'],

            // Sonstige
            'jetstash-jetstash-gravatar'           => ['name' => 'Gravatar (Automattic)', 'category' => 'Sonstige externe Dienste'],
        ];
    }

    /**
     * Regex-Patterns fuer Content-Scan (Post-Inhalte, Widgets usw.)
     * Jedes Pattern liefert den Dienst-Namen zurueck.
     */
    public static function get_content_patterns(): array {
        return [
            // Google
            '/google-analytics\.com|googletagmanager\.com|gtag\/js/i'
                => ['name' => 'Google Analytics / Tag Manager', 'category' => 'Analytics/Tracking'],
            '/maps\.googleapis\.com|maps\.google\.(com|de)|google\.com\/maps/i'
                => ['name' => 'Google Maps', 'category' => 'Karten/Standort'],
            '/fonts\.googleapis\.com|fonts\.gstatic\.com/i'
                => ['name' => 'Google Fonts (extern geladen)', 'category' => 'Fonts/CDN'],
            '/www\.google\.com\/recaptcha|www\.recaptcha\.net|gstatic\.com\/recaptcha/i'
                => ['name' => 'Google reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
            '/apis\.google\.com/i'
                => ['name' => 'Google APIs (allgemein)', 'category' => 'Sonstige externe Dienste'],
            '/youtube\.com\/embed|youtube-nocookie\.com\/embed|youtu\.be\//i'
                => ['name' => 'YouTube Embed', 'category' => 'Video/Embed'],
            '/googlesyndication\.com|doubleclick\.net|google-analytics\.com\/analytics|googleadservices\.com/i'
                => ['name' => 'Google Ads / AdSense', 'category' => 'Werbung'],

            // Facebook / Meta
            '/connect\.facebook\.net|facebook\.com\/plugins|fbcdn\.net|facebook\.com\/tr/i'
                => ['name' => 'Facebook SDK / Pixel', 'category' => 'Analytics/Tracking'],
            '/instagram\.com\/embed|cdninstagram\.com/i'
                => ['name' => 'Instagram Embed', 'category' => 'Social/Extern'],

            // Twitter / X
            '/platform\.twitter\.com|twitter\.com\/widgets|cdn\.syndication\.twimg/i'
                => ['name' => 'Twitter/X Embed', 'category' => 'Social/Extern'],

            // Video
            '/player\.vimeo\.com|vimeocdn\.com/i'
                => ['name' => 'Vimeo Embed', 'category' => 'Video/Embed'],
            '/open\.spotify\.com\/embed/i'
                => ['name' => 'Spotify Embed', 'category' => 'Audio/Embed'],

            // CDN / Fonts
            '/cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com|unpkg\.com/i'
                => ['name' => 'Externes CDN (jsDelivr/cdnjs/unpkg)', 'category' => 'Fonts/CDN'],
            '/use\.fontawesome\.com|kit\.fontawesome\.com/i'
                => ['name' => 'Font Awesome (extern)', 'category' => 'Fonts/CDN'],
            '/use\.typekit\.net|p\.typekit\.net/i'
                => ['name' => 'Adobe Typekit / Fonts', 'category' => 'Fonts/CDN'],

            // Chat / Support
            '/js\.hs-scripts\.com|js\.hsforms\.net|hubspot\.com/i'
                => ['name' => 'HubSpot', 'category' => 'Chat/Support'],
            '/embed\.tawk\.to|tawk\.to/i'
                => ['name' => 'Tawk.to Live Chat', 'category' => 'Chat/Support'],
            '/client\.crisp\.chat|crisp\.chat/i'
                => ['name' => 'Crisp Chat', 'category' => 'Chat/Support'],
            '/widget\.userlike\.com/i'
                => ['name' => 'Userlike Chat', 'category' => 'Chat/Support'],
            '/code\.tidio\.co/i'
                => ['name' => 'Tidio Chat', 'category' => 'Chat/Support'],
            '/static\.zdassets\.com|zopim\.com/i'
                => ['name' => 'Zendesk Chat', 'category' => 'Chat/Support'],

            // Newsletter / E-Mail
            '/chimpstatic\.com|list-manage\.com|mailchimp\.com/i'
                => ['name' => 'Mailchimp', 'category' => 'Newsletter/E-Mail'],
            '/sibforms\.com|sendinblue\.com|brevo\.com/i'
                => ['name' => 'Brevo (Sendinblue)', 'category' => 'Newsletter/E-Mail'],
            '/campaign-archive\.com|eepurl\.com/i'
                => ['name' => 'Newsletter-Service (E-Mail-Archiv)', 'category' => 'Newsletter/E-Mail'],

            // Zahlungsdienste
            '/js\.stripe\.com|checkout\.stripe\.com/i'
                => ['name' => 'Stripe', 'category' => 'E-Commerce/Zahlung'],
            '/paypal\.com\/sdk|paypalobjects\.com/i'
                => ['name' => 'PayPal', 'category' => 'E-Commerce/Zahlung'],
            '/klarna\.com/i'
                => ['name' => 'Klarna', 'category' => 'E-Commerce/Zahlung'],

            // Sonstige
            '/hcaptcha\.com/i'
                => ['name' => 'hCaptcha', 'category' => 'Anti-Spam/Captcha'],
            '/gravatar\.com/i'
                => ['name' => 'Gravatar (WordPress/Automattic)', 'category' => 'Sonstige externe Dienste'],
            '/wp\.com\/|stats\.wp\.com|pixel\.wp\.com/i'
                => ['name' => 'WordPress.com (Jetpack Stats)', 'category' => 'Analytics/Tracking'],
            '/consentmanager\.net|cookiebot\.com|cookieyes\.com/i'
                => ['name' => 'Cookie Consent Manager (extern)', 'category' => 'Datenschutz-Tool'],
            '/hotjar\.com|static\.hotjar\.com/i'
                => ['name' => 'Hotjar', 'category' => 'Analytics/Tracking'],
            '/cdn\.amplitude\.com|analytics\.amplitude\.com/i'
                => ['name' => 'Amplitude Analytics', 'category' => 'Analytics/Tracking'],
            '/mouseflow\.com/i'
                => ['name' => 'Mouseflow', 'category' => 'Analytics/Tracking'],
            '/clarity\.ms/i'
                => ['name' => 'Microsoft Clarity', 'category' => 'Analytics/Tracking'],
            '/disqus\.com/i'
                => ['name' => 'Disqus Kommentare', 'category' => 'Kommentare'],
            '/openstreetmap\.org/i'
                => ['name' => 'OpenStreetMap', 'category' => 'Karten/Standort'],
            '/leafletjs\.com|unpkg\.com\/leaflet/i'
                => ['name' => 'Leaflet Maps', 'category' => 'Karten/Standort'],
        ];
    }

    /**
     * Options-Keys, die auf externe Dienst-Konfigurationen hinweisen.
     */
    public static function get_option_patterns(): array {
        return [
            'google_analytics'       => ['name' => 'Google Analytics', 'category' => 'Analytics/Tracking'],
            'gtm_id'                 => ['name' => 'Google Tag Manager', 'category' => 'Analytics/Tracking'],
            'ga_tracking_id'         => ['name' => 'Google Analytics Tracking ID', 'category' => 'Analytics/Tracking'],
            'wpseo'                  => ['name' => 'Yoast SEO', 'category' => 'SEO'],
            'rank_math'              => ['name' => 'Rank Math SEO', 'category' => 'SEO'],
            'mailchimp'              => ['name' => 'Mailchimp', 'category' => 'Newsletter/E-Mail'],
            'mc4wp'                  => ['name' => 'Mailchimp for WP', 'category' => 'Newsletter/E-Mail'],
            'recaptcha'              => ['name' => 'Google reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
            'akismet_api_key'        => ['name' => 'Akismet', 'category' => 'Anti-Spam/Captcha'],
            'jetpack_activated'      => ['name' => 'Jetpack', 'category' => 'Social/Extern'],
            'google_maps_api_key'    => ['name' => 'Google Maps', 'category' => 'Karten/Standort'],
            'elementor'              => ['name' => 'Elementor', 'category' => 'Page Builder'],
            'wpforms'                => ['name' => 'WPForms', 'category' => 'Kontaktformular'],
            'wpcf7'                  => ['name' => 'Contact Form 7', 'category' => 'Kontaktformular'],
        ];
    }
}


/**
 * ============================================================
 * HAUPT-SCANNER
 * ============================================================
 */
class MultisitePrivacyScanner {

    private array $results = [];

    public function scan_all_sites(): array {
        if ( is_multisite() ) {
            $sites = get_sites( [ 'number' => 0 ] );
        } else {
            // Single-Site: Fake-Site-Objekt erstellen
            $sites = [ (object) [ 'blog_id' => get_current_blog_id() ] ];
        }

        foreach ( $sites as $site ) {
            $blog_id = (int) $site->blog_id;
            if ( is_multisite() ) {
                switch_to_blog( $blog_id );
            }

            $site_url  = get_site_url();
            $site_name = get_bloginfo( 'name' );

            $this->results[ $blog_id ] = [
                'blog_id'  => $blog_id,
                'url'      => $site_url,
                'name'     => $site_name,
                'services' => [],
            ];

            // 1. Aktive Plugins scannen
            $this->scan_plugins( $blog_id );

            // 2. Aktives Theme scannen
            $this->scan_theme( $blog_id );

            // 3. Options-Tabelle scannen
            $this->scan_options( $blog_id );

            // 4. Post-Inhalte scannen
            $this->scan_post_content( $blog_id );

            // 5. Widget-Inhalte scannen
            $this->scan_widgets( $blog_id );

            // Duplikate entfernen
            $this->deduplicate_services( $blog_id );

            if ( is_multisite() ) {
                restore_current_blog();
            }
        }

        // 6. Netzwerk-weite Plugins scannen (nur Multisite)
        if ( is_multisite() ) {
            $this->scan_network_plugins();
        }

        // 7. Theme-Dateien des aktiven Themes auf externe Aufrufe pruefen
        $this->scan_theme_files();

        return $this->results;
    }

    /**
     * Aktive Plugins pro Site pruefen
     */
    private function scan_plugins( int $blog_id ): void {
        $active_plugins = get_option( 'active_plugins', [] );
        $plugin_map     = PrivacyServiceRegistry::get_plugin_map();

        foreach ( $active_plugins as $plugin_path ) {
            $slug = dirname( $plugin_path );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_path, '.php' );
            }
            if ( isset( $plugin_map[ $slug ] ) ) {
                $this->add_service( $blog_id, $plugin_map[ $slug ], 'Plugin: ' . $plugin_path );
            }

            // Generische Erkennung ueber Plugin-Namen
            $this->check_generic_plugin_name( $blog_id, $slug, $plugin_path );
        }
    }

    /**
     * Generische Erkennung anhand von Plugin-Slugs
     */
    private function check_generic_plugin_name( int $blog_id, string $slug, string $plugin_path ): void {
        $generic_patterns = [
            '/google[-_]?maps/i'       => ['name' => 'Google Maps Plugin', 'category' => 'Karten/Standort'],
            '/google[-_]?analytic/i'   => ['name' => 'Google Analytics Plugin', 'category' => 'Analytics/Tracking'],
            '/google[-_]?tag/i'        => ['name' => 'Google Tag Manager Plugin', 'category' => 'Analytics/Tracking'],
            '/recaptcha/i'             => ['name' => 'reCAPTCHA Plugin', 'category' => 'Anti-Spam/Captcha'],
            '/hcaptcha/i'              => ['name' => 'hCaptcha Plugin', 'category' => 'Anti-Spam/Captcha'],
            '/mailchimp/i'             => ['name' => 'Mailchimp Plugin', 'category' => 'Newsletter/E-Mail'],
            '/facebook/i'              => ['name' => 'Facebook Integration Plugin', 'category' => 'Social/Extern'],
            '/youtube/i'               => ['name' => 'YouTube Plugin', 'category' => 'Video/Embed'],
            '/vimeo/i'                 => ['name' => 'Vimeo Plugin', 'category' => 'Video/Embed'],
            '/stripe/i'               => ['name' => 'Stripe Payment Plugin', 'category' => 'E-Commerce/Zahlung'],
            '/paypal/i'               => ['name' => 'PayPal Plugin', 'category' => 'E-Commerce/Zahlung'],
            '/chat|livechat|tawk|tidio|crisp|userlike|zendesk/i' => ['name' => 'Live Chat Plugin', 'category' => 'Chat/Support'],
            '/cookie[-_]?(consent|notice|banner|law|compliance|gdpr)/i' => ['name' => 'Cookie Consent Plugin', 'category' => 'Datenschutz-Tool'],
            '/newsletter/i'           => ['name' => 'Newsletter Plugin', 'category' => 'Newsletter/E-Mail'],
            '/brevo|sendinblue/i'     => ['name' => 'Brevo/Sendinblue Plugin', 'category' => 'Newsletter/E-Mail'],
        ];

        foreach ( $generic_patterns as $pattern => $service ) {
            if ( preg_match( $pattern, $slug ) ) {
                $this->add_service( $blog_id, $service, 'Plugin (generisch erkannt): ' . $plugin_path );
            }
        }
    }

    /**
     * Theme scannen (Google Fonts, externe Ressourcen in Theme-Optionen)
     */
    private function scan_theme( int $blog_id ): void {
        $theme = wp_get_theme();
        $theme_mods = get_theme_mods();

        // Theme-Mods nach externen URLs durchsuchen
        if ( is_array( $theme_mods ) ) {
            $serialized = serialize( $theme_mods );
            $patterns   = PrivacyServiceRegistry::get_content_patterns();
            foreach ( $patterns as $pattern => $service ) {
                if ( preg_match( $pattern, $serialized ) ) {
                    $this->add_service( $blog_id, $service, 'Theme-Option (' . $theme->get( 'Name' ) . ')' );
                }
            }
        }
    }

    /**
     * Options-Tabelle auf bekannte Schluessel pruefen
     */
    private function scan_options( int $blog_id ): void {
        global $wpdb;
        $option_patterns = PrivacyServiceRegistry::get_option_patterns();

        foreach ( $option_patterns as $key_fragment => $service ) {
            $table = $wpdb->options;
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value FROM {$table} WHERE option_name LIKE %s LIMIT 5",
                    '%' . $wpdb->esc_like( $key_fragment ) . '%'
                )
            );
            foreach ( $results as $row ) {
                $value = $row->option_value;
                // Nur melden wenn der Wert nicht leer ist
                if ( ! empty( $value ) && $value !== '0' && $value !== 'a:0:{}' ) {
                    $this->add_service( $blog_id, $service, 'Option: ' . $row->option_name );
                    break; // Ein Treffer reicht pro Pattern
                }
            }
        }
    }

    /**
     * Post-Inhalte auf externe Dienst-URLs scannen
     */
    private function scan_post_content( int $blog_id ): void {
        global $wpdb;
        $patterns = PrivacyServiceRegistry::get_content_patterns();
        $table    = $wpdb->posts;

        // Nur veroeffentlichte Posts/Pages/CPTs pruefen (limitiert auf Effizienz)
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content FROM {$table}
             WHERE post_status = 'publish'
               AND post_content != ''
               AND post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache','custom_css','wp_global_styles','wp_navigation','wp_template','wp_template_part')
             ORDER BY ID DESC
             LIMIT 1000"
        );

        foreach ( $posts as $post ) {
            foreach ( $patterns as $pattern => $service ) {
                if ( preg_match( $pattern, $post->post_content ) ) {
                    $this->add_service(
                        $blog_id,
                        $service,
                        sprintf( 'Post-Content: "%s" (ID %d)', mb_substr( $post->post_title, 0, 50 ), $post->ID )
                    );
                }
            }
        }

        // Auch Postmeta auf externe URLs pruefen (z.B. Page-Builder-Daten)
        $meta_results = $wpdb->get_results(
            "SELECT pm.meta_key, pm.meta_value, p.post_title, p.ID
             FROM {$wpdb->postmeta} pm
             JOIN {$table} p ON p.ID = pm.post_id
             WHERE p.post_status = 'publish'
               AND pm.meta_value LIKE '%http%'
               AND LENGTH(pm.meta_value) < 65535
             LIMIT 1000"
        );

        foreach ( $meta_results as $meta ) {
            foreach ( $patterns as $pattern => $service ) {
                if ( preg_match( $pattern, $meta->meta_value ) ) {
                    $this->add_service(
                        $blog_id,
                        $service,
                        sprintf( 'Post-Meta (%s): "%s" (ID %d)', $meta->meta_key, mb_substr( $meta->post_title, 0, 50 ), $meta->ID )
                    );
                }
            }
        }
    }

    /**
     * Widgets nach externen Diensten durchsuchen
     */
    private function scan_widgets( int $blog_id ): void {
        global $wpdb;
        $patterns = PrivacyServiceRegistry::get_content_patterns();

        // Alle Widget-Options durchsuchen
        $widget_options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                   AND option_value LIKE %s",
                'widget_%',
                '%http%'
            )
        );

        foreach ( $widget_options as $row ) {
            foreach ( $patterns as $pattern => $service ) {
                if ( preg_match( $pattern, $row->option_value ) ) {
                    $this->add_service( $blog_id, $service, 'Widget: ' . $row->option_name );
                }
            }
        }
    }

    /**
     * Netzwerk-weite Plugins (alle Sites betroffen)
     */
    private function scan_network_plugins(): void {
        $network_plugins = get_site_option( 'active_sitewide_plugins', [] );
        $plugin_map      = PrivacyServiceRegistry::get_plugin_map();

        foreach ( array_keys( $network_plugins ) as $plugin_path ) {
            $slug = dirname( $plugin_path );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_path, '.php' );
            }

            $service_info = null;
            if ( isset( $plugin_map[ $slug ] ) ) {
                $service_info = $plugin_map[ $slug ];
            }

            if ( $service_info ) {
                // Netzwerk-Plugin gilt fuer ALLE Sites
                foreach ( $this->results as $blog_id => &$site_data ) {
                    $this->add_service( $blog_id, $service_info, 'Netzwerk-Plugin: ' . $plugin_path );
                }
            }
        }
    }

    /**
     * Theme-Dateien auf externe Script-/Link-Tags scannen
     */
    private function scan_theme_files(): void {
        $patterns = PrivacyServiceRegistry::get_content_patterns();

        foreach ( $this->results as $blog_id => $site_data ) {
            if ( is_multisite() ) {
                switch_to_blog( $blog_id );
            }
            $theme_dir = get_stylesheet_directory();

            if ( ! is_dir( $theme_dir ) ) {
                if ( is_multisite() ) {
                    restore_current_blog();
                }
                continue;
            }

            $files_to_scan = [];
            $extensions    = [ 'php', 'html', 'js', 'css' ];
            $iterator      = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $theme_dir, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            $file_count = 0;
            foreach ( $iterator as $file ) {
                if ( $file_count >= 200 ) break; // Limit
                $ext = strtolower( $file->getExtension() );
                if ( in_array( $ext, $extensions, true ) ) {
                    $files_to_scan[] = $file->getPathname();
                    $file_count++;
                }
            }

            foreach ( $files_to_scan as $filepath ) {
                $content = @file_get_contents( $filepath );
                if ( empty( $content ) ) continue;

                $relative = str_replace( $theme_dir, '', $filepath );
                foreach ( $patterns as $pattern => $service ) {
                    if ( preg_match( $pattern, $content ) ) {
                        $this->add_service( $blog_id, $service, 'Theme-Datei: ' . $relative );
                    }
                }
            }

            if ( is_multisite() ) {
                restore_current_blog();
            }
        }
    }

    /**
     * Service zu einer Site hinzufuegen
     */
    private function add_service( int $blog_id, array $service, string $source ): void {
        $key = $service['name'];
        if ( ! isset( $this->results[ $blog_id ]['services'][ $key ] ) ) {
            $this->results[ $blog_id ]['services'][ $key ] = [
                'name'     => $service['name'],
                'category' => $service['category'],
                'sources'  => [],
            ];
        }
        $this->results[ $blog_id ]['services'][ $key ]['sources'][] = $source;
    }

    /**
     * Duplikate in Quellen entfernen
     */
    private function deduplicate_services( int $blog_id ): void {
        if ( ! isset( $this->results[ $blog_id ]['services'] ) ) return;
        foreach ( $this->results[ $blog_id ]['services'] as $key => &$service ) {
            $service['sources'] = array_unique( $service['sources'] );
        }
    }
}


/**
 * ============================================================
 * AUSGABE
 * ============================================================
 */
class PrivacyScanOutput {

    /**
     * Konsolenausgabe (WP-CLI / Terminal)
     */
    public static function print_console( array $results ): void {
        echo "\n";
        $mode = is_multisite() ? 'MULTISITE' : 'SINGLE-SITE';
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║  WP {$mode} – DATENSCHUTZ-RELEVANTE DIENSTE (Scan-Report)  ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Scan-Datum: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
        echo "Anzahl Sites: " . count( $results ) . "\n";
        echo str_repeat( '─', 68 ) . "\n";

        foreach ( $results as $blog_id => $site ) {
            echo "\n";
            echo "┌─ Site #{$blog_id}: {$site['name']}\n";
            echo "│  URL: {$site['url']}\n";
            echo "│\n";

            if ( empty( $site['services'] ) ) {
                echo "│  ✓ Keine datenschutzrelevanten Dienste erkannt.\n";
            } else {
                // Nach Kategorie gruppieren
                $by_category = [];
                foreach ( $site['services'] as $service ) {
                    $cat = $service['category'];
                    $by_category[ $cat ][] = $service;
                }
                ksort( $by_category );

                foreach ( $by_category as $category => $services ) {
                    echo "│  ▸ {$category}\n";
                    foreach ( $services as $svc ) {
                        echo "│    • {$svc['name']}\n";
                        foreach ( $svc['sources'] as $src ) {
                            echo "│      ↳ {$src}\n";
                        }
                    }
                }
            }

            echo "│\n";
            echo "└" . str_repeat( '─', 67 ) . "\n";
        }

        // Zusammenfassung: Welche Dienste kommen auf ALLEN/MEHREREN Sites vor
        echo "\n";
        $summary_label = is_multisite() ? 'ZUSAMMENFASSUNG (Netzwerk)' : 'ZUSAMMENFASSUNG';
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        printf( "║%s║\n", str_pad( "    {$summary_label}", 66 ) );
        echo "╚══════════════════════════════════════════════════════════════════╝\n";

        $all_services = [];
        foreach ( $results as $blog_id => $site ) {
            foreach ( $site['services'] as $svc ) {
                if ( ! isset( $all_services[ $svc['name'] ] ) ) {
                    $all_services[ $svc['name'] ] = [
                        'category' => $svc['category'],
                        'sites'    => [],
                    ];
                }
                $all_services[ $svc['name'] ]['sites'][] = "#{$blog_id} ({$site['name']})";
            }
        }

        ksort( $all_services );
        $total = count( $results );
        foreach ( $all_services as $name => $info ) {
            $count = count( $info['sites'] );
            $label = $count === $total ? 'ALLE Sites' : "{$count}/{$total} Sites";
            echo "\n  [{$info['category']}] {$name}\n";
            echo "    Vorkommen: {$label}\n";
            foreach ( $info['sites'] as $site_label ) {
                echo "      - {$site_label}\n";
            }
        }

        echo "\n" . str_repeat( '═', 68 ) . "\n";
        echo "Scan abgeschlossen.\n\n";
    }

    /**
     * JSON-Export
     */
    public static function export_json( array $results ): string {
        $export = [
            'scan_date'  => gmdate( 'c' ),
            'site_count' => count( $results ),
            'sites'      => [],
        ];

        foreach ( $results as $blog_id => $site ) {
            $services = [];
            foreach ( $site['services'] as $svc ) {
                $services[] = [
                    'name'     => $svc['name'],
                    'category' => $svc['category'],
                    'sources'  => array_values( $svc['sources'] ),
                ];
            }
            $export['sites'][] = [
                'blog_id'  => $blog_id,
                'name'     => $site['name'],
                'url'      => $site['url'],
                'services' => $services,
            ];
        }

        return wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    /**
     * CSV-Export
     */
    public static function export_csv( array $results ): string {
        $lines = [];
        $lines[] = implode( ';', [ 'Blog-ID', 'Site-Name', 'Site-URL', 'Kategorie', 'Dienst', 'Quelle' ] );

        foreach ( $results as $blog_id => $site ) {
            foreach ( $site['services'] as $svc ) {
                foreach ( $svc['sources'] as $src ) {
                    $lines[] = implode( ';', [
                        $blog_id,
                        '"' . str_replace( '"', '""', $site['name'] ) . '"',
                        $site['url'],
                        $svc['category'],
                        $svc['name'],
                        '"' . str_replace( '"', '""', $src ) . '"',
                    ] );
                }
            }
        }

        return implode( "\n", $lines );
    }
}


/**
 * ============================================================
 * AUSFUEHRUNG
 * ============================================================
 */
$scanner = new MultisitePrivacyScanner();
$results = $scanner->scan_all_sites();

// Konsolenausgabe
PrivacyScanOutput::print_console( $results );

// Optional JSON-Export
$json_path = ABSPATH . 'privacy-scan-' . gmdate( 'Y-m-d_His' ) . '.json';
$json_content = PrivacyScanOutput::export_json( $results );
$do_json = defined( 'WP_CLI' );
if ( $do_json ) {
    file_put_contents( $json_path, $json_content );
    WP_CLI::success( "JSON-Export gespeichert: {$json_path}" );
}

// Optional CSV-Export
$do_csv = defined( 'WP_CLI' );
if ( $do_csv ) {
    $csv_path = ABSPATH . 'privacy-scan-' . gmdate( 'Y-m-d_His' ) . '.csv';
    file_put_contents( $csv_path, "\xEF\xBB\xBF" . PrivacyScanOutput::export_csv( $results ) );
    WP_CLI::success( "CSV-Export gespeichert: {$csv_path}" );
}
