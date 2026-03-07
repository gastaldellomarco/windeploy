# WinDeploy — Open Questions

> Domande irrisolte e decisioni pendenti.

---

## [APERTO] Strategia cache per wizard payload
**Domanda:** Usare Redis per cachare i wizard eseguiti di frequente o affidarsi solo a MySQL con indici?
**Impatto:** Performance agent in reti lente.
**Priorità:** Bassa (affrontare dopo MVP)

## [APERTO] Formato log esecuzione agent
**Domanda:** I log di esecuzione devono essere inviati real-time al backend (WebSocket/SSE) o batch al completamento?
**Impatto:** Architettura ExecutionLog, latenza UX.
**Priorità:** Alta (decisione entro fase 3)

## [APERTO] Update automatico agent .exe
**Domanda:** L'agent deve auto-aggiornarsi scaricando il nuovo .exe da GitHub Releases o richiede update manuale?
**Impatto:** UX, sicurezza (verifica firma eseguibile).
**Priorità:** Media (affrontare in fase 4)
