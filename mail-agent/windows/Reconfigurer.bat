@echo off
set PYTHONUTF8=1
call "%~dp0Arreter.bat"
"%~dp0.venv\Scripts\python.exe" -m mail_agent --setup
call "%~dp0Demarrer.bat"
pause
