@echo off
REM ============================================================
REM  rodar-local.bat — abre o site no seu computador usando o
REM  PHP e o MySQL do XAMPP (sem enviar nada para o servidor).
REM
REM  Antes de usar:
REM    1. abra o XAMPP Control Panel e inicie o MySQL;
REM    2. importe o banco.sql no phpMyAdmin (uma vez só);
REM    3. rode o criar-admin.php no terminal (uma vez só).
REM
REM  Depois basta dar dois cliques neste arquivo e abrir
REM  http://127.0.0.1:8099 no navegador.
REM ============================================================
setlocal

set "PHP=C:\xampp\php\php.exe"
set "DB_HOST=127.0.0.1"
set "DB_NAME=studio_nay_lopes"
set "DB_USER=root"
set "DB_PASS="

if not exist "%PHP%" (
    echo Nao encontrei o PHP do XAMPP em %PHP%
    echo Ajuste o caminho dentro deste arquivo .bat
    pause
    exit /b 1
)

echo Site local: http://127.0.0.1:8099
echo (feche esta janela para parar o site)
"%PHP%" -S 127.0.0.1:8099 -t "%~dp0."

pause
