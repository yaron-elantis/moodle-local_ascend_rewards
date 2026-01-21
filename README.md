# Ascend Rewards Plugin

A comprehensive gamification plugin for Moodle that adds badges, coins, XP, and rewards system to enhance user engagement.

## Features

- **Badge System**: 9 operational badges (Getting Started, On a Roll, Halfway Hero, Master Navigator, Feedback Follower, Steady Improver, Tenacious Tiger, Glory Guide, Level Up)
- **Avatar Customization**: 2 avatar sets with leveling system (Elf & Imp, each with Lynx/Hamster and Dryad/Mole companions)
- **Pet & Villain Companions**: Unlock and manage companions through gameplay
- **Weekly Gameboard**: Pick-based weekly reward system
- **Store & Power-ups**: Purchase items with coins, including XP multipliers
- **Mystery Box**: Random reward system with pity mechanics
- **Leaderboard Rankings**: Site-wide and course-specific XP rankings
- **Performance Caching**: Optimized database queries for fast dashboard loads

## Installation

1. Download/clone this plugin
2. Extract to `moodle/local/ascend_rewards`
3. Visit Moodle admin dashboard
4. Run the installation process
5. Configure coins per badge in Settings > Plugins > Local Plugins > Ascend Rewards

## Requirements

- Moodle 4.2 or later (requires version 2022041900+)
- PHP 7.4 or higher

## Database Tables Created

- `local_ascend_rewards_coins` - Main coin ledger
- `local_ascend_rewards_gameboard` - Weekly gameboard picks
- `local_ascend_badge_cache` - Badge activity cache
- `local_ascend_rewards_badges` - Badge configuration
- `local_ascend_rewards_badgerlog` - Badge awarding audit log
- `local_ascend_mysterybox` - Mystery box tracking
- `local_ascend_avatar_unlocks` - Avatar/pet/villain unlocks
- `local_ascend_level_tokens` - Level-up unlock tokens
- `local_ascend_xp` - XP tracking (separate from coins)

## Scheduled Tasks

- **Award Badges** - Runs every 5 minutes to award earned badges
- **Rebuild Badge Cache** - Runs daily at 3 AM to maintain activity cache

## Author

ELANTIS

## License

GNU General Public License v3 or later

## Support

For issues, feature requests, or contributions, please contact the development team.

---

**Version**: 1.2.1  
**Last Updated**: January 2026
