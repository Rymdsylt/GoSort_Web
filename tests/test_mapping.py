import json

# Simulate mapping fetch from server
server_response = {
    'success': True,
    'mapping': {
        'zdeg': 'bio',
        'ndeg': 'nbio',
        'odeg': 'hazardous',
        'mdeg': 'mixed'
    }
}

# Default mapping
default_mapping = {'zdeg': 'bio', 'ndeg': 'nbio', 'odeg': 'hazardous', 'mdeg': 'mixed'}

# Merge logic (like GoSort.py)
mapping = default_mapping.copy()
mapping.update(server_response.get('mapping', {}))

# Build menu order
menu_order = []
for key in ['zdeg', 'ndeg', 'odeg', 'mdeg']:
    if key in mapping:
        menu_order.append((key, mapping[key]))

trash_labels = {'bio': 'Biodegradable', 'nbio': 'Non-Biodegradable', 'hazardous': 'Hazardous', 'mixed': 'Mixed Waste'}

# Render menu
print("Rendered Menu:")
for idx, (deg, ttype) in enumerate(menu_order, 1):
    label = trash_labels.get(ttype, ttype)
    print(f"{idx}. {label} ({deg})")

# Simulate selecting mixed (mdeg)
selected_command = 'mdeg'
print(f"\nSimulate command for mixed: {selected_command} -> {mapping[selected_command]}")
