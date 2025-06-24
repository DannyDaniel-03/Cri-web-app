// A global variable to hold the map instance.
let map;
console.log("Loaded **NEWEST** map.js!");

// This is the big one. It starts the map, puts you on it, and loads all the alert data.
// It's a core part of the system and even handles calculating the route to the nearest shelter.
function initMap() {
    // Create the map and center it on the user.
    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: USER_LAT, lng: USER_LON },
        zoom: 8
    });

    // A blue square to indicate where you set your place.
    const squareSymbol = {
        path: 'M -10 -10 H 10 V 10 H -10 Z',
        fillColor: '#4285F4',
        fillOpacity: 1.0,
        strokeWeight: 1,
        strokeColor: '#FFFFFF',
        scale: 1.2
    };

    // Put the user's blue square on the map.
    new google.maps.Marker({
        position: { lat: USER_LAT, lng: USER_LON },
        map: map,
        icon: squareSymbol,
        title: 'Your Location'
    });


    // Now, let's load up all the different data layers.
    loadEarthquakes();
    loadFloodData();
    loadWeatherForecasts(USER_LAT, USER_LON);
    loadWeatherAlerts(USER_LAT, USER_LON);
    loadAdminAlerts();
    loadAlerts();

    // Also, pull in the news feed and keep it fresh.
    renderAlertNews();
    setInterval(renderAlertNews, 10000);
}

// A simple helper to drop a standard pin on the map.
function addMarker(lat, lng, iconUrl, title) {
    new google.maps.Marker({
        position: { lat, lng },
        map,
        title: title || "",
        icon: iconUrl && {
            url: iconUrl,
            scaledSize: new google.maps.Size(32, 32)
        }
    });
}

// Lets us use an emoji for a map marker instead of an image.
function addEmojiMarker(lat, lng, emoji, title) {
    new google.maps.Marker({
        position: { lat, lng },
        map,
        title: title || "",
        label: { text: emoji, fontSize: "24px" }
    });
}


// Some fancy math (Haversine formula) to get the distance between two points in km.
// This is super useful for figuring out if an alert is actually close to you.
function getDistanceKm(lat1, lon1, lat2, lon2) {
    const toRad = x => x * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return 6371 * c; // Earth's radius in kilometers
}


// Grabs the latest earthquake data and puts it on the map.
function loadEarthquakes() {
    fetch("api/earthquake.php")
        .then(res => { if (!res.ok) throw new Error(`EQ API ${res.status}`); return res.json(); })
        .then(data => {
            // We only care about quakes in the last 24 hours.
            const since = Date.now() - 24 * 60 * 60 * 1000;
            (data.features || []).filter(f => f.properties.time >= since)
                .forEach(f => {
                    const [lng, lat] = f.geometry.coordinates;
                    const mag = f.properties.mag.toFixed(1);
                    addMarker(lat, lng, null, `M${mag} – ${f.properties.place}`);
                });
        })
        .catch(err => console.error("loadEarthquakes error:", err));
}
// This guy goes and gets the latest flood warnings.
function loadFloodData() {
    fetch("api/floods.php")
        .then(res => {
            if (!res.ok) {
                throw new Error(`Flood API returned ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    const lat = item.floodArea.lat;
                    const lng = item.floodArea.long;
                    const title = item.description;
                    addEmojiMarker(lat, lng, "💧", title);
                });
            }
        })
        .catch(err => console.error("loadFloodData error:", err));
}

// Gets the weather forecast for the user's area.
function loadWeatherForecasts(lat, lon) {
    fetch(`api/weather.php?lat=${lat}&lon=${lon}`)
        .then(res => { if (!res.ok) throw new Error(`Weather API ${res.status}`); return res.json(); })
        .then(data => {
            const feature = data.type === "FeatureCollection"
                ? (data.features[0] || {})
                : data;
            if (!feature.geometry) return;

            // The weather area can be a single point or a big polygon (like a county)
            // We need to find the center of that area to place our icon
            let lat2, lon2;
            if (feature.geometry.type === "Point") {
                [lon2, lat2] = feature.geometry.coordinates;
            } else if (feature.geometry.type === "Polygon") {
                // If it's a polygon, we'll do some quick math to find the average center point
                const ring = feature.geometry.coordinates[0];
                const { lng: sumLon, lat: sumLat } = ring.reduce(
                    (acc, [lng, lt]) => ({ lng: acc.lng + lng, lat: acc.lat + lt }),
                    { lng: 0, lat: 0 }
                );
                lat2 = sumLat / ring.length;
                lon2 = sumLon / ring.length;
            } else {
                return;
            }

            const period = feature.properties?.periods?.[0];
            if (!period) return;
            const iconUrl = period.icon;

            // Add the weather icon to the map.
            addMarker(
                lat2,
                lon2,
                iconUrl || null,
                `${period.shortForecast}, ${period.temperature}°${period.temperatureUnit}`
            );
        })
        .catch(err => console.error("loadWeatherForecasts:", err));
}

// Checks for any nasty weather alerts nearby, like tornado warnings or severe thunderstorms.
function loadWeatherAlerts(lat, lon) {
    fetch(`api/wx_alerts.php?lat=${lat}&lon=${lon}`)
        .then(res => { if (!res.ok) throw new Error(`Alerts API ${res.status}`); return res.json(); })
        .then(data => {
            (data.features || [])
                .filter(f => f && f.geometry && typeof f.geometry.type === 'string')
                .forEach(f => {
                    // we figure out the center of the alert zone, just like with the weather forecast
                    let lat2, lon2;
                    if (f.geometry.type === "Point") {
                        [lon2, lat2] = f.geometry.coordinates;
                    } else if (f.geometry.type === "Polygon") {
                        const ring = f.geometry.coordinates[0];
                        let sumLon = 0, sumLat = 0;
                        ring.forEach(pt => { sumLon += pt[0]; sumLat += pt[1]; });
                        const n = ring.length;
                        lon2 = sumLon / n;
                        lat2 = sumLat / n;
                    } else {
                        return;
                    }

                    // Only show alerts that are actually close to the user (within 100km).
                    if (getDistanceKm(lat, lon, lat2, lon2) <= 100) {
                        addEmojiMarker(lat2, lon2, "❗", f.properties.event);
                    }
                });
        })
        .catch(err => console.error("loadWeatherAlerts error:", err));
}

// Loads custom alerts from our own authority, which might be for things other than weather.
// This also handles the really serious ones that might need you to find a shelter.
function loadAdminAlerts() {
    fetch('api/user_notifications.php')
        .then(res => { if (!res.ok) throw new Error(`User notif API ${res.status}`); return res.json(); })
        .then(data => {
            const alerts = (data.popup || []).concat(data.list || []);
            alerts.forEach(a => {
                addEmojiMarker(a.lat, a.lng, "❗", a.title);

                // Now for the important part: check if there are any designated shelters for this alert.
                fetch(`api/alert_shelters.php?alert_id=${a.alert_id}`)
                    .then(res => { if (!res.ok) throw new Error(`Shelters API ${res.status}`); return res.json(); })
                    .then(shelters => {
                        if (!shelters.length) return;

                        // Find the shelter that's the shortest walk.
                        let closest = shelters[0];
                        let minDist = getDistanceKm(USER_LAT, USER_LON, closest.lat, closest.lng);
                        shelters.forEach(s => {
                            const dist = getDistanceKm(USER_LAT, USER_LON, s.lat, s.lng);
                            if (dist < minDist) {
                                closest = s;
                                minDist = dist;
                            }
                        });

                        // If the alert is recent and bad enough, we'll show a route to safety.
                        const now = Date.now();
                        const createdAt = new Date(a.created_at).getTime();
                        const hoursSince = (now - createdAt) / (1000 * 60 * 60);

                        // If the alert is less than 5 hours old, has a severity of 6 or higher (on a scale we defined),
                        // and we found a closest shelter
                        if (hoursSince <= 5 && a.severity >= 6 && closest) {
                            // Use the Google Directions service to draw a walking path.
                            const ds = new google.maps.DirectionsService();
                            const dr = new google.maps.DirectionsRenderer({ map, suppressMarkers: true });
                            ds.route({
                                origin: { lat: USER_LAT, lng: USER_LON },
                                destination: { lat: closest.lat, lng: closest.lng },
                                travelMode: google.maps.TravelMode.WALKING
                            }, (result, status) => {
                                if (status === 'OK') dr.setDirections(result);
                            });
                        }
                    });
            });
        })
        .catch(err => console.error("loadAdminAlerts error:", err));
}

// The Google Maps script that we load from Google's website looks for a function called 'initMap'
// on the main 'window' object. We have to put our initMap function there so it can be found and called
// once the Google Maps code is fully loaded.
window.initMap = initMap;