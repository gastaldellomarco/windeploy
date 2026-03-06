# ⚠️ FILE DEPRECATO

**Questo file è stato unito in `0014-Login.md`.**

Tutte le informazioni sulla pagina Login si trovano in:  
→ **`docs/0014-Login.md`**

---

<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Crea la pagina Login per WinDeploy in React 18 + Tailwind CSS.

DESIGN: professionale, dark mode opzionale, sfondo con gradiente blu scuro (\#1E3A5F → \#2E75B6),
card bianca al centro, logo "⚙️ WinDeploy" in alto, sotto titolo "IT Provisioning Platform".

 
FUNZIONALITÀ:

- Form con campo email e password
- Bottone "Accedi" con loading spinner durante la chiamata API
- Gestione errori: messaggio rosso se credenziali errate o troppi tentativi
- Dopo login riuscito → redirect alla /dashboard (usa React Router useNavigate)
- Salva il token nello authStore Zustand
- Se già loggato → redirect automatico a /dashboard

 
CHIAMATA API:
POST /api/auth/login con { email, password }
Risposta: { token, user: { id, nome, email, ruolo } }

 
COMPONENTI DA USARE: input con icona (Lucide: Mail, Lock, Eye/EyeOff), Button con variante primaria.

 
Scrivi il file completo: src/pages/Login/index.jsx
Includi anche il piccolo componente InputField riutilizzabile nello stesso file (o in src/components/InputField.jsx).
Codice completo, funzionante, con Tailwind classes.

Ecco una pagina Login completa (React 18 + Tailwind) con gradient background, card centrale, form email/password con icone (Lucide), spinner sul bottone, gestione errori (incl. 429 troppi tentativi) e redirect a `/dashboard` via `useNavigate` salvando il token nello `authStore` Zustand.[^1_1][^1_2]

## Struttura file

- `src/pages/Login/index.jsx` (questa implementazione)[^1_1]
- Dipendenze attese nel progetto: `zustand`, `react-router-dom`, `axios` client (es. `src/api/client.js`) e `lucide-react`.[^1_1]

## src/pages/Login/index.jsx

```jsx
import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Mail, Lock, Eye, EyeOff, Sun, Moon } from "lucide-react";

// If your authStore exports a named hook instead of default, adjust this import accordingly.
import useAuthStore from "../../store/authStore";
import client from "../../api/client";

function cn(...classes) {
  return classes.filter(Boolean).join(" ");
}

function LoadingSpinner({ className = "" }) {
  return (
    <span
      className={cn(
        "inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white",
        className,
      )}
      aria-hidden="true"
    />
  );
}

function Button({
  children,
  className = "",
  variant = "primary",
  isLoading = false,
  disabled,
  ...props
}) {
  const base =
    "inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2";
  const variants = {
    primary:
      "bg-[#1E3A5F] text-white hover:bg-[#17304E] focus:ring-[#1E3A5F] focus:ring-offset-white",
    ghost:
      "bg-transparent text-slate-700 hover:bg-slate-100 focus:ring-slate-400 focus:ring-offset-white",
  };

  return (
    <button
      className={cn(
        base,
        variants[variant] || variants.primary,
        "disabled:cursor-not-allowed disabled:opacity-60",
        className,
      )}
      disabled={Boolean(disabled) || isLoading}
      {...props}
    >
      {isLoading ? <LoadingSpinner /> : null}
      <span>{children}</span>
    </button>
  );
}

function InputField({
  id,
  name,
  label,
  value,
  onChange,
  type = "text",
  placeholder,
  autoComplete,
  disabled,
  required,
  leftIcon: LeftIcon,
  rightSlot,
}) {
  return (
    <div className="space-y-1.5">
      <label
        htmlFor={id}
        className="block text-xs font-semibold tracking-wide text-slate-700"
      >
        {label}
      </label>

      <div className="relative">
        {LeftIcon ? (
          <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <LeftIcon className="h-4 w-4 text-slate-400" />
          </div>
        ) : null}

        <input
          id={id}
          name={name}
          value={value}
          onChange={onChange}
          type={type}
          placeholder={placeholder}
          autoComplete={autoComplete}
          disabled={disabled}
          required={required}
          className={cn(
            "w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm",
            "placeholder:text-slate-400",
            "focus:border-transparent focus:ring-2 focus:ring-[#2E75B6]",
            LeftIcon ? "pl-10" : "",
            rightSlot ? "pr-10" : "",
            "disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500",
          )}
        />

        {rightSlot ? (
          <div className="absolute inset-y-0 right-0 flex items-center pr-2">
            {rightSlot}
          </div>
        ) : null}
      </div>
    </div>
  );
}

function getApiErrorMessage(error) {
  const status = error?.response?.status;

  if (status === 429)
    return "Troppi tentativi di login. Riprova tra qualche minuto.";
  if (status === 401) return "Credenziali non valide.";
  if (status === 422) return "Controlla i campi e riprova.";

  return "Impossibile effettuare il login. Verifica la connessione e riprova.";
}

function normalizeUser(user) {
  if (!user || typeof user !== "object") return null;

  return {
    id: user.id,
    nome: user.nome ?? user.name ?? "",
    email: user.email ?? "",
    ruolo: user.ruolo ?? user.role ?? "",
  };
}

export default function LoginPage() {
  const navigate = useNavigate();

  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const authLogin = useAuthStore((s) => s.login);

  const [form, setForm] = useState({ email: "", password: "" });
  const [showPassword, setShowPassword] = useState(false);

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");

  const [isDark, setIsDark] = useState(false);

  const canSubmit = useMemo(() => {
    return (
      form.email.trim().length > 0 && form.password.length > 0 && !isSubmitting
    );
  }, [form.email, form.password, isSubmitting]);

  useEffect(() => {
    const stored = window.localStorage.getItem("windeploy:theme");
    const nextIsDark = stored ? stored === "dark" : false;
    setIsDark(nextIsDark);
    document.documentElement.classList.toggle("dark", nextIsDark);
  }, []);

  useEffect(() => {
    if (isAuthenticated) {
      navigate("/dashboard", { replace: true });
    }
  }, [isAuthenticated, navigate]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errorMessage) setErrorMessage("");
  }

  function toggleTheme() {
    const next = !isDark;
    setIsDark(next);
    window.localStorage.setItem("windeploy:theme", next ? "dark" : "light");
    document.documentElement.classList.toggle("dark", next);
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!canSubmit) return;

    setIsSubmitting(true);
    setErrorMessage("");

    try {
      // POST /api/auth/login with { email, password }
      const res = await client.post("/auth/login", {
        email: form.email.trim(),
        password: form.password,
      });

      const token = res?.data?.token;
      const userRaw = res?.data?.user;

      if (!token || !userRaw) {
        throw new Error("Invalid login response shape");
      }

      const user = normalizeUser(userRaw);

      // Save into Zustand store
      authLogin(token, user);

      // Redirect after successful login
      navigate("/dashboard", { replace: true });
    } catch (err) {
      setErrorMessage(getApiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div
      className={cn(
        "min-h-screen w-full",
        "bg-[linear-gradient(135deg,#1E3A5F_0%,#2E75B6_100%)]",
      )}
    >
      <div className="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-10">
        <div className="relative w-full max-w-md">
          {/* Subtle glow */}
          <div className="pointer-events-none absolute -inset-6 rounded-3xl bg-white/10 blur-2xl" />

          <div className="relative rounded-2xl bg-white p-7 shadow-2xl ring-1 ring-black/5">
            <div className="absolute right-4 top-4">
              <button
                type="button"
                onClick={toggleTheme}
                className={cn(
                  "inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700",
                  "hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#2E75B6] focus:ring-offset-2",
                )}
                aria-label="Toggle dark mode"
              >
                {isDark ? (
                  <Sun className="h-4 w-4" />
                ) : (
                  <Moon className="h-4 w-4" />
                )}
                <span>{isDark ? "Light" : "Dark"}</span>
              </button>
            </div>

            <header className="mb-6 text-center">
              <div className="text-2xl font-extrabold tracking-tight text-slate-900">
                <span aria-hidden="true">⚙️ </span>
                WinDeploy
              </div>
              <div className="mt-1 text-sm font-medium text-slate-500">
                IT Provisioning Platform
              </div>
            </header>

            {errorMessage ? (
              <div
                className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                role="alert"
              >
                {errorMessage}
              </div>
            ) : null}

            <form onSubmit={handleSubmit} className="space-y-4">
              <InputField
                id="email"
                name="email"
                label="Email"
                type="email"
                placeholder="nome.cognome@azienda.it"
                autoComplete="email"
                required
                disabled={isSubmitting}
                value={form.email}
                onChange={handleChange}
                leftIcon={Mail}
              />

              <InputField
                id="password"
                name="password"
                label="Password"
                type={showPassword ? "text" : "password"}
                placeholder="••••••••"
                autoComplete="current-password"
                required
                disabled={isSubmitting}
                value={form.password}
                onChange={handleChange}
                leftIcon={Lock}
                rightSlot={
                  <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    className={cn(
                      "inline-flex h-9 w-9 items-center justify-center rounded-md",
                      "text-slate-500 hover:bg-slate-100 hover:text-slate-700",
                      "focus:outline-none focus:ring-2 focus:ring-[#2E75B6]",
                    )}
                    aria-label={
                      showPassword ? "Hide password" : "Show password"
                    }
                    tabIndex={0}
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4" />
                    ) : (
                      <Eye className="h-4 w-4" />
                    )}
                  </button>
                }
              />

              <div className="pt-1">
                <Button
                  type="submit"
                  variant="primary"
                  isLoading={isSubmitting}
                  disabled={!canSubmit}
                >
                  Accedi
                </Button>
              </div>

              <p className="text-center text-xs text-slate-500">
                Accesso riservato. Se non hai credenziali, contatta un Admin.
              </p>
            </form>
          </div>

          <p className="mt-4 text-center text-xs text-white/80">
            © {new Date().getFullYear()} WinDeploy
          </p>
        </div>
      </div>
    </div>
  );
}
```

## Nota rapida (errori \& rate limit)

- Se il backend applica un rate limit al login, tipicamente risponderà `429` e qui viene mostrato un messaggio dedicato “Troppi tentativi…”.[^1_2]
- In caso di credenziali errate, vengono gestiti `401/422` senza rivelare quale campo sia sbagliato (buona pratica).[^1_2]

Se vuoi, nel prossimo step posso allineare **esattamente** il mapping dei campi `user` (`nome/ruolo` vs `name/role`) al tuo `authStore` reale (mi basta che mi incolli `src/store/authStore.js`).
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_3][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0013-SetupprogettoReact.md

[^1_2]: 0010-autenticazione.md

[^1_3]: 0012-apiendpointwindows.md

[^1_4]: 0011-controllercrud.md

[^1_5]: 0009-scaffoldprogetto.md

[^1_6]: 0008-db.md

[^1_7]: 0007-cheatsheet.md

[^1_8]: 0006-viteconfigjs.md

[^1_9]: 0005-filedotenv.md

[^1_10]: 0004-Strutturacartelle.md

[^1_11]: 0003-setupiniziale.md

[^1_12]: 0002-ricerca-iniziale.md

[^1_13]: 0001-projectdescription.txt

---

# mi esce così è normale?

No: dalla schermata che mi hai inviato stai vedendo **la vecchia login “stub”** (testo a sinistra su sfondo scuro, senza card centrale/gradiente) oppure Tailwind non sta venendo applicato.[^2_1][^2_2]

## Checklist rapida (2 minuti)

### 1) Stai renderizzando il file giusto?

Nel tuo scaffold WinDeploy la route di login è importata come `../pages/Login/LoginPage` (file `LoginPage.jsx`), non `../pages/Login/index.jsx`.[^2_2]
Quindi se hai creato `src/pages/Login/index.jsx` ma il router punta ancora a `LoginPage.jsx`, vedrai esattamente una pagina “semplice” come nello screenshot.[^2_2]

**Cosa fare**

- Apri `src/router/AppRouter.jsx` e cambia l’import della login a:
  - `import LoginPage from "../pages/Login";` (così prende `pages/Login/index.jsx`), **oppure**
  - `import LoginPage from "../pages/Login/index.jsx";`[^2_2]

```
Test veloce: metti per 10 secondi in cima al tuo `LoginPage` nuovo un `<div className="bg-red-500 text-white p-4">NEW LOGIN</div>` e verifica che compaia.[^2_1]
```

### 2) Tailwind è attivo davvero?

Nel progetto WinDeploy è previsto che `src/main.jsx` importi `./index.css` e che `index.css` contenga le direttive Tailwind.[^2_2]
Se queste due cose mancano, le classi Tailwind (gradiente/card/spacing) non faranno effetto.[^2_2]

**Cosa controllare**

- `src/main.jsx` deve avere `import "./index.css";`[^2_2]
- `src/index.css` deve contenere:
  - `@tailwind base;`
  - `@tailwind components;`
  - `@tailwind utilities;`[^2_2]

### 3) Tailwind “content paths” corretti

Se il `tailwind.config` non include `./src/**/*.{js,jsx,...}`, Tailwind può “non vedere” i componenti e non generare le classi (specie quelle arbitrarie tipo `bg-[linear-gradient(...)]`).[^2_2]

### 4) Riavvia Vite

Dopo modifiche a config Tailwind / PostCSS, spesso serve fermare e riavviare `npm run dev`.[^2_2]

## Nota “avvocato del diavolo”

Se il router punta al file sbagliato, perdi tempo a debuggare “Tailwind rotto” quando in realtà stai semplicemente renderizzando un altro componente.[^2_2]

Se mi incolli qui il tuo `src/router/AppRouter.jsx` (solo la parte import + route `/login`) ti dico esattamente quale riga cambiare.
<span style="display:none">[^2_3]</span>

<div align="center">⁂</div>

[^2_1]: 0014-PaginaLogin.md

[^2_2]: 0013-SetupprogettoReact.md

[^2_3]: image.jpg

---

# http://localhost:5173/login

adesso è così

No, **non è normale**: una pagina completamente bianca su `/login` di solito significa che l’app React sta andando in errore a runtime (e quindi non renderizza nulla) oppure che stai importando un modulo in modo sbagliato.[^3_1]

## 1) Controllo immediato (Console)

Apri DevTools → **Console** e dimmi l’errore rosso che vedi (basta incollare le prime 2–3 righe).[^3_1]
In Vite/React, un errore tipo “`X is not a function`” o “Failed to resolve import” porta spesso a schermata bianca.[^3_1]

## 2) Causa più probabile: import di Zustand (default vs named)

Nel tuo scaffold WinDeploy lo store è mostrato come **named export**: `export const useAuthStore = create(...)`.[^3_1]
Se nel Login hai scritto `import useAuthStore from "../../store/authStore";`, allora `useAuthStore` può risultare `undefined` e la pagina “crasha” appena fai `useAuthStore(...)`, dando bianco.[^3_2][^3_1]

**Fix (consigliato, allineato allo scaffold)**
Nel file `src/pages/Login/index.jsx` cambia l’import così:[^3_1]

```js
import { useAuthStore } from "../../store/authStore";
```

e lascia invariato l’uso `useAuthStore((s) => s.isAuthenticated)` ecc.[^3_2]

**Alternativa**
Se invece nel tuo `src/store/authStore.js` esporti `export default ...`, allora l’import default va bene: l’import deve matchare l’export reale.[^3_1]

## 3) Altri 2 check rapidi se non è quello

- Router: assicurati che `/login` punti al componente giusto (nel documento lo scaffold importa `../pages/Login/LoginPage` per la route login). Se hai creato `pages/Login/index.jsx`, devi aggiornare l’import nel router.[^3_1]
- Tailwind: anche se Tailwind non funzionasse, dovresti vedere testo non stilizzato; se vedi bianco totale è più probabile un errore JS, però verifica comunque che `src/main.jsx` importi `./index.css`.[^3_1]

Incollami qui l’errore della Console (e se puoi anche l’inizio di `src/store/authStore.js`, solo export finale e 5–10 righe) e ti dico con precisione quale variante (default/named) stai usando.
<span style="display:none">[^3_3]</span>

<div align="center">⁂</div>

[^3_1]: 0013-SetupprogettoReact.md

[^3_2]: 0014-PaginaLogin.md

[^3_3]: image.jpg

---

# Uncaught SyntaxError: The requested module '/src/store/authStore.js' does not provide an export named 'default' (at index.jsx:6:8)

Perfetto: l’errore dice esattamente che `authStore.js` **non esporta un default**, quindi nel tuo `index.jsx` stai facendo un import default tipo `import useAuthStore from "/src/store/authStore.js"` (o simile) ma lo store è un **named export**.[^4_1]

## Fix (1 riga)

Nel file `src/pages/Login/index.jsx` cambia l’import dello store in **named import**:

```diff
- import useAuthStore from "../../store/authStore";
+ import { useAuthStore } from "../../store/authStore";
```

e lascia tutto il resto uguale (le chiamate tipo `useAuthStore((s) => s.isAuthenticated)` funzionano).[^4_1]

## Checklist veloce

- Salva il file.
- Se la pagina resta bianca, stop/start `npm run dev` (Vite a volte “incastra” errori di import finché non ricompila pulito).[^4_1]

## Nota sicurezza (importante)

Lo scaffold salva token in `localStorage` per comodità, ma in produzione è più esposto a XSS; tienilo presente quando hardeni la web app (CSP, sanitizzazione, niente `dangerouslySetInnerHTML`).[^4_1]

Se vuoi, incollami anche l’import di `client` (axios) che usi nel Login: nel tuo scaffold l’istanza è `src/api/client.js` con `baseURL: "api"` e interceptor del token.[^4_1]

<div align="center">⁂</div>

[^4_1]: 0013-SetupprogettoReact.md

---

# image.jpg

Non è normale: dallo screenshot si vede che **il componente ora viene renderizzato**, ma Tailwind non sta applicando le classi (es. il toggle “Light” dovrebbe stare in alto a destra e la card dovrebbe essere centrata/bianca).[^5_1]
In più, l’errore “Impossibile effettuare il login…” è coerente con una chiamata API che non sta passando dal proxy `/api`.[^5_2][^5_1]

## 1) Tailwind non applicato (fix)

Indizi: layout “tutto a sinistra”, niente gradient, niente card/spacing/posizionamenti assoluti ⇒ classi Tailwind ignorate.[^5_1]

Checklist:

- In `src/main.jsx` deve esserci `import "./index.css";` (come da scaffold).[^5_2]
- In `src/index.css` devono esserci le direttive Tailwind: `@tailwind base; @tailwind components; @tailwind utilities;`.[^5_2]
- Riavvia Vite dopo le modifiche: stop e `npm run dev`.[^5_2]

## 2) Login API: hai un “/” di troppo (quasi sicuramente)

Nel tuo scaffold l’axios client tipicamente usa `baseURL: "api"` (senza slash) e il proxy Vite inoltra `/api/*` al backend.[^5_2]
Se fai `client.post("/auth/login", ...)` (con slash iniziale), Axios tratta l’URL come assoluto e finisci su `http://localhost:5173/auth/login` invece che `http://localhost:5173/api/auth/login`, quindi fallisce e vedi il messaggio generico.[^5_1][^5_2]

### Patch minima in `src/pages/Login/index.jsx`

Cambia questa riga:

```diff
- const res = await client.post("/auth/login", {
+ const res = await client.post("auth/login", {
    email: form.email.trim(),
    password: form.password,
  });
```

Questo è coerente con lo stile mostrato nello scaffold (chiamate tipo `client.post("auth/login", ...)`).[^5_2]

## Verifica finale (30 secondi)

- DevTools → Network → clic “Accedi” → la request deve essere **POST** verso `/api/auth/login` (o comunque passare da `/api/...`).[^5_2]
- Se ancora non si centra/colora: Tailwind/CSS non sta caricando, torna al punto 1.[^5_1][^5_2]

Se mi incolli il contenuto di `src/api/client.js` (o almeno `baseURL`), ti dico l’URL esatto che devi usare (`"auth/login"` vs `"/api/auth/login"`).[^5_2]
<span style="display:none">[^5_3]</span>

<div align="center">⁂</div>

[^5_1]: 0014-PaginaLogin.md

[^5_2]: 0013-SetupprogettoReact.md

[^5_3]: image.jpg

---

# import axios from 'axios';

import { useAuthStore } from '../store/authStore';

// Creiamo una singola instance Axios per tutto il frontend
const client = axios.create({
  baseURL: '/api',
  withCredentials: false, // usiamo Bearer token, non cookie
  timeout: 15000,
});

// Request interceptor: aggiunge Authorization Bearer <token> se presente
client.interceptors.request.use(
  (config) => {
    const { token } = useAuthStore.getState();

if (token) {
      config.headers = config.headers || {};
      config.headers.Authorization = `Bearer ${token}`;
    }

return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor: se 401 → logout e redirect a /login
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      const { logout } = useAuthStore.getState();
      logout();

// Redirect “grezzo” fuori da React Router per evitare dipendenze circolari
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

return Promise.reject(error);
  }
);

export default client;
(client.js)

Ok, con quel `client.js` **la chiamata che hai nel Login va bene**: con `baseURL: '/api'` puoi (e dovresti) fare `client.post('/auth/login', ...)` e verrà chiamato `/api/auth/login`.[^6_1]

Quindi ora hai **due problemi separati**:

## 1) Layout “tutto a sinistra” = Tailwind/CSS non caricato

Dallo screenshot le classi Tailwind non stanno influenzando layout/posizionamenti (il bottone tema dovrebbe stare in alto a destra della card, la card al centro, gradient visibile).[^6_2]

Checklist (vai in ordine):

1. `src/main.jsx` deve avere `import './index.css'` (è proprio nello scaffold).[^6_1]
2. `src/index.css` deve contenere:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

[ file:1 ] 3) `tailwind.config.*` deve includere i path `./index.html` e `./src/**/*.{js,jsx,ts,tsx}` (altrimenti Tailwind non genera le classi).[^6_1] 4) Stop/start Vite: `CTRL+C` e poi `npm run dev`.[^6_1]

Se dopo questi check vedi ancora tutto “plain”, incollami `src/index.css` e il tuo `tailwind.config` e te li sistemo.

## 2) Messaggio “Impossibile effettuare il login…” = proxy/API che non risponde

Con `baseURL: '/api'`, la request deve uscire verso `http://localhost:5173/api/auth/login`.[^6_3]

Qui il failure più comune in WinDeploy dev è il **proxy Vite puntato al target sbagliato**: deve puntare al virtual host backend `windeploy.local.api` (non `windeploy.local`).[^6_3]

### Verifica rapida (30 sec)

Apri DevTools → Network → clic “Accedi” e guarda:

- URL chiamato: deve essere `/api/auth/login`
- Status: 404/502/ERR_CONNECTION_REFUSED?
- Response: HTML del frontend (sbagliato) o JSON Laravel (giusto)?[^6_3]

### Fix probabile: vite.config.js proxy

Nel tuo `vite.config.js` la proxy `/api` deve avere `target: 'http://windeploy.local.api'` (come indicato nella guida), altrimenti le chiamate finiscono sul frontend invece che su Laravel.[^6_3]

## Una cosa importante

La mia correzione precedente “togli lo slash” **non si applica** al tuo caso, perché tu hai `baseURL: '/api'` (con slash). In quel setup, `client.post('/auth/login')` è corretto.[^6_1]

Dimmi cosa vedi in Network per la chiamata `POST /api/auth/login` (status + response) e incollami il blocco `server.proxy` del tuo `vite.config.js`: con quelli ti dico esattamente la patch.[^6_3]

<div align="center">⁂</div>

[^6_1]: 0013-SetupprogettoReact.md

[^6_2]: 0014-PaginaLogin.md

[^6_3]: 0006-viteconfigjs.md

---

# image.jpg

Adesso **sì**, la pagina è sostanzialmente a posto: Tailwind sta funzionando, la card c’è, icone/input/bottone sono renderizzati correttamente.[^7_1]

Restano però 2 dettagli visivi evidenti nello screenshot:

## 1) Card non centrata (sta a sinistra)

Nel codice che ti ho dato la card dovrebbe essere centrata (`justify-center`), quindi se la vedi “incollata” a sinistra è quasi sempre perché **un container padre** (o un layout globale) sta imponendo un `display:flex`/`justify-start`, oppure hai qualche wrapper tipo sidebar/layout applicato anche alla login.[^7_2][^7_1]

Checklist rapida:

- Verifica che la route `/login` NON sia dentro `MainLayout` (quello con sidebar). Nel router scaffold, `/login` è “pubblica” e fuori dai gruppi con layout protetto.[^7_2]
- In DevTools, ispeziona il div più esterno della login: deve avere `min-h-screen` e il wrapper centrale `flex ... items-center justify-center`. Se uno di questi manca, stai renderizzando un componente diverso o hai modificato classi.[^7_1]

Se mi incolli il tuo `AppRouter.jsx` (solo la parte `Route path="login" ...` e i wrapper `MainLayout/PrivateRoute`) ti dico subito dov’è l’inghippo.[^7_2]

## 2) Sfondo “mezzo blu, mezzo scuro”

Questo effetto succede quando lo sfondo della Login non copre tutta la viewport oppure sotto c’è il background globale scuro di `App.jsx` (`bg-slate-950`). Nel tuo scaffold `App` ha un wrapper con background scuro globale.[^7_2]

Soluzioni (scegline una):

- **A)** assicurati che il tuo componente Login sia il root visuale (copra tutto) con `min-h-screen w-full` (è già così nel file proposto).[^7_1]
- **B)** rimuovi/evita lo sfondo globale in `App.jsx` e lascialo ai singoli layout/pagine (più flessibile). Nel tuo scaffold `App.jsx` mette `bg-slate-950 text-slate-50` sul wrapper principale.[^7_2]

## 3) Login API

Nello screenshot non vedo l’alert rosso, quindi magari la chiamata ora va; ricorda che con il tuo `client.js` (`baseURL: '/api'`) la chiamata corretta è:

- `client.post('/auth/login', {...})` → `/api/auth/login`.[^7_2]

Vuoi che ti indichi la patch “pulita” per centrare la card al 100% indipendentemente dal wrapper globale (senza toccare `App.jsx`)? Incollami il tuo `src/App.jsx` e `src/router/AppRouter.jsx` e te la preparo.
<span style="display:none">[^7_3]</span>

<div align="center">⁂</div>

[^7_1]: 0014-PaginaLogin.md

[^7_2]: 0013-SetupprogettoReact.md

[^7_3]: image.jpg

---

# import React from 'react';

import AppRouter from './router/AppRouter';

function App() {
  return (
    <div className="min-h-screen bg-slate-950 text-slate-50">
      <AppRouter />
    </div>
  );
}

export default App;

Il tuo `App.jsx` è coerente con lo scaffold (wrapper globale `min-h-screen bg-slate-950 text-slate-50`).[^8_1]
Però quel wrapper **può causare esattamente** l’effetto che vedi (sfondo “mezzo gradiente e mezzo scuro” e pagina non centrata) se la Login viene renderizzata _dentro_ un layout/container che non occupa tutta la viewport (es. `MainLayout` con sidebar, oppure un wrapper con `max-w-*`).[^8_1]

## Fix consigliato (pulito): Login fuori dal layout

Assicurati che `/login` sia una route “pubblica” e **non** dentro `MainLayout`. Nello scaffold WinDeploy `/login` sta fuori dai gruppi protetti/layout.[^8_1]

Esempio (concetto) in `src/router/AppRouter.jsx`:

```
- `Route path="/login" element={<LoginPage />}` **fuori** da `Route element={<MainLayout />}`.[^8_1]
```

Se incolli il tuo `AppRouter.jsx`, ti dico esattamente dove spostare/come annidare le route.

## Fix “anti-wrapper” (robusto): sfondo fixed nella Login

Se vuoi che la Login copra SEMPRE tutto lo schermo anche se qualcuno la mette per sbaglio dentro un container, cambia l’outer wrapper della Login così:

**Nel file** `src/pages/Login/index.jsx` (solo la prima `div`):

```diff
- <div className="min-h-screen w-full bg-[linear-gradient(135deg,#1E3A5F_0%,#2E75B6_100%)]">
+ <div className="fixed inset-0 w-full bg-[linear-gradient(135deg,#1E3A5F_0%,#2E75B6_100%)]">
```

e poi il wrapper interno:

```diff
- <div className="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-10">
+ <div className="mx-auto grid min-h-screen place-items-center px-4 py-10">
```

Così non dipendi dal `bg-slate-950` di `App.jsx` e non “tagli” il gradiente.[^8_1]

## Trade-off (da tecnico)

- Tenere `bg-slate-950` in `App.jsx` è comodo e uniforme, ma rende più facile avere interferenze visive con pagine “full-screen” (login, error pages).[^8_1]
- Spostare i background nei layout/pagine è più pulito a lungo termine, ma richiede un minimo di disciplina sulle classi.[^8_1]

Incollami `src/router/AppRouter.jsx` (intero) e ti dico con certezza se `/login` sta finendo dentro `MainLayout` (è la causa più probabile del gradiente “a colonna”).

<div align="center">⁂</div>

[^8_1]: 0013-SetupprogettoReact.md

---

# import React from 'react';

import { Routes, Route, Navigate } from 'react-router-dom';
import PrivateRoute from './PrivateRoute';
import MainLayout from '../components/layout/MainLayout';

// Pagine
import LoginPage from '../pages/Login';
import DashboardPage from '../pages/Dashboard/DashboardPage';
import WizardsListPage from '../pages/Wizards/WizardsListPage';
import WizardBuilderPage from '../pages/Wizards/WizardBuilderPage';
import WizardMonitorPage from '../pages/Wizards/WizardMonitorPage';
import TemplatesPage from '../pages/Templates/TemplatesPage';
import SoftwarePage from '../pages/Software/SoftwarePage';
import ReportsPage from '../pages/Reports/ReportsPage';
import UsersPage from '../pages/Users/UsersPage';
import NotFoundPage from '../pages/NotFound/NotFoundPage';

function AppRouter() {
  return (
    <Routes>
      {/_ Pubblico _/}
      <Route path="/login" element={<LoginPage />} />

{/_ Area protetta con layout _/}
      <Route element={<PrivateRoute allowedRoles={['admin', 'tecnico', 'viewer']} />}>
        <Route element={<MainLayout />}>
          {/_ Redirect root -> /dashboard _/}
          <Route index element={<Navigate to="/dashboard" replace />} />

{/_ /dashboard → admin + tecnico + viewer _/}
          <Route path="/dashboard" element={<DashboardPage />} />

{/_ /reports → tutti (admin + tecnico + viewer) _/}
          <Route path="/reports" element={<ReportsPage />} />
        </Route>
      </Route>

{/_ /wizards, /wizards/new, /wizards/:id/monitor → tecnico + admin _/}
      <Route element={<PrivateRoute allowedRoles={['admin', 'tecnico']} />}>
        <Route element={<MainLayout />}>
          <Route path="/wizards" element={<WizardsListPage />} />
          <Route path="/wizards/new" element={<WizardBuilderPage />} />
          <Route path="/wizards/:id/monitor" element={<WizardMonitorPage />} />

{/_ /templates → tecnico + admin _/}
          <Route path="/templates" element={<TemplatesPage />} />
        </Route>
      </Route>

{/_ /software → solo admin _/}
      <Route element={<PrivateRoute allowedRoles={['admin']} />}>
        <Route element={<MainLayout />}>
          <Route path="/software" element={<SoftwarePage />} />
        </Route>
      </Route>

{/_ /users → solo admin _/}
      <Route element={<PrivateRoute allowedRoles={['admin']} />}>
        <Route element={<MainLayout />}>
          <Route path="/users" element={<UsersPage />} />
        </Route>
      </Route>

{/_ 404 _/}
      <Route path="\*" element={<NotFoundPage />} />
    </Routes>
  );
}

export default AppRouter;

Il tuo `AppRouter.jsx` è corretto: `/login` è **fuori** da `PrivateRoute` e `MainLayout`, quindi la sidebar/layout non c’entra.[^9_1]

Quello che vedi (gradiente solo “a colonna” a sinistra + resto scuro) quasi sicuramente dipende da **CSS globale del template Vite** rimasto in `src/index.css` (tipicamente regole su `#root` tipo `max-width`, `margin`, `padding`) che restringono la larghezza dell’app; dentro quella “colonna” la tua Login rende correttamente, fuori vedi lo sfondo di `App.jsx` (`bg-slate-950`).[^9_1]

## Fix definitivo (index.css)

Apri `src/index.css` e assicurati che:

1. ci siano le direttive Tailwind (come da setup).[^9_1]
2. **non** ci siano stili tipo `#root { max-width: ...; margin: 0 auto; padding: ... }` (quelli vanno rimossi).
3. aggiungi queste regole minime per far occupare tutta la viewport:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

html,
body,
#root {
  height: 100%;
}

body {
  margin: 0;
}

#root {
  width: 100%;
}
```

Poi riavvia Vite (`CTRL+C` → `npm run dev`).[^9_1]

## “Bretella” extra (se vuoi renderla immune al CSS globale)

Nel tuo `src/pages/Login/index.jsx`, cambia il wrapper esterno così:

```diff
- <div className="min-h-screen w-full bg-[linear-gradient(135deg,#1E3A5F_0%,#2E75B6_100%)]">
+ <div className="fixed inset-0 w-full bg-[linear-gradient(135deg,#1E3A5F_0%,#2E75B6_100%)]">
```

Questo forza lo sfondo a coprire tutto anche se qualcuno in futuro rimette `#root` con `max-width`.[^9_1]

Se mi incolli il tuo `src/index.css` attuale, ti dico esattamente quali righe eliminare (di solito sono 10–20 righe “default Vite”).

<div align="center">⁂</div>

[^9_1]: 0013-SetupprogettoReact.md

---

# @tailwind base;

@tailwind components;
@tailwind utilities;

:root {
  font-family: system-ui, Avenir, Helvetica, Arial, sans-serif;
  line-height: 1.5;
  font-weight: 400;

color-scheme: light dark;
  color: rgba(255, 255, 255, 0.87);
  background-color: \#242424;

font-synthesis: none;
  text-rendering: optimizeLegibility;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a {
  font-weight: 500;
  color: \#646cff;
  text-decoration: inherit;
}
a:hover {
  color: \#535bf2;
}

body {
  margin: 0;
  display: flex;
  place-items: center;
  min-width: 320px;
  min-height: 100vh;
}

h1 {
  font-size: 3.2em;
  line-height: 1.1;
}

button {
  border-radius: 8px;
  border: 1px solid transparent;
  padding: 0.6em 1.2em;
  font-size: 1em;
  font-weight: 500;
  font-family: inherit;
  background-color: \#1a1a1a;
  cursor: pointer;
  transition: border-color 0.25s;
}
button:hover {
  border-color: \#646cff;
}
button:focus,
button:focus-visible {
  outline: 4px auto -webkit-focus-ring-color;
}

@media (prefers-color-scheme: light) {
  :root {
    color: \#213547;
    background-color: \#ffffff;
  }
  a:hover {
    color: \#747bff;
  }
  button {
    background-color: \#f9f9f9;
  }
}

Ecco il motivo: questo `index.css` è ancora quello “default Vite” e **ti sta sabotando il layout** (soprattutto `body { display:flex; place-items:center; min-height:100vh; }` + `:root { color-scheme: light dark; background-color... }` + gli stili globali su `button`). Risultato: la tua app viene “impacchettata” e il gradiente non occupa tutto.[^10_1]

## Fix (consigliato): pulisci index.css

Sostituisci **completamente** `src/index.css` con questo:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

html,
body,
#root {
  height: 100%;
}

body {
  margin: 0;
}
```

Stop/start Vite dopo la modifica (`CTRL+C` → `npm run dev`).[^10_1]

## Perché funziona

- Rimuovi `display:flex` dal `body` che stava centrando/restringendo tutto.
- Rimuovi gli stili globali su `button` che possono alterare i tuoi componenti Tailwind.
- Lasci a Tailwind e ai tuoi componenti il controllo dello stile (come previsto nello scaffold).[^10_1]

## Nota “avvocato del diavolo” (rischio)

Quegli stili globali del template Vite (specie `button { ... }`) possono causare bug UI subdoli in tutto il progetto (bottoni che non rispettano le classi, focus strani, spacing incoerente). Meglio eliminarli ora.[^10_1]

Se dopo questa pulizia la login non è ancora perfettamente full-screen, dimmelo e ti faccio anche la patch “immune” con `fixed inset-0` sul wrapper della Login.

<div align="center">⁂</div>

[^10_1]: 0013-SetupprogettoReact.md
