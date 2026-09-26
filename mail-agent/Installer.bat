@echo off
rem Installe l assistant e-mail vocal depuis ce dossier (sans GitHub).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
pause
