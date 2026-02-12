import Ajax from 'core/ajax';

const callAjax = (methodname, args) => {
    const requests = Ajax.call([{methodname, args}]);
    return requests && requests[0] ? requests[0] : Promise.reject(new Error());
};

const setupRewardModal = (onNoMoreBadges) => {
    const backdrop = document.getElementById('apexRewardBackdrop');
    const modal = document.getElementById('apexRewardModal');
    const video = document.getElementById('apexRewardVideo');
    const fullscreenBtn = document.getElementById('apexRewardFullscreen');
    const closeBtn = document.getElementById('apexRewardClose');

    if (!backdrop || !modal) {
        return {exists: false, show: () => {}};
    }

    let loopCount = 0;
    const maxLoops = 3;

    const showModal = () => {
        backdrop.style.display = 'block';
        modal.style.display = 'block';
        if (video) {
            loopCount = 0;
            video.volume = 0.7;
            video.currentTime = 0;
            video.play().catch(() => {});
        }
    };

    const closeModal = () => {
        modal.style.display = 'none';
        backdrop.style.display = 'none';
        if (video) {
            video.pause();
            video.currentTime = 0;
            loopCount = 0;
        }
        checkForMoreNotifications();
    };

    const checkForMoreNotifications = () => {
        callAjax('local_ascend_rewards_check_notifications', {})
            .then((response) => {
                if (response.has_more) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 300);
                } else if (typeof onNoMoreBadges === 'function') {
                    setTimeout(onNoMoreBadges, 500);
                }
            })
            .catch(() => {});
    };

    if (video) {
        video.addEventListener('ended', () => {
            loopCount += 1;
            if (loopCount < maxLoops) {
                video.currentTime = 0;
                video.play().catch(() => {});
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    backdrop.addEventListener('click', closeModal);

    if (fullscreenBtn && video) {
        fullscreenBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (video.requestFullscreen) {
                video.requestFullscreen();
            } else if (video.webkitRequestFullscreen) {
                video.webkitRequestFullscreen();
            } else if (video.mozRequestFullScreen) {
                video.mozRequestFullScreen();
            } else if (video.msRequestFullscreen) {
                video.msRequestFullscreen();
            }
        });
    }

    setTimeout(() => {
        closeModal();
    }, 25000);

    return {exists: true, show: showModal};
};

const setupLevelupModal = () => {
    const backdrop = document.getElementById('apexLevelupBackdrop');
    const modal = document.getElementById('apexLevelupModal');
    const video = document.getElementById('apexLevelupVideo');
    const sound = document.getElementById('apexLevelupSound');
    const closeBtn = document.getElementById('apexLevelupClose');

    if (!backdrop || !modal) {
        return {exists: false, show: () => {}};
    }

    let loopCount = 0;
    const maxLoops = 3;

    const showModal = () => {
        backdrop.style.display = 'block';
        modal.style.display = 'block';
        if (video) {
            loopCount = 0;
            video.volume = 0.7;
            video.currentTime = 0;
            video.play().catch(() => {});
        }
        if (sound) {
            sound.volume = 0.6;
            sound.currentTime = 0;
            sound.play().catch(() => {});
        }
    };

    const closeModal = () => {
        modal.style.display = 'none';
        backdrop.style.display = 'none';
        if (video) {
            video.pause();
            video.currentTime = 0;
            loopCount = 0;
        }
        checkForMoreLevelups();
    };

    const checkForMoreLevelups = () => {
        callAjax('local_ascend_rewards_check_levelups', {})
            .then((response) => {
                if (response.has_more) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 300);
                }
            })
            .catch(() => {});
    };

    if (video) {
        video.addEventListener('ended', () => {
            loopCount += 1;
            if (loopCount < maxLoops) {
                video.currentTime = 0;
                video.play().catch(() => {});
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    backdrop.addEventListener('click', closeModal);

    setTimeout(() => {
        closeModal();
    }, 25000);

    return {exists: true, show: showModal};
};

export const init = () => {
    const levelup = setupLevelupModal();
    const reward = setupRewardModal(() => {
        if (levelup.exists) {
            levelup.show();
        }
    });

if (reward.exists) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => reward.show(), 500);
            });
    } else {
        setTimeout(() => reward.show(), 500);
    }
} else if (levelup.exists) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => levelup.show(), 1000);
            });
    } else {
        setTimeout(() => levelup.show(), 1000);
    }
}
};
