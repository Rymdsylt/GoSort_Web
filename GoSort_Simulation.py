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

def check_maintenance_mode(device_identity):
    try:
        base_path = get_base_path()
        url = f"{base_path}/check_maintenance.php"
        response = requests.post(
            url,
            json={'identity': device_identity},
            headers={'Content-Type': 'application/json'},
            timeout=5
        )
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                return data.get('maintenance_mode') == 1
        return False
    except (requests.exceptions.Timeout, requests.exceptions.ConnectionError):
        # Silently fail on timeouts - maintenance mode remains unchanged
        return False
    except Exception as e:
        # Only print unexpected errors, not timeout/connection errors
        if not any(err in str(type(e).__name__) for err in ['Timeout', 'ConnectionError']):
            print(f"\n⚠️ Maintenance mode check failed: {type(e).__name__}")
        return False

def get_base_path():
    """Return the fixed server URL"""
    return "https://gosortweb-production.up.railway.app/gs_DB"

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
    # Network scanning no longer needed - using fixed server URL
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
    # Fixed server URL - no configuration needed
    return get_base_path()

def add_to_waiting_devices(device_identity):
    try:
        base_path = get_base_path()
        url = f"{base_path}/add_waiting_device.php"
        response = requests.post(url, json={
            'identity': device_identity
        }, timeout=5)
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
        try:
            response = requests.post(
                url,
                json={'identity': identity},
                timeout=5
            )
        except Exception as e:
            print(f"\n⚠️ POST request failed: {e}. Retrying...")
            # Fallback: try with data parameter instead of json
            response = requests.post(
                url,
                data=json.dumps({'identity': identity}),
                headers={'Content-Type': 'application/json'},
                timeout=5
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
                        json={'identity': identity},
                        timeout=5
                    )
                    if response.status_code == 200:
                        data = response.json()
                        if data.get('success'):
                            print("\n✅ Added to waiting devices list")
                            return False, None
                    return False, None
            print(f"\n❌ Server error: {data.get('message', 'Unknown error')}")
        else:
            print(f"\n❌ Server error: HTTP {response.status_code}")
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
        response = requests.post(url, json={'identity': device_identity}, headers={'Content-Type': 'application/json'}, timeout=5)
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                print(f"\n✅ Removed {device_identity} from waiting devices list")
                return True
        return False
    except Exception as e:
        print(f"\n❌ Error removing from waiting devices: {e}")
        return False

def process_bin_fullness(data, device_identity):
    # Format is "bin_fullness:BinName:Distance"
    try:
        parts = data.split(':')
        if len(parts) == 3 and parts[0] == 'bin_fullness':
            bin_name = parts[1]
            distance = int(parts[2])
            
            # Send data to database using form data
            try:
                base_path = get_base_path()
                response = requests.post(
                    f"{base_path}/update_bin_fullness.php",
                    data={
                        'device_identity': device_identity,
                        'bin_name': bin_name,
                        'distance': distance
                    },
                    timeout=5
                )
                
                if response.status_code == 200 and "Record inserted" in response.text:
                    # Success - show bin fullness and database status
                    print(f"\r✅ Bin Fullness - {bin_name}: {distance}cm (Saved to DB)\n", end="", flush=True)
                else:
                    # Error - show what went wrong
                    print(f"\r❌ Bin Fullness - {bin_name}: {distance}cm (DB Error: {response.text})\n", end="", flush=True)
            except Exception as e:
                print(f"\r❌ Bin Fullness - {bin_name}: {distance}cm (Error: {e})\n", end="", flush=True)
                
    except Exception as e:
        print(f"\nError processing bin fullness data: {e}")

def main():
    config = load_config()
    # Get fixed server URL
    base_path = get_base_path()
    print(f"\nUsing GoSort server at: {base_path}")

    # Then get or set identity
    config = load_config()
    if config.get('sorter_id') is None:
        print("\nFirst time setup - Sorter Identity Configuration")
        sorter_id = input("Enter Sorter Identity (e.g., Sorter1): ")
        config['sorter_id'] = sorter_id
        save_config(config)
    
    # Define default mapping for servo control
    default_mapping = {
        'zdeg': 'bio',       # Front-left position
        'ndeg': 'nbio',      # Front-right position
        'odeg': 'hazardous', # Back-left position
        'mdeg': 'mixed'      # Back-right position
    }
    
    # Fetch mapping from backend
    mapping_url = f"{base_path}/save_sorter_mapping.php?device_identity={config['sorter_id']}"
    try:
        resp = requests.get(mapping_url, timeout=5)
        server_mapping = resp.json().get('mapping', {})
        # Update default mapping with server values, keeping defaults for missing keys
        mapping = default_mapping.copy()
        mapping.update(server_mapping)
    except Exception as e:
        print(f"Warning: Could not fetch mapping, using default. {e}")
        mapping = default_mapping
    # For menu display: get the order and labels
    # Create menu order - only include items that exist in mapping
    menu_order = []
    if 'zdeg' in mapping:
        menu_order.append(('zdeg', mapping['zdeg']))
    if 'ndeg' in mapping:
        menu_order.append(('ndeg', mapping['ndeg']))
    if 'odeg' in mapping:
        menu_order.append(('odeg', mapping['odeg']))
    if 'mdeg' in mapping:
        menu_order.append(('mdeg', mapping['mdeg']))
        
    trash_labels = {
        'bio': 'Biodegradable',
        'nbio': 'Non-Biodegradable',
        'hazardous': 'Hazardous',
        'mixed': 'Mixed Waste'
    }

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
        registered, status = request_registration(config['sorter_id'])
        
        if registered:
            print("\n✅ Device registration confirmed!")
            break
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
            elif key == 'c':
                print("\n⚙️ Clearing Configuration...")
                # Remove from waiting devices before clearing config
                remove_from_waiting_devices(config['sorter_id'])
                # Clear identity
                config['sorter_id'] = None
                save_config(config)
                print("\n✅ Configuration cleared. Please restart the application.")
                return
            elif key == 'q':
                print("\n❌ Registration cancelled. Exiting...")
                # Remove from waiting devices before exiting
                remove_from_waiting_devices(config['sorter_id'])
                return
            else:
                print("\nChecking registration status...", end="", flush=True)
        
        time.sleep(2)  # Check every 2 seconds
        if not first_request:
            print(".", end="", flush=True)

    print("\n✅ Started in Simulation Mode (No Arduino required)")
    
    # Track Arduino connection status (Simulated as always true)
    arduino_connected = True
    
    def check_arduino_connection():
        """Check if simulated Arduino is still connected"""
        return arduino_connected

    def print_menu():
        print("\nTrash Selection Menu:")
        for idx, (deg, ttype) in enumerate(menu_order, 1):
            label = trash_labels.get(ttype, ttype)
            print(f"{idx}. {label}")
        print("4. Mixed")
        print("\nSensor Simulation (Triggers Bin Fullness at 10cm):")
        print("5. Simulate Biodegradable Bin Fullness")
        print("6. Simulate Non-Biodegradable Bin Fullness")
        print("7. Simulate Hazardous Bin Fullness")
        print("8. Simulate Mixed Bin Fullness")
        print("\nConfiguration:")
        print("r. Reconfigure IP")
        print("i. Reconfigure Identity")
        print("c. Clear All Configuration")
        print("q. Quit")

    print_menu()
    last_maintenance_status = False
    check_interval = 1  # Check maintenance mode every second

    last_heartbeat = 0
    heartbeat_interval = 10  # Send heartbeat every 10 seconds

    # A helper queue for artificial responses
    simulated_responses = []

    while True:
        # Send heartbeat periodically to keep device online
        current_time = time.time()
        if current_time - last_heartbeat >= heartbeat_interval:
            # Only send heartbeat if simulated Arduino is still connected
            if arduino_connected and check_arduino_connection():
                try:
                    # Process incoming simulated data
                    while simulated_responses:
                        response = simulated_responses.pop(0)
                        if response.startswith('bin_fullness:'):
                            process_bin_fullness(response, config['sorter_id'])
                        else:
                            print(f"\n🟢 Arduino (Simulated): {response}")
                    
                    # Send heartbeat to update last_active
                    requests.post(
                        f"{base_path}/verify_sorter.php",
                        json={'identity': config['sorter_id']},
                        headers={'Content-Type': 'application/json'},
                        timeout=5
                    )
                    last_heartbeat = current_time
                except (requests.exceptions.Timeout, requests.exceptions.ConnectionError):
                    # Silently fail on timeouts/connection errors for heartbeat
                    last_heartbeat = current_time
                except Exception as e:
                    # Only print unexpected errors
                    if not any(err in str(type(e).__name__) for err in ['Timeout', 'ConnectionError']):
                        print(f"\n⚠️ Heartbeat error: {type(e).__name__}")
            else:
                # Arduino disconnected, stop sending heartbeats
                print("\n⚠️ Arduino (Simulated) disconnected - stopping heartbeats")
                # Remove from waiting devices since Arduino is disconnected
                remove_from_waiting_devices(config['sorter_id'])
                break

        # Check maintenance mode periodically
        current_maintenance = check_maintenance_mode(config['sorter_id'])
        
        # If maintenance status changed, notify user
        if current_maintenance != last_maintenance_status:
            if current_maintenance:
                print("\n🔧 Entering maintenance mode - Controls disabled")
                print("Listening for maintenance commands...")
            else:
                print("\n✅ Exiting maintenance mode - Controls enabled")
                # Re-fetch mapping after maintenance mode
                try:
                    resp = requests.get(mapping_url, timeout=5)
                    server_mapping = resp.json().get('mapping', {})
                    # ensure defaults and merge
                    mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous', 'mdeg': 'mixed'}
                    mapping.update(server_mapping)
                except Exception as e:
                    print(f"Warning: Could not fetch mapping, using default. {e}")
                    mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous', 'mdeg': 'mixed'}
                # Rebuild menu_order including mdeg
                menu_order = []
                for key in ['zdeg', 'ndeg', 'odeg', 'mdeg']:
                    if key in mapping:
                        menu_order.append((key, mapping[key]))
                print_menu()
            last_maintenance_status = current_maintenance

        # If in maintenance mode, check for and execute maintenance commands
        if current_maintenance:
            # Check Arduino connection before processing maintenance commands
            if not arduino_connected or not check_arduino_connection():
                print("\n⚠️ Arduino (Simulated) disconnected - cannot execute maintenance commands")
                time.sleep(check_interval)
                continue
                
            try:
                response = requests.post(
                    f"{base_path}/gs_DB/check_maintenance_commands.php",
                    json={'device_identity': config['sorter_id']},
                    headers={'Content-Type': 'application/json'},
                    timeout=5
                )
                if response.status_code == 200:
                    data = response.json()
                    if data.get('success') and data.get('command'):
                        command = data['command']
                        print(f"\n📡 Received maintenance command from server: {command}")
                        print(f"Current mapping: {mapping}")
                        
                        # Simulating Arduino operations
                        if command in ['unclog', 'sweep1', 'sweep2']:
                            print("Sending maintmode command to enable maintenance mode (Simulated)...")
                            time.sleep(0.5)
                            print(f"🟢 Arduino (Simulated) Response: maintmode ok")
                        
                        print(f"Sending to Arduino (Simulated): {command}")
                        
                        # Wait longer for maintenance commands that take more time
                        if command == 'unclog':
                            time.sleep(6)
                        elif command in ['sweep1', 'sweep2']:
                            time.sleep(5)
                        else:
                            time.sleep(0.1)
                            
                        print(f"🟢 Arduino (Simulated) Response: {command} complete")
                        
                        if command in ['unclog', 'sweep1', 'sweep2']:
                            print("Sending maintend command to exit maintenance mode (Simulated)...")
                            time.sleep(0.5)
                            print(f"🟢 Arduino (Simulated) Response: maintend ok")
                        
                        # Record the sorting operation if it's a sorting command
                        if command in ['ndeg', 'zdeg', 'odeg', 'mdeg']:
                            # Find the trash type for this servo command using mapping
                            trash_type = mapping.get(command)
                            if trash_type:
                                try:
                                    requests.post(
                                        f"{base_path}/gs_DB/record_sorting.php",
                                        json={
                                            'device_identity': config['sorter_id'],
                                            'trash_type': trash_type,
                                            'is_maintenance': True
                                        },
                                        timeout=5
                                    )
                                except Exception as e:
                                    print(f"\n⚠️ Error recording sorting: {e}")
                        
                        # Mark command as executed
                        requests.post(
                            f"{base_path}/gs_DB/mark_command_executed.php",
                            json={'device_identity': config['sorter_id'], 'command': command},
                            timeout=5
                        )
                        if command == 'shutdown':
                            print("\n⚠️ Shutdown command received. Shutting down computer...")
                            # Mark command as executed before shutdown
                            try:
                                requests.post(
                                    f"{base_path}/gs_DB/mark_command_executed.php",
                                    json={'device_identity': config['sorter_id'], 'command': command},
                                    timeout=5
                                )
                            except Exception as e:
                                print(f"\n⚠️ Error marking shutdown command as executed: {e}")
                            print("[Simulation] Skipping actual PC shutdown to avoid terminating the session.")
            except Exception as e:
                print(f"\n❌ Error checking maintenance commands: {e}")
            
            time.sleep(check_interval)
            continue

        # Process while not in maintenance mode, or manual local inputs
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
            elif choice in ['1', '2', '3', '4']:
                idx = int(choice) - 1
                if idx < len(menu_order):
                    degree, trash_type = menu_order[idx]
                    command = f"{degree}\n"
                    if command:
                        try:
                            print(f"\n🔄 Moving servo to sort {trash_labels.get(trash_type, trash_type)} waste (Simulated)...")
                            
                            # Record the sorting action
                            try:
                                sorting_url = f"{base_path}/gs_DB/record_sorting.php"
                                response = requests.post(
                                    sorting_url,
                                    json={
                                        'device_identity': config['sorter_id'],
                                        'trash_type': trash_type,
                                        'is_maintenance': 0
                                    }
                                )
                                if response.status_code == 200:
                                    data = response.json()
                                    if data.get('success'):
                                        print(f"✅ Recorded sorting: {trash_labels.get(trash_type, trash_type)}")
                                    else:
                                        print(f"❌ Failed to record sorting: {data.get('message', 'Unknown error')}")
                            except Exception as e:
                                print(f"❌ Error recording sorting: {e}")
                        except Exception as e:
                            print(f"❌ Error sending command to Arduino (Simulated): {e}")
                else:
                    print("\n❌ Invalid selection")
                    
                time.sleep(0.1)
                print(f"🟢 Arduino (Simulated) Response: ok")
                
            elif choice in ['5', '6', '7', '8']:
                # Sensor Simulation triggers
                # '5' -> Bio fullness
                # '6' -> Non-bio fullness
                # '7' -> Haz fullness
                # '8' -> Mixed fullness
                sim_map = {
                    '5': 'bio',
                    '6': 'nbio',
                    '7': 'hazardous',
                    '8': 'mixed'
                }
                bin_type = sim_map[choice]
                
                # To trigger full notification, PHP requires two consecutive
                # readings between 5cm and 10cm. We simulate 9cm and 10cm.
                simulated_responses.append(f"bin_fullness:{bin_type}:9")
                simulated_responses.append(f"bin_fullness:{bin_type}:10")
                print(f"\n📡 Sensor Triggered: Simulating '{bin_type}' bin full at 9cm and 10cm")
                
            elif choice not in ['\r', '\n']:  # Ignore enter key presses
                print("\nInvalid choice. Please choose from the menu options.")
        
        # Add a small delay to prevent the loop from running too fast
        time.sleep(0.1)
    
    print("🔌 Connection closed (Simulated)")

if __name__ == "__main__":
    main()


