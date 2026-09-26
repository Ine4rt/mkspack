# Installation de l'assistant e-mail vocal sur Windows.
#
# Dans PowerShell (clic droit sur Demarrer > Terminal), coller :
#   irm https://raw.githubusercontent.com/Ine4rt/mkspack/refs/heads/claude/voice-email-agent-telegram-186hnq/mail-agent/install.ps1 | iex
#
# Le script : telecharge le programme, installe Python si besoin, installe les
# dependances, lance la configuration guidee, puis demarre le bot et le
# relance automatiquement a chaque ouverture de session Windows.
# Relancer la meme commande met le programme a jour (la configuration est gardee).
# (Texte volontairement sans accents : compatibilite avec Windows PowerShell 5.)

$ErrorActionPreference = "Stop"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$ProgressPreference = "SilentlyContinue"

$Repo = "Ine4rt/mkspack"
$Branch = "claude/voice-email-agent-telegram-186hnq"
$Dest = Join-Path $env:LOCALAPPDATA "MailAgent"
$TaskName = "Assistant e-mail vocal"
$PythonUrl = "https://www.python.org/ftp/python/3.12.10/python-3.12.10-amd64.exe"

function Step($text) { Write-Host "`n=== $text ===" -ForegroundColor Cyan }

function Stop-Bot {
    Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Get-CimInstance Win32_Process -Filter "Name='pythonw.exe' OR Name='python.exe'" |
        Where-Object { $_.CommandLine -like "*-m mail_agent*" -and $_.CommandLine -notlike "*--setup*" } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

function Find-Python {
    foreach ($v in "3.12", "3.13", "3.11", "3.10") {
        try {
            $exe = & py "-$v" -c "import sys; print(sys.executable)" 2>$null
            if ($LASTEXITCODE -eq 0 -and $exe) { return $exe.Trim() }
        } catch {}
    }
    foreach ($v in "312", "313", "311", "310") {
        $candidate = Join-Path $env:LOCALAPPDATA "Programs\Python\Python$v\python.exe"
        if (Test-Path $candidate) { return $candidate }
    }
    return $null
}

# --------------------------------------------------------------------------
Step "1/5 Telechargement du programme"
$zip = Join-Path $env:TEMP "mailagent.zip"
$tmp = Join-Path $env:TEMP "mailagent_src"
Invoke-WebRequest "https://github.com/$Repo/archive/refs/heads/$Branch.zip" -OutFile $zip -UseBasicParsing
Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
Expand-Archive $zip $tmp -Force
$src = Join-Path (Get-ChildItem $tmp -Directory | Select-Object -First 1).FullName "mail-agent"
if (-not (Test-Path (Join-Path $src "mail_agent"))) { throw "Archive inattendue : dossier mail-agent introuvable" }

Stop-Bot
New-Item -ItemType Directory -Force $Dest | Out-Null
# On garde la configuration (.env), les donnees et l'environnement Python existants.
robocopy $src $Dest /E /XD data .venv /XF .env /NFL /NDL /NJH /NJS /NP | Out-Null
Copy-Item (Join-Path $src "windows\*.bat") $Dest -Force
Remove-Item $zip, $tmp -Recurse -Force -ErrorAction SilentlyContinue
Write-Host "Installe dans $Dest"

# --------------------------------------------------------------------------
Step "2/5 Python"
$py = Find-Python
if (-not $py) {
    Write-Host "Python absent : installation de Python 3.12 (quelques minutes)..."
    $installer = Join-Path $env:TEMP "python-3.12-installer.exe"
    Invoke-WebRequest $PythonUrl -OutFile $installer -UseBasicParsing
    Start-Process $installer -Wait -ArgumentList `
        "/quiet InstallAllUsers=0 InstallLauncherAllUsers=0 PrependPath=1 Include_launcher=1 Include_test=0"
    Remove-Item $installer -Force -ErrorAction SilentlyContinue
    $py = Find-Python
    if (-not $py) { throw "Python n'a pas pu etre installe. Installez Python 3.12 depuis python.org puis relancez." }
}
Write-Host "Python : $py"

# --------------------------------------------------------------------------
Step "3/5 Installation des composants (plusieurs minutes la premiere fois)"
$venvPy = Join-Path $Dest ".venv\Scripts\python.exe"
$venvPyw = Join-Path $Dest ".venv\Scripts\pythonw.exe"
if (-not (Test-Path $venvPy)) { & $py -m venv (Join-Path $Dest ".venv") }
& $venvPy -m pip install --upgrade pip --quiet --disable-pip-version-check
& $venvPy -m pip install -e "$Dest[local-stt]" --quiet --disable-pip-version-check
if ($LASTEXITCODE -ne 0) { throw "Echec de l'installation des composants Python." }

# --------------------------------------------------------------------------
Step "4/5 Configuration guidee"
$env:PYTHONUTF8 = "1"
$envFile = Join-Path $Dest ".env"
$configure = $true
if (Test-Path $envFile) {
    $answer = Read-Host "Une configuration existe deja. La refaire ? (o/N)"
    $configure = $answer -match '^[oOyY]'
}
if ($configure) {
    & $venvPy -m mail_agent --setup
    if ($LASTEXITCODE -ne 0) { throw "Configuration interrompue. Relancez la commande pour reprendre." }
}

# --------------------------------------------------------------------------
Step "5/5 Demarrage automatique"
$user = "$env:USERDOMAIN\$env:USERNAME"
$autostart = "tache planifiee"
try {
    $action = New-ScheduledTaskAction -Execute $venvPyw -Argument "-m mail_agent" -WorkingDirectory $Dest
    $trigger = New-ScheduledTaskTrigger -AtLogOn -User $user
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
        -StartWhenAvailable -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew `
        -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)
    $principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings `
        -Principal $principal -Force | Out-Null
    Start-ScheduledTask -TaskName $TaskName
} catch {
    # Sans droits suffisants : raccourci dans le dossier Demarrage de Windows.
    $autostart = "dossier Demarrage"
    $shell = New-Object -ComObject WScript.Shell
    $link = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath("Startup")) "$TaskName.lnk"))
    $link.TargetPath = $venvPyw
    $link.Arguments = "-m mail_agent"
    $link.WorkingDirectory = $Dest
    $link.Save()
    Start-Process $venvPyw -ArgumentList "-m mail_agent" -WorkingDirectory $Dest
}

# Raccourci sur le Bureau vers le dossier (boutons Demarrer / Arreter / Journal).
$shell = New-Object -ComObject WScript.Shell
$desk = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath("Desktop")) "Assistant e-mail.lnk"))
$desk.TargetPath = $Dest
$desk.Save()

$answer = Read-Host "Empecher la mise en veille quand l'ordinateur est branche (le bot doit rester allume) ? (O/n)"
if ($answer -notmatch '^[nN]') {
    powercfg /change standby-timeout-ac 0
    powercfg /change hibernate-timeout-ac 0
}

Start-Sleep -Seconds 5
$log = Join-Path $Dest "data\mail_agent.log"
Write-Host ""
Write-Host "Termine ! L'assistant tourne en arriere-plan ($autostart)." -ForegroundColor Green
Write-Host "Sur ton telephone, ouvre ton bot dans Telegram et dis : 'Lis-moi mes mails non lus'."
Write-Host "Dossier et boutons (Demarrer, Arreter, Journal) : raccourci 'Assistant e-mail' sur le Bureau."
if (Test-Path $log) {
    Write-Host "`nDernieres lignes du journal :"
    Get-Content $log -Tail 5
}
