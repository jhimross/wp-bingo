# WP BINGO Plugin

**Contributors:** jhimross  
**Version:** 2.0.1  
**License:** GPLv2 or later  
**License URI:** [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

A fun and interactive BINGO game designed as an icebreaker for WordPress meetups, WordCamps, and other community events.

---

## Description

**WP BINGO** transforms the classic icebreaker game into a dynamic, customizable WordPress plugin. Instead of static paper cards, attendees can play on their mobile phones. As an admin, you have full control over the game's content, size, and timing—all from a convenient dashboard page.

This plugin allows players to join without needing a WordPress account, tracks their progress, and lets you see the results in real-time, making it the perfect tool to encourage networking and interaction at your events.

---

## Features

- **Guest Players** – No login required! Players simply enter their name to join the game.  
- **Customizable Cards** – Easily add, edit, or remove BINGO square items from the admin dashboard.  
- **Adjustable Grid Size** – Choose between 3x3, 4x4, or 5x5 BINGO cards.  
- **Logo on FREE Space** – Upload your own logo to display in the center "FREE" space on 5x5 grids.  
- **Live Game Dashboard** – View all player progress, see who has filled which squares, and identify winners from the WordPress admin area.  
- **Game Rounds** – Start new game rounds with the click of a button, archiving old results without losing them.  
- **Countdown Timer** – Set a timer for the game. When time is up, all boards are automatically saved and locked.  
- **Mobile-Friendly** – The BINGO board is fully responsive and designed for a great experience on mobile phones.  
- **Complete Data Reset** – A "Danger Zone" option allows you to completely reset the plugin's data for a fresh start.

---

## Installation

1. Download the plugin `.zip` file from the GitHub repository.  
2. In your WordPress dashboard, navigate to **Plugins > Add New**.  
3. Click the **Upload Plugin** button at the top of the page.  
4. Choose the downloaded `.zip` file and click **Install Now**.  
5. Once installed, click **Activate Plugin**.

---

## How to Use

After activating the plugin, a new **BINGO** menu item will appear in your WordPress dashboard.

### Admin Setup:

- Go to **BINGO** to access the admin dashboard.
- Customize the BINGO items in the **Game Settings** section.
- Choose the card size (3x3, 4x4, or 5x5).
- Upload a logo for the FREE space.
- Start new game rounds or set a timer in the **Game Controls** section.

### Frontend Display:

To display the BINGO game on the front end of your site:

1. Create or edit a Page or Post.
2. Add the following shortcode to the content area:

   ```wordpress
   [wp_bingo_game]
3. Publish or update the page. Players can now visit this page to play!

### Admin Dashboard

The admin dashboard is your central hub for managing the game.

1. Game Settings – Set up the game before your event. Change card size, upload a logo, and add BINGO items (one per line).
2. Game Controls – Start a new round or countdown timer during the event.
3. Game Results – View live progress, number of filled squares, and winners. Use the dropdown to access past rounds.
4. Danger Zone – Reset all plugin data. This is a destructive action that clears all player data and restores default settings.
