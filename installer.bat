@echo off
REM GoSort Python Environment Installer

REM 1. Check for Python installation
where python >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo Python is not installed. Please install Python 3.8 or newer and rerun this script.
    pause
    exit /b 1
)

REM 2. Create virtual environment (optional, but recommended)
if not exist venv (
    echo Creating Python virtual environment...
    python -m venv venv
)

REM 3. Activate virtual environment
call venv\Scripts\activate.bat

REM 4. Upgrade pip
python -m pip install --upgrade pip

REM 5. Install required packages
REM You can add/remove packages as needed for GoSort_Detect.py
python -m pip install ultralytics torch opencv-python numpy requests pyserial pillow pygame cpuinfo

REM 6. Deactivate virtual environment
REM deactivate

echo.
echo Installation complete!
echo To activate the environment in the future, run:
echo     venv\Scripts\activate.bat
echo.
pause
