/**
 * Avatar, Pet, and Villain Modal Functions
 * Token/Coin unlock system with video previews
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

let modalConfig = {
    strings: {},
    urls: {}
};

const getString = (key) => {
    if (!modalConfig || !modalConfig.strings) {
        return '';
    }
    const value = modalConfig.strings[key];
    return typeof value === 'string' ? value : '';
};

const formatString = (template, data = {}) => {
    if (!template || typeof template !== 'string') {
        return '';
    }
    return template.replace(/\{(\w+)\}/g, (match, key) => {
        if (Object.prototype.hasOwnProperty.call(data, key)) {
            return data[key];
        }
        return '';
    });
};

const normalizeImageName = (value) => {
    if (!value) {
        return '';
    }
    const base = String(value).split('/').pop();
    return base.replace(/\.png$/i, '');
};

const showAlert = (message, isError = false) => {
    if (!message) {
        return;
    }
    const title = isError ? getString('errorTitle') : getString('alertTitle');
    const closeLabel = getString('closeLabel');
    Notification.alert(title || '', message, closeLabel || '');
};

export const init = (config = {}) => {
    if (config && typeof config === 'object') {
        modalConfig = {
            strings: {
                ...modalConfig.strings,
                ...(config.strings || config)
            },
            urls: {
                ...modalConfig.urls,
                ...(config.urls || {})
            }
        };
    }
};

// Modal utility functions
var modalThemeMap = {
    '#FFD700': 'aa-modal-theme-gold',
    '#06b6d4': 'aa-modal-theme-cyan',
    '#ec4899': 'aa-modal-theme-pink',
    '#FF9500': 'aa-modal-theme-orange',
    '#dc2626': 'aa-modal-theme-red'
};

function getModalThemeClass(borderColor) {
    return modalThemeMap[borderColor] || 'aa-modal-theme-gold';
}

function createModal(id, borderColor) {
    var modal = document.createElement('div');
    modal.id = id;
    modal.className = 'aa-modal-backdrop';
    
    var content = document.createElement('div');
    content.className = 'aa-modal-content ' + getModalThemeClass(borderColor);
    
    modal.appendChild(content);
    return { modal: modal, content: content };
}

function closeModal(modal) {
    if (document.body.contains(modal)) {
        document.body.removeChild(modal);
    }
}

// Compatibility fallback map for sites where external functions have not been
// fully upgraded yet. External services remain the primary path.
var legacyEndpointMap = {
    local_ascend_rewards_avatar_unlock: 'avatar_unlock.php',
    local_ascend_rewards_pet_unlock: 'pet_unlock.php',
    local_ascend_rewards_villain_unlock: 'villain_unlock.php'
};

function getAjaxErrorMessage(error, fallbackMessage) {
    if (!error) {
        return fallbackMessage;
    }

    if (typeof error === 'string' && error.trim()) {
        return error;
    }

    if (error && typeof error.message === 'string' && error.message.trim()) {
        return error.message;
    }

    if (error && typeof error.error === 'string' && error.error.trim()) {
        return error.error;
    }

    if (error && error.error && typeof error.error.message === 'string' && error.error.message.trim()) {
        return error.error.message;
    }

    if (Array.isArray(error) && error.length > 0) {
        var first = error[0];
        if (first && typeof first.message === 'string' && first.message.trim()) {
            return first.message;
        }
    }

    return fallbackMessage;
}

function shouldUseLegacyFallback(error) {
    var message = getAjaxErrorMessage(error, '').toLowerCase();
    return message.indexOf('external_functions') !== -1 ||
        message.indexOf('unknown web service function') !== -1 ||
        message.indexOf('servicenotavailable') !== -1 ||
        message.indexOf('method not found') !== -1;
}

function callLegacyAjax(methodname, args) {
    return new Promise(function(resolve, reject) {
        var endpoint = legacyEndpointMap[methodname];
        if (!endpoint) {
            reject(new Error(getString('ajaxRequestFailed')));
            return;
        }
        if (typeof M === 'undefined' || !M.cfg || !M.cfg.wwwroot) {
            reject(new Error(getString('ajaxConfigMissing')));
            return;
        }

        var payload = [];
        var safeArgs = args || {};
        Object.keys(safeArgs).forEach(function(key) {
            payload.push(encodeURIComponent(key) + '=' + encodeURIComponent(safeArgs[key]));
        });
        if (M.cfg.sesskey) {
            payload.push('sesskey=' + encodeURIComponent(M.cfg.sesskey));
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/' + endpoint, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

        xhr.onload = function() {
            var response = {};
            try {
                response = JSON.parse(xhr.responseText || '{}');
            } catch (parseError) {
                reject(new Error(getString('ajaxInvalidJson')));
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(response);
            } else {
                reject(new Error((response && response.error) || getString('ajaxRequestFailed')));
            }
        };

        xhr.onerror = function() {
            reject(new Error(getString('ajaxNetworkError')));
        };

        xhr.send(payload.join('&'));
    });
}

function callAscendAjax(methodname, args) {
    try {
        var requests = Ajax.call([{methodname: methodname, args: args}]);
        if (requests && requests[0]) {
            return requests[0].catch(function(error) {
                if (shouldUseLegacyFallback(error)) {
                    return callLegacyAjax(methodname, args);
                }
                throw error;
            });
        }
        return Promise.reject(new Error(getString('ajaxRequestFailed')));
    } catch (err) {
        return Promise.reject(err);
    }
}

// Avatar Unlock Modal (Token Only)
function showAvatarUnlockModal(name, avatar, videoFile, level, tokensAvailable) {
    if (tokensAvailable <= 0) {
        showAlert(getString('noTokensAvailable'));
        return;
    }
    
    var elements = createModal('avatarUnlockModal', '#FFD700');
    var modal = elements.modal;
    var content = elements.content;
    
    var avatarImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/' + avatar;
    
    content.innerHTML =
        '<button id="avatarModalClose" class="aa-modal-close aa-modal-close--muted" type="button" aria-label="' + getString('closeLabel') + '"></button>' +
        '<h2 class="aa-modal-title aa-modal-title--sm">' + formatString(getString('unlockAvatarTitle'), {name: name}) + '</h2>' +
        '<div class="aa-modal-circle">' +
        '<img src="' + avatarImagePath + '" class="aa-modal-image aa-modal-image--locked">' +
        '<div class="aa-modal-lock-overlay">' +
        '<i class="fa-solid fa-lock aa-modal-lock-icon"></i>' +
        '</div>' +
        '</div>' +
        '<div class="aa-modal-note aa-modal-note--muted">' + formatString(getString('unlockAvatarLevelNote'), {level: level}) + '</div>' +
        '<button id="avatarUnlockConfirm" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--lg aa-modal-btn--full" type="button">' + getString('unlockAvatarButton') + '</button>' +
        '<div class="aa-modal-note aa-modal-note--center">' + getString('unlockAvatarNote') + '</div>';
    
    document.body.appendChild(modal);
    
    // Close button
    document.getElementById('avatarModalClose').addEventListener('click', function() {
        closeModal(modal);
    });
    
    // Confirm button
    document.getElementById('avatarUnlockConfirm').addEventListener('click', function() {
        this.disabled = true;
        this.textContent = getString('unlockingLabel');
        
        callAscendAjax('local_ascend_rewards_avatar_unlock', {avatar: avatar, level: level})
            .then(function(result) {
                if (result && result.success) {
                    closeModal(modal);
                    showAvatarSuccessModal(name, videoFile);
                } else {
                    var errorPrefix = getString('errorPrefix');
                    var fallbackMessage = getString('errorUnlockAvatar');
                    var message = (result && (result.error || result.message)) || fallbackMessage;
                    showAlert('' + errorPrefix + message, true);
                    closeModal(modal);
                }
            })
            .catch(function(error) {
                var errorPrefix = getString('errorPrefix');
                var fallbackMessage = getString('errorUnlockAvatar');
                showAlert('' + errorPrefix + getAjaxErrorMessage(error, fallbackMessage), true);
                closeModal(modal);
            });
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
        ? 'https://www.youtube.com/embed/' + videoFile + '?autoplay=1&loop=1'
        : M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/Videos/' + videoFile;

    var avatarFile = videoFile.replace('.mp4', '.png');
    var circularImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/circular%20avatars/' + avatarFile;

    content.innerHTML =
        '<button id="avatarSuccessClose" class="aa-modal-close aa-modal-close--pink aa-modal-close--grow" type="button" aria-label="' + getString('closeLabel') + '"></button>' +
        '<h2 class="aa-modal-title"> ' + formatString(getString('avatarUnlockedTitle'), {name: name}) + '</h2>' +
        '<div class="aa-modal-circle">' +
        (isYouTube
            ? '<iframe id="avatarSuccessVideo" class="aa-modal-iframe" src="' + videoPath + '" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>'
            : '<video id="avatarSuccessVideo" class="aa-modal-video" autoplay loop>' +
               '<source src="' + videoPath + '" type="video/mp4">' +
               '</video>'
        ) +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">' + formatString(getString('avatarPetRevealedTitle'), {name: name}) + '</div>' +
        '<div class="aa-modal-info-text">' + getString('avatarPetRevealedText') + '</div>' +
        '</div>' +
        '<a href="' + circularImagePath + '" download="' + name + '_avatar.png" class="aa-modal-btn aa-modal-btn--cyan aa-modal-btn--full">' + getString('downloadAvatarLabel') + '</a>' +
        '<div class="aa-modal-note aa-modal-note--center">' + getString('avatarUseProfileNote') + '</div>';

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
        '<button id="avatarReviewCloseX" class="aa-modal-close aa-modal-close--muted" type="button" aria-label="' + getString('closeLabel') + '"></button>' +
        '<h2 class="aa-modal-title"> ' + name + '</h2>' +
        '<div class="aa-modal-circle">' +
        '<video id="avatarReviewVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + circularImagePath + '" download="' + name + '_avatar.png" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--full">' + getString('downloadAvatarLabel') + '</a>' +
        '<div class="aa-modal-note aa-modal-note--center">' + getString('avatarUseProfileNote') + '</div>' +
        '<div class="aa-modal-actions">' +
        '<button id="avatarReviewCloseBtn" class="aa-modal-btn aa-modal-btn--gray aa-modal-btn--sm aa-modal-btn--center" type="button">' + getString('closeLabel') + '</button>' +
        '</div>';
    
    document.body.appendChild(modal);
    
    // Set video volume and unmute
    var video = document.getElementById('avatarReviewVideo');
    if (video) {
        video.volume = 0.7;
        video.muted = false;
    }
    
    // Close button
    document.getElementById('avatarReviewCloseX').addEventListener('click', function() {
        closeModal(modal);
    });

    document.getElementById('avatarReviewCloseBtn').addEventListener('click', function() {
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
    
    content.innerHTML =
        '<h2 class="aa-modal-title"> ' + getString('petUnlockTitle') + '</h2>' +
        '<div class="aa-modal-subtitle">' + petName + '</div>' +
        '<div class="aa-modal-circle aa-modal-circle--lg aa-modal-circle--tint">' +
        '<img src="' + M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/' + petName.toLowerCase().replace(/\s/g, '_') + '.png" alt="' + petName + '" class="aa-modal-image aa-modal-image--locked" onerror="this.style.display=\'none\'">' +
        '<div class="aa-modal-lock-overlay">' +
        '<i class="fa-solid fa-lock aa-modal-lock-icon"></i>' +
        '</div>' +
        '</div>' +
        '<div class="aa-modal-info">' +
        '<div class="aa-modal-info-title aa-modal-info-title--muted">' + getString('unlockMethodLabel') + '</div>' +
        (canUseToken ? '<div class="aa-modal-choice aa-modal-choice--gold"> ' +
            formatString(getString('tokenAvailableLabel'), {count: tokensAvailable}) +
            '</div>' : '') +
        (canBuyWithCoins ? '<div class="aa-modal-choice aa-modal-choice--accent"> ' +
            formatString(getString('payCoinsLabel'), {price: price.toLocaleString(), balance: userCoins.toLocaleString()}) +
            '</div>' : '<div class="aa-modal-choice aa-modal-choice--error"> ' +
            formatString(getString('insufficientCoinsLabel'), {price: price.toLocaleString()}) +
            '</div>') +
        '</div>' +
        '<div class="aa-modal-actions">' +
        (canUseToken ? '<button class="petUnlockToken aa-modal-btn aa-modal-btn--gold aa-modal-btn--action" type="button"> ' + getString('useTokenButton') + '</button>' : '') +
        (canBuyWithCoins ? '<button class="petUnlockCoins aa-modal-btn aa-modal-btn--accent aa-modal-btn--action" type="button"> ' +
            formatString(getString('payButtonLabel'), {price: price.toLocaleString()}) +
            '</button>' : '') +
        '<button class="petUnlockCancel aa-modal-btn aa-modal-btn--gray aa-modal-btn--action" type="button">' + getString('cancelLabel') + '</button>' +
        '</div>';
    
    // Token unlock handler
    if (canUseToken) {
        var tokenBtn = content.querySelector('.petUnlockToken');
        if (tokenBtn) {
            tokenBtn.onclick = function() {
                this.disabled = true;
                this.textContent = getString('unlockingLabel');
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
                this.textContent = getString('processingLabel');
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
    callAscendAjax('local_ascend_rewards_pet_unlock', {pet_id: petId, unlock_type: unlockType})
        .then(function(result) {
            if (result && result.success) {
                closeModal(modal);
                showPetSuccessModal(petName, videoFile, unlockType, result.new_balance);
            } else {
                var errorPrefix = getString('errorPrefix');
                var fallbackMessage = getString('errorUnlockPet');
                var message = (result && (result.error || result.message)) || fallbackMessage;
                showAlert('' + errorPrefix + message, true);
                closeModal(modal);
            }
        })
        .catch(function(error) {
            var errorPrefix = getString('errorPrefix');
            var fallbackMessage = getString('errorUnlockPet');
            showAlert('' + errorPrefix + getAjaxErrorMessage(error, fallbackMessage), true);
            closeModal(modal);
        });
}

function showPetSuccessModal(petName, videoFile, unlockType, newBalance) {
    var elements = createModal('petSuccessModal', '#FF9500');
    var modal = elements.modal;
    var content = elements.content;
    
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/videos/' + videoFile;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/pets_circular/' + videoFile.replace('.mp4', '.png');
    
    var unlockMethodLabel = unlockType === 'token' ? getString('tokenLabel') : getString('coinsLabel');

    content.innerHTML =
        '<h2 class="aa-modal-title"> ' + getString('petAdoptedTitle') + '</h2>' +
        '<div class="aa-modal-subtitle">' + petName + '</div>' +
        '<div class="aa-modal-circle">' +
        '<video id="petSuccessVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">' + getString('petVillainRevealedTitle') + '</div>' +
        '<div class="aa-modal-info-text">' + getString('petVillainRevealedText') + '</div>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + petName + '_pet.png" class="aa-modal-btn aa-modal-btn--cyan aa-modal-btn--full">' + getString('downloadPetLabel') + '</a>' +
        '<div class="aa-modal-note aa-modal-note--muted">' + formatString(getString('unlockedWithLabel'), {method: unlockMethodLabel}) + '</div>' +
        (unlockType === 'coin'
            ? '<div class="aa-modal-note aa-modal-note--muted">' +
                formatString(getString('newBalanceLabel'), {
                    balance: newBalance.toLocaleString(),
                    coinsLabel: getString('coinsLabel')
                }) +
                '</div>'
            : '') +
        '<button id="petSuccessClose" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--center" type="button">' + getString('continueLabel') + '</button>';
    
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
        '<h2 class="aa-modal-title"> ' + petName + '</h2>' +
        '<div class="aa-modal-circle">' +
        '<video id="petReviewVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + petName + '_pet.png" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--full">' + getString('downloadPetLabel') + '</a>' +
        '<div class="aa-modal-actions">' +
        '<button id="petReviewClose" class="aa-modal-btn aa-modal-btn--gray aa-modal-btn--sm aa-modal-btn--center" type="button">' + getString('closeLabel') + '</button>' +
        '</div>';
    
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
    var villainImageKey = normalizeImageName(villainImageName);
    
    // Just show modal regardless - it will display what options are available
    var elements = createModal('villainUnlockModal', '#06b6d4');
    var modal = elements.modal;
    var content = elements.content;
    
    content.innerHTML =
        '<h2 class="aa-modal-title"> ' + getString('villainUnlockTitle') + '</h2>' +
        '<div class="aa-modal-subtitle">' + villainName + '</div>' +
        '<div class="aa-modal-circle aa-modal-circle--lg aa-modal-circle--tint">' +
        '<img src="' + M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/' + villainImageKey + '.png" alt="' + villainName + '" class="aa-modal-image aa-modal-image--locked" onerror="this.style.display=\'none\'">' +
        '<div class="aa-modal-lock-overlay">' +
        '<i class="fa-solid fa-lock aa-modal-lock-icon"></i>' +
        '</div>' +
        '</div>' +
        '<div class="aa-modal-info">' +
        '<div class="aa-modal-info-title aa-modal-info-title--muted">' + getString('unlockMethodLabel') + '</div>' +
        (canUseToken ? '<div class="aa-modal-choice aa-modal-choice--gold"> ' +
            formatString(getString('tokenAvailableLabel'), {count: tokensAvailable}) +
            '</div>' : '') +
        (canBuyWithCoins ? '<div class="aa-modal-choice aa-modal-choice--accent"> ' +
            formatString(getString('payCoinsLabel'), {price: price.toLocaleString(), balance: userCoins.toLocaleString()}) +
            '</div>' : '<div class="aa-modal-choice aa-modal-choice--error"> ' +
            formatString(getString('insufficientCoinsLabel'), {price: price.toLocaleString()}) +
            '</div>') +
        '</div>' +
        '<div class="aa-modal-actions">' +
        (canUseToken ? '<button class="villainUnlockToken aa-modal-btn aa-modal-btn--gold aa-modal-btn--action" type="button"> ' + getString('useTokenButton') + '</button>' : '') +
        (canBuyWithCoins ? '<button class="villainUnlockCoins aa-modal-btn aa-modal-btn--accent aa-modal-btn--action" type="button"> ' +
            formatString(getString('payButtonLabel'), {price: price.toLocaleString()}) +
            '</button>' : '') +
        '<button class="villainUnlockCancel aa-modal-btn aa-modal-btn--gray aa-modal-btn--action" type="button">' + getString('cancelLabel') + '</button>' +
        '</div>';
    // Token unlock handler
    if (canUseToken) {
        var tokenBtn = content.querySelector('.villainUnlockToken');
        if (tokenBtn) {
            tokenBtn.onclick = function() {
                this.disabled = true;
                this.textContent = getString('unlockingLabel');
                processVillainUnlock(villainId, 'token', 0, modal, villainName, villainImageKey, villainVideo);
            };
        }
    }
    
    // Coin unlock handler
    if (canBuyWithCoins) {
        var coinBtn = content.querySelector('.villainUnlockCoins');
        if (coinBtn) {
            coinBtn.onclick = function() {
                this.disabled = true;
                this.textContent = getString('processingLabel');
                processVillainUnlock(villainId, 'coin', price, modal, villainName, villainImageKey, villainVideo);
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
    callAscendAjax('local_ascend_rewards_villain_unlock', {villain_id: villainId, unlock_type: unlockType})
        .then(function(result) {
            if (result && result.success) {
                closeModal(modal);
                showVillainSuccessModal(villainName, villainImageName, villainVideo, unlockType, result.new_balance);
            } else {
                var errorPrefix = getString('errorPrefix');
                var fallbackMessage = getString('errorUnlockVillain');
                var message = (result && (result.error || result.message)) || fallbackMessage;
                showAlert('' + errorPrefix + message, true);
                closeModal(modal);
            }
        })
        .catch(function(error) {
            var errorPrefix = getString('errorPrefix');
            var fallbackMessage = getString('errorUnlockVillain');
            showAlert('' + errorPrefix + getAjaxErrorMessage(error, fallbackMessage), true);
            closeModal(modal);
        });
}

function showVillainSuccessModal(villainName, villainImageName, villainVideo, unlockType, newBalance) {
    var elements = createModal('villainSuccessModal', '#dc2626');
    var modal = elements.modal;
    var content = elements.content;
    
    var villainImageKey = normalizeImageName(villainImageName);
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/videos/' + villainVideo;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/villains_circular/' + villainImageKey + '.png';
    
    var unlockMethodLabel = unlockType === 'token' ? getString('tokenLabel') : getString('coinsLabel');

    content.innerHTML =
        '<h2 class="aa-modal-title"> ' + getString('villainUnlockedTitle') + '</h2>' +
        '<div class="aa-modal-subtitle">' + villainName + '</div>' +
        '<div class="aa-modal-circle">' +
        '<video id="villainSuccessVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">' + getString('villainStorybookUnlockedTitle') + '</div>' +
        '<div class="aa-modal-info-text">' + getString('villainStorybookUnlockedText') + '</div>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + villainName + '_villain.png" class="aa-modal-btn aa-modal-btn--cyan aa-modal-btn--full">' + getString('downloadVillainLabel') + '</a>' +
        '<div class="aa-modal-note aa-modal-note--muted">' + formatString(getString('unlockedWithLabel'), {method: unlockMethodLabel}) + '</div>' +
        (unlockType === 'coin'
            ? '<div class="aa-modal-note aa-modal-note--muted">' +
                formatString(getString('newBalanceLabel'), {
                    balance: newBalance.toLocaleString(),
                    coinsLabel: getString('coinsLabel')
                }) +
                '</div>'
            : '') +
        '<button id="villainSuccessClose" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--center" type="button">' + getString('continueLabel') + '</button>';
    
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
    
    var villainImageKey = normalizeImageName(villainImageName);
    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/videos/' + villainVideo;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/villains_circular/' + villainImageKey + '.png';
    
    content.innerHTML =
        '<h2 class="aa-modal-title"> ' + villainName + '</h2>' +
        '<div class="aa-modal-circle">' +
        '<video id="villainReviewVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + villainName + '_villain.png" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--full">' + getString('downloadVillainLabel') + '</a>' +
        '<div class="aa-modal-actions">' +
        '<button id="villainReviewClose" class="aa-modal-btn aa-modal-btn--gray aa-modal-btn--sm aa-modal-btn--center" type="button">' + getString('closeLabel') + '</button>' +
        '</div>';
    
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

export {
    showAvatarUnlockModal,
    showAvatarReviewModal,
    showPetUnlockModal,
    showPetReviewModal,
    showVillainUnlockModal,
    showVillainReviewModal,
    showVillainSuccessModal
};
