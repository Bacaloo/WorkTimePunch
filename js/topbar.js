(function () {
    const APP_ID = 'worktimepunch';
    const ROOT_ID = 'worktimepunch-topbar';
    const STATE_CHANGED_KEY = `${APP_ID}:state-changed`;
    const MAX_ATTEMPTS = 40;
    let attempts = 0;
    let root = null;
    let pendingAction = null;
    let stateChannel = null;

    if (window.BroadcastChannel) {
        stateChannel = new window.BroadcastChannel(`${APP_ID}:state`);
    }

    const webRoot = window.OC?.webroot || '';
    const appWebRoot = window.OC?.appswebroots?.[APP_ID] || `${webRoot}/custom_apps/${APP_ID}`;
    const generateUrl = (path) => {
        if (window.OC?.generateUrl) {
            return window.OC.generateUrl(path);
        }

        return `${webRoot}${path}`;
    };

    const actions = [
        {
            id: 'kommen',
            label: 'Kommen',
            icon: 'kommen.svg',
        },
        {
            id: 'pausenanfang',
            label: 'In Pause gehen',
            icon: 'pausenanfang.svg',
        },
        {
            id: 'pausenende',
            label: 'Aus der Pause kommen',
            icon: 'pausenende.svg',
        },
        {
            id: 'gehen',
            label: 'Gehen',
            icon: 'gehen.svg',
        },
    ];

    const findAnchor = () => (
        document.getElementById('appmenu')
        || document.querySelector('#header .header-start')
        || document.querySelector('#header .header-left')
        || document.getElementById('header')
    );

    const requestToken = () => (
        window.OC?.requestToken
        || document.querySelector('[data-requesttoken]')?.dataset?.requesttoken
        || ''
    );

    const withRequestToken = (url) => {
        const token = requestToken();
        if (token === '') {
            return url;
        }

        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}requesttoken=${encodeURIComponent(token)}`;
    };

    const notify = (message) => {
        if (!message) {
            return;
        }

        if (window.OC?.Notification?.showTemporary) {
            window.OC.Notification.showTemporary(message);
            return;
        }

        window.alert(message);
    };

    const fetchJson = async (url, options = {}) => {
        const response = await window.fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'OCS-APIRequest': 'true',
                'X-Requested-With': 'XMLHttpRequest',
                requesttoken: requestToken(),
                ...(options.headers || {}),
            },
            ...options,
        });
        const raw = await response.text();
        let data = {};
        try {
            data = raw === '' ? {} : JSON.parse(raw);
        } catch (error) {
            data = {
                error: raw.replace(/\s+/g, ' ').trim().slice(0, 180),
            };
        }

        if (!response.ok) {
            const message = data.error || 'WorkTimePunch konnte die Aktion nicht ausfuehren.';
            const error = new Error(`HTTP ${response.status}: ${message}`);
            error.data = data;
            error.status = response.status;
            throw error;
        }

        return data;
    };

    const formatPunchTime = (date) => (
        `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
    );

    const formatStateTime = (value) => {
        if (typeof value !== 'string' || value === '') {
            return null;
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return formatPunchTime(date);
    };

    const sameMinute = (first, second) => (
        typeof first === 'string'
        && typeof second === 'string'
        && first.slice(0, 16) === second.slice(0, 16)
    );

    const lastPunchFromState = (state) => {
        const session = state?.session;
        if (!session) {
            return null;
        }

        if (state.state === 'paused') {
            return {
                action: 'pausenanfang',
                time: formatStateTime(session.breakStartedAt),
            };
        }

        if (state.state !== 'working') {
            return null;
        }

        if (
            session.startedAt
            && (
                !session.segmentStartedAt
                || sameMinute(session.startedAt, session.segmentStartedAt)
            )
        ) {
            return {
                action: 'kommen',
                time: formatStateTime(session.startedAt),
            };
        }

        return {
            action: 'pausenende',
            time: formatStateTime(session.segmentStartedAt),
        };
    };

    const stateLabel = (state) => {
        if (state === 'working') {
            return 'im Betrieb';
        }
        if (state === 'paused') {
            return 'in Pause';
        }
        return 'nicht im Betrieb';
    };

    const render = (state) => {
        if (!root) {
            return;
        }

        root.replaceChildren();
        if (!state?.available) {
            root.hidden = true;
            return;
        }

        root.hidden = false;
        root.title = `${state.employee?.name || 'WorkTime'}: ${stateLabel(state.state)}`;

        const lastPunch = lastPunchFromState(state);
        actions.forEach((action) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'worktimepunch-topbar-button';
            button.title = action.label;
            button.setAttribute('aria-label', action.label);
            button.dataset.worktimepunchAction = action.id;

            const enabled = Boolean(state.buttons?.[action.id]?.enabled) && pendingAction === null;
            button.disabled = !enabled;
            button.setAttribute('aria-disabled', enabled ? 'false' : 'true');

            const lastPunchTime = lastPunch?.action === action.id ? lastPunch.time : null;
            if (lastPunchTime) {
                button.classList.add('worktimepunch-topbar-button--with-time');
                button.setAttribute('aria-label', `${action.label}, letzter Klick ${lastPunchTime}`);
                button.title = `${action.label}: ${lastPunchTime}`;
            }

            const img = document.createElement('img');
            img.src = `${appWebRoot}/img/${action.icon}`;
            img.alt = '';
            img.setAttribute('aria-hidden', 'true');

            button.appendChild(img);
            if (lastPunchTime) {
                const time = document.createElement('span');
                time.className = 'worktimepunch-topbar-time';
                time.textContent = lastPunchTime;
                time.setAttribute('aria-hidden', 'true');
                button.appendChild(time);
            }
            button.addEventListener('click', () => punch(action.id));
            root.appendChild(button);
        });
    };

    const refresh = async () => {
        if (!root) {
            return;
        }

        try {
            render(await fetchJson(generateUrl('/apps/worktimepunch/api/state')));
        } catch (error) {
            if (root) {
                root.hidden = true;
            }
        }
    };

    const refreshWhenVisible = () => {
        if (pendingAction !== null || document.visibilityState === 'hidden') {
            return;
        }

        refresh();
    };

    const announceStateChange = () => {
        const payload = {
            type: 'stateChanged',
            at: Date.now(),
        };

        if (stateChannel) {
            stateChannel.postMessage(payload);
        }

        try {
            window.localStorage.setItem(STATE_CHANGED_KEY, String(payload.at));
        } catch (error) {
            // Ignore storage restrictions; focus refresh still keeps tabs current.
        }
    };

    const punch = async (action) => {
        if (pendingAction !== null) {
            return;
        }

        pendingAction = action;
        try {
            const state = await fetchJson(withRequestToken(generateUrl(`/apps/worktimepunch/api/punch/${action}`)), {
                method: 'POST',
                body: '{}',
            });

            render(state);
            announceStateChange();
        } catch (error) {
            notify(error.message);
            render(error.data?.state || await fetchJson(generateUrl('/apps/worktimepunch/api/state')));
        } finally {
            pendingAction = null;
            refresh();
        }
    };

    const createRoot = () => {
        const element = document.createElement('span');
        element.id = ROOT_ID;
        element.hidden = true;
        element.setAttribute('role', 'group');
        element.setAttribute('aria-label', 'WorkTimePunch');

        return element;
    };

    const mount = () => {
        if (document.getElementById(ROOT_ID)) {
            root = document.getElementById(ROOT_ID);
            refresh();
            return;
        }

        const anchor = findAnchor();
        if (!anchor || !anchor.parentElement) {
            attempts += 1;
            if (attempts < MAX_ATTEMPTS) {
                window.setTimeout(mount, 250);
            }
            return;
        }

        root = createRoot();
        anchor.parentElement.insertBefore(root, anchor.nextSibling);
        refresh();
    };

    if (stateChannel) {
        stateChannel.addEventListener('message', (event) => {
            if (event.data?.type === 'stateChanged') {
                refreshWhenVisible();
            }
        });
    }

    window.addEventListener('storage', (event) => {
        if (event.key === STATE_CHANGED_KEY) {
            refreshWhenVisible();
        }
    });

    window.addEventListener('focus', refreshWhenVisible);
    window.addEventListener('pageshow', refreshWhenVisible);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshWhenVisible();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount, { once: true });
        return;
    }

    mount();
})();
