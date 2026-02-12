/**
 * Avatar, Pet, and Villain Modal Functions
 * Token/Coin unlock system with video previews
 */

// Modal utility functions.
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

// Compatibility fallback map for sites where external functions have not been.
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
    return new Promise(function (resolve, reject) {
        var endpoint = legacyEndpointMap[methodname];
        if (!endpoint) {
            reject(new Error('AJAX request failed'));
            return;
        }
        if (typeof M === 'undefined' || !M.cfg || !M.cfg.wwwroot) {
            reject(new Error('Moodle config not available'));
            return;
        }

        var payload = [];
        var safeArgs = args || {};
        Object.keys(safeArgs).forEach(function (key) {
            payload.push(encodeURIComponent(key) + '=' + encodeURIComponent(safeArgs[key]));
        });
        if (M.cfg.sesskey) {
            payload.push('sesskey=' + encodeURIComponent(M.cfg.sesskey));
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/local/ascend_rewards/' + endpoint, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

        xhr.onload = function () {
            var response = {};
            try {
                response = JSON.parse(xhr.responseText || '{}');
            } catch (parseError) {
                reject(new Error('Invalid JSON response from server'));
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(response);
            } else {
                reject(new Error((response && response.error) || 'AJAX request failed'));
            }
        };

        xhr.onerror = function () {
            reject(new Error('Network error'));
        };

        xhr.send(payload.join('&'));
    });
}

function callAscendAjax(methodname, args) {
    return new Promise(function (resolve, reject) {
        try {
            require(['core/ajax'], function (Ajax) {
                var requests = Ajax.call([{methodname: methodname, args: args}]);
                if (requests && requests[0]) {
                    requests[0].then(resolve).catch(function (error) {
                        if (shouldUseLegacyFallback(error)) {
                            callLegacyAjax(methodname, args).then(resolve).catch(reject);
                            return;
                        }
                        reject(error);
                    });
                } else {
                    reject(new Error('AJAX request failed'));
                }
            }, function (error) {
                if (shouldUseLegacyFallback(error)) {
                    callLegacyAjax(methodname, args).then(resolve).catch(reject);
                    return;
                }
                reject(error);
            });
        } catch (err) {
            reject(err);
        }
    });
}

// Avatar Unlock Modal (Token Only).
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
        '<button id="avatarModalClose" class="aa-modal-close aa-modal-close--muted" type="button" aria-label="Close"></button>' +
        '<h2 class="aa-modal-title aa-modal-title--sm">Unlock ' + name + '?</h2>' +
        '<div class="aa-modal-circle">' +
        '<img src="' + avatarImagePath + '" class="aa-modal-image aa-modal-image--locked">' +
        '<div class="aa-modal-lock-overlay">' +
        '<i class="fa-solid fa-lock aa-modal-lock-icon"></i>' +
        '</div>' +
        '</div>' +
        '<div class="aa-modal-note aa-modal-note--muted">Level ' + level + ' Avatar Costs 1 Token</div>' +
        '<button id="avatarUnlockConfirm" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--lg aa-modal-btn--full" type="button"> Unlock Avatar</button>' +
        '<div class="aa-modal-note aa-modal-note--center">Once unlocked, click to view video & download</div>';

    document.body.appendChild(modal);

    // Close button.
    document.getElementById('avatarModalClose').addEventListener('click', function () {
        closeModal(modal);
    });

    // Confirm button.
    document.getElementById('avatarUnlockConfirm').addEventListener('click', function () {
        this.disabled = true;
        this.textContent = 'Unlocking...';

        callAscendAjax('local_ascend_rewards_avatar_unlock', {avatar: avatar, level: level})
            .then(function (result) {
                if (result && result.success) {
                    closeModal(modal);
                    showAvatarSuccessModal(name, videoFile);
                } else {
                    alert('Error: ' + ((result && (result.error || result.message)) || 'Could not unlock avatar'));
                    closeModal(modal);
                }
            })
            .catch(function (error) {
                alert('Error: ' + getAjaxErrorMessage(error, 'Could not unlock avatar'));
                closeModal(modal);
            });
    });

    // Close on backdrop click.
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// Avatar Success Modal (shows after unlock).
function showAvatarSuccessModal(name, videoFile, isYouTube = false) {
    var elements = createModal('avatarSuccessModal', '#06b6d4');
    var modal = elements.modal;
    var content = elements.content;

    var videoPath = isYouTube
        ? `https : //www.youtube.com / embed / ${videoFile} ? autoplay = 1 & loop = 1`
        : M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/Videos/' + videoFile;

    var avatarFile = videoFile.replace('.mp4', '.png');
    var circularImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/circular%20avatars/' + avatarFile;

    content.innerHTML =
        '<button id="avatarSuccessClose" class="aa-modal-close aa-modal-close--pink aa-modal-close--grow" type="button" aria-label="Close"></button>' +
        '<h2 class="aa-modal-title"> ' + name + ' Unlocked!</h2>' +
        '<div class="aa-modal-circle">' +
        (isYouTube
            ? ` < iframe id = "avatarSuccessVideo" class = "aa-modal-iframe" src = "${videoPath}" frameborder = "0" allow = "autoplay; encrypted-media" allowfullscreen > < / iframe > `
            : '<video id="avatarSuccessVideo" class="aa-modal-video" autoplay loop>' +
               '<source src="' + videoPath + '" type="video/mp4">' +
               '</video>'
        ) +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">The ' + name + '\'s Pet is Revealed!</div>' +
        '<div class="aa-modal-info-text">Check the Pets section below to adopt your new pet</div>' +
        '</div>' +
        '<a href="' + circularImagePath + '" download="' + name + '_avatar.png" class="aa-modal-btn aa-modal-btn--cyan aa-modal-btn--full"> Download Avatar Image</a>' +
        '<div class="aa-modal-note aa-modal-note--center">Use this image in your profile!</div>';

    document.body.appendChild(modal);

    // Set video volume and autoplay for non-YouTube videos.
    if (!isYouTube) {
        setTimeout(function () {
            var video = document.getElementById('avatarSuccessVideo');
            if (video) {
                video.volume = 0.7;
                video.muted = false;
            }
        }, 100);
    }

    // Close button.
    document.getElementById('avatarSuccessClose').addEventListener('click', function () {
        closeModal(modal);
        location.reload(true);
    });

    // Close on backdrop click.
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal(modal);
            location.reload(true);
        }
    });
}

// Avatar Review Modal (for already unlocked avatars).
function showAvatarReviewModal(avatarFile, name, videoFile) {
    var elements = createModal('avatarReviewModal', '#FFD700');
    var modal = elements.modal;
    var content = elements.content;

    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/Videos/' + videoFile;
    var circularImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/Avatars/circular%20avatars/' + avatarFile.replace('.png', '.png');

    content.innerHTML =
        '<button id="avatarReviewCloseX" class="aa-modal-close aa-modal-close--muted" type="button" aria-label="Close"></button>' +
        '<h2 class="aa-modal-title"> ' + name + '</h2>' +
        '<div class="aa-modal-circle">' +
        '<video id="avatarReviewVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">The ' + name + '\'s Pet is Revealed!</div>' +
        '<div class="aa-modal-info-text">Check the Pets section below to adopt your new pet</div>' +
        '</div>' +
        '<a href="' + circularImagePath + '" download="' + name + '_avatar.png" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--full"> Download Avatar Image</a>' +
        '<div class="aa-modal-note aa-modal-note--center">Use this image in your profile!</div>' +
        '<div class="aa-modal-actions">' +
        '<button id="avatarReviewCloseBtn" class="aa-modal-btn aa-modal-btn--gray aa-modal-btn--sm aa-modal-btn--center" type="button">Close</button>' +
        '</div>';

    document.body.appendChild(modal);

    // Set video volume and unmute.
    var video = document.getElementById('avatarReviewVideo');
    if (video) {
        video.volume = 0.7;
        video.muted = false;
    }

    // Close button.
    document.getElementById('avatarReviewCloseX').addEventListener('click', function () {
        closeModal(modal);
    });

    document.getElementById('avatarReviewCloseBtn').addEventListener('click', function () {
        closeModal(modal);
    });

    // Close on backdrop click.
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// Pet Unlock Modal (Token OR Coins).
function showPetUnlockModal(petId, petName, price, videoFile, tokensAvailable, userCoins) {
    var canUseToken = tokensAvailable > 0;
    var canBuyWithCoins = userCoins >= price;

    // Just show modal regardless - it will display what options are available.
    var elements = createModal('petUnlockModal', '#ec4899');
    var modal = elements.modal;
    var content = elements.content;

    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/Videos/' + videoFile;

    content.innerHTML =
        '<h2 class="aa-modal-title"> Adopt Pet</h2>' +
        '<div class="aa-modal-subtitle">' + petName + '</div>' +
        '<div class="aa-modal-circle aa-modal-circle--lg aa-modal-circle--tint">' +
        '<img src="' + M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/' + petName.toLowerCase().replace(/\s/g, '_') + '.png" alt="' + petName + '" class="aa-modal-image aa-modal-image--locked" onerror="this.style.display=\'none\'">' +
        '<div class="aa-modal-lock-overlay">' +
        '<i class="fa-solid fa-lock aa-modal-lock-icon"></i>' +
        '</div>' +
        '</div>' +
        '<div class="aa-modal-info">' +
        '<div class="aa-modal-info-title aa-modal-info-title--muted">Choose unlock method:</div>' +
        (canUseToken ? '<div class="aa-modal-choice aa-modal-choice--gold"> Use Token (' + tokensAvailable + ' available)</div>' : '') +
        (canBuyWithCoins ? '<div class="aa-modal-choice aa-modal-choice--accent"> Pay ' + price.toLocaleString() + ' Coins (Balance: ' + userCoins.toLocaleString() + ')</div>' : '<div class="aa-modal-choice aa-modal-choice--error"> Insufficient coins (need ' + price.toLocaleString() + ')</div>') +
        '</div>' +
        '<div class="aa-modal-actions">' +
        (canUseToken ? '<button class="petUnlockToken aa-modal-btn aa-modal-btn--gold aa-modal-btn--action" type="button"> Use Token</button>' : '') +
        (canBuyWithCoins ? '<button class="petUnlockCoins aa-modal-btn aa-modal-btn--accent aa-modal-btn--action" type="button"> Pay ' + price.toLocaleString() + '</button>' : '') +
        '<button class="petUnlockCancel aa-modal-btn aa-modal-btn--gray aa-modal-btn--action" type="button">Cancel</button>' +
        '</div>';

    // Token unlock handler.
    if (canUseToken) {
        var tokenBtn = content.querySelector('.petUnlockToken');
        if (tokenBtn) {
            tokenBtn.onclick = function () {
                this.disabled = true;
                this.textContent = 'Unlocking...';
                processPetUnlock(petId, 'token', 0, modal, petName, videoFile);
            };
        }
    }

    // Coin unlock handler.
    if (canBuyWithCoins) {
        var coinBtn = content.querySelector('.petUnlockCoins');
        if (coinBtn) {
            coinBtn.onclick = function () {
                this.disabled = true;
                this.textContent = 'Processing...';
                processPetUnlock(petId, 'coin', price, modal, petName, videoFile);
            };
        }
    }

    // Cancel button handler.
    var cancelBtn = content.querySelector('.petUnlockCancel');
    if (cancelBtn) {
        cancelBtn.onclick = function () {
            closeModal(modal);
        };
    }

    // Close on backdrop click.
    modal.onclick = function (e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    };

    document.body.appendChild(modal);
}

function processPetUnlock(petId, unlockType, price, modal, petName, videoFile) {
    callAscendAjax('local_ascend_rewards_pet_unlock', {pet_id: petId, unlock_type: unlockType})
        .then(function (result) {
            if (result && result.success) {
                closeModal(modal);
                showPetSuccessModal(petName, videoFile, unlockType, result.new_balance);
            } else {
                alert('Error: ' + ((result && (result.error || result.message)) || 'Could not adopt pet'));
                closeModal(modal);
            }
        })
        .catch(function (error) {
            alert('Error: ' + getAjaxErrorMessage(error, 'Could not adopt pet'));
            closeModal(modal);
        });
}

function showPetSuccessModal(petName, videoFile, unlockType, newBalance) {
    var elements = createModal('petSuccessModal', '#FF9500');
    var modal = elements.modal;
    var content = elements.content;

    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/videos/' + videoFile;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/pets/pets_circular/' + videoFile.replace('.mp4', '.png');

    content.innerHTML =
        '<h2 class="aa-modal-title"> Pet Adopted!</h2>' +
        '<div class="aa-modal-subtitle">' + petName + '</div>' +
        '<div class="aa-modal-circle">' +
        '<video id="petSuccessVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">The Villain is Revealed!</div>' +
        '<div class="aa-modal-info-text">Check the Villain section below to unleash your new villain.</div>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + petName + '_pet.png" class="aa-modal-btn aa-modal-btn--cyan aa-modal-btn--full"> Download Pet Image</a>' +
        '<div class="aa-modal-note aa-modal-note--muted">Unlocked with: ' + (unlockType === 'token' ? ' Token' : ' Coins') + '</div>' +
        (unlockType === 'coin' ? '<div class="aa-modal-note aa-modal-note--muted">New balance: ' + newBalance.toLocaleString() + ' coins</div>' : '') +
        '<button id="petSuccessClose" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--center" type="button">Continue</button>';

    document.body.appendChild(modal);

    var video = document.getElementById('petSuccessVideo');
    video.volume = 0.7;

    document.getElementById('petSuccessClose').addEventListener('click', function () {
        closeModal(modal);
        location.reload(true);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal(modal);
            location.reload(true);
        }
    });
}

// Pet Review Modal.
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
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">The Villain is Revealed!</div>' +
        '<div class="aa-modal-info-text">Check the Villain section below to unleash your new villain.</div>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + petName + '_pet.png" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--full"> Download Pet Image</a>' +
        '<div class="aa-modal-actions">' +
        '<button id="petReviewClose" class="aa-modal-btn aa-modal-btn--gray aa-modal-btn--sm aa-modal-btn--center" type="button">Close</button>' +
        '</div>';

    document.body.appendChild(modal);

    var video = document.getElementById('petReviewVideo');
    video.volume = 0.7;

    document.getElementById('petReviewClose').addEventListener('click', function () {
        closeModal(modal);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// Villain Unlock Modal (Token OR Coins).
function showVillainUnlockModal(villainId, villainName, price, villainImageName, villainVideo, tokensAvailable, userCoins) {
    var canUseToken = tokensAvailable > 0;
    var canBuyWithCoins = userCoins >= price;

    // Just show modal regardless - it will display what options are available.
    var elements = createModal('villainUnlockModal', '#06b6d4');
    var modal = elements.modal;
    var content = elements.content;

    content.innerHTML =
        '<h2 class="aa-modal-title"> Unlock Villain</h2>' +
        '<div class="aa-modal-subtitle">' + villainName + '</div>' +
        '<div class="aa-modal-circle aa-modal-circle--lg aa-modal-circle--tint">' +
        '<img src="' + M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/' + villainImageName + '.png" alt="' + villainName + '" class="aa-modal-image aa-modal-image--locked" onerror="this.style.display=\'none\'">' +
        '<div class="aa-modal-lock-overlay">' +
        '<i class="fa-solid fa-lock aa-modal-lock-icon"></i>' +
        '</div>' +
        '</div>' +
        '<div class="aa-modal-info">' +
        '<div class="aa-modal-info-title aa-modal-info-title--muted">Choose unlock method:</div>' +
        (canUseToken ? '<div class="aa-modal-choice aa-modal-choice--gold"> Use Token (' + tokensAvailable + ' available)</div>' : '') +
        (canBuyWithCoins ? '<div class="aa-modal-choice aa-modal-choice--accent"> Pay ' + price.toLocaleString() + ' Coins (Balance: ' + userCoins.toLocaleString() + ')</div>' : '<div class="aa-modal-choice aa-modal-choice--error"> Insufficient coins (need ' + price.toLocaleString() + ')</div>') +
        '</div>' +
        '<div class="aa-modal-actions">' +
        (canUseToken ? '<button class="villainUnlockToken aa-modal-btn aa-modal-btn--gold aa-modal-btn--action" type="button"> Use Token</button>' : '') +
        (canBuyWithCoins ? '<button class="villainUnlockCoins aa-modal-btn aa-modal-btn--accent aa-modal-btn--action" type="button"> Pay ' + price.toLocaleString() + '</button>' : '') +
        '<button class="villainUnlockCancel aa-modal-btn aa-modal-btn--gray aa-modal-btn--action" type="button">Cancel</button>' +
        '</div>';
    // Token unlock handler.
    if (canUseToken) {
        var tokenBtn = content.querySelector('.villainUnlockToken');
        if (tokenBtn) {
            tokenBtn.onclick = function () {
                this.disabled = true;
                this.textContent = 'Unlocking...';
                processVillainUnlock(villainId, 'token', 0, modal, villainName, villainImageName, villainVideo);
            };
        }
    }

    // Coin unlock handler.
    if (canBuyWithCoins) {
        var coinBtn = content.querySelector('.villainUnlockCoins');
        if (coinBtn) {
            coinBtn.onclick = function () {
                this.disabled = true;
                this.textContent = 'Processing...';
                processVillainUnlock(villainId, 'coin', price, modal, villainName, villainImageName, villainVideo);
            };
        }
    }

    // Cancel button handler.
    var cancelBtn = content.querySelector('.villainUnlockCancel');
    if (cancelBtn) {
        cancelBtn.onclick = function () {
            closeModal(modal);
        };
    }

    // Close on backdrop click.
    modal.onclick = function (e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    };

    document.body.appendChild(modal);
}

function processVillainUnlock(villainId, unlockType, price, modal, villainName, villainImageName, villainVideo) {
    callAscendAjax('local_ascend_rewards_villain_unlock', {villain_id: villainId, unlock_type: unlockType})
        .then(function (result) {
            if (result && result.success) {
                closeModal(modal);
                showVillainSuccessModal(villainName, villainImageName, villainVideo, unlockType, result.new_balance);
            } else {
                alert('Error: ' + ((result && (result.error || result.message)) || 'Could not unlock villain'));
                closeModal(modal);
            }
        })
        .catch(function (error) {
            alert('Error: ' + getAjaxErrorMessage(error, 'Could not unlock villain'));
            closeModal(modal);
        });
}

function showVillainSuccessModal(villainName, villainImageName, villainVideo, unlockType, newBalance) {
    var elements = createModal('villainSuccessModal', '#dc2626');
    var modal = elements.modal;
    var content = elements.content;

    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/videos/' + villainVideo;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/villains_circular/' + villainImageName + '.png';

    content.innerHTML =
        '<h2 class="aa-modal-title"> Villain Unlocked!</h2>' +
        '<div class="aa-modal-subtitle">' + villainName + '</div>' +
        '<div class="aa-modal-circle">' +
        '<video id="villainSuccessVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">Your Story Book is Unlocked!</div>' +
        '<div class="aa-modal-info-text">Check the Story Book section below to view the story.</div>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + villainName + '_villain.png" class="aa-modal-btn aa-modal-btn--cyan aa-modal-btn--full"> Download Villain Image</a>' +
        '<div class="aa-modal-note aa-modal-note--muted">Unlocked with: ' + (unlockType === 'token' ? ' Token' : ' Coins') + '</div>' +
        (unlockType === 'coin' ? '<div class="aa-modal-note aa-modal-note--muted">New balance: ' + newBalance.toLocaleString() + ' coins</div>' : '') +
        '<button id="villainSuccessClose" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--center" type="button">Continue</button>';

    document.body.appendChild(modal);

    var video = document.getElementById('villainSuccessVideo');
    video.volume = 0.7;

    document.getElementById('villainSuccessClose').addEventListener('click', function () {
        closeModal(modal);
        location.reload(true);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal(modal);
            location.reload(true);
        }
    });
}

// Villain Review Modal.
function showVillainReviewModal(villainName, villainId, villainImageName, villainVideo) {
    var elements = createModal('villainReviewModal', '#06b6d4');
    var modal = elements.modal;
    var content = elements.content;

    var videoPath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/videos/' + villainVideo;
    var downloadImagePath = M.cfg.wwwroot + '/local/ascend_rewards/pix/villains/villains_circular/' + villainImageName + '.png';

    content.innerHTML =
        '<h2 class="aa-modal-title"> ' + villainName + '</h2>' +
        '<div class="aa-modal-circle">' +
        '<video id="villainReviewVideo" class="aa-modal-video" autoplay loop>' +
        '<source src="' + videoPath + '" type="video/mp4">' +
        '</video>' +
        '</div>' +
        '<div class="aa-modal-info aa-modal-info--pink">' +
        '<div class="aa-modal-info-title">Your Story Book is Unlocked!</div>' +
        '<div class="aa-modal-info-text">Check the Story Book section below to view the story.</div>' +
        '</div>' +
        '<a href="' + downloadImagePath + '" download="' + villainName + '_villain.png" class="aa-modal-btn aa-modal-btn--accent aa-modal-btn--full"> Download Villain Image</a>' +
        '<div class="aa-modal-actions">' +
        '<button id="villainReviewClose" class="aa-modal-btn aa-modal-btn--gray aa-modal-btn--sm aa-modal-btn--center" type="button">Close</button>' +
        '</div>';

    document.body.appendChild(modal);

    var video = document.getElementById('villainReviewVideo');
    video.volume = 0.7;

    document.getElementById('villainReviewClose').addEventListener('click', function () {
        closeModal(modal);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}
