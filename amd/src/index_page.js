import Ajax from 'core/ajax';
import Notification from 'core/notification';

let ajaxRequestFailed = '';
let alertConfig = {
    alertTitle: '',
    errorTitle: '',
    closeLabel: ''
};

const callAjax = (methodname, args) => {
    const requests = Ajax.call([{methodname, args}]);
    return requests && requests[0]
        ? requests[0]
        : Promise.reject(new Error(ajaxRequestFailed || ''));
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

const showAlert = (message, isError = false) => {
    if (!message) {
        return;
    }
    const title = isError ? alertConfig.errorTitle : alertConfig.alertTitle;
    Notification.alert(title || '', message, alertConfig.closeLabel || '');
};

const initPanelToggles = () => {
    document.querySelectorAll('.aa-panel-head').forEach((header) => {
        header.addEventListener('click', () => {
            header.classList.toggle('open');
        });
    });

    document.querySelectorAll('.xp-ring[data-final-offset]').forEach((ring) => {
        const offset = ring.getAttribute('data-final-offset');
        if (offset !== null) {
            ring.style.setProperty('--final-offset', offset);
        }
    });
};

const initInstructions = (config) => {
    const windowName = (config && config.windowName) || 'ascend_instructions';
    const windowFeatures = (config && config.windowFeatures) || '';

    document.querySelectorAll('.js-instructions-link').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!windowFeatures) {
                return;
            }
            event.preventDefault();
            const name = link.getAttribute('data-window-name') || windowName;
            const features = link.getAttribute('data-window-features') || windowFeatures;
            window.open(link.href, name, features);
        });
    });
};

const initGameboard = (config) => {
    const cards = document.querySelectorAll('.gameboard-card:not(.picked)');
    if (!cards.length) {
        return;
    }

    const successText = (config && config.successText) || '';
    const errorPrefix = (config && config.errorPrefix) || '';
    const genericError = (config && config.genericError) || '';
    const processingErrorPrefix = (config && config.processingErrorPrefix) || '';
    const coinAltLabel = (config && config.coinAltLabel) || '';
    const coinIconUrl = (config && config.coinIconUrl) || '';

    cards.forEach((card) => {
        card.addEventListener('click', () => {
            const position = parseInt(card.getAttribute('data-position') || '0', 10);

            cards.forEach((item) => {
                item.style.pointerEvents = 'none';
            });

            callAjax('local_ascend_rewards_gameboard_pick', {position: position})
                .then((result) => {
                    if (result && result.success) {
                        card.style.animation = 'cardFlip 0.6s ease-in-out';

                        setTimeout(() => {
                            card.classList.add('picked');
                            card.style.background = '#ffffff';
                            card.style.cursor = 'default';
                            card.innerHTML = '<div class="gameboard-card-content">' +
                                '<img src="' + coinIconUrl + '" alt="' + coinAltLabel + '" class="gameboard-card-icon">' +
                                '<div class="gameboard-card-value">' + result.coins + '</div>' +
                                '</div>';

                            const remainingPicks = result.remaining;

                            try {
                                const audio = document.getElementById('levelUpSound');
                                if (audio) {
                                    audio.volume = 0.4;
                                    audio.currentTime = 0;
                                    audio.play().catch(() => {});
                                }
                            } catch (error) {
                                // Ignore audio failures.
                            }

                            setTimeout(() => {
                                showAlert(formatString(successText, {coins: result.coins}));

                                if (remainingPicks === 0) {
                                    window.location.reload();
                                } else {
                                    cards.forEach((item) => {
                                        if (!item.classList.contains('picked')) {
                                            item.style.pointerEvents = 'auto';
                                        }
                                    });
                                }
                            }, 800);
                        }, 600);
                    } else {
                        const message = (result && result.error) || genericError;
                        showAlert(errorPrefix + message, true);
                        cards.forEach((item) => {
                            item.style.pointerEvents = 'auto';
                        });
                    }
                })
                .catch((error) => {
                    showAlert(processingErrorPrefix + ((error && error.message) || ''), true);
                    cards.forEach((item) => {
                        item.style.pointerEvents = 'auto';
                    });
                });
        });
    });
};

const initBadgeModal = (config) => {
    const modal = document.getElementById('apxModal');
    const backdrop = document.getElementById('apxModalBackdrop');
    const closeBtn = document.getElementById('apxModalClose');
    const video = document.getElementById('apxRewardVideo');
    const videoSource = document.getElementById('apxRewardVideoSource');
    const fullscreenBtn = document.getElementById('apxVideoFullscreen');

    if (!modal || !backdrop) {
        return;
    }

    const badgeVideos = (config && config.badgeVideos) || {};
    const metaBadgeIds = (config && config.metaBadgeIds) || [];
    const knownBadgeNames = (config && config.knownBadgeNames) || [];
    const awardLabelPrefix = (config && config.awardLabelPrefix) || '';
    const awardLabelSuffix = (config && config.awardLabelSuffix) || '';
    const xpLabel = (config && config.xpLabel) || '';
    const coinsLabel = (config && config.coinsLabel) || '';
    const assetsLabel = (config && config.assetsLabel) || '';
    const badgeCategoryDefault = (config && config.badgeCategoryDefault) || '';
    const failedLabel = (config && config.failedLabel) || '';
    const passedLabel = (config && config.passedLabel) || '';

    const q = (selector) => document.querySelector(selector);

    const closeModal = () => {
        modal.style.display = 'none';
        backdrop.style.display = 'none';
        document.body.style.overflow = '';
        if (video) {
            video.pause();
            video.style.display = 'none';
        }
        if (fullscreenBtn) {
            fullscreenBtn.style.display = 'none';
        }
    };

    const openModal = (el) => {
        const d = el.dataset;
        q('#apxBName').textContent = d.badge || '';
        q('#apxBCategory').textContent = d.category || badgeCategoryDefault;
        q('#apxBCourse').textContent = d.course || '';
        q('#apxBWhen').textContent = d.when || '';
        q('#apxBWhy').textContent = d.why || '';

        const coinsText = d.coins || `+0 ${assetsLabel}`;
        const coins = parseInt(coinsText.match(/\d+/)) || 0;
        const xpValue = d.xp ? parseInt(d.xp, 10) : Math.floor(coins / 2);

        q('#apxBXP').textContent = `+${xpValue} ${xpLabel}`;
        q('#apxBCoins').textContent = coinsText || `+0 ${coinsLabel}`;

        const courseId = parseInt(d.courseid || '0', 10);
        const badgeId = parseInt(d.badgeid || '0', 10);
        const isMeta = metaBadgeIds.includes(badgeId);

        const activitiesDiv = q('#apxBActivities');
        const badgesDiv = q('#apxBBadges');
        const activitiesList = q('#apxBActivitiesList');
        const badgesList = q('#apxBBadgesList');

        if (activitiesDiv) {
            activitiesDiv.classList.add('aa-hidden');
        }
        if (badgesDiv) {
            badgesDiv.classList.add('aa-hidden');
        }

        if (courseId > 0) {
            callAjax('local_ascend_rewards_get_activities', {courseid: courseId, badgeid: badgeId, force: 1})
                .then((data) => {
                    if (data && data.activities && data.activities.length > 0) {
                        const areBadges = data.activities.some((item) => knownBadgeNames.includes(item));

                        if (isMeta || areBadges) {
                            if (badgesList) {
                                badgesList.innerHTML = '';
                                data.activities.forEach((badge) => {
                                    const li = document.createElement('li');
                                    li.className = 'apx-badge-item';
                                    li.textContent = badge;
                                    badgesList.appendChild(li);
                                });
                            }
                            if (badgesDiv) {
                                badgesDiv.classList.remove('aa-hidden');
                            }
                            if (activitiesDiv) {
                                activitiesDiv.classList.add('aa-hidden');
                            }
                        } else {
                            if (activitiesList) {
                                activitiesList.innerHTML = '';

                                const metadata = data.metadata || [];
                                const hasMetadata = metadata.length > 0;
                                let currentAward = 0;

                                data.activities.forEach((activity, index) => {
                                    const meta = hasMetadata ? metadata[index] : null;

                                    if (meta && meta.award_number && meta.award_number !== currentAward) {
                                        if (currentAward > 0) {
                                            const spacer = document.createElement('li');
                                            spacer.className = 'apx-award-spacer';
                                            activitiesList.appendChild(spacer);
                                        }

                                        const header = document.createElement('li');
                                        header.className = 'apx-award-header';

                                        const badgeCount = document.createElement('span');
                                        badgeCount.className = 'aa-badge-count apx-award-count';
                                        badgeCount.textContent = `x${meta.award_number}`;

                                        header.appendChild(badgeCount);
                                        header.appendChild(document.createTextNode(`${awardLabelPrefix}${meta.award_number}${awardLabelSuffix}`));
                                        activitiesList.appendChild(header);

                                        currentAward = meta.award_number;
                                    }

                                    const li = document.createElement('li');
                                    li.className = 'apx-activity-item';

                                    if (meta && meta.failed_grade !== undefined && meta.passed_grade !== undefined) {
                                        li.innerHTML = `<span class=\"apx-activity-failed\">${failedLabel} (${meta.failed_grade}%)</span> -> ` +
                                            `<span class=\"apx-activity-passed\">${passedLabel} (${meta.passed_grade}%)</span><br/>` +
                                            `<span class=\"apx-activity-indent\">${activity}</span>`;
                                    } else if (meta && meta.old_grade !== undefined && meta.new_grade !== undefined) {
                                        li.innerHTML = `${activity} <span class=\"apx-activity-improved\">(${meta.old_grade}% -> ${meta.new_grade}%)</span>`;
                                    } else {
                                        li.textContent = activity;
                                    }

                                    activitiesList.appendChild(li);
                                });
                            }

                            if (activitiesDiv) {
                                activitiesDiv.classList.remove('aa-hidden');
                            }
                            if (badgesDiv) {
                                badgesDiv.classList.add('aa-hidden');
                            }
                        }
                    } else {
                        if (activitiesDiv) {
                            activitiesDiv.classList.add('aa-hidden');
                        }
                        if (badgesDiv) {
                            badgesDiv.classList.add('aa-hidden');
                        }
                    }
                })
                .catch(() => {
                    if (activitiesDiv) {
                        activitiesDiv.classList.add('aa-hidden');
                    }
                    if (badgesDiv) {
                        badgesDiv.classList.add('aa-hidden');
                    }
                });
        }

        const videoFile = badgeVideos[badgeId] || 'reward_animation_2.mp4';
        const videoUrl = `${M.cfg.wwwroot}/local/ascend_rewards/pix/${videoFile}`;

        if (video && videoSource) {
            videoSource.src = videoUrl;
            video.load();
            video.style.display = 'block';
            video.currentTime = 0;
            video.play().catch(() => {});
            if (fullscreenBtn) {
                fullscreenBtn.style.display = 'block';
            }
        }

        modal.style.display = 'block';
        backdrop.style.display = 'block';
        document.body.style.overflow = 'hidden';
    };

    document.querySelectorAll('.js-badge-detail').forEach((el) => {
        el.addEventListener('click', () => openModal(el));
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    backdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });

    if (fullscreenBtn && video) {
        fullscreenBtn.addEventListener('click', () => {
            if (video.requestFullscreen) {
                video.requestFullscreen();
            } else if (video.webkitRequestFullscreen) {
                video.webkitRequestFullscreen();
            } else if (video.webkitEnterFullscreen) {
                video.webkitEnterFullscreen();
            } else if (video.msRequestFullscreen) {
                video.msRequestFullscreen();
            }
        });
    }
};

const initLeaderboard = (config) => {
    const btnTopView = document.getElementById('btnTopView');
    const btnContextView = document.getElementById('btnContextView');
    const leaderboardList = document.getElementById('leaderboardList');
    const leaderboardMode = document.getElementById('leaderboardMode');

    if (!leaderboardList || !leaderboardMode) {
        return;
    }

    const courseId = Number((config && config.courseId) || 0);
    const userId = Number((config && config.userId) || 0);
    const leaderboardRangeLabel = (config && config.leaderboardRangeLabel) || '';
    const leaderboardModeLabel = (config && config.leaderboardModeLabel) || '';
    const leaderboardLoadingLabel = (config && config.leaderboardLoadingLabel) || '';
    const leaderboardContextErrorLabel = (config && config.leaderboardContextErrorLabel) || '';
    const leaderboardContextViewErrorLabel = (config && config.leaderboardContextViewErrorLabel) || '';
    const youLabel = (config && config.youLabel) || '';
    const userNumberPrefix = (config && config.userNumberPrefix) || '';
    const userIdBadgeLabel = (config && config.userIdBadgeLabel) || '';

    const originalHTML = leaderboardList.innerHTML;

    const switchToContextView = () => {
        leaderboardList.innerHTML = `<li class="aa-muted aa-leaderboard-loading"><i class="fa-solid fa-spinner fa-spin"></i> ${leaderboardLoadingLabel}</li>`;

        callAjax('local_ascend_rewards_get_leaderboard_context', {courseid: courseId})
            .then((data) => {
                if (!data.success || !data.users) {
                    leaderboardList.innerHTML = `<li class="aa-muted">${leaderboardContextErrorLabel}</li>`;
                    return;
                }

                let html = '';
                data.users.forEach((user, idx) => {
                    const isCurrentUser = user.is_current_user;
                    const displayName = isCurrentUser ? youLabel : `${userNumberPrefix}${user.userid}`;
                    const userIdBadge = isCurrentUser
                        ? `<span class="user-id-badge">${userIdBadgeLabel} ${userId}</span>`
                        : '';
                    const medal = user.medal;
                    const xp = user.xp.toLocaleString();
                    const currentClass = isCurrentUser ? ' class="current-user"' : '';
                    const gradId = `xpIconGradLBCtx${idx}`;

                    html += `<li${currentClass}>`;
                    html += `<span class="pos">${medal}</span>`;
                    html += `<strong>${displayName}</strong>`;
                    html += userIdBadge;
                    html += '<div class="xp-display xp-display-right">';
                    html += '<svg width="24" height="24" viewBox="0 0 80 80" class="xp-icon">';
                    html += `<defs><linearGradient id="${gradId}" x1="0%" y1="0%" x2="100%" y2="100%">`;
                    html += '<stop offset="0%" stop-color="#00D4FF" />';
                    html += '<stop offset="50%" stop-color="#FF00AA" />';
                    html += '<stop offset="100%" stop-color="#FF9500" /></linearGradient></defs>';
                    html += `<circle cx="40" cy="40" r="36" fill="url(#${gradId})" />`;
                    html += '<text x="40" y="52" text-anchor="middle" fill="#01142E" font-size="32" font-weight="800">X</text>';
                    html += '</svg>';
                    html += `<span class="aa-muted aa-xp-value">${xp}</span>`;
                    html += '</div>';
                    html += '</li>';
                });

                leaderboardList.innerHTML = html;
                leaderboardMode.textContent = formatString(leaderboardRangeLabel, {
                    start: data.start_rank,
                    end: data.end_rank,
                    total: data.total_users
                });

                setTimeout(() => {
                    const currentUserLi = leaderboardList.querySelector('li.current-user');
                    if (currentUserLi) {
                        currentUserLi.scrollIntoView({behavior: 'smooth', block: 'center'});
                    }
                }, 100);

                if (btnTopView) {
                    btnTopView.classList.remove('is-active');
                }
                if (btnContextView) {
                    btnContextView.classList.add('is-active');
                }
            })
            .catch(() => {
                leaderboardList.innerHTML = `<li class="aa-muted">${leaderboardContextViewErrorLabel}</li>`;
            });
    };

    const switchToTopView = () => {
        leaderboardList.innerHTML = originalHTML;
        leaderboardMode.textContent = leaderboardModeLabel;

        if (btnTopView) {
            btnTopView.classList.add('is-active');
        }
        if (btnContextView) {
            btnContextView.classList.remove('is-active');
        }
    };

    if (btnContextView) {
        btnContextView.addEventListener('click', switchToContextView);
    }
    if (btnTopView) {
        btnTopView.addEventListener('click', switchToTopView);
    }
};

export const init = (config) => {
    const alerts = (config && config.alerts) || {};
    alertConfig = {
        alertTitle: alerts.alertTitle || '',
        errorTitle: alerts.errorTitle || alerts.alertTitle || '',
        closeLabel: alerts.closeLabel || ''
    };
    ajaxRequestFailed = alerts.ajaxRequestFailed || '';

    initPanelToggles();
    initInstructions((config && config.instructions) || {});
    initGameboard((config && config.gameboard) || {});
    initBadgeModal((config && config.badgeModal) || {});
    initLeaderboard((config && config.leaderboard) || {});
};
