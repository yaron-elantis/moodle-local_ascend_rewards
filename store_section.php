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
 * APEX REWARDS - STORE SECTION
 * Pets, Villains, Power-ups, and Mystery Box System
 */

defined('MOODLE_INTERNAL') || die();

// Get user's level for unlocking
$user_level = $level;

// Build pet list - items the user can still purchase
$available_pets = [];
$available_villains = [];

// Check pets
foreach ($avatar_pets_catalog as $pet_id => $pet_data) {
    // Only show pets for levels user can access
    if ($pet_data['level'] > $user_level) continue;
    
    // Check if user has avatar for this pet
    $avatar_unlocked = in_array($pet_data['avatar'], $unlocked_avatars);
    if (!$avatar_unlocked) continue;
    
    // Check if user already owns pet
    if (in_array($pet_id, $owned_pets)) continue;
    
    $available_pets[$pet_id] = $pet_data;
    $available_pets[$pet_id]['avatar_unlocked'] = true;
}

// Check villains
foreach ($villain_catalog as $villain_id => $villain_data) {
    // Only show villains for levels user can access
    if ($villain_data['level'] > $user_level) continue;
    
    // Check if user has pet for this villain
    $pet_owned = in_array($villain_data['pet_id'], $owned_pets);
    if (!$pet_owned) continue;
    
    // Check if user already owns villain
    if (in_array($villain_id, $owned_villains)) continue;
    
    $available_villains[$villain_id] = $villain_data;
    $available_villains[$villain_id]['pet_owned'] = true;
}

// Set up coin stack image
$apex_stack_url = (new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png'))->out(false);
?>

<!-- Fonts for store -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Uncial+Antiqua&display=swap" rel="stylesheet">

<!-- ========== ASCEND STORE SECTION ========== -->
<section class="aa-panel" id="a_store">
    <div class="aa-panel-head">
        <h3>
            <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/store.png'))->out(false); ?>" 
                 alt="Store" 
                 style="width:60px;height:60px;vertical-align:middle;margin-right:8px;">
            Ascend Store 
            <span style="color:#ec4899;font-size:14px;font-weight:600;margin-left:8px;">(<?php echo number_format($coin_balance); ?> coins available)</span>
        </h3>
        <i class="fa-solid fa-chevron-down"></i>
    </div>
    
    <div class="aa-panel-content">
        <!-- XP Multiplier Active Notice -->
        <?php if ($xp_multiplier_active): ?>
            <div style="background:linear-gradient(135deg,#ec4899,#FF00AA);border-radius:12px;padding:20px;margin-bottom:20px;text-align:center;">
                <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:8px;">🔥 XP Multiplier Active!</div>
                <div style="font-size:14px;color:rgba(255,255,255,0.9);">You're earning 2x XP! Expires in: <strong id="xpMultiplierTimer_store" data-expires="<?php echo $xp_multiplier_expires; ?>"></strong></div>
            </div>
        <?php endif; ?>
        
        <!-- ============================================================================
             POWER-UPS & MYSTERY BOX SECTION
             ============================================================================ -->
        <div style="margin-bottom:40px;padding-bottom:24px;border-bottom:2px solid rgba(255,255,255,0.1);">
            <h4 style="color:#00D4FF;font-size:18px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
                ⚡ Power-Ups & Mystery Items
            </h4>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
                <?php 
                // Get user inventory
                $inventory_str = get_user_preferences('ascend_store_inventory', '', $USER->id);
                $inventory = $inventory_str ? json_decode($inventory_str, true) : [];
                
                // XP Multiplier
                $xp_item = [
                    'id' => 4,
                    'name' => 'XP Multiplier (24h)',
                    'description' => 'Double your XP gains for 24 hours! Activate after purchase.',
                    'price' => 1000,
                    'icon' => 'ai_streak.png',
                    'category' => 'power-ups'
                ];
                $can_afford = $coin_balance >= $xp_item['price'];
                $in_inventory = isset($inventory[4]) && $inventory[4] > 0;
                $inventory_count = $in_inventory ? $inventory[4] : 0;
                ?>
                <div class="store-card" style="background:linear-gradient(135deg,#01142E,#010828);border:2px solid <?php echo $can_afford ? '#00D4FF' : 'rgba(255,255,255,0.1)'; ?>;border-radius:12px;padding:24px;transition:all 0.3s ease;">
                    <div style="text-align:center;margin-bottom:20px;">
                        <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/ai_streak.png'))->out(false); ?>" 
                             alt="XP Multiplier" 
                             style="width:120px;height:120px;object-fit:contain;margin-bottom:12px;filter:drop-shadow(0 4px 12px rgba(0,212,255,0.3));">
                        <div style="font-size:18px;font-weight:700;color:#e6e9f0;margin-bottom:8px;">XP Multiplier (24h)</div>
                        <div style="font-size:14px;color:#94a3b8;">Double your XP gains for 24 hours!</div>
                    </div>
                    
                    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:16px;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;">
                            <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false); ?>" 
                                 alt="Coins" 
                                 style="width:28px;height:28px;">
                            <span style="font-size:24px;font-weight:800;color:#FFD700;">1,000</span>
                        </div>
                        
                        <?php if (!$can_afford && !$in_inventory): ?>
                            <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;">
                                Not Enough Coins
                            </button>
                        <?php else: ?>
                            <?php if (!$in_inventory): ?>
                                <button class="store-buy-btn store-btn" 
                                    data-item-id="4"
                                    data-item-name="XP Multiplier"
                                    data-item-price="100"
                                    style="width:100%;background:linear-gradient(135deg,#00D4FF,#06b6d4);border:none;color:#01142E;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(0,212,255,0.4);">
                                    💰 Purchase for 100 Coins
                                </button>
                            <?php else: ?>
                                <button class="store-activate-btn" 
                                    data-item-id="4"
                                    data-item-name="XP Multiplier"
                                    style="width:100%;background:linear-gradient(135deg,#FFD700,#FFA500);border:none;color:#01142E;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(255,215,0,0.4);">
                                    ⚡ Activate (<?php echo $inventory_count; ?> available)
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Mystery Box Card -->
                <?php 
                $mystery_price = 50;
                $can_afford_mystery = $coin_balance >= $mystery_price;
                ?>
                <div class="store-card" style="background:linear-gradient(135deg,#01142E,#010828);border:2px solid <?php echo $can_afford_mystery ? '#FFD700' : 'rgba(255,255,255,0.1)'; ?>;border-radius:12px;padding:24px;transition:all 0.3s ease;">
                    <div style="text-align:center;margin-bottom:20px;">
                        <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/mystery_box.png'))->out(false); ?>" alt="Mystery Box" style="width:120px;height:120px;object-fit:contain;margin-bottom:12px;filter:drop-shadow(0 4px 12px rgba(255,215,0,0.3));">
                        <div style="font-size:18px;font-weight:700;color:#e6e9f0;margin-bottom:8px;">Mystery Box</div>
                        <div style="font-size:14px;color:#94a3b8;">Unwrap a random surprise reward!</div>
                    </div>
                    
                    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:16px;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;">
                            <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_coin_main.png'))->out(false); ?>" 
                                 alt="Coins" 
                                 style="width:28px;height:28px;">
                            <span style="font-size:24px;font-weight:800;color:#FFD700;">50</span>
                        </div>
                        
                        <?php if (!$can_afford_mystery): ?>
                            <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;">
                                Not Enough Coins
                            </button>
                        <?php else: ?>
                            <button class="mysterybox-open-btn store-btn" 
                                data-price="50"
                                style="width:100%;background:linear-gradient(135deg,#FFD700,#FFA500);border:none;color:#01142E;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(255,215,0,0.4);">
                                🎁 Open Mystery Box for 50 Coins
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================================
             PETS SECTION
             ============================================================================ -->
        <div style="margin-bottom:40px;padding-bottom:24px;border-bottom:2px solid rgba(255,255,255,0.1);">
            <h4 style="color:#ec4899;font-size:18px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
                <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/pets.png'))->out(false); ?>" alt="Pet" style="width:28px;height:28px;object-fit:contain;">
                Pets
            </h4>
            
            <?php if (empty($available_pets)): ?>
                <div style="background:rgba(236,72,153,0.1);border:2px dashed #ec4899;border-radius:12px;padding:32px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:16px;">🔒</div>
                    <div style="font-size:18px;font-weight:700;color:#e6e9f0;margin-bottom:12px;">No Pets Available for Adoption</div>
                    <div style="font-size:14px;color:#94a3b8;margin-bottom:16px;">
                        To unlock pets, you must first unlock an avatar on your current level. Once you unlock a hero, their companion pet becomes available for adoption!
                    </div>
                    <div style="font-size:13px;color:#64748b;">
                        💡 Tip: Head to the <strong>Ascend Universe</strong> section above to unlock your first hero, then their pet will be ready to adopt.
                    </div>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
                    <?php foreach ($available_pets as $pet_id => $pet_data):
                        $pet_name = $pet_data['name'];
                        $pet_price = $pet_data['price'];
                        $pet_icon_path = str_replace('pets/', '', $pet_data['icon']);
                        $pet_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$pet_icon_path}"))->out(false);
                        $can_afford_pet = $coin_balance >= $pet_price;
                        // level color mapping - ALL PETS GET PINK BORDER
                        $level_color = '#ec4899'; // Pink for all pets

                        // Pet avatar circular badge (if avatar unlocked for this pet)
                        $pet_avatar_circular_url = '';
                        if (!empty($pet_data['avatar']) && in_array($pet_data['avatar'], $unlocked_avatars)) {
                            $pet_avatar_filename = str_replace(['.png', '.jpeg'], '', $pet_data['avatar']);
                            $pet_avatar_circular_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/circular avatars/{$pet_avatar_filename}.png"))->out(false);
                        }
                    ?>
                    <div class="store-card" style="background:linear-gradient(135deg,#01142E,#010828);border:2px solid <?php echo $can_afford_pet ? '#ec4899' : 'rgba(255,255,255,0.1)'; ?>;border-radius:12px;padding:24px;transition:all 0.3s ease;">
                        <div style="text-align:center;margin-bottom:20px;">
                            <div class="store-pet-frame" role="button" tabindex="0" aria-label="Adopt <?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?> for <?php echo number_format($pet_price); ?> coins" style="width:120px;height:120px;margin:0 auto;border-radius:50%;overflow:hidden;border:3px solid <?php echo $level_color; ?>;box-shadow:0 6px 18px rgba(0,0,0,0.5);">
                                <img src="<?php echo $pet_url; ?>" 
                                     alt="<?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>" 
                                     style="width:100%;height:100%;object-fit:contain;display:block;filter:drop-shadow(0 6px 20px rgba(255,149,0,0.25));">
                            </div>
                            <div style="font-size:18px;font-weight:700;color:#e6e9f0;margin-top:12px;margin-bottom:6px;font-family:'Uncial Antiqua',serif;">
                                <?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div style="font-size:13px;color:#94a3b8;margin-bottom:12px;">Level <?php echo $pet_data['level']; ?> Pet</div>
                            
                            <?php if (!empty($pet_avatar_circular_url)): ?>
                                <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px;">
                                    <div style="font-size:12px;color:#94a3b8;">Linked Hero:</div>
                                    <div style="width:40px;height:40px;border-radius:50%;overflow:hidden;border:2px solid #FFD700;box-shadow:0 2px 8px rgba(255,215,0,0.3);">
                                        <img src="<?php echo $pet_avatar_circular_url; ?>" alt="Hero" style="width:100%;height:100%;object-fit:cover;">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:16px;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;">
                                <img src="<?php echo $apex_stack_url; ?>" 
                                     alt="Coins" 
                                     style="width:32px;height:32px;object-fit:contain;">
                                <span style="font-size:24px;font-weight:800;color:#FFD700;"><?php echo number_format($pet_price); ?></span>
                            </div>
                            
                            <?php if (!$can_afford_pet): ?>
                                <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;">
                                    Not Enough Coins
                                </button>
                            <?php else: ?>
                                <button class="pet-buy-btn store-btn" 
                                        data-item-id="<?php echo $pet_id; ?>"
                                        data-item-name="<?php echo htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-item-price="<?php echo $pet_price; ?>"
                                        style="width:100%;background:linear-gradient(135deg,#ec4899,#db2777);border:none;color:#fff;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(236,72,153,0.4);">
                                    🐾 Adopt for <?php echo number_format($pet_price); ?> Coins
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ============================================================================
             VILLAINS SECTION
             ============================================================================ -->
        <div style="margin-bottom:40px;padding-bottom:24px;border-bottom:2px solid rgba(255,255,255,0.1);">
            <h4 style="color:#06b6d4;font-size:18px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
                <img src="<?php echo (new moodle_url('/local/ascend_rewards/pix/villain.png'))->out(false); ?>" alt="Villain" style="width:28px;height:28px;object-fit:contain;">
                Villains
            </h4>
            
            <?php if (empty($available_villains)): ?>
                <div style="background:rgba(6,182,212,0.1);border:2px dashed #06b6d4;border-radius:12px;padding:32px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:16px;">🔒</div>
                    <div style="font-size:18px;font-weight:700;color:#e6e9f0;margin-bottom:12px;">No Villains Available to be Unleashed</div>
                    <div style="font-size:14px;color:#94a3b8;margin-bottom:16px;">
                        To unlock villains, you must first unlock a pet. Once you adopt a pet, its arch-nemesis villain becomes available for unleashing!
                    </div>
                    <div style="font-size:13px;color:#64748b;">
                        💡 Tip: Head to the <strong>Pets</strong> section above to adopt your first companion, then their villain will be ready to unleash.
                    </div>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
                    <?php foreach ($available_villains as $villain_id => $villain_data):
                        $villain_name = $villain_data['name'];
                        $villain_price = $villain_data['price'];
                        $villain_icon_path = str_replace('villains/', '', $villain_data['icon']);
                        $villain_url = (new moodle_url("/local/ascend_rewards/pix/villains/{$villain_icon_path}"))->out(false);
                        $can_afford_villain = $coin_balance >= $villain_price;
                        $villain_level = isset($villain_data['level']) ? (int)$villain_data['level'] : 1;
                        // Villains always have cyan borders
                        $villain_color = '#06b6d4';

                        // Determine avatar circular badge for villain (use villain avatar if set, otherwise fallback to the pet's avatar)
                        $villain_avatar_name = '';
                        if (!empty($villain_data['avatar'])) {
                            $villain_avatar_name = $villain_data['avatar'];
                        } else {
                            $villain_pet_info = $avatar_pets_catalog[$villain_data['pet_id']] ?? null;
                            if ($villain_pet_info && !empty($villain_pet_info['avatar'])) {
                                $villain_avatar_name = $villain_pet_info['avatar'];
                            }
                        }
                        $villain_avatar_circular_url = '';
                        if ($villain_avatar_name && in_array($villain_avatar_name, $unlocked_avatars)) {
                            $villain_avatar_filename = str_replace(['.png', '.jpeg'], '', $villain_avatar_name);
                            $villain_avatar_circular_url = (new moodle_url("/local/ascend_rewards/pix/Avatars/circular avatars/{$villain_avatar_filename}.png"))->out(false);
                        }
                        // Pet badge (show small pet image if available)
                        $villain_pet_badge_url = '';
                        if (!empty($villain_data['pet_id'])) {
                            $vp = $avatar_pets_catalog[$villain_data['pet_id']] ?? null;
                            if ($vp) {
                                $vp_icon = str_replace('pets/', '', $vp['icon']);
                                $villain_pet_badge_url = (new moodle_url("/local/ascend_rewards/pix/pets/{$vp_icon}"))->out(false);
                            }
                        }
                    ?>
                    <div class="store-card" style="background:linear-gradient(135deg,#01142E,#010828);border:2px solid <?php echo $can_afford_villain ? '#06b6d4' : 'rgba(255,255,255,0.1)'; ?>;border-radius:12px;padding:24px;transition:all 0.3s ease;">
                        <div style="text-align:center;margin-bottom:20px;">
                            <div class="store-villain-frame" role="button" tabindex="0" aria-label="Unleash <?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?> for <?php echo number_format($villain_price); ?> coins" style="width:120px;height:120px;margin:0 auto;border-radius:50%;overflow:hidden;border:3px solid <?php echo $villain_color; ?>;box-shadow:0 6px 18px rgba(0,0,0,0.5);">
                                <img src="<?php echo $villain_url; ?>" 
                                     alt="<?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>" 
                                     style="width:100%;height:100%;object-fit:contain;display:block;filter:drop-shadow(0 6px 20px rgba(220,38,38,0.25));">
                            </div>
                            <div style="font-size:18px;font-weight:700;color:#e6e9f0;margin-top:12px;margin-bottom:6px;font-family:'Uncial Antiqua',serif;">
                                <?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div style="font-size:13px;color:#94a3b8;margin-bottom:12px;">Level <?php echo $villain_data['level']; ?> Villain</div>
                            
                            <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:8px;">
                                <?php if (!empty($villain_avatar_circular_url)): ?>
                                    <div style="text-align:center;">
                                        <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Hero</div>
                                        <div style="width:40px;height:40px;border-radius:50%;overflow:hidden;border:2px solid #FFD700;box-shadow:0 2px 8px rgba(255,215,0,0.3);">
                                            <img src="<?php echo $villain_avatar_circular_url; ?>" alt="Hero" style="width:100%;height:100%;object-fit:cover;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($villain_pet_badge_url) && isset($villain_data['pet_id'])): ?>
                                    <div style="text-align:center;">
                                        <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Pet</div>
                                        <div style="width:40px;height:40px;border-radius:50%;overflow:hidden;border:2px solid #ec4899;box-shadow:0 2px 8px rgba(236,72,153,0.3);">
                                            <img src="<?php echo $villain_pet_badge_url; ?>" alt="Pet" style="width:100%;height:100%;object-fit:cover;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:16px;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;">
                                <img src="<?php echo $apex_stack_url; ?>" 
                                     alt="Coins" 
                                     style="width:32px;height:32px;object-fit:contain;">
                                <span style="font-size:24px;font-weight:800;color:#FFD700;"><?php echo number_format($villain_price); ?></span>
                            </div>
                            
                            <?php if (!$can_afford_villain): ?>
                                <button disabled style="width:100%;background:#4b5563;border:none;color:#94a3b8;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:not-allowed;">
                                    Not Enough Coins
                                </button>
                            <?php else: ?>
                                <button class="villain-buy-btn store-btn" 
                                        data-item-id="<?php echo $villain_id; ?>"
                                        data-item-name="<?php echo htmlspecialchars($villain_name, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-item-price="<?php echo $villain_price; ?>"
                                        style="width:100%;background:linear-gradient(135deg,#06b6d4,#0891b2);border:none;color:#fff;padding:12px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(6,182,212,0.4);">
                                    😈 Unleash for <?php echo number_format($villain_price); ?> Coins
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        

    </div>
</section>

<!-- Mystery Box Animation Modal -->
<div id="mysteryBoxModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:linear-gradient(135deg,#01142E,#010828);border:3px solid #FFD700;border-radius:20px;padding:24px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(255,215,0,0.3);">
        <!-- 4 Mystery Boxes Container -->
        <div id="boxesContainer" style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:40px;">
            <?php $box_img = (new moodle_url('/local/ascend_rewards/pix/mystery_box.png'))->out(false); ?>
            <div class="mystery-box" data-box="1" style="cursor:pointer;opacity:1;transition:all 0.3s;transform:scale(1);">
                <div style="height:120px;display:flex;align-items:center;justify-content:center;border-radius:15px;">
                    <img src="<?php echo s($box_img); ?>" alt="Mystery Box" style="width:110px;height:110px;object-fit:contain;">
                </div>
                <div style="text-align:center;margin-top:10px;color:#FFD700;font-weight:700;font-size:14px;">Box 1</div>
            </div>
            <div class="mystery-box" data-box="2" style="cursor:pointer;opacity:1;transition:all 0.3s;transform:scale(1);">
                <div style="height:120px;display:flex;align-items:center;justify-content:center;border-radius:15px;">
                    <img src="<?php echo s($box_img); ?>" alt="Mystery Box" style="width:110px;height:110px;object-fit:contain;">
                </div>
                <div style="text-align:center;margin-top:10px;color:#FFD700;font-weight:700;font-size:14px;">Box 2</div>
            </div>
            <div class="mystery-box" data-box="3" style="cursor:pointer;opacity:1;transition:all 0.3s;transform:scale(1);">
                <div style="height:120px;display:flex;align-items:center;justify-content:center;border-radius:15px;">
                    <img src="<?php echo s($box_img); ?>" alt="Mystery Box" style="width:110px;height:110px;object-fit:contain;">
                </div>
                <div style="text-align:center;margin-top:10px;color:#FFD700;font-weight:700;font-size:14px;">Box 3</div>
            </div>
            <div class="mystery-box" data-box="4" style="cursor:pointer;opacity:1;transition:all 0.3s;transform:scale(1);">
                <div style="height:120px;display:flex;align-items:center;justify-content:center;border-radius:15px;">
                    <img src="<?php echo s($box_img); ?>" alt="Mystery Box" style="width:110px;height:110px;object-fit:contain;">
                </div>
                <div style="text-align:center;margin-top:10px;color:#FFD700;font-weight:700;font-size:14px;">Box 4</div>
            </div>
        </div>
        
        <!-- Result Display -->
        <div id="resultDisplay" style="display:none;text-align:center;">
            <div style="margin-bottom:16px;" id="resultIcon"></div>
            <div style="font-size:18px;color:#e6e9f0;font-weight:700;margin-bottom:12px;opacity:0;transition:opacity 0.5s ease;" id="resultMessage"></div>
            <button id="closeModalBtn" style="background:linear-gradient(135deg,#FFD700,#FFA500);border:none;color:#01142E;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin-top:12px;opacity:0;transition:opacity 0.5s ease;">Continue</button>
        </div>
    </div>
</div>

<style>
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes bounceIn {
    0% {
        opacity: 0;
        transform: scale(0.3);
    }
    50% {
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-slide-in {
    animation: slideInUp 0.8s ease-out forwards;
}

.animate-bounce-in {
    animation: bounceIn 0.8s ease-out forwards;
}
.aa-panel:hover {
    box-shadow: 0 8px 24px rgba(6,182,212,0.2);
}

@keyframes boxSpin {
    0% { transform: rotateY(0deg) scale(1); }
    50% { transform: rotateY(180deg) scale(1.05); }
    100% { transform: rotateY(360deg) scale(1); }
}

@keyframes boxHop {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.mystery-box {
    animation: boxHop 0.5s ease-in-out infinite !important;
}

.mystery-box.spinning {
    animation: boxSpin 0.6s ease-in-out infinite !important;
}

.mystery-box.selected {
    animation: none !important;
    transform: scale(1.1) !important;
}

.store-pet-frame:hover,
.store-villain-frame:hover {
    transform: scale(1.06);
}

.store-pet-frame img,
.store-villain-frame img {
    transition: transform 0.25s ease, filter 0.25s ease;
}

.store-pet-frame:hover img,
.store-villain-frame:hover img {
    transform: scale(1.03);
    filter: none;
}

/* Modern unified store styles */
.store-card {
    background: linear-gradient(180deg, rgba(6,10,20,0.6), rgba(3,6,12,0.6));
    border-radius: 14px;
    padding: 22px;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    backdrop-filter: blur(6px);
}
.store-card:hover { transform: translateY(-6px); box-shadow: 0 18px 40px rgba(2,6,23,0.6); }

.store-btn {
    display: inline-block;
    width: 100%;
    padding: 12px 14px !important;
    border-radius: 10px !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    cursor: pointer !important;
    transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
}
.store-btn:active { transform: translateY(1px); }
.store-btn[disabled] { opacity: 0.6; cursor: not-allowed !important; }

.store-title { font-family: 'Uncial Antiqua', serif; font-size: 18px; font-weight: 700; color: #e6e9f0; }
.store-sub { color: #94a3b8; font-size: 13px; }

.store-price { display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:8px }
.store-price .amount { font-size:22px;font-weight:800;color:#FFD700 }

.corner-badge { position:absolute;width:36px;height:36px;border-radius:50%;overflow:hidden;border:2px solid rgba(255,255,255,0.06);background:#1a1f2e;box-shadow:0 2px 8px rgba(0,0,0,0.5);z-index:12 }
.corner-badge img { width:100%;height:100%;object-fit:cover }

@media (max-width:720px) { .store-price .amount { font-size:18px } .store-card { padding:16px } }
</style>

<script src="<?php echo (new moodle_url('/local/ascend_rewards/avatar_modals.js'))->out(false); ?>"></script>

<script>
(function() {
    'use strict';
    
    // XP Multiplier Timer (Store Section)
    var timerEl = document.getElementById('xpMultiplierTimer_store');
    if (timerEl) {
        function updateTimer() {
            var expiresAt = parseInt(timerEl.getAttribute('data-expires'));
            var now = Math.floor(Date.now() / 1000);
            var remaining = expiresAt - now;
            
            if (remaining <= 0) {
                timerEl.textContent = 'Expired';
                return;
            }
            
            var hours = Math.floor(remaining / 3600);
            var minutes = Math.floor((remaining % 3600) / 60);
            var seconds = remaining % 60;
            
            timerEl.textContent = hours + 'h ' + minutes + 'm ' + seconds + 's';
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    }
    
    // Store Purchase Handler (exclude pets and villains - they have their own handlers)
    var storeBuyBtns = document.querySelectorAll('.store-buy-btn:not(.pet-buy-btn):not(.villain-buy-btn)');
    storeBuyBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var itemId = btn.getAttribute('data-item-id');
            var itemName = btn.getAttribute('data-item-name');
            var itemPrice = btn.getAttribute('data-item-price');
            
            if (!confirm('Purchase ' + itemName + ' for ' + itemPrice + ' coins?')) {
                return;
            }
            
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/store_purchase.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var result = JSON.parse(xhr.responseText);
                        if (result.success) {
                            alert('✅ Purchase successful!\n\nRemaining balance: ' + result.remaining_coins.toLocaleString() + ' coins');
                            location.reload();
                        } else {
                            alert('Error: ' + (result.error || 'Could not complete purchase'));
                            btn.disabled = false;
                            btn.textContent = '💰 Purchase for ' + itemPrice + ' Coins';
                        }
                    } catch(e) {
                        alert('Error processing purchase');
                        btn.disabled = false;
                        btn.textContent = '💰 Purchase for ' + itemPrice + ' Coins';
                    }
                }
            };
            xhr.send('item_id=' + encodeURIComponent(itemId));
        });
    });
    
    // Pet Purchase Handler - Use same modal as Ascend Universe
    var petBuyBtns = document.querySelectorAll('.pet-buy-btn');
    petBuyBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var petId = parseInt(btn.getAttribute('data-item-id'));
            var petName = btn.getAttribute('data-item-name');
            var petPrice = parseInt(btn.getAttribute('data-item-price'));
            
            // Map pet IDs to video files (same as Ascend Universe)
            var petVideoMap = {
                100: 'lynx.mp4', 101: 'tortoise.mp4', 102: 'hamster.mp4',
                103: 'falcon.mp4', 104: 'gryphon.mp4', 105: 'boar.mp4',
                106: 'viper.mp4', 107: 'swan.mp4', 108: 'mischiefcap.mp4',
                109: 'otter.mp4', 110: 'kinkajou.mp4', 111: 'seahorse.mp4',
                112: 'dragonet.mp4', 113: 'mastiff.mp4', 114: 'raven.mp4',
                115: 'tiger.mp4', 116: 'wolf.mp4', 117: 'polar_bear.mp4'
            };
            
            var petVideo = petVideoMap[petId] || 'lynx.mp4';
            
            // Call the same function as Ascend Universe (requires avatar_modals.js)
            if (typeof showPetUnlockModal === 'function') {
                showPetUnlockModal(petId, petName, petPrice, petVideo, <?php echo $tokens_available; ?>, <?php echo $coin_balance; ?>);
            } else {
                alert('Error: Pet unlock system not loaded');
            }
        });
    });

    // Make pet frames keyboard-accessible: Enter/Space triggers nearest pet buy button
    document.querySelectorAll('.store-pet-frame').forEach(function(frame) {
        frame.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                var btn = frame.closest('.store-card').querySelector('.pet-buy-btn');
                if (btn && !btn.disabled) btn.click();
            }
        });
        frame.addEventListener('click', function() {
            var btn = frame.closest('.store-card').querySelector('.pet-buy-btn');
            if (btn && !btn.disabled) btn.click();
        });
    });
    
    // Villain Purchase Handler - Use same modal as Ascend Universe
    var villainBuyBtns = document.querySelectorAll('.villain-buy-btn');
    villainBuyBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var villainId = parseInt(btn.getAttribute('data-item-id'));
            var villainName = btn.getAttribute('data-item-name');
            var villainPrice = parseInt(btn.getAttribute('data-item-price'));
            
            // Map villain IDs to their icon names for video lookup
            var villainIconMap = {
                300: 'elf_dryad', 301: 'ent_blightmind', 302: 'imp_mole',
                303: 'nomad_dune', 304: 'gatekeeper_wraith', 305: 'warrior_warlord',
                306: 'sorceress_stormveil', 307: 'queen_serpent', 308: 'jester_mourner',
                309: 'amazon_huntsmistress', 310: 'pirate_barron', 311: 'mermaid_duchess',
                312: 'magician_spellbreaker', 313: 'philosopher_mirror', 314: 'wizard_pale_scholar',
                315: 'viking_betrayer', 316: 'sentinel_ice_queen', 317: 'beserker_frost_giant'
            };
            
            var villainIconName = villainIconMap[villainId] || 'elf_dryad';
            var villainVideo = villainIconName + '.mp4';
            
            // Call the same function as Ascend Universe (requires avatar_modals.js)
            if (typeof showVillainUnlockModal === 'function') {
                showVillainUnlockModal(villainId, villainName, villainPrice, villainIconName, villainVideo, <?php echo $tokens_available; ?>, <?php echo $coin_balance; ?>);
            } else {
                alert('Error: Villain unlock system not loaded');
            }
        });
    });

    // Keyboard/click forwarding for villain frames
    document.querySelectorAll('.store-villain-frame').forEach(function(frame) {
        frame.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                var btn = frame.closest('.store-card').querySelector('.villain-buy-btn');
                if (btn && !btn.disabled) btn.click();
            }
        });
        frame.addEventListener('click', function() {
            var btn = frame.closest('.store-card').querySelector('.villain-buy-btn');
            if (btn && !btn.disabled) btn.click();
        });
    });
    
    // Mystery Box Handler with Animation
    var mysteryBoxBtns = document.querySelectorAll('.mysterybox-open-btn');
    mysteryBoxBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var price = parseInt(btn.getAttribute('data-price'));
            
            // Show modal directly to result (skip boxes)
            var modal = document.getElementById('mysteryBoxModal');
            var boxesContainer = document.getElementById('boxesContainer');
            var resultDisplay = document.getElementById('resultDisplay');
            
            modal.style.display = 'flex';
            boxesContainer.style.display = 'none';
            resultDisplay.style.display = 'block';
            
            btn.disabled = true;
            btn.textContent = 'Opening...';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/mysterybox.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        // Skip boxes animation, go straight to result with video
                        boxesContainer.style.display = 'none';
                        resultDisplay.style.display = 'block';
                        
                        // Map reward type to video paths with cache busting
                        var cacheBust = '?v=' + Date.now();
                        var videoCoins = '<?php echo (new moodle_url('/local/ascend_rewards/pix/coins.mp4'))->out(false); ?>' + cacheBust;
                        var videoTokens = '<?php echo (new moodle_url('/local/ascend_rewards/pix/token.mp4'))->out(false); ?>' + cacheBust;
                        var videoHero = '<?php echo (new moodle_url('/local/ascend_rewards/pix/hero.mp4'))->out(false); ?>' + cacheBust;
                        var videoNoReward = '<?php echo (new moodle_url('/local/ascend_rewards/pix/no_reward.mp4'))->out(false); ?>' + cacheBust;

                        // Image URLs
                        var imgStar = '<?php echo (new moodle_url('/local/ascend_rewards/pix/start.png'))->out(false); ?>';
                        var imgCoins = '<?php echo (new moodle_url('/local/ascend_rewards/pix/ascend_assets_stack.png'))->out(false); ?>';
                        var imgAvatar = '<?php echo (new moodle_url('/local/ascend_rewards/pix/avatar.png'))->out(false); ?>';

                        var videos = {
                            'coins': videoCoins,
                            'coins_duplicate': videoCoins,
                            'tokens': videoTokens,
                            'avatar_new': videoHero,
                            'avatar_duplicate': videoHero,
                            'nothing': videoNoReward
                        };

                        var videoUrl = videos[result.reward_type] || videoNoReward;
                        
                        // Build result HTML with video (no loop, play once) - disable cache and preload
                        var resultHTML = '<video autoplay playsinline preload="auto" crossorigin="anonymous" style="width:280px;height:280px;object-fit:contain;border:none;border-radius:16px;box-shadow:0 8px 32px rgba(255,215,0,0.4), 0 0 60px rgba(255,215,0,0.2);background:linear-gradient(135deg, rgba(255,215,0,0.1), rgba(255,165,0,0.05));" id="rewardVideo"><source src="' + videoUrl + '" type="video/mp4"></video>';
                        
                        document.getElementById('resultIcon').innerHTML = resultHTML;
                        
                        // Build message with images based on reward type
                        var messageHTML = '';
                        var balanceHTML = '';
                        
                        if (result.reward_type === 'tokens') {
                            messageHTML = '<div style="margin-bottom:16px;"><img src="' + imgStar + '" style="width:75px;height:75px;object-fit:contain;"></div>' +
                                         '<div>' + result.message + '</div>';
                            balanceHTML = '<div style="margin-top:12px;font-size:16px;color:#94a3b8;">New balance: <img src="' + imgStar + '" style="width:24px;height:24px;vertical-align:middle;margin:0 4px;">' + result.total_tokens + ' tokens</div>';
                        } else if (result.reward_type === 'coins' || result.reward_type === 'coins_duplicate') {
                            messageHTML = '<div style="margin-bottom:16px;"><img src="' + imgCoins + '" style="width:75px;height:36px;object-fit:contain;"></div>' +
                                         '<div>' + result.message + '</div>';
                            balanceHTML = '<div style="margin-top:12px;font-size:16px;color:#94a3b8;">New balance: <img src="' + imgCoins + '" style="width:32px;height:15px;vertical-align:middle;margin:0 4px;object-fit:contain;">' + result.new_balance.toLocaleString() + ' coins</div>';
                        } else if (result.reward_type === 'avatar_duplicate') {
                            messageHTML = '<div>' + result.message + '</div>';
                            balanceHTML = '<div style="margin-top:12px;font-size:16px;color:#94a3b8;">New balance: <img src="' + imgCoins + '" style="width:32px;height:15px;vertical-align:middle;margin:0 4px;object-fit:contain;">' + result.new_balance.toLocaleString() + ' coins</div>';
                        } else if (result.reward_type === 'avatar_new') {
                            messageHTML = '<div>' + result.message + '</div>';
                        } else {
                            messageHTML = result.message;
                            balanceHTML = '<div style="margin-top:12px;font-size:16px;color:#94a3b8;">Balance: <img src="' + imgCoins + '" style="width:32px;height:15px;vertical-align:middle;margin:0 4px;object-fit:contain;">' + result.new_balance.toLocaleString() + ' coins</div>';
                        }
                        
                        document.getElementById('resultMessage').innerHTML = messageHTML + balanceHTML;
                        
                        // For avatar rewards, show the actual avatar image below the video
                        if ((result.reward_type === 'avatar_new' || result.reward_type === 'avatar_duplicate') && result.reward_data && result.reward_data.avatar_filename) {
                            var avatarUrl = '<?php echo (new moodle_url('/local/ascend_rewards/pix/Avatars/circular avatars/'))->out(false); ?>' + result.reward_data.avatar_filename;
                            resultHTML += '<div style="margin-top:16px;" id="avatarReward"><img src="' + avatarUrl + '" alt="Avatar" style="width:80px;height:80px;object-fit:contain;border:2px solid #06b6d4;border-radius:50%;box-shadow:0 4px 16px rgba(6,182,212,0.5);opacity:0;"></div>';
                            document.getElementById('resultIcon').innerHTML = resultHTML;
                        }
                        
                        // Wait for video to end before showing message
                        var video = document.getElementById('rewardVideo');
                        if (video) {
                            // Handle video errors
                            video.addEventListener('error', function(e) {
                                console.error('Video failed to load, showing message immediately');
                                // Show message immediately if video fails
                                var msgEl = document.getElementById('resultMessage');
                                var btnEl = document.getElementById('closeModalBtn');
                                msgEl.classList.add('animate-slide-in');
                                msgEl.style.opacity = '1';
                                btnEl.style.opacity = '1';
                                
                                var avatarReward = document.getElementById('avatarReward');
                                if (avatarReward) {
                                    var avatarImg = avatarReward.querySelector('img');
                                    if (avatarImg) {
                                        avatarImg.classList.add('animate-bounce-in');
                                        avatarImg.style.opacity = '1';
                                    }
                                }
                            });
                            
                            video.addEventListener('ended', function() {
                                // Animate message in
                                var msgEl = document.getElementById('resultMessage');
                                var btnEl = document.getElementById('closeModalBtn');
                                msgEl.classList.add('animate-slide-in');
                                msgEl.style.opacity = '1';
                                
                                setTimeout(function() {
                                    btnEl.style.opacity = '1';
                                }, 300);
                                
                                // Animate avatar if present
                                var avatarReward = document.getElementById('avatarReward');
                                if (avatarReward) {
                                    var avatarImg = avatarReward.querySelector('img');
                                    if (avatarImg) {
                                        avatarImg.classList.add('animate-bounce-in');
                                        avatarImg.style.opacity = '1';
                                    }
                                }
                            });
                            
                            // Fallback: Show message after 5 seconds if video hasn't ended
                            setTimeout(function() {
                                var msgEl = document.getElementById('resultMessage');
                                if (msgEl.style.opacity === '0' || msgEl.style.opacity === '') {
                                    msgEl.classList.add('animate-slide-in');
                                    msgEl.style.opacity = '1';
                                    document.getElementById('closeModalBtn').style.opacity = '1';
                                    
                                    var avatarReward = document.getElementById('avatarReward');
                                    if (avatarReward) {
                                        var avatarImg = avatarReward.querySelector('img');
                                        if (avatarImg && avatarImg.style.opacity === '0') {
                                            avatarImg.classList.add('animate-bounce-in');
                                            avatarImg.style.opacity = '1';
                                        }
                                    }
                                }
                            }, 5000);
                        }

                        // Close on button click
                        document.getElementById('closeModalBtn').onclick = function() {
                            modal.style.display = 'none';
                            location.reload();
                        };
                    } else {
                        modal.style.display = 'none';
                        alert('❌ ' + (result.message || 'Could not open mystery box'));
                        btn.disabled = false;
                        btn.textContent = '🎁 Open Mystery Box for ' + price + ' Coins';
                    }
                } catch(e) {
                    console.error('Error opening mystery box:', xhr.responseText, e);
                    // Show inline error in the result display instead of an alert
                    boxesContainer.style.display = 'none';
                    resultDisplay.style.display = 'block';
                    document.getElementById('resultIcon').innerHTML = '<video autoplay loop playsinline style="width:280px;height:280px;object-fit:contain;border:3px solid #FFD700;border-radius:12px;"><source src="' + '<?php echo (new moodle_url('/local/ascend_rewards/pix/no_reward.mp4'))->out(false); ?>' + '" type="video/mp4"></video>';
                    document.getElementById('resultMessage').textContent = 'Error opening mystery box. Please try again.';
                    document.getElementById('closeModalBtn').onclick = function() {
                        modal.style.display = 'none';
                        btn.disabled = false;
                        btn.textContent = '🎁 Open Mystery Box for ' + price + ' Coins';
                    };
                }
            };
            xhr.onerror = function() {
                console.error('Network error opening mystery box');
                boxesContainer.style.display = 'none';
                resultDisplay.style.display = 'block';
                document.getElementById('resultIcon').innerHTML = '<img src="' + '<?php echo (new moodle_url('/local/ascend_rewards/pix/mystery_box.png'))->out(false); ?>' + '" style="width:96px;height:96px;object-fit:contain;">';
                document.getElementById('resultMessage').textContent = 'Network error. Please check your connection and try again.';
                document.getElementById('closeModalBtn').onclick = function() {
                    modal.style.display = 'none';
                    btn.disabled = false;
                    btn.textContent = '🎁 Open Mystery Box for ' + price + ' Coins';
                };
            };
            xhr.send('price=' + encodeURIComponent(price));
        });
    });
    
    // Store Item Activation Handler
    var activateBtns = document.querySelectorAll('.store-activate-btn');
    activateBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var itemId = btn.getAttribute('data-item-id');
            var itemName = btn.getAttribute('data-item-name');
            
            if (!confirm('Activate ' + itemName + '?\n\nThis will consume one from your inventory and activate the effect immediately.')) {
                return;
            }
            
            btn.disabled = true;
            btn.textContent = 'Activating...';
            
            // Make AJAX request to activate item
            var xhr = new XMLHttpRequest();
            xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/store_activate.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        alert('✅ ' + result.message);
                        location.reload();
                    } else {
                        alert('Error: ' + (result.error || 'Could not activate item'));
                        btn.disabled = false;
                        btn.textContent = '⚡ Activate';
                    }
                } catch(e) {
                    alert('Error processing activation');
                    btn.disabled = false;
                    btn.textContent = '⚡ Activate';
                }
            };
            xhr.onerror = function() {
                alert('Network error. Please check your connection and try again.');
                btn.disabled = false;
                btn.textContent = '⚡ Activate';
            };
            xhr.send('item_id=' + encodeURIComponent(itemId));
        });
    });
})();
</script>

<?php
// ===== END STORE SECTION =====
?>
