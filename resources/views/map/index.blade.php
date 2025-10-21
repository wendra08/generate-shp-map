<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Multi-Layer Map Viewer</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <style>
        .layer-control {
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .layer-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .layer-item:hover {
            background: #f3f4f6;
        }

        .color-indicator {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">

            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-5xl font-extrabold text-gray-900 mb-3">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">
                        Multi-Layer Map Viewer
                    </span>
                </h1>
                <p class="text-xl text-gray-600">Upload boundary, roads, and rivers to visualize your geospatial data
                </p>
            </div>

            <!-- Input Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Map Configuration</h2>

                <form id="mapForm" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <!-- Coordinates -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="latitude" class="block text-sm font-semibold text-gray-700 mb-2">
                                📍 Latitude
                            </label>
                            <input type="number" step="any" id="latitude" name="latitude"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="e.g., 3.1737" required>
                        </div>

                        <div>
                            <label for="longitude" class="block text-sm font-semibold text-gray-700 mb-2">
                                📍 Longitude
                            </label>
                            <input type="number" step="any" id="longitude" name="longitude"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="e.g., 98.4913" required>
                        </div>
                    </div>

                    <!-- File Uploads -->
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">📁 Upload Geographic Layers</h3>

                        <!-- Boundary File (Required) -->
                        <div class="bg-gray-50 border-2 border-gray-800 rounded-lg p-4">
                            <label for="boundary_file" class="block text-sm font-semibold text-gray-800 mb-2">
                                ⚫ Boundary Layer <span class="text-red-600">*</span> (Required)
                            </label>
                            <input type="file" id="boundary_file" name="boundary_file" accept=".geojson,.json"
                                class="w-full px-3 py-2 border border-gray-800 rounded-md text-sm focus:ring-2 focus:ring-gray-800"
                                required>
                            <p class="mt-1 text-xs text-gray-600">
                                Will be displayed with <strong>thick black outline</strong> - defines the clip area
                            </p>
                        </div>

                        <!-- Road File (Optional) -->
                        <div class="bg-orange-50 border-2 border-orange-200 rounded-lg p-4">
                            <label for="road_file" class="block text-sm font-semibold text-gray-800 mb-2">
                                🟠 Road Layer (Optional)
                            </label>
                            <input type="file" id="road_file" name="road_file" accept=".geojson,.json"
                                class="w-full px-3 py-2 border border-orange-300 rounded-md text-sm focus:ring-2 focus:ring-orange-500">
                            <p class="mt-1 text-xs text-gray-600">Roads will be clipped to boundary area</p>
                        </div>

                        <!-- River File (Optional) -->
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
                            <label for="river_file" class="block text-sm font-semibold text-gray-800 mb-2">
                                🔵 River Layer (Optional)
                            </label>
                            <input type="file" id="river_file" name="river_file" accept=".geojson,.json"
                                class="w-full px-3 py-2 border border-blue-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-600">Rivers will be clipped to boundary area</p>
                        </div>

                        <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <p class="text-sm text-blue-800">
                                <strong>💡 Tip:</strong> Convert shapefiles to GeoJSON at
                                <a href="https://mapshaper.org/" target="_blank"
                                    class="underline font-semibold">mapshaper.org</a>
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="submit" id="loadMapBtn"
                            class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition duration-300 transform hover:-translate-y-0.5">
                            <span id="loadBtnText">🗺️ Load Map</span>
                        </button>
                        <button type="button" id="saveMapBtn"
                            class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition duration-300 transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                            disabled>
                            💾 Save as Image (with Legend)
                        </button>
                    </div>
                </form>

                <!-- Loading -->
                <div id="loading" class="hidden mt-6">
                    <div class="flex items-center justify-center p-4 bg-blue-50 rounded-lg">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="ml-3 text-blue-700 font-medium">Loading layers...</span>
                    </div>
                </div>

                <!-- Error Message -->
                <div id="errorMessage" class="hidden mt-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                    <p class="text-sm text-red-700 font-medium" id="errorText"></p>
                </div>

                <!-- Success Message -->
                <div id="successMessage" class="hidden mt-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
                    <p class="text-sm text-green-700 font-medium" id="successText"></p>
                </div>
            </div>

            <!-- Map Container with Layer Control -->
            <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800">Map View</h2>
                    <div id="layerControl" class="hidden layer-control">
                        <div class="text-xs font-semibold text-gray-600 mb-2">LAYERS</div>
                        <div id="layerList"></div>
                    </div>
                </div>

                <!-- Map Wrapper: Map + Legend side by side -->
                <div id="mapWrapper" class="flex gap-4">
                    <!-- Map Container (flex-1 = takes remaining space) -->
                    <div id="map" class="shadow-inner flex-1 rounded-lg" style="height: 600px;"></div>

                    <!-- Legend Container (fixed width, hidden by default) -->
                    <div id="mapLegend" class="hidden bg-white border-2 border-gray-800 rounded-lg p-4 shadow-lg"
                        style="width: 280px; height: 600px; overflow-y: auto;">
                        <!-- Header Section -->
                        <div class="border-b-2 border-gray-800 pb-3 mb-4 text-center">
                            <h3 class="font-bold text-sm uppercase tracking-wide">PETA RENCANA KERJA</h3>
                            <p class="text-xs mt-1 font-medium">USAHA PEMANFAATAN HUTAN</p>
                            <div class="mt-2 pt-2 border-t border-gray-400">
                                <p class="text-xs font-bold">PT ITCI KARTIKA UTAMA</p>
                                <p class="text-xs">KAB. KUTAI KARTANEGARA</p>
                            </div>
                        </div>

                        <!-- Legend Section -->
                        <div class="space-y-3">
                            <div class="text-xs font-bold uppercase tracking-wide border-b border-gray-400 pb-2 mb-3">
                                KETERANGAN
                            </div>

                            <!-- Boundary Legend -->
                            <div class="flex items-center gap-3" id="legendBoundary">
                                <div class="w-8 h-8 border-4 border-black bg-red-50 rounded flex-shrink-0"></div>
                                <span class="text-xs font-medium">Batas Wilayah Kerja</span>
                            </div>

                            <!-- Road Legend -->
                            <div class="items-center gap-3 hidden" id="legendRoad">
                                <div class="w-8 h-2 bg-red-600 rounded flex-shrink-0"></div>
                                <span class="text-xs font-medium">Jalan/Akses</span>
                            </div>

                            <!-- River Legend -->
                            <div class="items-center gap-3 hidden" id="legendRiver">
                                <div class="w-8 h-2 bg-blue-500 rounded flex-shrink-0"></div>
                                <span class="text-xs font-medium">Sungai/Aliran Air</span>
                            </div>
                        </div>

                        <!-- Coordinate Info -->
                        <div class="mt-4 pt-4 border-t-2 border-gray-300">
                            <div class="text-xs">
                                <div class="font-bold uppercase tracking-wide mb-2">KOORDINAT PUSAT</div>
                                <div class="bg-gray-50 p-2 rounded border border-gray-300">
                                    <div id="legendLat" class="font-mono">Lat: -</div>
                                    <div id="legendLng" class="font-mono">Lng: -</div>
                                </div>
                            </div>
                        </div>

                        <!-- Scale & Date Info -->
                        <div class="mt-4 pt-4 border-t-2 border-gray-300">
                            <div class="text-xs space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold">Skala:</span>
                                    <span class="font-mono">1:50.000</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold">Proyeksi:</span>
                                    <span>WGS 84</span>
                                </div>
                                <div id="legendDate" class="pt-2 border-t border-gray-300 text-center font-medium">
                                    Tanggal: -
                                </div>
                            </div>
                        </div>

                        <!-- Footer/Watermark -->
                        <div class="mt-4 pt-3 border-t border-gray-400 text-center">
                            <p class="text-xs text-gray-600">Generated by Multi-Layer Map Viewer</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-sm text-gray-500" id="mapInfo">Ready to load data</div>
            </div>

        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-image@0.4.0/leaflet-image.js"></script>

    <script>
        let map = L.map('map').setView([3.1737, 98.4913], 10);
        let layers = {};
        let markerLayer = null;
        let boundaryBounds = null;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const mapForm = document.getElementById('mapForm');
        const loadMapBtn = document.getElementById('loadMapBtn');
        const loadBtnText = document.getElementById('loadBtnText');
        const saveMapBtn = document.getElementById('saveMapBtn');
        const loading = document.getElementById('loading');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const successMessage = document.getElementById('successMessage');
        const successText = document.getElementById('successText');
        const mapInfo = document.getElementById('mapInfo');
        const layerControl = document.getElementById('layerControl');
        const layerList = document.getElementById('layerList');

        function showError(message) {
            errorText.textContent = message;
            errorMessage.classList.remove('hidden');
            successMessage.classList.add('hidden');
        }

        function showSuccess(message) {
            successText.textContent = message;
            successMessage.classList.remove('hidden');
            errorMessage.classList.add('hidden');
        }

        function hideMessages() {
            errorMessage.classList.add('hidden');
            successMessage.classList.add('hidden');
        }

        function createLayerControl(layerName, color, visible) {
            const item = document.createElement('div');
            item.className = 'layer-item';
            item.innerHTML = `
                <input type="checkbox" id="layer-${layerName}" ${visible ? 'checked' : ''}
                       class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                <div class="color-indicator" style="background-color: ${color}"></div>
                <label for="layer-${layerName}" class="text-sm font-medium text-gray-700 cursor-pointer flex-1">
                    ${layerName}
                </label>
            `;

            const checkbox = item.querySelector('input');
            checkbox.addEventListener('change', (e) => {
                if (layers[layerName.toLowerCase()]) {
                    if (e.target.checked) {
                        map.addLayer(layers[layerName.toLowerCase()]);
                    } else {
                        map.removeLayer(layers[layerName.toLowerCase()]);
                    }
                }
            });

            return item;
        }

        mapForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);

            hideMessages();
            loading.classList.remove('hidden');
            loadMapBtn.disabled = true;
            loadBtnText.textContent = '⏳ Loading...';
            saveMapBtn.disabled = true;
            mapInfo.textContent = 'Processing files...';
            layerControl.classList.add('hidden');

            try {
                const response = await fetch('/map/load', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (!data || !data.success) {
                    throw new Error(data.error || 'Failed to load map');
                }

                // Clear existing layers
                Object.values(layers).forEach(layer => map.removeLayer(layer));
                layers = {};
                if (markerLayer) map.removeLayer(markerLayer);
                layerList.innerHTML = '';

                let totalFeatures = 0;

                // Create vector pane
                if (!map.getPane('vectorPane')) {
                    map.createPane('vectorPane');
                    map.getPane('vectorPane').style.zIndex = 650;
                }
                console.log('✓ Custom vector pane created (z-index: 650)');

                const layerOrder = ['boundary', 'road', 'river'];

                layerOrder.forEach(layerType => {
                    if (data.layers[layerType]) {
                        const layerData = data.layers[layerType];
                        const geojson = layerData.geojson;

                        if (!geojson.features || geojson.features.length === 0) {
                            console.warn(`${layerType} has no features`);
                            return;
                        }

                        totalFeatures += geojson.features.length;

                        console.log(`Loading ${layerType}:`, {
                            features: geojson.features.length,
                            color: layerData.color,
                            weight: layerData.weight,
                            firstFeatureType: geojson.features[0]?.geometry?.type
                        });

                        const layer = L.geoJSON(geojson, {
                            pane: 'vectorPane',
                            style: {
                                color: layerData.color,
                                weight: layerData.weight || 2,
                                opacity: layerData.opacity || 0.8,
                                fillColor: layerData.fillColor || layerData.color,
                                fillOpacity: layerType === 'boundary' ? (layerData.fillOpacity || 0) : 0.3,
                                dashArray: layerType === 'boundary' ? '' : ''
                            },
                            onEachFeature: (feature, layer) => {
                                if (feature.properties && Object.keys(feature.properties).length > 0) {
                                    let popup = `<div class="text-sm"><strong>${layerData.name}</strong><br>`;
                                    for (let key in feature.properties) {
                                        if (feature.properties[key]) {
                                            popup += `<strong>${key}:</strong> ${feature.properties[key]}<br>`;
                                        }
                                    }
                                    popup += '</div>';
                                    layer.bindPopup(popup);
                                }
                            }
                        }).addTo(map);

                        layers[layerType] = layer;

                        try {
                            const layerBounds = layer.getBounds();
                            console.log(`${layerType} bounds:`, {
                                north: layerBounds.getNorth().toFixed(4),
                                south: layerBounds.getSouth().toFixed(4),
                                east: layerBounds.getEast().toFixed(4),
                                west: layerBounds.getWest().toFixed(4),
                                center: layerBounds.getCenter()
                            });
                        } catch (e) {
                            console.warn(`Cannot get bounds for ${layerType}`);
                        }

                        console.log(`✓ ${layerType} layer added to map, visible: ${map.hasLayer(layer)}`);

                        if (layerType === 'boundary') {
                            boundaryBounds = layer.getBounds();
                        }

                        layerList.appendChild(createLayerControl(layerData.name, layerData.color, true));
                    } else {
                        console.warn(`${layerType} layer not found in response`);
                    }
                });

                console.log('Adjusting layer z-index...');
                if (layers.boundary) {
                    layers.boundary.bringToFront();
                    console.log('✓ Boundary brought to front');
                }
                if (layers.river) {
                    layers.river.bringToFront();
                    console.log('✓ River brought to front');
                }
                if (layers.road) {
                    layers.road.bringToFront();
                    console.log('✓ Road brought to front');
                }

                // Add center marker
                markerLayer = L.marker([data.center.lat, data.center.lng], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).addTo(map);

                markerLayer.bindPopup(`
                    <div class="text-sm">
                        <strong class="text-red-600">Your Location</strong><br>
                        <strong>Lat:</strong> ${data.center.lat}<br>
                        <strong>Lng:</strong> ${data.center.lng}
                    </div>
                `).openPopup();

                // Fit map to boundary
                if (boundaryBounds && boundaryBounds.isValid()) {
                    map.fitBounds(boundaryBounds, { padding: [50, 50] });
                }

                layerControl.classList.remove('hidden');
                showSuccess(`Map loaded! ${totalFeatures} features across ${Object.keys(layers).length} layers.`);
                mapInfo.textContent = `${totalFeatures} features loaded`;
                saveMapBtn.disabled = false;

                // ===== Show legend dan update info =====
                const mapLegend = document.getElementById('mapLegend');
                mapLegend.classList.remove('hidden');

                // Update coordinate info
                document.getElementById('legendLat').textContent = `Lat: ${data.center.lat.toFixed(6)}°`;
                document.getElementById('legendLng').textContent = `Lng: ${data.center.lng.toFixed(6)}°`;

                // Update date
                const today = new Date();
                const dateStr = today.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                });
                document.getElementById('legendDate').textContent = `Tanggal: ${dateStr}`;

                // Show legend items based on loaded layers
                if (layers.road) {
                    const lr = document.getElementById('legendRoad');
                    lr.classList.remove('hidden');
                    lr.classList.add('flex');
                    console.log('✓ Road legend shown');
                }
                if (layers.river) {
                    const lrv = document.getElementById('legendRiver');
                    lrv.classList.remove('hidden');
                    lrv.classList.add('flex');
                    console.log('✓ River legend shown');
                }

                console.log('✓ Legend box displayed');

            } catch (error) {
                console.error('Error:', error);
                showError('Error: ' + error.message);
                mapInfo.textContent = 'Error loading data';
            } finally {
                loading.classList.add('hidden');
                loadMapBtn.disabled = false;
                loadBtnText.textContent = '🗺️ Load Map';
            }
        });

        // Save map image WITH LEGEND
        async function saveMapWithCrop() {
            if (!boundaryBounds) {
                showError('No boundary defined. Please load boundary layer first.');
                return;
            }
            if (!layers.boundary) {
                showError('Boundary layer not found.');
                return;
            }

            saveMapBtn.disabled = true;
            saveMapBtn.textContent = '⏳ Generating...';
            mapInfo.textContent = 'Preparing map for export...';

            try {
                const originalCenter = map.getCenter();
                const originalZoom = map.getZoom();
                const originalLayers = {};

                Object.keys(layers).forEach(layerType => {
                    originalLayers[layerType] = map.hasLayer(layers[layerType]);
                    if (!map.hasLayer(layers[layerType])) {
                        map.addLayer(layers[layerType]);
                    }
                });

                if (layers.river) layers.river.bringToFront();
                if (layers.road) layers.road.bringToFront();
                if (layers.boundary) layers.boundary.bringToFront();

                const markerVisible = markerLayer && map.hasLayer(markerLayer);
                if (markerVisible) {
                    map.removeLayer(markerLayer);
                }

                const bounds = boundaryBounds;
                map.fitBounds(bounds, {
                    padding: [50, 50],
                    animate: false,
                    maxZoom: 10
                });

                map.invalidateSize(false);
                mapInfo.textContent = 'Rendering layers...';

                await new Promise(resolve => setTimeout(resolve, 3000));

                // Get map and legend
                const mapContainer = map.getContainer();
                const mapLegend = document.getElementById('mapLegend');

                const mapWidth = mapContainer.offsetWidth;
                const mapHeight = mapContainer.offsetHeight;
                const legendWidth = mapLegend.offsetWidth;

                console.log('Map size:', mapWidth, 'x', mapHeight);
                console.log('Legend width:', legendWidth);

                // Extract GeoJSON for manual drawing
                const boundaryGeoJSON = layers.boundary ? layers.boundary.toGeoJSON() : null;
                const roadGeoJSON = layers.road ? layers.road.toGeoJSON() : null;
                const riverGeoJSON = layers.river ? layers.river.toGeoJSON() : null;

                mapInfo.textContent = 'Capturing base map...';

                // Capture ONLY the map (basemap tiles)
                const baseCanvas = await html2canvas(mapContainer, {
                    useCORS: true,
                    allowTaint: false,
                    backgroundColor: '#ffffff',
                    logging: false,
                    scale: 2,
                    width: mapWidth,
                    height: mapHeight
                });

                console.log('Base canvas captured:', baseCanvas.width, 'x', baseCanvas.height);

                // Create final canvas (map + manual layers + legend)
                const scale = 2;
                const finalCanvas = document.createElement('canvas');
                const totalWidth = (mapWidth + legendWidth + 16) * scale; // 16px gap
                const totalHeight = mapHeight * scale;

                finalCanvas.width = totalWidth;
                finalCanvas.height = totalHeight;

                const ctx = finalCanvas.getContext('2d');

                // Draw white background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);

                // Draw base map
                ctx.drawImage(baseCanvas, 0, 0);

                mapInfo.textContent = 'Drawing vector layers...';

                // Helper function to draw lines
                function drawGeoJSON(geojson, color, width) {
                    if (!geojson || !geojson.features) return;

                    ctx.strokeStyle = color;
                    ctx.lineWidth = width * scale;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';

                    geojson.features.forEach(feature => {
                        if (!feature.geometry) return;

                        const type = feature.geometry.type;
                        const coords = feature.geometry.coordinates;

                        if (type === 'LineString') {
                            drawLine(coords);
                        } else if (type === 'MultiLineString') {
                            coords.forEach(line => drawLine(line));
                        } else if (type === 'Polygon') {
                            drawPolygon(coords);
                        } else if (type === 'MultiPolygon') {
                            coords.forEach(polygon => drawPolygon(polygon));
                        }
                    });
                }

                function drawLine(coordinates) {
                    if (coordinates.length < 2) return;

                    ctx.beginPath();
                    let started = false;

                    coordinates.forEach((coord, i) => {
                        const point = map.latLngToContainerPoint([coord[1], coord[0]]);
                        const x = point.x * scale;
                        const y = point.y * scale;

                        if (i === 0) {
                            ctx.moveTo(x, y);
                            started = true;
                        } else {
                            ctx.lineTo(x, y);
                        }
                    });

                    if (started) {
                        ctx.stroke();
                    }
                }

                function drawPolygon(coordinates) {
                    if (!coordinates || coordinates.length === 0) return;

                    // Draw outline only (no fill)
                    coordinates.forEach(ring => {
                        if (ring.length < 3) return;

                        ctx.beginPath();
                        ring.forEach((coord, i) => {
                            const point = map.latLngToContainerPoint([coord[1], coord[0]]);
                            const x = point.x * scale;
                            const y = point.y * scale;

                            if (i === 0) {
                                ctx.moveTo(x, y);
                            } else {
                                ctx.lineTo(x, y);
                            }
                        });
                        ctx.closePath();
                        ctx.stroke();
                    });
                }

                // Draw layers in order
                if (riverGeoJSON) {
                    console.log('Drawing rivers...');
                    drawGeoJSON(riverGeoJSON, '#3b82f6', 0.3);
                }

                if (roadGeoJSON) {
                    console.log('Drawing roads...');
                    drawGeoJSON(roadGeoJSON, '#FF0000', 4);
                }

                if (boundaryGeoJSON) {
                    console.log('Drawing boundary...');
                    drawGeoJSON(boundaryGeoJSON, '#000000', 0.7);
                }

                mapInfo.textContent = 'Adding legend...';

                // Capture legend
                const legendCanvas = await html2canvas(mapLegend, {
                    backgroundColor: '#ffffff',
                    logging: false,
                    scale: 2
                });

                // Draw legend on the right side
                const legendX = mapWidth * scale + 16 * scale; // 16px gap
                ctx.drawImage(legendCanvas, legendX, 0);

                console.log('Final canvas size:', finalCanvas.width, 'x', finalCanvas.height);

                // Restore map state
                if (markerVisible) map.addLayer(markerLayer);
                Object.keys(originalLayers).forEach(layerType => {
                    if (!originalLayers[layerType] && layers[layerType]) {
                        map.removeLayer(layers[layerType]);
                    }
                });
                map.setView(originalCenter, originalZoom);

                mapInfo.textContent = 'Saving image...';

                finalCanvas.toBlob(async (blob) => {
                    if (!blob) {
                        throw new Error('Failed to create image blob');
                    }

                    const reader = new FileReader();
                    reader.onloadend = async () => {
                        try {
                            const response = await fetch('/map/save-image', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({ image_data: reader.result })
                            });

                            const data = await response.json();

                            if (data.success) {
                                const link = document.createElement('a');
                                link.href = data.url;
                                link.download = data.filename;
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);

                                showSuccess('✓ Map with legend saved! Size: ' + finalCanvas.width + 'x' + finalCanvas.height + 'px');
                                mapInfo.textContent = 'Saved: ' + data.filename;
                                console.log('✓ Image saved successfully');
                            } else {
                                throw new Error(data.error || 'Failed to save');
                            }
                        } catch (error) {
                            showError('Upload error: ' + error.message);
                            mapInfo.textContent = 'Error uploading';
                        } finally {
                            saveMapBtn.disabled = false;
                            saveMapBtn.textContent = '💾 Save as Image (with Legend)';
                        }
                    };
                    reader.readAsDataURL(blob);
                }, 'image/png', 1.0);

            } catch (error) {
                console.error('Export error:', error);
                showError('Error: ' + error.message);
                mapInfo.textContent = 'Export failed';
                saveMapBtn.disabled = false;
                saveMapBtn.textContent = '💾 Save as Image (with Legend)';
            }
        }

        saveMapBtn.addEventListener('click', saveMapWithCrop);
    </script>
</body>

</html>
