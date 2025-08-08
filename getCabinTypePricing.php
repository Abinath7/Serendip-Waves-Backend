<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once __DIR__ . '/DbConnector.php';

$response = array('success' => false);

try {
    $db = new DBConnector();
    $pdo = $db->connect();
    
    // Get filter parameters from URL
    $shipName = isset($_GET['ship']) ? $_GET['ship'] : '';
    $route = isset($_GET['route']) ? $_GET['route'] : '';
    $minPrice = isset($_GET['minPrice']) ? (float)$_GET['minPrice'] : 0;
    $maxPrice = isset($_GET['maxPrice']) ? (float)$_GET['maxPrice'] : 0;
    
    // Build dynamic query with filters
    $baseQuery = '
        SELECT ctp.*, i.ship_name, i.route, s.class as ship_class, s.year_built
        FROM cabin_type_pricing ctp
        JOIN itineraries i ON ctp.ship_name = i.ship_name AND ctp.route = i.route
        JOIN ship_details s ON i.ship_name = s.ship_name
    ';
    
    $whereConditions = [];
    $params = [];
    
    // Add ship name filter
    if (!empty($shipName)) {
        $whereConditions[] = 'i.ship_name LIKE :ship_name';
        $params[':ship_name'] = '%' . $shipName . '%';
    }
    
    // Add route filter
    if (!empty($route)) {
        $whereConditions[] = 'i.route LIKE :route';
        $params[':route'] = '%' . $route . '%';
    }
    
    // Add price range filters
    if ($minPrice > 0) {
        $whereConditions[] = '(
            ctp.interior_price >= :min_price OR 
            ctp.ocean_view_price >= :min_price OR 
            ctp.balcony_price >= :min_price OR 
            ctp.suite_price >= :min_price
        )';
        $params[':min_price'] = $minPrice;
    }
    
    if ($maxPrice > 0) {
        $whereConditions[] = '(
            ctp.interior_price <= :max_price AND 
            ctp.ocean_view_price <= :max_price AND 
            ctp.balcony_price <= :max_price AND 
            ctp.suite_price <= :max_price
        )';
        $params[':max_price'] = $maxPrice;
    }
    
    // Construct final query
    $finalQuery = $baseQuery;
    if (!empty($whereConditions)) {
        $finalQuery .= ' WHERE ' . implode(' AND ', $whereConditions);
    }
    $finalQuery .= ' ORDER BY i.ship_name, i.route';
    
    $stmt = $pdo->prepare($finalQuery);
    $stmt->execute($params);
    $pricing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['pricing'] = $pricing;
    $response['filters_applied'] = [
        'ship_name' => $shipName,
        'route' => $route,
        'min_price' => $minPrice,
        'max_price' => $maxPrice
    ];
} catch (Exception $e) {
    $response['message'] = 'Error fetching pricing: ' . $e->getMessage();
}

echo json_encode($response);
