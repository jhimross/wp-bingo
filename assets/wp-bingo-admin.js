jQuery(document).ready(function($) {
    if (typeof wp_bingo_admin_obj === 'undefined') return;

    function showAdminMessage(el, text, isSuccess) {
        el.text(text)
            .removeClass('notice-success notice-error')
            .addClass(isSuccess ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible')
            .slideDown();
        setTimeout(() => el.slideUp(), 5000);
    }

    // Handler for the "View Card" button
    $('.view-card-btn').on('click', function() {
        const playerId = $(this).data('player-id');
        $('#details-' + playerId).toggle();
    });

    // Handler for saving settings form
    $('#bingo-settings-form').on('submit', function(e) {
        e.preventDefault(); 
        const button = $('#save-bingo-settings');
        button.prop('disabled', true).val('Saving...');

        const postData = $(this).serialize() + '&action=wp_bingo_save_settings';

        $.ajax({
            url: wp_bingo_admin_obj.ajax_url,
            type: 'POST',
            data: postData,
            success: function(response) {
                const messageEl = $('#admin-message-settings');
                if (response.success) {
                    showAdminMessage(messageEl, response.data.message, true);
                } else {
                    const errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                    showAdminMessage(messageEl, 'Error: ' + errorMessage, false);
                }
            },
            error: function() {
                showAdminMessage($('#admin-message-settings'), 'An AJAX error occurred. Please try again.', false);
            },
            complete: function() {
                button.prop('disabled', false).val('Save Settings');
            }
        });
    });

    // Handler for "Start Timer" button
    $('#start-timer-btn').on('click', function() {
        const button = $(this);
        const duration = $('#timer-duration').val();
        
        if (parseInt(duration, 10) < 1) {
            showAdminMessage($('#admin-message-controls'), 'Please enter a valid duration.', false);
            return;
        }
        button.prop('disabled', true).text('Starting...');
        $.ajax({
            url: wp_bingo_admin_obj.ajax_url, type: 'POST',
            data: { 
                action: 'wp_bingo_manage_game',
                nonce: wp_bingo_admin_obj.manage_nonce,
                game_action: 'start_timer', 
                duration: duration 
            },
            success: (res) => {
                showAdminMessage($('#admin-message-controls'), res.success ? res.data.message : 'Error: ' + (res.data.message || 'Unknown error'), res.success);
                if (res.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            },
            error: () => showAdminMessage($('#admin-message-controls'), 'An unknown error occurred.', false),
            complete: () => button.prop('disabled', false).text('Start Timer')
        });
    });

    // Handler for "Start New Game" button
    $('#start-new-game-btn').on('click', function() {
        if (!confirm('Are you sure you want to start a new game? This will archive all current player progress and reset the timer.')) return;
        
        const button = $(this);
        button.prop('disabled', true).text('Starting New Game...');
        $.ajax({
            url: wp_bingo_admin_obj.ajax_url, type: 'POST',
            data: { 
                action: 'wp_bingo_manage_game',
                nonce: wp_bingo_admin_obj.manage_nonce,
                game_action: 'start_new_game' 
            },
            success: function(response) {
                const messageEl = $('#admin-message-controls');
                showAdminMessage(messageEl, response.success ? response.data.message : 'Error: ' + (response.data.message || 'Unknown error'), response.success);
                if (response.success) {
                    setTimeout(() => { window.location.href = window.location.pathname + '?page=wp-bingo-results' }, 1500);
                } else {
                    button.prop('disabled', false).text('Start New Game');
                }
            },
            error: () => {
                showAdminMessage($('#admin-message-controls'), 'An unknown error occurred.', false);
                button.prop('disabled', false).text('Start New Game');
            }
        });
    });

    // Handler for "Reset All Data" button
    $('#reset-all-data-btn').on('click', function() {
        const confirmation = prompt('This is a destructive action that cannot be undone. To confirm, please type "RESET" in the box below.');
        if (confirmation !== 'RESET') {
            alert('Reset cancelled. You did not type "RESET" correctly.');
            return;
        }

        const button = $(this);
        button.prop('disabled', true).text('Resetting...');
        $.ajax({
            url: wp_bingo_admin_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_bingo_manage_game',
                nonce: wp_bingo_admin_obj.manage_nonce,
                game_action: 'reset_all_data'
            },
            success: function(response) {
                const messageEl = $('#admin-message-reset');
                showAdminMessage(messageEl, response.success ? response.data.message : 'Error: ' + (response.data.message || 'Unknown error'), response.success);
                if (response.success) {
                    setTimeout(() => { window.location.href = window.location.pathname + '?page=wp-bingo-results' }, 2000);
                } else {
                    button.prop('disabled', false).text('Reset All Plugin Data');
                }
            },
            error: function() {
                showAdminMessage($('#admin-message-reset'), 'An unknown error occurred.', false);
                button.prop('disabled', false).text('Reset All Plugin Data');
            }
        });
    });

    // Logo Uploader Logic
    let mediaUploader;
    $('#upload-logo-button').on('click', function(e) {
        e.preventDefault();
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose BINGO Logo',
            button: { text: 'Choose Logo' },
            multiple: false
        });
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#logo_url_field').val(attachment.url);
            $('#logo-preview').attr('src', attachment.url).show();
            $('#remove-logo-button').show();
        });
        mediaUploader.open();
    });

    $('#remove-logo-button').on('click', function(e) {
        e.preventDefault();
        $('#logo_url_field').val('');
        $('#logo-preview').attr('src', '').hide();
        $(this).hide();
    });
});
