<?php
/**
 * Plugin Name:  BRK Datenschutz Scanner
 * Plugin URI:   https://brk.de
 * Description:  Erkennt datenschutzrelevante Dienste pro Site und ermoeglicht die Zuordnung von Datenschutzklauseln. Fuer Single-Site und Multisite.
 * Version:      1.0.0
 * Author:       AG-IT
 * Network:      true
 * Text Domain:  brk-datenschutz
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BRK_DS_VERSION', '1.0.0' );
define( 'BRK_DS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRK_DS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Klassen laden
require_once BRK_DS_PLUGIN_DIR . 'includes/class-registry.php';
require_once BRK_DS_PLUGIN_DIR . 'includes/class-scanner.php';
require_once BRK_DS_PLUGIN_DIR . 'includes/class-site-admin.php';
require_once BRK_DS_PLUGIN_DIR . 'includes/class-network-admin.php';

/**
 * Hauptklasse des Plugins
 */
final class BRK_Datenschutz {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin-Assets laden
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Site-Admin-Seite (fuer jeden Blog-Admin)
        BRK_DS_Site_Admin::init();

        // Network-Admin-Seite (nur Multisite Super-Admin)
        if ( is_multisite() ) {
            BRK_DS_Network_Admin::init();
        } else {
            // Single-Site: Klauseln direkt in der Site-Admin verwalten
            BRK_DS_Site_Admin::init_clauses_management();
        }

        // Aktivierung
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    /**
     * Bei Aktivierung: Standardwerte setzen
     */
    public function activate(): void {
        if ( is_multisite() ) {
            if ( ! get_site_option( 'brk_ds_clauses' ) ) {
                update_site_option( 'brk_ds_clauses', [] );
            }
        } else {
            if ( ! get_option( 'brk_ds_clauses' ) ) {
                update_option( 'brk_ds_clauses', [], false );
            }
        }
    }

    /**
     * Admin-CSS und -JS laden
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Nur auf unseren eigenen Seiten laden
        if ( false === strpos( $hook, 'brk-datenschutz' ) ) {
            return;
        }
        wp_enqueue_style(
            'brk-datenschutz-admin',
            BRK_DS_PLUGIN_URL . 'assets/admin.css',
            [],
            BRK_DS_VERSION
        );
    }

    /**
     * Datenschutzklauseln laden (netzwerk- oder site-weit)
     */
    public static function get_clauses(): array {
        if ( is_multisite() ) {
            return (array) get_site_option( 'brk_ds_clauses', [] );
        }
        return (array) get_option( 'brk_ds_clauses', [] );
    }

    /**
     * Datenschutzklauseln speichern
     */
    public static function save_clauses( array $clauses ): void {
        if ( is_multisite() ) {
            update_site_option( 'brk_ds_clauses', $clauses );
        } else {
            update_option( 'brk_ds_clauses', $clauses, false );
        }
    }

    /**
     * Gecachte Scan-Ergebnisse fuer eine Site laden (oder frisch scannen)
     */
    public static function get_scan_results( int $blog_id = 0, bool $force_rescan = false ): array {
        if ( ! $blog_id ) {
            $blog_id = get_current_blog_id();
        }

        $cache_key = 'brk_ds_scan_' . $blog_id;

        if ( is_multisite() ) {
            switch_to_blog( $blog_id );
        }

        $cached = get_transient( $cache_key );
        if ( ! $force_rescan && false !== $cached ) {
            if ( is_multisite() ) {
                restore_current_blog();
            }
            return $cached;
        }

        // Frisch scannen
        $scanner = new BRK_DS_Scanner();
        $result  = $scanner->scan_current_site();

        // 6 Stunden cachen
        set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );

        if ( is_multisite() ) {
            restore_current_blog();
        }

        return $result;
    }

    /**
     * Cache invalidieren
     */
    public static function flush_scan_cache( int $blog_id = 0 ): void {
        if ( ! $blog_id ) {
            $blog_id = get_current_blog_id();
        }
        if ( is_multisite() ) {
            switch_to_blog( $blog_id );
        }
        delete_transient( 'brk_ds_scan_' . $blog_id );
        if ( is_multisite() ) {
            restore_current_blog();
        }
    }
}

// Plugin initialisieren
add_action( 'plugins_loaded', [ 'BRK_Datenschutz', 'instance' ] );
