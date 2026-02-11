# Ascend Rewards

A Moodle local plugin that gamifies learning through a comprehensive badge, coin, and reward system.

## Description

Ascend Rewards is a gamification plugin that automatically awards badges to students based on their learning activities and achievements. Students earn coins and experience points (XP) which they can use to unlock avatars, pets, and defeat villains. The plugin includes:

- **Automatic Badge Awarding**: Seven different badge types that recognize various achievements
- **Coin & XP Economy**: Students earn currency for completing badges
- **Collectible System**: Unlock avatars, pets, and villains using earned coins
- **Leaderboard**: Track progress and compete with peers
- **Mystery Boxes**: Random rewards for added engagement

## Features

### Active Badges (7 types)
- **Getting Started** - Complete your first activity
- **Halfway Hero** - Reach 50% course completion  
- **Master Navigator** - Achieve multiple meta-badges in a course
- **Feedback Follower** - Submit assignments (repeatable)
- **Steady Improver** - Show consistent grade improvement (repeatable)
- **Tenacious Tiger** - Persist through challenges (repeatable)
- **Glory Guide** - Help peers through forum participation

### Gamification Elements
- **Avatars**: Unlockable character avatars (Elf, Imp)
- **Pets**: Companion pets (Lynx, Hamster)
- **Villains**: Challenges to overcome (Dryad, Mole)
- **Mystery Boxes**: Random reward system
- **Weekly Gameboard**: Track weekly badge achievements

## Requirements

- Moodle 4.0 or higher (2022041900)
- PHP 7.4 or higher
- Moodle Badges core functionality enabled

## Installation

1. Download the plugin and extract to `local/ascend_rewards/`
2. Visit Site Administration > Notifications to trigger installation
3. Configure default coin values in Settings
4. The plugin will automatically create default badges on first run

## Configuration

Navigate to **Site administration > Plugins > Local plugins > Ascend Rewards** to configure:

- Default coin rewards for each badge type
- Badge repeat settings
- Badge mapping to Moodle core badges

## Usage

### For Students
1. Navigate to "Ascend Rewards" from the main navigation menu
2. View earned badges, coin balance, and XP
3. Visit the store to unlock avatars, pets, and villains
4. Check the leaderboard to see rankings

### For Teachers/Admins
1. Access the admin dashboard from the Ascend Rewards page
2. View badge audit trail
3. Manually trigger badge cache rebuild if needed
4. Monitor student engagement through the leaderboard

## Access Control (Best Practices)

- The Ascend Rewards dashboard is intentionally available to all logged-in users.
- Administrative features are restricted via the `local/ascend_rewards:manage` capability (teachers, editing teachers, managers, admins).
- Public-facing endpoints require login; admin endpoints enforce capability checks.
- If you change the access model, update role permissions accordingly and run Site administration → Notifications.

## Coding Standards (Best Practices)

- Global functions are frankenstyle-prefixed (local_ascend_rewards_) to avoid namespace collisions.
- Helper functions inside classes follow Moodle namespacing conventions.

## Privacy

This plugin implements the Moodle Privacy API and stores:
- User badge awards
- Coin and XP transactions
- Purchased avatars, pets, and villains

Users can request deletion of this data through Moodle's standard privacy tools.

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.

## Author

Local plugin developed for Moodle gamification.

## Support

For issues, questions, or contributions, please use the Moodle plugins directory or contact the plugin maintainer.
