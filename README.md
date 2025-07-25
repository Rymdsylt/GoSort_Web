# GoSort_Web: Intelligent Waste Segregation System

> ⚠️ **Note:** This project is currently under construction.

A web interface for the automated waste segregation system that combines computer vision, robotics, and data management to intelligently sort waste materials. Part of the [GoSort Tools](https://github.com/Rymdsylt/GoSort_Tools) ecosystem.

## System Architecture

### 🤖 Hardware Integration
- **Servo Control:**
  - Pan servo (D8) and Tilt servo (D9) for precise waste placement
  - LCD Display with I2C module for system status and feedback

### 🔄 Communication Layer
- **Arduino Interface:**
  - Bi-directional serial communication with Python backend
  - Real-time control and system feedback
  - Arduino Mega 2560 handles hardware control commands

### 📊 Data Management
- **Database Features:**
  - Real-time sorting operation logging
  - Administrative monitoring dashboard
  - Analytics and reporting system
  - System performance tracking

## Project Structure
```
├── GoSort_Main.php        # Main web interface
├── gosort_config.json     # System configuration
├── gs_DB/                 # Database integration
├── css/                   # UI styling
└── js/                    # Frontend logic
```

## Developer Notes
- Web interface provides real-time monitoring and control
- Computer vision system for waste classification
- Automated sorting mechanism control
- Comprehensive data logging and analytics

## Author
Rymdsylt
