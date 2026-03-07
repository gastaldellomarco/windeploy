# WinDeploy — Domain Model
**versione**: 1.0.0  
**data**: 2026-03-07  
**autore**: architettura DDD  
**path**: `docs/architecture/domain-model.md`

> **Scope**: questo documento definisce il modello di dominio puro di WinDeploy.  
> NON contiene codice implementativo Eloquent/Laravel. Contiene contratti, invarianti  
> e relazioni che ogni implementazione DEVE rispettare.

---

## Indice

1. [Glossario del Dominio](#1-glossario-del-dominio)
2. [Bounded Context](#2-bounded-context)
3. [Entities (Aggregate Roots)](#3-entities-aggregate-roots)
4. [Value Objects](#4-value-objects)
5. [Domain Services](#5-domain-services)
6. [Repository Interfaces](#6-repository-interfaces)
7. [Policies (Autorizzazione)](#7-policies-autorizzazione)
8. [Domain Events & Listeners](#8-domain-events--listeners)
9. [Jobs (Queue)](#9-jobs-queue)
10. [Transizioni di Stato Wizard](#10-transizioni-di-stato-wizard)
11. [Decision Log](#11-decision-log)

---

## 1. Glossario del Dominio

| Termine | Definizione di dominio |
|---|---|
| **Tecnico** | Utente con ruolo `tecnico` che crea e gestisce Wizard |
| **Admin** | Utente con pieno accesso a tutte le risorse |
| **Wizard** | Configurazione completa di un PC target, ciclo di vita principale |
| **WizardCode** | Codice monouso `WD-XXXX` che autorizza l'Agent a eseguire il Wizard |
| **Agent** | Applicativo Python compilato `.exe` installato sul PC target |
| **Template** | Insieme di step riusabili tra più Wizard |
| **SoftwareLibrary** | Catalogo centralizzato di software installabili via winget |
| **ExecutionLog** | Registro immutabile di tutti gli step eseguiti dall'Agent |
| **Step** | Unità atomica di lavoro (installa software, applica GPO, ecc.) |
| **Report** | Documento HTML generato al termine dell'esecuzione |

---

## 2. Bounded Context

