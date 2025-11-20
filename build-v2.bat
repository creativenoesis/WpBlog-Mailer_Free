@echo off
REM ============================================================
REM CN Blog Mailer - Production Build Script v2 (Windows)
REM Fixed ZIP structure for WordPress compatibility
REM ============================================================

echo.
echo ============================================================
echo   CN Blog Mailer - Production Build Script v2
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
if exist %PLUGIN_NAME%-v%VERSION%.zip (
    del /f /q %PLUGIN_NAME%-v%VERSION%.zip
)
mkdir %BUILD_DIR%
mkdir %PLUGIN_DIR%

echo [2/6] Installing production dependencies...
call composer install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 (
    echo ERROR: Composer install failed!
    pause
    exit /b 1
)

echo [3/6] Copying plugin files...
REM Copy main plugin files
copy wp-blog-mailer.php %PLUGIN_DIR%\ >nul 2>&1
copy readme.txt %PLUGIN_DIR%\ >nul 2>&1
copy LICENSE %PLUGIN_DIR%\ >nul 2>&1
copy uninstall.php %PLUGIN_DIR%\ >nul 2>&1

REM Copy directories with error checking
echo     - Copying includes/
if exist includes (
    xcopy includes %PLUGIN_DIR%\includes\ /E /I /Q /Y >nul 2>&1
) else (
    echo       WARNING: includes/ folder not found!
)

echo     - Copying assets/
if exist assets (
    xcopy assets %PLUGIN_DIR%\assets\ /E /I /Q /Y >nul 2>&1
) else (
    echo       WARNING: assets/ folder not found!
)

echo     - Copying templates/
if exist templates (
    xcopy templates %PLUGIN_DIR%\templates\ /E /I /Q /Y >nul 2>&1
) else (
    echo       WARNING: templates/ folder not found!
)

echo     - Copying vendor/ (production only)
if exist vendor (
    xcopy vendor %PLUGIN_DIR%\vendor\ /E /I /Q /Y >nul 2>&1
) else (
    echo       WARNING: vendor/ folder not found!
)

echo [4/6] Removing unnecessary files...
REM Remove development files from the build
if exist %PLUGIN_DIR%\.git rmdir /s /q %PLUGIN_DIR%\.git
if exist %PLUGIN_DIR%\.gitignore del /f /q %PLUGIN_DIR%\.gitignore
if exist %PLUGIN_DIR%\tests rmdir /s /q %PLUGIN_DIR%\tests
if exist %PLUGIN_DIR%\README.md del /f /q %PLUGIN_DIR%\README.md
if exist %PLUGIN_DIR%\MIGRATION-GUIDE.md del /f /q %PLUGIN_DIR%\MIGRATION-GUIDE.md
if exist %PLUGIN_DIR%\BUILD-README.md del /f /q %PLUGIN_DIR%\BUILD-README.md
if exist %PLUGIN_DIR%\phpunit.xml del /f /q %PLUGIN_DIR%\phpunit.xml
if exist %PLUGIN_DIR%\.phpcs.xml del /f /q %PLUGIN_DIR%\.phpcs.xml
if exist %PLUGIN_DIR%\composer.json del /f /q %PLUGIN_DIR%\composer.json
if exist %PLUGIN_DIR%\composer.lock del /f /q %PLUGIN_DIR%\composer.lock
if exist %PLUGIN_DIR%\build.bat del /f /q %PLUGIN_DIR%\build.bat
if exist %PLUGIN_DIR%\build.sh del /f /q %PLUGIN_DIR%\build.sh
if exist %PLUGIN_DIR%\build-v2.bat del /f /q %PLUGIN_DIR%\build-v2.bat
if exist %PLUGIN_DIR%\build-manual.bat del /f /q %PLUGIN_DIR%\build-manual.bat

REM Remove .DS_Store files (Mac)
del /s /q %PLUGIN_DIR%\.DS_Store >nul 2>&1

REM Remove log files
del /s /q %PLUGIN_DIR%\*.log >nul 2>&1

echo [5/6] Verifying build contents...
if not exist %PLUGIN_DIR%\wp-blog-mailer.php (
    echo ERROR: Main plugin file missing!
    pause
    exit /b 1
)
if not exist %PLUGIN_DIR%\readme.txt (
    echo ERROR: readme.txt missing!
    pause
    exit /b 1
)
if not exist %PLUGIN_DIR%\assets (
    echo ERROR: assets/ folder missing!
    pause
    exit /b 1
)
echo     - Build verification passed!

echo [6/6] Creating production ZIP file...
REM Use PowerShell with explicit path handling
powershell -Command "$source = Join-Path $PWD '%BUILD_DIR%\%PLUGIN_NAME%'; $dest = Join-Path $PWD '%PLUGIN_NAME%-v%VERSION%.zip'; if (Test-Path $source) { Compress-Archive -Path $source -DestinationPath $dest -Force; Write-Host 'ZIP created successfully' } else { Write-Host 'ERROR: Source folder not found'; exit 1 }"

if errorlevel 1 (
    echo.
    echo ERROR: ZIP creation failed via PowerShell!
    echo.
    echo MANUAL SOLUTION:
    echo   1. Navigate to: %CD%\build\
    echo   2. Right-click on 'cn-blog-mailer' folder
    echo   3. Select "Send to -> Compressed (zipped) folder"
    echo   4. Rename the ZIP to: cn-blog-mailer-v1.0.0.zip
    echo   5. Move it to: %CD%\
    echo.
    pause
    exit /b 1
)

REM Final verification
if exist %PLUGIN_NAME%-v%VERSION%.zip (
    echo.
    echo ============================================================
    echo   SUCCESS! Production ZIP created successfully!
    echo ============================================================
    echo.
    echo   File: %PLUGIN_NAME%-v%VERSION%.zip
    echo   Location: %CD%\%PLUGIN_NAME%-v%VERSION%.zip
    echo.

    REM Show file size
    for %%A in (%PLUGIN_NAME%-v%VERSION%.zip) do echo   Size: %%~zA bytes

    echo.
    echo   This ZIP is ready for WordPress.org submission!
    echo.
    echo   Build directory: %BUILD_DIR%\%PLUGIN_NAME%\
    echo   (You can review the contents before submission)
    echo.
    echo ============================================================
) else (
    echo.
    echo ============================================================
    echo   ERROR: ZIP file was not created!
    echo ============================================================
    echo.
    echo   The build folder exists at: %CD%\%BUILD_DIR%\%PLUGIN_NAME%\
    echo   Please create the ZIP manually:
    echo     1. Right-click on the 'build\cn-blog-mailer' folder
    echo     2. Select "Send to -> Compressed (zipped) folder"
    echo     3. Rename it to: cn-blog-mailer-v1.0.0.zip
    echo.
    pause
    exit /b 1
)

echo.
echo NOTE: Don't forget to restore dev dependencies after building:
echo   composer install
echo.

pause
