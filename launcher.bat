@echo off
REM GoSort Launcher - Choose which script to run

:menu
cls
echo.
echo ==========================================
echo GoSort Program Testing Launcher
echo ==========================================
echo.
echo Simulation files are made for testing network requests and SQL queries.
echo.
echo NOTE: If you want to simulate multiple Sorter Devices on one server,
echo       run 'GoSort_Simulation' in multiple directories.
echo       (Arduino can't be called multiple times. Camera can't be initialized multiple times.)
echo.
echo ==========================================
echo.
echo 1. GoSort
echo    Run if you have Arduino (without detection)
echo.
echo 2. GoSort_Detect
echo    Run if you have Arduino (with detection)
echo.
echo 3. GoSort_Simulation
echo    Run if you don't have Arduino (without detection)
echo.
echo 4. GoSort_Detect_Simulation
echo    Run if you don't have Arduino (with detection)
echo.
echo 5. Exit
echo.
echo ==========================================
echo.

set /p choice="Enter your choice (1-5): "

if "%choice%"=="1" (
    python GoSort.py
    goto menu
) else if "%choice%"=="2" (
    python GoSort_Detect.py
    goto menu
) else if "%choice%"=="3" (
    python GoSort_Simulation.py
    goto menu
) else if "%choice%"=="4" (
    python GoSort_Detect_Simulation.py
    goto menu
) else if "%choice%"=="5" (
    exit /b 0
) else (
    echo Invalid choice. Please try again.
    timeout /t 2 >nul
    goto menu
)
