jQuery(document).ready(function($) {
    if (typeof wp_bingo_obj === 'undefined') return;

    // UI Elements
    const entryScreen = $('#player-entry-screen');
    const gameContainer = $('#wp-bingo-board-container');
    const nameInput = $('#player-name-input');
    const startButton = $('#start-game-button');
    const entryMessage = $('#player-entry-message');
    const board = $('#wp-bingo-board');
    const messageBox = $('#bingo-message');
    const timerDisplay = $('#bingo-timer');
    const allInputs = $('.bingo-name-input');
    const shoutButton = $('#bingo-shout-button');
    const resetButton = $('#reset-bingo-button');

    // Game State
    let cardState = {};
    let playerGuid = localStorage.getItem('wp_bingo_player_guid');
    let playerName = localStorage.getItem('wp_bingo_player_name');
    let saveTimeout;
    let timerInterval;

    /**
     * Shows a message to the user.
     * @param {jQuery} el - The jQuery element to display the message in.
     * @param {string} text - The message to show.
     * @param {boolean} isSuccess - True for a green success message, false for red.
     */
    function showMessage(el, text, isSuccess) {
        el.text(text)
            .removeClass('success error')
            .addClass(isSuccess ? 'success' : 'error')
            .slideDown();
        setTimeout(() => el.slideUp(), 4000);
    }

    /**
     * Disables the game board and buttons, showing a final message.
     * @param {string} message - The message to display.
     */
    function lockBoard(message) {
        allInputs.prop('disabled', true);
        shoutButton.prop('disabled', true).css('opacity', 0.6);
        resetButton.prop('disabled', true).css('opacity', 0.6);
        showMessage(messageBox, message, true); // Winners/end messages are styled as success
    }

    /**
     * Starts the countdown timer on the frontend.
     * @param {number} endTime - The UNIX timestamp when the timer should end.
     */
    function startTimer(endTime) {
        if (endTime <= 0) {
            timerDisplay.parent().hide();
            return;
        }

        timerInterval = setInterval(function() {
            const now = Math.floor(new Date().getTime() / 1000);
            const remaining = endTime - now;

            if (remaining <= 0) {
                clearInterval(timerInterval);
                timerDisplay.text("00:00");
                lockBoard("Time's up! The game is now locked.");
                return;
            }
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            timerDisplay.text(String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0'));
        }, 1000);
    }

    /**
     * Populates the game board with data fetched from the server.
     * @param {object} data - The player data object from the server.
     */
    function initializeGame(data) {
        cardState = data.card_data || {};
        allInputs.val(''); // Clear all inputs first
        $('.bingo-square').removeClass('selected'); // Clear selected state
        
        allInputs.each(function() {
            const index = $(this).closest('.bingo-square').data('index');
            if (cardState[index]) {
                $(this).val(cardState[index]);
                $(this).closest('.bingo-square').addClass('selected');
            }
        });

        entryScreen.hide();
        gameContainer.show();
        
        const serverTimeNow = parseInt(wp_bingo_obj.current_time, 10);
        const clientTimeNow = Math.floor(new Date().getTime() / 1000);
        const timeOffset = parseInt(wp_bingo_obj.timer_end, 10) - serverTimeNow;
        const clientEndTime = clientTimeNow + timeOffset;

        if (clientEndTime > clientTimeNow) {
            startTimer(clientEndTime);
        } else if (wp_bingo_obj.timer_end > 0) {
             lockBoard("This game round has ended.");
        }

        if (data.is_winner) {
            lockBoard('This game round has ended!');
        }
    }
    
    /**
     * Registers a new player or retrieves data for an existing one.
     */
    function playerInit() {
        $.ajax({
            url: wp_bingo_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_bingo_player_init',
                nonce: wp_bingo_obj.nonce,
                player_guid: playerGuid,
                player_name: nameInput.val().trim()
            },
            success: function(response) {
                startButton.prop('disabled', false).text("Let's Play!");
                if (response.success) {
                    playerGuid = response.data.player_guid;
                    playerName = response.data.player_name;
                    localStorage.setItem('wp_bingo_player_guid', playerGuid);
                    localStorage.setItem('wp_bingo_player_name', playerName);
                    initializeGame(response.data);
                } else {
                    showMessage(entryMessage, response.data.message, false);
                }
            },
            error: function() {
                showMessage(entryMessage, 'Could not connect to the server. Please try again.', false);
                startButton.prop('disabled', false).text("Let's Play!");
            }
        });
    }

    /**
     * Saves the current card state to the database.
     * @param {function} [callback] - An optional function to run after the save attempt. It receives a boolean indicating success.
     */
    function saveCardState(callback) {
        clearTimeout(saveTimeout);
        
        const doSave = () => {
            // Ensure playerGuid and playerName are set before trying to save
            if (!playerGuid || !playerName) {
                if (typeof callback === 'function') callback(false);
                return;
            }
            $.ajax({
                url: wp_bingo_obj.ajax_url,
                type: 'POST',
                data: { 
                    action: 'wp_bingo_save_state', 
                    nonce: wp_bingo_obj.nonce, 
                    card_data: cardState, 
                    player_guid: playerGuid,
                    player_name: playerName // Always send name with state
                },
                success: function(res) {
                    if (typeof callback === 'function') callback(res.success);
                },
                error: function() {
                    if (typeof callback === 'function') callback(false);
                }
            });
        };

        // If a callback is provided, save immediately. Otherwise, use a delay (debounce).
        if (typeof callback === 'function') {
            doSave();
        } else {
            saveTimeout = setTimeout(doSave, 500);
        }
    }

    // ===== Event Handlers =====

    // Player clicks "Let's Play!"
    startButton.on('click', function() {
        if (nameInput.val().trim() === '') {
            showMessage(entryMessage, 'Please enter your name.', false);
            return;
        }
        $(this).prop('disabled', true).text('Joining...');
        playerInit();
    });

    // Player types in a name field
    board.on('change', '.bingo-name-input', function() {
        const input = $(this);
        const square = input.closest('.bingo-square');
        const index = square.data('index');
        const newName = input.val().trim();
        const oldName = cardState[index] || '';

        let isDuplicate = false;
        if (newName !== '') {
            for (const key in cardState) {
                if (key != index && cardState[key] && cardState[key].toLowerCase() === newName.toLowerCase()) {
                    isDuplicate = true;
                    break;
                }
            }
        }

        if (isDuplicate) {
            showMessage(messageBox, `The name "${newName}" is already on your card.`, false);
            input.val(oldName); // Revert to the old value
            return;
        }

        cardState[index] = newName;
        square.toggleClass('selected', newName !== '');
        if (newName === '') delete cardState[index]; // Clean up empty entries from the state
        saveCardState(); // Debounced save
    });

    // Player clicks "BINGO!"
    shoutButton.on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Saving...');

        // Force an immediate save before checking for a win
        saveCardState(function(saveSuccess) {
            if (!saveSuccess) {
                showMessage(messageBox, 'Could not save card. Please check your connection and try again.', false);
                button.prop('disabled', false).text('BINGO!');
                return;
            }
            
            button.text('Checking...');
            $.ajax({
                url: wp_bingo_obj.ajax_url, type: 'POST',
                data: { action: 'wp_bingo_check_win', nonce: wp_bingo_obj.nonce, player_guid: playerGuid },
                success: res => {
                    if (res.success) {
                        lockBoard(res.data);
                    } else {
                        showMessage(messageBox, res.data.message || 'An unknown error occurred.', false);
                        button.prop('disabled', false).text('BINGO!');
                    }
                },
                error: () => {
                    showMessage(messageBox, 'An error occurred while checking your card. Please try again.', false);
                    button.prop('disabled', false).text('BINGO!');
                }
            });
        });
    });

    // Player clicks "Reset Card"
    resetButton.on('click', function() {
        if (confirm('Are you sure you want to reset your card for this game? This will clear all names.')) {
            cardState = {};
            allInputs.val('');
            $('.bingo-square').removeClass('selected');
            // Force an immediate save of the empty state
            saveCardState(function(saveSuccess){
                if(saveSuccess){
                    showMessage(messageBox, 'Your board has been reset for this game.', true);
                } else {
                    showMessage(messageBox, 'Could not reset the board. Please check your connection.', false);
                }
            });
        }
    });

    // ===== Initial App Load =====
    if (playerGuid && playerName) {
        // If a player is returning, initialize the game directly
        nameInput.val(playerName);
        playerInit();
    } else {
        // Otherwise, show the name entry screen
        entryScreen.show();
    }
});
