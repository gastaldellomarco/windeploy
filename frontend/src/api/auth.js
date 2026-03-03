// Minimal mock implementations for auth API used in hooks.
// Replace these with real HTTP calls to the backend when available.
export async function loginApi(_credentials) {
	// Simulate successful login
	return new Promise((resolve) => {
		setTimeout(() => {
			resolve({ token: 'fake-token', user: { id: 1, name: 'Demo User', role: 'admin' } });
		}, 200);
	});
}

export async function logoutApi() {
	return new Promise((resolve) => setTimeout(resolve, 100));
}

export async function meApi() {
	return new Promise((resolve) => setTimeout(() => resolve({ id: 1, name: 'Demo User', role: 'admin' }), 100));
}

export default {
	loginApi,
	logoutApi,
	meApi,
};
