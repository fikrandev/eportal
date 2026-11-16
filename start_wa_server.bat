@echo off
title E-Portal WhatsApp Gateway API
color 0A
cd /d "%~dp0wa-server"

echo ======================================================
echo           E-PORTAL WHATSAPP GATEWAY SERVER
echo ======================================================
echo Memeriksa instalasi Node.js...
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Node.js tidak ditemukan! Silakan install Node.js terlebih dahulu.
    pause
    exit /b 1
)

echo Menjalankan WhatsApp Gateway Server...
echo Buka browser dan masuk ke menu E-Portal:
echo Pengaturan Absensi -^> WhatsApp Gateway untuk melihat status atau scan QR.
echo.

node --expose-gc server.js

echo.
echo Server WhatsApp telah berhenti.
pause
