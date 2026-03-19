<?php
/**
 * BRK Datenschutz – Site-Admin-Seite
 *
 * Zeigt Seiten-Admins alle erkannten datenschutzrelevanten Dienste
 * und externen URLs der aktuellen Site.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BRK_DS_Site_Admin {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_brk_ds_rescan', [ __CLASS__, 'handle_rescan' ] );
    }

    /**
     * Klauselverwaltung fuer Single-Site (kein Network-Admin vorhanden)
     */
    public static function init_clauses_management(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_clauses_menu' ] );
        add_action( 'admin_post_brk_ds_save_clauses_single', [ __CLASS__, 'handle_save_clauses_single' ] );
    }

    public static function add_clauses_menu(): void {
        add_management_page(
            'Datenschutzklauseln',
            'DS-Klauseln',
            'manage_options',
            'brk-datenschutz-clauses',
            [ __CLASS__, 'render_clauses_page' ]
        );
    }

    /**
     * Menue-Eintrag im Site-Admin (nur wenn vom Super-Admin aktiviert)
     */
    public static function add_menu(): void {
        if ( ! BRK_Datenschutz::is_site_page_enabled() ) {
            return;
        }
        add_management_page(
            'Datenschutz-Dienste',
            'Datenschutz-Scan',
            'manage_options',
            'brk-datenschutz',
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Rescan-Handler (POST)
     */
    public static function handle_rescan(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        check_admin_referer( 'brk_ds_rescan' );

        BRK_Datenschutz::flush_scan_cache();
        BRK_Datenschutz::get_scan_results( 0, true );

        wp_safe_redirect( admin_url( 'tools.php?page=brk-datenschutz&rescanned=1' ) );
        exit;
    }

    /**
     * Admin-Seite rendern
     */
    public static function render_page(): void {
        $results  = BRK_Datenschutz::get_scan_results();
        $clauses  = BRK_Datenschutz::get_clauses();
        $services = $results['services'] ?? [];
        $ext_urls = $results['external_urls'] ?? [];

        // Nach Kategorie gruppieren
        $by_category = [];
        foreach ( $services as $svc ) {
            $cat = $svc['category'] ?? 'Sonstige';
            $by_category[ $cat ][] = $svc;
        }
        ksort( $by_category );

        ?>
        <div class="wrap brk-ds-wrap">
            <h1>Datenschutz-relevante Dienste</h1>

            <?php if ( isset( $_GET['rescanned'] ) && $_GET['rescanned'] === '1' ) : ?>
                <div class="notice notice-success"><p>Scan wurde erfolgreich aktualisiert.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
                <?php wp_nonce_field( 'brk_ds_rescan' ); ?>
                <input type="hidden" name="action" value="brk_ds_rescan">
                <button type="submit" class="button button-secondary">Erneut scannen</button>
                <span class="description" style="margin-left:10px;">Ergebnisse werden 6 Stunden gecacht.</span>
            </form>

            <?php if ( empty( $services ) ) : ?>
                <div class="notice notice-info"><p>Es wurden keine bekannten datenschutzrelevanten Dienste erkannt.</p></div>
            <?php else : ?>

                <p>Insgesamt <strong><?php echo count( $services ); ?></strong> Dienste in
                   <strong><?php echo count( $by_category ); ?></strong> Kategorien erkannt.</p>

                <?php foreach ( $by_category as $cat => $cat_services ) : ?>
                    <div class="brk-ds-category">
                        <h2><?php echo esc_html( $cat ); ?> <span class="brk-ds-badge"><?php echo count( $cat_services ); ?></span></h2>
                        <table class="widefat striped brk-ds-table">
                            <thead>
                                <tr>
                                    <th>Dienst</th>
                                    <th>Quellen</th>
                                    <th>Datenschutzklausel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $cat_services as $svc ) :
                                    $clause_key = sanitize_title( $svc['name'] );
                                    $clause     = $clauses[ $clause_key ] ?? null;
                                ?>
                                <tr>
                                    <td class="brk-ds-service-name">
                                        <strong><?php echo esc_html( $svc['name'] ); ?></strong>
                                    </td>
                                    <td>
                                        <ul class="brk-ds-source-list">
                                            <?php foreach ( $svc['sources'] as $src ) :
                                                $label   = is_array( $src ) ? $src['label'] : $src;
                                                $post_id = is_array( $src ) ? ( $src['post_id'] ?? 0 ) : 0;
                                            ?>
                                                <li>
                                                    <?php if ( $post_id ) : ?>
                                                        <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
                                                    <?php else : ?>
                                                        <?php echo esc_html( $label ); ?>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>
                                        <?php if ( $clause ) : ?>
                                            <?php if ( ! empty( $clause['url'] ) ) : ?>
                                                <a href="<?php echo esc_url( $clause['url'] ); ?>" target="_blank" rel="noopener">Datenschutzhinweis</a>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $clause['text'] ) ) : ?>
                                                <p class="brk-ds-clause-text"><?php echo esc_html( $clause['text'] ); ?></p>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="brk-ds-no-clause">Nicht hinterlegt</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>

            <?php if ( ! empty( $ext_urls ) ) : ?>
                <h2 style="margin-top:30px;">Externe Domains (<?php echo count( $ext_urls ); ?>)</h2>
                <p class="description">Alle im Content, in Widgets und Theme-Optionen gefundenen externen Domains.</p>
                <table class="widefat striped brk-ds-table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Beispiel-URLs</th>
                            <th>Quellen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $ext_urls as $domain => $data ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $domain ); ?></strong></td>
                            <td>
                                <ul class="brk-ds-source-list">
                                    <?php foreach ( array_slice( $data['urls'], 0, 5 ) as $u ) : ?>
                                        <li><code><?php echo esc_html( $u ); ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <ul class="brk-ds-source-list">
                                    <?php foreach ( $data['sources'] as $src ) : ?>
                                        <li><?php echo esc_html( $src ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ----------------------------------------------------------
     * Klauselverwaltung (Single-Site)
     * ---------------------------------------------------------- */

    public static function render_clauses_page(): void {
        $clauses  = BRK_Datenschutz::get_clauses();
        $results  = BRK_Datenschutz::get_scan_results();
        $services = $results['services'] ?? [];

        $all_services = [];
        foreach ( $services as $svc ) {
            $key = sanitize_title( $svc['name'] );
            $all_services[ $key ] = [
                'name'     => $svc['name'],
                'category' => $svc['category'],
            ];
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
                   zu einem Datenschutzhinweis hinterlegt werden.</p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'brk_ds_save_clauses_single' ); ?>
                    <input type="hidden" name="action" value="brk_ds_save_clauses_single">

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
     * Klauseln speichern (Single-Site POST-Handler)
     */
    public static function handle_save_clauses_single(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        check_admin_referer( 'brk_ds_save_clauses_single' );

        $raw     = $_POST['clauses'] ?? [];
        $clauses = [];

        foreach ( $raw as $key => $data ) {
            $key  = sanitize_key( $key );
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

        wp_safe_redirect( admin_url( 'tools.php?page=brk-datenschutz-clauses&saved=1' ) );
        exit;
    }
}
