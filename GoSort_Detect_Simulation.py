"""
GoSort_Detect_Simulation.py
A simulation version of GoSort_Detect.py that does not require Arduino hardware.
Simulates detection and sorting logic for development/testing without serial/Arduino dependencies.
"""

import os
import sys
import time
import json
import base64
import requests
import threading
import numpy as np
import cv2
import torch
from ultralytics import YOLO
from datetime import datetime

try:
    from PIL import Image, ImageDraw, ImageFont
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False

# Simulate audio (no pygame)
def play_sorting_audio(waste_type):
    print(f"[SIM] Would play audio for: {waste_type}")

def load_categories():
    categories_file = 'categories.json'
    if os.path.exists(categories_file):
        with open(categories_file, 'r') as f:
            return json.load(f)
    return {}

def load_config():
    config_file = 'gosort_config.json'
    if os.path.exists(config_file):
        with open(config_file, 'r') as f:
            return json.load(f)
    return {'ip_address': None, 'sorter_id': None}

def save_config(config):
    with open('gosort_config.json', 'w') as f:
        json.dump(config, f)

def check_server_connection(ip_address):
    try:
        url = f"http://{ip_address}/GoSort_Web/gs_DB/verify_sorter.php"
        response = requests.post(url, json={'identity': ''})
        return response.status_code == 200
    except:
        return False

def get_ip_address():
    config = load_config()
    ip = config.get('ip_address')
    if not ip:
        ip = input("Enter GoSort server IP address (simulation): ")
        config['ip_address'] = ip
        save_config(config)
    return ip

def map_category_to_command(waste_type, mapping):
    waste_type = waste_type.lower()
    for cmd, typ in mapping.items():
        if typ.lower() == waste_type:
            return cmd
    default_commands = {
        'bio': 'zdeg',
        'nbio': 'ndeg',
        'hazardous': 'odeg',
        'mixed': 'mdeg'
    }
    return default_commands.get(waste_type, 'ndeg')

def draw_text_with_font(img, text, position, font_size, color, use_poppins=True):
    if PIL_AVAILABLE and use_poppins:
        try:
            font_paths = [
                'fonts/Poppins-Regular.ttf',
                'fonts/Poppins-SemiBold.ttf',
                'C:/Windows/Fonts/poppins.ttf',
                'C:/Windows/Fonts/Poppins-Regular.ttf',
            ]
            font = None
            for path in font_paths:
                if os.path.exists(path):
                    try:
                        font = ImageFont.truetype(path, font_size)
                        break
                    except:
                        continue
            if font is None:
                try:
                    font = ImageFont.truetype("arial.ttf", font_size)
                except:
                    font = ImageFont.load_default()
            img_pil = Image.fromarray(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
            draw = ImageDraw.Draw(img_pil)
            color_rgb = (color[2], color[1], color[0])
            draw.text(position, text, font=font, fill=color_rgb)
            img = cv2.cvtColor(np.array(img_pil), cv2.COLOR_RGB2BGR)
            return img
        except Exception:
            pass
    scale = font_size / 30.0
    cv2.putText(img, text, position, cv2.FONT_HERSHEY_SIMPLEX, scale, color, 2)
    return img

def draw_kiosk_ui(sorting_history, current_view='both', kiosk_width=1280, kiosk_height=720):
    bg_color = (239, 243, 243)
    primary_green = (23, 74, 39)
    dark_gray = (55, 47, 31)
    medium_gray = (128, 114, 107)
    waste_colors = {
        'bio': (129, 185, 16),
        'nbio': (68, 68, 239),
        'hazardous': (11, 158, 245),
        'mixed': (128, 114, 107)
    }
    waste_names = {
        'bio': 'Biodegradable',
        'nbio': 'Non-Biodegradable',
        'hazardous': 'Hazardous',
        'mixed': 'Mixed'
    }
    kiosk_frame = np.full((kiosk_height, kiosk_width, 3), bg_color, dtype=np.uint8)
    header_height = 80
    cv2.rectangle(kiosk_frame, (0, 0), (kiosk_width, header_height), primary_green, -1)
    kiosk_frame = draw_text_with_font(kiosk_frame, "GoSort (Simulation)", (40, 30), 36, (255, 255, 255))
    view_text = "Simulation Mode"
    kiosk_frame = draw_text_with_font(kiosk_frame, view_text, (kiosk_width - 250, 30), 20, (200, 200, 200))
    if not sorting_history:
        center_x = kiosk_width // 2
        center_y = kiosk_height // 2
        no_items_text = "No items sorted yet"
        text_size = cv2.getTextSize(no_items_text, cv2.FONT_HERSHEY_SIMPLEX, 1.2, 2)[0]
        text_x = center_x - text_size[0] // 2
        kiosk_frame = draw_text_with_font(kiosk_frame, no_items_text, (text_x, center_y), 36, medium_gray)
    else:
        last_item = sorting_history[0]
        waste_type = last_item.get('waste_type', 'nbio')
        waste_label = waste_names.get(waste_type, 'Unknown')
        color = waste_colors.get(waste_type, medium_gray)
        center_x = kiosk_width // 2
        center_y = kiosk_height // 2 - 30
        sorted_text = "Sorted:"
        sorted_width = cv2.getTextSize(sorted_text, cv2.FONT_HERSHEY_SIMPLEX, 2.0, 3)[0][0]
        waste_width = cv2.getTextSize(waste_label, cv2.FONT_HERSHEY_SIMPLEX, 2.0, 3)[0][0]
        total_width = sorted_width + waste_width + 20
        start_x = center_x - total_width // 2
        kiosk_frame = draw_text_with_font(kiosk_frame, sorted_text, (start_x, center_y), 60, dark_gray)
        waste_x = start_x + sorted_width + 20
        kiosk_frame = draw_text_with_font(kiosk_frame, waste_label, (waste_x, center_y), 60, color)
        nice_day_y = center_y + 100
        nice_day_text = "Have a nice day"
        nice_day_width = cv2.getTextSize(nice_day_text, cv2.FONT_HERSHEY_SIMPLEX, 1.3, 2)[0][0]
        nice_day_x = center_x - nice_day_width // 2
        kiosk_frame = draw_text_with_font(kiosk_frame, nice_day_text, (nice_day_x, nice_day_y), 40, medium_gray)
    return kiosk_frame

def main():
    config = load_config()
    ip_address = get_ip_address()
    config['ip_address'] = ip_address
    save_config(config)
    print(f"\n[SIM] Using GoSort server at: {ip_address}")
    sorting_history = []
    current_view = 'kiosk'
    if config.get('sorter_id') is None:
        print("\nFirst time setup - Sorter Identity Configuration")
        sorter_id = input("Enter Sorter Identity (e.g., Sorter1): ")
        config['sorter_id'] = sorter_id
        save_config(config)
    sorter_id = config.get('sorter_id')
    print(f"[SIM] Using Sorter Identity: {sorter_id}")
    print("\n[SIM] Verifying server connection...")
    if not check_server_connection(ip_address):
        print("[SIM] Failed to connect to the server (simulation continues anyway)")
    print("\n[SIM] Device registration simulated.")
    if config.get('device_mode') is None:
        print("\nDevice Mode Configuration")
        gpu_available = torch.cuda.is_available()
        if gpu_available:
            print("Select device mode:")
            print("1. GPU (CUDA)")
            print("2. CPU")
            while True:
                choice = input("Enter 1 for GPU or 2 for CPU: ").strip()
                if choice == '1':
                    config['device_mode'] = 'gpu'
                    break
                elif choice == '2':
                    config['device_mode'] = 'cpu'
                    break
                else:
                    print("Invalid input. Please enter 1 or 2.")
        else:
            print("No GPU detected. Defaulting to CPU mode.")
            config['device_mode'] = 'cpu'
        save_config(config)
    device_mode = config.get('device_mode')
    print(f"[SIM] Using device mode: {device_mode.upper()}")
    if device_mode == 'gpu' and torch.cuda.is_available():
        device = torch.device('cuda')
        backend = 'CUDA (NVIDIA GPU)'
    else:
        device = torch.device('cpu')
        backend = 'CPU'
    print(f"[SIM] Using device: {device}")
    print(f"[SIM] Backend: {backend}")
    model = YOLO('best885.pt')
    if device.type == 'cuda':
        model.to('cuda')
    model.conf = 0.50
    model.iou = 0.50
    print("[SIM] Starting video stream (webcam 0)...")
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        print("[SIM] No camera found! Exiting simulation.")
        return
    fps = 0
    fps_time = time.time()
    frame_count = 0
    mapping_url = f"http://{ip_address}/GoSort_Web/gs_DB/save_sorter_mapping.php?device_identity={sorter_id}"
    try:
        resp = requests.get(mapping_url)
        mapping = resp.json().get('mapping', {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous'})
    except Exception as e:
        print(f"[SIM] Warning: Could not fetch mapping, using default. {e}")
        mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous'}
    while True:
        ret, frame = cap.read()
        if not ret:
            print("[SIM] Camera frame not available.")
            break
        frame_count += 1
        results = model.predict(frame, stream=False)
        for result in results:
            boxes = result.boxes.cpu().numpy()
            for box in boxes:
                x1, y1, x2, y2 = box.xyxy[0].astype(int)
                conf = box.conf[0]
                class_id = int(box.cls[0])
                detected_item = model.names[class_id]
                categories = load_categories()
                class_name = None
                for category, items in categories.items():
                    if detected_item.lower() in [item.lower() for item in items]:
                        class_name = category
                        break
                if class_name is None:
                    class_name = "nbio"
                clean_frame = frame.copy()
                cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)
                cv2.putText(frame, f"{detected_item} {conf:.2f}", (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)
                if conf > 0.50:
                    command = map_category_to_command(class_name, mapping)
                    trash_type = mapping.get(command, 'nbio')
                    print(f"[SIM] Detection: {detected_item} ({conf:.2f}) - Category: {class_name}")
                    _, buffer = cv2.imencode('.jpg', clean_frame)
                    image_base64 = base64.b64encode(buffer).decode('utf-8')
                    detected_classes = [detected_item]
                    trash_class_str = ', '.join(detected_classes)
                    print(f"[SIM] Would record sorting operation: {trash_type}, {trash_class_str}, {conf:.2f}")
                    timestamp = datetime.now().strftime("%H:%M:%S")
                    sorting_history.insert(0, {
                        'waste_type': trash_type,
                        'item_name': detected_item,
                        'timestamp': timestamp,
                        'confidence': float(conf)
                    })
                    if len(sorting_history) > 20:
                        sorting_history.pop()
                    play_sorting_audio(trash_type)
        current_time = time.time()
        if current_time - fps_time >= 1.0:
            fps = frame_count
            frame_count = 0
            fps_time = current_time
        ui_panel = np.zeros((60, frame.shape[1], 3), dtype=np.uint8)
        cv2.rectangle(ui_panel, (10, 10), (150, 40), (0, 255, 0), -1)
        cv2.putText(ui_panel, "Exit", (30, 35), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)
        cv2.putText(frame, f"FPS: {fps}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
        device_text = f"GPU" if torch.cuda.is_available() else f"CPU"
        cv2.putText(frame, device_text, (10, 60), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
        combined_frame = np.vstack((frame, ui_panel))
        if current_view == 'both' or current_view == 'camera':
            cv2.imshow("YOLOv11 Detection (Simulation)", combined_frame)
        if current_view == 'both' or current_view == 'kiosk':
            kiosk_frame = draw_kiosk_ui(sorting_history, current_view)
            cv2.namedWindow("GoSort Kiosk - Sorting Display (Simulation)", cv2.WINDOW_NORMAL)
            cv2.resizeWindow("GoSort Kiosk - Sorting Display (Simulation)", 1280, 720)
            cv2.imshow("GoSort Kiosk - Sorting Display (Simulation)", kiosk_frame)
        key = cv2.waitKey(1) & 0xFF
        if key == ord('q') or key == 27:
            print("[SIM] Exiting simulation...")
            break
        def mouse_callback(event, x, y, flags, param):
            if event == cv2.EVENT_LBUTTONDOWN:
                y = y - frame.shape[0]
                if 10 <= y <= 40 and 10 <= x <= 150:
                    print("[SIM] Exit button clicked.")
                    cv2.destroyAllWindows()
                    cap.release()
                    exit()
        if current_view == 'both' or current_view == 'camera':
            try:
                cv2.setMouseCallback("YOLOv11 Detection (Simulation)", mouse_callback)
            except cv2.error:
                pass
    cap.release()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    main()
