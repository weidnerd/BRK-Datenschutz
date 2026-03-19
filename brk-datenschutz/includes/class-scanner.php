<?php
/**
 * BRK Datenschutz – Scanner
 *
 * Scannt die aktuelle Site nach datenschutzrelevanten Diensten und externen URLs.
 * Adaptiert aus MultisitePrivacyScanner (scan-privacy-services.php v1.2.0).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BRK_DS_Scanner {

    private array $services      = [];
    private array $external_urls = [];

    /**
     * Aktuelle Site scannen und Ergebnis zurueckgeben.
     *
     * @return array { services: array, external_urls: array }
     */
    public function scan_current_site(): array {
        $this->services      = [];
        $this->external_urls = [];

        $this->scan_plugins();
        $this->scan_theme();
        $this->scan_options();
        $this->scan_post_content();
        $this->scan_widgets();
        $this->scan_external_urls();
        $this->scan_theme_files();

        // Netzwerk-Plugins (nur Multisite)
        if ( is_multisite() ) {
            $this->scan_network_plugins();
        }

        // Quellen deduplizieren
        foreach ( $this->services as $key => &$svc ) {
            $svc['sources'] = array_values( array_unique( $svc['sources'] ) );
        }

        return [
            'services'      => $this->services,
            'external_urls' => $this->external_urls,
        ];
    }

    /* ----------------------------------------------------------
     * Plugin-Scan
     * ---------------------------------------------------------- */

    private function scan_plugins(): void {
        $active_plugins = get_option( 'active_plugins', [] );
        $plugin_map     = BRK_DS_Registry::get_plugin_map();

        foreach ( $active_plugins as $plugin_path ) {
            $slug = dirname( $plugin_path );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_path, '.php' );
            }

            if ( isset( $plugin_map[ $slug ] ) ) {
                $this->add_service( $plugin_map[ $slug ], 'Plugin: ' . $plugin_path );
            }

            $this->check_generic_plugin_name( $slug, $plugin_path );
        }
    }

    private function check_generic_plugin_name( string $slug, string $plugin_path ): void {
        $generic = [
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

        foreach ( $generic as $pattern => $service ) {
            if ( preg_match( $pattern, $slug ) ) {
                $this->add_service( $service, 'Plugin (generisch): ' . $plugin_path );
            }
        }
    }

    /* ----------------------------------------------------------
     * Theme-Scan
     * ---------------------------------------------------------- */

    private function scan_theme(): void {
        $theme      = wp_get_theme();
        $theme_mods = get_theme_mods();

        if ( is_array( $theme_mods ) ) {
            $serialized = serialize( $theme_mods );
            foreach ( BRK_DS_Registry::get_content_patterns() as $pattern => $service ) {
                if ( preg_match( $pattern, $serialized ) ) {
                    $this->add_service( $service, 'Theme-Option (' . $theme->get( 'Name' ) . ')' );
                }
            }
        }
    }

    /* ----------------------------------------------------------
     * Options-Scan
     * ---------------------------------------------------------- */

    private function scan_options(): void {
        global $wpdb;
        foreach ( BRK_DS_Registry::get_option_patterns() as $key_fragment => $service ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 5",
                    '%' . $wpdb->esc_like( $key_fragment ) . '%'
                )
            );
            foreach ( $results as $row ) {
                if ( ! empty( $row->option_value ) && $row->option_value !== '0' && $row->option_value !== 'a:0:{}' ) {
                    $this->add_service( $service, 'Option: ' . $row->option_name );
                    break;
                }
            }
        }
    }

    /* ----------------------------------------------------------
     * Post-Content-Scan
     * ---------------------------------------------------------- */

    private function scan_post_content(): void {
        global $wpdb;
        $patterns = BRK_DS_Registry::get_content_patterns();

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts}
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
                        $service,
                        sprintf( 'Post: "%s" (ID %d)', mb_substr( $post->post_title, 0, 50 ), $post->ID )
                    );
                }
            }
        }

        // Postmeta
        $meta_results = $wpdb->get_results(
            "SELECT pm.meta_key, pm.meta_value, p.post_title, p.ID
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_status = 'publish'
               AND pm.meta_value LIKE '%http%'
               AND LENGTH(pm.meta_value) < 65535
             LIMIT 1000"
        );

        foreach ( $meta_results as $meta ) {
            foreach ( $patterns as $pattern => $service ) {
                if ( preg_match( $pattern, $meta->meta_value ) ) {
                    $this->add_service(
                        $service,
                        sprintf( 'Post-Meta (%s): "%s" (ID %d)', $meta->meta_key, mb_substr( $meta->post_title, 0, 50 ), $meta->ID )
                    );
                }
            }
        }
    }

    /* ----------------------------------------------------------
     * Widget-Scan
     * ---------------------------------------------------------- */

    private function scan_widgets(): void {
        global $wpdb;
        $patterns = BRK_DS_Registry::get_content_patterns();

        $widget_options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                 WHERE option_name LIKE %s AND option_value LIKE %s",
                'widget_%',
                '%http%'
            )
        );

        foreach ( $widget_options as $row ) {
            foreach ( $patterns as $pattern => $service ) {
                if ( preg_match( $pattern, $row->option_value ) ) {
                    $this->add_service( $service, 'Widget: ' . $row->option_name );
                }
            }
        }
    }

    /* ----------------------------------------------------------
     * Netzwerk-Plugins (Multisite)
     * ---------------------------------------------------------- */

    private function scan_network_plugins(): void {
        $network_plugins = get_site_option( 'active_sitewide_plugins', [] );
        $plugin_map      = BRK_DS_Registry::get_plugin_map();

        foreach ( array_keys( $network_plugins ) as $plugin_path ) {
            $slug = dirname( $plugin_path );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_path, '.php' );
            }
            if ( isset( $plugin_map[ $slug ] ) ) {
                $this->add_service( $plugin_map[ $slug ], 'Netzwerk-Plugin: ' . $plugin_path );
            }
        }
    }

    /* ----------------------------------------------------------
     * Theme-Dateien scannen
     * ---------------------------------------------------------- */

    private function scan_theme_files(): void {
        $theme_dir = get_stylesheet_directory();
        if ( ! is_dir( $theme_dir ) ) {
            return;
        }

        $patterns   = BRK_DS_Registry::get_content_patterns();
        $extensions = [ 'php', 'html', 'js', 'css' ];
        $iterator   = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $theme_dir, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $count = 0;
        foreach ( $iterator as $file ) {
            if ( $count >= 200 ) break;
            $ext = strtolower( $file->getExtension() );
            if ( ! in_array( $ext, $extensions, true ) ) continue;

            $content = @file_get_contents( $file->getPathname() );
            if ( empty( $content ) ) continue;

            $relative = str_replace( $theme_dir, '', $file->getPathname() );
            foreach ( $patterns as $pattern => $service ) {
                if ( preg_match( $pattern, $content ) ) {
                    $this->add_service( $service, 'Theme-Datei: ' . $relative );
                }
            }
            $count++;
        }
    }

    /* ----------------------------------------------------------
     * Externe URLs sammeln
     * ---------------------------------------------------------- */

    private function scan_external_urls(): void {
        global $wpdb;
        $site_host = wp_parse_url( get_site_url(), PHP_URL_HOST );

        $ignore_hosts = [
            $site_host,
            'wordpress.org',
            'w.org',
            's.w.org',
            'api.wordpress.org',
        ];
        if ( is_multisite() ) {
            $network = get_network();
            if ( $network ) {
                $ignore_hosts[] = $network->domain;
            }
        }
        $ignore_hosts = array_unique( array_map( 'strtolower', $ignore_hosts ) );

        $url_pattern    = '/https?:\/\/[^\s"\'>\)\]\\\\]{5,200}/i';
        $external_urls  = [];

        // Post-Content
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_content LIKE '%http%'
               AND post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache','custom_css','wp_global_styles','wp_navigation','wp_template','wp_template_part')
             ORDER BY ID DESC
             LIMIT 1000"
        );

        foreach ( $posts as $post ) {
            if ( preg_match_all( $url_pattern, $post->post_content, $matches ) ) {
                $source = sprintf( 'Post: "%s" (ID %d)', mb_substr( $post->post_title, 0, 40 ), $post->ID );
                foreach ( $matches[0] as $url ) {
                    $this->collect_external_url( $external_urls, $url, $source, $ignore_hosts );
                }
            }
        }

        // Post-Meta
        $metas = $wpdb->get_results(
            "SELECT pm.meta_key, pm.meta_value, p.post_title, p.ID
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_status = 'publish'
               AND pm.meta_value LIKE '%http%'
               AND LENGTH(pm.meta_value) < 65535
             LIMIT 1000"
        );

        foreach ( $metas as $meta ) {
            if ( preg_match_all( $url_pattern, $meta->meta_value, $matches ) ) {
                $source = sprintf( 'Meta (%s): "%s" (ID %d)', $meta->meta_key, mb_substr( $meta->post_title, 0, 30 ), $meta->ID );
                foreach ( $matches[0] as $url ) {
                    $this->collect_external_url( $external_urls, $url, $source, $ignore_hosts );
                }
            }
        }

        // Widget-Options
        $widget_options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                 WHERE option_name LIKE %s AND option_value LIKE %s",
                'widget_%',
                '%http%'
            )
        );

        foreach ( $widget_options as $row ) {
            if ( preg_match_all( $url_pattern, $row->option_value, $matches ) ) {
                foreach ( $matches[0] as $url ) {
                    $this->collect_external_url( $external_urls, $url, 'Widget: ' . $row->option_name, $ignore_hosts );
                }
            }
        }

        // Theme-Mods
        $theme_mods = get_theme_mods();
        if ( is_array( $theme_mods ) ) {
            $serialized = serialize( $theme_mods );
            if ( preg_match_all( $url_pattern, $serialized, $matches ) ) {
                foreach ( $matches[0] as $url ) {
                    $this->collect_external_url( $external_urls, $url, 'Theme-Optionen', $ignore_hosts );
                }
            }
        }

        ksort( $external_urls );
        $this->external_urls = $external_urls;
    }

    private function collect_external_url( array &$collection, string $url, string $source, array $ignore_hosts ): void {
        $url    = rtrim( $url, '.,;:!?&' );
        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['host'] ) ) {
            return;
        }

        $host = strtolower( $parsed['host'] );

        foreach ( $ignore_hosts as $ignore ) {
            if ( $host === $ignore || str_ends_with( $host, '.' . $ignore ) ) {
                return;
            }
        }

        $domain = $host;
        if ( ! isset( $collection[ $domain ] ) ) {
            $collection[ $domain ] = [ 'urls' => [], 'sources' => [] ];
        }

        if ( count( $collection[ $domain ]['urls'] ) < 10 ) {
            $clean_url = ( $parsed['scheme'] ?? 'https' ) . '://' . $host . ( $parsed['path'] ?? '/' );
            if ( ! in_array( $clean_url, $collection[ $domain ]['urls'], true ) ) {
                $collection[ $domain ]['urls'][] = $clean_url;
            }
        }

        if ( ! in_array( $source, $collection[ $domain ]['sources'], true ) && count( $collection[ $domain ]['sources'] ) < 5 ) {
            $collection[ $domain ]['sources'][] = $source;
        }
    }

    /* ----------------------------------------------------------
     * Helfer
     * ---------------------------------------------------------- */

    private function add_service( array $service, string $source ): void {
        $key = $service['name'];
        if ( ! isset( $this->services[ $key ] ) ) {
            $this->services[ $key ] = [
                'name'     => $service['name'],
                'category' => $service['category'],
                'sources'  => [],
            ];
        }
        $this->services[ $key ]['sources'][] = $source;
    }
}
