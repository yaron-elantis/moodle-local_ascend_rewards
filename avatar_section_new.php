<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * APEX REWARDS - ASCEND UNIVERSE SECTION
 * Level-based progression with vertical sets (1-4) and horizontal epic layout (5-8)
 * Clean, optimized code with color-coded borders for all items.
 */

defined('MOODLE_INTERNAL') || die();

// Verify required variables are available
if (empty($avatar_levels) || empty($avatar_pets_catalog) || empty($villain_catalog)) {
    return; // Skip if data not loaded
}

// ============================================================================
// CONFIGURATION
// ============================================================================
$config = [
    'coin_stack_size' => '92px',
    'font_family' => "'Uncial Antiqua', serif"
];

$apex_stack_url = (new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png'))->out(false);
?>

<!-- ============================================================================
     CUSTOM FONTS
     ============================================================================ -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Uncial+Antiqua&display=swap" rel="stylesheet">

<!-- ============================================================================
     MAIN SECTION
     ============================================================================ -->
<section class="aa-panel" id="a_avatars">
    <div class="aa-panel-head">
        <h3>
            <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/avatar.png'))->out(false); ?>" 
                 alt="Ascend Universe" 
                 style="width:60px;height:60px;vertical-align:middle;margin-right:8px;">
            Ascend Universe
            <?php if ($tokens_available > 0): ?>
                <span style="color:#ec4899;font-size:14px;font-weight:600;margin-left:8px;">
                    ⭐ <?php echo $tokens_available; ?> token<?php echo $tokens_available > 1 ? 's' : ''; ?> available!
                </span>
            <?php endif; ?>
        </h3>
        <i class="fa-solid fa-chevron-down"></i>
    </div>
    
    <div class="aa-panel-content">
        <!-- User Stats -->
        <p class="aa-muted" style="margin-bottom:24px;">
            You are level <?php echo $level; ?>. You have 
            <strong><?php echo count($unlocked_avatars); ?></strong> hero<?php echo count($unlocked_avatars) !== 1 ? 'es' : ''; ?> unlocked, 
            <strong><?php echo count($owned_pets); ?></strong> pet<?php echo count($owned_pets) !== 1 ? 's' : ''; ?> adopted, and 
            <strong><?php echo count($owned_villains); ?></strong> villain<?php echo count($owned_villains) !== 1 ? 's' : ''; ?> unlocked.
            <?php if ($tokens_available > 0): ?>
                <span style="color:#ec4899;">
                    You have <strong><?php echo $tokens_available; ?></strong> unlock token<?php echo $tokens_available > 1 ? 's' : ''; ?>!
                </span>
            <?php endif; ?>
        </p>
        
        <?php
        // ============================================================================
        // REGULAR LEVELS (1-6): Vertical Sets (3 sets per level)
        // ============================================================================
        $level_worlds = [
            1 => 'Emberveil Forest',
            2 => 'Stormscar Desert',
            3 => 'Veilspire Empire',
            4 => 'Thalassar Archipelago',
            5 => 'Arcanum Citadel',
            6 => 'Frostfang Tundra'
        ];
        
        // Storyline videos mapped by avatar basename (DEMO VERSION: only Elf and Imp)
        $story_videos = [
            'elf' => '5Br95mH5oTU',   // The Elf's story from https://youtu.be/5Br95mH5oTU
            'imp' => '5cWCcTysX54'    // The Imp's story from https://youtu.be/5cWCcTysX54
        ];
        
        for ($lv = 1; $lv <= 6; $lv++):
            $can_access_level = in_array($lv, $user_accessible_levels);
            $level_avatars = $avatar_levels[$lv] ?? [];
            
            if (empty($level_avatars)) continue;
            
            // Build sets by matching avatars with their pets and villains
            $sets = [];
            foreach ($level_avatars as $avatar) {
                $set = ['avatar' => $avatar, 'pet' => null, 'pet_id' => null, 'villain' => null, 'villain_id' => null];
                
                // Find matching pet
                foreach ($avatar_pets_catalog as $pet_id => $pet_data) {
                    if ($pet_data['level'] === $lv && $pet_data['avatar'] === $avatar) {
                        $set['pet'] = $pet_data;
                        $set['pet_id'] = $pet_id;
                        break;
                    }
                }
                
                // Find matching villain
                if ($set['pet_id']) {
                    foreach ($villain_catalog as $villain_id => $villain_data) {
                        if ($villain_data['level'] === $lv && $villain_data['pet_id'] == $set['pet_id']) {
                            $set['villain'] = $villain_data;
                            $set['villain_id'] = $villain_id;
                            break;
                        }
                    }
                }
                
                $sets[] = $set;
            }
        ?>
        
        <div style="margin-bottom:40px;padding-bottom:24px;border-bottom:2px solid rgba(255,255,255,0.1);">
            <h4 class="avatar-level-header" data-level="<?php echo $lv; ?>" style="color:<?php echo $can_access_level ? '#06b6d4' : '#64748b'; ?>;font-size:18px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;transition:all 0.3s;">
                <i class="fa-solid fa-chevron-down level-chevron" style="font-size:14px;transition:transform 0.3s;"></i>
                Level <?php echo $lv; ?>: <?php echo $level_worlds[$lv] ?? ''; ?>
                <?php if (!$can_access_level): ?>
                    <i class="fa-solid fa-lock" style="color:#64748b;"></i> Locked until Level <?php echo $lv; ?>
                <?php endif; ?>
            </h4>
            
            <div class="avatar-level-content" data-level="<?php echo $lv; ?>" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;opacity:<?php echo $can_access_level ? '1' : '0.4'; ?>;pointer-events:<?php echo $can_access_level ? 'auto' : 'none'; ?>;overflow:hidden;transition:max-height 0.3s ease, opacity 0.3s ease;max-height:5000px;">
                <?php
                foreach ($sets as $index => $set):
                    $avatar = $set['avatar'];
                    $avatar_name = pathinfo($avatar, PATHINFO_FILENAME);
                    $is_unlocked = in_array($avatar, $unlocked_avatars);
                    $avatar_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/{$avatar}"))->out(false);
                    
                    $pet_data = $set['pet'];
                    $pet_id = $set['pet_id'];
                    $has_pet_unlocked = $pet_data && in_array($pet_id, $owned_pets);
                    $can_unlock_pet = $pet_data && $is_unlocked;
                    
                    $villain_data = $set['villain'];
                    $villain_id = $set['villain_id'];
                    $has_villain_unlocked = $villain_data && in_array($villain_id, $owned_villains);
                    $can_unlock_villain = $villain_data && $has_pet_unlocked;
                    
                    $is_first_column = ($index === 0);
                ?>
                
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <!-- HERO -->
                    <div style="text-align:center;">
                        <?php if ($is_first_column): ?>
                        <h5 style="color:#FFD700;font-size:14px;font-weight:600;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;height:20px;"><img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/avatar.png'))->out(false); ?>" alt="Heroes" style="width:16px;height:16px;object-fit:contain;vertical-align:middle;margin-right:4px;">Heroes</h5>
                        <?php else: ?>
                        <div style="height:28px;"></div>
                        <?php endif; ?>
                        <div class="avatar-card <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>" 
                             data-avatar="<?php echo $avatar; ?>"
                             data-name="<?php echo ucfirst($avatar_name); ?>"
                             data-level="<?php echo $lv; ?>"
                             style="position:relative;aspect-ratio:1;border-radius:50%;overflow:hidden;cursor:pointer;border:3px solid #FFD700;transition:all 0.3s ease;">
                            <img src="<?php echo $avatar_url; ?>" 
                                 alt="<?php echo ucfirst($avatar_name); ?>" 
                                 style="width:100%;height:100%;object-fit:cover;filter:<?php echo $is_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)'; ?>;">
                            <?php if (!$is_unlocked): ?>
                                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);">
                                    <i class="fa-solid fa-lock" style="font-size:48px;color:#94a3b8;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:8px;color:#FFD700;font-size:13px;font-family:<?php echo $config['font_family']; ?>;">
                            <?php echo ucfirst($avatar_name); ?>
                        </div>
                    </div>
                    
                    <!-- PET -->
                    <?php if ($pet_data):
                        $pet_name = $pet_data['name'];
                        $pet_icon_path = str_replace('pets/', '', $pet_data['icon']);
                        $pet_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon_path}"))->out(false);
                        $avatar_filename = str_replace(['.png', '.jpeg'], '', $pet_data['avatar']);
                        $avatar_circular_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/circular avatars/{$avatar_filename}.png"))->out(false);
                    ?>
                    <div style="text-align:center;">
                        <?php if ($is_first_column): ?>
                        <h5 style="color:#ec4899;font-size:14px;font-weight:600;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;height:20px;"><img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/pets.png'))->out(false); ?>" alt="Pets" style="width:16px;height:16px;object-fit:contain;vertical-align:middle;margin-right:4px;">Pets</h5>
                        <?php else: ?>
                        <div style="height:28px;"></div>
                        <?php endif; ?>
                        <div class="pet-card <?php echo $has_pet_unlocked ? 'owned' : 'locked'; ?>" 
                             data-pet-id="<?php echo $pet_id; ?>"
                             data-pet-name="<?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>"
                             data-pet-price="<?php echo $pet_data['price']; ?>"
                             data-pet-avatar="<?php echo $pet_data['avatar']; ?>"
                             data-can-unlock="<?php echo $can_unlock_pet ? '1' : '0'; ?>"
                             style="position:relative;aspect-ratio:1;cursor:<?php echo $can_unlock_pet && !$has_pet_unlocked ? 'pointer' : 'default'; ?>;transition:all 0.3s ease;">
                            <div style="position:relative;width:100%;height:100%;border-radius:50%;overflow:hidden;border:3px solid #ec4899;">
                                <img src="<?php echo $pet_url; ?>" 
                                     alt="<?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>"
                                     style="width:100%;height:100%;object-fit:cover;filter:<?php echo $has_pet_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)'; ?>;">
                                
                                <?php if (!$can_unlock_pet): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);">
                                        <i class="fa-solid fa-lock" style="font-size:36px;color:#94a3b8;"></i>
                                    </div>
                                <?php elseif (!$has_pet_unlocked): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);flex-direction:column;gap:6px;">
                                        <img src="<?php echo $apex_stack_url; ?>" alt="Coins" style="width:<?php echo $config['coin_stack_size']; ?>;height:32px;object-fit:contain;">
                                        <div style="font-size:11px;color:#FFD700;font-weight:700;"><?php echo $pet_data['price']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($is_unlocked): ?>
                            <div style="position:absolute;top:-4px;right:-4px;width:28%;aspect-ratio:1;border-radius:50%;overflow:hidden;border:2px solid #FFD700;background:#1a1f2e;box-shadow:0 2px 8px rgba(0,0,0,0.5);z-index:10;display:flex;align-items:center;justify-content:center;">
                                <img src="<?php echo $avatar_circular_url; ?>" 
                                     alt="<?php echo htmlspecialchars($pet_data['avatar'], ENT_QUOTES, 'UTF-8'); ?>"
                                     style="width:90%;height:90%;object-fit:contain;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:8px;color:#ec4899;font-size:13px;font-family:<?php echo $config['font_family']; ?>;">
                            <?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- VILLAIN -->
                    <?php if ($villain_data):
                        $villain_name = $villain_data['name'];
                        $villain_icon_path = str_replace('villains/', '', $villain_data['icon']);
                        $villain_url = (new moodle_url("/local/ascend_rewards/pix/villains/{$villain_icon_path}"))->out(false);
                        $villain_pet_icon = $pet_data ? str_replace('pets/', '', $pet_data['icon']) : '';
                        $villain_pet_url = $pet_data ? (new moodle_url("/local/ascend_rewards/pix/pets/{$villain_pet_icon}"))->out(false) : '';
                        $villain_avatar_filename = $villain_data['avatar'] ? str_replace(['.png', '.jpeg'], '', $villain_data['avatar']) : '';
                        $villain_avatar_circular_url = $villain_avatar_filename ? (new moodle_url("/local/ascend_rewards/pix/Avatars/circular avatars/{$villain_avatar_filename}.png"))->out(false) : '';
                    ?>
                    <div style="text-align:center;">
                        <?php if ($is_first_column): ?>
                        <h5 style="color:#06b6d4;font-size:14px;font-weight:600;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;height:20px;"><img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/villain.png'))->out(false); ?>" alt="Villains" style="width:16px;height:16px;object-fit:contain;vertical-align:middle;margin-right:4px;">Villains</h5>
                        <?php else: ?>
                        <div style="height:28px;"></div>
                        <?php endif; ?>
                        <div class="villain-card <?php echo $has_villain_unlocked ? 'owned' : 'locked'; ?>" 
                             data-villain-id="<?php echo $villain_id; ?>"
                             data-villain-name="<?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>"
                             data-villain-price="<?php echo $villain_data['price']; ?>"
                             data-villain-pet="<?php echo $villain_data['pet_id']; ?>"
                             data-villain-icon="<?php echo htmlspecialchars($villain_icon_path, ENT_QUOTES, 'UTF-8'); ?>"
                             data-can-unlock="<?php echo $can_unlock_villain ? '1' : '0'; ?>"
                             style="position:relative;aspect-ratio:1;cursor:<?php echo $can_unlock_villain && !$has_villain_unlocked ? 'pointer' : 'default'; ?>;transition:all 0.3s ease;">
                            <div style="position:relative;width:100%;height:100%;border-radius:50%;overflow:hidden;border:3px solid #06b6d4;">
                                <img src="<?php echo $villain_url; ?>" 
                                     alt="<?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>"
                                     style="width:100%;height:100%;object-fit:cover;filter:<?php echo $has_villain_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)'; ?>;">
                                
                                <?php if (!$can_unlock_villain): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);">
                                        <i class="fa-solid fa-lock" style="font-size:36px;color:#94a3b8;"></i>
                                    </div>
                                <?php elseif (!$has_villain_unlocked): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);flex-direction:column;gap:6px;">
                                        <img src="<?php echo $apex_stack_url; ?>" alt="Coins" style="width:<?php echo $config['coin_stack_size']; ?>;height:32px;object-fit:contain;">
                                        <div style="font-size:11px;color:#ff4444;font-weight:700;"><?php echo $villain_data['price']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($has_pet_unlocked && $villain_pet_url): ?>
                            <div style="position:absolute;top:-4px;right:-4px;width:26%;aspect-ratio:1;border-radius:50%;overflow:hidden;border:2px solid #ec4899;background:#1a1f2e;box-shadow:0 2px 8px rgba(0,0,0,0.5);z-index:10;display:flex;align-items:center;justify-content:center;">
                                <img src="<?php echo $villain_pet_url; ?>" alt="Pet" style="width:90%;height:90%;object-fit:contain;">
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($has_pet_unlocked && $villain_avatar_circular_url): ?>
                            <div style="position:absolute;top:-4px;left:-4px;width:26%;aspect-ratio:1;border-radius:50%;overflow:hidden;border:2px solid #FFD700;background:#1a1f2e;box-shadow:0 2px 8px rgba(0,0,0,0.5);z-index:10;display:flex;align-items:center;justify-content:center;">
                                <img src="<?php echo $villain_avatar_circular_url; ?>" alt="Avatar" style="width:90%;height:90%;object-fit:contain;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:8px;color:#06b6d4;font-size:13px;font-family:<?php echo $config['font_family']; ?>;">
                            <?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        
                        <?php 
                        // Check if this set is complete (hero + pet + villain all unlocked)
                        $set_complete = $is_unlocked && $has_pet_unlocked && $has_villain_unlocked;
                        if ($set_complete):
                            $avatar_key = pathinfo($avatar, PATHINFO_FILENAME);
                            $set_name = ucfirst($avatar_key);
                            $story_youtube_id = $story_videos[$avatar_key] ?? 'qxZUXNi7AJw';
                            $storybook_url = (new moodle_url('/local/ascend_rewards/pix/storybook.png'))->out(false);
                        ?>
                        <button class="watch-story-btn" 
                                data-set-name="<?php echo htmlspecialchars($set_name, ENT_QUOTES, 'UTF-8'); ?>"
                                data-youtube-id="<?php echo $story_youtube_id; ?>"
                                style="margin-top:12px;background:none;border:none;cursor:pointer;transition:all 0.3s;padding:0;display:flex;flex-direction:column;align-items:center;gap:8px;width:100%;">
                            <img src="<?php echo $storybook_url; ?>" 
                                 alt="Watch Story" 
                                 style="width:72px;height:72px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(6,182,212,0.5));transition:filter 0.3s;">
                            <span style="font-size:13px;color:#06b6d4;font-weight:700;font-family:<?php echo $config['font_family']; ?>;text-shadow:0 2px 4px rgba(6,182,212,0.3);">Watch Story</span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php endfor; ?>
        
        <?php
        // ============================================================================
        // EPIC LEVELS (7-9): Horizontal Columns
        // ============================================================================
        
        foreach ([7, 8, 9] as $epic_lv):
            $can_access_epic = in_array($epic_lv, $user_accessible_levels);
            $epic_avatars = $avatar_levels[$epic_lv] ?? [];
            
            // Gather all epic content for this level
            $epic_pets = [];
            foreach ($avatar_pets_catalog as $pet_id => $pet_data) {
                if ($pet_data['level'] === $epic_lv) {
                    $epic_pets[$pet_id] = $pet_data;
                }
            }
            
            $epic_villains = [];
            foreach ($villain_catalog as $villain_id => $villain_data) {
                if ($villain_data['level'] === $epic_lv) {
                    $epic_villains[$villain_id] = $villain_data;
                }
            }
            
            if (empty($epic_avatars) && empty($epic_pets) && empty($epic_villains)) continue;
        ?>
        
        <div style="margin-bottom:40px;padding-bottom:24px;border-bottom:2px solid rgba(168,85,247,0.2);">
            <h4 class="avatar-level-header" data-level="<?php echo $epic_lv; ?>" style="color:<?php echo $can_access_epic ? '#a855f7' : '#64748b'; ?>;font-size:18px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;transition:all 0.3s;">
                <i class="fa-solid fa-chevron-down level-chevron" style="font-size:14px;transition:transform 0.3s;"></i>
                ⚡ Epic Level <?php echo $epic_lv; ?> Collection
                <?php if (!$can_access_epic): ?>
                    <i class="fa-solid fa-lock" style="color:#64748b;"></i> Locked until Level <?php echo $epic_lv; ?>
                <?php endif; ?>
            </h4>
            
            <div class="avatar-level-content" data-level="<?php echo $epic_lv; ?>" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;opacity:<?php echo $can_access_epic ? '1' : '0.4'; ?>;pointer-events:<?php echo $can_access_epic ? 'auto' : 'none'; ?>;overflow:hidden;transition:max-height 0.3s ease, opacity 0.3s ease;max-height:5000px;">
                
                <!-- HERO COLUMN -->
                <div>
                    <h5 style="color:#FFD700;font-size:15px;font-weight:600;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px;text-align:center;"><img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/avatar.png'))->out(false); ?>" alt="Heroes" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:6px;">Heroes</h5>
                    <?php foreach ($epic_avatars as $avatar):
                        $avatar_name = pathinfo($avatar, PATHINFO_FILENAME);
                        $is_unlocked = in_array($avatar, $unlocked_avatars);
                        $avatar_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/{$avatar}"))->out(false);
                    ?>
                    <div style="text-align:center;margin-bottom:16px;">
                        <div class="avatar-card <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>" 
                             data-avatar="<?php echo $avatar; ?>"
                             data-name="<?php echo ucfirst($avatar_name); ?>"
                             data-level="<?php echo $epic_lv; ?>"
                             style="position:relative;aspect-ratio:1;border-radius:50%;overflow:hidden;cursor:pointer;border:3px solid #FFD700;transition:all 0.3s ease;">
                            <img src="<?php echo $avatar_url; ?>" 
                                 alt="<?php echo ucfirst($avatar_name); ?>" 
                                 style="width:100%;height:100%;object-fit:cover;filter:<?php echo $is_unlocked ? 'none' : 'grayscale(100%) brightness(0.3)'; ?>;">
                            <?php if (!$is_unlocked): ?>
                                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);">
                                    <i class="fa-solid fa-lock" style="font-size:48px;color:#94a3b8;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:8px;color:#FFD700;font-size:13px;font-family:<?php echo $config['font_family']; ?>;">
                            <?php echo ucfirst($avatar_name); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- PET COLUMN -->
                <div>
                    <h5 style="color:#ec4899;font-size:15px;font-weight:600;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px;text-align:center;"><img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/pets.png'))->out(false); ?>" alt="Pets" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:6px;">Pets</h5>
                    <?php foreach ($epic_pets as $pet_id => $pet_data):
                        $pet_name = $pet_data['name'];
                        $is_owned = in_array($pet_id, $owned_pets);
                        $pet_icon_path = str_replace('pets/', '', $pet_data['icon']);
                        $pet_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon_path}"))->out(false);
                        $avatar_filename = str_replace(['.png', '.jpeg'], '', $pet_data['avatar']);
                        $avatar_circular_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/circular avatars/{$avatar_filename}.png"))->out(false);
                        $pet_avatar_unlocked = in_array($pet_data['avatar'], $unlocked_avatars);
                    ?>
                    <div style="text-align:center;margin-bottom:16px;">
                        <div class="pet-card <?php echo $is_owned ? 'owned' : 'locked'; ?>" 
                             data-pet-id="<?php echo $pet_id; ?>"
                             data-pet-name="<?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>"
                             data-pet-price="<?php echo $pet_data['price']; ?>"
                             data-pet-avatar="<?php echo $pet_data['avatar']; ?>"
                             data-can-unlock="<?php echo $pet_avatar_unlocked ? '1' : '0'; ?>"
                             style="position:relative;aspect-ratio:1;cursor:<?php echo ($pet_avatar_unlocked && !$is_owned) ? 'pointer' : 'default'; ?>;transition:all 0.3s ease;">
                            <div style="position:relative;width:100%;height:100%;border-radius:50%;overflow:hidden;border:3px solid #ec4899;">
                                <img src="<?php echo $pet_url; ?>" 
                                     alt="<?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>"
                                     style="width:100%;height:100%;object-fit:cover;filter:<?php echo $is_owned ? 'none' : 'grayscale(100%) brightness(0.3)'; ?>;">
                                
                                <?php if (!$pet_avatar_unlocked): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);">
                                        <i class="fa-solid fa-lock" style="font-size:36px;color:#94a3b8;"></i>
                                    </div>
                                <?php elseif (!$is_owned): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);flex-direction:column;gap:6px;">
                                        <img src="<?php echo $apex_stack_url; ?>" alt="Coins" style="width:<?php echo $config['coin_stack_size']; ?>;height:32px;object-fit:contain;">
                                        <div style="font-size:11px;color:#FFD700;font-weight:700;"><?php echo $pet_data['price']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($pet_avatar_unlocked): ?>
                            <div style="position:absolute;top:-4px;right:-4px;width:28%;aspect-ratio:1;border-radius:50%;overflow:hidden;border:2px solid #FFD700;background:#1a1f2e;box-shadow:0 2px 8px rgba(0,0,0,0.5);z-index:10;display:flex;align-items:center;justify-content:center;">
                                <img src="<?php echo $avatar_circular_url; ?>" 
                                     alt="<?php echo htmlspecialchars($pet_data['avatar'], ENT_QUOTES, 'UTF-8'); ?>"
                                     style="width:90%;height:90%;object-fit:contain;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:8px;color:#ec4899;font-size:13px;font-family:<?php echo $config['font_family']; ?>;">
                            <?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- VILLAIN COLUMN -->
                <div>
                    <h5 style="color:#06b6d4;font-size:15px;font-weight:600;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px;text-align:center;"><img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/villain.png'))->out(false); ?>" alt="Villains" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:6px;">Villains</h5>
                    <?php foreach ($epic_villains as $villain_id => $villain_data):
                        $villain_name = $villain_data['name'];
                        $is_owned = in_array($villain_id, $owned_villains);
                        $villain_icon_path = str_replace('villains/', '', $villain_data['icon']);
                        $villain_url = (new moodle_url("/local/ascend_rewards/pix/villains/{$villain_icon_path}"))->out(false);
                        $pet_data = $villain_data['pet_id'] ? ($avatar_pets_catalog[$villain_data['pet_id']] ?? null) : null;
                        $pet_icon = $pet_data ? str_replace('pets/', '', $pet_data['icon']) : '';
                        $pet_url = $pet_data ? (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon}"))->out(false) : '';
                        $avatar_filename = $villain_data['avatar'] ? str_replace(['.png', '.jpeg'], '', $villain_data['avatar']) : '';
                        $avatar_circular_url = $avatar_filename ? (new moodle_url("/local/ascend_rewards/pix/Avatars/circular avatars/{$avatar_filename}.png"))->out(false) : '';
                        $villain_pet_owned = $villain_data['pet_id'] && in_array($villain_data['pet_id'], $owned_pets);
                    ?>
                    <div style="text-align:center;margin-bottom:16px;">
                        <div class="villain-card <?php echo $is_owned ? 'owned' : 'locked'; ?>" 
                             data-villain-id="<?php echo $villain_id; ?>"
                             data-villain-name="<?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>"
                             data-villain-price="<?php echo $villain_data['price']; ?>"
                             data-villain-pet="<?php echo $villain_data['pet_id']; ?>"
                             data-villain-icon="<?php echo htmlspecialchars($villain_icon_path, ENT_QUOTES, 'UTF-8'); ?>"
                             data-can-unlock="<?php echo $villain_pet_owned ? '1' : '0'; ?>"
                             style="position:relative;aspect-ratio:1;cursor:<?php echo $villain_pet_owned && !$is_owned ? 'pointer' : 'default'; ?>;transition:all 0.3s ease;">
                            <div style="position:relative;width:100%;height:100%;border-radius:50%;overflow:hidden;border:3px solid #06b6d4;">
                                <img src="<?php echo $villain_url; ?>" 
                                     alt="<?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>"
                                     style="width:100%;height:100%;object-fit:cover;filter:<?php echo $is_owned ? 'none' : 'grayscale(100%) brightness(0.3)'; ?>;">
                                
                                <?php if (!$villain_pet_owned): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);">
                                        <i class="fa-solid fa-lock" style="font-size:36px;color:#94a3b8;"></i>
                                    </div>
                                <?php elseif (!$is_owned): ?>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);flex-direction:column;gap:6px;">
                                        <img src="<?php echo $apex_stack_url; ?>" alt="Coins" style="width:<?php echo $config['coin_stack_size']; ?>;height:32px;object-fit:contain;">
                                        <div style="font-size:11px;color:#ff4444;font-weight:700;"><?php echo $villain_data['price']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($villain_pet_owned && $pet_url): ?>
                            <div style="position:absolute;bottom:-4px;left:-4px;width:26%;aspect-ratio:1;border-radius:50%;overflow:hidden;border:2px solid #ec4899;background:#1a1f2e;box-shadow:0 2px 8px rgba(0,0,0,0.5);z-index:10;display:flex;align-items:center;justify-content:center;">
                                <img src="<?php echo $pet_url; ?>" alt="Pet" style="width:90%;height:90%;object-fit:contain;">
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($villain_pet_owned && $avatar_circular_url): ?>
                            <div style="position:absolute;top:-4px;right:-4px;width:26%;aspect-ratio:1;border-radius:50%;overflow:hidden;border:2px solid #FFD700;background:#1a1f2e;box-shadow:0 2px 8px rgba(0,0,0,0.5);z-index:10;display:flex;align-items:center;justify-content:center;">
                                <img src="<?php echo $avatar_circular_url; ?>" alt="Avatar" style="width:90%;height:90%;object-fit:contain;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:8px;color:#06b6d4;font-size:13px;font-family:<?php echo $config['font_family']; ?>;">
                            <?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
            </div>
        </div>
        
        <?php endforeach; ?>
        
    </div>
</section>

<!-- ============================================================================
     STYLES
     ============================================================================ -->
<style>
/* Constrain card sizes to prevent image cutoff */
.avatar-card,
.pet-card,
.villain-card {
    max-width: 220px !important;
    margin: 0 auto !important;
}

.avatar-card.locked:hover,
.pet-card.locked:hover,
.villain-card.locked:hover {
    transform: scale(1.05);
}

.avatar-card.locked:hover { border-color: #ec4899; }
.pet-card.locked:hover { border-color: #FF9500; }
.villain-card.locked:hover { border-color: #dc2626; }
</style>

<!-- ============================================================================
     JAVASCRIPT: Click Handlers & Modal Triggers
     ============================================================================ -->
<script>
(function() {
    'use strict';
    
    // Video mapping
    const videoMap = {
        'amazon.png': 'amazon.mp4', 'elf.png': 'elf.mp4', 'ent.png': 'ent.mp4',
        'guardian.png': 'guardian.mp4', 'imp.png': 'imp.mp4', 'jester.png': 'jester.mp4',
        'magician.png': 'magician.mp4', 'mermaid.png': 'mermaid.mp4', 'nomad.png': 'nomad.mp4',
        'philosopher.png': 'philosopher.mp4', 'pirate.png': 'pirate.mp4', 'queen.png': 'queen.mp4',
        'sorceress.png': 'sorceress.mp4', 'viking.png': 'viking.mp4', 'warrior.png': 'warrior.mp4',
        'wizard.png': 'wizard.mp4', 'maori.png': 'maori.mp4', 'zulu.png': 'zulu.mp4',
        'sentinel.png': 'sentinel.mp4', 'kapu.png': 'kapu.mp4', 'beserker.png': 'beserker.mp4'
    };
    
    const petVideoMap = {
        100: 'lynx.mp4',
        101: 'tortoise.mp4',
        102: 'hamster.mp4',
        103: 'falcon.mp4',
        104: 'gryphon.mp4',
        105: 'boar.mp4',
        106: 'viper.mp4',
        107: 'swan.mp4',
        108: 'mischiefcap.mp4',
        109: 'otter.mp4',
        110: 'kinkajou.mp4',
        111: 'seahorse.mp4',
        112: 'dragon.mp4',
        113: 'mastiff.mp4',
        114: 'raven.mp4',
        115: 'tiger.mp4',
        116: 'wolf.mp4',
        117: 'polar_bear.mp4',
        200: 'cheetah_bros.mp4',
        201: 'monkey.mp4',
        202: 'heron.mp4'
    };
    
    const villainVideoMap = {
        'dryad_elf': 'elf_dryad.mp4',
        'blightmind_ent': 'ent_blightmind.mp4',
        'jester_mourner': 'jester_mourner.mp4',
        'magician_spellbreaker': 'magician_spellbreaker.mp4',
        'nomad_dune': 'nomad_dune.mp4',
        'philosopher_mirror': 'philosopher_mirror.mp4',
        'mermaid_duchess': 'mermaid_duchess.mp4',
        'warrior_warlord': 'warrior_warlord.mp4',
        'sorceress_stormveil': 'sorceress_stormveil.mp4',
        'gatekeeper_wraith': 'gatekeeper_wraith.mp4',
        'viking_betrayer': 'viking_betrayer.mp4',
        'pirate_barron': 'pirate_barron.mp4',
        'amazon_huntsmistress': 'amazon_huntsmistress.mp4',
        'imp_mole': 'imp_mole.mp4',
        'wizard_pale_scholar': 'wizard_pale_scholar.mp4',
        'maori_shaman': 'maori_shaman.mp4',
        'zulu_witchdoctor': 'zulu_witchdoctor.mp4',
        'sentinel_void': 'sentinel_void.mp4',
        'kapu_judge': 'kapu_judge.mp4'
    };
    
    // Hero unlock handlers
    document.querySelectorAll('.avatar-card.locked').forEach(card => {
        card.addEventListener('click', () => {
            const avatar = card.dataset.avatar;
            const name = card.dataset.name;
            const level = card.dataset.level;
            const videoFile = videoMap[avatar] || 'amazon.mp4';
            showAvatarUnlockModal(name, avatar, videoFile, level, <?php echo $tokens_available; ?>);
        });
    });
    
    // Hero review handlers
    document.querySelectorAll('.avatar-card.unlocked').forEach(card => {
        card.addEventListener('click', () => {
            const avatar = card.dataset.avatar;
            const name = card.dataset.name;
            const videoFile = videoMap[avatar] || 'amazon.mp4';
            showAvatarReviewModal(name, avatar, videoFile);
        });
    });
    
    // Pet unlock handlers
    document.querySelectorAll('.pet-card.locked').forEach(card => {
        card.addEventListener('click', () => {
            // Only show modal if hero is unlocked (can_unlock = 1)
            if (card.dataset.canUnlock !== '1') {
                return; // Hero not unlocked, do nothing
            }
            const petId = card.dataset.petId;
            const petName = card.dataset.petName;
            const petPrice = parseInt(card.dataset.petPrice);
            const petVideo = petVideoMap[petId] || 'lynx.mp4';
            showPetUnlockModal(petId, petName, petPrice, petVideo, <?php echo $tokens_available; ?>, <?php echo $coin_balance; ?>);
        });
    });
    
    // Pet review handlers
    document.querySelectorAll('.pet-card.owned').forEach(card => {
        card.addEventListener('click', () => {
            const petId = card.dataset.petId;
            const petName = card.dataset.petName;
            const petVideo = petVideoMap[petId] || 'lynx.mp4';
            showPetReviewModal(petName, petId, petVideo);
        });
    });
    
    // Villain unlock handlers
    document.querySelectorAll('.villain-card.locked').forEach(card => {
        card.addEventListener('click', () => {
            // Only show modal if pet is unlocked (can_unlock = 1)
            if (card.dataset.canUnlock !== '1') {
                return; // Pet not unlocked, do nothing
            }
            const villainId = card.dataset.villainId;
            const villainName = card.dataset.villainName;
            const villainPrice = card.dataset.villainPrice;
            const villainIcon = card.dataset.villainIcon;
            const villainImageName = villainIcon.replace('villains/', '').replace('.png', '');
            const villainVideo = villainVideoMap[villainImageName] || villainImageName + '.mp4';
            showVillainUnlockModal(villainId, villainName, villainPrice, villainImageName, villainVideo, <?php echo $tokens_available; ?>, <?php echo $coin_balance; ?>);
        });
    });
    
    // Villain review handlers
    document.querySelectorAll('.villain-card.owned').forEach(card => {
        card.addEventListener('click', () => {
            const villainId = card.dataset.villainId;
            const villainName = card.dataset.villainName;
            const villainIcon = card.dataset.villainIcon;
            const villainImageName = villainIcon.replace('villains/', '').replace('.png', '');
            const villainVideo = villainVideoMap[villainImageName] || villainImageName + '.mp4';
            showVillainReviewModal(villainName, villainId, villainImageName, villainVideo);
        });
    });
    
    // ============================================================================
    // LEVEL COLLAPSE/EXPAND FUNCTIONALITY
    // ============================================================================
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCollapse);
    } else {
        initializeCollapse();
    }
    
    function initializeCollapse() {
        // Load collapsed state from localStorage, default all levels to collapsed
        let collapsedLevels = JSON.parse(localStorage.getItem('apexCollapsedLevels') || 'null');
        
        // If no saved state exists, collapse all levels by default
            if (collapsedLevels === null) {
            	collapsedLevels = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
            localStorage.setItem('apexCollapsedLevels', JSON.stringify(collapsedLevels));
        }
        
        // Ensure all level content divs have the correct initial state
        document.querySelectorAll('.avatar-level-content').forEach(content => {
            const level = content.getAttribute('data-level');
            const header = document.querySelector(`.avatar-level-header[data-level="${level}"]`);
            const chevron = header?.querySelector('.level-chevron');
            
            if (collapsedLevels.includes(level)) {
                // This level should be collapsed
                content.style.maxHeight = '0px';
                content.style.opacity = '0';
                content.style.pointerEvents = 'none';
                content.style.marginBottom = '0';
                if (chevron) {
                    chevron.style.transform = 'rotate(-90deg)';
                }
            } else {
                // This level should be expanded
                content.style.maxHeight = '5000px';
                content.style.opacity = '1';
                content.style.pointerEvents = 'auto';
                content.style.marginBottom = '';
                if (chevron) {
                    chevron.style.transform = 'rotate(0deg)';
                }
            }
        });
        
        // Add click handlers to all level headers
        document.querySelectorAll('.avatar-level-header').forEach(header => {
            // Add hover effect
            header.addEventListener('mouseenter', () => {
                header.style.opacity = '0.8';
            });
            header.addEventListener('mouseleave', () => {
                header.style.opacity = '1';
            });
            
            // Toggle collapse/expand on click
            header.addEventListener('click', () => {
                const level = header.dataset.level;
                const content = document.querySelector(`.avatar-level-content[data-level="${level}"]`);
                const chevron = header.querySelector('.level-chevron');
                
                if (!content) return;
                
                const isCollapsed = content.style.maxHeight === '0px' || content.style.maxHeight === '0';
                
                if (isCollapsed) {
                    // Expand
                    content.style.maxHeight = '5000px';
                    content.style.opacity = '1';
                    content.style.pointerEvents = 'auto';
                    content.style.marginBottom = '';
                    if (chevron) {
                        chevron.style.transform = 'rotate(0deg)';
                    }
                    
                    // Remove from collapsed list
                    collapsedLevels = collapsedLevels.filter(l => l !== level);
                } else {
                    // Collapse
                    content.style.maxHeight = '0px';
                    content.style.opacity = '0';
                    content.style.pointerEvents = 'none';
                    content.style.marginBottom = '0';
                    if (chevron) {
                        chevron.style.transform = 'rotate(-90deg)';
                    }
                    
                    // Add to collapsed list
                    if (!collapsedLevels.includes(level)) {
                        collapsedLevels.push(level);
                    }
                }
                
                // Save to localStorage
                localStorage.setItem('apexCollapsedLevels', JSON.stringify(collapsedLevels));
            });
        });
    }
})();

// ============================================================================
// STORY VIDEO MODAL
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const storyButtons = document.querySelectorAll('.watch-story-btn');
    const storyModal = document.getElementById('apexStoryModal');
    const storyBackdrop = document.getElementById('apexStoryBackdrop');
    const storyClose = document.getElementById('apexStoryClose');
    const storyIframe = document.getElementById('apexStoryIframe');
    const storyTitle = document.getElementById('apexStoryTitle');
    
    console.log('Story buttons found:', storyButtons.length);
    console.log('Story modal exists:', !!storyModal);
    
    if (storyButtons.length > 0 && storyModal) {
        storyButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Story button clicked!');
                const setName = this.dataset.setName;
                const youtubeId = this.dataset.youtubeId;
                
                console.log('Set name:', setName, 'YouTube ID:', youtubeId);
                
                // Set title
                storyTitle.textContent = setName + ' Story';
                
                // Set YouTube iframe
                storyIframe.src = 'https://www.youtube.com/embed/' + youtubeId + '?autoplay=1&rel=0';
                
                // Show modal
                storyModal.style.display = 'flex';
                storyBackdrop.style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
        });
        
        function closeStoryModal() {
            storyModal.style.display = 'none';
            storyBackdrop.style.display = 'none';
            document.body.style.overflow = '';
            
            // Stop video by clearing iframe src
            storyIframe.src = '';
        }
        
        if (storyClose) {
            storyClose.addEventListener('click', closeStoryModal);
        }
        
        if (storyBackdrop) {
            storyBackdrop.addEventListener('click', closeStoryModal);
        }
    } else {
        console.log('Story system not initialized - no complete sets or modal not found');
    }
});
</script>

<!-- Story Video Modal -->
<div id="apexStoryBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9998;"></div>
<div id="apexStoryModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#01142E;border-radius:16px;max-width:900px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);border:2px solid #FFD700;overflow:hidden;">
        <div style="padding:20px;border-bottom:2px solid rgba(255,215,0,0.2);display:flex;justify-content:space-between;align-items:center;">
            <h3 id="apexStoryTitle" style="color:#FFD700;font-size:20px;font-weight:700;margin:0;font-family:'Uncial Antiqua',serif;">Story</h3>
            <button id="apexStoryClose" style="background:none;border:none;color:#94a3b8;font-size:28px;cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;transition:color 0.2s;">&times;</button>
        </div>
        <div style="padding:20px;padding-top:0;">
            <div style="position:relative;width:100%;padding-bottom:56.25%;background:#000;border-radius:8px;overflow:hidden;margin-top:16px;">
                <iframe id="apexStoryIframe" 
                        src="" 
                        style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
        </div>
    </div>
</div>

<?php
// ===== END ASCEND UNIVERSE SECTION =====
?>
