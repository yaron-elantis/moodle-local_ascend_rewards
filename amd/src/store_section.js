import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {
    init as initAvatarModals,
    showPetUnlockModal,
    showVillainUnlockModal
} from 'local_ascend_rewards/avatar_modals';

const PET_VIDEO_MAP = {
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
    112: 'dragonet.mp4',
    113: 'mastiff.mp4',
    114: 'raven.mp4',
    115: 'tiger.mp4',
    116: 'wolf.mp4',
    117: 'polar_bear.mp4'
};

const VILLAIN_VIDEO_MAP = {
    'elf_dryad': 'elf_dryad.mp4',
    'ent_blightmind': 'ent_blightmind.mp4',
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

let ajaxRequestFailed = '';

const callAjax = (methodname, args) => {
    const requests = Ajax.call([{methodname, args}]);
    return requests && requests[0]
        ? requests[0]
        : Promise.reject(new Error(ajaxRequestFailed || ''));
};

const showAlert = (strings, message, isError = false) => {
    if (!message) {
        return;
    }
    const title = isError ? strings.errorTitle : strings.alertTitle;
    Notification.alert(title || '', message, strings.closeLabel || '');
};

const confirmAction = async(strings, message, triggerElement) => {
    if (!message) {
        return false;
    }

    if (typeof Notification.saveCancelPromise === 'function') {
        try {
            await Notification.saveCancelPromise(
                strings.confirmTitle || strings.alertTitle || '',
                message,
                strings.purchaseConfirmActionLabel || strings.confirmActionLabel || '',
                {triggerElement}
            );
            return true;
        } catch (error) {
            return false;
        }
    }

    return window.confirm(message);
};


const initXpMultiplierTimer = (strings) => {
    const timerEl = document.getElementById('xpMultiplierTimer_store');
    if (!timerEl) {
        return;
    }

    const updateTimer = () => {
        const expiresAt = parseInt(timerEl.getAttribute('data-expires') || '0', 10);
        const now = Math.floor(Date.now() / 1000);
        const remaining = expiresAt - now;

        if (remaining <= 0) {
            timerEl.textContent = strings.expiredLabel;
            return;
        }

        const hours = Math.floor(remaining / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);
        const seconds = remaining % 60;

        timerEl.textContent = `${hours}h ${minutes}m ${seconds}s`;
    };

    updateTimer();
    setInterval(updateTimer, 1000);
};

const initStorePurchases = (strings) => {
    const storeBuyBtns = document.querySelectorAll('.store-buy-btn:not(.pet-buy-btn):not(.villain-buy-btn)');
    storeBuyBtns.forEach((btn) => {
        btn.addEventListener('click', async() => {
            const itemId = btn.getAttribute('data-item-id');
            const itemName = btn.getAttribute('data-item-name');
            const itemPrice = btn.getAttribute('data-item-price');

            const confirmMessage = `${strings.purchaseConfirmPrefix}${itemName}${strings.purchaseConfirmMid}${itemPrice}${strings.purchaseConfirmSuffix}`;
            const confirmed = await confirmAction(strings, confirmMessage, btn);
            if (!confirmed) {
                return;
            }

            btn.disabled = true;
            btn.textContent = strings.processingLabel;

            callAjax('local_ascend_rewards_store_purchase', {item_id: parseInt(itemId, 10)})
                .then((result) => {
                    if (result && result.success) {
                        showAlert(
                            strings,
                            `${strings.purchaseSuccessLabel}\n\n${strings.remainingBalanceLabel} ${result.remaining_coins.toLocaleString()} ${strings.coinsLabel}`
                        );
                        window.location.reload();
                        return;
                    }

                    showAlert(
                        strings,
                        `${strings.errorPrefix || ""}${(result && result.error) || strings.purchaseErrorLabel}`,
                        true
                    );
                    btn.disabled = false;
                    btn.textContent = `${strings.purchaseButtonPrefix}${itemPrice} ${strings.coinsLabel}`;
                })
                .catch(() => {
                    showAlert(strings, strings.purchaseProcessingErrorLabel, true);
                    btn.disabled = false;
                    btn.textContent = `${strings.purchaseButtonPrefix}${itemPrice} ${strings.coinsLabel}`;
                });
        });
    });
};

const initStoreActivation = (strings) => {
    document.querySelectorAll('.store-activate-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const itemId = parseInt(btn.getAttribute('data-item-id') || '0', 10);

            btn.disabled = true;
            btn.textContent = strings.processingLabel;

            callAjax('local_ascend_rewards_store_activate', {item_id: itemId})
                .then((result) => {
                    if (result && result.success) {
                        showAlert(strings, result.message);
                        window.location.reload();
                        return;
                    }

                    showAlert(
                        strings,
                        `${strings.errorPrefix || ""}${(result && result.error) || strings.activationErrorLabel}`,
                        true
                    );
                    btn.disabled = false;
                    btn.textContent = strings.activateLabel;
                })
                .catch(() => {
                    showAlert(strings, strings.activationProcessingErrorLabel, true);
                    btn.disabled = false;
                    btn.textContent = strings.activateLabel;
                });
        });
    });
};

const initPetPurchases = (tokensAvailable, coinBalance, strings) => {
    document.querySelectorAll('.pet-buy-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const petId = parseInt(btn.getAttribute('data-item-id') || '0', 10);
            const petName = btn.getAttribute('data-item-name');
            const petPrice = parseInt(btn.getAttribute('data-item-price') || '0', 10);
            const petVideo = PET_VIDEO_MAP[petId] || 'lynx.mp4';

            showPetUnlockModal(petId, petName, petPrice, petVideo, tokensAvailable, coinBalance);
        });
    });

    document.querySelectorAll('.store-frame-pet').forEach((frame) => {
        frame.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const btn = frame.closest('.store-card').querySelector('.pet-buy-btn');
                if (btn && !btn.disabled) {
                    btn.click();
                }
            }
        });
        frame.addEventListener('click', () => {
            const btn = frame.closest('.store-card').querySelector('.pet-buy-btn');
            if (btn && !btn.disabled) {
                btn.click();
            }
        });
    });
};

const initVillainPurchases = (tokensAvailable, coinBalance) => {
    document.querySelectorAll('.villain-buy-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const villainId = parseInt(btn.getAttribute('data-item-id') || '0', 10);
            const villainName = btn.getAttribute('data-item-name');
            const villainPrice = parseInt(btn.getAttribute('data-item-price') || '0', 10);
            const villainIconName = btn.getAttribute('data-villain-icon') || '';
            const videoKey = villainIconName.replace('.png', '');
            const villainVideo = VILLAIN_VIDEO_MAP[videoKey] || 'elf_dryad.mp4';

            showVillainUnlockModal(
                villainId,
                villainName,
                villainPrice,
                villainIconName,
                villainVideo,
                tokensAvailable,
                coinBalance
            );
        });
    });

    document.querySelectorAll('.store-frame-villain').forEach((frame) => {
        frame.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const btn = frame.closest('.store-card').querySelector('.villain-buy-btn');
                if (btn && !btn.disabled) {
                    btn.click();
                }
            }
        });
        frame.addEventListener('click', () => {
            const btn = frame.closest('.store-card').querySelector('.villain-buy-btn');
            if (btn && !btn.disabled) {
                btn.click();
            }
        });
    });
};

const initMysteryBox = (strings, urls) => {
    const mysteryBtns = document.querySelectorAll('.mysterybox-open-btn');
    const mysteryModal = document.getElementById('mysteryBoxModal');
    const boxesContainer = document.getElementById('boxesContainer');
    const resultDisplay = document.getElementById('resultDisplay');
    const resultMessage = document.getElementById('resultMessage');
    const resultIcon = document.getElementById('resultIcon');
    const closeModalBtn = document.getElementById('closeModalBtn');

    if (!mysteryBtns.length) {
        return;
    }

    mysteryBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const price = parseInt(btn.getAttribute('data-price') || '50', 10);

            if (mysteryModal) {
                mysteryModal.style.display = 'flex';
                if (boxesContainer) {
                    boxesContainer.style.display = 'none';
                }
                if (resultDisplay) {
                    resultDisplay.style.display = 'block';
                }
            }

            btn.disabled = true;
            const originalText = btn.textContent;
            btn.textContent = strings.mysteryOpeningLabel;

            callAjax('local_ascend_rewards_mysterybox_open', {price: price})
                .then((result) => {
                    if (!result || !result.success) {
                        if (mysteryModal) {
                            mysteryModal.style.display = 'none';
                        }
                        const message = (result && result.message) || strings.mysteryErrorCouldNotOpen;
                        showAlert(strings, `${strings.errorPrefix || ''}${message}`, true);
                        btn.disabled = false;
                        btn.textContent = originalText;
                        return;
                    }

                    const cacheBust = ` ? v = ${Date.now()}`;
                    const videos = {
                        coins: `${urls.videoCoinsUrl}${cacheBust}`,
                        coins_duplicate: `${urls.videoCoinsUrl}${cacheBust}`,
                        tokens: `${urls.videoTokensUrl}${cacheBust}`,
                        avatar_new: `${urls.videoHeroUrl}${cacheBust}`,
                        avatar_duplicate: `${urls.videoHeroUrl}${cacheBust}`,
                        avatar_locked_level: `${urls.videoHeroUrl}${cacheBust}`,
                        nothing: `${urls.videoNoRewardUrl}${cacheBust}`
                    };

                    const videoUrl = videos[result.reward_type] || `${urls.videoNoRewardUrl}${cacheBust}`;

                    let resultHTML = ` < video autoplay playsinline preload = "auto" crossorigin = "anonymous" class = "mystery-result-video" id = "rewardVideo" > < source src = "${videoUrl}" type = "video/mp4" > < / video > `;
                    resultIcon.innerHTML = resultHTML;

                    let messageHTML = '';
                    let balanceHTML = '';

                    if (result.reward_type === 'tokens') {
                        messageHTML = ` < div class = "mystery-result-media" > < img src = "${urls.imgStarUrl}" class = "mystery-result-icon-image mystery-result-icon-image--star" > < / div > ` +
                            ` < div > ${result.message} < / div > `;
                        balanceHTML = ` < div class = "mystery-balance" > ${strings.newBalanceLabel} < img src = "${urls.imgStarUrl}" class = "mystery-balance-icon mystery-balance-icon--star" > ${result.total_tokens} ${strings.tokensLabel} < / div > `;
                    } else if (result.reward_type === 'coins' || result.reward_type === 'coins_duplicate') {
                        messageHTML = ` < div class = "mystery-result-media" > < img src = "${urls.imgCoinsUrl}" class = "mystery-result-icon-image mystery-result-icon-image--coins" > < / div > ` +
                            ` < div > ${result.message} < / div > `;
                        balanceHTML = ` < div class = "mystery-balance" > ${strings.newBalanceLabel} < img src = "${urls.imgCoinsUrl}" class = "mystery-balance-icon mystery-balance-icon--coins" > ${result.new_balance.toLocaleString()} ${strings.coinsLabel} < / div > `;
                    } else if (result.reward_type === 'avatar_duplicate' || result.reward_type === 'avatar_locked_level') {
                        messageHTML = ` < div > ${result.message} < / div > `;
                        balanceHTML = ` < div class = "mystery-balance" > ${strings.newBalanceLabel} < img src = "${urls.imgCoinsUrl}" class = "mystery-balance-icon mystery-balance-icon--coins" > ${result.new_balance.toLocaleString()} ${strings.coinsLabel} < / div > `;
                    } else if (result.reward_type === 'avatar_new') {
                        messageHTML = ` < div > ${result.message} < / div > `;
                    } else {
                        messageHTML = result.message;
                        balanceHTML = ` < div class = "mystery-balance" > ${strings.balanceLabel} < img src = "${urls.imgCoinsUrl}" class = "mystery-balance-icon mystery-balance-icon--coins" > ${result.new_balance.toLocaleString()} ${strings.coinsLabel} < / div > `;
                    }

                    resultMessage.innerHTML = messageHTML + balanceHTML;

                    if ((result.reward_type === 'avatar_new' || result.reward_type === 'avatar_duplicate') &&
                        result.reward_data &&
                        result.reward_data.avatar_filename) {
                        const avatarUrl = `${urls.avatarCircularBaseUrl}${result.reward_data.avatar_filename}`;
                        resultHTML += ` < div class = "mystery-avatar-reward" id = "avatarReward" > < img src = "${avatarUrl}" alt = "${strings.avatarAltLabel || ""}" class = "mystery-avatar-image" > < / div > `;
                        resultIcon.innerHTML = resultHTML;
                    }

                    const video = document.getElementById('rewardVideo');
                    if (video) {
                        video.addEventListener('error', () => {
                            resultMessage.classList.add('animate-slide-in');
                            resultMessage.style.opacity = '1';
                            closeModalBtn.style.opacity = '1';

                            const avatarReward = document.getElementById('avatarReward');
                            if (avatarReward) {
                                const avatarImg = avatarReward.querySelector('img');
                                if (avatarImg) {
                                    avatarImg.classList.add('animate-bounce-in');
                                    avatarImg.style.opacity = '1';
                                }
                            }
                        });

                        video.addEventListener('ended', () => {
                            resultMessage.classList.add('animate-slide-in');
                            resultMessage.style.opacity = '1';

                            setTimeout(() => {
                                closeModalBtn.style.opacity = '1';
                            }, 300);

                            const avatarReward = document.getElementById('avatarReward');
                        if (avatarReward) {
                            const avatarImg = avatarReward.querySelector('img');
                            if (avatarImg) {
                                avatarImg.classList.add('animate-bounce-in');
                                avatarImg.style.opacity = '1';
                            }
                        }
                        });

                        setTimeout(() => {
                            if (!resultMessage.style.opacity || resultMessage.style.opacity === '0') {
                                resultMessage.classList.add('animate-slide-in');
                                resultMessage.style.opacity = '1';
                                closeModalBtn.style.opacity = '1';

                                const avatarReward = document.getElementById('avatarReward');
                                if (avatarReward) {
                                    const avatarImg = avatarReward.querySelector('img');
                                    if (avatarImg && avatarImg.style.opacity === '0') {
                                        avatarImg.classList.add('animate-bounce-in');
                                        avatarImg.style.opacity = '1';
                                    }
                                }
                            }
                        }, 5000);
                    }

                    btn.disabled = false;
                    btn.textContent = originalText;
                })
                .catch((error) => {
                    // eslint-disable-next-line no-console
                    console.error('Error opening mystery box:', error);
                    if (boxesContainer) {
                        boxesContainer.style.display = 'none';
                    }
                    if (resultDisplay) {
                        resultDisplay.style.display = 'block';
                    }
                    resultIcon.innerHTML = ` < video autoplay loop playsinline class = "mystery-result-video mystery-result-video--fallback" > < source src = "${urls.videoNoRewardUrl}" type = "video/mp4" > < / video > `;
                    resultMessage.textContent = strings.mysteryErrorProcessing;
                    resultMessage.classList.add('animate-slide-in');
                    resultMessage.style.opacity = '1';
                    if (closeModalBtn) {
                        closeModalBtn.style.opacity = '1';
                    }
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
        });
    });

if (closeModalBtn) {
    closeModalBtn.addEventListener('click', () => {
        if (mysteryModal) {
            mysteryModal.style.display = 'none';
            window.location.reload();
        }
        });
}
};

export const init = (config) => {
    if (!document.querySelector('#a_store')) {
        return;
    }

    const tokensAvailable = Number(config ? .tokensAvailable ? ? 0);
    const coinBalance = Number(config ? .coinBalance ? ? 0);
    const strings = config ? .strings || {};
    const urls = config ? .urls || {};
    const modalStrings = config ? .modalStrings || {};

    initAvatarModals({strings: modalStrings});

    ajaxRequestFailed = strings.ajaxRequestFailed || '';

    initXpMultiplierTimer(strings);
    initStorePurchases(strings);
    initStoreActivation(strings);
    initPetPurchases(tokensAvailable, coinBalance, strings);
    initVillainPurchases(tokensAvailable, coinBalance);
    initMysteryBox(strings, urls);
};
