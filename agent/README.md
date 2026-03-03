Agent GUI - Build instructions

1. Create and activate a clean virtualenv (recommended):
   python -m venv .venv; .\.venv\Scripts\Activate.ps1

2. Install requirements:
   pip install -r requirements.txt

3. PyInstaller build (hide console, onefile, include CTk assets):
   pyinstaller --noconsole --onefile --windowed --icon=app.ico --add-data "<path_to_python_site_packages>\customtkinter;customtkinter" main.py

Notes:

- Replace <path_to_python_site_packages> with the actual path where customtkinter package assets live.
- Including the CTk package directory via --add-data is required otherwise the widget assets (fonts/images) may be missing.
- Consider using --clean and a fresh venv to reduce exe size.
