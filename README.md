# Crisis Notification App



## Description

A web-based application designed to monitor natural crises—such as earthquakes, floods, and severe weather—and notify registered users in real time. It also provides interactive shelter location mapping and routing to ensure user safety.&#x20;

## Table of Contents

- [Built With](#built-with)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Usage](#usage)
- [Functional Requirements](#functional-requirements)
- [User Interaction](#user-interaction)
- [API Reference](#api-reference)
- [Architecture](#architecture)
- [Contributing](#contributing)
- [License](#license)
- [Acknowledgements](#acknowledgements)

## Built With

- **Backend**: PHP 8.x, PDO
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **APIs & Data Sources**:
  - [USGS Earthquake API](https://earthquake.usgs.gov/)
  - [NOAA National Weather Service API](https://www.weather.gov/)
  - [UK Environment Agency Flood API](https://environment.data.gov.uk/)
  - [Google Maps JavaScript API](https://developers.google.com/maps/documentation/javascript)

## Prerequisites

- PHP 8.0+ with PDO extension
- MySQL or MariaDB server
- Apache (with `mod_rewrite`) or equivalent web server
- Google Maps JavaScript API key

## Installation

This application is set up using **XAMPP** on Windows, macOS, or Linux.

1. **Install XAMPP**:

   - Download and install XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org).
   - Ensure **Apache** and **MySQL** modules are running in the XAMPP Control Panel.

2. **Deploy the application**:

   ```bash
   # Navigate to XAMPP's htdocs directory
   cd <XAMPP_INSTALL_DIR>/htdocs
   # Clone the repository or copy project folder
   git clone https://github.com/yourusername/crisis-notification-app.git
   cd crisis-notification-app
   ```

3. **Database setup**:

   - Open **phpMyAdmin** by visiting `http://localhost/phpmyadmin/`.
   - Import the database (found in sql/schema.sql).

4. **Configuration**:

   - In `config.example.php`, update `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME` (set `DB_HOST` to `localhost`). 
   - Open `config.example.php` and set your Google Maps JavaScript API key.
   - Rename `config.example.php` to `config.php`.
   - In `config.php`, set `NWS_USER_AGE``NT` to your contact email (per NOAA policy).

5. **Permissions** (Linux/macOS only):

   ```bash
   chmod -R 755 storage/
   ```

6. **Access the app**:

   - In a browser, go to `http://localhost/crisis/index.php`.

## Usage

1. Start your web server and point the document root to the project folder.
2. Access the app at `http://localhost/index.php`.
3. Register a new account or log in.
4. View live crisis alerts and shelter locations on the interactive map.
5. Receive in-app notifications as new events occur.
6. Have the ability to generate an API key that you can use to receive all our alerts.  

## Functional Requirements

| ID  | Requirement                                                                                                                     |
| --- | ------------------------------------------------------------------------------------------------------------------------------- |
| FR1 | The system shall fetch and display earthquake data in real time from the USGS API.                                              |
| FR2 | The system shall fetch and display flood data from the UK Environment Agency.                                                   |
| FR3 | The system shall fetch and display severe weather alerts from NOAA NWS.                                                         |
| FR4 | The system shall allow users to register, authenticate.                                                                         |
| FR5 | The system shall display nearby shelters based on user location on an interactive map, if the alert was severe enough.          |
| FR6 | The system shall provide routing directions from the user’s location to a chosen shelter.                                       |
| FR7 | The system shall store and display an audit log of alerts, shelter additions, and user actions, for developers and authorities. |
| FR8 | The system shall allow users to mark notifications as seen.                                                                     |

## User Interaction

1. **Dashboard**: Upon login, users see a dashboard summarizing active crisis alerts.
2. **Map View**: Users can pan/zoom the map to explore shelter locations and crisis events.
3. **Alert Notification**: Provides a sidebar with all relevant alerts, that have been created withing 24 hours and will show details off the alert.
4. **Shelter Routing**: When alert of severity greater or equal to 6 it will provide a route to the nearest shelter, that was selected by the authorities.
5. **News Section**: At the bottom of the dashboard, there is a section called alert news, which provides all news of every alert that are fetched from the API's and the once created by authority.

## API Reference

All API endpoints return JSON and reside in the `api/` directory:

| Endpoint                  | Description                                  |
| ------------------------- | -------------------------------------------- |
| `/api/earthquake.php`     | Recent earthquakes near the user’s location. |
| `/api/floods.php`         | Current flood events in the UK region.       |
| `/api/weather.php`        | Active severe weather alerts.(US only)       |
| `/api/alert_shelters.php` | Shelters associated with a specific alert.   |
|                           |                                              |

Refer to inline comments for small explanations on the general functions and what they do.

## Architecture

The application follows an MVC-inspired structure:

```
crisis/
├── api/               # JSON endpoints (controllers)
├── css/               # Styles (views)
├── includes/          # Helper functions (models)
├── js/                # Interactive scripts
├── sql/               # Database schema
├── index.php          # Dashboard (front controller)
└── config.php         # Global config
```

## Contributing

Contributions are welcome! Please fork the repository and submit a pull request with descriptive commits.&#x20;

## License

This project is licensed under the MIT License.

## Acknowledgements

- [Awesome Readme Template](https://github.com/Louis3797/awesome-readme-template)
- [IEEE SRS Template](https://github.com/rick4470/IEEE-SRS-Tempate)

