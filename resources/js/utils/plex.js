import axios from 'axios';

/**
 * Browser-side Plex OAuth PIN flow (same approach as Overseerr / Seerr).
 */
export default class PlexOAuth {
    constructor() {
        this.plexHeaders = null;
        this.pin = null;
        this.popup = null;
    }

    initializeHeaders(clientId) {
        this.plexHeaders = {
            Accept: 'application/json',
            'X-Plex-Product': 'Omniseerr',
            'X-Plex-Version': 'Plex OAuth',
            'X-Plex-Client-Identifier': clientId,
            'X-Plex-Model': 'Plex OAuth',
            'X-Plex-Platform': navigator.userAgentData?.brands?.[0]?.brand || 'Web',
            'X-Plex-Platform-Version': '1.0',
            'X-Plex-Device': navigator.platform || 'Unknown',
            'X-Plex-Device-Name': 'Omniseerr (Web)',
            'X-Plex-Device-Screen-Resolution': `${window.screen.width}x${window.screen.height}`,
            'X-Plex-Language': 'en',
        };
    }

    preparePopup() {
        this.popup = this.openPopup({ title: 'Plex Auth', w: 600, h: 700 });
    }

    async login(clientId) {
        this.initializeHeaders(clientId);

        try {
            const { data } = await axios.post('https://plex.tv/api/v2/pins?strong=true', null, {
                headers: this.plexHeaders,
            });
            this.pin = { id: data.id, code: data.code };
        } catch (e) {
            this.closePopup();
            throw e;
        }

        if (!this.popup || this.popup.closed) {
            throw new Error('Unable to open the Plex login window. Allow popups and try again.');
        }

        const params = {
            clientID: this.plexHeaders['X-Plex-Client-Identifier'],
            'context[device][product]': this.plexHeaders['X-Plex-Product'],
            'context[device][version]': this.plexHeaders['X-Plex-Version'],
            'context[device][platform]': this.plexHeaders['X-Plex-Platform'],
            'context[device][platformVersion]': this.plexHeaders['X-Plex-Platform-Version'],
            'context[device][device]': this.plexHeaders['X-Plex-Device'],
            'context[device][deviceName]': this.plexHeaders['X-Plex-Device-Name'],
            'context[device][model]': this.plexHeaders['X-Plex-Model'],
            'context[device][screenResolution]': this.plexHeaders['X-Plex-Device-Screen-Resolution'],
            'context[device][layout]': 'desktop',
            code: this.pin.code,
        };

        this.popup.location.href = `https://app.plex.tv/auth/#!?${this.encodeData(params)}`;

        return this.pinPoll();
    }

    pinPoll() {
        const deadline = Date.now() + 15 * 60 * 1000;

        return new Promise((resolve, reject) => {
            const tick = async () => {
                try {
                    const { data } = await axios.get(`https://plex.tv/api/v2/pins/${this.pin.id}`, {
                        headers: this.plexHeaders,
                    });

                    if (data?.authToken) {
                        this.closePopup();
                        resolve(data.authToken);
                        return;
                    }

                    const expiresAt = data?.expiresAt ? Date.parse(data.expiresAt) : deadline;
                    if (Date.now() >= Math.min(expiresAt, deadline)) {
                        this.closePopup();
                        reject(new Error('Plex PIN expired before login completed.'));
                        return;
                    }

                    setTimeout(tick, 1000);
                } catch (e) {
                    if (e.response?.status === 404 && Date.now() < deadline) {
                        setTimeout(tick, 1000);
                        return;
                    }
                    this.closePopup();
                    reject(e);
                }
            };

            tick();
        });
    }

    closePopup() {
        this.popup?.close();
        this.popup = null;
    }

    openPopup({ title, w, h }) {
        const dualScreenLeft = window.screenLeft ?? window.screenX;
        const dualScreenTop = window.screenTop ?? window.screenY;
        const width = window.innerWidth || document.documentElement.clientWidth || screen.width;
        const height = window.innerHeight || document.documentElement.clientHeight || screen.height;
        const left = width / 2 - w / 2 + dualScreenLeft;
        const top = height / 2 - h / 2 + dualScreenTop;

        const popup = window.open(
            '/login/plex/loading',
            title,
            `scrollbars=yes,width=${w},height=${h},top=${top},left=${left}`,
        );

        popup?.focus();
        return popup;
    }

    encodeData(data) {
        return Object.keys(data)
            .map((key) => [key, data[key]].map(encodeURIComponent).join('='))
            .join('&');
    }
}
