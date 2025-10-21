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
        #map {
            height: 600px;
            width: 100%;
            border-radius: 0.5rem;
        }

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
                            💾 Save as Image (Boundary Area Only)
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
                <div id="map" class="shadow-inner"></div>
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

                // Add layers in order: boundary first, then roads, then rivers

                if (!map.getPane('vectorPane')) {
                    map.createPane('vectorPane');
                    map.getPane('vectorPane').style.zIndex = 650; // Di atas tile layer (400)
                }
                console.log('✓ Custom vector pane created (z-index: 650)');

                const layerOrder = ['boundary', 'road', 'river'];


                layerOrder.forEach(layerType => {
                    const layerData = data.layers?.[layerType] || data[layerType];
                    if (data.layers[layerType]) {
                        const layerData = data.layers[layerType];
                        const geojson = layerData.geojson;

                        if (!geojson.features || geojson.features.length === 0) {
                            console.warn(`${layerType} has no features`);
                            return;
                        }

                        totalFeatures += geojson.features.length;

                        // Debug logging
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

                        // DEBUG: Log layer bounds
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

                        // Verify layer added
                        console.log(`✓ ${layerType} layer added to map, visible: ${map.hasLayer(layer)}`);

                        // Store boundary bounds for cropping
                        if (layerType === 'boundary') {
                            boundaryBounds = layer.getBounds();
                        }

                        // Add to layer control
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

                // DEBUG: Tambahkan tombol untuk zoom ke road
                if (layers.road) {
                    console.log('Road layer exists, you can zoom to it');
                };

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

        // Save map image - cropped to boundary bounds with all layers visible
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
                // 1. Simpan state original
                const originalCenter = map.getCenter();
                const originalZoom = map.getZoom();
                const originalLayers = {};

                // 2. Pastikan SEMUA layer visible dan urutannya benar
                Object.keys(layers).forEach(layerType => {
                    originalLayers[layerType] = map.hasLayer(layers[layerType]);
                    if (!map.hasLayer(layers[layerType])) {
                        map.addLayer(layers[layerType]);
                        console.log('✓ Added', layerType, 'layer');
                    }
                });

                // Pastikan urutan layer: river di bawah, road di tengah, boundary paling atas
                if (layers.river) {
                    layers.river.bringToFront();
                    console.log('✓ River brought to front');
                }
                if (layers.road) {
                    layers.road.bringToFront();
                    console.log('✓ Road brought to front');
                }
                if (layers.boundary) {
                    layers.boundary.bringToFront();
                    console.log('✓ Boundary brought to front');
                }

                console.log('Layer order set: river → road → boundary');

                // 3. Sembunyikan marker
                const markerVisible = markerLayer && map.hasLayer(markerLayer);
                if (markerVisible) {
                    map.removeLayer(markerLayer);
                }

                // 4. Fit map ke boundary dengan padding minimal
                const bounds = boundaryBounds;
                map.fitBounds(bounds, {
                    padding: [50, 50],
                    animate: false,
                    maxZoom: 10
                });

                map.invalidateSize(false);

                // 5. Tunggu rendering - lebih lama untuk memastikan semua layer loaded
                mapInfo.textContent = 'Rendering layers...';

                // Log semua layer yang visible
                console.log('Visible layers before capture:');
                Object.keys(layers).forEach(layerType => {
                    const isVisible = map.hasLayer(layers[layerType]);
                    console.log(`- ${layerType}: ${isVisible ? '✓ visible' : '✗ hidden'}`);
                });

                await new Promise(resolve => setTimeout(resolve, 3000));

                // 6. Get map container
                const mapContainer = map.getContainer();
                const containerWidth = mapContainer.offsetWidth;
                const containerHeight = mapContainer.offsetHeight;

                console.log('Map size:', containerWidth, 'x', containerHeight);
                console.log('Zoom level:', map.getZoom());

                // 7. Hitung pixel coordinates boundary
                const nw = map.latLngToContainerPoint(bounds.getNorthWest());
                const se = map.latLngToContainerPoint(bounds.getSouthEast());

                const cropX = Math.max(0, Math.floor(nw.x));
                const cropY = Math.max(0, Math.floor(nw.y));
                const cropWidth = Math.min(Math.ceil(se.x - nw.x), containerWidth - cropX);
                const cropHeight = Math.min(Math.ceil(se.y - nw.y), containerHeight - cropY);

                console.log('Boundary crop area:', { cropX, cropY, cropWidth, cropHeight });

                if (cropWidth <= 0 || cropHeight <= 0) {
                    throw new Error('Invalid crop dimensions');
                }

                // 8. Extract boundary coordinates untuk clipping path
                const boundaryGeoJSON = layers.boundary.toGeoJSON();
                let boundaryCoords = [];

                // Extract coordinates dari GeoJSON
                if (boundaryGeoJSON.type === 'FeatureCollection') {
                    boundaryGeoJSON.features.forEach(feature => {
                        if (feature.geometry.type === 'Polygon') {
                            boundaryCoords.push(...feature.geometry.coordinates[0]);
                        } else if (feature.geometry.type === 'MultiPolygon') {
                            feature.geometry.coordinates.forEach(polygon => {
                                boundaryCoords.push(...polygon[0]);
                            });
                        }
                    });
                } else if (boundaryGeoJSON.geometry.type === 'Polygon') {
                    boundaryCoords = boundaryGeoJSON.geometry.coordinates[0];
                } else if (boundaryGeoJSON.geometry.type === 'MultiPolygon') {
                    boundaryGeoJSON.geometry.coordinates.forEach(polygon => {
                        boundaryCoords.push(...polygon[0]);
                    });
                }

                console.log('Boundary coordinates count:', boundaryCoords.length);

                // 9. Capture full map
                mapInfo.textContent = 'Capturing map...';

                const fullCanvas = await html2canvas(mapContainer, {
                    useCORS: true,
                    allowTaint: false,
                    backgroundColor: '#ffffff',
                    logging: false,
                    scale: 2,
                    width: containerWidth,
                    height: containerHeight
                });

                console.log('Full canvas captured:', fullCanvas.width, 'x', fullCanvas.height);

                // 10. Extract GeoJSON data dari layers untuk manual drawing
                const riverGeoJSON = layers.river ? layers.river.toGeoJSON() : null;
                const roadGeoJSON = layers.road ? layers.road.toGeoJSON() : null;

                // 11. Restore map state
                if (markerVisible) map.addLayer(markerLayer);
                Object.keys(originalLayers).forEach(layerType => {
                    if (!originalLayers[layerType] && layers[layerType]) {
                        map.removeLayer(layers[layerType]);
                    }
                });
                map.setView(originalCenter, originalZoom);

                // 12. Create cropped canvas dengan clipping mask
                mapInfo.textContent = 'Applying boundary mask...';

                const scale = 2;
                const croppedCanvas = document.createElement('canvas');
                croppedCanvas.width = cropWidth * scale;
                croppedCanvas.height = cropHeight * scale;

                const ctx = croppedCanvas.getContext('2d');

                // Fill white background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, croppedCanvas.width, croppedCanvas.height);

                // Create clipping path dari boundary polygon
                ctx.save();
                ctx.beginPath();

                boundaryCoords.forEach((coord, index) => {
                    // Convert lat/lng to pixel dalam cropped canvas
                    const point = map.latLngToContainerPoint([coord[1], coord[0]]);
                    const x = (point.x - cropX) * scale;
                    const y = (point.y - cropY) * scale;

                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });

                ctx.closePath();
                ctx.clip(); // Apply clipping mask

                // Draw cropped image DENGAN clipping mask
                ctx.drawImage(
                    fullCanvas,
                    cropX * scale,
                    cropY * scale,
                    cropWidth * scale,
                    cropHeight * scale,
                    0,
                    0,
                    cropWidth * scale,
                    cropHeight * scale
                );

                ctx.restore();

                // PENTING: Draw rivers dan roads secara manual karena html2canvas gagal capture SVG
                mapInfo.textContent = 'Drawing vector layers...';

                // Helper function untuk draw LineString/MultiLineString
                function drawLineString(coordinates, color, width) {
                    ctx.strokeStyle = color;
                    ctx.lineWidth = width;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';

                    coordinates.forEach(line => {
                        ctx.beginPath();
                        const coords = Array.isArray(line[0]) ? line : [line];

                        let validPath = false;
                        coords.forEach((coord, i) => {
                            // FILTER: Skip koordinat invalid (0,0 atau di luar bounds)
                            if (!coord || coord.length < 2) return;
                            const lng = coord[0];
                            const lat = coord[1];

                            // Skip jika koordinat invalid
                            if (lng === 0 && lat === 0) return;
                            if (Math.abs(lat) > 90 || Math.abs(lng) > 180) return;

                            // Skip jika koordinat terlalu jauh dari boundary (lebih dari 10 derajat)
                            const boundsCenter = bounds.getCenter();
                            const latDiff = Math.abs(lat - boundsCenter.lat);
                            const lngDiff = Math.abs(lng - boundsCenter.lng);
                            if (latDiff > 10 || lngDiff > 10) return;

                            const point = map.latLngToContainerPoint([lat, lng]);
                            const x = (point.x - cropX) * scale;
                            const y = (point.y - cropY) * scale;

                            // Skip jika point di luar canvas
                            if (x < -1000 || x > croppedCanvas.width + 1000) return;
                            if (y < -1000 || y > croppedCanvas.height + 1000) return;

                            if (i === 0 || !validPath) {
                                ctx.moveTo(x, y);
                                validPath = true;
                            } else {
                                ctx.lineTo(x, y);
                            }
                        });

                        if (validPath) {
                            ctx.stroke();
                        }
                    });
                }

                // Draw rivers (biru)
                if (riverGeoJSON) {
                    console.log('Drawing rivers...');
                    const features = riverGeoJSON.features || [riverGeoJSON];
                    features.forEach(feature => {
                        if (!feature.geometry) return;

                        if (feature.geometry.type === 'LineString') {
                            drawLineString([feature.geometry.coordinates], '#0066CC', 0.5 * scale);
                        } else if (feature.geometry.type === 'MultiLineString') {
                            drawLineString(feature.geometry.coordinates, '#0066CC', 0.5 * scale);
                        }
                    });
                    console.log('✓ Rivers drawn');
                }

                // Draw roads (merah/orange)
                if (roadGeoJSON) {
                    console.log('Drawing roads...');
                    const features = roadGeoJSON.features || [roadGeoJSON];
                    let roadCount = 0;
                    features.forEach(feature => {
                        if (!feature.geometry) return;

                        if (feature.geometry.type === 'LineString') {
                            drawLineString([feature.geometry.coordinates], '#BF092F', 2 * scale);
                            roadCount++;
                        } else if (feature.geometry.type === 'MultiLineString') {
                            drawLineString(feature.geometry.coordinates, '#BF092F', 2 * scale);
                            roadCount++;
                        }
                    });
                    console.log(`✓ Roads drawn: ${roadCount} features`);
                } else {
                    console.log('No road layer to draw');
                }

                // 12. Draw boundary outline (tebal)
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 4;
                ctx.beginPath();

                boundaryCoords.forEach((coord, index) => {
                    const point = map.latLngToContainerPoint([coord[1], coord[0]]);
                    const x = (point.x - cropX) * scale;
                    const y = (point.y - cropY) * scale;

                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });

                ctx.closePath();
                ctx.stroke();

                console.log('✓ Clipping mask applied');
                console.log('✓ Boundary outline drawn');

                // 13. Convert to blob and save
                mapInfo.textContent = 'Saving image...';

                croppedCanvas.toBlob(async (blob) => {
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

                                showSuccess(`✓ Map saved! Boundary area only: ${Math.round(cropWidth)}x${Math.round(cropHeight)}px`);
                                mapInfo.textContent = `Saved: ${data.filename}`;
                                console.log('✓ Image saved successfully');
                            } else {
                                throw new Error(data.error || 'Failed to save');
                            }
                        } catch (error) {
                            showError('Upload error: ' + error.message);
                            mapInfo.textContent = 'Error uploading';
                        } finally {
                            saveMapBtn.disabled = false;
                            saveMapBtn.textContent = '💾 Save as Image (Boundary Area Only)';
                        }
                    };
                    reader.readAsDataURL(blob);
                }, 'image/png', 1.0);

            } catch (error) {
                console.error('Export error:', error);
                showError('Error: ' + error.message);
                mapInfo.textContent = 'Export failed';
                saveMapBtn.disabled = false;
                saveMapBtn.textContent = '💾 Save as Image (Boundary Area Only)';
            }
        }

        // Attach event listener
        saveMapBtn.addEventListener('click', saveMapWithCrop);
    </script>
</body>

</html>
