# Avatar Pets Image Directory

This directory contains the pet companion images for the Ascend Store.

## Required Pet Images

Each pet requires a PNG image with transparent background, recommended size: 256x256px or 512x512px.

### Pet Image List (16 total)

1. **giant_otter.png** - Giant Otter (Amazon Avatar companion)
2. **jungle_lynx.png** - Jungle Lynx (Elf Avatar companion)
3. **rootshield_tortoise.png** - Rootshield Tortoise (Ent Avatar companion)
4. **mishiefcap_prankimp.png** - Mishiefcap Prankimp (Jester Avatar companion)
5. **tealeaf_dragonet.png** - Tealeaf Dragonet (Magician Avatar companion)
6. **kinkajou.png** - Kinkajou (Pirate Avatar companion)
7. **winged_starfalcon.png** - Winged Starfalcon (Nomad Avatar companion)
8. **stoic_mastiff.png** - Stoic Mastiff (Philosopher Avatar companion)
9. **biolu_seahorse.png** - Biolu Seahorse (Mermaid Avatar companion)
10. **iron_boar.png** - Iron Boar (Warrior Avatar companion)
11. **stormglass_viper.png** - Stormglass Viper (Sorceress Avatar companion)
12. **saami_calf.png** - Saami Calf (Viking Avatar companion)
13. **stone_gryphon.png** - Stone Gryphon (Guardian Avatar companion)
14. **gilded_swan.png** - Gilded Swan (Queen Avatar companion)
15. **runebound_raven.png** - Runebound Raven (Wizard Avatar companion)
16. **cinder_puff_hamster.png** - Cinder Puff Hamster (Imp Avatar companion)

## Image Requirements

- Format: PNG with transparency
- Dimensions: 256x256px minimum (512x512px preferred)
- Style: Match the aesthetic of existing avatar images
- Quality: Clear, detailed artwork suitable for display in store cards
- Naming: Use lowercase with underscores (as shown above)

## Fallback Behavior

If a pet image is missing, the system will automatically display the `apex_coin_main.png` icon as a fallback, so the store will remain functional even without custom pet artwork.

## Design Tips

Each pet should:
- Reflect the theme/personality of its associated avatar
- Be visually distinct and recognizable
- Have appropriate visual details for their rarity tier (rare collectibles)
- Use colors and style consistent with the overall Ascend Rewards theme

## File Locations

- Pet images go here: `/local/apex_rewards/pix/pets/`
- Avatar images: `/local/apex_rewards/pix/Avatars/`
- Other store icons: `/local/apex_rewards/pix/`
