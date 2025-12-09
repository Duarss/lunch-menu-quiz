import './bootstrap';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;

if (pusherKey) {
	window.Pusher = Pusher;

	window.Echo = new Echo({
		broadcaster: import.meta.env.VITE_BROADCAST_DRIVER ?? 'pusher',
		key: pusherKey,
		cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
		wsHost: import.meta.env.VITE_PUSHER_HOST
			? import.meta.env.VITE_PUSHER_HOST
			: `ws-${(import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1')}.pusher.com`,
		wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
		wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
		forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
		enabledTransports: ['ws', 'wss'],
		authEndpoint: import.meta.env.VITE_PUSHER_AUTH_ENDPOINT ?? '/broadcasting/auth',
	});
} else {
	console.warn('Pusher credentials not set. Real-time updates are disabled.');
}