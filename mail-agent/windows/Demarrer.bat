@echo off
start "" "%~dp0.venv\Scripts\pythonw.exe" -m mail_agent
echo Assistant demarre en arriere-plan.
timeout /t 3 >nul
