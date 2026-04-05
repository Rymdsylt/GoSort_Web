from ultralytics import YOLO
import cv2
import numpy as np
from threading import Thread, Lock
from queue import Queue
import time
import torch
import requests
import json
import os
import base64
import socket
import concurrent.futures
import threading
import sys
import msvcrt
import platform
import cpuinfo
import random
from datetime import datetime

try:
    import tkinter as tk
    TKINTER_AVAILABLE = True
except ImportError:
    TKINTER_AVAILABLE = False

try:
    from PIL import Image, ImageDraw, ImageFont
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False

try:
    import pygame
    PYGAME_AVAILABLE = True
    pygame.mixer.init()
except ImportError:
    PYGAME_AVAILABLE = False

def is_maintenance_mode():
    return os.path.exists('python_maintenance_mode.txt')

def get_screen_resolution():
    """Detect screen resolution, default to 1920x1080 if detection fails"""
    try:
        if TKINTER_AVAILABLE:
            root = tk.Tk()
            root.withdraw()  # Hide the window
            width = root.winfo_screenwidth()
            height = root.winfo_screenheight()
            root.destroy()
            return (width, height)
    except Exception:
        pass
    
    # Fallback to common resolutions based on common aspect ratios
    return (1920, 1080)

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

def play_sorting_audio(waste_type):
    """Play audio file based on waste type in a separate thread (non-blocking)"""
    if not PYGAME_AVAILABLE:
        return
    
    def play_audio_thread():
        # Map waste types to audio files
        audio_files = {
            'bio': 'audio/biodegradable.mp3',
            'nbio': 'audio/nonbiodegradable.mp3',
            'hazardous': 'audio/hazardous.mp3',
            'mixed': 'audio/mixed.mp3'
        }
        
        audio_file = audio_files.get(waste_type)
        if audio_file and os.path.exists(audio_file):
            try:
                pygame.mixer.music.load(audio_file)
                pygame.mixer.music.play()
                print(f"🔊 Playing audio for: {waste_type}")
            except Exception as e:
                print(f"\n⚠️ Error playing audio: {e}")
    
    # Start audio in a separate thread so it doesn't block UI updates
    audio_thread = Thread(target=play_audio_thread, daemon=True)
    audio_thread.start()

def get_poppins_font(font_size):
    """Try to load Poppins font, return None if not available"""
    if not PIL_AVAILABLE:
        return None
    
    font_paths = [
        'fonts/Poppins-Regular.ttf',
        'fonts/Poppins-SemiBold.ttf',
        'C:/Windows/Fonts/poppins.ttf',
        'C:/Windows/Fonts/Poppins-Regular.ttf',
    ]
    
    for path in font_paths:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, font_size)
            except:
                continue
    
    # Try system default sans-serif
    try:
        return ImageFont.truetype("arial.ttf", font_size)
    except:
        return ImageFont.load_default()

def get_poppins_font_bold(font_size):
    if not PIL_AVAILABLE:
        return None
    font_paths = [
        'fonts/Poppins-Bold.ttf',
        'fonts/Poppins-ExtraBold.ttf',
        'fonts/Poppins-SemiBold.ttf',
        'C:/Windows/Fonts/Poppins-Bold.ttf',
    ]
    for path in font_paths:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, font_size)
            except:
                continue
    return get_poppins_font(font_size)

def get_text_size_pil(text, font):
    """Get text size using PIL font"""
    if font is None:
        return (len(text) * 20, 30)  # Rough estimate
    try:
        bbox = font.getbbox(text)
        return (bbox[2] - bbox[0], bbox[3] - bbox[1])
    except:
        return (len(text) * 20, 30)

def draw_text_with_font(img, text, position, font_size, color, bold=False):
    """Draw text with Poppins font if available, otherwise use OpenCV default"""
    if PIL_AVAILABLE:
        try:
            font = get_poppins_font_bold(font_size) if bold else get_poppins_font(font_size)
            
            # Convert OpenCV image to PIL
            img_pil = Image.fromarray(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
            draw = ImageDraw.Draw(img_pil)
            
            # Draw text - convert BGR to RGB for PIL
            color_rgb = (color[2], color[1], color[0])
            draw.text(position, text, font=font, fill=color_rgb)
            
            # Convert back to OpenCV format
            img = cv2.cvtColor(np.array(img_pil), cv2.COLOR_RGB2BGR)
            return img
        except Exception as e:
            # Fallback to OpenCV if PIL fails
            pass
    
    # Fallback to OpenCV font
    scale = font_size / 30.0
    cv2.putText(img, text, position, cv2.FONT_HERSHEY_SIMPLEX, scale, color, 2)
    return img

def get_base_path():
    """Return the fixed server URL"""
    return "https://gosort.up.railway.app/gs_DB"

def scan_network():
    # Network scanning no longer needed - using fixed server URL
    return [], []

def check_server():
    print("\rChecking server...", end="", flush=True)
    try:
        base_path = get_base_path()
        response = requests.get(f"{base_path}/trash_detected.php", timeout=5)
        # The server returns 400 with "No trash type provided" if it exists
        if response.status_code == 200 or (response.status_code == 400 and "No trash type provided" in response.text):
            print("\r✅ Server connection successful!")
            return True
        print("\r❌ GoSort server is not reachable")
        return False
    except requests.exceptions.RequestException:
        print("\r❌ GoSort server is not reachable")
        return False

def get_ip_address():
    # Fixed server URL - no configuration needed
    return get_base_path()

# Kiosk state for idle timer
_kiosk_state = {
    'last_sorted_time': None,
    'idle_timeout': 5.0,  # seconds before returning to idle
    'current_waste': None,
    'current_message': None,
}

WASTE_MESSAGES = {
    'bio': [
        'This item will be composted.',
        'Organic waste sorted correctly!',
        'This goes back to the earth.',
        'Great job sorting your organic waste!',
        'Biodegradable items help reduce landfill waste.',
    ],
    'nbio': [
        'This item will be sent for recycling.',
        'Keep plastics out of landfills.',
        'Non-biodegradable waste handled correctly.',
        'Recycling this helps the environment.',
        'Thank you for sorting responsibly.',
    ],
    'hazardous': [
        'This item needs special handling.',
        'Hazardous waste isolated safely.',
        'Handled with care. Thank you!',
        'This item requires proper disposal.',
        'Keeping hazardous waste separate protects everyone.',
    ],
    'mixed': [
        'Multiple materials detected.',
        'Mixed waste sorted for processing.',
        'Try to separate waste next time!',
        'Mixed items will be processed separately.',
        'Sorting by type helps recycling efforts.',
    ],
}

def draw_kiosk_ui(sorting_history, current_view='both', kiosk_width=1920, kiosk_height=1080):
    # Colors (BGR)
    bg_color     = (240, 247, 240)   # off-white with green tint
    white        = (255, 255, 255)
    border_color = (235, 235, 235)
    dark_text    = (31, 41, 55)
    muted        = (156, 163, 175)
    green_logo1  = (39, 74, 23)      # #274a17 dark green (GOSORT)
    green_logo2  = (66, 197, 88)     # #58C542 bright green (GO)
    green_status_bg  = (240, 250, 240)
    green_status_txt = (55, 129, 55)

    waste_colors = {
        'bio':       (24, 128, 21),   # #158018
        'nbio':      (38, 38, 220),   # #dc2626
        'hazardous': (9,  117, 180),  # #b45309
        'mixed':     (107, 107, 107),
    }
    waste_names = {
        'bio':       'Biodegradable',
        'nbio':      'Non-Biodegradable',
        'hazardous': 'Hazardous',
        'mixed':     'Mixed Waste',
    }

    # Dotted background
    kiosk_frame = np.full((kiosk_height, kiosk_width, 3), bg_color, dtype=np.uint8)
    dot_spacing = 22
    dot_color = (190, 220, 190)
    for dy in range(0, kiosk_height, dot_spacing):
        for dx in range(0, kiosk_width, dot_spacing):
            cv2.circle(kiosk_frame, (dx, dy), 1, dot_color, -1)

    # Header bar (white, subtle border)
    header_h = 90
    cv2.rectangle(kiosk_frame, (0, 0), (kiosk_width, header_h), white, -1)
    cv2.line(kiosk_frame, (0, header_h), (kiosk_width, header_h), border_color, 1)

    # GOSORT logo (GO in bright green, SORT in dark green)
    logo_x, logo_y = 60, 22
    font_logo = get_poppins_font_bold(46)
    go_text = "GO"
    go_w, _ = get_text_size_pil(go_text, font_logo)
    kiosk_frame = draw_text_with_font(kiosk_frame, go_text, (logo_x, logo_y), 46, green_logo2, bold=True)
    kiosk_frame = draw_text_with_font(kiosk_frame, "SORT", (logo_x + go_w, logo_y), 46, green_logo1, bold=True)

    # Status pill (top right)
    status_text = "Connected"
    font_status = get_poppins_font(20)
    st_w, st_h = get_text_size_pil(status_text, font_status)
    pill_pad_x, pill_pad_y = 24, 10
    pill_w = st_w + pill_pad_x * 2 + 20
    pill_h = st_h + pill_pad_y * 2
    pill_x = kiosk_width - pill_w - 60
    pill_y = (header_h - pill_h) // 2
    cv2.rectangle(kiosk_frame, (pill_x, pill_y), (pill_x + pill_w, pill_y + pill_h), green_status_bg, -1)
    cv2.rectangle(kiosk_frame, (pill_x, pill_y), (pill_x + pill_w, pill_y + pill_h), (180, 220, 180), 1)
    cv2.circle(kiosk_frame, (pill_x + pill_pad_x, pill_y + pill_h // 2), 5, green_logo2, -1)
    kiosk_frame = draw_text_with_font(kiosk_frame, status_text,
                                      (pill_x + pill_pad_x + 14, pill_y + pill_pad_y - 2),
                                      20, green_status_txt)

    # Footer
    footer_h = 50
    footer_y = kiosk_height - footer_h
    cv2.rectangle(kiosk_frame, (0, footer_y), (kiosk_width, kiosk_height), white, -1)
    cv2.line(kiosk_frame, (0, footer_y), (kiosk_width, footer_y), border_color, 1)
    kiosk_frame = draw_text_with_font(kiosk_frame, "GoSort Kiosk Display",
                                      (60, footer_y + 14), 20, muted)
    time_str = datetime.now().strftime("%I:%M %p")
    font_time = get_poppins_font(20)
    tw, _ = get_text_size_pil(time_str, font_time)
    kiosk_frame = draw_text_with_font(kiosk_frame, time_str,
                                      (kiosk_width - tw - 60, footer_y + 14), 20, muted)

    # Body center area
    body_top = header_h
    body_bottom = footer_y
    center_x = kiosk_width // 2
    center_y = body_top + (body_bottom - body_top) // 2

    # Check idle state
    now = time.time()
    is_idle = True
    if sorting_history and _kiosk_state['last_sorted_time'] is not None:
        elapsed = now - _kiosk_state['last_sorted_time']
        if elapsed < _kiosk_state['idle_timeout']:
            is_idle = False

    if is_idle or not sorting_history:
        # Idle state
        idle_text = "Ready to sort"
        font_idle = get_poppins_font_bold(64)
        iw, _ = get_text_size_pil(idle_text, font_idle)
        kiosk_frame = draw_text_with_font(kiosk_frame, idle_text,
                                          (center_x - iw // 2, center_y - 50), 64, muted, bold=True)
        sub_text = "Place item inside the bin"
        font_sub = get_poppins_font(32)
        sw, _ = get_text_size_pil(sub_text, font_sub)
        kiosk_frame = draw_text_with_font(kiosk_frame, sub_text,
                                          (center_x - sw // 2, center_y + 36), 32, muted)
    else:
        # Sorted state
        waste_type = _kiosk_state['current_waste']
        message    = _kiosk_state['current_message']
        color      = waste_colors.get(waste_type, (107, 107, 107))
        label      = waste_names.get(waste_type, 'Unknown')

        # "SORTED AS" label
        sorted_label = "SORTED AS"
        font_lbl = get_poppins_font(24)
        lw, _ = get_text_size_pil(sorted_label, font_lbl)
        kiosk_frame = draw_text_with_font(kiosk_frame, sorted_label,
                                          (center_x - lw // 2, center_y - 160), 24, muted)

        # Waste type name (big bold)
        font_big = get_poppins_font_bold(96)
        nw, nh = get_text_size_pil(label, font_big)
        kiosk_frame = draw_text_with_font(kiosk_frame, label,
                                          (center_x - nw // 2, center_y - 110), 96, color, bold=True)

        # Colored bar
        bar_w, bar_h = 60, 4
        bar_x = center_x - bar_w // 2
        bar_y = center_y - 110 + nh + 20
        cv2.rectangle(kiosk_frame, (bar_x, bar_y), (bar_x + bar_w, bar_y + bar_h), color, -1)

        # Message
        font_msg = get_poppins_font(34)
        mw, _ = get_text_size_pil(message, font_msg)
        kiosk_frame = draw_text_with_font(kiosk_frame, message,
                                          (center_x - mw // 2, bar_y + 36), 34, dark_text)

        # Countdown
        elapsed = now - _kiosk_state['last_sorted_time']
        remaining = max(0, int(_kiosk_state['idle_timeout'] - elapsed) + 1)
        cd_text = f"Returning to idle in {remaining}s..."
        font_cd = get_poppins_font(22)
        cw, _ = get_text_size_pil(cd_text, font_cd)
        kiosk_frame = draw_text_with_font(kiosk_frame, cd_text,
                                          (center_x - cw // 2, bar_y + 100), 22, muted)

    return kiosk_frame

class ArduinoCommand:
    def __init__(self, command):
        self.command = command
        self.done = False
        self.event = threading.Event()  # Event to signal completion
    
    def wait_for_completion(self, timeout=10):
        """Wait for the command to complete (Arduino sends 'ready')"""
        return self.event.wait(timeout=timeout)
    
    def mark_done(self):
        """Mark the command as done and signal waiting threads"""
        self.done = True
        self.event.set()

class SortingRecord:
    def __init__(self, sorter_id, trash_type, trash_class, confidence, image_base64, is_maintenance):
        self.sorter_id = sorter_id
        self.trash_type = trash_type
        self.trash_class = trash_class
        self.confidence = confidence
        self.image_base64 = image_base64
        self.is_maintenance = is_maintenance

class SortingRecorderThread:
    """Background thread for posting sorting records to server (async, non-blocking)"""
    def __init__(self, base_path):
        self.base_path = base_path
        self.queue = Queue()
        self.running = True
        self.thread = Thread(target=self._process_queue, daemon=True)
        self.thread.start()
    
    def queue_record(self, record):
        """Queue a sorting record for async posting"""
        self.queue.put(record)
    
    def _process_queue(self):
        """Background worker that posts records to server"""
        while self.running:
            try:
                record = self.queue.get(timeout=0.5)
                try:
                    url = f"{self.base_path}/record_sorting.php"
                    response = requests.post(url, json={
                        'device_identity': record.sorter_id,
                        'trash_type': record.trash_type,
                        'trash_class': record.trash_class,
                        'confidence': float(record.confidence),
                        'image_data': record.image_base64,
                        'is_maintenance': record.is_maintenance
                    }, timeout=5)
                    
                    if response.status_code == 200:
                        print(f"📤 [BG] Sorting record posted: {record.trash_type}")
                    else:
                        print(f"⚠️ [BG] Failed to post sorting record: {response.status_code}")
                except Exception as e:
                    print(f"⚠️ [BG] Error posting sorting record: {e}")
            except:
                pass
    
    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

class BinFullnessRecord:
    def __init__(self, device_identity, bin_name, distance):
        self.device_identity = device_identity
        self.bin_name = bin_name
        self.distance = distance

class BinFullnessRecorderThread:
    """Background thread for posting bin fullness data to server (async, non-blocking)"""
    def __init__(self, base_path):
        self.base_path = base_path
        self.queue = Queue()
        self.running = True
        self.thread = Thread(target=self._process_queue, daemon=True)
        self.thread.start()
    
    def queue_record(self, record):
        """Queue a bin fullness record for async posting"""
        self.queue.put(record)
    
    def _process_queue(self):
        """Background worker that posts bin fullness to server"""
        while self.running:
            try:
                record = self.queue.get(timeout=0.5)
                try:
                    url = f"{self.base_path}/update_bin_fullness.php"
                    response = requests.post(
                        url,
                        data={
                            'device_identity': record.device_identity,
                            'bin_name': record.bin_name,
                            'distance': record.distance
                        },
                        timeout=5
                    )
                    
                    if response.status_code == 200 and "Record inserted" in response.text:
                        print(f"📤 [BG] Bin Fullness - {record.bin_name}: {record.distance}cm (Saved)")
                    else:
                        print(f"⚠️ [BG] Bin Fullness - {record.bin_name}: {record.distance}cm (Error: {response.text})")
                except Exception as e:
                    print(f"⚠️ [BG] Error posting bin fullness: {e}")
            except:
                pass
    
    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

class CommandHandler:
    def __init__(self, arduino):
        self.arduino = arduino
        self.command_queue = Queue()
        self.running = True
        self.thread = Thread(target=self._process_commands, daemon=True)
        self.thread.start()

    def send_command(self, command):
        self.command_queue.put(ArduinoCommand(command))

    def _process_commands(self):
        while self.running:
            try:
                if not self.command_queue.empty():
                    cmd = self.command_queue.get()
                    self.arduino.write(cmd.command.encode())
                    print(f"🔄 Sent command: {cmd.command.strip()}")
                    waiting_for_ready = True
                    while waiting_for_ready and self.running:
                        if self.arduino.in_waiting:
                            response = self.arduino.readline().decode().strip()
                            print(f"🟢 Arduino: {response}")
                            if response == "ready":
                                waiting_for_ready = False
                                cmd.mark_done()  # Signal that command is complete
                                print("✅ Arduino ready for next command")
                    cmd.done = True
                time.sleep(0.01)
            except Exception as e:
                print(f"Error in command handler: {e}")
                time.sleep(0.1)

    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

class VideoStream:
    def __init__(self, src=0):
        self.stream = cv2.VideoCapture(src, cv2.CAP_DSHOW)
        if not self.stream.isOpened():
            self.stream = cv2.VideoCapture(src)
        
        if self.stream.isOpened():
            self.stream.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            self.stream.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
            self.stream.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
            self.stream.set(cv2.CAP_PROP_FPS, 30)
            self.stream.set(cv2.CAP_PROP_FOURCC, cv2.VideoWriter_fourcc('M', 'J', 'P', 'G'))
        
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

    def release(self):
        self.stop()

class MaintenanceChecker:
    """Background thread for checking maintenance mode"""
    def __init__(self, base_path, sorter_id):
        self.base_path = base_path
        self.sorter_id = sorter_id
        self.maintenance_mode = False
        self.running = True
        self.thread = Thread(target=self._check_loop, daemon=True)
        self.thread.start()
    
    def _check_loop(self):
        """Background loop to check maintenance status"""
        while self.running:
            try:
                url = f"{self.base_path}/check_maintenance.php"
                response = requests.post(
                    url,
                    json={'identity': self.sorter_id},
                    headers={'Content-Type': 'application/json'},
                    timeout=5
                )
                if response.status_code == 200:
                    data = response.json()
                    if data.get('success'):
                        self.maintenance_mode = data.get('maintenance_mode') == 1
            except:
                pass
            time.sleep(2)  # Check every 2 seconds
    
    def is_maintenance(self):
        return self.maintenance_mode
    
    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

class AsyncDisplayThread:
    """Background thread for displaying frames and handling keyboard/mouse input asynchronously"""
    def __init__(self, frame_height=480, available_cams=None, mouse_callback=None):
        self.frame_height = frame_height
        self.available_cams = available_cams or []
        self.display_queue = Queue(maxsize=1)  # Keep only latest frame to avoid lag
        self.keyboard_queue = Queue()
        self.current_view = 'both'
        self.running = True
        self.mouse_callback = mouse_callback
        self.mouse_callback_set = False  # Track if we've set the callback
        self.thread = Thread(target=self._display_loop, daemon=True)
        self.thread.start()
    
    def queue_frame(self, frame, ui_panel, kiosk_ui):
        """Queue a frame for display (non-blocking, drops old frames to avoid lag)"""
        try:
            self.display_queue.get_nowait()  # Remove old frame
        except:
            pass
        self.display_queue.put((frame, ui_panel, kiosk_ui))
    
    def get_keyboard_input(self):
        """Get keyboard input from display thread (non-blocking)"""
        try:
            return self.keyboard_queue.get_nowait()
        except:
            return None
    
    def set_view_mode(self, view):
        """Update the view mode (both/camera/kiosk)"""
        self.current_view = view
    
    def set_mouse_callback(self, mouse_callback):
        """Set or update the mouse callback (can be called from main loop)"""
        self.mouse_callback = mouse_callback
        self.mouse_callback_set = False  # Reset flag to re-register callback next frame
    
    def _display_loop(self):
        """Background loop for all display operations"""
        detection_window_exists = False
        kiosk_window_exists = False
        
        while self.running:
            try:
                frame, ui_panel, kiosk_ui = self.display_queue.get(timeout=0.01)
                
                # Combine detection frame with UI panel
                combined_frame = np.vstack((frame, ui_panel))
                
                # Show/hide detection window based on view mode
                if self.current_view in ['both', 'camera']:
                    cv2.imshow("GoSort Detection", combined_frame)
                    # Set mouse callback only once when window is first created
                    if not self.mouse_callback_set and self.mouse_callback:
                        cv2.setMouseCallback("GoSort Detection", self.mouse_callback)
                        self.mouse_callback_set = True
                    detection_window_exists = True
                else:
                    if detection_window_exists:
                        try:
                            cv2.destroyWindow("GoSort Detection")
                            self.mouse_callback_set = False  # Reset flag when window is destroyed
                        except:
                            pass
                        detection_window_exists = False
                
                # Show/hide kiosk window based on view mode
                if self.current_view in ['both', 'kiosk']:
                    cv2.imshow("GoSort Kiosk", kiosk_ui)
                    cv2.setWindowProperty("GoSort Kiosk", cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)
                    kiosk_window_exists = True
                else:
                    if kiosk_window_exists:
                        try:
                            cv2.destroyWindow("GoSort Kiosk")
                        except:
                            pass
                        kiosk_window_exists = False
                
                # Get keyboard input (non-blocking)
                key = cv2.waitKey(1) & 0xFF
                if key != 255:
                    self.keyboard_queue.put(key)
                    
            except:
                # Queue timeout/empty - just continue
                pass
    
    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

class AsyncUIRenderThread:
    """Background thread for rendering UI asynchronously (non-blocking)"""
    def __init__(self, kiosk_width=640, kiosk_height=480):
        self.kiosk_width = kiosk_width
        self.kiosk_height = kiosk_height
        self.sorting_history = []
        self.history_lock = Lock()
        self.running = True
        self.thread = Thread(target=self._render_loop, daemon=True)
        self.thread.start()
    
    def add_to_history(self, waste_type):
        """Add a detected item to sorting history (thread-safe)"""
        with self.history_lock:
            self.sorting_history.insert(0, {'waste_type': waste_type, 'timestamp': datetime.now()})
            # Keep only last 10 items
            if len(self.sorting_history) > 10:
                self.sorting_history.pop()
            # Update kiosk state for the full UI
            _kiosk_state['last_sorted_time'] = time.time()
            _kiosk_state['current_waste'] = waste_type
            _kiosk_state['current_message'] = random.choice(WASTE_MESSAGES.get(waste_type, ['Sorted!']))
    
    def get_kiosk_ui(self):
        """Get the current kiosk UI frame (non-blocking)"""
        with self.history_lock:
            return draw_kiosk_ui(list(self.sorting_history), kiosk_width=self.kiosk_width, kiosk_height=self.kiosk_height)
    
    def _render_loop(self):
        """Background loop for UI rendering"""
        while self.running:
            time.sleep(0.1)  # Light background task
    
    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

class MaintenanceCommandChecker:
    """Background thread for checking and executing maintenance commands"""
    def __init__(self, base_path, sorter_id, command_handler):
        self.base_path = base_path
        self.sorter_id = sorter_id
        self.command_handler = command_handler
        self.running = True
        self.thread = Thread(target=self._check_loop, daemon=True)
        self.thread.start()
    
    def _check_loop(self):
        """Background loop to check for maintenance commands"""
        while self.running:
            try:
                response = requests.post(
                    f"{self.base_path}/check_maintenance_commands.php",
                    json={'device_identity': self.sorter_id},
                    headers={'Content-Type': 'application/json'},
                    timeout=5
                )
                if response.status_code == 200:
                    data = response.json()
                    if data.get('success') and data.get('command'):
                        command = data['command']
                        self._execute_command(command)
            except:
                pass
            time.sleep(1)  # Check every 1 second
    
    def _execute_command(self, command):
        """Execute a maintenance command"""
        print(f"\n📡 Received maintenance command: {command}")
        
        if command == 'shutdown':
            print("⚠️ Shutdown command received. Shutting down computer...")
            try:
                requests.post(
                    f"{self.base_path}/mark_command_executed.php",
                    json={'device_identity': self.sorter_id, 'command': command},
                    timeout=5
                )
            except:
                pass
            os.system('shutdown /s /t 1 /f')
            time.sleep(5)
            return
        
        # For other commands, queue to Arduino
        if self.command_handler and command in ['ndeg', 'zdeg', 'odeg', 'mdeg', 'unclog', 'sweep1', 'sweep2']:
            print(f"🔧 Sending maintenance command to Arduino: {command}")
            
            # Special handling for maintenance-specific commands
            if command in ['unclog', 'sweep1', 'sweep2']:
                # Enable maintenance mode first
                maintenance_cmd = ArduinoCommand("maintmode\n")
                self.command_handler.command_queue.put(maintenance_cmd)
                maintenance_cmd.wait_for_completion(timeout=10)
                time.sleep(0.5)
            
            # Send the actual command
            cmd = ArduinoCommand(f"{command}\n")
            self.command_handler.command_queue.put(cmd)
            cmd.wait_for_completion(timeout=15)
            
            # Exit maintenance mode if needed
            if command in ['unclog', 'sweep1', 'sweep2']:
                time.sleep(0.5)
                end_cmd = ArduinoCommand("maintend\n")
                self.command_handler.command_queue.put(end_cmd)
                end_cmd.wait_for_completion(timeout=10)
        
        # Mark command as executed on server
        try:
            requests.post(
                f"{self.base_path}/mark_command_executed.php",
                json={'device_identity': self.sorter_id, 'command': command},
                timeout=5
            )
            print(f"✅ Command executed: {command}")
        except Exception as e:
            print(f"⚠️ Error marking command as executed: {e}")
    
    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

class HeartbeatSender:
    """Background thread for sending heartbeats"""
    def __init__(self, base_path, sorter_id):
        self.base_path = base_path
        self.sorter_id = sorter_id
        self.running = True
        self.thread = Thread(target=self._heartbeat_loop, daemon=True)
        self.thread.start()
    
    def _heartbeat_loop(self):
        """Background loop to send heartbeats"""
        while self.running:
            try:
                url = f"{self.base_path}/verify_sorter.php"
                requests.post(url, json={'identity': self.sorter_id}, timeout=5)
            except:
                pass
            time.sleep(5)  # Heartbeat every 5 seconds
    
    def stop(self):
        self.running = False
        if self.thread.is_alive():
            self.thread.join()

def list_available_cameras(max_cams=10):
    available = []
    
    print("Scanning for available cameras...")
    
    # Try each camera index
    for i in range(max_cams):
        found = False
        
        # Try default backend first
        cap = cv2.VideoCapture(i)
        if cap.isOpened():
            ret, frame = cap.read()
            if ret and frame is not None and frame.size > 0:
                print(f"✅ Camera {i} is available (default backend)")
                available.append(i)
                found = True
            cap.release()
        
        # If not found with default, try DirectShow (Windows)
        if not found:
            cap = cv2.VideoCapture(i, cv2.CAP_DSHOW)
            if cap.isOpened():
                ret, frame = cap.read()
                if ret and frame is not None and frame.size > 0:
                    print(f"✅ Camera {i} is available (DirectShow)")
                    available.append(i)
                    found = True
                cap.release()
        
        # Small delay to avoid resource conflicts
        time.sleep(0.1)
    
    if available:
        print(f"\n🎥 Found {len(available)} camera(s): {available}")
    else:
        print("\n❌ No cameras found!")
    
    return available

def check_maintenance_command():
    command_file = 'maintenance_command.txt'
    if os.path.exists(command_file):
        with open(command_file, 'r') as f:
            command = f.read().strip()
        os.remove(command_file)
        return command
    return None

# Function to check server connection - returns True if server is reachable
def check_server_connection(ip_address):
    try:
        url = f"http://{ip_address}/gs_DB/verify_sorter.php"
        response = requests.post(url, json={'identity': ''})
        return response.status_code == 200
    except:
        return False

def map_category_to_command(waste_type, mapping):
    # waste_type is already a category like 'bio', 'nbio', 'hazardous', 'mixed'
    # Convert to lowercase for comparison
    waste_type = waste_type.lower()
    
    # Find the servo command for this waste type from the mapping
    for cmd, typ in mapping.items():
        if typ.lower() == waste_type:
            return cmd
    
    # Default commands if not found in mapping
    default_commands = {
        'bio': 'zdeg',
        'nbio': 'ndeg',
        'hazardous': 'odeg',
        'mixed': 'mdeg'
    }
    return default_commands.get(waste_type, 'ndeg')  # Default to ndeg if no mapping found

def check_maintenance_mode(device_identity):
    try:
        base_path = get_base_path()
        url = f"{base_path}/check_maintenance.php"
        response = requests.post(
            url,
            json={'identity': device_identity},
            headers={'Content-Type': 'application/json'}
        )
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                return data.get('maintenance_mode') == 1
        return False
    except Exception as e:
        print(f"\n❌ Error checking maintenance mode: {e}")
        return False

def send_heartbeat(device_identity):
    try:
        base_path = get_base_path()
        url = f"{base_path}/verify_sorter.php"
        response = requests.post(url, json={'identity': device_identity})
        return response.status_code == 200
    except requests.exceptions.RequestException as e:
        print(f"❌ Error sending heartbeat: {e}")
        return False

def add_to_waiting_devices(device_identity):
    try:
        base_path = get_base_path()
        url = f"{base_path}/add_waiting_device.php"
        response = requests.post(url, json={
            'identity': device_identity
        })
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                return True
            print(f"\n❌ Server error: {data.get('message', 'Unknown error')}")
        return False
    except Exception as e:
        print(f"\n❌ Error adding device to waiting list: {e}")
        return False

def request_registration(identity):
    try:
        # First check if device is in sorters table
        base_path = get_base_path()
        url = f"{base_path}/verify_sorter.php"
        response = requests.post(
            url,
            json={'identity': identity},
            headers={'Content-Type': 'application/json'}
        )
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                if data.get('registered'):
                    return True, None
                else:
                    # Try to add to waiting devices
                    response = requests.post(
                        f"{base_path}/add_waiting_device.php",
                        json={'identity': identity}
                    )
                    if response.status_code == 200:
                        data = response.json()
                        if data.get('success'):
                            if 'already in waiting list' in data.get('message', ''):
                                return False, "duplicate"
                            print("\n✅ Added to waiting devices list")
                            return False, None
                    return False, None
            print(f"\n❌ Server error: {data.get('message', 'Unknown error')}")
        return False, None
    except Exception as e:
        print(f"\n❌ Error requesting registration: {e}")
        return False, None

def restart_program():
    print("\n🔄 Restarting application...")
    python = sys.executable
    os.execl(python, python, *sys.argv)

def select_camera_from_list(available_cams):
    """Prompt user to select a camera from available cameras"""
    if not available_cams:
        return None
    
    if len(available_cams) == 1:
        print(f"\n✅ Using only available camera: index {available_cams[0]}")
        return available_cams[0]
    
    print("\n📷 Available Cameras:")
    for i, cam_idx in enumerate(available_cams):
        print(f"  {i + 1}. Camera {cam_idx}")
    
    while True:
        choice = input("\nSelect camera (enter number): ").strip()
        try:
            choice_idx = int(choice) - 1
            if 0 <= choice_idx < len(available_cams):
                selected_cam = available_cams[choice_idx]
                print(f"✅ Selected camera: index {selected_cam}")
                return selected_cam
            else:
                print("❌ Invalid selection. Please try again.")
        except ValueError:
            print("❌ Invalid input. Please enter a number.")

def select_arduino_from_list(candidate_ports):
    """Prompt user to select an Arduino port from available ports"""
    if not candidate_ports:
        return None
    
    print("\n🔌 Available Arduino Ports:")
    for i, port in enumerate(candidate_ports):
        print(f"  {i + 1}. {port}")
    
    while True:
        choice = input("\nSelect Arduino port (enter number): ").strip()
        try:
            choice_idx = int(choice) - 1
            if 0 <= choice_idx < len(candidate_ports):
                selected_port = candidate_ports[choice_idx]
                print(f"✅ Selected Arduino: {selected_port}")
                return selected_port
            else:
                print("❌ Invalid selection. Please try again.")
        except ValueError:
            print("❌ Invalid input. Please enter a number.")

def remove_from_waiting_devices(device_identity):
    try:
        base_path = get_base_path()
        url = f"{base_path}/remove_waiting_device.php"
        response = requests.post(url, json={'identity': device_identity}, headers={'Content-Type': 'application/json'})
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                print(f"\n✅ Removed {device_identity} from waiting devices list")
                return True
        return False
    except Exception as e:
        print(f"\n❌ Error removing from waiting devices: {e}")
        return False

def list_arduino_ports():
    """Return a list of candidate serial port device names likely to be Arduinos."""
    try:
        import serial.tools.list_ports
        ports = serial.tools.list_ports.comports()
        candidates = []
        for port in ports:
            if 'Arduino' in port.description or '2560' in port.description or 'Mega' in port.description or 'CH340' in port.description or 'CH341' in port.description:
                print(f"Found Arduino-like device at: {port.device} ({port.description})")
                candidates.append(port.device)
        # If none matched by description, return all ports as fallback
        if not candidates:
            for port in ports:
                candidates.append(port.device)
        return candidates
    except Exception:
        return []

def connect_to_arduino(port):
    """Try to open a serial connection to the provided port with safe options.

    Returns an open Serial object or None on failure.
    """
    try:
        import serial
        # Attempt quick open/close to release lock if possible
        try:
            temp_ser = serial.Serial(port)
            temp_ser.close()
        except Exception:
            pass

        ser = serial.Serial(
            port=port,
            baudrate=115200,
            timeout=1,
            write_timeout=1,
            # exclusive param may not exist on all platforms/pyserial versions
        )
        time.sleep(2)
        try:
            ser.reset_input_buffer()
        except Exception:
            pass
        return ser
    except Exception as e:
        print(f"Error connecting to {port}: {e}")
        return None

def process_bin_fullness(data, device_identity):
    """Parse and return bin fullness record for async posting"""
    # Format is "bin_fullness:BinName:Distance"
    try:
        parts = data.split(':')
        if len(parts) == 3 and parts[0] == 'bin_fullness':
            bin_name = parts[1]
            try:
                distance = int(parts[2])
            except Exception:
                distance = parts[2]
            
            return BinFullnessRecord(device_identity, bin_name, distance)
    except Exception as e:
        print(f"\nError processing bin fullness data: {e}")
    
    return None

def main():
    config = load_config()
    # Get fixed server URL
    base_path = get_base_path()
    print(f"\nUsing GoSort server at: {base_path}")

    # Then get identity configuration
    if config.get('sorter_id') is None:
        print("\nFirst time setup - Sorter Identity Configuration")
        sorter_id = input("Enter Sorter Identity (e.g., Sorter1): ")
        config['sorter_id'] = sorter_id
        save_config(config)
    sorter_id = config.get('sorter_id')
    print(f"Using Sorter Identity: {sorter_id}")
    
    print("\nVerifying server connection...")
    if not check_server():
        print("❌ Failed to connect to the server")
        return

    print("\nRequesting device registration with the server...")
    registered = False
    first_request = True

    def print_waiting_menu():
        print("\n\nOptions while waiting:")
        print("r - Reconfigure Identity")
        print("c - Clear Configuration")
        print("q - Quit")
        print("\nPress any other key to check registration status...")

    while not registered:
        registered, status = request_registration(sorter_id)
        
        if registered:
            print("\n✅ Device registration confirmed!")
            break
        elif first_request:
            print("\n⏳ Waiting for admin approval in the GoSort web interface")
            print(f"    Device Identity: {sorter_id}")
            print("    Please approve this device in the web interface...")
            print_waiting_menu()
            first_request = False
        
        if msvcrt.kbhit():
            key = msvcrt.getch().decode().lower()
            if key == 'r':
                print("\nReconfiguring Sorter Identity")
                sorter_id = input("Enter new Sorter Identity (e.g., Sorter1): ")
                config = load_config()
                config['sorter_id'] = sorter_id
                save_config(config)
                print("\n⏳ Trying with new identity:", sorter_id)
                first_request = True  # Reset to show the waiting message again
                continue
            elif key == 'c':
                print("\n⚠️ Clearing configuration...")
                # Remove from waiting devices before clearing config
                remove_from_waiting_devices(sorter_id)
                config['sorter_id'] = None
                save_config(config)
                print("✅ Configuration cleared.")
                print("Please restart the application.")
                return
            elif key == 'q':
                print("\n❌ Registration cancelled. Exiting...")
                # Remove from waiting devices before exiting
                remove_from_waiting_devices(sorter_id)
                return
            else:
                print("\nChecking registration status...", end="", flush=True)
        
        time.sleep(2)  # Check every 2 seconds
        if not first_request:
            print(".", end="", flush=True)

    # Set up last heartbeat time
    last_heartbeat = 0
    heartbeat_interval = 5  # Send heartbeat every 5 seconds

    # Track maintenance status for change detection
    last_maintenance_status = False
    check_interval = 1  # Check maintenance mode every second

    # Device mode selection (CPU/GPU)
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
    print(f"Using device mode: {device_mode.upper()}")

    # Use device_mode from config to set torch.device
    if device_mode == 'gpu' and torch.cuda.is_available():
        device = torch.device('cuda')
        backend = 'CUDA (NVIDIA GPU)'
    else:
        # No CUDA available or CPU explicitly selected -> use CPU
        device = torch.device('cpu')
        backend = 'CPU'
    print(f"Using device: {device}")
    print(f"Backend: {backend}")
    
    device_name = ""
    if device.type == 'cuda':
        device_name = torch.cuda.get_device_name(0)
        print(f"GPU: {device_name}")
    elif 'DirectML' in backend:
        device_name = 'Integrated Graphics (DirectML)'
        print(f"Integrated Graphics: {device_name}")
    else:
        device_name = cpuinfo.get_cpu_info()['brand_raw']
        print(f"CPU: {device_name}")
    
    model = YOLO('shit_weights.pt')
    if device.type == 'cuda':
        model.to('cuda')
    else:
        # model remains on CPU by default
        pass

    model.conf = 0.50  
    model.iou = 0.50   

    # Initialize async recorders early so Arduino setup can use them
    sorting_recorder = SortingRecorderThread(base_path)
    bin_fullness_recorder = BinFullnessRecorderThread(base_path)
    
    # Get screen resolution for responsive UI
    screen_width, screen_height = get_screen_resolution()
    print(f"Detected screen resolution: {screen_width}x{screen_height}")
    
    # Detect available cameras for display renderer
    print("Searching for available cameras...")
    available_cams = list_available_cameras()
    
    if not available_cams:
        print("No cameras found!")
        return
    
    # Ask user to select which camera to use (if not already configured)
    if config.get('camera_index') is None:
        selected_camera = select_camera_from_list(available_cams)
        if selected_camera is None:
            print("❌ No camera selected. Exiting...")
            return
        config['camera_index'] = selected_camera
        save_config(config)
    else:
        selected_camera = config.get('camera_index')
        print(f"\n✅ Using configured camera: index {selected_camera}")
        if selected_camera not in available_cams:
            print(f"⚠️  Configured camera {selected_camera} not found. Please reconfigure.")
            config['camera_index'] = None
            save_config(config)
            print("Please restart the application.")
            return
    
    cam_index = selected_camera
    
    # Initialize UI renderer thread for async kiosk display with detected screen dimensions
    ui_renderer = AsyncUIRenderThread(kiosk_width=screen_width, kiosk_height=screen_height)
    
    # Initialize async display thread for non-blocking UI rendering (improves FPS)
    display_renderer = AsyncDisplayThread(frame_height=480, available_cams=available_cams)
    
    # Initialize background threads for maintenance and heartbeat
    maintenance_checker = MaintenanceChecker(base_path, sorter_id)
    heartbeat_sender = HeartbeatSender(base_path, sorter_id)

    try:
        import serial
        arduino = None
        command_handler = None
        arduino_connected = False
        
        # Detect available Arduino ports
        candidate_ports = list_arduino_ports()
        
        # Ask user to select Arduino port if not already configured
        selected_port = None
        if candidate_ports:
            if config.get('arduino_port') is None:
                selected_port = select_arduino_from_list(candidate_ports)
                if selected_port:
                    config['arduino_port'] = selected_port
                    save_config(config)
            else:
                selected_port = config.get('arduino_port')
                print(f"\n✅ Using configured Arduino: {selected_port}")
                if selected_port not in candidate_ports:
                    print(f"⚠️  Configured Arduino {selected_port} not found. Available ports: {candidate_ports}")
                    print("Please reconfigure.")
                    config['arduino_port'] = None
                    save_config(config)
                    selected_port = select_arduino_from_list(candidate_ports)
                    if selected_port:
                        config['arduino_port'] = selected_port
                        save_config(config)
        
        # Try to connect to the selected Arduino port only
        if selected_port:
            ser = connect_to_arduino(selected_port)
            if ser is not None:
                try:
                    arduino = ser
                    print(f"Connected to Arduino on {selected_port}")
                    # Signal readiness to Arduino and read any initial messages
                    try:
                        arduino.write(b'gosort_ready\n')
                    except Exception:
                        pass
                    time.sleep(0.2)
                    while getattr(arduino, 'in_waiting', 0):
                        try:
                            response = arduino.readline().decode().strip()
                            if response:
                                print(f"Arduino: {response}")
                                if response.startswith('bin_fullness:'):
                                    # Queue bin fullness for async posting (non-blocking)
                                    record = process_bin_fullness(response, sorter_id)
                                    if record:
                                        bin_fullness_recorder.queue_record(record)
                        except Exception:
                            break

                    command_handler = CommandHandler(arduino)
                    arduino_connected = True

                    def check_arduino_connection():
                        nonlocal arduino_connected
                        try:
                            if not arduino or not getattr(arduino, 'is_open', True):
                                arduino_connected = False
                                return False
                            arduino.write(b'ping\n')
                            time.sleep(0.1)
                            return True
                        except Exception as e:
                            print(f"\n❌ Arduino connection lost: {e}")
                            arduino_connected = False
                            return False

                except Exception as e:
                    print(f"Failed to initialize serial on {selected_port}: {e}")
                    try:
                        ser.close()
                    except Exception:
                        pass
                    arduino = None
        
        if not arduino_connected:
            print("⚠️  Arduino not connected. Continuing without Arduino...")
            def check_arduino_connection():
                return False
    except Exception as e:
        print(f"Error during Arduino setup: {e}")
        arduino = None
        command_handler = None
        arduino_connected = False
        def check_arduino_connection():
            return False

    # Set up current camera index for view switching
    current_cam_idx = available_cams.index(cam_index)
    print(f"Using camera index: {cam_index}")

    print("Starting video stream...")
    vs = VideoStream(cam_index)
    stream = vs.start()
    time.sleep(1.0)  


    fps = 0
    fps_time = time.time()
    frame_count = 0


    mapping_url = f"{base_path}/save_sorter_mapping.php?device_identity={sorter_id}"
    try:
        resp = requests.get(mapping_url)
        mapping = resp.json().get('mapping', {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous'})
    except Exception as e:
        print(f"Warning: Could not fetch mapping, using default. {e}")
        mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous'}

    trash_to_cmd = {v: k for k, v in mapping.items()}

    # Detection buffer for 1000ms window (enables mixed waste detection)
    detection_buffer = {}  # waste_type -> {'item', 'conf', 'frame', 'command'}
    detection_window_start = None
    DETECTION_WINDOW = 1.0  # 1 second

    # Initialize maintenance command checker
    maintenance_command_checker = MaintenanceCommandChecker(base_path, sorter_id, command_handler) if command_handler else None

    # Initialize view toggle state: 'both', 'camera', or 'kiosk'
    current_view = 'both'  # Start with both views visible
    a_key_pressed = False
    a_key_time = 0

    while True:
        frame = stream.read()
        frame_count += 1
        
        # Get maintenance mode status from background thread (non-blocking)
        current_maintenance = maintenance_checker.is_maintenance()
        
        # Process any incoming serial messages (bin fullness, logs, etc.)
        try:
            if arduino_connected and command_handler is not None and getattr(command_handler.arduino, 'in_waiting', 0):
                while command_handler.arduino.in_waiting:
                    try:
                        line = command_handler.arduino.readline().decode().strip()
                        if not line:
                            continue
                        if line.startswith('bin_fullness:'):
                            # Queue bin fullness for async posting (non-blocking)
                            record = process_bin_fullness(line, sorter_id)
                            if record:
                                bin_fullness_recorder.queue_record(record)
                                print(f"\r✅ Bin Fullness - {record.bin_name}: {record.distance}cm (Queued)", end="", flush=True)
                        else:
                            print(f"🟢 Arduino: {line}")
                    except Exception:
                        break
        except Exception:
            pass

        if current_maintenance:
            cv2.putText(frame, "MAINTENANCE MODE - Detection Paused", (10, 110), 
                       cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)
            
            # Skip YOLOv8 inference during maintenance
            results = []
        else:
            # Only run YOLOv8 when not in maintenance mode
            results = model.predict(frame, stream=False)

        # Update FPS counter
        current_time = time.time()
        if current_time - fps_time >= 1.0:
            fps = frame_count
            frame_count = 0
            fps_time = current_time

        if not current_maintenance:
            for result in results:
                boxes = result.boxes.cpu().numpy()
                for box in boxes:
                    x1, y1, x2, y2 = box.xyxy[0].astype(int)
                    conf = box.conf[0]
                    class_id = int(box.cls[0])
                    detected_item = model.names[class_id]
                    
                    # Map the detected item to its category using categories.json
                    categories = load_categories()
                    class_name = None
                    for category, items in categories.items():
                        if detected_item.lower() in [item.lower() for item in items]:
                            class_name = category
                            break
                    
                    if class_name is None:
                        class_name = "nbio"  # Default category if not found

                    # Store original frame for database
                    clean_frame = frame.copy()

                    # Draw bounding box and label
                    cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)
                    cv2.putText(frame, f"{detected_item} {conf:.2f}", (x1, y1 - 10),
                              cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)

                    # Accumulate detections in 1000ms window buffer
                    if conf > 0.50:  # 50% confidence threshold
                        if detection_window_start is None:
                            detection_window_start = time.time()
                        if class_name not in detection_buffer or conf > detection_buffer[class_name]['conf']:
                            detection_buffer[class_name] = {
                                'item': detected_item,
                                'conf': conf,
                                'frame': clean_frame.copy(),
                                'command': map_category_to_command(class_name, mapping)
                            }

        # Process accumulated detections after 1000ms window
        if detection_window_start is not None and time.time() - detection_window_start >= DETECTION_WINDOW:
            if detection_buffer:
                unique_types = list(detection_buffer.keys())
                if len(unique_types) > 1:
                    # Multiple waste types detected in window -> classify as mixed
                    trash_type = 'mixed'
                else:
                    buf_command = detection_buffer[unique_types[0]]['command']
                    trash_type = mapping.get(buf_command, unique_types[0])

                command = map_category_to_command(trash_type, mapping)
                best_type = max(detection_buffer, key=lambda k: detection_buffer[k]['conf'])
                best = detection_buffer[best_type]
                proc_conf = best['conf']
                proc_frame = best['frame']
                trash_class_str = ', '.join(d['item'] for d in detection_buffer.values())

                try:
                    print(f"✅ Detection: {trash_class_str} ({proc_conf:.2f}) - Type: {trash_type}")

                    _, buf_enc = cv2.imencode('.jpg', proc_frame)
                    image_base64 = base64.b64encode(buf_enc).decode('utf-8')

                    record = SortingRecord(
                        sorter_id=sorter_id,
                        trash_type=trash_type,
                        trash_class=trash_class_str,
                        confidence=float(proc_conf),
                        image_base64=image_base64,
                        is_maintenance=False
                    )
                    sorting_recorder.queue_record(record)

                    print(f"✅ Detection queued for posting")

                    play_sorting_audio(trash_type)
                    ui_renderer.add_to_history(trash_type)

                    if command_handler is not None:
                        if command_handler.command_queue.empty():
                            print(f"⏱️ Sending sorting command: {command}")
                            cmd = ArduinoCommand(f"{command}\n")
                            command_handler.command_queue.put(cmd)

                            print("⏸️  Detection paused - waiting for servo to finish...")
                            if cmd.wait_for_completion(timeout=15):
                                print("✅ Servo finished - resuming detection")
                            else:
                                print("⚠️ Servo timeout - resuming detection anyway")
                        else:
                            print("⏳ Arduino busy - skipping this detection")

                except Exception as e:
                    print(f"❌ Error processing detection: {e}")

            # Reset buffer after processing
            detection_buffer = {}
            detection_window_start = None

        ui_panel = np.zeros((100, frame.shape[1], 3), dtype=np.uint8)
        
        # Maintenance Mode Indicator
        if current_maintenance:
            cv2.rectangle(ui_panel, (10, 10), (200, 40), (0, 0, 255), -1)
            cv2.putText(ui_panel, "MAINTENANCE MODE", (15, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
            status_x = 210
        else:
            # Server Status button (showing fixed server in use)
            cv2.rectangle(ui_panel, (10, 10), (150, 40), (100, 200, 255), -1)
            cv2.putText(ui_panel, "Server OK", (30, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)
            status_x = 160
        
        # Change Identity button
        cv2.rectangle(ui_panel, (status_x + 10, 10), (status_x + 150, 40), (0, 255, 0), -1)
        cv2.putText(ui_panel, "Change ID", (status_x + 30, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)
        
        # Switch Camera button
        cv2.rectangle(ui_panel, (status_x + 160, 10), (status_x + 320, 40), (100, 200, 255), -1)
        camera_label = f"Camera ({current_cam_idx + 1}/{len(available_cams)})"
        cv2.putText(ui_panel, camera_label, (status_x + 175, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 0), 2)
        
        # Reconfigure All button
        cv2.rectangle(ui_panel, (status_x + 330, 10), (status_x + 470, 40), (0, 255, 0), -1)
        cv2.putText(ui_panel, "Reconfig All", (status_x + 340, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)
        
        # Exit button
        cv2.rectangle(ui_panel, (status_x + 480, 10), (status_x + 620, 40), (0, 0, 255), -1)
        cv2.putText(ui_panel, "Exit", (status_x + 525, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
            
        cv2.putText(frame, f"FPS: {fps}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
        device_text = f"GPU: {device_name}" if torch.cuda.is_available() else f"CPU: {device_name}"
        cv2.putText(frame, device_text, (10, 70), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
        
        # Display current view mode
        view_text = f"View: {current_view.upper()} (Press A+S to toggle)"
        text_size = cv2.getTextSize(view_text, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 1)[0]
        text_x = frame.shape[1] - text_size[0] - 10
        cv2.putText(frame, view_text, (text_x, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 2)

        combined_frame = np.vstack((frame, ui_panel))
        
        # Get async kiosk UI (non-blocking)
        kiosk_ui = ui_renderer.get_kiosk_ui()

        # Queue frame for async display (non-blocking - main loop doesn't wait)
        display_renderer.current_view = current_view
        display_renderer.queue_frame(frame, ui_panel, kiosk_ui)
        
        # Handle mouse events
        def mouse_callback(event, x, y, flags, param):
            nonlocal current_cam_idx, stream
            if event == cv2.EVENT_LBUTTONDOWN:
                # Adjust y coordinate to account for the main frame
                y = y - frame.shape[0]
                if 10 <= y <= 40:  # Button row
                    # Calculate status_x based on maintenance mode
                    status_x_local = 210 if current_maintenance else 160
                    
                    if 10 <= x <= 150:  # Change IP button
                        pass
                    elif status_x_local + 10 <= x <= status_x_local + 150:  # Change Identity button
                        print("\nReconfiguring Sorter Identity")
                        sorter_id = input("Enter new Sorter Identity (e.g., Sorter1): ")
                        config = load_config()
                        config['sorter_id'] = sorter_id
                        save_config(config)
                        print("\nSorter Identity updated. Please restart the application.")
                        cv2.destroyAllWindows()
                        stream.stop()
                        if command_handler:
                            command_handler.stop()
                        ui_renderer.stop()
                        display_renderer.stop()
                        exit()
                    elif status_x_local + 160 <= x <= status_x_local + 320:  # Switch Camera button
                        if len(available_cams) > 1:
                            print("\n� Switching camera...")
                            stream.stop()
                            current_cam_idx = (current_cam_idx + 1) % len(available_cams)
                            cam_index = available_cams[current_cam_idx]
                            print(f"Switched to camera {current_cam_idx + 1}/{len(available_cams)} (Index: {cam_index})")
                            stream = VideoStream(cam_index).start()
                            time.sleep(1.0)
                        else:
                            print("\n⚠️ Only one camera available")
                    elif status_x_local + 330 <= x <= status_x_local + 470:  # Reconfigure All button
                        print("\nReconfiguring All Settings")
                        config = {}
                        save_config(config)
                        print("\nAll configuration cleared. Please restart the application.")
                        cv2.destroyAllWindows()
                        stream.stop()
                        if command_handler:
                            command_handler.stop()
                        ui_renderer.stop()
                        display_renderer.stop()
                        exit()
                    elif status_x_local + 480 <= x <= status_x_local + 620:  # Exit button
                        cv2.destroyAllWindows()
                        stream.stop()
                        if command_handler:
                            command_handler.stop()
                        ui_renderer.stop()
                        display_renderer.stop()
                        exit()

        # Update mouse callback with current callback (in case it changed)
        display_renderer.set_mouse_callback(mouse_callback)
        
        # Handle keyboard input from async display thread (non-blocking)
        key = display_renderer.get_keyboard_input()
        
        if key:
            # Check for 'a' or 'A' key
            if key == ord('a') or key == ord('A'):
                a_key_pressed = True
                a_key_time = time.time()
            
            # Check for 's' or 'S' key (after 'a' was pressed)
            if (key == ord('s') or key == ord('S')) and a_key_pressed:
                # Toggle view mode
                if current_view == 'both':
                    current_view = 'camera'
                    print("\n📺 View Mode: Camera Only")
                elif current_view == 'camera':
                    current_view = 'kiosk'
                    print("\n📱 View Mode: Kiosk Only")
                elif current_view == 'kiosk':
                    current_view = 'both'
                    print("\n👁️  View Mode: Both")
                a_key_pressed = False
            
            # Quit on 'q' or ESC key
            if key == ord('q') or key == 27:  # 27 is ESC key
                print("\n👋 Exiting application...")
                break
        
        # Reset 'a' key flag if too much time has passed
        if a_key_pressed and time.time() - a_key_time > 0.5:
            a_key_pressed = False

    # Release resources
    sorting_recorder.stop()
    bin_fullness_recorder.stop()
    maintenance_checker.stop()
    if maintenance_command_checker:
        maintenance_command_checker.stop()
    heartbeat_sender.stop()
    ui_renderer.stop()
    display_renderer.stop()
    stream.stop()
    if command_handler:
        command_handler.stop()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    main()