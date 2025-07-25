GoSort: Intelligent Waste Segregation System

To Test the Application:
Simply run run.bat. All necessary setup and instructions are handled inside the batch file.

🔧 FOR DEVELOPERS:
🟦 Arduino Setup
Pan Servo: Connected to D8

Tilt Servo: Connected to D9

LCD Display: Use 1602 LCD with I2C module for simplified wiring and control.

🔁 Python ↔ Arduino Communication
Communication is handled via serial connection.

The Arduino Mega 2560 receives serialized data from the Python application and executes commands accordingly.

Serial data is also sent back to Python for real-time debugging and system feedback.

🌐 Python ↔ XAMPP Server (MySQL Database)
After each successful sorting operation, relevant data is automatically sent to the database.

This enables admin monitoring, system analytics, and report generation through the GoSort admin panel.

