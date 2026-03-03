# 🛠️ WinDeploy Agent - Build Instructions (Windows)

Guida completa per la compilazione dell'agent Python in un singolo file `.exe` distribuibile.

## 1. Setup Ambiente (Windows 11)

Assicurati di usare **Python 3.11** (PyInstaller a volte ha instabilità con le release nuovissime tipo 3.12+ in concomitanza con librerie UI).

```powershell
# Crea e attiva l'ambiente virtuale
python -m venv venv
.\venv\Scripts\activate
```
