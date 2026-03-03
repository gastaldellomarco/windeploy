# File: main.py
# Path: windeploy\agent\main.py

"""Entry point for the Agent GUI.

This module keeps a tiny, explicit entrypoint so PyInstaller (and local runs)
have a stable script to execute (main.spec expects `main.py`).
"""
import sys
import pathlib
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent.parent))

from gui.app import WinDeployApp


def main():
    app = WinDeployApp()
    app.mainloop()


if __name__ == "__main__":
    main()
