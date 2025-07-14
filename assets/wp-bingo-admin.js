jQuery(document).ready(function($) {
    if (typeof wp_bingo_admin_obj === 'undefined') return;

    const adminMessageBox = $('#admin-message');

    /**
     * Shows a message in the admin dashboard.
     * @param {string} text - The message to display.
     * @param {boolean} isSuccess - If true, message will be a success notice. Otherwise, an error.
     */
    function showAdminMessage(text, isSuccess) {
        adminMessageBox
            .text(text)
            .removeClass('notice-success notice-error')
            .addClass(isSuccess ? 'notice notice-success' : 'notice notice-error')
            .slideDown();
        setTimeout(() => adminMessageBox.slideUp(), 5000);
    }

    // Handler for the "View Card" button
    $('.view-card-btn').on('click', function() {
        const playerId = $(this).data('player-id');
        $('#details-' + playerId).toggle();
    });

    // Handler for "Start Timer" button
    $('#start-timer-btn').on('click', function() {
        const button = $(this);
        const duration = $('#timer-duration').val();
        
        if (parseInt(duration, 10) < 1) {
            showAdminMessage('Please enter a valid duration (1 minute or more).', false);
            return;
        }

        button.prop('disabled', true).text('Starting...');

        $.ajax({
            url: wp_bingo_admin_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_bingo_manage_game',
                nonce: wp_bingo_admin_obj.nonce,
                game_action: 'start_timer',
                duration: duration
            },
            success: function(response) {
                if (response.success) {
                    showAdminMessage(response.data.message, true);
                    location.reload();
                } else {
                    showAdminMessage('Error: ' + response.data, false);
                }
            },
            error: function() {
                showAdminMessage('An unknown error occurred.', false);
            },
            complete: function() {
                button.prop('disabled', false).text('Start Timer');
            }
        });
    });

    // Handler for "Start New Game" button
    $('#start-new-game-btn').on('click', function() {
        if (!confirm('Are you sure you want to start a new game? This will archive all current player progress and reset the timer. This cannot be undone.')) {
            return;
        }

        const button = $(this);
        button.prop('disabled', true).text('Starting New Game...');

        $.ajax({
            url: wp_bingo_admin_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_bingo_manage_game',
                nonce: wp_bingo_admin_obj.nonce,
                game_action: 'start_new_game'
            },
            success: function(response) {
                if (response.success) {
                    showAdminMessage(response.data.message, true);
                    setTimeout(() => { window.location.href = window.location.pathname + '?page=wp-bingo-results' }, 1500);
                } else {
                    showAdminMessage('Error: ' + response.data, false);
                    button.prop('disabled', false).text('Start New Game');
                }
            },
            error: function() {
                showAdminMessage('An unknown error occurred.', false);
                button.prop('disabled', false).text('Start New Game');
            }
        });
    });
});
