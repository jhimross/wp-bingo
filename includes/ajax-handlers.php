<?php
/**
 * All AJAX handlers for the WP BINGO plugin.
 * Version 6.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// ===== Frontend AJAX Handlers =====

add_action( 'wp_ajax_nopriv_wp_bingo_player_init', 'wp_bingo_player_init_callback' );
add_action( 'wp_ajax_wp_bingo_player_init', 'wp_bingo_player_init_callback' );
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
        }
        $player_guid = wp_generate_uuid4();
        $wpdb->replace(
            $table_name,
            ['player_guid' => $player_guid, 'player_name' => $player_name, 'game_id' => $game_id, 'card_data' => '{}', 'card_layout' => ''],
            ['%s', '%s', '%d', '%s', '%s']
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
                ['player_guid' => $player_guid, 'player_name' => $existing_player_name, 'game_id' => $game_id, 'card_data' => '{}', 'card_layout' => ''],
                ['%s', '%s', '%d', '%s', '%s']
            );
            wp_send_json_success(['player_guid' => $player_guid, 'player_name' => $existing_player_name, 'card_data' => [], 'is_winner' => false]);
        }
    }
}

add_action( 'wp_ajax_nopriv_wp_bingo_save_state', 'wp_bingo_save_state_callback' );
add_action( 'wp_ajax_wp_bingo_save_state', 'wp_bingo_save_state_callback' );
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

    // Get the existing record to preserve the card layout
    $player_record = $wpdb->get_row($wpdb->prepare("SELECT card_layout FROM $table_name WHERE player_guid = %s AND game_id = %d", $player_guid, $game_id));

    $data_to_save = [
        'player_guid' => $player_guid,
        'game_id'     => $game_id,
        'player_name' => $player_name,
        'card_data'   => $card_data_json,
        'card_layout' => !empty($player_record->card_layout) ? $player_record->card_layout : ''
    ];

    // If a new layout was passed from JS and the DB record doesn't have one yet, save it.
    if (empty($data_to_save['card_layout']) && isset($_POST['card_layout'])) {
        $card_layout_json = wp_unslash($_POST['card_layout']);
        $decoded_layout = json_decode($card_layout_json);
        if (is_array($decoded_layout)) {
             $data_to_save['card_layout'] = $card_layout_json;
        }
    }

    $result = $wpdb->replace($table_name, $data_to_save);

    if ($result === false) {
        wp_send_json_error(['message' => 'Database error. Could not save card.']);
    } else {
        wp_send_json_success( 'State saved.' );
    }
}

add_action( 'wp_ajax_nopriv_wp_bingo_check_win', 'wp_bingo_check_win_callback' );
add_action( 'wp_ajax_wp_bingo_check_win', 'wp_bingo_check_win_callback' );
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
    }
    
    $card_state = json_decode( $card_state_json, true );
    $grid_size_str = get_option('wp_bingo_grid_size', '4x4');
    list($rows, $cols) = explode('x', $grid_size_str);
    $rows = intval($rows); $cols = intval($cols);
    $winning_combos = [];

    for ($r = 0; $r < $rows; $r++) { $combo = []; for ($c = 0; $c < $cols; $c++) { $combo[] = ($r * $cols) + $c; } $winning_combos[] = $combo; }
    for ($c = 0; $c < $cols; $c++) { $combo = []; for ($r = 0; $r < $rows; $r++) { $combo[] = ($r * $cols) + $c; } $winning_combos[] = $combo; }
    if ($rows == $cols) {
        $diag1 = []; $diag2 = [];
        for ($i = 0; $i < $rows; $i++) { $diag1[] = ($i * $cols) + $i; $diag2[] = ($i * $cols) + ($cols - 1 - $i); }
        $winning_combos[] = $diag1; $winning_combos[] = $diag2;
    }

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
        wp_send_json_success( 'Bingo Card Submitted' );
    } else {
        wp_send_json_error( 'Not a BINGO yet. Keep trying!' );
    }
}

// ===== Admin AJAX Handlers =====

add_action( 'wp_ajax_wp_bingo_save_settings', 'wp_bingo_save_settings_callback' );
function wp_bingo_save_settings_callback() {
    check_ajax_referer( 'wp_bingo_save_settings_action', 'wp_bingo_settings_nonce_field' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ['message' => 'Permission denied.'] );
    }

    $items = isset($_POST['items']) ? wp_kses_post( wp_unslash( $_POST['items'] ) ) : '';
    $grid_size = isset($_POST['grid_size']) ? sanitize_text_field( $_POST['grid_size'] ) : '4x4';
    $logo_url = isset($_POST['logo_url']) ? esc_url_raw($_POST['logo_url']) : '';
    
    if (!in_array($grid_size, ['3x3', '4x4', '5x5'])) {
        $grid_size = '4x4';
    }

    update_option('wp_bingo_items', $items);
    update_option('wp_bingo_grid_size', $grid_size);
    update_option('wp_bingo_logo_url', $logo_url);

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}

add_action( 'wp_ajax_wp_bingo_manage_game', 'wp_bingo_manage_game_callback' );
function wp_bingo_manage_game_callback() {
    check_ajax_referer( 'wp-bingo-manage-action', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ['message' => 'Permission denied.'] );
    }

    $action = isset($_POST['game_action']) ? sanitize_key($_POST['game_action']) : '';

    switch ($action) {
        case 'start_timer':
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
            if ($duration > 0) {
                $end_time = time() + ($duration * 60);
                update_option('wp_bingo_timer_end_time', $end_time);
                wp_send_json_success(['message' => "Timer started for {$duration} minutes."]);
            } else {
                wp_send_json_error(['message' => 'Invalid duration.']);
            }
            break;

        case 'start_new_game':
            $new_game_id = get_option('wp_bingo_current_game_id', 1) + 1;
            update_option('wp_bingo_current_game_id', $new_game_id);
            update_option('wp_bingo_timer_end_time', 0);
            wp_send_json_success(['message' => "Game #{$new_game_id} has started. All previous data is archived."]);
            break;
        
        case 'reset_all_data':
            global $wpdb;
            $table_name = $wpdb->prefix . 'bingo_players';
            $wpdb->query("TRUNCATE TABLE $table_name");

            delete_option('wp_bingo_current_game_id');
            delete_option('wp_bingo_timer_end_time');
            delete_option('wp_bingo_items');
            delete_option('wp_bingo_grid_size');
            delete_option('wp_bingo_version');
            delete_option('wp_bingo_logo_url');

            // Re-run the activation function to set defaults
            wp_bingo_activate();

            wp_send_json_success(['message' => 'All plugin data has been reset to defaults.']);
            break;

        default:
            wp_send_json_error(['message' => 'Invalid action specified.']);
            break;
    }
}
