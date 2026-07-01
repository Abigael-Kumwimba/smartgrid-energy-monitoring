#include <WiFi.h>
#include <HTTPClient.h>
#include <LiquidCrystal.h>
#include <PZEM004Tv30.h>
#include <HardwareSerial.h>
#include <math.h>

// ========================
// CONFIG WIFI / API
// ========================
const char* API_URL = "http://192.168.43.29/smartgrid_dashboard/smartgrid_app/api/save_consommation.php";
const char* WIFI_SSID = "Galaxy note";
const char* WIFI_PASSWORD = "elroy123456";
const char* DEVICE_ID = "SG001";

// ========================
// LCD PARALLEL 16x2
// RS, E, D4, D5, D6, D7
// ========================
LiquidCrystal lcd(23, 22, 21, 19, 18, 5);

// ========================
// PZEM via UART2
// RX = GPIO16
// TX = GPIO17
// ========================
HardwareSerial pzemSerial(2);
PZEM004Tv30 pzem(pzemSerial, 17, 16);

// ========================
// TIMERS
// ========================
const unsigned long READ_INTERVAL_MS = 2000;
const unsigned long LCD_PAGE_INTERVAL_MS = 3000;
const unsigned long API_SEND_INTERVAL_MS = 15000;
const unsigned long WIFI_RETRY_INTERVAL_MS = 10000;

unsigned long lastReadAt = 0;
unsigned long lastPageAt = 0;
unsigned long lastApiSendAt = 0;
unsigned long lastWifiRetryAt = 0;

// ========================
// LCD PAGE INDEX
// ========================
int currentPage = 0;

// ========================
// MEASUREMENTS
// ========================
float voltage = NAN;
float current = 0.0;
float power = 0.0;
float energy = 0.0;
float frequency = 0.0;
float powerFactor = 0.0;

// ========================
// WIFI
// ========================
void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    return;
  }

  WiFi.mode(WIFI_STA);
  WiFi.disconnect(true);
  delay(1000);

  Serial.print("[WiFi] Connecting to ");
  Serial.println(WIFI_SSID);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int retries = 0;
  while (WiFi.status() != WL_CONNECTED && retries < 30) {
    delay(500);
    Serial.print(".");
    retries++;
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[WiFi] Connected. IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.print("[WiFi] Failed. Status: ");
    Serial.println(WiFi.status());
  }
}

// ========================
// PZEM READ
// ========================
void readPzem() {
  voltage = pzem.voltage();
  current = pzem.current();
  power = pzem.power();
  energy = pzem.energy();
  frequency = pzem.frequency();
  powerFactor = pzem.pf();

  if (isnan(current)) current = 0.0;
  if (isnan(power)) power = 0.0;
  if (isnan(energy)) energy = 0.0;
  if (isnan(frequency)) frequency = 0.0;
  if (isnan(powerFactor)) powerFactor = 0.0;
}

// ========================
// SERIAL OUTPUT
// ========================
void printMeasurements() {
  Serial.println("------ MESURES ------");

  Serial.print("Voltage: ");
  Serial.print(voltage);
  Serial.println(" V");

  Serial.print("Current: ");
  Serial.print(current);
  Serial.println(" A");

  Serial.print("Power: ");
  Serial.print(power);
  Serial.println(" W");

  Serial.print("Energy: ");
  Serial.print(energy);
  Serial.println(" kWh");

  Serial.print("Frequency: ");
  Serial.print(frequency);
  Serial.println(" Hz");

  Serial.print("Power Factor: ");
  Serial.println(powerFactor);

  Serial.println("---------------------");
}

// ========================
// LCD DISPLAY
// ========================
void drawLcdPage() {
  lcd.clear();

  if (isnan(voltage)) {
    lcd.setCursor(0, 0);
    lcd.print("PZEM ERROR");
    lcd.setCursor(0, 1);
    lcd.print("Check AC/CT");
    return;
  }

  switch (currentPage) {
    case 0:
      lcd.setCursor(0, 0);
      lcd.print("V:");
      lcd.print(voltage, 1);
      lcd.print("V");

      lcd.setCursor(0, 1);
      lcd.print("I:");
      lcd.print(current, 2);
      lcd.print("A");
      break;

    case 1:
      lcd.setCursor(0, 0);
      lcd.print("P:");
      lcd.print(power, 0);
      lcd.print("W");

      lcd.setCursor(0, 1);
      lcd.print("E:");
      lcd.print(energy, 2);
      lcd.print("kWh");
      break;

    case 2:
      lcd.setCursor(0, 0);
      lcd.print("F:");
      lcd.print(frequency, 1);
      lcd.print("Hz");

      lcd.setCursor(0, 1);
      lcd.print("PF:");
      lcd.print(powerFactor, 2);

      if (WiFi.status() == WL_CONNECTED) {
        lcd.setCursor(11, 1);
        lcd.print("WiFi");
      }
      break;
  }
}

// ========================
// API SEND
// ========================
bool sendToApi() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[API] WiFi not connected");
    return false;
  }

  if (isnan(voltage)) {
    Serial.println("[API] Invalid voltage, skip send");
    return false;
  }

  WiFiClient client;
  HTTPClient http;

  http.setTimeout(5000);  // 5 secondes max

  Serial.print("[API] URL: ");
  Serial.println(API_URL);

  if (!http.begin(client, API_URL)) {
    Serial.println("[API] http.begin failed");
    return false;
  }

  http.addHeader("Content-Type", "application/json");

  String payload = "{";
  payload += "\"numero_compteur\":\"" + String(DEVICE_ID) + "\",";
  payload += "\"tension\":" + String(voltage, 2) + ",";
  payload += "\"courant\":" + String(current, 3) + ",";
  payload += "\"puissance\":" + String(power, 2) + ",";
  payload += "\"energie\":" + String(energy, 3) + ",";
  payload += "\"source\":\"esp32_pzem\"";
  payload += "}";

  Serial.println("[API] Sending payload:");
  Serial.println(payload);

  int statusCode = http.POST(payload);

  if (statusCode > 0) {
    Serial.print("[API] Status: ");
    Serial.println(statusCode);

    String response = http.getString();
    Serial.print("[API] Response: ");
    Serial.println(response);
  } else {
    Serial.print("[API] POST failed, error: ");
    Serial.println(http.errorToString(statusCode));
  }

  http.end();
  return (statusCode >= 200 && statusCode < 300);
}

void printWiFiStatus() {
  Serial.print("[WiFi] Status: ");
  Serial.println(WiFi.status());

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[WiFi] IP: ");
    Serial.println(WiFi.localIP());
  }
}

// ========================
// SETUP
// ========================
void setup() {
  Serial.begin(115200);

  pzemSerial.begin(9600, SERIAL_8N1, 16, 17);

  lcd.begin(16, 2);
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Smart Meter");
  lcd.setCursor(0, 1);
  lcd.print("Init...");
  delay(1500);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("[WiFi] Connecting");
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("WiFi connect...");
  
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry < 20) {
    delay(500);
    Serial.print(".");
    retry++;
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[WiFi] Connected. IP: ");
    Serial.println(WiFi.localIP());

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi connected");
    lcd.setCursor(0, 1);
    lcd.print(WiFi.localIP());
    delay(2000);
  } else {
    Serial.println("[WiFi] Connection failed");

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi failed");
    lcd.setCursor(0, 1);
    lcd.print("Check SSID/PASS");
    delay(2000);
  }

  Serial.println("System started");

  readPzem();
  printMeasurements();
  drawLcdPage();
}

// ========================
// LOOP
// ========================
void loop() {
  unsigned long now = millis();

  // Reconnexion WiFi si besoin
  if (WiFi.status() != WL_CONNECTED && now - lastWifiRetryAt >= WIFI_RETRY_INTERVAL_MS) {
    lastWifiRetryAt = now;
    connectWiFi();
    printWiFiStatus();
  }

  // Lecture PZEM
  if (now - lastReadAt >= READ_INTERVAL_MS) {
    lastReadAt = now;
    readPzem();
    printMeasurements();
    drawLcdPage();
  }

  // Rotation des pages LCD
  if (now - lastPageAt >= LCD_PAGE_INTERVAL_MS) {
    lastPageAt = now;
    currentPage = (currentPage + 1) % 3;
    drawLcdPage();
  }

  // Envoi API
  if (now - lastApiSendAt >= API_SEND_INTERVAL_MS) {
    lastApiSendAt = now;
    sendToApi();
  }
}
