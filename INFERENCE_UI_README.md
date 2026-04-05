# GoSort Object Detection Inference UI

A desktop application for detecting waste objects in images using YOLOv8 and your GoSort models.

## Features

✨ **Easy Image Upload** - Select any image from your computer
🔍 **Real-time Object Detection** - Detects waste objects using your trained YOLOv8 models
🎨 **Visual Results** - Displays bounding boxes with color-coded waste categories:
  - 🟢 **BIO** (Biodegradable) - Green
  - 🟠 **NBIO** (Non-biodegradable) - Orange  
  - 🔴 **HAZARDOUS** - Red
  - 🟣 **MIXED** - Magenta

📊 **Detailed Results Panel** - Shows:
  - All detected objects grouped by category
  - Confidence scores for each detection
  - Summary count of detected items by category

## How to Run

### Option 1: Batch File (Quick Start)
```bash
run_inference_ui.bat
```

### Option 2: Command Line
```bash
python GoSort_Inference_UI.py
```

### Option 3: From Python IDE
Open `GoSort_Inference_UI.py` in your Python IDE and run it.

## Requirements

Make sure you have these Python packages installed:
```bash
pip install ultralytics opencv-python pillow tkinter
```

If you're using the existing environment with your detection scripts, you should already have the required packages.

## Usage

1. **Launch the app** using one of the methods above
2. **Wait** for the model to load (you'll see "Loading model..." in the status)
3. **Click "📁 Upload Image"** and select an image file
4. **Click "🔍 Detect Objects"** to run detection
5. **View Results**:
   - Left side: Original image with bounding boxes
   - Right side: Detailed list of detections grouped by waste category

## Model Selection

The app automatically looks for and loads these models (in order):
1. `best885.pt` (recommended)
2. `weights_26.pt`
3. `weights_11.pt`

If none are found, the app will show an error. Make sure one of these model files exists in the GoSort_Web directory.

## Supported Image Formats

- JPEG (.jpg, .jpeg)
- PNG (.png)
- BMP (.bmp)
- GIF (.gif)

## Troubleshooting

**"Error loading model"**
- Make sure one of the `.pt` model files exists in the directory
- Check that ultralytics is properly installed: `pip install --upgrade ultralytics`

**"No objects detected"**
- The model didn't find any waste objects in the image
- Try with a different image containing waste items

**Window won't open**
- Make sure tkinter is installed (usually comes with Python)
- On Linux: `sudo apt-get install python3-tk`

## Notes

- Processing speed depends on your CPU/GPU and image size
- The app uses a confidence threshold of 50% for detections
- All detected objects are saved to a visual report on the image

Enjoy sorting! ♻️
