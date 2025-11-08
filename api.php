<?php
// ДОБАВЬТЕ ЭТИ СТРОКИ В САМОЕ НАЧАЛО
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ПОДКЛЮЧЕНИЕ AUTOLOAD ДО config.php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_calls':
            getCallsData($db);
            break;
        case 'get_stats':
            getStatistics($db);
            break;
        case 'get_sectors':
            getSectorsData($db);
            break;
        case 'get_filters':
            getFilterOptions($db);
            break;
            case 'search_by_exact_coordinates':
    searchByExactCoordinates($db);
    break;
        default:
            echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getCallsData($db)
{
    $filters = buildFilters();

    // ★★★ ДОБАВЛЯЕМ intensity В SELECT ★★★
    $sql = "SELECT 
        longitude, 
        latitude,
        povd as reason,
        vozr as age,
        pol as gender,
        mkb as diagnosis,
        city,
        ulic as street,
        dom as house,
        sect as sector,
        call_date,
        call_time,
        intensity
   FROM ambulance_calls 
WHERE longitude IS NOT NULL AND latitude IS NOT NULL 
{$filters['where']}"; // Без лимита - показываем все записи

    $stmt = $db->prepare($sql);
    $stmt->execute($filters['params']);

    $calls = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $calls[] = [
            'coordinates' => [(float) $row['longitude'], (float) $row['latitude']],
            'reason' => $row['reason'],
            'age' => $row['age'],
            'gender' => $row['gender'],
            'diagnosis' => $row['diagnosis'],
            'address' => ($row['city'] ? $row['city'] . ', ' : '') . ($row['street'] ? $row['street'] : '') . ($row['house'] ? ' ' . $row['house'] : ''),
            'sector' => $row['sector'],
            'date' => $row['call_date'],
            'time' => $row['call_time'],
            'intensity' => $row['intensity'] // ★★★ ДОБАВЛЯЕМ ИНТЕНСИВНОСТЬ ★★★
        ];
    }

    echo json_encode(['success' => true, 'calls' => $calls, 'total' => count($calls)]);
}

function getStatistics($db)
{
    $filters = buildFilters();

    $sql = "SELECT 
        COUNT(*) as total_calls,
        COUNT(DISTINCT sect) as sectors_covered,
        AVG(CASE WHEN vozr > 0 THEN vozr ELSE NULL END) as avg_age,
        COUNT(DISTINCT povd) as unique_reasons,
        COUNT(*) as calls_with_coords,
        MIN(call_date) as first_date,
        MAX(call_date) as last_date
    FROM ambulance_calls 
    WHERE 1=1 {$filters['where']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($filters['params']);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalCalls' => (int) ($stats['total_calls'] ?? 0),
            'sectorsCovered' => (int) ($stats['sectors_covered'] ?? 0),
            'avgAge' => $stats['avg_age'] ? round($stats['avg_age'], 1) : 0,
            'uniqueReasons' => (int) ($stats['unique_reasons'] ?? 0),
            'callsWithCoords' => (int) ($stats['calls_with_coords'] ?? 0),
            'dateRange' => $stats['first_date'] && $stats['last_date'] ?
                $stats['first_date'] . ' - ' . $stats['last_date'] : 'Не указано'
        ]
    ]);
}

function getSectorsData($db)
{
    $filters = buildFilters();

    $sql = "SELECT 
        COALESCE(sect, 0) as sector,
        COUNT(*) as call_count,
        AVG(CASE WHEN vozr > 0 THEN vozr ELSE NULL END) as avg_age,
        COUNT(DISTINCT povd) as unique_reasons,
        AVG(longitude) as center_lon,
        AVG(latitude) as center_lat
    FROM ambulance_calls 
    WHERE longitude IS NOT NULL AND latitude IS NOT NULL
    {$filters['where']}
    GROUP BY COALESCE(sect, 0)
    HAVING AVG(longitude) IS NOT NULL AND AVG(latitude) IS NOT NULL
    ORDER BY call_count DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($filters['params']);

    $sectors = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sectors[] = [
            'sector' => $row['sector'],
            'call_count' => (int) $row['call_count'],
            'avg_age' => $row['avg_age'] ? round($row['avg_age'], 1) : 0,
            'unique_reasons' => (int) $row['unique_reasons'],
            'center' => [
                'lon' => (float) $row['center_lon'],
                'lat' => (float) $row['center_lat']
            ]
        ];
    }

    echo json_encode(['success' => true, 'sectors' => $sectors]);
}

function getFilterOptions($db)
{
    // Получаем уникальные причины вызовов
    $reasons = $db->query("SELECT DISTINCT povd FROM ambulance_calls WHERE povd IS NOT NULL AND povd != '' ORDER BY povd LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);

    // Получаем уникальные диагнозы МКБ
    $diagnoses = $db->query("SELECT DISTINCT mkb FROM ambulance_calls WHERE mkb IS NOT NULL AND mkb != '' ORDER BY mkb LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);

    // Получаем уникальные города
    $cities = $db->query("SELECT DISTINCT city FROM ambulance_calls WHERE city IS NOT NULL AND city != '' ORDER BY city LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'filters' => [
            'reasons' => $reasons,
            'diagnoses' => $diagnoses,
            'cities' => $cities
        ]
    ]);
}
// ★★★ ОБНОВЛЕННАЯ ФУНКЦИЯ ФИЛЬТРАЦИИ ★★★
function buildFilters()
{
    $where = "";
    $params = [];

    // Фильтр по дате начала
    if (!empty($_GET['start_date'])) {
        $where .= " AND call_date >= :start_date";
        $params[':start_date'] = $_GET['start_date'];
    }

    // Фильтр по дате окончания
    if (!empty($_GET['end_date'])) {
        $where .= " AND call_date <= :end_date";
        $params[':end_date'] = $_GET['end_date'];
    }

    // Фильтр по времени начала
    if (!empty($_GET['start_time'])) {
        $where .= " AND call_time >= :start_time";
        $params[':start_time'] = $_GET['start_time'] . ':00';
    }

    // Фильтр по времени окончания
    if (!empty($_GET['end_time'])) {
        $where .= " AND call_time <= :end_time";
        $params[':end_time'] = $_GET['end_time'] . ':00';
    }

    // Фильтр по причине вызова
    if (!empty($_GET['reason']) && $_GET['reason'] != 'all') {
        $where .= " AND povd = :reason";
        $params[':reason'] = $_GET['reason'];
    }

    // Фильтр по диагнозу МКБ
    if (!empty($_GET['diagnosis']) && $_GET['diagnosis'] != 'all') {
        $where .= " AND mkb = :diagnosis";
        $params[':diagnosis'] = $_GET['diagnosis'];
    }

    // Фильтр по минимальному возрасту
    if (!empty($_GET['min_age'])) {
        $where .= " AND vozr >= :min_age";
        $params[':min_age'] = (int) $_GET['min_age'];
    }

    // Фильтр по максимальному возрасту
    if (!empty($_GET['max_age'])) {
        $where .= " AND vozr <= :max_age";
        $params[':max_age'] = (int) $_GET['max_age'];
    }

    // Фильтр по времени суток
    if (!empty($_GET['time_of_day']) && $_GET['time_of_day'] != 'all') {
        switch ($_GET['time_of_day']) {
            case 'morning':
                $where .= " AND EXTRACT(HOUR FROM call_time) BETWEEN 6 AND 11";
                break;
            case 'day':
                $where .= " AND EXTRACT(HOUR FROM call_time) BETWEEN 12 AND 17";
                break;
            case 'evening':
                $where .= " AND EXTRACT(HOUR FROM call_time) BETWEEN 18 AND 23";
                break;
            case 'night':
                $where .= " AND (EXTRACT(HOUR FROM call_time) BETWEEN 0 AND 5)";
                break;
        }
    }

    // Фильтр по интенсивности
    if (!empty($_GET['intensity']) && $_GET['intensity'] != 'all') {
        $where .= " AND intensity = :intensity";
        $params[':intensity'] = $_GET['intensity'];
    }

    // Фильтр по секторам
    if (!empty($_GET['sectors'])) {
        $sectors = explode(',', $_GET['sectors']);
        $placeholders = [];
        foreach ($sectors as $i => $sector) {
            $placeholders[] = ':sector_' . $i;
            $params[':sector_' . $i] = (int) $sector;
        }
        $where .= " AND sect IN (" . implode(',', $placeholders) . ")";
    }

    // Фильтр по городу
    if (!empty($_GET['city']) && $_GET['city'] != 'all') {
        $where .= " AND city = :city";
        $params[':city'] = $_GET['city'];
    }

    return ['where' => $where, 'params' => $params];
}

// ★★★ ФУНКЦИЯ ПОИСКА ПО ТОЧНЫМ КООРДИНАТАМ ★★★
// ★★★ ОБНОВЛЕННАЯ ФУНКЦИЯ ПОИСКА ПО КООРДИНАТАМ ★★★
function searchByExactCoordinates($db) {
    $latitude = floatval($_GET['lat'] ?? 0);
    $longitude = floatval($_GET['lng'] ?? 0);
    
    // Логируем полученные координаты для отладки
    error_log("Search coordinates - lat: " . $_GET['lat'] . ", lng: " . $_GET['lng']);
    error_log("Parsed coordinates - lat: $latitude, lng: $longitude");
    
    if (!$latitude && $latitude != 0) {
        echo json_encode(['success' => false, 'message' => 'Не указаны координаты']);
        return;
    }
    
    // Ищем точное совпадение координат (с учетом знаков)
    $sql = "
        SELECT * 
        FROM ambulance_calls 
        WHERE ABS(longitude - :lng) < 0.000001 
          AND ABS(latitude - :lat) < 0.000001
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':lat' => $latitude,
        ':lng' => $longitude
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'found' => true,
            'call' => [
                'id' => $result['id'],
                'coordinates' => [(float)$result['longitude'], (float)$result['latitude']],
                'reason' => $result['povd'],
                'intensity' => $result['intensity'],
                'sector' => $result['sect'],
                'address' => ($result['city'] ? $result['city'] . ', ' : '') . 
                            ($result['ulic'] ? $result['ulic'] : '') . 
                            ($result['dom'] ? ' ' . $result['dom'] : ''),
                'date' => $result['call_date'],
                'time' => $result['call_time'],
                'age' => $result['vozr'],
                'gender' => $result['pol'],
                'diagnosis' => $result['mkb']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'found' => false,
            'message' => 'На указанных координатах вызовов не найдено',
            'search_coordinates' => [$longitude, $latitude],
            'debug' => [
                'received_lat' => $_GET['lat'] ?? 'null',
                'received_lng' => $_GET['lng'] ?? 'null',
                'parsed_lat' => $latitude,
                'parsed_lng' => $longitude
            ]
        ]);
    }
}


?>