// frontend/src/utils/validateWizardConfig.ts
// Validazione lato frontend del payload WizardConfig prima del POST /api/wizards.
// Le regole sono speculari a WizardStoreRequest.php — ogni modifica a una
// deve essere replicata nell'altra.

import type { WizardConfig } from "../types/WizardConfig";

export interface ValidationResult {
  valid: boolean;
  /** Mappa campo → lista di messaggi di errore. */
  errors: Record<string, string[]>;
}

/** Aggiunge un errore al record degli errori. */
function addError(
  errors: Record<string, string[]>,
  field: string,
  message: string
): void {
  if (!errors[field]) errors[field] = [];
  errors[field].push(message);
}

/** Valida il nome PC secondo le regole Windows + backend. */
function validatePcName(
  name: string,
  errors: Record<string, string[]>
): void {
  const field = "configurazione.pc_name";
  if (!name || name.trim().length === 0) {
    addError(errors, field, "Il nome PC è obbligatorio.");
    return;
  }
  if (name.length > 15) {
    addError(errors, field, "Il nome PC non può superare 15 caratteri (limite Windows).");
  }
  if (!/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?$/.test(name)) {
    addError(
      errors,
      field,
      "Il nome PC può contenere solo lettere, numeri e trattini, e non può iniziare o finire con un trattino."
    );
  }
}

/** Valida la sezione admin_user. */
function validateAdminUser(
  adminUser: WizardConfig["admin_user"],
  errors: Record<string, string[]>
): void {
  if (!adminUser) {
    addError(errors, "configurazione.admin_user", "La sezione utente admin è obbligatoria.");
    return;
  }

  const username = adminUser.username?.trim() ?? "";
  if (!username) {
    addError(errors, "configurazione.admin_user.username", "Lo username dell'admin è obbligatorio.");
  } else {
    if (username.length > 50) {
      addError(errors, "configurazione.admin_user.username", "Lo username non può superare 50 caratteri.");
    }
    if (!/^[a-zA-Z0-9._-]+$/.test(username)) {
      addError(
        errors,
        "configurazione.admin_user.username",
        "Lo username può contenere solo lettere, numeri, punti, underscore e trattini (nessuno spazio)."
      );
    }
  }

  const password = adminUser.password ?? "";
  if (!password) {
    addError(errors, "configurazione.admin_user.password", "La password è obbligatoria.");
  } else {
    if (password.length < 6) {
      addError(errors, "configurazione.admin_user.password", "La password deve contenere almeno 6 caratteri.");
    }
    if (password.length > 128) {
      addError(errors, "configurazione.admin_user.password", "La password non può superare 128 caratteri.");
    }
  }
}

/** Valida ogni elemento dell'array software. */
function validateSoftware(
  software: WizardConfig["software"],
  errors: Record<string, string[]>
): void {
  if (!Array.isArray(software)) {
    addError(errors, "configurazione.software", "La lista software deve essere un array.");
    return;
  }
  software.forEach((sw, idx) => {
    const base = `configurazione.software.${idx}`;
    if (!sw.id || typeof sw.id !== "number") {
      addError(errors, `${base}.id`, "Ogni software deve avere un ID numerico valido.");
    }
    if (!sw.winget_id?.trim()) {
      addError(errors, `${base}.winget_id`, "Ogni software deve avere un identificatore winget.");
    }
    if (!sw.name?.trim()) {
      addError(errors, `${base}.name`, "Ogni software deve avere un nome.");
    }
    if (!["winget", "exe", "msi"].includes(sw.type)) {
      addError(errors, `${base}.type`, "Il tipo deve essere: winget, exe o msi.");
    }
    if (sw.download_url !== null && sw.download_url !== undefined) {
      try {
        new URL(sw.download_url);
      } catch {
        addError(errors, `${base}.download_url`, "L'URL di download non è un URL valido.");
      }
    }
  });
}

/** Valida ogni elemento dell'array bloatware. */
function validateBloatware(
  bloatware: WizardConfig["bloatware"],
  errors: Record<string, string[]>
): void {
  if (!Array.isArray(bloatware)) {
    addError(errors, "configurazione.bloatware", "La lista bloatware deve essere un array.");
    return;
  }
  bloatware.forEach((item, idx) => {
    const base = `configurazione.bloatware.${idx}`;
    if (!item.package_name?.trim()) {
      addError(errors, `${base}.package_name`, "Ogni voce bloatware deve avere un nome pacchetto.");
    }
    if (!item.display_name?.trim()) {
      addError(errors, `${base}.display_name`, "Ogni voce bloatware deve avere un nome visualizzabile.");
    }
    if (typeof item.selected !== "boolean") {
      addError(errors, `${base}.selected`, "Lo stato di selezione del bloatware deve essere true o false.");
    }
  });
}

/** Valida la sezione power_plan. */
function validatePowerPlan(
  powerPlan: WizardConfig["power_plan"],
  errors: Record<string, string[]>
): void {
  if (!powerPlan) {
    addError(errors, "configurazione.power_plan", "La configurazione del power plan è obbligatoria.");
    return;
  }
  const validTypes = ["balanced", "high_performance", "power_saver", "custom"];
  if (!validTypes.includes(powerPlan.type)) {
    addError(
      errors,
      "configurazione.power_plan.type",
      "Il tipo di power plan deve essere: balanced, high_performance, power_saver o custom."
    );
  }
  if (powerPlan.type === "custom") {
    if (powerPlan.screen_timeout_ac !== null && powerPlan.screen_timeout_ac !== undefined) {
      if (powerPlan.screen_timeout_ac < 1 || powerPlan.screen_timeout_ac > 60) {
        addError(errors, "configurazione.power_plan.screen_timeout_ac", "Il timeout schermo deve essere tra 1 e 60 minuti.");
      }
    }
    if (powerPlan.sleep_timeout_ac !== null && powerPlan.sleep_timeout_ac !== undefined) {
      if (powerPlan.sleep_timeout_ac < 1 || powerPlan.sleep_timeout_ac > 120) {
        addError(errors, "configurazione.power_plan.sleep_timeout_ac", "Il timeout sospensione deve essere tra 1 e 120 minuti.");
      }
    }
    if (powerPlan.cpu_min_percent < 0 || powerPlan.cpu_min_percent > 100) {
      addError(errors, "configurazione.power_plan.cpu_min_percent", "La percentuale CPU minima deve essere tra 0 e 100.");
    }
    if (powerPlan.cpu_max_percent < 0 || powerPlan.cpu_max_percent > 100) {
      addError(errors, "configurazione.power_plan.cpu_max_percent", "La percentuale CPU massima deve essere tra 0 e 100.");
    }
    if (powerPlan.cpu_min_percent > powerPlan.cpu_max_percent) {
      addError(
        errors,
        "configurazione.power_plan.cpu_min_percent",
        "La percentuale CPU minima non può superare quella massima."
      );
    }
  }
}

/** Valida la sezione extras (opzionale). */
function validateExtras(
  extras: WizardConfig["extras"],
  errors: Record<string, string[]>
): void {
  if (!extras) return; // Extras è nullable, assenza è valida

  const langRegex = /^[a-z]{2}-[A-Z]{2}$/;

  if (extras.language && !langRegex.test(extras.language)) {
    addError(errors, "configurazione.extras.language", "La lingua deve essere nel formato es. it-IT.");
  }
  if (extras.keyboard_layout && !langRegex.test(extras.keyboard_layout)) {
    addError(errors, "configurazione.extras.keyboard_layout", "Il layout tastiera deve essere nel formato es. it-IT.");
  }
  if (extras.windows_update && !["auto", "download_only", "manual"].includes(extras.windows_update)) {
    addError(errors, "configurazione.extras.windows_update", "La policy Windows Update deve essere: auto, download_only o manual.");
  }
  if (extras.wifi !== null && extras.wifi !== undefined) {
    if (!extras.wifi.ssid?.trim()) {
      addError(errors, "configurazione.extras.wifi.ssid", "L'SSID è obbligatorio quando si configura il WiFi.");
    } else if (extras.wifi.ssid.length > 32) {
      addError(errors, "configurazione.extras.wifi.ssid", "L'SSID non può superare 32 caratteri.");
    }
    if (!extras.wifi.password?.trim()) {
      addError(errors, "configurazione.extras.wifi.password", "La password WiFi è obbligatoria quando si configura il WiFi.");
    }
  }
}

/**
 * Valida il payload WizardConfig prima del POST /api/wizards.
 * Restituisce { valid: true, errors: {} } se tutto è corretto,
 * oppure { valid: false, errors: { campo: [messaggi] } } con dettaglio.
 *
 * Le regole sono speculari a WizardStoreRequest.php.
 */
export function validateWizardConfig(config: WizardConfig): ValidationResult {
  const errors: Record<string, string[]> = {};

  if (!config) {
    return { valid: false, errors: { configurazione: ["La configurazione è obbligatoria."] } };
  }

  if (config.version !== "1.0") {
    addError(errors, "configurazione.version", "Versione schema non supportata. Attesa: 1.0.");
  }

  validatePcName(config.pc_name, errors);
  validateAdminUser(config.admin_user, errors);
  validateSoftware(config.software, errors);
  validateBloatware(config.bloatware, errors);
  validatePowerPlan(config.power_plan, errors);
  validateExtras(config.extras, errors);

  return {
    valid: Object.keys(errors).length === 0,
    errors,
  };
}
