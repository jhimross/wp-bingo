<?php
/**
 * Admin View for BINGO Results Dashboard (v5.1)
 */
if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
// CORRECTED: The table name should not have 'wp_' hardcoded.
$player_table = $wpdb->prefix . 'bingo_players';

// Get current and total games
$current_game_id = get_option( 'wp_bingo_current_game_id', 1 );
$total_games = $wpdb->get_var( "SELECT MAX(game_id) FROM $player_table" );
if (!$total_games) $total_games = $current_game_id;

// Determine which game to display
$display_game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : $current_game_id;

// Fetch players for the selected game
$players = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $player_table WHERE game_id = %d ORDER BY is_winner DESC, win_time ASC", $display_game_id ) );

$bingo_items = array(
    'Just started using WordPress this year', 'Prefers the Block Editor', 'Has customized a theme using code', 'Has joined a WordPress group or forum',
    'Has used a page builder like Elementor', 'Knows how to install a plugin', 'Has set up an online store using WP', 'Learned WP from YouTube or blogs',
    'Has uploaded and optimized images', 'Uses WordPress for blogging', 'Has tried Full Site Editing (FSE)', 'Helped someone build a WP site',
    'Has attended a WordPress meetup or WordCamp', 'Can explain what a "hook" is', 'Knows what a shortcode is', 'Has used WP for over 5 years'
);
?>
<div class="wrap wp-bingo-admin">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <!-- Game Controls -->
    <div class="game-controls-box">
        <h2>Game Controls</h2>
        <div class="controls-flex">
            <div class="control-item">
                <h3>Start New Round</h3>
                <p>This will archive the current game and start a new one. All players will start with a fresh board.</p>
                <button id="start-new-game-btn" class="button button-primary">Start New Game (Currently on Game #<?php echo esc_html($current_game_id); ?>)</button>
            </div>
            <div class="control-item">
                <h3>Set Timer</h3>
                <p>Start a countdown timer for all players on the front end. The board will lock when time is up.</p>
                <div class="timer-form">
                    <input type="number" id="timer-duration" value="5" min="1" max="60">
                    <label for="timer-duration">minutes</label>
                    <button id="start-timer-btn" class="button button-secondary">Start Timer</button>
                </div>
            </div>
        </div>
        <div id="admin-message" style="display:none; margin-top: 15px;"></div>
    </div>

    <!-- Results Display -->
    <div class="results-box">
        <div class="results-header">
            <h2>Game Results</h2>
            <form method="get">
                <input type="hidden" name="page" value="wp-bingo-results">
                <label for="game-id-selector">View Results for:</label>
                <select name="game_id" id="game-id-selector">
                    <?php for ( $i = 1; $i <= $total_games; $i++ ): ?>
                        <option value="<?php echo $i; ?>" <?php selected( $display_game_id, $i ); ?>>
                            Game #<?php echo $i; ?> <?php if ($i == $current_game_id) echo '(Current)'; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <input type="submit" class="button button-secondary" value="View">
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
                        $filled_squares = is_array($card_data) ? count(array_filter($card_data)) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $player->player_name ); ?></strong></td>
                        <td><?php echo esc_html( $filled_squares ); ?> / 16</td>
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
                                <?php if ($filled_squares > 0): ?>
                                    <ul>
                                        <?php foreach($card_data as $index => $name): ?>
                                            <?php if (!empty($name)): ?>
                                                <li><strong><?php echo esc_html(isset($bingo_items[$index]) ? $bingo_items[$index] : 'Unknown Square'); ?>:</strong> <?php echo esc_html($name); ?></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No names entered for this game.</p>
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
</div>
