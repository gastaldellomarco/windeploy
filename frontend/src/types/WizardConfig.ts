// frontend/src/types/WizardConfig.ts
// Contratto TypeScript condiviso per la configurazione del Wizard WinDeploy.
// Speculare a docs/schemas/wizard-config.schema.json v1.0
// Convenzione chiavi: snake_case (coerente con PHP e Python).

// ─── Tipi primitivi ───────────────────────────────────────────────────────────

export type SoftwareType = "winget" | "exe" | "msi";

export type PowerPlanType =
  | "balanced"
  | "high_performance"
  | "power_saver"
  | "custom";

export type WindowsUpdatePolicy = "auto" | "download_only" | "manual";

// ─── Sotto-interfacce ─────────────────────────────────────────────────────────

export interface AdminUser {
  username: string;
  /** Password in chiaro. Presente SOLO in transito (form → POST). Il backend la cifra e rimuove. */
  password?: string;
  /** Password cifrata AES-256-GCM. Presente nel payload restituito dall'agent auth endpoint. */
  password_encrypted?: string;
  /** Se true, l'agent rimuove l'account OOBE/Microsoft al termine dell'installazione. */
  remove_setup_account: boolean;
}

export interface SoftwareItem {
  /** FK → tabella software_library */
  id: number;
  /** Identificatore per winget install --id */
  winget_id: string;
  /** Nome visualizzabile (solo display) */
  name: string;
  /** Tipo di installazione */
  type: SoftwareType;
  /** URL di download dal server WinDeploy. null se type='winget' */
  download_url: string | null;
}

export interface BloatwareItem {
  /** Nome pacchetto AppX o winget ID (es. 'Microsoft.XboxApp') */
  package_name: string;
  /** Nome leggibile mostrato all'utente */
  display_name: string;
  /** true = preselezionata per rimozione */
  selected: boolean;
}

export interface PowerPlan {
  type: PowerPlanType;
  /** Timeout schermo (minuti, AC). null = mai. Solo per type='custom'. */
  screen_timeout_ac: number | null;
  /** Timeout sospensione (minuti, AC). null = mai. Solo per type='custom'. */
  sleep_timeout_ac: number | null;
  /** % frequenza CPU minima. Solo per type='custom'. */
  cpu_min_percent: number;
  /** % frequenza CPU massima. Solo per type='custom'. */
  cpu_max_percent: number;
}

export interface WifiConfig {
  ssid: string;
  /** In chiaro solo in transito. Backend cifra immediatamente. */
  password?: string;
  /** Cifrata AES-256-GCM. Presente nel payload per l'agent. */
  password_encrypted?: string;
}

export interface ExtrasConfig {
  /** Fuso orario IANA (es. 'Europe/Rome') */
  timezone?: string;
  /** Lingua interfaccia (es. 'it-IT') */
  language?: string;
  /** Layout tastiera (es. 'it-IT') */
  keyboard_layout?: string;
  /** URL relativo wallpaper su server WinDeploy. null = nessun wallpaper. */
  wallpaper_url?: string | null;
  /** Configurazione WiFi. null = nessuna. */
  wifi?: WifiConfig | null;
  /** Policy Windows Update */
  windows_update?: WindowsUpdatePolicy;
}

// ─── Interfaccia principale ───────────────────────────────────────────────────

export interface WizardConfig {
  /** Versione schema. Attualmente "1.0". */
  version: "1.0";
  /** Nome del PC di destinazione (max 15 char, alfanumerici e trattini). */
  pc_name: string;
  /** Credenziali dell'utente amministratore locale. */
  admin_user: AdminUser;
  /** Lista software da installare. */
  software: SoftwareItem[];
  /** Lista bloatware pre-selezionata per rimozione. */
  bloatware: BloatwareItem[];
  /** Configurazione piano energetico. */
  power_plan: PowerPlan;
  /** Configurazioni opzionali aggiuntive. */
  extras?: ExtrasConfig | null;
}

// ─── Payload POST verso /api/wizards ─────────────────────────────────────────

/** Shape del body inviato dal frontend al backend per creare un wizard. */
export interface CreateWizardPayload {
  nome: string;
  template_id?: number | null;
  note_interne?: string | null;
  configurazione: WizardConfig;
  /** File immagine wallpaper. Inviato solo se presente (multipart/form-data). */
  wallpaper?: File;
}

// ─── Type Guards ──────────────────────────────────────────────────────────────

export function isWizardConfig(value: unknown): value is WizardConfig {
  if (typeof value !== "object" || value === null) return false;
  const v = value as Record<string, unknown>;
  return (
    v.version === "1.0" &&
    typeof v.pc_name === "string" &&
    typeof v.admin_user === "object" &&
    Array.isArray(v.software) &&
    Array.isArray(v.bloatware) &&
    typeof v.power_plan === "object"
  );
}

export function isSoftwareItem(value: unknown): value is SoftwareItem {
  if (typeof value !== "object" || value === null) return false;
  const v = value as Record<string, unknown>;
  return (
    typeof v.id === "number" &&
    typeof v.winget_id === "string" &&
    typeof v.name === "string" &&
    (v.type === "winget" || v.type === "exe" || v.type === "msi")
  );
}

// ─── Valori di default ────────────────────────────────────────────────────────

/** Oggetto di default per inizializzare lo stato del Wizard Builder.
 *  Usato in buildEmptyWizard() come struttura base. */
export const WIZARD_CONFIG_DEFAULT: WizardConfig = {
  version: "1.0",
  pc_name: "",
  admin_user: {
    username: "",
    password: "",
    remove_setup_account: false,
  },
  software: [],
  bloatware: [],
  power_plan: {
    type: "balanced",
    screen_timeout_ac: 15,
    sleep_timeout_ac: 30,
    cpu_min_percent: 5,
    cpu_max_percent: 100,
  },
  extras: {
    timezone: "Europe/Rome",
    language: "it-IT",
    keyboard_layout: "it-IT",
    wallpaper_url: null,
    wifi: null,
    windows_update: "auto",
  },
};
