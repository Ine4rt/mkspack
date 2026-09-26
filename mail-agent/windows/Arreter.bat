@echo off
powershell -NoProfile -Command "Stop-ScheduledTask -TaskName 'Assistant e-mail vocal' -ErrorAction SilentlyContinue; Get-CimInstance Win32_Process -Filter \"Name='pythonw.exe'\" | Where-Object { $_.CommandLine -like '*-m mail_agent*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"
echo Assistant arrete. Il redemarrera a la prochaine ouverture de session.
timeout /t 3 >nul
