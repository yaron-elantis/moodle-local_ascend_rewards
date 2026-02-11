import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {
    init as initAvatarModals,
    showAvatarUnlockModal,
    showAvatarReviewModal,
    showPetUnlockModal,
    showPetReviewModal,
    showVillainUnlockModal,
    showVillainReviewModal
} from 'local_ascend_rewards/avatar_modals';

const VIDEO_MAP = {
    'amazon.png': 'amazon.mp4',
    'elf.png': 'elf.mp4',
    'ent.png': 'ent.mp4',
    'guardian.png': 'guardian.mp4',
    'imp.png': 'imp.mp4',
    'jester.png': 'jester.mp4',
    'magician.png': 'magician.mp4',
    'mermaid.png': 'mermaid.mp4',
    'nomad.png': 'nomad.mp4',
    'philosopher.png': 'philosopher.mp4',
    'pirate.png': 'pirate.mp4',
    'queen.png': 'queen.mp4',
    'sorceress.png': 'sorceress.mp4',
    'viking.png': 'viking.mp4',
    'warrior.png': 'warrior.mp4',
    'wizard.png': 'wizard.mp4',
    'maori.png': 'maori.mp4',
    'zulu.png': 'zulu.mp4',
    'sentinel.png': 'sentinel.mp4',
    'kapu.png': 'kapu.mp4',
    'beserker.png': 'beserker.mp4'
};

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

const applyDataStyles = () => {
    document.querySelectorAll('[data-header-color]').forEach((el) => {
        const color = el.getAttribute('data-header-color');
        if (color) {
            el.style.color = color;
        }
    });

    document.querySelectorAll('.avatar-level-content[data-content-opacity]').forEach((el) => {
        const opacity = el.getAttribute('data-content-opacity');
        const pointer = el.getAttribute('data-content-pointer');
        if (opacity !== null && opacity !== '') {
            el.style.opacity = opacity;
        }
        if (pointer) {
            el.style.pointerEvents = pointer;
        }
    });

    document.querySelectorAll('[data-font-family]').forEach((el) => {
        const fontFamily = el.getAttribute('data-font-family');
        if (fontFamily) {
            el.style.fontFamily = fontFamily;
        }
    });

    document.querySelectorAll('[data-filter]').forEach((el) => {
        const filter = el.getAttribute('data-filter');
        if (filter) {
            el.style.filter = filter;
        }
    });

    document.querySelectorAll('[data-cursor]').forEach((el) => {
        const cursor = el.getAttribute('data-cursor');
        if (cursor) {
            el.style.cursor = cursor;
        }
    });

    document.querySelectorAll('[data-coin-size]').forEach((el) => {
        const size = el.getAttribute('data-coin-size');
        if (size) {
            el.style.width = size;
        }
    });
};

const openStoryModal = async (title, youtubeId) => {
    if (!youtubeId) {
        return;
    }

    const modal = await ModalFactory.create({
        type: ModalFactory.types.DEFAULT,
        title: title,
        body: `<div class="aa-youtube-modal-body">
            <iframe
                width="100%"
                height="600"
                src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(youtubeId)}?autoplay=1&rel=0&modestbranding=1&fs=1"
                title="${title}"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                class="aa-youtube-modal-iframe">
            </iframe>
        </div>`
    });

    modal.getRoot().addClass('aa-youtube-modal');
    modal.show();

    modal.getRoot().on(ModalEvents.hidden, () => {
        modal.destroy();
    });
};

export const init = (config) => {
    if (!document.querySelector('#a_avatars')) {
        return;
    }

    const tokensAvailable = Number(config?.tokensAvailable ?? 0);
    const coinBalance = Number(config?.coinBalance ?? 0);
    const storyFallbackTitle = config?.storyFallbackTitle || '';
    const modalStrings = config?.modalStrings || {};

    initAvatarModals({strings: modalStrings});

    applyDataStyles();

    document.querySelectorAll('.avatar-card').forEach((card) => {
        card.addEventListener('click', () => {
            const avatar = card.getAttribute('data-avatar');
            const name = card.getAttribute('data-name');
            const level = Number(card.getAttribute('data-level') || 0);
            const isUnlocked = card.classList.contains('unlocked');
            const videoFile = VIDEO_MAP[avatar] || 'elf.mp4';

            if (isUnlocked) {
                showAvatarReviewModal(avatar, name, videoFile);
                return;
            }
            showAvatarUnlockModal(name, avatar, videoFile, level, tokensAvailable);
        });
    });

    document.querySelectorAll('.pet-card').forEach((card) => {
        card.addEventListener('click', () => {
            const petId = Number(card.getAttribute('data-pet-id'));
            const petName = card.getAttribute('data-pet-name');
            const petPrice = Number(card.getAttribute('data-pet-price'));
            const canUnlock = card.getAttribute('data-can-unlock') === '1';
            const isOwned = card.classList.contains('owned');
            const petVideo = PET_VIDEO_MAP[petId] || 'lynx.mp4';

            if (isOwned) {
                showPetReviewModal(petName, petId, petVideo);
                return;
            }
            if (!canUnlock) {
                return;
            }
            showPetUnlockModal(petId, petName, petPrice, petVideo, tokensAvailable, coinBalance);
        });
    });

    document.querySelectorAll('.villain-card').forEach((card) => {
        card.addEventListener('click', () => {
            const villainId = Number(card.getAttribute('data-villain-id'));
            const villainName = card.getAttribute('data-villain-name');
            const villainPrice = Number(card.getAttribute('data-villain-price'));
            const villainImageName = card.getAttribute('data-villain-icon');
            const canUnlock = card.getAttribute('data-can-unlock') === '1';
            const isOwned = card.classList.contains('owned');
            const videoKey = villainImageName ? villainImageName.replace('.png', '') : '';
            const villainVideo = VILLAIN_VIDEO_MAP[videoKey] || 'elf_dryad.mp4';

            if (isOwned) {
                showVillainReviewModal(villainName, villainId, villainImageName, villainVideo);
                return;
            }
            if (!canUnlock) {
                return;
            }
            showVillainUnlockModal(
                villainId,
                villainName,
                villainPrice,
                villainImageName,
                villainVideo,
                tokensAvailable,
                coinBalance
            );
        });
    });

    document.querySelectorAll('.watch-story-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const youtubeId = btn.getAttribute('data-youtube-id');
            const setName = btn.getAttribute('data-set-name') || btn.textContent || '';
            const fallbackTitle = storyFallbackTitle || setName || '';
            openStoryModal(setName.trim() || fallbackTitle || '', youtubeId);
        });
    });
};
