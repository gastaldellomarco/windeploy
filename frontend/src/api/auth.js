import client from './client';

/**
 * Login utente via Sanctum Bearer Token.
 * POST /api/auth/login
 * Response attesa: { token, token_expires_at, user: { id, name, email, role } }
 */
export async function loginApi(credentials) {
  const response = await client.post('/auth/login', credentials);
  return response.data;
}

/**
 * Logout — invalida il token Sanctum lato server.
 * POST /api/auth/logout
 * Richiede Authorization: Bearer header (aggiunto dall'interceptor).
 */
export async function logoutApi() {
  const response = await client.post('/auth/logout');
  return response.data;
}

/**
 * Recupera il profilo dell'utente autenticato.
 * GET /api/auth/me
 * Usato all'avvio app per verificare che il token in localStorage sia ancora valido.
 */
export async function meApi() {
  const response = await client.get('/auth/me');
  return response.data;
}

export default { loginApi, logoutApi, meApi };
