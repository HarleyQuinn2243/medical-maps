<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

header('Content-Type: application/json');

// Увеличиваем лимиты для больших файлов
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Глобальные переменные для ограничения запросов
$geocodeCount = 0;
$lastGeocodeTime = 0;

function createAmbulanceTable($db)
{
    try {
        // Проверяем существование таблицы
        $check = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'ambulance_calls')")->fetchColumn();

        if (!$check) {
            $sql = "
            CREATE TABLE ambulance_calls (
                id SERIAL PRIMARY KEY,
                numv INTEGER,
                ngod INTEGER,
                prty INTEGER,
                sect INTEGER,
                rjon INTEGER,
                city VARCHAR(255),
                ulic VARCHAR(255),
                dom VARCHAR(255),
                mest INTEGER,
                povd VARCHAR(10),
                vozr INTEGER,
                pol VARCHAR(1),
                povt INTEGER,
                prof VARCHAR(1),
                smpt INTEGER,
                stan INTEGER,
                dprm VARCHAR(20),
                tprm TIME,
                wday INTEGER,
                line INTEGER,
                rezl INTEGER,
                sgsp VARCHAR(10),
                rgsp VARCHAR(10),
                kuda VARCHAR(255),
                ds1 VARCHAR(10),
                ds2 VARCHAR(10),
                trav VARCHAR(1),
                alk VARCHAR(1),
                numb INTEGER,
                smpb INTEGER,
                stbr INTEGER,
                stbb INTEGER,
                prfb VARCHAR(1),
                ncar VARCHAR(20),
                rcod VARCHAR(10),
                tabn INTEGER,
                tab2 INTEGER,
                tab3 INTEGER,
                tab4 INTEGER,
                vr51 INTEGER,
                d201 INTEGER,
                dsp1 INTEGER,
                dsp2 INTEGER,
                dspp INTEGER,
                dsp3 INTEGER,
                kakp INTEGER,
                tper TIME,
                vyez TIME,
                przd TIME,
                tgsp TIME,
                tsta TIME,
                tisp TIME,
                tvzv TIME,
                kilo INTEGER,
                poli INTEGER,
                socs INTEGER,
                mrab INTEGER,
                pri2 INTEGER,
                inf6 INTEGER,
                dshs INTEGER,
                ferr INTEGER,
                expo INTEGER,
                mkb VARCHAR(10),
                smpp INTEGER,
                subrf INTEGER,
                kladv VARCHAR(20),
                kladp VARCHAR(20),
                longitude FLOAT,
                latitude FLOAT,
                call_date DATE,
                call_time TIME,
                intensity VARCHAR(10),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";

            $db->exec($sql);

            // Создаем индексы для ускорения запросов
            $indexes = [
                "CREATE INDEX idx_ambulance_coords ON ambulance_calls(longitude, latitude)",
                "CREATE INDEX idx_ambulance_sector ON ambulance_calls(sect)",
                "CREATE INDEX idx_ambulance_date ON ambulance_calls(call_date)",
                "CREATE INDEX idx_ambulance_reason ON ambulance_calls(povd)",
                "CREATE INDEX idx_ambulance_city ON ambulance_calls(city)",
                "CREATE INDEX idx_ambulance_intensity ON ambulance_calls(intensity)"
            ];

            foreach ($indexes as $indexSql) {
                try {
                    $db->exec($indexSql);
                } catch (Exception $e) {
                    // Игнорируем ошибки создания индексов, если они уже существуют
                }
            }

            return "Таблица создана успешно с индексами!";
        }
        return "Таблица уже существует";
    } catch (Exception $e) {
        throw new Exception("Ошибка создания таблицы: " . $e->getMessage());
    }
}

function parseExcelDate($dateValue)
{
    if (empty($dateValue))
        return null;

    try {
        // Если это Excel дата
        if (is_numeric($dateValue)) {
            return Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
        }

        // Если это строка с датой
        $dateValue = trim($dateValue);

        // Пробуем разные форматы дат
        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'Ymd', 'dmY'];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateValue);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    } catch (Exception $e) {
        return null;
    }
}

function parseExcelTime($timeValue)
{
    if (empty($timeValue))
        return null;

    try {
        // Если это Excel время
        if (is_numeric($timeValue)) {
            return Date::excelToDateTimeObject($timeValue)->format('H:i:s');
        }

        $timeValue = trim($timeValue);

        // Заменяем разные разделители
        $timeValue = str_replace(['^', ' ', '-'], ':', $timeValue);

        // Пробуем разные форматы времени
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeValue, $matches)) {
            return sprintf("%02d:%02d:00", $matches[1], $matches[2]);
        }

        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $timeValue, $matches)) {
            return sprintf("%02d:%02d:%02d", $matches[1], $matches[2], $matches[3]);
        }

        if (preg_match('/^\d{4}$/', $timeValue)) {
            $hours = substr($timeValue, 0, 2);
            $minutes = substr($timeValue, 2, 2);
            return sprintf("%02d:%02d:00", $hours, $minutes);
        }

        return null;
    } catch (Exception $e) {
        return null;
    }
}

function parseCoordinate($coord)
{
    if (empty($coord) || $coord === '' || $coord === null)
        return null;

    $coord = trim($coord);
    $coord = str_replace('+', '', $coord);
    $coord = str_replace(',', '.', $coord);

    // Удаляем лишние пробелы
    $coord = preg_replace('/\s+/', '', $coord);

    if (is_numeric($coord)) {
        $value = floatval($coord);
        // Проверяем разумные границы для координат Сахалина
        if ($value > 140 && $value < 150)
            return $value; // longitude
        if ($value > 45 && $value < 50)
            return $value;   // latitude
    }

    return null;
}

function cleanValue($value)
{
    if ($value === null)
        return null;

    $value = trim($value);

    // Убираем лишние пробелы и непечатаемые символы
    $value = preg_replace('/\s+/', ' ', $value);
    $value = preg_replace('/[^\x20-\x7E]/u', '', $value);

    return $value;
}

function buildAddress($row) {
    $city = cleanValue($row[5] ?? '');
    $street = cleanValue($row[6] ?? '');
    $house = cleanValue($row[7] ?? '');
    
    // Фильтруем некорректные значения
    if (empty($city) || $city === '=-' || empty($street) || $street === 'НЕ НАЗВАН') {
        return null;
    }
    
    // Строим адрес
    $addressParts = [];
    
    if (!empty($street)) {
        $addressParts[] = $street;
    }
    if (!empty($house)) {
        $addressParts[] = $house;
    }
    if (!empty($city)) {
        $addressParts[] = $city;
    }
    
    if (empty($addressParts)) {
        return null;
    }
    
    $address = implode(', ', $addressParts) . ', Сахалинская область, Россия';
    
    return $address;
}

function geocodeAddress($address) {
    global $geocodeCount, $lastGeocodeTime;
    
    if (empty($address)) {
        return [null, null];
    }
    
    // Пауза 1 секунда между запросами (правила OpenStreetMap)
    $currentTime = microtime(true);
    if ($currentTime - $lastGeocodeTime < 1.0) {
        usleep(1000000); // 1 секунда
    }
    
    $geocodeCount++;
    $lastGeocodeTime = microtime(true);
    
    // URL API OpenStreetMap Nominatim
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address) . "&limit=1&countrycodes=ru";
    
    // Важно: добавляем User-Agent для соблюдения правил использования
    $options = [
        'http' => [
            'header' => "User-Agent: MedicalAnalystApp/1.0 (smp-analysis@example.com)\r\n"
        ]
    ];
    $context = stream_context_create($options);
    
    try {
        error_log("Геокодируем: $address");
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Не удалось подключиться к геокодеру");
        }
        
        $data = json_decode($response, true);
        
        if (!empty($data) && is_array($data) && count($data) > 0) {
            $longitude = (float)$data[0]['lon'];
            $latitude = (float)$data[0]['lat'];
            
            // Проверяем что координаты в разумных пределах для Сахалина
            if ($longitude > 140 && $longitude < 150 && $latitude > 45 && $latitude < 50) {
                error_log("✅ Успешно геокодировано: $longitude, $latitude");
                return [$longitude, $latitude];
            } else {
                error_log("❌ Координаты вне диапазона Сахалина: $longitude, $latitude");
            }
        } else {
            error_log("❌ Адрес не найден: $address");
        }
        
    } catch (Exception $e) {
        error_log("❌ Ошибка геокодирования для '$address': " . $e->getMessage());
    }
    
    return [null, null];
}

// ★★★ ДОБАВЛЯЕМ ФУНКЦИЮ ОПРЕДЕЛЕНИЯ ИНТЕНСИВНОСТИ ★★★
// ★★★ АВТОМАТИЧЕСКОЕ РАСПРЕДЕЛЕНИЕ ПО ЧАСТОТЕ ★★★
function getCallIntensity($povd) {
    if (empty($povd)) return 'low';
    
    $povd = strtoupper(trim($povd));
    
    // Всегда высокая интенсивность для этих кодов
    $always_high = ['91!', '92!', '06С', '06C', '15Я', '15Y'];
    
    if (in_array($povd, $always_high)) {
        return 'high';
    }
    
    // Для остальных - распределяем по первой цифре
    if (is_numeric($povd[0] ?? '')) {
        $first_digit = intval($povd[0]);
        
        if ($first_digit >= 8) {
            return 'high';      // 80-99 - высокая
        } elseif ($first_digit >= 4) {
            return 'medium';    // 40-79 - средняя
        } else {
            return 'low';       // 00-39 - низкая
        }
    }
    
    return 'low';
}

function getSectorCoordinates($sector, $index) {
    // Базовые координаты центров секторов Южно-Сахалинска
    $sectors = [
        1 => [142.734, 46.963], // Центральный
        2 => [142.726, 46.953], // Южный  
        3 => [142.745, 46.953], // Восточный
        4 => [142.720, 46.968], // Западный
        5 => [142.740, 46.968], // Северный
        6 => [142.730, 46.958], // Центр
        7 => [142.750, 46.958], // Северо-Восток
        8 => [142.728, 46.948], // Юго-Запад
    ];
    
    $center = $sectors[$sector] ?? [142.7360, 46.9587];
    
    // УНИКАЛЬНОЕ распределение для каждой записи
    $angle = ($index % 8) * (2 * pi() / 8);     // 8 направлений
    $distance = (($index % 4) + 1) * 0.003;     // 4 расстояния
    
    $lon = $center[0] + cos($angle) * $distance;
    $lat = $center[1] + sin($angle) * $distance;
    
    return [$lon, $lat];
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['file']['tmp_name'];
    $original_name = $_FILES['file']['name'];

    try {
        $database = new Database();
        $db = $database->getConnection();

        // Создаем таблицу
        $tableResult = createAmbulanceTable($db);

        // Загружаем Excel файл
        $spreadsheet = IOFactory::load($tmp_name);
        $worksheet = $spreadsheet->getActiveSheet();

        // Получаем все данные
        $data = $worksheet->toArray();

        // Пропускаем заголовок (первую строку)
        array_shift($data);

        $successCount = 0;
        $errorCount = 0;
        $totalRows = count($data);

        // ★★★ ОБНОВЛЯЕМ SQL ЗАПРОС - ДОБАВЛЯЕМ intensity ★★★
        $sql = "INSERT INTO ambulance_calls (
            numv, ngod, prty, sect, rjon, city, ulic, dom, mest, povd, vozr, pol, povt, prof, 
            smpt, stan, dprm, tprm, wday, line, rezl, sgsp, rgsp, kuda, ds1, ds2, trav, alk, 
            numb, smpb, stbr, stbb, prfb, ncar, rcod, tabn, tab2, tab3, tab4, vr51, d201, 
            dsp1, dsp2, dspp, dsp3, kakp, tper, vyez, przd, tgsp, tsta, tisp, tvzv, kilo, 
            poli, socs, mrab, pri2, inf6, dshs, ferr, expo, mkb, smpp, subrf, kladv, kladp, 
            longitude, latitude, call_date, call_time, intensity
        ) VALUES (
            :numv, :ngod, :prty, :sect, :rjon, :city, :ulic, :dom, :mest, :povd, :vozr, :pol, :povt, :prof,
            :smpt, :stan, :dprm, :tprm, :wday, :line, :rezl, :sgsp, :rgsp, :kuda, :ds1, :ds2, :trav, :alk,
            :numb, :smpb, :stbr, :stbb, :prfb, :ncar, :rcod, :tabn, :tab2, :tab3, :tab4, :vr51, :d201,
            :dsp1, :dsp2, :dspp, :dsp3, :kakp, :tper, :vyez, :przd, :tgsp, :tsta, :tisp, :tvzv, :kilo,
            :poli, :socs, :mrab, :pri2, :inf6, :dshs, :ferr, :expo, :mkb, :smpp, :subrf, :kladv, :kladp,
            :longitude, :latitude, :call_date, :call_time, :intensity
        )";

        $stmt = $db->prepare($sql);

        // Начинаем транзакцию для ускорения вставки
        $db->beginTransaction();

        $processed = 0;
        foreach ($data as $row) {
            $processed++;

            // Пропускаем пустые строки
            if (empty(array_filter($row)))
                continue;

            try {
                // ★★★ ВСЕГДА используем уникальные координаты на основе сектора и номера записи ★★★
                $sector = is_numeric($row[3] ?? '') ? intval($row[3]) : 1;
                list($longitude, $latitude) = getSectorCoordinates($sector, $processed);

                // ★★★ ДОБАВЛЯЕМ ОПРЕДЕЛЕНИЕ ИНТЕНСИВНОСТИ ★★★
                $povd = cleanValue($row[9] ?? '');
                $intensity = getCallIntensity($povd);

                // Парсим дату и время из dprm и tprm (колонки 16 и 17, индексы 15 и 16)
                $callDate = null;
                $callTime = null;

                if (count($row) > 16) {
                    $callDate = parseExcelDate($row[15] ?? '');
                    $callTime = parseExcelTime($row[16] ?? '');
                }

                // ★★★ ДОБАВЛЯЕМ ПАРАМЕТР :intensity В EXECUTE ★★★
                $stmt->execute([
                    ':numv' => is_numeric($row[0] ?? '') ? intval($row[0]) : null,
                    ':ngod' => is_numeric($row[1] ?? '') ? intval($row[1]) : null,
                    ':prty' => is_numeric($row[2] ?? '') ? intval($row[2]) : null,
                    ':sect' => is_numeric($row[3] ?? '') ? intval($row[3]) : null,
                    ':rjon' => is_numeric($row[4] ?? '') ? intval($row[4]) : null,
                    ':city' => cleanValue($row[5] ?? null),
                    ':ulic' => cleanValue($row[6] ?? null),
                    ':dom' => cleanValue($row[7] ?? null),
                    ':mest' => is_numeric($row[8] ?? '') ? intval($row[8]) : null,
                    ':povd' => cleanValue($row[9] ?? null),
                    ':vozr' => is_numeric($row[10] ?? '') ? intval($row[10]) : null,
                    ':pol' => cleanValue($row[11] ?? null),
                    ':povt' => is_numeric($row[12] ?? '') ? intval($row[12]) : null,
                    ':prof' => cleanValue($row[13] ?? null),
                    ':smpt' => is_numeric($row[14] ?? '') ? intval($row[14]) : null,
                    ':stan' => is_numeric($row[15] ?? '') ? intval($row[15]) : null,
                    ':dprm' => cleanValue($row[16] ?? null),
                    ':tprm' => $callTime,
                    ':wday' => is_numeric($row[18] ?? '') ? intval($row[18]) : null,
                    ':line' => is_numeric($row[19] ?? '') ? intval($row[19]) : null,
                    ':rezl' => is_numeric($row[20] ?? '') ? intval($row[20]) : null,
                    ':sgsp' => cleanValue($row[21] ?? null),
                    ':rgsp' => cleanValue($row[22] ?? null),
                    ':kuda' => cleanValue($row[23] ?? null),
                    ':ds1' => cleanValue($row[24] ?? null),
                    ':ds2' => cleanValue($row[25] ?? null),
                    ':trav' => cleanValue($row[26] ?? null),
                    ':alk' => cleanValue($row[27] ?? null),
                    ':numb' => is_numeric($row[28] ?? '') ? intval($row[28]) : null,
                    ':smpb' => is_numeric($row[29] ?? '') ? intval($row[29]) : null,
                    ':stbr' => is_numeric($row[30] ?? '') ? intval($row[30]) : null,
                    ':stbb' => is_numeric($row[31] ?? '') ? intval($row[31]) : null,
                    ':prfb' => cleanValue($row[32] ?? null),
                    ':ncar' => cleanValue($row[33] ?? null),
                    ':rcod' => cleanValue($row[34] ?? null),
                    ':tabn' => is_numeric($row[35] ?? '') ? intval($row[35]) : null,
                    ':tab2' => is_numeric($row[36] ?? '') ? intval($row[36]) : null,
                    ':tab3' => is_numeric($row[37] ?? '') ? intval($row[37]) : null,
                    ':tab4' => is_numeric($row[38] ?? '') ? intval($row[38]) : null,
                    ':vr51' => is_numeric($row[39] ?? '') ? intval($row[39]) : null,
                    ':d201' => is_numeric($row[40] ?? '') ? intval($row[40]) : null,
                    ':dsp1' => is_numeric($row[41] ?? '') ? intval($row[41]) : null,
                    ':dsp2' => is_numeric($row[42] ?? '') ? intval($row[42]) : null,
                    ':dspp' => is_numeric($row[43] ?? '') ? intval($row[43]) : null,
                    ':dsp3' => is_numeric($row[44] ?? '') ? intval($row[44]) : null,
                    ':kakp' => is_numeric($row[45] ?? '') ? intval($row[45]) : null,
                    ':tper' => parseExcelTime($row[46] ?? ''),
                    ':vyez' => parseExcelTime($row[47] ?? ''),
                    ':przd' => parseExcelTime($row[48] ?? ''),
                    ':tgsp' => parseExcelTime($row[49] ?? ''),
                    ':tsta' => parseExcelTime($row[50] ?? ''),
                    ':tisp' => parseExcelTime($row[51] ?? ''),
                    ':tvzv' => parseExcelTime($row[52] ?? ''),
                    ':kilo' => is_numeric($row[53] ?? '') ? intval($row[53]) : null,
                    ':poli' => is_numeric($row[54] ?? '') ? intval($row[54]) : null,
                    ':socs' => is_numeric($row[55] ?? '') ? intval($row[55]) : null,
                    ':mrab' => is_numeric($row[56] ?? '') ? intval($row[56]) : null,
                    ':pri2' => is_numeric($row[57] ?? '') ? intval($row[57]) : null,
                    ':inf6' => is_numeric($row[58] ?? '') ? intval($row[58]) : null,
                    ':dshs' => is_numeric($row[59] ?? '') ? intval($row[59]) : null,
                    ':ferr' => is_numeric($row[60] ?? '') ? intval($row[60]) : null,
                    ':expo' => is_numeric($row[61] ?? '') ? intval($row[61]) : null,
                    ':mkb' => cleanValue($row[62] ?? null),
                    ':smpp' => is_numeric($row[63] ?? '') ? intval($row[63]) : null,
                    ':subrf' => is_numeric($row[64] ?? '') ? intval($row[64]) : null,
                    ':kladv' => cleanValue($row[65] ?? null),
                    ':kladp' => cleanValue($row[66] ?? null),
                    ':longitude' => $longitude,
                    ':latitude' => $latitude,
                    ':call_date' => $callDate,
                    ':call_time' => $callTime,
                    ':intensity' => $intensity
                ]);

                $successCount++;

                // Выводим прогресс каждые 1000 строк
                if ($successCount % 1000 === 0) {
                    error_log("Обработано $successCount из $totalRows строк");
                }

            } catch (Exception $e) {
                $errorCount++;
                // Логируем ошибку, но продолжаем обработку
                error_log("Ошибка в строке $processed: " . $e->getMessage());
            }
        }

        // Завершаем транзакцию
        $db->commit();

        // Статистика по координатам
        $coordStats = $db->query("SELECT COUNT(*) as with_coords FROM ambulance_calls WHERE longitude IS NOT NULL AND latitude IS NOT NULL")->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => "Файл $original_name обработан. Успешно: $successCount записей, Ошибок: $errorCount. $tableResult. Геокодировано запросов: $geocodeCount",
            'imported' => $successCount,
            'errors' => $errorCount,
            'with_coordinates' => $coordStats['with_coords'],
            'total_rows' => $totalRows,
            'geocoded_requests' => $geocodeCount
        ]);

    } catch (Exception $e) {
        // Откатываем транзакцию в случае ошибки
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при обработке файла: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка загрузки файла: ' . $_FILES['file']['error']
    ]);
}
?>