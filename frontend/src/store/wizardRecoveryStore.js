// Path: frontend/src/store/wizardRecoveryStore.js
import { create } from "zustand";

export const WIZARD_RECOVERY_STORAGE_KEY = "windeploy_wizard_recovery";

export const useWizardRecoveryStore = create((set) => ({
  latestSnapshot: null,
  applyToken: 0,
  resetToken: 0,

  setLatestSnapshot: (snapshot) => set({ latestSnapshot: snapshot }),

  requestApplySnapshot: (snapshot) =>
    set({
      latestSnapshot: snapshot,
      applyToken: Date.now(),
    }),

  requestResetWizard: () =>
    set({
      latestSnapshot: null,
      resetToken: Date.now(),
    }),
}));
