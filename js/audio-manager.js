(function () {
    const config = window.RpsAudioConfig || {};

    const stateKey = 'rpsAudioState';
    const canPlayKey = 'rpsAudioCanPlay';
    const playedEffectsKey = 'rpsAudioPlayedEffects';
    const muteKey = 'rpsBacksoundMuted';
    const trackKey = config.trackKey || config.track;
    const trackVolume = Number.isFinite(config.volume) ? config.volume : 0.45;
    const audio = config.track ? new Audio(config.track) : null;
    let muteButton = null;

    if (audio) {
        audio.loop = true;
        audio.preload = 'auto';
        audio.volume = trackVolume;
        audio.muted = isBacksoundMuted();
    }

    window.RpsBacksound = audio;

    function readJson(key, fallback) {
        try {
            return JSON.parse(sessionStorage.getItem(key)) || fallback;
        } catch (error) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            sessionStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Storage can be unavailable in private browsing; audio should still work.
        }
    }

    function isBacksoundMuted() {
        try {
            return localStorage.getItem(muteKey) === '1';
        } catch (error) {
            return false;
        }
    }

    function setBacksoundMuted(isMuted) {
        try {
            localStorage.setItem(muteKey, isMuted ? '1' : '0');
        } catch (error) {}

        applyMuteState();
    }

    function applyMuteState() {
        const isMuted = isBacksoundMuted();

        if (audio) {
            audio.muted = isMuted;
        }

        if (muteButton) {
            muteButton.textContent = isMuted ? 'Muted' : 'Audio';
            muteButton.title = isMuted ? 'Nyalakan backsound' : 'Matikan backsound';
            muteButton.setAttribute('aria-label', muteButton.title);
            muteButton.setAttribute('aria-pressed', isMuted ? 'true' : 'false');
            muteButton.classList.toggle('is-muted', isMuted);
        }
    }

    function injectMuteButtonStyles() {
        if (document.getElementById('rps-audio-toggle-style')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'rps-audio-toggle-style';
        style.textContent = `
            .rps-audio-toggle {
                position: fixed;
                top: 16px;
                right: 16px;
                z-index: 9999;
                min-width: 76px;
                height: 38px;
                padding: 0 14px;
                border: 1px solid rgba(201, 168, 76, 0.45);
                border-radius: 8px;
                background: rgba(12, 18, 12, 0.78);
                color: #f0ead6;
                font: 700 12px/1.1 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                letter-spacing: 0;
                cursor: pointer;
                box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
                backdrop-filter: blur(8px);
            }

            .rps-audio-toggle:hover,
            .rps-audio-toggle:focus-visible {
                border-color: rgba(232, 212, 139, 0.8);
                color: #fff7d6;
                outline: none;
            }

            .rps-audio-toggle.is-muted {
                border-color: rgba(224, 122, 106, 0.55);
                color: #f0c2bb;
            }

            @media (max-width: 575px) {
                .rps-audio-toggle {
                    top: 10px;
                    right: 10px;
                    min-width: 68px;
                    height: 34px;
                    padding: 0 10px;
                    font-size: 11px;
                }
            }
        `;
        document.head.appendChild(style);
    }

    function createMuteButton() {
        if (document.getElementById('rps-audio-toggle')) {
            muteButton = document.getElementById('rps-audio-toggle');
            applyMuteState();
            return;
        }

        injectMuteButtonStyles();

        muteButton = document.createElement('button');
        muteButton.id = 'rps-audio-toggle';
        muteButton.type = 'button';
        muteButton.className = 'rps-audio-toggle';
        muteButton.addEventListener('click', () => {
            const nextMuted = !isBacksoundMuted();
            setBacksoundMuted(nextMuted);

            if (!nextMuted && audio) {
                startAudio().catch(() => {});
            }
        });

        document.body.appendChild(muteButton);
        applyMuteState();
    }

    function getSavedState() {
        return readJson(stateKey, {});
    }

    function saveState() {
        if (!audio) {
            return;
        }

        if (!Number.isFinite(audio.currentTime)) {
            return;
        }

        writeJson(stateKey, {
            trackKey: trackKey,
            time: audio.currentTime,
            updatedAt: Date.now()
        });
    }

    function restorePosition() {
        if (!audio) {
            return;
        }

        const saved = getSavedState();

        if (saved.trackKey !== trackKey || !Number.isFinite(saved.time) || saved.time <= 0) {
            return;
        }

        const nextTime = Number.isFinite(audio.duration) && audio.duration > 0
            ? saved.time % audio.duration
            : saved.time;

        try {
            audio.currentTime = nextTime;
        } catch (error) {
            audio.addEventListener('canplay', () => {
                try {
                    audio.currentTime = nextTime;
                } catch (innerError) {}
            }, { once: true });
        }
    }

    function playedEffects() {
        return readJson(playedEffectsKey, {});
    }

    function markEffectPlayed(effectKey) {
        const effects = playedEffects();
        effects[effectKey] = true;
        writeJson(playedEffectsKey, effects);
    }

    function playEffect() {
        if (!config.effect) {
            return Promise.resolve();
        }

        const effectKey = config.effectOnceKey || config.effect;
        const effects = playedEffects();

        if (effects[effectKey]) {
            return Promise.resolve();
        }

        const effect = new Audio(config.effect);
        const originalVolume = audio ? audio.volume : null;

        effect.preload = 'auto';
        effect.volume = Number.isFinite(config.effectVolume) ? config.effectVolume : 0.85;

        effect.addEventListener('play', () => {
            if (audio) {
                audio.volume = Math.min(originalVolume, 0.22);
            }
        });

        effect.addEventListener('ended', () => {
            if (audio) {
                audio.volume = originalVolume;
            }
        });

        effect.addEventListener('pause', () => {
            if (audio) {
                audio.volume = originalVolume;
            }
        });

        return effect.play()
            .then(() => {
                markEffectPlayed(effectKey);
            })
            .catch((error) => {
                if (audio) {
                    audio.volume = originalVolume;
                }
                throw error;
            });
    }

    function startAudio() {
        if (!audio) {
            return playEffect();
        }

        restorePosition();

        return audio.play()
            .then(() => {
                sessionStorage.setItem(canPlayKey, '1');
                return playEffect().catch(() => {});
            });
    }

    function waitForGesture() {
        const unlock = () => {
            startAudio()
                .then(removeUnlockListeners)
                .catch(() => {});
        };

        const removeUnlockListeners = () => {
            document.removeEventListener('pointerdown', unlock);
            document.removeEventListener('keydown', unlock);
            document.removeEventListener('touchstart', unlock);
        };

        document.addEventListener('pointerdown', unlock, { passive: true });
        document.addEventListener('keydown', unlock);
        document.addEventListener('touchstart', unlock, { passive: true });
    }

    if (audio) {
        audio.addEventListener('loadedmetadata', restorePosition, { once: true });
        audio.addEventListener('timeupdate', saveState);
        setInterval(saveState, 750);
    }

    window.addEventListener('pagehide', saveState);
    window.addEventListener('beforeunload', saveState);
    window.addEventListener('storage', (event) => {
        if (event.key === muteKey) {
            applyMuteState();
        }
    });

    if (document.body) {
        createMuteButton();
    } else {
        document.addEventListener('DOMContentLoaded', createMuteButton, { once: true });
    }

    if (!config.track && !config.effect) {
        return;
    }

    startAudio().catch(() => {
        if (sessionStorage.getItem(canPlayKey) === '1') {
            startAudio().catch(waitForGesture);
            return;
        }

        waitForGesture();
    });
})();
