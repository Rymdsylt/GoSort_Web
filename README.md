GoSort

Just open "run.bat" for testing. Everything will be explained in the batch file.

FOR DEVELOPERS:

(ARDUINO) - Pan servo is in D8, tilt servo is in d9.
          - Use i2c module for LCD 1602.

(PYTHON <-> ARDUINO) - Data are being communicated through serialization and mega2560 will do the instruction based on the serial data received.
                     - The python application will also receive serial data for debug.

(PYTHON <-> XAMPP SERVER) - Once trash is sorted, data will be sent to the database for admin and analytics.
                   

