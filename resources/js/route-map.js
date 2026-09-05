import mapboxgl from 'mapbox-gl';

const DEFAULT_CENTER = [
    -85.2072,
    12.8654,
];

const DEFAULT_ZOOM = 6;

function createMarkerElement(feature) {
    const element =
        document.createElement('div');

    element.classList.add(
        'route-map-marker'
    );

    const markerType =
        feature.properties.marker_type;

    if (markerType === 'COURIER') {
        element.classList.add(
            'route-map-marker-courier'
        );

        element.textContent = 'R';

        return element;
    }

    if (markerType === 'ORIGIN') {
        element.classList.add(
            'route-map-marker-origin'
        );

        element.textContent = 'O';

        return element;
    }

    element.classList.add(
        'route-map-marker-destination'
    );

    element.textContent =
        feature.properties.delivery_order
        ?? 'D';

    return element;
}

function createPopupContent(feature) {
    const container =
        document.createElement('div');

    container.classList.add(
        'route-map-popup'
    );

    const title =
        document.createElement('strong');

    const markerType =
        feature.properties.marker_type;

    if (markerType === 'COURIER') {
        title.textContent =
            'Ubicación del repartidor';
    } else if (markerType === 'ORIGIN') {
        title.textContent =
            'Origen del envío';
    } else {
        title.textContent = `Entrega #${
            feature.properties.delivery_order
        }`;
    }

    container.appendChild(title);

    if (
        feature.properties.tracking_code
    ) {
        const tracking =
            document.createElement('p');

        tracking.textContent = `Código: ${
            feature.properties.tracking_code
        }`;

        container.appendChild(tracking);
    }

    if (
        feature.properties.delivery_status
    ) {
        const status =
            document.createElement('p');

        const formattedStatus =
            String(
                feature.properties
                    .delivery_status
            ).replaceAll('_', ' ');

        status.textContent =
            `Estado: ${formattedStatus}`;

        container.appendChild(status);
    }

    if (feature.properties.recorded_at) {
        const recordedAt =
            document.createElement('p');

        recordedAt.textContent =
            `Registrada: ${
                new Date(
                    feature.properties.recorded_at
                ).toLocaleString('es-NI')
            }`;

        container.appendChild(
            recordedAt
        );
    }

    return container;
}

function createStopItem(stop) {
    const item =
        document.createElement('li');

    item.classList.add(
        'route-map-stop-item'
    );

    const order =
        document.createElement('span');

    order.classList.add(
        'route-map-stop-order'
    );

    order.textContent =
        stop.delivery_order;

    const information =
        document.createElement('div');

    information.classList.add(
        'route-map-stop-information'
    );

    const title =
        document.createElement('strong');

    title.textContent =
        stop.destination.address_line;

    const tracking =
        document.createElement('span');

    tracking.textContent =
        stop.shipment.tracking_code;

    const status =
        document.createElement('span');

    status.classList.add(
        'route-map-stop-status'
    );

    const deliveryStatus =
        String(
            stop.delivery_status
            ?? 'PENDING'
        );

    status.dataset.status =
        deliveryStatus.toLowerCase();

    status.textContent =
        deliveryStatus.replaceAll(
            '_',
            ' '
        );

    information.append(
        title,
        tracking,
        status
    );

    item.append(
        order,
        information
    );

    return item;
}

function renderStops(stops) {
    const list =
        document.getElementById(
            'route-map-stop-list'
        );

    const counter =
        document.getElementById(
            'route-map-stop-count'
        );

    if (list === null || counter === null) {
        return;
    }

    list.replaceChildren();

    counter.textContent =
        stops.length === 1
            ? '1 parada registrada'
            : `${stops.length} paradas registradas`;

    for (const stop of stops) {
        list.appendChild(
            createStopItem(stop)
        );
    }
}

function waitForMapLoad(map) {
    if (map.loaded()) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        map.once('load', resolve);
    });
}

function createDeliveryGuide(
    map,
    features
) {
    const destinationFeatures =
        features
            .filter(
                (feature) =>
                    feature.properties
                        .marker_type
                    === 'DESTINATION'
            )
            .sort(
                (first, second) =>
                    first.properties
                        .delivery_order
                    - second.properties
                        .delivery_order
            );

    if (
        destinationFeatures.length < 2
    ) {
        return;
    }

    const coordinates =
        destinationFeatures.map(
            (feature) =>
                feature.geometry.coordinates
        );

    map.addSource(
        'delivery-guide',
        {
            type: 'geojson',
            data: {
                type: 'Feature',
                properties: {},
                geometry: {
                    type: 'LineString',
                    coordinates,
                },
            },
        }
    );

    map.addLayer({
        id: 'delivery-guide-line',
        type: 'line',
        source: 'delivery-guide',
        layout: {
            'line-join': 'round',
            'line-cap': 'round',
        },
        paint: {
            'line-color': '#FF5028',
            'line-width': 4,
            'line-opacity': 0.82,
            'line-dasharray': [
                2,
                1.5,
            ],
        },
    });
}

function addMarkers(
    map,
    features
) {
    const bounds =
        new mapboxgl.LngLatBounds();

    for (const feature of features) {
        const coordinates =
            feature.geometry.coordinates;

        const popup =
            new mapboxgl.Popup({
                offset: 25,
            }).setDOMContent(
                createPopupContent(feature)
            );

        new mapboxgl.Marker({
            element:
                createMarkerElement(feature),
        })
            .setLngLat(coordinates)
            .setPopup(popup)
            .addTo(map);

        bounds.extend(coordinates);
    }

    if (! bounds.isEmpty()) {
        map.fitBounds(bounds, {
            padding: 70,
            maxZoom: 15,
            duration: 900,
        });
    }
}

function showMessage(
    message,
    error = false
) {
    const element =
        document.getElementById(
            'route-map-message'
        );

    if (element === null) {
        return;
    }

    element.textContent = message;

    element.classList.toggle(
        'route-map-message-error',
        error
    );
}

async function initializeRouteMap() {
    const application =
        document.getElementById(
            'route-map-application'
        );

    if (application === null) {
        return;
    }

    const token =
        application.dataset.mapboxToken;

    const dataUrl =
        application.dataset.mapDataUrl;

    if (! token) {
        showMessage(
            'No se configuró el token público de Mapbox.',
            true
        );

        return;
    }

    if (! token.startsWith('pk.')) {
        showMessage(
            'El token de Mapbox debe ser público y comenzar con pk.',
            true
        );

        return;
    }

    if (! dataUrl) {
        showMessage(
            'No se configuró la dirección de los datos del mapa.',
            true
        );

        return;
    }

    mapboxgl.accessToken = token;

    let map;

    try {
        map = new mapboxgl.Map({
            container: 'route-map',
            style:
                'mapbox://styles/mapbox/streets-v12',
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
        });

        map.addControl(
            new mapboxgl.NavigationControl(),
            'top-right'
        );

        map.addControl(
            new mapboxgl.FullscreenControl(),
            'top-right'
        );

        const response = await fetch(
            dataUrl,
            {
                headers: {
                    Accept:
                        'application/json',
                    'X-Requested-With':
                        'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }
        );

        if (! response.ok) {
            throw new Error(
                `HTTP ${response.status}`
            );
        }

        const payload =
            await response.json();

        const data = payload.data;

        if (
            data === null
            || typeof data !== 'object'
        ) {
            throw new Error(
                'La respuesta del mapa no contiene datos válidos.'
            );
        }

        const stops =
            Array.isArray(data.stops)
                ? data.stops
                : [];

        const features =
            Array.isArray(
                data.geojson?.features
            )
                ? data.geojson.features
                : [];

        renderStops(stops);

        await waitForMapLoad(map);

        addMarkers(
            map,
            features
        );

        createDeliveryGuide(
            map,
            features
        );

        if (features.length === 0) {
            showMessage(
                'La ruta todavía no tiene coordenadas disponibles.'
            );

            return;
        }

        showMessage(
            'Mapa cargado correctamente.'
        );
    } catch (error) {
        console.error(
            'No fue posible cargar el mapa:',
            error
        );

        showMessage(
            'No fue posible cargar la información de la ruta.',
            true
        );
    }
}

if (
    document.readyState === 'loading'
) {
    document.addEventListener(
        'DOMContentLoaded',
        initializeRouteMap
    );
} else {
    initializeRouteMap();
}