// Graphical server console using noVNC (MPL-2.0, vendored in /assets/vendor/novnc).
// The page requests a single-use console session from the panel and connects
// to the panel's console proxy; no credentials of the hypervisor reach the browser.
import RFB from '../vendor/novnc/core/rfb.js';

const connectButton = document.getElementById('console-connect');
const cadButton = document.getElementById('console-cad');
const disconnectButton = document.getElementById('console-disconnect');
const status = document.getElementById('console-status');
const screen = document.getElementById('console-screen');
let rfb = null;

function setStatus(key) {
    status.textContent = status.dataset[key] || key;
}

function connected(isConnected) {
    connectButton.disabled = isConnected;
    cadButton.disabled = !isConnected;
    disconnectButton.disabled = !isConnected;
}

connectButton.addEventListener('click', async () => {
    connectButton.disabled = true;
    setStatus('connecting');
    try {
        const body = new FormData();
        body.append('_token', connectButton.dataset.csrf);
        const response = await fetch(connectButton.dataset.sessionUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok || typeof data.url !== 'string') {
            throw new Error(data.error || 'failed');
        }

        rfb = new RFB(screen, data.url, { credentials: { password: data.password }, wsProtocols: ['binary'] });
        rfb.scaleViewport = true;
        rfb.addEventListener('connect', () => {
            setStatus('connected');
            connected(true);
        });
        rfb.addEventListener('disconnect', () => {
            setStatus('disconnected');
            connected(false);
            rfb = null;
        });
        rfb.addEventListener('credentialsrequired', () => rfb.sendCredentials({ password: data.password }));
    } catch (error) {
        setStatus('failed');
        connected(false);
    }
});

cadButton.addEventListener('click', () => rfb && rfb.sendCtrlAltDel());
disconnectButton.addEventListener('click', () => rfb && rfb.disconnect());
