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
import socket
import concurrent.futures
import threading

def load_config():
    config_file = 'gosort_config.json'
    if os.path.exists(config_file):
        with open(config_file, 'r') as f:
            return json.load(f)
    return {'ip_address': None}

def save_config(config):
    with open('gosort_config.json', 'w') as f:
        json.dump(config, f)

def scan_network():
    print("\nScanning network for available devices...")
    available_ips = []
    gosort_ips = []
    
    # Get local IP address
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        # Doesn't need to be reachable
        s.connect(('10.255.255.255', 1))
        local_ip = s.getsockname()[0]
    except Exception:
        local_ip = '127.0.0.1'
    finally:
        s.close()

    # Get network address
    ip_parts = local_ip.split('.')
    network_prefix = '.'.join(ip_parts[:3])
    
    # Create list of all possible IPs in the network
    network_ips = [f"{network_prefix}.{i}" for i in range(1, 255)]
    
    # Progress tracking
    total_ips = len(network_ips)
    scanned_ips = 0
    print_lock = threading.Lock()

    def update_progress():
        nonlocal scanned_ips
        with print_lock:
            scanned_ips += 1
            progress = (scanned_ips / total_ips) * 100
            print(f"\rScanning network... {progress:.1f}% complete", end="", flush=True)

    def check_ip(ip):
        try:
            # Quick ping test first (0.5 second timeout)
            response = requests.get(f"http://{ip}/GoSort/gs_DB/trash_detected.php", 
                                 timeout=0.5)
            if response.status_code == 200 or (
                response.status_code == 400 and 
                "No trash type provided" in response.text
            ):
                gosort_ips.append(str(ip))
            else:
                available_ips.append(str(ip))
        except:
            pass
        update_progress()

    # Use ThreadPoolExecutor to scan IPs concurrently
    with concurrent.futures.ThreadPoolExecutor(max_workers=50) as executor:
        executor.map(check_ip, network_ips)

    print("\n\nScan complete!")
    
    # Sort and remove duplicates
    gosort_ips = sorted(list(set(gosort_ips)))
    available_ips = sorted(list(set(available_ips) - set(gosort_ips)))
    
    return gosort_ips, available_ips

def check_server(ip):
    print("\rChecking server...", end="", flush=True)
    try:
        response = requests.get(f"http://{ip}/GoSort/gs_DB/trash_detected.php", timeout=5)
        # The server returns 400 with "No trash type provided" if it exists
        if response.status_code == 200 or (response.status_code == 400 and "No trash type provided" in response.text):
            print("\r✅ Server connection successful!")
            return True
        print("\r❌ GoSort does not exist in this server")
        return False
    except requests.exceptions.RequestException:
        print("\r❌ GoSort does not exist in this server")
        return False

def get_ip_address():
    config = load_config()
    ip = config.get('ip_address')
    
    while True:
        if not ip:
            # Scan network for available IPs
            gosort_ips, available_ips = scan_network()
            
            if not gosort_ips and not available_ips:
                print("\nNo devices found in the network.")
                ip = input("\nEnter GoSort IP address manually (e.g., 192.168.1.100): ")
            else:
                print("\nAvailable IP addresses:")
                
                # First list GoSort servers if any
                if gosort_ips:
                    print("\n🟢 GoSort servers found:")
                    for i, ip_addr in enumerate(gosort_ips):
                        print(f"{i+1}. {ip_addr}")
                
                # Then list other available IPs
                if available_ips:
                    print("\n⚪ Other devices found:")
                    offset = len(gosort_ips)
                    for i, ip_addr in enumerate(available_ips):
                        print(f"{i+offset+1}. {ip_addr}")
                
                # Manual entry option
                print(f"{len(gosort_ips) + len(available_ips) + 1}. Enter IP manually")
                
                while True:
                    try:
                        choice = int(input("\nChoose an IP address (enter the number): "))
                        if 1 <= choice <= len(gosort_ips):
                            ip = gosort_ips[choice-1]
                            break
                        elif len(gosort_ips) < choice <= len(gosort_ips) + len(available_ips):
                            ip = available_ips[choice-len(gosort_ips)-1]
                            break
                        elif choice == len(gosort_ips) + len(available_ips) + 1:
                            ip = input("\nEnter GoSort IP address manually: ")
                            break
                        else:
                            print("Invalid choice. Please try again.")
                    except ValueError:
                        print("Invalid input. Please enter a number.")
        
        # Verify the selected IP
        if check_server(ip):
            config['ip_address'] = ip
            save_config(config)
            return ip
        else:
            # If check fails, clear the IP and start over
            ip = None
            config['ip_address'] = None
            save_config(config)

class ArduinoCommand:
    def __init__(self, command):
        self.command = command
        self.done = False

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
        # Try DirectShow first
        self.stream = cv2.VideoCapture(src, cv2.CAP_DSHOW)
        if not self.stream.isOpened():
            # If DirectShow fails, try default
            self.stream = cv2.VideoCapture(src)
        
        if self.stream.isOpened():
            # Set buffer size and resolution
            self.stream.set(cv2.CAP_PROP_BUFFERSIZE, 1)  # Minimum buffer size
            self.stream.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
            self.stream.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
            self.stream.set(cv2.CAP_PROP_FPS, 30)
            # Reduce internal OpenCV buffer
            self.stream.set(cv2.CAP_PROP_FOURCC, cv2.VideoWriter_fourcc('M', 'J', 'P', 'G'))
        
        self.stopped = False
        self.Q = Queue(maxsize=2)  # Reduced queue size to minimize latency

    def start(self):
        thread = Thread(target=self.update, args=(), daemon=True)
        thread.start()
        return self

    def update(self):
        while True:
            if self.stopped:
                return

            # Always read frame to clear the buffer
            ret, frame = self.stream.read()
            if not ret:
                self.stop()
                return
            
            # If queue is full, remove old frame
            if self.Q.full():
                try:
                    self.Q.get_nowait()
                except:
                    pass
            
            try:
                self.Q.put_nowait(frame)
            except:
                pass  # Skip frame if queue is full

    def read(self):
        return self.Q.get()

    def stop(self):
        self.stopped = True
        self.stream.release()

    def release(self):
        self.stop()

def list_available_cameras(max_cams=10):
    """Try to detect available cameras using multiple backends."""
    available = []

    cap = cv2.VideoCapture(0)
    if cap.isOpened():
        ret, _ = cap.read()
        if ret:
            print("Camera 0 is available (default backend)")
            available.append(0)
        cap.release()

    if not available:
        cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
        if cap.isOpened():
            ret, _ = cap.read()
            if ret:
                print("Camera 0 is available (DirectShow)")
                available.append(0)
            cap.release()
    
    return available

def main():
    # Get IP address first
    ip_address = get_ip_address()
    print(f"\nUsing GoSort server at: {ip_address}")

    device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
    print(f"Using device: {device}")
    
    device_name = ""
    if torch.cuda.is_available():
        device_name = torch.cuda.get_device_name(0)
        print(f"GPU: {device_name}")
    else:
        import platform, cpuinfo
        device_name = cpuinfo.get_cpu_info()['brand_raw']
        print(f"CPU: {device_name}")
    
    model = YOLO('best.pt')
    if torch.cuda.is_available():
        model.to('cuda')

    model.conf = 0.78
    model.iou = 0.45

    try:
        import serial
        # Try to connect to Arduino Mega 2560
        arduino = serial.Serial('COM3', 19200, timeout=1)
        time.sleep(2)  # Wait for Arduino to reset after serial connection
        print("Connected to Arduino Mega 2560")
        
        # Send gosort_ready directly through serial
        arduino.write(b'gosort_ready\n')
        print("Sent gosort_ready signal")
        
        # Wait for initialization response
        while arduino.in_waiting:
            response = arduino.readline().decode().strip()
            print(f"Arduino: {response}")
        
        # Create command handler after initialization
        command_handler = CommandHandler(arduino)
    except Exception as e:
        print(f"Failed to connect to Arduino: {e}")
        arduino = None
        command_handler = None

    print("Searching for available cameras...")

    available_cams = list_available_cameras()
    
    if not available_cams:
        print("No cameras found!")
        return

    cam_index = available_cams[0]
    print(f"Using camera index: {cam_index}")

   
    print("Starting video stream...")
    vs = VideoStream(cam_index)
    stream = vs.start()
    time.sleep(1.0)  


    fps = 0
    fps_time = time.time()
    frame_count = 0

    while True:
        frame = stream.read()
        frame_count += 1

       
        current_time = time.time()
        if current_time - fps_time >= 1.0:
            fps = frame_count
            frame_count = 0
            fps_time = current_time

              
        with torch.cuda.amp.autocast(), torch.inference_mode(): 
            results = model(frame, stream=True)  

        for result in results:
            boxes = result.boxes.cpu().numpy()
            for box in boxes:
                x1, y1, x2, y2 = box.xyxy[0].astype(int)
                conf = box.conf[0]
                class_id = int(box.cls[0])
                class_name = model.names[class_id]

           
                cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)
                label = f"{class_name} {conf:.2f}"
                cv2.putText(frame, label, (x1, y1 - 10),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)
                
     
                if command_handler is not None and conf > 0.78:
                    # Map detected class to trash type
                    trash_type = ''
                    if class_name.lower() in ['plastic', 'metal', 'glass', 'botol_kaca', 'botol_kaleng']:
                        trash_type = 'recyc'
                    elif class_name.lower() in ['paper', 'food', 'organic']:
                        trash_type = 'bio'
                    else:
                        trash_type = 'nbio'
                        
                    # Send detection to server
                    try:
                        url = f"http://{ip_address}/GoSort/gs_DB/trash_detected.php"
                        response = requests.get(url, params={'type': trash_type})
                        if response.status_code == 200:
                            print(f"✅ Detection recorded: {class_name} ({conf:.2f})")
                        else:
                            print(f"❌ Failed to record detection: {response.text}")
                            print(f"Status code: {response.status_code}")
                    except requests.exceptions.RequestException as e:
                        print(f"❌ Error connecting to server: {e}")
                        
                    # Only send command if the command queue is empty
                    if command_handler.command_queue.empty():
                        # Send the mapped trash_type to Arduino
                        command = f"{trash_type}\n"
                        command_handler.send_command(command)
                        
                        # Add delay to match Arduino servo movement time (1 second)
                        time.sleep(1.0)
        cv2.putText(frame, f"FPS: {fps}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
        device_text = f"GPU: {device_name}" if torch.cuda.is_available() else f"CPU: {device_name}"
        cv2.putText(frame, device_text, (10, 70), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)

        # Show the result
        cv2.imshow("YOLOv8 Detection", frame)

        # Press 'q' to exit, 'r' to reconfigure IP
        key = cv2.waitKey(1) & 0xFF
        if key == ord('q'):
            break
        elif key == ord('r'):
            # Delete IP from config and get new one
            config = load_config()
            config['ip_address'] = None
            save_config(config)
            ip_address = get_ip_address()
            print(f"\nUpdated GoSort server address to: {ip_address}")

    # Release resources
    stream.stop()
    if command_handler:
        command_handler.stop()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    main()
