// This function is like asking Google, "Hey, what's the name of the place at this specific spot on Earth?"
// We use it to turn latitude and longitude coordinates into a human-readable city name.
function getCityFromLatLng(lat, lng, elementId, format = 'sidebar') {
    console.log('Geocode called for', lat, lng, elementId, format);
    const locationElement = document.getElementById(elementId);
    if (!locationElement) {
        return;
    }

    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        locationElement.textContent = "📍 Maps API Error";
        return;
    }

    const geocoder = new google.maps.Geocoder();
    // We bundle up the latitude and longitude into the format Google's API expects.
    const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };

    // Now we ask the geocoder to get the address for our location.
    // It will run our code once it gets a result back.
    geocoder.geocode({ location: latlng }, (results, status) => {
        console.log('Geocode status:', status, results);
        if (status === "OK" && results[0]) {
            const components = results[0].address_components;

            // Try to find the best possible name in order of preference
            const city = components.find(c => c.types.includes("locality"))?.long_name;
            const state = components.find(c => c.types.includes("administrative_area_level_1"))?.long_name;
            const country = components.find(c => c.types.includes("country"))?.long_name;

            // Use the best name we could find
            const bestName = city || state || country || "Unknown Location";

            // Apply the correct format
            if (format === 'news') {
                locationElement.textContent = `📍 ${bestName}`;
            } else { // 'sidebar' format
                locationElement.textContent = `Location: ${bestName}`;
            }

        } else {
            // If geocode fails, update with an error message
            locationElement.textContent = "Location: Not found";
        }
    });
}

// Some fancy math to get the distance between two points in kilometers
function getDistanceKm(lat1, lon1, lat2, lon2) {
    const toRad = x => x * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return 6371 * c; // Earth's radius in kilometers (we already said that)
}

// This function fetches all the alerts and displays them in the sidebar list.
function loadAlerts() {
    // Go ask our server for the list of current notifications.
    fetch('api/notifications.php').then(r => r.json()).then(data => {
        const d = document.getElementById('alert-list');
        const greeting = document.getElementById('greeting');
        d.innerHTML = '';

        // If the server sends back an empty list, it means there are no alerts.
        if (!data.length) {
            d.innerHTML = '<li>No alerts nearby.</li>';
            greeting.textContent = `Hello ${USER_NAME}, you currently have no alerts, stay safe!`;
            return;
        }

        // If there ARE alerts, we update the greeting to say how many there are.
        // It even handles the grammar for "1 alert" vs "2 alerts" (cool right?)
        greeting.textContent = `Hello ${USER_NAME}, you currently have ${data.length} alert${data.length === 1 ? '' : 's'}.`;

        const alertsHtml = data.map(alert => {
            const alertDate = new Date(alert.created_at);
            alert._locationId = `location-${alert.created_at.replace(/[^0-9]/g, '')}`;
            alert._timeDiffHours = (new Date() - alertDate) / (1000 * 60 * 60); // We also calculate how many hours old the alert is and save it for later.

            // This is the HTML template for a single alert item in the list.
            return `
        <li>
            <div class="alert-header">
                <strong>${alert.title}</strong>
                <span class="alert-time">${alertDate.toLocaleString()}</span>
            </div>
            <div class="alert-details">
                <span class="alert-location" id="${alert._locationId}">📍 Fetching location...</span>
                <span class="alert-severity">Severity: ${alert.severity}</span>
                ${alert.radius > 0 ? `<span class="alert-radius">Radius: ${Number(alert.radius).toLocaleString()} m</span>` : ''}
            </div>
            <div class="alert-message">${alert.message}</div>
            <div id="route-info-${alert.created_at.replace(/[^0-9]/g, '')}"></div>
        </li>
    `;
        }).join('');

        // 2. Only now update the DOM.
        d.innerHTML = alertsHtml;

        // 3. Then trigger location lookup and routing for every alert.
        data.forEach(alert => {
            getCityFromLatLng(alert.lat, alert.lng, alert._locationId, 'news');

            if (alert.severity >= 6 && alert._timeDiffHours < 5) {
                findAndDisplayShelterRoute(alert);
            }
        });
    }).catch(() => {
        document.getElementById('alert-list').innerHTML = '<li>Error loading alerts.</li>';
        document.getElementById('greeting').textContent = `Hello ${USER_NAME}, error loading alerts.`;
    });
}

// For serious alerts, this function finds the closest shelter and draws a route to it on the map.
function findAndDisplayShelterRoute(alert) {
    if (!alert.id) {
        console.error("Alert object is missing an ID, cannot fetch shelters.", alert);
        return;
    }

    // First, we fetch ONLY the shelters that have been specifically assigned to this alert.
    fetch(`api/alert_shelters.php?alert_id=${alert.id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Shelters API error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(shelters => {
            // If the server says there are no shelters for this alert, then there's nothing more to do.
            if (!shelters || shelters.length === 0) {
                return;
            }

            let closestShelter = shelters[0];
            // We'll start by assuming the first one is the closest.
            let minDistance = getDistanceKm(USER_LAT, USER_LON, closestShelter.lat, closestShelter.lng);

            // Then we loop through the rest to see if we can find a closer one.
            for (let i = 1; i < shelters.length; i++) {
                const distance = getDistanceKm(USER_LAT, USER_LON, shelters[i].lat, shelters[i].lng);
                if (distance < minDistance) {
                    minDistance = distance;
                    closestShelter = shelters[i];
                }
            }

            const routeContainer = document.getElementById(`route-info-${alert.created_at.replace(/[^0-9]/g, '')}`);
            if (routeContainer) {
                routeContainer.innerHTML = `<div class="route-info"><strong>Evacuation Route:</strong> Path to shelter "${closestShelter.name}" displayed on the map.</div>`;
            }

            // Finally, we use the Google Maps Directions Service to actually draw the route.
            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({
                map: map, // The global 'map' variable from map.js
                suppressMarkers: true
            });

            // The actual request to be sent for us to get the route
            const request = {
                origin: new google.maps.LatLng(USER_LAT, USER_LON),
                destination: new google.maps.LatLng(closestShelter.lat, closestShelter.lng),
                travelMode: 'DRIVING'
            };

            // Ask for the route, and when we get a response, run this function.
            directionsService.route(request, function (result, status) {
                if (status == 'OK') {
                    // Here it actually draws it
                    directionsRenderer.setDirections(result);
                    // Add a marker for the destination shelter
                    new google.maps.Marker({
                        position: new google.maps.LatLng(closestShelter.lat, closestShelter.lng),
                        map: map,
                        icon: {
                            url: 'https://static.thenounproject.com/png/41325-200.png',
                            scaledSize: new google.maps.Size(40, 40),
                            origin: new google.maps.Point(0, 0),
                            anchor: new google.maps.Point(20, 40)
                        },
                        title: closestShelter.name
                    });
                }
            });
        })
        .catch(e => {
            console.error("Failed to fetch or process shelter route:", e);
        });
}
// This makes sure we don't start running our code until the whole webpage has finished loading.
document.addEventListener('DOMContentLoaded', () => {
    setInterval(loadAlerts, 10000);
});