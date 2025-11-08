<?php
// find_excel_coords.php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "<h1>🔍 Поиск координат в Excel файле</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];
    
    echo "<h2>Анализ файла: $filename</h2>";
    
    try {
        $spreadsheet = IOFactory::load($tmp_name);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Получим ВСЕ данные
        $data = $worksheet->toArray();
        
        echo "<h3>📊 Первые 5 строк всех колонок:</h3>";
        
        // Проверим первые 5 строк ВСЕХ колонок
        for ($i = 0; $i < min(5, count($data)); $i++) {
            echo "<div style='border: 2px solid #ccc; margin: 10px 0; padding: 10px;'>";
            echo "<h4>📝 Строка $i:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background: #f0f0f0;'><th>Колонка</th><th>Значение</th><th>Тип</th><th>Примечание</th></tr>";
            
            foreach ($data[$i] as $colIndex => $value) {
                $value = trim($value ?? '');
                if ($value !== '') {
                    $type = is_numeric($value) ? '🔢 ЧИСЛО' : '📄 ТЕКСТ';
                    $note = '';
                    $style = '';
                    
                    // Проверяем потенциальные координаты
                    if (is_numeric($value)) {
                        $num_value = floatval($value);
                        if ($num_value > 140 && $num_value < 150) {
                            $note = '🚨 ВОЗМОЖНА ДОЛГОТА Сахалина';
                            $style = 'background: #FFB6C1;'; // Красный
                        } elseif ($num_value > 45 && $num_value < 50) {
                            $note = '🚨 ВОЗМОЖНА ШИРОТА Сахалина';
                            $style = 'background: #87CEFA;'; // Синий
                        } elseif ($num_value > 100) {
                            $note = 'Большое число';
                            $style = 'background: #FFFFE0;'; // Желтый
                        }
                    }
                    
                    echo "<tr style='$style'>";
                    echo "<td style='text-align: center;'><strong>$colIndex</strong></td>";
                    echo "<td style='font-weight: bold;'>" . htmlspecialchars($value) . "</td>";
                    echo "<td>$type</td>";
                    echo "<td>$note</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
            echo "</div>";
        }
        
        // Проверим специально колонки 67 и 68 (LONG, LATI)
        echo "<h3>🎯 Проверка колонок 67 (LONG) и 68 (LATI):</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'><th>Строка</th><th>Колонка 67 (LONG)</th><th>Колонка 68 (LATI)</th></tr>";
        
        for ($i = 0; $i < min(10, count($data)); $i++) {
            $long = $data[$i][67] ?? 'ПУСТО';
            $lati = $data[$i][68] ?? 'ПУСТО';
            echo "<tr>";
            echo "<td>$i</td>";
            echo "<td>" . htmlspecialchars($long) . "</td>";
            echo "<td>" . htmlspecialchars($lati) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<div style='color: red;'><strong>Ошибка:</strong> " . $e->getMessage() . "</div>";
    }
    
    echo '<br><a href="find_excel_coords.php">🔄 Загрузить другой файл</a>';
    
} else {
    // Показываем форму загрузки
    echo "
    <h2>📤 Загрузите ваш Excel файл для анализа</h2>
    <form method='post' enctype='multipart/form-data' style='border: 2px dashed #ccc; padding: 20px; text-align: center;'>
        <input type='file' name='file' accept='.xlsx,.xls' style='margin: 10px 0; padding: 10px; border: 1px solid #ccc; width: 80%;'><br>
        <button type='submit' style='background: #4CAF50; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px;'>
            🚀 Анализировать Excel файл
        </button>
    </form>
    
    <div style='margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2196F3;'>
        <strong>Что ищем:</strong><br>
        • 🔢 Числа в диапазоне <strong>140-150</strong> (долготы Сахалина)<br>
        • 🔢 Числа в диапазоне <strong>45-50</strong> (широты Сахалина)<br>
        • 📍 Колонки с координатами в любом формате
    </div>
    ";
}
?>