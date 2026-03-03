import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Mail, Lock, Eye, EyeOff, Loader2 } from "lucide-react";

import client from "../../api/client";
import { useAuthStore } from "../../store/authStore";

function InputField({
  id,
  name,
  label,
  type = "text",
  placeholder,
  value,
  onChange,
  disabled = false,
  autoComplete,
  leftIcon: LeftIcon,
  rightSlot,
  error,
}) {
  return (
    <div className="space-y-1.5">
      {label ? (
        <label
          htmlFor={id}
          className="block text-sm font-medium text-slate-700"
        >
          {label}
        </label>
      ) : null}

      <div
        className={[
          "relative flex items-center rounded-xl border bg-white shadow-sm",
          "transition focus-within:ring-2 focus-within:ring-sky-500/40",
          error ? "border-red-300 focus-within:ring-red-500/30" : "border-slate-200",
          disabled ? "opacity-60" : "",
        ].join(" ")}
      >
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
            "w-full rounded-xl bg-transparent px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400",
            LeftIcon ? "pl-11" : "",
            rightSlot ? "pr-11" : "",
            "focus:outline-none",
          ].join(" ")}
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

      {error ? (
        <p className="text-sm text-red-600">{error}</p>
      ) : null}
    </div>
  );
}

export default function LoginPage() {
  const navigate = useNavigate();

  const { isAuthenticated, login } = useAuthStore();

  const [isDark, setIsDark] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const [form, setForm] = useState({ email: "", password: "" });
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [errorMessage, setErrorMessage] = useState("");

  const canSubmit = useMemo(() => {
    return form.email.trim().length > 0 && form.password.trim().length > 0 && !isSubmitting;
  }, [form.email, form.password, isSubmitting]);

  useEffect(() => {
    if (isAuthenticated) navigate("/dashboard", { replace: true });
  }, [isAuthenticated, navigate]);

  useEffect(() => {
    const root = document.documentElement;
    if (isDark) root.classList.add("dark");
    else root.classList.remove("dark");
  }, [isDark]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errorMessage) setErrorMessage("");
  }

  function mapLoginError(err) {
    const status = err?.response?.status;
    const data = err?.response?.data;

    // Se il backend manda messaggi strutturati, prova a usarli senza assumere uno schema rigido.
    const serverMessage =
      (typeof data === "string" && data) ||
      data?.message ||
      data?.error ||
      null;

    if (status === 401) return "Credenziali non valide. Controlla email e password.";
    if (status === 429) return "Troppi tentativi. Attendi qualche minuto e riprova.";
    if (serverMessage) return serverMessage;

    return "Errore di rete o server non disponibile. Riprova.";
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!canSubmit) return;

    setIsSubmitting(true);
    setErrorMessage("");

    try {
      const payload = { email: form.email.trim(), password: form.password };
      const { data } = await client.post("/auth/login", payload);

      // Atteso: { token, user: { id, nome, email, ruolo } }
      if (!data?.token || !data?.user) {
        throw new Error("Invalid login response shape");
      }

      login({ token: data.token, user: data.user });
      navigate("/dashboard", { replace: true });
    } catch (err) {
      setErrorMessage(mapLoginError(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div
      className={[
        "min-h-screen w-full",
        "bg-gradient-to-br from-[#1E3A5F] to-[#2E75B6]",
        "flex items-center justify-center p-6",
      ].join(" ")}
    >
      <div className="w-full max-w-md">
        <div
          className={[
            "rounded-2xl bg-white shadow-2xl",
            "border border-white/40",
            "px-6 py-7 sm:px-8 sm:py-8",
            "backdrop-blur",
            // Dark mode “opzionale”: card leggermente scura, mantenendo il contrasto
            "dark:bg-slate-900 dark:border-slate-800",
          ].join(" ")}
        >
          <div className="flex items-start justify-between gap-4">
            <div>
              <div className="text-2xl font-semibold text-slate-900 dark:text-white">
                ⚙️ WinDeploy
              </div>
              <div className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                IT Provisioning Platform
              </div>
            </div>

            <button
              type="button"
              onClick={() => setIsDark((v) => !v)}
              className={[
                "rounded-xl border px-3 py-2 text-xs font-medium",
                "border-slate-200 text-slate-700 hover:bg-slate-50",
                "dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800",
              ].join(" ")}
              aria-label="Toggle dark mode"
            >
              {isDark ? "Light" : "Dark"}
            </button>
          </div>

          <div className="mt-6">
            {errorMessage ? (
              <div
                className={[
                  "mb-4 rounded-xl border px-4 py-3 text-sm",
                  "border-red-200 bg-red-50 text-red-700",
                  "dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200",
                ].join(" ")}
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
                disabled={isSubmitting}
                autoComplete="email"
                leftIcon={Mail}
              />

              <InputField
                id="password"
                name="password"
                label="Password"
                type={showPassword ? "text" : "password"}
                placeholder="La tua password"
                value={form.password}
                onChange={handleChange}
                disabled={isSubmitting}
                autoComplete="current-password"
                leftIcon={Lock}
                rightSlot={
                  <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    className={[
                      "inline-flex h-9 w-9 items-center justify-center rounded-lg",
                      "text-slate-500 hover:bg-slate-100 hover:text-slate-700",
                      "dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white",
                      "transition",
                    ].join(" ")}
                    aria-label={showPassword ? "Hide password" : "Show password"}
                    disabled={isSubmitting}
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
                className={[
                  "w-full rounded-xl px-4 py-2.5 text-sm font-semibold",
                  "inline-flex items-center justify-center gap-2",
                  "bg-sky-600 text-white hover:bg-sky-500",
                  "disabled:opacity-60 disabled:cursor-not-allowed",
                  "focus:outline-none focus:ring-2 focus:ring-sky-500/40",
                  "transition",
                ].join(" ")}
              >
                {isSubmitting ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Accesso in corso...
                  </>
                ) : (
                  "Accedi"
                )}
              </button>
            </form>

            <div className="mt-5 text-xs text-slate-500 dark:text-slate-400">
              Consiglio sicurezza: evita password riutilizzate e usa MFA dove possibile.
            </div>
          </div>
        </div>

        <div className="mt-4 text-center text-xs text-white/80">
          © {new Date().getFullYear()} WinDeploy
        </div>
      </div>
    </div>
  );
}
