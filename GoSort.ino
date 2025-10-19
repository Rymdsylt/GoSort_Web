#include <Servo.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// Servo pins
Servo rotateServo;  // Servo for rotation (D8)
Servo tiltServo;    // Servo for tilting (D9)

// Ultrasonic sensor pins for bin fullness
const int TRIG_PIN_1 = 2;  // Non-biodegradable bin sensor
const int ECHO_PIN_1 = 3;
const int TRIG_PIN_2 = 4;  // Biodegradable bin sensor
const int ECHO_PIN_2 = 5;
const int TRIG_PIN_3 = 6;  // Hazardous bin sensor
const int ECHO_PIN_3 = 7;
const int TRIG_PIN_4 = 10; // Mixed bin sensor
const int ECHO_PIN_4 = 11;

// Variables for sensor timing
const unsigned long SENSOR_BURST_INTERVAL = 5000;  // Check sensors every 5 seconds
const unsigned long SENSOR_READ_DELAY = 300;       // 0.3 seconds between readings in burst
unsigned long lastSensorBurstTime;   // Time of last sensor burst
unsigned long lastSensorReadTime;    // Time of last individual sensor read
int currentSensor;                   // Current sensor being read in burst
bool sensorsActive = false;          // Flag to control sensor activation
bool burstInProgress = false;        // Flag to track if we're in the middle of a burst

String inputString = "";
bool isSorting = false;
bool maintenanceMode = false;
unsigned long lastMaintenanceScrollTime = 0;
int maintenanceScrollPos = 0;

// LCD 1602 I2C address is commonly 0x27 or 0x3F
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Array of sorting jokes
const char* jokes[] = {
  "Sorting trash? Its   a waste of time!",
  "Why did the can    join recycling?   ",
  "To make new      friends!           1",
  "Im not trashy,    Im recyclable!    ",
  "Refuse to waste!                    ",
  "Keep calm and     sort on!          ",
  "Time to sort      things out!       ",
  "Waste wars: A new hope!             ",
  "Sort properly, or get the BIN Laden!"
};
const int NUM_JOKES = 8;

// Servo positions (must be between 0 and 180)
const int neutralPos = 45;    // D8 neutral position (pan)
const int nbioPos = 22;      // Non-bio position (pan)
const int bioPos = 22;       // Bio position (pan)
const int hazardPos = 67;    // Hazardous position (pan)
const int mixedPos = 67;     // Mixed position (pan)

// Tilt positions
const int tiltNeutralPos = 90;   // D9 neutral position
const int tiltHighPos = 150;     // High tilt position
const int tiltLowPos = 30;       // Low tilt position

void setup() {
  // Initialize servos
  rotateServo.attach(8);
  tiltServo.attach(9);
  
  // Initialize ultrasonic sensor pins
  pinMode(TRIG_PIN_1, OUTPUT);
  pinMode(ECHO_PIN_1, INPUT);
  pinMode(TRIG_PIN_2, OUTPUT);
  pinMode(ECHO_PIN_2, INPUT);
  pinMode(TRIG_PIN_3, OUTPUT);
  pinMode(ECHO_PIN_3, INPUT);
  pinMode(TRIG_PIN_4, OUTPUT);
  pinMode(ECHO_PIN_4, INPUT);
  
  Serial.begin(115200);
  lcd.begin(16, 2);  
  lcd.backlight();

  // Wait for gosort_ready signal
  lcd.setCursor(0, 0);
  lcd.print("GoSort - Booting");
  int dots = 0;
  unsigned long lastJokeTime = 0;
  unsigned long lastDotTime = 0;
  unsigned long lastScrollTime = 0;
  int currentJoke = 0;
  int scrollPos = 0;
  bool isScrolling = false;
  
  while (true) {
    if (Serial.available()) {
      String command = Serial.readString();
      if (command.indexOf("gosort_ready") != -1) {
        break;  // Exit the loop when gosort_ready is received
      }
    }
    
    unsigned long currentTime = millis();
    
    // Update dots every 500ms
    if (currentTime - lastDotTime >= 500) {
      lcd.setCursor(13, 0);
      switch(dots) {
        case 0: lcd.print("   "); break;
        case 1: lcd.print(".  "); break;
        case 2: lcd.print(".. "); break;
        case 3: lcd.print("..."); break;
      }
      dots = (dots + 1) % 4;
      lastDotTime = currentTime;
    }
    
    // Handle joke display and scrolling
    if (!isScrolling && currentTime - lastJokeTime >= 2000) {
      // Start new joke
      const char* currentJokeText = jokes[currentJoke];
      int jokeLength = strlen(currentJokeText);
      
      lcd.setCursor(0, 1);
      if (jokeLength <= 16) {
        // Short joke - display directly
        lcd.print(currentJokeText);
        currentJoke = (currentJoke + 1) % NUM_JOKES;
        lastJokeTime = currentTime;
      } else {
        // Long joke - start scrolling
        isScrolling = true;
        scrollPos = 0;
        lastScrollTime = currentTime;
      }
    }
    
    // Handle scrolling for long jokes
    if (isScrolling && currentTime - lastScrollTime >= 500) {
      const char* currentJokeText = jokes[currentJoke];
      int jokeLength = strlen(currentJokeText);
      
      lcd.setCursor(0, 1);
      if (scrollPos + 16 >= jokeLength) {
        // Reached end of joke
        isScrolling = false;
        currentJoke = (currentJoke + 1) % NUM_JOKES;
        lastJokeTime = currentTime;
      } else {
        // Continue scrolling
        char buffer[17];
        strncpy(buffer, &currentJokeText[scrollPos], 16);
        buffer[16] = '\0';
        lcd.print(buffer);
        scrollPos++;
        lastScrollTime = currentTime;
      }
    }
  }
  
  lcd.clear();
  Serial.println("Starting initialization...");
  lcd.setCursor(0, 0);
  lcd.print("Initializing...");

  // Sweep test with LCD output
  rotateServo.write(nbioPos);
  lcd.setCursor(0, 1);
  lcd.print("Non-Biodegradable");
  delay(1000);

  rotateServo.write(neutralPos);
  lcd.setCursor(0, 1);
  lcd.print("Neutral           ");
  delay(1000);

  rotateServo.write(bioPos);
  lcd.setCursor(0, 1);
  lcd.print("Biodegradable     ");
  delay(1000);

  rotateServo.write(hazardPos);
  lcd.setCursor(0, 1);
  lcd.print("Hazardous        ");
  delay(1000);

  rotateServo.write(neutralPos);
  lcd.clear();

  lcd.setCursor(0, 0);
  lcd.print("Ready           ");

  // Scroll "Go Sort by BeaBunda and Members"
  String message = "Go Sort by BeaBunda and Members   ";
  for (int i = 0; i < message.length() - 15; i++) {
    lcd.setCursor(0, 1);
    lcd.print(message.substring(i, i + 16));
    delay(300);
  }

  lcd.setCursor(0, 1);
  lcd.print("Awaiting Trash ");

  Serial.println("Initialization complete - Ready for sorting!");
  
  // Initialize sensor variables after booting
  lastSensorBurstTime = millis();
  lastSensorReadTime = millis();
  currentSensor = 1;
  sensorsActive = true;  // Activate sensors
  burstInProgress = false;
}




// Function to measure distance from a specific sensor
long measureDistance(int trigPin, int echoPin) {
  // Clear the trigger pin
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  
  // Send 10µs pulse
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);
  
  // Measure the response
  long duration = pulseIn(echoPin, HIGH);
  
  // Calculate distance in centimeters
  return duration * 0.034 / 2;
}

void loop() {
  unsigned long currentTime = millis();
  
  // Check if it's time to start a new burst of sensor readings
  if (sensorsActive && !burstInProgress && currentTime - lastSensorBurstTime >= SENSOR_BURST_INTERVAL) {
    burstInProgress = true;
    currentSensor = 1;
    lastSensorReadTime = currentTime;
    lastSensorBurstTime = currentTime;
  }
  
  // If we're in a burst and it's time for the next sensor reading
  if (sensorsActive && burstInProgress && currentTime - lastSensorReadTime >= SENSOR_READ_DELAY) {
    int distance = 0;
    String binName = "";
    
    // Select which sensor to read based on the current rotation
    switch(currentSensor) {
      case 1:
        distance = measureDistance(TRIG_PIN_1, ECHO_PIN_1);
        binName = "Non-Bio";
        break;
      case 2:
        distance = measureDistance(TRIG_PIN_2, ECHO_PIN_2);
        binName = "Bio";
        break;
      case 3:
        distance = measureDistance(TRIG_PIN_3, ECHO_PIN_3);
        binName = "Hazardous";
        break;
      case 4:
        distance = measureDistance(TRIG_PIN_4, ECHO_PIN_4);
        binName = "Hazardous";
        break;
    }
    
    // Send bin fullness data over Serial
    Serial.print("bin_fullness:");
    Serial.print(binName);
    Serial.print(":");
    Serial.println(distance);
    
    // Update timing and move to next sensor
    lastSensorReadTime = currentTime;
    currentSensor++;
    
    // If we've read all sensors, end the burst
    if (currentSensor > 4) {
      burstInProgress = false;
      currentSensor = 1;
    }
  }

  // Handle maintenance mode scrolling text
  if (maintenanceMode) {
    if (currentTime - lastMaintenanceScrollTime >= 300) { // Scroll every 300ms
      String maintText = "Maintenance Mode... Standby   ";
      lcd.setCursor(0, 0);
      lcd.print(maintText.substring(maintenanceScrollPos, maintenanceScrollPos + 16));
      maintenanceScrollPos = (maintenanceScrollPos + 1) % (maintText.length() - 15);
      lastMaintenanceScrollTime = currentTime;
    }
  }

  if (Serial.available()) {
    char inChar = (char)Serial.read();
    inputString += inChar;

    if (inChar == '\n' || inChar == '\r') {
      inputString.trim();
      if (!isSorting || maintenanceMode) {  // Process command if not sorting or in maintenance mode

      if (inputString == "ndeg") {
        lcd.setCursor(0, 0);
        lcd.print("Sorting:          ");
        lcd.setCursor(0, 1);
        lcd.print("Non-Biodegradable");

        rotateServo.write(nbioPos);    // Pan to non-bio position (22)
        delay(500);
        tiltServo.write(tiltHighPos);  // Tilt up (150)
        delay(500);
        tiltServo.write(tiltNeutralPos); // Return tilt to neutral
        delay(500);
        rotateServo.write(neutralPos);  // Pan back to neutral (45)
        delay(500);

        Serial.println("Moved to non-biodegradable position");
        Serial.println("ready");
      } 
      else if (inputString == "zdeg") {
        lcd.setCursor(0, 0);
        lcd.print("Sorting:          ");
        lcd.setCursor(0, 1);
        lcd.print("Biodegradable     ");

        rotateServo.write(bioPos);     // Pan to bio position (22)
        delay(500);
        tiltServo.write(tiltLowPos);   // Tilt down (30)
        delay(500);
        tiltServo.write(tiltNeutralPos); // Return tilt to neutral
        delay(500);
        rotateServo.write(neutralPos);  // Pan back to neutral (45)
        delay(500);

        Serial.println("Moved to biodegradable position");
        Serial.println("ready");
      } 
      else if (inputString == "odeg") {
        lcd.setCursor(0, 0);
        lcd.print("Sorting:          ");
        lcd.setCursor(0, 1);
        lcd.print("Hazardous         ");

        rotateServo.write(hazardPos);   // Pan to hazardous position (67)
        delay(500);
        tiltServo.write(tiltHighPos);   // Tilt up (150)
        delay(500);
        tiltServo.write(tiltNeutralPos); // Return tilt to neutral
        delay(500);
        rotateServo.write(neutralPos);   // Pan back to neutral (45)
        delay(500);

        Serial.println("Moved to recyclable position");
        Serial.println("ready");
      }
      else if (inputString == "mdeg") {
        lcd.setCursor(0, 0);
        lcd.print("Sorting:          ");
        lcd.setCursor(0, 1);
        lcd.print("Mixed Waste       ");

        rotateServo.write(mixedPos);    // Pan to mixed position (67)
        delay(500);
        tiltServo.write(tiltLowPos);    // Tilt down (30)
        delay(500);
        tiltServo.write(tiltNeutralPos); // Return tilt to neutral
        delay(500);
        rotateServo.write(neutralPos);   // Pan back to neutral (45)
        delay(500);

        Serial.println("Moved to mixed waste position");
        Serial.println("ready");
      }
      // Maintenance mode commands
      else if (inputString == "maintmode") {
        maintenanceMode = true;
        maintenanceScrollPos = 0;
        lcd.clear();
        Serial.println("Entered maintenance mode");
      }
      else if (inputString == "maintend") {
        maintenanceMode = false;
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Ready             ");
        lcd.setCursor(0, 1);
        lcd.print("Awaiting command  ");
        Serial.println("Exited maintenance mode");
      }
      // Maintenance unclog command
      else if (maintenanceMode && inputString == "unclog") {
          lcd.setCursor(0, 1);
          lcd.print("Unclogging...    ");
          
          // Get D8's current position before unclogging
          int currentD8Position = rotateServo.read();  // Read current angle
          
          // Keep D8 at its current stuck position by not sending any new commands to it
          // Only operate D9 (tilt) for unclogging
          tiltServo.write(150);    // Tilt to maximum unclog position
          delay(3000);             // Hold for 3 seconds
          tiltServo.write(tiltNeutralPos); // Return tilt to neutral
          
          // No movement command sent to D8, so it stays where it was stuck
          
          lcd.setCursor(0, 1);
          lcd.print("Unclog at: ");
          lcd.print(currentD8Position);   // Show the position where unclog happened
          delay(2000);             // Show position for 2 seconds
          
          Serial.print("Unclog complete at position: ");
          Serial.println(currentD8Position);
          Serial.println("ready");
      }
      // Test sweep D8 only
      else if (maintenanceMode && inputString == "sweep1") {
          lcd.setCursor(0, 0);
          lcd.print("Test Sweep D8    ");
          lcd.setCursor(0, 1);
          lcd.print("Testing...       ");

          // Start from neutral position
          rotateServo.write(neutralPos); // Start at neutral (45°)
          delay(1000);
          
          // Test left side positions
          rotateServo.write(nbioPos);    // Go to non-bio/bio (22°)
          delay(1000);
          
          // Return to neutral
          rotateServo.write(neutralPos); // Back to neutral (45°)
          delay(1000);
          
          // Test right side positions
          rotateServo.write(hazardPos);  // Go to hazardous/mixed (67°)
          delay(1000);
          
          // End at neutral
          rotateServo.write(neutralPos); // Return to neutral (45°)
          
          Serial.println("D8 sweep test complete");
          Serial.println("ready");
      }
      // Test sweep both servos
      else if (maintenanceMode && inputString == "sweep2") {
          lcd.setCursor(0, 0);
          lcd.print("Full Sweep Test  ");
          lcd.setCursor(0, 1);
          lcd.print("Testing...       ");

          // Start both servos at neutral
          rotateServo.write(neutralPos);    // Pan to neutral (45°)
          tiltServo.write(tiltNeutralPos);  // Tilt to neutral
          delay(1000);
          
          // Test left side with both tilt positions
          rotateServo.write(nbioPos);       // Pan to left (22°)
          delay(1000);
          tiltServo.write(tiltHighPos);     // High tilt for non-bio (150°)
          delay(1000);
          tiltServo.write(tiltLowPos);      // Low tilt for bio (30°)
          delay(1000);
          
          // Return to neutral
          rotateServo.write(neutralPos);    // Pan to neutral (45°)
          tiltServo.write(tiltNeutralPos);  // Tilt to neutral
          delay(1000);
          
          // Test right side with both tilt positions
          rotateServo.write(hazardPos);     // Pan to right (67°)
          delay(1000);
          tiltServo.write(tiltHighPos);     // High tilt for hazardous (150°)
          delay(1000);
          tiltServo.write(tiltLowPos);      // Low tilt for mixed (30°)
          delay(1000);
          
          // Return to neutral
          rotateServo.write(neutralPos);    // Pan to neutral (45°)
          tiltServo.write(tiltNeutralPos);  // Tilt to neutral
          
          Serial.println("Full sweep test complete");
          Serial.println("ready");
      }

        // Only reset if the command is complete
        if (!maintenanceMode) {
          lcd.setCursor(0, 0);
          lcd.print("Ready             ");
          lcd.setCursor(0, 1);
          lcd.print("Awaiting command  ");
        }
        inputString = "";
        isSorting = false;
      }  // Close the if(!isSorting || maintenanceMode) block
    }
  }
}
