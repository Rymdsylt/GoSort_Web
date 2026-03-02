from ultralytics import YOLO
import cv2
import numpy as np
from threading import Thread
from queue import Queue
import time
import torch
import json
import os

def load_categories():
    """Load waste categories mapping"""
    categories_file = 'categories.json'
    if os.path.exists(categories_file):
        with open(categories_file, 'r') as f:
            return json.load(f)
    return {}

class VideoStream:
    """Threaded video stream reader"""
    def __init__(self, src=0):
        self.stream = cv2.VideoCapture(src, cv2.CAP_DSHOW)
        if not self.stream.isOpened():
            self.stream = cv2.VideoCapture(src)
        
        if self.stream.isOpened():
            self.stream.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            self.stream.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
            self.stream.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
            self.stream.set(cv2.CAP_PROP_FPS, 30)
        
        self.stopped = False
        self.Q = Queue(maxsize=2)

    def start(self):
        thread = Thread(target=self.update, args=(), daemon=True)
        thread.start()
        return self

    def update(self):
        while True:
            if self.stopped:
                return

            ret, frame = self.stream.read()
            if not ret:
                self.stop()
                return
            
            if self.Q.full():
                try:
                    self.Q.get_nowait()
                except:
                    pass
            
            try:
                self.Q.put_nowait(frame)
            except:
                pass

    def read(self):
        return self.Q.get()

    def stop(self):
        self.stopped = True
        self.stream.release()

def list_available_cameras(max_cams=10):
    """Find available cameras"""
    available = []
    
    print("Scanning for available cameras...")
    
    for i in range(max_cams):
        cap = cv2.VideoCapture(i)
        if cap.isOpened():
            ret, frame = cap.read()
            if ret and frame is not None and frame.size > 0:
                print(f"✅ Camera {i} is available")
                available.append(i)
            cap.release()
        
        time.sleep(0.1)
    
    if available:
        print(f"🎥 Found {len(available)} camera(s): {available}\n")
    else:
        print("❌ No cameras found!")
    
    return available

def main():
    print("GoSort Simple Detection System")
    print("=" * 50)
    
    # Check GPU availability
    gpu_available = torch.cuda.is_available()
    if gpu_available:
        device = torch.device('cuda')
        device_name = torch.cuda.get_device_name(0)
        print(f"✅ GPU Available: {device_name}")
    else:
        device = torch.device('cpu')
        print("⚠️  GPU not available - using CPU")
    
    # Load YOLO model
    print("\n📦 Loading YOLO model...")
    model = YOLO('best885.pt')
    if gpu_available:
        model.to('cuda')
    model.conf = 0.50
    model.iou = 0.50
    print("✅ Model loaded")
    
    # Find cameras
    print("\n🎥 Looking for cameras...")
    available_cams = list_available_cameras()
    
    if not available_cams:
        print("No cameras found. Exiting.")
        return
    
    # Start video stream
    cam_index = available_cams[0]
    print(f"📹 Starting video stream from camera {cam_index}...")
    vs = VideoStream(cam_index)
    stream = vs.start()
    time.sleep(1.0)
    
    # Load categories
    categories = load_categories()
    print(f"📋 Loaded {len(categories)} waste categories")
    print(f"   Categories: {list(categories.keys())}\n")
    
    # FPS tracking
    fps = 0
    fps_time = time.time()
    frame_count = 0
    
    print("Starting detection (press 'q' or ESC to exit)...")
    print("-" * 50)
    
    try:
        while True:
            frame = stream.read()
            frame_count += 1
            
            # Run YOLO detection
            results = model.predict(frame, stream=False)
            
            # Update FPS
            current_time = time.time()
            if current_time - fps_time >= 1.0:
                fps = frame_count
                frame_count = 0
                fps_time = current_time
            
            # Draw detections
            for result in results:
                boxes = result.boxes.cpu().numpy()
                for box in boxes:
                    x1, y1, x2, y2 = box.xyxy[0].astype(int)
                    conf = box.conf[0]
                    class_id = int(box.cls[0])
                    detected_item = model.names[class_id]
                    
                    # Map to category
                    class_name = None
                    for category, items in categories.items():
                        if detected_item.lower() in [item.lower() for item in items]:
                            class_name = category
                            break
                    
                    if class_name is None:
                        class_name = "unknown"
                    
                    # Draw bounding box
                    cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)
                    
                    # Draw label with confidence and category
                    label = f"{detected_item} ({conf:.2f}) [{class_name}]"
                    cv2.putText(frame, label, (x1, y1 - 10),
                              cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)
                    
                    if conf > 0.50:
                        print(f"✅ {detected_item} - Confidence: {conf:.2f} - Category: {class_name}")
            
            # Display FPS
            cv2.putText(frame, f"FPS: {fps}", (10, 30),
                       cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
            
            # Display device info
            device_text = f"GPU: {device_name}" if gpu_available else "CPU"
            cv2.putText(frame, device_text, (10, 70),
                       cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
            
            # Show frame
            cv2.imshow("GoSort Detection", frame)
            
            # Check for quit
            key = cv2.waitKey(1) & 0xFF
            if key == ord('q') or key == 27:  # q or ESC
                print("\n👋 Exiting...")
                break
    
    except KeyboardInterrupt:
        print("\n⚠️  Interrupted by user")
    
    finally:
        print("Cleaning up...")
        stream.stop()
        cv2.destroyAllWindows()
        print("✅ Done")

if __name__ == "__main__":
    main()
