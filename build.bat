@echo off
REM ============================================================
REM CN Blog Mailer - Production Build Script (Windows)
REM ============================================================
REM This script creates a production-ready ZIP file for WordPress.org
REM ============================================================

echo.
echo ============================================================
echo   CN Blog Mailer - Production Build Script
echo ============================================================
echo.

REM Set plugin name and version
set PLUGIN_NAME=cn-blog-mailer
set VERSION=1.0.0

REM Set build directory
set BUILD_DIR=build
set PLUGIN_DIR=%BUILD_DIR%\%PLUGIN_NAME%

REM Clean previous build
echo [1/6] Cleaning previous build...
if exist %BUILD_DIR% (
    rmdir /s /q %BUILD_DIR%
)
mkdir %BUILD_DIR%
mkdir %PLUGIN_DIR%

echo [2/6] Installing production dependencies...
REM Install production dependencies only (no dev tools)
call composer install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 (
    echo ERROR: Composer install failed!
    pause
    exit /b 1
)

echo [3/6] Copying plugin files...
REM Copy main plugin files
copy wp-blog-mailer.php %PLUGIN_DIR%\ >nul
copy readme.txt %PLUGIN_DIR%\ >nul
copy LICENSE %PLUGIN_DIR%\ >nul
copy uninstall.php %PLUGIN_DIR%\ >nul

REM Copy directories
echo     - Copying includes/
xcopy includes %PLUGIN_DIR%\includes\ /E /I /Q >nul

echo     - Copying assets/
xcopy assets %PLUGIN_DIR%\assets\ /E /I /Q >nul

echo     - Copying templates/
xcopy templates %PLUGIN_DIR%\templates\ /E /I /Q >nul

echo     - Copying vendor/ (production only)
xcopy vendor %PLUGIN_DIR%\vendor\ /E /I /Q >nul

echo [4/6] Removing unnecessary files...
REM Remove development files from the build
if exist %PLUGIN_DIR%\.git rmdir /s /q %PLUGIN_DIR%\.git
if exist %PLUGIN_DIR%\.gitignore del /f /q %PLUGIN_DIR%\.gitignore
if exist %PLUGIN_DIR%\tests rmdir /s /q %PLUGIN_DIR%\tests
if exist %PLUGIN_DIR%\README.md del /f /q %PLUGIN_DIR%\README.md
if exist %PLUGIN_DIR%\MIGRATION-GUIDE.md del /f /q %PLUGIN_DIR%\MIGRATION-GUIDE.md
if exist %PLUGIN_DIR%\phpunit.xml del /f /q %PLUGIN_DIR%\phpunit.xml
if exist %PLUGIN_DIR%\.phpcs.xml del /f /q %PLUGIN_DIR%\.phpcs.xml
if exist %PLUGIN_DIR%\composer.json del /f /q %PLUGIN_DIR%\composer.json
if exist %PLUGIN_DIR%\composer.lock del /f /q %PLUGIN_DIR%\composer.lock
if exist %PLUGIN_DIR%\build.bat del /f /q %PLUGIN_DIR%\build.bat
if exist %PLUGIN_DIR%\build.sh del /f /q %PLUGIN_DIR%\build.sh

REM Remove .DS_Store files (Mac)
del /s /q %PLUGIN_DIR%\.DS_Store >nul 2>&1

REM Remove log files
del /s /q %PLUGIN_DIR%\*.log >nul 2>&1

echo [5/6] Creating production ZIP file...
REM Create ZIP using PowerShell (Windows built-in)
powershell -Command "Compress-Archive -Path '%BUILD_DIR%\%PLUGIN_NAME%' -DestinationPath '%PLUGIN_NAME%-v%VERSION%.zip' -Force"
if errorlevel 1 (
    echo ERROR: ZIP creation failed!
    echo NOTE: You may need to install 7-Zip or WinRAR and create the ZIP manually.
    pause
    exit /b 1
)

echo [6/6] Verifying ZIP file...
if exist %PLUGIN_NAME%-v%VERSION%.zip (
    echo.
    echo ============================================================
    echo   SUCCESS! Production ZIP created successfully!
    echo ============================================================
    echo.
    echo   File: %PLUGIN_NAME%-v%VERSION%.zip
    echo   Location: %CD%\%PLUGIN_NAME%-v%VERSION%.zip
    echo.
    echo   This ZIP is ready for WordPress.org submission!
    echo.
    echo   Build directory: %BUILD_DIR%\%PLUGIN_NAME%\
    echo   (You can review the contents before submission)
    echo.
    echo ============================================================
) else (
    echo ERROR: ZIP file was not created!
)

echo.
echo NOTE: Don't forget to restore dev dependencies after building:
echo   composer install
echo.

pause
