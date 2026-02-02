/**
 * Avatar, Pet, and Villain Modal Functions
 * Token/Coin unlock system with video previews
 */

// Modal utility functions
function createModal(id, borderColor) {
    var modal = document.createElement('div');
    modal.id = id;
    modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(1,8,40,0.95);display:flex;align-items:center;justify-content:center;z-index:9999;padding:20px;';
    
    var content = document.createElement('div');
    content.style.cssText = 'background:#01142E;border-radius:12px;padding:32px 24px 24px;max-width:420px;width:100%;text-align:center;border:2px solid ' + borderColor + ';box-shadow:0 4px 20px rgba(0,0,0,0.3);';
    
    modal.appendChild(content);
    return { modal: modal, content: content };
}

function closeModal(modal) {
    if (document.body.contains(modal)) {
        document.body.removeChild(modal);
    }
}

// Avatar Unlock Modal (Token Only)
function showAvatarUnlockModal(name, avatar, videoFile, level, tokensAvailable) {
    if (tokensAvailable <= 0) {
        alert('No tokens available! Level up to get more unlock tokens.');
        return;
    }
    
    var elements = createModal('avatarUnlockModal', '#FFD700');
    var modal = elements.modal;
    var content = elements.content;
    
    var avatarImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/' + avatar;
    
    content.innerHTML = 
        '<button id="avatarModalClose" style="position:absolute;top:12px;right:12px;background:transparent;border:none;color:#94a3b8;font-size:24px;cursor:pointer;padding:4px;line-height:1;"></button>' +
        '<h2 style="color:#FFD700;font-size:18px;font-weight:700;margin:0 0 12px 0;">Unlock ' + name + '?</h2>' +
        '<div style="position:relative;width:100%;max-width:250px;aspect-ratio:1;margin:0 auto 16px;border-radius:50%;overflow:hidden;border:3px solid #FFD700;">' +
        '<img src="' + avatarImagePath + '" style="width:100%;height:100%;object-fit:cover;filter:grayscale(100%) brightness(0.4);">' +
        '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);">' +
        '<i class="fa-solid fa-lock" style="font-size:48px;color:#94a3b8;"></i>' +
        '</div>' +
        '</div>' +
        '<div style="font-size:13px;color:#94a3b8;margin-bottom:16px;">Level ' + level + ' Avatar  Costs 1 Token</div>' +
        '<button id="avatarUnlockConfirm" style="background:#FFD700;border:none;color:#01142E;padding:12px 32px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.2s;width:100%;margin-bottom:8px;"> Unlock Avatar</button>' +
        '<div style="font-size:12px;color:#94a3b8;text-align:center;">Once unlocked, click to view video & download</div>';
    
    content.style.position = 'relative';
    
    document.body.appendChild(modal);
    
    // Close button
    document.getElementById('avatarModalClose').addEventListener('click', function() {
        closeModal(modal);
    });
    
    // Confirm button
    document.getElementById('avatarUnlockConfirm').addEventListener('click', function() {
        this.disabled = true;
        this.textContent = 'Unlocking...';
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/avatar_unlock.php');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        closeModal(modal);
                        showAvatarSuccessModal(name, videoFile);
                    } else {
                        alert('Error: ' + (result.error || 'Could not unlock avatar'));
                        closeModal(modal);
                    }
                } catch(e) {
                    alert('Error unlocking avatar');
                    closeModal(modal);
                }
            }
        };
        xhr.send('avatar=' + encodeURIComponent(avatar) + '&level=' + level);
    });
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// Avatar Success Modal (shows after unlock)
function showAvatarSuccessModal(name, videoFile, isYouTube = false) {
    var elements = createModal('avatarSuccessModal', '#06b6d4');
    var modal = elements.modal;
    var content = elements.content;

    var videoPath = isYouTube
        ? `https://www.youtube.com/embed/${videoFile}?autoplay=1&loop=1`
        : M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/Videos/' + videoFile;

    var avatarFile = videoFile.replace('.mp4', '.png');
    var circularImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/circular%20avatars/' + avatarFile;

    content.innerHTML = 
        '<button id="avatarSuccessClose" style="position:absolute;top:12px;right:12px;background:transparent;border:none;color:#ec4899;font-size:28px;cursor:pointer;padding:4px;line-height:1;transition:transform 0.2s;" onmouseover="this.style.transform=\'scale(1.1)\'" onmouseout="this.style.transform=\'scale(1)\'"></button>' +
        '<h2 style="color:#06b6d4;font-size:20px;font-weight:700;margin:0 0 16px 0;"> ' + name + ' Unlocked!</h2>' +
        '<div style="position:relative;width:100%;max-width:250px;aspect-ratio:1;border-radius:50%;overflow:hidden;border:3px solid #06b6d4;margin:0 auto 16px;background:#000;">' +
        (isYouTube
            ? `<iframe id="avatarSuccessVideo" src="${videoPath}" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="width:100%;height:100%;"></iframe>`
            : `<video id="avatarSuccessVideo" autoplay loop style="width:100%;height:100%;object-fit:cover;">' +
               '<source src="${videoPath}" type="video/mp4">' +
               '</video>`
        ) +
        '</div>' +
        '<div style="background:rgba(236,72,153,0.1);border:2px solid #ec4899;border-radius:8px;padding:12px;margin-bottom:16px;">' +
        '<div style="font-size:14px;color:#ec4899;font-weight:600;margin-bottom:4px;"><span style="color:#ec4899;"></span> The ' + name + '\'s Pet is Revealed!</div>' +
        '<div style="font-size:13px;color:#ec4899;">Check the Pets section below to adopt your new pet</div>' +
        '</div>' +
        '<a href="' + circularImagePath + '" download="' + name + '_avatar.png" style="display:block;background:#06b6d4;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;margin-bottom:8px;"> Download Avatar Image</a>' +
        '<div style="font-size:12px;color:#94a3b8;text-align:center;">Use this image in your profile!</div>';

    content.style.position = 'relative';

    document.body.appendChild(modal);

    // Set video volume and autoplay for non-YouTube videos
    if (!isYouTube) {
        setTimeout(function() {
            var video = document.getElementById('avatarSuccessVideo');
            if (video) {
                video.volume = 0.7;
                video.muted = false;
            }
        }, 100);
    }

    // Close button
    document.getElementById('avatarSuccessClose').addEventListener('click', function() {
        closeModal(modal);
        location.reload(true);
    });

    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
            location.reload(true);
        }
    });
}

// Avatar Review Modal (for already unlocked avatars)
function showAvatarReviewModal(avatarFile, name, videoFile) {
    var elements = createModal('avatarReviewModal', '#FFD700');
    var modal = elements.modal;
    var content = elements.content;
    
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/Videos/' + videoFile;
    var circularImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/circular%20avatars/' + avatarFile.replace('.png', '.png');
    
    content.innerHTML = 
        '<button id="avatarReviewClose" style="position:absolute;top:12px;right:12px;background:transparent;border:none;color:#94a3b8;font-size:24px;cursor:pointer;padding:4px;line-height:1;"></button>' +
        '<h2 style="color:#FFD700;font-size:20px;font-weight:700;margin:0 0 16px 0;"> ' + name + '</h2>' +
        '<div style="position:relative;width:100%;max-width:250px;aspect-ratio:1;border-radius:50%;overflow:hidden;border:3px solid #FFD700;margin:0 auto 16px;background:#000;">' +
        '<video id="avatarReviewVideo" autoplay loop style="width:100%;height:100%;object-fit:cover;">' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + circularImagePath + '" download="' + name + '_avatar.png" style="display:block;background:#FFD700;border:none;color:#01142E;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;margin-bottom:8px;"> Download Avatar Image</a>' +
        '<div style="font-size:12px;color:#94a3b8;text-align:center;">Use this image in your profile!</div>';
    
    content.style.position = 'relative';
    
    document.body.appendChild(modal);
    
    // Set video volume and unmute
    var video = document.getElementById('avatarReviewVideo');
    if (video) {
        video.volume = 0.7;
        video.muted = false;
    }
    
    // Close button
    document.getElementById('avatarReviewClose').addEventListener('click', function() {
        closeModal(modal);
    });
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// Pet Unlock Modal (Token OR Coins)
function showPetUnlockModal(petId, petName, price, videoFile, tokensAvailable, userCoins) {
    var canUseToken = tokensAvailable > 0;
    var canBuyWithCoins = userCoins >= price;
    
    // Just show modal regardless - it will display what options are available
    var elements = createModal('petUnlockModal', '#ec4899');
    var modal = elements.modal;
    var content = elements.content;
    
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/Videos/' + videoFile;
    
    content.innerHTML = 
        '<h2 style="color:#ec4899;font-size:20px;font-weight:700;margin:0 0 16px 0;"> Adopt Pet</h2>' +
        '<div style="margin-bottom:12px;font-size:16px;font-weight:600;color:#e6e9f0;">' + petName + '</div>' +
        '<div style="width:100%;max-width:300px;aspect-ratio:1;border-radius:50%;overflow:hidden;border:3px solid #ec4899;margin:0 auto 16px;position:relative;background:#1a1f2e;">' +
        '<img src="' + M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/' + petName.toLowerCase().replace(/\s/g, '_') + '.png" alt="' + petName + '" style="width:100%;height:100%;object-fit:cover;filter:grayscale(100%) brightness(0.5);" onerror="this.style.display=\'none\'">' +
        '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">' +
        '<i class="fa-solid fa-lock" style="font-size:48px;color:#94a3b8;"></i>' +
        '</div>' +
        '</div>' +
        '<div style="background:rgba(236,72,153,0.1);border:1px solid #ec4899;border-radius:8px;padding:12px;margin-bottom:16px;">' +
        '<div style="font-size:14px;color:#e6e9f0;margin-bottom:8px;">Choose unlock method:</div>' +
        (canUseToken ? '<div style="font-size:16px;font-weight:700;color:#FFD700;margin-bottom:4px;"> Use Token (' + tokensAvailable + ' available)</div>' : '') +
        (canBuyWithCoins ? '<div style="font-size:16px;font-weight:700;color:#ec4899;"> Pay ' + price.toLocaleString() + ' Coins (Balance: ' + userCoins.toLocaleString() + ')</div>' : '<div style="font-size:14px;color:#dc2626;"> Insufficient coins (need ' + price.toLocaleString() + ')</div>') +
        '</div>' +
        '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">' +
        (canUseToken ? '<button class="petUnlockToken" style="background:#FFD700;border:none;color:#01142E;padding:12px 20px;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;"> Use Token</button>' : '') +
        (canBuyWithCoins ? '<button class="petUnlockCoins" style="background:#ec4899;border:none;color:#fff;padding:12px 20px;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;"> Pay ' + price.toLocaleString() + '</button>' : '') +
        '<button class="petUnlockCancel" style="background:#4b5563;border:none;color:#fff;padding:12px 20px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>' +
        '</div>';
    
    // Token unlock handler
    if (canUseToken) {
        var tokenBtn = content.querySelector('.petUnlockToken');
        if (tokenBtn) {
            tokenBtn.onclick = function() {
                this.disabled = true;
                this.textContent = 'Unlocking...';
                processPetUnlock(petId, 'token', 0, modal, petName, videoFile);
            };
        }
    }
    
    // Coin unlock handler
    if (canBuyWithCoins) {
        var coinBtn = content.querySelector('.petUnlockCoins');
        if (coinBtn) {
            coinBtn.onclick = function() {
                this.disabled = true;
                this.textContent = 'Processing...';
                processPetUnlock(petId, 'coin', price, modal, petName, videoFile);
            };
        }
    }
    
    // Cancel button handler
    var cancelBtn = content.querySelector('.petUnlockCancel');
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            closeModal(modal);
        };
    }
    
    // Close on backdrop click
    modal.onclick = function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    };
    
    document.body.appendChild(modal);
}

function processPetUnlock(petId, unlockType, price, modal, petName, videoFile) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/pet_unlock.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var result = JSON.parse(xhr.responseText);
                if (result.success) {
                    closeModal(modal);
                    showPetSuccessModal(petName, videoFile, unlockType, result.new_balance);
                } else {
                    alert('Error: ' + (result.error || 'Could not adopt pet'));
                    closeModal(modal);
                }
            } catch(e) {
                alert('Error adopting pet');
                closeModal(modal);
            }
        }
    };
    xhr.send('pet_id=' + encodeURIComponent(petId) + '&unlock_type=' + unlockType);
}

function showPetSuccessModal(petName, videoFile, unlockType, newBalance) {
    var elements = createModal('petSuccessModal', '#FF9500');
    var modal = elements.modal;
    var content = elements.content;
    
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/videos/' + videoFile;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/pets_circular/' + videoFile.replace('.mp4', '.png');
    
    content.innerHTML = 
        '<h2 style="color:#FF9500;font-size:20px;font-weight:700;margin:0 0 16px 0;"> Pet Adopted!</h2>' +
        '<div style="margin-bottom:12px;font-size:16px;font-weight:600;color:#e6e9f0;">' + petName + '</div>' +
        '<div style="position:relative;width:100%;max-width:250px;border-radius:50%;overflow:hidden;border:3px solid #FF9500;margin:0 auto 16px;aspect-ratio:1;background:#000;">' +
        '<video id="petSuccessVideo" autoplay loop style="width:100%;height:100%;object-fit:cover;">' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + petName + '_pet.png" style="display:block;background:#06b6d4;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;margin-bottom:8px;"> Download Pet Image</a>' +
        '<div style="font-size:14px;color:#94a3b8;margin-bottom:16px;">Unlocked with: ' + (unlockType === 'token' ? ' Token' : ' Coins') + '</div>' +
        (unlockType === 'coin' ? '<div style="font-size:14px;color:#94a3b8;margin-bottom:16px;">New balance: ' + newBalance.toLocaleString() + ' coins</div>' : '') +
        '<button id="petSuccessClose" style="background:#FF9500;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">Continue</button>';
    
    document.body.appendChild(modal);
    
    var video = document.getElementById('petSuccessVideo');
    video.volume = 0.7;
    
    document.getElementById('petSuccessClose').addEventListener('click', function() {
        closeModal(modal);
        location.reload(true);
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
            location.reload(true);
        }
    });
}

// Pet Review Modal
function showPetReviewModal(petName, petId, videoFile) {
    var elements = createModal('petReviewModal', '#ec4899');
    var modal = elements.modal;
    var content = elements.content;
    
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/videos/' + videoFile;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/pets_circular/' + videoFile.replace('.mp4', '.png');
    
    content.innerHTML = 
        '<h2 style="color:#ec4899;font-size:20px;font-weight:700;margin:0 0 16px 0;"> ' + petName + '</h2>' +
        '<div style="position:relative;width:100%;max-width:250px;border-radius:50%;overflow:hidden;border:3px solid #ec4899;margin:0 auto 16px;aspect-ratio:1;background:#000;">' +
        '<video id="petReviewVideo" autoplay loop style="width:100%;height:100%;object-fit:cover;">' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + petName + '_pet.png" style="display:block;background:#ec4899;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;margin-bottom:8px;"> Download Pet Image</a>' +
        '<button id="petReviewClose" style="background:#4b5563;border:none;color:#fff;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Close</button>';
    
    document.body.appendChild(modal);
    
    var video = document.getElementById('petReviewVideo');
    video.volume = 0.7;
    
    document.getElementById('petReviewClose').addEventListener('click', function() {
        closeModal(modal);
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// Villain Unlock Modal (Token OR Coins)
function showVillainUnlockModal(villainId, villainName, price, villainImageName, villainVideo, tokensAvailable, userCoins) {
    var canUseToken = tokensAvailable > 0;
    var canBuyWithCoins = userCoins >= price;
    
    // Just show modal regardless - it will display what options are available
    var elements = createModal('villainUnlockModal', '#06b6d4');
    var modal = elements.modal;
    var content = elements.content;
    
    content.innerHTML = 
        '<h2 style="color:#06b6d4;font-size:20px;font-weight:700;margin:0 0 16px 0;"> Unlock Villain</h2>' +
        '<div style="margin-bottom:12px;font-size:16px;font-weight:600;color:#e6e9f0;">' + villainName + '</div>' +
        '<div style="width:100%;max-width:300px;aspect-ratio:1;border-radius:50%;overflow:hidden;border:3px solid #06b6d4;margin:0 auto 16px;position:relative;background:#1a1f2e;">' +
        '<img src="' + M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/' + villainImageName + '.png" alt="' + villainName + '" style="width:100%;height:100%;object-fit:cover;filter:grayscale(100%) brightness(0.5);" onerror="this.style.display=\'none\'">' +
        '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">' +
        '<i class="fa-solid fa-lock" style="font-size:48px;color:#94a3b8;"></i>' +
        '</div>' +
        '</div>' +
        '<div style="background:rgba(6,182,212,0.1);border:1px solid #06b6d4;border-radius:8px;padding:12px;margin-bottom:16px;">' +
        '<div style="font-size:14px;color:#e6e9f0;margin-bottom:8px;">Choose unlock method:</div>' +
        (canUseToken ? '<div style="font-size:16px;font-weight:700;color:#FFD700;margin-bottom:4px;"> Use Token (' + tokensAvailable + ' available)</div>' : '') +
        (canBuyWithCoins ? '<div style="font-size:16px;font-weight:700;color:#06b6d4;"> Pay ' + price.toLocaleString() + ' Coins (Balance: ' + userCoins.toLocaleString() + ')</div>' : '<div style="font-size:14px;color:#dc2626;"> Insufficient coins (need ' + price.toLocaleString() + ')</div>') +
        '</div>' +
        '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">' +
        (canUseToken ? '<button class="villainUnlockToken" style="background:#FFD700;border:none;color:#01142E;padding:12px 20px;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;"> Use Token</button>' : '') +
        (canBuyWithCoins ? '<button class="villainUnlockCoins" style="background:#06b6d4;border:none;color:#fff;padding:12px 20px;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;"> Pay ' + price.toLocaleString() + '</button>' : '') +
        '<button class="villainUnlockCancel" style="background:#4b5563;border:none;color:#fff;padding:12px 20px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>' +
        '</div>';
    
    // Token unlock handler
    if (canUseToken) {
        var tokenBtn = content.querySelector('.villainUnlockToken');
        if (tokenBtn) {
            tokenBtn.onclick = function() {
                this.disabled = true;
                this.textContent = 'Unlocking...';
                processVillainUnlock(villainId, 'token', 0, modal, villainName, villainImageName, villainVideo);
            };
        }
    }
    
    // Coin unlock handler
    if (canBuyWithCoins) {
        var coinBtn = content.querySelector('.villainUnlockCoins');
        if (coinBtn) {
            coinBtn.onclick = function() {
                this.disabled = true;
                this.textContent = 'Processing...';
                processVillainUnlock(villainId, 'coin', price, modal, villainName, villainImageName, villainVideo);
            };
        }
    }
    
    // Cancel button handler
    var cancelBtn = content.querySelector('.villainUnlockCancel');
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            closeModal(modal);
        };
    }
    
    // Close on backdrop click
    modal.onclick = function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    };
    
    document.body.appendChild(modal);
}

function processVillainUnlock(villainId, unlockType, price, modal, villainName, villainImageName, villainVideo) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/villain_unlock.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var result = JSON.parse(xhr.responseText);
                if (result.success) {
                    closeModal(modal);
                    showVillainSuccessModal(villainName, villainImageName, villainVideo, unlockType, result.new_balance);
                } else {
                    alert('Error: ' + (result.error || 'Could not unlock villain'));
                    closeModal(modal);
                }
            } catch(e) {
                alert('Error unlocking villain');
                closeModal(modal);
            }
        }
    };
    xhr.send('villain_id=' + encodeURIComponent(villainId) + '&unlock_type=' + unlockType);
}

function showVillainSuccessModal(villainName, villainImageName, villainVideo, unlockType, newBalance) {
    var elements = createModal('villainSuccessModal', '#dc2626');
    var modal = elements.modal;
    var content = elements.content;
    
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/videos/' + villainVideo;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/villains_circular/' + villainImageName + '.png';
    
    content.innerHTML = 
        '<h2 style="color:#dc2626;font-size:20px;font-weight:700;margin:0 0 16px 0;"> Villain Unlocked!</h2>' +
        '<div style="margin-bottom:12px;font-size:16px;font-weight:600;color:#e6e9f0;">' + villainName + '</div>' +
        '<div style="position:relative;width:100%;max-width:250px;border-radius:50%;overflow:hidden;border:3px solid #dc2626;margin:0 auto 16px;aspect-ratio:1;background:#000;">' +
        '<video id="villainSuccessVideo" autoplay loop style="width:100%;height:100%;object-fit:cover;">' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + villainName + '_villain.png" style="display:block;background:#06b6d4;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;margin-bottom:8px;"> Download Villain Image</a>' +
        '<div style="font-size:14px;color:#94a3b8;margin-bottom:16px;">Unlocked with: ' + (unlockType === 'token' ? ' Token' : ' Coins') + '</div>' +
        (unlockType === 'coin' ? '<div style="font-size:14px;color:#94a3b8;margin-bottom:16px;">New balance: ' + newBalance.toLocaleString() + ' coins</div>' : '') +
        '<button id="villainSuccessClose" style="background:#dc2626;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">Continue</button>';
    
    document.body.appendChild(modal);
    
    var video = document.getElementById('villainSuccessVideo');
    video.volume = 0.7;
    
    document.getElementById('villainSuccessClose').addEventListener('click', function() {
        closeModal(modal);
        location.reload(true);
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
            location.reload(true);
        }
    });
}

// Villain Review Modal
function showVillainReviewModal(villainName, villainId, villainImageName, villainVideo) {
    var elements = createModal('villainReviewModal', '#06b6d4');
    var modal = elements.modal;
    var content = elements.content;
    
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/videos/' + villainVideo;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/villains_circular/' + villainImageName + '.png';
    
    content.innerHTML = 
        '<h2 style="color:#06b6d4;font-size:20px;font-weight:700;margin:0 0 16px 0;"> ' + villainName + '</h2>' +
        '<div style="position:relative;width:100%;max-width:250px;border-radius:50%;overflow:hidden;border:3px solid #06b6d4;margin:0 auto 16px;aspect-ratio:1;background:#000;">' +
        '<video id="villainReviewVideo" autoplay loop style="width:100%;height:100%;object-fit:cover;">' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + villainName + '_villain.png" style="display:block;background:#06b6d4;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;margin-bottom:8px;"> Download Villain Image</a>' +
        '<button id="villainReviewClose" style="background:#4b5563;border:none;color:#fff;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Close</button>';
    
    document.body.appendChild(modal);
    
    var video = document.getElementById('villainReviewVideo');
    video.volume = 0.7;
    
    document.getElementById('villainReviewClose').addEventListener('click', function() {
        closeModal(modal);
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}
