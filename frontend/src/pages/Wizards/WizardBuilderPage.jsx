import React, { useEffect, useMemo, useReducer, useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { ArrowLeft, ArrowRight, Save, Wand2 } from 'lucide-react';
import client from '../../api/client';
import StepProgress from '../../components/StepProgress';

import Step1Info from './Steps/Step1Info';
import Step2NomePC from './Steps/Step2NomePC';
import Step3Utente from './Steps/Step3Utente';
import Step4Software from './Steps/Step4Software';
import Step5Bloatware from './Steps/Step5Bloatware';
import { BLOATWARE_LIST } from '../../data/bloatware';
import Step6PowerPlan from './Steps/Step6PowerPlan';
import Step7Extra from './Steps/Step7Extra';
import Step8Recap from './Steps/Step8Recap';

const LS_KEY = 'windeploy:wizardBuilder:draft:v1';

const STEPS = [
  { key: 'step1', label: 'Info base' },
  { key: 'step2', label: 'Nome PC' },
  { key: 'step3', label: 'Utente admin locale' },
  { key: 'step4', label: 'Software' },
  { key: 'step5', label: 'Bloatware' },
  { key: 'step6', label: 'Power Plan' },
  { key: 'step7', label: 'Extra' },
  { key: 'step8', label: 'Recap & genera' },
];

function buildEmptyWizard() {
  return {
    meta: {
      wizardName: '',
      templateId: '',
      internalNotes: '',
    },
    pcName: {
      raw: '',
      useDepartmentVariable: false,
      department: 'IT',
      formattedPreview: '',
    },
    localAdmin: {
      username: '',
      password: '',
      passwordConfirm: '',
      removeMicrosoftSetupAccount: false,
    },
    software: {
      selectedIds: [],
    },
    bloatware: {
      preselected: [],
    },
    powerPlan: {
      preset: 'balanced',
      manual: false,
      screenTimeoutMin: 15,
      sleepTimeoutMin: 30,
      cpuMinPercent: 5,
      cpuMaxPercent: 100,
      sleepNever: false,
    },
    extras: {
      timezone: 'Europe/Rome',
      language: 'it-IT',
      keyboardLayout: 'it-IT',
      wallpaperFile: null,
      wallpaperPreviewUrl: '',
      wifiEnabled: false,
      wifiSsid: '',
      wifiPassword: '',
      windowsUpdatePolicy: 'auto',
    },
  };
}

function safeParse(json) {
  try {
    return JSON.parse(json);
  } catch {
    return null;
  }
}

function reducer(state, action) {
  switch (action.type) {
    case 'LOAD_DRAFT':
      return action.payload || state;
    case 'UPDATE':
      return {
        ...state,
        ...action.payload,
      };
    case 'PATCH_PATH': {
      const { path, value } = action.payload;
      const next = structuredClone(state);

      let ref = next;
      for (let i = 0; i < path.length - 1; i += 1) {
        ref = ref[path[i]];
      }
      ref[path[path.length - 1]] = value;
      return next;
    }
    case 'RESET':
      return buildEmptyWizard();
    default:
      return state;
  }
}

function validatePcName(name) {
  const trimmed = String(name || '').trim();
  if (!trimmed) return { ok: false, message: 'Il nome PC è obbligatorio.' };
  if (trimmed.length > 15) return { ok: false, message: 'Max 15 caratteri (limite Windows).' };
  if (!/^[A-Za-z0-9-]+$/.test(trimmed)) return { ok: false, message: 'Solo lettere, numeri e trattini.' };
  if (/^-|-$/.test(trimmed)) return { ok: false, message: 'Non può iniziare o finire con un trattino.' };
  return { ok: true, message: '' };
}

function validateUsername(username) {
  const u = String(username || '').trim();
  if (!u) return { ok: false, message: 'Username obbligatorio.' };
  if (u.length > 50) return { ok: false, message: 'Max 50 caratteri.' };
  if (!/^[A-Za-z0-9._-]+$/.test(u)) return { ok: false, message: 'No spazi o caratteri speciali.' };
  return { ok: true, message: '' };
}

function passwordStrength(pw) {
  const p = String(pw || '');
  let score = 0;
  if (p.length >= 8) score += 1;
  if (/[A-Z]/.test(p)) score += 1;
  if (/[a-z]/.test(p)) score += 1;
  if (/[0-9]/.test(p)) score += 1;
  if (/[^A-Za-z0-9]/.test(p)) score += 1;
  return score; // 0..5
}

function validateStep(wizard, stepIndex) {
  if (stepIndex === 0) {
    if (!String(wizard.meta.wizardName || '').trim()) {
      return { ok: false, message: 'Compila “Nome wizard”.' };
    }
    return { ok: true, message: '' };
  }

  if (stepIndex === 1) {
    return validatePcName(wizard.pcName.raw);
  }

  if (stepIndex === 2) {
    const u = validateUsername(wizard.localAdmin.username);
    if (!u.ok) return u;
    if (!wizard.localAdmin.password) return { ok: false, message: 'Password obbligatoria.' };
    if (wizard.localAdmin.password !== wizard.localAdmin.passwordConfirm) {
      return { ok: false, message: 'Password e conferma non coincidono.' };
    }
    if (passwordStrength(wizard.localAdmin.password) < 3) {
      return { ok: false, message: 'Password troppo debole (minimo consigliato: score 3/5).' };
    }
    return { ok: true, message: '' };
  }

  if (stepIndex === 3) {
    return { ok: true, message: '' };
  }

  if (stepIndex === 4) {
    if (!Array.isArray(wizard.bloatware.preselected)) return { ok: false, message: 'Selezione bloatware non valida.' };
    if (wizard.bloatware.preselected.length === 0) return { ok: false, message: 'Seleziona almeno un bloatware (puoi anche scegliere “nessuno” deselezionando tutto, ma serve una scelta esplicita).' };
    return { ok: true, message: '' };
  }

  if (stepIndex === 5) {
    if (wizard.powerPlan.manual) {
      if (wizard.powerPlan.screenTimeoutMin < 1 || wizard.powerPlan.screenTimeoutMin > 60) return { ok: false, message: 'Timeout schermo fuori range.' };
      if (!wizard.powerPlan.sleepNever && (wizard.powerPlan.sleepTimeoutMin < 1 || wizard.powerPlan.sleepTimeoutMin > 120)) return { ok: false, message: 'Sospensione sistema fuori range.' };
      if (wizard.powerPlan.cpuMinPercent < 0 || wizard.powerPlan.cpuMinPercent > 100) return { ok: false, message: 'CPU min% fuori range.' };
      if (wizard.powerPlan.cpuMaxPercent < 50 || wizard.powerPlan.cpuMaxPercent > 100) return { ok: false, message: 'CPU max% fuori range.' };
      if (wizard.powerPlan.cpuMinPercent > wizard.powerPlan.cpuMaxPercent) return { ok: false, message: 'CPU min% non può superare CPU max%.' };
    }
    return { ok: true, message: '' };
  }

  if (stepIndex === 6) {
    if (wizard.extras.wifiEnabled) {
      if (!String(wizard.extras.wifiSsid || '').trim()) return { ok: false, message: 'SSID Wi-Fi obbligatorio.' };
      if (!String(wizard.extras.wifiPassword || '').trim()) return { ok: false, message: 'Password Wi-Fi obbligatoria.' };
    }
    return { ok: true, message: '' };
  }

  return { ok: true, message: '' };
}

async function fetchTemplates() {
  const res = await client.get('/templates');
  return res.data;
}

async function fetchSoftware() {
  const res = await client.get('/software');
  return res.data;
}

function normalizeTemplates(payload) {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (payload.data && Array.isArray(payload.data)) return payload.data;
  if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
  return [];
}

function getTemplateRow(item) {
  const t = item?.data ? item.data : item;
  return {
    id: t?.id,
    name: t?.nome ?? t?.name ?? '—',
    config: t?.configurazione ?? t?.config ?? null,
  };
}

async function createWizardApi(payload) {
  try {
    const response = await client.post('/wizards', payload);
    return response.data;
  } catch (error) {
    console.error('Wizard create error:', error?.response?.data || error);
    throw error;
  }
}

export default function WizardBuilderPage() {
  const navigate = useNavigate();
  const [wizard, dispatch] = useReducer(reducer, undefined, () => {
    // lazy init from localStorage to avoid synchronous setState in an effect
    const stored = typeof window !== 'undefined' ? window.localStorage.getItem(LS_KEY) : null;
    const parsed = safeParse(stored);
    if (parsed?.wizard) return parsed.wizard;
    return buildEmptyWizard();
  });

  const [currentStep, setCurrentStep] = useState(() => {
    const stored = typeof window !== 'undefined' ? window.localStorage.getItem(LS_KEY) : null;
    const parsed = safeParse(stored);
    return typeof parsed?.currentStep === 'number' ? parsed.currentStep : 0;
  });

  const [completedMap, setCompletedMap] = useState(() => {
    const stored = typeof window !== 'undefined' ? window.localStorage.getItem(LS_KEY) : null;
    const parsed = safeParse(stored);
    return parsed?.completedMap || {};
  });
  const [globalError, setGlobalError] = useState('');
  const [postGenModal, setPostGenModal] = useState({ open: false, code: '', wizardId: null, directLink: '' });

  const templatesQuery = useQuery({
    queryKey: ['templates'],
    queryFn: fetchTemplates,
    staleTime: 30 * 1000,
  });

  const softwareQuery = useQuery({
    queryKey: ['software'],
    queryFn: fetchSoftware,
    staleTime: 30 * 1000,
  });

  const availableSoftware = useMemo(() => {
    const payload = softwareQuery.data;
    if (!payload) return [];
    if (Array.isArray(payload)) return payload.map((s) => (s.data ? s.data : s));
    if (payload.data && Array.isArray(payload.data)) return payload.data;
    if (payload.data && payload.data.data && Array.isArray(payload.data.data)) return payload.data.data;
    return [];
  }, [softwareQuery.data]);

  const templates = useMemo(() => normalizeTemplates(templatesQuery.data).map(getTemplateRow), [templatesQuery.data]);

  // initial state is handled lazily above; no mount effect required

  useEffect(() => {
    const payload = {
      wizard,
      currentStep,
      completedMap,
      savedAt: new Date().toISOString(),
    };
    window.localStorage.setItem(LS_KEY, JSON.stringify(payload));
  }, [wizard, currentStep, completedMap]);

  useEffect(() => {
    const raw = String(wizard.pcName.raw || '').trim().toUpperCase();
    const dept = wizard.pcName.useDepartmentVariable ? String(wizard.pcName.department || 'IT').toUpperCase() : '';
    const formatted = wizard.pcName.useDepartmentVariable && dept
      ? `${dept}-${raw}`.replace(/--+/g, '-')
      : raw;

    dispatch({
      type: 'PATCH_PATH',
      payload: { path: ['pcName', 'formattedPreview'], value: formatted },
    });
  }, [wizard.pcName.raw, wizard.pcName.useDepartmentVariable, wizard.pcName.department]);

  const createWizardMutation = useMutation({
    mutationFn: createWizardApi,
    onSuccess: (data) => {
      const w = data?.data ? data.data : data;
      const code = w?.codiceunivoco ?? w?.code ?? '';
      const wizardId = w?.id ?? null;
      const directLink = code ? `${window.location.origin}/agent?code=${encodeURIComponent(code)}` : '';

      setPostGenModal({
        open: true,
        code: code || 'WD-????',
        wizardId,
        directLink,
      });

      window.localStorage.removeItem(LS_KEY);
    },
    onError: (error) => {
      console.error('Wizard create status:', error?.response?.status);
      console.error('Wizard create payload error:', error?.response?.data || error);

      const validationErrors = error?.response?.data?.errors;
      if (validationErrors) {
        const firstError = Object.values(validationErrors).flat()[0];
        setGlobalError(firstError || 'Errore di validazione durante la creazione del wizard.');
      } else {
        setGlobalError(error?.response?.data?.message || error?.message || 'Errore imprevisto durante la creazione del wizard.');
      }

      throw error;
    },
  });

  function markStepCompleted(stepIndex) {
    setCompletedMap((prev) => ({ ...prev, [stepIndex]: true }));
  }

  function handleGoToStep(targetIdx) {
    if (targetIdx < 0 || targetIdx > STEPS.length - 1) return;
    setGlobalError('');

    if (targetIdx > currentStep) {
      const v = validateStep(wizard, currentStep);
      if (!v.ok) {
        setGlobalError(v.message);
        return;
      }
      markStepCompleted(currentStep);
    }
    setCurrentStep(targetIdx);
  }

  function handleNext() {
    setGlobalError('');
    const v = validateStep(wizard, currentStep);
    if (!v.ok) {
      setGlobalError(v.message);
      return;
    }
    markStepCompleted(currentStep);
    setCurrentStep((s) => Math.min(s + 1, STEPS.length - 1));
  }

  function handleBack() {
    setGlobalError('');
    setCurrentStep((s) => Math.max(s - 1, 0));
  }

  function handleReset() {
    dispatch({ type: 'RESET' });
    setCurrentStep(0);
    setCompletedMap({});
    setGlobalError('');
    window.localStorage.removeItem(LS_KEY);
  }

  function applyTemplateById(templateId) {
    const selected = templates.find((t) => String(t.id) === String(templateId));
    if (!selected?.config) return;

    const cfg = selected.config;

    const next = buildEmptyWizard();

    next.meta.wizardName = wizard.meta.wizardName;
    next.meta.templateId = String(templateId);
    next.meta.internalNotes = wizard.meta.internalNotes;

    if (cfg.nomepc) next.pcName.raw = String(cfg.nomepc);

    if (cfg.utenteadmin?.username) next.localAdmin.username = String(cfg.utenteadmin.username);

    if (Array.isArray(cfg.softwareinstalla)) {
      const ids = cfg.softwareinstalla
        .map((x) => x?.softwarelibraryid ?? x?.id)
        .filter(Boolean)
        .map((x) => Number(x))
        .filter((n) => Number.isFinite(n));
      next.software.selectedIds = Array.from(new Set(ids));
    }

    if (Array.isArray(cfg.bloatwaredefault)) {
      next.bloatware.preselected = cfg.bloatwaredefault.map((x) => String(x));
    }

    if (cfg.powerplan?.tipo === 'preset' && cfg.powerplan?.params?.preset) {
      next.powerPlan.preset = String(cfg.powerplan.params.preset);
      next.powerPlan.manual = false;
    }
    if (cfg.powerplan?.tipo === 'custom' && cfg.powerplan?.params) {
      next.powerPlan.manual = true;
      const p = cfg.powerplan.params;
      if (typeof p.monitortimeoutac === 'number') next.powerPlan.screenTimeoutMin = p.monitortimeoutac;
      if (typeof p.sleeptimeoutac === 'number') next.powerPlan.sleepTimeoutMin = p.sleeptimeoutac;
      if (typeof p.cpuminpercent === 'number') next.powerPlan.cpuMinPercent = p.cpuminpercent;
      if (typeof p.cpumaxpercent === 'number') next.powerPlan.cpuMaxPercent = p.cpumaxpercent;
    }

    if (cfg.extras?.timezone) next.extras.timezone = String(cfg.extras.timezone);
    if (cfg.extras?.language) next.extras.language = String(cfg.extras.language);
    if (cfg.extras?.keyboardlayout) next.extras.keyboardLayout = String(cfg.extras.keyboardlayout);
    if (cfg.extras?.windowsupdate?.policy) next.extras.windowsUpdatePolicy = String(cfg.extras.windowsupdate.policy);

    dispatch({ type: 'LOAD_DRAFT', payload: next });
    setCompletedMap({});
    setCurrentStep(0);
    setGlobalError('');
  }

  function buildApiPayload() {
  // availableSoftware comes from the softwareQuery; BLOATWARE_LIST is imported
  const softwareSource = availableSoftware || [];
  const bloatwareSource = BLOATWARE_LIST || [];

    const softwareItems = (softwareSource || [])
      .filter((item) => wizard.software.selectedIds.includes(item.id))
      .map((item) => ({
        id: Number(item.id),
        winget_id: String(item.identificatore ?? item.winget_id ?? ''),
        name: String(item.nome ?? item.name ?? ''),
        type: String(item.tipo ?? item.type ?? 'winget'),
        download_url: item.download_url ?? null,
      }));

    const bloatwareItems = (bloatwareSource || []).map((item) => ({
      package_name: String(item.package_name),
      display_name: String(item.display_name),
      selected: Array.isArray(wizard.bloatware.preselected) ? wizard.bloatware.preselected.includes(item.package_name) : false,
    }));

    const payload = {
      nome: wizard.meta.wizardName,
      template_id: wizard.meta.templateId || null,
      note_interne: wizard.meta.internalNotes || null,
      configurazione: {
        version: '1.0',
        pc_name: wizard.pcName.formattedPreview,
        admin_user: {
          username: wizard.localAdmin.username,
          password: wizard.localAdmin.password,
          remove_setup_account: Boolean(wizard.localAdmin.removeMicrosoftSetupAccount),
        },
        software: softwareItems,
        bloatware: bloatwareItems,
        power_plan: {
          type: wizard.powerPlan.manual
            ? 'custom'
            : wizard.powerPlan.preset === 'prestazioni_elevate'
            ? 'high_performance'
            : wizard.powerPlan.preset === 'risparmio_energetico'
            ? 'power_saver'
            : 'balanced',
          screen_timeout_ac: wizard.powerPlan.screenTimeoutMin ?? null,
          sleep_timeout_ac: wizard.powerPlan.sleepNever ? null : wizard.powerPlan.sleepTimeoutMin ?? null,
          cpu_min_percent: wizard.powerPlan.cpuMinPercent ?? 0,
          cpu_max_percent: wizard.powerPlan.cpuMaxPercent ?? 100,
        },
        extras: {
          timezone: wizard.extras.timezone || null,
          language: wizard.extras.language || null,
          keyboard_layout: wizard.extras.keyboardLayout || null,
          wallpaper_url: null,
          wifi: wizard.extras.wifiEnabled
            ? {
                ssid: wizard.extras.wifiSsid,
                password: wizard.extras.wifiPassword,
              }
            : null,
          windows_update: wizard.extras.windowsUpdatePolicy || 'auto',
        },
      },
    };

    return payload;
  }

  async function handleGenerateWizard() {
    setGlobalError('');

    for (let i = 0; i < 7; i += 1) {
      const validationResult = validateStep(wizard, i);

      if (!validationResult.ok) {
        setGlobalError(`Step ${i + 1}: ${validationResult.message}`);
        setCurrentStep(i);
        return;
      }
    }

    const payload = buildApiPayload();

    const formData = new FormData();
    formData.append('nome', payload.nome);

    if (payload.template_id) {
      formData.append('template_id', String(payload.template_id));
    }

    if (payload.note_interne) {
      formData.append('note_interne', String(payload.note_interne));
    }

    formData.append('configurazione', JSON.stringify(payload.configurazione));

    if (wizard.extras.wallpaperFile instanceof File) {
      formData.append('wallpaper', wizard.extras.wallpaperFile);
    }

    createWizardMutation.mutate(formData);
  }

  const StepComponent = (() => {
    const commonProps = { wizard, dispatch };

    if (currentStep === 0) {
      return (
        <Step1Info
          {...commonProps}
          templates={templates}
          templatesLoading={templatesQuery.isLoading}
          onTemplateSelect={(templateId) => {
            dispatch({ type: 'PATCH_PATH', payload: { path: ['meta', 'templateId'], value: templateId } });
            if (templateId) applyTemplateById(templateId);
          }}
        />
      );
    }

    if (currentStep === 1) return <Step2NomePC {...commonProps} validatePcName={validatePcName} />;
    if (currentStep === 2) return <Step3Utente {...commonProps} validateUsername={validateUsername} passwordStrength={passwordStrength} />;
    if (currentStep === 3) return <Step4Software {...commonProps} />;
    if (currentStep === 4) return <Step5Bloatware {...commonProps} />;
    if (currentStep === 5) return <Step6PowerPlan {...commonProps} />;
    if (currentStep === 6) return <Step7Extra {...commonProps} />;
    return (
      <Step8Recap
        {...commonProps}
        onEditStep={(idx) => setCurrentStep(idx)}
        onGenerate={handleGenerateWizard}
        isGenerating={createWizardMutation.isPending}
      />
    );
  })();

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-100">Wizard Builder</h1>
          <p className="mt-1 text-sm text-slate-400">
            Costruisci un wizard multi-step per l’agent WinDeploy.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <NavLink
            to="/wizards"
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-900"
          >
            <ArrowLeft className="h-4 w-4" />
            Wizards
          </NavLink>

          <button
            type="button"
            onClick={handleReset}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-900"
          >
            <Save className="h-4 w-4" />
            Reset draft
          </button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
        <StepProgress
          steps={STEPS}
          currentStepIndex={currentStep}
          completedMap={completedMap}
          onStepClick={(idx) => handleGoToStep(idx)}
        />

        {globalError && (
          <div className="mt-4 rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-200">
            {globalError}
          </div>
        )}

        <div className="mt-6">{StepComponent}</div>

        <div className="mt-6 flex items-center justify-between gap-3">
          <button
            type="button"
            onClick={handleBack}
            disabled={currentStep === 0}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-950 disabled:opacity-50"
          >
            <ArrowLeft className="h-4 w-4" />
            Indietro
          </button>

          <div className="flex items-center gap-2">
            {currentStep < 7 ? (
              <button
                type="button"
                onClick={handleNext}
                className="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500"
              >
                Avanti
                <ArrowRight className="h-4 w-4" />
              </button>
            ) : (
              <button
                type="button"
                onClick={handleGenerateWizard}
                disabled={createWizardMutation.isPending}
                className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
              >
                <Wand2 className="h-4 w-4" />
                {createWizardMutation.isPending ? 'Generazione…' : 'GENERA WIZARD'}
              </button>
            )}
          </div>
        </div>
      </div>

      {postGenModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
          <div className="w-full max-w-lg rounded-xl border border-slate-800 bg-slate-950 p-5">
            <div className="text-lg font-semibold text-slate-100">Wizard generato</div>
            <p className="mt-1 text-sm text-slate-400">
              Usa questo codice nell’agent Windows oppure condividi il link diretto.
            </p>

            <div className="mt-4 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
              <div className="text-xs uppercase tracking-wide text-slate-400">Codice univoco</div>
              <div className="mt-2 flex items-center justify-between gap-3">
                <div className="font-mono text-3xl font-bold text-slate-100">
                  {postGenModal.code}
                </div>
                <button
                  type="button"
                  onClick={() => navigator.clipboard.writeText(postGenModal.code)}
                  className="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
                >
                  Copia
                </button>
              </div>

              <div className="mt-4 text-xs uppercase tracking-wide text-slate-400">Link diretto</div>
              <div className="mt-2 flex items-center gap-2">
                <input
                  readOnly
                  value={postGenModal.directLink}
                  className="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-100"
                />
                <button
                  type="button"
                  onClick={() => navigator.clipboard.writeText(postGenModal.directLink)}
                  className="shrink-0 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-200 hover:bg-slate-950"
                >
                  Copia link
                </button>
              </div>
            </div>

            <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
              <a
                href="/agent/agent.exe"
                className="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500"
              >
                Scarica agent.exe
              </a>

              {postGenModal.wizardId && (
                <button
                  type="button"
                  onClick={() => navigate(`/wizards/${postGenModal.wizardId}/monitor`)}
                  className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
                >
                  Vai al monitor
                </button>
              )}

              <button
                type="button"
                onClick={() => setPostGenModal({ open: false, code: '', wizardId: null, directLink: '' })}
                className="inline-flex items-center justify-center rounded-lg border border-slate-800 bg-slate-900/40 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-900"
              >
                Chiudi
              </button>
            </div>

            <div className="mt-3 text-xs text-slate-400">
              Sicurezza: la password admin e la password Wi-Fi vanno cifrate server-side e non devono essere restituite nelle API di listing. [file:8]
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
