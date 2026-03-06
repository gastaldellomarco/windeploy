<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un esperto Python. Devo creare l'agent Windows per WinDeploy — un'applicazione

desktop Windows (.exe) che configura automaticamente PC nuovi.
 
STACK:

- Python 3.11
- CustomTkinter (interfaccia grafica moderna)
- Requests (chiamate HTTPS al backend Laravel)
- Subprocess (esecuzione comandi PowerShell)
- Winreg (lettura registro Windows per app installate)
 
STRUTTURA DEL PROGETTO agent/:
agent/
  main.py              → entry point
  config.py            → costanti (API_URL, timeout, versione agent)
  api_client.py        → classe ApiClient (tutte le chiamate al backend)
  system_scanner.py    → scansione app installate
  installer.py         → installazione/disinstallazione software via PowerShell
  system_config.py     → rinomina PC, crea utente, power plan, extras
  report_generator.py  → genera report HTML finale
  gui/
    app.py             → finestra principale CustomTkinter con navigazione tra schermate
    screens/
      screen_connect.py    → schermata 1: inserimento codice wizard
      screen_overview.py   → schermata 2: riepilogo wizard
      screen_uninstall.py  → schermata 3: selezione app da disinstallare (la più complessa)
      screen_progress.py   → schermata 4: esecuzione con progress bar e step list
      screen_complete.py   → schermata 5: completamento con riepilogo
 
SCRIVI I FILE SEGUENTI COMPLETI:

1. config.py: API_URL configurabile, versione "1.0.0", timeout requests, colori UI (palette blu aziendale)
2. gui/app.py:
   - Finestra principale CTk, 900x650px, non ridimensionabile, tema dark
   - Logo "⚙️ WinDeploy" in alto, versione in basso a destra
   - Sistema di navigazione tra schermate (mostra/nascondi frame)
   - Funzione navigate(screen_name, data={}) per passare dati tra schermate
3. gui/screens/screen_connect.py:
   - Campo testo per codice wizard (es. WD-7A3F),
   - Bottone CONNETTI con spinner durante la chiamata API
   - Validazione formato codice (6 char: WD-XXXX)
   - Messaggi di errore: codice non trovato, scaduto, già usato, no connessione
   - Progress step indicator in alto (step 1 di 5 attivo)
 
Codice Python completo, commentato in italiano, pronto all'uso.

Ecco la soluzione completa per i tre file richiesti, progettata per essere robusta, manutenibile e pronta per la compilazione con PyInstaller.

### 📂 Struttura dei File Coinvolti

Prima di procedere con il codice, ecco come si inseriscono i file nella struttura del progetto `agent/`:

```text
agent/
├── config.py                 # File 1: Costanti globali, colori, timeout
├── gui/
│   ├── app.py                # File 2: Core dell'interfaccia CustomTkinter (Finestra e Router)
│   └── screens/
│       └── screen_connect.py # File 3: UI per l'inserimento del codice wizard
```


***

### 💻 1. File `config.py`

Questo file centralizza la configurazione per facilitare i cambi di ambiente (sviluppo/produzione) e la coerenza visiva.

```python
# agent/config.py
import os

# --- API Configuration ---
# In produzione si può usare os.getenv() se si inietta il parametro al build time
API_URL = os.getenv("WINDEPLOY_API_URL", "https://api.windeploy.tuodominio.com/api")
AGENT_VERSION = "1.0.0"

# --- Network & Timeouts ---
# Timeout per le richieste HTTPS in secondi (evita il blocco dell'agent se la rete cade)
REQUESTS_TIMEOUT = 10

# --- UI Palette (Tema Scuro / Blu Aziendale) ---
COLORS = {
    "primary": "#2563EB",       # Blue-600 (Main buttons)
    "primary_hover": "#1D4ED8", # Blue-700 (Hover state)
    "bg_main": "#111827",       # Gray-900 (Main background)
    "bg_card": "#1F2937",       # Gray-800 (Frames & Cards)
    "text_main": "#F9FAFB",     # Gray-50  (Primary text)
    "text_muted": "#9CA3AF",    # Gray-400 (Secondary text)
    "error": "#EF4444",         # Red-500  (Error labels)
    "success": "#10B981",       # Emerald-500 (Success indicators)
    "border": "#374151"         # Gray-700 (Input borders)
}

# --- Window Settings ---
WINDOW_WIDTH = 900
WINDOW_HEIGHT = 650
```


***

### 💻 2. File `gui/app.py`

Funge da contenitore principale e gestisce il *routing* simulato tra le schermate mostrando e nascondendo i `CTkFrame`.

```python
# agent/gui/app.py
import customtkinter as ctk
from config import AGENT_VERSION, WINDOW_WIDTH, WINDOW_HEIGHT, COLORS

# Import delle schermate (qui importiamo solo la prima per brevità)
from gui.screens.screen_connect import ScreenConnect

class WinDeployApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # --- Setup Finestra Principale ---
        self.title("WinDeploy Agent")
        self.geometry(f"{WINDOW_WIDTH}x{WINDOW_HEIGHT}")
        self.resizable(False, False)
        
        # Tema e colori globali
        ctk.set_appearance_mode("dark")
        self.configure(fg_color=COLORS["bg_main"])

        # --- Stato dell'Applicazione ---
        # Questo dizionario agisce da "store" globale per passare JWT token e wizard_config
        self.app_state = {}

        # --- Layout Principale ---
        self.main_container = ctk.CTkFrame(self, fg_color="transparent")
        self.main_container.pack(fill="both", expand=True, padx=20, pady=20)

        # Header (Logo in alto a sinistra)
        self.header_frame = ctk.CTkFrame(self.main_container, fg_color="transparent")
        self.header_frame.pack(fill="x", pady=(0, 20))
        
        self.logo_label = ctk.CTkLabel(
            self.header_frame, 
            text="⚙️ WinDeploy", 
            font=ctk.CTkFont(size=24, weight="bold"),
            text_color=COLORS["primary"]
        )
        self.logo_label.pack(side="left")

        # Container centrale per le schermate (Screens)
        self.screen_container = ctk.CTkFrame(
            self.main_container, 
            fg_color=COLORS["bg_card"], 
            corner_radius=10
        )
        self.screen_container.pack(fill="both", expand=True)
        self.screen_container.grid_rowconfigure(0, weight=1)
        self.screen_container.grid_columnconfigure(0, weight=1)

        # --- Inizializzazione Schermate ---
        self.screens = {}
        self.current_screen = None
        self._init_screens()

        # Footer (Versione in basso a destra)
        self.footer_frame = ctk.CTkFrame(self.main_container, fg_color="transparent")
        self.footer_frame.pack(fill="x", pady=(10, 0))
        
        self.version_label = ctk.CTkLabel(
            self.footer_frame, 
            text=f"v{AGENT_VERSION}", 
            font=ctk.CTkFont(size=12),
            text_color=COLORS["text_muted"]
        )
        self.version_label.pack(side="right")

        # Avvio sull'entry point
        self.navigate("ScreenConnect")

    def _init_screens(self):
        """Istanzia le schermate e le posiziona nella griglia sovrapposte."""
        # Passiamo 'self' (controller) per permettere la navigazione dalle schermate figlie
        self.screens["ScreenConnect"] = ScreenConnect(parent=self.screen_container, controller=self)
        
        # Placeholder per future implementazioni:
        # self.screens["ScreenOverview"] = ScreenOverview(parent=self.screen_container, controller=self)
        
        for screen in self.screens.values():
            screen.grid(row=0, column=0, sticky="nsew")

    def navigate(self, screen_name: str, payload: dict = None):
        """
        Gestisce il cambio schermata e il passaggio di dati.
        """
        # Aggiorna lo stato globale con i nuovi dati in ingresso
        if payload:
            self.app_state.update(payload)

        if screen_name in self.screens:
            # tkraise() porta in primo piano il frame richiesto
            screen = self.screens[screen_name]
            screen.tkraise()
            self.current_screen = screen
            
            # Se la schermata ha un metodo di refresh, passagli lo stato aggiornato
            if hasattr(screen, "on_show"):
                screen.on_show(self.app_state)
        else:
            print(f"[ERROR] Schermata non trovata: {screen_name}")

if __name__ == "__main__":
    app = WinDeployApp()
    app.mainloop()
```


***

### 💻 3. File `gui/screens/screen_connect.py`

Implementa la UI e la validazione asincrona. Include la separazione dei thread tra UI e richieste di rete (essenziale con CustomTkinter).

```python
# agent/gui/screens/screen_connect.py
import re
import threading
import uuid
import requests
import customtkinter as ctk

from config import COLORS, API_URL, REQUESTS_TIMEOUT

def get_mac_address():
    """Recupera l'indirizzo MAC locale formattato (es. 00:1A:2B:3C:4D:5E)."""
    mac_num = uuid.getnode()
    mac_hex = ':'.join(['{:02x}'.format((mac_num >> ele) & 0xff) for ele in range(0,8*6,8)][::-1])
    return mac_hex.upper()

class ScreenConnect(ctk.CTkFrame):
    def __init__(self, parent, controller):
        super().__init__(parent, fg_color="transparent")
        self.controller = controller
        self.pack_propagate(False)

        # --- Indicatori di Progresso ---
        self.step_label = ctk.CTkLabel(
            self, 
            text="Step 1 di 5: Connessione", 
            font=ctk.CTkFont(size=14, weight="bold"),
            text_color=COLORS["text_muted"]
        )
        self.step_label.pack(pady=(40, 10))

        # --- Titolo e Testi ---
        self.title_label = ctk.CTkLabel(
            self, 
            text="Inserisci il Codice Wizard", 
            font=ctk.CTkFont(size=24, weight="bold"),
            text_color=COLORS["text_main"]
        )
        self.title_label.pack(pady=(30, 5))

        self.subtitle_label = ctk.CTkLabel(
            self, 
            text="Il codice a 6 caratteri generato dalla web app (es. WD-7A3F)", 
            font=ctk.CTkFont(size=14),
            text_color=COLORS["text_muted"]
        )
        self.subtitle_label.pack(pady=(0, 30))

        # --- Input Campo Codice ---
        self.code_entry = ctk.CTkEntry(
            self, 
            placeholder_text="WD-XXXX",
            font=ctk.CTkFont(size=22, weight="bold"),
            width=280,
            height=55,
            justify="center",
            fg_color=COLORS["bg_main"],
            border_color=COLORS["border"],
            text_color=COLORS["text_main"]
        )
        self.code_entry.pack(pady=10)
        self.code_entry.bind("<Return>", lambda event: self.handle_connect())

        # --- Label per Errori ---
        self.error_label = ctk.CTkLabel(
            self, 
            text="", 
            font=ctk.CTkFont(size=13),
            text_color=COLORS["error"]
        )
        self.error_label.pack(pady=5)

        # --- Bottone d'Azione ---
        self.connect_btn = ctk.CTkButton(
            self, 
            text="CONNETTI",
            font=ctk.CTkFont(size=16, weight="bold"),
            width=280,
            height=50,
            fg_color=COLORS["primary"],
            hover_color=COLORS["primary_hover"],
            command=self.handle_connect
        )
        self.connect_btn.pack(pady=20)

    def set_error(self, message: str):
        """Visualizza un errore a schermo e formatta l'input in rosso."""
        self.error_label.configure(text=message)
        self.code_entry.configure(border_color=COLORS["error"])

    def clear_error(self):
        """Ripulisce lo stato d'errore."""
        self.error_label.configure(text="")
        self.code_entry.configure(border_color=COLORS["border"])

    def handle_connect(self):
        """Punto d'ingresso per il submit del form."""
        code = self.code_entry.get().strip().upper()
        
        # Riformattiamo il testo nell'entry pulito
        self.code_entry.delete(0, 'end')
        self.code_entry.insert(0, code)
        self.clear_error()

        # Validazione RegEx formato WD-XXXX
        if not re.match(r"^WD-[A-Z0-9]{4}$", code):
            self.set_error("Formato non valido. Il codice deve essere nel formato WD-XXXX.")
            return

        # Modifica UI per lo stato di "Loading" (spinner testuale)
        self.connect_btn.configure(text="CONNESSIONE IN CORSO...", state="disabled", fg_color=COLORS["border"])
        self.code_entry.configure(state="disabled")

        # Delega la chiamata API a un thread separato per prevenire il freeze dell'UI [^1_1].
        threading.Thread(target=self._api_call_thread, args=(code,), daemon=True).start()

    def _api_call_thread(self, code: str):
        """Task asincrono (Thread) che esegue l'autenticazione tramite Laravel."""
        # NOTA ARCHITETTURALE: Per pura Single Responsibility, questa chiamata andrebbe
        # delegata a api_client.py. Eseguita qui per mantenere lo script standalone e pronto all'uso.
        try:
            payload = {
                "codicewizard": code,
                "macaddress": get_mac_address()
            }
            
            response = requests.post(f"{API_URL}/agent/auth", json=payload, timeout=REQUESTS_TIMEOUT)
            
            if response.status_code == 200:
                data = response.json()
                # Il passaggio alla UI deve essere sempre instradato verso il main thread con .after [^1_1].
                self.after(0, self._on_success, data, code)
            elif response.status_code == 404:
                self.after(0, self.set_error, "Codice wizard non trovato o errato.")
            elif response.status_code == 410:
                msg = response.json().get("message", "Il codice è scaduto o è già stato utilizzato.")
                self.after(0, self.set_error, msg)
            elif response.status_code == 422:
                msg = response.json().get("message", "Il wizard non è ancora pronto per l'esecuzione.")
                self.after(0, self.set_error, msg)
            else:
                self.after(0, self.set_error, f"Errore del server ({response.status_code}). Riprova.")
                
        except requests.exceptions.ConnectionError:
            self.after(0, self.set_error, "Impossibile raggiungere il server. Controlla la rete LAN/WAN.")
        except requests.exceptions.Timeout:
            self.after(0, self.set_error, "Timeout della richiesta. Il server non risponde.")
        except Exception as e:
            self.after(0, self.set_error, f"Si è verificato un errore critico: {str(e)}")
        finally:
            self.after(0, self._restore_ui)

    def _on_success(self, api_data: dict, code: str):
        """Callback eseguita nel main thread in caso di risposta 200 OK."""
        payload = {
            "wizard_config": api_data.get("wizardconfig", {}),
            "auth_token": api_data.get("token"),
            "wizard_code": code
        }
        # Naviga al passaggio 2 passando il Token JWT e il JSON del backend
        self.controller.navigate("ScreenOverview", payload)

    def _restore_ui(self):
        """Ripristina i componenti allo stato iniziale terminata l'attesa."""
        self.connect_btn.configure(text="CONNETTI", state="normal", fg_color=COLORS["primary"])
        self.code_entry.configure(state="normal")
```


***

### ✅ Checklist Operativa per la Compilazione

1. Installa i requirement corretti per PyInstaller e CTk. Evita virtual env "sporchi" per ridurre la size dell'exe.
2. Inserisci in `api_client.py` l'implementazione del client basata su classi. Estrai il blocco `requests.post()` visto in `screen_connect.py` e fagli restituire i dati raw.
3. Compila l'app utilizzando l'opzione noconsole per nascondere il prompt di Windows: `pyinstaller --noconsole --onefile --windowed --icon=app.ico main.py`. Attenzione: CustomTkinter necessita dell'inclusione forzata dei suoi asset con `--add-data`.

***

### ⚖️ Analisi Critica, Sicurezza e Trade-off (L'Avvocato del Diavolo)

- **⚠️ Rischio Architetturale e di Sicurezza (In-Memory Secrets)**: Le API di Laravel inviano all'agent un payload (`wizardconfig`) che include il campo decifrato `utente['admin']['password']`. Questo JSON viene tenuto nel dizionario `self.app_state` (memoria Heap di Python). È cruciale che, subito dopo aver richiamato `subprocess.run()` in `installer.py` per creare l'account Windows, la password venga cancellata dallo stato (`del self.app_state['wizard_config']['utente']`), altrimenti un dump di memoria estrarrebbe una credenziale di dominio/local admin in chiaro.
- **Limiti MAC Address Spoofing**: `uuid.getnode()` viene utilizzato come fallback nativo cross-platform per ottenere il MAC. Su PC Windows con VPN o schede di rete virtuali multiple, `getnode()` può "pescare" il mac address della scheda sbagliata. Se in Laravel configuri un vincolo stretto sul MAC generato dal frontend, c'è il rischio di un disallineamento al momento dell'installazione. *Alternativa raccomandata*: Usa il modulo `psutil` o fai un check `wmi` per iterare esclusivamente sulle schede Ethernet/Wi-Fi fisiche attive.[^1_1]
- **Multithreading e Tkinter**: CustomTkinter eredita da `tkinter` le classiche problematiche di thread safety. Tutte le chiamate HTTP lunghe o bloccanti (come il download dei file MSI via Requests o l'invio via HTTPS dei progressi dal `system_config.py`) DEVONO obbligatoriamente passare per un thread isolato e richiamare il master worker usando sempre `.after(0, function)` per l'aggiornamento UI. In caso contrario, lo stack di Windows contrassegnerà la GUI del `.exe` come "Non risponde".[^1_1]
- **Dimensione file EXE**: Il binario prodotto con `--onefile` contenente `customtkinter` e `requests` peserà almeno 25-40 MB. Inoltre, all'avvio, PyInstaller decomprime in locale l'ambiente nella cartella temp `%AppData%\Local\Temp\_MEIXXXXXX`, introducendo un ritardo di 1-2 secondi all'apertura dell'app sul nuovo PC.
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_2][^1_3][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_2]: 0017-motorpage.md

[^1_3]: 0016-wizards.md

[^1_4]: 0015-dashboard.md

[^1_5]: 0014-Login.md

[^1_6]: 0013-SetupprogettoReact.md

[^1_7]: 0012-apiendpointwindows.md

[^1_8]: 0011-controllercrud.md

[^1_9]: 0010-autenticazione.md

[^1_10]: 0009-scaffoldprogetto.md

[^1_11]: 0008-db.md

[^1_12]: 0007-cheatsheet.md

[^1_13]: 0006-viteconfigjs.md

[^1_14]: 0005-filedotenv.md

[^1_15]: 0004-Strutturacartelle.md

[^1_16]: 0003-setupiniziale.md

[^1_17]: 0002-ricerca-iniziale.md

[^1_18]: 0001-projectdescription.txt

