# GoSort_Web

> ⚠️ **Note:** This project is currently under construction.

GoSort_Web is a web-based application designed to facilitate sorting and detection tasks, likely for waste or object sorting, using a combination of PHP, Python, and modern web technologies.

## Features
- User authentication and login system (`GoSort_Login.php`)
- Main dashboard and interface (`GoSort_Main.php`)
- Database integration for storing and retrieving data (`gs_DB/`)
- Responsive UI using Bootstrap CSS and JS (`css/`, `js/`)

## Project Structure
```
gosort_config.json         # Configuration file
GoSort_Detect.py           # Python detection script
GoSort_Login.php           # User login page
GoSort_Main.php            # Main dashboard
css/                       # Bootstrap CSS files
js/                        # Bootstrap JS files
gs_DB/                     # Database connection and logic
```

## Setup Instructions
1. **Requirements:**
   - XAMPP or similar local server (Apache, PHP, MySQL)
   - Python 3.x
2. **Clone the repository:**
   ```
   git clone <repo-url>
   ```
3. **Place the project in your XAMPP `htdocs` directory:**
   - Example: `C:/xampp/htdocs/GoSort_Web`
4. **Configure the database:**
   - Edit `gs_DB/connection.php` with your MySQL credentials.
   - Import any provided SQL files to set up the database.
5. **Install Python dependencies:**
   - Navigate to the project directory and install required packages (see `GoSort_Detect.py` for requirements).
6. **Start XAMPP services:**
   - Start Apache and MySQL from the XAMPP control panel.
7. **Access the application:**
   - Open your browser and go to `http://localhost/GoSort_Web/GoSort_Login.php`

## Usage
- Log in with your credentials.
- Use the main dashboard to interact with the detection and sorting features.

## Author
Rymdsylt
