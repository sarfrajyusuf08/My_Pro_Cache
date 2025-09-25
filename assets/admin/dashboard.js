(() => {
    const storageKey = 'mpc:debug:open';

    const onReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    const persistDebugPanel = () => {
        const panel = document.getElementById('mpc-debug-panel');
        if (!panel) {
            return;
        }

        try {
            const stored = window.localStorage.getItem(storageKey);
            if (stored !== null) {
                panel.open = stored === '1';
            } else if (panel.dataset.defaultOpen === 'true') {
                panel.open = true;
            }
        } catch (error) {
            // Ignore storage access issues.
        }

        panel.addEventListener('toggle', () => {
            try {
                window.localStorage.setItem(storageKey, panel.open ? '1' : '0');
            } catch (error) {
                // Ignore storage write failures.
            }
        });
    };

    const setupCopyButtons = () => {
        const buttons = document.querySelectorAll('[data-mpc-copy-log]');
        if (!buttons.length) {
            return;
        }

        const copyText = async (text) => {
            if (!text) {
                throw new Error('Empty text');
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                return;
            }

            const temp = document.createElement('textarea');
            temp.value = text;
            temp.setAttribute('readonly', '');
            temp.style.position = 'absolute';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
        };

        buttons.forEach((button) => {
            const targetSelector = button.getAttribute('data-mpc-copy-log');
            const statusNode = button.nextElementSibling?.classList.contains('mpc-copy-feedback')
                ? button.nextElementSibling
                : null;

            button.addEventListener('click', async () => {
                const target = targetSelector ? document.querySelector(targetSelector) : null;
                const fallbackMessage = button.getAttribute('data-copy-label') || '';

                if (!target) {
                    return;
                }

                const text = target.innerText.trim();

                const announce = (message) => {
                    if (statusNode) {
                        statusNode.textContent = message;
                    }
                };

                try {
                    await copyText(text);
                    announce(fallbackMessage || button.dataset.copied || '');
                } catch (error) {
                    announce(button.dataset.copyError || '');
                }
            });
        });
    };

    onReady(() => {
        persistDebugPanel();
        setupCopyButtons();
    });
})();
