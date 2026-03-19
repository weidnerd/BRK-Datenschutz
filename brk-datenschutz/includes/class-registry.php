<?php
/**
 * BRK Datenschutz – Service-Registry
 *
 * Bekannte datenschutzrelevante Dienste, Content-Patterns und Options-Keys.
 * Extrahiert aus scan-privacy-services.php v1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BRK_DS_Registry {

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

            // Kontaktformulare
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
            'wp-google-maps'                       => ['name' => 'WP Google Maps', 'category' => 'Karten/Standort'],

            // Fonts / CDN
            'jetstash-custom-fonts'                => ['name' => 'Custom Fonts (extern)', 'category' => 'Fonts/CDN'],

            // E-Commerce / Zahlung
            'woocommerce'                          => ['name' => 'WooCommerce', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-payments'                 => ['name' => 'WooCommerce Payments (Stripe)', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-paypal-payments'          => ['name' => 'WooCommerce PayPal', 'category' => 'E-Commerce/Zahlung'],
            'woo-stripe-payment'                   => ['name' => 'Stripe Payment Gateway', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-germanized'               => ['name' => 'WooCommerce Germanized', 'category' => 'E-Commerce/Zahlung'],

            // Kommentare
            'disqus-comment-system'                => ['name' => 'Disqus Comment System', 'category' => 'Kommentare'],

            // Sicherheit / Login
            'wordfence'                            => ['name' => 'Wordfence Security', 'category' => 'Sicherheit'],

            // Page Builder
            'elementor'                            => ['name' => 'Elementor (Google Fonts, externe Bibliotheken)', 'category' => 'Page Builder'],

            // Backup
            'updraftplus'                          => ['name' => 'UpdraftPlus (Cloud-Backup)', 'category' => 'Backup/Cloud'],

            // Datenschutz-Tools
            'complianz-gdpr'                       => ['name' => 'Complianz GDPR/CCPA', 'category' => 'Datenschutz-Tool'],
            'cookie-notice'                        => ['name' => 'Cookie Notice & Compliance', 'category' => 'Datenschutz-Tool'],
            'real-cookie-banner'                   => ['name' => 'Real Cookie Banner', 'category' => 'Datenschutz-Tool'],
            'borlabs-cookie'                       => ['name' => 'Borlabs Cookie', 'category' => 'Datenschutz-Tool'],
        ];
    }

    /**
     * Regex-Patterns fuer Content-Scan (Post-Inhalte, Widgets usw.)
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
            '/googlesyndication\.com|doubleclick\.net|googleadservices\.com/i'
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
