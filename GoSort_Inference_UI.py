"""
GoSort Object Detection Inference App with GUI
Detects waste objects in images and displays results
"""

import tkinter as tk
from tkinter import filedialog, messagebox, ttk
import cv2
from PIL import Image, ImageTk
import numpy as np
from ultralytics import YOLO
import json
import os
import threading
from pathlib import Path

class ObjectDetectionApp:
    def __init__(self, root):
        self.root = root
        self.root.title("GoSort - Object Detection Inference")
        self.root.geometry("1200x800")
        self.root.configure(bg="#f0f0f0")
        
        # Load categories and config
        self.categories = self.load_categories()
        self.model = None
        self.current_image = None
        self.current_image_path = None
        self.detection_results = None
        
        # Color map for categories
        self.category_colors = {
            'bio': (0, 255, 0),      # Green
            'nbio': (0, 165, 255),   # Orange
            'hazardous': (0, 0, 255),  # Red
            'mixed': (255, 0, 255)   # Magenta
        }
        
        self.category_reverse_map = {}
        for category, items in self.categories.items():
            for item in items:
                self.category_reverse_map[item] = category
        
        self.setup_ui()
        self.load_model()
    
    def load_categories(self):
        """Load categories from JSON file"""
        categories_file = 'categories.json'
        if os.path.exists(categories_file):
            with open(categories_file, 'r') as f:
                return json.load(f)
        return {'bio': [], 'nbio': [], 'hazardous': [], 'mixed': []}
    
    def setup_ui(self):
        """Setup the user interface"""
        # Top control panel
        control_frame = ttk.Frame(self.root)
        control_frame.pack(side=tk.TOP, fill=tk.X, padx=10, pady=10)
        
        # Upload button
        upload_btn = ttk.Button(control_frame, text="📁 Upload Image", command=self.upload_image)
        upload_btn.pack(side=tk.LEFT, padx=5)
        
        # Detect button
        self.detect_btn = ttk.Button(control_frame, text="🔍 Detect Objects", command=self.run_detection)
        self.detect_btn.pack(side=tk.LEFT, padx=5)
        self.detect_btn.config(state=tk.DISABLED)
        
        # Status label
        self.status_label = ttk.Label(control_frame, text="Ready", foreground="blue")
        self.status_label.pack(side=tk.LEFT, padx=20)
        
        # Main content area
        content_frame = ttk.Frame(self.root)
        content_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Image display area
        image_frame = ttk.LabelFrame(content_frame, text="Image Preview", height=500)
        image_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=5)
        
        self.image_label = ttk.Label(image_frame, background="#e0e0e0")
        self.image_label.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Results panel
        results_frame = ttk.LabelFrame(content_frame, text="Detection Results", width=300)
        results_frame.pack(side=tk.RIGHT, fill=tk.BOTH, padx=5)
        results_frame.pack_propagate(False)
        
        # Results text area
        self.results_text = tk.Text(results_frame, height=30, width=40, font=("Courier", 9))
        self.results_text.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Configure text tags for colors
        self.results_text.tag_configure("bio", foreground="green", font=("Courier", 9, "bold"))
        self.results_text.tag_configure("nbio", foreground="darkorange", font=("Courier", 9, "bold"))
        self.results_text.tag_configure("hazardous", foreground="red", font=("Courier", 9, "bold"))
        self.results_text.tag_configure("mixed", foreground="magenta", font=("Courier", 9, "bold"))
        self.results_text.tag_configure("header", foreground="darkblue", font=("Courier", 10, "bold"))
        
        # Status bar
        status_bar = ttk.Frame(self.root)
        status_bar.pack(side=tk.BOTTOM, fill=tk.X)
        self.model_status = ttk.Label(status_bar, text="Model: Loading...", relief=tk.SUNKEN)
        self.model_status.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=5, pady=5)
    
    def load_model(self):
        """Load YOLO model in background thread"""
        def load():
            try:
                self.status_label.config(text="Loading model...", foreground="orange")
                self.root.update()
                
                # Try to load the best model
                model_path = 'weights_11.pt'
           
                
                self.model = YOLO(model_path)
                self.model_status.config(text=f"Model: {model_path} loaded successfully")
                self.status_label.config(text="Ready", foreground="blue")
            except Exception as e:
                self.model_status.config(text=f"Error loading model: {str(e)}")
                self.status_label.config(text="Error loading model", foreground="red")
                messagebox.showerror("Model Load Error", f"Failed to load model:\n{str(e)}")
        
        thread = threading.Thread(target=load, daemon=True)
        thread.start()
    
    def upload_image(self):
        """Open file dialog to upload an image"""
        file_path = filedialog.askopenfilename(
            title="Select an image",
            filetypes=[("Image files", "*.jpg *.jpeg *.png *.bmp *.gif"), ("All files", "*.*")]
        )
        
        if file_path:
            self.current_image_path = file_path
            self.display_image(file_path)
            self.detect_btn.config(state=tk.NORMAL)
            self.status_label.config(text=f"Image loaded: {Path(file_path).name}", foreground="blue")
            self.results_text.delete(1.0, tk.END)
            self.detection_results = None
    
    def display_image(self, image_path):
        """Display image in the UI"""
        try:
            img = Image.open(image_path)
            # Resize to fit in frame
            img.thumbnail((600, 500), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            
            self.image_label.config(image=photo)
            self.image_label.image = photo
            self.current_image = img
        except Exception as e:
            messagebox.showerror("Error", f"Failed to load image: {str(e)}")
    
    def run_detection(self):
        """Run object detection in background thread"""
        if not self.current_image_path or not self.model:
            messagebox.showerror("Error", "Please load an image and model first")
            return
        
        self.detect_btn.config(state=tk.DISABLED)
        self.status_label.config(text="Running detection...", foreground="orange")
        
        def detect():
            try:
                # Run YOLO detection
                results = self.model.predict(self.current_image_path, conf=0.5, verbose=False)
                
                if results:
                    self.detection_results = results[0]
                    self.display_results_with_boxes()
                    self.status_label.config(text="Detection completed", foreground="blue")
                else:
                    self.status_label.config(text="No objects detected", foreground="gray")
                    self.results_text.delete(1.0, tk.END)
                    self.results_text.insert(tk.END, "No objects detected in the image", "header")
            except Exception as e:
                self.status_label.config(text=f"Detection error: {str(e)}", foreground="red")
                messagebox.showerror("Detection Error", f"Failed to run detection:\n{str(e)}")
            finally:
                self.detect_btn.config(state=tk.NORMAL)
        
        thread = threading.Thread(target=detect, daemon=True)
        thread.start()
    
    def display_results_with_boxes(self):
        """Display detection results with bounding boxes"""
        if not self.detection_results:
            return
        
        # Load original image for drawing
        img = cv2.imread(self.current_image_path)
        img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        
        # Parse detections
        detections = []
        for box in self.detection_results.boxes:
            conf = float(box.conf[0])
            cls = int(box.cls[0])
            
            # Get class name
            class_name = self.model.names[cls] if cls < len(self.model.names) else f"Class {cls}"
            
            # Get category
            category = self.category_reverse_map.get(class_name, 'mixed')
            
            # Get coordinates
            x1, y1, x2, y2 = box.xyxy[0]
            x1, y1, x2, y2 = int(x1), int(y1), int(x2), int(y2)
            
            detections.append({
                'class': class_name,
                'category': category,
                'confidence': conf,
                'coords': (x1, y1, x2, y2)
            })
            
            # Draw bounding box
            color = self.category_colors.get(category, (255, 255, 255))
            cv2.rectangle(img, (x1, y1), (x2, y2), color, 2)
            
            # Draw label
            label = f"{class_name} ({conf:.2f})"
            cv2.putText(img, label, (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
        
        # Display image with boxes
        img_pil = Image.fromarray(img)
        img_pil.thumbnail((600, 500), Image.Resampling.LANCZOS)
        photo = ImageTk.PhotoImage(img_pil)
        
        self.image_label.config(image=photo)
        self.image_label.image = photo
        
        # Display results in text area
        self.results_text.delete(1.0, tk.END)
        self.results_text.insert(tk.END, "DETECTION RESULTS\n", "header")
        self.results_text.insert(tk.END, "=" * 40 + "\n\n")
        
        if not detections:
            self.results_text.insert(tk.END, "No objects detected")
            return
        
        # Group by category
        by_category = {}
        for detection in detections:
            cat = detection['category']
            if cat not in by_category:
                by_category[cat] = []
            by_category[cat].append(detection)
        
        # Display grouped results
        for category in ['bio', 'nbio', 'hazardous', 'mixed']:
            if category in by_category:
                items = by_category[category]
                self.results_text.insert(tk.END, f"\n{category.upper()}\n", category)
                self.results_text.insert(tk.END, "-" * 40 + "\n")
                
                for i, detection in enumerate(items, 1):
                    label = f"{i}. {detection['class']}\n"
                    conf = f"   Confidence: {detection['confidence']:.1%}\n\n"
                    self.results_text.insert(tk.END, label, category)
                    self.results_text.insert(tk.END, conf)
        
        # Summary
        self.results_text.insert(tk.END, "\n" + "=" * 40 + "\n")
        self.results_text.insert(tk.END, "SUMMARY\n", "header")
        self.results_text.insert(tk.END, f"Total objects: {len(detections)}\n")
        for category in ['bio', 'nbio', 'hazardous', 'mixed']:
            count = len(by_category.get(category, []))
            if count > 0:
                self.results_text.insert(tk.END, f"{category.upper()}: {count}\n", category)


def main():
    root = tk.Tk()
    app = ObjectDetectionApp(root)
    root.mainloop()


if __name__ == "__main__":
    main()
