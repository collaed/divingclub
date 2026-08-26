import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Chart = Chart;

// PWA service worker registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

// Push notification subscription (only for logged-in users)
window.initPushNotifications = function(vapidPublicKey, csrfToken) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !vapidPublicKey) return;

    navigator.serviceWorker.ready.then(reg => {
        reg.pushManager.getSubscription().then(sub => {
            if (sub) return; // already subscribed

            // Ask permission and subscribe
            reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
            }).then(subscription => {
                const key = subscription.getKey('p256dh');
                const auth = subscription.getKey('auth');
                fetch('/push/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        keys: {
                            p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
                            auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth)))
                        }
                    })
                });
            }).catch(() => {}); // user denied or error
        });
    });
};

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}
import './table-utils';
import './datepicker';
