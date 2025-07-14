<?php
/**
 * Plugin Name:       WP BINGO
 * Plugin URI:        https://example.com/
 * Description:       A BINGO game for WordPress meetups to help attendees connect.
 * Version:           5.2.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-bingo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WP_BINGO_VERSION', '5.2.0' );

/**
 * The core plugin activation function.
 * This runs when the plugin is activated and on version updates.
 */
function wp_bingo_activate() {
    global $wpdb;
    // CORRECTED: The table name should not have 'wp_' hardcoded.
    $table_name = $wpdb->prefix . 'bingo_players';
    $charset_collate = $wpdb->get_charset_collate();

    // The correct schema for the table.
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        player_guid varchar(36) NOT NULL,
        player_name varchar(100) NOT NULL,
        game_id mediumint(9) NOT NULL,
        card_data text NOT NULL,
        is_winner tinyint(1) NOT NULL DEFAULT 0,
        win_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY player_game (player_guid, game_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Set default options if they don't exist
    add_option( 'wp_bingo_current_game_id', 1 );
    add_option( 'wp_bingo_timer_end_time', 0 );
    update_option( 'wp_bingo_version', WP_BINGO_VERSION );
}
register_activation_hook( __FILE__, 'wp_bingo_activate' );

/**
 * Checks plugin version on every load and runs the updater if needed.
 * This ensures database changes are applied even if the user just updates files.
 */
function wp_bingo_update_check() {
    if ( get_option( 'wp_bingo_version' ) != WP_BINGO_VERSION ) {
        wp_bingo_activate();
    }
}
add_action( 'plugins_loaded', 'wp_bingo_update_check' );


// ===== Admin Menu & Dashboard =====
function wp_bingo_admin_menu() {
    add_menu_page(
        'BINGO Dashboard', 'BINGO', 'manage_options', 'wp-bingo-results',
        'wp_bingo_results_page_html', 'dashicons-screenoptions', 20
    );
}
add_action( 'admin_menu', 'wp_bingo_admin_menu' );

function wp_bingo_results_page_html() {
    require_once plugin_dir_path( __FILE__ ) . 'admin/dashboard-view.php';
}


// ===== Enqueue Scripts & Styles =====
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
    wp_enqueue_style( 'wp-bingo-admin-style', plugin_dir_url( __FILE__ ) . 'assets/wp-bingo-admin.css', array(), WP_BINGO_VERSION );
    wp_enqueue_script( 'wp-bingo-admin-script', plugin_dir_url( __FILE__ ) . 'assets/wp-bingo-admin.js', array( 'jquery' ), WP_BINGO_VERSION, true );
     wp_localize_script( 'wp-bingo-admin-script', 'wp_bingo_admin_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wp-bingo-admin-nonce' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'wp_bingo_admin_enqueue_scripts' );


// ===== BINGO Shortcode =====
function wp_bingo_shortcode() {
    $bingo_items = array(
        'Just started using WordPress this year', 'Prefers the Block Editor', 'Has customized a theme using code', 'Has joined a WordPress group or forum',
        'Has used a page builder like Elementor', 'Knows how to install a plugin', 'Has set up an online store using WP', 'Learned WP from YouTube or blogs',
        'Has uploaded and optimized images', 'Uses WordPress for blogging', 'Has tried Full Site Editing (FSE)', 'Helped someone build a WP site',
        'Has attended a WordPress meetup or WordCamp', 'Can explain what a "hook" is', 'Knows what a shortcode is', 'Has used WP for over 5 years'
    );

    ob_start();
    ?>
    <div id="wp-bingo-app">
        <div id="player-entry-screen">
            <div class="player-entry-box">
                <h2>WP BINGO!</h2>
                <p>Please enter your name to start playing.</p>
                <input type="text" id="player-name-input" placeholder="Your Name" maxlength="50">
                <button id="start-game-button" class="bingo-button">Let's Play!</button>
                <div id="player-entry-message" class="bingo-message" style="display:none;"></div>
            </div>
        </div>
        <div id="wp-bingo-board-container" style="display:none;">
            <div class="wp-bingo-header">
				<div id="logo"><img src="https://wp-bingo.instawp.xyz/wp-content/uploads/2025/07/331027450_753203329301541_251763577924949351_n-Photoroom.png" width="170" height="auto"></div>
				<h1>B.I.N.G.O</h1>
				<h4>
					Build Connections |  Interact Naturally |  Nurture |  Grow Your Network |  Opensource
				</h4>
                <p><strong>Instructions:</strong> Find someone who fits a description, type their name in the box, and move to the next one. No repeated names!"</p>
            </div>
            <div id="wp-bingo-board">
                <?php foreach ( $bingo_items as $index => $item ) : ?>
                    <div class="bingo-square" data-index="<?php echo $index; ?>">
                        <label class="bingo-square-content" for="bingo-input-<?php echo $index; ?>"><?php echo esc_html( $item ); ?></label>
                        <input type="text" id="bingo-input-<?php echo $index; ?>" class="bingo-name-input" placeholder="Enter name...">
                    </div>
                <?php endforeach; ?>
			</div><br>
			<div id="bingo-timer-wrapper">
                <div id="bingo-timer">--:--</div>
            </div>
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
function wp_bingo_generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

function wp_bingo_player_init_callback() {
    check_ajax_referer( 'wp-bingo-nonce', 'nonce' );

    $player_guid = isset($_POST['player_guid']) ? sanitize_key($_POST['player_guid']) : '';
    $player_name = isset($_POST['player_name']) ? sanitize_text_field($_POST['player_name']) : '';
    $game_id = get_option( 'wp_bingo_current_game_id', 1 );
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'bingo_players';

    if (empty($player_guid)) {
        if (empty($player_name)) {
            wp_send_json_error(['message' => 'Player name cannot be empty.']);
            return;
        }
        $player_guid = wp_bingo_generate_uuid();
        $wpdb->replace(
            $table_name,
            ['player_guid' => $player_guid, 'player_name' => $player_name, 'game_id' => $game_id, 'card_data' => '{}'],
            ['%s', '%s', '%d', '%s']
        );
        wp_send_json_success(['player_guid' => $player_guid, 'player_name' => $player_name, 'card_data' => []]);
    } else {
        $player = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE player_guid = %s AND game_id = %d", $player_guid, $game_id ) );
        if ($player) {
            wp_send_json_success(['player_guid' => $player->player_guid, 'player_name' => $player->player_name, 'card_data' => json_decode($player->card_data, true), 'is_winner' => (bool)$player->is_winner]);
        } else {
            $existing_player_name = $wpdb->get_var( $wpdb->prepare( "SELECT player_name FROM $table_name WHERE player_guid = %s LIMIT 1", $player_guid ) );
            if (!$existing_player_name && !empty($_POST['player_name'])) {
                $existing_player_name = sanitize_text_field($_POST['player_name']);
            }
            $wpdb->replace(
                $table_name,
                ['player_guid' => $player_guid, 'player_name' => $existing_player_name, 'game_id' => $game_id, 'card_data' => '{}'],
                ['%s', '%s', '%d', '%s']
            );
            wp_send_json_success(['player_guid' => $player_guid, 'player_name' => $existing_player_name, 'card_data' => [], 'is_winner' => false]);
        }
    }
}
add_action( 'wp_ajax_nopriv_wp_bingo_player_init', 'wp_bingo_player_init_callback' );
add_action( 'wp_ajax_wp_bingo_player_init', 'wp_bingo_player_init_callback' );

function wp_bingo_save_state_callback() {
    check_ajax_referer( 'wp-bingo-nonce', 'nonce' );
    if ( ! isset( $_POST['card_data'] ) || empty($_POST['player_guid']) || empty($_POST['player_name']) ) {
        wp_send_json_error( ['message' => 'Invalid request. Missing player data.'] );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'bingo_players';
    $player_guid = sanitize_key($_POST['player_guid']);
    $player_name = sanitize_text_field($_POST['player_name']);
    $game_id = get_option( 'wp_bingo_current_game_id', 1 );
    
    $card_data_raw = wp_unslash( $_POST['card_data'] );
    $card_data_sanitized = array();
    if (is_array($card_data_raw)) {
        foreach ($card_data_raw as $key => $value) {
            $card_data_sanitized[intval($key)] = sanitize_text_field($value);
        }
    }
    $card_data_json = json_encode( $card_data_sanitized );

    // Use REPLACE to either INSERT a new row or UPDATE an existing one based on the UNIQUE key (player_guid, game_id).
    // This is the key fix for the race condition.
    $result = $wpdb->replace(
        $table_name,
        array(
            'player_guid' => $player_guid,
            'game_id'     => $game_id,
            'player_name' => $player_name,
            'card_data'   => $card_data_json
        ),
        array('%s', '%d', '%s', '%s')
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Database error. Could not save card.']);
    } else {
        wp_send_json_success( 'State saved.' );
    }
}
add_action( 'wp_ajax_nopriv_wp_bingo_save_state', 'wp_bingo_save_state_callback' );
add_action( 'wp_ajax_wp_bingo_save_state', 'wp_bingo_save_state_callback' );

function wp_bingo_check_win_callback() {
    check_ajax_referer( 'wp-bingo-nonce', 'nonce' );
    if ( empty( $_POST['player_guid'] ) ) wp_send_json_error( 'Invalid request.' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'bingo_players';
    $player_guid = sanitize_key($_POST['player_guid']);
    $game_id = get_option( 'wp_bingo_current_game_id', 1 );

    $card_state_json = $wpdb->get_var( $wpdb->prepare( "SELECT card_data FROM $table_name WHERE player_guid = %s AND game_id = %d", $player_guid, $game_id ) );
    if ( $card_state_json === null ) {
        wp_send_json_error( ['message' => 'No card data found. Please try entering a name first.'] );
        return;
    }
    
    $card_state = json_decode( $card_state_json, true );
    $winning_combos = [ [0, 1, 2, 3], [4, 5, 6, 7], [8, 9, 10, 11], [12, 13, 14, 15], [0, 4, 8, 12], [1, 5, 9, 13], [2, 6, 10, 14], [3, 7, 11, 15], [0, 5, 10, 15], [3, 6, 9, 12] ];

    $is_a_winner = false;
    foreach ($winning_combos as $combo) {
        $is_a_win = true;
        foreach ($combo as $index) {
            if ( empty($card_state[$index]) ) { $is_a_win = false; break; }
        }
        if ($is_a_win) { $is_a_winner = true; break; }
    }

    if ($is_a_winner) {
        $wpdb->update(
            $table_name,
            array( 'is_winner' => 1, 'win_time' => current_time( 'mysql' ) ),
            array( 'player_guid' => $player_guid, 'game_id' => $game_id ),
            array( '%d', '%s' ), array( '%s', '%d' )
        );
        wp_send_json_success( 'Thanks for submitting! Wait for the announcement of the winner' );
    } else {
        wp_send_json_error( 'Not a BINGO yet. Keep trying!' );
    }
}
add_action( 'wp_ajax_nopriv_wp_bingo_check_win', 'wp_bingo_check_win_callback' );
add_action( 'wp_ajax_wp_bingo_check_win', 'wp_bingo_check_win_callback' );

function wp_bingo_manage_game_callback() {
    check_ajax_referer( 'wp-bingo-admin-nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );

    $action = isset($_POST['game_action']) ? sanitize_key($_POST['game_action']) : '';

    switch ($action) {
        case 'start_timer':
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
            if ($duration > 0) {
                $end_time = time() + ($duration * 60);
                update_option('wp_bingo_timer_end_time', $end_time);
                wp_send_json_success(['message' => "Timer started for {$duration} minutes."]);
            } else {
                wp_send_json_error('Invalid duration.');
            }
            break;

        case 'start_new_game':
            $new_game_id = get_option('wp_bingo_current_game_id', 1) + 1;
            update_option('wp_bingo_current_game_id', $new_game_id);
            update_option('wp_bingo_timer_end_time', 0);
            wp_send_json_success(['message' => "Game #{$new_game_id} has started. All previous data is archived."]);
            break;
    }
    wp_send_json_error('Invalid action.');
}
add_action( 'wp_ajax_wp_bingo_manage_game', 'wp_bingo_manage_game_callback' );
