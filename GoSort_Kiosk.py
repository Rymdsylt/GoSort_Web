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
from datetime import datetime
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

def get_base_path():
    return "https://gosortweb-production.up.railway.app/gs_DB"

def scan_network():
    return [], []

def check_server():
    print("\rChecking server...", end="", flush=True)
    try:
        base_path = get_base_path()
        response = requests.get(f"{base_path}/trash_detected.php", timeout=5)
        if response.status_code == 200 or (response.status_code == 400 and "No trash type provided" in response.text):
            print("\r✅ Server connection successful!")
            return True
        print("\r❌ GoSort server is not reachable")
        return False
    except requests.exceptions.RequestException:
        print("\r❌ GoSort server is not reachable")
        return False

def get_ip_address():
    return get_base_path()

class ArduinoCommand:
    def __init__(self, command):
        self.command = command
        self.done = False

class SortingRecord:
    def __init__(self, sorter_id, trash_type, trash_class, confidence, image_base64, is_maintenance):
        self.sorter_id = sorter_id
        self.trash_type = trash_type
        self.trash_class = trash_class
        self.confidence = confidence
        self.image_base64 = image_base64
        self.is_maintenance = is_maintenance

class SortingRecorderThread:
    def __init__(self, base_path):
        self.base_path = base_path
        self.queue = Queue()
        self.running = True
        self.thread = Thread(target=self._process_queue, daemon=True)
        self.thread.start()

    def queue_record(self, record):
        self.queue.put(record)

    def _process_queue(self):
        while self.running:
            try:
                if not self.queue.empty():
                    record = self.queue.get(timeout=0.1)
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
                else:
                    time.sleep(0.01)
            except Exception as e:
                print(f"Error in sorting recorder: {e}")
                time.sleep(0.1)

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
    def __init__(self, base_path):
        self.base_path = base_path
        self.queue = Queue()
        self.running = True
        self.thread = Thread(target=self._process_queue, daemon=True)
        self.thread.start()

    def queue_record(self, record):
        self.queue.put(record)

    def _process_queue(self):
        while self.running:
            try:
                if not self.queue.empty():
                    record = self.queue.get(timeout=0.1)
                    try:
                        url = f"{self.base_path}/update_bin_fullness.php"
                        response = requests.post(url, data={
                            'device_identity': record.device_identity,
                            'bin_name': record.bin_name,
                            'distance': record.distance
                        }, timeout=5)
                        if response.status_code == 200 and "Record inserted" in response.text:
                            print(f"📤 [BG] Bin Fullness - {record.bin_name}: {record.distance}cm (Saved)", end="", flush=True)
                        else:
                            print(f"⚠️ [BG] Bin Fullness - {record.bin_name}: {record.distance}cm (Error: {response.text})", end="", flush=True)
                    except Exception as e:
                        print(f"⚠️ [BG] Error posting bin fullness: {e}", end="", flush=True)
                else:
                    time.sleep(0.01)
            except Exception as e:
                print(f"Error in bin fullness recorder: {e}")
                time.sleep(0.1)

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

def list_available_cameras(max_cams=10):
    available = []
    print("Scanning for available cameras...")
    for i in range(max_cams):
        found = False
        cap = cv2.VideoCapture(i)
        if cap.isOpened():
            ret, frame = cap.read()
            if ret and frame is not None and frame.size > 0:
                print(f"✅ Camera {i} is available (default backend)")
                available.append(i)
                found = True
            cap.release()
        if not found:
            cap = cv2.VideoCapture(i, cv2.CAP_DSHOW)
            if cap.isOpened():
                ret, frame = cap.read()
                if ret and frame is not None and frame.size > 0:
                    print(f"✅ Camera {i} is available (DirectShow)")
                    available.append(i)
                    found = True
                cap.release()
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

def check_server_connection(ip_address):
    try:
        url = f"http://{ip_address}/gs_DB/verify_sorter.php"
        response = requests.post(url, json={'identity': ''})
        return response.status_code == 200
    except:
        return False

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

def check_maintenance_mode(device_identity):
    try:
        base_path = get_base_path()
        url = f"{base_path}/check_maintenance.php"
        response = requests.post(url, json={'identity': device_identity}, headers={'Content-Type': 'application/json'})
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
        response = requests.post(url, json={'identity': device_identity})
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
        base_path = get_base_path()
        url = f"{base_path}/verify_sorter.php"
        response = requests.post(url, json={'identity': identity}, headers={'Content-Type': 'application/json'})
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                if data.get('registered'):
                    return True, None
                else:
                    response = requests.post(f"{base_path}/add_waiting_device.php", json={'identity': identity})
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
    try:
        import serial.tools.list_ports
        ports = serial.tools.list_ports.comports()
        candidates = []
        for port in ports:
            if 'Arduino' in port.description or '2560' in port.description or 'Mega' in port.description:
                print(f"Found Arduino-like device at: {port.device} ({port.description})")
                candidates.append(port.device)
        if not candidates:
            for port in ports:
                candidates.append(port.device)
        return candidates
    except Exception:
        return []

def connect_to_arduino(port):
    try:
        import serial
        try:
            temp_ser = serial.Serial(port)
            temp_ser.close()
        except Exception:
            pass
        ser = serial.Serial(port=port, baudrate=115200, timeout=1, write_timeout=1)
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

def play_sorting_audio(waste_type):
    if not PYGAME_AVAILABLE:
        return
    def play_audio_thread():
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
            except Exception as e:
                print(f"\n⚠️ Error playing audio: {e}")
    audio_thread = Thread(target=play_audio_thread, daemon=True)
    audio_thread.start()

def get_poppins_font(font_size):
    if not PIL_AVAILABLE:
        return None
    font_paths = [
        'fonts/Poppins-Regular.ttf',
        'fonts/Poppins-SemiBold.ttf',
        'fonts/Poppins-Bold.ttf',
        'C:/Windows/Fonts/poppins.ttf',
        'C:/Windows/Fonts/Poppins-Regular.ttf',
    ]
    for path in font_paths:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, font_size)
            except:
                continue
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
    if font is None:
        return (len(text) * 20, 30)
    try:
        bbox = font.getbbox(text)
        return (bbox[2] - bbox[0], bbox[3] - bbox[1])
    except:
        return (len(text) * 20, 30)

def draw_text_with_font(img, text, position, font_size, color, bold=False):
    if PIL_AVAILABLE:
        try:
            font = get_poppins_font_bold(font_size) if bold else get_poppins_font(font_size)
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

import random

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

def main():
    import sys
    if "--preview" in sys.argv:
        # Simulate a sorted item for preview
        _kiosk_state['last_sorted_time'] = time.time()
        _kiosk_state['current_waste'] = 'bio'
        _kiosk_state['current_message'] = random.choice(WASTE_MESSAGES['bio'])
        sorting_history = [{'waste_type': 'bio', 'item_name': 'Banana Peel', 'timestamp': '10:23:01', 'confidence': 0.91}]
        while True:
            frame = draw_kiosk_ui(sorting_history)
            cv2.namedWindow("Kiosk Preview", cv2.WINDOW_NORMAL)
            cv2.resizeWindow("Kiosk Preview", 1280, 720)
            cv2.imshow("Kiosk Preview", frame)
            key = cv2.waitKey(500) & 0xFF
            if key == ord('q') or key == 27:
                break
        cv2.destroyAllWindows()
        return

    config = load_config()
    base_path = get_base_path()
    print(f"\nUsing GoSort server at: {base_path}")

    sorting_history = []
    current_view = 'kiosk'
    a_key_pressed = False
    a_key_time = 0
    kiosk_maximized = False

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
                first_request = True
                continue
            elif key == 'c':
                print("\n⚠️ Clearing configuration...")
                remove_from_waiting_devices(sorter_id)
                config['sorter_id'] = None
                save_config(config)
                print("✅ Configuration cleared.")
                print("Please restart the application.")
                return
            elif key == 'q':
                print("\n❌ Registration cancelled. Exiting...")
                remove_from_waiting_devices(sorter_id)
                return
            else:
                print("\nChecking registration status...", end="", flush=True)
        time.sleep(2)
        if not first_request:
            print(".", end="", flush=True)

    last_heartbeat = 0
    heartbeat_interval = 5
    last_maintenance_status = False
    check_interval = 1

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

    if device_mode == 'gpu' and torch.cuda.is_available():
        device = torch.device('cuda')
        backend = 'CUDA (NVIDIA GPU)'
    else:
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

    model = YOLO('best885.pt')
    if device.type == 'cuda':
        model.to('cuda')

    model.conf = 0.50
    model.iou = 0.50

    sorting_recorder = SortingRecorderThread(base_path)
    bin_fullness_recorder = BinFullnessRecorderThread(base_path)

    try:
        import serial
        arduino = None
        command_handler = None
        arduino_connected = False
        candidate_ports = list_arduino_ports()
        for p in candidate_ports:
            ser = connect_to_arduino(p)
            if ser is None:
                continue
            try:
                arduino = ser
                print(f"Connected to Arduino on {p}")
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
                break
            except Exception as e:
                print(f"Failed to initialize serial on {p}: {e}")
                try:
                    ser.close()
                except Exception:
                    pass
                arduino = None
        if not arduino_connected:
            print("No Arduino found on any COM port.")
            def check_arduino_connection():
                return False
    except Exception as e:
        print(f"Error during Arduino port search: {e}")
        arduino = None
        command_handler = None
        arduino_connected = False
        def check_arduino_connection():
            return False

    print("Searching for available cameras...")
    available_cams = list_available_cameras()
    if not available_cams:
        print("No cameras found!")
        return

    cam_index = available_cams[0]
    current_cam_idx = 0
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

    while True:
        frame = stream.read()
        frame_count += 1

        current_time = time.time()
        if current_time - last_heartbeat >= heartbeat_interval:
            if arduino_connected and check_arduino_connection():
                if send_heartbeat(sorter_id):
                    last_heartbeat = current_time
                else:
                    print("\n Failed to send heartbeat")
                    remove_from_waiting_devices(sorter_id)
            else:
                print("\n Arduino disconnected - stopping heartbeats")
                remove_from_waiting_devices(sorter_id)
                break

        try:
            if arduino_connected and command_handler is not None and getattr(command_handler.arduino, 'in_waiting', 0):
                while command_handler.arduino.in_waiting:
                    try:
                        line = command_handler.arduino.readline().decode().strip()
                        if not line:
                            continue
                        if line.startswith('bin_fullness:'):
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

        current_maintenance = check_maintenance_mode(sorter_id)

        if current_maintenance != last_maintenance_status:
            if current_maintenance:
                print("\n Entering maintenance mode - Detection paused")
                print("Listening for maintenance commands...")
            else:
                print("\n Exiting maintenance mode - Detection resumed")
                try:
                    resp = requests.get(mapping_url)
                    mapping = resp.json().get('mapping', {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous'})
                except Exception as e:
                    print(f"Warning: Could not fetch mapping, using default. {e}")
                    mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous'}
                trash_to_cmd = {v: k for k, v in mapping.items()}
            last_maintenance_status = current_maintenance

        if current_maintenance:
            cv2.putText(frame, "MAINTENANCE MODE - Detection Paused", (10, 110),
                       cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)
            if not arduino_connected or not check_arduino_connection():
                print("\n Arduino disconnected - cannot execute maintenance commands")
                results = []
            else:
                try:
                    response = requests.post(
                        f"{base_path}/check_maintenance_commands.php",
                        json={'device_identity': sorter_id},
                        headers={'Content-Type': 'application/json'}
                    )
                    if response.status_code == 200:
                        data = response.json()
                        if data.get('success') and data.get('command'):
                            command = data['command']
                            print(f"\n📡 Received maintenance command from server: {command}")
                            print(f"Current mapping: {mapping}")
                            if command == 'shutdown':
                                print("\n⚠️ Shutdown command received. Shutting down computer...")
                                try:
                                    requests.post(f"{base_path}/mark_command_executed.php",
                                                  json={'device_identity': sorter_id, 'command': command})
                                except Exception as e:
                                    print(f"\n⚠️ Error marking shutdown command as executed: {e}")
                                os.system('shutdown /s /t 1 /f')
                                time.sleep(5)
                                break
                            if command_handler is not None:
                                if command_handler.command_queue.empty():
                                    if command in ['unclog', 'sweep1', 'sweep2']:
                                        print("Sending maintmode command to enable maintenance mode...")
                                        command_handler.arduino.write("maintmode\n".encode())
                                        time.sleep(0.5)
                                        while command_handler.arduino.in_waiting:
                                            response = command_handler.arduino.readline().decode().strip()
                                            if response:
                                                print(f" Arduino Response: {response}")
                                    print(f"Sending to Arduino: {command}")
                                    command_handler.arduino.write(f"{command}\n".encode())
                                    if command == 'unclog':
                                        time.sleep(6)
                                    elif command in ['sweep1', 'sweep2']:
                                        time.sleep(5)
                                    else:
                                        time.sleep(0.1)
                                    while command_handler.arduino.in_waiting:
                                        response = command_handler.arduino.readline().decode().strip()
                                        if response:
                                            print(f"Arduino Response: {response}")
                                    if command in ['unclog', 'sweep1', 'sweep2']:
                                        print("Sending maintend command to exit maintenance mode...")
                                        command_handler.arduino.write("maintend\n".encode())
                                        time.sleep(0.5)
                                        while command_handler.arduino.in_waiting:
                                            response = command_handler.arduino.readline().decode().strip()
                                            if response:
                                                print(f"🟢 Arduino Response: {response}")
                                    if command in ['ndeg', 'zdeg', 'odeg']:
                                        trash_type = mapping.get(command)
                                        if trash_type:
                                            try:
                                                record = SortingRecord(
                                                    sorter_id=sorter_id,
                                                    trash_type=trash_type,
                                                    trash_class='Maintenance Sort',
                                                    confidence=1.0,
                                                    image_base64='',
                                                    is_maintenance=True
                                                )
                                                sorting_recorder.queue_record(record)
                                                timestamp = datetime.now().strftime("%H:%M:%S")
                                                sorting_history.insert(0, {
                                                    'waste_type': trash_type,
                                                    'item_name': 'Maintenance Sort',
                                                    'timestamp': timestamp,
                                                    'confidence': 1.0
                                                })
                                                if len(sorting_history) > 20:
                                                    sorting_history.pop()
                                                _kiosk_state['last_sorted_time'] = time.time()
                                                _kiosk_state['current_waste'] = trash_type
                                                _kiosk_state['current_message'] = random.choice(WASTE_MESSAGES.get(trash_type, ['Sorted!']))
                                                play_sorting_audio(trash_type)
                                            except Exception as e:
                                                print(f"\n⚠️ Error recording sorting: {e}")
                                    requests.post(f"{base_path}/mark_command_executed.php",
                                                  json={'device_identity': sorter_id, 'command': command})
                except Exception as e:
                    print(f"\n❌ Error checking maintenance commands: {e}")
            results = []
        else:
            results = model.predict(frame, stream=False)

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
                    cv2.putText(frame, f"{detected_item} {conf:.2f}", (x1, y1 - 10),
                              cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)

                    if conf > 0.50:
                        command = map_category_to_command(class_name, mapping)
                        trash_type = mapping.get(command, 'nbio')
                        try:
                            print(f"✅ Detection: {detected_item} ({conf:.2f}) - Category: {class_name}")
                            _, buffer = cv2.imencode('.jpg', clean_frame)
                            image_base64 = base64.b64encode(buffer).decode('utf-8')
                            detected_classes = []
                            if trash_type == 'mixed':
                                for other_box in boxes:
                                    other_conf = other_box.conf[0]
                                    if other_conf > 0.50:
                                        other_class_id = int(other_box.cls[0])
                                        other_item = model.names[other_class_id]
                                        detected_classes.append(other_item)
                            else:
                                detected_classes = [detected_item]
                            trash_class_str = ', '.join(detected_classes)
                            record = SortingRecord(
                                sorter_id=sorter_id,
                                trash_type=trash_type,
                                trash_class=trash_class_str,
                                confidence=float(conf),
                                image_base64=image_base64,
                                is_maintenance=False
                            )
                            sorting_recorder.queue_record(record)
                            timestamp = datetime.now().strftime("%H:%M:%S")
                            sorting_history.insert(0, {
                                'waste_type': trash_type,
                                'item_name': detected_item,
                                'timestamp': timestamp,
                                'confidence': float(conf)
                            })
                            if len(sorting_history) > 20:
                                sorting_history.pop()
                            # Update kiosk state
                            _kiosk_state['last_sorted_time'] = time.time()
                            _kiosk_state['current_waste'] = trash_type
                            _kiosk_state['current_message'] = random.choice(WASTE_MESSAGES.get(trash_type, ['Sorted!']))
                            play_sorting_audio(trash_type)
                            print(f"✅ Detection: {detected_item} ({conf:.2f}) - Queued for posting")
                            if command_handler is not None:
                                if command_handler.command_queue.empty():
                                    print(f"⏱️ Queued sorting command: {command}")
                                    cmd = ArduinoCommand(f"{command}\n")
                                    command_handler.command_queue.put(cmd)
                                else:
                                    print("⏳ Arduino busy - skipping this detection")
                        except Exception as e:
                            print(f"❌ Error processing detection: {e}")

        ui_panel = np.zeros((100, frame.shape[1], 3), dtype=np.uint8)
        cv2.rectangle(ui_panel, (10, 10), (150, 40), (100, 200, 255), -1)
        cv2.putText(ui_panel, "Server OK", (30, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)
        cv2.rectangle(ui_panel, (170, 10), (310, 40), (0, 255, 0), -1)
        cv2.putText(ui_panel, "Change ID", (190, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)
        cv2.rectangle(ui_panel, (330, 10), (490, 40), (100, 200, 255), -1)
        camera_label = f"Camera ({current_cam_idx + 1}/{len(available_cams)})"
        cv2.putText(ui_panel, camera_label, (345, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 0), 2)
        cv2.rectangle(ui_panel, (510, 10), (650, 40), (0, 255, 0), -1)
        cv2.putText(ui_panel, "Reconfig All", (520, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 2)
        cv2.rectangle(ui_panel, (670, 10), (810, 40), (0, 0, 255), -1)
        cv2.putText(ui_panel, "Exit", (715, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)

        cv2.putText(frame, f"FPS: {fps}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
        device_text = f"GPU: {device_name}" if torch.cuda.is_available() else f"CPU: {device_name}"
        cv2.putText(frame, device_text, (10, 70), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
        view_text = f"View: {current_view.upper()} (Press A+S to toggle)"
        text_size = cv2.getTextSize(view_text, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 1)[0]
        text_x = frame.shape[1] - text_size[0] - 10
        cv2.putText(frame, view_text, (text_x, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 2)

        combined_frame = np.vstack((frame, ui_panel))

        if current_view == 'both' or current_view == 'camera':
            cv2.imshow("YOLOv11 Detection", combined_frame)
        else:
            try:
                cv2.destroyWindow("YOLOv11 Detection")
            except cv2.error:
                pass

        if current_view == 'both' or current_view == 'kiosk':
            kiosk_frame = draw_kiosk_ui(sorting_history, current_view)
            cv2.namedWindow("GoSort Kiosk - Sorting Display", cv2.WINDOW_NORMAL)
            cv2.resizeWindow("GoSort Kiosk - Sorting Display", 1920, 1080)
            cv2.imshow("GoSort Kiosk - Sorting Display", kiosk_frame)
            if not kiosk_maximized:
                try:
                    time.sleep(0.1)
                    if platform.system() == 'Windows':
                        import ctypes
                        hwnd = ctypes.windll.user32.FindWindowW(None, "GoSort Kiosk - Sorting Display")
                        if hwnd:
                            ctypes.windll.user32.ShowWindow(hwnd, 3)
                    else:
                        cv2.setWindowProperty("GoSort Kiosk - Sorting Display",
                                            cv2.WND_PROP_FULLSCREEN,
                                            cv2.WINDOW_FULLSCREEN)
                    kiosk_maximized = True
                except Exception as e:
                    cv2.resizeWindow("GoSort Kiosk - Sorting Display", 1920, 1080)
                    kiosk_maximized = True
        else:
            try:
                cv2.destroyWindow("GoSort Kiosk - Sorting Display")
            except cv2.error:
                pass

        def mouse_callback(event, x, y, flags, param):
            nonlocal current_cam_idx, stream
            if event == cv2.EVENT_LBUTTONDOWN:
                y = y - frame.shape[0]
                if 10 <= y <= 40:
                    if 10 <= x <= 150:
                        print(f"\n✅ Using fixed GoSort server: {base_path}")
                    elif 170 <= x <= 310:
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
                        exit()
                    elif 330 <= x <= 490:
                        if len(available_cams) > 1:
                            print("\n🔄 Switching camera...")
                            stream.stop()
                            current_cam_idx = (current_cam_idx + 1) % len(available_cams)
                            cam_index = available_cams[current_cam_idx]
                            print(f"Switched to camera {current_cam_idx + 1}/{len(available_cams)} (Index: {cam_index})")
                            stream = VideoStream(cam_index).start()
                            time.sleep(1.0)
                        else:
                            print("\n⚠️ Only one camera available")
                    elif 510 <= x <= 650:
                        print("\nReconfiguring All Settings")
                        config = {}
                        save_config(config)
                        print("\nAll configuration cleared. Please restart the application.")
                        cv2.destroyAllWindows()
                        stream.stop()
                        if command_handler:
                            command_handler.stop()
                        exit()
                    elif 670 <= x <= 810:
                        cv2.destroyAllWindows()
                        stream.stop()
                        if command_handler:
                            command_handler.stop()
                        exit()

        if current_view == 'both' or current_view == 'camera':
            try:
                cv2.setMouseCallback("YOLOv11 Detection", mouse_callback)
            except cv2.error:
                pass

        key = cv2.waitKey(1) & 0xFF
        if key == ord('a') or key == ord('A'):
            a_key_pressed = True
            a_key_time = time.time()
        if (key == ord('s') or key == ord('S')) and a_key_pressed:
            if time.time() - a_key_time < 0.5:
                if current_view == 'both':
                    current_view = 'camera'
                    print("\n🔄 Switched to Camera view only (Press A+S to toggle)")
                elif current_view == 'camera':
                    current_view = 'kiosk'
                    print("\n🔄 Switched to Kiosk view only (Press A+S to toggle)")
                elif current_view == 'kiosk':
                    current_view = 'both'
                    print("\n🔄 Switched to Both views (Press A+S to toggle)")
            a_key_pressed = False
        if a_key_pressed and time.time() - a_key_time > 0.5:
            a_key_pressed = False
        if key == ord('q') or key == 27:
            print("\n👋 Exiting application...")
            break

    sorting_recorder.stop()
    bin_fullness_recorder.stop()
    stream.stop()
    if command_handler:
        command_handler.stop()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    main()