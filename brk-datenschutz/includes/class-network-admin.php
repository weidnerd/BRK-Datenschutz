<?php
/**
 * BRK Datenschutz – Network-Admin-Seite (Super-Admin)
 *
 * Uebersicht aller Dienste ueber alle Sites hinweg, Haeufigkeiten,
 * und Verwaltung von Datenschutzklauseln / Links pro Dienst.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BRK_DS_Network_Admin {

    public static function init(): void {
        add_action( 'network_admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_brk_ds_save_clauses', [ __CLASS__, 'handle_save_clauses' ] );
        add_action( 'admin_post_brk_ds_network_rescan', [ __CLASS__, 'handle_network_rescan' ] );
        add_action( 'admin_post_brk_ds_save_settings', [ __CLASS__, 'handle_save_settings' ] );
    }

    /**
     * Menue im Netzwerk-Admin
     */
    public static function add_menu(): void {
        add_menu_page(
            'Datenschutz-Uebersicht',
            'Datenschutz',
            'manage_network',
            'brk-datenschutz',
            [ __CLASS__, 'render_page' ],
            'dashicons-shield',
            80
        );

        add_submenu_page(
            'brk-datenschutz',
            'Dienste-Uebersicht',
            'Uebersicht',
            'manage_network',
            'brk-datenschutz',
            [ __CLASS__, 'render_page' ]
        );

        add_submenu_page(
            'brk-datenschutz',
            'Datenschutzklauseln verwalten',
            'Klauseln verwalten',
            'manage_network',
            'brk-datenschutz-clauses',
            [ __CLASS__, 'render_clauses_page' ]
        );

        add_submenu_page(
            'brk-datenschutz',
            'Einstellungen',
            'Einstellungen',
            'manage_network',
            'brk-datenschutz-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /* ----------------------------------------------------------
     * Uebersichtsseite: Dienste + Haeufigkeiten
     * ---------------------------------------------------------- */

    public static function render_page(): void {
        $sites     = get_sites( [ 'number' => 0 ] );
        $all_data  = [];
        $aggregate = []; // service_name => { info, count, sites[] }

        foreach ( $sites as $site ) {
            $blog_id = (int) $site->blog_id;
            $result  = BRK_Datenschutz::get_scan_results( $blog_id );

            switch_to_blog( $blog_id );
            $site_name = get_bloginfo( 'name' ) ?: get_site_url();
            restore_current_blog();

            $all_data[ $blog_id ] = [
                'name'     => $site_name,
                'url'      => get_site_url( $blog_id ),
                'services' => $result['services'] ?? [],
            ];

            foreach ( $result['services'] ?? [] as $svc ) {
                $key = sanitize_title( $svc['name'] );
                if ( ! isset( $aggregate[ $key ] ) ) {
                    $aggregate[ $key ] = [
                        'name'     => $svc['name'],
                        'category' => $svc['category'],
                        'count'    => 0,
                        'sites'    => [],
                    ];
                }
                $aggregate[ $key ]['count']++;
                $aggregate[ $key ]['sites'][] = [
                    'name'    => $site_name,
                    'blog_id' => $blog_id,
                    'sources' => $svc['sources'] ?? [],
                ];
            }
        }

        // Nach Haeufigkeit sortieren (absteigend)
        uasort( $aggregate, fn( $a, $b ) => $b['count'] <=> $a['count'] );

        $clauses = BRK_Datenschutz::get_clauses();

        ?>
        <div class="wrap brk-ds-wrap">
            <h1>Datenschutz-Dienste im Netzwerk</h1>

            <?php if ( isset( $_GET['rescanned'] ) && $_GET['rescanned'] === '1' ) : ?>
                <div class="notice notice-success"><p>Alle Sites wurden neu gescannt.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
                <?php wp_nonce_field( 'brk_ds_network_rescan' ); ?>
                <input type="hidden" name="action" value="brk_ds_network_rescan">
                <button type="submit" class="button button-secondary">Alle Sites neu scannen</button>
                <span class="description" style="margin-left:10px;"><?php echo count( $sites ); ?> Sites im Netzwerk</span>
            </form>

            <?php if ( empty( $aggregate ) ) : ?>
                <div class="notice notice-info"><p>Es wurden keine datenschutzrelevanten Dienste gefunden.</p></div>
            <?php else : ?>

                <h2>Erkannte Dienste (<?php echo count( $aggregate ); ?>)</h2>
                <table class="widefat striped brk-ds-table">
                    <thead>
                        <tr>
                            <th>Dienst</th>
                            <th>Kategorie</th>
                            <th>Vorkommen</th>
                            <th>Sites</th>
                            <th>Klausel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $aggregate as $key => $data ) :
                            $clause = $clauses[ $key ] ?? null;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $data['name'] ); ?></strong></td>
                            <td><?php echo esc_html( $data['category'] ); ?></td>
                            <td class="brk-ds-count"><?php echo (int) $data['count']; ?></td>
                            <td>
                                <?php
                                // Unique Sites ermitteln
                                $seen       = [];
                                $unique_sites = [];
                                foreach ( $data['sites'] as $site_entry ) {
                                    if ( isset( $seen[ $site_entry['blog_id'] ] ) ) continue;
                                    $seen[ $site_entry['blog_id'] ] = true;
                                    $unique_sites[] = $site_entry;
                                }
                                $site_count = count( $unique_sites );

                                if ( $site_count > 1 ) {
                                    ?>
                                    <a href="#" class="brk-ds-toggle" data-count="<?php echo $site_count; ?>"
                                       onclick="var el=this.nextElementSibling;if(el.style.display==='none'){el.style.display='block';this.textContent='\u25BC Sites ausblenden';}else{el.style.display='none';this.textContent='\u25B6 '+this.dataset.count+' Sites anzeigen';}return false;">&#9654; <?php echo $site_count; ?> Sites anzeigen</a>
                                    <ul class="brk-ds-source-list" style="display:none;">
                                    <?php
                                } else {
                                    echo '<ul class="brk-ds-source-list">';
                                }

                                foreach ( $unique_sites as $site_entry ) :
                                    $admin_url = get_admin_url( $site_entry['blog_id'], 'tools.php?page=brk-datenschutz' );
                                ?>
                                    <li>
                                        <a href="<?php echo esc_url( $admin_url ); ?>"><strong><?php echo esc_html( $site_entry['name'] ); ?></strong></a>
                                        <?php
                                        // Alle Quellen ausgeben
                                        $source_items = [];
                                        foreach ( $site_entry['sources'] as $src ) {
                                            $s_label   = is_array( $src ) ? $src['label']   : $src;
                                            $s_post_id = is_array( $src ) ? ( $src['post_id'] ?? 0 ) : 0;
                                            $fingerprint = $s_label;
                                            if ( isset( $source_items[ $fingerprint ] ) ) continue;
                                            if ( $s_post_id ) {
                                                $edit_url = get_admin_url( $site_entry['blog_id'], 'post.php?post=' . $s_post_id . '&action=edit' );
                                                $source_items[ $fingerprint ] = '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $s_label ) . '</a>';
                                            } else {
                                                $source_items[ $fingerprint ] = esc_html( $s_label );
                                            }
                                        }
                                        if ( $source_items ) {
                                            echo '<ul class="brk-ds-source-list" style="margin-left:12px;">';
                                            foreach ( $source_items as $item ) {
                                                echo '<li>' . $item . '</li>';
                                            }
                                            echo '</ul>';
                                        }
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <?php if ( $clause && ( ! empty( $clause['url'] ) || ! empty( $clause['text'] ) ) ) : ?>
                                    <span class="brk-ds-has-clause" title="Klausel hinterlegt">&#10003;</span>
                                <?php else : ?>
                                    <span class="brk-ds-no-clause">&#10007;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

            <h2 style="margin-top:40px;">Uebersicht pro Site</h2>
            <?php foreach ( $all_data as $blog_id => $site_info ) : ?>
                <div class="brk-ds-category">
                    <h3>
                        <?php echo esc_html( $site_info['name'] ); ?>
                        <small style="font-weight:normal;color:#666;">(<?php echo esc_html( $site_info['url'] ); ?>)</small>
                    </h3>
                    <?php if ( empty( $site_info['services'] ) ) : ?>
                        <p class="description">Keine Dienste erkannt.</p>
                    <?php else : ?>
                        <ul class="brk-ds-service-chips">
                            <?php foreach ( $site_info['services'] as $svc ) : ?>
                                <li class="brk-ds-chip"><?php echo esc_html( $svc['name'] ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /* ----------------------------------------------------------
     * Klauseln-Verwaltung
     * ---------------------------------------------------------- */

    public static function render_clauses_page(): void {
        $clauses = BRK_Datenschutz::get_clauses();

        // Alle bekannten Dienste ueber alle Sites sammeln
        $sites     = get_sites( [ 'number' => 0 ] );
        $all_services = [];

        foreach ( $sites as $site ) {
            $result = BRK_Datenschutz::get_scan_results( (int) $site->blog_id );
            foreach ( $result['services'] ?? [] as $svc ) {
                $key = sanitize_title( $svc['name'] );
                if ( ! isset( $all_services[ $key ] ) ) {
                    $all_services[ $key ] = [
                        'name'     => $svc['name'],
                        'category' => $svc['category'],
                    ];
                }
            }
        }

        ksort( $all_services );

        ?>
        <div class="wrap brk-ds-wrap">
            <h1>Datenschutzklauseln verwalten</h1>

            <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?>
                <div class="notice notice-success"><p>Klauseln wurden gespeichert.</p></div>
            <?php endif; ?>

            <?php if ( empty( $all_services ) ) : ?>
                <div class="notice notice-info"><p>Noch keine Dienste erkannt. Bitte zuerst einen Scan durchfuehren.</p></div>
            <?php else : ?>

                <p>Fuer jeden erkannten Dienst kann eine Datenschutzklausel (Freitext) und/oder ein Link
                   zu einem Datenschutzhinweis hinterlegt werden. Site-Admins sehen diese Informationen
                   auf ihrer Datenschutz-Scan-Seite.</p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'brk_ds_save_clauses' ); ?>
                    <input type="hidden" name="action" value="brk_ds_save_clauses">

                    <table class="widefat brk-ds-table brk-ds-clauses-table">
                        <thead>
                            <tr>
                                <th style="width:200px;">Dienst</th>
                                <th style="width:120px;">Kategorie</th>
                                <th>Datenschutz-URL</th>
                                <th>Klausel / Hinweistext</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $all_services as $key => $svc ) :
                                $clause = $clauses[ $key ] ?? [];
                                $c_url  = $clause['url']  ?? '';
                                $c_text = $clause['text'] ?? '';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $svc['name'] ); ?></strong></td>
                                <td><?php echo esc_html( $svc['category'] ); ?></td>
                                <td>
                                    <input type="url"
                                           name="clauses[<?php echo esc_attr( $key ); ?>][url]"
                                           value="<?php echo esc_attr( $c_url ); ?>"
                                           class="regular-text"
                                           placeholder="https://example.com/datenschutz">
                                </td>
                                <td>
                                    <textarea name="clauses[<?php echo esc_attr( $key ); ?>][text]"
                                              rows="2"
                                              class="large-text"
                                              placeholder="Freitext-Klausel..."><?php echo esc_textarea( $c_text ); ?></textarea>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Klauseln speichern</button>
                    </p>
                </form>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Klauseln speichern (POST-Handler)
     */
    public static function handle_save_clauses(): void {
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        check_admin_referer( 'brk_ds_save_clauses' );

        $raw = $_POST['clauses'] ?? [];
        $clauses = [];

        foreach ( $raw as $key => $data ) {
            $key = sanitize_key( $key );
            $url  = isset( $data['url'] )  ? esc_url_raw( $data['url'] )  : '';
            $text = isset( $data['text'] ) ? sanitize_textarea_field( $data['text'] ) : '';

            if ( '' !== $url || '' !== $text ) {
                $clauses[ $key ] = [
                    'url'  => $url,
                    'text' => $text,
                ];
            }
        }

        BRK_Datenschutz::save_clauses( $clauses );

        wp_safe_redirect( network_admin_url( 'admin.php?page=brk-datenschutz-clauses&saved=1' ) );
        exit;
    }

    /**
     * Alle Sites neu scannen (POST-Handler)
     */
    public static function handle_network_rescan(): void {
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        check_admin_referer( 'brk_ds_network_rescan' );

        $sites = get_sites( [ 'number' => 0 ] );
        foreach ( $sites as $site ) {
            BRK_Datenschutz::flush_scan_cache( (int) $site->blog_id );
            BRK_Datenschutz::get_scan_results( (int) $site->blog_id, true );
        }

        wp_safe_redirect( network_admin_url( 'admin.php?page=brk-datenschutz&rescanned=1' ) );
        exit;
    }

    /* ----------------------------------------------------------
     * Einstellungen: Site-Seiten ein-/ausschalten
     * ---------------------------------------------------------- */

    public static function render_settings_page(): void {
        $enabled = (bool) get_site_option( 'brk_ds_site_pages_enabled', '1' );

        ?>
        <div class="wrap brk-ds-wrap">
            <h1>Datenschutz-Scanner Einstellungen</h1>

            <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?>
                <div class="notice notice-success"><p>Einstellungen gespeichert.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'brk_ds_save_settings' ); ?>
                <input type="hidden" name="action" value="brk_ds_save_settings">

                <table class="form-table">
                    <tr>
                        <th scope="row">Site-Backend-Seiten</th>
                        <td>
                            <label>
                                <input type="checkbox" name="site_pages_enabled" value="1" <?php checked( $enabled ); ?>>
                                Seite &laquo;Datenschutz-Scan&raquo; im Werkzeuge-Menue der Sites anzeigen
                            </label>
                            <p class="description">Wenn deaktiviert, sehen Site-Admins die Datenschutz-Scan-Seite nicht mehr.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Einstellungen speichern</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Einstellungen speichern (POST-Handler)
     */
    public static function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        check_admin_referer( 'brk_ds_save_settings' );

        $enabled = ! empty( $_POST['site_pages_enabled'] ) ? '1' : '0';
        update_site_option( 'brk_ds_site_pages_enabled', $enabled );

        wp_safe_redirect( network_admin_url( 'admin.php?page=brk-datenschutz-settings&saved=1' ) );
        exit;
    }
}
