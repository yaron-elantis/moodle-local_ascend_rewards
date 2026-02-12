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

const initBadgePreview = (config) => {
    const modal = document.getElementById('apxModal');
    const backdrop = document.getElementById('apxModalBackdrop');
    const closeBtn = document.getElementById('apxModalClose');
    const congratsBanner = document.getElementById('adminAscendCongrats');
    const congratsVideo = document.getElementById('adminAscendCongratsVideo');
    const congratsText = document.getElementById('adminAscendCongratsText');
    const video = document.getElementById('apxRewardVideo');
    const videoSource = document.getElementById('apxRewardVideoSource');
    const fullscreenBtn = document.getElementById('apxVideoFullscreen');

    if (!modal || !backdrop) {
        return;
    }

    const badgeVideos = config ? .badgeVideos || {};
    const badgeCategories = config ? .badgeCategories || {};
    const badgeDescriptions = config ? .badgeDescriptions || {};
    const knownBadgeNames = config ? .knownBadgeNames || [];
    const metaBadgeIds = config ? .metaBadgeIds || [];
    const moreActivitiesTemplate = config ? .moreActivitiesTemplate || '';
    const badgePreviewFallback = config ? .badgePreviewFallback || '';
    const congratsTemplate = config ? .congratsTemplate || '';
    const xpLabel = config ? .xpLabel || '';
    const assetsLabel = config ? .assetsLabel || '';
    const badgeLabel = config ? .badgeLabel || '';
    const badgeCategoryDefault = config ? .badgeCategoryDefault || '';
    const sampleCourseLabel = config ? .sampleCourseLabel || '';
    const selectBadgeAlert = config ? .selectBadgeAlert || '';

    const q = (selector) => document.querySelector(selector);

    const closeModal = () => {
        modal.style.display = 'none';
        backdrop.style.display = 'none';
        document.body.style.overflow = '';
        if (video) {
            video.pause();
            video.style.display = 'none';
        }
        if (congratsBanner) {
            congratsBanner.style.display = 'none';
        }
        if (congratsVideo) {
            congratsVideo.pause();
            try {
                congratsVideo.currentTime = 0;
            } catch (error) {
                // Ignore reset failure.
            }
        }
    };

    const openModal = (badgeData) => {
        const d = badgeData;
        q('#apxBName').textContent = d.badge || '';
        q('#apxBCategory').textContent = d.category || badgeCategoryDefault;
        q('#apxBCourse').textContent = d.course || '';
        q('#apxBWhen').textContent = d.when || '';
        q('#apxBWhy').textContent = d.why || '';

        const coinsText = d.coins || ` + 0 ${assetsLabel}`;
        const coins = parseInt(coinsText.match(/\d+/)) || 0;
        const badgeXp = Math.floor(coins / 2);

        q('#apxBXP').textContent = ` + ${badgeXp} ${xpLabel}`;
        q('#apxBCoins').textContent = coinsText;

        const courseId = parseInt(d.courseid || '0', 10);
        const badgeId = parseInt(d.badgeid || '0', 10);
        const isMeta = metaBadgeIds.includes(badgeId);

        const activitiesDiv = q('#apxBActivities');
        const badgesDiv = q('#apxBBadges');
        const activitiesList = q('#apxBActivitiesList');
        const badgesList = q('#apxBBadgesList');

        if (activitiesDiv) {
            activitiesDiv.style.display = 'none';
        }
        if (badgesDiv) {
            badgesDiv.style.display = 'none';
        }

        if (courseId > 0 && !isMeta) {
            callAjax('local_ascend_rewards_get_activities', {courseid: courseId, badgeid: badgeId})
                .then((data) => {
                    if (data.activities && data.activities.length > 0) {
                        const areBadges = data.activities.some((item) => {
                            if (knownBadgeNames.includes(item)) {
                                return true;
                            }
                            return knownBadgeNames.some((badgeName) => item.includes(badgeName));
                        });

                        if (areBadges) {
                            if (badgesList) {
                                badgesList.innerHTML = '';
                                data.activities.forEach((badge) => {
                                    const li = document.createElement('li');
                                    li.textContent = ` ${badge}`;
                                    li.style.color = '#FFD700';
                                    badgesList.appendChild(li);
                                });
                            }
                            if (badgesDiv) {
                                badgesDiv.style.display = 'block';
                            }
                            if (activitiesDiv) {
                                activitiesDiv.style.display = 'none';
                            }
                        } else {
                            if (activitiesList) {
                                activitiesList.innerHTML = '';
                                data.activities.forEach((activity, index) => {
                                    if (index < 15) {
                                        const li = document.createElement('li');
                                        li.textContent = ` ${activity}`;
                                        li.style.color = '#A5B4D6';
                                        activitiesList.appendChild(li);
                                    }
                                });
                                if (data.activities.length > 15) {
                                    const li = document.createElement('li');
                                    li.textContent = formatString(moreActivitiesTemplate, {
                                        count: data.activities.length - 15
                                    });
                                    li.style.color = '#FFD700';
                                    activitiesList.appendChild(li);
                                }
                            }
                            if (activitiesDiv) {
                                activitiesDiv.style.display = 'block';
                            }
                            if (badgesDiv) {
                                badgesDiv.style.display = 'none';
                            }
                        }
                    } else {
                        if (activitiesDiv) {
                            activitiesDiv.style.display = 'none';
                        }
                        if (badgesDiv) {
                            badgesDiv.style.display = 'none';
                        }
                    }
                })
                .catch(() => {
                    if (activitiesDiv) {
                        activitiesDiv.style.display = 'none';
                    }
                    if (badgesDiv) {
                        badgesDiv.style.display = 'none';
                    }
                });
        } else {
            if (activitiesDiv) {
                activitiesDiv.style.display = 'none';
            }
            if (isMeta && badgesDiv) {
                badgesDiv.style.display = 'none';
            }
        }

        const videoFile = badgeVideos[badgeId] || 'reward_animation_2.mp4';
        const videoUrl = `${M.cfg.wwwroot} / local / ascend_rewards / pix / ${videoFile}`;

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

        if (congratsBanner) {
            const badgeName = d.badge || badgeLabel;
            congratsText.textContent = formatString(congratsTemplate, {badge: badgeName});
            congratsBanner.style.display = 'flex';
        }
        if (congratsVideo) {
            try {
                congratsVideo.currentTime = 0;
                congratsVideo.play();
            } catch (error) {
                // Ignore video playback errors.
            }
        }

        modal.style.display = 'block';
        backdrop.style.display = 'block';
        document.body.style.overflow = 'hidden';
    };

    const triggerTestModal = () => {
        const badgeSelect = document.getElementById('badgeModalPreviewSelect') || document.getElementById('testBadgeSelect');
        if (!badgeSelect || !badgeSelect.value) {
            showAlert(selectBadgeAlert);
            return;
        }

        const badgeLabelText = badgeSelect.options[badgeSelect.selectedIndex].text || '';
        const badgeName = badgeLabelText.replace(/\s*\([^)]*\)\s*$/, '');
        const badgeId = badgeSelect.value;

        const badgeData = {
            badge: badgeName,
            category: badgeCategories[badgeId] || badgeCategoryDefault,
            course: sampleCourseLabel,
            courseid: 1,
            badgeid: badgeId,
            when: new Date().toLocaleDateString(),
            why: badgeDescriptions[badgeId] || badgePreviewFallback,
            coins: ` + 100 ${assetsLabel}`
        };

        openModal(badgeData);
    };

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    backdrop.addEventListener('click', closeModal);

    if (fullscreenBtn && video) {
        fullscreenBtn.addEventListener('click', () => {
            if (video.requestFullscreen) {
                video.requestFullscreen();
            }
        });
    }

    const testButtons = document.querySelectorAll('[data-test-badge-modal]');
    testButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            triggerTestModal();
        });
    });

    window.openBadgeModal = openModal;
};

const initSearchTabs = () => {
    document.querySelectorAll('[data-search-tab]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const tab = btn.getAttribute('data-search-tab');
            if (!tab) {
                return;
            }
            const url = new URL(window.location.toString());
            url.searchParams.set('tab', 'users');
            url.searchParams.set('search_type', tab);
            url.searchParams.delete('userid');
            url.searchParams.delete('badgeid_search');
            window.location = url.toString();
        });
    });
};

const initAwardForm = (config) => {
    const awardCourseError = config ? .awardCourseError || '';
    const awardBadgeError = config ? .awardBadgeError || '';
    const awardForm = document.querySelector('[data-award-form]');

    if (!awardForm) {
        return;
    }

    awardForm.addEventListener('submit', (event) => {
        const courseId = document.getElementById('courseid_input') ? .value || '';
        const badgeId = document.getElementById('badgeid_input') ? .value || '';
        const idPattern = /\(\s*[^)]*\d+\s*\)\s*$/;

        if (!courseId || !idPattern.test(courseId)) {
            showAlert(awardCourseError, true);
            event.preventDefault();
            return;
        }
        if (!badgeId || !idPattern.test(badgeId)) {
            showAlert(awardBadgeError, true);
            event.preventDefault();
        }
    });
};

const initBadgeChart = () => {
    document.querySelectorAll('.aa-admin-chart-bar').forEach((bar) => {
        const height = parseInt(bar.getAttribute('data-bar-height') || '0', 10);
        if (!Number.isNaN(height) && height > 0) {
            bar.style.height = `${height}px`;
        }
    });
};

export const init = (config) => {
    const fallbackConfig = window.M ? .cfg ? .local_ascend_rewards_admin_dashboard || {};
    const resolvedConfig = config && typeof config === 'object' ? config : fallbackConfig;
    const alerts = resolvedConfig ? .alerts || {};
    alertConfig = {
        alertTitle: alerts.alertTitle || '',
        errorTitle: alerts.errorTitle || alerts.alertTitle || '',
        closeLabel: alerts.closeLabel || ''
    };
    ajaxRequestFailed = alerts.ajaxRequestFailed || '';

    initBadgePreview(resolvedConfig ? .badgePreview || {});
    initSearchTabs();
    initAwardForm(resolvedConfig ? .awardForm || {});
    initBadgeChart();
};
