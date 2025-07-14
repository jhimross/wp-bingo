<?php
/**
 * Plugin Name:       WP BINGO
 * Plugin URI:        https://example.com/
 * Description:       A BINGO game for WordPress meetups to help attendees connect.
 * Version:           6.1.1
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-bingo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WP_BINGO_VERSION', '6.1.1' );

// ===== Plugin Activation & Update =====
function wp_bingo_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bingo_players';
    $charset_collate = $wpdb->get_charset_collate();

    // Added card_layout column to store the player's specific card
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        player_guid varchar(36) NOT NULL,
        player_name varchar(100) NOT NULL,
        game_id mediumint(9) NOT NULL,
        card_data text NOT NULL,
        card_layout text NOT NULL DEFAULT '',
        is_winner tinyint(1) NOT NULL DEFAULT 0,
        win_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY player_game (player_guid, game_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    $default_items = "Just started using WordPress this year\nPrefers the Block Editor\nHas customized a theme using code\nHas joined a WordPress group or forum\nHas used a page builder like Elementor\nKnows how to install a plugin\nHas set up an online store using WP\nLearned WP from YouTube or blogs\nHas uploaded and optimized images\nUses WordPress for blogging\nHas tried Full Site Editing (FSE)\nHelped someone build a WP site\nHas attended a WordPress meetup or WordCamp\nCan explain what a \"hook\" is\nKnows what a shortcode is\nHas used WP for over 5 years\nContributed to a plugin or theme\nKnows what REST API means\nHas built a block pattern\nCan name 3 page builders\nHas used WP-CLI\nPrefers a classic theme\nHas spoken at a meetup\nHas fixed the White Screen of Death\nFollows a WP news site";

    add_option( 'wp_bingo_items', $default_items );
    add_option( 'wp_bingo_grid_size', '4x4' );
    add_option( 'wp_bingo_logo_url', '' );
    add_option( 'wp_bingo_current_game_id', 1 );
    add_option( 'wp_bingo_timer_end_time', 0 );
    update_option( 'wp_bingo_version', WP_BINGO_VERSION );
}
register_activation_hook( __FILE__, 'wp_bingo_activate' );

function wp_bingo_update_check() {
    if ( get_option( 'wp_bingo_version' ) != WP_BINGO_VERSION ) {
        wp_bingo_activate();
    }
}
add_action( 'plugins_loaded', 'wp_bingo_update_check' );


// ===== Admin & Enqueues =====
function wp_bingo_admin_menu() {
    add_menu_page( 'BINGO Dashboard', 'BINGO', 'manage_options', 'wp-bingo-results', 'wp_bingo_results_page_html', 'dashicons-screenoptions', 20 );
}
add_action( 'admin_menu', 'wp_bingo_admin_menu' );

function wp_bingo_results_page_html() {
    require_once plugin_dir_path( __FILE__ ) . 'admin/dashboard-view.php';
}

function wp_bingo_enqueue_scripts() {
    if ( is_singular() && has_shortcode( get_post()->post_content, 'wp_bingo_game' ) ) {
        wp_enqueue_style( 'wp-bingo-style', plugin_dir_url( __FILE__ ) . 'assets/wp-bingo.css', array(), WP_BINGO_VERSION );
        wp_enqueue_script( 'wp-bingo-script', plugin_dir_url( __FILE__ ) . 'assets/wp-bingo.js', array( 'jquery' ), WP_BINGO_VERSION, true );
        wp_localize_script( 'wp-bingo-script', 'wp_bingo_obj', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'wp-bingo-nonce' ),
            'timer_end'     => get_option( 'wp_bingo_timer_end_time', 0 ),
            'current_time'  => current_time( 'timestamp' ),
            'game_id'       => get_option( 'wp_bingo_current_game_id', 1 )
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'wp_bingo_enqueue_scripts' );

function wp_bingo_admin_enqueue_scripts($hook) {
    if ($hook != 'toplevel_page_wp-bingo-results') return;
    
    wp_enqueue_media();

    wp_enqueue_style( 'wp-bingo-admin-style', plugin_dir_url( __FILE__ ) . 'assets/wp-bingo-admin.css', array(), WP_BINGO_VERSION );
    wp_enqueue_script( 'wp-bingo-admin-script', plugin_dir_url( __FILE__ ) . 'assets/wp-bingo-admin.js', array( 'jquery' ), WP_BINGO_VERSION, true );
    wp_localize_script( 'wp-bingo-admin-script', 'wp_bingo_admin_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'manage_nonce' => wp_create_nonce( 'wp-bingo-manage-action' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'wp_bingo_admin_enqueue_scripts' );


// ===== BINGO Shortcode =====
function wp_bingo_shortcode() {
    $grid_size_str = get_option('wp_bingo_grid_size', '4x4');
    list($rows, $cols) = explode('x', $grid_size_str);
    $total_squares = intval($rows) * intval($cols);

    $items_str = get_option('wp_bingo_items', '');
    $all_items = array_filter(array_map('trim', explode("\n", $items_str)));
    shuffle($all_items);

    $is_free_space_grid = ($rows == 5 && $cols == 5);
    $squares_to_pick = $is_free_space_grid ? $total_squares - 1 : $total_squares;
    $bingo_items = array_slice($all_items, 0, $squares_to_pick);
    
    $logo_url = get_option('wp_bingo_logo_url', '');

    if ($is_free_space_grid) {
        $free_space_content = !empty($logo_url) ? '' : 'FREE';
        array_splice($bingo_items, 12, 0, $free_space_content);
    }

    ob_start();
    ?>
    <div id="wp-bingo-app">
        <div id="player-entry-screen">
            <div class="player-entry-box">
                <h2>Welcome to WP BINGO!</h2>
                <p>Please enter your name to start playing.</p>
                <input type="text" id="player-name-input" placeholder="Your Name" maxlength="50">
                <button id="start-game-button" class="bingo-button">Let's Play!</button>
                <div id="player-entry-message" class="bingo-message" style="display:none;"></div>
            </div>
        </div>
        <div id="wp-bingo-board-container" style="display:none;">
            <div class="wp-bingo-header"><h1>B.I.N.G.O</h1></div>
            <div id="wp-bingo-board" class="grid-<?php echo esc_attr($grid_size_str); ?>">
                <?php foreach ( $bingo_items as $index => $item ) : ?>
                    <?php
                        $is_free = ($is_free_space_grid && $index == 12);
                        $square_class = 'bingo-square';
                        $style = '';
                        if ($is_free) {
                            $square_class .= ' free-space';
                            if (!empty($logo_url)) {
                                $style = 'background-image: url(' . esc_url($logo_url) . ');';
                            }
                        }
                    ?>
                    <div class="<?php echo $square_class; ?>" data-index="<?php echo $index; ?>" style="<?php echo $style; ?>">
                        <label class="bingo-square-content" for="bingo-input-<?php echo $index; ?>"><?php echo esc_html( $item ); ?></label>
                        <input type="text" id="bingo-input-<?php echo $index; ?>" class="bingo-name-input" placeholder="Enter name..." <?php if ($is_free) echo 'disabled'; ?>>
                    </div>
                <?php endforeach; ?>
            </div><br>
            <div id="bingo-timer-wrapper"><div id="bingo-timer">--:--</div></div>
            <div class="wp-bingo-footer">
                 <button id="bingo-shout-button" class="bingo-button bingo-shout">Submit!</button>
                 <button id="reset-bingo-button" class="bingo-button bingo-reset">Reset Card</button>
            </div>
            <div id="bingo-message" class="bingo-message" style="display:none;"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'wp_bingo_game', 'wp_bingo_shortcode' );


// ===== AJAX Handlers =====
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax-handlers.php';
