# agent/config.py
import os
from pathlib import Path

# --- API Configuration ---
# Default to the backend virtual host used in the local development .env
# (backend/.env sets APP_URL=http://windeploy.local.api). Keep the
# environment override for production or custom installs.
API_URL = os.getenv("WINDEPLOY_API_URL", "http://windeploy.local.api/api")
AGENT_VERSION = "1.0.0"

# Development flag (set WINDEPLOY_DEV=1 to enable developer helpers like
# auto-opening saved backend responses)
DEV_MODE = os.getenv("WINDEPLOY_DEV", "0") in ("1", "true", "True")

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
WINDOW_WIDTH = 1000
WINDOW_HEIGHT = 600

# --- File System e Percorsi ---
# Costante globale referenziata per i logger (fallback gestito in logger.py)
LOG_DIR = Path(os.environ.get('PROGRAMDATA', 'C:\\ProgramData')) / 'WinDeploy' / 'logs'

# --- Logging System Settings ---
LOG_MAX_BYTES = 50 * 1024 * 1024  # 50 MB
LOG_BACKUP_COUNT = 3

# Regex per sanificazione dati su stringhe, dizionari e tuple di log
# Copre sintassi JSON, sintassi kwargs, sintassi plain text
LOG_SENSITIVE_PATTERNS = [
    r'(?i)(password["\s:=]+)[^\s,}"\']+',
    r'(?i)(passwd["\s:=]+)[^\s,}"\']+',
    r'(?i)(secret["\s:=]+)[^\s,}"\']+',
    r'(?i)(token["\s:=]+)[^\s,}"\']{20,}',
]

