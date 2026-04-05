@echo off
REM GoSort Object Detection Inference UI
REM Run this to launch the object detection inference app with GUI

cd /d "%~dp0"
echo Starting GoSort Object Detection Inference App...
python GoSort_Inference_UI.py
pause
