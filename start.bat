@echo off
title Point of Sale System
echo Starting MySQL...
start /B "" "C:\xampp\mysql\bin\mysqld" --standalone
timeout /t 4 /nobreak >nul
echo.
echo  POS System is running!
echo  Opening http://localhost:8000 in your browser...
explorer "http://localhost:8000"
echo.
php -S localhost:8000 -t C:\PointOfSaleSystem
echo.
echo Stopping MySQL...
taskkill /f /im mysqld.exe >nul 2>&1
pause
