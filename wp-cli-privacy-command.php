<?php
/**
 * WP-CLI Kommando: Datenschutz-Scanner fuer WordPress Multisite
 *
 * Installation:
 *   Datei nach wp-content/mu-plugins/ kopieren
 *
 * Nutzung:
 *   wp privacy scan                    (Konsolen-Report)
 *   wp privacy scan --format=json      (JSON-Ausgabe)
 *   wp privacy scan --format=csv       (CSV-Ausgabe)
 *   wp privacy scan --export=json      (JSON in Datei speichern)
 *   wp privacy scan --export=csv       (CSV in Datei speichern)
 *   wp privacy scan --site=3           (Nur Site #3 scannen)
 *   wp privacy scan --category=Analytics  (Nach Kategorie filtern)
 *
 * @package BRK-Datenschutz
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Scannt das Multisite-Netzwerk nach datenschutzrelevanten Diensten.
 */
class Privacy_Scan_Command extends WP_CLI_Command {

    /**
     * Scannt alle Sites im Netzwerk nach datenschutzrelevanten Diensten.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Ausgabeformat.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     * ---
     *
     * [--export=<type>]
     * : Export in Datei (json oder csv).
     *
     * [--site=<blog_id>]
     * : Nur eine bestimmte Site scannen.
     *
     * [--category=<category>]
     * : Ergebnisse nach Kategorie filtern.
     *
     * ## EXAMPLES
     *
     *     wp privacy scan
     *     wp privacy scan --format=json
     *     wp privacy scan --export=csv
     *     wp privacy scan --site=2
     *     wp privacy scan --category=Analytics
     *
     * @when after_wp_load
     */
    public function scan( $args, $assoc_args ) {
        if ( ! is_multisite() ) {
            WP_CLI::error( 'Dieses Kommando ist nur fuer WordPress Multisite.' );
        }

        $format   = $assoc_args['format'] ?? 'table';
        $export   = $assoc_args['export'] ?? null;
        $site_id  = isset( $assoc_args['site'] ) ? (int) $assoc_args['site'] : null;
        $cat_filter = $assoc_args['category'] ?? null;

        // Scanner-Skript laden
        $scanner_path = __DIR__ . '/../../../scan-privacy-services.php';
        $alt_paths = [
            ABSPATH . 'scan-privacy-services.php',
            WP_CONTENT_DIR . '/scan-privacy-services.php',
            dirname( __FILE__ ) . '/scan-privacy-services.php',
        ];

        // Wir nutzen die Klassen direkt, wenn die Datei schon geladen ist
        if ( ! class_exists( 'MultisitePrivacyScanner' ) ) {
            $loaded = false;
            foreach ( $alt_paths as $path ) {
                if ( file_exists( $path ) ) {
                    // Nicht ausfuehren, nur Klassen laden
                    // Wir muessen die Datei anders einbinden
                    $loaded = true;
                    WP_CLI::log( "Scanner gefunden: {$path}" );
                    break;
                }
            }

            if ( ! $loaded ) {
                WP_CLI::error(
                    "scan-privacy-services.php nicht gefunden.\n" .
                    "Bitte legen Sie die Datei in eines der folgenden Verzeichnisse:\n" .
                    "  - " . ABSPATH . "\n" .
                    "  - " . WP_CONTENT_DIR . "\n"
                );
            }
        }

        WP_CLI::log( '' );
        WP_CLI::log( '🔍 Starte Datenschutz-Scan...' );
        WP_CLI::log( '' );

        // Eigene leichtgewichtige Scan-Logik
        $results = $this->run_scan( $site_id );

        // Kategorie-Filter anwenden
        if ( $cat_filter ) {
            foreach ( $results as $blog_id => &$site ) {
                $site['services'] = array_filter( $site['services'], function( $svc ) use ( $cat_filter ) {
                    return stripos( $svc['category'], $cat_filter ) !== false;
                });
            }
        }

        // Ausgabe
        switch ( $format ) {
            case 'json':
                $this->output_json( $results );
                break;
            case 'csv':
                $this->output_csv( $results );
                break;
            default:
                $this->output_table( $results );
                break;
        }

        // Export
        if ( $export ) {
            $this->do_export( $results, $export );
        }

        $total_services = 0;
        foreach ( $results as $site ) {
            $total_services += count( $site['services'] );
        }
        WP_CLI::success(
            sprintf(
                'Scan abgeschlossen: %d Sites, %d Dienste erkannt.',
                count( $results ),
                $total_services
            )
        );
    }

    /**
     * Zeigt eine Liste aller erkannten Dienst-Kategorien.
     *
     * ## EXAMPLES
     *
     *     wp privacy categories
     *
     * @when after_wp_load
     * @subcommand categories
     */
    public function categories( $args, $assoc_args ) {
        $categories = [
            'Analytics/Tracking'          => 'Google Analytics, Tag Manager, Matomo, Hotjar, Facebook Pixel, etc.',
            'Kontaktformular'             => 'Contact Form 7, WPForms, Gravity Forms, Ninja Forms, etc.',
            'Newsletter/E-Mail'           => 'Mailchimp, Brevo, MailPoet, KlickTipp, etc.',
            'Anti-Spam/Captcha'           => 'Google reCAPTCHA, hCaptcha, Akismet, Antispam Bee',
            'Social/Extern'               => 'Facebook, Twitter, Instagram, Jetpack, Social Share Buttons',
            'Karten/Standort'             => 'Google Maps, OpenStreetMap, Leaflet',
            'Fonts/CDN'                   => 'Google Fonts, Font Awesome, Adobe Typekit, CDNs',
            'Video/Embed'                 => 'YouTube, Vimeo, Spotify Embeds',
            'E-Commerce/Zahlung'          => 'WooCommerce, Stripe, PayPal, Klarna',
            'Chat/Support'                => 'Tawk.to, Tidio, Crisp, Zendesk, HubSpot, Userlike',
            'Kommentare'                  => 'Disqus, externe Kommentarsysteme',
            'Datenschutz-Tool'            => 'Cookie Consent Banner (Complianz, Borlabs, Real Cookie Banner)',
            'Page Builder'                => 'Elementor (laedt ggf. Google Fonts extern)',
            'Sicherheit'                  => 'Wordfence, Sucuri (externe API-Verbindungen)',
            'Backup/Cloud'                => 'UpdraftPlus, BackWPup (Cloud-Speicher)',
            'Werbung'                     => 'Google AdSense, Google Ads',
            'SEO'                         => 'Yoast SEO, Rank Math (sitemaps, Indexierung)',
            'Sonstige externe Dienste'    => 'Gravatar, sonstige APIs',
        ];

        $items = [];
        foreach ( $categories as $cat => $desc ) {
            $items[] = [
                'Kategorie'   => $cat,
                'Beschreibung' => $desc,
            ];
        }

        WP_CLI\Utils\format_items( 'table', $items, [ 'Kategorie', 'Beschreibung' ] );
    }

    /**
     * Scan-Logik (nutzt die gleiche Methodik wie das Standalone-Skript)
     */
    private function run_scan( ?int $only_blog_id = null ): array {
        $site_args = [ 'number' => 0 ];
        if ( $only_blog_id ) {
            $site_args['site__in'] = [ $only_blog_id ];
        }

        $sites   = get_sites( $site_args );
        $results = [];

        // Include des Haupt-Scanners falls vorhanden
        if ( class_exists( 'MultisitePrivacyScanner' ) ) {
            $scanner = new MultisitePrivacyScanner();
            return $scanner->scan_all_sites();
        }

        // Fallback: vereinfachter Inline-Scan
        $content_patterns = $this->get_content_patterns();
        $plugin_map       = $this->get_plugin_map();

        $progress = \WP_CLI\Utils\make_progress_bar( 'Scanne Sites', count( $sites ) );

        foreach ( $sites as $site ) {
            $blog_id = (int) $site->blog_id;
            switch_to_blog( $blog_id );

            $results[ $blog_id ] = [
                'blog_id'  => $blog_id,
                'name'     => get_bloginfo( 'name' ),
                'url'      => get_site_url(),
                'services' => [],
            ];

            // Plugins pruefen
            $active_plugins = get_option( 'active_plugins', [] );
            foreach ( $active_plugins as $plugin_path ) {
                $slug = dirname( $plugin_path );
                if ( $slug === '.' ) $slug = basename( $plugin_path, '.php' );
                if ( isset( $plugin_map[ $slug ] ) ) {
                    $key = $plugin_map[ $slug ]['name'];
                    $results[ $blog_id ]['services'][ $key ] = [
                        'name'     => $plugin_map[ $slug ]['name'],
                        'category' => $plugin_map[ $slug ]['category'],
                        'sources'  => [ 'Plugin: ' . $plugin_path ],
                    ];
                }
            }

            // Post-Content scannen
            global $wpdb;
            $posts = $wpdb->get_results(
                "SELECT ID, post_title, post_content FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_content != ''
                   AND post_type IN ('post','page','wp_block')
                 ORDER BY ID DESC LIMIT 500"
            );

            foreach ( $posts as $post ) {
                foreach ( $content_patterns as $pattern => $service ) {
                    if ( preg_match( $pattern, $post->post_content ) ) {
                        $key = $service['name'];
                        if ( ! isset( $results[ $blog_id ]['services'][ $key ] ) ) {
                            $results[ $blog_id ]['services'][ $key ] = [
                                'name'     => $service['name'],
                                'category' => $service['category'],
                                'sources'  => [],
                            ];
                        }
                        $results[ $blog_id ]['services'][ $key ]['sources'][] =
                            sprintf( 'Content: "%s" (ID %d)', mb_substr( $post->post_title, 0, 40 ), $post->ID );
                    }
                }
            }

            // Options scannen
            $option_checks = [
                'google_analytics'    => ['name' => 'Google Analytics', 'category' => 'Analytics/Tracking'],
                'gtm'                 => ['name' => 'Google Tag Manager', 'category' => 'Analytics/Tracking'],
                'recaptcha'           => ['name' => 'Google reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
                'akismet_api_key'     => ['name' => 'Akismet', 'category' => 'Anti-Spam/Captcha'],
                'mailchimp'           => ['name' => 'Mailchimp', 'category' => 'Newsletter/E-Mail'],
                'mc4wp'               => ['name' => 'Mailchimp for WP', 'category' => 'Newsletter/E-Mail'],
                'jetpack_activated'   => ['name' => 'Jetpack', 'category' => 'Social/Extern'],
                'elementor'           => ['name' => 'Elementor', 'category' => 'Page Builder'],
            ];

            foreach ( $option_checks as $key_fragment => $service ) {
                $found = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
                        '%' . $wpdb->esc_like( $key_fragment ) . '%'
                    )
                );
                if ( ! empty( $found ) && $found !== '0' ) {
                    $svc_key = $service['name'];
                    if ( ! isset( $results[ $blog_id ]['services'][ $svc_key ] ) ) {
                        $results[ $blog_id ]['services'][ $svc_key ] = [
                            'name'     => $service['name'],
                            'category' => $service['category'],
                            'sources'  => [ 'Option: ' . $key_fragment ],
                        ];
                    }
                }
            }

            restore_current_blog();
            $progress->tick();
        }

        $progress->finish();

        // Netzwerk-Plugins
        $network_plugins = get_site_option( 'active_sitewide_plugins', [] );
        foreach ( array_keys( $network_plugins ) as $plugin_path ) {
            $slug = dirname( $plugin_path );
            if ( $slug === '.' ) $slug = basename( $plugin_path, '.php' );
            if ( isset( $plugin_map[ $slug ] ) ) {
                foreach ( $results as $blog_id => &$site_data ) {
                    $key = $plugin_map[ $slug ]['name'];
                    if ( ! isset( $site_data['services'][ $key ] ) ) {
                        $site_data['services'][ $key ] = [
                            'name'     => $plugin_map[ $slug ]['name'],
                            'category' => $plugin_map[ $slug ]['category'],
                            'sources'  => [],
                        ];
                    }
                    $site_data['services'][ $key ]['sources'][] = 'Netzwerk-Plugin: ' . $plugin_path;
                }
            }
        }

        return $results;
    }

    private function output_table( array $results ): void {
        foreach ( $results as $blog_id => $site ) {
            WP_CLI::log( '' );
            WP_CLI::log( WP_CLI::colorize( "%B━━━ Site #{$blog_id}: {$site['name']} ({$site['url']}) ━━━%n" ) );

            if ( empty( $site['services'] ) ) {
                WP_CLI::log( WP_CLI::colorize( '  %G✓ Keine datenschutzrelevanten Dienste erkannt.%n' ) );
                continue;
            }

            $items = [];
            foreach ( $site['services'] as $svc ) {
                $items[] = [
                    'Kategorie' => $svc['category'],
                    'Dienst'    => $svc['name'],
                    'Quellen'   => implode( ', ', array_slice( $svc['sources'], 0, 3 ) ),
                ];
            }

            usort( $items, fn( $a, $b ) => strcmp( $a['Kategorie'], $b['Kategorie'] ) );
            WP_CLI\Utils\format_items( 'table', $items, [ 'Kategorie', 'Dienst', 'Quellen' ] );
        }
    }

    private function output_json( array $results ): void {
        $export = [];
        foreach ( $results as $blog_id => $site ) {
            $services = [];
            foreach ( $site['services'] as $svc ) {
                $services[] = $svc;
            }
            $export[] = [
                'blog_id'  => $blog_id,
                'name'     => $site['name'],
                'url'      => $site['url'],
                'services' => $services,
            ];
        }
        WP_CLI::log( json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    private function output_csv( array $results ): void {
        echo "Blog-ID;Site-Name;Site-URL;Kategorie;Dienst;Quelle\n";
        foreach ( $results as $blog_id => $site ) {
            foreach ( $site['services'] as $svc ) {
                foreach ( $svc['sources'] as $src ) {
                    printf(
                        "%d;\"%s\";\"%s\";\"%s\";\"%s\";\"%s\"\n",
                        $blog_id,
                        str_replace( '"', '""', $site['name'] ),
                        $site['url'],
                        $svc['category'],
                        $svc['name'],
                        str_replace( '"', '""', $src )
                    );
                }
            }
        }
    }

    private function do_export( array $results, string $type ): void {
        $filename = 'privacy-scan-' . gmdate( 'Y-m-d_His' );

        if ( $type === 'json' ) {
            $path = ABSPATH . $filename . '.json';
            $export = [];
            foreach ( $results as $blog_id => $site ) {
                $export[] = [
                    'blog_id'  => $blog_id,
                    'name'     => $site['name'],
                    'url'      => $site['url'],
                    'services' => array_values( $site['services'] ),
                ];
            }
            file_put_contents( $path, json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
            WP_CLI::success( "JSON-Export: {$path}" );
        } elseif ( $type === 'csv' ) {
            $path = ABSPATH . $filename . '.csv';
            ob_start();
            $this->output_csv( $results );
            $csv = ob_get_clean();
            file_put_contents( $path, "\xEF\xBB\xBF" . $csv );
            WP_CLI::success( "CSV-Export: {$path}" );
        }
    }

    /**
     * Verkuerzte Content-Patterns (gleich wie im Hauptskript)
     */
    private function get_content_patterns(): array {
        return [
            '/google-analytics\.com|googletagmanager\.com|gtag\/js/i'   => ['name' => 'Google Analytics / Tag Manager', 'category' => 'Analytics/Tracking'],
            '/maps\.googleapis\.com|maps\.google\./i'                   => ['name' => 'Google Maps', 'category' => 'Karten/Standort'],
            '/fonts\.googleapis\.com|fonts\.gstatic\.com/i'             => ['name' => 'Google Fonts (extern)', 'category' => 'Fonts/CDN'],
            '/www\.google\.com\/recaptcha|gstatic\.com\/recaptcha/i'    => ['name' => 'Google reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
            '/youtube\.com\/embed|youtube-nocookie\.com|youtu\.be/i'    => ['name' => 'YouTube Embed', 'category' => 'Video/Embed'],
            '/connect\.facebook\.net|facebook\.com\/plugins/i'          => ['name' => 'Facebook SDK', 'category' => 'Social/Extern'],
            '/platform\.twitter\.com|twitter\.com\/widgets/i'           => ['name' => 'Twitter/X Embed', 'category' => 'Social/Extern'],
            '/player\.vimeo\.com/i'                                     => ['name' => 'Vimeo Embed', 'category' => 'Video/Embed'],
            '/open\.spotify\.com\/embed/i'                              => ['name' => 'Spotify Embed', 'category' => 'Audio/Embed'],
            '/js\.stripe\.com/i'                                        => ['name' => 'Stripe', 'category' => 'E-Commerce/Zahlung'],
            '/paypal\.com\/sdk|paypalobjects\.com/i'                    => ['name' => 'PayPal', 'category' => 'E-Commerce/Zahlung'],
            '/gravatar\.com/i'                                          => ['name' => 'Gravatar', 'category' => 'Sonstige externe Dienste'],
            '/stats\.wp\.com|pixel\.wp\.com/i'                          => ['name' => 'WordPress.com Stats', 'category' => 'Analytics/Tracking'],
            '/hotjar\.com/i'                                            => ['name' => 'Hotjar', 'category' => 'Analytics/Tracking'],
            '/clarity\.ms/i'                                            => ['name' => 'Microsoft Clarity', 'category' => 'Analytics/Tracking'],
            '/cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com/i'              => ['name' => 'Externes CDN', 'category' => 'Fonts/CDN'],
            '/disqus\.com/i'                                            => ['name' => 'Disqus', 'category' => 'Kommentare'],
            '/openstreetmap\.org/i'                                     => ['name' => 'OpenStreetMap', 'category' => 'Karten/Standort'],
            '/instagram\.com\/embed/i'                                  => ['name' => 'Instagram Embed', 'category' => 'Social/Extern'],
            '/chimpstatic\.com|list-manage\.com/i'                      => ['name' => 'Mailchimp', 'category' => 'Newsletter/E-Mail'],
            '/embed\.tawk\.to/i'                                        => ['name' => 'Tawk.to Chat', 'category' => 'Chat/Support'],
            '/js\.hs-scripts\.com|hubspot\.com/i'                       => ['name' => 'HubSpot', 'category' => 'Chat/Support'],
            '/googlesyndication\.com|doubleclick\.net/i'                => ['name' => 'Google Ads/AdSense', 'category' => 'Werbung'],
        ];
    }

    /**
     * Plugin-Map (verkuerzt, gleich wie im Hauptskript)
     */
    private function get_plugin_map(): array {
        return [
            'google-analytics-for-wordpress'    => ['name' => 'MonsterInsights (GA)', 'category' => 'Analytics/Tracking'],
            'google-site-kit'                   => ['name' => 'Google Site Kit', 'category' => 'Analytics/Tracking'],
            'ga-google-analytics'               => ['name' => 'GA Google Analytics', 'category' => 'Analytics/Tracking'],
            'wp-statistics'                     => ['name' => 'WP Statistics', 'category' => 'Analytics/Tracking'],
            'matomo'                            => ['name' => 'Matomo', 'category' => 'Analytics/Tracking'],
            'duracelltomi-google-tag-manager'   => ['name' => 'Google Tag Manager', 'category' => 'Analytics/Tracking'],
            'pixelyoursite'                     => ['name' => 'PixelYourSite', 'category' => 'Analytics/Tracking'],
            'contact-form-7'                    => ['name' => 'Contact Form 7', 'category' => 'Kontaktformular'],
            'wpforms-lite'                      => ['name' => 'WPForms', 'category' => 'Kontaktformular'],
            'gravityforms'                      => ['name' => 'Gravity Forms', 'category' => 'Kontaktformular'],
            'ninja-forms'                       => ['name' => 'Ninja Forms', 'category' => 'Kontaktformular'],
            'fluentform'                        => ['name' => 'Fluent Forms', 'category' => 'Kontaktformular'],
            'formidable'                        => ['name' => 'Formidable Forms', 'category' => 'Kontaktformular'],
            'mailchimp-for-wp'                  => ['name' => 'Mailchimp for WP', 'category' => 'Newsletter/E-Mail'],
            'mailpoet'                          => ['name' => 'MailPoet', 'category' => 'Newsletter/E-Mail'],
            'newsletter'                        => ['name' => 'Newsletter Plugin', 'category' => 'Newsletter/E-Mail'],
            'sendinblue'                        => ['name' => 'Brevo (Sendinblue)', 'category' => 'Newsletter/E-Mail'],
            'google-captcha'                    => ['name' => 'Google reCAPTCHA', 'category' => 'Anti-Spam/Captcha'],
            'akismet'                           => ['name' => 'Akismet', 'category' => 'Anti-Spam/Captcha'],
            'antispam-bee'                      => ['name' => 'Antispam Bee', 'category' => 'Anti-Spam/Captcha'],
            'hcaptcha-for-forms-and-more'       => ['name' => 'hCaptcha', 'category' => 'Anti-Spam/Captcha'],
            'jetpack'                           => ['name' => 'Jetpack', 'category' => 'Social/Extern'],
            'instagram-feed'                    => ['name' => 'Instagram Feed', 'category' => 'Social/Extern'],
            'custom-facebook-feed'              => ['name' => 'Facebook Feed', 'category' => 'Social/Extern'],
            'add-to-any'                        => ['name' => 'AddToAny Share', 'category' => 'Social/Extern'],
            'wp-google-maps'                    => ['name' => 'WP Google Maps', 'category' => 'Karten/Standort'],
            'woocommerce'                       => ['name' => 'WooCommerce', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-payments'              => ['name' => 'WooCommerce Payments', 'category' => 'E-Commerce/Zahlung'],
            'woocommerce-paypal-payments'       => ['name' => 'WooCommerce PayPal', 'category' => 'E-Commerce/Zahlung'],
            'woo-stripe-payment'                => ['name' => 'Stripe Payment', 'category' => 'E-Commerce/Zahlung'],
            'elementor'                         => ['name' => 'Elementor', 'category' => 'Page Builder'],
            'wordfence'                         => ['name' => 'Wordfence', 'category' => 'Sicherheit'],
            'updraftplus'                       => ['name' => 'UpdraftPlus', 'category' => 'Backup/Cloud'],
            'complianz-gdpr'                    => ['name' => 'Complianz GDPR', 'category' => 'Datenschutz-Tool'],
            'cookie-notice'                     => ['name' => 'Cookie Notice', 'category' => 'Datenschutz-Tool'],
            'real-cookie-banner'                => ['name' => 'Real Cookie Banner', 'category' => 'Datenschutz-Tool'],
            'borlabs-cookie'                    => ['name' => 'Borlabs Cookie', 'category' => 'Datenschutz-Tool'],
            'disqus-comment-system'             => ['name' => 'Disqus', 'category' => 'Kommentare'],
        ];
    }
}

WP_CLI::add_command( 'privacy', 'Privacy_Scan_Command' );
