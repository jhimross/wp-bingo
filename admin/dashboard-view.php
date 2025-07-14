<?php
/**
 * Admin View for BINGO Dashboard (v2.0.0)
 */
if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
$player_table = $wpdb->prefix . 'bingo_players';

// Get current and total games
$current_game_id = get_option( 'wp_bingo_current_game_id', 1 );
$total_games = $wpdb->get_var( "SELECT MAX(game_id) FROM $player_table" );
if (!$total_games) $total_games = $current_game_id;

// Determine which game to display
$display_game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : $current_game_id;

// Fetch players for the selected game
$players = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $player_table WHERE game_id = %d ORDER BY is_winner DESC, win_time ASC", $display_game_id ) );

$logo_url = get_option('wp_bingo_logo_url', '');
?>
<div class="wrap wp-bingo-admin">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <p>Manage BINGO game settings, control the active game, and view results all on this page.</p>

    <!-- Game Settings -->
    <div class="bingo-admin-box">
        <h2>Game Settings</h2>
        <p>Configure the BINGO card content and size here. Settings will apply to the next game round you start.</p>
        <form id="bingo-settings-form">
            <?php wp_nonce_field( 'wp_bingo_save_settings_action', 'wp_bingo_settings_nonce_field' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="bingo-grid-size">Card Size</label></th>
                    <td>
                        <select name="grid_size" id="bingo-grid-size">
                            <option value="3x3" <?php selected(get_option('wp_bingo_grid_size'), '3x3'); ?>>3 x 3</option>
                            <option value="4x4" <?php selected(get_option('wp_bingo_grid_size'), '4x4'); ?>>4 x 4</option>
                            <option value="5x5" <?php selected(get_option('wp_bingo_grid_size'), '5x5'); ?>>5 x 5 (with FREE center)</option>
                        </select>
                        <p class="description">Select the number of rows and columns for the BINGO card.</p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label>Logo for FREE Space</label></th>
                    <td>
                        <div class="logo-uploader">
                            <img id="logo-preview" src="<?php echo esc_url($logo_url); ?>" style="<?php echo empty($logo_url) ? 'display:none;' : ''; ?>">
                            <input type="hidden" name="logo_url" id="logo_url_field" value="<?php echo esc_url($logo_url); ?>">
                            <button type="button" id="upload-logo-button" class="button">Upload Logo</button>
                            <button type="button" id="remove-logo-button" class="button" style="<?php echo empty($logo_url) ? 'display:none;' : ''; ?>">Remove Logo</button>
                        </div>
                        <p class="description">Upload a logo to appear in the center "FREE" space on 5x5 grids.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="bingo-items">BINGO Square Items</label></th>
                    <td>
                        <textarea name="items" id="bingo-items" rows="15" cols="50" class="large-text"><?php echo esc_textarea(get_option('wp_bingo_items')); ?></textarea>
                        <p class="description">Enter one BINGO item per line. The plugin will randomly select from this list. Ensure you have enough items for your chosen grid size (e.g., at least 24 for a 5x5 grid).</p>
                    </td>
                </tr>
            </table>
            <input type="submit" name="save" id="save-bingo-settings" class="button button-primary" value="Save Settings">
        </form>
        <div id="admin-message-settings" class="admin-message" style="display:none;"></div>
    </div>

    <!-- Game Controls -->
    <div class="bingo-admin-box">
        <h2>Game Controls</h2>
        <div class="controls-flex">
            <div class="control-item">
                <h3>Start New Round</h3>
                <p>This will archive the current game and start a new one. Players will get a fresh board based on the saved settings above.</p>
                <button id="start-new-game-btn" class="button button-primary">Start New Game (Currently on Game #<?php echo esc_html($current_game_id); ?>)</button>
            </div>
            <div class="control-item">
                <h3>Set Timer</h3>
                <p>Start a countdown timer for all players. The board will lock when time is up.</p>
                <div class="timer-form">
                    <input type="number" id="timer-duration" value="5" min="1" max="60">
                    <label for="timer-duration">minutes</label>
                    <button id="start-timer-btn" class="button button-secondary">Start Timer</button>
                </div>
            </div>
        </div>
        <div id="admin-message-controls" class="admin-message" style="display:none;"></div>
    </div>

    <!-- Results Display -->
    <div class="bingo-admin-box">
        <div class="results-header">
            <h2>Game Results</h2>
            <form method="get">
                <input type="hidden" name="page" value="wp-bingo-results">
                <label for="game-id-selector">View Results for:</label>
                <select name="game_id" id="game-id-selector" onchange="this.form.submit()">
                    <?php for ( $i = 1; $i <= $total_games; $i++ ): ?>
                        <option value="<?php echo $i; ?>" <?php selected( $display_game_id, $i ); ?>>
                            Game #<?php echo $i; ?> <?php if ($i == $current_game_id) echo '(Current)'; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <noscript><input type="submit" class="button button-secondary" value="View"></noscript>
            </form>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Player Name</th>
                    <th scope="col">Squares Filled</th>
                    <th scope="col">Winner?</th>
                    <th scope="col">Win Time</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $players ) ) : ?>
                    <?php foreach ( $players as $player ) :
                        $card_data = json_decode( $player->card_data, true );
                        $player_card_layout = json_decode( $player->card_layout, true );
                        $filled_squares = is_array($card_data) ? count(array_filter($card_data)) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $player->player_name ); ?></strong></td>
                        <td><?php echo esc_html( $filled_squares ); ?></td>
                        <td><?php echo $player->is_winner ? '<span class="winner-yes">Yes</span>' : 'No'; ?></td>
                        <td><?php echo $player->is_winner ? esc_html( $player->win_time ) : 'N/A'; ?></td>
                        <td>
                            <?php if ($filled_squares > 0): ?>
                                <button class="button button-secondary view-card-btn" data-player-id="<?php echo esc_attr($player->id); ?>">View Card</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="details-row" id="details-<?php echo esc_attr($player->id); ?>" style="display:none;">
                        <td colspan="5">
                            <div class="bingo-card-details">
                                <h4><?php echo esc_html( $player->player_name ); ?>'s Card (Game #<?php echo esc_html($display_game_id); ?>)</h4>
                                <?php if ($filled_squares > 0 && is_array($player_card_layout)): ?>
                                    <ul>
                                        <?php foreach($card_data as $index => $name): ?>
                                            <?php if (!empty($name)): 
                                                $square_text = isset($player_card_layout[$index]) ? $player_card_layout[$index] : 'Square ' . ($index + 1);
                                                if (strtoupper($name) === 'FREE') : ?>
                                                    <li><strong>FREE SPACE</strong></li>
                                                <?php else: ?>
                                                    <li><strong><?php echo esc_html($square_text); ?>:</strong> <?php echo esc_html($name); ?></li>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No names entered for this game, or card layout is missing.</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No players have participated in Game #<?php echo esc_html($display_game_id); ?> yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Danger Zone -->
    <div class="bingo-admin-box danger-zone">
        <h2>Danger Zone</h2>
        <p>This will permanently delete ALL players, ALL game history, and reset ALL settings to their original defaults. This cannot be undone.</p>
        <button id="reset-all-data-btn" class="button button-danger">Reset All Plugin Data</button>
        <div id="admin-message-reset" class="admin-message" style="display:none;"></div>
    </div>
</div>
