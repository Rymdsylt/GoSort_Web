@echo off
REM GoSort Python Environment Installer

REM 1. Check for Python installation
where python >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo Python is not installed. Please install Python 3.10 or newer and rerun this script.
    pause
    exit /b 1
)

REM 2. Upgrade pip
echo Upgrading pip...
python -m pip install --upgrade pip

REM 3. Install required packages globally
REM You can add/remove packages as needed for GoSort_Detect.py
echo Installing required packages...
python -m pip install ultralytics torch opencv-python numpy requests pyserial pillow pygame

echo.
echo Installation complete!
echo.
pause
