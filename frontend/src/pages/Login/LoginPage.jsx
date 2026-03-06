// frontend/src/pages/Login/LoginPage.jsx
import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Eye, EyeOff, Loader2, Lock, Mail } from 'lucide-react';
import { useAuthStore } from '../../store/authStore';

function InputField({
  id,
  name,
  label,
  type = 'text',
  placeholder,
  value,
  onChange,
  disabled = false,
  autoComplete,
  leftIcon: LeftIcon,
  rightSlot,
}) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">
        {label}
      </label>

      <div className="relative flex items-center rounded-xl border border-slate-200 bg-white shadow-sm transition focus-within:ring-2 focus-within:ring-sky-500/40">
        {LeftIcon ? (
          <div className="pointer-events-none absolute left-3 flex items-center">
            <LeftIcon className="h-5 w-5 text-slate-400" />
          </div>
        ) : null}

        <input
          id={id}
          name={name}
          type={type}
          className={[
            'w-full rounded-xl bg-transparent px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none',
            LeftIcon ? 'pl-11' : '',
            rightSlot ? 'pr-11' : '',
          ].join(' ')}
          placeholder={placeholder}
          value={value}
          onChange={onChange}
          disabled={disabled}
          autoComplete={autoComplete}
        />

        {rightSlot ? (
          <div className="absolute right-2 flex items-center">{rightSlot}</div>
        ) : null}
      </div>
    </div>
  );
}

function flattenValidationErrors(errorsObject) {
  if (!errorsObject || typeof errorsObject !== 'object') {
    return 'Dati non validi';
  }

  const messages = Object.values(errorsObject)
    .flat()
    .filter(Boolean);

  return messages.length > 0 ? messages.join(' ') : 'Dati non validi';
}

export default function LoginPage() {
  const navigate = useNavigate();

  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const login = useAuthStore((state) => state.login);

  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [form, setForm] = useState({
    email: '',
    password: '',
  });

  const canSubmit = useMemo(() => {
    return form.email.trim().length > 0 && form.password.trim().length > 0 && !isLoading;
  }, [form.email, form.password, isLoading]);

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/dashboard', { replace: true });
    }
  }, [isAuthenticated, navigate]);

  function handleChange(event) {
    const { name, value } = event.target;

    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));

    if (errorMessage) {
      setErrorMessage('');
    }
  }

  function mapLoginError(error) {
    const status = error?.response?.status;
    const responseData = error?.response?.data;

    if (status === 401) {
      return 'Credenziali non valide';
    }

    if (status === 422) {
      return flattenValidationErrors(responseData?.errors);
    }

    return 'Errore di rete, riprova';
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!canSubmit) {
      return;
    }

    setIsLoading(true);
    setErrorMessage('');

    try {
      await login(form.email.trim(), form.password);
      navigate('/dashboard', { replace: true });
    } catch (error) {
      setErrorMessage(mapLoginError(error));
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen w-full items-center justify-center bg-gradient-to-br from-[#1E3A5F] to-[#2E75B6] p-6">
      <div className="w-full max-w-md">
        <div className="rounded-2xl border border-white/40 bg-white px-6 py-7 shadow-2xl backdrop-blur sm:px-8 sm:py-8">
          <div>
            <div className="text-2xl font-semibold text-slate-900">⚙️ WinDeploy</div>
            <div className="mt-1 text-sm text-slate-600">IT Provisioning Platform</div>
          </div>

          <div className="mt-6">
            {errorMessage ? (
              <div
                className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
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
                value={form.email}
                onChange={handleChange}
                disabled={isLoading}
                autoComplete="email"
                leftIcon={Mail}
              />

              <InputField
                id="password"
                name="password"
                label="Password"
                type={showPassword ? 'text' : 'password'}
                placeholder="La tua password"
                value={form.password}
                onChange={handleChange}
                disabled={isLoading}
                autoComplete="current-password"
                leftIcon={Lock}
                rightSlot={
                  <button
                    type="button"
                    onClick={() => setShowPassword((prev) => !prev)}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label={showPassword ? 'Nascondi password' : 'Mostra password'}
                    disabled={isLoading}
                  >
                    {showPassword ? (
                      <EyeOff className="h-5 w-5" />
                    ) : (
                      <Eye className="h-5 w-5" />
                    )}
                  </button>
                }
              />

              <button
                type="submit"
                disabled={!canSubmit}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Accesso in corso...
                  </>
                ) : (
                  'Accedi'
                )}
              </button>
            </form>
          </div>
        </div>

        <div className="mt-4 text-center text-xs text-white/80">
          © {new Date().getFullYear()} WinDeploy
        </div>
      </div>
    </div>
  );
}
