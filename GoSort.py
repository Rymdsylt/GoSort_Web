import serial
import serial.tools.list_ports
import time
import requests
import json
import os
import socket
import concurrent.futures
import threading
import ipaddress
import msvcrt
import sys

def check_maintenance_mode(ip_address, device_identity):
    try:
        url = f"http://{ip_address}/GoSort_Web/gs_DB/check_maintenance.php"
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

def load_config():
    config_file = 'gosort_config.json'
    if os.path.exists(config_file):
        with open(config_file, 'r') as f:
            return json.load(f)
    return {'ip_address': None, 'sorter_id': None}

def save_config(config):
    with open('gosort_config.json', 'w') as f:
        json.dump(config, f)

def scan_network():
    print("\nScanning network for available devices...")
    available_ips = []
    gosort_ips = []
    
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(('10.255.255.255', 1))
        local_ip = s.getsockname()[0]
    except Exception:
        local_ip = '127.0.0.1'
    finally:
        s.close()
    ip_parts = local_ip.split('.')
    network_prefix = '.'.join(ip_parts[:3])
    
    network_ips = [f"{network_prefix}.{i}" for i in range(1, 255)]
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
            response = requests.get(f"http://{ip}/GoSort_Web/gs_DB/trash_detected.php", 
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
    with concurrent.futures.ThreadPoolExecutor(max_workers=50) as executor:
        executor.map(check_ip, network_ips)

    print("\n\nScan complete!")
    
    gosort_ips = sorted(list(set(gosort_ips)))
    available_ips = sorted(list(set(available_ips) - set(gosort_ips)))
    
    return gosort_ips, available_ips

def check_server(ip):
    print("\rChecking server...", end="", flush=True)
    try:
        response = requests.get(f"http://{ip}/GoSort_Web/gs_DB/trash_detected.php", timeout=5)
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
            gosort_ips, available_ips = scan_network()
            
            if not gosort_ips and not available_ips:
                print("\nNo devices found in the network.")
                ip = input("\nEnter GoSort IP address manually (e.g., 192.168.1.100): ")
            else:
                print("\nAvailable IP addresses:")
                if gosort_ips:
                    print("\n🟢 GoSort servers found:")
                    for i, ip_addr in enumerate(gosort_ips):
                        print(f"{i+1}. {ip_addr}")
                
                if available_ips:
                    print("\n⚪ Other devices found:")
                    offset = len(gosort_ips)
                    for i, ip_addr in enumerate(available_ips):
                        print(f"{i+offset+1}. {ip_addr}")
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
        
        if check_server(ip):
            config['ip_address'] = ip
            save_config(config)
            return ip
        else:
            ip = None
            config['ip_address'] = None
            save_config(config)

def list_arduino_ports():
    ports = serial.tools.list_ports.comports()
    mega_ports = []
    
    for port in ports:
        if 'Arduino' in port.description or '2560' in port.description:
            print(f"Found Arduino Mega at: {port.device}")
            mega_ports.append(port.device)
    
    return mega_ports

def connect_to_arduino(port):
    try:
        try:
            temp_ser = serial.Serial(port)
            temp_ser.close()
        except:
            pass

        ser = serial.Serial(
            port=port,
            baudrate=19200,
            timeout=1,
            write_timeout=1,
            exclusive=True
        )
        time.sleep(3) 
        ser.reset_input_buffer()
        return ser
    except serial.SerialException as e:
        if 'PermissionError' in str(e):
            print(f"Error: Port {port} is being used by another program.")
            print("Close any other programs using it (e.g., Arduino IDE).")
        else:
            print(f"Error connecting to {port}: {e}")
        return None
    except Exception as e:
        print(f"Unexpected error while connecting to {port}: {e}")
        return None

def add_to_waiting_devices(ip_address, device_identity):
    try:
        url = f"http://{ip_address}/GoSort_Web/gs_DB/add_waiting_device.php"
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

def request_registration(ip_address, identity):
    try:
        # First check if device is in sorters table
        url = f"http://{ip_address}/GoSort_Web/gs_DB/verify_sorter.php"
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
                        f"http://{ip_address}/GoSort_Web/gs_DB/add_waiting_device.php",
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

def is_identity_duplicate(ip_address, identity):
    try:
        print("Checking for identical identity...")
        url = f"http://{ip_address}/GoSort_Web/gs_DB/check_duplicate_identity.php"
        response = requests.post(url, json={'identity': identity}, headers={'Content-Type': 'application/json'})
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                status = data.get('status')
                if status == 'waiting':
                    return True, "waiting"
                elif status == 'registered':
                    return True, "registered"
        return False, None
    except Exception as e:
        print(f"Error checking duplicate identity: {e}")
        return False, None

def remove_from_waiting_devices(ip_address, device_identity):
    try:
        url = f"http://{ip_address}/GoSort_Web/gs_DB/remove_waiting_device.php"
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

def main():
    config = load_config()
    # First get IP address
    ip_address = get_ip_address()
    print(f"\nUsing GoSort server at: {ip_address}")

    # Then get or set identity
    config = load_config()
    if config.get('sorter_id') is None:
        print("\nFirst time setup - Sorter Identity Configuration")
        while True:
            sorter_id = input("Enter Sorter Identity (e.g., Sorter1): ")
            is_duplicate, status = is_identity_duplicate(ip_address, sorter_id)
            if is_duplicate:
                if status == "waiting":
                    print("Identical Identity Found in waiting list, reenter")
                elif status == "registered":
                    print("Identical Identity Found in registered devices, reenter")
                continue
            config['sorter_id'] = sorter_id
            save_config(config)
            break
    
    # Fetch mapping from backend
    mapping_url = f"http://{ip_address}/GoSort_Web/gs_DB/save_sorter_mapping.php?device_identity={config['sorter_id']}"
    try:
        resp = requests.get(mapping_url)
        mapping = resp.json().get('mapping', {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'recyc'})
    except Exception as e:
        print(f"Warning: Could not fetch mapping, using default. {e}")
        mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'recyc'}
    # For menu display: get the order and labels
    menu_order = [('zdeg', mapping['zdeg']), ('ndeg', mapping['ndeg']), ('odeg', mapping['odeg'])]
    trash_labels = {'bio': 'Biodegradable', 'nbio': 'Non-Biodegradable', 'recyc': 'Recyclable'}

    print("\nRequesting device registration with the server...")
    dots_thread = None

    def print_waiting_dots():
        while True:
            print(".", end="", flush=True)
            time.sleep(1)

    registered = False
    first_request = True

    def print_waiting_menu():
        print("\n\nOptions while waiting:")
        print("r - Reconfigure Identity")
        print("a - Reconfigure All (IP and Identity)")
        print("q - Quit")
        print("\nPress any other key to check registration status...")

    while not registered:
        registered, status = request_registration(ip_address, config['sorter_id'])
        
        if registered:
            print("\n✅ Device registration confirmed!")
            break
        elif status == "duplicate":
            print("\n❌ This identity is already in the waiting list")
            sorter_id = input("Please enter a different Sorter Identity: ")
            config['sorter_id'] = sorter_id
            save_config(config)
            first_request = True
            continue
        elif first_request:
            print("\n⏳ Waiting for admin approval in the GoSort web interface")
            print(f"    Device Identity: {config['sorter_id']}")
            print("    Please approve this device in the web interface...")
            print_waiting_menu()
            first_request = False
        
        # Check for keyboard input (non-blocking)
        if msvcrt.kbhit():
            key = msvcrt.getch().decode().lower()
            if key == 'r':
                print("\nReconfiguring Sorter Identity")
                sorter_id = input("Enter new Sorter Identity (e.g., Sorter1): ")
                config['sorter_id'] = sorter_id
                save_config(config)
                print("\n⏳ Trying with new identity:", config['sorter_id'])
                first_request = True  # Reset to show the waiting message again
                continue
            elif key == 'a':
                print("\n⚙️ Reconfiguring All Settings...")
                # Remove from waiting devices before clearing config
                remove_from_waiting_devices(ip_address, config['sorter_id'])
                # Clear IP and identity
                config['ip_address'] = None
                config['sorter_id'] = None
                save_config(config)
                print("\n✅ All configuration cleared. Please restart the application.")
                return
            elif key == 'q':
                print("\n❌ Registration cancelled. Exiting...")
                # Remove from waiting devices before exiting
                remove_from_waiting_devices(ip_address, config['sorter_id'])
                return
            else:
                print("\nChecking registration status...", end="", flush=True)
        
        time.sleep(2)  # Check every 2 seconds
        if not first_request:
            print(".", end="", flush=True)

    # Ready to connect to Arduino
    
    mega_ports = list_arduino_ports()
    
    if not mega_ports:
        print("No Arduino Mega found. Please check connection.")
        return
    
    if len(mega_ports) > 1:
        print("\nMultiple Arduino ports found. Choose:")
        for i, port in enumerate(mega_ports):
            print(f"{i+1}. {port}")
        choice = int(input("Enter the number of your choice: ")) - 1
        port = mega_ports[choice]
    else:
        port = mega_ports[0]
    
    ser = connect_to_arduino(port)
    if not ser:
        return
    
    ser.write('gosort_ready\n'.encode())
    time.sleep(0.1)
    
    while ser.in_waiting:
        response = ser.readline().decode().strip()
        if response:
            print(f"🟢 Arduino Response: {response}")
    
    print("\n✅ Connected to Arduino Mega 2560")
    
    def print_menu():
        print("\nTrash Selection Menu:")
        for idx, (deg, ttype) in enumerate(menu_order, 1):
            label = trash_labels.get(ttype, ttype)
            print(f"{idx}. {label}")
        print("r. Reconfigure IP")
        print("i. Reconfigure Identity")
        print("c. Clear All Configuration")
        print("q. Quit")

    print_menu()
    last_maintenance_status = False
    check_interval = 1  # Check maintenance mode every second

    last_heartbeat = 0
    heartbeat_interval = 10  # Send heartbeat every 10 seconds

    while True:
        # Send heartbeat periodically to keep device online
        current_time = time.time()
        if current_time - last_heartbeat >= heartbeat_interval:
            try:
                # Send heartbeat to update last_active
                requests.post(
                    f"http://{ip_address}/GoSort_Web/gs_DB/verify_sorter.php",
                    json={'identity': config['sorter_id']},
                    headers={'Content-Type': 'application/json'}
                )
                last_heartbeat = current_time
            except Exception as e:
                print(f"\n⚠️ Heartbeat error: {e}")
                # Remove from waiting devices if heartbeat fails
                remove_from_waiting_devices(ip_address, config['sorter_id'])

        # Check maintenance mode periodically
        current_maintenance = check_maintenance_mode(ip_address, config['sorter_id'])
        
        # If maintenance status changed, notify user
        if current_maintenance != last_maintenance_status:
            if current_maintenance:
                print("\n🔧 Entering maintenance mode - Controls disabled")
                print("Listening for maintenance commands...")
            else:
                print("\n✅ Exiting maintenance mode - Controls enabled")
                # Re-fetch mapping after maintenance mode
                try:
                    resp = requests.get(mapping_url)
                    mapping = resp.json().get('mapping', {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'recyc'})
                except Exception as e:
                    print(f"Warning: Could not fetch mapping, using default. {e}")
                    mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'recyc'}
                menu_order = [('zdeg', mapping['zdeg']), ('ndeg', mapping['ndeg']), ('odeg', mapping['odeg'])]
                print_menu()
            last_maintenance_status = current_maintenance

        # If in maintenance mode, check for and execute maintenance commands
        if current_maintenance:
            try:
                response = requests.post(
                    f"http://{ip_address}/GoSort_Web/gs_DB/check_maintenance_commands.php",
                    json={'device_identity': config['sorter_id']},
                    headers={'Content-Type': 'application/json'}
                )
                if response.status_code == 200:
                    data = response.json()
                    if data.get('success') and data.get('command'):
                        command = data['command']
                        print(f"\n📡 Received maintenance command from server: {command}")
                        print(f"Current mapping: {mapping}")
                        print(f"Sending to Arduino: {command}")
                        ser.write(f"{command}\n".encode())
                        time.sleep(0.1)
                        
                        while ser.in_waiting:
                            response = ser.readline().decode().strip()
                            if response:
                                print(f"🟢 Arduino Response: {response}")
                        
                        # Record the sorting operation if it's a sorting command
                        if command in ['ndeg', 'zdeg', 'odeg']:
                            # Find the trash type for this servo command using mapping
                            trash_type = mapping.get(command)
                            if trash_type:
                                try:
                                    requests.post(
                                        f"http://{ip_address}/GoSort_Web/gs_DB/record_sorting.php",
                                        json={
                                            'device_identity': config['sorter_id'],
                                            'trash_type': trash_type,
                                            'is_maintenance': True
                                        }
                                    )
                                except Exception as e:
                                    print(f"\n⚠️ Error recording sorting: {e}")
                        
                        # Mark command as executed
                        requests.post(
                            f"http://{ip_address}/GoSort_Web/gs_DB/mark_command_executed.php",
                            json={'device_identity': config['sorter_id'], 'command': command}
                        )
                        if command == 'shutdown':
                            print("\n⚠️ Shutdown command received. Shutting down computer...")
                            # Mark command as executed before shutdown
                            try:
                                requests.post(
                                    f"http://{ip_address}/GoSort_Web/gs_DB/mark_command_executed.php",
                                    json={'device_identity': config['sorter_id'], 'command': command}
                                )
                            except Exception as e:
                                print(f"\n⚠️ Error marking shutdown command as executed: {e}")
                            os.system('shutdown /s /t 1 /f')
                            time.sleep(5)
                            break
            except Exception as e:
                print(f"\n❌ Error checking maintenance commands: {e}")
            
            time.sleep(check_interval)
            continue

        # Process normal operation input only if available and not in maintenance mode
        if msvcrt.kbhit():
            choice = msvcrt.getch().decode().lower()
            
            if choice == 'q':
                break
            elif choice == 'r':
                config = load_config()
                config['ip_address'] = None
                save_config(config)
                print("\nIP configuration reset. Please restart the application.")
                break
            elif choice == 'i':
                config = load_config()
                print("\nReconfiguring Sorter Identity")
                sorter_id = input("Enter new Sorter Identity (e.g., Sorter1): ")
                config['sorter_id'] = sorter_id
                save_config(config)
                print("\nSorter Identity updated. Please restart the application.")
                break
            elif choice == 'c':
                # Clear all configuration
                print("\n⚠️ Clearing all configuration...")
                if os.path.exists('gosort_config.json'):
                    os.remove('gosort_config.json')
                print("✅ All configuration cleared. Please restart the application.")
                break
            elif choice in ['1', '2', '3']:
                 idx = int(choice) - 1
                 if idx < 0 or idx >= len(menu_order):
                     print("Invalid choice.")
                     continue
                 trash_type = menu_order[idx][1]
                 # Find the servo command for this trash type
                 command = None
                 for servo_key, ttype in mapping.items():
                     if ttype == trash_type:
                         command = servo_key
                         break
                 if not command:
                     command = 'zdeg'  # Default fallback
                 ser.write(f"{command}\n".encode())
                 print(f"\n🔄 Moving to {command.upper()}...")
                 time.sleep(0.1)
                 while ser.in_waiting:
                     response = ser.readline().decode().strip()
                     if response:
                         print(f"🟢 Arduino Response: {response}")
                 
                 # Record the sorting operation
                 try:
                     requests.post(
                         f"http://{ip_address}/GoSort_Web/gs_DB/record_sorting.php",
                         json={
                             'device_identity': config['sorter_id'],
                             'trash_type': trash_type,
                             'is_maintenance': False
                         }
                     )
                 except Exception as e:
                     print(f"\n⚠️ Error recording sorting: {e}")
                 
                 print_menu()
            elif choice not in ['\r', '\n']:  # Ignore enter key presses
                print("\nInvalid choice. Please choose 1, 2, 3, r for IP config, i for Identity config, or q to quit")
        
        # Add a small delay to prevent the loop from running too fast
        time.sleep(0.1)
    
    ser.close()
    print("🔌 Connection closed")

if __name__ == "__main__":
    main()
