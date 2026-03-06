# agent/logger.py
import logging
import os
import re
import sys
from pathlib import Path
from datetime import datetime
from logging.handlers import RotatingFileHandler
from config import LOG_DIR, LOG_MAX_BYTES, LOG_BACKUP_COUNT, LOG_SENSITIVE_PATTERNS

class SensitiveDataFilter(logging.Filter):
    """Filtro che redige automaticamente password e token usando regex."""
    def __init__(self):
        super().__init__()
        self.patterns = [re.compile(pattern) for pattern in LOG_SENSITIVE_PATTERNS]

    def filter(self, record):
        # Protezione su msg
        if isinstance(record.msg, str):
            msg = record.msg
            for pattern in self.patterns:
                msg = pattern.sub(r'\g<1>[REDACTED]', msg)
            record.msg = msg
        
        # Protezione sugli argomenti del log (se formattazione lazily es. logger.info("%s", data))
        if isinstance(record.args, tuple):
            new_args = []
            for arg in record.args:
                if isinstance(arg, str):
                    arg_str = arg
                    for pattern in self.patterns:
                        arg_str = pattern.sub(r'\g<1>[REDACTED]', arg_str)
                    new_args.append(arg_str)
                else:
                    new_args.append(arg)
            record.args = tuple(new_args)
        elif isinstance(record.args, dict):
            new_args = {}
            for k, v in record.args.items():
                if isinstance(v, str):
                    val_str = v
                    for pattern in self.patterns:
                        val_str = pattern.sub(r'\g<1>[REDACTED]', val_str)
                    new_args[k] = val_str
                else:
                    new_args[k] = v
            record.args = new_args
            
        return True

def get_base_log_dir() -> Path:
    """Restituisce la cartella dei log gestendo i permessi del filesystem."""
    try:
        LOG_DIR.mkdir(parents=True, exist_ok=True)
        # Test di permessi scrittura
        test_file = LOG_DIR / '.write_test'
        test_file.touch()
        test_file.unlink()
        return LOG_DIR
    except (PermissionError, OSError):
        # Fallback in AppData/Local utente corrente se no-admin
        fallback_dir = Path.home() / 'AppData' / 'Local' / 'WinDeploy' / 'logs'
        fallback_dir.mkdir(parents=True, exist_ok=True)
        return fallback_dir

def setup_logger(wizard_code: str) -> logging.Logger:
    """Inizializza l'albero di logging per WinDeploy Agent."""
    logger = logging.getLogger('windeploy.agent')
    logger.setLevel(logging.DEBUG)
    
    # Previene l'aggiunta di molteplici handler in chiamate ripetute
    if logger.hasHandlers():
        return logger
        
    base_dir = get_base_log_dir()
    if 'AppData' in str(base_dir):
        logger.warning(f"[FALLBACK] Impossibile scrivere in ProgramData. Uso directory: {base_dir}")

    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    log_file = base_dir / f'execution_{wizard_code}_{timestamp}.log'
    
    formatter = logging.Formatter(
        '%(asctime)s | %(levelname)-8s | %(name)s | %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    sensitive_filter = SensitiveDataFilter()
    
    # 1. FileHandler per l'esecuzione specifica
    fh = logging.FileHandler(log_file, encoding='utf-8')
    fh.setLevel(logging.DEBUG)
    fh.setFormatter(formatter)
    fh.addFilter(sensitive_filter)
    logger.addHandler(fh)
    
    # 2. Console (utile a te in fase di testing)
    sh = logging.StreamHandler(sys.stdout)
    sh.setLevel(logging.INFO)
    sh.setFormatter(formatter)
    sh.addFilter(sensitive_filter)
    logger.addHandler(sh)
    
    # 3. Rotating File Cumulativo
    all_log_file = base_dir / 'windeploy_all.log'
    rh = RotatingFileHandler(all_log_file, maxBytes=LOG_MAX_BYTES, backupCount=LOG_BACKUP_COUNT, encoding='utf-8')
    rh.setLevel(logging.WARNING)
    rh.setFormatter(formatter)
    rh.addFilter(sensitive_filter)
    logger.addHandler(rh)
    
    logger.info(f"Logger inizializzato per l'esecuzione del wizard: {wizard_code}")
    return logger

def get_log_path(wizard_code: str) -> Path | None:
    """Trova il file di log della sessione corrente (l'ultimo creato)."""
    base_dir = get_base_log_dir()
    if not base_dir.exists():
        return None
        
    logs = list(base_dir.glob(f'execution_{wizard_code}_*.log'))
    if not logs:
        return None
        
    logs.sort(key=lambda x: x.stat().st_mtime)
    return logs[-1]
