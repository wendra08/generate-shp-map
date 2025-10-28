<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index');
    }

    public function loadShapefile(Request $request)
    {
        try {
            Log::info('Upload request received', [
                'points' => $request->points,
                'files' => array_keys($request->allFiles())
            ]);

            $request->validate([
                'points' => 'required|json',
                'boundary_file' => 'required|file',
                'road_file' => 'nullable|file',
                'river_file' => 'nullable|file',
            ]);

            // Parse points dari JSON
            $points = json_decode($request->points, true);

            if (empty($points)) {
                throw new \Exception('At least one point is required');
            }

            // Validasi setiap point
            foreach ($points as $index => $point) {
                if (!isset($point['lat']) || !isset($point['lng'])) {
                    throw new \Exception("Point " . ($index + 1) . " missing coordinates");
                }

                $lat = floatval($point['lat']);
                $lng = floatval($point['lng']);

                if ($lat < -90 || $lat > 90) {
                    throw new \Exception("Point " . ($index + 1) . " has invalid latitude");
                }

                if ($lng < -180 || $lng > 180) {
                    throw new \Exception("Point " . ($index + 1) . " has invalid longitude");
                }
            }

            $layers = [];

            // Process Boundary File (Required)
            $boundaryFile = $request->file('boundary_file');
            $boundaryGeoJSON = $this->processFile($boundaryFile, 'boundary');
            $boundaryGeoJSON = $this->fixGeoJSONCoordinates($boundaryGeoJSON);

            $layers['boundary'] = [
                'geojson' => $boundaryGeoJSON,
                'name' => 'Boundary',
                'color' => '#000000',
                'fillColor' => '#fee2e2',
                'weight' => 3
            ];

            // Process Road File (Optional)
            if ($request->hasFile('road_file')) {
                $roadFile = $request->file('road_file');
                $roadGeoJSON = $this->processFile($roadFile, 'road');
                $roadGeoJSON = $this->fixGeoJSONCoordinates($roadGeoJSON);

                if (!empty($roadGeoJSON['features'])) {
                    $layers['road'] = [
                        'geojson' => $roadGeoJSON,
                        'name' => 'Roads',
                        'color' => '#FF0000',
                        'weight' => 4,
                        'opacity' => 1
                    ];
                }
            }

            // Process River File (Optional)
            if ($request->hasFile('river_file')) {
                $riverFile = $request->file('river_file');
                $riverGeoJSON = $this->processFile($riverFile, 'river');
                $riverGeoJSON = $this->fixGeoJSONCoordinates($riverGeoJSON);

                $riverGeoJSON = $this->clipToBoundary($riverGeoJSON, $boundaryGeoJSON);

                if (!empty($riverGeoJSON['features'])) {
                    $layers['river'] = [
                        'geojson' => $riverGeoJSON,
                        'name' => 'Rivers',
                        'color' => '#3b82f6',
                        'weight' => 2
                    ];
                }
            }

            // Calculate center from all points
            $centerLat = array_sum(array_column($points, 'lat')) / count($points);
            $centerLng = array_sum(array_column($points, 'lng')) / count($points);

            $response = [
                'success' => true,
                'layers' => $layers,
                'points' => $points,
                'center' => [
                    'lat' => $centerLat,
                    'lng' => $centerLng
                ]
            ];

            Log::info('Sending successful response', [
                'layer_count' => count($layers),
                'points_count' => count($points)
            ]);

            return response()->json($response);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'error' => 'Validation failed: ' . implode(', ', array_map(function($errors) {
                    return implode(', ', $errors);
                }, $e->errors()))
            ], 422);

        } catch (\Exception $e) {
            Log::error('File processing error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function processFile($file, $type)
    {
        $extension = strtolower($file->getClientOriginalExtension());

        Log::info("Processing {$type} file", [
            'name' => $file->getClientOriginalName(),
            'extension' => $extension,
            'size' => $file->getSize()
        ]);

        $content = file_get_contents($file->getRealPath());

        if (empty($content)) {
            throw new \Exception("File is empty: {$type}");
        }

        if ($extension === 'geojson' || $extension === 'json') {
            $geojson = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON in {$type} file: " . json_last_error_msg());
            }

            if (!isset($geojson['type'])) {
                throw new \Exception("Invalid GeoJSON in {$type} file: missing 'type' field");
            }

            $typeField = $geojson['type'] ?? null;

            switch ($typeField) {
                case 'FeatureCollection':
                    if (!isset($geojson['features']) || !is_array($geojson['features'])) {
                        throw new \Exception("Invalid GeoJSON in {$type} file: invalid features");
                    }
                    break;

                case 'Feature':
                    $geojson = [
                        'type' => 'FeatureCollection',
                        'features' => [$geojson]
                    ];
                    break;

                case 'GeometryCollection':
                    $features = array_map(function ($geometry) {
                        return [
                            "type" => "Feature",
                            "geometry" => $geometry,
                            "properties" => new \stdClass()
                        ];
                    }, $geojson['geometries'] ?? []);

                    $geojson = [
                        "type" => "FeatureCollection",
                        "features" => $features
                    ];
                    break;

                case 'Polygon':
                case 'MultiPolygon':
                case 'LineString':
                case 'MultiLineString':
                case 'Point':
                case 'MultiPoint':
                    $geojson = [
                        'type' => 'FeatureCollection',
                        'features' => [[
                            'type' => 'Feature',
                            'geometry' => $geojson,
                            'properties' => new \stdClass()
                        ]]
                    ];
                    break;

                default:
                    throw new \Exception("Unsupported GeoJSON type in {$type} file: " . $geojson['type']);
            }

            $geojson = $this->ensureWGS84($geojson, $type);

            Log::info("{$type} file processed", [
                'feature_count' => count($geojson['features'])
            ]);

            return $geojson;

        } elseif ($extension === 'shp') {
            throw new \Exception("Shapefile support for {$type}: Please convert to GeoJSON at https://mapshaper.org/");
        } else {
            throw new \Exception("Unsupported file format for {$type}: .{$extension}");
        }
    }

    private function fixGeoJSONCoordinates($geojson)
    {
        if (!isset($geojson['features'])) {
            return $geojson;
        }

        foreach ($geojson['features'] as &$feature) {
            if (isset($feature['geometry']['coordinates'])) {
                $feature['geometry']['coordinates'] = $this->swapCoordinates($feature['geometry']['coordinates']);
            }
        }

        return $geojson;
    }

    private function swapCoordinates($coords)
    {
        if (!is_array($coords)) {
            return $coords;
        }

        if (count($coords) === 2 && is_numeric($coords[0]) && is_numeric($coords[1])) {
            $lon = $coords[0];
            $lat = $coords[1];

            if ($lat < -90 || $lat > 90) {
                return [$lat, $lon];
            }
            return $coords;
        }

        return array_map([$this, 'swapCoordinates'], $coords);
    }

    private function ensureWGS84($geojson, $type)
    {
        if (!isset($geojson['features']) || empty($geojson['features'])) {
            return $geojson;
        }

        $firstFeature = $geojson['features'][0];
        $coords = $this->getFirstCoordinate($firstFeature);

        if (!$coords || count($coords) < 2) {
            return $geojson;
        }

        $x = $coords[0];
        $y = $coords[1];

        if (abs($x) > 180 || abs($y) > 90) {
            Log::info("{$type} detected as Web Mercator, converting to WGS84");
            return $this->convertWebMercatorToWGS84($geojson);
        }

        return $geojson;
    }

    private function getFirstCoordinate($feature)
    {
        if (!isset($feature['geometry']) || !isset($feature['geometry']['coordinates'])) {
            return null;
        }

        $coords = $feature['geometry']['coordinates'];
        $type = $feature['geometry']['type'];

        switch ($type) {
            case 'Point':
                return $coords;
            case 'LineString':
            case 'MultiPoint':
                return $coords[0] ?? null;
            case 'Polygon':
            case 'MultiLineString':
                return $coords[0][0] ?? null;
            case 'MultiPolygon':
                return $coords[0][0][0] ?? null;
            default:
                return null;
        }
    }

    private function convertWebMercatorToWGS84($geojson)
    {
        foreach ($geojson['features'] as &$feature) {
            if (isset($feature['geometry']['coordinates'])) {
                $feature['geometry']['coordinates'] = $this->convertCoordinates(
                    $feature['geometry']['coordinates'],
                    $feature['geometry']['type']
                );
            }
        }
        return $geojson;
    }

    private function convertCoordinates($coords, $type)
    {
        switch ($type) {
            case 'Point':
                return $this->webMercatorToWGS84($coords);

            case 'LineString':
            case 'MultiPoint':
                return array_map([$this, 'webMercatorToWGS84'], $coords);

            case 'Polygon':
            case 'MultiLineString':
                return array_map(function($ring) {
                    return array_map([$this, 'webMercatorToWGS84'], $ring);
                }, $coords);

            case 'MultiPolygon':
                return array_map(function($polygon) {
                    return array_map(function($ring) {
                        return array_map([$this, 'webMercatorToWGS84'], $ring);
                    }, $polygon);
                }, $coords);

            default:
                return $coords;
        }
    }

    private function webMercatorToWGS84($coord)
    {
        $x = $coord[0];
        $y = $coord[1];

        $lng = ($x / 20037508.34) * 180;
        $lat = ($y / 20037508.34) * 180;
        $lat = 180 / M_PI * (2 * atan(exp($lat * M_PI / 180)) - M_PI / 2);

        return [$lng, $lat];
    }

    private function clipToBoundary($layerGeoJSON, $boundaryGeoJSON)
    {
        $bounds = $this->getBoundaryBounds($boundaryGeoJSON);

        if (!$bounds) {
            Log::warning("Could not extract boundary bounds");
            return $layerGeoJSON;
        }

        Log::info("Boundary bounds", $bounds);

        $clippedFeatures = [];
        $originalCount = count($layerGeoJSON['features']);

        foreach ($layerGeoJSON['features'] as $feature) {
            if (!isset($feature['geometry'])) continue;

            $geom = $feature['geometry'];
            $type = $geom['type'];

            if ($type === 'LineString') {
                $clipped = $this->clipLineString($geom['coordinates'], $bounds);
                if (!empty($clipped)) {
                    $feature['geometry']['coordinates'] = $clipped;
                    $clippedFeatures[] = $feature;
                }
            } elseif ($type === 'MultiLineString') {
                $clippedLines = [];
                foreach ($geom['coordinates'] as $line) {
                    $clipped = $this->clipLineString($line, $bounds);
                    if (!empty($clipped)) {
                        $clippedLines[] = $clipped;
                    }
                }
                if (!empty($clippedLines)) {
                    $feature['geometry']['coordinates'] = $clippedLines;
                    $clippedFeatures[] = $feature;
                }
            } elseif ($type === 'Point') {
                if ($this->pointInBounds($geom['coordinates'], $bounds)) {
                    $clippedFeatures[] = $feature;
                }
            }
        }

        $clippedCount = count($clippedFeatures);
        Log::info("Clipping result", [
            'original' => $originalCount,
            'clipped' => $clippedCount,
            'removed' => $originalCount - $clippedCount
        ]);

        return [
            'type' => 'FeatureCollection',
            'features' => $clippedFeatures
        ];
    }

    private function getBoundaryBounds($boundaryGeoJSON)
    {
        $minLng = PHP_FLOAT_MAX;
        $maxLng = -PHP_FLOAT_MAX;
        $minLat = PHP_FLOAT_MAX;
        $maxLat = -PHP_FLOAT_MAX;

        $features = $boundaryGeoJSON['features'] ?? [$boundaryGeoJSON];

        foreach ($features as $feature) {
            $geom = $feature['geometry'] ?? $feature;
            $coords = null;

            if ($geom['type'] === 'Polygon') {
                $coords = $geom['coordinates'][0];
            } elseif ($geom['type'] === 'MultiPolygon') {
                $coords = $geom['coordinates'][0][0];
            }

            if ($coords) {
                foreach ($coords as $coord) {
                    $lng = $coord[0];
                    $lat = $coord[1];

                    if ($lng < $minLng) $minLng = $lng;
                    if ($lng > $maxLng) $maxLng = $lng;
                    if ($lat < $minLat) $minLat = $lat;
                    if ($lat > $maxLat) $maxLat = $lat;
                }
            }
        }

        if ($minLng === PHP_FLOAT_MAX) {
            return null;
        }

        return [
            'minLng' => $minLng,
            'maxLng' => $maxLng,
            'minLat' => $minLat,
            'maxLat' => $maxLat
        ];
    }

    private function clipLineString($coords, $bounds)
    {
        $clipped = [];

        foreach ($coords as $coord) {
            if ($this->pointInBounds($coord, $bounds)) {
                $clipped[] = $coord;
            }
        }

        return count($clipped) >= 2 ? $clipped : [];
    }

    private function pointInBounds($coord, $bounds)
    {
        $lng = $coord[0];
        $lat = $coord[1];

        return $lng >= $bounds['minLng'] && $lng <= $bounds['maxLng'] &&
               $lat >= $bounds['minLat'] && $lat <= $bounds['maxLat'];
    }

    public function saveMapImage(Request $request)
    {
        $request->validate([
            'image_data' => 'required|string',
        ]);

        try {
            $imageData = $request->image_data;

            if (strpos($imageData, 'data:image/png;base64,') === 0) {
                $imageData = substr($imageData, strlen('data:image/png;base64,'));
            }

            $imageData = str_replace(' ', '+', $imageData);
            $decoded = base64_decode($imageData);

            if ($decoded === false) {
                throw new \Exception('Failed to decode image data');
            }

            $filename = 'map_' . time() . '_' . uniqid() . '.png';
            $path = storage_path('app/public/maps');

            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $fullPath = $path . '/' . $filename;
            file_put_contents($fullPath, $decoded);

            return response()->json([
                'success' => true,
                'filename' => $filename,
                'url' => asset('storage/maps/' . $filename)
            ]);

        } catch (\Exception $e) {
            Log::error('Image save error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to save image: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadImage($filename)
    {
        $path = storage_path('app/public/maps/' . $filename);

        if (!file_exists($path)) {
            abort(404, 'Image not found');
        }

        return response()->download($path);
    }
}
